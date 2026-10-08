<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Plugin source file resolution tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// phpcs:disable WordPress.WP.AlternativeFunctions

/**
 * Tests that a source slug cannot lead outside the plugins directory.
 */
class PluginSourceFilesTest extends TestCase {
	/**
	 * Temporary base directory.
	 *
	 * @var string
	 */
	private $base;

	/**
	 * Plugins root used by the tests.
	 *
	 * @var string
	 */
	private $root;

	/**
	 * Creates a plugins root with one plugin and a secret file next to it.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->base = sys_get_temp_dir() . '/i18nly-files-' . uniqid( '', true );
		$this->root = $this->base . '/plugins';

		mkdir( $this->root . '/sample', 0755, true );
		file_put_contents( $this->root . '/sample/sample.php', "<?php\n" );
		file_put_contents( $this->base . '/secret.php', "<?php\n" );
	}

	/**
	 * Removes the temporary files.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( array( $this->root . '/sample/link.php', $this->root . '/sample/sample.php', $this->base . '/secret.php' ) as $file ) {
			if ( is_link( $file ) || is_file( $file ) ) {
				unlink( $file );
			}
		}

		foreach ( array( $this->root . '/sample', $this->root, $this->base ) as $directory ) {
			if ( is_dir( $directory ) ) {
				rmdir( $directory );
			}
		}
	}

	/**
	 * A regular slug is resolved.
	 *
	 * @return void
	 */
	public function test_resolves_a_plugin_file_inside_the_root() {
		$files = new \WP_I18nly\Build\PluginSourceFiles( $this->root );

		$this->assertSame( $this->root . '/sample/sample.php', $files->resolve_main_file( 'sample/sample.php' ) );
	}

	/**
	 * Slugs trying to leave the root are rejected.
	 *
	 * @param string $slug Slug.
	 * @return void
	 */
	#[DataProvider( 'unsafe_slugs' )]
	public function test_rejects_unsafe_slugs( $slug ) {
		$files = new \WP_I18nly\Build\PluginSourceFiles( $this->root );

		$this->assertSame( '', $files->resolve_main_file( $slug ) );
	}

	/**
	 * Unsafe slugs.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function unsafe_slugs() {
		return array(
			'parent segment'      => array( '../secret.php' ),
			'nested parent'       => array( 'sample/../../secret.php' ),
			'backslash parent'    => array( 'sample\\..\\..\\secret.php' ),
			'dot segment'         => array( 'sample/./sample.php' ),
			'empty segment'       => array( 'sample//sample.php' ),
			'nul byte'            => array( "sample/sample.php\0.txt" ),
			'drive or wrapper'    => array( 'php://filter/resource=sample.php' ),
			'empty'               => array( '' ),
			'only separators'     => array( '//' ),
		);
	}

	/**
	 * A plugin folder that is a symbolic link to a working copy elsewhere is followed (development setups).
	 *
	 * @return void
	 */
	public function test_follows_symbolic_links_inside_the_plugins_root() {
		if ( ! function_exists( 'symlink' ) || ! @symlink( $this->base . '/secret.php', $this->root . '/sample/link.php' ) ) {
			$this->markTestSkipped( 'Symbolic links are not available.' );
		}

		$files = new \WP_I18nly\Build\PluginSourceFiles( $this->root );

		$this->assertSame( $this->root . '/sample/link.php', $files->resolve_main_file( 'sample/link.php' ) );
	}
}
