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

$options = getopt( '', array( 'input::', 'languages-dir::', 'wp-locales-command::', 'dry-run' ) );

$input_path = isset( $options['input'] ) ? (string) $options['input'] : __DIR__ . '/plurals/cldr-baseline.sample.json';
$languages_dir = isset( $options['languages-dir'] ) ? (string) $options['languages-dir'] : __DIR__ . '/../plugin/includes/WP_I18nly/Plurals/Languages';
$wp_locales_command = isset( $options['wp-locales-command'] )
	? (string) $options['wp-locales-command']
	: 'wp language core list --field=language';
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

$wp_prefixes = resolve_wp_language_prefixes( $wp_locales_command );
if ( ! empty( $wp_prefixes ) ) {
	$all_generated = $generated;
	$generated     = filter_generated_by_prefixes( $generated, $wp_prefixes );
	$missing       = array_values( array_diff( $wp_prefixes, array_keys( $all_generated ) ) );

	fwrite(
		STDOUT,
		sprintf(
			'WP locale filter enabled: kept %d of %d language specs.' . PHP_EOL,
			count( $generated ),
			count( $all_generated )
		)
	);

	if ( ! empty( $missing ) ) {
		fwrite(
			STDOUT,
			sprintf(
				'WP languages missing in CLDR baseline: %s' . PHP_EOL,
				implode( ', ', $missing )
			)
		);
	}
} else {
	fwrite( STDOUT, 'WP locale filter disabled or unavailable; generating all baseline languages.' . PHP_EOL );
}

if ( $dry_run ) {
	fwrite( STDOUT, sprintf( 'Dry run OK: validated %d language specs.' . PHP_EOL, count( $generated ) ) );
	exit( 0 );
}

if ( '' !== $languages_dir ) {
	generate_language_classes( $generated, $languages_dir );
	fwrite( STDOUT, sprintf( 'Generated %d language classes to %s' . PHP_EOL, count( $generated ), $languages_dir ) );
}

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

/**
 * Resolves WordPress language prefixes (first two letters).
 *
 * @param string $command WP-CLI command returning one locale per line.
 * @return array<int, string>
 */
function resolve_wp_language_prefixes( $command ) {
	$command = trim( (string) $command );

	if ( '' === $command ) {
		return array();
	}

	$raw = shell_exec( $command . ' 2>/dev/null' );

	if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
		return array();
	}

	$prefix_map = array();
	$lines      = preg_split( '/\r?\n/', $raw );

	if ( ! is_array( $lines ) ) {
		return array();
	}

	foreach ( $lines as $line ) {
		$prefix = normalize_language_prefix( $line );

		if ( '' !== $prefix ) {
			$prefix_map[ $prefix ] = true;
		}
	}

	$prefixes = array_keys( $prefix_map );
	sort( $prefixes );

	return $prefixes;
}

/**
 * Filters generated language specs by allowed prefixes.
 *
 * @param array<string, array<string, mixed>> $generated Generated specs.
 * @param array<int, string>                  $prefixes Allowed prefixes.
 * @return array<string, array<string, mixed>>
 */
function filter_generated_by_prefixes( array $generated, array $prefixes ) {
	$allowed = array_fill_keys( $prefixes, true );
	$result  = array();

	foreach ( $generated as $language => $spec ) {
		if ( isset( $allowed[ $language ] ) ) {
			$result[ $language ] = $spec;
		}
	}

	return $result;
}

/**
 * Normalizes a locale/language string to a two-letter prefix.
 *
 * @param string $value Locale/language value.
 * @return string
 */
function normalize_language_prefix( $value ) {
	$value = strtolower( trim( (string) $value ) );

	if ( ! preg_match( '/^[a-z]{2}/', $value, $matches ) ) {
		return '';
	}

	return isset( $matches[0] ) ? (string) $matches[0] : '';
}
