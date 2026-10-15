<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Tests for the translatable headers of a theme.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\Build\ThemeHeaderExtractor;

/**
 * Tests for ThemeHeaderExtractor.
 */
class ThemeHeaderExtractorTest extends TestCase {
	/**
	 * The name, the description and the author are translatable, the other headers are not.
	 *
	 * @return void
	 */
	public function test_extracts_the_translated_headers() {
		$css = "/*\nTheme Name: Sample Theme\nTheme URI: https://example.com\nAuthor: Jane Doe\nDescription: A very sample theme.\nVersion: 1.0\nText Domain: sample\n*/\nbody { color: red; }";

		$entries = ( new ThemeHeaderExtractor() )->extract_from_file_contents( $css, 'style.css' );

		$this->assertSame( array( 'Sample Theme', 'A very sample theme.', 'Jane Doe' ), array_column( $entries, 'original' ) );
		$this->assertSame( array( 'Theme Name of the theme' ), $entries[0]['comments'] );
		$this->assertSame( 'style.css', $entries[0]['references'][0]['file'] );
		$this->assertSame( 2, $entries[0]['references'][0]['line'] );
	}

	/**
	 * A style sheet without headers gives nothing.
	 *
	 * @return void
	 */
	public function test_style_sheet_without_headers() {
		$this->assertSame( array(), ( new ThemeHeaderExtractor() )->extract_from_file_contents( 'body { color: red; }', 'style.css' ) );
	}
}
