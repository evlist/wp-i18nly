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

if ( ! class_exists( 'WP_List_Table', false ) ) {
	$wp_list_table_file = ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

	if ( file_exists( $wp_list_table_file ) ) {
		require_once $wp_list_table_file;
	}
}

define( 'I18NLY_VERSION', '0.1.0' );
define( 'I18NLY_PLUGIN_FILE', __FILE__ );

/**
 * Returns one class file path from its class name.
 *
 * @param string $class_name Requested class name.
 * @return string
 */
function i18nly_get_class_file_path( $class_name ) {
	if ( 0 !== strpos( $class_name, 'I18nly_' ) ) {
		return '';
	}

	$normalized = strtolower( str_replace( '_', '-', $class_name ) );
	$file_path  = plugin_dir_path( __FILE__ ) . 'includes/class-' . $normalized . '.php';

	if ( ! is_readable( $file_path ) ) {
		return '';
	}

	return $file_path;
}

/**
 * Autoloads I18nly classes from the includes directory.
 *
 * @param string $class_name Requested class name.
 * @return void
 */
function i18nly_autoload( $class_name ) {
	$class_file_path = i18nly_get_class_file_path( (string) $class_name );

	if ( '' === $class_file_path ) {
		return;
	}

	require_once $class_file_path;
}

spl_autoload_register( 'i18nly_autoload' );

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
	$schema_manager = new I18nly_Source_Schema_Manager();
	$schema_manager->maybe_upgrade();

	$admin_page = new I18nly_Admin_Page();
	$admin_page->register();
}
add_action( 'plugins_loaded', 'i18nly_bootstrap' );
