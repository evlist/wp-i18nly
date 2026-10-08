/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { createEnvironment } = require( './helpers/environment' );

function queryOf( env ) {
	return new env.window.URLSearchParams( env.window.location.search );
}

test( 'hides obsolete rows by default and clears their selection checkbox', async () => {
	const env = await createEnvironment();

	assert.equal( env.isHidden( 13 ), true );
	assert.equal( env.row( 13 ).getAttribute( 'aria-hidden' ), 'true' );
	assert.equal( env.isHidden( 11 ), false );
	assert.equal( env.row( 11 ).getAttribute( 'aria-hidden' ), 'false' );

	env.close();
} );

test( 'shows obsolete rows when the obsolete filter is checked', async () => {
	const env = await createEnvironment();

	env.setFilter( '#i18nly-filter-entry-status-obsolete', true );

	assert.equal( env.isHidden( 13 ), false );

	env.close();
} );

test( 'unchecking a quality filter hides rows that only have that quality', async () => {
	const env = await createEnvironment();

	env.setFilter( '#i18nly-filter-quality-status-empty', false );

	assert.equal( env.isHidden( 14 ), true );
	assert.equal( env.isHidden( 12 ), false, 'row 12 still has a draft input' );

	env.setFilter( '#i18nly-filter-quality-status-draft', false );

	assert.equal( env.isHidden( 12 ), true );
	assert.equal( env.isHidden( 11 ), false );

	env.close();
} );

test( 'filters rows by provenance', async () => {
	const env = await createEnvironment();

	env.setFilter( '#i18nly-filter-provenance-ai', false );

	assert.equal( env.isHidden( 12 ), false, 'row 12 has an input without provenance' );

	env.setFilter( '#i18nly-filter-provenance-none', false );

	assert.equal( env.isHidden( 12 ), true );
	assert.equal( env.isHidden( 14 ), true );
	assert.equal( env.isHidden( 11 ), false );

	env.close();
} );

test( 'unselects a row checkbox when the row gets hidden', async () => {
	const env = await createEnvironment();

	env.selectRow( 14 );
	env.setFilter( '#i18nly-filter-quality-status-empty', false );

	assert.equal( env.row( 14 ).querySelector( '.i18nly-entry-checkbox' ).checked, false );

	env.close();
} );

test( 'persists the filters to the query string and keeps unrelated parameters', async () => {
	const env = await createEnvironment();
	const query = queryOf( env );

	assert.equal( query.get( 'post' ), '42' );
	assert.equal( query.get( 'action' ), 'edit' );
	assert.equal( query.get( 'i18nly_filter_entries' ), 'active' );
	assert.equal( query.get( 'i18nly_filter_statuses' ), 'suspect,draft,validated,empty' );
	assert.equal( query.get( 'i18nly_filter_provenance' ), 'ai,manual,none' );
	assert.equal( query.get( 'i18nly_filter_search_fields' ), 'source,translated' );
	assert.equal( query.has( 'i18nly_filter_search' ), false );

	env.setFilter( '#i18nly-filter-quality-status-draft', false );

	assert.equal( queryOf( env ).get( 'i18nly_filter_statuses' ), 'suspect,validated,empty' );

	env.close();
} );

test( 'writes the none token when every checkbox of a group is unchecked', async () => {
	const env = await createEnvironment();

	env.setFilter( '#i18nly-filter-provenance-ai', false );
	env.setFilter( '#i18nly-filter-provenance-manual', false );
	env.setFilter( '#i18nly-filter-provenance-none', false );

	assert.equal( queryOf( env ).get( 'i18nly_filter_provenance' ), '__none__' );
	assert.equal( env.isHidden( 11 ), true );

	env.close();
} );

test( 'restores filters and search from the query string', async () => {
	const env = await createEnvironment(
		{
			search: '?post=42&action=edit&i18nly_filter_entries=active,obsolete&i18nly_filter_search=hel&i18nly_filter_search_fields=source'
		}
	);

	assert.equal( env.document.getElementById( 'i18nly-filter-entry-status-obsolete' ).checked, true );
	assert.equal( env.document.getElementById( 'i18nly-filter-search-text' ).value, 'hel' );
	assert.equal( env.document.getElementById( 'i18nly-filter-search-source' ).checked, true );
	assert.equal( env.document.getElementById( 'i18nly-filter-search-translated' ).checked, false );
	assert.equal( env.isHidden( 11 ), false );
	assert.equal( env.isHidden( 12 ), true );
	assert.equal( env.isHidden( 13 ), true );
	assert.equal( env.isHidden( 14 ), true );

	env.close();
} );

test( 'restores the none token as an empty selection and ignores unknown values', async () => {
	const env = await createEnvironment(
		{
			search: '?post=42&action=edit&i18nly_filter_statuses=__none__&i18nly_filter_provenance=ai,bogus'
		}
	);

	assert.equal( env.document.querySelectorAll( '.i18nly-filter-quality-status:checked' ).length, 0 );
	assert.deepEqual(
		Array.from( env.document.querySelectorAll( '.i18nly-filter-provenance:checked' ) ).map( ( box ) => box.value ),
		[ 'ai' ]
	);
	assert.equal( env.isHidden( 11 ), true );

	env.close();
} );

