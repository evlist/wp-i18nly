<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Language spec resolver tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests locale-to-provider resolution for plural specs.
 */
class LanguageSpecResolverTest extends TestCase {
	/**
	 * Resolves English provider from locale.
	 *
	 * @return void
	 */
	public function test_resolves_english_provider_from_locale() {
		$resolver = new \WP_I18nly\Plurals\LanguageSpecResolver();
		$spec     = $resolver->resolve_spec_for_locale( 'en_US' );

		$this->assertSame( '(n != 1)', $spec['plural_expression'] );
		$this->assertSame( 2, $spec['nplurals'] );
		$this->assertSame(
			array(
				array(
					'label'   => '1',
					'tooltip' => 'One',
				),
				array(
					'label'   => 'n',
					'tooltip' => 'Other than one',
				),
			),
			$spec['forms']
		);
	}

	/**
	 * Resolves French provider from locale.
	 *
	 * @return void
	 */
	public function test_resolves_french_provider_from_locale() {
		$resolver = new \WP_I18nly\Plurals\LanguageSpecResolver();
		$spec     = $resolver->resolve_spec_for_locale( 'fr_FR' );

		$this->assertSame( '(n > 1)', $spec['plural_expression'] );
		$this->assertArrayHasKey( 'forms', $spec );
	}

	/**
	 * Resolves locale-specific providers without collapsing to language.
	 *
	 * @return void
	 */
	public function test_resolves_locale_specific_provider_without_language_fallback() {
		$resolver = new \WP_I18nly\Plurals\LanguageSpecResolver();
		$pt_spec  = $resolver->resolve_spec_for_locale( 'pt' );
		$br_spec  = $resolver->resolve_spec_for_locale( 'pt_BR' );

		$this->assertSame( '(n != 1)', $pt_spec['plural_expression'] );
		$this->assertSame( '(n > 1)', $br_spec['plural_expression'] );
	}

	/**
	 * Applies generic two-form override for locales using `n != 1`.
	 *
	 * @return void
	 */
	public function test_applies_generic_two_form_override_for_n_not_1_locales() {
		$resolver = new \WP_I18nly\Plurals\LanguageSpecResolver();
		$spec     = $resolver->resolve_spec_for_locale( 'de_DE' );

		$this->assertSame( '(n != 1)', $spec['plural_expression'] );
		$this->assertSame(
			array(
				array(
					'label'   => '1',
					'tooltip' => 'One',
				),
				array(
					'label'   => 'n',
					'tooltip' => 'Other than one',
				),
			),
			$spec['forms']
		);
	}

	/**
	 * Applies generic single-form override for invariant locales.
	 *
	 * @return void
	 */
	public function test_applies_generic_single_form_override_for_invariant_locales() {
		$resolver = new \WP_I18nly\Plurals\LanguageSpecResolver();
		$spec     = $resolver->resolve_spec_for_locale( 'ja' );

		$this->assertSame( 1, $spec['nplurals'] );
		$this->assertSame(
			array(
				array(
					'label'   => '*',
					'tooltip' => 'Any number',
				),
			),
			$spec['forms']
		);
	}

	/**
	 * Falls back to default provider when language is unsupported.
	 *
	 * @return void
	 */
	public function test_falls_back_to_default_provider_for_unknown_language() {
		$resolver = new \WP_I18nly\Plurals\LanguageSpecResolver();
		$spec     = $resolver->resolve_spec_for_locale( 'zz_ZZ' );

		$this->assertSame( '(n != 1)', $spec['plural_expression'] );
		$this->assertSame(
			array(
				'1' => 'One',
				'n' => 'Other than one',
			),
			$spec['forms']
		);
	}
}
