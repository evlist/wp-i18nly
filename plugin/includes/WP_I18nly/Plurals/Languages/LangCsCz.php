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

final class LangCsCz implements LanguageSpecProvider {
	/**
	 * @return array<string, mixed>
	 */
	public static function get_spec() {
		$spec = array (
  'nplurals' => 3,
  'plural_expression' => '((n == 1) ? 0 : ((n >= 2 && n <= 4) ? 1 : 2))',
  'forms' => 
  array (
  ),
);

		$spec['forms'][] = array(
			/* translators: Short label identifying one plural form input in the translation editor. */
			'label'   => __( 'a', 'i18nly' ),
			/* translators: Tooltip explaining when this plural form input should be used in the translation editor. */
			'tooltip' => __( '1', 'i18nly' ),
		);
		$spec['forms'][] = array(
			/* translators: Short label identifying one plural form input in the translation editor. */
			'label'   => __( 'b', 'i18nly' ),
			/* translators: Tooltip explaining when this plural form input should be used in the translation editor. */
			'tooltip' => __( '2, 3, 4', 'i18nly' ),
		);
		$spec['forms'][] = array(
			/* translators: Short label identifying one plural form input in the translation editor. */
			'label'   => __( 'c', 'i18nly' ),
			/* translators: Tooltip explaining when this plural form input should be used in the translation editor. */
			'tooltip' => __( 'other', 'i18nly' ),
		);

		return $spec;
	}
}
