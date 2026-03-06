<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * CLDR-derived plural forms registry.
 *
 * @package I18nly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolves plural forms metadata for one locale.
 *
 * The embedded values are derived from Unicode CLDR plural rules.
 */
class I18nly_Plural_Forms_Registry {
	/**
	 * Default spec used when locale is unknown.
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULT_SPEC = array(
		'nplurals'          => 2,
		'categories'        => array( 'one', 'other' ),
		'plural_expression' => '(n != 1)',
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

		if ( '' === $language ) {
			return self::DEFAULT_SPEC;
		}

		if ( isset( self::LANGUAGE_SPEC_MAP[ $language ] ) ) {
			return self::LANGUAGE_SPEC_MAP[ $language ];
		}

		return self::DEFAULT_SPEC;
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
		$spec = self::get_spec_for_locale( $locale );

		if ( isset( $spec['categories'] ) && is_array( $spec['categories'] ) ) {
			return array_values( $spec['categories'] );
		}

		return (array) self::DEFAULT_SPEC['categories'];
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
	 * Returns gettext Plural-Forms header value for one locale.
	 *
	 * @param string $locale Target locale.
	 * @return string
	 */
	public static function get_plural_forms_header_for_locale( $locale ) {
		return sprintf(
			'nplurals=%1$d; plural=%2$s;',
			self::get_plural_forms_count_for_locale( $locale ),
			self::get_plural_expression_for_locale( $locale )
		);
	}

	/**
	 * Computes form index for one locale and quantity.
	 *
	 * @param string $locale Target locale.
	 * @param int    $n Quantity.
	 * @return int
	 */
	public static function compute_form_index( $locale, $n ) {
		$language = self::normalize_language( $locale );
		$n        = max( 0, (int) $n );

		$index = self::compute_form_index_by_language( $language, $n );

		return self::clamp_form_index( $locale, $index );
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
