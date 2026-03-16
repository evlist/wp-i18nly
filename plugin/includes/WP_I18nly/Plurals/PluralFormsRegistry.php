<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Plural forms registry backed by generated language classes.
 *
 * @package I18nly
 */

namespace WP_I18nly\Plurals;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves plural forms metadata for one locale.
 */
class PluralFormsRegistry {
	/**
	 * Default spec used when locale is unknown.
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULT_SPEC = array(
		'nplurals'          => 2,
		'plural_expression' => '(n != 1)',
		'forms'             => array(
			array(
				'marker'   => 'a',
				'label'    => 'a',
				'tooltip'  => 'One',
				'examples' => array( 1 ),
			),
			array(
				'marker'   => 'b',
				'label'    => 'b',
				'tooltip'  => 'Other than one',
				'examples' => array( 0, 2 ),
			),
		),
	);

	/**
	 * Returns plural spec for one locale.
	 *
	 * @param string $locale Target locale.
	 * @return array<string, mixed>
	 */
	public static function get_spec_for_locale( $locale ) {
		$resolver = new LanguageSpecResolver();
		$raw_spec = $resolver->resolve_spec_for_locale( (string) $locale );

		if ( ! is_array( $raw_spec ) ) {
			$raw_spec = self::DEFAULT_SPEC;
		}

		return self::normalize_spec_for_ui( $raw_spec );
	}

	/**
	 * Returns plural forms count for one locale.
	 *
	 * @param string $locale Target locale.
	 * @return int
	 */
	public static function get_plural_forms_count_for_locale( $locale ) {
		$spec = self::get_spec_for_locale( $locale );

		if ( isset( $spec['nplurals'] ) ) {
			return max( 1, (int) $spec['nplurals'] );
		}

		return (int) self::DEFAULT_SPEC['nplurals'];
	}

	/**
	 * Returns ordered form labels for one locale.
	 *
	 * @param string $locale Target locale.
	 * @return array<int, string>
	 */
	public static function get_form_labels_for_locale( $locale ) {
		$forms  = self::get_forms_for_locale( $locale );
		$labels = array();

		foreach ( $forms as $form ) {
			$labels[] = isset( $form['label'] ) ? (string) $form['label'] : '';
		}

		return $labels;
	}

	/**
	 * Returns ordered form marker symbols for one locale.
	 *
	 * @param string $locale Target locale.
	 * @return array<int, string>
	 */
	public static function get_form_markers_for_locale( $locale ) {
		$forms   = self::get_forms_for_locale( $locale );
		$markers = array();

		foreach ( $forms as $form ) {
			$markers[] = isset( $form['marker'] ) ? (string) $form['marker'] : '';
		}

		return $markers;
	}

	/**
	 * Returns ordered form tooltips for one locale.
	 *
	 * @param string $locale Target locale.
	 * @return array<int, string>
	 */
	public static function get_form_tooltips_for_locale( $locale ) {
		$forms    = self::get_forms_for_locale( $locale );
		$tooltips = array();

		foreach ( $forms as $form ) {
			$tooltips[] = isset( $form['tooltip'] ) ? (string) $form['tooltip'] : '';
		}

		return $tooltips;
	}

	/**
	 * Returns ordered witness examples for each plural form.
	 *
	 * @param string $locale Target locale.
	 * @return array<int, array<int, int>>
	 */
	public static function get_form_examples_for_locale( $locale ) {
		$spec     = self::get_spec_for_locale( $locale );
		$nplurals = isset( $spec['nplurals'] ) ? max( 1, (int) $spec['nplurals'] ) : 1;
		$forms    = isset( $spec['forms'] ) && is_array( $spec['forms'] ) ? $spec['forms'] : array();
		$raw      = self::extract_form_examples_from_forms( $forms, $nplurals );

		if ( empty( $raw ) && isset( $spec['form_examples'] ) && is_array( $spec['form_examples'] ) ) {
			$raw = self::normalize_form_examples( $spec['form_examples'], $nplurals );
		}

		$result = array();

		for ( $index = 0; $index < $nplurals; $index++ ) {
			$examples = isset( $raw[ $index ] ) && is_array( $raw[ $index ] )
				? array_values( array_map( 'intval', $raw[ $index ] ) )
				: array();

			if ( empty( $examples ) ) {
				$fallback   = ( 0 === $index ) ? 1 : 2;
				$examples[] = $fallback;
			}

			$result[ $index ] = array_values( array_unique( $examples ) );
		}

		return $result;
	}

