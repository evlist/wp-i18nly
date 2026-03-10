<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * CLDR-derived plural forms registry.
 *
 * @package I18nly
 */

namespace WP_I18nly\Plurals;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves plural forms metadata for one locale.
 *
 * The embedded values are derived from Unicode CLDR plural rules.
 */
class PluralFormsRegistry {
	/**
	 * Default spec used when locale is unknown.
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULT_SPEC = array(
		'nplurals'          => 2,
		'categories'        => array( 'one', 'other' ),
		'plural_expression' => '(n != 1)',
		'forms'             => array(
			array(
				'marker'  => 'a',
				'label'   => 'one',
				'tooltip' => 'One',
			),
			array(
				'marker'  => 'b',
				'label'   => 'other',
				'tooltip' => 'Other values',
			),
		),
	);

	/**
	 * Language to plural forms spec map.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private const LANGUAGE_SPEC_MAP = array(
		'ar' => array(
			'nplurals'          => 6,
			'categories'        => array( 'zero', 'one', 'two', 'few', 'many', 'other' ),
			'plural_expression' => '(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 && n%100<=99 ? 4 : 5)',
		),
		'be' => array(
			'nplurals'          => 3,
			'categories'        => array( 'one', 'few', 'many' ),
			'plural_expression' => '(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : 2)',
		),
		'bs' => array(
			'nplurals'          => 3,
			'categories'        => array( 'one', 'few', 'other' ),
			'plural_expression' => '(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : 2)',
		),
		'cs' => array(
			'nplurals'          => 3,
			'categories'        => array( 'one', 'few', 'other' ),
			'plural_expression' => '(n==1 ? 0 : n>=2 && n<=4 ? 1 : 2)',
		),
		'dz' => array(
			'nplurals'          => 1,
			'categories'        => array( 'other' ),
			'plural_expression' => '0',
		),
		'fr' => array(
			'nplurals'          => 2,
			'categories'        => array( 'one', 'other' ),
			'plural_expression' => '(n > 1)',
			'forms'             => array(
				'a' => 'Zero or one',
				'b' => 'More than one',
			),
		),
		'ga' => array(
			'nplurals'          => 5,
			'categories'        => array( 'one', 'two', 'few', 'many', 'other' ),
			'plural_expression' => '(n==1 ? 0 : n==2 ? 1 : n<7 ? 2 : n<11 ? 3 : 4)',
		),
		'hr' => array(
			'nplurals'          => 3,
			'categories'        => array( 'one', 'few', 'other' ),
			'plural_expression' => '(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : 2)',
		),
		'id' => array(
			'nplurals'          => 1,
			'categories'        => array( 'other' ),
			'plural_expression' => '0',
		),
		'ja' => array(
			'nplurals'          => 1,
			'categories'        => array( 'other' ),
			'plural_expression' => '0',
		),
		'km' => array(
			'nplurals'          => 1,
			'categories'        => array( 'other' ),
			'plural_expression' => '0',
		),
		'ko' => array(
			'nplurals'          => 1,
			'categories'        => array( 'other' ),
			'plural_expression' => '0',
		),
		'lo' => array(
			'nplurals'          => 1,
			'categories'        => array( 'other' ),
			'plural_expression' => '0',
		),
		'ms' => array(
			'nplurals'          => 1,
			'categories'        => array( 'other' ),
			'plural_expression' => '0',
		),
		'my' => array(
			'nplurals'          => 1,
			'categories'        => array( 'other' ),
			'plural_expression' => '0',
		),
		'pl' => array(
			'nplurals'          => 3,
			'categories'        => array( 'one', 'few', 'many' ),
			'plural_expression' => '(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : 2)',
		),
		'ru' => array(
			'nplurals'          => 3,
			'categories'        => array( 'one', 'few', 'many' ),
			'plural_expression' => '(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : 2)',
		),
		'sk' => array(
			'nplurals'          => 3,
			'categories'        => array( 'one', 'few', 'other' ),
			'plural_expression' => '(n==1 ? 0 : n>=2 && n<=4 ? 1 : 2)',
		),
		'sl' => array(
			'nplurals'          => 4,
			'categories'        => array( 'one', 'two', 'few', 'other' ),
			'plural_expression' => '(n%100==1 ? 0 : n%100==2 ? 1 : n%100==3 || n%100==4 ? 2 : 3)',
		),
		'sr' => array(
			'nplurals'          => 3,
			'categories'        => array( 'one', 'few', 'other' ),
			'plural_expression' => '(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : 2)',
		),
		'th' => array(
			'nplurals'          => 1,
			'categories'        => array( 'other' ),
			'plural_expression' => '0',
		),
		'uk' => array(
			'nplurals'          => 3,
			'categories'        => array( 'one', 'few', 'many' ),
			'plural_expression' => '(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : 2)',
		),
		'vi' => array(
			'nplurals'          => 1,
			'categories'        => array( 'other' ),
			'plural_expression' => '0',
		),
		'zh' => array(
			'nplurals'          => 1,
			'categories'        => array( 'other' ),
			'plural_expression' => '0',
		),
	);

	/**
	 * Returns plural spec for one locale.
	 *
	 * @param string $locale Target locale.
	 * @return array<string, mixed>
	 */
	public static function get_spec_for_locale( $locale ) {
		$language = self::normalize_language( $locale );
		$spec     = self::DEFAULT_SPEC;

		if ( '' !== $language && isset( self::LANGUAGE_SPEC_MAP[ $language ] ) ) {
			$spec = self::LANGUAGE_SPEC_MAP[ $language ];
		}

		return self::enrich_spec_for_ui( $spec );
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
		$spec  = self::get_spec_for_locale( $locale );
		$forms = self::get_forms_from_spec( $spec );

		if ( ! empty( $forms ) ) {
			return self::pluck_forms_string_field( $forms, 'label' );
		}

		if ( isset( $spec['categories'] ) && is_array( $spec['categories'] ) ) {
			return array_values( $spec['categories'] );
		}

		return (array) self::DEFAULT_SPEC['categories'];
	}

