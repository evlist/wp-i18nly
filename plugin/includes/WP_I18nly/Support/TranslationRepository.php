<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation repository adapter.
 *
 * @package I18nly
 */

namespace WP_I18nly\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Provides translation entity queries and URL helpers.
 */
class TranslationRepository {
	/**
	 * Returns one translation row by ID.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $post_type Translation post type.
	 * @param string $meta_source_key Source meta key.
	 * @param string $meta_target_key Target meta key.
	 * @return array<string, mixed>|null
	 */
	public function get_translation( $translation_id, $post_type, $meta_source_key, $meta_target_key ) {
		$translation_post = get_post( (int) $translation_id );
		if ( ! $translation_post || $post_type !== $translation_post->post_type ) {
			return null;
		}

		return array(
			'id'              => (int) $translation_post->ID,
			'source_slug'     => (string) get_post_meta( (int) $translation_post->ID, $meta_source_key, true ),
			'target_language' => (string) get_post_meta( (int) $translation_post->ID, $meta_target_key, true ),
			'created_at'      => $this->get_post_created_at( $translation_post ),
		);
	}

	/**
	 * Returns stable creation date for one post.
	 *
	 * @param object $translation_post Post object.
	 * @return string
	 */
	public function get_post_created_at( $translation_post ) {
		$post_date_gmt = isset( $translation_post->post_date_gmt ) ? (string) $translation_post->post_date_gmt : '';
		if ( '' !== $post_date_gmt && '0000-00-00 00:00:00' !== $post_date_gmt ) {
			return $post_date_gmt;
		}

		return isset( $translation_post->post_date ) ? (string) $translation_post->post_date : '';
	}

	/**
	 * Returns standard edit URL for translation.
	 *
	 * @param int $translation_id Translation ID.
	 * @return string
	 */
	public function get_edit_url( $translation_id ) {
		$edit_url = add_query_arg( 'post', (string) $translation_id, admin_url( 'post.php' ) );

		return add_query_arg( 'action', 'edit', $edit_url );
	}

	/**
	 * Returns native list URL for translations.
	 *
	 * @param string $list_screen_slug List screen slug.
	 * @return string
	 */
	public function get_list_url( $list_screen_slug ) {
		return admin_url( $list_screen_slug );
	}
}