test( 'searches source and translated texts case-insensitively', async () => {
	const env = await createEnvironment();
	const search = env.document.getElementById( 'i18nly-filter-search-text' );

	search.value = 'BONJ';
	search.dispatchEvent( new env.window.Event( 'input', { bubbles: true } ) );

	assert.equal( env.isHidden( 11 ), false );
	assert.equal( env.isHidden( 12 ), true );
	assert.equal( queryOf( env ).get( 'i18nly_filter_search' ), 'BONJ' );

	env.setFilter( '#i18nly-filter-search-translated', false );

	assert.equal( env.isHidden( 11 ), true, 'translated text is no longer searched' );

	search.value = 'hello';
	search.dispatchEvent( new env.window.Event( 'input', { bubbles: true } ) );

	assert.equal( env.isHidden( 11 ), false, 'source text is still searched' );

	env.close();
} );

test( 'matches nothing when search text is set without any search field', async () => {
	const env = await createEnvironment();
	const search = env.document.getElementById( 'i18nly-filter-search-text' );

	env.setFilter( '#i18nly-filter-search-source', false );
	env.setFilter( '#i18nly-filter-search-translated', false );
	search.value = 'hello';
	search.dispatchEvent( new env.window.Event( 'input', { bubbles: true } ) );

	assert.equal( env.isHidden( 11 ), true );
	assert.equal( env.isHidden( 12 ), true );

	search.value = '';
	search.dispatchEvent( new env.window.Event( 'input', { bubbles: true } ) );

	assert.equal( env.isHidden( 11 ), false );

	env.close();
} );

test( 'a status change keeps a modified row visible and announces it', async () => {
	const env = await createEnvironment();

	env.setFilter( '#i18nly-filter-quality-status-suspect', false );

	const toggle = env.qualityBadge( 11 ).querySelector( '.i18nly-quality-toggle' );

	toggle.click();
	env.qualityBadge( 11 ).querySelector( '[data-quality-token="suspect"]' ).click();

	const notice = env.document.querySelector( '.i18nly-filter-feedback' );

	assert.ok( notice, 'a notice is displayed' );
	assert.equal( notice.hidden, false );
	assert.equal( notice.querySelector( '.i18nly-filter-feedback-text' ).textContent, '1 modified row no longer matches active filters.' );
	assert.equal( env.isHidden( 11 ), false, 'the modified row stays visible' );
	assert.equal( notice.previousElementSibling.classList.contains( 'i18nly-entry-filters' ), true );

	env.close();
} );

test( 'the only-modified toggle shows just the modified rows', async () => {
	const env = await createEnvironment();

	env.setFilter( '#i18nly-filter-quality-status-suspect', false );
	env.qualityBadge( 11 ).querySelector( '.i18nly-quality-toggle' ).click();
	env.qualityBadge( 11 ).querySelector( '[data-quality-token="suspect"]' ).click();

	const toggle = env.document.querySelector( '.i18nly-filter-feedback-only-modified' );

	toggle.checked = true;
	toggle.dispatchEvent( new env.window.Event( 'change', { bubbles: true } ) );

	assert.equal( env.isHidden( 11 ), false );
	assert.equal( env.isHidden( 12 ), true );
	assert.equal( env.isHidden( 14 ), true );

	toggle.checked = false;
	toggle.dispatchEvent( new env.window.Event( 'change', { bubbles: true } ) );

	assert.equal( env.isHidden( 12 ), false );

	env.close();
} );

test( 'applying the filters hides the modified row and removes the notice', async () => {
	const env = await createEnvironment();

	env.setFilter( '#i18nly-filter-quality-status-suspect', false );
	env.qualityBadge( 11 ).querySelector( '.i18nly-quality-toggle' ).click();
	env.qualityBadge( 11 ).querySelector( '[data-quality-token="suspect"]' ).click();

	env.document.querySelector( '.i18nly-filter-feedback-apply' ).click();

	assert.equal( env.isHidden( 11 ), true );
	assert.equal( env.document.querySelector( '.i18nly-filter-feedback' ), null );

	env.close();
} );

test( 'a modified row that still matches does not trigger a notice', async () => {
	const env = await createEnvironment();

	env.qualityBadge( 11 ).querySelector( '.i18nly-quality-toggle' ).click();
	env.qualityBadge( 11 ).querySelector( '[data-quality-token="draft"]' ).click();

	assert.equal( env.document.querySelector( '.i18nly-filter-feedback' ), null );

	env.close();
} );

test( 'the notice uses the localized labels', async () => {
	const env = await createEnvironment(
		{
			translations: { 'Show only these modified rows': 'Seulement les lignes modifiées', 'Apply filters and close': 'Appliquer' }
		}
	);

	env.setFilter( '#i18nly-filter-quality-status-suspect', false );
	env.qualityBadge( 11 ).querySelector( '.i18nly-quality-toggle' ).click();
	env.qualityBadge( 11 ).querySelector( '[data-quality-token="suspect"]' ).click();

	assert.equal( env.document.querySelector( '.i18nly-filter-feedback-toggle' ).textContent.trim(), 'Seulement les lignes modifiées' );
	assert.equal( env.document.querySelector( '.i18nly-filter-feedback-apply' ).textContent, 'Appliquer' );

	env.close();
} );
