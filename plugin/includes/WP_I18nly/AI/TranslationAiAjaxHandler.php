<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * AI translation AJAX handler.
 *
 * @package I18nly
 */

namespace WP_I18nly\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Handles AJAX requests for AI-assisted single-item translation.
 */
class TranslationAiAjaxHandler {
	/**
	 * Callback returning one translation row by ID.
	 *
	 * @var callable
	 */
	private $get_translation_callback;

	/**
	 * Callback returning the saved DeepL API key.
	 *
	 * @var callable
	 */
	private $get_api_key_callback;

	/**
	 * Callable used to perform the translation.
	 * Receives (string $source_text, string $source_locale, string $target_locale)
	 * and returns array{success: bool, translation?: string, review_token?: string, message?: string}.
	 *
	 * @var callable
	 */
	private $translate_callable;

	/**
	 * Constructor.
	 *
	 * @param callable      $get_translation_callback Callback returning translation row for one ID.
	 * @param callable      $get_api_key_callback Callback returning saved DeepL API key.
	 * @param callable|null $translate_callable Optional translation callable override (defaults to DeepLClient).
	 */
	public function __construct(
		callable $get_translation_callback,
		callable $get_api_key_callback,
		$translate_callable = null
	) {
		$this->get_translation_callback = $get_translation_callback;
		$this->get_api_key_callback     = $get_api_key_callback;
		$this->translate_callable       = is_callable( $translate_callable )
			? $translate_callable
			: function ( $source_text, $source_locale, $target_locale, $context = '' ) use ( &$get_api_key_callback ) {
				$api_key = call_user_func( $get_api_key_callback );
				$client  = new DeepLClient( $api_key );
				return $client->translate_item( $source_text, $source_locale, $target_locale, $context );
			};
	}

	/**
	 * Handles AJAX request to translate one entry form.
	 *
	 * @return void
	 */
	public function handle_translate_entry() {
		if ( ! isset(
			$_POST['translation_id'],
			$_POST['source_entry_id'],
			$_POST['form_index'],
			$_POST['source_text'],
			$_POST['nonce']
		) ) {
			wp_send_json_error( array( 'message' => 'Missing parameters.' ), 400 );
			return;
		}

		$translation_id  = absint( wp_unslash( $_POST['translation_id'] ) );
		$source_entry_id = absint( wp_unslash( $_POST['source_entry_id'] ) );
		$form_index      = absint( wp_unslash( $_POST['form_index'] ) );
		$source_text     = sanitize_text_field( wp_unslash( $_POST['source_text'] ) );
		$witness_raw     = isset( $_POST['witness_n'] ) ? sanitize_text_field( wp_unslash( $_POST['witness_n'] ) ) : '';
		$witness_raw     = trim( (string) $witness_raw );
		$has_witness_n   = '' !== $witness_raw;
		$witness_n       = $has_witness_n ? (int) $witness_raw : 0;
		$nonce           = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

		if ( $translation_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid translation id.' ), 400 );
			return;
		}

		if ( ! current_user_can( 'edit_post', $translation_id ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'i18nly_translate_entry_' . $translation_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
			return;
		}

		$get_translation = $this->get_translation_callback;
		$translation     = $get_translation( $translation_id );

		if ( ! is_array( $translation ) || empty( $translation['target_language'] ) ) {
			wp_send_json_error( array( 'message' => 'Translation target locale is missing.' ), 400 );
			return;
		}

		$get_api_key = $this->get_api_key_callback;
		$api_key     = (string) $get_api_key();

		if ( '' === $api_key ) {
			wp_send_json_error( array( 'message' => 'No DeepL API key configured.' ), 400 );
			return;
		}

		$target_locale = (string) $translation['target_language'];
		$translate     = $this->translate_callable;
		$placeholder   = $this->extract_single_printf_placeholder( $source_text );
		$prepared_text = $source_text;

		// Deterministic fallback for one-placeholder strings:
		// inject witness n before translation to bias MT morphology.
		if ( '' !== $placeholder && $has_witness_n && false === strpos( $source_text, (string) $witness_n ) ) {
			$prepared_text = preg_replace( '/' . preg_quote( $placeholder, '/' ) . '/', (string) $witness_n, $source_text, 1 );
			$prepared_text = is_string( $prepared_text ) ? $prepared_text : $source_text;
		}

		$context = $this->build_deepl_context( $source_text, $witness_n );
		$result  = $translate( $prepared_text, 'en_US', $target_locale, $context );

		// Restore placeholder in translated output when we used witness injection.
		if ( ! empty( $result['success'] ) && isset( $result['translation'] ) && '' !== $placeholder && $has_witness_n && $prepared_text !== $source_text ) {
			$translated            = (string) $result['translation'];
			$pattern               = '/(?<!\\d)' . preg_quote( (string) $witness_n, '/' ) . '(?!\\d)/';
			$restored              = preg_replace( $pattern, $placeholder, $translated, 1 );
			$result['translation'] = is_string( $restored ) ? $restored : $translated;
		}

		if ( empty( $result['success'] ) ) {
			wp_send_json_error(
				array(
					'message' => isset( $result['message'] ) ? (string) $result['message'] : 'Translation failed.',
				),
				500
			);
			return;
		}

		wp_send_json_success(
			array(
				'source_entry_id' => $source_entry_id,
				'form_index'      => $form_index,
				'translation'     => isset( $result['translation'] ) ? (string) $result['translation'] : '',
				'review_token'    => isset( $result['review_token'] ) ? (string) $result['review_token'] : 'ai_draft_ok',
			)
		);
	}

	/**
	 * Builds optional DeepL context for placeholder semantics.
	 *
	 * @param string $source_text Source text.
	 * @param int    $witness_n Representative value for current plural form.
	 * @return string
	 */
	private function build_deepl_context( $source_text, $witness_n ) {
		$source_text = (string) $source_text;
		$has_printf  = 1 === preg_match( '/%([0-9]+\$)?[sd]/', $source_text );

		if ( ! $has_printf ) {
			return '';
		}

		$lines   = array();
		$lines[] = 'Software UI message.';
		$lines[] = 'Keep printf placeholders (%s, %d) unchanged.';

		if ( $witness_n >= 0 ) {
			$lines[] = 'The placeholder represents a numeric count; representative value n=' . (int) $witness_n . '.';
		}

		return implode( ' ', $lines );
	}

	/**
	 * Returns the single printf-style placeholder token when unambiguous.
	 *
	 * @param string $source_text Source text.
	 * @return string
	 */
	private function extract_single_printf_placeholder( $source_text ) {
		$source_text = (string) $source_text;
		$matches     = array();

		if ( 1 !== preg_match_all( '/%(?:[0-9]+\\$)?[sd]/', $source_text, $matches ) ) {
			return '';
		}

		if ( ! isset( $matches[0] ) || 1 !== count( $matches[0] ) ) {
			return '';
		}

		return (string) $matches[0][0];
	}
}
