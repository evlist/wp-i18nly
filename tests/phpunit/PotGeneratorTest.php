<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * POT generator tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

// phpcs:disable WordPress.WP.AlternativeFunctions

/**
 * Tests POT generation.
 */
class PotGeneratorTest extends TestCase {
	/**
	 * Writes a POT file with expected headers and message blocks.
	 *
	 * @return void
	 */
	public function test_generate_writes_expected_pot_file() {
		$generator = new \WP_I18nly\PotGenerator();

		$temp_file = sys_get_temp_dir() . '/i18nly-pot-' . uniqid( '', true ) . '.pot';

		$entries = array(
			array(
				'original'   => 'Hello world',
				'comments'   => array( 'translators: Greeting message.' ),
				'references' => array(
					array(
						'file' => 'plugin/includes/example.php',
						'line' => 10,
					),
				),
			),
			array(
				'context'  => 'button',
				'original' => 'Save',
			),
		);

		$generator->generate( $temp_file, 'i18nly', $entries );

		$this->assertFileExists( $temp_file );

		$content = file_get_contents( $temp_file );
		$this->assertIsString( $content );
		$this->assertStringContainsString( '"Project-Id-Version: i18nly\\n"', $content );
		$this->assertStringContainsString( '"X-Domain: i18nly\\n"', $content );
		$this->assertStringContainsString( '#. translators: Greeting message.', $content );
		$this->assertStringContainsString( '#: plugin/includes/example.php:10', $content );
		$this->assertStringContainsString( 'msgid "Hello world"', $content );
		$this->assertStringContainsString( 'msgctxt "button"', $content );
		$this->assertStringContainsString( 'msgid "Save"', $content );

		unlink( $temp_file );
	}

	/**
	 * Creates missing destination directories.
	 *
	 * @return void
	 */
	public function test_generate_creates_missing_destination_directory() {
		$generator = new \WP_I18nly\PotGenerator();

		$base_dir    = sys_get_temp_dir() . '/i18nly-pot-dir-' . uniqid( '', true );
		$nested_dir  = $base_dir . '/nested';
		$destination = $nested_dir . '/messages.pot';

		$generator->generate(
			$destination,
			'i18nly',
			array(
				array(
					'original' => 'Generated string',
				),
			)
		);

		$this->assertDirectoryExists( $nested_dir );
		$this->assertFileExists( $destination );

		unlink( $destination );
		rmdir( $nested_dir );
		rmdir( $base_dir );
	}
}

// phpcs:enable
