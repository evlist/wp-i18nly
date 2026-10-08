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
use PHPUnit\Framework\TestCase;
use WP_I18nly\Admin\TranslationExportController;
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
		$file = $this->controller()->build_file( 7, 'mo' );

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
		$file = $this->controller()->build_file( 7, 'po' );

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
	 * The download URL carries the action, the format and a nonce bound to the translation.
	 *
	 * @return void
	 */
	public function test_download_url_is_signed() {
		$url = $this->controller()->get_download_url( 7, 'mo' );

		$this->assertStringContainsString( 'action=i18nly_export_translation', $url );
		$this->assertStringContainsString( 'translation_id=7', $url );
		$this->assertStringContainsString( 'format=mo', $url );
		$this->assertStringContainsString( 'nonce-i18nly_export_translation_7', $url );
	}
}
