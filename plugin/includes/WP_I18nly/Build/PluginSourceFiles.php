<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Plugin source file discovery.
 *
 * @package I18nly
 */

namespace WP_I18nly\Build;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use SplFileObject;

defined( 'ABSPATH' ) || exit;

/**
 * Finds the main file of a plugin and the source files to scan for translatable strings.
 *
 * A source slug such as `akismet/akismet.php` designates a directory based plugin: its whole
 * directory is scanned. A root-level slug such as `hello.php` designates a single-file plugin: only
 * that file is scanned and the plugins root is never scanned recursively.
 */
class PluginSourceFiles {
	/**
	 * Optional plugins root directory.
	 *
	 * @var string
	 */
	private $plugins_root;

	/**
	 * Constructor.
	 *
	 * @param string $plugins_root Optional plugins root directory.
	 */
	public function __construct( $plugins_root = '' ) {
		$this->plugins_root = (string) $plugins_root;
	}

	/**
	 * Resolves plugin main file absolute path from source slug.
	 *
	 * @param string $source_slug Source slug.
	 * @return string Absolute path, or an empty string when the plugin cannot be found.
	 */
	public function resolve_main_file( $source_slug ) {
		$source_slug = ltrim( (string) $source_slug, '/\\' );

		if ( ! $this->is_safe_relative_path( $source_slug ) ) {
			return '';
		}

		$candidates = array();

		if ( '' !== $this->plugins_root ) {
			$candidates[] = array( rtrim( $this->plugins_root, '/\\' ), $source_slug );
		}

		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$candidates[] = array( rtrim( (string) WP_PLUGIN_DIR, '/\\' ), $source_slug );
		}

		if ( defined( 'I18NLY_PLUGIN_FILE' ) ) {
			$plugin_directory = dirname( (string) I18NLY_PLUGIN_FILE );
			$slug_parts       = explode( '/', $source_slug );
			$slug_directory   = isset( $slug_parts[0] ) ? (string) $slug_parts[0] : '';
			$plugin_basename  = basename( $plugin_directory );

			if ( '' !== $slug_directory && $slug_directory === $plugin_basename ) {
				$candidates[] = array( $plugin_directory, basename( $source_slug ) );
			}
		}

		foreach ( $candidates as $candidate ) {
			$resolved = $this->resolve_inside_root( $candidate[0], $candidate[1] );

			if ( '' !== $resolved ) {
				return $resolved;
			}
		}

