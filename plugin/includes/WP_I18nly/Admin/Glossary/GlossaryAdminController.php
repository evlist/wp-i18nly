<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Glossary administration: menu entry and form handlers.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin\Glossary;

use WP_I18nly\LinguisticResources\GlossaryResourceRepository;
use WP_I18nly\Support\TranslationTextNormalizer;

defined( 'ABSPATH' ) || exit;

/**
 * Lets an administrator create glossaries and manage their terms.
 *
 * Every change is a plain form posted to admin-post.php with a nonce, handled here, and followed by a
 * redirect to the screen drawn by GlossaryScreen. Validation errors and the submitted values travel in a
 * short-lived transient of the user, so that the form is shown again with the messages.
 */
class GlossaryAdminController {
	public const PAGE_SLUG = 'i18nly-glossaries';

	public const ACTION_CREATE      = 'i18nly_glossary_create';
	public const ACTION_DELETE      = 'i18nly_glossary_delete';
	public const ACTION_SAVE_TERM   = 'i18nly_glossary_save_term';
	public const ACTION_DELETE_TERM = 'i18nly_glossary_delete_term';

	/**
	 * Query argument carrying the code of a success message.
	 */
	public const NOTICE_ARG = 'i18nly_glossary_notice';

	/**
	 * Repository.
	 *
	 * @var GlossaryResourceRepository|null
	 */
	private $repository;

	/**
	 * Screen renderer, set when the hooks are registered.
	 *
	 * @var GlossaryScreen|null
	 */
	private $screen;

	/**
	 * Constructor.
	 *
	 * @param GlossaryResourceRepository|null $repository Optional repository.
	 */
	public function __construct( GlossaryResourceRepository $repository = null ) {
		$this->repository = $repository;
	}

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public function register() {
		$this->screen = new GlossaryScreen( $this );

		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_post_' . self::ACTION_CREATE, array( $this, 'handle_create' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE, array( $this, 'handle_delete' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE_TERM, array( $this, 'handle_save_term' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE_TERM, array( $this, 'handle_delete_term' ) );
	}

	/**
	 * Returns the repository.
	 *
	 * @return GlossaryResourceRepository
	 */
	public function get_repository() {
		if ( ! $this->repository instanceof GlossaryResourceRepository ) {
			$this->repository = new GlossaryResourceRepository();
		}

		return $this->repository;
	}

