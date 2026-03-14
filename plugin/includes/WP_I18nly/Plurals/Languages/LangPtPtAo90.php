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

final class LangPtPtAo90 implements LanguageSpecProvider {
	/**
	 * @return array<string, mixed>
	 */
	public static function get_spec() {
		$spec = array (
  'nplurals' => 2,
  'plural_expression' => '(n != 1)',
  'forms' => 
  array (
  ),
);

		$spec['forms'][] = array(
			/* translators: Short label identifying one plural form input in the translation editor. */
			'label'   => __( '1', 'i18nly' ),
			/* translators: Tooltip explaining when this plural form input should be used in the translation editor. */
			'tooltip' => __( 'One', 'i18nly' ),
		);
		$spec['forms'][] = array(
			/* translators: Short label identifying one plural form input in the translation editor. */
			'label'   => __( 'n', 'i18nly' ),
			/* translators: Tooltip explaining when this plural form input should be used in the translation editor. */
			'tooltip' => __( 'Other than one', 'i18nly' ),
		);

		return $spec;
	}
}
