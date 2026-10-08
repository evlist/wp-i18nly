<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Source wpdb repository tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.Files.OneObjectStructurePerFile

/**
 * Tests resource-centric target storage while keeping translation-facing APIs.
 */
class SourceWpdbRepositoryTest extends TestCase {
	/**
	 * Persists target rows under resource_id while keeping translation methods unchanged.
	 *
	 * @return void
	 */
	public function test_translation_target_rows_use_resource_id_storage() {
		$wpdb_stub = new I18nly_Test_WPDB_Repository_Stub();
		$manager   = new \WP_I18nly\Storage\SourceSchemaManager( $wpdb_stub );
		$repo      = new \WP_I18nly\Storage\SourceWpdbRepository( $manager, $wpdb_stub );

		$catalog_id = $repo->upsert_source_resource( 'sample-plugin/sample.php', 'sample-plugin', '{}', '2026-05-10 09:00:00' );
		$entry_id   = $wpdb_stub->seed_entry(
			array(
				'resource_id'        => $catalog_id,
				'msgctxt'            => '',
				'msgid'              => 'Hello world',
				'msgid_plural'       => '',
				'translator_comment' => '',
				'references_json'    => '{"assets/js/app.js":[3,10],"includes/a.php":[7]}',
				'status'             => 'active',
				'last_seen_at_gmt'   => '2026-05-10 10:00:00',
				'updated_at_gmt'     => '2026-05-10 10:00:00',
			)
		);

		$inserted = $repo->ensure_translation_target_rows( 42, 'sample-plugin/sample.php', 'fr_FR', '2026-05-10 11:00:00', 2 );

		$translation_resource_id = $repo->find_translation_resource_id( 42 );

		$this->assertSame( 1, $inserted );
		$this->assertGreaterThan( 0, $translation_resource_id );
		$this->assertNotSame( 42, $translation_resource_id );
		$this->assertCount( 1, $wpdb_stub->get_targets() );
		$this->assertSame( $translation_resource_id, $wpdb_stub->get_targets()[0]['resource_id'] );
		$this->assertArrayNotHasKey( 'translation_id', $wpdb_stub->get_targets()[0] );

		$this->assertTrue( $repo->upsert_translation_target( 42, $entry_id, 0, 'Bonjour le monde', '2026-05-10 12:00:00', 'translated', 1, 0 ) );

		$rows = $repo->list_translation_rows( 42, 'sample-plugin/sample.php', 500, 2 );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'Hello world', $rows[0]['msgid'] );
		$this->assertSame(
			array(
				'assets/js/app.js' => array( 3, 10 ),
				'includes/a.php'   => array( 7 ),
			),
			$rows[0]['references']
		);
		$this->assertCount( 1, $rows[0]['translations'] );
		$this->assertSame( 'Bonjour le monde', $rows[0]['translations'][0]['translation'] );
		$this->assertSame( 'translated', $rows[0]['translations'][0]['status'] );
		$this->assertSame( 1, $rows[0]['translations'][0]['used_ai'] );
		$this->assertSame( 0, $rows[0]['translations'][0]['used_manual'] );
	}

	/**
	 * Ensuring the targets reads the existing ones with one query, not one per entry and form.
	 *
	 * @return void
	 */
	public function test_ensure_translation_target_rows_uses_one_query_to_find_existing_targets() {
		$wpdb_stub = new I18nly_Test_WPDB_Repository_Stub();
		$manager   = new \WP_I18nly\Storage\SourceSchemaManager( $wpdb_stub );
		$repo      = new \WP_I18nly\Storage\SourceWpdbRepository( $manager, $wpdb_stub );

		$catalog_id = $repo->upsert_source_resource( 'sample-plugin/sample.php', 'sample-plugin', '{}', '2026-05-10 09:00:00' );

		foreach ( array( 'One', 'Two', 'Three' ) as $msgid ) {
			$wpdb_stub->seed_entry(
				array(
					'resource_id'        => $catalog_id,
					'msgctxt'            => '',
					'msgid'              => $msgid,
					'msgid_plural'       => $msgid . 's',
					'translator_comment' => '',
					'status'             => 'active',
					'last_seen_at_gmt'   => '2026-05-10 10:00:00',
					'updated_at_gmt'     => '2026-05-10 10:00:00',
				)
			);
		}

		$first  = $repo->ensure_translation_target_rows( 42, 'sample-plugin/sample.php', 'fr_FR', '2026-05-10 11:00:00', 2 );
		$second = $repo->ensure_translation_target_rows( 42, 'sample-plugin/sample.php', 'fr_FR', '2026-05-10 11:05:00', 2 );

		$this->assertSame( 6, $first );
		$this->assertSame( 0, $second );
		$this->assertSame( 2, $wpdb_stub->existing_targets_queries );
	}

	/**
	 * Creates one translation resource row anchored on the translation post.
	 *
	 * @return void
	 */
	public function test_ensure_translation_resource_creates_one_anchored_row_once() {
		$wpdb_stub = new I18nly_Test_WPDB_Repository_Stub();
		$manager   = new \WP_I18nly\Storage\SourceSchemaManager( $wpdb_stub );
		$repo      = new \WP_I18nly\Storage\SourceWpdbRepository( $manager, $wpdb_stub );

		$this->assertSame( 0, $repo->find_translation_resource_id( 42 ) );

		$first  = $repo->ensure_translation_resource( 42, 'sample-plugin/sample.php', 'fr_FR', '2026-05-10 11:00:00' );
		$second = $repo->ensure_translation_resource( 42, 'sample-plugin/sample.php', 'fr_FR', '2026-05-10 12:00:00' );

		$this->assertGreaterThan( 0, $first );
		$this->assertSame( $first, $second );
		$this->assertSame( $first, $repo->find_translation_resource_id( 42 ) );
		$this->assertCount( 1, $wpdb_stub->get_resources() );

		$row = $wpdb_stub->get_resources()[0];
		$this->assertSame( 'translation', $row['resource_kind'] );
		$this->assertSame( 'sample-plugin/sample.php', $row['source_slug'] );
		$this->assertSame( 'fr_FR', $row['target_locale'] );
		$this->assertSame( 42, $row['anchor_post_id'] );
	}

	/**
	 * Keeps target rows of two translations apart.
	 *
	 * @return void
	 */
	public function test_translation_targets_are_isolated_per_translation_resource() {
		$wpdb_stub = new I18nly_Test_WPDB_Repository_Stub();
		$manager   = new \WP_I18nly\Storage\SourceSchemaManager( $wpdb_stub );
		$repo      = new \WP_I18nly\Storage\SourceWpdbRepository( $manager, $wpdb_stub );

		$catalog_id = $repo->upsert_source_resource( 'sample-plugin/sample.php', 'sample-plugin', '{}', '2026-05-10 09:00:00' );
		$entry_id   = $wpdb_stub->seed_entry(
			array(
				'resource_id'  => $catalog_id,
				'msgctxt'      => '',
				'msgid'        => 'Hello world',
				'msgid_plural' => '',
				'status'       => 'active',
			)
		);

		$repo->ensure_translation_target_rows( 42, 'sample-plugin/sample.php', 'fr_FR', '2026-05-10 11:00:00', 2 );
		$repo->ensure_translation_target_rows( 43, 'sample-plugin/sample.php', 'de_DE', '2026-05-10 11:00:00', 2 );
		$repo->upsert_translation_target( 42, $entry_id, 0, 'Bonjour le monde', '2026-05-10 12:00:00' );

		$french = $repo->list_translation_rows( 42, 'sample-plugin/sample.php', 500, 2 );
		$german = $repo->list_translation_rows( 43, 'sample-plugin/sample.php', 500, 2 );

		$this->assertNotSame( $repo->find_translation_resource_id( 42 ), $repo->find_translation_resource_id( 43 ) );
		$this->assertSame( 'Bonjour le monde', $french[0]['translations'][0]['translation'] );
		$this->assertSame( '', $german[0]['translations'][0]['translation'] );
	}

	/**
	 * Refuses to write targets for a translation without a resource row.
	 *
	 * @return void
	 */
	public function test_upsert_translation_target_requires_existing_resource() {
		$wpdb_stub = new I18nly_Test_WPDB_Repository_Stub();
		$manager   = new \WP_I18nly\Storage\SourceSchemaManager( $wpdb_stub );
		$repo      = new \WP_I18nly\Storage\SourceWpdbRepository( $manager, $wpdb_stub );

		$this->assertFalse( $repo->upsert_translation_target( 42, 1, 0, 'Bonjour', '2026-05-10 12:00:00' ) );
		$this->assertCount( 0, $wpdb_stub->get_targets() );
	}

	/**
	 * Exposes the storage resource ID through the translation repository.
	 *
	 * @return void
	 */
	public function test_translation_repository_exposes_storage_resource_id() {
		$wpdb_stub = new I18nly_Test_WPDB_Repository_Stub();
		$manager   = new \WP_I18nly\Storage\SourceSchemaManager( $wpdb_stub );
		$storage   = new \WP_I18nly\Storage\SourceWpdbRepository( $manager, $wpdb_stub );
		$repo      = new \WP_I18nly\LinguisticResources\TranslationResourceRepository( $storage, $manager );

		$this->assertSame( 0, $repo->get_translation_resource_id( 42 ) );

		$repo->ensure_translation_targets( 42, 'sample-plugin/sample.php', 'fr_FR', '2026-05-10 11:00:00', 2 );

		$this->assertSame( $storage->find_translation_resource_id( 42 ), $repo->get_translation_resource_id( 42 ) );
		$this->assertGreaterThan( 0, $repo->get_translation_resource_id( 42 ) );
	}

	/**
	 * Deletes one translation resource with its targets and nothing else.
	 *
	 * @return void
	 */
	public function test_delete_translation_resource_removes_resource_and_its_targets_only() {
		$wpdb_stub = new I18nly_Test_WPDB_Repository_Stub();
		$manager   = new \WP_I18nly\Storage\SourceSchemaManager( $wpdb_stub );
		$repo      = new \WP_I18nly\Storage\SourceWpdbRepository( $manager, $wpdb_stub );

		$catalog_id = $repo->upsert_source_resource( 'sample-plugin/sample.php', 'sample-plugin', '{}', '2026-05-10 09:00:00' );
		$wpdb_stub->seed_entry(
			array(
				'resource_id'  => $catalog_id,
				'msgctxt'      => '',
				'msgid'        => 'Hello world',
				'msgid_plural' => '',
				'status'       => 'active',
			)
		);

		$repo->ensure_translation_target_rows( 42, 'sample-plugin/sample.php', 'fr_FR', '2026-05-10 11:00:00', 2 );
		$repo->ensure_translation_target_rows( 43, 'sample-plugin/sample.php', 'de_DE', '2026-05-10 11:00:00', 2 );
		$kept_resource_id = $repo->find_translation_resource_id( 43 );

		$this->assertTrue( $repo->delete_translation_resource( 42 ) );

		$this->assertSame( 0, $repo->find_translation_resource_id( 42 ) );
		$this->assertSame( $kept_resource_id, $repo->find_translation_resource_id( 43 ) );
		$this->assertCount( 1, $wpdb_stub->get_targets() );
		$this->assertSame( $kept_resource_id, $wpdb_stub->get_targets()[0]['resource_id'] );
		$this->assertCount( 2, $wpdb_stub->get_resources() );
		$this->assertFalse( $repo->delete_translation_resource( 42 ) );
	}

	/**
	 * Lets a new translation reuse the identity of a trashed one.
	 *
	 * @return void
	 */
	public function test_new_translation_resource_coexists_with_trashed_translation_of_same_identity() {
		$wpdb_stub = new I18nly_Test_WPDB_Repository_Stub();
		$manager   = new \WP_I18nly\Storage\SourceSchemaManager( $wpdb_stub );
		$repo      = new \WP_I18nly\Storage\SourceWpdbRepository( $manager, $wpdb_stub );

		$trashed = $repo->ensure_translation_resource( 42, 'sample-plugin/sample.php', 'fr_FR', '2026-05-10 11:00:00' );
		$new     = $repo->ensure_translation_resource( 77, 'sample-plugin/sample.php', 'fr_FR', '2026-05-11 11:00:00' );

		$this->assertGreaterThan( 0, $trashed );
		$this->assertGreaterThan( 0, $new );
		$this->assertNotSame( $trashed, $new );
		$this->assertSame( $new, $repo->find_translation_resource_id( 77 ) );
		$this->assertSame( $trashed, $repo->find_translation_resource_id( 42 ) );
	}

	/**
	 * Lists source rows through the new resource-centric alias.
	 *
	 * @return void
	 */
	public function test_source_resource_alias_lists_source_rows_by_source_slug() {
		$wpdb_stub = new I18nly_Test_WPDB_Repository_Stub();
		$manager   = new \WP_I18nly\Storage\SourceSchemaManager( $wpdb_stub );
		$repo      = new \WP_I18nly\Storage\SourceWpdbRepository( $manager, $wpdb_stub );

		$catalog_id = $repo->upsert_source_resource( 'sample-plugin/sample.php', 'sample-plugin', '{}', '2026-05-10 09:00:00' );
		$wpdb_stub->seed_entry(
			array(
				'resource_id'        => $catalog_id,
				'msgctxt'            => '',
				'msgid'              => 'Hello world',
				'msgid_plural'       => '',
				'translator_comment' => 'Greeting',
				'status'             => 'active',
				'last_seen_at_gmt'   => '2026-05-10 10:00:00',
				'updated_at_gmt'     => '2026-05-10 10:00:00',
			)
		);

		$rows = $repo->list_source_resource_entries_by_source_slug( 'sample-plugin/sample.php', 500 );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'Hello world', $rows[0]['msgid'] );
		$this->assertSame( 'Greeting', $rows[0]['translator_comment'] );
	}

	/**
	 * Lists source rows through the source catalog repository abstraction.
	 *
	 * @return void
	 */
	public function test_source_catalog_resource_repository_lists_entries() {
		$wpdb_stub = new I18nly_Test_WPDB_Repository_Stub();
		$manager   = new \WP_I18nly\Storage\SourceSchemaManager( $wpdb_stub );
		$storage   = new \WP_I18nly\Storage\SourceWpdbRepository( $manager, $wpdb_stub );
		$repo      = new \WP_I18nly\LinguisticResources\SourceCatalogResourceRepository( $storage, $manager );

		$catalog_id = $storage->upsert_source_resource( 'sample-plugin/sample.php', 'sample-plugin', '{}', '2026-05-10 09:00:00' );
		$wpdb_stub->seed_entry(
			array(
				'resource_id'        => $catalog_id,
				'msgctxt'            => '',
				'msgid'              => 'Hello world',
				'msgid_plural'       => '',
				'translator_comment' => 'Greeting',
				'status'             => 'active',
				'last_seen_at_gmt'   => '2026-05-10 10:00:00',
				'updated_at_gmt'     => '2026-05-10 10:00:00',
			)
		);

		$rows = $repo->list_source_catalog_entries( 'sample-plugin/sample.php', 500 );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'Hello world', $rows[0]['msgid'] );
		$this->assertSame( 'Greeting', $rows[0]['translator_comment'] );
	}
}