	/**
	 * Returns ordered form metadata for one locale.
	 *
	 * @param string $locale Target locale.
	 * @return array<int, array{marker: string, label: string, tooltip: string}>
	 */
	public static function get_forms_for_locale( $locale ) {
		$spec = self::get_spec_for_locale( $locale );

		if ( isset( $spec['forms'] ) && is_array( $spec['forms'] ) ) {
			return $spec['forms'];
		}

		return self::DEFAULT_SPEC['forms'];
	}

	/**
	 * Returns gettext plural expression for one locale.
	 *
	 * @param string $locale Target locale.
	 * @return string
	 */
	public static function get_plural_expression_for_locale( $locale ) {
		$spec = self::get_spec_for_locale( $locale );

		if ( isset( $spec['plural_expression'] ) && is_string( $spec['plural_expression'] ) ) {
			return $spec['plural_expression'];
		}

		return (string) self::DEFAULT_SPEC['plural_expression'];
	}

	/**
	 * Returns plural form index for one locale and quantity.
	 *
	 * @param string $locale Target locale.
	 * @param int    $quantity Quantity value.
	 * @return int
	 */
	public static function get_form_index_for_locale( $locale, $quantity ) {
		$spec       = self::get_spec_for_locale( $locale );
		$nplurals   = isset( $spec['nplurals'] ) ? max( 1, (int) $spec['nplurals'] ) : 2;
		$expression = isset( $spec['plural_expression'] ) && is_string( $spec['plural_expression'] )
			? $spec['plural_expression']
			: (string) self::DEFAULT_SPEC['plural_expression'];

		$n      = max( 0, (int) $quantity );
		$tokens = self::tokenize_plural_expression( $expression );

		if ( empty( $tokens ) ) {
			return ( 1 === $n ) ? 0 : min( 1, $nplurals - 1 );
		}

		$position = 0;
		$index    = self::parse_plural_conditional( $tokens, $position, $n );
		$index    = (int) $index;

		if ( $index < 0 ) {
			return 0;
		}

		if ( $index >= $nplurals ) {
			return $nplurals - 1;
		}

		return $index;
	}

	/**
	 * Normalizes resolver spec to UI-oriented forms format.
	 *
	 * @param array<string, mixed> $spec Raw resolver spec.
	 * @return array<string, mixed>
	 */
	private static function normalize_spec_for_ui( array $spec ) {
		$nplurals          = isset( $spec['nplurals'] ) ? max( 1, (int) $spec['nplurals'] ) : 2;
		$forms             = array();
		$raw_form_examples = isset( $spec['form_examples'] ) && is_array( $spec['form_examples'] )
			? self::normalize_form_examples( $spec['form_examples'], $nplurals )
			: array();

		if ( isset( $spec['forms'] ) && is_array( $spec['forms'] ) ) {
			$index = 0;
			foreach ( $spec['forms'] as $entry ) {
				$marker   = self::marker_from_index( $index );
				$label    = $marker;
				$tooltip  = 'other';
				$examples = isset( $raw_form_examples[ $index ] ) && is_array( $raw_form_examples[ $index ] )
					? $raw_form_examples[ $index ]
					: array( 0 === $index ? 1 : 2 );

				if ( is_array( $entry ) ) {
					if ( isset( $entry['label'] ) && '' !== trim( (string) $entry['label'] ) ) {
						$label  = (string) $entry['label'];
						$marker = (string) $entry['label'];
					}
					if ( isset( $entry['tooltip'] ) && '' !== trim( (string) $entry['tooltip'] ) ) {
						$tooltip = (string) $entry['tooltip'];
					}
					if ( isset( $entry['examples'] ) && is_array( $entry['examples'] ) ) {
						$examples = array_values( array_map( 'intval', $entry['examples'] ) );
					}
				} elseif ( is_string( $entry ) ) {
					$tooltip = trim( $entry ) !== '' ? $entry : 'other';
				}

				$forms[] = array(
					'marker'   => $marker,
					'label'    => $label,
					'tooltip'  => $tooltip,
					'examples' => array_values( array_unique( $examples ) ),
				);
				++$index;
			}
		}

		for ( $index = count( $forms ); $index < $nplurals; $index++ ) {
			$marker  = self::marker_from_index( $index );
			$forms[] = array(
				'marker'   => $marker,
				'label'    => $marker,
				'tooltip'  => __( 'other', 'i18nly' ),
				'examples' => array( 0 === $index ? 1 : 2 ),
			);
		}

		$spec['nplurals'] = count( $forms );
		$spec['forms']    = $forms;

		if ( ! isset( $spec['plural_expression'] ) || ! is_string( $spec['plural_expression'] ) || '' === trim( $spec['plural_expression'] ) ) {
			$spec['plural_expression'] = (string) self::DEFAULT_SPEC['plural_expression'];
		}

		return $spec;
	}

