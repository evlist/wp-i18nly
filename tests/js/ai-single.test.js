/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { createEnvironment } = require( './helpers/environment' );

function translateButton( env, sourceEntryId, formIndex = 0 ) {
	return env.document.querySelector( '.i18nly-translate-btn[data-for="i18nly-translation-' + sourceEntryId + '-' + formIndex + '"]' );
}

test( 'translates one input and records the AI result', async () => {
	const env = await createEnvironment(
		{
			handler: async () => ( { json: { success: true, data: { translation: 'Paramètres', review_token: 'ai_draft_suspect' } } } )
		}
	);

	translateButton( env, 14 ).click();

	assert.equal( translateButton( env, 14 ).disabled, true, 'busy while the request is pending' );
	assert.equal( translateButton( env, 14 ).getAttribute( 'aria-busy' ), 'true' );

	await env.settle();

	const [ request ] = env.requestsFor( 'i18nly_ai_translate_entry' );

	assert.deepEqual(
		request.params,
		{
			action: 'i18nly_ai_translate_entry',
			translation_id: '42',
			source_entry_id: '14',
			form_index: '0',
			source_text: 'Settings',
			witness_n: '0',
			nonce: 'translate-nonce'
		}
	);
	assert.equal( request.init.method, 'POST' );
	assert.equal( request.init.credentials, 'same-origin' );
	assert.equal( request.init.headers[ 'Content-Type' ], 'application/x-www-form-urlencoded; charset=UTF-8' );
	assert.equal( env.input( 14 ).value, 'Paramètres' );
	assert.equal( env.qualityBadge( 14 ).getAttribute( 'data-status-token' ), 'suspect', 'legacy AI review tokens are normalized' );
	assert.deepEqual( env.provenanceTokens( 14 ), [ 'ai' ] );
	assert.equal( translateButton( env, 14 ).disabled, false );
	assert.equal( translateButton( env, 14 ).hasAttribute( 'aria-busy' ), false );

	env.submit();

	assert.deepEqual(
		env.payload()[ 14 ],
		{ forms: { 0: 'Paramètres' }, statuses: { 0: 'suspect' }, used_ai: { 0: 1 }, used_manual: { 0: 0 } }
	);

	env.close();
} );

test( 'an AI result replaces the manual badge by the AI badge', async () => {
	const env = await createEnvironment(
		{
			handler: async () => ( { json: { success: true, data: { translation: 'Salut', review_token: 'draft' } } } )
		}
	);

	translateButton( env, 11 ).click();
	await env.settle();

	assert.deepEqual( env.provenanceTokens( 11 ), [ 'ai' ] );
	assert.equal( env.qualityBadge( 11 ).getAttribute( 'data-status-token' ), 'draft' );

	env.close();
} );

test( 'announces an AI result that leaves the active filters', async () => {
	const env = await createEnvironment(
		{
			handler: async () => ( { json: { success: true, data: { translation: 'Salut', review_token: 'suspect' } } } )
		}
	);

	env.setFilter( '#i18nly-filter-quality-status-suspect', false );
	translateButton( env, 14 ).click();
	await env.settle();

	assert.ok( env.document.querySelector( '.i18nly-filter-feedback' ) );
	assert.equal( env.isHidden( 14 ), false );

	env.close();
} );

test( 'shows an error dialog with the server message and settings link', async () => {
	const env = await createEnvironment(
		{
			handler: async () => (
				{
					json: {
						success: false,
						data: { message: ' DeepL quota exceeded ', settings_url: 'https://example.test/settings', settings_label: 'Open settings' }
					}
				}
			)
		}
	);

	translateButton( env, 14 ).click();
	await env.settle();

	const modal = env.document.getElementById( 'i18nly-ai-error-modal' );

	assert.ok( modal );
	assert.equal( modal.getAttribute( 'role' ), 'dialog' );
	assert.equal( env.document.getElementById( 'i18nly-ai-error-title' ).textContent, 'AI Translation Error' );
	assert.equal( env.document.getElementById( 'i18nly-ai-error-message' ).textContent, 'DeepL quota exceeded' );

	const link = env.document.querySelector( '#i18nly-ai-error-help a' );

	assert.equal( link.getAttribute( 'href' ), 'https://example.test/settings' );
	assert.equal( link.textContent, 'Open settings' );
	assert.equal( env.input( 14 ).value, '' );
	assert.equal( translateButton( env, 14 ).disabled, false );

	modal.querySelector( 'button' ).click();

	assert.equal( env.document.getElementById( 'i18nly-ai-error-modal' ), null );

	env.close();
} );

test( 'reuses the error dialog for consecutive errors', async () => {
	let calls = 0;
	const env = await createEnvironment(
		{
			handler: async () => {
				calls += 1;

				return { json: { success: false, data: { message: 'Error ' + calls } } };
			}
		}
	);

	translateButton( env, 14 ).click();
	await env.settle();
	translateButton( env, 11 ).click();
	await env.settle();

	assert.equal( env.document.querySelectorAll( '#i18nly-ai-error-modal' ).length, 1 );
	assert.equal( env.document.getElementById( 'i18nly-ai-error-message' ).textContent, 'Error 2' );
	assert.equal( env.document.querySelectorAll( '#i18nly-ai-error-help a' ).length, 0 );

	env.close();
} );

test( 'falls back to a generic message when the response has none', async () => {
	const env = await createEnvironment( { handler: async () => ( { json: { success: false } } ) } );

	translateButton( env, 14 ).click();
	await env.settle();

	assert.equal( env.document.getElementById( 'i18nly-ai-error-message' ).textContent, 'Translation failed.' );

	env.close();
} );

test( 'reports a network failure and releases the button', async () => {
	const env = await createEnvironment(
		{
			handler: async () => {
				throw new Error( 'offline' );
			}
		}
	);

	translateButton( env, 14 ).click();
	await env.settle();

	assert.equal( env.document.getElementById( 'i18nly-ai-error-message' ).textContent, 'Translation failed because the request could not be completed.' );
	assert.equal( translateButton( env, 14 ).disabled, false );

	env.close();
} );

test( 'ignores a blank server message and shows the generic one', async () => {
	const env = await createEnvironment( { handler: async () => ( { json: { success: false, data: { message: '   ' } } } ) } );

	translateButton( env, 14 ).click();
	await env.settle();

	assert.equal( env.document.getElementById( 'i18nly-ai-error-message' ).textContent, 'Translation failed.' );
	assert.deepEqual( env.alerts, [], 'the alert fallback is never needed while a message exists' );

	env.close();
} );

test( 'disables the translate buttons without a DeepL key', async () => {
	const env = await createEnvironment( { config: { hasDeeplKey: false } } );

	Array.from( env.document.querySelectorAll( '.i18nly-translate-btn' ) ).forEach(
		( button ) => {
			assert.equal( button.disabled, true );
			assert.equal( button.title, 'DeepL API key not configured' );
		}
	);

	env.close();
} );
