/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Entry point of the translation edit screen.
 *
 * The behavior lives in the classes loaded before this script: the generic linguistic resource editor
 * under linguistic-resource/ and the translation specific code under translation/.
 *
 * @package I18nly
 */

( function () {
	var config  = window.i18nlyTranslationEditConfig || null;
	var isReady = typeof window.fetch === 'function' && null !== config;

	if ( window.i18nlyPotInitDone || ! isReady ) {
		return;
	}

	window.i18nlyPotInitDone = true;

	new window.I18nly.TranslationEditor( config ).boot();
} )();