	/**
	 * Extracts per-form examples from normalized forms.
	 *
	 * @param array<int, mixed> $forms Normalized forms.
	 * @param int               $nplurals Number of plural forms.
	 * @return array<int, array<int, int>>
	 */
	private static function extract_form_examples_from_forms( array $forms, $nplurals ) {
		$normalized = array();
		$count      = max( 1, (int) $nplurals );

		for ( $index = 0; $index < $count; $index++ ) {
			$examples = isset( $forms[ $index ] ) && is_array( $forms[ $index ] ) && isset( $forms[ $index ]['examples'] ) && is_array( $forms[ $index ]['examples'] )
				? array_values( array_map( 'intval', $forms[ $index ]['examples'] ) )
				: array();

			if ( empty( $examples ) ) {
				$examples = array( 0 === $index ? 1 : 2 );
			}

			sort( $examples );
			$normalized[ $index ] = array_values( array_unique( $examples ) );
		}

		return $normalized;
	}

	/**
	 * Normalizes per-form examples list.
	 *
	 * @param array<int|string, mixed> $form_examples Raw examples list.
	 * @param int                      $nplurals Number of plural forms.
	 * @return array<int, array<int, int>>
	 */
	private static function normalize_form_examples( array $form_examples, $nplurals ) {
		$normalized = array();
		$count      = max( 1, (int) $nplurals );

		for ( $index = 0; $index < $count; $index++ ) {
			$examples = isset( $form_examples[ $index ] ) && is_array( $form_examples[ $index ] )
				? array_values( array_map( 'intval', $form_examples[ $index ] ) )
				: array();

			if ( empty( $examples ) ) {
				$examples = array( 0 === $index ? 1 : 2 );
			}

			sort( $examples );
			$normalized[ $index ] = array_values( array_unique( $examples ) );
		}

		return $normalized;
	}

	/**
	 * Returns alphabetical marker for one index.
	 *
	 * @param int $index Marker index.
	 * @return string
	 */
	private static function marker_from_index( $index ) {
		$index  = max( 0, (int) $index );
		$marker = '';

		do {
			$marker = chr( 97 + ( $index % 26 ) ) . $marker;
			$index  = (int) floor( $index / 26 ) - 1;
		} while ( $index >= 0 );

		return $marker;
	}

	/**
	 * Tokenizes one gettext plural expression.
	 *
	 * @param string $expression Expression.
	 * @return array<int, string>
	 */
	private static function tokenize_plural_expression( $expression ) {
		$normalized = trim( (string) $expression );
		if ( '' === $normalized ) {
			return array();
		}

		$tokens = array();
		$offset = 0;
		$length = strlen( $normalized );

		while ( $offset < $length ) {
			if ( preg_match( '/\G\s+/', $normalized, $matches, 0, $offset ) ) {
				$offset += strlen( $matches[0] );
				continue;
			}

			if ( preg_match( '/\G(==|!=|<=|>=|\|\||&&|[()?:%<>]|n|\d+)/', $normalized, $matches, 0, $offset ) ) {
				$tokens[] = $matches[1];
				$offset  += strlen( $matches[1] );
				continue;
			}

			return array();
		}

		return $tokens;
	}

	/**
	 * Parses conditional expression level.
	 *
	 * @param array<int, string> $tokens Tokens.
	 * @param int                $position Current parser position.
	 * @param int                $n Quantity.
	 * @return int
	 */
	private static function parse_plural_conditional( array $tokens, &$position, $n ) {
		$value = self::parse_plural_or( $tokens, $position, $n );

		if ( isset( $tokens[ $position ] ) && '?' === $tokens[ $position ] ) {
			++$position;
			$when_true = self::parse_plural_conditional( $tokens, $position, $n );

			if ( isset( $tokens[ $position ] ) && ':' === $tokens[ $position ] ) {
				++$position;
			}

			$when_false = self::parse_plural_conditional( $tokens, $position, $n );

			return ( 0 !== (int) $value ) ? (int) $when_true : (int) $when_false;
		}

		return (int) $value;
	}

	/**
	 * Parses logical OR level.
	 *
	 * @param array<int, string> $tokens Tokens.
	 * @param int                $position Current parser position.
	 * @param int                $n Quantity.
	 * @return int
	 */
	private static function parse_plural_or( array $tokens, &$position, $n ) {
		$value = self::parse_plural_and( $tokens, $position, $n );

		while ( isset( $tokens[ $position ] ) && '||' === $tokens[ $position ] ) {
			++$position;
			$rhs   = self::parse_plural_and( $tokens, $position, $n );
			$value = ( 0 !== (int) $value || 0 !== (int) $rhs ) ? 1 : 0;
		}

		return (int) $value;
	}

