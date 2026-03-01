<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * I18nly admin page class.
 *
 * @package I18nly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the I18nly admin pages.
 */
class I18nly_Admin_Page {
	/**
	 * The top-level menu slug.
	 */
	private const MENU_SLUG = 'i18nly-translations';

	/**
	 * The add translation submenu slug.
	 */
	private const ADD_MENU_SLUG = 'i18nly-add-translation';

	/**
	 * Registers hooks used by the admin page.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Registers the admin menu entries.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Translations', 'i18nly' ),
			__( 'Translations', 'i18nly' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_all_translations_page' ),
			'dashicons-translation',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'All translations', 'i18nly' ),
			__( 'All translations', 'i18nly' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_all_translations_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Add translation', 'i18nly' ),
			__( 'Add translation', 'i18nly' ),
			'manage_options',
			self::ADD_MENU_SLUG,
			array( $this, 'render_add_translation_page' )
		);
	}

	/**
	 * Renders the all translations page.
	 *
	 * @return void
	 */
	public function render_all_translations_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Translations', 'i18nly' ); ?></h1>
			<div id="i18nly-translations-list" aria-live="polite"></div>
		</div>
		<?php
	}

	/**
	 * Renders the add translation page.
	 *
	 * @return void
	 */
	public function render_add_translation_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Add translation', 'i18nly' ); ?></h1>
			<div id="i18nly-translation-create" aria-live="polite"></div>
		</div>
		<?php
	}
}
