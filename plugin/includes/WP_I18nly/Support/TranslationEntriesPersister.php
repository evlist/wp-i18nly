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

defined( 'ABSPATH' ) || exit;

/**
 * Handles persistence of translation entry values to database.
 */
class TranslationEntriesPersister {
	/**
	 * Persists translation entry values.
	 *
	 * @param int                                     $translation_id Translation ID.
	 * @param string                                  $source_slug Source slug.
	 * @param array<int|string, array<string, mixed>> $entries_payload Posted entries payload.
	 * @return void
	 */
	public function persist( $translation_id, $source_slug, array $entries_payload ) {
		$schema_manager = new \WP_I18nly\SourceSchemaManager();
		$schema_manager->maybe_upgrade();

		$repository = new \WP_I18nly\SourceWpdbRepository( $schema_manager );
		$now_gmt    = gmdate( 'Y-m-d H:i:s' );
		$locale     = (string) get_post_meta( (int) $translation_id, '_i18nly_target_language', true );
		$form_count = \WP_I18nly\PluralFormsRegistry::get_plural_forms_count_for_locale( $locale );

		if ( method_exists( $repository, 'ensure_translated_entries_for_translation' ) ) {
			$repository->ensure_translated_entries_for_translation( (int) $translation_id, (string) $source_slug, $now_gmt, $form_count );
		}

		if ( ! method_exists( $repository, 'upsert_translated_entry' ) ) {
			return;
		}

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

			foreach ( $forms as $form_index => $form_translation ) {
				$normalized_form_index = absint( $form_index );

				$repository->upsert_translated_entry(
					(int) $translation_id,
					$normalized_source_entry_id,
					$normalized_form_index,
					sanitize_text_field( (string) $form_translation ),
					$now_gmt
				);
			}
		}
	}

	/**
	 * Normalizes translation entries payload rows.
	 *
	 * @param array<int|string, mixed> $entries_payload Raw entries payload.
	 * @return array<int|string, array<string, string>>
	 */
	public function normalize( array $entries_payload ) {
		$normalized_payload = array();

		foreach ( $entries_payload as $source_entry_id => $entry_payload ) {
			if ( ! is_array( $entry_payload ) ) {
				continue;
			}

			$forms = isset( $entry_payload['forms'] ) && is_array( $entry_payload['forms'] )
				? $entry_payload['forms']
				: array();

			$normalized_forms = array();

			foreach ( $forms as $form_index => $form_translation ) {
				$normalized_forms[ absint( $form_index ) ] = sanitize_text_field( (string) $form_translation );
			}

			$normalized_payload[ $source_entry_id ] = array(
				'forms' => $normalized_forms,
			);
		}

		return $normalized_payload;
	}
}
