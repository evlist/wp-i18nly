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
use WP_I18nly\Export\TranslationFileExporter;
use WP_I18nly\Export\TranslationInstaller;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the "Save and install" and "Save and download" buttons, the choice about doubtful
 * translations, and the result notice.
 *
 * The buttons are submit buttons of the form of the post: they save the translation like "Save Draft"
 * does, and the controller then installs the files or asks this screen to start the download. The box
 * never contains a form of its own: it would be nested in the form of the post.
 */
class TranslationExportMetaBox {
	private const POST_TYPE = 'i18nly_translation';

	/**
	 * ID of the element holding the buttons.
	 */
	public const CONTROLS_ID = 'i18nly-export-controls';

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

		$this->render_choice( $this->controller->count_unvalidated( $translation_id ) );

		$has_saved_strings = $this->controller->count_translated_strings( $translation_id ) > 0;
		$can_archive       = TranslationFileExporter::can_create_archives();

		echo '<div id="' . esc_attr( self::CONTROLS_ID ) . '" data-saved="' . esc_attr( $has_saved_strings ? '1' : '0' ) . '"><p>';
		$this->render_button( 'install', __( 'Save and install on this site', 'i18nly' ), true, ! $has_saved_strings, false );
		echo '</p><p>';
		$this->render_button( 'download', __( 'Save and download', 'i18nly' ), false, ! $has_saved_strings || ! $can_archive, ! $can_archive );
		echo '</p></div>';

		if ( ! $can_archive ) {
			echo '<p class="description">' . esc_html__( 'Download is not available: the PHP zip extension is missing on this server.', 'i18nly' ) . '</p>';
		}

		echo '<p class="description">' . esc_html__( 'The buttons are enabled as soon as a string is translated. Both save the translation first.', 'i18nly' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Install makes the translation available on this site. Download gives a ZIP with all the files, to use on another site.', 'i18nly' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Strings without translation are left out, and so is a plural string unless all its forms are translated.', 'i18nly' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'A language pack from WordPress.org may replace the installed files when it is updated. A file not created by I18nly is kept with the suffix .i18nly-backup.', 'i18nly' ) . '</p>';

