<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Minimal PHPUnit stubs for Intelephense.
 *
 * @package I18nly
 */

declare( strict_types=1 );

namespace PHPUnit\Framework;

/**
 * Minimal TestCase surface used in this repository tests.
 */
abstract class TestCase {
	/**
	 * @param mixed  $actual Actual value.
	 * @param string $message Failure message.
	 * @return void
	 */
	public function assertIsString( $actual, string $message = '' ): void {}

	/**
	 * @param string $needle Needle.
	 * @param string $haystack Haystack.
	 * @param string $message Failure message.
	 * @return void
	 */
	public function assertStringContainsString( string $needle, string $haystack, string $message = '' ): void {}

	/**
	 * @param string $pattern Pattern.
	 * @param string $string String to match.
	 * @param string $message Failure message.
	 * @return void
	 */
	public function assertMatchesRegularExpression( string $pattern, string $string, string $message = '' ): void {}

	/**
	 * @param mixed  $expected Expected.
	 * @param mixed  $actual Actual.
	 * @param string $message Failure message.
	 * @return void
	 */
	public function assertSame( $expected, $actual, string $message = '' ): void {}
}
