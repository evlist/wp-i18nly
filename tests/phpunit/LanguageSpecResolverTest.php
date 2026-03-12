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