	/**
	 * Returns ordered form marker symbols for one locale.
	 *
	 * @param string $locale Target locale.
	 * @return array<int, string>
	 */
	public static function get_form_markers_for_locale( $locale ) {
		$spec  = self::get_spec_for_locale( $locale );
		$forms = self::get_forms_from_spec( $spec );

		if ( ! empty( $forms ) ) {
			return self::pluck_forms_string_field( $forms, 'marker' );
		}

		if ( isset( $spec['form_markers'] ) && is_array( $spec['form_markers'] ) ) {
			return array_values( $spec['form_markers'] );
		}

		return array( 'a', 'b' );
	}

	/**
	 * Returns ordered form tooltips for one locale.
	 *
	 * @param string $locale Target locale.
	 * @return array<int, string>
	 */
	public static function get_form_tooltips_for_locale( $locale ) {
		$spec  = self::get_spec_for_locale( $locale );
		$forms = self::get_forms_from_spec( $spec );

		if ( ! empty( $forms ) ) {
			return self::pluck_forms_string_field( $forms, 'tooltip' );
		}

		if ( isset( $spec['form_tooltips'] ) && is_array( $spec['form_tooltips'] ) ) {
			return array_values( $spec['form_tooltips'] );
		}

		return array( 'One', 'Other values' );
	}

