/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { createEnvironment } = require( './helpers/environment' );

test( 'serializes every translation input into the hidden payload field', async () => {
	const env = await createEnvironment();

	assert.deepEqual(
		env.payload(),
		{
			11: { forms: { 0: 'Bonjour' }, statuses: { 0: 'validated' }, used_ai: { 0: 0 }, used_manual: { 0: 1 } },
			12: { forms: { 0: '%d pomme', 1: '' }, statuses: { 0: 'draft', 1: '' }, used_ai: { 0: 1, 1: 0 }, used_manual: { 0: 0, 1: 0 } },
			13: { forms: { 0: 'Hérité' }, statuses: { 0: 'suspect' }, used_ai: { 0: 1 }, used_manual: { 0: 0 } },
			14: { forms: { 0: '' }, statuses: { 0: '' }, used_ai: { 0: 0 }, used_manual: { 0: 0 } }
		}
	);

	env.close();
} );

test( 'exposes a function rebuilding the payload on demand', async () => {
	const env = await createEnvironment();

	env.input( 14 ).value = 'Direct';
	env.window.i18nlyRebuildEntriesPayload();

	assert.equal( env.payload()[ 14 ].forms[ 0 ], 'Direct' );

	env.close();
} );

test( 'typing in an empty input marks it as a manual draft', async () => {
	const env = await createEnvironment();

	env.typeInto( env.input( 14 ), 'Paramètres' );

	assert.equal( env.qualityBadge( 14 ).getAttribute( 'data-status-token' ), 'draft' );
	assert.equal( env.qualityBadge( 14 ).querySelector( '.i18nly-quality-label' ).textContent, 'Draft' );
	assert.equal( env.qualityBadge( 14 ).querySelector( '.i18nly-quality-toggle' ).disabled, false );
	assert.deepEqual( env.provenanceTokens( 14 ), [ 'manual' ] );
	assert.deepEqual(
		env.payload()[ 14 ],
		{ forms: { 0: 'Paramètres' }, statuses: { 0: 'draft' }, used_ai: { 0: 0 }, used_manual: { 0: 1 } }
	);

	env.close();
} );

test( 'typing in an input that already has a manual badge does not duplicate it', async () => {
	const env = await createEnvironment();

	env.typeInto( env.input( 14 ), 'P' );
	env.typeInto( env.input( 14 ), 'Pa' );

	assert.deepEqual( env.provenanceTokens( 14 ), [ 'manual' ] );

	env.close();
} );

test( 'programmatic input events do not add a manual badge', async () => {
	const env = await createEnvironment();

	env.input( 14 ).value = 'Programmatic';
	env.input( 14 ).dispatchEvent( new env.window.Event( 'input', { bubbles: true } ) );

	assert.deepEqual( env.provenanceTokens( 14 ), [] );
	assert.equal( env.qualityBadge( 14 ).getAttribute( 'data-status-token' ), 'draft' );

	env.close();
} );

test( 'clearing an input resets its status and removes provenance badges', async () => {
	const env = await createEnvironment();

	env.typeInto( env.input( 11 ), '' );

	assert.equal( env.qualityBadge( 11 ).getAttribute( 'data-status-token' ), '__empty__' );
	assert.equal( env.qualityBadge( 11 ).querySelector( '.i18nly-quality-label' ).textContent, ' ' );
	assert.equal( env.qualityBadge( 11 ).querySelector( '.i18nly-quality-toggle' ).disabled, true );
	assert.deepEqual( env.provenanceTokens( 11 ), [] );
	assert.deepEqual(
		env.payload()[ 11 ],
		{ forms: { 0: '' }, statuses: { 0: '' }, used_ai: { 0: 0 }, used_manual: { 0: 0 } }
	);

	env.close();
} );

test( 'editing a validated input downgrades it to draft', async () => {
	const env = await createEnvironment();

	env.typeInto( env.input( 11 ), 'Salut' );

	assert.equal( env.qualityBadge( 11 ).getAttribute( 'data-status-token' ), 'draft' );
	assert.equal( env.payload()[ 11 ].statuses[ 0 ], 'draft' );

	env.close();
} );

test( 'rebuilds the payload and syncs the referer when the form is submitted', async () => {
	const env = await createEnvironment();
	const form = env.document.getElementById( 'post' );

	env.input( 14 ).value = 'Before submit';
	env.submit();

	assert.equal( env.payload()[ 14 ].forms[ 0 ], 'Before submit' );

	const expected = env.window.location.pathname + env.window.location.search;

	assert.equal( form.querySelector( 'input[name="_wp_http_referer"]' ).value, expected );
	assert.equal( form.getAttribute( 'action' ), expected );

	env.close();
} );

test( 'does not initialize twice', async () => {
	const env = await createEnvironment();
	const before = env.requests.length;

	await env.boot();

	assert.equal( env.requests.length, before );

	env.close();
} );

test( 'does not initialize without fetch support', async () => {
	const env = await createEnvironment( { boot: false } );

	env.window.fetch = undefined;
	await env.boot();

	assert.equal( env.requests.length, 0 );
	assert.equal( env.window.i18nlyPotInitDone, undefined );

	env.close();
} );