/**
 * Repository-focused wpdb stub with explicit access to stored target rows.
 */
class I18nly_Test_WPDB_Repository_Stub extends I18nly_Test_WPDB_Stub {
	/**
	 * Seeded resource rows.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $catalogs = array();

	/**
	 * Seeded entry rows.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $entries = array();

	/**
	 * Seeded target rows.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $targets = array();

	/**
	 * Number of queries listing the existing targets.
	 *
	 * @var int
	 */
	public $existing_targets_queries = 0;

	/**
	 * Seeds one resource row.
	 *
	 * @param array<string, mixed> $catalog Catalog row.
	 * @return void
	 */
	public function seed_catalog( array $catalog ) {
		$this->insert( $this->prefix . 'i18nly_linguistic_resources', $catalog );
		$this->insert_id = 0;
	}

	/**
	 * Seeds one source entry row.
	 *
	 * @param array<string, mixed> $entry Entry row.
	 * @return int
	 */
	public function seed_entry( array $entry ) {
		$this->insert( $this->prefix . 'i18nly_linguistic_resource_entries', $entry );
		$entry_id        = (int) $this->insert_id;
		$this->insert_id = 0;

		return $entry_id;
	}

	/**
	 * Deletes rows matching a simple where clause.
	 *
	 * @param string               $table Table name.
	 * @param array<string, mixed> $where Match conditions.
	 * @param array<int, string>   $where_format Where formats.
	 * @return int|false
	 */
	public function delete( $table, $where, $where_format = null ) {
		unset( $where_format );

		$table   = (string) $table;
		$deleted = 0;

		if ( false !== strpos( $table, 'i18nly_linguistic_resource_targets' ) ) {
			$property = 'targets';
		} elseif ( false !== strpos( $table, 'i18nly_linguistic_resources' ) ) {
			$property = 'catalogs';
		} else {
			return false;
		}

		foreach ( $this->{$property} as $index => $row ) {
			foreach ( $where as $column => $value ) {
				if ( ! isset( $row[ $column ] ) || (int) $row[ $column ] !== (int) $value ) {
					continue 2;
				}
			}

			unset( $this->{$property}[ $index ] );
			++$deleted;
		}

		$this->{$property} = array_values( $this->{$property} );

		return $deleted;
	}

