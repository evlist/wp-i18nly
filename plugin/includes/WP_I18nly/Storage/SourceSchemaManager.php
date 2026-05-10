<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Source entries schema manager.
 *
 * @package I18nly
 */

namespace WP_I18nly\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades source catalog/entries tables.
 */
class SourceSchemaManager {
	/**
	 * Source schema version.
	 */
	private const SCHEMA_VERSION = '0.2.0';

	/**
	 * Option key storing installed source schema version.
	 */
	private const VERSION_OPTION = 'i18nly_source_schema_version';

	/**
	 * WordPress database object.
	 *
	 * @var object
	 */
	private $wpdb;

	/**
	 * Constructor.
	 *
	 * @param object|null $wpdb Optional wpdb instance.
	 */
	public function __construct( $wpdb = null ) {
		if ( null !== $wpdb ) {
			$this->wpdb = $wpdb;
			return;
		}

		global $wpdb;

		$this->wpdb = $wpdb;
	}

	/**
	 * Ensures source schema is installed.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		$installed_version = '';

		if ( function_exists( 'get_option' ) ) {
			$installed_version = (string) get_option( self::VERSION_OPTION, '' );
		}

		if ( self::SCHEMA_VERSION === $installed_version ) {
			return;
		}

		$this->create_tables();

		if ( function_exists( 'update_option' ) ) {
			update_option( self::VERSION_OPTION, self::SCHEMA_VERSION );
		}
	}

	/**
	 * Returns linguistic resources table name.
	 *
	 * @return string
	 */
	public function get_resources_table_name() {
		return (string) $this->wpdb->prefix . 'i18nly_linguistic_resources';
	}

	/**
	 * Returns source catalogs table name.
	 *
	 * @return string
	 */
	public function get_catalogs_table_name() {
		return $this->get_resources_table_name();
	}

	/**
	 * Returns linguistic resource entries table name.
	 *
	 * @return string
	 */
	public function get_resource_entries_table_name() {
		return (string) $this->wpdb->prefix . 'i18nly_linguistic_resource_entries';
	}

	/**
	 * Returns source entries table name.
	 *
	 * @return string
	 */
	public function get_entries_table_name() {
		return $this->get_resource_entries_table_name();
	}

	/**
	 * Returns linguistic resource targets table name.
	 *
	 * @return string
	 */
	public function get_resource_targets_table_name() {
		return (string) $this->wpdb->prefix . 'i18nly_linguistic_resource_targets';
	}

	/**
	 * Returns translated entries table name.
	 *
	 * @return string
	 */
	public function get_translated_entries_table_name() {
		return $this->get_resource_targets_table_name();
	}

	/**
	 * Creates source tables.
	 *
	 * @return void
	 */
	public function create_tables() {
		$catalogs_table           = $this->escape_table_name( $this->get_catalogs_table_name() );
		$entries_table            = $this->escape_table_name( $this->get_entries_table_name() );
		$translated_entries_table = $this->escape_table_name( $this->get_translated_entries_table_name() );
		$collation                = $this->get_charset_collate();

		if ( '' === $catalogs_table || '' === $entries_table || '' === $translated_entries_table ) {
			return;
		}

		$catalogs_sql = "CREATE TABLE {$catalogs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			resource_kind varchar(32) NOT NULL,
			source_slug varchar(191) NOT NULL,
			source_locale varchar(20) NOT NULL DEFAULT 'en_US',
			target_locale varchar(20) NOT NULL DEFAULT '',
			domain varchar(191) DEFAULT NULL,
			headers_json longtext DEFAULT NULL,
			created_at_gmt datetime NOT NULL,
			updated_at_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY resource_identity (resource_kind, source_slug, target_locale),
			KEY resource_lookup (resource_kind, source_slug)
		) {$collation}";

		$entries_sql = "CREATE TABLE {$entries_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			resource_id bigint(20) unsigned NOT NULL,
			msgctxt text DEFAULT NULL,
			msgid longtext NOT NULL,
			msgid_plural longtext DEFAULT NULL,
			translator_comment text DEFAULT NULL,
			comments_json longtext DEFAULT NULL,
			references_json longtext DEFAULT NULL,
			flags_json longtext DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			last_seen_at_gmt datetime DEFAULT NULL,
			created_at_gmt datetime NOT NULL,
			updated_at_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_identity (resource_id, msgctxt(191), msgid(191)),
			KEY resource_status (resource_id, status)
		) {$collation}";

		$translated_entries_sql = "CREATE TABLE {$translated_entries_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			resource_id bigint(20) unsigned NOT NULL,
			source_entry_id bigint(20) unsigned NOT NULL,
			form_index smallint(5) unsigned NOT NULL DEFAULT 0,
			target_text longtext DEFAULT NULL,
			status varchar(32) NOT NULL DEFAULT 'draft',
			used_ai tinyint(1) unsigned NOT NULL DEFAULT 0,
			used_manual tinyint(1) unsigned NOT NULL DEFAULT 1,
			comment text DEFAULT NULL,
			created_at_gmt datetime NOT NULL,
			updated_at_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY resource_source_entry_form (resource_id, source_entry_id, form_index),
			KEY resource_lookup (resource_id),
			KEY source_entry_lookup (source_entry_id)
		) {$collation}";

		if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			if ( function_exists( 'dbDelta' ) ) {
				dbDelta( $catalogs_sql );
				dbDelta( $entries_sql );
				dbDelta( $translated_entries_sql );

				return;
			}
		}

		$this->db_query( $catalogs_sql );
		$this->db_query( $entries_sql );
		$this->db_query( $translated_entries_sql );
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
	 * Executes one SQL query.
	 *
	 * @param string $query SQL query.
	 * @return void
	 */
	private function db_query( $query ) {
		$method = 'query';

		$this->wpdb->{$method}( $query );
	}

	/**
	 * Returns charset/collation SQL fragment.
	 *
	 * @return string
	 */
	private function get_charset_collate() {
		if ( method_exists( $this->wpdb, 'get_charset_collate' ) ) {
			return (string) $this->wpdb->get_charset_collate();
		}

		return '';
	}
}
