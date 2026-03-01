<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Plugin Name: I18nly
 * Description: Workflow management tool for WordPress internationalization.
 * Version: 0.1.0
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author: Eric van der Vlist
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: i18nly
 *
 * @package I18nly
 */

defined( 'ABSPATH' ) || exit;

define( 'I18NLY_VERSION', '0.1.0' );
define( 'I18NLY_PLUGIN_FILE', __FILE__ );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-i18nly-schema.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-i18nly-admin-page.php';

/**
 * Activates the plugin.
 *
 * @return void
 */
function i18nly_activate() {
	I18nly_Schema::install();
}
register_activation_hook( __FILE__, 'i18nly_activate' );

/**
 * Loads plugin translations.
 *
 * WordPress.org language packs are loaded automatically. This function adds
 * a fallback for direct distributions by loading MO files from the plugin
 * `languages` directory when needed.
 *
 * @return void
 */
function i18nly_load_textdomain() {
	$locale = determine_locale();

	$wp_lang_mofile = WP_LANG_DIR . '/plugins/i18nly-' . $locale . '.mo';
	if ( is_readable( $wp_lang_mofile ) ) {
		load_textdomain( 'i18nly', $wp_lang_mofile );
		return;
	}

	$plugin_mofile = plugin_dir_path( __FILE__ ) . 'languages/i18nly-' . $locale . '.mo';
	if ( is_readable( $plugin_mofile ) ) {
		load_textdomain( 'i18nly', $plugin_mofile );
	}
}
add_action( 'init', 'i18nly_load_textdomain' );

/**
 * Boots the admin components.
 *
 * @return void
 */
function i18nly_bootstrap() {
	I18nly_Schema::maybe_upgrade();

	$admin_page = new I18nly_Admin_Page();
	$admin_page->register();
}
add_action( 'plugins_loaded', 'i18nly_bootstrap' );
