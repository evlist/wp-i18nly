<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation export controller tests.
 *
 * @package I18nly
 */

use Gettext\Loader\MoLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/class-i18nly-test-memory-filesystem.php';
use WP_I18nly\Admin\TranslationExportController;
use WP_I18nly\Export\TranslationInstaller;
use WP_I18nly\LinguisticResources\TranslationResourceRepository;

/**
 * Tests the building of the downloaded files.
 */
class TranslationExportControllerTest extends TestCase {
	/**
	 * Registers one translation into Polish and a repository returning two translated rows.
	 *
	 * @param bool                 $blank Whether the translations of the rows are empty.
	 * @param TranslationInstaller $installer Optional installer.
	 * @return TranslationExportController
	 */
	private function controller( $blank = false, TranslationInstaller $installer = null ) {
		global $i18nly_test_plugins;

		$i18nly_test_plugins = array(
			'sample/sample.php' => array(
				'Name'    => 'Sample',
				'Version' => '2.0',
			),
		);

		i18nly_test_set_translations_rows(
			array(
				array(
					'id'              => 7,
					'source_slug'     => 'sample/sample.php',
					'target_language' => 'pl_PL',
				),
				array(
					'id'              => 8,
					'source_slug'     => '',
					'target_language' => '',
				),
			)
		);

		$repository = new class( $blank ) extends TranslationResourceRepository {
			/**
			 * Whether the translations are empty.
			 *
			 * @var bool
			 */
			private $blank;

			/**
			 * Does not need storage.
			 *
			 * @param bool $blank Whether the translations are empty.
			 */
			public function __construct( $blank = false ) {
				$this->blank = $blank;
			}

			/**
			 * Returns one translated row.
			 *
			 * @param int    $translation_id Translation ID.
			 * @param string $source_slug Source slug.
			 * @param int    $limit Limit.
			 * @param int    $plural_forms_count Number of plural forms.
			 * @return array<int, array<string, mixed>>
			 */
			public function list_translation_rows( $translation_id, $source_slug, $limit, $plural_forms_count ) {
				$rows = array(
					array(
						'msgctxt'      => '',
						'msgid'        => 'Hello',
						'msgid_plural' => '',
						'references'   => array(
							'includes/a.php'  => array( 4 ),
							'assets/js/app.js' => array( 7 ),
						),
						'translations' => array(
							array(
								'form_index'  => 0,
								'translation' => 'Cześć <b>%s</b>',
								'status'      => 'validated',
							),
						),
					),
					array(
						'msgctxt'      => '',
						'msgid'        => 'Draft text',
						'msgid_plural' => '',
						'references'   => array( 'assets/js/app.js' => array( 9 ) ),
						'translations' => array(
							array(
								'form_index'  => 0,
								'translation' => 'Brouillon',
								'status'      => 'draft_ai',
							),
						),
					),
				);

				if ( $this->blank ) {
					foreach ( $rows as $index => $row ) {
						$rows[ $index ]['translations'][0]['translation'] = '';
					}
				}

				return $rows;
			}
		};

		return new TranslationExportController( $repository, $installer );
	}

	/**
	 * The MO file is named like WordPress expects and carries the plural header.
	 *
	 * @return void
	 */
	public function test_builds_the_mo_file_with_plugin_data() {
		$file = $this->controller()->build_file( 7, 'mo', true );

		$this->assertSame( 'sample-pl_PL.mo', $file['name'] );

		$loaded = ( new MoLoader() )->loadString( $file['contents'] );

		$this->assertSame( 'Cześć <b>%s</b>', $loaded->find( null, 'Hello' )->getTranslation() );
		$this->assertSame( 3, $loaded->getHeaders()->getPluralForm()[0] );
		$this->assertSame( 'pl_PL', $loaded->getHeaders()->getLanguage() );
	}

	/**
	 * The PO file is a text file.
	 *
	 * @return void
	 */
	public function test_builds_the_po_file() {
		$file = $this->controller()->build_file( 7, 'po', true );

		$this->assertSame( 'sample-pl_PL.po', $file['name'] );
		$this->assertStringContainsString( 'msgstr "Cześć <b>%s</b>"', $file['contents'] );
	}

	/**
	 * Unknown formats, translations without identity and unknown translations are refused.
	 *
	 * @return void
	 */
	public function test_refuses_invalid_requests() {
		$controller = $this->controller();

		$this->assertNull( $controller->build_file( 7, 'php' ) );
		$this->assertNull( $controller->build_file( 8, 'mo' ) );
		$this->assertNull( $controller->build_file( 999, 'mo' ) );
	}

