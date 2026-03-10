<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Creates i18nly source tables (schema 0.0.2).
 *
 * Usage:
 *   wp eval-file scripts/create-source-tables.php
 *
 * @package I18nly
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/plugin/includes/WP_I18nly/SourceSchemaManager.php';

$schema_manager = new \WP_I18nly\Storage\SourceSchemaManager();
$schema_manager->maybe_upgrade();

echo "i18nly source schema ensured (version 0.0.2).\n";
