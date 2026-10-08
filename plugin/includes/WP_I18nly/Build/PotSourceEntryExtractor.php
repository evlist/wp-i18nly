<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Source entry extraction for POT generation.
 *
 * @package I18nly
 */

namespace WP_I18nly\Build;

defined( 'ABSPATH' ) || exit;

/**
 * Extracts POT entries from a plugin source tree.
 *
 * It finds the source files of a plugin and delegates the extraction of each kind of file to a
 * dedicated extractor: PHP and Blade, JavaScript and sourcemaps, block and theme JSON. The entries
 * found in several places are merged into one, with all their references.
 */
class PotSourceEntryExtractor {
	/**
	 * Source file finder.
	 *
	 * @var PluginSourceFiles
	 */
	private $files;

	/**
	 * PHP and Blade extractor.
	 *
	 * @var PhpGettextExtractor
	 */
	private $php;

	/**
	 * JavaScript extractor.
	 *
	 * @var JsGettextExtractor
	 */
	private $js;

	/**
	 * JSON extractor.
	 *
	 * @var JsonI18nExtractor
	 */
	private $json;

	/**
	 * Constructor.
	 *
	 * @param string $plugins_root Optional plugins root directory.
	 */
	public function __construct( $plugins_root = '' ) {
		$this->files = new PluginSourceFiles( $plugins_root );
		$this->php   = new PhpGettextExtractor();
		$this->js    = new JsGettextExtractor();
		$this->json  = new JsonI18nExtractor();
	}

	/**
	 * Extracts translatable entries from one source plugin slug.
	 *
	 * @param string $source_slug Plugin source slug.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_from_source_slug( $source_slug ) {
		$source_slug = (string) $source_slug;
		$main_file   = $this->files->resolve_main_file( $source_slug );

		if ( '' === $main_file || ! is_readable( $main_file ) ) {
			return array();
		}

		$directory   = dirname( $main_file );
		$entries_map = array();

		$this->collect( $entries_map, $directory, $this->files->list_php_files( $main_file, $source_slug ), array( $this->php, 'extract_from_php' ) );
		$this->collect( $entries_map, $directory, $this->files->list_blade_files( $main_file, $source_slug ), array( $this->php, 'extract_from_blade' ) );
		$this->collect( $entries_map, $directory, $this->files->list_js_files( $main_file, $source_slug ), array( $this->js, 'extract_from_code' ) );
		$this->collect( $entries_map, $directory, $this->files->list_js_map_files( $main_file, $source_slug ), array( $this->js, 'extract_from_sourcemap' ) );
		$this->collect( $entries_map, $directory, $this->files->list_json_files( $main_file, $source_slug ), array( $this->json, 'extract_from_file_contents' ) );

		return array_values( $entries_map );
	}

	/**
	 * Extracts the entries of some files and merges them into the entries map.
	 *
	 * Empty files are skipped. The references of the entries are relative to the plugin directory.
	 *
	 * @param array<string, array<string, mixed>> $entries_map Entries indexed by identity, completed by reference.
	 * @param string                              $directory Plugin directory.
	 * @param array<int, string>                  $file_paths Absolute file paths.
	 * @param callable                            $extract Extractor receiving the file contents and the relative path.
	 * @return void
	 */
	private function collect( array &$entries_map, $directory, array $file_paths, callable $extract ) {
		foreach ( $file_paths as $file_path ) {
			$contents = $this->files->read_contents( $file_path );

			if ( '' === $contents ) {
				continue;
			}

			$relative_path = ltrim( str_replace( $directory, '', $file_path ), '/\\' );

			foreach ( $extract( $contents, $relative_path ) as $entry ) {
				$this->merge_entry_into_map( $entries_map, $entry );
			}
		}
	}

	/**
	 * Merges one extracted entry into map with deduplicated references/comments.
	 *
	 * @param array<string, array<string, mixed>> $entries_map Existing entries map.
	 * @param array<string, mixed>                $entry One extracted entry.
	 * @return void
	 */
	private function merge_entry_into_map( array &$entries_map, array $entry ) {
		$key = ( isset( $entry['context'] ) ? (string) $entry['context'] : '' )
			. "\004" . (string) $entry['original']
			. "\004" . ( isset( $entry['plural'] ) ? (string) $entry['plural'] : '' );

		if ( ! isset( $entries_map[ $key ] ) ) {
			$entries_map[ $key ] = $entry;
			return;
		}

		if ( ! empty( $entry['references'] ) && is_array( $entry['references'] ) ) {
			$existing_references = isset( $entries_map[ $key ]['references'] ) && is_array( $entries_map[ $key ]['references'] )
				? $entries_map[ $key ]['references']
				: array();

			$entries_map[ $key ]['references'] = array_values(
				array_unique( array_merge( $existing_references, $entry['references'] ), SORT_REGULAR )
			);
		}

		if ( ! empty( $entry['comments'] ) && is_array( $entry['comments'] ) ) {
			$existing_comments = isset( $entries_map[ $key ]['comments'] ) && is_array( $entries_map[ $key ]['comments'] )
				? $entries_map[ $key ]['comments']
				: array();

			$entries_map[ $key ]['comments'] = array_values(
				array_unique( array_merge( $existing_comments, $entry['comments'] ) )
			);
		}

		if ( ! empty( $entry['flags'] ) && is_array( $entry['flags'] ) ) {
			$existing_flags = isset( $entries_map[ $key ]['flags'] ) && is_array( $entries_map[ $key ]['flags'] )
				? $entries_map[ $key ]['flags']
				: array();

			$entries_map[ $key ]['flags'] = array_values(
				array_unique( array_merge( $existing_flags, $entry['flags'] ) )
			);
		}
	}
}
