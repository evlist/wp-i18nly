<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Plural forms registry tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests CLDR-derived plural forms registry.
 */
class PluralFormsRegistryTest extends TestCase {
	/**
	 * Uses default two-form spec for unknown locale.
	 *
	 * @return void
	 */
	public function test_registry_falls_back_to_default_spec_for_unknown_locale() {
		$this->assertSame( 2, \WP_I18nly\PluralFormsRegistry::get_plural_forms_count_for_locale( 'xx_XX' ) );
		$this->assertSame( array( 'one', 'other' ), \WP_I18nly\PluralFormsRegistry::get_form_labels_for_locale( 'xx_XX' ) );
		$this->assertSame(
			array(
				array(
					'marker'  => 'a',
					'label'   => 'one',
					'tooltip' => 'One',
				),
				array(
					'marker'  => 'b',
					'label'   => 'other',
					'tooltip' => 'Other values',
				),
			),
			\WP_I18nly\PluralFormsRegistry::get_forms_for_locale( 'xx_XX' )
		);
	}

	/**
	 * Resolves one form for invariant plural locales.
	 *
	 * @return void
	 */
	public function test_registry_resolves_single_form_locale() {
		$this->assertSame( 1, \WP_I18nly\PluralFormsRegistry::get_plural_forms_count_for_locale( 'ja_JP' ) );
		$this->assertSame( array( 'other' ), \WP_I18nly\PluralFormsRegistry::get_form_labels_for_locale( 'ja_JP' ) );
	}

	/**
	 * Resolves three-form locale with meaningful CLDR categories.
	 *
	 * @return void
	 */
	public function test_registry_resolves_three_form_locale() {
		$this->assertSame( 3, \WP_I18nly\PluralFormsRegistry::get_plural_forms_count_for_locale( 'ru_RU' ) );
		$this->assertSame( array( 'one', 'few', 'many' ), \WP_I18nly\PluralFormsRegistry::get_form_labels_for_locale( 'ru_RU' ) );
	}

	/**
	 * Exposes gettext plural expression and related forms metadata.
	 *
	 * @return void
	 */
	public function test_registry_exposes_plural_forms_header_for_locale() {
		$this->assertSame( '(n > 1)', \WP_I18nly\PluralFormsRegistry::get_plural_expression_for_locale( 'fr_FR' ) );
		$this->assertSame( 2, \WP_I18nly\PluralFormsRegistry::get_plural_forms_count_for_locale( 'fr_FR' ) );
		$this->assertSame( array( 'a', 'b' ), \WP_I18nly\PluralFormsRegistry::get_form_markers_for_locale( 'fr_FR' ) );
		$this->assertSame( array( 'Zero or one', 'More than one' ), \WP_I18nly\PluralFormsRegistry::get_form_tooltips_for_locale( 'fr_FR' ) );
		$this->assertSame(
			array(
				array(
					'marker'  => 'a',
					'label'   => 'one',
					'tooltip' => 'Zero or one',
				),
				array(
					'marker'  => 'b',
					'label'   => 'other',
					'tooltip' => 'More than one',
				),
			),
			\WP_I18nly\PluralFormsRegistry::get_forms_for_locale( 'fr_FR' )
		);
	}

	/**
	 * Keeps helper API wired to registry results.
	 *
	 * @return void
	 */
	public function test_admin_page_helper_uses_registry_for_plural_count() {
		$this->assertSame( 6, I18nly_Admin_Page_Helper::get_plural_forms_count_for_locale( 'ar_AR' ) );
	}
}
