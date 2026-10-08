<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Glossary resource repository tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\LinguisticResources\GlossaryResourceRepository;
use WP_I18nly\Storage\SourceSchemaManager;
use WP_I18nly\Storage\SourceWpdbRepository;

/**
 * Tests the persistence of glossaries, their terms and their translations.
 */
class GlossaryResourceRepositoryTest extends TestCase {
	/**
	 * In-memory database.
	 *
	 * @var I18nly_Test_InMemory_Wpdb
	 */
	private $wpdb;

	/**
	 * Storage repository.
	 *
	 * @var SourceWpdbRepository
	 */
	private $storage;

	/**
	 * Repository under test.
	 *
	 * @var GlossaryResourceRepository
	 */
	private $repository;

	/**
	 * Builds the repository on an empty in-memory database.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->wpdb       = new I18nly_Test_InMemory_Wpdb();
		$manager          = new SourceSchemaManager( $this->wpdb );
		$this->storage    = new SourceWpdbRepository( $manager, $this->wpdb );
		$this->repository = new GlossaryResourceRepository( $this->storage, $manager );
	}

	/**
	 * Creates the default French glossary and returns its ID.
	 *
	 * @param string $slug Slug.
	 * @param string $target_locale Target locale.
	 * @return int
	 */
	private function create_glossary( $slug = 'brand-terms', $target_locale = 'fr_FR' ) {
		$result = $this->repository->create_glossary( $slug, 'en_US', $target_locale, '2026-06-01 10:00:00' );

		$this->assertTrue( $result->is_success(), implode( ' ', $result->get_errors() ) );

		return $result->get_id();
	}

	/**
	 * Builds a term input with overrides.
	 *
	 * @param array<string, mixed> $overrides Overrides.
	 * @return array<string, mixed>
	 */
	private function term( array $overrides = array() ) {
		return array_merge(
			array(
				'term'       => 'Block editor',
				'match_mode' => 'exact',
				'preferred'  => 'Éditeur de blocs',
				'alternates' => array(),
				'note'       => '',
			),
			$overrides
		);
	}

	/**
	 * Returns the target rows of one table, as text by rank.
	 *
	 * @param int $entry_id Entry ID.
	 * @return array<int, string>
	 */
	private function stored_targets( $entry_id ) {
		$targets = array();

		foreach ( $this->wpdb->rows( 'i18nly_linguistic_resource_targets' ) as $row ) {
			if ( (int) $row['source_entry_id'] === (int) $entry_id ) {
				$targets[ (int) $row['form_index'] ] = $row['target_text'];
			}
		}

		ksort( $targets );

		return $targets;
	}

