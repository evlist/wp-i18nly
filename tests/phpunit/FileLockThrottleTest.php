<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * File lock throttle tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

/**
 * Validates file lock throttling behavior.
 */
class FileLockThrottleTest extends TestCase {
	/**
	 * Temporary lock directory.
	 *
	 * @var string
	 */
	private $lock_directory;

	/**
	 * Prepares test lock directory.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->lock_directory = sys_get_temp_dir() . '/i18nly-throttle-tests';

		if ( ! is_dir( $this->lock_directory ) ) {
			// phpcs:disable WordPress.WP.AlternativeFunctions -- test fixture setup in temp directory.
			mkdir( $this->lock_directory, 0777, true );
			// phpcs:enable WordPress.WP.AlternativeFunctions
		}
	}

	/**
	 * Removes lock directory content after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( ! is_dir( $this->lock_directory ) ) {
			return;
		}

		$files = glob( $this->lock_directory . '/i18nly_throttle_*.lock' );

		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_string( $file ) && file_exists( $file ) ) {
					// phpcs:disable WordPress.WP.AlternativeFunctions -- test fixture cleanup in temp directory.
					unlink( $file );
					// phpcs:enable WordPress.WP.AlternativeFunctions
				}
			}
		}
	}

	/**
	 * Enforces minimal delay between two consecutive calls.
	 *
	 * @return void
	 */
	public function test_wait_until_allowed_enforces_minimal_delay() {
		$throttle = new \WP_I18nly\Support\FileLockThrottle( 'unit_test_delay', 120, $this->lock_directory );

		$throttle->wait_until_allowed();
		$start = microtime( true );
		$throttle->wait_until_allowed();
		$elapsed_ms = ( microtime( true ) - $start ) * 1000;

		$this->assertGreaterThanOrEqual( 90.0, $elapsed_ms );
	}

	/**
	 * Skips throttling when lock directory is invalid.
	 *
	 * @return void
	 */
	public function test_wait_until_allowed_is_noop_when_directory_is_invalid() {
		$throttle = new \WP_I18nly\Support\FileLockThrottle( 'unit_test_invalid_dir', 300, $this->lock_directory . '/does-not-exist' );

		$start = microtime( true );
		$throttle->wait_until_allowed();
		$elapsed_ms = ( microtime( true ) - $start ) * 1000;

		$this->assertLessThan( 80.0, $elapsed_ms );
	}

	/**
	 * Returns immediately when delay is zero.
	 *
	 * @return void
	 */
	public function test_wait_until_allowed_returns_immediately_when_delay_is_zero() {
		$throttle = new \WP_I18nly\Support\FileLockThrottle( 'unit_test_zero_delay', 0, $this->lock_directory );

		$start = microtime( true );
		$throttle->wait_until_allowed();
		$elapsed_ms = ( microtime( true ) - $start ) * 1000;

		$this->assertLessThan( 30.0, $elapsed_ms );
	}
}
