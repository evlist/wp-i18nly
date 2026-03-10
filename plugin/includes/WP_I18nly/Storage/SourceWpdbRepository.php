<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Source entries wpdb repository.
 *
 * @package I18nly
 */

namespace WP_I18nly\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Persists source catalogs and entries in WordPress tables.
 */
class SourceWpdbRepository {
	/**
	 * Source entry identity columns.
	 *
	 * @var array<int, string>
	 */
	private const ENTRY_IDENTITY_COLUMNS = array(
		'catalog_id',
		'msgctxt',
		'msgid',
	);

	/**
	 * Default plural forms count used when locale rules are unknown.
	 */
	private const DEFAULT_PLURAL_FORMS_COUNT = 2;

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
		'status',
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
	 * @var SourceSchemaManager
	 */
	private $schema_manager;

	/**
	 * Constructor.
	 *
	 * @param SourceSchemaManager $schema_manager Source schema manager.
	 * @param object|null         $wpdb Optional wpdb object.
	 */
	public function __construct( SourceSchemaManager $schema_manager, $wpdb = null ) {
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
			(string) $entry['msgid']
		);

		$now_gmt          = (string) $entry['updated_at_gmt'];
		$last_seen_at_gmt = isset( $entry['last_seen_at_gmt'] ) ? (string) $entry['last_seen_at_gmt'] : $now_gmt;

