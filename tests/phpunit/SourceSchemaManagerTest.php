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

// phpcs:disable Generic.Files.OneObjectStructurePerFile

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

		$this->assertCount( 3, $wpdb_stub->queries );
		$this->assertStringContainsString( 'i18nly_source_catalogs', $wpdb_stub->queries[0] );
		$this->assertStringContainsString( 'i18nly_source_entries', $wpdb_stub->queries[1] );
		$this->assertStringContainsString( 'last_seen_at_gmt', $wpdb_stub->queries[1] );
		$this->assertStringContainsString( 'i18nly_translated_entries', $wpdb_stub->queries[2] );
		$this->assertStringContainsString( 'translation_source_entry_form', $wpdb_stub->queries[2] );
		$this->assertStringContainsString( 'form_index', $wpdb_stub->queries[2] );
		$this->assertSame( '0.0.6', get_option( 'i18nly_source_schema_version', '' ) );
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
	 * Prepares SQL by interpolating values for tests.
	 *
	 * @param string $query Query template.
	 * @param mixed  ...$args Query arguments.
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		$parts = explode( '%', (string) $query );
		if ( count( $parts ) <= 1 ) {
			return (string) $query;
		}

		$result = array_shift( $parts );
		$index  = 0;

		foreach ( $parts as $part ) {
			$specifier = substr( $part, 0, 1 );
			$tail      = substr( $part, 1 );
			$arg       = isset( $args[ $index ] ) ? $args[ $index ] : '';

			if ( 'i' === $specifier ) {
				$identifier = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $arg );
				$result    .= '`' . (string) $identifier . '`';
			} elseif ( 'd' === $specifier ) {
				$result .= (string) (int) $arg;
			} else {
				$result .= "'" . addslashes( (string) $arg ) . "'";
			}

			$result .= $tail;
			++$index;
		}

		return $result;
	}

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

// phpcs:enable
