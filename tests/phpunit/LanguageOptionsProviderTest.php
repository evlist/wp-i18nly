<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Language options provider tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests target locale options built from generated supported locales.
 */
class LanguageOptionsProviderTest extends TestCase {
	/**
	 * Uses generated supported locales and excludes source locale.
	 *
	 * @return void
	 */
	public function test_returns_only_generated_supported_locales() {
		i18nly_test_set_available_translations(
			array(
				'fr_FR' => array(
					'native_name' => 'Francais',
				),
				'de_DE' => array(
					'native_name' => 'Deutsch',
				),
				'xx_YY' => array(
					'native_name' => 'Unsupported Locale',
				),
			)
		);

		$provider = new \WP_I18nly\Support\LanguageOptionsProvider();
		$options  = $provider->get_target_language_options( 'en_US' );

		$values = array_column( $options, 'value' );
		$labels = array_column( $options, 'label', 'value' );

		$this->assertContains( 'fr_FR', $values );
		$this->assertContains( 'de_DE', $values );
		$this->assertNotContains( 'en_US', $values );
		$this->assertNotContains( 'xx_YY', $values );
		$this->assertSame( 'Francais', isset( $labels['fr_FR'] ) ? $labels['fr_FR'] : '' );
	}
}
