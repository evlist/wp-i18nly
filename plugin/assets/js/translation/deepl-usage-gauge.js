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
	 * Refreshes the DeepL monthly usage gauges displayed on the page.
	 */
	class DeepLUsageGauge {
		/**
		 * Replaces every gauge of the page by the one found in server rendered HTML.
		 *
		 * @param {string} usageHtml HTML containing a .i18nly-deepl-usage-box element.
		 * @return {void}
		 */
		static updateFromHtml( usageHtml ) {
			var html = String( usageHtml || '' ).trim();
			var wrapper;
			var replacementBox;
			var replacementHtml;

			if ( '' === html ) {
				return;
			}

			wrapper           = window.document.createElement( 'div' );
			wrapper.innerHTML = html;
			replacementBox    = wrapper.querySelector( '.i18nly-deepl-usage-box' );

			if ( ! replacementBox ) {
				return;
			}

			replacementHtml = replacementBox.outerHTML;

			Array.prototype.slice.call( window.document.querySelectorAll( '.i18nly-deepl-usage-box' ) ).forEach(
				function ( box ) {
					if ( box && box.parentNode ) {
						box.outerHTML = replacementHtml;
					}
				}
			);
		}
	}

	namespace.DeepLUsageGauge = DeepLUsageGauge;
} )( window );
