/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation functions of the editor scripts.
 *
 * The strings are translated by WordPress (wp_set_script_translations()): the scripts call these
 * functions like wp.i18n.__(), with the text domain as last argument, so that the strings can be
 * extracted. When wp.i18n is not loaded the text is returned unchanged.
 *
 * @package I18nly
 */

( function ( window ) {
	'use strict';

	var namespace = window.I18nly = window.I18nly || {};

	/**
	 * Returns the wp.i18n function of that name, resolved at call time, or null.
	 *
	 * @param {string} name Function name.
	 * @return {?Function}
	 */
	function wpFunction( name ) {
		var wpI18n = window.wp && window.wp.i18n ? window.wp.i18n : null;

		return wpI18n && 'function' === typeof wpI18n[name] ? wpI18n[name] : null;
	}

	namespace.i18n = {
		/**
		 * Translates a text.
		 *
		 * @param {string} text   Text.
		 * @param {string} domain Text domain.
		 * @return {string}
		 */
		__: function ( text, domain ) {
			var translate = wpFunction( '__' );

			return translate ? translate( text, domain ) : text;
		},

		/**
		 * Translates a text depending on a number.
		 *
		 * @param {string} singular Singular text.
		 * @param {string} plural   Plural text.
		 * @param {number} number   Number.
		 * @param {string} domain   Text domain.
		 * @return {string}
		 */
		_n: function ( singular, plural, number, domain ) {
			var translate = wpFunction( '_n' );

			return translate ? translate( singular, plural, number, domain ) : ( 1 === number ? singular : plural );
		},

		/**
		 * Replaces the placeholders (%s, %d, %1$s) of a text.
		 *
		 * @param {string} format Text with placeholders.
		 * @return {string}
		 */
		sprintf: function ( format ) {
			var format_function = wpFunction( 'sprintf' );
			var values          = Array.prototype.slice.call( arguments, 1 );
			var position        = 0;

			if ( format_function ) {
				return format_function.apply( null, [ format ].concat( values ) );
			}

			return String( format ).replace(
				/%(?:(\d+)\$)?[sd]/g,
				function ( match, index ) {
					var value = index ? values[ Number( index ) - 1 ] : values[ position++ ];

					return undefined === value ? match : String( value );
				}
			);
		}
	};
} )( window );
