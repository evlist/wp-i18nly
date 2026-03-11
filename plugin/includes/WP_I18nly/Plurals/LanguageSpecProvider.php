<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Language plural spec provider contract.
 *
 * @package I18nly
 */

namespace WP_I18nly\Plurals;

defined( 'ABSPATH' ) || exit;

/**
 * Provides one plural language spec.
 */
interface LanguageSpecProvider {
	/**
	 * Returns language plural specification.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_spec();
}