	/**
	 * Installing writes the MO and PO files named after the plugin text domain and locale.
	 *
	 * @return void
	 */
	public function test_install_writes_the_files() {
		$filesystem = new I18nly_Test_Memory_Filesystem();
		$controller = $this->controller();

		$result = $controller->install( 7, true, new TranslationInstaller( $filesystem, '/lang/plugins' ) );

		$this->assertSame( TranslationInstaller::INSTALLED, $result );
		$this->assertArrayHasKey( '/lang/plugins/sample-pl_PL.mo', $filesystem->files );
		$this->assertArrayHasKey( '/lang/plugins/sample-pl_PL.po', $filesystem->files );
		$this->assertStringContainsString( 'X-Generator: I18nly', $filesystem->files['/lang/plugins/sample-pl_PL.mo'] );
	}

	/**
	 * A translation without identity cannot be installed.
	 *
	 * @return void
	 */
	public function test_install_refuses_a_translation_without_identity() {
		$filesystem = new I18nly_Test_Memory_Filesystem();

		$this->assertSame( 'not_exportable', $this->controller()->install( 8, false, new TranslationInstaller( $filesystem, '/lang/plugins' ) ) );
		$this->assertSame( array(), $filesystem->files );
	}

	/**
	 * The text domain declared by the plugin header wins over the folder name.
	 *
	 * @return void
	 */
	public function test_file_name_uses_the_declared_text_domain() {
		global $i18nly_test_plugins;

		$controller = $this->controller();

		$i18nly_test_plugins['sample/sample.php']['TextDomain'] = 'sample-domain';

		$this->assertSame( 'sample-domain-pl_PL.mo', $controller->build_file( 7, 'mo', true )['name'] );
	}

	/**
	 * Doubtful translations are left out unless the administrator chose to include them.
	 *
	 * @return void
	 */
	public function test_doubtful_translations_are_left_out_by_default() {
		$controller = $this->controller();

		$default = ( new MoLoader() )->loadString( $controller->build_file( 7, 'mo' )['contents'] );
		$all     = ( new MoLoader() )->loadString( $controller->build_file( 7, 'mo', true )['contents'] );

		$this->assertNull( $default->find( null, 'Draft text' ) );
		$this->assertNotNull( $default->find( null, 'Hello' ) );
		$this->assertNotNull( $all->find( null, 'Draft text' ) );
		$this->assertSame( 1, $controller->count_unvalidated( 7 ) );
	}

	/**
	 * A choice is required when there are doubtful translations, and only then.
	 *
	 * @param string    $choice Posted choice.
	 * @param bool|null $expected Expected result.
	 * @return void
	 */
	#[DataProvider( 'choices' )]
	public function test_choice_is_required_when_there_are_doubtful_translations( $choice, $expected ) {
		$method = new ReflectionMethod( TranslationExportController::class, 'resolve_choice' );
		$method->setAccessible( true );

		$this->assertSame( $expected, $method->invoke( $this->controller(), 7, $choice ) );
	}

	/**
	 * Posted choices.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public static function choices() {
		return array(
			'no choice'      => array( '', null ),
			'unknown choice' => array( 'maybe', null ),
			'exclude'        => array( 'exclude', false ),
			'include'        => array( 'include', true ),
		);
	}

	/**
	 * Script files are built from the references, honoring the choice about doubtful translations.
	 *
	 * @return void
	 */
	public function test_builds_script_files_from_references() {
		$controller = $this->controller();
		$name       = 'sample-pl_PL-' . md5( 'assets/js/app.js' ) . '.json';

		$validated = json_decode( $controller->build_script_files( 7 )[ $name ], true );
		$all       = json_decode( $controller->build_script_files( 7, true )[ $name ], true );

		$this->assertArrayHasKey( 'Hello', $validated['locale_data']['sample'] );
		$this->assertArrayNotHasKey( 'Draft text', $validated['locale_data']['sample'] );
		$this->assertArrayHasKey( 'Draft text', $all['locale_data']['sample'] );
		$this->assertSame( array(), $controller->build_script_files( 8 ) );
	}

	/**
	 * The download is one archive with the MO, the PO and the JSON files of the scripts.
	 *
	 * @return void
	 */
	public function test_download_is_an_archive_with_every_file() {
		$file = $this->controller()->build_file( 7, 'bundle', true );

		$this->assertSame( 'sample-pl_PL.zip', $file['name'] );
		$this->assertSame( 'application/zip', $file['mime'] );

		$path = tempnam( sys_get_temp_dir(), 'bundle' );

		file_put_contents( $path, $file['contents'] ); // phpcs:ignore

		$archive = new ZipArchive();
		$archive->open( $path );

		$names = array();

		for ( $index = 0; $index < $archive->count(); $index++ ) {
			$names[] = $archive->getNameIndex( $index );
		}

		$archive->close();
		unlink( $path ); // phpcs:ignore
		sort( $names );

		$this->assertSame( array( 'sample-pl_PL-' . md5( 'assets/js/app.js' ) . '.json', 'sample-pl_PL.mo', 'sample-pl_PL.po' ), $names );
	}

