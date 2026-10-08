<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Glossary validator.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

defined( 'ABSPATH' ) || exit;

/**
 * Validates and normalizes glossary identities and terms.
 *
 * It holds the rules specific to glossaries: terms and targets are short single-line texts, a term
 * is matched exactly or partially, and a term has one preferred target plus a few alternates.
 */
class GlossaryValidator {
	/**
	 * Match modes of a term.
	 *
	 * Exact matches the whole term; partial also matches it inside longer words or phrases.
	 *
	 * @var array<int, string>
	 */
	public const MATCH_MODES = array( 'exact', 'partial' );

	/**
	 * Maximum length of a term or of one target, in characters.
	 */
	public const MAX_TEXT_LENGTH = 255;

	/**
	 * Maximum number of alternate targets of a term.
	 */
	public const MAX_ALTERNATES = 10;

	/**
	 * Maximum length of a note, in characters.
	 */
	public const MAX_NOTE_LENGTH = 1000;

	/**
	 * Validates the identity of a glossary.
	 *
	 * @param string $slug Glossary slug.
	 * @param string $source_locale Source locale.
	 * @param string $target_locale Target locale.
	 * @return GlossaryValidationResult
	 */
	public function validate_identity( $slug, $source_locale, $target_locale ) {
		$errors = array();
		$values = array(
			'slug'          => strtolower( trim( (string) $slug ) ),
			'source_locale' => trim( (string) $source_locale ),
			'target_locale' => trim( (string) $target_locale ),
		);

		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]{0,99}$/', $values['slug'] ) ) {
			$errors['invalid_slug'] = __( 'The glossary identifier must contain 1 to 100 lowercase letters, digits, hyphens or underscores, and start with a letter or a digit.', 'i18nly' );
		}

		$valid_source = $this->is_valid_locale( $values['source_locale'] );
		$valid_target = $this->is_valid_locale( $values['target_locale'] );

		if ( ! $valid_source ) {
			$errors['invalid_source_locale'] = __( 'The source language is not a valid locale.', 'i18nly' );
		}

		if ( ! $valid_target ) {
			$errors['invalid_target_locale'] = __( 'The target language is not a valid locale.', 'i18nly' );
		}

		if ( $valid_source && $valid_target && $values['source_locale'] === $values['target_locale'] ) {
			$errors['same_locale'] = __( 'The source and target languages must be different.', 'i18nly' );
		}

		return new GlossaryValidationResult( $values, $errors );
	}

	/**
	 * Validates one glossary term.
	 *
	 * @param array<string, mixed> $input Term input: term, match_mode, preferred, alternates, note.
	 * @param array<int, string>   $existing_terms Other terms of the glossary, to detect duplicates.
	 * @return GlossaryValidationResult
	 */
	public function validate_term( array $input, array $existing_terms = array() ) {
		$errors = array();
		$term   = $this->normalize_text( isset( $input['term'] ) ? $input['term'] : '' );

		if ( '' === $term ) {
			$errors['missing_term'] = __( 'The term is required.', 'i18nly' );
		} elseif ( $this->length( $term ) > self::MAX_TEXT_LENGTH ) {
			$errors['term_too_long'] = __( 'The term is too long.', 'i18nly' );
		}

		$match_mode = isset( $input['match_mode'] ) && '' !== trim( (string) $input['match_mode'] )
			? strtolower( trim( (string) $input['match_mode'] ) )
			: 'exact';

		if ( ! in_array( $match_mode, self::MATCH_MODES, true ) ) {
			$errors['invalid_match_mode'] = __( 'The match mode must be exact or partial.', 'i18nly' );
		}

		$preferred = $this->normalize_text( isset( $input['preferred'] ) ? $input['preferred'] : '' );

		if ( '' === $preferred ) {
			$errors['missing_preferred'] = __( 'The preferred translation is required.', 'i18nly' );
		} elseif ( $this->length( $preferred ) > self::MAX_TEXT_LENGTH ) {
			$errors['preferred_too_long'] = __( 'The preferred translation is too long.', 'i18nly' );
		}

		$alternates = $this->validate_alternates( isset( $input['alternates'] ) ? $input['alternates'] : array(), $preferred, $errors );
		$note       = $this->normalize_note( isset( $input['note'] ) ? $input['note'] : '' );

		if ( $this->length( $note ) > self::MAX_NOTE_LENGTH ) {
			$errors['note_too_long'] = __( 'The note is too long.', 'i18nly' );
		}

		if ( '' !== $term ) {
			$folded_term = $this->fold( $term );

			foreach ( $existing_terms as $existing_term ) {
				if ( $this->fold( $this->normalize_text( $existing_term ) ) === $folded_term ) {
					$errors['duplicate_term'] = __( 'This term already exists in the glossary.', 'i18nly' );
					break;
				}
			}
		}

		return new GlossaryValidationResult(
			array(
				'term'       => $term,
				'match_mode' => $match_mode,
				'preferred'  => $preferred,
				'alternates' => $alternates,
				'note'       => $note,
			),
			$errors
		);
	}

	/**
	 * Cleans up the alternate targets and reports their errors.
	 *
	 * @param mixed                 $raw_alternates Raw alternates.
	 * @param string                $preferred Normalized preferred target.
	 * @param array<string, string> $errors Errors, completed by reference.
	 * @return array<int, string>
	 */
	private function validate_alternates( $raw_alternates, $preferred, array &$errors ) {
		$alternates = array();
		$seen       = array();

		if ( null === $raw_alternates ) {
			$raw_alternates = array();
		}

		if ( ! is_array( $raw_alternates ) ) {
			$errors['invalid_alternates'] = __( 'The alternate translations must be a list.', 'i18nly' );

			return array();
		}

		$folded_preferred = $this->fold( $preferred );

		foreach ( $raw_alternates as $raw_alternate ) {
			$alternate = $this->normalize_text( is_scalar( $raw_alternate ) ? $raw_alternate : '' );

			if ( '' === $alternate ) {
				continue;
			}

			if ( $this->length( $alternate ) > self::MAX_TEXT_LENGTH ) {
				$errors['alternate_too_long'] = __( 'An alternate translation is too long.', 'i18nly' );
			}

			$folded = $this->fold( $alternate );

			if ( '' !== $folded_preferred && $folded === $folded_preferred ) {
				$errors['alternate_equals_preferred'] = __( 'An alternate translation must differ from the preferred translation.', 'i18nly' );
			}

			if ( isset( $seen[ $folded ] ) ) {
				$errors['duplicate_alternate'] = __( 'The alternate translations must be different from each other.', 'i18nly' );
			}

			$seen[ $folded ] = true;
			$alternates[]    = $alternate;
		}

		if ( count( $alternates ) > self::MAX_ALTERNATES ) {
			$errors['too_many_alternates'] = __( 'There are too many alternate translations.', 'i18nly' );
		}

		return $alternates;
	}

	/**
	 * Tells whether a string looks like a WordPress locale.
	 *
	 * @param string $locale Locale.
	 * @return bool
	 */
	private function is_valid_locale( $locale ) {
		return 1 === preg_match( '/^[a-z]{2,3}(?:_[A-Za-z0-9]+)*$/', (string) $locale );
	}

	/**
	 * Normalizes a single-line text: control characters removed, whitespace collapsed, trimmed.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function normalize_text( $value ) {
		$text      = $this->strip_control_characters( (string) $value, false );
		$collapsed = preg_replace( '/\s+/u', ' ', $text );

		if ( ! is_string( $collapsed ) ) {
			$collapsed = preg_replace( '/\s+/', ' ', $text );
		}

		return trim( (string) $collapsed );
	}

	/**
	 * Normalizes a note: control characters removed, line breaks unified, trimmed.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function normalize_note( $value ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );

		return trim( $this->strip_control_characters( $text, true ) );
	}

	/**
	 * Removes control characters.
	 *
	 * @param string $text Text.
	 * @param bool   $keep_line_breaks Whether new lines and tabs are kept.
	 * @return string
	 */
	private function strip_control_characters( $text, $keep_line_breaks ) {
		$pattern = $keep_line_breaks ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x08\x0E-\x1F\x7F]/';

		return (string) preg_replace( $pattern, '', $text );
	}

	/**
	 * Returns the length of a text in characters.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private function length( $text ) {
		return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	/**
	 * Lowercases a text for case-insensitive comparisons.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function fold( $text ) {
		return function_exists( 'mb_strtolower' ) ? (string) mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}
}
