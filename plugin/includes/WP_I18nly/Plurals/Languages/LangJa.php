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

final class LangJa implements LanguageSpecProvider {
	/**
	 * @return array<string, mixed>
	 */
	public static function get_spec() {
		$spec = array (
  'nplurals' => 1,
  'plural_expression' => '(0)',
  'forms' => 
  array (
  ),
);

		$spec['forms'][] = array(
			'label'   => __( '*', 'i18nly' ),
			'tooltip' => __( 'Any number', 'i18nly' ),
		);

		return $spec;
	}
}
