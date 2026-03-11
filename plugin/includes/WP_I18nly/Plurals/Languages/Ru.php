<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Auto-generated file. Do not edit manually.
 *
 * @package I18nly
 */

namespace WP_I18nly\Plurals\Languages;

use WP_I18nly\Plurals\LanguageSpecProvider;

defined( 'ABSPATH' ) || exit;

final class Ru implements LanguageSpecProvider {
	/**
	 * @return array<string, mixed>
	 */
	public static function get_spec() {
		return array (
  'nplurals' => 3,
  'plural_expression' => '(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : 2)',
  'forms' => 
  array (
    'a' => 'One',
    'b' => 'Few',
    'c' => 'Many',
  ),
);
	}
}
