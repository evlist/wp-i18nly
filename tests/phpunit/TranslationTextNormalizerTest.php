<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation text normalizer tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\Support\TranslationTextNormalizer;

/**
 * Tests that texts are cleaned without being altered.
 */
class TranslationTextNormalizerTest extends TestCase {
	/**
	 * Markup, spaces, tabs and percent signs are kept.
	 *
	 * @return void
	 */
	public function test_keeps_markup_and_whitespace() {
		$text = "<b>Gras</b>  et\ttab\n100% %1\$s %ab ";

		$this->assertSame( $text, TranslationTextNormalizer::normalize( $text ) );
	}

	/**
	 * Line breaks are normalized to line feeds.
	 *
	 * @return void
	 */
	public function test_normalizes_line_breaks() {
		$this->assertSame( "a\nb\nc", TranslationTextNormalizer::normalize( "a\r\nb\rc" ) );
	}

	/**
	 * Control characters and invalid UTF-8 are removed.
	 *
	 * @return void
	 */
	public function test_removes_control_characters_and_invalid_utf8() {
		$this->assertSame( 'ab', TranslationTextNormalizer::normalize( "a\0\x07b" ) );
		$this->assertSame( 'ab', TranslationTextNormalizer::normalize( "a\xFFb" ) );
	}

	/**
	 * Non scalar values give an empty text.
	 *
	 * @return void
	 */
	public function test_non_scalar_values_are_empty() {
		$this->assertSame( '', TranslationTextNormalizer::normalize( array( 'x' ) ) );
		$this->assertSame( '', TranslationTextNormalizer::normalize( null ) );
	}

	/**
	 * JSON decoding returns arrays only.
	 *
	 * @return void
	 */
	public function test_decode_json_array() {
		$this->assertSame( array( 'a' => 1 ), TranslationTextNormalizer::decode_json_array( '{"a":1}' ) );
		$this->assertNull( TranslationTextNormalizer::decode_json_array( '"text"' ) );
		$this->assertNull( TranslationTextNormalizer::decode_json_array( '{bad' ) );
		$this->assertNull( TranslationTextNormalizer::decode_json_array( '' ) );
		$this->assertNull( TranslationTextNormalizer::decode_json_array( array() ) );
		$this->assertNull( TranslationTextNormalizer::decode_json_array( '["' . str_repeat( 'a', TranslationTextNormalizer::MAX_JSON_BYTES ) . '"]' ) );
	}
}
