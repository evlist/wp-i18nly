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

use FilesystemIterator;
use Peast\Peast;
use Peast\Syntax\Node;
use Peast\Traverser;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use SplFileObject;

defined( 'ABSPATH' ) || exit;

/**
 * Extracts POT entries from a plugin source tree.
 */
class PotSourceEntryExtractor {
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
	 * Extracts translatable entries from one source plugin slug.
	 *
	 * @param string $source_slug Plugin source slug.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_from_source_slug( $source_slug ) {
		$main_file = $this->resolve_main_file_from_source_slug( (string) $source_slug );

		if ( '' === $main_file || ! is_readable( $main_file ) ) {
			return array();
		}

		$plugin_directory = dirname( $main_file );
		$php_files        = $this->list_source_php_files( $main_file, (string) $source_slug );
		$js_files         = $this->list_source_js_files( $main_file, (string) $source_slug );
		$js_map_files     = $this->list_source_js_map_files( $main_file, (string) $source_slug );
		$json_files       = $this->list_source_json_files( $main_file, (string) $source_slug );
		$entries_map      = array();

		foreach ( $php_files as $file_path ) {
			$code = $this->read_php_file_contents( $file_path );
			if ( '' === $code ) {
				continue;
			}

			$relative_path = ltrim( str_replace( $plugin_directory, '', $file_path ), '/\\' );
			$tokens        = token_get_all( $code );
			$token_count   = count( $tokens );

			for ( $index = 0; $index < $token_count; $index++ ) {
				if ( ! is_array( $tokens[ $index ] ) || T_STRING !== $tokens[ $index ][0] ) {
					continue;
				}

				$function_name = strtolower( (string) $tokens[ $index ][1] );
				if ( ! $this->is_supported_gettext_function( $function_name ) ) {
					continue;
				}

				$open_parenthesis_index = $this->find_next_non_whitespace_token_index( $tokens, $index + 1 );
				if ( null === $open_parenthesis_index || '(' !== $tokens[ $open_parenthesis_index ] ) {
					continue;
				}

				$parsed = $this->parse_function_call_arguments( $tokens, $open_parenthesis_index );
				if ( null === $parsed ) {
					continue;
				}

				$entry = $this->build_entry_from_function_call(
					$function_name,
					$parsed['args'],
					$relative_path,
					(int) $tokens[ $index ][2],
					$this->extract_translator_comments_before_index( $tokens, $index )
				);

				if ( null === $entry ) {
					continue;
				}

				$key = ( isset( $entry['context'] ) ? (string) $entry['context'] : '' )
					. "\004" . (string) $entry['original']
					. "\004" . ( isset( $entry['plural'] ) ? (string) $entry['plural'] : '' );

				if ( ! isset( $entries_map[ $key ] ) ) {
					$entries_map[ $key ] = $entry;
					continue;
				}

				$entries_map[ $key ]['references'][] = array(
					'file' => $relative_path,
					'line' => (int) $tokens[ $index ][2],
				);

				if ( ! empty( $entry['comments'] ) ) {
					$existing_comments = isset( $entries_map[ $key ]['comments'] ) && is_array( $entries_map[ $key ]['comments'] )
						? $entries_map[ $key ]['comments']
						: array();

					$entries_map[ $key ]['comments'] = array_values(
						array_unique( array_merge( $existing_comments, $entry['comments'] ) )
					);
				}
			}
		}

		foreach ( $js_files as $file_path ) {
			$code = $this->read_text_file_contents( $file_path );
			if ( '' === $code ) {
				continue;
			}

			$relative_path = ltrim( str_replace( $plugin_directory, '', $file_path ), '/\\' );
			$entries       = $this->extract_js_entries_from_code( $code, $relative_path );

			foreach ( $entries as $entry ) {
				$this->merge_entry_into_map( $entries_map, $entry );
			}
		}

		foreach ( $js_map_files as $file_path ) {
			$map_contents = $this->read_text_file_contents( $file_path );
			if ( '' === $map_contents ) {
				continue;
			}

			$relative_map_path = ltrim( str_replace( $plugin_directory, '', $file_path ), '/\\' );
			$entries           = $this->extract_js_entries_from_sourcemap( $map_contents, $relative_map_path );

			foreach ( $entries as $entry ) {
				$this->merge_entry_into_map( $entries_map, $entry );
			}
		}

		foreach ( $json_files as $file_path ) {
			$json_contents = $this->read_text_file_contents( $file_path );
			if ( '' === $json_contents ) {
				continue;
			}

			$relative_json_path = ltrim( str_replace( $plugin_directory, '', $file_path ), '/\\' );
			$entries            = $this->extract_json_entries_from_file( $json_contents, $relative_json_path );

			foreach ( $entries as $entry ) {
				$this->merge_entry_into_map( $entries_map, $entry );
			}
		}

		return array_values( $entries_map );
	}

