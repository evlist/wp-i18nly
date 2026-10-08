<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Glossary administration screen.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin\Glossary;

use WP_I18nly\LinguisticResources\GlossaryResource;
use WP_I18nly\LinguisticResources\GlossaryResourceEntry;
use WP_I18nly\Support\LanguageOptionsProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Draws the list of glossaries, and the terms of one glossary with the form to add or edit a term.
 */
class GlossaryScreen {
	/**
	 * Source language proposed for a new glossary.
	 */
	private const DEFAULT_SOURCE_LOCALE = 'en_US';

	/**
	 * Controller handling the forms.
	 *
	 * @var GlossaryAdminController
	 */
	private $controller;

	/**
	 * Constructor.
	 *
	 * @param GlossaryAdminController $controller Controller.
	 */
	public function __construct( GlossaryAdminController $controller ) {
		$this->controller = $controller;
	}

	/**
	 * Remembers validation errors and the submitted values for the next display of the form.
	 *
	 * @param string                $form Form: "create" or "term".
	 * @param array<string, string> $errors Messages indexed by error code.
	 * @param array<string, mixed>  $values Submitted values.
	 * @return void
	 */
	public static function remember_feedback( $form, array $errors, array $values ) {
		set_transient(
			self::feedback_key(),
			array(
				'form'   => (string) $form,
				'errors' => $errors,
				'values' => $values,
			),
			5 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Returns the transient key of the feedback of the current user.
	 *
	 * @return string
	 */
	private static function feedback_key() {
		return 'i18nly_glossary_feedback_' . get_current_user_id();
	}

	/**
	 * Takes the feedback of the previous request for a form, once.
	 *
	 * @param string $form Form: "create" or "term".
	 * @return array{errors: array<string, string>, values: array<string, mixed>}
	 */
	private function take_feedback( $form ) {
		$feedback = get_transient( self::feedback_key() );

		if ( ! is_array( $feedback ) || ! isset( $feedback['form'] ) || $form !== $feedback['form'] ) {
			return array(
				'errors' => array(),
				'values' => array(),
			);
		}

		delete_transient( self::feedback_key() );

		return array(
			'errors' => (array) $feedback['errors'],
			'values' => (array) $feedback['values'],
		);
	}

	/**
	 * Renders the screen: the list, or one glossary when "glossary" is in the query.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only: nothing is changed by these query arguments.
		$glossary_id = isset( $_GET['glossary'] ) ? absint( $_GET['glossary'] ) : 0;
		$term_id     = isset( $_GET['term'] ) ? absint( $_GET['term'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Glossaries', 'i18nly' ) . '</h1>';
		$this->render_notice();

		$glossary = $glossary_id > 0 ? $this->controller->get_repository()->get_glossary( $glossary_id ) : null;

		if ( null !== $glossary ) {
			$this->render_glossary( $glossary, $term_id );
		} else {
			$this->render_list();
		}

		echo '</div>';
	}

	/**
	 * Shows the success message of the previous request, if any.
	 *
	 * @return void
	 */
	private function render_notice() {
		$messages = array(
			'glossary_created' => __( 'Glossary created.', 'i18nly' ),
			'glossary_deleted' => __( 'Glossary deleted.', 'i18nly' ),
			'term_saved'       => __( 'Term saved.', 'i18nly' ),
			'term_deleted'     => __( 'Term deleted.', 'i18nly' ),
		);

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$code = isset( $_GET[ GlossaryAdminController::NOTICE_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ GlossaryAdminController::NOTICE_ARG ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( isset( $messages[ $code ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $code ] ) . '</p></div>';
		}
	}

	/**
	 * Renders the validation errors of a form.
	 *
	 * @param array<string, string> $errors Messages.
	 * @return void
	 */
	private function render_errors( array $errors ) {
		if ( empty( $errors ) ) {
			return;
		}

		echo '<div class="notice notice-error inline"><ul>';

		foreach ( $errors as $message ) {
			echo '<li>' . esc_html( (string) $message ) . '</li>';
		}

		echo '</ul></div>';
	}

	/**
	 * Renders the list of glossaries and the creation form.
	 *
	 * @return void
	 */
	private function render_list() {
		$glossaries = $this->controller->get_repository()->list_glossaries();

		echo '<p>' . esc_html__( 'A glossary lists terms of a source language with the translations to use in a target language.', 'i18nly' ) . '</p>';

		if ( empty( $glossaries ) ) {
			echo '<p>' . esc_html__( 'There is no glossary yet.', 'i18nly' ) . '</p>';
		} else {
			echo '<table class="wp-list-table widefat striped"><thead><tr>';
			echo '<th>' . esc_html__( 'Identifier', 'i18nly' ) . '</th><th>' . esc_html__( 'Languages', 'i18nly' ) . '</th><th>' . esc_html__( 'Actions', 'i18nly' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $glossaries as $glossary ) {
				echo '<tr><td><a href="' . esc_url( GlossaryAdminController::get_screen_url( array( 'glossary' => $glossary['id'] ) ) ) . '">' . esc_html( $glossary['slug'] ) . '</a></td>';
				echo '<td>' . esc_html( $glossary['source_locale'] . ' → ' . $glossary['target_locale'] ) . '</td><td>';
				echo '<a class="button button-small" href="' . esc_url( GlossaryAdminController::get_screen_url( array( 'glossary' => $glossary['id'] ) ) ) . '">' . esc_html__( 'Edit terms', 'i18nly' ) . '</a> ';
				$this->render_delete_form( GlossaryAdminController::ACTION_DELETE, (int) $glossary['id'], 0, __( 'Delete', 'i18nly' ), __( 'Delete this glossary and all its terms?', 'i18nly' ) );
				echo '</td></tr>';
			}

			echo '</tbody></table>';
		}

		$this->render_create_form();
	}

	/**
	 * Renders the glossary creation form.
	 *
	 * @return void
	 */
	private function render_create_form() {
		$feedback = $this->take_feedback( 'create' );
		$values   = $feedback['values'] + array(
			'slug'          => '',
			'source_locale' => self::DEFAULT_SOURCE_LOCALE,
			'target_locale' => '',
		);

		echo '<h2>' . esc_html__( 'Add a glossary', 'i18nly' ) . '</h2>';
		$this->render_errors( $feedback['errors'] );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( GlossaryAdminController::get_nonce_action( GlossaryAdminController::ACTION_CREATE ), '_wpnonce', false );
		echo '<input type="hidden" name="action" value="' . esc_attr( GlossaryAdminController::ACTION_CREATE ) . '" />';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="i18nly-glossary-slug">' . esc_html__( 'Identifier', 'i18nly' ) . '</label></th><td>';
		echo '<input type="text" id="i18nly-glossary-slug" name="slug" class="regular-text" maxlength="100" value="' . esc_attr( (string) $values['slug'] ) . '" required />';
		echo '<p class="description">' . esc_html__( 'Lowercase letters, digits, hyphens and underscores, for example "my-plugin".', 'i18nly' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="i18nly-glossary-source">' . esc_html__( 'Source language', 'i18nly' ) . '</label></th><td>';
		$this->render_locale_select( 'i18nly-glossary-source', 'source_locale', (string) $values['source_locale'], false );
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="i18nly-glossary-target">' . esc_html__( 'Target language', 'i18nly' ) . '</label></th><td>';
		$this->render_locale_select( 'i18nly-glossary-target', 'target_locale', (string) $values['target_locale'], true );
		echo '</td></tr></tbody></table>';
		submit_button( __( 'Add glossary', 'i18nly' ) );
		echo '</form>';
	}

	/**
	 * Renders a language selector.
	 *
	 * @param string $id Element ID.
	 * @param string $name Field name.
	 * @param string $selected Selected locale.
	 * @param bool   $with_empty_choice Whether to propose an empty choice first.
	 * @return void
	 */
	private function render_locale_select( $id, $name, $selected, $with_empty_choice ) {
		$options = array(
			array(
				'value' => self::DEFAULT_SOURCE_LOCALE,
				'label' => 'English (United States)',
			),
		);

		foreach ( ( new LanguageOptionsProvider() )->get_target_language_options( '' ) as $option ) {
			if ( self::DEFAULT_SOURCE_LOCALE !== $option['value'] ) {
				$options[] = $option;
			}
		}

		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" required>';

		if ( $with_empty_choice ) {
			echo '<option value="">' . esc_html__( '— Select —', 'i18nly' ) . '</option>';
		}

		foreach ( $options as $option ) {
			echo '<option value="' . esc_attr( $option['value'] ) . '"' . selected( $selected, $option['value'], false ) . '>' . esc_html( $option['label'] . ' (' . $option['value'] . ')' ) . '</option>';
		}

		echo '</select>';
	}

	/**
	 * Renders a small form posting a deletion.
	 *
	 * @param string $action Admin-post action.
	 * @param int    $glossary_id Glossary ID.
	 * @param int    $term_id Term ID (0 when deleting a glossary).
	 * @param string $label Button label.
	 * @param string $confirmation Confirmation message.
	 * @return void
	 */
	private function render_delete_form( $action, $glossary_id, $term_id, $label, $confirmation ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline" onsubmit="return window.confirm(' . esc_attr( wp_json_encode( $confirmation ) ) . ');">';
		wp_nonce_field( GlossaryAdminController::get_nonce_action( $action, $glossary_id ), '_wpnonce', false );
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
		echo '<input type="hidden" name="glossary_id" value="' . esc_attr( (string) $glossary_id ) . '" />';

		if ( $term_id > 0 ) {
			echo '<input type="hidden" name="term_id" value="' . esc_attr( (string) $term_id ) . '" />';
		}

		echo '<button type="submit" class="button button-small button-link-delete">' . esc_html( $label ) . '</button>';
		echo '</form>';
	}

	/**
	 * Renders one glossary: its terms and the form to add or edit a term.
	 *
	 * @param GlossaryResource $glossary Glossary.
	 * @param int              $term_id Term being edited, or 0.
	 * @return void
	 */
	private function render_glossary( GlossaryResource $glossary, $term_id ) {
		$glossary_id = $glossary->get_resource_id();

		echo '<p><a href="' . esc_url( GlossaryAdminController::get_screen_url() ) . '">&larr; ' . esc_html__( 'All glossaries', 'i18nly' ) . '</a></p>';
		echo '<h2>' . esc_html( $glossary->get_slug() . ' (' . $glossary->get_source_locale() . ' → ' . $glossary->get_target_locale() . ')' ) . '</h2>';

		$entries = $glossary->get_entries();

		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'This glossary has no term yet.', 'i18nly' ) . '</p>';
		} else {
			echo '<table class="wp-list-table widefat striped"><thead><tr>';
			echo '<th>' . esc_html__( 'Term', 'i18nly' ) . '</th><th>' . esc_html__( 'Match', 'i18nly' ) . '</th><th>' . esc_html__( 'Preferred translation', 'i18nly' ) . '</th><th>' . esc_html__( 'Alternatives', 'i18nly' ) . '</th><th>' . esc_html__( 'Note', 'i18nly' ) . '</th><th>' . esc_html__( 'Actions', 'i18nly' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $entries as $entry ) {
				$this->render_term_row( $glossary_id, $entry );
			}

			echo '</tbody></table>';
		}

		$this->render_term_form( $glossary, $term_id );
	}

	/**
	 * Renders one row of the terms table.
	 *
	 * @param int                   $glossary_id Glossary ID.
	 * @param GlossaryResourceEntry $entry Term.
	 * @return void
	 */
	private function render_term_row( $glossary_id, GlossaryResourceEntry $entry ) {
		$preferred  = $entry->get_preferred_target();
		$alternates = array_map(
			static function ( $target ) {
				return $target->get_text();
			},
			$entry->get_alternate_targets()
		);
		$entry_id   = $entry->get_source_entry_id();

		echo '<tr><td>' . esc_html( $entry->get_term() ) . '</td>';
		echo '<td>' . esc_html( 'partial' === $entry->get_match_mode() ? __( 'Partial', 'i18nly' ) : __( 'Exact', 'i18nly' ) ) . '</td>';
		echo '<td>' . esc_html( null === $preferred ? '' : $preferred->get_text() ) . '</td>';
		echo '<td>' . esc_html( implode( ' | ', $alternates ) ) . '</td>';
		echo '<td>' . esc_html( $entry->get_note() ) . '</td><td>';
		echo '<a class="button button-small" href="' . esc_url(
			GlossaryAdminController::get_screen_url(
				array(
					'glossary' => $glossary_id,
					'term' => $entry_id,
				)
			)
		) . '">' . esc_html__( 'Edit', 'i18nly' ) . '</a> ';
		$this->render_delete_form( GlossaryAdminController::ACTION_DELETE_TERM, $glossary_id, $entry_id, __( 'Delete', 'i18nly' ), __( 'Delete this term?', 'i18nly' ) );
		echo '</td></tr>';
	}

	/**
	 * Renders the form adding a term, or editing the term $term_id.
	 *
	 * @param GlossaryResource $glossary Glossary.
	 * @param int              $term_id Term being edited, or 0.
	 * @return void
	 */
	private function render_term_form( GlossaryResource $glossary, $term_id ) {
		$feedback = $this->take_feedback( 'term' );
		$values   = array(
			'term'       => '',
			'match_mode' => 'exact',
			'preferred'  => '',
			'alternates' => array(),
			'note'       => '',
		);

		foreach ( $glossary->get_entries() as $entry ) {
			if ( $term_id > 0 && $entry->get_source_entry_id() === $term_id ) {
				$preferred = $entry->get_preferred_target();
				$values    = array(
					'term'       => $entry->get_term(),
					'match_mode' => $entry->get_match_mode(),
					'preferred'  => null === $preferred ? '' : $preferred->get_text(),
					'alternates' => array_map(
						static function ( $target ) {
							return $target->get_text();
						},
						$entry->get_alternate_targets()
					),
					'note'       => $entry->get_note(),
				);
			}
		}

		$values      = $feedback['values'] + $values;
		$alternates  = is_array( $values['alternates'] ) ? implode( "\n", $values['alternates'] ) : (string) $values['alternates'];
		$glossary_id = $glossary->get_resource_id();
		$editing     = $term_id > 0;

		echo '<h2>' . esc_html( $editing ? __( 'Edit the term', 'i18nly' ) : __( 'Add a term', 'i18nly' ) ) . '</h2>';
		$this->render_errors( $feedback['errors'] );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( GlossaryAdminController::get_nonce_action( GlossaryAdminController::ACTION_SAVE_TERM, $glossary_id ), '_wpnonce', false );
		echo '<input type="hidden" name="action" value="' . esc_attr( GlossaryAdminController::ACTION_SAVE_TERM ) . '" />';
		echo '<input type="hidden" name="glossary_id" value="' . esc_attr( (string) $glossary_id ) . '" />';
		echo '<input type="hidden" name="term_id" value="' . esc_attr( (string) $term_id ) . '" />';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="i18nly-term">' . esc_html__( 'Term', 'i18nly' ) . '</label></th><td><input type="text" id="i18nly-term" name="term" class="regular-text" maxlength="255" value="' . esc_attr( (string) $values['term'] ) . '" required /></td></tr>';
		echo '<tr><th scope="row"><label for="i18nly-match-mode">' . esc_html__( 'Match', 'i18nly' ) . '</label></th><td><select id="i18nly-match-mode" name="match_mode">';
		echo '<option value="exact"' . selected( (string) $values['match_mode'], 'exact', false ) . '>' . esc_html__( 'Exact: the whole text is the term', 'i18nly' ) . '</option>';
		echo '<option value="partial"' . selected( (string) $values['match_mode'], 'partial', false ) . '>' . esc_html__( 'Partial: the term may appear inside a text', 'i18nly' ) . '</option>';
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="i18nly-preferred">' . esc_html__( 'Preferred translation', 'i18nly' ) . '</label></th><td><input type="text" id="i18nly-preferred" name="preferred" class="regular-text" maxlength="255" value="' . esc_attr( (string) $values['preferred'] ) . '" required /></td></tr>';
		echo '<tr><th scope="row"><label for="i18nly-alternates">' . esc_html__( 'Alternative translations', 'i18nly' ) . '</label></th><td><textarea id="i18nly-alternates" name="alternates" class="large-text" rows="3">' . esc_textarea( $alternates ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'One per line, in order of preference.', 'i18nly' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="i18nly-note">' . esc_html__( 'Note', 'i18nly' ) . '</label></th><td><textarea id="i18nly-note" name="note" class="large-text" rows="2" maxlength="1000">' . esc_textarea( (string) $values['note'] ) . '</textarea></td></tr>';
		echo '</tbody></table>';
		submit_button( $editing ? __( 'Save the term', 'i18nly' ) : __( 'Add the term', 'i18nly' ) );

		if ( $editing ) {
			echo '<p><a href="' . esc_url( GlossaryAdminController::get_screen_url( array( 'glossary' => $glossary_id ) ) ) . '">' . esc_html__( 'Cancel', 'i18nly' ) . '</a></p>';
		}

		echo '</form>';
	}
}
