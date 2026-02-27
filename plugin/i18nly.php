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

require_once plugin_dir_path( __FILE__ ) . 'includes/class-i18nly-admin-page.php';

/**
 * Loads the plugin text domain.
 *
 * @return void
 */
function i18nly_load_textdomain() {
	load_plugin_textdomain( 'i18nly', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'i18nly_load_textdomain' );

/**
 * Boots the admin components.
 *
 * @return void
 */
function i18nly_bootstrap() {
	$admin_page = new I18nly_Admin_Page();
	$admin_page->register();
}
add_action( 'plugins_loaded', 'i18nly_bootstrap' );
