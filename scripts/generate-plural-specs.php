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
		'wp-locales-command::',
		'dry-run',
		'audit',
		'audit-report::',
		'audit-fail-on-overrides',
	)
);

$input_path             = isset( $options['input'] ) ? (string) $options['input'] : discover_default_input_path();
$languages_dir          = isset( $options['languages-dir'] ) ? (string) $options['languages-dir'] : __DIR__ . '/../plugin/includes/WP_I18nly/Plurals/Languages';
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
$overridden_languages = array();

foreach ( $baseline as $language => $spec ) {
	if ( ! is_string( $language ) || ! is_array( $spec ) ) {
		fwrite( STDERR, "Invalid baseline entry, expected map<string, object>.\n" );
		exit( 1 );
	}

	$normalized_language = strtolower( trim( $language ) );
	$validator->validate_language_spec( $normalized_language, $spec );

	$final_spec = $overrides->apply( $normalized_language, $spec );
	$validator->validate_language_spec( $normalized_language, $final_spec );

	if ( $spec !== $final_spec ) {
		$overridden_languages[ $normalized_language ] = detect_changed_spec_keys( $spec, $final_spec );
	}

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
				'WP languages missing in GlotPress baseline: %s' . PHP_EOL,
				implode( ', ', $missing )
			)
		);
	}
} else {
	fwrite(
		STDOUT,
		'WP locale filter disabled or unavailable; generating all GlotPress baseline languages. ' .
		'If WP exists outside current directory, pass --wp-locales-command="wp --path=/path/to/wp language core list --field=language".' .
		PHP_EOL
	);
}

if ( $audit_enabled ) {
	$audit_report = build_generation_audit_report(
		$generated,
		$overridden_languages,
		$audit_fail_on_override
	);

	$issue_count = isset( $audit_report['issues'] ) && is_array( $audit_report['issues'] )
		? count( $audit_report['issues'] )
		: 0;

	fwrite(
		STDOUT,
		sprintf(
			'Audit summary: %d issue(s), %d overridden language(s).' . PHP_EOL,
			$issue_count,
			count( $overridden_languages )
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
		$lines .= "\t\t\t'label'   => __( {$label_export}, 'i18nly' ),\n";
		$lines .= "\t\t\t'tooltip' => __( {$tooltip_export}, 'i18nly' ),\n";
		$lines .= "\t\t);\n";
		++$index;
	}

	return $lines;
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

	return 'Lang' . ucfirst( $normalized );
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

		$normalized_language = normalize_language_prefix( $slug );

		if ( '' === $normalized_language && isset( $locale->wp_locale ) && is_string( $locale->wp_locale ) ) {
			$normalized_language = normalize_language_prefix( $locale->wp_locale );
		}

		if ( '' === $normalized_language ) {
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

		if ( ! isset( $specs[ $normalized_language ] ) || 2 === strlen( $slug ) ) {
			$specs[ $normalized_language ] = array(
				'nplurals'          => $nplurals,
				'plural_expression' => '(' . $plural_expression . ')',
				'forms'             => build_forms_from_nplurals( $nplurals, $plural_expression ),
			);
		}
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
