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
	 * Registers one translation into Polish and a repository returning one translated row.
	 *
	 * @return TranslationExportController
	 */
	private function controller() {
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

		$repository = new class() extends TranslationResourceRepository {
			/**
			 * Does not need storage.
			 */
			public function __construct() {}

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
				return array(
					array(
						'msgctxt'      => '',
						'msgid'        => 'Hello',
						'msgid_plural' => '',
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
						'translations' => array(
							array(
								'form_index'  => 0,
								'translation' => 'Brouillon',
								'status'      => 'draft_ai',
							),
						),
					),
				);
			}
		};

		return new TranslationExportController( $repository );
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
}
