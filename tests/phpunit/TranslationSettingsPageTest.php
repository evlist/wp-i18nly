<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

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
}
