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
$i18nly_test_menu_pages         = array();
$i18nly_test_submenu_pages      = array();
$i18nly_test_plugins            = array();

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

/**
 * Resets captured admin menu registration calls.
 *
 * @return void
 */
function i18nly_test_reset_admin_menu_capture() {
	global $i18nly_test_menu_pages, $i18nly_test_submenu_pages;

	$i18nly_test_menu_pages    = array();
	$i18nly_test_submenu_pages = array();
}

/**
 * Sets plugin list used by get_plugins test stub.
 *
 * @param array<string, array<string, mixed>> $plugins Plugin data keyed by plugin file.
 * @return void
 */
function i18nly_test_set_plugins( array $plugins ) {
	global $i18nly_test_plugins;

	$i18nly_test_plugins = $plugins;
}

/**
 * Returns captured top-level admin menu calls.
 *
 * @return array<int, array<string, mixed>>
 */
function i18nly_test_get_menu_pages() {
	global $i18nly_test_menu_pages;

	return $i18nly_test_menu_pages;
}

/**
 * Returns captured submenu admin menu calls.
 *
 * @return array<int, array<string, mixed>>
 */
function i18nly_test_get_submenu_pages() {
	global $i18nly_test_submenu_pages;

	return $i18nly_test_submenu_pages;
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

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Returns escaped attribute string in tests.
	 *
	 * @param string $text Text value.
	 * @return string
	 */
	function esc_attr( $text ) {
		return (string) $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Returns escaped HTML string in tests.
	 *
	 * @param string $text Text value.
	 * @return string
	 */
	function esc_html( $text ) {
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
		global $i18nly_test_menu_pages;

		$i18nly_test_menu_pages[] = array(
			'page_title' => $page_title,
			'menu_title' => $menu_title,
			'capability' => $capability,
			'menu_slug'  => $menu_slug,
			'callback'   => $callback,
			'icon_url'   => $icon_url,
			'position'   => $position,
		);
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	/**
	 * Captures add_submenu_page calls for tests.
	 *
	 * @param string   $parent_slug Parent slug.
	 * @param string   $page_title Page title.
	 * @param string   $menu_title Menu title.
	 * @param string   $capability Capability.
	 * @param string   $menu_slug Menu slug.
	 * @param callable $callback Callback.
	 * @return void
	 */
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback ) {
		global $i18nly_test_submenu_pages;

		$i18nly_test_submenu_pages[] = array(
			'parent_slug' => $parent_slug,
			'page_title'  => $page_title,
			'menu_title'  => $menu_title,
			'capability'  => $capability,
			'menu_slug'   => $menu_slug,
			'callback'    => $callback,
		);
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

if ( ! function_exists( 'get_plugins' ) ) {
	/**
	 * Returns plugin list from test runtime.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function get_plugins() {
		global $i18nly_test_plugins;

		return $i18nly_test_plugins;
	}
}

require_once __DIR__ . '/../../plugin/includes/class-i18nly-admin-page.php';
