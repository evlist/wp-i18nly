<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Glossary resource model tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\LinguisticResources\GlossaryResource;
use WP_I18nly\LinguisticResources\GlossaryResourceEntry;
use WP_I18nly\LinguisticResources\GlossaryResourceTarget;

/**
 * Tests the glossary value objects built on the linguistic resource abstractions.
 */
class GlossaryResourceModelTest extends TestCase {
	/**
	 * Builds one entry row as returned by the glossary repository.
	 *
	 * @return array<string, mixed>
	 */
	private function entry_row() {
		return array(
			'source_entry_id' => 5,
			'term'            => 'Block editor',
			'match_mode'      => 'partial',
			'note'            => 'UI only',
			'status'          => 'active',
			'translations'    => array(
				array(
					'form_index'  => 1,
					'translation' => 'Éditeur',
				),
				array(
					'form_index'  => 0,
					'translation' => 'Éditeur de blocs',
				),
				array(
					'form_index'  => 2,
					'translation' => 'Éditeur Gutenberg',
				),
			),
		);
	}

	/**
	 * Extends the abstract resource classes.
	 *
	 * @return void
	 */
	public function test_glossary_classes_extend_the_abstract_resource_classes() {
		$this->assertTrue( is_subclass_of( GlossaryResource::class, 'WP_I18nly\\LinguisticResources\\AbstractLinguisticResource' ) );
		$this->assertTrue( is_subclass_of( GlossaryResourceEntry::class, 'WP_I18nly\\LinguisticResources\\AbstractLinguisticResourceEntry' ) );
		$this->assertTrue( is_subclass_of( GlossaryResourceTarget::class, 'WP_I18nly\\LinguisticResources\\AbstractLinguisticResourceTarget' ) );
		$this->assertTrue( is_subclass_of( 'WP_I18nly\\LinguisticResources\\GlossaryResourceRepository', 'WP_I18nly\\LinguisticResources\\AbstractLinguisticResourceRepository' ) );
	}

	/**
	 * Exposes the identity of the glossary.
	 *
	 * @return void
	 */
	public function test_glossary_resource_exposes_its_identity_and_entries() {
		$entry    = new GlossaryResourceEntry( $this->entry_row() );
		$resource = new GlossaryResource( 9, 'brand-terms', 'en_US', 'fr_FR', array( $entry ) );

		$this->assertSame( 'glossary', $resource->get_resource_kind() );
		$this->assertSame( 9, $resource->get_resource_id() );
		$this->assertSame( 'brand-terms', $resource->get_slug() );
		$this->assertSame( 'en_US', $resource->get_source_locale() );
		$this->assertSame( 'fr_FR', $resource->get_target_locale() );
		$this->assertSame( array( $entry ), $resource->get_entries() );
	}

	/**
	 * Exposes the term data of an entry.
	 *
	 * @return void
	 */
	public function test_glossary_entry_exposes_term_data() {
		$entry = new GlossaryResourceEntry( $this->entry_row() );

		$this->assertSame( 'glossary', $entry->get_entry_kind() );
		$this->assertSame( 5, $entry->get_source_entry_id() );
		$this->assertSame( 'Block editor', $entry->get_term() );
		$this->assertSame( 'partial', $entry->get_match_mode() );
		$this->assertSame( 'UI only', $entry->get_note() );
		$this->assertSame( 'active', $entry->get_status() );
	}

	/**
	 * Applies defaults to sparse rows.
	 *
	 * @return void
	 */
	public function test_glossary_entry_defaults() {
		$entry = new GlossaryResourceEntry( array( 'source_entry_id' => 1 ) );

		$this->assertSame( '', $entry->get_term() );
		$this->assertSame( 'exact', $entry->get_match_mode() );
		$this->assertSame( '', $entry->get_note() );
		$this->assertSame( 'active', $entry->get_status() );
		$this->assertNull( $entry->get_preferred_target() );
		$this->assertSame( array(), $entry->get_alternate_targets() );
	}

	/**
	 * Splits the preferred target from the alternates, in rank order.
	 *
	 * @return void
	 */
	public function test_glossary_entry_separates_preferred_and_alternate_targets() {
		$entry = new GlossaryResourceEntry( $this->entry_row() );

		$preferred = $entry->get_preferred_target();

		$this->assertInstanceOf( GlossaryResourceTarget::class, $preferred );
		$this->assertSame( 'Éditeur de blocs', $preferred->get_text() );
		$this->assertTrue( $preferred->is_preferred() );
		$this->assertSame( 0, $preferred->get_rank() );
		$this->assertSame( 'glossary', $preferred->get_target_kind() );

		$alternates = $entry->get_alternate_targets();

		$this->assertSame( array( 'Éditeur', 'Éditeur Gutenberg' ), array_map( static fn( $target ) => $target->get_text(), $alternates ) );
		$this->assertSame( array( 1, 2 ), array_map( static fn( $target ) => $target->get_rank(), $alternates ) );
		$this->assertFalse( $alternates[0]->is_preferred() );
		$this->assertSame( array( 'Éditeur de blocs', 'Éditeur', 'Éditeur Gutenberg' ), array_map( static fn( $target ) => $target->get_text(), $entry->get_ordered_targets() ) );
	}
}