	/**
	 * Returns stored resource rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_resources() {
		return $this->catalogs;
	}

	/**
	 * Returns stored target rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_targets() {
		return $this->targets;
	}

	/**
	 * Returns one scalar value for repository queries.
	 *
	 * @param string $query SQL query.
	 * @return mixed
	 */
	public function get_var( $query ) {
		$query = (string) $query;

		if ( preg_match( '/FROM\s+`?\w+i18nly_linguistic_resources`?\s+WHERE\s+resource_kind\s*=\s*\'([^\']+)\'\s+AND\s+source_slug\s*=\s*\'([^\']+)\'\s+AND\s+target_locale\s*=\s*\'([^\']*)\'/', $query, $matches ) ) {
			foreach ( $this->catalogs as $catalog ) {
				if ( stripslashes( $matches[1] ) === (string) $catalog['resource_kind'] && stripslashes( $matches[2] ) === (string) $catalog['source_slug'] && stripslashes( $matches[3] ) === (string) $catalog['target_locale'] ) {
					return (int) $catalog['id'];
				}
			}
		}

		if ( preg_match( '/FROM\s+`?\w+i18nly_linguistic_resources`?\s+WHERE\s+resource_kind\s*=\s*\'([^\']+)\'\s+AND\s+anchor_post_id\s*=\s*(\d+)/', $query, $matches ) ) {
			foreach ( $this->catalogs as $catalog ) {
				if ( stripslashes( $matches[1] ) === (string) $catalog['resource_kind'] && isset( $catalog['anchor_post_id'] ) && (int) $matches[2] === (int) $catalog['anchor_post_id'] ) {
					return (int) $catalog['id'];
				}
			}
		}

		if ( preg_match( '/FROM\s+`?\w+i18nly_linguistic_resource_targets`?\s+WHERE\s+resource_id\s*=\s*(\d+)\s+AND\s+source_entry_id\s*=\s*(\d+)\s+AND\s+form_index\s*=\s*(\d+)/', $query, $matches ) ) {
			foreach ( $this->targets as $target ) {
				if ( (int) $matches[1] === (int) $target['resource_id'] && (int) $matches[2] === (int) $target['source_entry_id'] && (int) $matches[3] === (int) $target['form_index'] ) {
					return (int) $target['id'];
				}
			}
		}

		return null;
	}

