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
		$this->assertSame( 2, \WP_I18nly\Plurals\PluralFormsRegistry::get_plural_forms_count_for_locale( 'xx_XX' ) );
		$this->assertSame( array( 'a', 'b' ), \WP_I18nly\Plurals\PluralFormsRegistry::get_form_labels_for_locale( 'xx_XX' ) );
		$this->assertSame(
			array(
				array(
					'marker'  => 'a',
					'label'   => 'a',
					'tooltip' => 'One',
				),
				array(
					'marker'  => 'b',
					'label'   => 'b',
					'tooltip' => 'Other than one',
				),
			),
			\WP_I18nly\Plurals\PluralFormsRegistry::get_forms_for_locale( 'xx_XX' )
		);
	}

	/**
	 * Resolves one form for invariant plural locales.
	 *
	 * @return void
	 */
	public function test_registry_resolves_single_form_locale() {
		$this->assertSame( 1, \WP_I18nly\Plurals\PluralFormsRegistry::get_plural_forms_count_for_locale( 'ja' ) );
		$this->assertSame( array( 'a' ), \WP_I18nly\Plurals\PluralFormsRegistry::get_form_labels_for_locale( 'ja' ) );
	}

	/**
	 * Resolves three-form locale with meaningful CLDR categories.
	 *
	 * @return void
	 */
	public function test_registry_resolves_three_form_locale() {
		$this->assertSame( 3, \WP_I18nly\Plurals\PluralFormsRegistry::get_plural_forms_count_for_locale( 'ru_RU' ) );
		$this->assertSame( array( 'a', 'b', 'c' ), \WP_I18nly\Plurals\PluralFormsRegistry::get_form_labels_for_locale( 'ru_RU' ) );
	}

	/**
	 * Exposes gettext plural expression and related forms metadata.
	 *
	 * @return void
	 */
	public function test_registry_exposes_plural_forms_header_for_locale() {
		$this->assertSame( '(n > 1)', \WP_I18nly\Plurals\PluralFormsRegistry::get_plural_expression_for_locale( 'fr_FR' ) );
		$this->assertSame( 2, \WP_I18nly\Plurals\PluralFormsRegistry::get_plural_forms_count_for_locale( 'fr_FR' ) );
		$this->assertSame( array( 'a', 'b' ), \WP_I18nly\Plurals\PluralFormsRegistry::get_form_markers_for_locale( 'fr_FR' ) );
		$this->assertSame( array( '0, 1', 'other' ), \WP_I18nly\Plurals\PluralFormsRegistry::get_form_tooltips_for_locale( 'fr_FR' ) );
		$this->assertSame(
			array(
				array(
					'marker'  => 'a',
					'label'   => 'a',
					'tooltip' => '0, 1',
				),
				array(
					'marker'  => 'b',
					'label'   => 'b',
					'tooltip' => 'other',
				),
			),
			\WP_I18nly\Plurals\PluralFormsRegistry::get_forms_for_locale( 'fr_FR' )
		);
	}

	/**
	 * Resolves six plural forms for Arabic locales.
	 *
	 * @return void
	 */
	public function test_registry_resolves_six_form_locale() {
		$this->assertSame( 6, \WP_I18nly\Plurals\PluralFormsRegistry::get_plural_forms_count_for_locale( 'ar' ) );
	}

	/**
	 * Computes plural form index for representative locales.
	 *
	 * @return void
	 */
	public function test_registry_computes_form_index_for_locale() {
		$this->assertSame( 0, \WP_I18nly\Plurals\PluralFormsRegistry::get_form_index_for_locale( 'fr_FR', 0 ) );
		$this->assertSame( 0, \WP_I18nly\Plurals\PluralFormsRegistry::get_form_index_for_locale( 'fr_FR', 1 ) );
		$this->assertSame( 1, \WP_I18nly\Plurals\PluralFormsRegistry::get_form_index_for_locale( 'fr_FR', 2 ) );

		$this->assertSame( 0, \WP_I18nly\Plurals\PluralFormsRegistry::get_form_index_for_locale( 'ru_RU', 1 ) );
		$this->assertSame( 1, \WP_I18nly\Plurals\PluralFormsRegistry::get_form_index_for_locale( 'ru_RU', 2 ) );
		$this->assertSame( 2, \WP_I18nly\Plurals\PluralFormsRegistry::get_form_index_for_locale( 'ru_RU', 5 ) );

		$this->assertSame( 0, \WP_I18nly\Plurals\PluralFormsRegistry::get_form_index_for_locale( 'ar', 0 ) );
		$this->assertSame( 1, \WP_I18nly\Plurals\PluralFormsRegistry::get_form_index_for_locale( 'ar', 1 ) );
		$this->assertSame( 2, \WP_I18nly\Plurals\PluralFormsRegistry::get_form_index_for_locale( 'ar', 2 ) );
		$this->assertSame( 3, \WP_I18nly\Plurals\PluralFormsRegistry::get_form_index_for_locale( 'ar', 3 ) );
		$this->assertSame( 4, \WP_I18nly\Plurals\PluralFormsRegistry::get_form_index_for_locale( 'ar', 11 ) );
		$this->assertSame( 5, \WP_I18nly\Plurals\PluralFormsRegistry::get_form_index_for_locale( 'ar', 100 ) );

		$this->assertSame( 0, \WP_I18nly\Plurals\PluralFormsRegistry::get_form_index_for_locale( 'ja', 42 ) );
		$this->assertSame( 0, \WP_I18nly\Plurals\PluralFormsRegistry::get_form_index_for_locale( 'xx_XX', 1 ) );
		$this->assertSame( 1, \WP_I18nly\Plurals\PluralFormsRegistry::get_form_index_for_locale( 'xx_XX', 2 ) );
	}
}
