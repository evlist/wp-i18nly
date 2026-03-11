<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Default plural language spec.
 *
 * @package I18nly
 */

namespace WP_I18nly\Plurals;

defined( 'ABSPATH' ) || exit;

/**
 * Provides fallback plural spec.
 */
final class LanguageSpecDefault implements LanguageSpecProvider {
	/**
	 * @return array<string, mixed>
	 */
	public static function get_spec() {
		return array(
			'nplurals'          => 2,
			'plural_expression' => '(n != 1)',
			'forms'             => array(
				'1' => 'One',
				'n' => 'Other than one',
			),
		);
	}
}