	/**
	 * Nothing is exported, downloaded or installed while no string is translated.
	 *
	 * @return void
	 */
	public function test_nothing_is_exported_without_translated_string() {
		$controller = $this->controller( true );
		$filesystem = new I18nly_Test_Memory_Filesystem();

		$this->assertSame( 0, $controller->count_translated_strings( 7 ) );
		$this->assertNull( $controller->build_file( 7, 'bundle', true ) );
		$this->assertNull( $controller->build_file( 7, 'po', true ) );
		$this->assertSame( 'not_exportable', $controller->install( 7, true, new TranslationInstaller( $filesystem, '/lang/plugins' ) ) );
		$this->assertSame( array(), $filesystem->files );
		$this->assertSame( 2, $this->controller()->count_translated_strings( 7 ) );
	}

	/**
	 * Installing also writes the script files.
	 *
	 * @return void
	 */
	public function test_install_writes_the_script_files() {
		$filesystem = new I18nly_Test_Memory_Filesystem();

		$this->controller()->install( 7, true, new TranslationInstaller( $filesystem, '/lang/plugins' ) );

		$this->assertArrayHasKey( '/lang/plugins/sample-pl_PL-' . md5( 'assets/js/app.js' ) . '.json', $filesystem->files );
	}

	/**
	 * Renders the box of a controller.
	 *
	 * @param TranslationExportController $controller Controller.
	 * @return string
	 */
	private function render_box( TranslationExportController $controller ) {
		ob_start();
		( new \WP_I18nly\Admin\UI\TranslationExportMetaBox( $controller ) )->render( (object) array( 'ID' => 7 ) );

		return ob_get_clean();
	}

	/**
	 * The box has two submit buttons of the post form, no form of its own, and no field named like the ones of WordPress.
	 *
	 * @return void
	 */
	public function test_box_has_the_save_and_install_and_save_and_download_buttons() {
		$html = $this->render_box( $this->controller() );

		$this->assertStringContainsString( 'name="i18nly_after_save" value="install"', $html );
		$this->assertStringContainsString( 'name="i18nly_after_save" value="download"', $html );
		$this->assertStringContainsString( '>Save and install on this site</button>', $html );
		$this->assertStringContainsString( '>Save and download</button>', $html );
		$this->assertStringNotContainsString( '<form', $html, 'a form cannot be nested in the form of the post' );
		$this->assertStringNotContainsString( 'name="_wpnonce"', $html );
		$this->assertStringNotContainsString( 'name="action"', $html );
		$this->assertStringNotContainsString( ' required', $html, 'Save Draft must not be blocked by the choice' );
		$this->assertStringNotContainsString( 'disabled="disabled"', $html );
	}

	/**
	 * Both buttons start disabled while nothing is saved, and the script can enable them when text is typed.
	 *
	 * @return void
	 */
	public function test_buttons_start_disabled_without_saved_string() {
		$html = $this->render_box( $this->controller( true ) );

		$this->assertSame( 2, substr_count( $html, 'disabled="disabled"' ) );
		$this->assertStringContainsString( 'data-saved="0"', $html );
		$this->assertStringContainsString( '.i18nly-translation-input', $html );
	}

	/**
	 * Runs the save hook of the controller for translation 7 with posted fields.
	 *
	 * @param TranslationExportController $controller Controller.
	 * @param array<string, string>       $fields Fields posted besides the nonce.
	 * @return string The address after the save.
	 */
	private function save( TranslationExportController $controller, array $fields ) {
		$_POST = $fields + array( '_wpnonce' => 'nonce-update-post_7' );

		$controller->handle_save_and_export( 7 );

		$_POST = array();

		return $controller->filter_redirect_location( 'https://example.test/wp-admin/post.php?post=7&action=edit&message=1' );
	}

	/**
	 * "Save and install" installs the files after the save and reports the result on the screen.
	 *
	 * @return void
	 */
	public function test_save_and_install() {
		i18nly_test_set_can_manage_options( true );

		$filesystem = new I18nly_Test_Memory_Filesystem();
		$controller = $this->controller( false, new TranslationInstaller( $filesystem, '/lang/plugins' ) );

		$location = $this->save(
			$controller,
			array(
				'i18nly_after_save'  => 'install',
				'i18nly_unvalidated' => 'include',
			)
		);

		$this->assertStringContainsString( 'i18nly_install=installed', $location );
		$this->assertArrayHasKey( '/lang/plugins/sample-pl_PL.mo', $filesystem->files );
		$this->assertStringContainsString( 'Cześć', $filesystem->files['/lang/plugins/sample-pl_PL.po'] );
		$this->assertStringContainsString( 'Brouillon', $filesystem->files['/lang/plugins/sample-pl_PL.po'], 'the doubtful translation was included' );
	}

