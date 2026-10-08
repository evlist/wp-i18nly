/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { createEnvironment } = require( './helpers/environment' );

function applyButtons( env ) {
	return Array.from( env.document.querySelectorAll( '.i18nly-bulk-apply' ) );
}

test( 'enables the apply buttons only with a selection and an action', async () => {
	const env = await createEnvironment();
	const select = env.document.querySelector( '.bulkactions .i18nly-bulk-action-selector' );
	const button = env.document.querySelector( '.bulkactions .i18nly-bulk-apply' );

	assert.equal( button.disabled, true );

	env.selectRow( 11 );

	assert.equal( button.disabled, true, 'no action chosen yet' );

	select.value = 'mark_as_draft';
	select.dispatchEvent( new env.window.Event( 'change', { bubbles: true } ) );

	assert.equal( button.disabled, false );
	assert.equal( button.hasAttribute( 'aria-disabled' ), false );

	env.selectRow( 11, false );

	assert.equal( button.disabled, true );
	assert.equal( button.getAttribute( 'aria-disabled' ), 'true' );

	env.close();
} );

test( 'the select-all checkbox selects visible rows only and tracks partial selection', async () => {
	const env = await createEnvironment();
	const selectAll = env.document.querySelector( '.i18nly-bulk-select-all' );

	selectAll.checked = true;
	selectAll.dispatchEvent( new env.window.Event( 'change', { bubbles: true } ) );

	const checked = Array.from( env.document.querySelectorAll( '.i18nly-entry-checkbox' ) ).filter( ( box ) => box.checked ).map( ( box ) => box.value );

	assert.deepEqual( checked, [ '11', '12', '14' ], 'the hidden obsolete row is skipped' );
	assert.equal( selectAll.indeterminate, true, 'one row is not selected' );

	env.setFilter( '#i18nly-filter-entry-status-obsolete', true );
	env.selectRow( 13 );

	assert.equal( selectAll.checked, true );
	assert.equal( selectAll.indeterminate, false );

	env.selectRow( 11, false );

	assert.equal( selectAll.checked, false );
	assert.equal( selectAll.indeterminate, true );

	env.close();
} );

test( 'does nothing when applying without an action', async () => {
	const env = await createEnvironment();
	const before = env.payload();

	env.selectRow( 11 );
	env.document.querySelector( '.bulkactions .i18nly-bulk-apply' ).click();

	assert.deepEqual( env.payload(), before );

	env.close();
} );

test( 'marks the selected rows with a quality status and clears empty inputs', async () => {
	const env = await createEnvironment();

	env.selectRow( 12 );
	env.selectRow( 14 );
	env.runBulkAction( 'mark_as_validated' );

	assert.equal( env.qualityBadge( 12, 0 ).getAttribute( 'data-status-token' ), 'validated' );
	assert.equal( env.qualityBadge( 12, 1 ).getAttribute( 'data-status-token' ), '__empty__', 'empty input gets no status' );
	assert.equal( env.qualityBadge( 14 ).getAttribute( 'data-status-token' ), '__empty__' );
	assert.equal( env.qualityBadge( 11 ).getAttribute( 'data-status-token' ), 'validated', 'unselected row is untouched' );
	assert.equal( env.payload()[ 12 ].statuses[ 0 ], 'validated' );

	env.close();
} );

test( 'marks the selected rows as suspect and draft', async () => {
	const env = await createEnvironment();

	env.selectRow( 11 );
	env.runBulkAction( 'mark_as_suspect' );

	assert.equal( env.qualityBadge( 11 ).getAttribute( 'data-status-token' ), 'suspect' );

	env.runBulkAction( 'mark_as_draft' );

	assert.equal( env.qualityBadge( 11 ).getAttribute( 'data-status-token' ), 'draft' );

	env.close();
} );

test( 'copies the source text into the selected translations and drops provenance', async () => {
	const env = await createEnvironment();

	env.selectRow( 12 );
	env.runBulkAction( 'copy_source_to_translation' );

	assert.equal( env.input( 12, 0 ).value, '%d apple' );
	assert.equal( env.input( 12, 1 ).value, '%d apples' );
	assert.deepEqual( env.provenanceTokens( 12, 0 ), [] );
	assert.equal( env.qualityBadge( 12, 1 ).getAttribute( 'data-status-token' ), 'draft' );
	assert.deepEqual(
		env.payload()[ 12 ],
		{ forms: { 0: '%d apple', 1: '%d apples' }, statuses: { 0: 'draft', 1: 'draft' }, used_ai: { 0: 0, 1: 0 }, used_manual: { 0: 0, 1: 0 } }
	);

	env.close();
} );

test( 'clears the selected translations', async () => {
	const env = await createEnvironment();

	env.selectRow( 11 );
	env.runBulkAction( 'clear_selected_translations' );

	assert.equal( env.input( 11 ).value, '' );
	assert.equal( env.qualityBadge( 11 ).getAttribute( 'data-status-token' ), '__empty__' );
	assert.deepEqual( env.provenanceTokens( 11 ), [] );
	assert.equal( env.payload()[ 11 ].forms[ 0 ], '' );

	env.close();
} );

test( 'keeps the bottom toolbar in sync with the selection', async () => {
	const env = await createEnvironment();
	const bottomSelect = env.document.querySelector( '.tablenav.bottom .i18nly-bulk-action-selector' );
	const bottomButton = env.document.querySelector( '.tablenav.bottom .i18nly-bulk-apply' );

	env.selectRow( 11 );
	bottomSelect.value = 'mark_as_draft';
	bottomSelect.dispatchEvent( new env.window.Event( 'change', { bubbles: true } ) );

	assert.equal( bottomButton.disabled, false );
	assert.equal( applyButtons( env )[ 0 ].disabled, true, 'top toolbar has no action chosen' );

	bottomButton.click();

	assert.equal( env.qualityBadge( 11 ).getAttribute( 'data-status-token' ), 'draft' );

	env.close();
} );

test( 'marking rows as modified announces the rows that leave the filters', async () => {
	const env = await createEnvironment();

	env.setFilter( '#i18nly-filter-quality-status-validated', false );
	env.selectRow( 14 );
	env.input( 14 ).value = 'Réglages';
	env.runBulkAction( 'mark_as_validated' );

	assert.ok( env.document.querySelector( '.i18nly-filter-feedback' ) );
	assert.equal( env.isHidden( 14 ), false );

	env.close();
} );