	/**
	 * Lists PHP files to scan for one source slug.
	 *
	 * Root-level plugin files such as `hello.php` are scanned as a single file.
	 * Directory-based plugins keep recursive scan behavior.
	 *
	 * @param string $main_file Resolved main plugin file.
	 * @param string $source_slug Source slug.
	 * @return array<int, string>
	 */
	private function list_source_php_files( $main_file, $source_slug ) {
		if ( false === strpos( $source_slug, '/' ) ) {
			return array( $main_file );
		}

		return $this->list_php_files( dirname( $main_file ) );
	}

	/**
	 * Lists JS-like files to scan for one source slug.
	 *
	 * Root-level plugin files such as `hello.php` are treated as single-file
	 * plugins and do not trigger recursive JS scan on plugins root.
	 *
	 * @param string $main_file Resolved main plugin file.
	 * @param string $source_slug Source slug.
	 * @return array<int, string>
	 */
	private function list_source_js_files( $main_file, $source_slug ) {
		if ( false === strpos( $source_slug, '/' ) ) {
			return array();
		}

		return $this->list_js_files( dirname( $main_file ) );
	}

	/**
	 * Lists JS sourcemap files to scan for one source slug.
	 *
	 * @param string $main_file Resolved main plugin file.
	 * @param string $source_slug Source slug.
	 * @return array<int, string>
	 */
	private function list_source_js_map_files( $main_file, $source_slug ) {
		if ( false === strpos( $source_slug, '/' ) ) {
			return array();
		}

		return $this->list_js_map_files( dirname( $main_file ) );
	}

	/**
	 * Lists translatable JSON metadata files for one source slug.
	 *
	 * @param string $main_file Resolved main plugin file.
	 * @param string $source_slug Source slug.
	 * @return array<int, string>
	 */
	private function list_source_json_files( $main_file, $source_slug ) {
		if ( false === strpos( $source_slug, '/' ) ) {
			return array();
		}

		return $this->list_json_files( dirname( $main_file ) );
	}

