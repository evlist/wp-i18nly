<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * I18nly admin page class.
 *
 * @package I18nly
 */

/**
 * Handles the I18nly admin workspace page.
 */
class I18nly_Admin_Page {
	/**
	 * The top-level menu slug.
	 */
	private const MENU_SLUG = 'i18nly-workspace';

	/**
	 * Registers hooks used by the admin page.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Registers the top-level admin menu entry.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'I18nly', 'i18nly' ),
			__( 'I18nly', 'i18nly' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-translation',
			58
		);
	}

	/**
	 * Renders the workspace page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'I18nly Workspace', 'i18nly' ); ?></h1>
			<div id="i18nly-workspace" class="i18nly-workspace" aria-live="polite"></div>
		</div>
		<?php
	}
}
