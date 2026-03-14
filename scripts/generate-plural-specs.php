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

$options = getopt(
	'',
	array(
		'input::',
		'languages-dir::',
		'supported-locales-file::',
		'wp-locales-command::',
		'dry-run',
		'audit',
		'audit-report::',
		'audit-fail-on-overrides',
	)
);

$input_path             = isset( $options['input'] ) ? (string) $options['input'] : discover_default_input_path();
$languages_dir          = isset( $options['languages-dir'] ) ? (string) $options['languages-dir'] : __DIR__ . '/../plugin/includes/WP_I18nly/Plurals/Languages';
$supported_locales_file = isset( $options['supported-locales-file'] )
	? (string) $options['supported-locales-file']
	: __DIR__ . '/../plugin/includes/WP_I18nly/Support/GeneratedTargetLocales.php';
$wp_locales_command     = isset( $options['wp-locales-command'] )
	? (string) $options['wp-locales-command']
	: 'wp language core list --field=language';
$dry_run                = array_key_exists( 'dry-run', $options );
$audit_enabled          = array_key_exists( 'audit', $options );
$audit_report_path      = isset( $options['audit-report'] ) ? trim( (string) $options['audit-report'] ) : '';
$audit_fail_on_override = array_key_exists( 'audit-fail-on-overrides', $options );

if ( ! is_file( $input_path ) ) {
	fwrite( STDERR, "Input file not found: {$input_path}\n" );
	exit( 1 );
}

require_once $input_path;

if ( ! class_exists( 'GP_Locales' ) ) {
	fwrite( STDERR, "Invalid GlotPress input (GP_Locales class missing): {$input_path}\n" );
	exit( 1 );
}

$baseline = build_specs_from_glotpress_locales();

$validator            = new SpecContractValidator();
$overrides            = new ProjectPluralSpecOverrides();
$generated            = array();
$overridden_locales   = array();

foreach ( $baseline as $locale => $spec ) {
	if ( ! is_string( $locale ) || ! is_array( $spec ) ) {
		fwrite( STDERR, "Invalid baseline entry, expected map<string, object>.\n" );
		exit( 1 );
	}

	$normalized_locale = normalize_locale_key( $locale );
	$override_locale   = locale_key_to_wp_locale( $normalized_locale );
	$validator->validate_language_spec( $normalized_locale, $spec );

	$final_spec = $overrides->apply( $override_locale, $spec );
	$validator->validate_language_spec( $normalized_locale, $final_spec );

	if ( $spec !== $final_spec ) {
		$overridden_locales[ $normalized_locale ] = detect_changed_spec_keys( $spec, $final_spec );
	}

	$generated[ $normalized_locale ] = $final_spec;
}

ksort( $generated );

$wp_locales = resolve_wp_locales( $wp_locales_command );
if ( ! empty( $wp_locales ) ) {
	$all_generated = $generated;
	$generated     = filter_generated_by_locales( $generated, $wp_locales );
	$missing       = array_values( array_diff( $wp_locales, array_keys( $all_generated ) ) );

	fwrite(
		STDOUT,
		sprintf(
			'WP locale filter enabled: kept %d of %d locale specs.' . PHP_EOL,
			count( $generated ),
			count( $all_generated )
		)
	);

	if ( ! empty( $missing ) ) {
		fwrite(
			STDOUT,
			sprintf(
				'WP locales missing in GlotPress baseline: %s' . PHP_EOL,
				implode( ', ', array_map( 'locale_key_to_wp_locale', $missing ) )
			)
		);
	}
} else {
	fwrite(
		STDOUT,
		'WP locale filter unavailable; generating all GlotPress baseline locales. ' .
		'Pass --wp-locales-command to override the default WP-CLI command if needed.' .
		PHP_EOL
	);
}

