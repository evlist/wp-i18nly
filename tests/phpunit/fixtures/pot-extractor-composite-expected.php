<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Expected output of the extractor on the composite plugin of PotSourceEntryExtractorGoldenTest.
 *
 * @package I18nly
 */

return array(
	array(
		'original' => 'Welcome, %s',
		'references' => array(
			array(
				'file' => 'composite.php',
				'line' => 7,
			),
		),
		'comments' => array(
			'translators: %s is a user name.',
		),
		'flags' => array(
			'php-format',
		),
	),
	array(
		'original' => 'Shared string',
		'references' => array(
			array(
				'file' => 'composite.php',
				'line' => 8,
			),
			array(
				'file' => 'includes/helpers.php',
				'line' => 3,
			),
			array(
				'file' => 'resources/views/page.blade.php',
				'line' => 2,
			),
			array(
				'file' => 'assets/js/app.js',
				'line' => 10,
			),
			array(
				'file' => 'assets/js/bundle.js',
				'line' => 3,
			),
		),
		'comments' => array(
			'translators: 1: first value, 2: second value.',
		),
	),
	array(
		'original' => 'Echoed text',
		'references' => array(
			array(
				'file' => 'composite.php',
				'line' => 9,
			),
		),
	),
	array(
		'original' => 'Post',
		'references' => array(
			array(
				'file' => 'composite.php',
				'line' => 10,
			),
		),
		'context' => 'noun',
	),
	array(
		'original' => 'Archive',
		'references' => array(
			array(
				'file' => 'composite.php',
				'line' => 11,
			),
		),
		'context' => 'menu',
	),
	array(
		'original' => '%d comment',
		'references' => array(
			array(
				'file' => 'composite.php',
				'line' => 12,
			),
		),
		'flags' => array(
			'php-format',
		),
		'plural' => '%d comments',
	),
	array(
		'original' => '%d step',
		'references' => array(
			array(
				'file' => 'composite.php',
				'line' => 13,
			),
		),
		'flags' => array(
			'php-format',
		),
		'plural' => '%d steps',
		'context' => 'workflow',
	),
	array(
		'original' => 'Back',
		'references' => array(
			array(
				'file' => 'composite.php',
				'line' => 14,
			),
		),
		'context' => 'navigation',
	),
	array(
		'original' => 'Attribute text',
		'references' => array(
			array(
				'file' => 'composite.php',
				'line' => 15,
			),
		),
	),
	array(
		'original' => "Double quoted \"escaped\"\n",
		'references' => array(
			array(
				'file' => 'composite.php',
				'line' => 17,
			),
		),
	),
	array(
		'original' => 'Method call',
		'references' => array(
			array(
				'file' => 'composite.php',
				'line' => 18,
			),
		),
	),
	array(
		'original' => '%1$s and %2$s',
		'references' => array(
			array(
				'file' => 'includes/helpers.php',
				'line' => 3,
			),
		),
		'comments' => array(
			'translators: 1: first value, 2: second value.',
		),
		'flags' => array(
			'php-format',
		),
	),
	array(
		'original' => 'Inner string',
		'references' => array(
			array(
				'file' => 'includes/helpers.php',
				'line' => 3,
			),
		),
		'comments' => array(
			'translators: 1: first value, 2: second value.',
		),
	),
	array(
		'original' => '%s noop singular',
		'references' => array(
			array(
				'file' => 'includes/helpers.php',
				'line' => 8,
			),
		),
		'comments' => array(
			'translators: Docblock style comment.',
		),
		'flags' => array(
			'php-format',
		),
		'plural' => '%s noop plural',
	),
	array(
		'original' => '%s noop ctx singular',
		'references' => array(
			array(
				'file' => 'includes/helpers.php',
				'line' => 9,
			),
		),
		'flags' => array(
			'php-format',
		),
		'plural' => '%s noop ctx plural',
		'context' => 'noop context',
	),
	array(
		'original' => 'XML escaped',
		'references' => array(
			array(
				'file' => 'includes/helpers.php',
				'line' => 10,
			),
		),
	),
	array(
		'original' => 'XML echoed',
		'references' => array(
			array(
				'file' => 'includes/helpers.php',
				'line' => 11,
			),
		),
	),
	array(
		'original' => 'XML context',
		'references' => array(
			array(
				'file' => 'includes/helpers.php',
				'line' => 12,
			),
		),
		'context' => 'xml',
	),
	array(
		'original' => '%s old singular',
		'references' => array(
			array(
				'file' => 'includes/helpers.php',
				'line' => 13,
			),
		),
		'flags' => array(
			'php-format',
		),
		'plural' => '%s old plural',
	),
	array(
		'original' => '%s old noop singular',
		'references' => array(
			array(
				'file' => 'includes/helpers.php',
				'line' => 14,
			),
		),
		'flags' => array(
			'php-format',
		),
		'plural' => '%s old noop plural',
	),
	array(
		'original' => 'Deprecated singular',
		'references' => array(
			array(
				'file' => 'includes/helpers.php',
				'line' => 15,
			),
		),
	),
	array(
		'original' => '%s deprecated singular',
		'references' => array(
			array(
				'file' => 'includes/helpers.php',
				'line' => 16,
			),
		),
		'flags' => array(
			'php-format',
		),
		'plural' => '%s deprecated plural',
	),
	array(
		'original' => 'Compat gettext call',
		'references' => array(
			array(
				'file' => 'includes/helpers.php',
				'line' => 17,
			),
		),
	),
	array(
		'original' => 'Upper case function',
		'references' => array(
			array(
				'file' => 'includes/helpers.php',
				'line' => 18,
			),
		),
	),
	array(
		'original' => 'Blade heading',
		'references' => array(
			array(
				'file' => 'resources/views/page.blade.php',
				'line' => 1,
			),
		),
	),
	array(
		'original' => '%d blade item',
		'references' => array(
			array(
				'file' => 'resources/views/page.blade.php',
				'line' => 4,
			),
		),
		'flags' => array(
			'php-format',
		),
		'plural' => '%d blade items',
	),
	array(
		'original' => 'Blade component label',
		'references' => array(
			array(
				'file' => 'resources/views/page.blade.php',
				'line' => 6,
			),
		),
	),
	array(
		'original' => 'Direct label',
		'references' => array(
			array(
				'file' => 'assets/js/app.js',
				'line' => 2,
			),
		),
		'comments' => array(
			'Label of the main button.',
		),
	),
	array(
		'original' => 'Open',
		'references' => array(
			array(
				'file' => 'assets/js/app.js',
				'line' => 4,
			),
		),
		'comments' => array(
			'Dialog title.',
		),
		'context' => 'verb',
	),
	array(
		'original' => '%d row',
		'references' => array(
			array(
				'file' => 'assets/js/app.js',
				'line' => 5,
			),
		),
		'flags' => array(
			'js-format',
		),
		'plural' => '%d rows',
	),
	array(
		'original' => '%d file',
		'references' => array(
			array(
				'file' => 'assets/js/app.js',
				'line' => 6,
			),
		),
		'flags' => array(
			'js-format',
		),
		'plural' => '%d files',
		'context' => 'noun',
	),
	array(
		'original' => 'Template literal label',
		'references' => array(
			array(
				'file' => 'assets/js/app.js',
				'line' => 7,
			),
		),
	),
	array(
		'original' => 'Webpack object call',
		'references' => array(
			array(
				'file' => 'assets/js/app.js',
				'line' => 8,
			),
		),
	),
	array(
		'original' => 'Babel indirect call',
		'references' => array(
			array(
				'file' => 'assets/js/app.js',
				'line' => 9,
			),
		),
	),
	array(
		'original' => 'Eval extracted',
		'references' => array(
			array(
				'file' => 'assets/js/app.js',
				'line' => 1,
			),
		),
	),
	array(
		'original' => 'From sourcemap source',
		'references' => array(
			array(
				'file' => 'assets/js/bundle.js',
				'line' => 2,
			),
		),
		'comments' => array(
			'From source map.',
		),
	),
	array(
		'original' => '%d mapped',
		'references' => array(
			array(
				'file' => 'assets/js/bundle.js',
				'line' => 4,
			),
		),
		'flags' => array(
			'js-format',
		),
		'plural' => '%d mapped items',
	),
	array(
		'original' => 'Composite block',
		'context' => 'block title',
		'references' => array(
			array(
				'file' => 'block.json',
				'line' => 1,
			),
		),
	),
	array(
		'original' => 'Composite block description',
		'context' => 'block description',
		'references' => array(
			array(
				'file' => 'block.json',
				'line' => 1,
			),
		),
	),
	array(
		'original' => 'alpha keyword',
		'context' => 'block keyword',
		'references' => array(
			array(
				'file' => 'block.json',
				'line' => 1,
			),
		),
	),
	array(
		'original' => 'beta keyword',
		'context' => 'block keyword',
		'references' => array(
			array(
				'file' => 'block.json',
				'line' => 1,
			),
		),
	),
	array(
		'original' => 'Outline style',
		'context' => 'block style label',
		'references' => array(
			array(
				'file' => 'block.json',
				'line' => 1,
			),
		),
	),
	array(
		'original' => 'Compact variation',
		'context' => 'block variation title',
		'references' => array(
			array(
				'file' => 'block.json',
				'line' => 1,
			),
		),
	),
	array(
		'original' => 'Compact variation description',
		'context' => 'block variation description',
		'references' => array(
			array(
				'file' => 'block.json',
				'line' => 1,
			),
		),
	),
	array(
		'original' => 'Dark palette name',
		'context' => 'color name',
		'references' => array(
			array(
				'file' => 'styles/dark.json',
				'line' => 1,
			),
		),
	),
	array(
		'original' => 'Palette name',
		'context' => 'color name',
		'references' => array(
			array(
				'file' => 'theme.json',
				'line' => 1,
			),
		),
	),
	array(
		'original' => 'Theme font size',
		'context' => 'font size name',
		'references' => array(
			array(
				'file' => 'theme.json',
				'line' => 1,
			),
		),
	),
);
