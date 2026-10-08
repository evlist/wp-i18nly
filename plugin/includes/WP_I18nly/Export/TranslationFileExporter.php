<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Generates translation files.
 *
 * @package I18nly
 */

namespace WP_I18nly\Export;

use Gettext\Generator\MoGenerator;
use Gettext\Generator\PoGenerator;

defined( 'ABSPATH' ) || exit;

/**
 * Produces the PO and MO files of a translation.
 */
class TranslationFileExporter {
	/**
	 * Supported formats with their file extension and media type.
	 *
	 * @var array<string, array{extension: string, mime: string}>
	 */
	private const FORMATS = array(
		'po' => array(
			'extension' => 'po',
			'mime'      => 'text/x-gettext-translation',
		),
		'mo' => array(
			'extension' => 'mo',
			'mime'      => 'application/x-gettext-translation',
		),
		// Every generated file (MO, PO and the JSON files of the scripts), packed into one archive.
		'bundle' => array(
			'extension' => 'zip',
			'mime'      => 'application/zip',
		),
	);

	/**
	 * Tells whether a format is supported.
	 *
	 * @param string $format Format.
	 * @return bool
	 */
	public static function is_supported_format( $format ) {
		return isset( self::FORMATS[ (string) $format ] );
	}

	/**
	 * Returns the file name WordPress expects for a plugin translation: {text-domain}-{locale}.{extension}.
	 *
	 * @param string $text_domain Text domain.
	 * @param string $locale Locale.
	 * @param string $format Format.
	 * @return string
	 */
	public static function get_file_name( $text_domain, $locale, $format ) {
		return sanitize_file_name( $text_domain . '-' . $locale . '.' . self::FORMATS[ $format ]['extension'] );
	}

	/**
	 * Returns the media type of a format.
	 *
	 * @param string $format Format.
	 * @return string
	 */
	public static function get_mime_type( $format ) {
		return self::FORMATS[ $format ]['mime'];
	}

	/**
	 * Generates the file contents.
	 *
	 * @param \Gettext\Translations $translations Catalog.
	 * @param string                $format Format.
	 * @return string
	 * @throws \InvalidArgumentException When the format is not supported.
	 */
	public function generate( $translations, $format ) {
		if ( ! self::is_supported_format( $format ) || 'bundle' === $format ) {
			throw new \InvalidArgumentException( 'Unsupported export format.' );
		}

		if ( 'po' === $format ) {
			return ( new PoGenerator() )->generateString( $translations );
		}

		// The headers carry Plural-Forms, which WordPress needs to pick the right plural form.
		return ( new MoGenerator() )->includeHeaders( true )->generateString( $translations );
	}

	/**
	 * Tells whether archives can be created on this server.
	 *
	 * @return bool
	 */
	public static function can_create_archives() {
		return class_exists( '\\ZipArchive' );
	}

	/**
	 * Packs files into a ZIP archive.
	 *
	 * @param array<string, string> $files Contents indexed by file name.
	 * @return string|null Archive contents, or null when archives are not available or cannot be created.
	 */
	public function zip( array $files ) {
		if ( ! self::can_create_archives() || empty( $files ) ) {
			return null;
		}

		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$archive_path = wp_tempnam( 'i18nly-export' );
		$archive      = new \ZipArchive();

		if ( ! is_string( $archive_path ) || true !== $archive->open( $archive_path, \ZipArchive::OVERWRITE ) ) {
			return null;
		}

		foreach ( $files as $name => $contents ) {
			$archive->addFromString( (string) $name, (string) $contents );
		}

		$closed = $archive->close();
		// phpcs:disable WordPress.WP.AlternativeFunctions -- reading a temporary file created above.
		$data = $closed ? file_get_contents( $archive_path ) : false;
		// phpcs:enable WordPress.WP.AlternativeFunctions

		wp_delete_file( $archive_path );

		return is_string( $data ) ? $data : null;
	}
}
