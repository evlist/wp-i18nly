<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Source entries wpdb repository.
 *
 * @package I18nly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Persists source catalogs and entries in WordPress tables.
 */
class I18nly_Source_Wpdb_Repository {
	/**
	 * WordPress database object.
	 *
	 * @var object
	 */
	private $wpdb;

	/**
	 * Schema manager.
	 *
	 * @var I18nly_Source_Schema_Manager
	 */
	private $schema_manager;

	/**
	 * Constructor.
	 *
	 * @param I18nly_Source_Schema_Manager $schema_manager Source schema manager.
	 * @param object|null                  $wpdb Optional wpdb object.
	 */
	public function __construct( I18nly_Source_Schema_Manager $schema_manager, $wpdb = null ) {
		$this->schema_manager = $schema_manager;

		if ( null !== $wpdb ) {
			$this->wpdb = $wpdb;
			return;
		}

		global $wpdb;

		$this->wpdb = $wpdb;
	}

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
		$table = $this->schema_manager->get_catalogs_table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Internal table name interpolation; values are prepared.
		$catalog_id = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$table} WHERE plugin_slug = %s",
				$plugin_slug
			)
		);
		// phpcs:enable

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
				'plugin_slug'    => $plugin_slug,
				'domain'         => $domain,
				'headers_json'   => $headers_json,
				'created_at_gmt' => $now_gmt,
				'updated_at_gmt' => $now_gmt,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
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
		$table = $this->schema_manager->get_entries_table_name();

		$entry_id = $this->find_entry_id(
			(int) $entry['catalog_id'],
			isset( $entry['msgctxt'] ) ? (string) $entry['msgctxt'] : null,
			(string) $entry['msgid'],
			(int) $entry['plural_index']
		);

		$now_gmt = (string) $entry['updated_at_gmt'];

		if ( $entry_id > 0 ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Internal table name interpolation; values are prepared.
			$existing = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT msgid_plural, comments_json, references_json, flags_json, status FROM {$table} WHERE id = %d",
					$entry_id
				),
				ARRAY_A
			);
			// phpcs:enable

			$unchanged = is_array( $existing )
				&& (string) $existing['msgid_plural'] === (string) $entry['msgid_plural']
				&& (string) $existing['comments_json'] === (string) $entry['comments_json']
				&& (string) $existing['references_json'] === (string) $entry['references_json']
				&& (string) $existing['flags_json'] === (string) $entry['flags_json']
				&& (string) $existing['status'] === (string) $entry['status'];

			if ( $unchanged ) {
				return 'unchanged';
			}

			$this->wpdb->update(
				$table,
				array(
					'msgid_plural'    => $entry['msgid_plural'],
					'comments_json'   => $entry['comments_json'],
					'references_json' => $entry['references_json'],
					'flags_json'      => $entry['flags_json'],
					'status'          => $entry['status'],
					'updated_at_gmt'  => $now_gmt,
				),
				array( 'id' => $entry_id ),
				array( '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return 'updated';
		}

		$this->wpdb->insert(
			$table,
			array(
				'catalog_id'      => $entry['catalog_id'],
				'msgctxt'         => $entry['msgctxt'],
				'msgid'           => $entry['msgid'],
				'msgid_plural'    => $entry['msgid_plural'],
				'plural_index'    => $entry['plural_index'],
				'comments_json'   => $entry['comments_json'],
				'references_json' => $entry['references_json'],
				'flags_json'      => $entry['flags_json'],
				'status'          => $entry['status'],
				'created_at_gmt'  => $entry['created_at_gmt'],
				'updated_at_gmt'  => $entry['updated_at_gmt'],
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return 'inserted';
	}

	/**
	 * Finds source entry row id for one source identity.
	 *
	 * @param int         $catalog_id Catalog ID.
	 * @param string|null $msgctxt Message context.
	 * @param string      $msgid Message ID.
	 * @param int         $plural_index Plural index.
	 * @return int
	 */
	private function find_entry_id( $catalog_id, $msgctxt, $msgid, $plural_index ) {
		$table = $this->schema_manager->get_entries_table_name();

		if ( null === $msgctxt ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Internal table name interpolation; values are prepared.
			return (int) $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT id FROM {$table} WHERE catalog_id = %d AND msgctxt IS NULL AND msgid = %s AND plural_index = %d",
					$catalog_id,
					$msgid,
					$plural_index
				)
			);
			// phpcs:enable
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Internal table name interpolation; values are prepared.
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$table} WHERE catalog_id = %d AND msgctxt = %s AND msgid = %s AND plural_index = %d",
				$catalog_id,
				$msgctxt,
				$msgid,
				$plural_index
			)
		);
		// phpcs:enable
	}
}
