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
		if ( ! self::is_supported_format( $format ) ) {
			throw new \InvalidArgumentException( 'Unsupported export format.' );
		}

		if ( 'po' === $format ) {
			return ( new PoGenerator() )->generateString( $translations );
		}

		// The headers carry Plural-Forms, which WordPress needs to pick the right plural form.
		return ( new MoGenerator() )->includeHeaders( true )->generateString( $translations );
	}
}
