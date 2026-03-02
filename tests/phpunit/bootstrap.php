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

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

require_once __DIR__ . '/class-i18nly-test-wpdb.php';

$i18nly_test_can_manage_options     = true;
$i18nly_test_menu_pages             = array();
$i18nly_test_submenu_pages          = array();
$i18nly_test_plugins                = array();
$i18nly_test_available_languages    = array();
$i18nly_test_available_translations = array();

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
 * Sets available languages returned by get_available_languages stub.
 *
 * @param array<int, string> $languages Installed locale codes.
 * @return void
 */
function i18nly_test_set_available_languages( array $languages ) {
	global $i18nly_test_available_languages;

	$i18nly_test_available_languages = $languages;
}

/**
 * Sets available translations returned by wp_get_available_translations stub.
 *
 * @param array<string, array<string, mixed>> $translations Translation metadata.
 * @return void
 */
function i18nly_test_set_available_translations( array $translations ) {
	global $i18nly_test_available_translations;

	$i18nly_test_available_translations = $translations;
}

/**
 * Sets translations returned by wpdb get_results/get_row stubs.
 *
 * @param array<int, array<string, mixed>> $translations Translation rows.
 * @return void
 */
function i18nly_test_set_translations_rows( array $translations ) {
	global $wpdb;

	if ( ! $wpdb instanceof I18nly_Test_WPDB ) {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test bootstrap provides a wpdb stub.
		$wpdb = new I18nly_Test_WPDB();
	}

	$wpdb->set_translations( $translations );
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

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Returns escaped URL in tests.
	 *
	 * @param string $url URL value.
	 * @return string
	 */
	function esc_url( $url ) {
		return (string) $url;
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

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * Returns a test admin URL.
	 *
	 * @param string $path Relative admin path.
	 * @return string
	 */
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Adds one query argument to a URL in tests.
	 *
	 * @param string $key Query key.
	 * @param string $value Query value.
	 * @param string $url Base URL.
	 * @return string
	 */
	function add_query_arg( $key, $value, $url ) {
		$separator = false === strpos( (string) $url, '?' ) ? '?' : '&';

		return (string) $url . $separator . rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * Returns nonce field markup in tests.
	 *
	 * @param string $action Nonce action.
	 * @param string $name Nonce field name.
	 * @param bool   $referer Whether to add referer field.
	 * @param bool   $display Whether to display output.
	 * @return string
	 */
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
		unset( $referer );

		$html = '<input type="hidden" name="' . (string) $name . '" value="nonce-' . (string) $action . '">';

		if ( $display ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test stub output.
			echo $html;
		}

		return $html;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Returns unslashed value in tests.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Returns integer absolute value.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
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

if ( ! function_exists( 'get_available_languages' ) ) {
	/**
	 * Returns available installed languages from test runtime.
	 *
	 * @return array<int, string>
	 */
	function get_available_languages() {
		global $i18nly_test_available_languages;

		return $i18nly_test_available_languages;
	}
}

if ( ! function_exists( 'wp_get_available_translations' ) ) {
	/**
	 * Returns available translations from test runtime.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function wp_get_available_translations() {
		global $i18nly_test_available_translations;

		return $i18nly_test_available_translations;
	}
}

if ( ! function_exists( 'disabled' ) ) {
	/**
	 * Returns disabled attribute in test context.
	 *
	 * @param bool $disabled True when option should be disabled.
	 * @param bool $current Current comparison value.
	 * @param bool $should_echo Whether to echo the attribute.
	 * @return string
	 */
	function disabled( $disabled, $current = true, $should_echo = true ) {
		$attribute = ( $disabled === $current ) ? ' disabled="disabled"' : '';

		unset( $should_echo );

		return $attribute;
	}
}

require_once __DIR__ . '/../../plugin/includes/class-i18nly-schema.php';
require_once __DIR__ . '/../../plugin/includes/class-i18nly-admin-page.php';