	/**
	 * Parses logical AND level.
	 *
	 * @param array<int, string> $tokens Tokens.
	 * @param int                $position Current parser position.
	 * @param int                $n Quantity.
	 * @return int
	 */
	private static function parse_plural_and( array $tokens, &$position, $n ) {
		$value = self::parse_plural_equality( $tokens, $position, $n );

		while ( isset( $tokens[ $position ] ) && '&&' === $tokens[ $position ] ) {
			++$position;
			$rhs   = self::parse_plural_equality( $tokens, $position, $n );
			$value = ( 0 !== (int) $value && 0 !== (int) $rhs ) ? 1 : 0;
		}

		return (int) $value;
	}

	/**
	 * Parses equality level.
	 *
	 * @param array<int, string> $tokens Tokens.
	 * @param int                $position Current parser position.
	 * @param int                $n Quantity.
	 * @return int
	 */
	private static function parse_plural_equality( array $tokens, &$position, $n ) {
		$value = self::parse_plural_relational( $tokens, $position, $n );

		while ( isset( $tokens[ $position ] ) && in_array( $tokens[ $position ], array( '==', '!=' ), true ) ) {
			$operator = $tokens[ $position ];
			++$position;
			$rhs = self::parse_plural_relational( $tokens, $position, $n );

			if ( '==' === $operator ) {
				$value = ( (int) $value === (int) $rhs ) ? 1 : 0;
			} else {
				$value = ( (int) $value !== (int) $rhs ) ? 1 : 0;
			}
		}

		return (int) $value;
	}

	/**
	 * Parses relational level.
	 *
	 * @param array<int, string> $tokens Tokens.
	 * @param int                $position Current parser position.
	 * @param int                $n Quantity.
	 * @return int
	 */
	private static function parse_plural_relational( array $tokens, &$position, $n ) {
		$value = self::parse_plural_modulo( $tokens, $position, $n );

		while ( isset( $tokens[ $position ] ) && in_array( $tokens[ $position ], array( '<', '<=', '>', '>=' ), true ) ) {
			$operator = $tokens[ $position ];
			++$position;
			$rhs = self::parse_plural_modulo( $tokens, $position, $n );

			switch ( $operator ) {
				case '<':
					$value = ( (int) $value < (int) $rhs ) ? 1 : 0;
					break;
				case '<=':
					$value = ( (int) $value <= (int) $rhs ) ? 1 : 0;
					break;
				case '>':
					$value = ( (int) $value > (int) $rhs ) ? 1 : 0;
					break;
				case '>=':
					$value = ( (int) $value >= (int) $rhs ) ? 1 : 0;
					break;
			}
		}

		return (int) $value;
	}

	/**
	 * Parses modulo level.
	 *
	 * @param array<int, string> $tokens Tokens.
	 * @param int                $position Current parser position.
	 * @param int                $n Quantity.
	 * @return int
	 */
	private static function parse_plural_modulo( array $tokens, &$position, $n ) {
		$value = self::parse_plural_primary( $tokens, $position, $n );

		while ( isset( $tokens[ $position ] ) && '%' === $tokens[ $position ] ) {
			++$position;
			$rhs = self::parse_plural_primary( $tokens, $position, $n );

			$divisor = (int) $rhs;
			$value   = 0 === $divisor ? 0 : ( (int) $value % $divisor );
		}

		return (int) $value;
	}

	/**
	 * Parses primary values.
	 *
	 * @param array<int, string> $tokens Tokens.
	 * @param int                $position Current parser position.
	 * @param int                $n Quantity.
	 * @return int
	 */
	private static function parse_plural_primary( array $tokens, &$position, $n ) {
		if ( ! isset( $tokens[ $position ] ) ) {
			return 0;
		}

		$token = $tokens[ $position ];

		if ( 'n' === $token ) {
			++$position;
			return (int) $n;
		}

		if ( preg_match( '/^\d+$/', $token ) ) {
			++$position;
			return (int) $token;
		}

		if ( '(' === $token ) {
			++$position;
			$value = self::parse_plural_conditional( $tokens, $position, $n );

			if ( isset( $tokens[ $position ] ) && ')' === $tokens[ $position ] ) {
				++$position;
			}

			return (int) $value;
		}

		++$position;

		return 0;
	}
}
