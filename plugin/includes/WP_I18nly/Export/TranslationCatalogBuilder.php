<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Builds a gettext catalog from the rows of a translation.
 *
 * @package I18nly
 */

namespace WP_I18nly\Export;

use Gettext\Translation;
use Gettext\Translations;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the rows of a translation (source entries with their translated forms) into gettext translations.
 *
 * The plural header comes from the plugin's own plural data, not from gettext's language database, so
 * that the files agree with what the translator saw in the editor.
 */
class TranslationCatalogBuilder {
	/**
	 * Status of a translation that a human validated. Any other status (draft, AI draft, suspect) is doubtful.
	 */
	public const VALIDATED_STATUS = 'validated';

	/**
	 * Builds the catalog.
	 *
	 * Entries without any translation are left out. Options:
	 * - complete_plurals_only (false): leave out a plural entry when one of its forms is empty, because
	 *   binary MO files cannot represent a partly translated plural entry;
	 * - include_unvalidated (true): when false, leave out the entries having a translation that is not validated;
	 * - fuzzy_unvalidated (false): flag the entries having a translation that is not validated as fuzzy.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows as returned by list_translation_rows().
	 * @param string                           $locale Target locale, for example fr_FR.
	 * @param string                           $text_domain Text domain.
	 * @param int                              $plural_count Number of plural forms of the locale.
	 * @param string                           $plural_expression Gettext plural expression.
	 * @param array<string, string>            $headers Extra headers.
	 * @param array<string, bool>              $options Options.
	 * @return Translations
	 */
	public function build( array $rows, $locale, $text_domain, $plural_count, $plural_expression, array $headers = array(), array $options = array() ) {
		$options      = array_merge(
			array(
				'complete_plurals_only' => false,
				'include_unvalidated'   => true,
				'fuzzy_unvalidated'     => false,
			),
			$options
		);
		$translations = Translations::create( (string) $text_domain );
		$plural_count = max( 1, (int) $plural_count );

		$translations->getHeaders()
			->set( 'MIME-Version', '1.0' )
			->set( 'Content-Type', 'text/plain; charset=UTF-8' )
			->set( 'Content-Transfer-Encoding', '8bit' )
			->setLanguage( (string) $locale )
			->setPluralForm( $plural_count, (string) $plural_expression );

		foreach ( $headers as $name => $value ) {
			$translations->getHeaders()->set( (string) $name, (string) $value );
		}

		foreach ( $rows as $row ) {
			$doubtful = $this->is_doubtful( $row );

			if ( $doubtful && ! $options['include_unvalidated'] ) {
				continue;
			}

			$translation = $this->build_entry( $row, $plural_count, (bool) $options['complete_plurals_only'] );

			if ( null === $translation ) {
				continue;
			}

			if ( $doubtful && $options['fuzzy_unvalidated'] ) {
				$translation->getFlags()->add( 'fuzzy' );
			}

			$translations->add( $translation );
		}

		return $translations;
	}

	/**
	 * Counts the entries having a translation that is not validated (empty forms are ignored).
	 *
	 * @param array<int, array<string, mixed>> $rows Rows as returned by list_translation_rows().
	 * @return int
	 */
	public function count_unvalidated( array $rows ) {
		return count( array_filter( $rows, array( $this, 'is_doubtful' ) ) );
	}

	/**
	 * Tells whether an entry has at least one non-empty form whose status is not validated.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return bool
	 */
	public function is_doubtful( array $row ) {
		if ( ! isset( $row['translations'] ) || ! is_array( $row['translations'] ) ) {
			return false;
		}

		foreach ( $row['translations'] as $target ) {
			$text   = isset( $target['translation'] ) ? (string) $target['translation'] : '';
			$status = isset( $target['status'] ) ? (string) $target['status'] : '';

			if ( '' !== $text && self::VALIDATED_STATUS !== $status ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds one entry, or null when it has nothing to export.
	 *
	 * @param array<string, mixed> $row Row.
	 * @param int                  $plural_count Number of plural forms.
	 * @param bool                 $complete_plurals_only Whether to leave out partly translated plural entries.
	 * @return Translation|null
	 */
	private function build_entry( array $row, $plural_count, $complete_plurals_only ) {
		$original = isset( $row['msgid'] ) ? (string) $row['msgid'] : '';

		if ( '' === $original ) {
			return null;
		}

		$context = isset( $row['msgctxt'] ) && '' !== (string) $row['msgctxt'] ? (string) $row['msgctxt'] : null;
		$plural  = isset( $row['msgid_plural'] ) ? (string) $row['msgid_plural'] : '';
		$forms   = $this->collect_forms( $row, '' !== $plural ? $plural_count : 1 );

		if ( '' === implode( '', $forms ) ) {
			return null;
		}

		if ( '' !== $plural && $complete_plurals_only && in_array( '', $forms, true ) ) {
			return null;
		}

		$translation = Translation::create( $context, $original );
		$translation->translate( $forms[0] );

		if ( '' !== $plural ) {
			$translation->setPlural( $plural );
			$translation->translatePlural( ...array_slice( $forms, 1 ) );
		}

		if ( isset( $row['translator_comment'] ) && '' !== trim( (string) $row['translator_comment'] ) ) {
			$translation->getExtractedComments()->add( trim( (string) $row['translator_comment'] ) );
		}

		return $translation;
	}

	/**
	 * Returns the translated texts indexed from 0, with empty strings for missing forms.
	 *
	 * @param array<string, mixed> $row Row.
	 * @param int                  $count Number of forms wanted.
	 * @return string[]
	 */
	private function collect_forms( array $row, $count ) {
		$forms = array_fill( 0, $count, '' );

		if ( ! isset( $row['translations'] ) || ! is_array( $row['translations'] ) ) {
			return $forms;
		}

		foreach ( $row['translations'] as $target ) {
			$index = isset( $target['form_index'] ) ? (int) $target['form_index'] : -1;

			if ( $index >= 0 && $index < $count ) {
				$forms[ $index ] = isset( $target['translation'] ) ? (string) $target['translation'] : '';
			}
		}

		return $forms;
	}
}
