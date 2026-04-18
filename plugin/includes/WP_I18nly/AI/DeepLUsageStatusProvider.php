<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * DeepL monthly usage status provider.
 *
 * @package I18nly
 */

namespace WP_I18nly\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Provides a normalized DeepL monthly usage snapshot with caching.
 */
class DeepLUsageStatusProvider {
	/**
	 * Option key used for cached status snapshots.
	 */
	private const CACHE_OPTION = 'i18nly_deepl_usage_status_cache';

	/**
	 * Default cache TTL in seconds.
	 */
	private const CACHE_TTL_SECONDS = 1800;

	/**
	 * Callback returning the saved DeepL API key.
	 *
	 * @var callable
	 */
	private $get_api_key_callback;

	/**
	 * HTTP GET callable.
	 *
	 * @var callable
	 */
	private $http_get;

	/**
	 * Constructor.
	 *
	 * @param callable      $get_api_key_callback Callback returning DeepL API key.
	 * @param callable|null $http_get Optional HTTP GET callable override.
	 */
	public function __construct( callable $get_api_key_callback, $http_get = null ) {
		$this->get_api_key_callback = $get_api_key_callback;
		$this->http_get             = is_callable( $http_get )
			? $http_get
			: function ( $url, array $args ) {
				return wp_remote_get( (string) $url, $args );
			};
	}

	/**
	 * Returns one monthly usage status snapshot.
	 *
	 * @param bool $force_refresh Whether to bypass cache.
	 * @return array<string, mixed>
	 */
	public function get_status( $force_refresh = false ) {
		$cached = $this->get_cached_status();

		if ( ! $force_refresh && $this->is_cache_fresh( $cached ) ) {
			return $this->normalize_output( $cached, false, '' );
		}

		$fresh = $this->fetch_status_from_api();

		if ( ! empty( $fresh['success'] ) ) {
			$this->store_cached_status( $fresh );
			return $this->normalize_output( $fresh, false, '' );
		}

		$error_message = isset( $fresh['message'] ) ? (string) $fresh['message'] : '';

		if ( $this->is_cache_usable( $cached ) ) {
			return $this->normalize_output( $cached, true, $error_message );
		}

		return $this->normalize_output( $fresh, true, $error_message );
	}

	/**
	 * Forces one API refresh and updates the cache on success.
	 *
	 * @return array<string, mixed>
	 */
	public function refresh_status() {
		return $this->get_status( true );
	}

	/**
	 * Clears cached usage snapshot.
	 *
	 * @return void
	 */
	public function invalidate_cache() {
		update_option( self::CACHE_OPTION, array() );
	}

