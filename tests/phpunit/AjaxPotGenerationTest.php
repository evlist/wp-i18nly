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
	 * Renders edit-screen script that triggers generation AJAX request.
	 *
	 * @return void
	 */
	public function test_render_translation_edit_pot_generation_script_outputs_ajax_call() {
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

		$_GET['post']   = '42';
		$_GET['action'] = 'edit';

		$page = new I18nly_Admin_Page();

		ob_start();
		$page->render_translation_edit_pot_generation_script();
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'i18nly_generate_translation_pot', $html );
		$this->assertStringContainsString( 'translation_id=42', $html );
		$this->assertStringContainsString( 'admin-ajax.php', $html );

		unset( $_GET['post'], $_GET['action'] );
	}

	/**
	 * Generates temporary POT file through AJAX endpoint.
	 *
	 * @return void
	 */
	public function test_ajax_generate_translation_pot_returns_success_and_file_path() {
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
		$this->assertIsString( $response['data']['pot_file_path'] );
		$this->assertFileExists( $response['data']['pot_file_path'] );

		$storage = new I18nly_Temporary_Storage();
		$storage->cleanup_translation_workspace( 42 );

		unset( $_POST['translation_id'], $_POST['nonce'] );
	}
}

// phpcs:enable