	/**
	 * Reads PHP file content using SPL APIs.
	 *
	 * @param string $file_path Absolute file path.
	 * @return string
	 */
	private function read_php_file_contents( $file_path ) {
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
	 * Reads generic text file content using SPL APIs.
	 *
	 * @param string $file_path Absolute file path.
	 * @return string
	 */
	private function read_text_file_contents( $file_path ) {
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
	 * Resolves plugin main file absolute path from source slug.
	 *
	 * @param string $source_slug Source slug.
	 * @return string
	 */
	private function resolve_main_file_from_source_slug( $source_slug ) {
		$source_slug = ltrim( $source_slug, '/\\' );

		if ( '' === $source_slug ) {
			return '';
		}

		$candidates = array();

		if ( '' !== $this->plugins_root ) {
			$candidates[] = rtrim( $this->plugins_root, '/\\' ) . '/' . $source_slug;
		}

		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$candidates[] = rtrim( (string) WP_PLUGIN_DIR, '/\\' ) . '/' . $source_slug;
		}

		if ( defined( 'I18NLY_PLUGIN_FILE' ) ) {
			$plugin_directory = dirname( (string) I18NLY_PLUGIN_FILE );
			$slug_parts       = explode( '/', $source_slug );
			$slug_directory   = isset( $slug_parts[0] ) ? (string) $slug_parts[0] : '';
			$plugin_basename  = basename( $plugin_directory );

			if ( '' !== $slug_directory && $slug_directory === $plugin_basename ) {
				$candidates[] = $plugin_directory . '/' . basename( $source_slug );
			}
		}

		foreach ( $candidates as $candidate ) {
			if ( is_readable( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Lists all PHP files recursively in a directory.
	 *
	 * @param string $directory Root directory.
	 * @return array<int, string>
	 */
	private function list_php_files( $directory ) {
		$files = array();

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file_info ) {
			if ( ! $file_info instanceof SplFileInfo ) {
				continue;
			}

			if ( 'php' !== strtolower( (string) $file_info->getExtension() ) ) {
				continue;
			}

			$files[] = (string) $file_info->getPathname();
		}

		sort( $files );

		return $files;
	}

	/**
	 * Lists all JS-like files recursively in a directory.
	 *
	 * @param string $directory Root directory.
	 * @return array<int, string>
	 */
	private function list_js_files( $directory ) {
		$files      = array();
		$extensions = array( 'js', 'jsx', 'ts', 'tsx', 'mjs', 'cjs' );

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file_info ) {
			if ( ! $file_info instanceof SplFileInfo ) {
				continue;
			}

			if ( ! in_array( strtolower( (string) $file_info->getExtension() ), $extensions, true ) ) {
				continue;
			}

			$files[] = (string) $file_info->getPathname();
		}

		sort( $files );

		return $files;
	}

	/**
	 * Lists all JS sourcemap files recursively in a directory.
	 *
	 * @param string $directory Root directory.
	 * @return array<int, string>
	 */
	private function list_js_map_files( $directory ) {
		$files = array();

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file_info ) {
			if ( ! $file_info instanceof SplFileInfo ) {
				continue;
			}

			$filename = strtolower( (string) $file_info->getFilename() );
			if ( '.js.map' !== substr( $filename, -7 ) ) {
				continue;
			}

			$files[] = (string) $file_info->getPathname();
		}

		sort( $files );

		return $files;
	}

	/**
	 * Lists translatable JSON metadata files recursively in a directory.
	 *
	 * @param string $directory Root directory.
	 * @return array<int, string>
	 */
	private function list_json_files( $directory ) {
		$files = array();

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file_info ) {
			if ( ! $file_info instanceof SplFileInfo ) {
				continue;
			}

			if ( 'json' !== strtolower( (string) $file_info->getExtension() ) ) {
				continue;
			}

			$filename      = strtolower( (string) $file_info->getFilename() );
			$relative_path = str_replace( '\\', '/', (string) $file_info->getPathname() );

			if ( 'block.json' === $filename || 'theme.json' === $filename || false !== strpos( $relative_path, '/styles/' ) ) {
				$files[] = (string) $file_info->getPathname();
			}
		}

		sort( $files );

		return $files;
	}

	/**
	 * Merges one extracted entry into map with deduplicated references/comments.
	 *
	 * @param array<string, array<string, mixed>> $entries_map Existing entries map.
	 * @param array<string, mixed>                 $entry One extracted entry.
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

	/**
	 * Extracts gettext entries from JS/TS code.
	 *
	 * @param string $code Source code.
	 * @param string $relative_path Relative reference file path.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_js_entries_from_code( $code, $relative_path ) {
		$entries = array();
		$lines   = preg_split( '/\r\n|\r|\n/', $code );

		if ( false === $lines ) {
			$lines = array();
		}

		try {
			$ast = Peast::latest(
				$code,
				array(
					'sourceType' => Peast::SOURCE_TYPE_MODULE,
					'comments'   => true,
					'jsx'        => true,
				)
			)->parse();
		} catch ( \Exception $exception ) {
			return $entries;
		}

		$traverser = new Traverser();

		$traverser->addFunction(
			function ( $node ) use ( &$entries, $relative_path, $lines ) {
				if ( ! $node instanceof Node\CallExpression ) {
					return;
				}

				$function_name = $this->resolve_js_callee_gettext_name( $node );
				if ( null === $function_name ) {
					return;
				}

				$args = $this->extract_js_call_argument_values( $node );
				if ( null === $args ) {
					return;
				}

				$line = $node->getLocation()->getStart()->getLine();
				$translator_comments = array_values(
					array_unique(
						array_merge(
							$this->extract_js_translator_comments_for_node( $node ),
							$this->extract_js_translator_comments_near_line( $lines, $line )
						)
					)
				);

				$entry = $this->build_entry_from_js_gettext_call(
					$function_name,
					$args,
					$relative_path,
					$line,
					$translator_comments
				);

				if ( null !== $entry ) {
					$entries[] = $entry;
				}

				if ( 'eval' !== $function_name ) {
					return;
				}

				$eval_code = $this->extract_js_eval_literal_code( $node );
				if ( '' === $eval_code ) {
					return;
				}

				$nested_entries = $this->extract_js_entries_from_code( $eval_code, $relative_path );
				foreach ( $nested_entries as $nested_entry ) {
					$entries[] = $nested_entry;
				}
			}
		);

		$traverser->traverse( $ast );

		return $entries;
	}

	/**
	 * Extracts gettext entries from JS code embedded in a .js.map file.
	 *
	 * @param string $map_contents Sourcemap JSON content.
	 * @param string $relative_map_path Relative sourcemap path.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_js_entries_from_sourcemap( $map_contents, $relative_map_path ) {
		$map_data = json_decode( $map_contents, true );
		if ( ! is_array( $map_data ) || ! isset( $map_data['sourcesContent'] ) || ! is_array( $map_data['sourcesContent'] ) ) {
			return array();
		}

		$concatenated_sources = implode( "\n", array_map( 'strval', $map_data['sourcesContent'] ) );
		if ( '' === $concatenated_sources ) {
			return array();
		}

		$reference_file = '.js.map' === substr( strtolower( $relative_map_path ), -7 )
			? substr( $relative_map_path, 0, -4 )
			: $relative_map_path;

		return $this->extract_js_entries_from_code( $concatenated_sources, $reference_file );
	}

	/**
	 * Extracts entries from translatable JSON metadata files.
	 *
	 * @param string $json_contents JSON content.
	 * @param string $relative_json_path Relative JSON path.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_json_entries_from_file( $json_contents, $relative_json_path ) {
		$decoded = json_decode( $json_contents, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$basename = strtolower( (string) basename( $relative_json_path ) );
		$entries  = array();

		if ( 'block.json' === $basename ) {
			$this->collect_block_json_entries( $decoded, $relative_json_path, $entries );
		}

		if ( 'theme.json' === $basename || 0 === strpos( str_replace( '\\', '/', strtolower( $relative_json_path ) ), 'styles/' ) ) {
			$this->collect_theme_json_entries( $decoded, $relative_json_path, $entries );
		}

		return $entries;
	}

	/**
	 * Collects common block.json translatable fields.
	 *
	 * @param array<string, mixed>              $data JSON object.
	 * @param string                            $relative_path Relative file path.
	 * @param array<int, array<string, mixed>> &$entries Accumulator.
	 * @return void
	 */
	private function collect_block_json_entries( array $data, $relative_path, array &$entries ) {
		$this->add_json_string_entry( $entries, isset( $data['title'] ) ? $data['title'] : null, 'block title', $relative_path );
		$this->add_json_string_entry( $entries, isset( $data['description'] ) ? $data['description'] : null, 'block description', $relative_path );

		if ( isset( $data['keywords'] ) && is_array( $data['keywords'] ) ) {
			foreach ( $data['keywords'] as $keyword ) {
				$this->add_json_string_entry( $entries, $keyword, 'block keyword', $relative_path );
			}
		}

		if ( isset( $data['styles'] ) && is_array( $data['styles'] ) ) {
			foreach ( $data['styles'] as $style ) {
				if ( is_array( $style ) ) {
					$this->add_json_string_entry( $entries, isset( $style['label'] ) ? $style['label'] : null, 'block style label', $relative_path );
				}
			}
		}

		if ( isset( $data['variations'] ) && is_array( $data['variations'] ) ) {
			foreach ( $data['variations'] as $variation ) {
				if ( ! is_array( $variation ) ) {
					continue;
				}

				$this->add_json_string_entry( $entries, isset( $variation['title'] ) ? $variation['title'] : null, 'block variation title', $relative_path );
				$this->add_json_string_entry( $entries, isset( $variation['description'] ) ? $variation['description'] : null, 'block variation description', $relative_path );
			}
		}
	}

	/**
	 * Collects common theme.json translatable fields.
	 *
	 * @param array<string, mixed>              $data JSON object.
	 * @param string                            $relative_path Relative file path.
	 * @param array<int, array<string, mixed>> &$entries Accumulator.
	 * @return void
	 */
	private function collect_theme_json_entries( array $data, $relative_path, array &$entries ) {
		if ( isset( $data['settings']['color']['palette'] ) && is_array( $data['settings']['color']['palette'] ) ) {
			foreach ( $data['settings']['color']['palette'] as $palette_entry ) {
				if ( is_array( $palette_entry ) ) {
					$this->add_json_string_entry( $entries, isset( $palette_entry['name'] ) ? $palette_entry['name'] : null, 'color name', $relative_path );
				}
			}
		}

		if ( isset( $data['settings']['color']['gradients'] ) && is_array( $data['settings']['color']['gradients'] ) ) {
			foreach ( $data['settings']['color']['gradients'] as $gradient_entry ) {
				if ( is_array( $gradient_entry ) ) {
					$this->add_json_string_entry( $entries, isset( $gradient_entry['name'] ) ? $gradient_entry['name'] : null, 'gradient name', $relative_path );
				}
			}
		}

		if ( isset( $data['settings']['typography']['fontFamilies'] ) && is_array( $data['settings']['typography']['fontFamilies'] ) ) {
			foreach ( $data['settings']['typography']['fontFamilies'] as $font_family ) {
				if ( is_array( $font_family ) ) {
					$this->add_json_string_entry( $entries, isset( $font_family['name'] ) ? $font_family['name'] : null, 'font family name', $relative_path );
				}
			}
		}
	}

	/**
	 * Adds one JSON string entry to an accumulator.
	 *
	 * @param array<int, array<string, mixed>> &$entries Accumulator.
	 * @param mixed                            $value Candidate value.
	 * @param string                           $context Translation context.
	 * @param string                           $relative_path Relative file path.
	 * @return void
	 */
	private function add_json_string_entry( array &$entries, $value, $context, $relative_path ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return;
		}

		$entries[] = array(
			'original'   => $value,
			'context'    => (string) $context,
			'references' => array(
				array(
					'file' => $relative_path,
					'line' => 1,
				),
			),
		);
	}

