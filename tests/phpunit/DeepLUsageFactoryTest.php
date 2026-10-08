<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * DeepL usage factory tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests the shared DeepL usage status provider factory.
 */
class DeepLUsageFactoryTest extends TestCase {
	/**
	 * Resets test options before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		i18nly_test_reset_options();
	}

	/**
	 * Reports usage as unavailable when no API key is saved.
	 *
	 * @return void
	 */
	public function test_provider_without_saved_key_is_unavailable() {
		$provider = ( new \WP_I18nly\Admin\DeepLUsageFactory() )->create_status_provider();

		$this->assertInstanceOf( 'WP_I18nly\\AI\\DeepLUsageStatusProvider', $provider );

		$status = $provider->get_status();

		$this->assertFalse( $status['success'] );
		$this->assertSame( 'unavailable', $status['state'] );
	}

	/**
	 * Uses the saved API key and reserved characters from the settings.
	 *
	 * @return void
	 */
	public function test_provider_uses_saved_key_and_reserved_characters() {
		update_option(
			'i18nly_translation_settings',
			array(
				'deepl_api_key'             => 'saved-key',
				'deepl_reserved_characters' => 250,
			)
		);

		$captured_args = array();
		$provider      = ( new \WP_I18nly\Admin\DeepLUsageFactory() )->create_status_provider(
			function ( $url, array $args ) use ( &$captured_args ) {
				unset( $url );
				$captured_args = $args;

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"character_count":700,"character_limit":1000}',
				);
			}
		);

		$status = $provider->get_status( true );

		$this->assertTrue( $status['success'] );
		$this->assertSame( 750, $status['character_limit'] );
		$this->assertSame( 250, $status['reserved_characters'] );
		$this->assertStringContainsString( 'saved-key', wp_json_encode( $captured_args ) );
	}
}
