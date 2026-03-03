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
	 * Source entry identity columns.
	 *
	 * @var array<int, string>
	 */
	private const ENTRY_IDENTITY_COLUMNS = array(
		'catalog_id',
		'msgctxt',
		'msgid',
		'plural_index',
	);

	/**
	 * Source entry content columns tracked for unchanged detection.
	 *
	 * @var array<int, string>
	 */
	private const ENTRY_CONTENT_COLUMNS = array(
		'msgid_plural',
		'comments_json',
		'references_json',
		'flags_json',
	);

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
		$table = $this->escape_table_name( $this->schema_manager->get_catalogs_table_name() );

		if ( '' === $table ) {
			return 0;
		}

		$catalog_id = (int) $this->db_get_var(
			$this->wpdb->prepare(
				'SELECT id FROM %i WHERE plugin_slug = %s',
				$table,
				$plugin_slug
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
		$table = $this->escape_table_name( $this->schema_manager->get_entries_table_name() );

		if ( '' === $table ) {
			return 'unchanged';
		}

		$entry_id = $this->find_entry_id(
			(int) $entry['catalog_id'],
			isset( $entry['msgctxt'] ) ? (string) $entry['msgctxt'] : null,
			(string) $entry['msgid'],
			(int) $entry['plural_index']
		);

		$now_gmt          = (string) $entry['updated_at_gmt'];
		$last_seen_at_gmt = isset( $entry['last_seen_at_gmt'] ) ? (string) $entry['last_seen_at_gmt'] : $now_gmt;

		if ( $entry_id > 0 ) {
			$existing = $this->db_get_row(
				$this->wpdb->prepare(
					'SELECT catalog_id, msgctxt, msgid, plural_index, msgid_plural, comments_json, references_json, flags_json, status, last_seen_at_gmt FROM %i WHERE id = %d',
					$table,
					$entry_id
				),
				ARRAY_A
			);

			$unchanged = $this->entry_rows_are_equal( $existing, $entry );

			if ( $unchanged ) {
				$this->wpdb->update(
					$table,
					array(
						'last_seen_at_gmt' => $last_seen_at_gmt,
						'updated_at_gmt'   => $now_gmt,
						'status'           => 'active',
					),
					array( 'id' => $entry_id ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);

				return 'unchanged';
			}

			$this->wpdb->update(
				$table,
				array(
					'msgid_plural'     => $entry['msgid_plural'],
					'comments_json'    => $entry['comments_json'],
					'references_json'  => $entry['references_json'],
					'flags_json'       => $entry['flags_json'],
					'status'           => 'active',
					'last_seen_at_gmt' => $last_seen_at_gmt,
					'updated_at_gmt'   => $now_gmt,
				),
				array( 'id' => $entry_id ),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return 'updated';
		}

		$this->wpdb->insert(
			$table,
			array(
				'catalog_id'       => $entry['catalog_id'],
				'msgctxt'          => $entry['msgctxt'],
				'msgid'            => $entry['msgid'],
				'msgid_plural'     => $entry['msgid_plural'],
				'plural_index'     => $entry['plural_index'],
				'comments_json'    => $entry['comments_json'],
				'references_json'  => $entry['references_json'],
				'flags_json'       => $entry['flags_json'],
				'status'           => 'active',
				'last_seen_at_gmt' => $last_seen_at_gmt,
				'created_at_gmt'   => $entry['created_at_gmt'],
				'updated_at_gmt'   => $entry['updated_at_gmt'],
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
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
		$table = $this->escape_table_name( $this->schema_manager->get_entries_table_name() );

		if ( '' === $table ) {
			return 0;
		}

		if ( null === $msgctxt ) {
			return (int) $this->db_get_var(
				$this->wpdb->prepare(
					'SELECT id FROM %i WHERE catalog_id = %d AND msgctxt IS NULL AND msgid = %s AND plural_index = %d',
					$table,
					$catalog_id,
					$msgid,
					$plural_index
				)
			);
		}

		return (int) $this->db_get_var(
			$this->wpdb->prepare(
				'SELECT id FROM %i WHERE catalog_id = %d AND msgctxt = %s AND msgid = %s AND plural_index = %d',
				$table,
				$catalog_id,
				$msgctxt,
				$msgid,
				$plural_index
			)
		);
	}

	/**
	 * Marks as obsolete active entries not seen in current import.
	 *
	 * @param int    $catalog_id Catalog ID.
	 * @param string $now_gmt Update datetime in GMT.
	 * @return int Number of rows marked obsolete.
	 */
	public function mark_obsolete_entries_not_seen( $catalog_id, $now_gmt ) {
		$table = $this->escape_table_name( $this->schema_manager->get_entries_table_name() );

		if ( '' === $table ) {
			return 0;
		}

		$query = $this->wpdb->prepare(
			'UPDATE %i SET status = %s, updated_at_gmt = %s WHERE catalog_id = %d AND status = %s AND last_seen_at_gmt IS NULL',
			$table,
			'obsolete',
			$now_gmt,
			(int) $catalog_id,
			'active'
		);

		$result = $this->db_query( $query );

		if ( is_int( $result ) ) {
			return $result;
		}

		return 0;
	}

	/**
	 * Clears last_seen marker for active entries before one import.
	 *
	 * @param int $catalog_id Catalog ID.
	 * @return void
	 */
	public function reset_last_seen_for_catalog( $catalog_id ) {
		$table = $this->escape_table_name( $this->schema_manager->get_entries_table_name() );

		if ( '' === $table ) {
			return;
		}

		$query = $this->wpdb->prepare(
			'UPDATE %i SET last_seen_at_gmt = NULL WHERE catalog_id = %d AND status = %s',
			$table,
			(int) $catalog_id,
			'active'
		);

		$this->db_query( $query );
	}

	/**
	 * Validates and escapes a table name.
	 *
	 * @param string $table_name Raw table name.
	 * @return string
	 */
	private function escape_table_name( $table_name ) {
		$table_name = (string) $table_name;

		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $table_name ) ) {
			return '';
		}

		if ( function_exists( 'esc_sql' ) ) {
			return (string) esc_sql( $table_name );
		}

		return $table_name;
	}

	/**
	 * Executes one scalar read query.
	 *
	 * @param string $query Prepared query.
	 * @return mixed
	 */
	private function db_get_var( $query ) {
		$method = 'get_var';

		return $this->wpdb->{$method}( $query );
	}

	/**
	 * Executes one row read query.
	 *
	 * @param string $query Prepared query.
	 * @param string $output Output type.
	 * @return array<string, mixed>|null
	 */
	private function db_get_row( $query, $output = OBJECT ) {
		$method = 'get_row';

		$result = $this->wpdb->{$method}( $query, $output );

		return is_array( $result ) ? $result : null;
	}

	/**
	 * Executes one write query.
	 *
	 * @param string $query Prepared query.
	 * @return int|false
	 */
	private function db_query( $query ) {
		$method = 'query';

		return $this->wpdb->{$method}( $query );
	}

	/**
	 * Returns whether existing DB row and candidate entry are equivalent.
	 *
	 * @param array<string, mixed>|null $existing Existing DB row.
	 * @param array<string, mixed>      $entry Candidate entry payload.
	 * @return bool
	 */
	private function entry_rows_are_equal( $existing, array $entry ) {
		if ( ! is_array( $existing ) ) {
			return false;
		}

		foreach ( array_merge( self::ENTRY_IDENTITY_COLUMNS, self::ENTRY_CONTENT_COLUMNS ) as $column ) {
			$existing_value = array_key_exists( $column, $existing ) ? $existing[ $column ] : null;
			$entry_value    = array_key_exists( $column, $entry ) ? $entry[ $column ] : null;

			if ( (string) $existing_value !== (string) $entry_value ) {
				return false;
			}
		}

		return true;
	}
}
