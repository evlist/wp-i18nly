<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

declare(strict_types=1);

use I18nly\Scripts\Plurals\ProjectPluralSpecOverrides;
use I18nly\Scripts\Plurals\SpecContractValidator;

require_once __DIR__ . '/plurals/class-plural-spec-overrides.php';
require_once __DIR__ . '/plurals/class-project-plural-spec-overrides.php';
require_once __DIR__ . '/plurals/class-spec-contract-validator.php';

$options = getopt( '', array( 'input::', 'output::', 'dry-run' ) );

$input_path = isset( $options['input'] ) ? (string) $options['input'] : __DIR__ . '/plurals/cldr-baseline.sample.json';
$output_path = isset( $options['output'] ) ? (string) $options['output'] : __DIR__ . '/plurals/generated/plural-spec-map.php';
$dry_run    = array_key_exists( 'dry-run', $options );

if ( ! is_file( $input_path ) ) {
	fwrite( STDERR, "Input file not found: {$input_path}\n" );
	exit( 1 );
}

$raw_json = file_get_contents( $input_path );
if ( ! is_string( $raw_json ) ) {
	fwrite( STDERR, "Cannot read input file: {$input_path}\n" );
	exit( 1 );
}

$baseline = json_decode( $raw_json, true );
if ( ! is_array( $baseline ) ) {
	fwrite( STDERR, "Invalid JSON input: {$input_path}\n" );
	exit( 1 );
}

$validator = new SpecContractValidator();
$overrides = new ProjectPluralSpecOverrides();
$generated = array();

foreach ( $baseline as $language => $spec ) {
	if ( ! is_string( $language ) || ! is_array( $spec ) ) {
		fwrite( STDERR, "Invalid baseline entry, expected map<string, object>.\n" );
		exit( 1 );
	}

	$normalized_language = strtolower( trim( $language ) );
	$validator->validate_language_spec( $normalized_language, $spec );

	$final_spec = $overrides->apply( $normalized_language, $spec );
	$validator->validate_language_spec( $normalized_language, $final_spec );

	$generated[ $normalized_language ] = $final_spec;
}

ksort( $generated );

if ( $dry_run ) {
	fwrite( STDOUT, sprintf( 'Dry run OK: validated %d language specs.' . PHP_EOL, count( $generated ) ) );
	exit( 0 );
}

$output_dir = dirname( $output_path );
if ( ! is_dir( $output_dir ) && ! mkdir( $output_dir, 0777, true ) && ! is_dir( $output_dir ) ) {
	fwrite( STDERR, "Cannot create output directory: {$output_dir}\n" );
	exit( 1 );
}

$php = "<?php\n"
	. "/**\n"
	. " * Auto-generated plural specs map.\n"
	. " *\n"
	. " * DO NOT EDIT MANUALLY.\n"
	. " */\n\n"
	. 'return ' . var_export( $generated, true ) . ";\n";

if ( false === file_put_contents( $output_path, $php ) ) {
	fwrite( STDERR, "Cannot write output file: {$output_path}\n" );
	exit( 1 );
}

fwrite( STDOUT, sprintf( 'Generated %d language specs to %s' . PHP_EOL, count( $generated ), $output_path ) );
