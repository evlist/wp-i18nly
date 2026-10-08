/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { createEnvironment } = require( './helpers/environment' );

test( 'boots by generating the POT then refreshing the entries table', async () => {
	const env = await createEnvironment();

	assert.equal( env.requestsFor( 'i18nly_generate_translation_pot' ).length, 1 );
	assert.equal( env.requestsFor( 'i18nly_get_translation_entries_table' ).length, 2 );
	assert.equal( env.requestsFor( 'i18nly_generate_translation_pot' )[ 0 ].params.nonce, 'generate-nonce' );
	assert.equal( env.requestsFor( 'i18nly_generate_translation_pot' )[ 0 ].params.translation_id, '42' );
	assert.ok( env.input( 11 ), 'the entries table is rendered in the container' );

	env.close();
} );
