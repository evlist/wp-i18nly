<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * French plural language spec.
 *
 * @package I18nly
 */

namespace WP_I18nly\Plurals\Languages;

use WP_I18nly\Plurals\LanguageSpecProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Provides French plural forms spec.
 */
final class Fr implements LanguageSpecProvider {
	/**
	 * @return array<string, mixed>
	 */
	public static function get_spec() {
		return array(
			'nplurals'          => 2,
			'categories'        => array( 'one', 'other' ),
			'plural_expression' => '(n > 1)',
			'forms'             => array(
				'a' => 'Zero or one',
				'b' => 'More than one',
			),
		);
	}
}
