<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Plugin Name: I18nly
 * Description: Because translating a plugin in WordPress should be as simple as writing a blog post.
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
	$i18nly_wp_list_table_file = ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

	if ( file_exists( $i18nly_wp_list_table_file ) ) {
		require_once $i18nly_wp_list_table_file;
	}
}

define( 'I18NLY_VERSION', '0.1.0' );
define( 'I18NLY_PLUGIN_FILE', __FILE__ );

require_once plugin_dir_path( __FILE__ ) . 'third-party/vendor/autoload.php';

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
	$schema_manager = new \WP_I18nly\Storage\SourceSchemaManager();
	$schema_manager->maybe_upgrade();

	$admin_page = new \WP_I18nly\Admin\AdminPage();
	$admin_page->register();

	$settings_page = new \WP_I18nly\Admin\TranslationSettingsPage();
	add_action( 'admin_menu', array( $settings_page, 'register_menu' ) );
	add_action( 'admin_init', array( $settings_page, 'register_settings' ) );
	add_action( 'admin_post_i18nly_test_deepl_connection', array( $settings_page, 'handle_test_connection' ) );
	add_action( 'admin_post_i18nly_clear_deepl_api_key', array( $settings_page, 'handle_clear_api_key' ) );
}
add_action( 'plugins_loaded', 'i18nly_bootstrap' );
