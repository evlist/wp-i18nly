<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation settings page.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin;

use WP_I18nly\AI\DeepLCredentialsValidator;

defined( 'ABSPATH' ) || exit;

/**
 * Manages Settings > Translations.
 */
class TranslationSettingsPage {
	/**
	 * WordPress option storing translation settings.
	 */
	private const OPTION_NAME = 'i18nly_translation_settings';

	/**
	 * Settings page slug.
	 */
	private const PAGE_SLUG = 'i18nly-translations-settings';

	/**
	 * Settings group key.
	 */
	private const GROUP_KEY = 'i18nly_translation_settings_group';

	/**
	 * Registers settings and settings fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::GROUP_KEY,
			self::OPTION_NAME,
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'i18nly_deepl_section',
			esc_html__( 'DeepL API', 'i18nly' ),
			array( $this, 'render_deepl_section_description' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'i18nly_deepl_api_key',
			esc_html__( 'DeepL API key', 'i18nly' ),
			array( $this, 'render_deepl_api_key_field' ),
			self::PAGE_SLUG,
			'i18nly_deepl_section'
		);
	}

	/**
	 * Registers the settings submenu entry under Settings.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'options-general.php',
			esc_html__( 'Translations', 'i18nly' ),
			esc_html__( 'Translations', 'i18nly' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Sanitizes settings before persistence.
	 *
	 * @param array<string, mixed> $raw Raw submitted settings.
	 * @return array<string, string>
	 */
	public function sanitize_settings( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();

		$api_key = isset( $raw['deepl_api_key'] )
			? sanitize_text_field( (string) $raw['deepl_api_key'] )
			: '';

		if ( '' === $api_key ) {
			$api_key = $this->get_saved_api_key();
		}

		return array(
			'deepl_api_key' => $api_key,
		);
	}

