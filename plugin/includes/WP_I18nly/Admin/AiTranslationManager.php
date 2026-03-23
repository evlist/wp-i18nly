<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * AI translation management helper.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin;

use WP_I18nly\AI\TranslationAiAjaxHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Manages AI translation configuration and AJAX handler.
 */
class AiTranslationManager {
	/**
	 * Callback to get a translation by ID.
	 *
	 * @var callable
	 */
	private $get_translation_callback;

	/**
	 * Constructor.
	 *
	 * @param callable $get_translation_callback Callback returning translation row.
	 */
	public function __construct( $get_translation_callback ) {
		$this->get_translation_callback = $get_translation_callback;
	}

	/**
	 * Checks if DeepL API key exists.
	 *
	 * @return bool
	 */
	public function has_deepl_api_key() {
		return '' !== $this->get_api_key();
	}

	/**
	 * Returns saved DeepL API key.
	 *
	 * @return string
	 */
	private function get_api_key() {
		$settings = new TranslationSettingsPage();
		return $settings->get_saved_api_key();
	}

	/**
	 * Returns translation AJAX handler.
	 *
	 * @return TranslationAiAjaxHandler
	 */
	public function get_ajax_handler() {
		$get_translation = $this->get_translation_callback;
		return new TranslationAiAjaxHandler(
			$get_translation,
			function () {
				return $this->get_api_key();
			},
			null,
			function ( $translation_id, $source_entry_id, $form_index, $translation, $status ) {
				$schema_manager = new \WP_I18nly\Storage\SourceSchemaManager();
				$schema_manager->maybe_upgrade();

				$repository = new \WP_I18nly\Storage\SourceWpdbRepository( $schema_manager );
				$repository->upsert_translated_entry(
					(int) $translation_id,
					(int) $source_entry_id,
					(int) $form_index,
					(string) $translation,
					gmdate( 'Y-m-d H:i:s' ),
					(string) $status
				);
			},
			function () {
				$throttle = new \WP_I18nly\Support\FileLockThrottle(
					'i18nly_ai_translate',
					$this->get_translate_min_delay_ms()
				);
				$throttle->wait_until_allowed();
			}
		);
	}

	/**
	 * Extends base script config with AI values.
	 *
	 * @param array<string, mixed> $base_config Base config.
	 * @param int                  $translation_id Translation ID.
	 * @return array<string, mixed>
	 */
	public function extend_script_config( array $base_config, $translation_id ) {
		return array_merge(
			$base_config,
			array(
				'translateAction'               => 'i18nly_ai_translate_entry',
				'translateNonce'                => wp_create_nonce( 'i18nly_translate_entry_' . (int) $translation_id ),
				'translateBatchAction'          => 'i18nly_ai_translate_entry',
				'translateBatchNonce'           => wp_create_nonce( 'i18nly_translate_entry_' . (int) $translation_id ),
				'hasDeeplKey'                   => $this->has_deepl_api_key(),
				'translateBatchSize'            => $this->get_translate_batch_size(),
				'translateMaxItemsPerRequest'   => $this->get_translate_max_items_per_request(),
				'translateBackoffBaseMs'        => $this->get_translate_backoff_base_ms(),
				'translateMaxConcurrentBatches' => $this->get_translate_max_concurrent_batches(),
			)
		);
	}

	/**
	 * Returns client-side batch size for bulk translation.
	 *
	 * @return int
	 */
	private function get_translate_batch_size() {
		$max_items = $this->get_translate_max_items_per_request();

		$size = function_exists( 'apply_filters' )
			? (int) apply_filters( 'i18nly_ai_translate_batch_size', $max_items )
			: $max_items;

		return max( 1, min( $max_items, $size ) );
	}

	/**
	 * Returns max number of source strings allowed per request.
	 *
	 * DeepL accepts up to 50 texts per /translate request.
	 *
	 * @return int
	 */
	private function get_translate_max_items_per_request() {
		$max_items = function_exists( 'apply_filters' )
			? (int) apply_filters( 'i18nly_ai_translate_max_items_per_request', 50 )
			: 50;

		return max( 1, min( 50, $max_items ) );
	}

	/**
	 * Returns base delay used by client exponential backoff.
	 *
	 * @return int
	 */
	private function get_translate_backoff_base_ms() {
		$delay_ms = function_exists( 'apply_filters' )
			? (int) apply_filters( 'i18nly_ai_translate_backoff_base_ms', 1000 )
			: 1000;

		return max( 100, $delay_ms );
	}

	/**
	 * Returns max number of batches processed in parallel client-side.
	 *
	 * @return int
	 */
	private function get_translate_max_concurrent_batches() {
		$concurrency = function_exists( 'apply_filters' )
			? (int) apply_filters( 'i18nly_ai_translate_max_concurrent_batches', 1 )
			: 1;

		return max( 1, $concurrency );
	}

	/**
	 * Returns minimal delay between server-side requests.
	 *
	 * @return int
	 */
	private function get_translate_min_delay_ms() {
		$delay_ms = function_exists( 'apply_filters' )
			? (int) apply_filters( 'i18nly_ai_translate_min_delay_ms', 250 )
			: 250;

		return max( 0, $delay_ms );
	}
}
