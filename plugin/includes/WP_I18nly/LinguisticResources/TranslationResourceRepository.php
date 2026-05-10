<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation linguistic resource repository.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

defined( 'ABSPATH' ) || exit;

/**
 * Translation-specific repository backed by the resource-centric wpdb storage.
 */
class TranslationResourceRepository extends AbstractLinguisticResourceRepository {
	/**
	 * Returns the resource kind.
	 *
	 * @return string
	 */
	public function get_resource_kind() {
		return 'translation';
	}

	/**
	 * Ensures target rows exist for one translation resource.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $source_slug Source slug.
	 * @param string $now_gmt Datetime in GMT.
	 * @param int    $plural_forms_count Plural forms count.
	 * @return int
	 */
	public function ensure_translation_targets( $translation_id, $source_slug, $now_gmt, $plural_forms_count ) {
		return (int) $this->get_storage_repository()->ensure_translated_entries_for_translation(
			(int) $translation_id,
			(string) $source_slug,
			(string) $now_gmt,
			(int) $plural_forms_count
		);
	}

	/**
	 * Returns editor rows for one translation resource.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $source_slug Source slug.
	 * @param int    $limit Maximum number of rows.
	 * @param int    $plural_forms_count Plural forms count.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_translation_rows( $translation_id, $source_slug, $limit, $plural_forms_count ) {
		return $this->get_storage_repository()->list_translation_entries_by_plugin_slug(
			(int) $translation_id,
			(string) $source_slug,
			(int) $limit,
			(int) $plural_forms_count
		);
	}

	/**
	 * Upserts one translated target value.
	 *
	 * @param int         $translation_id Translation ID.
	 * @param int         $source_entry_id Source entry ID.
	 * @param int         $form_index Form index.
	 * @param string      $translation Translation text.
	 * @param string      $now_gmt Datetime in GMT.
	 * @param string|null $status Optional explicit status.
	 * @param int|null    $used_ai Optional AI provenance flag.
	 * @param int|null    $used_manual Optional manual provenance flag.
	 * @return bool
	 */
	public function upsert_translation_target( $translation_id, $source_entry_id, $form_index, $translation, $now_gmt, $status = null, $used_ai = null, $used_manual = null ) {
		return (bool) $this->get_storage_repository()->upsert_translated_entry(
			(int) $translation_id,
			(int) $source_entry_id,
			(int) $form_index,
			(string) $translation,
			(string) $now_gmt,
			null === $status ? null : (string) $status,
			null === $used_ai ? null : (int) $used_ai,
			null === $used_manual ? null : (int) $used_manual
		);
	}
}