	/**
	 * Resolves gettext function name from a JS call expression.
	 *
	 * Supports direct, member, webpack Object(...) and babel indirect call forms.
	 *
	 * @param Node\CallExpression $node Call expression.
	 * @return string|null
	 */
	private function resolve_js_callee_gettext_name( Node\CallExpression $node ) {
		$callee = $node->getCallee();

		if ( $callee instanceof Node\Identifier ) {
			$name = (string) $callee->getName();
			return $this->is_supported_js_gettext_function( $name ) ? $name : null;
		}

		if ( $callee instanceof Node\MemberExpression ) {
			$property = $callee->getProperty();
			if ( $property instanceof Node\Identifier ) {
				$name = (string) $property->getName();
				return $this->is_supported_js_gettext_function( $name ) ? $name : null;
			}

			if ( $property instanceof Node\Literal ) {
				$value = $property->getValue();
				$name  = is_string( $value ) ? $value : '';
				return $this->is_supported_js_gettext_function( $name ) ? $name : null;
			}
		}

		if ( $callee instanceof Node\CallExpression ) {
			$inner_callee = $callee->getCallee();

			if ( $inner_callee instanceof Node\Identifier && 'Object' === $inner_callee->getName() ) {
				$arguments = $callee->getArguments();
				if ( empty( $arguments ) || ! $arguments[0] instanceof Node\MemberExpression ) {
					return null;
				}

				$property = $arguments[0]->getProperty();
				if ( $property instanceof Node\Identifier ) {
					$name = (string) $property->getName();
					return $this->is_supported_js_gettext_function( $name ) ? $name : null;
				}

				if ( $property instanceof Node\Literal ) {
					$value = $property->getValue();
					$name  = is_string( $value ) ? $value : '';
					return $this->is_supported_js_gettext_function( $name ) ? $name : null;
				}
			}
		}

		if ( $callee instanceof Node\ParenthesizedExpression ) {
			$expression = $callee->getExpression();
			if ( ! $expression instanceof Node\SequenceExpression ) {
				return null;
			}

			$expressions = $expression->getExpressions();
			if ( 2 !== count( $expressions ) ) {
				return null;
			}

			if ( ! $expressions[0] instanceof Node\Literal ) {
				return null;
			}

			$target = $expressions[1];
			if ( $target instanceof Node\Identifier ) {
				$name = (string) $target->getName();
				return $this->is_supported_js_gettext_function( $name ) ? $name : null;
			}

			if ( $target instanceof Node\MemberExpression && $target->getProperty() instanceof Node\Identifier ) {
				$name = (string) $target->getProperty()->getName();
				return $this->is_supported_js_gettext_function( $name ) ? $name : null;
			}
		}

		return null;
	}

