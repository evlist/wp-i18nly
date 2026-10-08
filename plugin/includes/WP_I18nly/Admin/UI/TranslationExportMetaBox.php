<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Export box of the translation edit screen.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin\UI;

use WP_I18nly\Admin\TranslationExportController;
use WP_I18nly\Export\TranslationInstaller;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the buttons to download or install the files of a translation, the choice about doubtful
 * translations, and the result notice.
 */
class TranslationExportMetaBox {
	/**
	 * Translation post type.
	 */
	private const POST_TYPE = 'i18nly_translation';

	/**
	 * Controller handling the requests.
	 *
	 * @var TranslationExportController
	 */
	private $controller;

	/**
	 * Constructor.
	 *
	 * @param TranslationExportController $controller Controller.
	 */
	public function __construct( TranslationExportController $controller ) {
		$this->controller = $controller;
	}

	/**
	 * Registers the side meta box.
	 *
	 * @return void
	 */
	public function register() {
		add_meta_box( 'i18nly-translation-export', __( 'Export', 'i18nly' ), array( $this, 'render' ), self::POST_TYPE, 'side', 'default' );
	}

	/**
	 * Renders the box.
	 *
	 * @param object $post Translation post.
	 * @return void
	 */
	public function render( $post ) {
		$translation_id = (int) $post->ID;
		$translation    = $this->controller->get_translation( $translation_id );

		if ( null === $translation || '' === $translation['source_slug'] || '' === $translation['target_language'] ) {
			echo '<p>' . esc_html__( 'Save the translation with a plugin and a language to enable the export.', 'i18nly' ) . '</p>';
			return;
		}

		$doubtful = $this->controller->count_unvalidated( $translation_id );
		$action   = admin_url( 'admin-post.php' );

		echo '<form method="post" action="' . esc_url( $action ) . '">';
		wp_nonce_field( TranslationExportController::get_nonce_action( $translation_id ), '_wpnonce', false );
		echo '<input type="hidden" name="translation_id" value="' . esc_attr( (string) $translation_id ) . '" />';

		$this->render_choice( $doubtful );

		echo '<p>';
		$this->render_button( TranslationExportController::ACTION, __( 'Download MO', 'i18nly' ), 'mo' );
		echo ' ';
		$this->render_button( TranslationExportController::ACTION, __( 'Download PO', 'i18nly' ), 'po' );

		$script_state = $this->controller->get_script_files_state( $translation_id );

		if ( 'available' === $script_state ) {
			echo ' ';
			$this->render_button( TranslationExportController::ACTION, __( 'Download JSON (ZIP)', 'i18nly' ), 'json' );
		}

		echo '</p>';

		if ( 'no_translated_script_string' === $script_state ) {
			echo '<p class="description">' . esc_html__( 'The JSON download for JavaScript files appears when a string used by a JavaScript file of the plugin is translated.', 'i18nly' ) . '</p>';
		} elseif ( 'no_archive_support' === $script_state ) {
			echo '<p class="description">' . esc_html__( 'The JSON download is not available: the PHP zip extension is missing on this server. "Install on this site" still writes the JSON files.', 'i18nly' ) . '</p>';
		}

		echo '<p>';
		$this->render_button( TranslationExportController::INSTALL_ACTION, __( 'Install on this site', 'i18nly' ), '', true );
		echo '</p>';
		echo '<p class="description">' . esc_html__( 'Install writes the MO and PO files in wp-content/languages/plugins/, where WordPress looks for the translations of the plugin. A language pack from WordPress.org may replace them when it is updated. A file not created by I18nly is kept with the suffix .i18nly-backup.', 'i18nly' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Entries without translation are left out. In the MO and JSON files, plural entries are left out unless all their forms are translated. The JSON files are the translations of the JavaScript files of the plugin; install them on the site, or unpack the ZIP into wp-content/languages/plugins/.', 'i18nly' ) . '</p>';
		echo '</form>';
	}

	/**
	 * Renders the mandatory choice about the translations that are not validated.
	 *
	 * @param int $doubtful Number of entries having a translation that is not validated.
	 * @return void
	 */
	private function render_choice( $doubtful ) {
		if ( $doubtful <= 0 ) {
			return;
		}

		echo '<fieldset><p><strong>' . esc_html(
			sprintf(
				/* translators: %d: number of entries. */
				_n( '%d entry has a translation that is not validated.', '%d entries have a translation that is not validated.', $doubtful, 'i18nly' ),
				$doubtful
			)
		) . '</strong><br />' . esc_html__( 'Drafts, machine suggestions and suspect translations may be wrong. Choose what to do with them:', 'i18nly' ) . '</p>';
		echo '<p><label><input type="radio" name="unvalidated" value="' . esc_attr( TranslationExportController::CHOICE_EXCLUDE ) . '" required /> ' . esc_html__( 'Leave them out', 'i18nly' ) . '</label><br />';
		echo '<label><input type="radio" name="unvalidated" value="' . esc_attr( TranslationExportController::CHOICE_INCLUDE ) . '" required /> ' . esc_html__( 'Include them (flagged fuzzy in the PO file)', 'i18nly' ) . '</label></p></fieldset>';
	}

	/**
	 * Renders one submit button posting to an admin-post action.
	 *
	 * @param string $action Admin-post action.
	 * @param string $label Label.
	 * @param string $format Format sent with the button, if any.
	 * @param bool   $primary Whether it is the primary button.
	 * @return void
	 */
	private function render_button( $action, $label, $format, $primary = false ) {
		echo '<button type="submit" class="button' . ( $primary ? ' button-primary' : '' ) . '" formaction="' . esc_url( admin_url( 'admin-post.php?action=' . $action ) ) . '"';

		if ( '' !== $format ) {
			echo ' name="format" value="' . esc_attr( $format ) . '"';
		}

		echo '>' . esc_html( $label ) . '</button>';
	}

	/**
	 * Shows the result of an installation or a refused request.
	 *
	 * @return void
	 */
	public function render_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! is_object( $screen ) || self::POST_TYPE !== $screen->post_type || ! isset( $_GET[ TranslationExportController::RESULT_ARG ] ) ) {
			return;
		}

		$messages = array(
			TranslationInstaller::INSTALLED                  => array( 'success', __( 'Translation installed in the languages directory.', 'i18nly' ) ),
			TranslationInstaller::FILESYSTEM_ERROR           => array( 'error', __( 'The languages directory cannot be written: WordPress needs direct file access or file system credentials.', 'i18nly' ) ),
			TranslationInstaller::WRITE_ERROR                => array( 'error', __( 'A translation file could not be written.', 'i18nly' ) ),
			TranslationExportController::CONFIRMATION_REQUIRED => array( 'warning', __( 'Some translations are not validated: choose whether to include them or to leave them out, then try again.', 'i18nly' ) ),
			'not_exportable'                                 => array( 'error', __( 'This translation cannot be installed.', 'i18nly' ) ),
		);
		$code     = sanitize_key( wp_unslash( $_GET[ TranslationExportController::RESULT_ARG ] ) );

		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}

		echo '<div class="notice notice-' . esc_attr( $messages[ $code ][0] ) . ' is-dismissible"><p>' . esc_html( $messages[ $code ][1] ) . '</p></div>';
	}
}
