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
	 * Minimal adaptive delay lifetime in milliseconds.
	 *
	 * @var int
	 */
	private const MIN_ADAPTIVE_TTL_MS = 1800000;

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

		$state     = $this->read_state( $handle );
		$now_ms    = (int) floor( microtime( true ) * 1000 );
		$state     = $this->normalize_state( $state, $now_ms );
		$last_ms   = (int) $state['last_ms'];
		$delay_ms  = (int) $state['delay_ms'];
		$now_ms           = (int) floor( microtime( true ) * 1000 );
		$elapsed_ms       = $now_ms - $last_ms;
		$effective_delay  = max( $this->minimal_delay_ms, $delay_ms );
		$wait_ms          = $effective_delay - $elapsed_ms;

		if ( $wait_ms > 0 ) {
			usleep( $wait_ms * 1000 );
			$now_ms = (int) floor( microtime( true ) * 1000 );
		}

		$state['last_ms'] = $now_ms;
		$this->write_state( $handle, $state );
		flock( $handle, LOCK_UN );
		fclose( $handle );
		// phpcs:enable WordPress.WP.AlternativeFunctions
	}

	/**
	 * Increases adaptive delay after a rate-limit response.
	 *
	 * If Retry-After is provided, this value is used. Otherwise adaptive delay is doubled.
	 * Adaptive delay remains active for max(30 min, 10 x delay).
	 *
	 * @param int $retry_after_ms Retry-After hint in milliseconds.
	 * @return int Updated adaptive delay in milliseconds.
	 */
	public function increase_adaptive_delay( $retry_after_ms = 0 ) {
		$lock_file = $this->get_lock_file_path();

		if ( '' === $lock_file ) {
			return max( $this->minimal_delay_ms, (int) $retry_after_ms );
		}

		// phpcs:disable WordPress.WP.AlternativeFunctions -- flock-based inter-process throttling requires native file handles.
		$handle = fopen( $lock_file, 'c+' );

		if ( false === $handle ) {
			return max( $this->minimal_delay_ms, (int) $retry_after_ms );
		}

		if ( ! flock( $handle, LOCK_EX ) ) {
			fclose( $handle );
			return max( $this->minimal_delay_ms, (int) $retry_after_ms );
		}

		$now_ms = (int) floor( microtime( true ) * 1000 );
		$state  = $this->normalize_state( $this->read_state( $handle ), $now_ms );

		$retry_after_ms = max( 0, (int) $retry_after_ms );
		$current_delay  = max( 0, (int) $state['delay_ms'] );

		if ( $retry_after_ms > 0 ) {
			$new_delay_ms = max( $this->minimal_delay_ms, $retry_after_ms );
		} else {
			$base_delay_ms = max( $this->minimal_delay_ms, $current_delay );
			$new_delay_ms  = max( $this->minimal_delay_ms, $base_delay_ms * 2 );
		}

		$ttl_ms = max( self::MIN_ADAPTIVE_TTL_MS, $new_delay_ms * 10 );

		$state['delay_ms']      = $new_delay_ms;
		$state['expires_at_ms'] = $now_ms + $ttl_ms;

		$this->write_state( $handle, $state );
		flock( $handle, LOCK_UN );
		fclose( $handle );
		// phpcs:enable WordPress.WP.AlternativeFunctions

		return $new_delay_ms;
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

	/**
	 * Reads persisted throttle state from file handle.
	 *
	 * @param resource $handle File handle.
	 * @return array{last_ms: int, delay_ms: int, expires_at_ms: int}
	 */
	private function read_state( $handle ) {
		rewind( $handle );
		$raw = stream_get_contents( $handle );

		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array(
				'last_ms'       => 0,
				'delay_ms'      => 0,
				'expires_at_ms' => 0,
			);
		}

		$decoded = json_decode( $raw, true );

		if ( is_array( $decoded ) ) {
			return array(
				'last_ms'       => isset( $decoded['last_ms'] ) ? (int) $decoded['last_ms'] : 0,
				'delay_ms'      => isset( $decoded['delay_ms'] ) ? (int) $decoded['delay_ms'] : 0,
				'expires_at_ms' => isset( $decoded['expires_at_ms'] ) ? (int) $decoded['expires_at_ms'] : 0,
			);
		}

		return array(
			'last_ms'       => (int) $raw,
			'delay_ms'      => 0,
			'expires_at_ms' => 0,
		);
	}

	/**
	 * Writes throttle state to file handle.
	 *
	 * @param resource                              $handle File handle.
	 * @param array{last_ms: int, delay_ms: int, expires_at_ms: int} $state State.
	 * @return void
	 */
	private function write_state( $handle, array $state ) {
		$encoded = wp_json_encode( $state );

		if ( ! is_string( $encoded ) || '' === $encoded ) {
			$encoded = (string) (int) $state['last_ms'];
		}

		ftruncate( $handle, 0 );
		rewind( $handle );
		fwrite( $handle, $encoded );
		fflush( $handle );
	}

	/**
	 * Normalizes state and clears expired adaptive delay.
	 *
	 * @param array{last_ms: int, delay_ms: int, expires_at_ms: int} $state State.
	 * @param int                                                     $now_ms Current timestamp.
	 * @return array{last_ms: int, delay_ms: int, expires_at_ms: int}
	 */
	private function normalize_state( array $state, $now_ms ) {
		$last_ms       = max( 0, isset( $state['last_ms'] ) ? (int) $state['last_ms'] : 0 );
		$delay_ms      = max( 0, isset( $state['delay_ms'] ) ? (int) $state['delay_ms'] : 0 );
		$expires_at_ms = max( 0, isset( $state['expires_at_ms'] ) ? (int) $state['expires_at_ms'] : 0 );

		if ( $expires_at_ms > 0 && $expires_at_ms <= (int) $now_ms ) {
			$delay_ms      = 0;
			$expires_at_ms = 0;
		}

		return array(
			'last_ms'       => $last_ms,
			'delay_ms'      => $delay_ms,
			'expires_at_ms' => $expires_at_ms,
		);
	}
}
