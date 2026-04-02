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
	 * @return array{success: bool, translation?: string, review_token?: string, message?: string, rate_limited?: bool, retry_after_ms?: int}
	 */
	public function translate_item( $source_text, $source_locale, $target_locale, $context = '' ) {
		$batch_result = $this->translate_batch(
			array(
				array(
					'text'    => (string) $source_text,
					'context' => (string) $context,
				),
			),
			$source_locale,
			$target_locale
		);

		if ( empty( $batch_result['success'] ) ) {
			return $batch_result;
		}

		if ( ! isset( $batch_result['items'][0] ) || ! is_array( $batch_result['items'][0] ) ) {
			return array(
				'success' => false,
				'message' => __( 'DeepL returned an unexpected response format.', 'i18nly' ),
			);
		}

		return $batch_result['items'][0];
	}

	/**
	 * Translates multiple source text items in one DeepL request.
	 *
	 * @param array<int, array<string, mixed>|string> $items Source items. Each item can be string or array{text: string, context?: string}.
	 * @param string                                   $source_locale WordPress source locale (e.g. en_US).
	 * @param string                                   $target_locale WordPress target locale (e.g. fr_FR).
	 * @return array{success: bool, items?: array<int, array<string, mixed>>, message?: string, rate_limited?: bool, retry_after_ms?: int}
	 */
	public function translate_batch( array $items, $source_locale, $target_locale ) {
		$normalized_items = array();
		$texts            = array();

		foreach ( $items as $item ) {
			$text    = '';
			$context = '';

			if ( is_array( $item ) ) {
				$text    = isset( $item['text'] ) ? (string) $item['text'] : '';
				$context = isset( $item['context'] ) ? trim( (string) $item['context'] ) : '';
			} else {
				$text = (string) $item;
			}

			if ( '' === trim( $text ) ) {
				return array(
					'success' => false,
					'message' => __( 'The source text is empty.', 'i18nly' ),
				);
			}

			$normalized_items[] = array(
				'text'    => $text,
				'context' => $context,
			);
			$texts[]            = $text;
		}

		if ( empty( $normalized_items ) ) {
			return array(
				'success' => false,
				'message' => __( 'Batch payload is empty.', 'i18nly' ),
			);
		}

		$endpoint    = $this->resolve_translate_endpoint();
		$source_lang = $this->to_deepl_source_lang( (string) $source_locale );
		$target_lang = $this->to_deepl_target_lang( (string) $target_locale );
		$context     = $this->resolve_batch_context( $normalized_items );

		$body = $this->build_translate_body( $texts, $source_lang, $target_lang, $context );

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

		return $this->normalize_translate_response( $response, count( $normalized_items ) );
	}

	/**
	 * Extracts Retry-After header as milliseconds.
	 *
	 * @param array<string, mixed> $response HTTP response array.
	 * @return int
	 */
	private function extract_retry_after_ms( array $response ) {
		if ( ! isset( $response['headers'] ) || ! is_array( $response['headers'] ) ) {
			return 0;
		}

		$headers = $response['headers'];
		$value   = '';

		if ( isset( $headers['retry-after'] ) ) {
			$value = (string) $headers['retry-after'];
		} elseif ( isset( $headers['Retry-After'] ) ) {
			$value = (string) $headers['Retry-After'];
		}

		$value = trim( $value );

		if ( '' === $value ) {
			return 0;
		}

		if ( ctype_digit( $value ) ) {
			return max( 0, (int) $value * 1000 );
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return 0;
		}

		$delta_seconds = $timestamp - time();

		if ( $delta_seconds <= 0 ) {
			return 0;
		}

		return (int) $delta_seconds * 1000;
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

	/**
	 * Builds request body for one DeepL /translate call.
	 *
	 * @param array<int, string> $texts Source texts.
	 * @param string             $source_lang DeepL source language code.
	 * @param string             $target_lang DeepL target language code.
	 * @param string             $context Optional context used when common to all items.
	 * @return string
	 */
	private function build_translate_body( array $texts, $source_lang, $target_lang, $context = '' ) {
		$parts = array(
			'source_lang=' . rawurlencode( (string) $source_lang ),
			'target_lang=' . rawurlencode( (string) $target_lang ),
		);

		foreach ( $texts as $text ) {
			$parts[] = 'text=' . rawurlencode( (string) $text );
		}

		$context = trim( (string) $context );

		if ( '' !== $context ) {
			$parts[] = 'context=' . rawurlencode( $context );
		}

		return implode( '&', $parts );
	}

	/**
	 * Returns one shared context for the full batch when possible.
	 *
	 * DeepL applies one context value to the full request.
	 *
	 * @param array<int, array{text: string, context: string}> $items Normalized items.
	 * @return string
	 */
	private function resolve_batch_context( array $items ) {
		$contexts = array();

		foreach ( $items as $item ) {
			$context = isset( $item['context'] ) ? trim( (string) $item['context'] ) : '';

			if ( '' !== $context ) {
				$contexts[] = $context;
			}
		}

		$contexts = array_values( array_unique( $contexts ) );

		if ( 1 !== count( $contexts ) ) {
			return '';
		}

		return (string) $contexts[0];
	}

	/**
	 * Normalizes DeepL HTTP response.
	 *
	 * @param mixed $response HTTP response array.
	 * @param int   $expected_count Expected number of translated items.
	 * @return array{success: bool, items?: array<int, array<string, mixed>>, message?: string, rate_limited?: bool, retry_after_ms?: int}
	 */
	private function normalize_translate_response( $response, $expected_count ) {
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

		if ( ! is_array( $response ) ) {
			return array(
				'success' => false,
				'message' => __( 'DeepL returned an unexpected response format.', 'i18nly' ),
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

			if ( 429 === $code ) {
				$retry_after_ms = $this->extract_retry_after_ms( $response );

				return array(
					'success'        => false,
					'rate_limited'   => true,
					'retry_after_ms' => $retry_after_ms,
					'message'        => __( 'DeepL rate limit reached. Please retry later.', 'i18nly' ),
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
		) {
			return array(
				'success' => false,
				'message' => __( 'DeepL returned an unexpected response format.', 'i18nly' ),
			);
		}

		$translations = $payload['translations'];

		if ( count( $translations ) !== (int) $expected_count ) {
			return array(
				'success' => false,
				'message' => __( 'DeepL returned an unexpected number of translated items.', 'i18nly' ),
			);
		}

		$items = array();

		foreach ( $translations as $entry ) {
			$items[] = array(
				'success'      => true,
				'translation'  => is_array( $entry ) && isset( $entry['text'] ) ? (string) $entry['text'] : '',
				'review_token' => 'ai_draft_ok',
			);
		}

		return array(
			'success' => true,
			'items'   => $items,
		);
	}
}
