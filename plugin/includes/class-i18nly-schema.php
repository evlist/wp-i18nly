<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * I18nly schema installer.
 *
 * @package I18nly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades I18nly database tables.
 */
class I18nly_Schema {
	/**
	 * Current schema version.
	 */
	private const DB_VERSION = '0.0.1';

	/**
	 * Option key storing the installed schema version.
	 */
	private const DB_VERSION_OPTION = 'i18nly_db_version';

	/**
	 * Creates or updates plugin database tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate    = $wpdb->get_charset_collate();
		$translations_table = self::translations_table_name();

		$translations_sql = "CREATE TABLE {$translations_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_slug varchar(191) NOT NULL,
			target_language varchar(35) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY source_slug (source_slug),
			KEY target_language (target_language)
		) {$charset_collate};";

		dbDelta( $translations_sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Runs schema install only when plugin schema is outdated.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed_version = get_option( self::DB_VERSION_OPTION, '' );

		if ( self::DB_VERSION !== (string) $installed_version ) {
			self::install();
		}
	}

	/**
	 * Returns the translations table name.
	 *
	 * @return string
	 */
	public static function translations_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'i18nly_translations';
	}
}
