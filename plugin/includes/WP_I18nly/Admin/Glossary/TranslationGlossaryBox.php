<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Glossaries box of the translation edit screen.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin\Glossary;

use WP_I18nly\Glossary\TranslationGlossaries;

defined( 'ABSPATH' ) || exit;

/**
 * Lets the translator choose which glossaries of the language the translation uses.
 */
class TranslationGlossaryBox {
	private const POST_TYPE = 'i18nly_translation';

	private const META_TARGET_LANGUAGE = '_i18nly_target_language';

	private const NONCE_FIELD = 'i18nly_glossaries_nonce';

	/**
	 * Glossaries of translations.
	 *
	 * @var TranslationGlossaries|null
	 */
	private $glossaries;

	/**
	 * Constructor.
	 *
	 * @param TranslationGlossaries|null $glossaries Optional glossaries of translations.
	 */
	public function __construct( TranslationGlossaries $glossaries = null ) {
		$this->glossaries = $glossaries;
	}

	/**
	 * Returns the glossaries of translations.
	 *
	 * @return TranslationGlossaries
	 */
	private function glossaries() {
		if ( ! $this->glossaries instanceof TranslationGlossaries ) {
			$this->glossaries = new TranslationGlossaries();
		}

		return $this->glossaries;
	}

	/**
	 * Returns the nonce action of the box.
	 *
	 * @param int $post_id Translation post ID.
	 * @return string
	 */
	public static function get_nonce_action( $post_id ) {
		return 'i18nly_translation_glossaries_' . (int) $post_id;
	}

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'register_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save' ), 10, 1 );
	}

	/**
	 * Registers the side meta box.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		add_meta_box( 'i18nly-translation-glossaries', __( 'Glossaries', 'i18nly' ), array( $this, 'render' ), self::POST_TYPE, 'side', 'default' );
	}

	/**
	 * Renders the box.
	 *
	 * @param object $post Translation post.
	 * @return void
	 */
	public function render( $post ) {
		$post_id       = (int) $post->ID;
		$target_locale = (string) get_post_meta( $post_id, self::META_TARGET_LANGUAGE, true );

		if ( '' === $target_locale ) {
			echo '<p>' . esc_html__( 'Save the translation with a language to choose its glossaries.', 'i18nly' ) . '</p>';
			return;
		}

		$usable = $this->glossaries()->list_usable_glossaries( $target_locale );
		$linked = $this->glossaries()->get_linked_ids( $post_id );

		wp_nonce_field( self::get_nonce_action( $post_id ), self::NONCE_FIELD, false );

		if ( empty( $usable ) ) {
			echo '<p>' . esc_html__( 'No glossary exists for this language yet.', 'i18nly' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'Terms of the selected glossaries are shown next to the source strings, and the translations are checked against them. When a term is in several glossaries, the first one by identifier is used.', 'i18nly' ) . '</p>';

			foreach ( $usable as $glossary ) {
				echo '<p><label><input type="checkbox" name="i18nly_glossary_ids[]" value="' . esc_attr( (string) $glossary['id'] ) . '"' . checked( in_array( (int) $glossary['id'], $linked, true ), true, false ) . ' /> ' . esc_html( $glossary['slug'] ) . '</label></p>';
			}
		}

		echo '<p><a href="' . esc_url( GlossaryAdminController::get_screen_url() ) . '">' . esc_html__( 'Manage the glossaries', 'i18nly' ) . '</a></p>';
	}

	/**
	 * Saves the selection with the translation.
	 *
	 * @param int $post_id Translation post ID.
	 * @return void
	 */
	public function save( $post_id ) {
		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::get_nonce_action( (int) $post_id ) ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', (int) $post_id ) ) {
			return;
		}

		$posted_ids = isset( $_POST['i18nly_glossary_ids'] ) && is_array( $_POST['i18nly_glossary_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['i18nly_glossary_ids'] ) ) : array();

		$this->glossaries()->set_linked_ids( (int) $post_id, $posted_ids, (string) get_post_meta( (int) $post_id, self::META_TARGET_LANGUAGE, true ) );
	}
}
