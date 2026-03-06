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
		$this->assertSame( 2, I18nly_Plural_Forms_Registry::get_plural_forms_count_for_locale( 'xx_XX' ) );
		$this->assertSame( array( 'one', 'other' ), I18nly_Plural_Forms_Registry::get_form_labels_for_locale( 'xx_XX' ) );
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
			I18nly_Plural_Forms_Registry::get_forms_for_locale( 'xx_XX' )
		);
	}

	/**
	 * Resolves one form for invariant plural locales.
	 *
	 * @return void
	 */
	public function test_registry_resolves_single_form_locale() {
		$this->assertSame( 1, I18nly_Plural_Forms_Registry::get_plural_forms_count_for_locale( 'ja_JP' ) );
		$this->assertSame( array( 'other' ), I18nly_Plural_Forms_Registry::get_form_labels_for_locale( 'ja_JP' ) );
	}

	/**
	 * Resolves three-form locale with meaningful CLDR categories.
	 *
	 * @return void
	 */
	public function test_registry_resolves_three_form_locale() {
		$this->assertSame( 3, I18nly_Plural_Forms_Registry::get_plural_forms_count_for_locale( 'ru_RU' ) );
		$this->assertSame( array( 'one', 'few', 'many' ), I18nly_Plural_Forms_Registry::get_form_labels_for_locale( 'ru_RU' ) );
	}

	/**
	 * Exposes gettext plural expression and header value.
	 *
	 * @return void
	 */
	public function test_registry_exposes_plural_forms_header_for_locale() {
		$this->assertSame( '(n > 1)', I18nly_Plural_Forms_Registry::get_plural_expression_for_locale( 'fr_FR' ) );
		$this->assertSame(
			'nplurals=2; plural=(n > 1);',
			I18nly_Plural_Forms_Registry::get_plural_forms_header_for_locale( 'fr_FR' )
		);
		$this->assertSame( array( 'a', 'b' ), I18nly_Plural_Forms_Registry::get_form_markers_for_locale( 'fr_FR' ) );
		$this->assertSame( array( 'Zero or one', 'More than one' ), I18nly_Plural_Forms_Registry::get_form_tooltips_for_locale( 'fr_FR' ) );
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
			I18nly_Plural_Forms_Registry::get_forms_for_locale( 'fr_FR' )
		);
	}

	/**
	 * Distinguishes French and English behavior for zero.
	 *
	 * @return void
	 */
	public function test_registry_computes_different_zero_index_for_french_and_english() {
		$this->assertSame( 0, I18nly_Plural_Forms_Registry::compute_form_index( 'fr_FR', 0 ) );
		$this->assertSame( 1, I18nly_Plural_Forms_Registry::compute_form_index( 'en_US', 0 ) );
	}

	/**
	 * Computes Arabic indexes across six plural forms.
	 *
	 * @return void
	 */
	public function test_registry_computes_arabic_form_indexes() {
		$this->assertSame( 0, I18nly_Plural_Forms_Registry::compute_form_index( 'ar_AR', 0 ) );
		$this->assertSame( 1, I18nly_Plural_Forms_Registry::compute_form_index( 'ar_AR', 1 ) );
		$this->assertSame( 2, I18nly_Plural_Forms_Registry::compute_form_index( 'ar_AR', 2 ) );
		$this->assertSame( 3, I18nly_Plural_Forms_Registry::compute_form_index( 'ar_AR', 7 ) );
		$this->assertSame( 4, I18nly_Plural_Forms_Registry::compute_form_index( 'ar_AR', 13 ) );
		$this->assertSame( 5, I18nly_Plural_Forms_Registry::compute_form_index( 'ar_AR', 100 ) );
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
