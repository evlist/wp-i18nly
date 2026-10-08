<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests TranslationSettingsPage settings sanitization.
 */
class TranslationSettingsPageTest extends TestCase {
	/**
	 * Resets options before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		i18nly_test_reset_options();
	}

	/**
	 * Sanitizes reserved characters to non-negative integer.
	 *
	 * @return void
	 */
	public function test_sanitize_settings_stores_reserved_characters() {
		$page = new \WP_I18nly\Admin\TranslationSettingsPage();

		$sanitized = $page->sanitize_settings(
			array(
				'deepl_api_key'            => 'my-key',
				'deepl_reserved_characters' => '1200',
			)
		);

		$this->assertSame( 'my-key', $sanitized['deepl_api_key'] );
		$this->assertSame( 1200, $sanitized['deepl_reserved_characters'] );
	}

	/**
	 * Negative reserved quota is clamped to zero.
	 *
	 * @return void
	 */
	public function test_sanitize_settings_clamps_reserved_characters_to_zero() {
		$page = new \WP_I18nly\Admin\TranslationSettingsPage();

		$sanitized = $page->sanitize_settings(
			array(
				'deepl_api_key'            => 'my-key',
				'deepl_reserved_characters' => -50,
			)
		);

		$this->assertSame( 0, $sanitized['deepl_reserved_characters'] );
	}

	/**
	 * Returns zero when no reserved quota has been saved.
	 *
	 * @return void
	 */
	public function test_get_saved_reserved_characters_defaults_to_zero() {
		$page = new \WP_I18nly\Admin\TranslationSettingsPage();

		$this->assertSame( 0, $page->get_saved_reserved_characters() );
	}

	/**
	 * Preserves saved key when incoming key is empty.
	 *
	 * @return void
	 */
	public function test_sanitize_settings_preserves_saved_key_when_input_empty() {
		update_option(
			'i18nly_translation_settings',
			array(
				'deepl_api_key'            => 'stored-key',
				'deepl_reserved_characters' => 20,
			)
		);

		$page = new \WP_I18nly\Admin\TranslationSettingsPage();

		$sanitized = $page->sanitize_settings(
			array(
				'deepl_api_key'            => '',
				'deepl_reserved_characters' => 25,
			)
		);

		$this->assertSame( 'stored-key', $sanitized['deepl_api_key'] );
		$this->assertSame( 25, $sanitized['deepl_reserved_characters'] );
	}

	/**
	 * The delete-on-uninstall flag is stored as 0 or 1 and defaults to 0.
	 *
	 * @return void
	 */
	public function test_delete_data_flag_is_normalized_and_off_by_default() {
		$page = new \WP_I18nly\Admin\TranslationSettingsPage();

		$this->assertSame( 0, $page->sanitize_settings( array() )['delete_data_on_uninstall'] );
		$this->assertSame( 0, $page->sanitize_settings( array( 'delete_data_on_uninstall' => '0' ) )['delete_data_on_uninstall'] );
		$this->assertSame( 1, $page->sanitize_settings( array( 'delete_data_on_uninstall' => '1' ) )['delete_data_on_uninstall'] );
	}

	/**
	 * Without the constant, the saved key is used.
	 *
	 * @return void
	 */
	public function test_saved_key_is_used_without_constant() {
		update_option( 'i18nly_translation_settings', array( 'deepl_api_key' => 'saved-key' ) );

		$page = new \WP_I18nly\Admin\TranslationSettingsPage();

		$this->assertFalse( $page->is_api_key_defined_by_constant() );
		$this->assertSame( 'saved-key', $page->get_saved_api_key() );
	}

	/**
	 * The constant takes precedence and is never copied into the option.
	 *
	 * @return void
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_constant_takes_precedence_and_is_not_stored() {
		define( 'I18NLY_DEEPL_API_KEY', ' constant-key ' );
		update_option( 'i18nly_translation_settings', array( 'deepl_api_key' => 'saved-key' ) );

		$page = new \WP_I18nly\Admin\TranslationSettingsPage();

		$this->assertTrue( $page->is_api_key_defined_by_constant() );
		$this->assertSame( 'constant-key', $page->get_saved_api_key() );

		$sanitized = $page->sanitize_settings( array( 'deepl_reserved_characters' => '10' ) );

		$this->assertSame( 'saved-key', $sanitized['deepl_api_key'] );
	}
}