		if ( $entry_id > 0 ) {
			$entry['status'] = 'active';

			$existing = $this->db_get_row(
				$this->wpdb->prepare(
					'SELECT catalog_id, msgctxt, msgid, msgid_plural, comments_json, references_json, flags_json, status, last_seen_at_gmt FROM %i WHERE id = %d',
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
				'comments_json'    => $entry['comments_json'],
				'references_json'  => $entry['references_json'],
				'flags_json'       => $entry['flags_json'],
				'status'           => 'active',
				'last_seen_at_gmt' => $last_seen_at_gmt,
				'created_at_gmt'   => $entry['created_at_gmt'],
				'updated_at_gmt'   => $entry['updated_at_gmt'],
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return 'inserted';
	}

	/**
	 * Finds source entry row id for one source identity.
	 *
	 * @param int         $catalog_id Catalog ID.
	 * @param string|null $msgctxt Message context.
	 * @param string      $msgid Message ID.
	 * @return int
	 */
	private function find_entry_id( $catalog_id, $msgctxt, $msgid ) {
		$table = $this->escape_table_name( $this->schema_manager->get_entries_table_name() );

		if ( '' === $table ) {
			return 0;
		}

		if ( null === $msgctxt ) {
			return (int) $this->db_get_var(
				$this->wpdb->prepare(
					'SELECT id FROM %i WHERE catalog_id = %d AND msgctxt IS NULL AND msgid = %s',
					$table,
					$catalog_id,
					$msgid
				)
			);
		}

		return (int) $this->db_get_var(
			$this->wpdb->prepare(
				'SELECT id FROM %i WHERE catalog_id = %d AND msgctxt = %s AND msgid = %s',
				$table,
				$catalog_id,
				$msgctxt,
				$msgid
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
	 * Lists source entries for one plugin slug.
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @param int    $limit Maximum row count.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_source_entries_by_plugin_slug( $plugin_slug, $limit = 500 ) {
		$entries_table  = $this->escape_table_name( $this->schema_manager->get_entries_table_name() );
		$catalogs_table = $this->escape_table_name( $this->schema_manager->get_catalogs_table_name() );

		if ( '' === $entries_table || '' === $catalogs_table ) {
			return array();
		}

		$max_rows = max( 1, (int) $limit );

		$query = $this->wpdb->prepare(
			'SELECT e.id AS source_entry_id, e.msgctxt, e.msgid, e.msgid_plural, e.status, e.last_seen_at_gmt, e.updated_at_gmt FROM %i e INNER JOIN %i c ON c.id = e.catalog_id WHERE c.plugin_slug = %s ORDER BY e.msgid ASC, e.id ASC LIMIT %d',
			$entries_table,
			$catalogs_table,
			(string) $plugin_slug,
			$max_rows
		);

		return $this->db_get_results( $query, ARRAY_A );
	}

	/**
	 * Ensures translated rows exist for all source entries of one translation.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $plugin_slug Plugin slug.
	 * @param string $now_gmt Current GMT datetime.
	 * @param int    $plural_forms_count Number of target plural forms.
	 * @return int Number of inserted rows.
	 */
	public function ensure_translated_entries_for_translation( $translation_id, $plugin_slug, $now_gmt, $plural_forms_count = self::DEFAULT_PLURAL_FORMS_COUNT ) {
		$entries_table            = $this->escape_table_name( $this->schema_manager->get_entries_table_name() );
		$catalogs_table           = $this->escape_table_name( $this->schema_manager->get_catalogs_table_name() );
		$translated_entries_table = $this->escape_table_name( $this->schema_manager->get_translated_entries_table_name() );

		if ( '' === $entries_table || '' === $catalogs_table || '' === $translated_entries_table ) {
			return 0;
		}

		$max_forms = max( 1, (int) $plural_forms_count );

		$source_rows = $this->db_get_results(
			$this->wpdb->prepare(
				'SELECT e.id AS source_entry_id, e.msgid_plural FROM %i e INNER JOIN %i c ON c.id = e.catalog_id WHERE c.plugin_slug = %s ORDER BY e.id ASC',
				$entries_table,
				$catalogs_table,
				(string) $plugin_slug
			),
			ARRAY_A
		);

		$inserted = 0;

		foreach ( $source_rows as $source_row ) {
			$source_entry_id = isset( $source_row['source_entry_id'] ) ? (int) $source_row['source_entry_id'] : 0;

			if ( $source_entry_id <= 0 ) {
				continue;
			}

			$has_plural     = isset( $source_row['msgid_plural'] ) && '' !== trim( (string) $source_row['msgid_plural'] );
			$required_forms = $has_plural ? max( 2, $max_forms ) : 1;

			for ( $form_index = 0; $form_index < $required_forms; $form_index++ ) {
				$existing_translated_entry_id = (int) $this->db_get_var(
					$this->wpdb->prepare(
						'SELECT id FROM %i WHERE translation_id = %d AND source_entry_id = %d AND form_index = %d',
						$translated_entries_table,
						(int) $translation_id,
						$source_entry_id,
						$form_index
					)
				);

				if ( $existing_translated_entry_id > 0 ) {
					continue;
				}

				$result = $this->wpdb->insert(
					$translated_entries_table,
					array(
						'translation_id'  => (int) $translation_id,
						'source_entry_id' => $source_entry_id,
						'form_index'      => $form_index,
						'translation'     => '',
						'comment'         => '',
						'created_at_gmt'  => (string) $now_gmt,
						'updated_at_gmt'  => (string) $now_gmt,
					),
					array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
				);

				if ( false !== $result ) {
					++$inserted;
				}
			}
		}

		return $inserted;
	}

	/**
	 * Lists source entries joined with translated values for one translation.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $plugin_slug Plugin slug.
	 * @param int    $limit Maximum row count.
	 * @param int    $plural_forms_count Number of target plural forms.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_translation_entries_by_plugin_slug( $translation_id, $plugin_slug, $limit = 500, $plural_forms_count = self::DEFAULT_PLURAL_FORMS_COUNT ) {
		$entries_table            = $this->escape_table_name( $this->schema_manager->get_entries_table_name() );
		$catalogs_table           = $this->escape_table_name( $this->schema_manager->get_catalogs_table_name() );
		$translated_entries_table = $this->escape_table_name( $this->schema_manager->get_translated_entries_table_name() );

		if ( '' === $entries_table || '' === $catalogs_table || '' === $translated_entries_table ) {
			return array();
		}

		$max_rows = max( 1, (int) $limit );

		$query = $this->wpdb->prepare(
			'SELECT e.id AS source_entry_id, e.msgctxt, e.msgid, e.msgid_plural, e.status, e.last_seen_at_gmt, e.updated_at_gmt, t.form_index, t.translation, t.comment, t.updated_at_gmt AS translation_updated_at_gmt FROM %i e INNER JOIN %i c ON c.id = e.catalog_id LEFT JOIN %i t ON t.source_entry_id = e.id AND t.translation_id = %d WHERE c.plugin_slug = %s ORDER BY e.msgid ASC, e.id ASC, t.form_index ASC LIMIT %d',
			$entries_table,
			$catalogs_table,
			$translated_entries_table,
			(int) $translation_id,
			(string) $plugin_slug,
			$max_rows
		);

		$rows             = $this->db_get_results( $query, ARRAY_A );
		$normalized_rows  = array();
		$max_plural_forms = max( 1, (int) $plural_forms_count );

		foreach ( $rows as $row ) {
			$source_entry_id = isset( $row['source_entry_id'] ) ? absint( $row['source_entry_id'] ) : 0;

			if ( $source_entry_id <= 0 ) {
				continue;
			}

			if ( ! isset( $normalized_rows[ $source_entry_id ] ) ) {
				$normalized_rows[ $source_entry_id ] = array(
					'source_entry_id'  => $source_entry_id,
					'msgctxt'          => isset( $row['msgctxt'] ) ? (string) $row['msgctxt'] : '',
					'msgid'            => isset( $row['msgid'] ) ? (string) $row['msgid'] : '',
					'msgid_plural'     => isset( $row['msgid_plural'] ) ? (string) $row['msgid_plural'] : '',
					'status'           => isset( $row['status'] ) ? (string) $row['status'] : '',
					'last_seen_at_gmt' => isset( $row['last_seen_at_gmt'] ) ? (string) $row['last_seen_at_gmt'] : '',
					'updated_at_gmt'   => isset( $row['updated_at_gmt'] ) ? (string) $row['updated_at_gmt'] : '',
					'translations'     => array(),
				);
			}

			if ( isset( $row['form_index'] ) && '' !== (string) $row['form_index'] ) {
				$form_index = max( 0, (int) $row['form_index'] );

				$normalized_rows[ $source_entry_id ]['translations'][ $form_index ] = array(
					'source_entry_id' => $source_entry_id,
					'form_index'      => $form_index,
					'translation'     => isset( $row['translation'] ) ? (string) $row['translation'] : '',
				);
			}
		}

		foreach ( $normalized_rows as &$normalized_row ) {
			$has_plural     = '' !== trim( (string) $normalized_row['msgid_plural'] );
			$required_forms = $has_plural ? max( 2, $max_plural_forms ) : 1;

			for ( $form_index = 0; $form_index < $required_forms; $form_index++ ) {
				if ( isset( $normalized_row['translations'][ $form_index ] ) ) {
					continue;
				}

				$normalized_row['translations'][ $form_index ] = array(
					'source_entry_id' => (int) $normalized_row['source_entry_id'],
					'form_index'      => $form_index,
					'translation'     => '',
				);
			}

			ksort( $normalized_row['translations'] );
			$normalized_row['translations'] = array_values( $normalized_row['translations'] );
		}
		unset( $normalized_row );

		return array_values( $normalized_rows );
	}

	/**
	 * Upserts one translated entry value row.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param int    $source_entry_id Source entry ID.
	 * @param int    $form_index Target plural form index.
	 * @param string $translation Translated value.
	 * @param string $now_gmt Current GMT datetime.
	 * @return bool
	 */
	public function upsert_translated_entry( $translation_id, $source_entry_id, $form_index, $translation, $now_gmt ) {
		$translated_entries_table = $this->escape_table_name( $this->schema_manager->get_translated_entries_table_name() );

		if ( '' === $translated_entries_table ) {
			return false;
		}

		$translated_entry_id = (int) $this->db_get_var(
			$this->wpdb->prepare(
				'SELECT id FROM %i WHERE translation_id = %d AND source_entry_id = %d AND form_index = %d',
				$translated_entries_table,
				(int) $translation_id,
				(int) $source_entry_id,
				(int) $form_index
			)
		);

		if ( $translated_entry_id > 0 ) {
			$result = $this->wpdb->update(
				$translated_entries_table,
				array(
					'translation'    => (string) $translation,
					'updated_at_gmt' => (string) $now_gmt,
				),
				array( 'id' => (int) $translated_entry_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			return false !== $result;
		}

		$result = $this->wpdb->insert(
			$translated_entries_table,
			array(
				'translation_id'  => (int) $translation_id,
				'source_entry_id' => (int) $source_entry_id,
				'form_index'      => (int) $form_index,
				'translation'     => (string) $translation,
				'comment'         => '',
				'created_at_gmt'  => (string) $now_gmt,
				'updated_at_gmt'  => (string) $now_gmt,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return false !== $result;
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
	 * Executes one multi-row read query.
	 *
	 * @param string $query Prepared query.
	 * @param string $output Output type.
	 * @return array<int, array<string, mixed>>
	 */
	private function db_get_results( $query, $output = OBJECT ) {
		$method = 'get_results';

		if ( ! method_exists( $this->wpdb, $method ) ) {
			return array();
		}

		$results = $this->wpdb->{$method}( $query, $output );

		if ( ! is_array( $results ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $results as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$normalized[] = $row;
		}

		return $normalized;
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
