<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Generates plugin/languages/i18nly.pot with the extractor of the plugin itself.
 *
 * Usage: php scripts/generate-pot.php
 *
 * It needs no WordPress: the few WordPress functions used by the extractor and the POT generator are
 * those the test suite already stubs (tests/phpunit/bootstrap.php).
 *
 * @package I18nly
 */

$root = dirname( __DIR__ );

require $root . '/tests/phpunit/bootstrap.php';
require $root . '/plugin/third-party/vendor/autoload.php';

$entries   = ( new WP_I18nly\Build\PotSourceEntryExtractor( $root ) )->extract_from_source_slug( 'plugin/i18nly.php' );
$directory = $root . '/plugin/languages';

if ( ! is_dir( $directory ) && ! mkdir( $directory, 0755, true ) ) {
	fwrite( STDERR, "Cannot create $directory\n" );
	exit( 1 );
}

( new WP_I18nly\Build\PotGenerator() )->generate(
	$directory . '/i18nly.pot',
	'i18nly',
	$entries,
	array(
		'Project-Id-Version' => 'I18nly ' . ( preg_match( '/^ \* Version: (\S+)/m', (string) file_get_contents( $root . '/plugin/i18nly.php' ), $matches ) ? $matches[1] : '' ),
	)
);

echo count( $entries ) . " entries written to plugin/languages/i18nly.pot\n";
