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
	 * Callable used to perform one batch translation.
	 * Receives (array $items, string $source_locale, string $target_locale)
	 * and returns array{success: bool, items?: array<int, array<string, mixed>>, message?: string, rate_limited?: bool, retry_after_ms?: int}.
	 *
	 * @var callable
	 */
	private $translate_batch_callable;

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
	 * Optional callback updating shared throttle delay after rate limiting.
	 *
	 * @var callable|null
	 */
	private $rate_limit_callback;

	/**
	 * Optional callback invoked after a successful provider batch.
	 *
	 * @var callable|null
	 */
	private $post_batch_success_callback;

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
	 */
	public function __construct(
		callable $get_translation_callback,
		callable $get_api_key_callback,
		$translate_callable = null,
		$persist_status_callback = null,
		$throttle_wait_callback = null,
		$translate_batch_callable = null,
		$rate_limit_callback = null,
		$post_batch_success_callback = null
	) {
		$single_translate_callable = $translate_callable;

		$this->get_translation_callback = $get_translation_callback;
		$this->get_api_key_callback     = $get_api_key_callback;
		$this->translate_callable       = is_callable( $single_translate_callable )
			? $single_translate_callable
			: function ( $source_text, $source_locale, $target_locale, $context = '' ) use ( &$get_api_key_callback ) {
				$api_key = call_user_func( $get_api_key_callback );
				$client  = new DeepLClient( $api_key );
				return $client->translate_item( $source_text, $source_locale, $target_locale, $context );
			};
		if ( is_callable( $translate_batch_callable ) ) {
			$this->translate_batch_callable = $translate_batch_callable;
		} elseif ( is_callable( $single_translate_callable ) ) {
			$this->translate_batch_callable = function ( array $items, $source_locale, $target_locale ) use ( $single_translate_callable ) {
				$results = array();

				foreach ( $items as $item ) {
					$text    = isset( $item['text'] ) ? (string) $item['text'] : '';
					$context = isset( $item['context'] ) ? (string) $item['context'] : '';
					$result  = call_user_func( $single_translate_callable, $text, $source_locale, $target_locale, $context );

					if ( empty( $result['success'] ) && ! empty( $result['rate_limited'] ) ) {
						return array(
							'success'        => false,
							'rate_limited'   => true,
							'retry_after_ms' => isset( $result['retry_after_ms'] ) ? (int) $result['retry_after_ms'] : 0,
							'message'        => isset( $result['message'] ) ? (string) $result['message'] : 'Rate limit reached.',
						);
					}

					$results[] = array(
						'success'      => ! empty( $result['success'] ),
						'translation'  => isset( $result['translation'] ) ? (string) $result['translation'] : '',
						'review_token' => isset( $result['review_token'] ) ? (string) $result['review_token'] : '',
						'message'      => isset( $result['message'] ) ? (string) $result['message'] : '',
					);
				}

				return array(
					'success' => true,
					'items'   => $results,
				);
			};
		} else {
			$this->translate_batch_callable = function ( array $items, $source_locale, $target_locale ) use ( &$get_api_key_callback ) {
				$api_key = call_user_func( $get_api_key_callback );
				$client  = new DeepLClient( $api_key );
				return $client->translate_batch( $items, $source_locale, $target_locale );
			};
		}
		$this->persist_status_callback  = is_callable( $persist_status_callback ) ? $persist_status_callback : null;
		$this->throttle_wait_callback   = is_callable( $throttle_wait_callback ) ? $throttle_wait_callback : null;
		$this->rate_limit_callback      = is_callable( $rate_limit_callback ) ? $rate_limit_callback : null;
		$this->post_batch_success_callback = is_callable( $post_batch_success_callback ) ? $post_batch_success_callback : null;
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
		$batch_result  = $this->translate_items_batch(
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
			if ( ! empty( $batch_result['rate_limited'] ) ) {
				wp_send_json_error(
					array(
						'message'        => isset( $batch_result['message'] ) ? (string) $batch_result['message'] : 'Rate limit reached.',
						'retry_after_ms' => isset( $batch_result['retry_after_ms'] ) ? (int) $batch_result['retry_after_ms'] : 0,
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
		$batch_result  = $this->translate_items_batch( $translation_id, $target_locale, $items );

		if ( empty( $batch_result['success'] ) ) {
			if ( ! empty( $batch_result['rate_limited'] ) ) {
				wp_send_json_error(
					array(
						'message'        => isset( $batch_result['message'] ) ? (string) $batch_result['message'] : 'Rate limit reached.',
						'retry_after_ms' => isset( $batch_result['retry_after_ms'] ) ? (int) $batch_result['retry_after_ms'] : 0,
						'batch_index'    => $batch_index,
						'total_batches'  => $total_batches,
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
			return;
		}

		$results = isset( $batch_result['results'] ) && is_array( $batch_result['results'] ) ? $batch_result['results'] : array();
		$usage_status = isset( $batch_result['usage_status'] ) && is_array( $batch_result['usage_status'] )
			? $batch_result['usage_status']
			: null;
		$usage_html = isset( $batch_result['usage_html'] ) && is_string( $batch_result['usage_html'] )
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
	 * Translates one or many items in one provider call and maps results to UI payload.
	 *
	 * @param int                           $translation_id Translation ID.
	 * @param string                        $target_locale Target locale.
	 * @param array<int, array<string, mixed>> $raw_items Incoming payload items.
	 * @return array{success: bool, results?: array<int, array<string, mixed>>, message?: string, rate_limited?: bool, retry_after_ms?: int}
	 */
	private function translate_items_batch( $translation_id, $target_locale, array $raw_items ) {
		$prepared_items = array();
		$results        = array();

		foreach ( $raw_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$source_entry_id = isset( $item['source_entry_id'] ) ? absint( $item['source_entry_id'] ) : 0;
			$form_index      = isset( $item['form_index'] ) ? absint( $item['form_index'] ) : 0;
			$source_text     = isset( $item['source_text'] ) ? sanitize_text_field( (string) $item['source_text'] ) : '';
			$witness_n       = null;

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

			$placeholder   = $this->extract_single_printf_placeholder( $source_text );
			$has_witness_n = null !== $witness_n;
			$prepared_text = $source_text;

			if ( '' !== $placeholder && $has_witness_n && false === strpos( $source_text, (string) $witness_n ) ) {
				$prepared_text = preg_replace( '/' . preg_quote( $placeholder, '/' ) . '/', (string) $witness_n, $source_text, 1 );
				$prepared_text = is_string( $prepared_text ) ? $prepared_text : $source_text;
			}

			$prepared_items[] = array(
				'source_entry_id' => $source_entry_id,
				'form_index'      => $form_index,
				'source_text'     => $source_text,
				'prepared_text'   => $prepared_text,
				'placeholder'     => $placeholder,
				'witness_n'       => $witness_n,
				'context'         => $this->build_deepl_context( $source_text, $has_witness_n ? (int) $witness_n : -1 ),
			);
		}

		if ( empty( $prepared_items ) ) {
			return array(
				'success' => true,
				'results' => $results,
			);
		}

		$wait_callback = $this->throttle_wait_callback;

		if ( is_callable( $wait_callback ) ) {
			try {
				call_user_func( $wait_callback );
			} catch ( \Throwable $throwable ) {
				unset( $throwable );
			}
		}

		$translate_batch = $this->translate_batch_callable;
		$provider_result = call_user_func(
			$translate_batch,
			array_map(
				function ( array $prepared_item ) {
					return array(
						'text'    => (string) $prepared_item['prepared_text'],
						'context' => (string) $prepared_item['context'],
					);
				},
				$prepared_items
			),
			'en_US',
			$target_locale
		);

		if ( empty( $provider_result['success'] ) && ! empty( $provider_result['rate_limited'] ) ) {
			$retry_after_ms = isset( $provider_result['retry_after_ms'] ) ? (int) $provider_result['retry_after_ms'] : 0;
			$rate_callback  = $this->rate_limit_callback;

			if ( is_callable( $rate_callback ) ) {
				try {
					$retry_after_ms = (int) call_user_func( $rate_callback, $retry_after_ms );
				} catch ( \Throwable $throwable ) {
					unset( $throwable );
				}
			}

			return array(
				'success'        => false,
				'rate_limited'   => true,
				'retry_after_ms' => max( 0, $retry_after_ms ),
				'message'        => isset( $provider_result['message'] ) ? (string) $provider_result['message'] : 'Rate limit reached.',
			);
		}

		$provider_items = isset( $provider_result['items'] ) && is_array( $provider_result['items'] ) ? array_values( $provider_result['items'] ) : array();

		if ( empty( $provider_result['success'] ) || count( $provider_items ) !== count( $prepared_items ) ) {
			foreach ( $prepared_items as $prepared_item ) {
				$results[] = array(
					'source_entry_id' => (int) $prepared_item['source_entry_id'],
					'form_index'      => (int) $prepared_item['form_index'],
					'success'         => false,
					'message'         => isset( $provider_result['message'] ) ? (string) $provider_result['message'] : 'Translation failed.',
				);
			}

			return array(
				'success' => true,
				'results' => $results,
			);
		}

		foreach ( $prepared_items as $index => $prepared_item ) {
			$provider_item = isset( $provider_items[ $index ] ) && is_array( $provider_items[ $index ] ) ? $provider_items[ $index ] : array();
			$success       = ! empty( $provider_item['success'] );
			$message       = isset( $provider_item['message'] ) ? (string) $provider_item['message'] : '';
			$translation   = isset( $provider_item['translation'] ) ? (string) $provider_item['translation'] : '';
			$status        = $this->review_token_to_translated_status( isset( $provider_item['review_token'] ) ? (string) $provider_item['review_token'] : '' );

			if ( $success ) {
				$placeholder   = isset( $prepared_item['placeholder'] ) ? (string) $prepared_item['placeholder'] : '';
				$witness_n     = isset( $prepared_item['witness_n'] ) ? $prepared_item['witness_n'] : null;
				$source_text   = isset( $prepared_item['source_text'] ) ? (string) $prepared_item['source_text'] : '';
				$prepared_text = isset( $prepared_item['prepared_text'] ) ? (string) $prepared_item['prepared_text'] : '';
				$has_witness_n = null !== $witness_n;

				if ( '' !== $placeholder && $has_witness_n && $prepared_text !== $source_text ) {
					$pattern  = '/(?<!\\d)' . preg_quote( (string) $witness_n, '/' ) . '(?!\\d)/';
					$restored = preg_replace( $pattern, $placeholder, $translation, 1 );
					if ( is_string( $restored ) ) {
						if ( $restored === $translation ) {
							$status = 'suspect';
						}
						$translation = $restored;
					} else {
						$status = 'suspect';
					}
				} elseif ( '' !== $placeholder && ! $has_witness_n ) {
					$status = 'suspect';
				}

				if ( is_callable( $this->persist_status_callback ) ) {
					call_user_func(
						$this->persist_status_callback,
						$translation_id,
						(int) $prepared_item['source_entry_id'],
						(int) $prepared_item['form_index'],
						$translation,
						$status
					);
				}
			}

			$results[] = array(
				'source_entry_id' => (int) $prepared_item['source_entry_id'],
				'form_index'      => (int) $prepared_item['form_index'],
				'success'         => $success,
				'translation'     => $success ? $translation : '',
				'review_token'    => $success ? $status : '',
				'message'         => $message,
			);
		}

		$post_batch_callback = $this->post_batch_success_callback;
		$post_batch_meta     = array();

		if ( is_callable( $post_batch_callback ) ) {
			$success_count = 0;

			foreach ( $results as $result_row ) {
				if ( is_array( $result_row ) && ! empty( $result_row['success'] ) ) {
					++$success_count;
				}
			}

			try {
				$callback_result = call_user_func(
					$post_batch_callback,
					array(
						'translation_id' => (int) $translation_id,
						'target_locale'  => (string) $target_locale,
						'items_count'    => count( $prepared_items ),
						'success_count'  => $success_count,
					)
				);

				if ( is_array( $callback_result ) ) {
					$post_batch_meta = $callback_result;
				}
			} catch ( \Throwable $throwable ) {
				unset( $throwable );
			}
		}

		$response = array(
			'success' => true,
			'results' => $results,
		);

		if ( isset( $post_batch_meta['usage_status'] ) && is_array( $post_batch_meta['usage_status'] ) ) {
			$response['usage_status'] = $post_batch_meta['usage_status'];
		}

		if ( isset( $post_batch_meta['usage_html'] ) && is_string( $post_batch_meta['usage_html'] ) ) {
			$response['usage_html'] = $post_batch_meta['usage_html'];
		}

		return $response;
	}

	/**
	 * Maps review token to translated-entry status stored in DB.
	 *
	 * @param string $review_token Review token.
	 * @return string
	 */
	private function review_token_to_translated_status( $review_token ) {
		$review_token = (string) $review_token;

		if (
			'suspect' === $review_token
			|| 'draft_ai_needs_fix' === $review_token
			|| 'ai_draft_needs_fix' === $review_token
			|| 'draft_ai_suspect' === $review_token
			|| 'ai_draft_suspect' === $review_token
		) {
			return 'suspect';
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
