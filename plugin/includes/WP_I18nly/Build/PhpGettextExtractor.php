<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Gettext entry extraction from PHP and Blade sources.
 *
 * @package I18nly
 */

namespace WP_I18nly\Build;

defined( 'ABSPATH' ) || exit;

/**
 * Extracts gettext entries from PHP code using the PHP tokenizer, and from Blade templates.
 */
class PhpGettextExtractor {
	/**
	 * Extracts gettext entries from PHP code.
	 *
	 * @param string $code PHP source code.
	 * @param string $relative_path Relative reference file path.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_from_php( $code, $relative_path ) {
		$entries     = array();
		$tokens      = token_get_all( $code );
		$token_count = count( $tokens );

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

			if ( null !== $entry ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Extracts gettext-relevant PHP snippets from a Blade template.
	 *
	 * @param string $template Blade template source.
	 * @return string
	 */
	private function compile_blade_template( $template ) {
		$chunks = array();

		if ( preg_match_all( '/\{\{\s*(.*?)\s*\}\}|\{!!\s*(.*?)\s*!!\}/s', $template, $matches ) ) {
			$expressions = array_merge( $matches[1], $matches[2] );
			foreach ( $expressions as $expression ) {
				$expression = trim( (string) $expression );
				if ( '' === $expression ) {
					continue;
				}

				$chunks[] = '<?php ' . rtrim( $expression, ';' ) . '; ?>';
			}
		}

		if ( preg_match_all( '/@php\s*(.*?)\s*@endphp/s', $template, $php_blocks ) ) {
			foreach ( $php_blocks[1] as $php_block ) {
				$php_block = trim( (string) $php_block );
				if ( '' === $php_block ) {
					continue;
				}

				$chunks[] = "<?php\n" . $php_block . "\n?>";
			}
		}

		if ( preg_match_all( '/<x[-:\\w]+\\s+((?:[^>"\']*(?:"[^"]*"|\'[^\']*\'))*[^>"]*)\\/?/s', $template, $tag_matches ) ) {
			foreach ( $tag_matches[1] as $attributes ) {
				if ( preg_match_all( '/(?<!\\w):[\\w.-]+=(["\'])(.*?)\\1/s', $attributes, $attr_matches ) ) {
					foreach ( $attr_matches[2] as $expression ) {
						$expression = trim( (string) $expression );
						if ( '' === $expression ) {
							continue;
						}

						$chunks[] = '<?php ' . rtrim( $expression, ';' ) . '; ?>';
					}
				}
			}
		}

		return implode( "\n", $chunks );
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

		if ( GettextPlaceholders::contains_sprintf_placeholder( $original ) ) {
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

	/**
	 * Extracts gettext entries from a Blade template.
	 *
	 * Only the PHP expressions of the template are scanned: echo tags, @php blocks and bound
	 * component attributes.
	 *
	 * @param string $template Blade template source.
	 * @param string $relative_path Relative reference file path.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_from_blade( $template, $relative_path ) {
		$compiled = $this->compile_blade_template( $template );

		if ( '' === $compiled ) {
			return array();
		}

		return $this->extract_from_php( $compiled, $relative_path );
	}
}
