<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation glossaries tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\Admin\Glossary\TranslationGlossaryBox;
use WP_I18nly\Glossary\GlossaryMatcher;
use WP_I18nly\Glossary\TranslationGlossaries;
use WP_I18nly\LinguisticResources\GlossaryResourceRepository;
use WP_I18nly\Storage\SourceSchemaManager;
use WP_I18nly\Storage\SourceWpdbRepository;

/**
 * Tests the links between translations and glossaries, and the box that edits them.
 */
class TranslationGlossariesTest extends TestCase {
	/**
	 * Repository.
	 *
	 * @var GlossaryResourceRepository
	 */
	private $repository;

	/**
	 * Glossaries of translations.
	 *
	 * @var TranslationGlossaries
	 */
	private $glossaries;

	/**
	 * Glossary IDs by name.
	 *
	 * @var array<string, int>
	 */
	private $ids = array();

	/**
	 * Builds the storage with three glossaries: two for fr_FR, one for de_DE.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		global $i18nly_test_post_meta;

		$i18nly_test_post_meta = array();
		$_POST                 = array();
		$wpdb_double           = new I18nly_Test_InMemory_Wpdb();
		$manager               = new SourceSchemaManager( $wpdb_double );

		$this->repository = new GlossaryResourceRepository( new SourceWpdbRepository( $manager, $wpdb_double ), $manager );
		$this->glossaries = new TranslationGlossaries( $this->repository );

		$this->ids['zeta']  = $this->repository->create_glossary( 'zeta', 'en_US', 'fr_FR' )->get_id();
		$this->ids['alpha'] = $this->repository->create_glossary( 'alpha', 'en_US', 'fr_FR' )->get_id();
		$this->ids['de']    = $this->repository->create_glossary( 'de-terms', 'en_US', 'de_DE' )->get_id();

		$this->repository->save_term(
			$this->ids['zeta'],
			array(
				'term' => 'cart',
				'preferred' => 'chariot',
			)
		);
		$this->repository->save_term(
			$this->ids['alpha'],
			array(
				'term' => 'Cart',
				'preferred' => 'panier',
				'alternates' => array( 'caddie' ),
			)
		);
		$this->repository->save_term(
			$this->ids['de'],
			array(
				'term' => 'cart',
				'preferred' => 'Warenkorb',
			)
		);
	}

	/**
	 * Clears the request.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_POST = array();
	}

	/**
	 * Only the glossaries of the language of the translation can be linked.
	 *
	 * @return void
	 */
	public function test_only_glossaries_of_the_language_can_be_linked() {
		$this->glossaries->set_linked_ids( 7, array( $this->ids['zeta'], $this->ids['de'], 9999, 0 ), 'fr_FR' );

		$this->assertSame( array( $this->ids['zeta'] ), $this->glossaries->get_linked_ids( 7 ) );
		$this->assertSame( array( 'alpha', 'zeta' ), array_column( $this->glossaries->list_usable_glossaries( 'fr_FR' ), 'slug' ) );
	}

	/**
	 * The matcher uses the linked glossaries only, the first by identifier winning.
	 *
	 * @return void
	 */
	public function test_matcher_follows_the_links_and_the_identifier_order() {
		$this->assertSame( 0, $this->glossaries->build_matcher( 7, 'fr_FR' )->count(), 'no link, no term' );

		$this->glossaries->set_linked_ids( 7, array( $this->ids['zeta'], $this->ids['alpha'] ), 'fr_FR' );

		$matcher = $this->glossaries->build_matcher( 7, 'fr_FR' );
		$matches = $matcher->match_entry( 'Cart', '', array( 'Un caddie' ) );

		$this->assertSame( 1, $matcher->count(), 'the same term in two glossaries counts once' );
		$this->assertSame( 'alpha', $matches[0]['glossary'] );
		$this->assertSame( 'panier', $matches[0]['preferred'] );
		$this->assertSame( array( 'caddie' ), $matches[0]['alternates'] );
		$this->assertSame( GlossaryMatcher::QA_OK, $matches[0]['qa'] );
	}

	/**
	 * A deleted glossary is ignored.
	 *
	 * @return void
	 */
	public function test_deleted_glossary_is_ignored() {
		$this->glossaries->set_linked_ids( 7, array( $this->ids['alpha'] ), 'fr_FR' );
		$this->repository->delete_glossary( $this->ids['alpha'] );

		$this->assertSame( 0, $this->glossaries->build_matcher( 7, 'fr_FR' )->count() );
	}

	/**
	 * The box saves the selection with a valid nonce and the capability, and nothing otherwise.
	 *
	 * @return void
	 */
	public function test_box_saves_the_selection() {
		update_post_meta( 7, '_i18nly_target_language', 'fr_FR' );
		i18nly_test_set_can_manage_options( true );

		$box = new TranslationGlossaryBox( $this->glossaries );

		$_POST = array( 'i18nly_glossary_ids' => array( (string) $this->ids['alpha'] ) );
		$box->save( 7 );
		$this->assertSame( array(), $this->glossaries->get_linked_ids( 7 ), 'no nonce' );

		$_POST['i18nly_glossaries_nonce'] = 'nonce-' . TranslationGlossaryBox::get_nonce_action( 7 );
		$box->save( 7 );
		$this->assertSame( array( $this->ids['alpha'] ), $this->glossaries->get_linked_ids( 7 ) );

		$_POST = array( 'i18nly_glossaries_nonce' => 'nonce-' . TranslationGlossaryBox::get_nonce_action( 7 ) );
		$box->save( 7 );
		$this->assertSame( array(), $this->glossaries->get_linked_ids( 7 ), 'unticking everything unlinks' );

		i18nly_test_set_can_manage_options( false );
		$_POST = array(
			'i18nly_glossaries_nonce' => 'nonce-' . TranslationGlossaryBox::get_nonce_action( 7 ),
			'i18nly_glossary_ids'     => array( (string) $this->ids['zeta'] ),
		);
		$box->save( 7 );
		$this->assertSame( array(), $this->glossaries->get_linked_ids( 7 ), 'no capability' );
	}

	/**
	 * The box lists the glossaries of the language, ticking the linked ones.
	 *
	 * @return void
	 */
	public function test_box_lists_the_glossaries_of_the_language() {
		update_post_meta( 7, '_i18nly_target_language', 'fr_FR' );
		$this->glossaries->set_linked_ids( 7, array( $this->ids['zeta'] ), 'fr_FR' );

		ob_start();
		( new TranslationGlossaryBox( $this->glossaries ) )->render( (object) array( 'ID' => 7 ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'value="' . $this->ids['zeta'] . '" checked', $html );
		$this->assertStringNotContainsString( 'value="' . $this->ids['alpha'] . '" checked', $html );
		$this->assertStringContainsString( 'alpha', $html );
		$this->assertStringNotContainsString( 'de-terms', $html );
	}
}
