<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Gettext placeholder detection.
 *
 * @package I18nly
 */

namespace WP_I18nly\Build;

defined( 'ABSPATH' ) || exit;

/**
 * Detects printf-style placeholders in gettext messages.
 */
class GettextPlaceholders {
	/**
	 * Returns whether a message contains sprintf placeholders.
	 *
	 * @param string $message Message text.
	 * @return bool
	 */
	public static function contains_sprintf_placeholder( $message ) {
		$message = (string) $message;

		return 1 === preg_match( '/(?<!%)%(?:[0-9]+\$)?[+-]?(?:0|\'.)?-?[0-9]*(?:\.(?:[ 0]|\'.)?[0-9]+)?[bcdeEfFgGosuxX]/', $message )
			|| 1 === preg_match( '/(?<!%)%(?:[0-9]+\$)?[+-]?(?:0|\'.)?-?[0-9]*(?:\.(?:[ 0]|\'.)?[0-9]+)?[%bcdeEfFgGosuxX]/', $message );
	}
}
