// phpcs:ignoreFile
<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * PHPUnit bootstrap and WordPress test stubs.
 *
 * @package I18nly
 */

// phpcs:disable

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../../' );
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

$i18nly_test_can_manage_options     = true;
$i18nly_test_menu_pages             = array();
$i18nly_test_submenu_pages          = array();
$i18nly_test_plugins                = array();
$i18nly_test_available_languages    = array();
$i18nly_test_available_translations = array();
$i18nly_test_posts                  = array();
$i18nly_test_post_meta              = array();
$i18nly_test_last_redirect_url      = '';
$i18nly_test_last_updated_post      = array();
$i18nly_test_options                = array();
$i18nly_test_last_json_response     = array();
$i18nly_test_enqueued_scripts       = array();
$i18nly_test_enqueued_styles        = array();
$i18nly_test_inline_scripts         = array();

/**
 * Minimal in-memory wpdb stub for source import tests.
 */
class I18nly_Test_WPDB_Stub {
	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public $prefix = 'wp_';

	/**
	 * Last inserted ID.
	 *
	 * @var int
	 */
	public $insert_id = 0;

	/**
	 * Catalog rows.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $catalogs = array();

	/**
	 * Entry rows.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $entries = array();

	/**
	 * Executes SQL query.
	 *
	 * @param string $sql SQL query.
	 * @return int
	 */
	public function query( $sql ) {
		unset( $sql );

		return 1;
	}

	/**
	 * Returns charset collate fragment.
	 *
	 * @return string
	 */
	public function get_charset_collate() {
		return '';
	}

	/**
	 * Prepares SQL by interpolating values for tests.
	 *
	 * @param string $query Query template.
	 * @param mixed  ...$args Query arguments.
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		$parts = explode( '%', (string) $query );
		if ( count( $parts ) <= 1 ) {
			return (string) $query;
		}

		$result = array_shift( $parts );
		$index  = 0;

		foreach ( $parts as $part ) {
			$specifier = substr( $part, 0, 1 );
			$tail      = substr( $part, 1 );
			$arg       = isset( $args[ $index ] ) ? $args[ $index ] : '';

			if ( 'd' === $specifier ) {
				$result .= (string) (int) $arg;
			} elseif ( 'i' === $specifier ) {
				$identifier = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $arg );
				$result    .= '`' . (string) $identifier . '`';
			} else {
				$result .= "'" . addslashes( (string) $arg ) . "'";
			}

			$result .= $tail;
			++$index;
		}

		return $result;
	}

	/**
	 * Returns one scalar value from in-memory rows.
	 *
	 * @param string $query SQL query.
	 * @return mixed
	 */
	public function get_var( $query ) {
		$query = (string) $query;

		if ( preg_match( "/FROM\\s+\\w+i18nly_source_catalogs\\s+WHERE\\s+plugin_slug\\s*=\\s*'([^']+)'/", $query, $matches ) ) {
			$plugin_slug = stripslashes( $matches[1] );

			foreach ( $this->catalogs as $catalog ) {
				if ( $plugin_slug === (string) $catalog['plugin_slug'] ) {
					return (int) $catalog['id'];
				}
			}
		}

		if ( preg_match( "/FROM\\s+\\w+i18nly_source_entries\\s+WHERE\\s+catalog_id\\s*=\\s*(\\d+)\\s+AND\\s+msgctxt\\s+IS\\s+NULL\\s+AND\\s+msgid\\s*=\\s*'([^']*)'/", $query, $matches ) ) {
			$catalog_id = (int) $matches[1];
			$msgid      = stripslashes( $matches[2] );

			foreach ( $this->entries as $entry ) {
				if ( $catalog_id === (int) $entry['catalog_id'] && null === $entry['msgctxt'] && $msgid === (string) $entry['msgid'] ) {
					return (int) $entry['id'];
				}
			}
		}

		if ( preg_match( "/FROM\\s+\\w+i18nly_source_entries\\s+WHERE\\s+catalog_id\\s*=\\s*(\\d+)\\s+AND\\s+msgctxt\\s*=\\s*'([^']*)'\\s+AND\\s+msgid\\s*=\\s*'([^']*)'/", $query, $matches ) ) {
			$catalog_id = (int) $matches[1];
			$msgctxt    = stripslashes( $matches[2] );
			$msgid      = stripslashes( $matches[3] );

			foreach ( $this->entries as $entry ) {
				if ( $catalog_id === (int) $entry['catalog_id'] && $msgctxt === (string) $entry['msgctxt'] && $msgid === (string) $entry['msgid'] ) {
					return (int) $entry['id'];
				}
			}
		}

		return null;
	}