if ( $audit_enabled ) {
	$audit_report = build_generation_audit_report(
		$generated,
		$overridden_locales,
		$audit_fail_on_override
	);

	$issue_count = isset( $audit_report['issues'] ) && is_array( $audit_report['issues'] )
		? count( $audit_report['issues'] )
		: 0;

	fwrite(
		STDOUT,
		sprintf(
			'Audit summary: %d issue(s), %d overridden locale(s).' . PHP_EOL,
			$issue_count,
			count( $overridden_locales )
		)
	);

	if ( '' !== $audit_report_path ) {
		$report_dir = dirname( $audit_report_path );

		if ( ! is_dir( $report_dir ) && ! mkdir( $report_dir, 0777, true ) && ! is_dir( $report_dir ) ) {
			fwrite( STDERR, "Cannot create audit report directory: {$report_dir}\n" );
			exit( 1 );
		}

		$encoded = json_encode( $audit_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( ! is_string( $encoded ) || false === file_put_contents( $audit_report_path, $encoded . PHP_EOL ) ) {
			fwrite( STDERR, "Cannot write audit report: {$audit_report_path}\n" );
			exit( 1 );
		}

		fwrite( STDOUT, sprintf( 'Audit report written to %s' . PHP_EOL, $audit_report_path ) );
	}

	if ( $issue_count > 0 ) {
		fwrite( STDERR, 'Audit failed. Fix issues or adjust policy before generation.' . PHP_EOL );
		exit( 1 );
	}
}

if ( $dry_run ) {
	fwrite( STDOUT, sprintf( 'Dry run OK: validated %d language specs.' . PHP_EOL, count( $generated ) ) );
	exit( 0 );
}

if ( '' !== $languages_dir ) {
	generate_language_classes( $generated, $languages_dir );
	fwrite( STDOUT, sprintf( 'Generated %d locale classes to %s' . PHP_EOL, count( $generated ), $languages_dir ) );
}

if ( '' !== $supported_locales_file ) {
	generate_supported_locales_class( array_keys( $generated ), $supported_locales_file );
	fwrite( STDOUT, sprintf( 'Generated supported locales file to %s' . PHP_EOL, $supported_locales_file ) );
}

/**
 * Generates one class file per locale.
 *
 * @param array<string, array<string, mixed>> $generated Generated specs by locale.
 * @param string                              $languages_dir Target directory.
 * @return void
 */
function generate_language_classes( array $generated, $languages_dir ) {
	if ( ! is_dir( $languages_dir ) && ! mkdir( $languages_dir, 0777, true ) && ! is_dir( $languages_dir ) ) {
		fwrite( STDERR, "Cannot create languages directory: {$languages_dir}\n" );
		exit( 1 );
	}

	$existing_files = glob( rtrim( $languages_dir, '/\\' ) . '/Lang*.php' );
	if ( false !== $existing_files ) {
		foreach ( $existing_files as $existing_file ) {
			if ( is_string( $existing_file ) && is_file( $existing_file ) ) {
				unlink( $existing_file );
			}
		}
	}

	foreach ( $generated as $locale => $spec ) {
		$class_name = locale_to_class_name( $locale );
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
	$nplurals                 = isset( $spec['nplurals'] ) ? max( 1, (int) $spec['nplurals'] ) : 1;
	$plural_expression        = isset( $spec['plural_expression'] ) ? (string) $spec['plural_expression'] : '(n != 1)';
	$forms                    = isset( $spec['forms'] ) && is_array( $spec['forms'] ) ? $spec['forms'] : array();
	$forms_assignments        = build_forms_assignments_php( $forms );
	$plural_expression_export = var_export( $plural_expression, true );

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
		. "\t\t\$spec = array (\n"
		. "  'nplurals' => {$nplurals},\n"
		. "  'plural_expression' => {$plural_expression_export},\n"
		. "  'forms' => \n"
		. "  array (\n"
		. "  ),\n"
		. ");\n"
		. "\n"
		. $forms_assignments
		. "\n"
		. "\t\treturn \$spec;\n"
		. "\t}\n"
		. "}\n";
}

/**
 * Builds explicit forms assignment lines for one generated class.
 *
 * @param array<int|string, mixed> $forms Forms map.
 * @return string
 */
function build_forms_assignments_php( array $forms ) {
	$lines = '';
	$index = 0;

	foreach ( $forms as $value ) {
		$label   = marker_from_index( $index );
		$tooltip = is_string( $value ) ? $value : '';

		if ( is_array( $value ) ) {
			if ( isset( $value['label'] ) && is_string( $value['label'] ) && '' !== trim( $value['label'] ) ) {
				$label = $value['label'];
			}

			if ( isset( $value['tooltip'] ) && is_string( $value['tooltip'] ) && '' !== trim( $value['tooltip'] ) ) {
				$tooltip = $value['tooltip'];
			}
		}

		if ( '' === trim( $tooltip ) ) {
			$tooltip = 'other';
		}

		$label_export   = var_export( (string) $label, true );
		$tooltip_export = var_export( (string) $tooltip, true );

		$lines .= "\t\t\$spec['forms'][] = array(\n";
		$lines .= "\t\t\t/* translators: Short label identifying one plural form input in the translation editor. */\n";
		$lines .= "\t\t\t'label'   => __( {$label_export}, 'i18nly' ),\n";
		$lines .= "\t\t\t/* translators: Tooltip explaining when this plural form input should be used in the translation editor. */\n";
		$lines .= "\t\t\t'tooltip' => __( {$tooltip_export}, 'i18nly' ),\n";
		$lines .= "\t\t);\n";
		++$index;
	}

	return $lines;
}

/**
 * Converts locale code to PSR-4 class name.
 *
 * Example: `pt_BR` => `LangPtBr`.
 *
 * @param string $locale Locale code.
 * @return string
 */
function locale_to_class_name( $locale ) {
	$normalized = normalize_locale_key( $locale );

	if ( '' === $normalized ) {
		return 'DefaultSpec';
	}

	$parts = explode( '_', $normalized );
	$camel = '';

	foreach ( $parts as $part ) {
		if ( '' === $part ) {
			continue;
		}

		$camel .= strtoupper( substr( $part, 0, 1 ) ) . strtolower( substr( $part, 1 ) );
	}

	return 'Lang' . $camel;
}

/**
 * Builds internal specs from GlotPress locales definitions.
 *
 * Source of truth:
 * - `GP_Locales::locales()` from an imported copy of `locales.php`.
 *
 * @return array<string, array<string, mixed>>
 */
function build_specs_from_glotpress_locales() {
	$specs   = array();
	$locales = GP_Locales::locales();

	if ( ! is_array( $locales ) ) {
		fwrite( STDERR, "Invalid GlotPress locale list; expected array.\n" );
		exit( 1 );
	}

	foreach ( $locales as $slug => $locale ) {
		if ( ! is_string( $slug ) || ! is_object( $locale ) ) {
			continue;
		}

		$canonical_locale  = ( isset( $locale->wp_locale ) && is_string( $locale->wp_locale ) ) ? $locale->wp_locale : $slug;
		$normalized_locale = normalize_locale_key( $canonical_locale );

		if ( '' === $normalized_locale ) {
			$normalized_locale = normalize_locale_key( $slug );
		}

		if ( '' === $normalized_locale ) {
			continue;
		}

		$nplurals = isset( $locale->nplurals ) ? (int) $locale->nplurals : 2;
		$nplurals = max( 1, $nplurals );

		$plural_expression = isset( $locale->plural_expression ) && is_string( $locale->plural_expression )
			? trim( $locale->plural_expression )
			: 'n != 1';

		if ( '' === $plural_expression ) {
			$plural_expression = 'n != 1';
		}

		$specs[ $normalized_locale ] = array(
				'nplurals'          => $nplurals,
				'plural_expression' => '(' . $plural_expression . ')',
				'forms'             => build_forms_from_nplurals( $nplurals, $plural_expression ),
			);
	}

	ksort( $specs );

	return $specs;
}

/**
 * Builds runtime forms from nplurals.
 *
 * The last form is intentionally labeled as "other" to keep UI simple for
 * non-technical users, while other forms expose concrete number examples.
 *
 * @param int    $nplurals Number of plural forms.
 * @param string $plural_expression Gettext plural expression.
 * @return array<string, string>
 */
function build_forms_from_nplurals( $nplurals, $plural_expression ) {
	$count    = max( 1, (int) $nplurals );
	$forms    = array();
	$examples = collect_plural_examples_by_index( $plural_expression, $count, 200, 4 );

	foreach ( range( 0, $count - 1 ) as $index ) {
		$marker = marker_from_index( (int) $index );

		if ( 1 === $count || $index === ( $count - 1 ) ) {
			$forms[ $marker ] = 'other';
			continue;
		}

		$forms[ $marker ] = format_plural_examples_label( $examples, (int) $index );
	}

	return $forms;
}

/**
 * Collects sample integers by resolved plural index.
 *
 * @param string $plural_expression Gettext plural expression.
 * @param int    $count Number of plural forms.
 * @param int    $max_n Max integer to probe.
 * @param int    $max_examples Number of examples retained per index.
 * @return array<int, array{examples: array<int, int>, hits: int}>
 */
function collect_plural_examples_by_index( $plural_expression, $count, $max_n, $max_examples ) {
	$result = array();

	for ( $index = 0; $index < $count; $index++ ) {
		$result[ $index ] = array(
			'examples' => array(),
			'hits'     => 0,
		);
	}

	for ( $n = 0; $n <= $max_n; $n++ ) {
		$index = evaluate_plural_expression_index( $plural_expression, $n, $count );
		if ( ! isset( $result[ $index ] ) ) {
			continue;
		}

		++$result[ $index ]['hits'];
		if ( count( $result[ $index ]['examples'] ) < $max_examples ) {
			$result[ $index ]['examples'][] = $n;
		}
	}

	return $result;
}

/**
 * Formats one example label for UI.
 *
 * @param array<int, array{examples: array<int, int>, hits: int}> $examples_by_index Collected examples.
 * @param int                                                     $index Plural index.
 * @return string
 */
function format_plural_examples_label( array $examples_by_index, $index ) {
	if ( ! isset( $examples_by_index[ $index ] ) ) {
		return 'other';
	}

	$examples = $examples_by_index[ $index ]['examples'];
	$hits     = $examples_by_index[ $index ]['hits'];

	if ( empty( $examples ) ) {
		return 'other';
	}

	$label = implode( ', ', array_map( 'strval', $examples ) );

	if ( $hits > count( $examples ) ) {
		$label .= ', ...';
	}

	return $label;
}

/**
 * Evaluates one gettext plural expression for an integer quantity.
 *
 * @param string $plural_expression Gettext plural expression.
 * @param int    $n Quantity.
 * @param int    $count Number of plural forms.
 * @return int
 */
function evaluate_plural_expression_index( $plural_expression, $n, $count ) {
	$tokens = tokenize_plural_expression( (string) $plural_expression );
	if ( empty( $tokens ) ) {
		return ( 1 === $n ) ? 0 : min( 1, $count - 1 );
	}

	$position = 0;
	$value    = parse_plural_conditional( $tokens, $position, (int) $n );

	if ( ! is_int( $value ) ) {
		$value = (int) $value;
	}

	if ( $value < 0 ) {
		return 0;
	}

	if ( $value >= $count ) {
		return $count - 1;
	}

	return $value;
}

/**
 * Tokenizes one gettext plural expression.
 *
 * @param string $expression Expression.
 * @return array<int, string>
 */
function tokenize_plural_expression( $expression ) {
	$normalized = trim( (string) $expression );
	if ( '' === $normalized ) {
		return array();
	}

	$tokens = array();
	$offset = 0;
	$length = strlen( $normalized );

	while ( $offset < $length ) {
		if ( preg_match( '/\G\s+/', $normalized, $matches, 0, $offset ) ) {
			$offset += strlen( $matches[0] );
			continue;
		}

		if ( preg_match( '/\G(==|!=|<=|>=|\|\||&&|[()?:%<>]|n|\d+)/', $normalized, $matches, 0, $offset ) ) {
			$tokens[] = $matches[1];
			$offset  += strlen( $matches[1] );
			continue;
		}

		return array();
	}

	return $tokens;
}

/**
 * Parses conditional expression level.
 *
 * @param array<int, string> $tokens Tokens.
 * @param int                $position Current parser position.
 * @param int                $n Quantity.
 * @return int
 */
function parse_plural_conditional( array $tokens, &$position, $n ) {
	$value = parse_plural_or( $tokens, $position, $n );

	if ( isset( $tokens[ $position ] ) && '?' === $tokens[ $position ] ) {
		++$position;
		$when_true = parse_plural_conditional( $tokens, $position, $n );

		if ( isset( $tokens[ $position ] ) && ':' === $tokens[ $position ] ) {
			++$position;
		}

		$when_false = parse_plural_conditional( $tokens, $position, $n );

		return ( 0 !== (int) $value ) ? (int) $when_true : (int) $when_false;
	}

	return (int) $value;
}

/**
 * Parses logical OR level.
 *
 * @param array<int, string> $tokens Tokens.
 * @param int                $position Current parser position.
 * @param int                $n Quantity.
 * @return int
 */
function parse_plural_or( array $tokens, &$position, $n ) {
	$value = parse_plural_and( $tokens, $position, $n );

	while ( isset( $tokens[ $position ] ) && '||' === $tokens[ $position ] ) {
		++$position;
		$rhs   = parse_plural_and( $tokens, $position, $n );
		$value = ( 0 !== (int) $value || 0 !== (int) $rhs ) ? 1 : 0;
	}

	return (int) $value;
}

/**
 * Parses logical AND level.
 *
 * @param array<int, string> $tokens Tokens.
 * @param int                $position Current parser position.
 * @param int                $n Quantity.
 * @return int
 */
function parse_plural_and( array $tokens, &$position, $n ) {
	$value = parse_plural_equality( $tokens, $position, $n );

	while ( isset( $tokens[ $position ] ) && '&&' === $tokens[ $position ] ) {
		++$position;
		$rhs   = parse_plural_equality( $tokens, $position, $n );
		$value = ( 0 !== (int) $value && 0 !== (int) $rhs ) ? 1 : 0;
	}

	return (int) $value;
}

/**
 * Parses equality level.
 *
 * @param array<int, string> $tokens Tokens.
 * @param int                $position Current parser position.
 * @param int                $n Quantity.
 * @return int
 */
function parse_plural_equality( array $tokens, &$position, $n ) {
	$value = parse_plural_relational( $tokens, $position, $n );

	while ( isset( $tokens[ $position ] ) && in_array( $tokens[ $position ], array( '==', '!=' ), true ) ) {
		$operator = $tokens[ $position ];
		++$position;
		$rhs = parse_plural_relational( $tokens, $position, $n );

		if ( '==' === $operator ) {
			$value = ( (int) $value === (int) $rhs ) ? 1 : 0;
		} else {
			$value = ( (int) $value !== (int) $rhs ) ? 1 : 0;
		}
	}

	return (int) $value;
}

/**
 * Parses relational level.
 *
 * @param array<int, string> $tokens Tokens.
 * @param int                $position Current parser position.
 * @param int                $n Quantity.
 * @return int
 */
function parse_plural_relational( array $tokens, &$position, $n ) {
	$value = parse_plural_modulo( $tokens, $position, $n );

	while ( isset( $tokens[ $position ] ) && in_array( $tokens[ $position ], array( '<', '<=', '>', '>=' ), true ) ) {
		$operator = $tokens[ $position ];
		++$position;
		$rhs = parse_plural_modulo( $tokens, $position, $n );

		switch ( $operator ) {
			case '<':
				$value = ( (int) $value < (int) $rhs ) ? 1 : 0;
				break;
			case '<=':
				$value = ( (int) $value <= (int) $rhs ) ? 1 : 0;
				break;
			case '>':
				$value = ( (int) $value > (int) $rhs ) ? 1 : 0;
				break;
			case '>=':
				$value = ( (int) $value >= (int) $rhs ) ? 1 : 0;
				break;
		}
	}

	return (int) $value;
}

/**
 * Parses modulo level.
 *
 * @param array<int, string> $tokens Tokens.
 * @param int                $position Current parser position.
 * @param int                $n Quantity.
 * @return int
 */
function parse_plural_modulo( array $tokens, &$position, $n ) {
	$value = parse_plural_primary( $tokens, $position, $n );

	while ( isset( $tokens[ $position ] ) && '%' === $tokens[ $position ] ) {
		++$position;
		$rhs = parse_plural_primary( $tokens, $position, $n );

		$divisor = (int) $rhs;
		$value   = 0 === $divisor ? 0 : ( (int) $value % $divisor );
	}

	return (int) $value;
}

/**
 * Parses primary values.
 *
 * @param array<int, string> $tokens Tokens.
 * @param int                $position Current parser position.
 * @param int                $n Quantity.
 * @return int
 */
function parse_plural_primary( array $tokens, &$position, $n ) {
	if ( ! isset( $tokens[ $position ] ) ) {
		return 0;
	}

	$token = $tokens[ $position ];

	if ( 'n' === $token ) {
		++$position;
		return (int) $n;
	}

	if ( preg_match( '/^\d+$/', $token ) ) {
		++$position;
		return (int) $token;
	}

	if ( '(' === $token ) {
		++$position;
		$value = parse_plural_conditional( $tokens, $position, $n );

		if ( isset( $tokens[ $position ] ) && ')' === $tokens[ $position ] ) {
			++$position;
		}

		return (int) $value;
	}

	++$position;

	return 0;
}

/**
 * Returns alphabetical marker for one index.
 *
 * @param int $index Marker index.
 * @return string
 */
function marker_from_index( $index ) {
	$index  = max( 0, (int) $index );
	$marker = '';

	do {
		$marker = chr( 97 + ( $index % 26 ) ) . $marker;
		$index  = (int) floor( $index / 26 ) - 1;
	} while ( $index >= 0 );

	return $marker;
}

/**
 * Normalizes a locale string to the internal key format.
 *
 * Example outputs: `pt_br`, `es_419`.
 *
 * @param string $value Locale value.
 * @return string
 */
function normalize_locale_key( $value ) {
	$value = str_replace( '-', '_', strtolower( trim( (string) $value ) ) );
	$value = preg_replace( '/[^a-z0-9_]/', '', $value );

	if ( ! is_string( $value ) ) {
		return '';
	}

	if ( ! preg_match( '/^[a-z]{2,3}(?:_[a-z0-9]{2,})*$/', $value ) ) {
		return '';
	}

	return $value;
}

/**
 * Resolves WordPress locales from a WP-CLI command.
 *
 * @param string $command WP-CLI command returning one locale per line.
 * @return array<int, string>
 */
function resolve_wp_locales( $command ) {
	$command = trim( (string) $command );

	if ( '' === $command ) {
		return array();
	}

	$raw = run_command_capture_output( $command );

	if ( ( ! is_string( $raw ) || '' === trim( $raw ) ) && 0 === strpos( $command, 'wp ' ) ) {
		$wp_path = discover_wordpress_path();

		if ( '' !== $wp_path ) {
			$command_with_path = preg_replace(
				'/^wp\s+/',
				'wp --path=' . escapeshellarg( $wp_path ) . ' ',
				$command,
				1
			);

			if ( is_string( $command_with_path ) ) {
				$raw = run_command_capture_output( $command_with_path );
			}
		}
	}

	if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
		return array();
	}

	$locale_map = array();
	$lines      = preg_split( '/\r?\n/', $raw );

	if ( ! is_array( $lines ) ) {
		return array();
	}

	foreach ( $lines as $line ) {
		$locale_key = normalize_locale_key( $line );

		if ( '' !== $locale_key ) {
			$locale_map[ $locale_key ] = true;
		}
	}

	$locales = array_keys( $locale_map );
	sort( $locales );

	return $locales;
}

/**
 * Runs one shell command and captures stdout.
 *
 * @param string $command Command.
 * @return string|null
 */
function run_command_capture_output( $command ) {
	$wrapped = 'env PATH="/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:$PATH" ' . $command . ' 2>/dev/null';

	return shell_exec( $wrapped );
}

/**
 * Tries to discover a local WordPress path.
 *
 * @return string
 */
function discover_wordpress_path() {
	$candidates = array();

	if ( isset( $_ENV['WP_PATH'] ) && is_string( $_ENV['WP_PATH'] ) ) {
		$candidates[] = $_ENV['WP_PATH'];
	}

	if ( isset( $_ENV['WP_ROOT'] ) && is_string( $_ENV['WP_ROOT'] ) ) {
		$candidates[] = $_ENV['WP_ROOT'];
	}

	$candidates[] = '/var/www/html';
	$candidates[] = '/workspaces/wordpress';

	foreach ( $candidates as $candidate ) {
		$path = rtrim( (string) $candidate, '/\\' );

		if ( '' !== $path && is_file( $path . '/wp-includes/version.php' ) ) {
			return $path;
		}
	}

	return '';
}

/**
 * Filters generated locale specs by allowed locales.
 *
 * @param array<string, array<string, mixed>> $generated Generated specs.
 * @param array<int, string>                  $locales Allowed normalized locales.
 * @return array<string, array<string, mixed>>
 */
function filter_generated_by_locales( array $generated, array $locales ) {
	$allowed = array_fill_keys( $locales, true );
	$result  = array();

	foreach ( $generated as $locale => $spec ) {
		if ( isset( $allowed[ $locale ] ) ) {
			$result[ $locale ] = $spec;
		}
	}

	return $result;
}

/**
 * Generates one PHP class exposing all supported target locales.
 *
 * @param array<int, string> $locales Locales list.
 * @param string             $output_file Destination file.
 * @return void
 */
function generate_supported_locales_class( array $locales, $output_file ) {
	$normalized_locales = array();

	foreach ( $locales as $locale ) {
		$key = normalize_locale_key( $locale );

		if ( '' !== $key ) {
			$normalized_locales[] = $key;
		}
	}

	$normalized_locales = array_values( array_unique( $normalized_locales ) );

	$wp_locales = array();
	foreach ( $normalized_locales as $normalized_locale ) {
		$wp_locales[] = locale_key_to_wp_locale( $normalized_locale );
	}

	$locales_for_export = array_values( array_unique( $wp_locales ) );
	sort( $locales_for_export );

	$output_dir = dirname( $output_file );
	if ( ! is_dir( $output_dir ) && ! mkdir( $output_dir, 0777, true ) && ! is_dir( $output_dir ) ) {
		fwrite( STDERR, "Cannot create supported locales directory: {$output_dir}\n" );
		exit( 1 );
	}

	$export_locales = var_export( $locales_for_export, true );

	$content = "<?php\n"
		. "/**\n"
		. " * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>\n"
		. " * SPDX-License-Identifier: GPL-3.0-or-later\n"
		. " *\n"
		. " * Auto-generated file. Do not edit manually.\n"
		. " *\n"
		. " * @package I18nly\n"
		. " */\n\n"
		. "namespace WP_I18nly\\Support;\n\n"
		. "defined( 'ABSPATH' ) || exit;\n\n"
		. "final class GeneratedTargetLocales {\n"
		. "\t/**\n"
		. "\t * Returns all target locales supported by generated plural specs.\n"
		. "\t *\n"
		. "\t * @return array<int, string>\n"
		. "\t */\n"
		. "\tpublic static function all() {\n"
		. "\t\treturn {$export_locales};\n"
		. "\t}\n"
		. "}\n";

	if ( false === file_put_contents( $output_file, $content ) ) {
		fwrite( STDERR, "Cannot write supported locales file: {$output_file}\n" );
		exit( 1 );
	}
}

/**
 * Converts internal locale key to canonical WP locale string.
 *
 * @param string $locale_key Normalized locale key.
 * @return string
 */
function locale_key_to_wp_locale( $locale_key ) {
	$parts = explode( '_', strtolower( (string) $locale_key ) );

	if ( empty( $parts ) ) {
		return '';
	}

	$language = (string) array_shift( $parts );
	$rebuilt  = $language;

	foreach ( $parts as $part ) {
		if ( preg_match( '/^[0-9]+$/', $part ) ) {
			$rebuilt .= '_' . $part;
			continue;
		}

		$rebuilt .= '_' . strtoupper( $part );
	}

	return $rebuilt;
}

/**
 * Discovers the default input path.
 *
 * Default source path for GlotPress locale definitions copy.
 *
 * @return string
 */
function discover_default_input_path() {
	return __DIR__ . '/plurals/upstream/glotpress-locales.php';
}

/**
 * Detects changed top-level keys between original and final specs.
 *
 * @param array<string, mixed> $original Original spec.
 * @param array<string, mixed> $final Final spec.
 * @return array<int, string>
 */
function detect_changed_spec_keys( array $original, array $final ) {
	$keys         = array_unique( array_merge( array_keys( $original ), array_keys( $final ) ) );
	$changed_keys = array();

	foreach ( $keys as $key ) {
		if ( ! is_string( $key ) ) {
			continue;
		}

		$before = $original[ $key ] ?? null;
		$after  = $final[ $key ] ?? null;

		if ( $before !== $after ) {
			$changed_keys[] = $key;
		}
	}

	sort( $changed_keys );

	return $changed_keys;
}

/**
 * Builds a strict audit report for generated language specs.
 *
 * @param array<string, array<string, mixed>> $generated Generated specs.
 * @param array<string, array<int, string>>   $overridden_languages Override changes by language.
 * @param bool                                $fail_on_overrides Whether overrides should fail audit.
 * @return array<string, mixed>
 */
function build_generation_audit_report( array $generated, array $overridden_languages, $fail_on_overrides ) {
	$issues = array();

	foreach ( $generated as $language => $spec ) {
		$nplurals          = isset( $spec['nplurals'] ) ? (int) $spec['nplurals'] : 0;
		$forms             = isset( $spec['forms'] ) && is_array( $spec['forms'] ) ? $spec['forms'] : array();
		$plural_expression = isset( $spec['plural_expression'] ) && is_string( $spec['plural_expression'] )
			? $spec['plural_expression']
			: '';

		if ( count( $forms ) !== $nplurals ) {
			$issues[] = array(
				'type'     => 'forms_count_mismatch',
				'language' => $language,
				'message'  => sprintf( 'forms count (%d) differs from nplurals (%d).', count( $forms ), $nplurals ),
			);
		}

		if ( '' === trim( $plural_expression ) ) {
			$issues[] = array(
				'type'     => 'empty_plural_expression',
				'language' => $language,
				'message'  => 'plural_expression must be non-empty.',
			);
		}

		if ( $fail_on_overrides && isset( $overridden_languages[ $language ] ) ) {
			$issues[] = array(
				'type'     => 'override_applied',
				'language' => $language,
				'message'  => sprintf(
					'override modified keys: %s',
					implode( ', ', $overridden_languages[ $language ] )
				),
			);
		}
	}

	return array(
		'generated_count'      => count( $generated ),
		'overridden_languages' => $overridden_languages,
		'issues'               => $issues,
	);
}