	/**
	 * Returns whether a JS gettext function name is supported.
	 *
	 * @param string $function_name Function name.
	 * @return bool
	 */
	private function is_supported_js_gettext_function( $function_name ) {
		return in_array( strtolower( (string) $function_name ), array( '__', '_x', '_n', '_nx', 'eval' ), true );
	}

	/**
	 * Extracts normalized argument values from one JS call expression.
	 *
	 * Returns null when an unsupported argument type is encountered.
	 *
	 * @param Node\CallExpression $node Call expression.
	 * @return array<int, mixed>|null
	 */
	private function extract_js_call_argument_values( Node\CallExpression $node ) {
		$values = array();

		foreach ( $node->getArguments() as $argument ) {
			if ( $argument instanceof Node\Identifier ) {
				$values[] = '';
				continue;
			}

			if ( $argument instanceof Node\TemplateLiteral ) {
				if ( 0 !== count( $argument->getExpressions() ) ) {
					return null;
				}

				$parts = $argument->getParts();
				if ( ! empty( $parts ) ) {
					$values[] = $parts[0]->getValue();
					continue;
				}

				$values[] = '';
				continue;
			}

			if ( $argument instanceof Node\Literal ) {
				$values[] = $argument->getValue();
				continue;
			}

			if ( substr( $argument->getType(), -strlen( 'Expression' ) ) === 'Expression' ) {
				$values[] = '';
				continue;
			}

			return null;
		}

		return $values;
	}