	/**
	 * Returns one row from in-memory entries.
	 *
	 * @param string $query SQL query.
	 * @param string $output Output type.
	 * @return array<string, mixed>|null
	 */
	public function get_row( $query, $output = ARRAY_A ) {
		unset( $output );

		if ( preg_match( '/WHERE\\s+id\\s*=\\s*(\\d+)/', (string) $query, $matches ) ) {
			$entry_id = (int) $matches[1];

			foreach ( $this->entries as $entry ) {
				if ( $entry_id === (int) $entry['id'] ) {
					return array(
						'msgid_plural'    => $entry['msgid_plural'],
						'comments_json'   => $entry['comments_json'],
						'references_json' => $entry['references_json'],
						'flags_json'      => $entry['flags_json'],
						'status'          => $entry['status'],
					);
				}
			}
		}

		return null;
	}

	/**
	 * Inserts one row into in-memory tables.
	 *
	 * @param string               $table Table name.
	 * @param array<string, mixed> $data Row data.
	 * @param array<int, string>   $format Format list.
	 * @return int|false
	 */
	public function insert( $table, $data, $format = null ) {
		unset( $format );

		if ( false !== strpos( (string) $table, 'i18nly_source_catalogs' ) ) {
			$this->insert_id  = count( $this->catalogs ) + 1;
			$data['id']       = $this->insert_id;
			$this->catalogs[] = $data;

			return 1;
		}

		if ( false !== strpos( (string) $table, 'i18nly_source_entries' ) ) {
			$this->insert_id = count( $this->entries ) + 1;
			$data['id']      = $this->insert_id;
			$this->entries[] = $data;

			return 1;
		}

		return false;
	}

	/**
	 * Updates one row in in-memory tables.
	 *
	 * @param string               $table Table name.
	 * @param array<string, mixed> $data Data to update.
	 * @param array<string, mixed> $where Match conditions.
	 * @param array<int, string>   $format Data formats.
	 * @param array<int, string>   $where_format Where formats.
	 * @return int|false
	 */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		unset( $format, $where_format );

		if ( false !== strpos( (string) $table, 'i18nly_source_catalogs' ) && isset( $where['id'] ) ) {
			foreach ( $this->catalogs as $index => $row ) {
				if ( (int) $row['id'] === (int) $where['id'] ) {
					$this->catalogs[ $index ] = array_merge( $row, $data );
					return 1;
				}
			}
		}

		if ( false !== strpos( (string) $table, 'i18nly_source_entries' ) && isset( $where['id'] ) ) {
			foreach ( $this->entries as $index => $row ) {
				if ( (int) $row['id'] === (int) $where['id'] ) {
					$this->entries[ $index ] = array_merge( $row, $data );
					return 1;
				}
			}
		}

		return false;
	}
}

/**
 * Minimal WP filesystem stub for tests.
 */
class I18nly_Test_WP_Filesystem_Stub {
	/**
	 * Recursively removes one directory.
	 *
	 * @param string $path Directory path.
	 * @return bool
	 */
	public function rmdir( $path ) {
		if ( ! is_dir( $path ) ) {
			return false;
		}

		$children = glob( (string) $path . '/*' );

		if ( false !== $children ) {
			foreach ( $children as $child ) {
				if ( is_dir( $child ) ) {
					$this->rmdir( $child );
					continue;
				}

				unlink( $child );
			}
		}

		return rmdir( (string) $path );
	}
}

global $wpdb;

if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test bootstrap defines in-memory wpdb stub.
	$wpdb = new I18nly_Test_WPDB_Stub();
}

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
 * Resets captured redirect URL.
 *
 * @return void
 */
function i18nly_test_reset_last_redirect_url() {
	global $i18nly_test_last_redirect_url;

	$i18nly_test_last_redirect_url = '';
}

