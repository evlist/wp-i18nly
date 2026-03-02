<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Source schema manager tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests source schema installation.
 */
class SourceSchemaManagerTest extends TestCase {
	/**
	 * Creates both source tables and stores schema version option.
	 *
	 * @return void
	 */
	public function test_maybe_upgrade_creates_tables_and_updates_version_option() {
		i18nly_test_reset_options();

		$wpdb_stub = new I18nly_Test_WPDB_Query_Stub();
		$manager   = new I18nly_Source_Schema_Manager( $wpdb_stub );

		$manager->maybe_upgrade();

		$this->assertCount( 2, $wpdb_stub->queries );
		$this->assertStringContainsString( 'i18nly_source_catalogs', $wpdb_stub->queries[0] );
		$this->assertStringContainsString( 'i18nly_source_entries', $wpdb_stub->queries[1] );
		$this->assertSame( '0.0.2', get_option( 'i18nly_source_schema_version', '' ) );
	}
}

/**
 * Query-only wpdb test stub.
 */
class I18nly_Test_WPDB_Query_Stub {
	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public $prefix = 'wp_';

	/**
	 * Captured SQL queries.
	 *
	 * @var array<int, string>
	 */
	public $queries = array();

	/**
	 * Captures executed query.
	 *
	 * @param string $sql SQL query.
	 * @return int
	 */
	public function query( $sql ) {
		$this->queries[] = (string) $sql;

		return 1;
	}

	/**
	 * Returns charset/collation fragment.
	 *
	 * @return string
	 */
	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
	}
}
