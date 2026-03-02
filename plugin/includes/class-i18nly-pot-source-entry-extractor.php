<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Source entry extraction for POT generation.
 *
 * @package I18nly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Extracts POT entries from a plugin source tree.
 */
class I18nly_Pot_Source_Entry_Extractor {
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
		$php_files        = $this->list_php_files( $plugin_directory );
		$entries_map      = array();

		foreach ( $php_files as $file_path ) {
			$code = file_get_contents( $file_path );
			if ( false === $code ) {
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
					(int) $tokens[ $index ][2]
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
			}
		}

		return array_values( $entries_map );
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
	 * Returns whether a function name is supported.
	 *
	 * @param string $function_name Function name.
	 * @return bool
	 */
	private function is_supported_gettext_function( $function_name ) {
		return in_array(
			$function_name,
			array(
				'__',
				'_e',
				'esc_html__',
				'esc_attr__',
				'esc_html_e',
				'esc_attr_e',
				'_x',
				'_ex',
				'esc_html_x',
				'esc_attr_x',
				'_n',
				'_nx',
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
		$depth         = 0;
		$arguments     = array();
		$current_arg   = array();
		$token_count   = count( $tokens );

		for ( $index = $open_parenthesis_index; $index < $token_count; $index++ ) {
			$token = $tokens[ $index ];

			if ( '(' === $token ) {
				if ( $depth > 0 ) {
					$current_arg[] = $token;
				}

				$depth++;
				continue;
			}

			if ( ')' === $token ) {
				$depth--;

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
	 * @param string                $function_name Function name.
	 * @param array<int, array<int, mixed>> $args Parsed args.
	 * @param string                $relative_path Relative reference file path.
	 * @param int                   $line Source line.
	 * @return array<string, mixed>|null
	 */
	private function build_entry_from_function_call( $function_name, array $args, $relative_path, $line ) {
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

		if ( in_array( $function_name, array( '_x', '_ex', 'esc_html_x', 'esc_attr_x' ), true ) ) {
			$context = $this->token_argument_to_literal_string( $args, 1 );
			if ( null !== $context && '' !== $context ) {
				$entry['context'] = $context;
			}
		}

		if ( in_array( $function_name, array( '_n', '_nx' ), true ) ) {
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
		}

		return $entry;
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
