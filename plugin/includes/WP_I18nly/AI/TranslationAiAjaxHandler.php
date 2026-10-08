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
 * Handles AJAX requests for AI-assisted single-item and batch translation.
 *
 * It validates the request (parameters, capability, nonce, translation, API key, quota guard) and
 * answers with JSON. The translation itself is done by a TranslationBatchTranslator.
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
	 * Optional callback deciding whether translation requests are allowed.
	 *
	 * @var callable|null
	 */
	private $can_translate_callback;

	/**
	 * Batch translator.
	 *
	 * @var TranslationBatchTranslator
	 */
	private $translator;

	/**
	 * Constructor.
	 *
	 * @param callable      $get_translation_callback Callback returning translation row for one ID.
	 * @param callable      $get_api_key_callback Callback returning saved DeepL API key.
	 * @param callable|null $translate_callable Optional translation callable override (defaults to DeepLClient).
	 * @param callable|null $persist_status_callback Optional callback to persist translated status.
	 * @param callable|null $throttle_wait_callback Optional callback enforcing throttling.
	 * @param callable|null $translate_batch_callable Optional batch translation callable override.
	 * @param callable|null $rate_limit_callback Optional callback adjusting shared delay after 429.
	 * @param callable|null $post_batch_success_callback Optional callback triggered after a successful batch.
	 * @param callable|null $can_translate_callback Optional callback deciding whether new translations can be sent.
	 */
	public function __construct(
		callable $get_translation_callback,
		callable $get_api_key_callback,
		$translate_callable = null,
		$persist_status_callback = null,
		$throttle_wait_callback = null,
		$translate_batch_callable = null,
		$rate_limit_callback = null,
		$post_batch_success_callback = null,
		$can_translate_callback = null
	) {
		$this->get_translation_callback = $get_translation_callback;
		$this->get_api_key_callback     = $get_api_key_callback;
		$this->can_translate_callback   = is_callable( $can_translate_callback ) ? $can_translate_callback : null;
		$this->translator               = new TranslationBatchTranslator(
			$get_api_key_callback,
			$translate_callable,
			$persist_status_callback,
			$throttle_wait_callback,
			$translate_batch_callable,
			$rate_limit_callback,
			$post_batch_success_callback
		);
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

		if ( ! $this->can_edit_translation( $translation_id ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'i18nly_translate_entry_' . $translation_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
			return;
		}

		$target_locale = $this->resolve_target_locale( $translation_id );

		if ( null === $target_locale ) {
			return;
		}

		$batch_result = $this->translator->translate(
			$translation_id,
			$target_locale,
			array(
				array(
					'source_entry_id' => $source_entry_id,
					'form_index'      => $form_index,
					'source_text'     => $source_text,
					'witness_n'       => $has_witness_n ? $witness_n : null,
				),
			)
		);

		if ( empty( $batch_result['success'] ) ) {
			$this->send_batch_failure( $batch_result );
			return;
		}

		$results = isset( $batch_result['results'] ) && is_array( $batch_result['results'] ) ? $batch_result['results'] : array();
		$result  = isset( $results[0] ) && is_array( $results[0] ) ? $results[0] : array();

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

		if ( ! $this->can_edit_translation( $translation_id ) ) {
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

		$target_locale = $this->resolve_target_locale( $translation_id );

		if ( null === $target_locale ) {
			return;
		}

		$batch_result = $this->translator->translate( $translation_id, $target_locale, $items );

		if ( empty( $batch_result['success'] ) ) {
			$this->send_batch_failure(
				$batch_result,
				array(
					'batch_index'   => $batch_index,
					'total_batches' => $total_batches,
				)
			);
			return;
		}

		$results      = isset( $batch_result['results'] ) && is_array( $batch_result['results'] ) ? $batch_result['results'] : array();
		$usage_status = isset( $batch_result['usage_status'] ) && is_array( $batch_result['usage_status'] )
			? $batch_result['usage_status']
			: null;
		$usage_html   = isset( $batch_result['usage_html'] ) && is_string( $batch_result['usage_html'] )
			? $batch_result['usage_html']
			: '';

		$response_data = array(
			'results'       => $results,
			'batch_index'   => $batch_index,
			'total_batches' => $total_batches,
		);

		if ( is_array( $usage_status ) ) {
			$response_data['usage_status'] = $usage_status;
		}

		if ( '' !== trim( $usage_html ) ) {
			$response_data['usage_html'] = $usage_html;
		}

		wp_send_json_success( $response_data );
	}

	/**
	 * Checks the translation ID and that the current user may edit it, answering with an error otherwise.
	 *
	 * The nonce is verified by the callers, next to the request data they read.
	 *
	 * @param int $translation_id Translation ID.
	 * @return bool True when the user may edit the translation.
	 */
	private function can_edit_translation( $translation_id ) {
		if ( $translation_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid translation id.' ), 400 );
			return false;
		}

		if ( ! current_user_can( 'edit_post', $translation_id ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
			return false;
		}

		return true;
	}

	/**
	 * Resolves the target locale of a translation once translating is possible, answering with an error otherwise.
	 *
	 * The translation must have a target locale, an API key must be configured and the quota guard must allow
	 * new requests.
	 *
	 * @param int $translation_id Translation ID.
	 * @return string|null Target locale, or null when an error response was sent.
	 */
	private function resolve_target_locale( $translation_id ) {
		$get_translation = $this->get_translation_callback;
		$translation     = $get_translation( $translation_id );

		if ( ! is_array( $translation ) || empty( $translation['target_language'] ) ) {
			wp_send_json_error( array( 'message' => 'Translation target locale is missing.' ), 400 );
			return null;
		}

		$get_api_key = $this->get_api_key_callback;
		$api_key     = (string) $get_api_key();

		if ( '' === $api_key ) {
			wp_send_json_error( array( 'message' => 'No DeepL API key configured.' ), 400 );
			return null;
		}

		$guard_result = $this->guard_translation_allowed();

		if ( ! empty( $guard_result['blocked'] ) ) {
			$error_data = array(
				'message' => isset( $guard_result['message'] ) ? (string) $guard_result['message'] : 'Translation is currently blocked.',
			);

			if ( isset( $guard_result['settings_url'] ) ) {
				$error_data['settings_url'] = (string) $guard_result['settings_url'];
			}

			if ( isset( $guard_result['settings_label'] ) ) {
				$error_data['settings_label'] = (string) $guard_result['settings_label'];
			}

			wp_send_json_error(
				$error_data,
				isset( $guard_result['status'] ) ? (int) $guard_result['status'] : 403
			);
			return null;
		}

		return (string) $translation['target_language'];
	}

	/**
	 * Answers with the error matching a failed batch translation.
	 *
	 * @param array<string, mixed> $batch_result Failed translator result.
	 * @param array<string, mixed> $rate_limit_data Extra data added to a rate limit error.
	 * @return void
	 */
	private function send_batch_failure( array $batch_result, array $rate_limit_data = array() ) {
		if ( ! empty( $batch_result['rate_limited'] ) ) {
			wp_send_json_error(
				array_merge(
					array(
						'message'        => isset( $batch_result['message'] ) ? (string) $batch_result['message'] : 'Rate limit reached.',
						'retry_after_ms' => isset( $batch_result['retry_after_ms'] ) ? (int) $batch_result['retry_after_ms'] : 0,
					),
					$rate_limit_data
				),
				429
			);
			return;
		}

		wp_send_json_error(
			array(
				'message' => isset( $batch_result['message'] ) ? (string) $batch_result['message'] : 'Translation failed.',
			),
			500
		);
	}

	/**
	 * Evaluates whether new translation requests can be sent.
	 *
	 * @return array<string, mixed>
	 */
	private function guard_translation_allowed() {
		$guard_callback = $this->can_translate_callback;

		if ( ! is_callable( $guard_callback ) ) {
			return array(
				'blocked' => false,
			);
		}

		try {
			$result = call_user_func( $guard_callback );
		} catch ( \Throwable $throwable ) {
			unset( $throwable );

			return array(
				'blocked' => false,
			);
		}

		if ( ! is_array( $result ) ) {
			return array(
				'blocked' => false,
			);
		}

		return array(
			'blocked'        => ! empty( $result['blocked'] ),
			'message'        => isset( $result['message'] ) ? (string) $result['message'] : '',
			'status'         => isset( $result['status'] ) ? (int) $result['status'] : 403,
			'settings_url'   => isset( $result['settings_url'] ) ? (string) $result['settings_url'] : '',
			'settings_label' => isset( $result['settings_label'] ) ? (string) $result['settings_label'] : '',
		);
	}
}