	/**
	 * Creates a glossary resource row of kind glossary.
	 *
	 * @return void
	 */
	public function test_create_glossary_stores_a_glossary_resource() {
		$result = $this->repository->create_glossary( ' Brand-Terms ', 'en_US', 'fr_FR', '2026-06-01 10:00:00' );

		$this->assertTrue( $result->is_success() );
		$this->assertGreaterThan( 0, $result->get_id() );

		$rows = $this->wpdb->rows( 'i18nly_linguistic_resources' );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'glossary', $rows[0]['resource_kind'] );
		$this->assertSame( 'brand-terms', $rows[0]['source_slug'] );
		$this->assertSame( 'en_US', $rows[0]['source_locale'] );
		$this->assertSame( 'fr_FR', $rows[0]['target_locale'] );
		$this->assertSame( 0, $rows[0]['anchor_post_id'] );
		$this->assertSame( '2026-06-01 10:00:00', $rows[0]['created_at_gmt'] );
		$this->assertSame( $result->get_id(), $this->repository->find_glossary_id( 'BRAND-TERMS', 'fr_FR' ) );
	}

	/**
	 * Refuses an invalid identity without writing anything.
	 *
	 * @return void
	 */
	public function test_create_glossary_rejects_invalid_identity() {
		$result = $this->repository->create_glossary( 'not valid', 'en_US', 'en_US', '2026-06-01 10:00:00' );

		$this->assertFalse( $result->is_success() );
		$this->assertTrue( $result->has_error( 'invalid_slug' ) );
		$this->assertTrue( $result->has_error( 'same_locale' ) );
		$this->assertSame( 0, $result->get_id() );
		$this->assertSame( array(), $this->wpdb->rows( 'i18nly_linguistic_resources' ) );
	}

	/**
	 * Allows one slug per target language only.
	 *
	 * @return void
	 */
	public function test_create_glossary_rejects_duplicates_per_target_locale() {
		$first = $this->create_glossary( 'brand-terms', 'fr_FR' );

		$duplicate = $this->repository->create_glossary( 'brand-terms', 'en_US', 'fr_FR', '2026-06-01 10:00:00' );

		$this->assertFalse( $duplicate->is_success() );
		$this->assertTrue( $duplicate->has_error( 'duplicate_glossary' ) );
		$this->assertSame( $first, $this->repository->find_glossary_id( 'brand-terms', 'fr_FR' ) );

		$german = $this->repository->create_glossary( 'brand-terms', 'en_US', 'de_DE', '2026-06-01 10:00:00' );

		$this->assertTrue( $german->is_success() );
		$this->assertNotSame( $first, $german->get_id() );
	}

	/**
	 * Lists glossaries only, not translations or source catalogs.
	 *
	 * @return void
	 */
	public function test_list_glossaries_ignores_other_resource_kinds() {
		$this->create_glossary( 'zebra', 'fr_FR' );
		$this->create_glossary( 'alpha', 'fr_FR' );
		$this->storage->upsert_source_resource( 'akismet/akismet.php', 'akismet', '{}', '2026-06-01 10:00:00' );
		$this->storage->ensure_translation_resource( 42, 'akismet/akismet.php', 'fr_FR', '2026-06-01 10:00:00' );

		$glossaries = $this->repository->list_glossaries();

		$this->assertSame( array( 'alpha', 'zebra' ), array_column( $glossaries, 'slug' ) );
		$this->assertSame( array( 'id', 'slug', 'source_locale', 'target_locale' ), array_keys( $glossaries[0] ) );
		$this->assertSame( 'en_US', $glossaries[0]['source_locale'] );
		$this->assertSame( 0, $this->repository->find_glossary_id( 'akismet', 'fr_FR' ) );
	}

	/**
	 * Stores a term with its preferred and alternate translations.
	 *
	 * @return void
	 */
	public function test_save_term_stores_entry_and_ranked_targets() {
		$glossary_id = $this->create_glossary();

		$result = $this->repository->save_term(
			$glossary_id,
			$this->term(
				array(
					'term'       => '  Block   editor ',
					'match_mode' => 'partial',
					'alternates' => array( 'Éditeur', 'Éditeur Gutenberg' ),
					'note'       => 'UI only',
				)
			),
			0,
			'2026-06-01 11:00:00'
		);

		$this->assertTrue( $result->is_success(), implode( ' ', $result->get_errors() ) );

		$entries = $this->wpdb->rows( 'i18nly_linguistic_resource_entries' );

		$this->assertCount( 1, $entries );
		$this->assertSame( $glossary_id, $entries[0]['resource_id'] );
		$this->assertSame( 'Block editor', $entries[0]['msgid'] );
		$this->assertSame( '', $entries[0]['msgctxt'] );
		$this->assertSame( 'partial', $entries[0]['match_mode'] );
		$this->assertSame( 'UI only', $entries[0]['translator_comment'] );
		$this->assertSame( 'active', $entries[0]['status'] );
		$this->assertSame( array( 'Éditeur de blocs', 'Éditeur', 'Éditeur Gutenberg' ), $this->stored_targets( $result->get_id() ) );
	}

	/**
	 * Returns the glossary as a resource model.
	 *
	 * @return void
	 */
	public function test_get_glossary_returns_the_resource_model_with_ordered_entries() {
		$glossary_id = $this->create_glossary();

		$this->repository->save_term(
			$glossary_id,
			$this->term(
				array(
					'term'      => 'zoom',
					'preferred' => 'zoom',
				)
			),
			0,
			'2026-06-01 11:00:00'
		);
		$this->repository->save_term(
			$glossary_id,
			$this->term(
				array(
					'term'       => 'Block editor',
					'match_mode' => 'partial',
					'alternates' => array( 'Éditeur' ),
					'note'       => 'UI only',
				)
			),
			0,
			'2026-06-01 11:00:00'
		);

		$glossary = $this->repository->get_glossary( $glossary_id );

		$this->assertInstanceOf( 'WP_I18nly\\LinguisticResources\\GlossaryResource', $glossary );
		$this->assertSame( $glossary_id, $glossary->get_resource_id() );
		$this->assertSame( 'brand-terms', $glossary->get_slug() );
		$this->assertSame( 'en_US', $glossary->get_source_locale() );
		$this->assertSame( 'fr_FR', $glossary->get_target_locale() );

		$entries = $glossary->get_entries();

		$this->assertSame( array( 'Block editor', 'zoom' ), array_map( static fn( $entry ) => $entry->get_term(), $entries ) );
		$this->assertSame( 'partial', $entries[0]->get_match_mode() );
		$this->assertSame( 'UI only', $entries[0]->get_note() );
		$this->assertSame( 'Éditeur de blocs', $entries[0]->get_preferred_target()->get_text() );
		$this->assertSame( array( 'Éditeur' ), array_map( static fn( $target ) => $target->get_text(), $entries[0]->get_alternate_targets() ) );
		$this->assertSame( array(), $entries[1]->get_alternate_targets() );
	}

	/**
	 * Returns nothing for an unknown glossary or another kind of resource.
	 *
	 * @return void
	 */
	public function test_get_glossary_ignores_unknown_ids_and_other_kinds() {
		$translation_id = $this->storage->ensure_translation_resource( 42, 'akismet/akismet.php', 'fr_FR', '2026-06-01 10:00:00' );

		$this->assertNull( $this->repository->get_glossary( 999 ) );
		$this->assertNull( $this->repository->get_glossary( $translation_id ) );
	}

	/**
	 * Updates a term, replacing its targets.
	 *
	 * @return void
	 */
	public function test_save_term_updates_an_existing_term() {
		$glossary_id = $this->create_glossary();
		$created     = $this->repository->save_term( $glossary_id, $this->term( array( 'alternates' => array( 'Éditeur', 'Éditeur Gutenberg' ) ) ), 0, '2026-06-01 11:00:00' );

		$updated = $this->repository->save_term(
			$glossary_id,
			$this->term(
				array(
					'term'       => 'Block Editor',
					'match_mode' => 'partial',
					'preferred'  => 'Éditeur par blocs',
					'alternates' => array( 'Éditeur' ),
				)
			),
			$created->get_id(),
			'2026-06-02 09:00:00'
		);

		$this->assertTrue( $updated->is_success(), implode( ' ', $updated->get_errors() ) );
		$this->assertSame( $created->get_id(), $updated->get_id() );

		$entries = $this->wpdb->rows( 'i18nly_linguistic_resource_entries' );

		$this->assertCount( 1, $entries, 'The term was updated, not duplicated.' );
		$this->assertSame( 'Block Editor', $entries[0]['msgid'] );
		$this->assertSame( 'partial', $entries[0]['match_mode'] );
		$this->assertSame( '2026-06-01 11:00:00', $entries[0]['created_at_gmt'] );
		$this->assertSame( '2026-06-02 09:00:00', $entries[0]['updated_at_gmt'] );
		$this->assertSame( array( 'Éditeur par blocs', 'Éditeur' ), $this->stored_targets( $created->get_id() ), 'The dropped alternate was removed.' );
	}

	/**
	 * Rejects an invalid term without writing anything.
	 *
	 * @return void
	 */
	public function test_save_term_rejects_invalid_input() {
		$glossary_id = $this->create_glossary();

		$result = $this->repository->save_term(
			$glossary_id,
			$this->term(
				array(
					'term'      => '',
					'preferred' => '',
				)
			),
			0,
			'2026-06-01 11:00:00'
		);

		$this->assertFalse( $result->is_success() );
		$this->assertTrue( $result->has_error( 'missing_term' ) );
		$this->assertTrue( $result->has_error( 'missing_preferred' ) );
		$this->assertSame( array(), $this->wpdb->rows( 'i18nly_linguistic_resource_entries' ) );
		$this->assertSame( array(), $this->wpdb->rows( 'i18nly_linguistic_resource_targets' ) );
	}

	/**
	 * Detects duplicate terms ignoring case, but lets a term keep its own name.
	 *
	 * @return void
	 */
	public function test_save_term_rejects_case_insensitive_duplicates() {
		$glossary_id = $this->create_glossary();
		$first       = $this->repository->save_term( $glossary_id, $this->term( array( 'term' => 'API' ) ), 0, '2026-06-01 11:00:00' );
		$other       = $this->repository->save_term(
			$glossary_id,
			$this->term(
				array(
					'term'      => 'Plugin',
					'preferred' => 'Extension',
				)
			),
			0,
			'2026-06-01 11:00:00'
		);

		$duplicate = $this->repository->save_term( $glossary_id, $this->term( array( 'term' => 'api' ) ), 0, '2026-06-01 11:00:00' );

		$this->assertFalse( $duplicate->is_success() );
		$this->assertTrue( $duplicate->has_error( 'duplicate_term' ) );

		$renamed = $this->repository->save_term( $glossary_id, $this->term( array( 'term' => 'plugin' ) ), $first->get_id(), '2026-06-01 11:00:00' );

		$this->assertTrue( $renamed->has_error( 'duplicate_term' ), 'Renaming a term onto another one is a duplicate.' );

		$recased = $this->repository->save_term(
			$glossary_id,
			$this->term(
				array(
					'term'      => 'Api',
					'preferred' => 'API',
				)
			),
			$first->get_id(),
			'2026-06-02 09:00:00'
		);

		$this->assertTrue( $recased->is_success(), 'A term can change its own case.' );
		$this->assertCount( 2, $this->wpdb->rows( 'i18nly_linguistic_resource_entries' ) );
		$this->assertNotSame( $other->get_id(), $first->get_id() );
	}

	/**
	 * Allows the same term in different glossaries.
	 *
	 * @return void
	 */
	public function test_same_term_can_exist_in_two_glossaries() {
		$french = $this->create_glossary( 'brand-terms', 'fr_FR' );
		$german = $this->create_glossary( 'brand-terms', 'de_DE' );

		$this->assertTrue( $this->repository->save_term( $french, $this->term(), 0, '2026-06-01 11:00:00' )->is_success() );
		$this->assertTrue( $this->repository->save_term( $german, $this->term( array( 'preferred' => 'Block-Editor' ) ), 0, '2026-06-01 11:00:00' )->is_success() );
		$this->assertSame( 'Éditeur de blocs', $this->repository->get_glossary( $french )->get_entries()[0]->get_preferred_target()->get_text() );
		$this->assertSame( 'Block-Editor', $this->repository->get_glossary( $german )->get_entries()[0]->get_preferred_target()->get_text() );
	}

	/**
	 * Reports unknown glossaries and terms.
	 *
	 * @return void
	 */
	public function test_save_term_reports_unknown_glossary_and_term() {
		$glossary_id = $this->create_glossary( 'one', 'fr_FR' );
		$other_id    = $this->create_glossary( 'two', 'fr_FR' );
		$foreign     = $this->repository->save_term( $other_id, $this->term(), 0, '2026-06-01 11:00:00' );

		$this->assertTrue( $this->repository->save_term( 999, $this->term(), 0, '2026-06-01 11:00:00' )->has_error( 'unknown_glossary' ) );
		$this->assertTrue( $this->repository->save_term( $glossary_id, $this->term(), 999, '2026-06-01 11:00:00' )->has_error( 'unknown_term' ) );
		$this->assertTrue( $this->repository->save_term( $glossary_id, $this->term(), $foreign->get_id(), '2026-06-01 11:00:00' )->has_error( 'unknown_term' ), 'A term of another glossary cannot be edited.' );
		$this->assertCount( 1, $this->wpdb->rows( 'i18nly_linguistic_resource_entries' ) );
	}

	/**
	 * Leaves no entry behind when storing the targets fails.
	 *
	 * @return void
	 */
	public function test_save_term_rolls_back_when_a_write_fails() {
		$glossary_id = $this->create_glossary();
		$created     = $this->repository->save_term( $glossary_id, $this->term( array( 'alternates' => array( 'Éditeur' ) ) ), 0, '2026-06-01 11:00:00' );

		$this->wpdb->fail_insert_into( 'i18nly_linguistic_resource_targets', 1 );

		$failed = $this->repository->save_term(
			$glossary_id,
			$this->term(
				array(
					'term'       => 'Plugin',
					'preferred'  => 'Extension',
					'alternates' => array( 'Module' ),
				)
			),
			0,
			'2026-06-01 12:00:00'
		);

		$this->assertFalse( $failed->is_success() );
		$this->assertTrue( $failed->has_error( 'storage_error' ) );
		$this->assertCount( 1, $this->wpdb->rows( 'i18nly_linguistic_resource_entries' ), 'The new entry was rolled back.' );
		$this->assertSame( array( 'Éditeur de blocs', 'Éditeur' ), $this->stored_targets( $created->get_id() ) );

		$this->wpdb->fail_insert_into( 'i18nly_linguistic_resource_targets', 1 );

		$failed_update = $this->repository->save_term(
			$glossary_id,
			$this->term(
				array(
					'preferred'  => 'Nouveau',
					'alternates' => array( 'Autre' ),
				)
			),
			$created->get_id(),
			'2026-06-01 12:00:00'
		);

		$this->assertTrue( $failed_update->has_error( 'storage_error' ) );
		$this->assertSame( array( 'Éditeur de blocs', 'Éditeur' ), $this->stored_targets( $created->get_id() ), 'The previous targets were restored.' );
	}

	/**
	 * Deletes one term and its targets only.
	 *
	 * @return void
	 */
	public function test_delete_term_removes_the_term_and_its_targets() {
		$glossary_id = $this->create_glossary();
		$first       = $this->repository->save_term( $glossary_id, $this->term( array( 'alternates' => array( 'Éditeur' ) ) ), 0, '2026-06-01 11:00:00' );
		$second      = $this->repository->save_term(
			$glossary_id,
			$this->term(
				array(
					'term'      => 'Plugin',
					'preferred' => 'Extension',
				)
			),
			0,
			'2026-06-01 11:00:00'
		);

		$this->assertTrue( $this->repository->delete_term( $glossary_id, $first->get_id() ) );

		$this->assertSame( array(), $this->stored_targets( $first->get_id() ) );
		$this->assertSame( array( 'Extension' ), $this->stored_targets( $second->get_id() ) );
		$this->assertSame( array( 'Plugin' ), array_column( $this->wpdb->rows( 'i18nly_linguistic_resource_entries' ), 'msgid' ) );
		$this->assertFalse( $this->repository->delete_term( $glossary_id, $first->get_id() ), 'The term is already gone.' );
	}

	/**
	 * Does not delete a term through another glossary.
	 *
	 * @return void
	 */
	public function test_delete_term_checks_the_glossary() {
		$one  = $this->create_glossary( 'one', 'fr_FR' );
		$two  = $this->create_glossary( 'two', 'fr_FR' );
		$term = $this->repository->save_term( $one, $this->term(), 0, '2026-06-01 11:00:00' );

		$this->assertFalse( $this->repository->delete_term( $two, $term->get_id() ) );
		$this->assertCount( 1, $this->wpdb->rows( 'i18nly_linguistic_resource_entries' ) );
	}

	/**
	 * Deletes a glossary with its content and nothing else.
	 *
	 * @return void
	 */
	public function test_delete_glossary_removes_only_its_own_rows() {
		$doomed = $this->create_glossary( 'doomed', 'fr_FR' );
		$kept   = $this->create_glossary( 'kept', 'fr_FR' );

		$this->repository->save_term( $doomed, $this->term( array( 'alternates' => array( 'Éditeur' ) ) ), 0, '2026-06-01 11:00:00' );
		$kept_term = $this->repository->save_term( $kept, $this->term(), 0, '2026-06-01 11:00:00' );

		$catalog_id     = $this->storage->upsert_source_resource( 'akismet/akismet.php', 'akismet', '{}', '2026-06-01 10:00:00' );
		$translation_id = $this->storage->ensure_translation_resource( 42, 'akismet/akismet.php', 'fr_FR', '2026-06-01 10:00:00' );

		$this->assertTrue( $this->repository->delete_glossary( $doomed ) );

		$this->assertNull( $this->repository->get_glossary( $doomed ) );
		$this->assertNotNull( $this->repository->get_glossary( $kept ) );
		$this->assertSame( array( 'Éditeur de blocs' ), $this->stored_targets( $kept_term->get_id() ) );
		$this->assertCount( 1, $this->wpdb->rows( 'i18nly_linguistic_resource_entries' ) );
		$this->assertSame( $translation_id, $this->storage->find_translation_resource_id( 42 ) );
		$this->assertCount( 3, $this->wpdb->rows( 'i18nly_linguistic_resources' ) );
		$this->assertGreaterThan( 0, $catalog_id );
		$this->assertFalse( $this->repository->delete_glossary( $doomed ) );
	}

	/**
	 * Refuses to delete a resource of another kind as a glossary.
	 *
	 * @return void
	 */
	public function test_delete_glossary_ignores_other_resource_kinds() {
		$translation_id = $this->storage->ensure_translation_resource( 42, 'akismet/akismet.php', 'fr_FR', '2026-06-01 10:00:00' );

		$this->assertFalse( $this->repository->delete_glossary( $translation_id ) );
		$this->assertSame( $translation_id, $this->storage->find_translation_resource_id( 42 ) );
	}

	/**
	 * Identifies the repository as the glossary one.
	 *
	 * @return void
	 */
	public function test_repository_kind_is_glossary() {
		$this->assertSame( 'glossary', $this->repository->get_resource_kind() );
	}
}
