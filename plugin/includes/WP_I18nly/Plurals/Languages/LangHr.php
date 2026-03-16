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

defined('ABSPATH') || exit;

final class LangHr implements LanguageSpecProvider
{
    /**
     * @return array<string, mixed>
     */
    public static function get_spec()
    {
        $spec = array(
            'nplurals'          => 3,
            'plural_expression' => '((n % 10 == 1 && n % 100 != 11) ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : 2))',
            'forms'             => array(),
        );

        $spec['forms'][] = array(
            /* translators: Short label identifying one plural form input in the translation editor. */
            'label'   => __('a', 'i18nly'),
            /* translators: Tooltip explaining when this plural form input should be used in the translation editor. */
            'tooltip' => __('1, 21, 31, 41, ...', 'i18nly'),
            'examples' => array( 1, 21 ),
        );
        $spec['forms'][] = array(
            /* translators: Short label identifying one plural form input in the translation editor. */
            'label'   => __('b', 'i18nly'),
            /* translators: Tooltip explaining when this plural form input should be used in the translation editor. */
            'tooltip' => __('2, 3, 4, 22, ...', 'i18nly'),
            'examples' => array( 2, 3, 4 ),
        );
        $spec['forms'][] = array(
            /* translators: Short label identifying one plural form input in the translation editor. */
            'label'   => __('c', 'i18nly'),
            /* translators: Tooltip explaining when this plural form input should be used in the translation editor. */
            'tooltip' => __('other', 'i18nly'),
            'examples' => array( 0, 7, 8, 11, 20, 100 ),
        );

        return $spec;
    }
}
