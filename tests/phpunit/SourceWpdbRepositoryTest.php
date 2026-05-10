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

		$catalog_id = $repo->upsert_catalog( 'sample-plugin/sample.php', 'sample-plugin', '{}', '2026-05-10 09:00:00' );
		$entry_id   = $wpdb_stub->seed_entry(
			array(
				'resource_id'        => $catalog_id,
				'msgctxt'            => '',
				'msgid'              => 'Hello world',
				'msgid_plural'       => '',
				'translator_comment' => '',
				'status'             => 'active',
				'last_seen_at_gmt'   => '2026-05-10 10:00:00',
				'updated_at_gmt'     => '2026-05-10 10:00:00',
			)
		);

		$inserted = $repo->ensure_translated_entries_for_translation( 42, 'sample-plugin/sample.php', '2026-05-10 11:00:00', 2 );

		$this->assertSame( 1, $inserted );
		$this->assertCount( 1, $wpdb_stub->get_targets() );
		$this->assertSame( 42, $wpdb_stub->get_targets()[0]['resource_id'] );
		$this->assertArrayNotHasKey( 'translation_id', $wpdb_stub->get_targets()[0] );

		$this->assertTrue( $repo->upsert_translated_entry( 42, $entry_id, 0, 'Bonjour le monde', '2026-05-10 12:00:00', 'translated', 1, 0 ) );

		$rows = $repo->list_translation_entries_by_plugin_slug( 42, 'sample-plugin/sample.php', 500, 2 );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'Hello world', $rows[0]['msgid'] );
		$this->assertCount( 1, $rows[0]['translations'] );
		$this->assertSame( 'Bonjour le monde', $rows[0]['translations'][0]['translation'] );
		$this->assertSame( 'translated', $rows[0]['translations'][0]['status'] );
		$this->assertSame( 1, $rows[0]['translations'][0]['used_ai'] );
		$this->assertSame( 0, $rows[0]['translations'][0]['used_manual'] );
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
			$this->insert_id = count( $this->catalogs ) + 1;
			$data['id']      = $this->insert_id;
			$data += array(
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
	 * Builds source-entry rows for ensure_translated_entries_for_translation().
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
	 * Builds translation rows for list_translation_entries_by_plugin_slug().
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
	 * Builds one joined row returned by list_translation_entries_by_plugin_slug().
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
