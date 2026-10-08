/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { createEnvironment } = require( './helpers/environment' );

function menuOf( env, sourceEntryId, formIndex = 0 ) {
	return env.qualityBadge( sourceEntryId, formIndex ).querySelector( '.i18nly-quality-menu' );
}

function toggleOf( env, sourceEntryId, formIndex = 0 ) {
	return env.qualityBadge( sourceEntryId, formIndex ).querySelector( '.i18nly-quality-toggle' );
}

test( 'opens the quality menu and focuses the first option', async () => {
	const env = await createEnvironment();

	toggleOf( env, 11 ).click();

	assert.equal( menuOf( env, 11 ).hidden, false );
	assert.equal( toggleOf( env, 11 ).getAttribute( 'aria-expanded' ), 'true' );
	assert.equal( env.document.activeElement, menuOf( env, 11 ).querySelector( '.i18nly-quality-option' ) );

	toggleOf( env, 11 ).click();

	assert.equal( menuOf( env, 11 ).hidden, true );
	assert.equal( toggleOf( env, 11 ).getAttribute( 'aria-expanded' ), 'false' );

	env.close();
} );

test( 'selecting an option applies the status, closes the menu and updates the payload', async () => {
	const env = await createEnvironment();

	toggleOf( env, 11 ).click();
	menuOf( env, 11 ).querySelector( '[data-quality-token="suspect"]' ).click();

	const badge = env.qualityBadge( 11 );

	assert.equal( badge.getAttribute( 'data-status-token' ), 'suspect' );
	assert.equal( badge.className, 'i18nly-entry-status i18nly-entry-status--quality i18nly-entry-status--suspect' );
	assert.equal( badge.querySelector( '.i18nly-quality-label' ).textContent, 'Suspect' );
	assert.equal( menuOf( env, 11 ).hidden, true );
	assert.equal( env.document.activeElement, toggleOf( env, 11 ) );
	assert.equal( env.payload()[ 11 ].statuses[ 0 ], 'suspect' );

	env.close();
} );

test( 'closes the menu with Escape from the toggle and from an option', async () => {
	const env = await createEnvironment();

	toggleOf( env, 11 ).click();
	toggleOf( env, 11 ).dispatchEvent( new env.window.KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } ) );

	assert.equal( menuOf( env, 11 ).hidden, true );

	toggleOf( env, 11 ).click();
	menuOf( env, 11 ).querySelector( '.i18nly-quality-option' ).dispatchEvent( new env.window.KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } ) );

	assert.equal( menuOf( env, 11 ).hidden, true );
	assert.equal( env.document.activeElement, toggleOf( env, 11 ) );

	env.close();
} );

test( 'closes the menu when focus leaves the badge', async () => {
	const env = await createEnvironment();

	toggleOf( env, 11 ).click();
	env.qualityBadge( 11 ).dispatchEvent( new env.window.FocusEvent( 'focusout', { bubbles: true, relatedTarget: env.document.body } ) );

	assert.equal( menuOf( env, 11 ).hidden, true );

	env.close();
} );

test( 'keeps the menu open when focus moves inside the badge', async () => {
	const env = await createEnvironment();

	toggleOf( env, 11 ).click();
	env.qualityBadge( 11 ).dispatchEvent( new env.window.FocusEvent( 'focusout', { bubbles: true, relatedTarget: menuOf( env, 11 ).querySelector( '.i18nly-quality-option' ) } ) );

	assert.equal( menuOf( env, 11 ).hidden, false );

	env.close();
} );

test( 'disables the quality toggle of untranslated inputs', async () => {
	const env = await createEnvironment();

	assert.equal( toggleOf( env, 14 ).disabled, true );
	assert.equal( env.qualityBadge( 14 ).getAttribute( 'data-status-token' ), '__empty__' );

	env.close();
} );

test( 'selecting an option rebuilds the payload when the input has text', async () => {
	const env = await createEnvironment();

	env.input( 14 ).value = 'Typed without event';
	toggleOf( env, 14 ).disabled = false;
	toggleOf( env, 14 ).click();
	menuOf( env, 14 ).querySelector( '[data-quality-token="validated"]' ).click();

	assert.equal( env.qualityBadge( 14 ).getAttribute( 'data-status-token' ), 'validated' );
	assert.equal( env.payload()[ 14 ].statuses[ 0 ], 'validated' );
	assert.equal( env.payload()[ 14 ].forms[ 0 ], 'Typed without event' );

	env.close();
} );

test( 'selecting an option leaves the payload untouched when the input is empty until submit', async () => {
	const env = await createEnvironment();

	toggleOf( env, 14 ).disabled = false;
	toggleOf( env, 14 ).click();
	menuOf( env, 14 ).querySelector( '[data-quality-token="validated"]' ).click();

	assert.equal( env.payload()[ 14 ].statuses[ 0 ], '' );

	env.submit();

	assert.equal( env.payload()[ 14 ].statuses[ 0 ], 'validated' );

	env.close();
} );