	/**
	 * Returns ordered form metadata for one locale.
	 *
	 * @param string $locale Target locale.
	 * @return array<int, array{marker: string, label: string, tooltip: string}>
	 */
	public static function get_forms_for_locale( $locale ) {
		$spec  = self::get_spec_for_locale( $locale );
		$forms = self::get_forms_from_spec( $spec );

		if ( ! empty( $forms ) ) {
			return $forms;
		}

		return self::get_forms_from_spec( self::DEFAULT_SPEC );
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
	 * Computes form index for language-specific rule.
	 *
	 * @param string $language Normalized language code.
	 * @param int    $n Quantity.
	 * @return int
	 */
	private static function compute_form_index_by_language( $language, $n ) {
		if ( '' === $language ) {
			return ( 1 === $n ) ? 0 : 1;
		}

		$one_form_languages = array( 'dz', 'id', 'ja', 'km', 'ko', 'lo', 'ms', 'my', 'th', 'vi', 'zh' );

		if ( in_array( $language, $one_form_languages, true ) ) {
			return 0;
		}

		switch ( $language ) {
			case 'fr':
				return ( $n > 1 ) ? 1 : 0;

			case 'cs':
			case 'sk':
				if ( 1 === $n ) {
					return 0;
				}

				if ( $n >= 2 && $n <= 4 ) {
					return 1;
				}

				return 2;

			case 'pl':
				if ( 1 === $n ) {
					return 0;
				}

				if ( $n % 10 >= 2 && $n % 10 <= 4 && ( $n % 100 < 12 || $n % 100 > 14 ) ) {
					return 1;
				}

				return 2;

			case 'be':
			case 'bs':
			case 'hr':
			case 'ru':
			case 'sr':
			case 'uk':
				if ( 1 === $n % 10 && 11 !== $n % 100 ) {
					return 0;
				}

				if ( $n % 10 >= 2 && $n % 10 <= 4 && ( $n % 100 < 12 || $n % 100 > 14 ) ) {
					return 1;
				}

				return 2;

			case 'sl':
				if ( 1 === $n % 100 ) {
					return 0;
				}

				if ( 2 === $n % 100 ) {
					return 1;
				}

				if ( 3 === $n % 100 || 4 === $n % 100 ) {
					return 2;
				}

				return 3;

			case 'ga':
				if ( 1 === $n ) {
					return 0;
				}

				if ( 2 === $n ) {
					return 1;
				}

				if ( $n < 7 ) {
					return 2;
				}

				if ( $n < 11 ) {
					return 3;
				}

				return 4;

			case 'ar':
				if ( 0 === $n ) {
					return 0;
				}

				if ( 1 === $n ) {
					return 1;
				}

				if ( 2 === $n ) {
					return 2;
				}

				if ( $n % 100 >= 3 && $n % 100 <= 10 ) {
					return 3;
				}

				if ( $n % 100 >= 11 && $n % 100 <= 99 ) {
					return 4;
				}

				return 5;

			default:
				return ( 1 === $n ) ? 0 : 1;
		}
	}

	/**
	 * Clamps one computed index to locale bounds.
	 *
	 * @param string $locale Target locale.
	 * @param int    $index Computed index.
	 * @return int
	 */
	private static function clamp_form_index( $locale, $index ) {
		$max = self::get_plural_forms_count_for_locale( $locale ) - 1;

		if ( $index < 0 ) {
			return 0;
		}

		if ( $index > $max ) {
			return $max;
		}

		return (int) $index;
	}

	/**
	 * Adds UI-oriented marker and tooltip fields to one spec.
	 *
	 * @param array<string, mixed> $spec Raw spec.
	 * @return array<string, mixed>
	 */
	private static function enrich_spec_for_ui( array $spec ) {
		$nplurals      = isset( $spec['nplurals'] ) ? max( 1, (int) $spec['nplurals'] ) : 2;
		$raw_forms     = isset( $spec['forms'] ) && is_array( $spec['forms'] ) ? $spec['forms'] : array();
		$form_count    = max( $nplurals, count( $raw_forms ) );
		$categories    = self::pad_labels( self::extract_categories( $spec ), $form_count );
		$form_markers  = self::pad_markers( self::extract_markers( $spec ), $form_count );
		$form_tooltips = self::pad_tooltips( self::extract_tooltips( $spec, $categories ), $form_count );

		$spec['forms'] = self::normalize_forms(
			$raw_forms,
			$form_count,
			$form_markers,
			$categories,
			$form_tooltips
		);

		$spec['nplurals']      = count( $spec['forms'] );
		$spec['categories']    = self::pluck_forms_string_field( $spec['forms'], 'label' );
		$spec['form_markers']  = self::pluck_forms_string_field( $spec['forms'], 'marker' );
		$spec['form_tooltips'] = self::pluck_forms_string_field( $spec['forms'], 'tooltip' );

		return $spec;
	}

	/**
	 * Normalizes one forms definition array.
	 *
	 * @param array<int|string, mixed> $forms Raw forms array.
	 * @param int                      $count Expected count.
	 * @param array<int, string>       $markers Fallback markers.
	 * @param array<int, string>       $labels Fallback labels.
	 * @param array<int, string>       $tooltips Fallback tooltips.
	 * @return array<int, array{marker: string, label: string, tooltip: string}>
	 */
	private static function normalize_forms( array $forms, $count, array $markers, array $labels, array $tooltips ) {
		$normalized = array();

		for ( $index = 0; $index < $count; $index++ ) {
			$entry = null;

			if ( isset( $forms[ $index ] ) ) {
				$entry = $forms[ $index ];
			} elseif ( isset( $markers[ $index ] ) && isset( $forms[ $markers[ $index ] ] ) ) {
				$entry = $forms[ $markers[ $index ] ];
			}

			$marker  = isset( $markers[ $index ] ) ? (string) $markers[ $index ] : self::marker_from_index( $index );
			$label   = isset( $labels[ $index ] ) ? (string) $labels[ $index ] : (string) $index;
			$tooltip = isset( $tooltips[ $index ] ) ? (string) $tooltips[ $index ] : $label;

			if ( is_array( $entry ) ) {
				if ( isset( $entry['marker'] ) && '' !== trim( (string) $entry['marker'] ) ) {
					$marker = (string) $entry['marker'];
				}

				if ( isset( $entry['label'] ) && '' !== trim( (string) $entry['label'] ) ) {
					$label = (string) $entry['label'];
				}

				if ( isset( $entry['tooltip'] ) && '' !== trim( (string) $entry['tooltip'] ) ) {
					$tooltip = (string) $entry['tooltip'];
				}
			} elseif ( is_string( $entry ) && '' !== trim( $entry ) ) {
				$tooltip = $entry;
			}

			$normalized[] = array(
				'marker'  => $marker,
				'label'   => $label,
				'tooltip' => $tooltip,
			);
		}

		return $normalized;
	}

	/**
	 * Returns categories from one spec.
	 *
	 * @param array<string, mixed> $spec Raw spec.
	 * @return array<int, string>
	 */
	private static function extract_categories( array $spec ) {
		if ( isset( $spec['categories'] ) && is_array( $spec['categories'] ) ) {
			return array_values(
				array_map(
					'strval',
					$spec['categories']
				)
			);
		}

		return array();
	}

	/**
	 * Returns marker symbols from one spec.
	 *
	 * @param array<string, mixed> $spec Raw spec.
	 * @return array<int, string>
	 */
	private static function extract_markers( array $spec ) {
		if ( isset( $spec['form_markers'] ) && is_array( $spec['form_markers'] ) ) {
			return array_values(
				array_map(
					'strval',
					$spec['form_markers']
				)
			);
		}

		return array();
	}

	/**
	 * Returns tooltips from one spec.
	 *
	 * @param array<string, mixed> $spec Raw spec.
	 * @param array<int, string>   $categories Fallback categories.
	 * @return array<int, string>
	 */
	private static function extract_tooltips( array $spec, array $categories ) {
		if ( isset( $spec['form_tooltips'] ) && is_array( $spec['form_tooltips'] ) ) {
			return array_values(
				array_map(
					'strval',
					$spec['form_tooltips']
				)
			);
		}

		$tooltips = array();

		foreach ( $categories as $category ) {
			$tooltips[] = ucfirst( (string) $category );
		}

		return $tooltips;
	}

	/**
	 * Returns normalized forms list from one spec.
	 *
	 * @param array<string, mixed> $spec Spec.
	 * @return array<int, array{marker: string, label: string, tooltip: string}>
	 */
	private static function get_forms_from_spec( array $spec ) {
		if ( ! isset( $spec['forms'] ) || ! is_array( $spec['forms'] ) ) {
			return array();
		}

		$forms = array();

		foreach ( $spec['forms'] as $form ) {
			if ( ! is_array( $form ) ) {
				continue;
			}

			$marker  = isset( $form['marker'] ) ? (string) $form['marker'] : '';
			$label   = isset( $form['label'] ) ? (string) $form['label'] : '';
			$tooltip = isset( $form['tooltip'] ) ? (string) $form['tooltip'] : '';

			if ( '' === trim( $marker ) ) {
				continue;
			}

			$forms[] = array(
				'marker'  => $marker,
				'label'   => $label,
				'tooltip' => $tooltip,
			);
		}

		return $forms;
	}

	/**
	 * Returns one string field from forms list.
	 *
	 * @param array<int, array<string, mixed>> $forms Forms list.
	 * @param string                           $field Field key.
	 * @return array<int, string>
	 */
	private static function pluck_forms_string_field( array $forms, $field ) {
		$values = array();

		foreach ( $forms as $form ) {
			$values[] = isset( $form[ $field ] ) ? (string) $form[ $field ] : '';
		}

		return $values;
	}

	/**
	 * Pads marker array to expected count.
	 *
	 * @param array<int, string> $markers Marker symbols.
	 * @param int                $count Expected count.
	 * @return array<int, string>
	 */
	private static function pad_markers( array $markers, $count ) {
		$normalized = array_values(
			array_map(
				'strval',
				$markers
			)
		);

		for ( $index = count( $normalized ); $index < $count; $index++ ) {
			$normalized[] = self::marker_from_index( $index );
		}

		return array_slice( $normalized, 0, $count );
	}

	/**
	 * Pads form labels array to expected count.
	 *
	 * @param array<int, string> $labels Labels.
	 * @param int                $count Expected count.
	 * @return array<int, string>
	 */
	private static function pad_labels( array $labels, $count ) {
		$normalized = array_values(
			array_map(
				'strval',
				$labels
			)
		);

		for ( $index = count( $normalized ); $index < $count; $index++ ) {
			$normalized[] = sprintf( 'form-%d', $index );
		}

		return array_slice( $normalized, 0, $count );
	}

	/**
	 * Pads tooltips array to expected count.
	 *
	 * @param array<int, mixed> $tooltips Tooltips.
	 * @param int               $count Expected count.
	 * @return array<int, string>
	 */
	private static function pad_tooltips( array $tooltips, $count ) {
		$normalized = array();

		foreach ( $tooltips as $tooltip ) {
			$normalized[] = (string) $tooltip;
		}

		$normalized_count = count( $normalized );

		while ( $normalized_count < $count ) {
			$normalized[] = sprintf(
				/* translators: %d is plural form index. */
				__( 'Plural form %d', 'i18nly' ),
				$normalized_count
			);

			++$normalized_count;
		}

		return array_slice( $normalized, 0, $count );
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
	 * Extracts normalized language code from locale.
	 *
	 * @param string $locale Target locale.
	 * @return string
	 */
	private static function normalize_language( $locale ) {
		$locale = strtolower( (string) $locale );

		if ( '' === $locale ) {
			return '';
		}

		$language = preg_replace( '/[_-].*$/', '', $locale );

		if ( ! is_string( $language ) ) {
			return '';
		}

		return $language;
	}
}