	/**
	 * Returns joined rows for repository queries.
	 *
	 * @param string $query SQL query.
	 * @param string $output Output type.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_results( $query, $output = ARRAY_A ) {
		unset( $output );

		$query = (string) $query;

		if ( false !== strpos( $query, 'SELECT e.id AS source_entry_id, e.msgctxt, e.msgid, e.msgid_plural, e.translator_comment, e.status, e.last_seen_at_gmt, e.updated_at_gmt FROM' ) ) {
			return $this->build_list_source_rows( $query );
		}

		if ( 1 === preg_match( '/^SELECT source_entry_id, form_index FROM\s+`?\w+i18nly_linguistic_resource_targets`?\s+WHERE resource_id = (\d+)$/', $query, $matches ) ) {
			++$this->existing_targets_queries;

			return array_values(
				array_map(
					static function ( $target ) {
						return array(
							'source_entry_id' => $target['source_entry_id'],
							'form_index'      => $target['form_index'],
						);
					},
					array_filter(
						$this->targets,
						static function ( $target ) use ( $matches ) {
							return (int) $matches[1] === (int) $target['resource_id'];
						}
					)
				)
			);
		}

		if ( false !== strpos( $query, 'SELECT e.id AS source_entry_id, e.msgid_plural FROM' ) ) {
			return $this->build_source_rows( $query );
		}

		if ( false !== strpos( $query, 'SELECT e.id AS source_entry_id, e.msgctxt, e.msgid' ) ) {
			return $this->build_translation_rows( $query );
		}

		return array();
	}

	/**
	 * Inserts one row into the in-memory repository.
	 *
	 * @param string               $table Table name.
	 * @param array<string, mixed> $data Row data.
	 * @param array<int, string>   $format Row format.
	 * @return int|false
	 */
	public function insert( $table, $data, $format = null ) {
		unset( $format );

		$table = (string) $table;

		if ( false !== strpos( $table, 'i18nly_linguistic_resources' ) ) {
			$this->insert_id  = count( $this->catalogs ) + 1;
			$data['id']       = $this->insert_id;
			$data            += array(
				'target_locale' => '',
			);
			$this->catalogs[] = $data;

			return 1;
		}

		if ( false !== strpos( $table, 'i18nly_linguistic_resource_entries' ) ) {
			$this->insert_id = count( $this->entries ) + 1;
			$data['id']      = $this->insert_id;
			$this->entries[] = $data;

			return 1;
		}

		if ( false !== strpos( $table, 'i18nly_linguistic_resource_targets' ) ) {
			$this->insert_id = count( $this->targets ) + 1;
			$data['id']      = $this->insert_id;
			$this->targets[] = $data;

			return 1;
		}

		return false;
	}

