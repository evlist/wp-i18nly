<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Matching of glossary terms against source and translated texts.
 *
 * @package I18nly
 */

namespace WP_I18nly\Glossary;

defined( 'ABSPATH' ) || exit;

/**
 * Finds the terms of glossaries in a source text and checks that a translation uses their translations.
 *
 * Terms are given in the order of precedence: when the same term (ignoring case) is given twice, the
 * first one wins. Comparisons ignore case and the amount of white space.
 *
 * - An "exact" term matches a source text that is the term and nothing else.
 * - A "partial" term matches a source text containing the term as whole words.
 */
class GlossaryMatcher {
	/**
	 * Check result: no translated text to check yet.
	 */
	public const QA_PENDING = 'pending';

	/**
	 * Check result: every translated text uses an accepted translation of the term.
	 */
	public const QA_OK = 'ok';

	/**
	 * Check result: a translated text does not use any accepted translation of the term.
	 */
	public const QA_MISSING = 'missing';

	/**
	 * Terms, deduplicated, in order of precedence.
	 *
	 * @var array<int, array{glossary: string, term: string, folded: string, mode: string, preferred: string, alternates: string[], note: string}>
	 */
	private $terms = array();

	/**
	 * Constructor.
	 *
	 * @param array<int, array{glossary: string, term: string, mode: string, preferred: string, alternates: string[], note: string}> $terms Terms in order of precedence.
	 */
	public function __construct( array $terms ) {
		$seen = array();

		foreach ( $terms as $term ) {
			$folded = self::fold( $term['term'] );
			$key    = $folded . "\0" . $term['mode'];

			if ( '' === $folded || isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ]  = true;
			$this->terms[] = $term + array( 'folded' => $folded );
		}
	}

	/**
	 * Returns the number of terms.
	 *
	 * @return int
	 */
	public function count() {
		return count( $this->terms );
	}

	/**
	 * Finds the terms present in a source entry, and checks its translations.
	 *
	 * @param string   $singular Source text.
	 * @param string   $plural Source plural text, or an empty string.
	 * @param string[] $translations Translated texts of the entry, one per form (empty ones are ignored).
	 * @return array<int, array{glossary: string, term: string, mode: string, preferred: string, alternates: string[], note: string, qa: string}>
	 */
	public function match_entry( $singular, $plural, array $translations ) {
		$matches = array();
		$sources = array_filter( array( self::fold( $singular ), self::fold( $plural ) ), 'strlen' );

		foreach ( $this->terms as $term ) {
			if ( ! $this->term_is_in( $term, $sources ) ) {
				continue;
			}

			$matches[] = array(
				'glossary'   => $term['glossary'],
				'term'       => $term['term'],
				'mode'       => $term['mode'],
				'preferred'  => $term['preferred'],
				'alternates' => $term['alternates'],
				'note'       => $term['note'],
				'qa'         => $this->check( $term, $translations ),
			);
		}

		return $matches;
	}

	/**
	 * Tells whether a term is in one of the folded source texts.
	 *
	 * @param array<string, mixed> $term Term.
	 * @param string[]             $sources Folded source texts.
	 * @return bool
	 */
	private function term_is_in( array $term, array $sources ) {
		foreach ( $sources as $source ) {
			if ( 'exact' === $term['mode'] ) {
				if ( $source === $term['folded'] ) {
					return true;
				}

				continue;
			}

			if ( 1 === preg_match( '/(?<![\p{L}\p{N}_])' . preg_quote( $term['folded'], '/' ) . '(?![\p{L}\p{N}_])/u', $source ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks that every non-empty translation contains an accepted translation of a term.
	 *
	 * @param array<string, mixed> $term Term.
	 * @param string[]             $translations Translated texts.
	 * @return string One of the QA_* constants.
	 */
	private function check( array $term, array $translations ) {
		$accepted = array_filter( array_map( array( __CLASS__, 'fold' ), array_merge( array( $term['preferred'] ), $term['alternates'] ) ), 'strlen' );
		$checked  = 0;

		foreach ( $translations as $translation ) {
			$folded = self::fold( $translation );

			if ( '' === $folded ) {
				continue;
			}

			++$checked;

			if ( ! $this->contains_any( $folded, $accepted ) ) {
				return self::QA_MISSING;
			}
		}

		return 0 === $checked ? self::QA_PENDING : self::QA_OK;
	}

	/**
	 * Tells whether a folded text contains one of the folded needles.
	 *
	 * @param string   $text Folded text.
	 * @param string[] $needles Folded needles.
	 * @return bool
	 */
	private function contains_any( $text, array $needles ) {
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $text, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Folds a text for comparison: trimmed, white space collapsed, lower case.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function fold( $text ) {
		return mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', (string) $text ) ), 'UTF-8' );
	}
}
