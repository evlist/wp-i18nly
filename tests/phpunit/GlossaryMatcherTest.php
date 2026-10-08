<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Glossary matcher tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\Glossary\GlossaryMatcher;

/**
 * Tests the search of glossary terms in source texts and the check of translations.
 */
class GlossaryMatcherTest extends TestCase {
	/**
	 * Builds a term.
	 *
	 * @param string   $term Term.
	 * @param string   $preferred Preferred translation.
	 * @param string   $mode Match mode.
	 * @param string[] $alternates Alternatives.
	 * @param string   $glossary Glossary identifier.
	 * @return array<string, mixed>
	 */
	private function term( $term, $preferred, $mode = 'exact', array $alternates = array(), $glossary = 'g1' ) {
		return array(
			'glossary'   => $glossary,
			'term'       => $term,
			'mode'       => $mode,
			'preferred'  => $preferred,
			'alternates' => $alternates,
			'note'       => '',
		);
	}

	/**
	 * An exact term matches the whole source text only, ignoring case and spacing.
	 *
	 * @return void
	 */
	public function test_exact_term_matches_the_whole_text() {
		$matcher = new GlossaryMatcher( array( $this->term( 'Add to cart', 'Ajouter au panier' ) ) );

		$this->assertCount( 1, $matcher->match_entry( '  add   TO cart ', '', array() ) );
		$this->assertCount( 0, $matcher->match_entry( 'Add to cart now', '', array() ) );
		$this->assertCount( 1, $matcher->match_entry( 'Item', 'add to cart', array() ), 'the plural source text counts too' );
	}

	/**
	 * A partial term matches whole words inside the text.
	 *
	 * @return void
	 */
	public function test_partial_term_matches_whole_words() {
		$matcher = new GlossaryMatcher( array( $this->term( 'cart', 'panier', 'partial' ) ) );

		$this->assertCount( 1, $matcher->match_entry( 'Your Cart is empty', '', array() ) );
		$this->assertCount( 1, $matcher->match_entry( 'Empty the cart.', '', array() ) );
		$this->assertCount( 0, $matcher->match_entry( 'Cartridge', '', array() ) );
		$this->assertCount( 0, $matcher->match_entry( 'Apple_cart', '', array() ) );
	}

	/**
	 * Words boundaries work with non-ASCII letters, and regular expression characters in terms are literal.
	 *
	 * @return void
	 */
	public function test_boundaries_are_unicode_aware_and_terms_are_literal() {
		$matcher = new GlossaryMatcher( array( $this->term( 'été', 'summer', 'partial' ), $this->term( 'a+b (c)', 'x', 'partial' ) ) );

		$this->assertCount( 1, $matcher->match_entry( 'Un été chaud', '', array() ) );
		$this->assertCount( 0, $matcher->match_entry( 'abétés', '', array() ) );
		$this->assertCount( 1, $matcher->match_entry( 'Compute a+b (c) now', '', array() ) );
		$this->assertCount( 0, $matcher->match_entry( 'Compute aab c now', '', array() ) );
	}

	/**
	 * The check says pending, ok or missing.
	 *
	 * @return void
	 */
	public function test_translation_check() {
		$matcher = new GlossaryMatcher( array( $this->term( 'cart', 'panier', 'partial', array( 'caddie' ) ) ) );

		$this->assertSame( GlossaryMatcher::QA_PENDING, $matcher->match_entry( 'cart', '', array( '' ) )[0]['qa'] );
		$this->assertSame( GlossaryMatcher::QA_OK, $matcher->match_entry( 'cart', '', array( 'Votre PANIER est vide' ) )[0]['qa'] );
		$this->assertSame( GlossaryMatcher::QA_OK, $matcher->match_entry( 'cart', '', array( 'Un caddie' ) )[0]['qa'], 'an alternative is accepted' );
		$this->assertSame( GlossaryMatcher::QA_MISSING, $matcher->match_entry( 'cart', '', array( 'Votre chariot' ) )[0]['qa'] );
		$this->assertSame( GlossaryMatcher::QA_MISSING, $matcher->match_entry( 'cart', 'carts', array( 'un panier', 'des chariots' ) )[0]['qa'], 'every translated form is checked' );
		$this->assertSame( GlossaryMatcher::QA_OK, $matcher->match_entry( 'cart', 'carts', array( 'un panier', '' ) )[0]['qa'], 'empty forms are ignored' );
	}

	/**
	 * When a term is in several glossaries the first one wins; different match modes are different terms.
	 *
	 * @return void
	 */
	public function test_first_glossary_wins() {
		$matcher = new GlossaryMatcher(
			array(
				$this->term( 'Cart', 'Panier', 'exact', array(), 'a' ),
				$this->term( 'cart', 'Chariot', 'exact', array(), 'b' ),
				$this->term( 'cart', 'Chariot', 'partial', array(), 'b' ),
			)
		);

		$matches = $matcher->match_entry( 'cart', '', array() );

		$this->assertSame( 2, $matcher->count() );
		$this->assertCount( 2, $matches );
		$this->assertSame( 'a', $matches[0]['glossary'] );
		$this->assertSame( 'b', $matches[1]['glossary'] );
		$this->assertSame( 'partial', $matches[1]['mode'] );
	}
}
