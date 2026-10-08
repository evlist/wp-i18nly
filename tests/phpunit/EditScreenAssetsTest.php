<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Edit screen assets tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests the script manifest of the translation edit screen.
 */
class EditScreenAssetsTest extends TestCase {
	/**
	 * Returns the script definitions.
	 *
	 * @return array<int, array{handle: string, src: string, deps: array<int, string>}>
	 */
	private function definitions() {
		return ( new \WP_I18nly\Admin\UI\EditScreenAssets() )->get_script_definitions();
	}

	/**
	 * Ends the list with the entry script.
	 *
	 * @return void
	 */
	public function test_entry_script_is_last_and_keeps_its_handle() {
		$definitions = $this->definitions();
		$entry       = end( $definitions );

		$this->assertSame( 'i18nly-translation-edit', $entry['handle'] );
		$this->assertStringEndsWith( 'assets/js/translation-edit.js', $entry['src'] );
	}

	/**
	 * Lists every script once.
	 *
	 * @return void
	 */
	public function test_handles_and_sources_are_unique() {
		$handles = array_column( $this->definitions(), 'handle' );
		$sources = array_column( $this->definitions(), 'src' );

		$this->assertSame( $handles, array_values( array_unique( $handles ) ) );
		$this->assertSame( $sources, array_values( array_unique( $sources ) ) );
	}

	/**
	 * Orders the scripts so that dependencies come first.
	 *
	 * @return void
	 */
	public function test_dependencies_are_registered_before_their_dependents() {
		$known = array( 'wp-i18n' );

		foreach ( $this->definitions() as $definition ) {
			foreach ( $definition['deps'] as $dependency ) {
				$this->assertContains( $dependency, $known, $definition['handle'] . ' depends on ' . $dependency . ' which is not registered before it.' );
			}

			$known[] = $definition['handle'];
		}
	}

	/**
	 * Only lists files that exist in the plugin.
	 *
	 * @return void
	 */
	public function test_every_script_file_exists() {
		foreach ( $this->definitions() as $definition ) {
			$this->assertFileExists( dirname( __DIR__, 2 ) . '/plugin/' . $definition['src'] );
		}
	}

	/**
	 * Loads every JavaScript file of the editor classes.
	 *
	 * @return void
	 */
	public function test_every_editor_script_file_is_listed() {
		$listed = array_column( $this->definitions(), 'src' );
		$found  = array_merge(
			glob( dirname( __DIR__, 2 ) . '/plugin/assets/js/*.js' ),
			glob( dirname( __DIR__, 2 ) . '/plugin/assets/js/*/*.js' )
		);

		foreach ( $found as $path ) {
			$this->assertContains(
				substr( $path, strlen( dirname( __DIR__, 2 ) . '/plugin/' ) ),
				$listed,
				$path . ' is not part of the edit screen script manifest.'
			);
		}
	}

	/**
	 * Keeps the JavaScript test environment aligned with the PHP manifest.
	 *
	 * @return void
	 */
	public function test_javascript_test_environment_loads_the_same_scripts_in_the_same_order() {
		$helper = implode( '', (array) file( dirname( __DIR__ ) . '/js/helpers/scripts.js' ) );

		preg_match_all( "/'(assets\\/js\\/[^']+\\.js)'/", $helper, $matches );

		$this->assertSame( array_column( $this->definitions(), 'src' ), $matches[1] );
	}
}
