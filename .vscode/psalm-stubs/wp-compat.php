<?php
// SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Minimal WordPress compatibility stubs for Psalm.
 *
 * This file is for static analysis only.
 */

defined( 'WP_LANG_DIR' ) || define( 'WP_LANG_DIR', '/tmp' );
defined( 'OBJECT' ) || define( 'OBJECT', 'OBJECT' );
defined( 'ARRAY_A' ) || define( 'ARRAY_A', 'ARRAY_A' );
defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/fake-wp/' );

if ( ! class_exists( 'WP_List_Table' ) ) {
	abstract class WP_List_Table {
		protected $_column_headers = array();
		public $items              = array();

		public function __construct( $args = array() ) {}
		public function prepare_items() {}
		public function display() {}
		public function get_columns() {
			return array(); }
		public function no_items() {}
		protected function display_tablenav( $which ) {}
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		return (string) $text; } }
if ( ! function_exists( '_x' ) ) {
	function _x( $text, $context, $domain = null ) {
		return (string) $text; } }
if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = null ) {
		return ( 1 === (int) $number ) ? (string) $single : (string) $plural; } }
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return (string) $text; } }
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = null ) {
		return (string) $text; } }
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return (string) $text; } }
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url; } }
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return (string) $url; } }
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return (string) $text; } }
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $content ) {
		return (string) $content; } }
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return (string) $value; } }
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		return (string) $value; } }
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value ); } }
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value; } }
if ( ! function_exists( 'wp_slash' ) ) {
	function wp_slash( $value ) {
		return $value; } }
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
		return json_encode( $value, (int) $flags, (int) $depth ); } }
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, (int) $decimals, '.', '' ); } }
if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $echo = true ) {
		return ( $selected == $current ) ? ' selected="selected"' : ''; } }
if ( ! function_exists( 'disabled' ) ) {
	function disabled( $disabled, $current = true, $echo = true ) {
		return ( $disabled == $current ) ? ' disabled="disabled"' : ''; } }

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {} }
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {} }
if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {
		return ''; } }
if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {
		return ''; } }
if ( ! function_exists( 'add_meta_box' ) ) {
	function add_meta_box( $id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $callback_args = null ) {} }
if ( ! function_exists( 'register_post_type' ) ) {
	function register_post_type( $post_type, $args = array() ) {
		return true; } }
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return true; } }
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		return true; } }
if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = null ) {
		return array(); } }
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		return $single ? '' : array(); } }
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
		return true; } }
if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( $postarr = array(), $wp_error = false, $fire_after_hooks = true ) {
		return isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0; } }
if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( $postarr = array(), $wp_error = false, $fire_after_hooks = true ) {
		return isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 1; } }
if ( ! function_exists( 'wp_trash_post' ) ) {
	function wp_trash_post( $post_id = 0 ) {
		return true; } }
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return false; } }
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return true; } }
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return 'nonce'; } }
if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
		return true; } }
if ( ! function_exists( 'wp_send_json_success' ) ) {
	function wp_send_json_success( $value = null, $status_code = null, $flags = 0 ) {} }
if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error( $value = null, $status_code = null, $flags = 0 ) {} }
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = array() ) {
		exit; } }
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '', $scheme = 'admin' ) {
		return '/wp-admin/' . ltrim( (string) $path, '/' ); } }
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $key, $value = null, $url = '' ) {
		return (string) $url; } }
if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $location, $status = 302, $x_redirect_by = 'WordPress' ) {
		return true; } }
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
		return ''; } }
if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return rtrim( dirname( (string) $file ), '/' ) . '/'; } }
if ( ! function_exists( 'determine_locale' ) ) {
	function determine_locale() {
		return 'en_US'; } }
if ( ! function_exists( 'load_textdomain' ) ) {
	function load_textdomain( $domain, $mofile ) {
		return true; } }
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		return '1970-01-01 00:00:00'; } }
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post = null, $output = OBJECT, $filter = 'raw' ) {
		return is_object( $post ) ? $post : (object) array( 'ID' => (int) $post ); } }
if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $target ) {
		return true; } }
if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( $file ) {
		return true; } }
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {} }
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {} }
if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $object_name, $l10n ) {
		return true; } }
if ( ! function_exists( 'wp_add_inline_script' ) ) {
	function wp_add_inline_script( $handle, $data, $position = 'after' ) {
		return true; } }
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( (array) $defaults, (array) $args ); } }
if ( ! function_exists( 'is_plugin_active' ) ) {
	function is_plugin_active( $plugin ) {
		return true; } }
if ( ! function_exists( 'plugins_api' ) ) {
	function plugins_api( $action, $args = array() ) {
		return (object) array(); } }
