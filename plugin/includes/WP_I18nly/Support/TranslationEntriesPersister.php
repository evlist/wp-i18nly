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
	 * @return void
	 */
	public function persist( $translation_id, $source_slug, array $entries_payload ) {
		$repository = $this->repository instanceof TranslationResourceRepository ? $this->repository : new TranslationResourceRepository();
		$now_gmt    = gmdate( 'Y-m-d H:i:s' );
		$locale     = (string) get_post_meta( (int) $translation_id, '_i18nly_target_language', true );
		$form_count = \WP_I18nly\Plurals\PluralFormsRegistry::get_plural_forms_count_for_locale( $locale );

		$repository->ensure_translation_targets( (int) $translation_id, (string) $source_slug, $locale, $now_gmt, $form_count );

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
}