	/**
	 * Builds one normalized entry from one JS gettext function call.
	 *
	 * @param string           $function_name Function name.
	 * @param array<int, mixed> $args Parsed args.
	 * @param string           $relative_path Relative reference file path.
	 * @param int              $line Source line.
	 * @return array<string, mixed>|null
	 */
	private function build_entry_from_js_gettext_call( $function_name, array $args, $relative_path, $line, array $translator_comments = array() ) {
		if ( 'eval' === $function_name ) {
			return null;
		}

		$original = isset( $args[0] ) && is_string( $args[0] ) ? (string) $args[0] : null;
		if ( null === $original || '' === $original ) {
			return null;
		}

		$entry = array(
			'original'   => $original,
			'references' => array(
				array(
					'file' => $relative_path,
					'line' => (int) $line,
				),
			),
		);

		if ( ! empty( $translator_comments ) ) {
			$entry['comments'] = $translator_comments;
		}

		if ( $this->contains_sprintf_placeholder( $original ) ) {
			$entry['flags'] = array( 'js-format' );
		}

		if ( '_x' === $function_name ) {
			$context = isset( $args[1] ) && is_string( $args[1] ) ? (string) $args[1] : '';
			if ( '' !== $context ) {
				$entry['context'] = $context;
			}
		}

		if ( '_n' === $function_name || '_nx' === $function_name ) {
			$plural = isset( $args[1] ) && is_string( $args[1] ) ? (string) $args[1] : '';
			if ( '' !== $plural ) {
				$entry['plural'] = $plural;
			}

			if ( '_nx' === $function_name ) {
				$context = isset( $args[3] ) && is_string( $args[3] ) ? (string) $args[3] : '';
				if ( '' !== $context ) {
					$entry['context'] = $context;
				}
			}
		}

		return $entry;
	}

