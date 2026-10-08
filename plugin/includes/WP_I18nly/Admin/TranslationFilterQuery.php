<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation edit-screen filter query handling.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, sanitizes and re-applies the translation entries filters carried by the request.
 */
class TranslationFilterQuery {
	/**
	 * Query key for entry lifecycle filters.
	 */
	public const KEY_ENTRY = 'i18nly_filter_entries';

	/**
	 * Query key for quality status filters.
	 */
	public const KEY_QUALITY = 'i18nly_filter_statuses';

	/**
	 * Query key for provenance filters.
	 */
	public const KEY_PROVENANCE = 'i18nly_filter_provenance';

	/**
	 * Query key for search text filter.
	 */
	public const KEY_SEARCH = 'i18nly_filter_search';

	/**
	 * Query key for search scope filters.
	 */
	public const KEY_SEARCH_FIELDS = 'i18nly_filter_search_fields';

	/**
	 * Request parameter reader.
	 *
	 * @var callable
	 */
	private $read_parameter;

	/**
	 * Constructor.
	 *
	 * @param callable|null $read_parameter Optional reader receiving a parameter key and returning its raw string value.
	 */
	public function __construct( $read_parameter = null ) {
		$this->read_parameter = is_callable( $read_parameter )
			? $read_parameter
			: array( $this, 'read_request_parameter' );
	}

	/**
	 * Returns current filter values from the request, falling back to the referer query string.
	 *
	 * @return array<string, string>
	 */
	public function get_values() {
		$values = array(
			self::KEY_ENTRY         => '',
			self::KEY_QUALITY       => '',
			self::KEY_PROVENANCE    => '',
			self::KEY_SEARCH        => '',
			self::KEY_SEARCH_FIELDS => '',
		);

		foreach ( array_keys( $values ) as $query_key ) {
			$values[ $query_key ] = $this->sanitize_value( $this->read( $query_key ), $query_key );
		}

		$referer = $this->read( '_wp_http_referer' );
		if ( '' === $referer ) {
			return $values;
		}

		$parsed_query = wp_parse_url( $referer, PHP_URL_QUERY );
		if ( ! is_string( $parsed_query ) || '' === $parsed_query ) {
			return $values;
		}

		$referer_args = array();
		parse_str( $parsed_query, $referer_args );

		foreach ( array_keys( $values ) as $query_key ) {
			if ( '' !== $values[ $query_key ] ) {
				continue;
			}

			if ( ! isset( $referer_args[ $query_key ] ) || ! is_scalar( $referer_args[ $query_key ] ) ) {
				continue;
			}

			$values[ $query_key ] = $this->sanitize_value( (string) $referer_args[ $query_key ], $query_key );
		}

		return $values;
	}

	/**
	 * Appends the non-empty current filters to one location.
	 *
	 * @param string $location Redirect location.
	 * @return string
	 */
	public function append_to_location( $location ) {
		foreach ( $this->get_values() as $query_key => $query_value ) {
			if ( '' === $query_value ) {
				continue;
			}

			$location = add_query_arg( $query_key, $query_value, $location );
		}

		return $location;
	}

	/**
	 * Sanitizes one filter query value.
	 *
	 * @param string $value Raw filter query value.
	 * @param string $query_key Filter query key.
	 * @return string
	 */
	public function sanitize_value( $value, $query_key = '' ) {
		if ( self::KEY_SEARCH === $query_key ) {
			return trim( sanitize_text_field( (string) $value ) );
		}

		$normalized = strtolower( trim( (string) $value ) );
		$normalized = preg_replace( '/[^a-z_,]/', '', $normalized );

		if ( ! is_string( $normalized ) ) {
			return '';
		}

		return trim( $normalized, ',' );
	}

	/**
	 * Reads one parameter through the configured reader.
	 *
	 * @param string $key Parameter key.
	 * @return string
	 */
	private function read( $key ) {
		return (string) call_user_func( $this->read_parameter, (string) $key );
	}

	/**
	 * Reads one request parameter from POST first, then GET.
	 *
	 * @param string $key Parameter key.
	 * @return string
	 */
	private function read_request_parameter( $key ) {
		$post_value = filter_input( INPUT_POST, (string) $key, FILTER_UNSAFE_RAW );
		if ( is_string( $post_value ) && '' !== $post_value ) {
			return (string) wp_unslash( $post_value );
		}

		$get_value = filter_input( INPUT_GET, (string) $key, FILTER_UNSAFE_RAW );
		if ( is_string( $get_value ) && '' !== $get_value ) {
			return $get_value;
		}

		return '';
	}
}
