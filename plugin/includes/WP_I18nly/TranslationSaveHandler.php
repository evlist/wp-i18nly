<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation save handler.
 *
 * @package I18nly
 */

namespace WP_I18nly;

defined( 'ABSPATH' ) || exit;

/**
 * Handles translation save flow orchestration.
 */
class TranslationSaveHandler {
	/**
	 * Translation post type.
	 *
	 * @var string
	 */
	private $post_type;

	/**
	 * Source slug meta key.
	 *
	 * @var string
	 */
	private $meta_source_slug;

	/**
	 * Target language meta key.
	 *
	 * @var string
	 */
	private $meta_target_language;

	/**
	 * Persist entries callback.
	 *
	 * @var callable
	 */
	private $persist_entries_callback;

	/**
	 * Duplicate lookup callback.
	 *
	 * @var callable
	 */
	private $find_duplicate_callback;

	/**
	 * Duplicate handling callback.
	 *
	 * @var callable
	 */
	private $handle_duplicate_callback;

	/**
	 * Constructor.
	 *
	 * @param string   $post_type Translation post type.
	 * @param string   $meta_source_slug Source slug meta key.
	 * @param string   $meta_target_language Target language meta key.
	 * @param callable $persist_entries_callback Persist callback.
	 * @param callable $find_duplicate_callback Duplicate lookup callback.
	 * @param callable $handle_duplicate_callback Duplicate handling callback.
	 */
	public function __construct(
		$post_type,
		$meta_source_slug,
		$meta_target_language,
		$persist_entries_callback,
		$find_duplicate_callback,
		$handle_duplicate_callback
	) {
		$this->post_type                 = (string) $post_type;
		$this->meta_source_slug          = (string) $meta_source_slug;
		$this->meta_target_language      = (string) $meta_target_language;
		$this->persist_entries_callback  = $persist_entries_callback;
		$this->find_duplicate_callback   = $find_duplicate_callback;
		$this->handle_duplicate_callback = $handle_duplicate_callback;
	}

	/**
	 * Handles translation save.
	 *
	 * @param int    $post_id Post ID.
	 * @param object $post Post object.
	 * @param bool   $update Update flag.
	 * @return void
	 */
	public function handle_save( $post_id, $post, $update ) {
		unset( $update );

		if ( ! isset( $post->post_type ) || $this->post_type !== (string) $post->post_type ) {
			return;
		}

		if ( ! isset( $_POST['i18nly_translation_meta_box_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['i18nly_translation_meta_box_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'i18nly_translation_meta_box' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', (int) $post_id ) ) {
			return;
		}

		$existing_source   = (string) get_post_meta( (int) $post_id, $this->meta_source_slug, true );
		$existing_language = (string) get_post_meta( (int) $post_id, $this->meta_target_language, true );
		$is_locked         = '' !== $existing_source || '' !== $existing_language;

		$source_slug = $existing_source;
		if ( ! $is_locked && isset( $_POST['i18nly_plugin_selector'] ) ) {
			$source_slug = sanitize_text_field( wp_unslash( $_POST['i18nly_plugin_selector'] ) );
		}

		$target_language = $existing_language;
		if ( ! $is_locked && isset( $_POST['i18nly_target_language_selector'] ) ) {
			$target_language = sanitize_text_field( wp_unslash( $_POST['i18nly_target_language_selector'] ) );
		}

		if ( ! $is_locked && '' !== $source_slug && '' !== $target_language ) {
			$existing_translation_id = (int) call_user_func( $this->find_duplicate_callback, $source_slug, $target_language, (int) $post_id );

			if ( $existing_translation_id > 0 ) {
				call_user_func( $this->handle_duplicate_callback, (int) $post_id, $existing_translation_id, $source_slug, $target_language );
				return;
			}
		}

		update_post_meta( (int) $post_id, $this->meta_source_slug, $source_slug );
		update_post_meta( (int) $post_id, $this->meta_target_language, $target_language );

		if ( '' !== $source_slug ) {
			$entries_payload = array();
			$payload_json    = filter_input( INPUT_POST, 'i18nly_translation_entries_payload', FILTER_UNSAFE_RAW );

			if ( ! is_string( $payload_json ) && isset( $_POST['i18nly_translation_entries_payload'] ) ) {
				$payload_json = sanitize_textarea_field( wp_unslash( $_POST['i18nly_translation_entries_payload'] ) );
			}

			if ( is_string( $payload_json ) && '' !== $payload_json ) {
				$decoded_payload = json_decode( wp_unslash( $payload_json ), true );

				if ( is_array( $decoded_payload ) ) {
					$entries_payload = $decoded_payload;
				}
			}

			if ( ! empty( $entries_payload ) ) {
				call_user_func(
					$this->persist_entries_callback,
					(int) $post_id,
					$source_slug,
					AdminPageHelper::normalize_translation_entries_payload( $entries_payload )
				);
			}
		}

		$current_title = isset( $post->post_title ) ? (string) $post->post_title : '';
		if ( '' !== trim( $current_title ) || '' === $source_slug || '' === $target_language ) {
			return;
		}

		wp_update_post(
			array(
				'ID'         => (int) $post_id,
				'post_title' => $source_slug . ' → ' . $target_language,
			)
		);
	}
}
