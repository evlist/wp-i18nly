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
	 * Posts form-encoded requests to WordPress admin-ajax and reads their responses.
	 */
	class AjaxClient {
		/**
		 * @param {Object} config Editor configuration (ajaxUrl, contentTypeHeader).
		 */
		constructor( config ) {
			this.config = config;
		}

		/**
		 * Encodes a flat object as an application/x-www-form-urlencoded body.
		 *
		 * @param {Object} values Request values.
		 * @return {string}
		 */
		static toFormBody( values ) {
			return Object.keys( values )
				.map(
					function ( key ) {
						return encodeURIComponent( key ) + '=' + encodeURIComponent( String( values[key] ) );
					}
				)
				.join( '&' );
		}

		/**
		 * Posts values and resolves with the decoded JSON response.
		 *
		 * @param {Object} values Request values.
		 * @return {Promise<Object>}
		 */
		post( values ) {
			return window.fetch(
				this.config.ajaxUrl,
				{
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': this.config.contentTypeHeader
					},
					body: AjaxClient.toFormBody( values )
				}
			).then(
				function ( response ) {
					return response.json();
				}
			);
		}

		/**
		 * Posts values and resolves with the HTTP status and the JSON payload, if any.
		 *
		 * @param {Object} values Request values.
		 * @param {Object} options Options (signal).
		 * @return {Promise<{ok: boolean, status: number, payload: ?Object}>}
		 */
		postWithMeta( values, options ) {
			var requestOptions = options || {};

			return window.fetch(
				this.config.ajaxUrl,
				{
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': this.config.contentTypeHeader
					},
					body: AjaxClient.toFormBody( values ),
					signal: requestOptions.signal
				}
			).then(
				function ( response ) {
					return response.text().then(
						function ( text ) {
							var payload = null;

							if ( '' !== text ) {
								try {
									payload = JSON.parse( text );
								} catch ( error ) {
									payload = null;
								}
							}

							return {
								ok: response.ok,
								status: response.status,
								payload: payload
							};
						}
					);
				}
			);
		}

		/**
		 * Extracts the best error message from a WordPress AJAX payload.
		 *
		 * @param {?Object} payload Decoded response.
		 * @param {string}  fallbackMessage Message used when the payload has none.
		 * @return {string}
		 */
		static getErrorMessage( payload, fallbackMessage ) {
			var fallback = String( fallbackMessage || '' ).trim();

			if ( ! payload || 'object' !== typeof payload ) {
				return fallback;
			}

			if ( payload.data && 'string' === typeof payload.data.message && '' !== payload.data.message.trim() ) {
				return payload.data.message.trim();
			}

			if ( 'string' === typeof payload.message && '' !== payload.message.trim() ) {
				return payload.message.trim();
			}

			return fallback;
		}

		/**
		 * Extracts the optional settings link carried by an error payload.
		 *
		 * @param {?Object} payload Decoded response.
		 * @return {?{url: string, label: string}}
		 */
		static getSettingsLinkMeta( payload ) {
			var data  = payload && payload.data ? payload.data : null;
			var url   = data && 'string' === typeof data.settings_url ? data.settings_url.trim() : '';
			var label = data && 'string' === typeof data.settings_label ? data.settings_label.trim() : '';

			if ( '' === url ) {
				return null;
			}

			return {
				url: url,
				label: '' !== label ? label : 'Settings > Translations'
			};
		}
	}

	namespace.AjaxClient = AjaxClient;
} )( window );
