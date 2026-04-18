<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\AI\DeepLUsageStatusProvider;

/**
 * Tests DeepL usage status provider behavior.
 */
class DeepLUsageStatusProviderTest extends TestCase {
	/**
	 * Resets test options before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		i18nly_test_reset_options();
	}

	/**
	 * Loads usage from API and reuses cache while fresh.
	 *
	 * @return void
	 */
	public function test_get_status_uses_cache_after_successful_refresh() {
		$calls = 0;

		$provider = new DeepLUsageStatusProvider(
			function () {
				return 'pro-key';
			},
			function () use ( &$calls ) {
				++$calls;

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"character_count":750,"character_limit":1000}',
				);
			}
		);

		$first = $provider->get_status();
		$this->assertTrue( $first['success'] );
		$this->assertSame( 750, $first['used_characters'] );
		$this->assertSame( 1000, $first['character_limit'] );
		$this->assertSame( 75, $first['percent_used'] );
		$this->assertSame( 'warning', $first['state'] );

		$second = $provider->get_status();
		$this->assertTrue( $second['success'] );
		$this->assertSame( 1, $calls );
	}

	/**
	 * Deducts reserved monthly quota from DeepL character limit.
	 *
	 * @return void
	 */
	public function test_get_status_deducts_reserved_characters_from_limit() {
		$provider = new DeepLUsageStatusProvider(
			function () {
				return 'pro-key';
			},
			function () {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"character_count":700,"character_limit":1000}',
				);
			},
			function () {
				return 250;
			}
		);

		$status = $provider->get_status( true );

		$this->assertTrue( $status['success'] );
		$this->assertSame( 700, $status['used_characters'] );
		$this->assertSame( 1000, $status['raw_character_limit'] );
		$this->assertSame( 250, $status['reserved_characters'] );
		$this->assertSame( 750, $status['character_limit'] );
		$this->assertSame( 93, $status['percent_used'] );
		$this->assertSame( 'critical', $status['state'] );
	}

	/**
	 * Falls back to stale cached values when refresh fails.
	 *
	 * @return void
	 */
	public function test_get_status_returns_stale_cache_when_refresh_fails() {
		$seed_provider = new DeepLUsageStatusProvider(
			function () {
				return 'pro-key';
			},
			function () {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"character_count":100,"character_limit":1000}',
				);
			}
		);

		$seed_provider->refresh_status();

		$failing_provider = new DeepLUsageStatusProvider(
			function () {
				return 'pro-key';
			},
			function () {
				return new WP_Error( 'http_request_failed', 'timeout' );
			}
		);

		$status = $failing_provider->refresh_status();

		$this->assertTrue( $status['is_stale'] );
		$this->assertSame( 100, $status['used_characters'] );
		$this->assertStringContainsString( 'timeout', strtolower( (string) $status['message'] ) );
	}

	/**
	 * Returns unavailable status when no key is configured.
	 *
	 * @return void
	 */
	public function test_get_status_without_key_returns_unavailable_status() {
		$provider = new DeepLUsageStatusProvider(
			function () {
				return '';
			}
		);

		$status = $provider->get_status( true );

		$this->assertFalse( $status['success'] );
		$this->assertSame( 'unavailable', $status['state'] );
		$this->assertStringContainsString( 'no deepl api key', strtolower( (string) $status['message'] ) );
	}
}