/**
 * Resets captured wp_update_post payload.
 *
 * @return void
 */
function i18nly_test_reset_last_updated_post() {
	global $i18nly_test_last_updated_post;

	$i18nly_test_last_updated_post = array();
}

/**
 * Resets stored option values in test runtime.
 *
 * @return void
 */
function i18nly_test_reset_options() {
	global $i18nly_test_options;

	$i18nly_test_options = array();
}

/**
 * Returns one GET query parameter in test runtime.
 *
 * @param string $key Query parameter key.
 * @return string
 */
function i18nly_test_get_query_parameter( $key ) {
	if ( ! isset( $_GET[ $key ] ) ) {
		return '';
	}

	return sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) );
}

/**
 * Resets last JSON response captured from wp_send_json_* stubs.
 *
 * @return void
 */
function i18nly_test_reset_last_json_response() {
	global $i18nly_test_last_json_response;

	$i18nly_test_last_json_response = array();
}

/**
 * Returns last JSON response captured from wp_send_json_* stubs.
 *
 * @return array<string, mixed>
 */
function i18nly_test_get_last_json_response() {
	global $i18nly_test_last_json_response;

	return $i18nly_test_last_json_response;
}

/**
 * Resets captured enqueued scripts.
 *
 * @return void
 */
function i18nly_test_reset_enqueued_scripts() {
	global $i18nly_test_enqueued_scripts, $i18nly_test_enqueued_styles, $i18nly_test_inline_scripts;

	$i18nly_test_enqueued_scripts = array();
	$i18nly_test_enqueued_styles  = array();
	$i18nly_test_inline_scripts   = array();
}

/**
 * Returns captured enqueued scripts.
 *
 * @return array<string, array<string, mixed>>
 */
function i18nly_test_get_enqueued_scripts() {
	global $i18nly_test_enqueued_scripts;

	return $i18nly_test_enqueued_scripts;
}

/**
 * Returns captured enqueued styles.
 *
 * @return array<string, array<string, mixed>>
 */
function i18nly_test_get_enqueued_styles() {
	global $i18nly_test_enqueued_styles;

	return $i18nly_test_enqueued_styles;
}

/**
 * Returns captured inline script blocks.
 *
 * @return array<string, array<int, array<string, string>>>
 */
function i18nly_test_get_inline_scripts() {
	global $i18nly_test_inline_scripts;

	return $i18nly_test_inline_scripts;
}

/**
 * Returns captured wp_update_post payload.
 *
 * @return array<string, mixed>
 */
function i18nly_test_get_last_updated_post() {
	global $i18nly_test_last_updated_post;

	return $i18nly_test_last_updated_post;
}

/**
 * Returns captured redirect URL.
 *
 * @return string
 */