	/**
	 * Updates one row in the in-memory repository.
	 *
	 * @param string               $table Table name.
	 * @param array<string, mixed> $data Data to update.
	 * @param array<string, mixed> $where Match conditions.
	 * @param array<int, string>   $format Data formats.
	 * @param array<int, string>   $where_format Where formats.
	 * @return int|false
	 */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		unset( $format, $where_format );

		$table = (string) $table;

		if ( false !== strpos( $table, 'i18nly_linguistic_resource_targets' ) && isset( $where['id'] ) ) {
			foreach ( $this->targets as $index => $row ) {
				if ( (int) $row['id'] === (int) $where['id'] ) {
					$this->targets[ $index ] = array_merge( $row, $data );
					return 1;
				}
			}
		}

		if ( false !== strpos( $table, 'i18nly_linguistic_resources' ) && isset( $where['id'] ) ) {
			foreach ( $this->catalogs as $index => $row ) {
				if ( (int) $row['id'] === (int) $where['id'] ) {
					$this->catalogs[ $index ] = array_merge( $row, $data );
					return 1;
				}
			}
		}

		return false;
	}

	/**
	 * Builds source-entry rows for ensure_translation_target_rows().
	 *
	 * @param string $query Prepared query.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_source_rows( $query ) {
		$catalog_ids = $this->match_catalog_ids( $query );
		$results     = array();

		foreach ( $this->entries as $entry ) {
			if ( ! in_array( (int) $entry['resource_id'], $catalog_ids, true ) ) {
				continue;
			}

			$results[] = array(
				'source_entry_id' => (int) $entry['id'],
				'msgid_plural'    => isset( $entry['msgid_plural'] ) ? (string) $entry['msgid_plural'] : '',
			);
		}

		return $results;
	}

	/**
	 * Builds source rows for list_source_resource_entries_by_source_slug().
	 *
	 * @param string $query Prepared query.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_list_source_rows( $query ) {
		$catalog_ids = $this->match_catalog_ids( $query );
		$results     = array();

		foreach ( $this->entries as $entry ) {
			if ( ! in_array( (int) $entry['resource_id'], $catalog_ids, true ) ) {
				continue;
			}

			$results[] = array(
				'source_entry_id'    => (int) $entry['id'],
				'msgctxt'            => isset( $entry['msgctxt'] ) ? (string) $entry['msgctxt'] : '',
				'msgid'              => isset( $entry['msgid'] ) ? (string) $entry['msgid'] : '',
				'msgid_plural'       => isset( $entry['msgid_plural'] ) ? (string) $entry['msgid_plural'] : '',
				'translator_comment' => isset( $entry['translator_comment'] ) ? (string) $entry['translator_comment'] : '',
				'status'             => isset( $entry['status'] ) ? (string) $entry['status'] : 'active',
				'last_seen_at_gmt'   => isset( $entry['last_seen_at_gmt'] ) ? (string) $entry['last_seen_at_gmt'] : '',
				'updated_at_gmt'     => isset( $entry['updated_at_gmt'] ) ? (string) $entry['updated_at_gmt'] : '',
			);
		}

		return $results;
	}

	/**
	 * Builds translation rows for list_translation_rows().
	 *
	 * @param string $query Prepared query.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_translation_rows( $query ) {
		$catalog_ids        = $this->match_catalog_ids( $query );
		$target_resource_id = $this->match_target_resource_id( $query );
		$results            = array();

		foreach ( $this->entries as $entry ) {
			if ( ! in_array( (int) $entry['resource_id'], $catalog_ids, true ) ) {
				continue;
			}

			$matching_targets = array();
			foreach ( $this->targets as $target ) {
				if ( $target_resource_id === (int) $target['resource_id'] && (int) $entry['id'] === (int) $target['source_entry_id'] ) {
					$matching_targets[] = $target;
				}
			}

			if ( empty( $matching_targets ) ) {
				$results[] = $this->build_joined_row( $entry );
				continue;
			}

			foreach ( $matching_targets as $target ) {
				$results[] = $this->build_joined_row( $entry, $target );
			}
		}

		return $results;
	}

	/**
	 * Matches source catalog IDs from one prepared query.
	 *
	 * @param string $query Prepared query.
	 * @return array<int, int>
	 */
	private function match_catalog_ids( $query ) {
		if ( ! preg_match( '/c\.resource_kind = \'([^\']+)\' AND c\.source_slug = \'([^\']+)\'/', (string) $query, $matches ) ) {
			return array();
		}

		$catalog_ids = array();

		foreach ( $this->catalogs as $catalog ) {
			if ( stripslashes( $matches[1] ) === (string) $catalog['resource_kind'] && stripslashes( $matches[2] ) === (string) $catalog['source_slug'] ) {
				$catalog_ids[] = (int) $catalog['id'];
			}
		}

		return $catalog_ids;
	}

	/**
	 * Matches the target resource ID from one prepared query.
	 *
	 * @param string $query Prepared query.
	 * @return int
	 */
	private function match_target_resource_id( $query ) {
		if ( ! preg_match( '/t\\.resource_id = (\\d+)/', (string) $query, $matches ) ) {
			return 0;
		}

		return (int) $matches[1];
	}

	/**
	 * Builds one joined row returned by list_translation_rows().
	 *
	 * @param array<string, mixed>      $entry Source entry row.
	 * @param array<string, mixed>|null $target Target row.
	 * @return array<string, mixed>
	 */
	private function build_joined_row( array $entry, array $target = null ) {
		$row = array(
			'source_entry_id'            => (int) $entry['id'],
			'msgctxt'                    => isset( $entry['msgctxt'] ) ? (string) $entry['msgctxt'] : '',
			'msgid'                      => isset( $entry['msgid'] ) ? (string) $entry['msgid'] : '',
			'msgid_plural'               => isset( $entry['msgid_plural'] ) ? (string) $entry['msgid_plural'] : '',
			'translator_comment'         => isset( $entry['translator_comment'] ) ? (string) $entry['translator_comment'] : '',
			'references_json'            => isset( $entry['references_json'] ) ? (string) $entry['references_json'] : '',
			'source_status'              => isset( $entry['status'] ) ? (string) $entry['status'] : 'active',
			'last_seen_at_gmt'           => isset( $entry['last_seen_at_gmt'] ) ? (string) $entry['last_seen_at_gmt'] : '',
			'updated_at_gmt'             => isset( $entry['updated_at_gmt'] ) ? (string) $entry['updated_at_gmt'] : '',
			'translation_updated_at_gmt' => '',
		);

		if ( null === $target ) {
			return $row;
		}

		$row['form_index']                 = (int) $target['form_index'];
		$row['translation']                = isset( $target['target_text'] ) ? (string) $target['target_text'] : '';
		$row['translated_status']          = isset( $target['status'] ) ? (string) $target['status'] : 'draft';
		$row['used_ai']                    = isset( $target['used_ai'] ) ? (int) $target['used_ai'] : 0;
		$row['used_manual']                = isset( $target['used_manual'] ) ? (int) $target['used_manual'] : 1;
		$row['comment']                    = isset( $target['comment'] ) ? (string) $target['comment'] : '';
		$row['translation_updated_at_gmt'] = isset( $target['updated_at_gmt'] ) ? (string) $target['updated_at_gmt'] : '';

		return $row;
	}
}

// phpcs:enable
