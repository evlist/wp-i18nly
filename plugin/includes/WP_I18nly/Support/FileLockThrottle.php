<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * File-based throttle utility.
 *
 * @package I18nly
 */

namespace WP_I18nly\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Provides a process-safe minimum delay between actions using one lock file.
 */
class FileLockThrottle {
	/**
	 * Throttle namespace.
	 *
	 * @var string
	 */
	private $namespace;

	/**
	 * Minimal delay between actions in milliseconds.
	 *
	 * @var int
	 */
	private $minimal_delay_ms;

	/**
	 * Directory storing lock files.
	 *
	 * @var string
	 */
	private $directory;

	/**
	 * Constructor.
	 *
	 * @param string      $throttle_namespace Throttle namespace.
	 * @param int         $minimal_delay_ms Minimal delay in milliseconds.
	 * @param string|null $directory Optional lock directory.
	 */
	public function __construct( $throttle_namespace, $minimal_delay_ms, $directory = null ) {
		$this->namespace        = function_exists( 'sanitize_key' )
			? sanitize_key( (string) $throttle_namespace )
			: strtolower( (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $throttle_namespace ) );
		$this->minimal_delay_ms = max( 0, (int) $minimal_delay_ms );
		$this->directory        = is_string( $directory ) && '' !== trim( $directory )
			? (string) $directory
			: (string) sys_get_temp_dir();
	}

	/**
	 * Blocks until the throttle allows the next action.
	 *
	 * @return void
	 */
	public function wait_until_allowed() {
		if ( $this->minimal_delay_ms <= 0 ) {
			return;
		}

		$lock_file = $this->get_lock_file_path();

		if ( '' === $lock_file ) {
			return;
		}

		// phpcs:disable WordPress.WP.AlternativeFunctions -- flock-based inter-process throttling requires native file handles.
		$handle = fopen( $lock_file, 'c+' );

		if ( false === $handle ) {
			return;
		}

		if ( ! flock( $handle, LOCK_EX ) ) {
			fclose( $handle );
			return;
		}

		rewind( $handle );
		$stored_timestamp = stream_get_contents( $handle );
		$last_ms          = is_string( $stored_timestamp ) ? (int) $stored_timestamp : 0;
		$now_ms           = (int) floor( microtime( true ) * 1000 );
		$elapsed_ms       = $now_ms - $last_ms;
		$wait_ms          = $this->minimal_delay_ms - $elapsed_ms;

		if ( $wait_ms > 0 ) {
			usleep( $wait_ms * 1000 );
			$now_ms = (int) floor( microtime( true ) * 1000 );
		}

		ftruncate( $handle, 0 );
		rewind( $handle );
		fwrite( $handle, (string) $now_ms );
		fflush( $handle );
		flock( $handle, LOCK_UN );
		fclose( $handle );
		// phpcs:enable WordPress.WP.AlternativeFunctions
	}

	/**
	 * Returns lock file path.
	 *
	 * @return string
	 */
	private function get_lock_file_path() {
		$directory = rtrim( $this->directory, DIRECTORY_SEPARATOR );

		if ( '' === $directory || ! is_dir( $directory ) ) {
			return '';
		}

		if ( '' === $this->namespace ) {
			return '';
		}

		return $directory . DIRECTORY_SEPARATOR . 'i18nly_throttle_' . $this->namespace . '.lock';
	}
}