	/**
	 * Adds the "Glossaries" entry under "Translations".
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=i18nly_translation',
			__( 'Glossaries', 'i18nly' ),
			__( 'Glossaries', 'i18nly' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this->screen, 'render' )
		);
	}

	/**
	 * Returns the URL of the glossaries screen.
	 *
	 * @param array<string, string|int> $arguments Extra query arguments.
	 * @return string
	 */
	public static function get_screen_url( array $arguments = array() ) {
		return add_query_arg( $arguments, admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
	}

	/**
	 * Returns the nonce action of a form.
	 *
	 * @param string $action Form action.
	 * @param int    $glossary_id Glossary ID (0 for the creation form).
	 * @return string
	 */
	public static function get_nonce_action( $action, $glossary_id = 0 ) {
		return $action . '_' . (int) $glossary_id;
	}

	/**
	 * Creates a glossary.
	 *
	 * @return void
	 */
	public function handle_create() {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'i18nly' ), 403 );
		}

		if ( ! wp_verify_nonce( $nonce, self::get_nonce_action( self::ACTION_CREATE ) ) ) {
			wp_die( esc_html__( 'Invalid request.', 'i18nly' ), 400 );
		}

		$values = array(
			'slug'          => $this->read_text( 'slug' ),
			'source_locale' => $this->read_text( 'source_locale' ),
			'target_locale' => $this->read_text( 'target_locale' ),
		);

		$result = $this->get_repository()->create_glossary( $values['slug'], $values['source_locale'], $values['target_locale'] );

		if ( ! $result->is_success() ) {
			GlossaryScreen::remember_feedback( 'create', $result->get_errors(), $values );
			$this->redirect( self::get_screen_url() );
		}

		$this->redirect(
			self::get_screen_url(
				array(
					'glossary' => $result->get_id(),
					self::NOTICE_ARG => 'glossary_created',
				)
			)
		);
	}

	/**
	 * Deletes a glossary with its terms.
	 *
	 * @return void
	 */
	public function handle_delete() {
		$glossary_id = isset( $_POST['glossary_id'] ) ? absint( $_POST['glossary_id'] ) : 0;
		$nonce       = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'i18nly' ), 403 );
		}

		if ( ! wp_verify_nonce( $nonce, self::get_nonce_action( self::ACTION_DELETE, $glossary_id ) ) ) {
			wp_die( esc_html__( 'Invalid request.', 'i18nly' ), 400 );
		}

		$this->get_repository()->delete_glossary( $glossary_id );

		$this->redirect( self::get_screen_url( array( self::NOTICE_ARG => 'glossary_deleted' ) ) );
	}

	/**
	 * Creates or updates a term.
	 *
	 * @return void
	 */
	public function handle_save_term() {
		$glossary_id = isset( $_POST['glossary_id'] ) ? absint( $_POST['glossary_id'] ) : 0;
		$term_id     = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$nonce       = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'i18nly' ), 403 );
		}

		if ( ! wp_verify_nonce( $nonce, self::get_nonce_action( self::ACTION_SAVE_TERM, $glossary_id ) ) ) {
			wp_die( esc_html__( 'Invalid request.', 'i18nly' ), 400 );
		}

		$input = array(
			'term'       => $this->read_text( 'term' ),
			'match_mode' => $this->read_text( 'match_mode' ),
			'preferred'  => $this->read_text( 'preferred' ),
			'alternates' => array_values( array_filter( array_map( 'trim', explode( "\n", $this->read_text( 'alternates' ) ) ), 'strlen' ) ),
			'note'       => $this->read_text( 'note' ),
		);

		$result = $this->get_repository()->save_term( $glossary_id, $input, $term_id );

		if ( ! $result->is_success() ) {
			GlossaryScreen::remember_feedback( 'term', $result->get_errors(), $input + array( 'term_id' => $term_id ) );
			$this->redirect(
				self::get_screen_url(
					array_filter(
						array(
							'glossary' => $glossary_id,
							'term' => $term_id,
						)
					)
				)
			);
		}

		$this->redirect(
			self::get_screen_url(
				array(
					'glossary' => $glossary_id,
					self::NOTICE_ARG => 'term_saved',
				)
			)
		);
	}

	/**
	 * Deletes a term.
	 *
	 * @return void
	 */
	public function handle_delete_term() {
		$glossary_id = isset( $_POST['glossary_id'] ) ? absint( $_POST['glossary_id'] ) : 0;
		$term_id     = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$nonce       = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'i18nly' ), 403 );
		}

		if ( ! wp_verify_nonce( $nonce, self::get_nonce_action( self::ACTION_DELETE_TERM, $glossary_id ) ) ) {
			wp_die( esc_html__( 'Invalid request.', 'i18nly' ), 400 );
		}

		$this->get_repository()->delete_term( $glossary_id, $term_id );

		$this->redirect(
			self::get_screen_url(
				array(
					'glossary' => $glossary_id,
					self::NOTICE_ARG => 'term_deleted',
				)
			)
		);
	}

	/**
	 * Reads a posted text field as typed (the nonce was verified by the caller).
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	private function read_text( $name ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- the callers verify the nonce first.
		$value = isset( $_POST[ $name ] ) ? filter_var( wp_unslash( $_POST[ $name ] ), FILTER_UNSAFE_RAW ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return TranslationTextNormalizer::normalize( $value );
	}

	/**
	 * Redirects and stops.
	 *
	 * @param string $url Target URL.
	 * @return void
	 */
	protected function redirect( $url ) {
		wp_safe_redirect( $url );
		exit;
	}
}
