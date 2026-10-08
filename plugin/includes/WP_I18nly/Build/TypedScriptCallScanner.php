<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Scanner of gettext calls in scripts that cannot be parsed.
 *
 * @package I18nly
 */

namespace WP_I18nly\Build;

defined( 'ABSPATH' ) || exit;

/**
 * Finds the calls of some functions in the source of a script without parsing it.
 *
 * It is the fallback of the JavaScript extractor for the code the syntax tree parser rejects, typically
 * TypeScript with type annotations. It reads the code character by character, skips comments and string
 * contents, and returns the calls of the wanted functions with the value of their string literal
 * arguments (an empty string for any other argument).
 */
class TypedScriptCallScanner {
	/**
	 * Source code.
	 *
	 * @var string
	 */
	private $code = '';

	/**
	 * Length of the source code.
	 *
	 * @var int
	 */
	private $length = 0;

	/**
	 * Scans a script.
	 *
	 * @param string   $code Source code.
	 * @param callable $is_wanted_function Receives a function name and tells whether its calls are wanted.
	 * @return array<int, array{name: string, args: string[], line: int}>
	 */
	public function scan( $code, callable $is_wanted_function ) {
		$this->code   = (string) $code;
		$this->length = strlen( $this->code );
		$calls        = array();
		$position     = 0;

		while ( $position < $this->length ) {
			$character = $this->code[ $position ];

			if ( $this->starts_comment( $position ) ) {
				$position = $this->skip_comment( $position );
				continue;
			}

			if ( "'" === $character || '"' === $character || '`' === $character ) {
				$read     = $this->read_string( $position );
				$position = $read['end'];
				continue;
			}

			if ( 1 !== preg_match( '/[A-Za-z_$]/', $character ) ) {
				++$position;
				continue;
			}

			preg_match( '/\G[\w$]+/', $this->code, $matches, 0, $position );
			$name      = $matches[0];
			$name_end  = $position + strlen( $name );
			$arguments = $this->read_call_arguments( $name_end );

			if ( null !== $arguments && $is_wanted_function( $name ) && ! $this->is_declaration( $position ) ) {
				$calls[] = array(
					'name' => $name,
					'args' => $arguments['args'],
					'line' => 1 + substr_count( $this->code, "\n", 0, $position ),
				);
			}

			$position = $name_end;
		}

		return $calls;
	}

	/**
	 * Tells whether the identifier at a position is the name of a function being declared.
	 *
	 * @param int $position Position of the identifier.
	 * @return bool
	 */
	private function is_declaration( $position ) {
		return 1 === preg_match( '/\bfunction\s*\*?\s*$/', substr( $this->code, max( 0, $position - 20 ), min( 20, $position ) ) );
	}

	/**
	 * Reads the arguments of a call whose name ends at a position, or returns null when it is not a call.
	 *
	 * @param int $position Position after the function name.
	 * @return array{args: string[]}|null
	 */
	private function read_call_arguments( $position ) {
		$position = $this->skip_whitespace_and_comments( $position );

		if ( $position >= $this->length || '(' !== $this->code[ $position ] ) {
			return null;
		}

		++$position;

		$args = array();

		while ( $position < $this->length ) {
			$position = $this->skip_whitespace_and_comments( $position );

			if ( $position >= $this->length ) {
				return null;
			}

			if ( ')' === $this->code[ $position ] ) {
				return array( 'args' => $args );
			}

			$argument = $this->read_argument( $position );
			$args[]   = $argument['value'];
			$position = $this->skip_whitespace_and_comments( $argument['end'] );

			if ( $position < $this->length && ',' === $this->code[ $position ] ) {
				++$position;
			}
		}

		return null;
	}

	/**
	 * Reads one argument: a string literal alone gives its value, anything else gives an empty string.
	 *
	 * @param int $position Position of the first character of the argument.
	 * @return array{value: string, end: int}
	 */
	private function read_argument( $position ) {
		$first = $this->code[ $position ];

		if ( "'" === $first || '"' === $first || '`' === $first ) {
			$read  = $this->read_string( $position );
			$after = $this->skip_whitespace_and_comments( $read['end'] );

			if ( $read['plain'] && $after < $this->length && ( ',' === $this->code[ $after ] || ')' === $this->code[ $after ] ) ) {
				return array(
					'value' => $read['value'],
					'end'   => $read['end'],
				);
			}
		}

		return array(
			'value' => '',
			'end'   => $this->skip_expression( $position ),
		);
	}

