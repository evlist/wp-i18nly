<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * LangEn generated spec tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

/**
 * Verifies the generated English plural forms contract.
 */
class LangEnSpecTest extends TestCase {
	/**
	 * Ensures LangEn exposes two plural forms with non-empty label and tooltip.
	 *
	 * @return void
	 */
	public function test_langenus_returns_two_forms_with_label_and_tooltip() {
		$spec = \WP_I18nly\Plurals\Languages\LangEnUs::get_spec();

		$this->assertArrayHasKey( 'forms', $spec );
		$this->assertIsArray( $spec['forms'] );
		$this->assertCount( 2, $spec['forms'] );

		$this->assertArrayHasKey( 0, $spec['forms'] );
		$this->assertArrayHasKey( 1, $spec['forms'] );
		$this->assertIsArray( $spec['forms'][0] );
		$this->assertIsArray( $spec['forms'][1] );

		$this->assertArrayHasKey( 'label', $spec['forms'][0] );
		$this->assertArrayHasKey( 'tooltip', $spec['forms'][0] );
		$this->assertArrayHasKey( 'label', $spec['forms'][1] );
		$this->assertArrayHasKey( 'tooltip', $spec['forms'][1] );

		$this->assertIsString( $spec['forms'][0]['label'] );
		$this->assertIsString( $spec['forms'][0]['tooltip'] );
		$this->assertIsString( $spec['forms'][1]['label'] );
		$this->assertIsString( $spec['forms'][1]['tooltip'] );

		$this->assertNotSame( '', trim( $spec['forms'][0]['label'] ) );
		$this->assertNotSame( '', trim( $spec['forms'][0]['tooltip'] ) );
		$this->assertNotSame( '', trim( $spec['forms'][1]['label'] ) );
		$this->assertNotSame( '', trim( $spec['forms'][1]['tooltip'] ) );
	}
}
