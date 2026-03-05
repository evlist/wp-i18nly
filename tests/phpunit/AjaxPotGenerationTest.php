<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * AJAX POT generation tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

// phpcs:disable WordPress.WP.AlternativeFunctions

/**
 * Tests edit-screen triggered POT generation AJAX flow.
 */
class AjaxPotGenerationTest extends TestCase {
	/**
	 * Enqueues edit-screen script and localizes AJAX config.
	 *
	 * @return void
	 */
	public function test_render_translation_edit_pot_generation_script_enqueues_external_script() {
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'               => 42,
					'source_slug'      => 'akismet/akismet.php',
					'target_language'  => 'fr_FR',
					'created_at_gmt'   => '2026-03-02 11:15:00',
					'created_at_local' => '2026-03-02 12:15:00',
				),
			)
		);
		i18nly_test_reset_enqueued_scripts();

		$_GET['post']   = '42';
		$_GET['action'] = 'edit';

		$page = new class() extends I18nly_Admin_Page {
			/**
			 * Returns one query parameter for CLI tests.
			 *
			 * @param string $key Query parameter key.
			 * @return string
			 */
			protected function get_query_parameter( $key ) {
				return i18nly_test_get_query_parameter( $key );
			}
		};

		ob_start();
		$page->render_translation_edit_pot_generation_script();
		$html    = ob_get_clean();
		$scripts = i18nly_test_get_enqueued_scripts();
		$styles  = i18nly_test_get_enqueued_styles();
		$inline  = i18nly_test_get_inline_scripts();

		$this->assertIsString( $html );
		$this->assertSame( '', $html );
		$this->assertArrayHasKey( 'i18nly-translation-edit-style', $styles );
		$this->assertStringContainsString( 'assets/css/translation-edit.css', $styles['i18nly-translation-edit-style']['src'] );
		$this->assertArrayHasKey( 'i18nly-translation-edit', $scripts );
		$this->assertStringContainsString( 'assets/js/translation-edit.js', $scripts['i18nly-translation-edit']['src'] );
		$this->assertArrayHasKey( 'i18nly-translation-edit', $inline );
		$this->assertNotEmpty( $inline['i18nly-translation-edit'] );
		$this->assertStringContainsString( 'window.i18nlyTranslationEditConfig', $inline['i18nly-translation-edit'][0]['data'] );
		$this->assertStringContainsString( 'i18nly_generate_translation_pot', $inline['i18nly-translation-edit'][0]['data'] );
		$this->assertStringContainsString( 'i18nly_get_translation_entries_table', $inline['i18nly-translation-edit'][0]['data'] );
		$this->assertStringContainsString( 'admin-ajax.php', $inline['i18nly-translation-edit'][0]['data'] );

		unset( $_GET['post'], $_GET['action'] );
	}

	/**
	 * Generates temporary POT file through AJAX endpoint.
	 *
	 * @return void
	 */
	public function test_ajax_generate_translation_pot_returns_success_and_file_path() {
		i18nly_test_set_can_manage_options( true );
		i18nly_test_set_plugins(
			array(
				'akismet/akismet.php' => array(
					'Name'      => 'Akismet Anti-Spam',
					'Version'   => '5.3.7',
					'PluginURI' => 'https://example.test/akismet',
				),
			)
		);
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'               => 42,
					'source_slug'      => 'akismet/akismet.php',
					'target_language'  => 'fr_FR',
					'created_at_gmt'   => '2026-03-02 11:15:00',
					'created_at_local' => '2026-03-02 12:15:00',
				),
			)
		);
		i18nly_test_reset_last_json_response();

		$_POST['translation_id'] = '42';
		$_POST['nonce']          = 'nonce-i18nly_generate_translation_pot_42';

		$page = new I18nly_Admin_Page();
		$page->ajax_generate_translation_pot();

		$response = i18nly_test_get_last_json_response();

		$this->assertArrayHasKey( 'success', $response );
		$this->assertSame( true, $response['success'] );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertSame( 42, $response['data']['translation_id'] );
		$this->assertArrayHasKey( 'entries_count', $response['data'] );
		$this->assertSame( 0, $response['data']['entries_count'] );
		$this->assertArrayHasKey( 'import_summary', $response['data'] );
		$this->assertArrayHasKey( 'catalog_id', $response['data']['import_summary'] );
		$this->assertArrayHasKey( 'inserted', $response['data']['import_summary'] );
		$this->assertArrayHasKey( 'updated', $response['data']['import_summary'] );
		$this->assertArrayHasKey( 'unchanged', $response['data']['import_summary'] );
		$this->assertIsString( $response['data']['pot_file_path'] );
		$this->assertFileExists( $response['data']['pot_file_path'] );

		$content = file_get_contents( $response['data']['pot_file_path'] );
		$this->assertIsString( $content );
		$this->assertStringContainsString( '"Project-Id-Version: Akismet Anti-Spam 5.3.7\\n"', $content );
		$this->assertStringContainsString( '"Report-Msgid-Bugs-To: https://example.test/akismet\\n"', $content );

		$storage = new I18nly_Temporary_Storage();
		$storage->cleanup_translation_workspace( 42 );

		unset( $_POST['translation_id'], $_POST['nonce'] );
	}

	/**
	 * Returns source entries table HTML through AJAX endpoint.
	 *
	 * @return void
	 */
	public function test_ajax_get_translation_entries_table_returns_html() {
		i18nly_test_set_can_manage_options( true );
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'               => 42,
					'source_slug'      => 'akismet/akismet.php',
					'target_language'  => 'fr_FR',
					'created_at_gmt'   => '2026-03-02 11:15:00',
					'created_at_local' => '2026-03-02 12:15:00',
				),
			)
		);
		i18nly_test_reset_last_json_response();

		$_POST['translation_id'] = '42';
		$_POST['nonce']          = 'nonce-i18nly_get_translation_entries_table_42';

		$page = new class() extends I18nly_Admin_Page {
			/**
			 * Returns deterministic source entries in tests.
			 *
			 * @param int    $translation_id Translation ID.
			 * @param string $source_slug Source slug.
			 * @return array<int, array<string, mixed>>
			 */
			protected function get_translation_source_entries( $translation_id, $source_slug ) {
				unset( $translation_id, $source_slug );

				return array(
					array(
						'source_entry_id'    => 99,
						'msgctxt'            => 'email',
						'msgid'              => 'Welcome',
						'translation'        => 'Bienvenue',
						'msgid_plural'       => 'Welcomes',
						'translation_plural' => 'Bienvenues',
						'status'             => 'active',
					),
				);
			}
		};

		$page->ajax_get_translation_entries_table();

		$response = i18nly_test_get_last_json_response();

		$this->assertArrayHasKey( 'success', $response );
		$this->assertSame( true, $response['success'] );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertSame( 42, $response['data']['translation_id'] );
		$this->assertSame( 1, $response['data']['entries_count'] );
		$this->assertIsString( $response['data']['html'] );
		$this->assertStringContainsString( 'wp-list-table widefat fixed striped', $response['data']['html'] );
		$this->assertStringContainsString( 'Welcome', $response['data']['html'] );
		$this->assertStringContainsString( 'Translation', $response['data']['html'] );
		$this->assertStringContainsString( 'i18nly-form-marker', $response['data']['html'] );
		$this->assertStringContainsString( 'Singular form', $response['data']['html'] );
		$this->assertStringContainsString( 'Plural form', $response['data']['html'] );
		$this->assertStringContainsString( 'data-i18nly-source-entry-id="99"', $response['data']['html'] );
		$this->assertStringContainsString( 'data-i18nly-entry-field="translation"', $response['data']['html'] );
		$this->assertStringContainsString( 'data-i18nly-entry-field="translation_plural"', $response['data']['html'] );
		$this->assertStringContainsString( 'value="Bienvenue"', $response['data']['html'] );
		$this->assertStringContainsString( 'value="Bienvenues"', $response['data']['html'] );
		$this->assertStringNotContainsString( 'name="_wpnonce"', $response['data']['html'] );
		$this->assertStringNotContainsString( 'name="_wp_http_referer"', $response['data']['html'] );
		$this->assertStringContainsString( 'active', $response['data']['html'] );

		unset( $_POST['translation_id'], $_POST['nonce'] );
	}
}

// phpcs:enable