		$this->render_enabling_script();
	}

	/**
	 * Renders the choice about the translations that are not validated.
	 *
	 * The radio buttons are not "required": the form is also the one of "Save Draft", which must not be
	 * blocked. When no choice was made the controller saves the translation and asks to choose.
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
		) . '</strong><br />' . esc_html__( 'Drafts, machine suggestions and suspect translations may be wrong. Before saving and installing or downloading, choose what to do with them:', 'i18nly' ) . '</p>';
		echo '<p><label><input type="radio" name="' . esc_attr( TranslationExportController::CHOICE_FIELD ) . '" value="' . esc_attr( TranslationExportController::CHOICE_EXCLUDE ) . '" /> ' . esc_html__( 'Leave them out', 'i18nly' ) . '</label><br />';
		echo '<label><input type="radio" name="' . esc_attr( TranslationExportController::CHOICE_FIELD ) . '" value="' . esc_attr( TranslationExportController::CHOICE_INCLUDE ) . '" /> ' . esc_html__( 'Include them (flagged fuzzy in the PO file)', 'i18nly' ) . '</label></p></fieldset>';
	}

	/**
	 * Renders one submit button of the post form.
	 *
	 * @param string $after What to do after saving: "install" or "download".
	 * @param string $label Label.
	 * @param bool   $primary Whether it is the primary button.
	 * @param bool   $disabled Whether the button is disabled.
	 * @param bool   $always_disabled Whether the button stays disabled whatever the translation.
	 * @return void
	 */
	private function render_button( $after, $label, $primary, $disabled, $always_disabled ) {
		echo '<button type="submit" class="button' . ( $primary ? ' button-primary' : '' ) . '" name="' . esc_attr( TranslationExportController::AFTER_SAVE_FIELD ) . '" value="' . esc_attr( $after ) . '"' . ( $disabled ? ' disabled="disabled"' : '' ) . ( $always_disabled ? ' data-always-disabled="1"' : '' ) . '>' . esc_html( $label ) . '</button>';
	}

	/**
	 * Enables the buttons when a translation text exists, saved or typed, since the buttons save it.
	 *
	 * The state is refreshed when something is typed and when the pointer or the focus reaches the box,
	 * because texts set by the machine translation do not always fire an input event.
	 *
	 * @return void
	 */
	private function render_enabling_script() {
		echo '<script>( function () {'
			. 'var box = document.getElementById( ' . wp_json_encode( self::CONTROLS_ID ) . ' );'
			. 'if ( ! box ) { return; }'
			. 'var buttons = box.querySelectorAll( "button" );'
			. 'function refresh() {'
			. 'var typed = Array.prototype.some.call( document.querySelectorAll( ".i18nly-translation-input" ), function ( input ) { return "" !== String( input.value ).trim(); } );'
			. 'var enabled = "1" === box.getAttribute( "data-saved" ) || typed;'
			. 'Array.prototype.forEach.call( buttons, function ( button ) { button.disabled = ! enabled || "1" === button.getAttribute( "data-always-disabled" ); } );'
			. '}'
			. 'document.addEventListener( "input", refresh );'
			. 'document.addEventListener( "change", refresh );'
			. 'box.addEventListener( "mouseover", refresh );'
			. 'box.addEventListener( "focusin", refresh );'
			. 'refresh();'
			. '} )();</script>';
	}

	/**
	 * Starts the download after the translation was saved: loads the archive in a hidden frame, so that the screen stays.
	 *
	 * @return void
	 */
	public function render_download_trigger() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! is_object( $screen ) || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only; the download itself checks its own nonce.
		$choice  = isset( $_GET[ TranslationExportController::DOWNLOAD_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ TranslationExportController::DOWNLOAD_ARG ] ) ) : '';
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $post_id <= 0 || ! in_array( $choice, array( TranslationExportController::CHOICE_INCLUDE, TranslationExportController::CHOICE_EXCLUDE ), true ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$url = $this->controller->get_download_url( $post_id, TranslationExportController::CHOICE_INCLUDE === $choice );

		echo '<script>( function () { var frame = document.createElement( "iframe" ); frame.style.display = "none"; frame.src = ' . wp_json_encode( esc_url_raw( $url ) ) . '; document.body.appendChild( frame ); } )();</script>';
	}

	/**
	 * Shows the result of an installation, of a download or of a refused request.
	 *
	 * @return void
	 */
	public function render_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! is_object( $screen ) || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$code     = isset( $_GET[ TranslationExportController::RESULT_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ TranslationExportController::RESULT_ARG ] ) ) : '';
		$download = isset( $_GET[ TranslationExportController::DOWNLOAD_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ TranslationExportController::DOWNLOAD_ARG ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$messages = array(
			TranslationInstaller::INSTALLED                    => array( 'success', __( 'Translation saved and installed in the languages directory.', 'i18nly' ) ),
			TranslationInstaller::FILESYSTEM_ERROR             => array( 'error', __( 'Translation saved, but the languages directory cannot be written: WordPress needs direct file access or file system credentials.', 'i18nly' ) ),
			TranslationInstaller::WRITE_ERROR                  => array( 'error', __( 'Translation saved, but a translation file could not be written.', 'i18nly' ) ),
			TranslationExportController::CONFIRMATION_REQUIRED => array( 'warning', __( 'Translation saved. Some translations are not validated: choose whether to include them or to leave them out, then use the button again.', 'i18nly' ) ),
			'forbidden'                                        => array( 'error', __( 'Translation saved, but you are not allowed to install languages.', 'i18nly' ) ),
			'not_exportable'                                   => array( 'error', __( 'Translation saved. There is nothing to export: no string is translated, or the translated ones were left out.', 'i18nly' ) ),
		);

		if ( '' === $code && '' !== $download ) {
			$code     = 'download';
			$messages = array( 'download' => array( 'success', __( 'Translation saved. The download starts.', 'i18nly' ) ) );
		}

		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}

		echo '<div class="notice notice-' . esc_attr( $messages[ $code ][0] ) . ' is-dismissible"><p>' . esc_html( $messages[ $code ][1] ) . '</p></div>';
	}
}
