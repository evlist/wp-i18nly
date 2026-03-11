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

$options = getopt( '', array( 'input::', 'output::', 'languages-dir::', 'dry-run' ) );

$input_path = isset( $options['input'] ) ? (string) $options['input'] : __DIR__ . '/plurals/cldr-baseline.sample.json';
$output_path = isset( $options['output'] ) ? (string) $options['output'] : __DIR__ . '/plurals/generated/plural-spec-map.php';
$languages_dir = isset( $options['languages-dir'] ) ? (string) $options['languages-dir'] : '';
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

if ( '' !== $languages_dir ) {
	generate_language_classes( $generated, $languages_dir );
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

/**
 * Generates one class file per language.
 *
 * @param array<string, array<string, mixed>> $generated Generated specs map.
 * @param string                              $languages_dir Target directory.
 * @return void
 */
function generate_language_classes( array $generated, $languages_dir ) {
	if ( ! is_dir( $languages_dir ) && ! mkdir( $languages_dir, 0777, true ) && ! is_dir( $languages_dir ) ) {
		fwrite( STDERR, "Cannot create languages directory: {$languages_dir}\n" );
		exit( 1 );
	}

	foreach ( $generated as $language => $spec ) {
		$class_name = language_to_class_name( $language );
		$file_path  = rtrim( $languages_dir, '/\\' ) . '/' . $class_name . '.php';
		$file_php   = build_language_class_php( $class_name, $spec );

		if ( false === file_put_contents( $file_path, $file_php ) ) {
			fwrite( STDERR, "Cannot write language class file: {$file_path}\n" );
			exit( 1 );
		}
	}
}

/**
 * Builds one PHP class content for a language spec.
 *
 * @param string               $class_name Class name.
 * @param array<string, mixed> $spec Language spec.
 * @return string
 */
function build_language_class_php( $class_name, array $spec ) {
	$spec_export = var_export( $spec, true );

	return "<?php\n"
		. "/**\n"
		. " * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>\n"
		. " * SPDX-License-Identifier: GPL-3.0-or-later\n"
		. " *\n"
		. " * Auto-generated file. Do not edit manually.\n"
		. " *\n"
		. " * @package I18nly\n"
		. " */\n\n"
		. "namespace WP_I18nly\\Plurals\\Languages;\n\n"
		. "use WP_I18nly\\Plurals\\LanguageSpecProvider;\n\n"
		. "defined( 'ABSPATH' ) || exit;\n\n"
		. "final class {$class_name} implements LanguageSpecProvider {\n"
		. "\t/**\n"
		. "\t * @return array<string, mixed>\n"
		. "\t */\n"
		. "\tpublic static function get_spec() {\n"
		. "\t\treturn {$spec_export};\n"
		. "\t}\n"
		. "}\n";
}

/**
 * Converts language code to PSR-4 class name.
 *
 * Uses the first two letters only.
 *
 * @param string $language Language code.
 * @return string
 */
function language_to_class_name( $language ) {
	$normalized = strtolower( substr( preg_replace( '/[^a-z]/i', '', (string) $language ), 0, 2 ) );

	if ( '' === $normalized ) {
		return 'DefaultSpec';
	}

	return ucfirst( $normalized );
}
