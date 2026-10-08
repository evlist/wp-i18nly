/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { createEnvironment } = require( './helpers/environment' );

function batchResponse( params, extra = {} ) {
	const items = JSON.parse( params.items_json );

	return {
		json: {
			success: true,
			data: Object.assign(
				{
					results: items.map(
						( item ) => ( {
							success: true,
							source_entry_id: item.source_entry_id,
							form_index: item.form_index,
							translation: 'T-' + item.source_entry_id + '-' + item.form_index,
							review_token: 'draft'
						} )
					)
				},
				extra
			)
		}
	};
}

function deferred() {
	let resolve;
	const promise = new Promise( ( done ) => {
		resolve = done;
	} );

	return { promise, resolve };
}

test( 'translates the selected rows in batches and applies the results', async () => {
	const env = await createEnvironment(
		{
			handler: async ( params ) => batchResponse( params, { usage_html: '<div class="i18nly-deepl-usage-box"><span>new usage</span></div>' } )
		}
	);

	env.selectRow( 11 );
	env.selectRow( 12 );
	env.selectRow( 14 );
	env.runBulkAction( 'ai_translate_selected' );
	await env.settle( 30 );

	const batches = env.requestsFor( 'i18nly_ai_translate_entry' );

	assert.equal( batches.length, 2, 'four inputs with a batch size of two' );
	assert.deepEqual( JSON.parse( batches[ 0 ].params.items_json ), [
		{ source_entry_id: 11, form_index: 0, source_text: 'Hello', witness_n: '0' },
		{ source_entry_id: 12, form_index: 0, source_text: '%d apple', witness_n: '1' }
	] );
	assert.equal( batches[ 0 ].params.batch_index, '1' );
	assert.equal( batches[ 0 ].params.total_batches, '2' );
	assert.equal( batches[ 0 ].params.translation_id, '42' );
	assert.equal( batches[ 0 ].params.nonce, 'translate-nonce' );
	assert.deepEqual(
		JSON.parse( batches[ 1 ].params.items_json ).map( ( item ) => [ item.source_entry_id, item.form_index ] ),
		[ [ 12, 1 ], [ 14, 0 ] ]
	);

	assert.equal( env.input( 11 ).value, 'T-11-0' );
	assert.equal( env.input( 12, 1 ).value, 'T-12-1' );
	assert.equal( env.input( 14 ).value, 'T-14-0' );
	assert.deepEqual( env.provenanceTokens( 11 ), [ 'ai' ] );
	assert.equal( env.qualityBadge( 14 ).getAttribute( 'data-status-token' ), 'draft' );
	assert.equal( env.document.getElementById( 'i18nly-progress-modal' ), null, 'the progress modal closes when done' );
	assert.equal( env.document.querySelector( '.i18nly-deepl-usage-box' ).textContent, 'new usage' );
	assert.equal( env.document.querySelectorAll( '.i18nly-translate-btn[aria-busy]' ).length, 0 );

	env.close();
} );

test( 'shows progress while a batch is pending', async () => {
	const gate = deferred();
	const env = await createEnvironment(
		{
			handler: async ( params ) => {
				await gate.promise;

				return batchResponse( params );
			}
		}
	);

	env.selectRow( 14 );
	env.runBulkAction( 'ai_translate_selected' );
	await env.settle();

	const modal = env.document.getElementById( 'i18nly-progress-modal' );

	assert.ok( modal );
	assert.equal( modal.getAttribute( 'role' ), 'dialog' );
	assert.equal( env.document.getElementById( 'i18nly-progress-title' ).textContent, 'AI Translation in Progress' );
	assert.equal( env.document.getElementById( 'i18nly-progress-text' ).textContent, 'Processing batch 1 of 1' );
	assert.equal( env.document.getElementById( 'i18nly-progress-fill' ).style.width, '0%' );
	assert.equal( env.document.querySelector( '.i18nly-translate-btn[data-for="i18nly-translation-14-0"]' ).getAttribute( 'aria-busy' ), 'true' );
	assert.equal( env.document.querySelector( '.i18nly-progress-close' ).style.display, 'none' );

	gate.resolve();
	await env.settle( 30 );

	assert.equal( env.document.getElementById( 'i18nly-progress-modal' ), null );
	assert.equal( env.input( 14 ).value, 'T-14-0' );

	env.close();
} );

test( 'cancelling stops further batches and ignores the pending response', async () => {
	const gate = deferred();
	const env = await createEnvironment(
		{
			handler: async ( params ) => {
				await gate.promise;

				return batchResponse( params );
			}
		}
	);

	env.selectRow( 11 );
	env.selectRow( 12 );
	env.selectRow( 14 );
	env.runBulkAction( 'ai_translate_selected' );
	await env.settle();

	env.document.querySelector( '.i18nly-progress-cancel' ).click();

	assert.equal( env.document.getElementById( 'i18nly-progress-text' ).textContent, 'Translation cancelled.' );
	assert.equal( env.document.querySelectorAll( '.i18nly-translate-btn[aria-busy]' ).length, 0, 'buttons are released immediately' );

	gate.resolve();
	await env.settle( 30 );

	assert.equal( env.requestsFor( 'i18nly_ai_translate_entry' ).length, 1 );
	assert.equal( env.input( 11 ).value, 'Bonjour', 'the late response is not applied' );
	assert.equal( env.document.getElementById( 'i18nly-progress-modal' ), null );

	env.close();
} );

