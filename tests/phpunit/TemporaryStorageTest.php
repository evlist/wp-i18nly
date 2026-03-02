<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Temporary storage tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

// phpcs:disable WordPress.WP.AlternativeFunctions

/**
 * Tests temporary storage for unpublished translation artifacts.
 */
class TemporaryStorageTest extends TestCase {
	/**
	 * Returns predictable workspace and POT paths.
	 *
	 * @return void
	 */
	public function test_path_helpers_return_expected_paths() {
		$storage = new I18nly_Temporary_Storage( '/tmp/i18nly-tests' );

		$this->assertSame( '/tmp/i18nly-tests', $storage->get_base_directory() );
		$this->assertSame( '/tmp/i18nly-tests/translation-42', $storage->get_translation_directory( 42 ) );
		$this->assertSame( '/tmp/i18nly-tests/translation-42/messages.pot', $storage->get_pot_file_path( 42 ) );
	}

	/**
	 * Creates temporary workspace directories when missing.
	 *
	 * @return void
	 */
	public function test_ensure_translation_workspace_creates_directories() {
		$base_dir = sys_get_temp_dir() . '/i18nly-storage-' . uniqid( '', true );
		$storage  = new I18nly_Temporary_Storage( $base_dir );

		$workspace = $storage->ensure_translation_workspace( 99 );

		$this->assertSame( $base_dir . '/translation-99', $workspace );
		$this->assertDirectoryExists( $base_dir );
		$this->assertDirectoryExists( $workspace );

		rmdir( $workspace );
		rmdir( $base_dir );
	}

	/**
	 * Removes a workspace recursively.
	 *
	 * @return void
	 */
	public function test_cleanup_translation_workspace_removes_directory_tree() {
		$base_dir        = sys_get_temp_dir() . '/i18nly-storage-' . uniqid( '', true );
		$translation_dir = $base_dir . '/translation-5';
		$nested_dir      = $translation_dir . '/nested';
		$nested_file     = $nested_dir . '/strings.txt';
		$storage         = new I18nly_Temporary_Storage( $base_dir );

		mkdir( $nested_dir, 0755, true );
		file_put_contents( $nested_file, 'temporary data' );

		$this->assertDirectoryExists( $translation_dir );

		$storage->cleanup_translation_workspace( 5 );

		$this->assertDirectoryDoesNotExist( $translation_dir );

		rmdir( $base_dir );
	}

	/**
	 * Uses system temp fallback when uploads API is unavailable.
	 *
	 * @return void
	 */
	public function test_default_base_directory_falls_back_to_system_temp() {
		$storage = new I18nly_Temporary_Storage();

		$this->assertStringEndsWith( '/i18nly/staging', $storage->get_base_directory() );
	}
}

// phpcs:enable
