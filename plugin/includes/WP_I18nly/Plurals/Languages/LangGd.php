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

final class LangGd implements LanguageSpecProvider
{
    /**
     * @return array<string, mixed>
     */
    public static function get_spec()
    {
        $spec = array(
            'nplurals'          => 4,
            'plural_expression' => '((n == 1 || n == 11) ? 0 : ((n == 2 || n == 12) ? 1 : ((n >= 3 && n <= 10 || n >= 13 && n <= 19) ? 2 : 3)))',
            'forms'             => array(),
        );

        $spec['forms'][] = array(
            /* translators: Short label identifying one plural form input in the translation editor. */
            'label'   => __('a', 'i18nly'),
            /* translators: Tooltip explaining when this plural form input should be used in the translation editor. */
            'tooltip' => __('1, 11', 'i18nly'),
            'examples' => array( 1, 11 ),
        );
        $spec['forms'][] = array(
            /* translators: Short label identifying one plural form input in the translation editor. */
            'label'   => __('b', 'i18nly'),
            /* translators: Tooltip explaining when this plural form input should be used in the translation editor. */
            'tooltip' => __('2, 12', 'i18nly'),
            'examples' => array( 2 ),
        );
        $spec['forms'][] = array(
            /* translators: Short label identifying one plural form input in the translation editor. */
            'label'   => __('c', 'i18nly'),
            /* translators: Tooltip explaining when this plural form input should be used in the translation editor. */
            'tooltip' => __('3, 4, 5, 6, ...', 'i18nly'),
            'examples' => array( 3, 4, 7, 8 ),
        );
        $spec['forms'][] = array(
            /* translators: Short label identifying one plural form input in the translation editor. */
            'label'   => __('d', 'i18nly'),
            /* translators: Tooltip explaining when this plural form input should be used in the translation editor. */
            'tooltip' => __('other', 'i18nly'),
            'examples' => array( 0, 20, 21, 100 ),
        );

        return $spec;
    }
}