function i18nly_test_get_last_redirect_url() {
	global $i18nly_test_last_redirect_url;

	return (string) $i18nly_test_last_redirect_url;
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
 * Sets translations returned by post/postmeta test stubs.
 *
 * @param array<int, array<string, mixed>> $translations Translation rows.
 * @return void
 */
function i18nly_test_set_translations_rows( array $translations ) {
	global $i18nly_test_posts, $i18nly_test_post_meta;

	$i18nly_test_posts     = array();
	$i18nly_test_post_meta = array();

	foreach ( $translations as $translation ) {
		if ( ! isset( $translation['id'] ) ) {
			continue;
		}

		$post_id = (int) $translation['id'];

		$i18nly_test_posts[] = (object) array(
			'ID'            => $post_id,
			'post_type'     => 'i18nly_translation',
			'post_status'   => 'publish',
			'post_title'    => (string) $translation['source_slug'] . ' → ' . (string) $translation['target_language'],
			'post_date_gmt' => isset( $translation['created_at_gmt'] ) ? (string) $translation['created_at_gmt'] : ( isset( $translation['created_at'] ) ? (string) $translation['created_at'] : '' ),
			'post_date'     => isset( $translation['created_at_local'] ) ? (string) $translation['created_at_local'] : ( isset( $translation['created_at'] ) ? (string) $translation['created_at'] : '' ),
		);

		$i18nly_test_post_meta[ $post_id ] = array(
			'_i18nly_source_slug'     => (string) $translation['source_slug'],
			'_i18nly_target_language' => (string) $translation['target_language'],
		);
	}
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

if ( ! function_exists( '_x' ) ) {
	/**
	 * Returns untranslated contextual string in tests.
	 *
	 * @param string $text Text value.
	 * @param string $context Translation context.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function _x( $text, $context, $domain ) {
		unset( $context, $domain );

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

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Returns sanitized raw URL in tests.
	 *
	 * @param string $url URL value.
	 * @return string
	 */
	function esc_url_raw( $url ) {
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

if ( ! function_exists( 'remove_action' ) ) {
	/**
	 * No-op remove_action stub.
	 *
	 * @param string   $hook_name Hook name.
	 * @param callable $callback Hook callback.
	 * @param int      $priority Hook priority.
	 * @return void
	 */
	function remove_action( $hook_name, $callback, $priority = 10 ) {
		unset( $hook_name, $callback, $priority );
	}
}

if ( ! function_exists( 'add_meta_box' ) ) {
	/**
	 * No-op add_meta_box stub.
	 *
	 * @param string   $id Meta box ID.
	 * @param string   $title Meta box title.
	 * @param callable $callback Render callback.
	 * @param string   $screen Screen ID.
	 * @param string   $context Context.
	 * @param string   $priority Priority.
	 * @return void
	 */
	function add_meta_box( $id, $title, $callback, $screen, $context = 'advanced', $priority = 'default' ) {
		unset( $id, $title, $callback, $screen, $context, $priority );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * No-op add_filter stub.
	 *
	 * @param string   $hook_name Hook name.
	 * @param callable $callback Hook callback.
	 * @param int      $priority Hook priority.
	 * @param int      $accepted_args Number of accepted args.
	 * @return void
	 */
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook_name, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'register_post_type' ) ) {
	/**
	 * No-op register_post_type stub.
	 *
	 * @param string               $post_type Post type key.
	 * @param array<string, mixed> $args Post type args.
	 * @return void
	 */
	function register_post_type( $post_type, array $args ) {
		unset( $post_type, $args );
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
	 * @param string          $parent_slug Parent slug.
	 * @param string          $page_title Page title.
	 * @param string          $menu_title Menu title.
	 * @param string          $capability Capability.
	 * @param string          $menu_slug Menu slug.
	 * @param callable|string $callback Callback.
	 * @return void
	 */
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {
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

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	/**
	 * Captures safe redirects in tests.
	 *
	 * @param string $location Redirect URL.
	 * @param int    $status HTTP status.
	 * @return bool
	 */
	function wp_safe_redirect( $location, $status = 302 ) {
		global $i18nly_test_last_redirect_url;

		unset( $status );

		$i18nly_test_last_redirect_url = (string) $location;

		return true;
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

if ( ! function_exists( 'plugin_dir_url' ) ) {
	/**
	 * Returns plugin directory URL in tests.
	 *
	 * @param string $path File path.
	 * @return string
	 */
	function plugin_dir_url( $path ) {
		unset( $path );

		return 'https://example.test/wp-content/plugins/i18nly/';
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * Captures enqueued scripts in tests.
	 *
	 * @param string               $handle Script handle.
	 * @param string               $src Script URL.
	 * @param array<int, string>   $deps Dependencies.
	 * @param string|bool|null     $ver Version.
	 * @param bool                 $in_footer In footer.
	 * @return void
	 */
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
		global $i18nly_test_enqueued_scripts;

		$i18nly_test_enqueued_scripts[ (string) $handle ] = array(
			'src'       => (string) $src,
			'deps'      => is_array( $deps ) ? $deps : array(),
			'ver'       => $ver,
			'in_footer' => (bool) $in_footer,
		);
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * Captures enqueued styles in tests.
	 *
	 * @param string             $handle Style handle.
	 * @param string             $src Style URL.
	 * @param array<int, string> $deps Dependencies.
	 * @param string|bool|null   $ver Version.
	 * @param string             $media Media.
	 * @return void
	 */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		global $i18nly_test_enqueued_styles;

		$i18nly_test_enqueued_styles[ (string) $handle ] = array(
			'src'   => (string) $src,
			'deps'  => is_array( $deps ) ? $deps : array(),
			'ver'   => $ver,
			'media' => (string) $media,
		);
	}
}

if ( ! function_exists( 'wp_add_inline_script' ) ) {
	/**
	 * Captures inline script blocks in tests.
	 *
	 * @param string $handle Script handle.
	 * @param string $data Inline script.
	 * @param string $position Position.
	 * @return bool
	 */
	function wp_add_inline_script( $handle, $data, $position = 'after' ) {
		global $i18nly_test_inline_scripts;

		if ( ! isset( $i18nly_test_inline_scripts[ $handle ] ) ) {
			$i18nly_test_inline_scripts[ $handle ] = array();
		}

		$i18nly_test_inline_scripts[ $handle ][] = array(
			'data'     => (string) $data,
			'position' => (string) $position,
		);

		return true;
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

if ( ! function_exists( 'wp_nonce_url' ) ) {
	/**
	 * Appends a nonce query arg to a URL in tests.
	 *
	 * @param string $action_url Action URL.
	 * @param string $action Nonce action.
	 * @param string $name Nonce arg name.
	 * @return string
	 */
	function wp_nonce_url( $action_url, $action = -1, $name = '_wpnonce' ) {
		return add_query_arg( $name, 'nonce-' . (string) $action, (string) $action_url );
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

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Returns sanitized text field in tests.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	function sanitize_text_field( $value ) {
		return trim( (string) $value );
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * Validates nonce in tests.
	 *
	 * @param string $nonce Nonce value.
	 * @param string $action Nonce action.
	 * @return bool
	 */
	function wp_verify_nonce( $nonce, $action ) {
		return 'nonce-' . (string) $action === (string) $nonce;
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * Returns deterministic nonce in tests.
	 *
	 * @param string|int $action Nonce action.
	 * @return string
	 */
	function wp_create_nonce( $action = -1 ) {
		return 'nonce-' . (string) $action;
	}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	/**
	 * Captures successful JSON responses in tests.
	 *
	 * @param mixed    $value Response payload.
	 * @param int|null $status_code Optional status code.
	 * @param int      $flags Json flags.
	 * @return void
	 */
	function wp_send_json_success( $value = null, $status_code = null, $flags = 0 ) {
		global $i18nly_test_last_json_response;

		unset( $flags );

		$i18nly_test_last_json_response = array(
			'success' => true,
			'data'    => $value,
			'status'  => $status_code,
		);
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	/**
	 * Captures error JSON responses in tests.
	 *
	 * @param mixed    $value Response payload.
	 * @param int|null $status_code Optional status code.
	 * @param int      $flags Json flags.
	 * @return void
	 */
	function wp_send_json_error( $value = null, $status_code = null, $flags = 0 ) {
		global $i18nly_test_last_json_response;

		unset( $flags );

		$i18nly_test_last_json_response = array(
			'success' => false,
			'data'    => $value,
			'status'  => $status_code,
		);
	}
}

if ( ! function_exists( 'WP_Filesystem' ) ) {
	/**
	 * Initializes test filesystem object.
	 *
	 * @return bool
	 */
	function WP_Filesystem() {
		global $wp_filesystem;

		$wp_filesystem = new I18nly_Test_WP_Filesystem_Stub();

		return true;
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	/**
	 * Creates directory recursively in tests.
	 *
	 * @param string $target Directory path.
	 * @return bool
	 */
	function wp_mkdir_p( $target ) {
		if ( is_dir( $target ) ) {
			return true;
		}

		return mkdir( (string) $target, 0755, true );
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	/**
	 * Deletes one file in tests.
	 *
	 * @param string $file File path.
	 * @return bool
	 */
	function wp_delete_file( $file ) {
		if ( ! is_file( $file ) ) {
			return false;
		}

		return unlink( (string) $file );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Encodes one value as JSON in tests.
	 *
	 * @param mixed $value Value to encode.
	 * @return string|false
	 */
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Returns option value from test runtime.
	 *
	 * @param string $option Option key.
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	function get_option( $option, $default_value = false ) {
		global $i18nly_test_options;

		if ( ! is_array( $i18nly_test_options ) ) {
			$i18nly_test_options = array();
		}

		if ( ! array_key_exists( (string) $option, $i18nly_test_options ) ) {
			return $default_value;
		}

		return $i18nly_test_options[ (string) $option ];
	}
}

// phpcs:enable

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Stores option value in test runtime.
	 *
	 * @param string $option Option key.
	 * @param mixed  $value Option value.
	 * @param bool   $autoload Autoload flag.
	 * @return bool
	 */
	function update_option( $option, $value, $autoload = null ) {
		global $i18nly_test_options;

		if ( ! is_array( $i18nly_test_options ) ) {
			$i18nly_test_options = array();
		}

		unset( $autoload );

		$i18nly_test_options[ (string) $option ] = $value;

		return true;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Returns whether a value is a WP_Error.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	function is_wp_error( $value ) {
		unset( $value );

		return false;
	}
}

if ( ! function_exists( 'wp_trash_post' ) ) {
	/**
	 * No-op post trash stub.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	function wp_trash_post( $post_id ) {
		unset( $post_id );

		return true;
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

if ( ! function_exists( 'map_deep' ) ) {
	/**
	 * Recursively maps values for arrays/objects.
	 *
	 * @param mixed    $value Input value.
	 * @param callable $callback Mapping callback.
	 * @return mixed
	 */
	function map_deep( $value, $callback ) {
		if ( is_array( $value ) ) {
			$mapped = array();

			foreach ( $value as $key => $item ) {
				$mapped[ $key ] = map_deep( $item, $callback );
			}

			return $mapped;
		}

		if ( is_object( $value ) ) {
			foreach ( $value as $property => $item ) {
				$value->{$property} = map_deep( $item, $callback );
			}

			return $value;
		}

		return call_user_func( $callback, $value );
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

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * Returns test posts for get_posts calls.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<int, object>
	 */
	function get_posts( array $args ) {
		global $i18nly_test_posts;

		unset( $args );

		return $i18nly_test_posts;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * Returns one test post by ID.
	 *
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	function get_post( $post_id ) {
		global $i18nly_test_posts;

		foreach ( $i18nly_test_posts as $post ) {
			if ( (int) $post->ID === (int) $post_id ) {
				return $post;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * Returns test post meta values.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param bool   $single Single value flag.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $meta_key, $single = false ) {
		global $i18nly_test_post_meta;

		if ( ! isset( $i18nly_test_post_meta[ $post_id ][ $meta_key ] ) ) {
			return $single ? '' : array();
		}

		$meta_value = $i18nly_test_post_meta[ $post_id ][ $meta_key ];

		return $single ? $meta_value : array( $meta_value );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * Sets test post meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return bool
	 */
	function update_post_meta( $post_id, $meta_key, $meta_value ) {
		global $i18nly_test_post_meta;

		if ( ! isset( $i18nly_test_post_meta[ $post_id ] ) ) {
			$i18nly_test_post_meta[ $post_id ] = array();
		}

		$i18nly_test_post_meta[ $post_id ][ $meta_key ] = $meta_value;

		return true;
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	/**
	 * Inserts one test post.
	 *
	 * @param array<string, mixed> $postarr Post data.
	 * @param bool                 $wp_error Whether to return WP_Error on failure.
	 * @return int
	 */
	function wp_insert_post( array $postarr, $wp_error = false ) {
		global $i18nly_test_posts;

		unset( $wp_error );

		$post_id = count( $i18nly_test_posts ) + 1;

		$i18nly_test_posts[] = (object) array(
			'ID'            => $post_id,
			'post_type'     => isset( $postarr['post_type'] ) ? (string) $postarr['post_type'] : 'post',
			'post_status'   => isset( $postarr['post_status'] ) ? (string) $postarr['post_status'] : 'publish',
			'post_title'    => isset( $postarr['post_title'] ) ? (string) $postarr['post_title'] : '',
			'post_date_gmt' => '2026-03-02 00:00:00',
		);

		return $post_id;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	/**
	 * Captures updated post payload in tests.
	 *
	 * @param array<string, mixed> $postarr Post data.
	 * @return int
	 */
	function wp_update_post( array $postarr ) {
		global $i18nly_test_last_updated_post;

		$i18nly_test_last_updated_post = $postarr;

		return isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
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

if ( ! function_exists( 'selected' ) ) {
	/**
	 * Returns selected attribute in test context.
	 *
	 * @param mixed $selected Selected value.
	 * @param mixed $current Current value.
	 * @param bool  $should_echo Whether to echo attribute.
	 * @return string
	 */
	function selected( $selected, $current = true, $should_echo = true ) {
		$attribute = ( (string) $selected === (string) $current ) ? ' selected="selected"' : '';

		if ( $should_echo ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test stub output.
			echo $attribute;
		}

		return $attribute;
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	/**
	 * Sanitizes textarea-like string value in tests.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function sanitize_textarea_field( $value ) {
		return sanitize_text_field( $value );
	}
}

if ( ! class_exists( 'WP_List_Table', false ) ) {
	/**
	 * Minimal WP_List_Table fallback for PHPUnit bootstrap.
	 */
	abstract class WP_List_Table {
		/**
		 * Current list table rows.
		 *
		 * @var array<int, array<string, mixed>>
		 */
		public $items = array();

		/**
		 * Column headers tuple.
		 *
		 * @var array<int, mixed>
		 */
		protected $_column_headers = array();

		/**
		 * Constructor.
		 *
		 * @param array<string, mixed> $args Constructor args.
		 */
		public function __construct( array $args = array() ) {
			unset( $args );
		}

		/**
		 * Displays minimal list table markup.
		 *
		 * @return void
		 */
		public function display() {
			if ( method_exists( $this, 'prepare_items' ) ) {
				$this->prepare_items();
			}

			$columns = method_exists( $this, 'get_columns' ) ? (array) $this->get_columns() : array();

			echo '<div class="tablenav top"></div>';
			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead><tr>';

			foreach ( $columns as $label ) {
				echo '<th scope="col">' . esc_html( (string) $label ) . '</th>';
			}

			echo '</tr></thead>';
			echo '<tbody>';

			if ( empty( $this->items ) ) {
				echo '<tr><td colspan="' . esc_attr( (string) max( 1, count( $columns ) ) ) . '">';
				if ( method_exists( $this, 'no_items' ) ) {
					$this->no_items();
				}
				echo '</td></tr>';
			} else {
				foreach ( $this->items as $item ) {
					echo '<tr>';

					foreach ( array_keys( $columns ) as $column_name ) {
						echo '<td>';
						$column_method = 'column_' . (string) $column_name;

						if ( method_exists( $this, $column_method ) ) {
							echo $this->{$column_method}( (array) $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaping is delegated to column rendering.
						} elseif ( method_exists( $this, 'column_default' ) ) {
							echo $this->column_default( (array) $item, (string) $column_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaping is delegated to column rendering.
						}
						echo '</td>';
					}

					echo '</tr>';
				}
			}

			echo '</tbody>';
			echo '</table>';
			echo '<div class="tablenav bottom"></div>';
		}
	}
}

require_once __DIR__ . '/../../plugin/includes/class-i18nly-admin-page.php';
require_once __DIR__ . '/../../plugin/includes/WP_I18nly/PluralFormsRegistry.php';
require_once __DIR__ . '/../../plugin/includes/class-i18nly-admin-page-helper.php';
require_once __DIR__ . '/../../plugin/includes/WP_I18nly/TranslationSaveHandler.php';
require_once __DIR__ . '/../../plugin/includes/WP_I18nly/TranslationAjaxController.php';
require_once __DIR__ . '/../../plugin/includes/WP_I18nly/TranslationMetaBoxRenderer.php';
require_once __DIR__ . '/../../plugin/includes/WP_I18nly/TranslationEntriesListTable.php';
require_once __DIR__ . '/../../plugin/includes/WP_I18nly/PotGenerator.php';
require_once __DIR__ . '/../../plugin/includes/WP_I18nly/PotSourceEntryExtractor.php';
require_once __DIR__ . '/../../plugin/includes/WP_I18nly/TemporaryStorage.php';
require_once __DIR__ . '/../../plugin/includes/WP_I18nly/PotWorkspaceService.php';
require_once __DIR__ . '/../../plugin/includes/WP_I18nly/SourceSchemaManager.php';
require_once __DIR__ . '/../../plugin/includes/WP_I18nly/SourceWpdbRepository.php';
require_once __DIR__ . '/../../plugin/includes/WP_I18nly/PotSourceImporter.php';
