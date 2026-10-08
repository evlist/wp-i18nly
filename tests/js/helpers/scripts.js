/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Ordered list of the admin scripts evaluated by the test environment, relative to the plugin directory.
 *
 * It must match the order in which EditScreenAssets registers the scripts.
 */

'use strict';

module.exports = [
	'assets/js/linguistic-resource/ui-text.js',
	'assets/js/linguistic-resource/ajax-client.js',
	'assets/js/linguistic-resource/entry-badges.js',
	'assets/js/linguistic-resource/abstract-row.js',
	'assets/js/linguistic-resource/entry-filter-bar.js',
	'assets/js/linguistic-resource/modified-rows-tracker.js',
	'assets/js/linguistic-resource/abstract-editor.js',
	'assets/js/translation/translation-row.js',
	'assets/js/translation/ai-error-dialog.js',
	'assets/js/translation/deepl-usage-gauge.js',
	'assets/js/translation/ai-batch-translation.js',
	'assets/js/translation/translation-editor.js',
	'assets/js/translation-edit.js'
];
