<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation entries persister.
 *
 * @package I18nly
 */

namespace WP_I18nly\Support;

use WP_I18nly\LinguisticResources\TranslationResourceRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Handles persistence of translation entry values to database.
 */
class TranslationEntriesPersister {
	/**
	 * Repository.
	 *
	 * @var TranslationResourceRepository|null
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param TranslationResourceRepository|null $repository Optional repository.
	 */
	public function __construct( TranslationResourceRepository $repository = null ) {
		$this->repository = $repository;
	}

	/**
	 * Persists translation entry values.
	 *
	 * @param int                                     $translation_id Translation ID.
	 * @param string                                  $source_slug Source slug.
	 * @param array<int|string, array<string, mixed>> $entries_payload Posted entries payload.
	 * @param string                                  $loaded_at GMT time (Y-m-d H:i:s) at which the editor loaded the translation, or an empty string.
	 * @return int Number of forms not saved because someone else changed them since $loaded_at.
	 */
	public function persist( $translation_id, $source_slug, array $entries_payload, $loaded_at = '' ) {
		$repository = $this->repository instanceof TranslationResourceRepository ? $this->repository : new TranslationResourceRepository();
		$now_gmt    = gmdate( 'Y-m-d H:i:s' );
		$locale     = (string) get_post_meta( (int) $translation_id, '_i18nly_target_language', true );
		$form_count = \WP_I18nly\Plurals\PluralFormsRegistry::get_plural_forms_count_for_locale( $locale );

		$repository->ensure_translation_targets( (int) $translation_id, (string) $source_slug, $locale, $now_gmt, $form_count );

		$current   = '' === (string) $loaded_at ? array() : $this->index_current_targets( $repository->list_translation_rows( (int) $translation_id, (string) $source_slug, self::ROW_LIMIT, $form_count ) );
		$conflicts = 0;

		foreach ( $entries_payload as $source_entry_id => $entry_payload ) {
			if ( ! is_array( $entry_payload ) ) {
				continue;
			}

			$normalized_source_entry_id = absint( $source_entry_id );
			if ( $normalized_source_entry_id <= 0 ) {
				continue;
			}

			$forms = isset( $entry_payload['forms'] ) && is_array( $entry_payload['forms'] )
				? $entry_payload['forms']
				: array();

			$statuses    = isset( $entry_payload['statuses'] ) && is_array( $entry_payload['statuses'] )
				? $entry_payload['statuses']
				: array();
			$used_ai     = isset( $entry_payload['used_ai'] ) && is_array( $entry_payload['used_ai'] )
				? $entry_payload['used_ai']
				: array();
			$used_manual = isset( $entry_payload['used_manual'] ) && is_array( $entry_payload['used_manual'] )
				? $entry_payload['used_manual']
				: array();

			foreach ( $forms as $form_index => $form_translation ) {
				$normalized_form_index = absint( $form_index );
				$normalized_text       = TranslationTextNormalizer::normalize( $form_translation );
				$explicit_status       = array_key_exists( $normalized_form_index, $statuses )
					? sanitize_key( (string) $statuses[ $normalized_form_index ] )
					: null;
				$explicit_used_ai      = array_key_exists( $normalized_form_index, $used_ai )
					? max( 0, min( 1, (int) $used_ai[ $normalized_form_index ] ) )
					: null;
				$explicit_used_manual  = array_key_exists( $normalized_form_index, $used_manual )
					? max( 0, min( 1, (int) $used_manual[ $normalized_form_index ] ) )
					: null;

				if ( $this->is_changed_since( $current, $normalized_source_entry_id, $normalized_form_index, $normalized_text, (string) $loaded_at ) ) {
					++$conflicts;
					continue;
				}

				if ( '' === (string) $explicit_status ) {
					$explicit_status = '' === trim( $normalized_text ) ? null : 'draft';
				}

				$repository->upsert_translation_target(
					(int) $translation_id,
					$normalized_source_entry_id,
					$normalized_form_index,
					$normalized_text,
					$now_gmt,
					$explicit_status,
					$explicit_used_ai,
					$explicit_used_manual
				);
			}
		}

		return $conflicts;
	}

	/**
	 * Maximum number of source entries read to detect changes made by someone else.
	 */
	private const ROW_LIMIT = 100000;

	/**
	 * Indexes the saved targets by source entry and form.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows as returned by list_translation_rows().
	 * @return array<int, array<int, array{translation: string, updated_at_gmt: string}>>
	 */
	private function index_current_targets( array $rows ) {
		$index = array();

		foreach ( $rows as $row ) {
			foreach ( isset( $row['translations'] ) && is_array( $row['translations'] ) ? $row['translations'] : array() as $target ) {
				$index[ (int) $row['source_entry_id'] ][ (int) $target['form_index'] ] = array(
					'translation'    => isset( $target['translation'] ) ? (string) $target['translation'] : '',
					'updated_at_gmt' => isset( $target['updated_at_gmt'] ) ? (string) $target['updated_at_gmt'] : '',
				);
			}
		}

		return $index;
	}

	/**
	 * Tells whether a saved form was changed by someone else after the editor loaded it.
	 *
	 * A form is changed when it was saved after $loaded_at with a non-empty text different from the posted one.
	 * Empty saved texts are not changes: they are the empty rows created when a translation is opened.
	 *
	 * @param array<int, array<int, array{translation: string, updated_at_gmt: string}>> $current Saved targets.
	 * @param int                                                                        $source_entry_id Source entry ID.
	 * @param int                                                                        $form_index Form index.
	 * @param string                                                                     $posted_text Posted text.
	 * @param string                                                                     $loaded_at Load time.
	 * @return bool
	 */
	private function is_changed_since( array $current, $source_entry_id, $form_index, $posted_text, $loaded_at ) {
		if ( '' === $loaded_at || ! isset( $current[ $source_entry_id ][ $form_index ] ) ) {
			return false;
		}

		$saved = $current[ $source_entry_id ][ $form_index ];

		return '' !== $saved['translation'] && $saved['translation'] !== $posted_text && $saved['updated_at_gmt'] > $loaded_at;
	}
}