		return '';
	}

	/**
	 * Tells whether a source slug is a plain relative path: no empty, dot or parent segments, no NUL, no drive.
	 *
	 * @param string $source_slug Source slug without leading separators.
	 * @return bool
	 */
	private function is_safe_relative_path( $source_slug ) {
		if ( '' === $source_slug || false !== strpos( $source_slug, "\0" ) || false !== strpos( $source_slug, ':' ) ) {
			return false;
		}

		foreach ( preg_split( '#[/\\\\]#', $source_slug ) as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns the readable file at a relative path under a root, or an empty string when it is missing or outside the root.
	 *
	 * Symbolic links are resolved before checking, so a link cannot lead out of the root.
	 *
	 * @param string $root Root directory.
	 * @param string $relative_path Safe relative path.
	 * @return string
	 */
	private function resolve_inside_root( $root, $relative_path ) {
		$candidate = $root . '/' . $relative_path;

		if ( ! is_readable( $candidate ) ) {
			return '';
		}

		$real_root      = realpath( $root );
		$real_candidate = realpath( $candidate );

		if ( false === $real_root || false === $real_candidate ) {
			return '';
		}

		$real_root = rtrim( $real_root, '/\\' ) . DIRECTORY_SEPARATOR;

		if ( 0 !== strpos( $real_candidate, $real_root ) ) {
			return '';
		}

		return $candidate;
	}

	/**
	 * Lists PHP files to scan.
	 *
	 * Blade templates are PHP files too, so they are listed here as well as with list_blade_files().
	 *
	 * @param string $main_file Resolved main plugin file.
	 * @param string $source_slug Source slug.
	 * @return array<int, string>
	 */
	public function list_php_files( $main_file, $source_slug ) {
		if ( ! $this->is_directory_plugin( $source_slug ) ) {
			return array( $main_file );
		}

		return $this->scan(
			dirname( $main_file ),
			static function ( SplFileInfo $file ) {
				return 'php' === strtolower( (string) $file->getExtension() );
			}
		);
	}

	/**
	 * Lists Blade templates to scan.
	 *
	 * @param string $main_file Resolved main plugin file.
	 * @param string $source_slug Source slug.
	 * @return array<int, string>
	 */
	public function list_blade_files( $main_file, $source_slug ) {
		if ( ! $this->is_directory_plugin( $source_slug ) ) {
			return array();
		}

		return $this->scan(
			dirname( $main_file ),
			static function ( SplFileInfo $file ) {
				return '.blade.php' === substr( strtolower( (string) $file->getFilename() ), -10 );
			}
		);
	}

	/**
	 * Lists JS-like files to scan.
	 *
	 * @param string $main_file Resolved main plugin file.
	 * @param string $source_slug Source slug.
	 * @return array<int, string>
	 */
	public function list_js_files( $main_file, $source_slug ) {
		if ( ! $this->is_directory_plugin( $source_slug ) ) {
			return array();
		}

		return $this->scan(
			dirname( $main_file ),
			static function ( SplFileInfo $file ) {
				return in_array( strtolower( (string) $file->getExtension() ), array( 'js', 'jsx', 'ts', 'tsx', 'mjs', 'cjs' ), true );
			}
		);
	}

	/**
	 * Lists JS sourcemap files to scan.
	 *
	 * @param string $main_file Resolved main plugin file.
	 * @param string $source_slug Source slug.
	 * @return array<int, string>
	 */
	public function list_js_map_files( $main_file, $source_slug ) {
		if ( ! $this->is_directory_plugin( $source_slug ) ) {
			return array();
		}

		return $this->scan(
			dirname( $main_file ),
			static function ( SplFileInfo $file ) {
				return '.js.map' === substr( strtolower( (string) $file->getFilename() ), -7 );
			}
		);
	}

	/**
	 * Lists the JSON metadata files to scan: block.json, theme.json and files under a styles directory.
	 *
	 * @param string $main_file Resolved main plugin file.
	 * @param string $source_slug Source slug.
	 * @return array<int, string>
	 */
	public function list_json_files( $main_file, $source_slug ) {
		if ( ! $this->is_directory_plugin( $source_slug ) ) {
			return array();
		}

		return $this->scan(
			dirname( $main_file ),
			static function ( SplFileInfo $file ) {
				if ( 'json' !== strtolower( (string) $file->getExtension() ) ) {
					return false;
				}

				$filename = strtolower( (string) $file->getFilename() );
				$path     = str_replace( '\\', '/', (string) $file->getPathname() );

				return 'block.json' === $filename || 'theme.json' === $filename || false !== strpos( $path, '/styles/' );
			}
		);
	}

	/**
	 * Reads a text file using SPL APIs.
	 *
	 * @param string $file_path Absolute file path.
	 * @return string File contents, or an empty string when the file is not readable.
	 */
	public function read_contents( $file_path ) {
		if ( ! is_readable( $file_path ) ) {
			return '';
		}

		$file_object = new SplFileObject( $file_path, 'r' );
		$content     = '';

		while ( ! $file_object->eof() ) {
			$content .= (string) $file_object->fgets();
		}

		return $content;
	}

	/**
	 * Tells whether a source slug designates a directory based plugin.
	 *
	 * @param string $source_slug Source slug.
	 * @return bool
	 */
	private function is_directory_plugin( $source_slug ) {
		return false !== strpos( (string) $source_slug, '/' );
	}

	/**
	 * Lists the files of a directory, recursively, that satisfy a filter.
	 *
	 * @param string   $directory Root directory.
	 * @param callable $accept Filter receiving a SplFileInfo and returning whether to keep it.
	 * @return array<int, string> Sorted absolute paths.
	 */
	private function scan( $directory, callable $accept ) {
		$files    = array();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file_info ) {
			if ( $file_info instanceof SplFileInfo && $accept( $file_info ) ) {
				$files[] = (string) $file_info->getPathname();
			}
		}

		sort( $files );

		return $files;
	}
}
