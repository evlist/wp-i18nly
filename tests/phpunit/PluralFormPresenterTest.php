<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Plural form presenter and entry status badges tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\Admin\UI\EntryStatusBadges;
use WP_I18nly\Admin\UI\PluralFormPresenter;

/**
 * Tests the helpers extracted from the translation entries list table.
 */
class PluralFormPresenterTest extends TestCase {
	/**
	 * Prefers the locale form metadata over the flat lists, then falls back.
	 *
	 * @return void
	 */
	public function test_texts_come_from_form_metadata_then_flat_lists_then_fallbacks() {
		$presenter = new PluralFormPresenter(
			array(
				array(
					'label'   => 'one',
					'marker'  => '1',
					'tooltip' => 'One tooltip',
				),
				array(
					'label'   => '   ',
					'marker'  => '',
				),
			),
			array( 'ignored', 'other' ),
			array( 'x', 'n' ),
			array( 'ignored tooltip', 'Other tooltip' )
		);

		$this->assertSame( 'one', $presenter->label( 0 ) );
		$this->assertSame( '1', $presenter->marker( 0 ) );
		$this->assertSame( 'One tooltip', $presenter->tooltip( 0 ) );
		$this->assertSame( 'other', $presenter->label( 1 ), 'A blank label falls back to the flat list.' );
		$this->assertSame( 'n', $presenter->marker( 1 ) );
		$this->assertSame( 'Other tooltip', $presenter->tooltip( 1 ) );
		$this->assertSame( '2', $presenter->label( 2 ), 'Without any source the label is the form index.' );
		$this->assertSame( '2', $presenter->marker( 2 ) );
		$this->assertSame( '', $presenter->tooltip( 2 ) );
	}

	/**
	 * Builds a presenter from an entry row and tolerates missing metadata.
	 *
	 * @return void
	 */
	public function test_from_item_reads_the_form_metadata_of_a_row() {
		$presenter = PluralFormPresenter::from_item(
			array(
				'form_labels'  => array(
					3 => 'ignored shifted key',
					'few',
				),
				'form_markers' => 'not-a-list',
			)
		);

		$this->assertSame( 'ignored shifted key', $presenter->label( 0 ), 'Lists are re-indexed.' );
		$this->assertSame( 'few', $presenter->label( 1 ) );
		$this->assertSame( '0', $presenter->marker( 0 ) );
		$this->assertSame( '', PluralFormPresenter::from_item( array() )->tooltip( 0 ) );
	}

	/**
	 * Chooses the source text and the witness number from the examples of a form.
	 *
	 * @return void
	 */
	public function test_source_text_and_witness_follow_the_form_examples() {
		$presenter = new PluralFormPresenter(
			array(
				array( 'examples' => array( 0, 1 ) ),
				array( 'examples' => array( 2, 3 ) ),
				array(),
			)
		);

		$this->assertSame( 'one item', $presenter->source_text( 0, 'one item', 'items' ) );
		$this->assertSame( 'items', $presenter->source_text( 1, 'one item', 'items' ) );
		$this->assertSame( 1, $presenter->witness_example( 0 ), 'A form containing 1 is witnessed by 1.' );
		$this->assertSame( 2, $presenter->witness_example( 1 ), 'Otherwise the first example is used.' );

		$this->assertSame( 'items', $presenter->source_text( 2, 'one item', 'items' ), 'A form without examples uses the plural unless it is the form 0.' );
		$this->assertSame( 'items', $presenter->source_text( 5, 'one item', 'items' ), 'Forms without examples: singular for index 0 only.' );
		$this->assertSame( 2, $presenter->witness_example( 5 ) );
		$this->assertSame( 1, ( new PluralFormPresenter() )->witness_example( 0 ) );
		$this->assertSame( 'one item', ( new PluralFormPresenter() )->source_text( 0, 'one item', 'items' ) );
	}

	/**
	 * Normalizes legacy quality statuses.
	 *
	 * @return void
	 */
	public function test_badges_normalize_statuses() {
		$badges = new EntryStatusBadges();

		$this->assertSame( 'draft', $badges->normalize_status( 'draft_ai' ) );
		$this->assertSame( 'draft', $badges->normalize_status( 'ai_draft_ok' ) );
		$this->assertSame( 'suspect', $badges->normalize_status( 'suspect' ) );
		$this->assertSame( 'validated', $badges->normalize_status( 'validated' ) );
		$this->assertSame( 'draft', $badges->normalize_status( 'anything else' ) );
		$this->assertSame( 'draft', $badges->normalize_status( '' ) );
	}

	/**
	 * Renders quality, provenance and obsolete badges linked to their input.
	 *
	 * @return void
	 */
	public function test_badges_render_quality_provenance_and_obsolete_markup() {
		$badges = new EntryStatusBadges();

		$quality = $badges->render_quality_badge( 'input-1', 'validated' );

		$this->assertStringContainsString( 'data-for="input-1"', $quality );
		$this->assertStringContainsString( 'data-status-token="validated"', $quality );
		$this->assertStringContainsString( 'i18nly-entry-status--validated', $quality );
		$this->assertStringNotContainsString( 'disabled', $quality );

		$empty = $badges->render_quality_badge( 'input-1', '' );

		$this->assertStringContainsString( 'data-status-token="__empty__"', $empty );
		$this->assertStringContainsString( 'disabled="disabled"', $empty );

		$provenance = $badges->render_provenance_badges( 'input-1', 1, 1 );

		$this->assertStringContainsString( 'data-provenance-token="ai"', $provenance );
		$this->assertStringContainsString( 'data-provenance-token="manual"', $provenance );
		$this->assertSame( '', $badges->render_provenance_badges( 'input-1', 0, 0 ) );
		$this->assertStringNotContainsString( 'data-provenance-token="ai"', $badges->render_provenance_badges( 'input-1', 0, 1 ) );

		$this->assertSame( '', $badges->render_obsolete_badge( false ) );
		$this->assertStringContainsString( 'data-status-token="obsolete"', $badges->render_obsolete_badge( true ) );
	}
}
