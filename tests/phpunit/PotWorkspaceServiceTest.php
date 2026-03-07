<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * POT workspace service tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

// phpcs:disable WordPress.WP.AlternativeFunctions

/**
 * Tests temporary POT orchestration service.
 */
class PotWorkspaceServiceTest extends TestCase {
	/**
	 * Generates POT in translation temporary workspace.
	 *
	 * @return void
	 */
	public function test_generate_temporary_pot_creates_file_in_workspace() {
		$base_dir = sys_get_temp_dir() . '/i18nly-workspace-' . uniqid( '', true );

		$storage = new \WP_I18nly\TemporaryStorage( $base_dir );
		$service = new \WP_I18nly\PotWorkspaceService( $storage, new I18nly_Pot_Generator() );

		$pot_path = $service->generate_temporary_pot(
			12,
			'i18nly',
			array(
				array(
					'original' => 'Temporary string',
				),
			)
		);

		$this->assertSame( $base_dir . '/translation-12/messages.pot', $pot_path );
		$this->assertFileExists( $pot_path );

		$content = file_get_contents( $pot_path );
		$this->assertIsString( $content );
		$this->assertStringContainsString( 'msgid "Temporary string"', $content );

		$storage->cleanup_translation_workspace( 12 );
		$this->assertDirectoryDoesNotExist( $base_dir . '/translation-12' );

		rmdir( $base_dir );
	}
}

// phpcs:enable
