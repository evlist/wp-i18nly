<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace I18nly\Sniffs\Files;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Reports warning/error when PHP file line count exceeds thresholds.
 */
class FileLengthSniff implements Sniff {
	/**
	 * Warning threshold.
	 *
	 * @var int
	 */
	public $lineLimit = 400;

	/**
	 * Error threshold.
	 *
	 * @var int
	 */
	public $absoluteLineLimit = 700;

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
	 * @return int|void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		if ( 0 !== (int) $stackPtr ) {
			return $phpcsFile->numTokens;
		}

		$tokens = $phpcsFile->getTokens();
		if ( empty( $tokens ) ) {
			return $phpcsFile->numTokens;
		}

		$last_token = $tokens[ $phpcsFile->numTokens - 1 ];
		$line_count = isset( $last_token['line'] ) ? (int) $last_token['line'] : 0;

		if ( $line_count > (int) $this->absoluteLineLimit ) {
			$phpcsFile->addError(
				'File has %s lines; maximum is %s lines',
				$stackPtr,
				'TooLong',
				array( $line_count, (int) $this->absoluteLineLimit )
			);

			return $phpcsFile->numTokens;
		}

		if ( $line_count > (int) $this->lineLimit ) {
			$phpcsFile->addWarning(
				'File has %s lines; recommended maximum is %s lines',
				$stackPtr,
				'TooLong',
				array( $line_count, (int) $this->lineLimit )
			);
		}

		return $phpcsFile->numTokens;
	}
}
