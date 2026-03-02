<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Source entries schema manager.
 *
 * @package I18nly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades source catalog/entries tables.
 */
class I18nly_Source_Schema_Manager {
	/**
	 * Source schema version.
	 */
	private const SCHEMA_VERSION = '0.0.2';

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
	 * Returns source catalogs table name.
	 *
	 * @return string
	 */
	public function get_catalogs_table_name() {
		return (string) $this->wpdb->prefix . 'i18nly_source_catalogs';
	}

	/**
	 * Returns source entries table name.
	 *
	 * @return string
	 */
	public function get_entries_table_name() {
		return (string) $this->wpdb->prefix . 'i18nly_source_entries';
	}

	/**
	 * Creates source tables.
	 *
	 * @return void
	 */
	public function create_tables() {
		$catalogs_table = $this->get_catalogs_table_name();
		$entries_table  = $this->get_entries_table_name();
		$collation      = $this->get_charset_collate();

		$catalogs_sql = "CREATE TABLE IF NOT EXISTS {$catalogs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			plugin_slug varchar(191) NOT NULL,
			domain varchar(191) DEFAULT NULL,
			headers_json longtext DEFAULT NULL,
			created_at_gmt datetime NOT NULL,
			updated_at_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY plugin_slug (plugin_slug)
		) {$collation}";

		$entries_sql = "CREATE TABLE IF NOT EXISTS {$entries_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			catalog_id bigint(20) unsigned NOT NULL,
			msgctxt text DEFAULT NULL,
			msgid longtext NOT NULL,
			msgid_plural longtext DEFAULT NULL,
			plural_index smallint(5) unsigned NOT NULL DEFAULT 0,
			comments_json longtext DEFAULT NULL,
			references_json longtext DEFAULT NULL,
			flags_json longtext DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at_gmt datetime NOT NULL,
			updated_at_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_identity (catalog_id, msgctxt(191), msgid(191), plural_index),
			KEY catalog_status (catalog_id, status)
		) {$collation}";

		$this->wpdb->query( $catalogs_sql );
		$this->wpdb->query( $entries_sql );
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
