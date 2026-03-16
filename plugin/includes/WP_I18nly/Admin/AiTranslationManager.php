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
				'translateAction' => 'i18nly_ai_translate_entry',
				'translateNonce'  => wp_create_nonce( 'i18nly_translate_entry_' . (int) $translation_id ),
				'hasDeeplKey'     => $this->has_deepl_api_key(),
			)
		);
	}
}
