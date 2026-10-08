<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Gettext entry extraction from JavaScript sources.
 *
 * @package I18nly
 */

namespace WP_I18nly\Build;

use Peast\Peast;
use Peast\Syntax\Node;
use Peast\Traverser;

defined( 'ABSPATH' ) || exit;

/**
 * Extracts gettext entries from JavaScript code with a Peast syntax tree, and from the sources embedded in sourcemaps.
 */
class JsGettextExtractor {
	/**
	 * Extracts gettext entries from JS/TS code.
	 *
	 * @param string $code Source code.
	 * @param string $relative_path Relative reference file path.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_from_code( $code, $relative_path ) {
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
			// Typically TypeScript with type annotations, which the parser rejects: read the calls without parsing.
			return $this->extract_from_unparsable_code( $code, $relative_path, $lines );
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

				$line                = $node->getLocation()->getStart()->getLine();
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

				$nested_entries = $this->extract_from_code( $eval_code, $relative_path );
				foreach ( $nested_entries as $nested_entry ) {
					$entries[] = $nested_entry;
				}
			}
		);

		$traverser->traverse( $ast );

		return $entries;
	}

	/**
	 * Extracts gettext entries from code the parser rejects, by scanning for the calls.
	 *
	 * Only the calls of __, _x, _n and _nx are found, with string literal arguments; code that is not
	 * valid script, JSX text with quotes or regular expressions may hide calls.
	 *
	 * @param string            $code Source code.
	 * @param string            $relative_path Relative reference file path.
	 * @param array<int,string> $lines Source lines.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_from_unparsable_code( $code, $relative_path, array $lines ) {
		$entries = array();
		$calls   = ( new TypedScriptCallScanner() )->scan(
			$code,
			function ( $name ) {
				return 'eval' !== strtolower( (string) $name ) && $this->is_supported_js_gettext_function( $name );
			}
		);

		foreach ( $calls as $call ) {
			$entry = $this->build_entry_from_js_gettext_call(
				$call['name'],
				$call['args'],
				$relative_path,
				$call['line'],
				$this->extract_js_translator_comments_near_line( $lines, $call['line'] )
			);

			if ( null !== $entry ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Extracts gettext entries from JS code embedded in a .js.map file.
	 *
	 * @param string $map_contents Sourcemap JSON content.
	 * @param string $relative_map_path Relative sourcemap path.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_from_sourcemap( $map_contents, $relative_map_path ) {
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

		return $this->extract_from_code( $concatenated_sources, $reference_file );
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
	 * @param string             $function_name Function name.
	 * @param array<int, mixed>  $args Parsed args.
	 * @param string             $relative_path Relative reference file path.
	 * @param int                $line Source line.
	 * @param array<int, string> $translator_comments Translator comments.
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

		if ( GettextPlaceholders::contains_sprintf_placeholder( $original ) ) {
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

			// JSX comments are wrapped in braces: { /* translators: ... */ }.
			$text = trim( $text, "{} \t" );
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
}
