/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * jsdom environment reproducing the translation edit screen.
 */

'use strict';

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { JSDOM } = require( 'jsdom' );

const pluginDirectory = path.resolve( __dirname, '../../../plugin' );
const fixturesDirectory = path.resolve( __dirname, '../fixtures' );
const scripts = require( './scripts' );

const defaultConfig = {
	ajaxUrl: '/wp-admin/admin-ajax.php',
	translationId: 42,
	generateAction: 'i18nly_generate_translation_pot',
	generateNonce: 'generate-nonce',
	refreshAction: 'i18nly_get_translation_entries_table',
	refreshNonce: 'refresh-nonce',
	tableContainerId: 'i18nly-source-entries-table',
	contentTypeHeader: 'application/x-www-form-urlencoded; charset=UTF-8',
	translateAction: 'i18nly_ai_translate_entry',
	translateNonce: 'translate-nonce',
	translateBatchAction: 'i18nly_ai_translate_entry',
	translateBatchNonce: 'translate-nonce',
	hasDeeplKey: true,
	translateBatchSize: 2,
	translateMaxItemsPerRequest: 50,
	translateBackoffBaseMs: 1000,
	translateMaxConcurrentBatches: 1
};

function readFixture( name ) {
	return fs.readFileSync( path.join( fixturesDirectory, name ), 'utf8' );
}

function parseBody( body ) {
	const params = {};

	new URLSearchParams( String( body || '' ) ).forEach(
		( value, key ) => {
			params[ key ] = value;
		}
	);

	return params;
}

/**
 * Lets pending promise callbacks and zero-delay timers run (one real timer tick per round).
 *
 * @param {number} rounds Number of macrotask rounds.
 * @return {Promise<void>}
 */
async function settle( rounds = 12 ) {
	for ( let index = 0; index < rounds; index++ ) {
		await new Promise( ( resolve ) => setTimeout( resolve, 1 ) );
	}
}

/**
 * Creates the edit screen, evaluates the admin scripts and waits for the initial requests.
 *
 * @param {Object}   options Options.
 * @param {Object}   options.config Config overrides.
 * @param {string}   options.search Query string of the page URL.
 * @param {Function} options.handler Handler receiving (params, request) for non-default actions.
 * @param {boolean}  options.boot Whether to evaluate the scripts immediately.
 * @return {Promise<Object>}
 */
