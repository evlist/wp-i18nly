<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * POT source entry extractor tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

// phpcs:disable WordPress.WP.AlternativeFunctions

/**
 * Tests source extraction from plugin PHP files.
 */
class PotSourceEntryExtractorTest extends TestCase {
	/**
	 * Extracts singular, contextual and plural strings.
	 *
	 * @return void
	 */
	public function test_extract_from_source_slug_collects_gettext_entries() {
		$plugins_root = sys_get_temp_dir() . '/i18nly-extractor-' . uniqid( '', true );
		$plugin_dir   = $plugins_root . '/sample-plugin';
		$main_file    = $plugin_dir . '/sample-plugin.php';

		mkdir( $plugin_dir, 0755, true );
		file_put_contents(
			$main_file,
			"<?php\n/* translators: Greeting shown on welcome panel. */\nprintf( __( 'Hello world', 'sample-plugin' ) );\n_x( 'Open', 'verb', 'sample-plugin' );\n_n( '%s item', '%s items', 2, 'sample-plugin' );\n"
		);

		$extractor = new I18nly_Pot_Source_Entry_Extractor( $plugins_root );
		$entries   = $extractor->extract_from_source_slug( 'sample-plugin/sample-plugin.php' );

		$this->assertCount( 3, $entries );

		$originals = array_map(
			static function ( $entry ) {
				return $entry['original'];
			},
			$entries
		);

		$this->assertContains( 'Hello world', $originals );
		$this->assertContains( 'Open', $originals );
		$this->assertContains( '%s item', $originals );

		$plural_entry = null;
		$hello_entry  = null;
		foreach ( $entries as $entry ) {
			if ( 'Hello world' === $entry['original'] ) {
				$hello_entry = $entry;
			}

			if ( '%s item' === $entry['original'] ) {
				$plural_entry = $entry;
			}
		}

		$this->assertIsArray( $plural_entry );
		$this->assertSame( '%s items', $plural_entry['plural'] );
		$this->assertIsArray( $hello_entry );
		$this->assertArrayHasKey( 'comments', $hello_entry );
		$this->assertContains( 'translators: Greeting shown on welcome panel.', $hello_entry['comments'] );

		unlink( $main_file );
		rmdir( $plugin_dir );
		rmdir( $plugins_root );
	}

	/**
	 * Extracting from a root-level plugin file must not scan sibling plugins.
	 *
	 * @return void
	 */
	public function test_extract_from_root_level_plugin_file_does_not_scan_plugins_root() {
		$plugins_root       = sys_get_temp_dir() . '/i18nly-extractor-root-' . uniqid( '', true );
		$root_plugin_file   = $plugins_root . '/hello.php';
		$sibling_plugin_dir = $plugins_root . '/akismet';
		$sibling_file       = $sibling_plugin_dir . '/akismet.php';

		mkdir( $plugins_root, 0755, true );
		mkdir( $sibling_plugin_dir, 0755, true );

		file_put_contents(
			$root_plugin_file,
			"<?php\n__( 'Hello Dolly only', 'hello' );\n"
		);

		file_put_contents(
			$sibling_file,
			"<?php\n__( 'Akismet only', 'akismet' );\n"
		);

		$extractor = new I18nly_Pot_Source_Entry_Extractor( $plugins_root );
		$entries   = $extractor->extract_from_source_slug( 'hello.php' );

		$this->assertCount( 1, $entries );
		$this->assertSame( 'Hello Dolly only', $entries[0]['original'] );

		unlink( $sibling_file );
		rmdir( $sibling_plugin_dir );
		unlink( $root_plugin_file );
		rmdir( $plugins_root );
	}
}

// phpcs:enable
