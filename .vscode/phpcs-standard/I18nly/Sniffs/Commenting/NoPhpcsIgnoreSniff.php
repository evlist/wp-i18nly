<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace I18nly\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Forbids PHPCS ignore directives in source files.
 */
class NoPhpcsIgnoreSniff implements Sniff {
	/**
	 * Registers tokens.
	 *
	 * @return array<int, int>
	 */
	public function register() {
		return array( T_OPEN_TAG );
	}

	/**
	 * Processes one token.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int  $stackPtr Current token pointer.
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		if ( 0 !== (int) $stackPtr ) {
			return;
		}

		$file_content = @file_get_contents( $phpcsFile->getFilename() );

		if ( ! is_string( $file_content ) || '' === $file_content ) {
			return;
		}

		$lines = preg_split( '/\r\n|\r|\n/', $file_content );

		if ( false === $lines ) {
			return;
		}

		foreach ( $lines as $line_number => $line_content ) {
			if ( 1 !== preg_match( '/phpcs:ignore(File)?\b/i', (string) $line_content ) ) {
				continue;
			}

			$phpcsFile->addError(
				'Usage of phpcs:ignore is forbidden in this path (found on line %s)',
				$stackPtr,
				'FoundIgnore',
				array( (int) $line_number + 1 )
			);
		}
	}
}
