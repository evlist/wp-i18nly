<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Source catalog linguistic resource repository.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

defined( 'ABSPATH' ) || exit;

/**
 * Source-catalog specific repository backed by the resource-centric wpdb storage.
 */
class SourceCatalogResourceRepository extends AbstractLinguisticResourceRepository {
	/**
	 * Returns the resource kind.
	 *
	 * @return string
	 */
	public function get_resource_kind() {
		return 'source_catalog';
	}

	/**
	 * Upserts one source catalog resource row.
	 *
	 * @param string $source_slug Source slug.
	 * @param string $domain Text domain.
	 * @param string $headers_json POT headers JSON.
	 * @param string $now_gmt Datetime in GMT.
	 * @return int
	 */
	public function upsert_source_catalog( $source_slug, $domain, $headers_json, $now_gmt ) {
		return (int) $this->get_storage_repository()->upsert_catalog(
			(string) $source_slug,
			(string) $domain,
			(string) $headers_json,
			(string) $now_gmt
		);
	}

	/**
	 * Clears last-seen markers for one source catalog resource.
	 *
	 * @param int $resource_id Resource ID.
	 * @return void
	 */
	public function reset_source_catalog_last_seen( $resource_id ) {
		$this->get_storage_repository()->reset_last_seen_for_catalog( (int) $resource_id );
	}

	/**
	 * Upserts one source catalog entry row.
	 *
	 * @param int         $resource_id Source catalog resource ID.
	 * @param string|null $msgctxt Message context.
	 * @param string      $msgid Message ID.
	 * @param string|null $msgid_plural Optional plural source text.
	 * @param string      $translator_comment Translator comment.
	 * @param string      $comments_json Comments JSON.
	 * @param string      $references_json References JSON.
	 * @param string      $flags_json Flags JSON.
	 * @param string      $now_gmt Datetime in GMT.
	 * @return string
	 */
	public function upsert_source_catalog_entry( $resource_id, $msgctxt, $msgid, $msgid_plural, $translator_comment, $comments_json, $references_json, $flags_json, $now_gmt ) {
		return (string) $this->get_storage_repository()->upsert_source_entry(
			array(
				'resource_id'        => (int) $resource_id,
				'msgctxt'            => null !== $msgctxt ? (string) $msgctxt : null,
				'msgid'              => (string) $msgid,
				'msgid_plural'       => null !== $msgid_plural ? (string) $msgid_plural : null,
				'translator_comment' => (string) $translator_comment,
				'comments_json'      => (string) $comments_json,
				'references_json'    => (string) $references_json,
				'flags_json'         => (string) $flags_json,
				'status'             => 'active',
				'last_seen_at_gmt'   => (string) $now_gmt,
				'created_at_gmt'     => (string) $now_gmt,
				'updated_at_gmt'     => (string) $now_gmt,
			)
		);
	}

	/**
	 * Marks missing active entries as obsolete for one source catalog resource.
	 *
	 * @param int    $resource_id Resource ID.
	 * @param string $now_gmt Datetime in GMT.
	 * @return int
	 */
	public function mark_obsolete_source_catalog_entries( $resource_id, $now_gmt ) {
		return (int) $this->get_storage_repository()->mark_obsolete_entries_not_seen(
			(int) $resource_id,
			(string) $now_gmt
		);
	}
}
