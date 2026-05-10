<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Source wpdb repository source-resource helpers.
 *
 * @package I18nly
 */

namespace WP_I18nly\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Shares source-resource persistence and read helpers for the source wpdb repository.
 */
trait SourceWpdbRepositorySourceResourceTrait {
	/**
	 * Upserts one source catalog row.
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @param string $domain Text domain.
	 * @param string $headers_json POT headers JSON.
	 * @param string $now_gmt Datetime in GMT.
	 * @return int Catalog ID.
	 */
	public function upsert_catalog( $plugin_slug, $domain, $headers_json, $now_gmt ) {
		$table = $this->escape_table_name( $this->schema_manager->get_catalogs_table_name() );

		if ( '' === $table ) {
			return 0;
		}

		$catalog_id = (int) $this->db_get_var(
			$this->wpdb->prepare(
				'SELECT id FROM %i WHERE resource_kind = %s AND source_slug = %s AND target_locale = %s',
				$table,
				self::SOURCE_CATALOG_RESOURCE_KIND,
				$plugin_slug,
				''
			)
		);

		if ( $catalog_id > 0 ) {
			$this->wpdb->update(
				$table,
				array(
					'domain'         => $domain,
					'headers_json'   => $headers_json,
					'updated_at_gmt' => $now_gmt,
				),
				array( 'id' => $catalog_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			return $catalog_id;
		}

		$this->wpdb->insert(
			$table,
			array(
				'resource_kind'  => self::SOURCE_CATALOG_RESOURCE_KIND,
				'source_slug'    => $plugin_slug,
				'source_locale'  => 'en_US',
				'target_locale'  => '',
				'domain'         => $domain,
				'headers_json'   => $headers_json,
				'created_at_gmt' => $now_gmt,
				'updated_at_gmt' => $now_gmt,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Upserts one source entry row.
	 *
	 * @param array<string, mixed> $entry Entry payload.
	 * @return string inserted|updated|unchanged.
	 */
	public function upsert_source_entry( array $entry ) {
		$table                       = $this->escape_table_name( $this->schema_manager->get_entries_table_name() );
		$entry['translator_comment'] = isset( $entry['translator_comment'] )
			? (string) $entry['translator_comment']
			: '';
		$entry['resource_id']        = isset( $entry['resource_id'] )
			? (int) $entry['resource_id']
			: ( isset( $entry['catalog_id'] ) ? (int) $entry['catalog_id'] : 0 );

		if ( '' === $table ) {
			return 'unchanged';
		}

		$entry_id = $this->find_resource_entry_id(
			(int) $entry['resource_id'],
			isset( $entry['msgctxt'] ) ? (string) $entry['msgctxt'] : null,
			(string) $entry['msgid']
		);

		$now_gmt          = (string) $entry['updated_at_gmt'];
		$last_seen_at_gmt = isset( $entry['last_seen_at_gmt'] ) ? (string) $entry['last_seen_at_gmt'] : $now_gmt;

		if ( $entry_id > 0 ) {
			$entry['status'] = 'active';

			$existing = $this->db_get_row(
				$this->wpdb->prepare(
					'SELECT resource_id, msgctxt, msgid, msgid_plural, translator_comment, comments_json, references_json, flags_json, status, last_seen_at_gmt FROM %i WHERE id = %d',
					$table,
					$entry_id
				),
				ARRAY_A
			);

			$unchanged = ( new SourceEntryRowComparator() )->rows_are_equal( $existing, $entry, self::ENTRY_IDENTITY_COLUMNS, self::ENTRY_CONTENT_COLUMNS );

			if ( $unchanged ) {
				$this->wpdb->update(
					$table,
					array(
						'last_seen_at_gmt' => $last_seen_at_gmt,
					),
					array( 'id' => $entry_id ),
					array( '%s' ),
					array( '%d' )
				);

				return 'unchanged';
			}

			$this->wpdb->update(
				$table,
				array(
					'msgid_plural'       => $entry['msgid_plural'],
					'translator_comment' => $entry['translator_comment'],
					'comments_json'      => $entry['comments_json'],
					'references_json'    => $entry['references_json'],
					'flags_json'         => $entry['flags_json'],
					'status'             => 'active',
					'last_seen_at_gmt'   => $last_seen_at_gmt,
					'updated_at_gmt'     => $now_gmt,
				),
				array( 'id' => $entry_id ),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return 'updated';
		}

		$this->wpdb->insert(
			$table,
			array(
				'resource_id'        => $entry['resource_id'],
				'msgctxt'            => $entry['msgctxt'],
				'msgid'              => $entry['msgid'],
				'msgid_plural'       => $entry['msgid_plural'],
				'translator_comment' => $entry['translator_comment'],
				'comments_json'      => $entry['comments_json'],
				'references_json'    => $entry['references_json'],
				'flags_json'         => $entry['flags_json'],
				'status'             => 'active',
				'last_seen_at_gmt'   => $last_seen_at_gmt,
				'created_at_gmt'     => $entry['created_at_gmt'],
				'updated_at_gmt'     => $entry['updated_at_gmt'],
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return 'inserted';
	}

	/**
	 * Finds source entry row id for one source identity.
	 *
	 * @param int         $resource_id Resource ID.
	 * @param string|null $msgctxt Message context.
	 * @param string      $msgid Message ID.
	 * @return int
	 */
	private function find_resource_entry_id( $resource_id, $msgctxt, $msgid ) {
		$table = $this->escape_table_name( $this->schema_manager->get_entries_table_name() );

		if ( '' === $table ) {
			return 0;
		}

		if ( null === $msgctxt ) {
			return (int) $this->db_get_var(
				$this->wpdb->prepare(
					'SELECT id FROM %i WHERE resource_id = %d AND msgctxt IS NULL AND msgid = %s',
					$table,
					$resource_id,
					$msgid
				)
			);
		}

		return (int) $this->db_get_var(
			$this->wpdb->prepare(
				'SELECT id FROM %i WHERE resource_id = %d AND msgctxt = %s AND msgid = %s',
				$table,
				$resource_id,
				$msgctxt,
				$msgid
			)
		);
	}

	/**
	 * Finds source entry row id for one source resource identity.
	 *
	 * @param int         $resource_id Resource ID.
	 * @param string|null $msgctxt Message context.
	 * @param string      $msgid Message ID.
	 * @return int
	 */
	public function find_source_resource_entry_id( $resource_id, $msgctxt, $msgid ) {
		return $this->find_resource_entry_id( (int) $resource_id, $msgctxt, (string) $msgid );
	}

	/**
	 * Marks as obsolete active entries not seen in current import.
	 *
	 * @param int    $resource_id Resource ID.
	 * @param string $now_gmt Update datetime in GMT.
	 * @return int Number of rows marked obsolete.
	 */
	public function mark_obsolete_source_resource_entries_not_seen( $resource_id, $now_gmt ) {
		$table = $this->escape_table_name( $this->schema_manager->get_entries_table_name() );

		if ( '' === $table ) {
			return 0;
		}

		$query = $this->wpdb->prepare(
			'UPDATE %i SET status = %s, updated_at_gmt = %s WHERE resource_id = %d AND status = %s AND last_seen_at_gmt IS NULL',
			$table,
			'obsolete',
			$now_gmt,
			(int) $resource_id,
			'active'
		);

		$result = $this->db_query( $query );

		if ( is_int( $result ) ) {
			return $result;
		}

		return 0;
	}

	/**
	 * Marks as obsolete active entries not seen in current import.
	 *
	 * @param int    $catalog_id Catalog ID.
	 * @param string $now_gmt Update datetime in GMT.
	 * @return int Number of rows marked obsolete.
	 */
	public function mark_obsolete_entries_not_seen( $catalog_id, $now_gmt ) {
		return $this->mark_obsolete_source_resource_entries_not_seen( (int) $catalog_id, (string) $now_gmt );
	}

	/**
	 * Clears last_seen marker for active entries before one import.
	 *
	 * @param int $resource_id Resource ID.
	 * @return void
	 */
	public function reset_last_seen_for_source_resource( $resource_id ) {
		$table = $this->escape_table_name( $this->schema_manager->get_entries_table_name() );

		if ( '' === $table ) {
			return;
		}

		$query = $this->wpdb->prepare(
			'UPDATE %i SET last_seen_at_gmt = NULL WHERE resource_id = %d AND status = %s',
			$table,
			(int) $resource_id,
			'active'
		);

		$this->db_query( $query );
	}

	/**
	 * Clears last_seen marker for active entries before one import.
	 *
	 * @param int $catalog_id Catalog ID.
	 * @return void
	 */
	public function reset_last_seen_for_catalog( $catalog_id ) {
		$this->reset_last_seen_for_source_resource( (int) $catalog_id );
	}

	/**
	 * Lists source entries for one plugin slug.
	 *
	 * @param string $source_slug Source slug.
	 * @param int    $limit Maximum row count.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_source_resource_entries_by_source_slug( $source_slug, $limit = 500 ) {
		$entries_table  = $this->escape_table_name( $this->schema_manager->get_entries_table_name() );
		$catalogs_table = $this->escape_table_name( $this->schema_manager->get_catalogs_table_name() );

		if ( '' === $entries_table || '' === $catalogs_table ) {
			return array();
		}

		$max_rows = max( 1, (int) $limit );

		$query = $this->wpdb->prepare(
			'SELECT e.id AS source_entry_id, e.msgctxt, e.msgid, e.msgid_plural, e.translator_comment, e.status, e.last_seen_at_gmt, e.updated_at_gmt FROM %i e INNER JOIN %i c ON c.id = e.resource_id WHERE c.resource_kind = %s AND c.source_slug = %s ORDER BY e.msgid ASC, e.id ASC LIMIT %d',
			$entries_table,
			$catalogs_table,
			self::SOURCE_CATALOG_RESOURCE_KIND,
			(string) $source_slug,
			$max_rows
		);

		return $this->db_get_results( $query, ARRAY_A );
	}

	/**
	 * Lists source entries for one plugin slug.
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @param int    $limit Maximum row count.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_source_entries_by_plugin_slug( $plugin_slug, $limit = 500 ) {
		return $this->list_source_resource_entries_by_source_slug( (string) $plugin_slug, (int) $limit );
	}
}
