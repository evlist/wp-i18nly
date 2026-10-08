<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation duplicate guard.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin;

use WP_I18nly\Support\TranslationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Detects and rejects a second translation for the same source slug and target language.
 *
 * Detection queries posts with `post_status => any`, which excludes the trash: a trashed
 * translation does not block creating a new one.
 */
class TranslationDuplicateGuard {
	/**
	 * Translation post type.
	 *
	 * @var string
	 */
	private $post_type;

	/**
	 * Source slug post meta key.
	 *
	 * @var string
	 */
	private $meta_source_slug;

	/**
	 * Target language post meta key.
	 *
	 * @var string
	 */
	private $meta_target_language;

	/**
	 * New translation screen slug.
	 *
	 * @var string
	 */
	private $new_screen_slug;

	/**
	 * Translation repository.
	 *
	 * @var TranslationRepository|null
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param string                     $post_type Translation post type.
	 * @param string                     $meta_source_slug Source slug post meta key.
	 * @param string                     $meta_target_language Target language post meta key.
	 * @param string                     $new_screen_slug New translation screen slug.
	 * @param TranslationRepository|null $repository Optional translation repository.
	 */
	public function __construct( $post_type, $meta_source_slug, $meta_target_language, $new_screen_slug, TranslationRepository $repository = null ) {
		$this->post_type            = (string) $post_type;
		$this->meta_source_slug     = (string) $meta_source_slug;
		$this->meta_target_language = (string) $meta_target_language;
		$this->new_screen_slug      = (string) $new_screen_slug;
		$this->repository           = $repository;
	}

	/**
	 * Finds existing translation with same source and target language.
	 *
	 * @param string $source_slug Source slug.
	 * @param string $target_language Target language.
	 * @param int    $current_post_id Current post ID.
	 * @return int Existing translation ID, or 0 when none exists.
	 */
	public function find_duplicate_translation_id( $source_slug, $target_language, $current_post_id ) {
		$posts = get_posts(
			array(
				'post_type'   => $this->post_type,
				'post_status' => 'any',
				'numberposts' => -1,
			)
		);

		foreach ( $posts as $post ) {
			if ( ! is_object( $post ) || ! isset( $post->ID, $post->post_type ) ) {
				continue;
			}

			if ( $this->post_type !== (string) $post->post_type ) {
				continue;
			}

			$post_id = (int) $post->ID;
			if ( $post_id <= 0 || $post_id === (int) $current_post_id ) {
				continue;
			}

			if (
				(string) get_post_meta( $post_id, $this->meta_source_slug, true ) === $source_slug
				&& (string) get_post_meta( $post_id, $this->meta_target_language, true ) === $target_language
			) {
				return $post_id;
			}
		}

		return 0;
	}

	/**
	 * Handles duplicate translation creation attempt.
	 *
	 * @param int    $new_post_id New post ID.
	 * @param int    $existing_translation_id Existing translation ID.
	 * @param string $source_slug Source slug.
	 * @param string $target_language Target language.
	 * @return void
	 */
	public function handle_duplicate_translation_creation( $new_post_id, $existing_translation_id, $source_slug, $target_language ) {
		wp_trash_post( (int) $new_post_id );

		$repository = $this->repository instanceof TranslationRepository
			? $this->repository
			: new TranslationRepository();
		$open_url   = $repository->get_edit_url( (int) $existing_translation_id );
		$cancel_url = admin_url( $this->new_screen_slug );

		$message = sprintf(
			/* translators: 1: source slug, 2: target language. */
			__( 'A translation already exists for %1$s in %2$s.', 'i18nly' ),
			esc_html( $source_slug ),
			esc_html( $target_language )
		);

		$message .= '<p>';
		$message .= '<a class="button button-primary" href="' . esc_url( $open_url ) . '">' . esc_html__( 'Open existing translation', 'i18nly' ) . '</a> ';
		$message .= '<a class="button" href="' . esc_url( $cancel_url ) . '">' . esc_html__( 'Cancel', 'i18nly' ) . '</a>';
		$message .= '</p>';

		wp_die( wp_kses_post( $message ), esc_html__( 'Duplicate translation', 'i18nly' ), array( 'response' => 409 ) );
	}
}
