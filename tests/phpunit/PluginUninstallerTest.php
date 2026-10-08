<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Plugin uninstaller tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\Support\PluginUninstaller;

/**
 * Tests that the data is removed only when the administrator asked for it.
 */
class PluginUninstallerTest extends TestCase {
	/**
	 * Queries captured by the wpdb double.
	 *
	 * @var object
	 */
	private $wpdb;

	/**
	 * Prepares options, translations and a wpdb double.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		global $i18nly_test_deleted_posts, $wpdb;

		i18nly_test_reset_options();
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'              => 11,
					'source_slug'     => 'a/a.php',
					'target_language' => 'fr_FR',
				),
				array(
					'id'              => 12,
					'source_slug'     => 'b/b.php',
					'target_language' => 'de_DE',
				),
			)
		);

		$i18nly_test_deleted_posts = array();
		$this->wpdb                = new class() {
			/**
			 * Table prefix.
			 *
			 * @var string
			 */
			public $prefix = 'wp_';

			/**
			 * Captured queries.
			 *
			 * @var string[]
			 */
			public $queries = array();

			/**
			 * Captures one query.
			 *
			 * @param string $query SQL.
			 * @return int
			 */
			public function query( $query ) {
				$this->queries[] = $query;

				return 0;
			}
		};
		$wpdb                      = $this->wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	}

	/**
	 * Nothing is removed by default.
	 *
	 * @return void
	 */
	public function test_keeps_everything_by_default() {
		global $i18nly_test_deleted_posts;

		update_option( 'i18nly_translation_settings', array( 'deepl_api_key' => 'k' ) );

		( new PluginUninstaller() )->run();

		$this->assertSame( array(), $i18nly_test_deleted_posts );
		$this->assertSame( array(), $this->wpdb->queries );
		$this->assertNotFalse( get_option( 'i18nly_translation_settings', false ) );
	}

	/**
	 * Translations, tables and options are removed when requested.
	 *
	 * @return void
	 */
	public function test_removes_translations_tables_and_options_when_requested() {
		global $i18nly_test_deleted_posts;

		update_option( 'i18nly_translation_settings', array( 'delete_data_on_uninstall' => 1 ) );
		update_option( 'i18nly_deepl_usage_status_cache', array( 'x' => 1 ) );
		update_option( 'i18nly_source_schema_version', '0.4.0' );

		( new PluginUninstaller() )->run();

		$this->assertSame( array( array( 11, true ), array( 12, true ) ), $i18nly_test_deleted_posts );
		$this->assertCount( 3, $this->wpdb->queries );
		$this->assertStringStartsWith( 'DROP TABLE IF EXISTS', $this->wpdb->queries[0] );
		$this->assertFalse( get_option( 'i18nly_translation_settings', false ) );
		$this->assertFalse( get_option( 'i18nly_deepl_usage_status_cache', false ) );
		$this->assertFalse( get_option( 'i18nly_source_schema_version', false ) );
	}
}