	/**
	 * Renders settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$has_saved_key = '' !== $this->get_saved_api_key();

		echo '<div class="wrap">';
		echo '<style>.i18nly-danger-button{color:#b32d2e!important;border-color:#b32d2e!important}.i18nly-danger-button:hover,.i18nly-danger-button:focus{color:#8a2424!important;border-color:#8a2424!important;background:#fff5f5!important}</style>';
		echo '<h1>' . esc_html__( 'Translations', 'i18nly' ) . '</h1>';
		echo '<p>' . esc_html__( 'Because translating a plugin in WordPress should be as simple as writing a blog post.', 'i18nly' ) . '</p>';

		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP_KEY );
		do_settings_sections( self::PAGE_SLUG );
		echo '<div class="submit" style="display:flex;align-items:center;gap:8px;">';
		submit_button( esc_html__( 'Save changes', 'i18nly' ), 'primary', 'i18nly_save_changes', false );

		if ( $has_saved_key ) {
			echo '<button type="submit" form="i18nly-clear-key-form" id="i18nly_clear_saved_key" name="i18nly_clear_saved_key" class="button button-secondary i18nly-danger-button" onclick="return window.confirm(\'' . esc_js( __( 'Are you sure you want to clear the saved API key?', 'i18nly' ) ) . '\');">' . esc_html__( 'Clear saved key', 'i18nly' ) . '</button>';
		}

		echo '</div>';
		echo '</form>';
		$this->render_clear_key_form();
		$this->render_save_button_state_script();

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Connection test', 'i18nly' ) . '</h2>';
		echo '<p>' . esc_html__( 'Validate your DeepL API key before enabling translation actions.', 'i18nly' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'i18nly_test_deepl_connection_action', 'i18nly_test_deepl_connection_nonce' );
		echo '<input type="hidden" name="action" value="i18nly_test_deepl_connection" />';
		submit_button(
			esc_html__( 'Test connection', 'i18nly' ),
			'secondary',
			'i18nly_test_connection',
			false,
			$has_saved_key
				? array()
				: array(
					'disabled'      => 'disabled',
					'aria-disabled' => 'true',
				)
		);

		if ( ! $has_saved_key ) {
			echo '<p class="description">' . esc_html__( 'Save a DeepL API key first to enable connection testing.', 'i18nly' ) . '</p>';
		}

		echo '</form>';
		echo '</div>';
	}

	/**
	 * Handles admin-post connection test action.
	 *
	 * @return void
	 */
	public function handle_test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'i18nly' ), 403 );
		}

		$nonce = isset( $_POST['i18nly_test_deepl_connection_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['i18nly_test_deepl_connection_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'i18nly_test_deepl_connection_action' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'i18nly' ), 400 );
		}

		$settings  = $this->get_settings();
		$validator = $this->get_credentials_validator();
		$result    = $validator->validate_credentials( isset( $settings['deepl_api_key'] ) ? (string) $settings['deepl_api_key'] : '' );

		add_settings_error(
			'i18nly_deepl_connection',
			'i18nly_deepl_connection_result',
			isset( $result['message'] ) ? (string) $result['message'] : '',
			! empty( $result['success'] ) ? 'success' : 'error'
		);

		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect( $this->get_page_url() . '&settings-updated=true' );
		exit;
	}

	/**
	 * Handles admin-post action to clear saved DeepL key.
	 *
	 * @return void
	 */
	public function handle_clear_api_key() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'i18nly' ), 403 );
		}

		$nonce = isset( $_POST['i18nly_clear_deepl_api_key_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['i18nly_clear_deepl_api_key_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'i18nly_clear_deepl_api_key_action' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'i18nly' ), 400 );
		}

		delete_option( self::OPTION_NAME );

		add_settings_error(
			'i18nly_deepl_connection',
			'i18nly_deepl_connection_cleared',
			esc_html__( 'DeepL API key cleared.', 'i18nly' ),
			'success'
		);

		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect( $this->get_page_url() . '&settings-updated=true' );
		exit;
	}

	/**
	 * Renders DeepL section description.
	 *
	 * @return void
	 */
	public function render_deepl_section_description() {
		echo '<p>' . esc_html__( 'Configure DeepL credentials for AI-assisted translation suggestions.', 'i18nly' ) . '</p>';
	}

	/**
	 * Renders DeepL API key input.
	 *
	 * @return void
	 */
	public function render_deepl_api_key_field() {
		$settings = $this->get_settings();
		$value    = isset( $settings['deepl_api_key'] ) ? (string) $settings['deepl_api_key'] : '';
		$has_key  = '' !== $value;

		echo '<input type="password" id="i18nly-deepl-api-key" name="' . esc_attr( self::OPTION_NAME ) . '[deepl_api_key]" value="" class="regular-text" autocomplete="off" data-has-saved-key="' . esc_attr( $has_key ? '1' : '0' ) . '" />';

		if ( $has_key ) {
			echo '<p class="description">' . esc_html__( 'A DeepL API key is already saved. Enter a new key to replace it.', 'i18nly' ) . '</p>';
		}

		echo '<p class="description">' . esc_html__( 'Paste your DeepL API key.', 'i18nly' ) . '</p>';
	}

	/**
	 * Returns settings array from WordPress options.
	 *
	 * @return array<string, string>
	 */
	private function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			return array();
		}

		return $this->sanitize_settings( $settings );
	}

	/**
	 * Returns the currently saved DeepL API key.
	 *
	 * @return string
	 */
	public function get_saved_api_key() {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) || ! isset( $settings['deepl_api_key'] ) ) {
			return '';
		}

		return sanitize_text_field( (string) $settings['deepl_api_key'] );
	}

	/**
	 * Disables save button when a key exists and field is unchanged.
	 *
	 * @return void
	 */
	private function render_save_button_state_script() {
		echo '<script>';
		echo '( function() {';
		echo 'var apiKeyInput = document.getElementById( "i18nly-deepl-api-key" );';
		echo 'var saveButton = document.getElementById( "i18nly_save_changes" );';
		echo 'if ( ! apiKeyInput || ! saveButton ) { return; }';
		echo 'var hasSavedKey = apiKeyInput.getAttribute( "data-has-saved-key" ) === "1";';
		echo 'var toggleState = function() {';
		echo 'if ( ! hasSavedKey ) {';
		echo 'saveButton.disabled = false;';
		echo 'saveButton.removeAttribute( "aria-disabled" );';
		echo 'return;';
		echo '}';
		echo 'var hasNewValue = apiKeyInput.value.trim() !== "";';
		echo 'saveButton.disabled = ! hasNewValue;';
		echo 'if ( saveButton.disabled ) {';
		echo 'saveButton.setAttribute( "aria-disabled", "true" );';
		echo '} else {';
		echo 'saveButton.removeAttribute( "aria-disabled" );';
		echo '}';
		echo '};';
		echo 'apiKeyInput.addEventListener( "input", toggleState );';
		echo 'toggleState();';
		echo '}() );';
		echo '</script>';
	}

	/**
	 * Renders a delete-style button to clear the saved key.
	 *
	 * @return void
	 */
	private function render_clear_key_form() {
		if ( '' === $this->get_saved_api_key() ) {
			return;
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="i18nly-clear-key-form">';
		wp_nonce_field( 'i18nly_clear_deepl_api_key_action', 'i18nly_clear_deepl_api_key_nonce' );
		echo '<input type="hidden" name="action" value="i18nly_clear_deepl_api_key" />';
		echo '</form>';
	}

	/**
	 * Returns settings page URL.
	 *
	 * @return string
	 */
	private function get_page_url() {
		return admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Returns the credentials validator.
	 *
	 * @return \WP_I18nly\AI\DeepLCredentialsValidator
	 */
	protected function get_credentials_validator() {
		return new DeepLCredentialsValidator();
	}
}
