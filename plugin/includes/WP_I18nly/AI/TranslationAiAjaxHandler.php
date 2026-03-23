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
	 * Optional callback persisting translated-entry status.
	 *
	 * @var callable|null
	 */
	private $persist_status_callback;

	/**
	 * Optional callback waiting for throttle slot before external request.
	 *
	 * @var callable|null
	 */
	private $throttle_wait_callback;

	/**
	 * Constructor.
	 *
	 * @param callable      $get_translation_callback Callback returning translation row for one ID.
	 * @param callable      $get_api_key_callback Callback returning saved DeepL API key.
	 * @param callable|null $translate_callable Optional translation callable override (defaults to DeepLClient).
	 * @param callable|null $persist_status_callback Optional callback to persist translated status.
	 * @param callable|null $throttle_wait_callback Optional callback enforcing throttling.
	 */
	public function __construct(
		callable $get_translation_callback,
		callable $get_api_key_callback,
		$translate_callable = null,
		$persist_status_callback = null,
		$throttle_wait_callback = null
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
		$this->persist_status_callback  = is_callable( $persist_status_callback ) ? $persist_status_callback : null;
		$this->throttle_wait_callback   = is_callable( $throttle_wait_callback ) ? $throttle_wait_callback : null;
	}

	/**
	 * Handles AJAX request to translate one entry form.
	 *
	 * @return void
	 */
	public function handle_translate_entry() {
		if ( isset( $_POST['items_json'] ) ) {
			$this->handle_translate_entries_batch();
			return;
		}

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
		$result        = $this->translate_single_item( $translation_id, $source_entry_id, $form_index, $source_text, $has_witness_n ? $witness_n : null, $target_locale );

		if ( empty( $result['success'] ) ) {
			if ( ! empty( $result['rate_limited'] ) ) {
				wp_send_json_error(
					array(
						'message'        => isset( $result['message'] ) ? (string) $result['message'] : 'Rate limit reached.',
						'retry_after_ms' => isset( $result['retry_after_ms'] ) ? (int) $result['retry_after_ms'] : 0,
					),
					429
				);
				return;
			}

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
				'review_token'    => isset( $result['review_token'] ) ? (string) $result['review_token'] : '',
			)
		);
	}

	/**
	 * Handles AJAX request to translate one batch of entry forms.
	 *
	 * @return void
	 */
	public function handle_translate_entries_batch() {
		if ( ! isset( $_POST['translation_id'], $_POST['items_json'], $_POST['nonce'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing parameters.' ), 400 );
			return;
		}

		$translation_id = absint( wp_unslash( $_POST['translation_id'] ) );
		$nonce          = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
		$batch_index    = isset( $_POST['batch_index'] ) ? absint( wp_unslash( $_POST['batch_index'] ) ) : 0;
		$total_batches  = isset( $_POST['total_batches'] ) ? absint( wp_unslash( $_POST['total_batches'] ) ) : 1;

		if ( $translation_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid translation id.' ), 400 );
			return;
		}

		if ( ! current_user_can( 'edit_post', $translation_id ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
			return;
		}

		if (
			! wp_verify_nonce( $nonce, 'i18nly_translate_entries_batch_' . $translation_id )
			&& ! wp_verify_nonce( $nonce, 'i18nly_translate_entry_' . $translation_id )
		) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
			return;
		}

		$items_json = sanitize_textarea_field( wp_unslash( $_POST['items_json'] ) );
		$items      = json_decode( $items_json, true );

		if ( ! is_array( $items ) || empty( $items ) ) {
			wp_send_json_error( array( 'message' => 'Batch payload is empty.' ), 400 );
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
		$results       = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$source_entry_id = isset( $item['source_entry_id'] ) ? absint( $item['source_entry_id'] ) : 0;
			$form_index      = isset( $item['form_index'] ) ? absint( $item['form_index'] ) : 0;
			$source_text     = isset( $item['source_text'] ) ? sanitize_text_field( (string) $item['source_text'] ) : '';
			$witness_n       = null;
			$item_result     = array();

			if ( isset( $item['witness_n'] ) && '' !== trim( (string) $item['witness_n'] ) ) {
				$witness_n = (int) $item['witness_n'];
			}

			if ( $source_entry_id <= 0 || '' === $source_text ) {
				$results[] = array(
					'source_entry_id' => $source_entry_id,
					'form_index'      => $form_index,
					'success'         => false,
					'message'         => 'Invalid item payload.',
				);
				continue;
			}

			$item_result = $this->translate_single_item( $translation_id, $source_entry_id, $form_index, $source_text, $witness_n, $target_locale );

			if ( ! empty( $item_result['rate_limited'] ) ) {
				wp_send_json_error(
					array(
						'message'        => isset( $item_result['message'] ) ? (string) $item_result['message'] : 'Rate limit reached.',
						'retry_after_ms' => isset( $item_result['retry_after_ms'] ) ? (int) $item_result['retry_after_ms'] : 0,
						'batch_index'    => $batch_index,
						'total_batches'  => $total_batches,
					),
					429
				);
				return;
			}

			$results[] = array(
				'source_entry_id' => $source_entry_id,
				'form_index'      => $form_index,
				'success'         => ! empty( $item_result['success'] ),
				'translation'     => isset( $item_result['translation'] ) ? (string) $item_result['translation'] : '',
				'review_token'    => isset( $item_result['review_token'] ) ? (string) $item_result['review_token'] : '',
				'message'         => isset( $item_result['message'] ) ? (string) $item_result['message'] : '',
			);
		}

		wp_send_json_success(
			array(
				'results'       => $results,
				'batch_index'   => $batch_index,
				'total_batches' => $total_batches,
			)
		);
	}

	/**
	 * Translates one item and optionally persists status.
	 *
	 * @param int      $translation_id Translation ID.
	 * @param int      $source_entry_id Source entry ID.
	 * @param int      $form_index Form index.
	 * @param string   $source_text Source text.
	 * @param int|null $witness_n Optional witness number.
	 * @param string   $target_locale Target locale.
	 * @return array{success: bool, translation?: string, review_token?: string, message?: string}
	 */
	private function translate_single_item( $translation_id, $source_entry_id, $form_index, $source_text, $witness_n, $target_locale ) {
		$translate     = $this->translate_callable;
		$placeholder   = $this->extract_single_printf_placeholder( $source_text );
		$prepared_text = $source_text;
		$wait_callback = $this->throttle_wait_callback;
		$has_witness_n = null !== $witness_n;

		if ( '' !== $placeholder && $has_witness_n && false === strpos( $source_text, (string) $witness_n ) ) {
			$prepared_text = preg_replace( '/' . preg_quote( $placeholder, '/' ) . '/', (string) $witness_n, $source_text, 1 );
			$prepared_text = is_string( $prepared_text ) ? $prepared_text : $source_text;
		}

		$context = $this->build_deepl_context( $source_text, $has_witness_n ? (int) $witness_n : -1 );

		if ( is_callable( $wait_callback ) ) {
			try {
				call_user_func( $wait_callback );
			} catch ( \Throwable $throwable ) {
				unset( $throwable );
			}
		}

		$result = $translate( $prepared_text, 'en_US', $target_locale, $context );
		$status = $this->review_token_to_translated_status( isset( $result['review_token'] ) ? (string) $result['review_token'] : '' );

		if ( ! empty( $result['success'] ) && isset( $result['translation'] ) && '' !== $placeholder && $has_witness_n && $prepared_text !== $source_text ) {
			$translated            = (string) $result['translation'];
			$pattern               = '/(?<!\\d)' . preg_quote( (string) $witness_n, '/' ) . '(?!\\d)/';
			$restored              = preg_replace( $pattern, $placeholder, $translated, 1 );
			$result['translation'] = is_string( $restored ) ? $restored : $translated;

			if ( ! is_string( $restored ) || $restored === $translated ) {
				$status = 'draft_ai_needs_fix';
			}
		} elseif ( '' !== $placeholder && ! $has_witness_n ) {
			$status = 'draft_ai_suspect';
		}

		if ( empty( $result['success'] ) ) {
			return array(
				'success'        => false,
				'message'        => isset( $result['message'] ) ? (string) $result['message'] : 'Translation failed.',
				'rate_limited'   => ! empty( $result['rate_limited'] ),
				'retry_after_ms' => isset( $result['retry_after_ms'] ) ? (int) $result['retry_after_ms'] : 0,
			);
		}

		$result['review_token'] = $status;

		if ( is_callable( $this->persist_status_callback ) ) {
			call_user_func(
				$this->persist_status_callback,
				$translation_id,
				$source_entry_id,
				$form_index,
				isset( $result['translation'] ) ? (string) $result['translation'] : '',
				$status
			);
		}

		return $result;
	}

	/**
	 * Maps review token to translated-entry status stored in DB.
	 *
	 * @param string $review_token Review token.
	 * @return string
	 */
	private function review_token_to_translated_status( $review_token ) {
		$review_token = (string) $review_token;

		if ( 'draft_ai_needs_fix' === $review_token || 'ai_draft_needs_fix' === $review_token ) {
			return 'draft_ai_needs_fix';
		}

		if ( 'draft_ai_suspect' === $review_token || 'ai_draft_suspect' === $review_token ) {
			return 'draft_ai_suspect';
		}

		if ( 'draft_ai' === $review_token || 'ai_draft_ok' === $review_token ) {
			return 'draft_ai';
		}

		if ( 'draft' === $review_token ) {
			return 'draft';
		}

		if ( 'validated' === $review_token ) {
			return 'validated';
		}

		return 'draft_ai';
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