	/**
	 * Skips an expression up to the comma or closing parenthesis that ends the argument.
	 *
	 * @param int $position Start of the expression.
	 * @return int Position of that comma or parenthesis.
	 */
	private function skip_expression( $position ) {
		$depth = 0;

		while ( $position < $this->length ) {
			$character = $this->code[ $position ];

			if ( $this->starts_comment( $position ) ) {
				$position = $this->skip_comment( $position );
				continue;
			}

			if ( "'" === $character || '"' === $character || '`' === $character ) {
				$position = $this->read_string( $position )['end'];
				continue;
			}

			if ( false !== strpos( '([{', $character ) ) {
				++$depth;
			} elseif ( false !== strpos( ')]}', $character ) ) {
				if ( 0 === $depth ) {
					return $position;
				}

				--$depth;
			} elseif ( ',' === $character && 0 === $depth ) {
				return $position;
			}

			++$position;
		}

		return $position;
	}

	/**
	 * Reads a string literal.
	 *
	 * @param int $position Position of the opening quote.
	 * @return array{value: string, end: int, plain: bool} The value, the position after the closing quote, and whether the literal has no template expression.
	 */
	private function read_string( $position ) {
		$quote = $this->code[ $position ];
		$value = '';
		$plain = true;
		++$position;

		while ( $position < $this->length ) {
			$character = $this->code[ $position ];

			// A quoted string ends with its line: a stray apostrophe (JSX text, regular expression) must not swallow the code.
			if ( "\n" === $character && '`' !== $quote ) {
				return array(
					'value' => $value,
					'end'   => $position,
					'plain' => false,
				);
			}

			if ( '\\' === $character ) {
				$value   .= $this->unescape( $position );
				$position = $this->escape_end( $position );
				continue;
			}

			if ( $character === $quote ) {
				return array(
					'value' => $value,
					'end'   => $position + 1,
					'plain' => $plain,
				);
			}

			if ( '`' === $quote && '$' === $character && $position + 1 < $this->length && '{' === $this->code[ $position + 1 ] ) {
				$plain = false;
			}

			$value .= $character;
			++$position;
		}

		return array(
			'value' => $value,
			'end'   => $position,
			'plain' => false,
		);
	}

	/**
	 * Returns the character represented by the escape sequence at a position.
	 *
	 * @param int $position Position of the backslash.
	 * @return string
	 */
	private function unescape( $position ) {
		$next = $position + 1 < $this->length ? $this->code[ $position + 1 ] : '';
		$map  = array(
			'n' => "\n",
			't' => "\t",
			'r' => "\r",
			'b' => "\x08",
			'f' => "\f",
			'v' => "\v",
			'0' => "\0",
		);

		if ( 'u' === $next && 1 === preg_match( '/\G\\\\u(?:\{([0-9a-fA-F]+)\}|([0-9a-fA-F]{4}))/', $this->code, $matches, 0, $position ) ) {
			return (string) mb_chr( (int) hexdec( '' !== $matches[1] ? $matches[1] : $matches[2] ), 'UTF-8' );
		}

		if ( 'x' === $next && 1 === preg_match( '/\G\\\\x([0-9a-fA-F]{2})/', $this->code, $matches, 0, $position ) ) {
			return (string) mb_chr( (int) hexdec( $matches[1] ), 'UTF-8' );
		}

		if ( "\n" === $next ) {
			return '';
		}

		return isset( $map[ $next ] ) ? $map[ $next ] : $next;
	}

	/**
	 * Returns the position after the escape sequence at a position.
	 *
	 * @param int $position Position of the backslash.
	 * @return int
	 */
	private function escape_end( $position ) {
		if ( 1 === preg_match( '/\G\\\\(?:u\{[0-9a-fA-F]+\}|u[0-9a-fA-F]{4}|x[0-9a-fA-F]{2})/', $this->code, $matches, 0, $position ) ) {
			return $position + strlen( $matches[0] );
		}

		return $position + 2;
	}

	/**
	 * Tells whether a comment starts at a position.
	 *
	 * @param int $position Position.
	 * @return bool
	 */
	private function starts_comment( $position ) {
		return '/' === $this->code[ $position ] && $position + 1 < $this->length && ( '/' === $this->code[ $position + 1 ] || '*' === $this->code[ $position + 1 ] );
	}

	/**
	 * Returns the position after the comment starting at a position.
	 *
	 * @param int $position Position of the comment start.
	 * @return int
	 */
	private function skip_comment( $position ) {
		if ( '/' === $this->code[ $position + 1 ] ) {
			$end = strpos( $this->code, "\n", $position );

			return false === $end ? $this->length : $end + 1;
		}

		$end = strpos( $this->code, '*/', $position + 2 );

		return false === $end ? $this->length : $end + 2;
	}

	/**
	 * Skips spaces and comments.
	 *
	 * @param int $position Position.
	 * @return int
	 */
	private function skip_whitespace_and_comments( $position ) {
		while ( $position < $this->length ) {
			if ( 1 === preg_match( '/\s/', $this->code[ $position ] ) ) {
				++$position;
			} elseif ( $this->starts_comment( $position ) ) {
				$position = $this->skip_comment( $position );
			} else {
				break;
			}
		}

		return $position;
	}
}
