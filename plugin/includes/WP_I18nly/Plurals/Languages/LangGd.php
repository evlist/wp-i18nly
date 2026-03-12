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

final class LangGd implements LanguageSpecProvider {
	/**
	 * @return array<string, mixed>
	 */
	public static function get_spec() {
		$spec = array (
  'nplurals' => 4,
  'plural_expression' => '((n == 1 || n == 11) ? 0 : ((n == 2 || n == 12) ? 1 : ((n >= 3 && n <= 10 || n >= 13 && n <= 19) ? 2 : 3)))',
  'forms' => 
  array (
  ),
);

		$spec['forms'][] = array(
			'label'   => __( 'a', 'i18nly' ),
			'tooltip' => __( '1, 11', 'i18nly' ),
		);
		$spec['forms'][] = array(
			'label'   => __( 'b', 'i18nly' ),
			'tooltip' => __( '2, 12', 'i18nly' ),
		);
		$spec['forms'][] = array(
			'label'   => __( 'c', 'i18nly' ),
			'tooltip' => __( '3, 4, 5, 6, ...', 'i18nly' ),
		);
		$spec['forms'][] = array(
			'label'   => __( 'd', 'i18nly' ),
			'tooltip' => __( 'other', 'i18nly' ),
		);

		return $spec;
	}
}
