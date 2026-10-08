<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation installer tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\Export\TranslationInstaller;

require_once __DIR__ . '/support/class-i18nly-test-memory-filesystem.php';

/**
 * Tests the writing of the files into the languages directory.
 */
class TranslationInstallerTest extends TestCase {
	/**
	 * Filesystem double.
	 *
	 * @var I18nly_Test_Memory_Filesystem
	 */
	private $fs;

	/**
	 * Creates the filesystem double.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->fs = new I18nly_Test_Memory_Filesystem();
	}

	/**
	 * Creates the directory and writes both files.
	 *
	 * @return void
	 */
	public function test_installs_both_files_and_creates_the_directory() {
		$result = ( new TranslationInstaller( $this->fs, '/lang/plugins' ) )->install( 'sample', 'fr_FR', 'MO', 'PO' );

		$this->assertSame( TranslationInstaller::INSTALLED, $result );
		$this->assertTrue( $this->fs->is_dir( '/lang/plugins' ) );
		$this->assertSame( 'MO', $this->fs->files['/lang/plugins/sample-fr_FR.mo'] );
		$this->assertSame( 'PO', $this->fs->files['/lang/plugins/sample-fr_FR.po'] );
	}

	/**
	 * A file written by I18nly is replaced without backup.
	 *
	 * @return void
	 */
	public function test_replaces_a_file_written_by_the_plugin_without_backup() {
		$this->fs->directories['/lang/plugins']             = true;
		$this->fs->files['/lang/plugins/sample-fr_FR.mo'] = "X-Generator: I18nly 0.1.1\nold";

		( new TranslationInstaller( $this->fs, '/lang/plugins' ) )->install( 'sample', 'fr_FR', 'NEW', 'PO' );

		$this->assertSame( 'NEW', $this->fs->files['/lang/plugins/sample-fr_FR.mo'] );
		$this->assertArrayNotHasKey( '/lang/plugins/sample-fr_FR.mo.i18nly-backup', $this->fs->files );
	}

	/**
	 * A foreign file is kept as a backup, and the first backup is never overwritten.
	 *
	 * @return void
	 */
	public function test_keeps_a_foreign_file_as_backup_once() {
		$this->fs->directories['/lang/plugins']             = true;
		$this->fs->files['/lang/plugins/sample-fr_FR.mo'] = 'language pack';

		$installer = new TranslationInstaller( $this->fs, '/lang/plugins' );
		$installer->install( 'sample', 'fr_FR', 'FIRST', 'PO' );

		$this->assertSame( 'language pack', $this->fs->files['/lang/plugins/sample-fr_FR.mo.i18nly-backup'] );

		$this->fs->files['/lang/plugins/sample-fr_FR.mo'] = 'another pack';
		$installer->install( 'sample', 'fr_FR', 'SECOND', 'PO' );

		$this->assertSame( 'language pack', $this->fs->files['/lang/plugins/sample-fr_FR.mo.i18nly-backup'] );
		$this->assertSame( 'SECOND', $this->fs->files['/lang/plugins/sample-fr_FR.mo'] );
	}

	/**
	 * A failing write is reported.
	 *
	 * @return void
	 */
	public function test_reports_write_errors() {
		$this->fs->fail_writes = true;

		$result = ( new TranslationInstaller( $this->fs, '/lang/plugins' ) )->install( 'sample', 'fr_FR', 'MO', 'PO' );

		$this->assertSame( TranslationInstaller::WRITE_ERROR, $result );
	}
}