async function createEnvironment( options = {} ) {
	const tableHtml = readFixture( 'entries-table.html' );
	const pageHtml = readFixture( 'edit-screen.html' ).replace( '__TABLE__', '<p>Loading translation entries…</p>' );
	const search = options.search || '?post=42&action=edit';
	const dom = new JSDOM(
		'<!doctype html><html><body><form id="post" method="post" action="/wp-admin/post.php">'
			+ '<input type="hidden" name="_wp_http_referer" value="/wp-admin/post.php?post=42&action=edit" />'
			+ pageHtml
			+ '</form><div class="i18nly-deepl-usage-box" id="usage-box"><span>old usage</span></div></body></html>',
		{
			url: 'https://example.test/wp-admin/post.php' + search,
			runScripts: 'outside-only',
			pretendToBeVisual: true
		}
	);
	const window = dom.window;
	const requests = [];
	const alerts = [];
	const config = Object.assign( {}, defaultConfig, options.config || {} );

	// Installs a wp.i18n that translates with a dictionary, as WordPress does with the script translations.
	if ( options.translations ) {
		const dictionary = options.translations;

		window.wp = {
			i18n: {
				__: ( text ) => dictionary[ text ] || text,
				_n: ( singular, plural, number ) => dictionary[ 1 === number ? singular : plural ] || ( 1 === number ? singular : plural ),
				sprintf: ( format, ...values ) => String( format ).replace( /%(?:(\d+)\$)?[sd]/g, ( match, index ) => String( values[ index ? Number( index ) - 1 : 0 ] ) )
			}
		};
	}

	// Lets tests dispatch input events that the scripts see as user-initiated.
	const originalAddEventListener = window.EventTarget.prototype.addEventListener;
	window.EventTarget.prototype.addEventListener = function ( type, listener, listenerOptions ) {
		if ( 'input' === type && 'function' === typeof listener ) {
			const wrapped = function ( event ) {
				if ( event && event.__userInitiated ) {
					const trustedEvent = new Proxy(
						event,
						{
							get( target, key ) {
								if ( 'isTrusted' === key ) {
									return true;
								}

								const value = Reflect.get( target, key, target );

								return 'function' === typeof value ? value.bind( target ) : value;
							}
						}
					);

					return listener.call( this, trustedEvent );
				}

				return listener.call( this, event );
			};

			return originalAddEventListener.call( this, type, wrapped, listenerOptions );
		}

		return originalAddEventListener.call( this, type, listener, listenerOptions );
	};

	window.alert = ( message ) => {
		alerts.push( String( message ) );
	};

	window.fetch = async ( url, init ) => {
		const params = parseBody( init && init.body );
		const request = { url: String( url ), init: init || {}, params };

		requests.push( request );

		let result;

		if ( params.action === config.generateAction ) {
			result = { json: { success: true, data: {} } };
		} else if ( params.action === config.refreshAction ) {
			result = { json: { success: true, data: { html: options.tableHtml || tableHtml } } };
		} else if ( options.handler ) {
			result = await options.handler( params, request );
		}

		result = result || { json: { success: false } };

		const status = result.status || 200;
		const text = undefined !== result.text ? result.text : JSON.stringify( result.json );

		return {
			ok: status >= 200 && status < 300,
			status,
			json: async () => JSON.parse( text ),
			text: async () => text
		};
	};

	if ( options.fastTimers !== false ) {
		const originalSetTimeout = window.setTimeout.bind( window );

		window.setTimeout = ( callback, delay, ...rest ) => originalSetTimeout( callback, 0, ...rest );
	}

	const environment = {
		window,
		document: window.document,
		config,
		requests,
		alerts,
		settle,
		boot() {
			window.i18nlyTranslationEditConfig = config;

			scripts.forEach(
				( relativePath ) => {
					window.eval( fs.readFileSync( path.join( pluginDirectory, relativePath ), 'utf8' ) );
				}
			);

			return settle();
		},
		/** Returns requests posted for one action. */
		requestsFor( action ) {
			return requests.filter( ( request ) => request.params.action === action );
		},
		/** Simulates the user typing in one translation input. */
		typeInto( input, value ) {
			input.value = value;

			const event = new window.Event( 'input', { bubbles: true } );

			event.__userInitiated = true;
			input.dispatchEvent( event );
		},
		/** Returns the row element of one source entry. */
		row( sourceEntryId ) {
			const checkbox = window.document.querySelector( '.i18nly-entry-checkbox[value="' + sourceEntryId + '"]' );

			return checkbox ? checkbox.closest( 'tr' ) : null;
		},
		/** Returns one translation input. */
		input( sourceEntryId, formIndex = 0 ) {
			return window.document.getElementById( 'i18nly-translation-' + sourceEntryId + '-' + formIndex );
		},
		/** Returns the quality badge of one input. */
		qualityBadge( sourceEntryId, formIndex = 0 ) {
			return window.document.querySelector( '.i18nly-entry-status--quality[data-for="i18nly-translation-' + sourceEntryId + '-' + formIndex + '"]' );
		},
		/** Returns the provenance tokens displayed for one input. */
		provenanceTokens( sourceEntryId, formIndex = 0 ) {
			return Array.from(
				window.document.querySelectorAll( '[data-for="i18nly-translation-' + sourceEntryId + '-' + formIndex + '"][data-provenance-token]' )
			).map( ( badge ) => badge.getAttribute( 'data-provenance-token' ) );
		},
		/** Returns the decoded hidden payload posted with the form. */
		payload() {
			const field = window.document.querySelector( 'input[name="i18nly_translation_entries_payload"]' );

			return field ? JSON.parse( field.value ) : null;
		},
		/** Returns whether one row is currently hidden by the filters. */
		isHidden( sourceEntryId ) {
			return 'none' === this.row( sourceEntryId ).style.display;
		},
		/** Toggles one filter checkbox. */
		setFilter( selector, checked ) {
			const checkbox = window.document.querySelector( selector );

			checkbox.checked = checked;
			checkbox.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
		},
		/** Checks one row selection checkbox. */
		selectRow( sourceEntryId, checked = true ) {
			const checkbox = window.document.querySelector( '.i18nly-entry-checkbox[value="' + sourceEntryId + '"]' );

			checkbox.checked = checked;
			checkbox.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
		},
		/** Runs one bulk action from the top toolbar. */
		runBulkAction( action ) {
			const select = window.document.querySelector( '.bulkactions .i18nly-bulk-action-selector' );
			const button = window.document.querySelector( '.bulkactions .i18nly-bulk-apply' );

			select.value = action;
			select.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
			button.click();
		},
		/** Submits the post form. */
		submit() {
			window.document.getElementById( 'post' ).dispatchEvent( new window.Event( 'submit', { bubbles: true, cancelable: true } ) );
		},
		close() {
			window.close();
		}
	};

	if ( options.boot !== false ) {
		await environment.boot();
	}

	return environment;
}

module.exports = { createEnvironment, settle, defaultConfig };
