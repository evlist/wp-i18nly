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
		$manager   = new \WP_I18nly\Storage\SourceSchemaManager( $wpdb_stub );

		$manager->maybe_upgrade();

		$this->assertCount( 3, $wpdb_stub->queries );
		$this->assertStringContainsString( 'i18nly_linguistic_resources', $wpdb_stub->queries[0] );
		$this->assertStringContainsString( 'resource_kind varchar(32) NOT NULL', $wpdb_stub->queries[0] );
		$this->assertStringContainsString( 'source_slug varchar(191) NOT NULL', $wpdb_stub->queries[0] );
		$this->assertStringContainsString( 'anchor_post_id bigint(20) unsigned NOT NULL DEFAULT 0', $wpdb_stub->queries[0] );
		$this->assertStringContainsString( 'UNIQUE KEY resource_scope (resource_kind, source_slug, target_locale, anchor_post_id)', $wpdb_stub->queries[0] );
		$this->assertStringContainsString( 'KEY resource_anchor (resource_kind, anchor_post_id)', $wpdb_stub->queries[0] );
		$this->assertStringContainsString( 'i18nly_linguistic_resource_entries', $wpdb_stub->queries[1] );
		$this->assertStringContainsString( 'resource_id bigint(20) unsigned NOT NULL', $wpdb_stub->queries[1] );
		$this->assertStringContainsString( 'translator_comment', $wpdb_stub->queries[1] );
		$this->assertStringContainsString( "match_mode varchar(16) NOT NULL DEFAULT 'exact'", $wpdb_stub->queries[1] );
		$this->assertStringContainsString( 'last_seen_at_gmt', $wpdb_stub->queries[1] );
		$this->assertStringContainsString( 'i18nly_linguistic_resource_targets', $wpdb_stub->queries[2] );
		$this->assertStringContainsString( 'resource_id bigint(20) unsigned NOT NULL', $wpdb_stub->queries[2] );
		$this->assertStringContainsString( 'resource_source_entry_form', $wpdb_stub->queries[2] );
		$this->assertStringContainsString( 'resource_lookup (resource_id)', $wpdb_stub->queries[2] );
		$this->assertStringContainsString( 'form_index', $wpdb_stub->queries[2] );
		$this->assertStringContainsString( 'target_text longtext DEFAULT NULL', $wpdb_stub->queries[2] );
		$this->assertStringContainsString( "status varchar(32) NOT NULL DEFAULT 'draft'", $wpdb_stub->queries[2] );
		$this->assertStringContainsString( 'used_ai tinyint(1) unsigned NOT NULL DEFAULT 0', $wpdb_stub->queries[2] );
		$this->assertStringContainsString( 'used_manual tinyint(1) unsigned NOT NULL DEFAULT 1', $wpdb_stub->queries[2] );
		$this->assertSame( '0.4.0', get_option( 'i18nly_source_schema_version', '' ) );
	}

	/**
	 * Runs only the steps newer than the installed version and not newer than the current one, in order.
	 *
	 * @return void
	 */
	public function test_runs_pending_migration_steps_in_version_order() {
		i18nly_test_reset_options();
		update_option( 'i18nly_source_schema_version', '0.3.0' );

		$ran     = array();
		$manager = new I18nly_Test_Schema_Manager_With_Steps( new I18nly_Test_WPDB_Query_Stub() );

		$manager->steps = array(
			'0.4.0'  => static function () use ( &$ran ) {
				$ran[] = '0.4.0';
			},
			'0.3.5'  => static function () use ( &$ran ) {
				$ran[] = '0.3.5';
			},
			'0.3.0'  => static function () use ( &$ran ) {
				$ran[] = '0.3.0';
			},
			'0.10.0' => static function () use ( &$ran ) {
				$ran[] = '0.10.0';
			},
		);

		$manager->maybe_upgrade();

		$this->assertSame( array( '0.3.5', '0.4.0' ), $ran );
		$this->assertSame( '0.4.0', get_option( 'i18nly_source_schema_version' ) );
	}

	/**
	 * A fresh install runs no migration.
	 *
	 * @return void
	 */
	public function test_fresh_install_runs_no_migration() {
		i18nly_test_reset_options();

		$ran            = false;
		$manager        = new I18nly_Test_Schema_Manager_With_Steps( new I18nly_Test_WPDB_Query_Stub() );
		$manager->steps = array(
			'0.4.0' => static function () use ( &$ran ) {
				$ran = true;
			},
		);

		$manager->maybe_upgrade();

		$this->assertFalse( $ran );
	}

	/**
	 * A failing step keeps the old version so that the upgrade is retried.
	 *
	 * @return void
	 */
	public function test_failed_migration_keeps_the_installed_version() {
		i18nly_test_reset_options();
		update_option( 'i18nly_source_schema_version', '0.3.0' );

		$manager        = new I18nly_Test_Schema_Manager_With_Steps( new I18nly_Test_WPDB_Query_Stub() );
		$manager->steps = array(
			'0.4.0' => static function () {
				throw new \RuntimeException( 'boom' );
			},
		);

		$manager->maybe_upgrade();

		$this->assertSame( '0.3.0', get_option( 'i18nly_source_schema_version' ) );
	}

	/**
	 * Dropping removes the three tables and the version option.
	 *
	 * @return void
	 */
	public function test_drop_tables_removes_the_tables_and_the_version() {
		i18nly_test_reset_options();
		update_option( 'i18nly_source_schema_version', '0.4.0' );

		$wpdb_stub = new I18nly_Test_WPDB_Query_Stub();
		( new \WP_I18nly\Storage\SourceSchemaManager( $wpdb_stub ) )->drop_tables();

		$this->assertSame(
			array(
				'DROP TABLE IF EXISTS `wp_i18nly_linguistic_resource_targets`',
				'DROP TABLE IF EXISTS `wp_i18nly_linguistic_resource_entries`',
				'DROP TABLE IF EXISTS `wp_i18nly_linguistic_resources`',
			),
			$wpdb_stub->queries
		);
		$this->assertSame( '', get_option( 'i18nly_source_schema_version', '' ) );
	}
}

/**
 * Schema manager with test migration steps.
 */
class I18nly_Test_Schema_Manager_With_Steps extends \WP_I18nly\Storage\SourceSchemaManager {
	/**
	 * Steps to run.
	 *
	 * @var array<string, callable>
	 */
	public $steps = array();

	/**
	 * Returns the test steps.
	 *
	 * @return array<string, callable>
	 */
	protected function get_migration_steps() {
		return $this->steps;
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
