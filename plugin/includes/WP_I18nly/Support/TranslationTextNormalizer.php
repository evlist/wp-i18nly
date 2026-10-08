<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Normalization of translatable and translated texts coming from requests.
 *
 * @package I18nly
 */

namespace WP_I18nly\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Cleans texts without altering their content.
 *
 * Translations and source texts are data, not markup: HTML, placeholders, line breaks, tabs and
 * spaces must reach the storage and the translation provider unchanged. Functions like
 * sanitize_text_field() strip tags and collapse whitespace, so they must not be used on these texts.
 * Escaping is done where the texts are output.
 *
 * Only what can never be valid text is removed: invalid UTF-8 and control characters other than tab
 * and line feed. Line breaks are normalized to line feeds, as in PO files.
 */
class TranslationTextNormalizer {
	/**
	 * Maximum size of a posted JSON document, in bytes: far above a real translation, below what could exhaust memory.
	 */
	public const MAX_JSON_BYTES = 10485760;

	/**
	 * Normalizes one text.
	 *
	 * @param mixed $value Raw (already unslashed) value.
	 * @return string
	 */
	public static function normalize( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$text = wp_check_invalid_utf8( (string) $value, true );
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );

		return (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );
	}

	/**
	 * Decodes a JSON object or list posted by the editor.
	 *
	 * @param mixed $raw Raw (already unslashed) JSON string.
	 * @return array<int|string, mixed>|null Decoded array, or null when the value is not a JSON array/object.
	 */
	public static function decode_json_array( $raw ) {
		if ( ! is_string( $raw ) || '' === $raw || strlen( $raw ) > self::MAX_JSON_BYTES ) {
			return null;
		}

		$decoded = json_decode( $raw, true, 64 );

		return is_array( $decoded ) ? $decoded : null;
	}
}