	/**
	 * Extracts translator comments associated with one JS call expression.
	 *
	 * @param Node\CallExpression $node Call expression.
	 * @return array<int, string>
	 */
	private function extract_js_translator_comments_for_node( Node\CallExpression $node ) {
		$comments = array();

		foreach ( $node->getLeadingComments() as $comment ) {
			$comments[] = $comment;
		}

		$callee = $node->getCallee();
		if ( method_exists( $callee, 'getLeadingComments' ) ) {
			foreach ( $callee->getLeadingComments() as $comment ) {
				$comments[] = $comment;
			}
		}

		$normalized = array();
		foreach ( $comments as $comment ) {
			$raw = method_exists( $comment, 'getRawText' ) ? (string) $comment->getRawText() : '';
			if ( '' === $raw && method_exists( $comment, 'getText' ) ) {
				$raw = (string) $comment->getText();
			}

			if ( '' === $raw ) {
				continue;
			}

			$lines = preg_split( '/\r\n|\r|\n/', $raw );
			if ( false === $lines ) {
				continue;
			}

			foreach ( $lines as $line ) {
				$text = trim( (string) $line );
				$text = ltrim( $text, "/*# \t" );
				$text = preg_replace( '/\*\/$/', '', $text );

				if ( null === $text ) {
					continue;
				}

				$text = trim( $text );
				if ( ! preg_match( '/^translators\s*:/i', $text ) ) {
					continue;
				}

				$text = preg_replace( '/^translators\s*:\s*/i', '', $text );
				if ( null === $text || '' === trim( $text ) ) {
					continue;
				}

				$normalized[] = trim( $text );
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Extracts translator comments from source lines preceding one JS call.
	 *
	 * @param array<int, string> $lines Source code lines (0-based array).
	 * @param int                $line 1-based target line.
	 * @return array<int, string>
	 */
	private function extract_js_translator_comments_near_line( array $lines, $line ) {
		$comments = array();
		$index    = max( 0, (int) $line - 2 );
		$start    = max( 0, $index - 6 );

		for ( $cursor = $index; $cursor >= $start; $cursor-- ) {
			if ( ! isset( $lines[ $cursor ] ) ) {
				continue;
			}

			$text = trim( (string) $lines[ $cursor ] );
			if ( '' === $text ) {
				continue;
			}

			if ( false === strpos( $text, '//' ) && false === strpos( $text, '/*' ) && false === strpos( $text, '*' ) ) {
				break;
			}

			$text = ltrim( $text, "/*# \t" );
			$text = preg_replace( '/\*\/$/', '', $text );
			if ( null === $text ) {
				continue;
			}

			$text = trim( $text );
			if ( ! preg_match( '/^translators\s*:/i', $text ) ) {
				continue;
			}

			$text = preg_replace( '/^translators\s*:\s*/i', '', $text );
			if ( null === $text || '' === trim( $text ) ) {
				continue;
			}

			$comments[] = trim( $text );
		}

		return array_values( array_unique( $comments ) );
	}

	/**
	 * Extracts JS source code passed to eval() when literal.
	 *
	 * @param Node\CallExpression $node Call expression.
	 * @return string
	 */
	private function extract_js_eval_literal_code( Node\CallExpression $node ) {
		$arguments = $node->getArguments();
		if ( empty( $arguments ) || ! $arguments[0] instanceof Node\Literal ) {
			return '';
		}

		$value = $arguments[0]->getValue();

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Returns whether a message contains sprintf placeholders.
	 *
	 * @param string $message Message text.
	 * @return bool
	 */
	private function contains_sprintf_placeholder( $message ) {
		$message = (string) $message;

		return 1 === preg_match( '/(?<!%)%(?:[0-9]+\$)?[+-]?(?:0|\'.)?-?[0-9]*(?:\.(?:[ 0]|\'.)?[0-9]+)?[bcdeEfFgGosuxX]/', $message )
			|| 1 === preg_match( '/(?<!%)%(?:[0-9]+\$)?[+-]?(?:0|\'.)?-?[0-9]*(?:\.(?:[ 0]|\'.)?[0-9]+)?[%bcdeEfFgGosuxX]/', $message );
	}

	/**
	 * Returns whether a function name is supported.
	 *
	 * @param string $function_name Function name.
	 * @return bool
	 */
	private function is_supported_gettext_function( $function_name ) {
		return in_array(
			$function_name,
			array(
				'_',
				'__',
				'_e',
				'esc_html__',
				'esc_attr__',
				'esc_xml__',
				'esc_html_e',
				'esc_attr_e',
				'esc_xml_e',
				'_c',
				'_x',
				'_ex',
				'esc_html_x',
				'esc_attr_x',
				'esc_xml_x',
				'_n',
				'_nc',
				'_nx',
				'_n_noop',
				'_nx_noop',
				'__ngettext',
				'__ngettext_noop',
			),
			true
		);
	}

	/**
	 * Finds next non-whitespace token index.
	 *
	 * @param array<int, mixed> $tokens Tokens.
	 * @param int               $start_index Start index.
	 * @return int|null
	 */
	private function find_next_non_whitespace_token_index( array $tokens, $start_index ) {
		$token_count = count( $tokens );

		for ( $index = $start_index; $index < $token_count; $index++ ) {
			$token = $tokens[ $index ];

			if ( is_array( $token ) && T_WHITESPACE === $token[0] ) {
				continue;
			}

			return $index;
		}

		return null;
	}

	/**
	 * Parses function call arguments from token stream.
	 *
	 * @param array<int, mixed> $tokens Tokens.
	 * @param int               $open_parenthesis_index Opening parenthesis index.
	 * @return array{args: array<int, array<int, mixed>>, close_index: int}|null
	 */
	private function parse_function_call_arguments( array $tokens, $open_parenthesis_index ) {
		$depth       = 0;
		$arguments   = array();
		$current_arg = array();
		$token_count = count( $tokens );

		for ( $index = $open_parenthesis_index; $index < $token_count; $index++ ) {
			$token = $tokens[ $index ];

			if ( '(' === $token ) {
				if ( $depth > 0 ) {
					$current_arg[] = $token;
				}

				++$depth;
				continue;
			}

			if ( ')' === $token ) {
				--$depth;

				if ( 0 === $depth ) {
					$arguments[] = $current_arg;

					return array(
						'args'        => $arguments,
						'close_index' => $index,
					);
				}

				$current_arg[] = $token;
				continue;
			}

			if ( 1 === $depth && ',' === $token ) {
				$arguments[] = $current_arg;
				$current_arg = array();
				continue;
			}

			if ( $depth > 0 ) {
				$current_arg[] = $token;
			}
		}

		return null;
	}

	/**
	 * Builds one normalized entry from one gettext function call.
	 *
	 * @param string                        $function_name Function name.
	 * @param array<int, array<int, mixed>> $args Parsed args.
	 * @param string                        $relative_path Relative reference file path.
	 * @param int                           $line Source line.
	 * @param array<int, string>            $translator_comments Translator comments.
	 * @return array<string, mixed>|null
	 */
	private function build_entry_from_function_call( $function_name, array $args, $relative_path, $line, array $translator_comments = array() ) {
		$original = $this->token_argument_to_literal_string( $args, 0 );
		if ( null === $original || '' === $original ) {
			return null;
		}

		$entry = array(
			'original'   => $original,
			'references' => array(
				array(
					'file' => $relative_path,
					'line' => $line,
				),
			),
		);

		if ( ! empty( $translator_comments ) ) {
			$entry['comments'] = $translator_comments;
		}

		if ( $this->contains_sprintf_placeholder( $original ) ) {
			$entry['flags'] = array( 'php-format' );
		}

		if ( in_array( $function_name, array( '_x', '_ex', 'esc_html_x', 'esc_attr_x', 'esc_xml_x' ), true ) ) {
			$context = $this->token_argument_to_literal_string( $args, 1 );
			if ( null !== $context && '' !== $context ) {
				$entry['context'] = $context;
			}
		}

		if ( in_array( $function_name, array( '_n', '_nc', '_nx', '_n_noop', '_nx_noop', '__ngettext', '__ngettext_noop' ), true ) ) {
			$plural = $this->token_argument_to_literal_string( $args, 1 );
			if ( null !== $plural && '' !== $plural ) {
				$entry['plural'] = $plural;
			}

			if ( '_nx' === $function_name ) {
				$context = $this->token_argument_to_literal_string( $args, 3 );
				if ( null !== $context && '' !== $context ) {
					$entry['context'] = $context;
				}
			}

			if ( '_nx_noop' === $function_name ) {
				$context = $this->token_argument_to_literal_string( $args, 2 );
				if ( null !== $context && '' !== $context ) {
					$entry['context'] = $context;
				}
			}
		}

		return $entry;
	}

	/**
	 * Extracts translators comments found immediately before one gettext call.
	 *
	 * @param array<int, mixed> $tokens Token stream.
	 * @param int               $index Current token index.
	 * @return array<int, string>
	 */
	private function extract_translator_comments_before_index( array $tokens, $index ) {
		$comments     = array();
		$current_line = ( isset( $tokens[ $index ] ) && is_array( $tokens[ $index ] ) && isset( $tokens[ $index ][2] ) )
			? (int) $tokens[ $index ][2]
			: 0;

		for ( $cursor = $index - 1; $cursor >= 0; $cursor-- ) {
			$token = $tokens[ $cursor ];

			if ( is_array( $token ) && T_WHITESPACE === $token[0] ) {
				continue;
			}

			if ( is_array( $token ) && ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) ) {
				$comment_line = isset( $token[2] ) ? (int) $token[2] : 0;

				if ( $current_line > 0 && $comment_line > 0 && ( $current_line - $comment_line ) > 6 ) {
					break;
				}

				$comment_block = (string) $token[1];
				$lines         = preg_split( '/\r\n|\r|\n/', $comment_block );

				if ( false === $lines ) {
					continue;
				}

				foreach ( $lines as $line ) {
					$normalized = trim( (string) $line );
					$normalized = ltrim( $normalized, "/*# \t" );
					$normalized = preg_replace( '/\*\/$/', '', $normalized );

					if ( null === $normalized ) {
						continue;
					}

					$normalized = trim( $normalized );

					if ( 0 !== stripos( $normalized, 'translators:' ) ) {
						continue;
					}

					$comments[] = trim( $normalized );
				}

				continue;
			}

			if ( ';' === $token || '}' === $token || '{' === $token ) {
				break;
			}
		}

		return array_values( array_unique( $comments ) );
	}

	/**
	 * Converts one argument token list to a literal string when possible.
	 *
	 * @param array<int, array<int, mixed>> $args Parsed args.
	 * @param int                           $arg_index Argument index.
	 * @return string|null
	 */
	private function token_argument_to_literal_string( array $args, $arg_index ) {
		if ( ! isset( $args[ $arg_index ] ) || ! is_array( $args[ $arg_index ] ) ) {
			return null;
		}

		$argument_tokens = $args[ $arg_index ];

		foreach ( $argument_tokens as $token ) {
			if ( is_array( $token ) && T_WHITESPACE === $token[0] ) {
				continue;
			}

			if ( is_array( $token ) && T_CONSTANT_ENCAPSED_STRING === $token[0] ) {
				$raw = (string) $token[1];
				if ( strlen( $raw ) < 2 ) {
					return null;
				}

				$quote = $raw[0];
				if ( '"' !== $quote && '\'' !== $quote ) {
					return null;
				}

				$content = substr( $raw, 1, -1 );

				return stripcslashes( $content );
			}

			return null;
		}

		return null;
	}
}
