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

final class Ar implements LanguageSpecProvider {
	/**
	 * @return array<string, mixed>
	 */
	public static function get_spec() {
		return array (
  'nplurals' => 6,
  'plural_expression' => '(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 && n%100<=99 ? 4 : 5)',
  'forms' => 
  array (
    'a' => 'Zero',
    'b' => 'One',
    'c' => 'Two',
    'd' => 'Few',
    'e' => 'Many',
    'f' => 'Other',
  ),
);
	}
}