	/**
	 * Fetches one status snapshot from DeepL usage endpoint.
	 *
	 * @return array<string, mixed>
	 */
	private function fetch_status_from_api() {
		$get_api_key = $this->get_api_key_callback;
		$api_key     = trim( (string) call_user_func( $get_api_key ) );

		if ( '' === $api_key ) {
			return $this->build_unavailable_status( __( 'No DeepL API key configured.', 'i18nly' ) );
		}

		$response = call_user_func(
			$this->http_get,
			$this->resolve_usage_endpoint( $api_key ),
			array(
				'headers' => array(
					'Authorization' => 'DeepL-Auth-Key ' . $api_key,
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->build_unavailable_status(
				sprintf(
					/* translators: %s: transport error message. */
					__( 'Unable to refresh DeepL usage: %s', 'i18nly' ),
					(string) $response->get_error_message()
				)
			);
		}

		$code = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
		$body = isset( $response['body'] ) ? (string) $response['body'] : '';

		if ( 200 !== $code ) {
			return $this->build_unavailable_status(
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Unable to refresh DeepL usage (HTTP %d).', 'i18nly' ),
					$code
				)
			);
		}

		$payload = json_decode( $body, true );

		if ( ! is_array( $payload ) || ! isset( $payload['character_count'], $payload['character_limit'] ) ) {
			return $this->build_unavailable_status( __( 'DeepL usage response format is invalid.', 'i18nly' ) );
		}

		$used    = max( 0, (int) $payload['character_count'] );
		$limit   = max( 0, (int) $payload['character_limit'] );
		$percent = $limit > 0
			? (int) min( 100, max( 0, round( ( $used * 100 ) / $limit ) ) )
			: 0;

		return array(
			'success'         => true,
			'used_characters' => $used,
			'character_limit' => $limit,
			'percent_used'    => $percent,
			'state'           => $this->resolve_state_from_percent( $percent ),
			'fetched_at'      => time(),
			'message'         => '',
		);
	}

	/**
	 * Resolves usage endpoint based on key type.
	 *
	 * @param string $api_key DeepL API key.
	 * @return string
	 */
	private function resolve_usage_endpoint( $api_key ) {
		$is_free_key = (bool) preg_match( '/:fx$/', (string) $api_key );

		if ( $is_free_key ) {
			return 'https://api-free.deepl.com/v2/usage';
		}

		return 'https://api.deepl.com/v2/usage';
	}

	/**
	 * Maps percentage to state token.
	 *
	 * @param int $percent_used Percentage.
	 * @return string
	 */
	private function resolve_state_from_percent( $percent_used ) {
		$percent_used = max( 0, min( 100, (int) $percent_used ) );

		if ( $percent_used >= 90 ) {
			return 'critical';
		}

		if ( $percent_used >= 70 ) {
			return 'warning';
		}

		return 'ok';
	}

	/**
	 * Builds an unavailable status payload.
	 *
	 * @param string $message Status message.
	 * @return array<string, mixed>
	 */
	private function build_unavailable_status( $message ) {
		return array(
			'success'         => false,
			'used_characters' => 0,
			'character_limit' => 0,
			'percent_used'    => 0,
			'state'           => 'unavailable',
			'fetched_at'      => time(),
			'message'         => (string) $message,
		);
	}

	/**
	 * Reads cached status from options.
	 *
	 * @return array<string, mixed>
	 */
	private function get_cached_status() {
		$cached = get_option( self::CACHE_OPTION, array() );

		if ( ! is_array( $cached ) ) {
			return array();
		}

		return $cached;
	}

	/**
	 * Stores cached status in options.
	 *
	 * @param array<string, mixed> $status Status payload.
	 * @return void
	 */
	private function store_cached_status( array $status ) {
		update_option( self::CACHE_OPTION, $status );
	}

	/**
	 * Returns whether cached status can be used.
	 *
	 * @param array<string, mixed> $cached Cached payload.
	 * @return bool
	 */
	private function is_cache_usable( array $cached ) {
		return isset( $cached['fetched_at'] ) && (int) $cached['fetched_at'] > 0;
	}

	/**
	 * Returns whether cached status is still fresh.
	 *
	 * @param array<string, mixed> $cached Cached payload.
	 * @return bool
	 */
	private function is_cache_fresh( array $cached ) {
		if ( ! $this->is_cache_usable( $cached ) ) {
			return false;
		}

		$ttl = $this->get_cache_ttl_seconds();

		return ( (int) $cached['fetched_at'] + $ttl ) >= time();
	}

	/**
	 * Returns cache TTL in seconds.
	 *
	 * @return int
	 */
	private function get_cache_ttl_seconds() {
		$ttl = self::CACHE_TTL_SECONDS;

		if ( function_exists( 'apply_filters' ) ) {
			$ttl = (int) apply_filters( 'i18nly_deepl_usage_cache_ttl_seconds', $ttl );
		}

		return max( 60, $ttl );
	}

	/**
	 * Normalizes status payload for UI usage.
	 *
	 * @param array<string, mixed> $status Raw status.
	 * @param bool                 $is_stale Whether values are stale.
	 * @param string               $stale_message Optional stale message.
	 * @return array<string, mixed>
	 */
	private function normalize_output( array $status, $is_stale, $stale_message ) {
		$message = isset( $status['message'] ) ? (string) $status['message'] : '';

		if ( $is_stale && '' !== trim( (string) $stale_message ) ) {
			$message = (string) $stale_message;
		}

		$state = isset( $status['state'] ) ? (string) $status['state'] : 'unavailable';

		if ( ! in_array( $state, array( 'ok', 'warning', 'critical', 'unavailable' ), true ) ) {
			$state = 'unavailable';
		}

		return array(
			'success'         => ! empty( $status['success'] ),
			'used_characters' => isset( $status['used_characters'] ) ? max( 0, (int) $status['used_characters'] ) : 0,
			'character_limit' => isset( $status['character_limit'] ) ? max( 0, (int) $status['character_limit'] ) : 0,
			'percent_used'    => isset( $status['percent_used'] ) ? max( 0, min( 100, (int) $status['percent_used'] ) ) : 0,
			'state'           => $state,
			'fetched_at'      => isset( $status['fetched_at'] ) ? max( 0, (int) $status['fetched_at'] ) : 0,
			'is_stale'        => (bool) $is_stale,
			'message'         => $message,
		);
	}
}
