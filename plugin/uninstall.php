<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * @package I18nly
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/third-party/vendor/autoload.php';

( new \WP_I18nly\Support\PluginUninstaller() )->run();
