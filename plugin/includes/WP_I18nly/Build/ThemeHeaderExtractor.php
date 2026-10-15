<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Extracts the translatable headers of a theme style sheet.
 *
 * @package I18nly
 */

namespace WP_I18nly\Build;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress translates some headers of style.css with the text domain of the theme (WP_Theme::translate_header()).
 */
class ThemeHeaderExtractor {
	/**
	 * Translated headers, with the comment WordPress tools give them.
	 *
	 * @var array<string, string>
	 */
	const HEADERS = array(
		'Theme Name:'  => 'Theme Name of the theme',
		'Description:' => 'Description of the theme',
		'Author:'      => 'Author of the theme',
	);

	/**
	 * Extracts the entries from the contents of a style sheet.
	 *
	 * @param string $css_contents Style sheet contents.
	 * @param string $relative_path Relative file path.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_from_file_contents( $css_contents, $relative_path ) {
		// The headers are in the first comment, WordPress reads the first 8 KB only.
		$head    = substr( (string) $css_contents, 0, 8192 );
		$entries = array();

		foreach ( self::HEADERS as $label => $comment ) {
			if ( ! preg_match( '/^[ \t\/*#@]*' . preg_quote( $label, '/' ) . '(.*)$/mi', $head, $matches ) ) {
				continue;
			}

			$value = trim( preg_replace( '/\s*(?:\*\/|-->).*$/', '', $matches[1] ) );

			if ( '' === $value ) {
				continue;
			}

			$entries[] = array(
				'original'   => $value,
				'comments'   => array( $comment ),
				'references' => array(
					array(
						'file' => $relative_path,
						'line' => substr_count( substr( $head, 0, (int) strpos( $head, $matches[0] ) ), "\n" ) + 1,
					),
				),
			);
		}

		return $entries;
	}
}
