/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

( function ( window ) {
	'use strict';

	var namespace = window.I18nly = window.I18nly || {};

	/**
	 * One entry row of a translation table.
	 */
	class TranslationRow extends namespace.AbstractLinguisticResourceRow {
		/**
		 * Returns the resource kind.
		 *
		 * @return {string}
		 */
		getKind() {
			return 'translation';
		}

		/**
		 * Returns the "translate with AI" buttons of the row.
		 *
		 * @return {Element[]}
		 */
		getTranslateButtons() {
			return Array.prototype.slice.call( this.element.querySelectorAll( '.i18nly-translate-btn' ) );
		}
	}

	namespace.TranslationRow = TranslationRow;
} )( window );
