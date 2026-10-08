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
	 * Resolves localized UI strings handed over by PHP and formats plural messages.
	 */
	class UiText {
		/**
		 * @param {Object} config Editor configuration (i18n).
		 */
		constructor( config ) {
			var wpI18n = window.wp && window.wp.i18n ? window.wp.i18n : null;

			this.strings = config && config.i18n ? config.i18n : {};
			this.pluralize = wpI18n && 'function' === typeof wpI18n._n
				? wpI18n._n
				: function ( singular, plural, count ) {
					return 1 === count ? singular : plural;
				};
			this.format = wpI18n && 'function' === typeof wpI18n.sprintf
				? wpI18n.sprintf
				: function ( message, count ) {
					return String( message ).replace( '%d', String( count ) );
				};
		}

		/**
		 * Returns a localized string, or the fallback when PHP provided none.
		 *
		 * @param {string} key Message key.
		 * @param {string} fallback Fallback message.
		 * @return {string}
		 */
		get( key, fallback ) {
			var value = this.strings[key];

			if ( 'string' === typeof value && '' !== value ) {
				return value;
			}

			return fallback;
		}

		/**
		 * Formats a plural message containing a %d placeholder.
		 *
		 * @param {string} singular Singular message.
		 * @param {string} plural Plural message.
		 * @param {number} count Item count.
		 * @param {string} domain Text domain.
		 * @return {string}
		 */
		formatPlural( singular, plural, count, domain ) {
			return this.format( this.pluralize( singular, plural, count, domain ), count );
		}
	}

	namespace.UiText = UiText;
} )( window );
