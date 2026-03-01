<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * PHPUnit bootstrap and WordPress test stubs.
 *
 * @package I18nly
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../../' );
}

$i18nly_test_can_manage_options = true;

/**
 * Sets capability state for current_user_can test stub.
 *
 * @param bool $can_manage_options Capability state.
 * @return void
 */
function i18nly_test_set_can_manage_options( $can_manage_options ) {
	global $i18nly_test_can_manage_options;

	$i18nly_test_can_manage_options = (bool) $can_manage_options;
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Returns untranslated string in tests.
	 *
	 * @param string $text Text value.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain ) {
		unset( $domain );

		return (string) $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Returns untranslated escaped HTML string in tests.
	 *
	 * @param string $text Text value.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_html__( $text, $domain ) {
		unset( $domain );

		return (string) $text;
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	/**
	 * Returns untranslated escaped attribute string in tests.
	 *
	 * @param string $text Text value.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_attr__( $text, $domain ) {
		unset( $domain );

		return (string) $text;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * No-op add_action stub.
	 *
	 * @param string   $hook_name Hook name.
	 * @param callable $callback Hook callback.
	 * @return void
	 */
	function add_action( $hook_name, $callback ) {
		unset( $hook_name, $callback );
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	/**
	 * No-op add_menu_page stub.
	 *
	 * @param string   $page_title Page title.
	 * @param string   $menu_title Menu title.
	 * @param string   $capability Capability.
	 * @param string   $menu_slug Menu slug.
	 * @param callable $callback Callback.
	 * @param string   $icon_url Icon URL.
	 * @param int      $position Position.
	 * @return void
	 */
	function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback, $icon_url, $position ) {
		unset( $page_title, $menu_title, $capability, $menu_slug, $callback, $icon_url, $position );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Returns capability state from test runtime.
	 *
	 * @param string $capability Capability.
	 * @return bool
	 */
	function current_user_can( $capability ) {
		global $i18nly_test_can_manage_options;

		unset( $capability );

		return (bool) $i18nly_test_can_manage_options;
	}
}

require_once __DIR__ . '/../../plugin/includes/class-i18nly-admin-page.php';