	/**
	 * "Save and download" sends the screen to start the download, with the choice made.
	 *
	 * @return void
	 */
	public function test_save_and_download() {
		i18nly_test_set_can_manage_options( true );

		$location = $this->save(
			$this->controller(),
			array(
				'i18nly_after_save'  => 'download',
				'i18nly_unvalidated' => 'exclude',
			)
		);

		$this->assertStringContainsString( 'i18nly_download=exclude', $location );
		$this->assertStringNotContainsString( 'i18nly_install', $location );
	}

	/**
	 * Without a choice about the doubtful translations the translation is saved and the user is asked to choose.
	 *
	 * @return void
	 */
	public function test_choice_is_asked_after_the_save() {
		i18nly_test_set_can_manage_options( true );

		$filesystem = new I18nly_Test_Memory_Filesystem();
		$location   = $this->save( $this->controller( false, new TranslationInstaller( $filesystem, '/lang/plugins' ) ), array( 'i18nly_after_save' => 'install' ) );

		$this->assertStringContainsString( 'i18nly_install=confirmation_required', $location );
		$this->assertSame( array(), $filesystem->files );
	}

	/**
	 * Other saves, wrong nonces and users who cannot edit do nothing.
	 *
	 * @return void
	 */
	public function test_other_saves_do_nothing() {
		i18nly_test_set_can_manage_options( true );

		$this->assertStringNotContainsString( 'i18nly_', $this->save( $this->controller(), array() ), 'a plain save' );
		$this->assertStringNotContainsString(
			'i18nly_',
			$this->save(
				$this->controller(),
				array(
					'i18nly_after_save' => 'download',
					'i18nly_unvalidated' => 'include',
					'_wpnonce' => 'wrong',
				)
			),
			'wrong nonce'
		);
		$this->assertStringNotContainsString(
			'i18nly_',
			$this->save(
				$this->controller(),
				array(
					'i18nly_after_save' => 'format-disk',
					'i18nly_unvalidated' => 'include',
				)
			),
			'unknown action'
		);

		i18nly_test_set_can_manage_options( false );

		$this->assertStringNotContainsString(
			'i18nly_',
			$this->save(
				$this->controller(),
				array(
					'i18nly_after_save' => 'download',
					'i18nly_unvalidated' => 'include',
				)
			),
			'no capability'
		);
	}

	/**
	 * The download address carries the action, the translation, the choice and a nonce.
	 *
	 * @return void
	 */
	public function test_download_address_is_signed() {
		$url = $this->controller()->get_download_url( 7, true );

		$this->assertStringContainsString( 'action=i18nly_export_translation', $url );
		$this->assertStringContainsString( 'translation_id=7', $url );
		$this->assertStringContainsString( 'unvalidated=include', $url );
		$this->assertStringContainsString( 'nonce-i18nly_translation_files_7', $url );
		$this->assertStringNotContainsString( '&amp;', $url );
		$this->assertStringNotContainsString( '&#038;', $url );
	}

	/**
	 * The result arguments are removed from the address, so that a reload does not repeat the action.
	 *
	 * @return void
	 */
	public function test_address_is_cleaned_after_the_action() {
		global $current_screen;

		$current_screen = (object) array( 'post_type' => 'i18nly_translation' );

		ob_start();
		( new \WP_I18nly\Admin\UI\TranslationExportMetaBox( $this->controller() ) )->render_address_cleaner();
		$output = ob_get_clean();

		unset( $current_screen );

		$this->assertStringContainsString( 'replaceState', $output );
		$this->assertStringContainsString( 'i18nly_download', $output );
		$this->assertStringContainsString( 'i18nly_install', $output );
	}

	/**
	 * The edit screen starts the download in a hidden frame after the save, and only then.
	 *
	 * @return void
	 */
	public function test_screen_starts_the_download_after_the_save() {
		global $current_screen;

		i18nly_test_set_can_manage_options( true );

		$box = new \WP_I18nly\Admin\UI\TranslationExportMetaBox( $this->controller() );

		$current_screen = (object) array( 'post_type' => 'i18nly_translation' );
		$_GET           = array( 'post' => '7' );

		ob_start();
		$box->render_download_trigger();
		$this->assertSame( '', ob_get_clean(), 'no download requested' );

		$_GET['i18nly_download'] = 'include';

		ob_start();
		$box->render_download_trigger();
		$script = ob_get_clean();

		$_GET           = array();
		$current_screen = null;

		$this->assertStringContainsString( 'iframe', $script );
		$this->assertStringContainsString( 'action=i18nly_export_translation', $script );
		$this->assertStringContainsString( 'unvalidated=include', $script );
	}
}
