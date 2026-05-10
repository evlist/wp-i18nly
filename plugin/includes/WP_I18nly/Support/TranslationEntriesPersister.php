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
	 * Persists translation entry values.
	 *
	 * @param int                                     $translation_id Translation ID.
	 * @param string                                  $source_slug Source slug.
	 * @param array<int|string, array<string, mixed>> $entries_payload Posted entries payload.
	 * @return void
	 */
	public function persist( $translation_id, $source_slug, array $entries_payload ) {
		$repository = new TranslationResourceRepository();
		$now_gmt    = gmdate( 'Y-m-d H:i:s' );
		$locale     = (string) get_post_meta( (int) $translation_id, '_i18nly_target_language', true );
		$form_count = \WP_I18nly\Plurals\PluralFormsRegistry::get_plural_forms_count_for_locale( $locale );

		$repository->ensure_translation_targets( (int) $translation_id, (string) $source_slug, $now_gmt, $form_count );

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

			$statuses = isset( $entry_payload['statuses'] ) && is_array( $entry_payload['statuses'] )
				? $entry_payload['statuses']
				: array();
			$used_ai = isset( $entry_payload['used_ai'] ) && is_array( $entry_payload['used_ai'] )
				? $entry_payload['used_ai']
				: array();
			$used_manual = isset( $entry_payload['used_manual'] ) && is_array( $entry_payload['used_manual'] )
				? $entry_payload['used_manual']
				: array();

			foreach ( $forms as $form_index => $form_translation ) {
				$normalized_form_index = absint( $form_index );
				$normalized_text       = sanitize_text_field( (string) $form_translation );
				$explicit_status       = array_key_exists( $normalized_form_index, $statuses )
					? sanitize_key( (string) $statuses[ $normalized_form_index ] )
					: null;
				$explicit_used_ai      = array_key_exists( $normalized_form_index, $used_ai )
					? max( 0, min( 1, (int) $used_ai[ $normalized_form_index ] ) )
					: null;
				$explicit_used_manual  = array_key_exists( $normalized_form_index, $used_manual )
					? max( 0, min( 1, (int) $used_manual[ $normalized_form_index ] ) )
					: null;

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
	}

	/**
	 * Normalizes translation entries payload rows.
	 *
	 * @param array<int|string, mixed> $entries_payload Raw entries payload.
	 * @return array<int|string, array{forms: array<int, string>, statuses?: array<int, string>, used_ai?: array<int, int>, used_manual?: array<int, int>}>
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

			$normalized_forms       = array();
			$statuses               = isset( $entry_payload['statuses'] ) && is_array( $entry_payload['statuses'] )
				? $entry_payload['statuses']
				: array();
			$used_ai                = isset( $entry_payload['used_ai'] ) && is_array( $entry_payload['used_ai'] )
				? $entry_payload['used_ai']
				: array();
			$used_manual            = isset( $entry_payload['used_manual'] ) && is_array( $entry_payload['used_manual'] )
				? $entry_payload['used_manual']
				: array();
			$normalized_statuses    = array();
			$normalized_used_ai     = array();
			$normalized_used_manual = array();

			foreach ( $forms as $form_index => $form_translation ) {
				$normalized_forms[ absint( $form_index ) ] = sanitize_text_field( (string) $form_translation );

				if ( ! array_key_exists( $form_index, $statuses ) && ! array_key_exists( absint( $form_index ), $statuses ) ) {
					continue;
				}

				$status_value = array_key_exists( absint( $form_index ), $statuses ) ? $statuses[ absint( $form_index ) ] : $statuses[ $form_index ];

				$normalized_statuses[ absint( $form_index ) ] = sanitize_key( (string) $status_value );

				if ( array_key_exists( $form_index, $used_ai ) || array_key_exists( absint( $form_index ), $used_ai ) ) {
					$used_ai_value = array_key_exists( absint( $form_index ), $used_ai ) ? $used_ai[ absint( $form_index ) ] : $used_ai[ $form_index ];
					$normalized_used_ai[ absint( $form_index ) ] = max( 0, min( 1, (int) $used_ai_value ) );
				}

				if ( array_key_exists( $form_index, $used_manual ) || array_key_exists( absint( $form_index ), $used_manual ) ) {
					$used_manual_value = array_key_exists( absint( $form_index ), $used_manual ) ? $used_manual[ absint( $form_index ) ] : $used_manual[ $form_index ];
					$normalized_used_manual[ absint( $form_index ) ] = max( 0, min( 1, (int) $used_manual_value ) );
				}
			}

			$entry = array(
				'forms' => $normalized_forms,
			);

			if ( ! empty( $normalized_statuses ) ) {
				$entry['statuses'] = $normalized_statuses;
			}

			if ( ! empty( $normalized_used_ai ) ) {
				$entry['used_ai'] = $normalized_used_ai;
			}

			if ( ! empty( $normalized_used_manual ) ) {
				$entry['used_manual'] = $normalized_used_manual;
			}

			$normalized_payload[ $source_entry_id ] = $entry;
		}

		return $normalized_payload;
	}
}
