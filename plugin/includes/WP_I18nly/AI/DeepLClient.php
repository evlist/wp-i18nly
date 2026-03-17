<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * DeepL translation client.
 *
 * @package I18nly
 */

namespace WP_I18nly\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Translates one item via the DeepL API.
 */
class DeepLClient {
	/**
	 * DeepL API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * HTTP POST callable.
	 *
	 * @var callable
	 */
	private $http_post;

	/**
	 * Constructor.
	 *
	 * @param string        $api_key DeepL API key.
	 * @param callable|null $http_post HTTP POST callable override.
	 */
	public function __construct( $api_key, $http_post = null ) {
		$this->api_key   = trim( (string) $api_key );
		$this->http_post = is_callable( $http_post )
			? $http_post
			: function ( $url, array $args ) {
				return wp_remote_post( (string) $url, $args );
			};
	}

	/**
	 * Translates one source text item.
	 *
	 * @param string $source_text Source text to translate.
	 * @param string $source_locale WordPress source locale (e.g. en_US).
	 * @param string $target_locale WordPress target locale (e.g. fr_FR).
	 * @param string $context Optional context hint for disambiguation.
	 * @return array{success: bool, translation?: string, review_token?: string, message?: string}
	 */
	public function translate_item( $source_text, $source_locale, $target_locale, $context = '' ) {
		$source_text = (string) $source_text;
		$context     = trim( (string) $context );

		if ( '' === trim( $source_text ) ) {
			return array(
				'success' => false,
				'message' => __( 'The source text is empty.', 'i18nly' ),
			);
		}

		$endpoint    = $this->resolve_translate_endpoint();
		$source_lang = $this->to_deepl_source_lang( (string) $source_locale );
		$target_lang = $this->to_deepl_target_lang( (string) $target_locale );

		$params = array(
			'text'        => $source_text,
			'source_lang' => $source_lang,
			'target_lang' => $target_lang,
		);

		if ( '' !== $context ) {
			$params['context'] = $context;
		}

		$body = http_build_query( $params );

		$response = call_user_func(
			$this->http_post,
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'DeepL-Auth-Key ' . $this->api_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => $body,
				'timeout' => 15,
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

		$raw_body = isset( $response['body'] ) ? (string) $response['body'] : '';
		$payload  = json_decode( $raw_body, true );

		if ( ! is_array( $payload )
			|| ! isset( $payload['translations'] )
			|| ! is_array( $payload['translations'] )
			|| empty( $payload['translations'] )
		) {
			return array(
				'success' => false,
				'message' => __( 'DeepL returned an unexpected response format.', 'i18nly' ),
			);
		}

		$first       = $payload['translations'][0];
		$translation = isset( $first['text'] ) ? (string) $first['text'] : '';

		return array(
			'success'      => true,
			'translation'  => $translation,
			'review_token' => 'ai_draft_ok',
		);
	}

	/**
	 * Resolves the DeepL translate endpoint URL based on key type.
	 *
	 * @return string
	 */
	private function resolve_translate_endpoint() {
		$is_free_key = (bool) preg_match( '/:fx$/', $this->api_key );

		if ( $is_free_key ) {
			return 'https://api-free.deepl.com/v2/translate';
		}

		return 'https://api.deepl.com/v2/translate';
	}

	/**
	 * Converts a WordPress locale to a DeepL source language code.
	 *
	 * @param string $wp_locale WordPress locale (e.g. en_US).
	 * @return string DeepL two-letter source lang code (e.g. EN).
	 */
	private function to_deepl_source_lang( $wp_locale ) {
		$parts = explode( '_', (string) $wp_locale, 2 );

		return strtoupper( $parts[0] );
	}

	/**
	 * Converts a WordPress locale to a DeepL target language code.
	 *
	 * DeepL requires regional variants for EN, PT and ZH.
	 *
	 * @param string $wp_locale WordPress locale (e.g. fr_FR, pt_BR, en_US).
	 * @return string DeepL target lang code (e.g. FR, PT-BR, EN-US).
	 */
	private function to_deepl_target_lang( $wp_locale ) {
		$parts  = explode( '_', (string) $wp_locale, 2 );
		$lang   = strtoupper( $parts[0] );
		$region = count( $parts ) > 1 ? strtoupper( $parts[1] ) : '';

		if ( 'EN' === $lang ) {
			return 'GB' === $region ? 'EN-GB' : 'EN-US';
		}

		if ( 'PT' === $lang ) {
			return 'BR' === $region ? 'PT-BR' : 'PT-PT';
		}

		if ( 'ZH' === $lang ) {
			return in_array( $region, array( 'TW', 'HK' ), true ) ? 'ZH-HANT' : 'ZH-HANS';
		}

		return $lang;
	}
}
