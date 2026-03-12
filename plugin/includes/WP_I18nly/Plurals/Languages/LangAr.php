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

final class LangAr implements LanguageSpecProvider {
	/**
	 * @return array<string, mixed>
	 */
	public static function get_spec() {
		$spec = array (
  'nplurals' => 6,
  'plural_expression' => '((n == 0) ? 0 : ((n == 1) ? 1 : ((n == 2) ? 2 : ((n % 100 >= 3 && n % 100 <= 10) ? 3 : ((n % 100 >= 11 && n % 100 <= 99) ? 4 : 5)))))',
  'forms' => 
  array (
  ),
);

		$spec['forms'][] = array(
			'label'   => __( 'a', 'i18nly' ),
			'tooltip' => __( '0', 'i18nly' ),
		);
		$spec['forms'][] = array(
			'label'   => __( 'b', 'i18nly' ),
			'tooltip' => __( '1', 'i18nly' ),
		);
		$spec['forms'][] = array(
			'label'   => __( 'c', 'i18nly' ),
			'tooltip' => __( '2', 'i18nly' ),
		);
		$spec['forms'][] = array(
			'label'   => __( 'd', 'i18nly' ),
			'tooltip' => __( '3, 4, 5, 6, ...', 'i18nly' ),
		);
		$spec['forms'][] = array(
			'label'   => __( 'e', 'i18nly' ),
			'tooltip' => __( '11, 12, 13, 14, ...', 'i18nly' ),
		);
		$spec['forms'][] = array(
			'label'   => __( 'f', 'i18nly' ),
			'tooltip' => __( 'other', 'i18nly' ),
		);

		return $spec;
	}
}
