<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Installation of translation files in the WordPress languages directory.
 *
 * @package I18nly
 */

namespace WP_I18nly\Export;

defined( 'ABSPATH' ) || exit;

/**
 * Writes the PO and MO files of a translation into wp-content/languages/plugins/, where WordPress
 * looks for the translations of a plugin before its own languages directory.
 *
 * Files are written through the WordPress filesystem API. A file that was not written by I18nly is not
 * overwritten blindly: it is first renamed with the suffix ".i18nly-backup" (once), so that the
 * operation can be undone.
 */
class TranslationInstaller {
	public const INSTALLED        = 'installed';
	public const FILESYSTEM_ERROR = 'filesystem_error';
	public const WRITE_ERROR      = 'write_error';

	/**
	 * Marker written in the X-Generator header by the exporter.
	 */
	private const GENERATOR_MARKER = 'X-Generator: I18nly';

	/**
	 * Filesystem object (WP_Filesystem_Base compatible), or null to use WordPress's.
	 *
	 * @var object|null
	 */
	private $filesystem;

	/**
	 * Languages directory of the plugins, or an empty string to use WP_LANG_DIR/plugins.
	 *
	 * @var string
	 */
	private $directory;

	/**
	 * Constructor.
	 *
	 * @param object|null $filesystem Optional filesystem object.
	 * @param string      $directory Optional target directory.
	 */
	public function __construct( $filesystem = null, $directory = '' ) {
		$this->filesystem = $filesystem;
		$this->directory  = (string) $directory;
	}

	/**
	 * Installs the files.
	 *
	 * @param string                $text_domain Text domain.
	 * @param string                $locale Locale.
	 * @param string                $mo_contents MO file contents.
	 * @param string                $po_contents PO file contents.
	 * @param array<string, string> $extra_files Other files (the JSON files of the scripts), contents indexed by file name.
	 * @return string One of the class constants.
	 */
	public function install( $text_domain, $locale, $mo_contents, $po_contents, array $extra_files = array() ) {
		$filesystem = $this->get_filesystem();
		$directory  = $this->get_directory();

		if ( null === $filesystem || '' === $directory ) {
			return self::FILESYSTEM_ERROR;
		}

		if ( ! $filesystem->is_dir( $directory ) && ! $filesystem->mkdir( $directory, defined( 'FS_CHMOD_DIR' ) ? FS_CHMOD_DIR : 0755 ) ) {
			return self::FILESYSTEM_ERROR;
		}

		$files = array(
			TranslationFileExporter::get_file_name( $text_domain, $locale, 'mo' ) => $mo_contents,
			TranslationFileExporter::get_file_name( $text_domain, $locale, 'po' ) => $po_contents,
		) + $extra_files;

		foreach ( $files as $name => $contents ) {
			$path = rtrim( $directory, '/\\' ) . '/' . $name;

			$this->back_up_foreign_file( $filesystem, $path );

			if ( ! $filesystem->put_contents( $path, $contents, defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644 ) ) {
				return self::WRITE_ERROR;
			}
		}

		return self::INSTALLED;
	}

	/**
	 * Renames an existing file that was not written by I18nly, unless a backup already exists.
	 *
	 * @param object $filesystem Filesystem.
	 * @param string $path File path.
	 * @return void
	 */
	private function back_up_foreign_file( $filesystem, $path ) {
		if ( ! $filesystem->exists( $path ) ) {
			return;
		}

		$existing = (string) $filesystem->get_contents( $path );
		$backup   = $path . '.i18nly-backup';

		if ( false !== strpos( $existing, self::GENERATOR_MARKER ) || $filesystem->exists( $backup ) ) {
			return;
		}

		$filesystem->move( $path, $backup );
	}

	/**
	 * Returns the target directory.
	 *
	 * @return string
	 */
	private function get_directory() {
		if ( '' !== $this->directory ) {
			return $this->directory;
		}

		return defined( 'WP_LANG_DIR' ) ? rtrim( (string) WP_LANG_DIR, '/\\' ) . '/plugins' : '';
	}

	/**
	 * Returns the filesystem object, initializing the WordPress one when needed.
	 *
	 * @return object|null
	 */
	private function get_filesystem() {
		if ( is_object( $this->filesystem ) ) {
			return $this->filesystem;
		}

		global $wp_filesystem;

		if ( ! is_object( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';

			if ( ! WP_Filesystem() ) {
				return null;
			}
		}

		return is_object( $wp_filesystem ) ? $wp_filesystem : null;
	}
}