test( 'retries a rate-limited batch', async () => {
	let calls = 0;
	const env = await createEnvironment(
		{
			handler: async ( params ) => {
				calls += 1;

				if ( 1 === calls ) {
					return { status: 429, json: { success: false, data: { retry_after_ms: 2000 } } };
				}

				return batchResponse( params );
			}
		}
	);

	env.selectRow( 14 );
	env.runBulkAction( 'ai_translate_selected' );
	await env.settle( 30 );

	assert.equal( env.requestsFor( 'i18nly_ai_translate_entry' ).length, 2 );
	assert.equal( env.input( 14 ).value, 'T-14-0' );

	env.close();
} );

test( 'announces the retry delay while waiting for a rate-limited batch', async () => {
	let calls = 0;
	const env = await createEnvironment(
		{
			fastTimers: false,
			handler: async ( params ) => {
				calls += 1;

				if ( 1 === calls ) {
					return { status: 429, json: { success: false, data: { retry_after_ms: 1500 } } };
				}

				return batchResponse( params );
			}
		}
	);

	env.selectRow( 14 );
	env.runBulkAction( 'ai_translate_selected' );
	await env.settle();

	assert.equal( env.document.getElementById( 'i18nly-progress-text' ).textContent, 'Too many requests. Retrying batch 1 of 1 in 2s...' );

	env.document.querySelector( '.i18nly-progress-cancel' ).click();
	env.close();
} );

test( 'stops with an error dialog when the batch response is invalid', async () => {
	const env = await createEnvironment(
		{
			handler: async () => ( { json: { success: false, data: { message: 'Batch rejected', settings_url: 'https://example.test/settings' } } } )
		}
	);

	env.selectRow( 11 );
	env.selectRow( 12 );
	env.runBulkAction( 'ai_translate_selected' );
	await env.settle( 30 );

	assert.equal( env.requestsFor( 'i18nly_ai_translate_entry' ).length, 1, 'no further batch after a failure' );
	assert.equal( env.document.getElementById( 'i18nly-progress-modal' ), null );
	assert.equal( env.document.getElementById( 'i18nly-ai-error-message' ).textContent, 'Batch rejected' );
	assert.equal( env.document.querySelector( '#i18nly-ai-error-help a' ).getAttribute( 'href' ), 'https://example.test/settings' );
	assert.equal( env.document.querySelectorAll( '.i18nly-translate-btn[aria-busy]' ).length, 0 );

	env.close();
} );

test( 'stops with a message when the batch request fails', async () => {
	const env = await createEnvironment(
		{
			handler: async () => {
				throw new Error( 'offline' );
			}
		}
	);

	env.selectRow( 14 );
	env.runBulkAction( 'ai_translate_selected' );
	await env.settle( 30 );

	assert.equal( env.document.getElementById( 'i18nly-ai-error-message' ).textContent, 'Translation stopped because the batch request failed.' );
	assert.equal( env.document.getElementById( 'i18nly-progress-modal' ), null );
	assert.equal( env.document.querySelectorAll( '.i18nly-translate-btn[aria-busy]' ).length, 0 );

	env.close();
} );

test( 'skips results that failed or match no requested item', async () => {
	const env = await createEnvironment(
		{
			handler: async () => (
				{
					json: {
						success: true,
						data: {
							results: [
								{ success: false, source_entry_id: 14, form_index: 0, translation: 'ignored' },
								{ success: true, source_entry_id: 99, form_index: 0, translation: 'unknown' }
							]
						}
					}
				}
			)
		}
	);

	env.selectRow( 14 );
	env.runBulkAction( 'ai_translate_selected' );
	await env.settle( 30 );

	assert.equal( env.input( 14 ).value, '' );
	assert.equal( env.document.getElementById( 'i18nly-progress-modal' ), null );

	env.close();
} );

test( 'translates one input at a time when no batch action is configured', async () => {
	const env = await createEnvironment(
		{
			config: { translateBatchAction: '', translateBatchNonce: '' },
			handler: async ( params ) => ( { json: { success: true, data: { translation: 'S-' + params.source_entry_id, review_token: 'draft' } } } )
		}
	);

	env.selectRow( 11 );
	env.selectRow( 14 );
	env.runBulkAction( 'ai_translate_selected' );
	await env.settle( 40 );

	const requests = env.requestsFor( 'i18nly_ai_translate_entry' );

	assert.equal( requests.length, 2 );
	assert.equal( requests[ 0 ].params.items_json, undefined );
	assert.equal( env.input( 11 ).value, 'S-11' );
	assert.equal( env.input( 14 ).value, 'S-14' );

	env.close();
} );

test( 'does nothing without a DeepL key or without selected rows', async () => {
	const withoutKey = await createEnvironment( { config: { hasDeeplKey: false } } );

	withoutKey.selectRow( 14 );
	withoutKey.runBulkAction( 'ai_translate_selected' );
	await withoutKey.settle();

	assert.equal( withoutKey.requestsFor( 'i18nly_ai_translate_entry' ).length, 0 );
	assert.equal( withoutKey.document.getElementById( 'i18nly-progress-modal' ), null );
	withoutKey.close();

	const noSelection = await createEnvironment();

	noSelection.runBulkAction( 'ai_translate_selected' );
	await noSelection.settle();

	assert.equal( noSelection.requestsFor( 'i18nly_ai_translate_entry' ).length, 0 );
	noSelection.close();
} );

test( 'caps the batch size to the maximum items per request', async () => {
	const env = await createEnvironment(
		{
			config: { translateBatchSize: 40, translateMaxItemsPerRequest: 3 },
			handler: async ( params ) => batchResponse( params )
		}
	);

	env.selectRow( 11 );
	env.selectRow( 12 );
	env.selectRow( 14 );
	env.runBulkAction( 'ai_translate_selected' );
	await env.settle( 30 );

	const batches = env.requestsFor( 'i18nly_ai_translate_entry' );

	assert.equal( batches.length, 2 );
	assert.equal( JSON.parse( batches[ 0 ].params.items_json ).length, 3 );

	env.close();
} );
