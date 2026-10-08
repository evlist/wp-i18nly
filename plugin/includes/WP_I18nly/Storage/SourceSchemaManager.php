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
	private const SCHEMA_VERSION = '0.4.0';

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
	 * Ensures source schema is installed and up to date.
	 *
	 * The tables are created or completed (dbDelta adds tables and columns, it never drops or alters
	 * anything), then the data migration steps newer than the installed version are run in version order.
	 * When a step fails, the stored version is left unchanged so that the upgrade is retried on the next
	 * request. See get_migration_steps().
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

		if ( '' !== $installed_version && ! $this->run_migrations( $installed_version ) ) {
			return;
		}

		if ( function_exists( 'update_option' ) ) {
			update_option( self::VERSION_OPTION, self::SCHEMA_VERSION );
		}
	}

	/**
	 * Returns the migration steps, indexed by the schema version they migrate to.
	 *
	 * To change the schema after a release: raise SCHEMA_VERSION, update the CREATE TABLE statements (new
	 * columns and indexes are added by dbDelta) and add here a step for the changes dbDelta cannot make
	 * (renames, drops, data conversions). A step receives no argument, must be idempotent and may throw.
	 * The schema of 0.4.0 is the first one with a migration path: databases created before it must be reset.
	 *
	 * @return array<string, callable>
	 */
	protected function get_migration_steps() {
		return array();
	}

	/**
	 * Runs the migration steps newer than a version, up to the current schema version.
	 *
	 * @param string $from_version Installed schema version.
	 * @return bool False when a step failed.
	 */
	private function run_migrations( $from_version ) {
		$steps = $this->get_migration_steps();

		uksort( $steps, 'version_compare' );

		foreach ( $steps as $version => $step ) {
			if ( version_compare( (string) $version, $from_version, '<=' ) || version_compare( (string) $version, self::SCHEMA_VERSION, '>' ) ) {
				continue;
			}

			try {
				call_user_func( $step );
			} catch ( \Throwable $error ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Drops the three tables of the plugin. Used when the plugin data is deleted.
	 *
	 * @return void
	 */
	public function drop_tables() {
		$tables = array(
			$this->get_resource_targets_table_name(),
			$this->get_resource_entries_table_name(),
			$this->get_resources_table_name(),
		);

		foreach ( $tables as $table ) {
			$escaped = $this->escape_table_name( $table );

			if ( '' !== $escaped ) {
				$this->db_query( 'DROP TABLE IF EXISTS `' . $escaped . '`' );
			}
		}

		if ( function_exists( 'delete_option' ) ) {
			delete_option( self::VERSION_OPTION );
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
	 * Returns linguistic resource entries table name.
	 *
	 * @return string
	 */
	public function get_resource_entries_table_name() {
		return (string) $this->wpdb->prefix . 'i18nly_linguistic_resource_entries';
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
	 * Creates source tables.
	 *
	 * @return void
	 */
	public function create_tables() {
		$resources_table = $this->escape_table_name( $this->get_resources_table_name() );
		$entries_table   = $this->escape_table_name( $this->get_resource_entries_table_name() );
		$targets_table   = $this->escape_table_name( $this->get_resource_targets_table_name() );
		$collation       = $this->get_charset_collate();

		if ( '' === $resources_table || '' === $entries_table || '' === $targets_table ) {
			return;
		}

		$resources_sql = "CREATE TABLE {$resources_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			resource_kind varchar(32) NOT NULL,
			source_slug varchar(191) NOT NULL,
			source_locale varchar(20) NOT NULL DEFAULT 'en_US',
			target_locale varchar(20) NOT NULL DEFAULT '',
			anchor_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			domain varchar(191) DEFAULT NULL,
			headers_json longtext DEFAULT NULL,
			created_at_gmt datetime NOT NULL,
			updated_at_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY resource_scope (resource_kind, source_slug, target_locale, anchor_post_id),
			KEY resource_anchor (resource_kind, anchor_post_id),
			KEY resource_lookup (resource_kind, source_slug)
		) {$collation}";

		$entries_sql = "CREATE TABLE {$entries_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			resource_id bigint(20) unsigned NOT NULL,
			msgctxt text DEFAULT NULL,
			msgid longtext NOT NULL,
			msgid_plural longtext DEFAULT NULL,
			match_mode varchar(16) NOT NULL DEFAULT 'exact',
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

		$targets_sql = "CREATE TABLE {$targets_table} (
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
				dbDelta( $resources_sql );
				dbDelta( $entries_sql );
				dbDelta( $targets_sql );

				return;
			}
		}

		$this->db_query( $resources_sql );
		$this->db_query( $entries_sql );
		$this->db_query( $targets_sql );
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
