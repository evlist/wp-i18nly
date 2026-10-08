<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Tests for the POT file of the installed version of a plugin.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\Export\SourcePotBuilder;

/**
 * Tests for SourcePotBuilder.
 */
class SourcePotBuilderTest extends TestCase {
	/**
	 * The POT is generated from the plugin as installed, and named after the text domain.
	 *
	 * @return void
	 */
	public function test_pot_comes_from_the_installed_sources() {
		$root   = sys_get_temp_dir() . '/i18nly-pot-' . uniqid( '', true );
		$folder = $root . '/sample';

		mkdir( $folder, 0777, true ); // phpcs:ignore
		file_put_contents( $folder . '/sample.php', "<?php\n/**\n * Plugin Name: Sample\n * Text Domain: sample\n */\n__( 'Brand new string', 'sample' );\n" ); // phpcs:ignore

		$files = ( new SourcePotBuilder( $root ) )->build( 'sample/sample.php' );

		unlink( $folder . '/sample.php' ); // phpcs:ignore
		rmdir( $folder ); // phpcs:ignore
		rmdir( $root ); // phpcs:ignore

		$this->assertSame( array( 'sample.pot' ), array_keys( $files ) );
		$this->assertStringContainsString( 'msgid "Brand new string"', $files['sample.pot'] );
		$this->assertStringContainsString( 'X-Generator: I18nly', $files['sample.pot'] );
	}

	/**
	 * A plugin that cannot be found gives no file.
	 *
	 * @return void
	 */
	public function test_unknown_plugin_gives_nothing() {
		$this->assertSame( array(), ( new SourcePotBuilder( sys_get_temp_dir() . '/i18nly-nothing-here' ) )->build( 'nothing/nothing.php' ) );
	}
}
