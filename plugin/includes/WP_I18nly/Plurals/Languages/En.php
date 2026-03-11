<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * English plural language spec.
 *
 * Auto-generated file. Do not edit manually.
 *
 * @package I18nly
 */

namespace WP_I18nly\Plurals\Languages;

use WP_I18nly\Plurals\LanguageSpecProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Provides English plural forms spec.
 */
final class En implements LanguageSpecProvider {
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
