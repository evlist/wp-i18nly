<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * DeepL credentials validator.
 *
 * @package I18nly
 */

namespace WP_I18nly\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Validates DeepL API credentials against the usage endpoint.
 */
class DeepLCredentialsValidator {
	/**
	 * HTTP GET callable.
	 *
	 * @var callable
	 */
	private $http_get;

	/**
	 * Constructor.
	 *
	 * @param callable|null $http_get HTTP GET callable override.
	 */
	public function __construct( $http_get = null ) {
		$this->http_get = is_callable( $http_get )
			? $http_get
			: function ( $url, array $args ) {
				return wp_remote_get( (string) $url, $args );
			};
	}

	/**
	 * Validates one DeepL API key.
	 *
	 * @param string $api_key DeepL API key.
	 * @return array{success: bool, message: string}
	 */
	public function validate_credentials( $api_key ) {
		$api_key = trim( (string) $api_key );

		if ( '' === $api_key ) {
			return array(
				'success' => false,
				'message' => __( 'The DeepL API key is empty.', 'i18nly' ),
			);
		}

		$endpoint = $this->resolve_usage_endpoint( $api_key );
		$response = call_user_func(
			$this->http_get,
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'DeepL-Auth-Key ' . $api_key,
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: transport error message. */
					__( 'DeepL connection failed: %s', 'i18nly' ),
					(string) $response->get_error_message()
				),
			);
		}

		$code = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
		$body = isset( $response['body'] ) ? (string) $response['body'] : '';

		if ( 200 !== $code ) {
			if ( 401 === $code || 403 === $code ) {
				return array(
					'success' => false,
					'message' => __( 'DeepL rejected the API key. Check that the key is valid and active.', 'i18nly' ),
				);
			}

			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %d: HTTP status code. */
					__( 'DeepL returned an unexpected HTTP status: %d', 'i18nly' ),
					$code
				),
			);
		}

		$payload = json_decode( $body, true );
		if ( ! is_array( $payload ) ) {
			return array(
				'success' => true,
				'message' => __( 'DeepL connection successful.', 'i18nly' ),
			);
		}

		$character_count = isset( $payload['character_count'] ) ? (int) $payload['character_count'] : null;
		$character_limit = isset( $payload['character_limit'] ) ? (int) $payload['character_limit'] : null;

		if ( null !== $character_count && null !== $character_limit ) {
			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: 1: used character count, 2: character limit. */
					__( 'DeepL connection successful. Usage: %1$s / %2$s characters.', 'i18nly' ),
					$this->format_number( $character_count ),
					$this->format_number( $character_limit )
				),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'DeepL connection successful.', 'i18nly' ),
		);
	}

	/**
	 * Returns the usage endpoint URL for the given key.
	 *
	 * Free API keys end with ":fx" and require api-free.deepl.com.
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
	 * Formats numbers using WordPress i18n if available.
	 *
	 * @param int $value Numeric value.
	 * @return string
	 */
	private function format_number( $value ) {
		if ( function_exists( 'number_format_i18n' ) ) {
			return (string) number_format_i18n( (int) $value );
		}

		return number_format( (int) $value );
	}
}
