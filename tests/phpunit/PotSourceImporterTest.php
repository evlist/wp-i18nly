<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * POT source importer tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

// phpcs:disable WordPress.WP.AlternativeFunctions, Generic.Files.OneObjectStructurePerFile

/**
 * Tests POT source import into source entries repository.
 */
class PotSourceImporterTest extends TestCase {
	/**
	 * Imports singular and plural entries and stays idempotent.
	 *
	 * @return void
	 */
	public function test_import_file_inserts_entries_then_is_idempotent() {
		$pot_content = <<<POT
msgid ""
msgstr ""
"Project-Id-Version: i18nly\\n"
"X-Domain: sample-plugin\\n"
"Plural-Forms: nplurals=2; plural=n != 1;\\n"

#. translators: Greeting message.
#: plugin/file.php:10
msgid "Hello world"
msgstr ""

#. translators: Label for item count.
#: plugin/file.php:20
msgctxt "list"
msgid "%s item"
msgid_plural "%s items"
msgstr[0] ""
msgstr[1] ""
POT;

		$temp_file = sys_get_temp_dir() . '/i18nly-source-import-' . uniqid( '', true ) . '.pot';
		file_put_contents( $temp_file, $pot_content );

		$schema_stub = new I18nly_Test_Source_Schema_Manager_Stub();
		$repository  = new I18nly_Test_InMemory_Source_Repository();
		$importer    = new \WP_I18nly\Build\PotSourceImporter( $schema_stub, $repository );

		$first_summary = $importer->import_file( 'sample-plugin/sample.php', $temp_file );

		$this->assertSame( 2, $first_summary['inserted'] );
		$this->assertSame( 0, $first_summary['updated'] );
		$this->assertSame( 0, $first_summary['unchanged'] );
		$this->assertSame( 0, $first_summary['obsoleted'] );
		$this->assertCount( 2, $repository->entries );

		$comments_found = false;
		$context_found  = false;
		foreach ( $repository->entries as $entry ) {
			if ( '%s item' === $entry['msgid'] && 'list' === $entry['msgctxt'] ) {
				$context_found = true;
			}

			if ( 'Hello world' !== $entry['msgid'] ) {
				continue;
			}

			$comments = json_decode( (string) $entry['comments_json'], true );
			if ( isset( $comments['comments'] ) && in_array( 'translators: Greeting message.', (array) $comments['comments'], true ) ) {
				$comments_found = true;
			}

			if ( isset( $comments['extracted_comments'] ) && in_array( 'translators: Greeting message.', (array) $comments['extracted_comments'], true ) ) {
				$comments_found = true;
			}
		}

		$this->assertSame( true, $comments_found );
		$this->assertSame( true, $context_found );

		$second_summary = $importer->import_file( 'sample-plugin/sample.php', $temp_file );

		$this->assertSame( 0, $second_summary['inserted'] );
		$this->assertSame( 0, $second_summary['updated'] );
		$this->assertSame( 2, $second_summary['unchanged'] );
		$this->assertSame( 0, $second_summary['obsoleted'] );
		$this->assertCount( 2, $repository->entries );

		unlink( $temp_file );
	}

	/**
	 * Marks missing entries as obsolete after a subsequent import.
	 *
	 * @return void
	 */
	public function test_import_file_marks_missing_entries_obsolete() {
		$first_pot = <<<POT
msgid ""
msgstr ""
"Project-Id-Version: i18nly\\n"
"X-Domain: sample-plugin\\n"

msgid "First"
msgstr ""

msgid "Second"
msgstr ""
POT;

		$second_pot = <<<POT
msgid ""
msgstr ""
"Project-Id-Version: i18nly\\n"
"X-Domain: sample-plugin\\n"

msgid "First"
msgstr ""
POT;

		$temp_first  = sys_get_temp_dir() . '/i18nly-source-import-first-' . uniqid( '', true ) . '.pot';
		$temp_second = sys_get_temp_dir() . '/i18nly-source-import-second-' . uniqid( '', true ) . '.pot';

		file_put_contents( $temp_first, $first_pot );
		file_put_contents( $temp_second, $second_pot );

		$schema_stub = new I18nly_Test_Source_Schema_Manager_Stub();
		$repository  = new I18nly_Test_InMemory_Source_Repository();
		$importer    = new \WP_I18nly\Build\PotSourceImporter( $schema_stub, $repository );

		$importer->import_file( 'sample-plugin/sample.php', $temp_first );
		$summary = $importer->import_file( 'sample-plugin/sample.php', $temp_second );

		$this->assertSame( 1, $summary['obsoleted'] );

		$obsolete_found = false;
		foreach ( $repository->entries as $entry ) {
			if ( 'Second' !== $entry['msgid'] ) {
				continue;
			}

			if ( 'obsolete' === $entry['status'] ) {
				$obsolete_found = true;
			}
		}

		$this->assertSame( true, $obsolete_found );

		unlink( $temp_first );
		unlink( $temp_second );
	}
}

/**
 * In-memory schema manager stub.
 */
class I18nly_Test_Source_Schema_Manager_Stub extends \WP_I18nly\Storage\SourceSchemaManager {
	/**
	 * Upgrade invocation count.
	 *
	 * @var int
	 */
	public $upgrade_calls = 0;

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/**
	 * Captures upgrade calls.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		++$this->upgrade_calls;
	}
}

/**
 * In-memory source repository used to simulate DB insertions/upserts.
 */
class I18nly_Test_InMemory_Source_Repository {
	/**
	 * Catalog rows.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public $catalogs = array();

	/**
	 * Entry rows.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public $entries = array();

	/**
	 * Auto-increment catalog id counter.
	 *
	 * @var int
	 */
	private $catalog_auto_id = 1;

	/**
	 * Upserts one catalog row.
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @param string $domain Domain.
	 * @param string $headers_json Headers JSON.
	 * @param string $now_gmt GMT datetime.
	 * @return int
	 */
	public function upsert_catalog( $plugin_slug, $domain, $headers_json, $now_gmt ) {
		$key = (string) $plugin_slug;

		if ( isset( $this->catalogs[ $key ] ) ) {
			$this->catalogs[ $key ]['domain']         = (string) $domain;
			$this->catalogs[ $key ]['headers_json']   = (string) $headers_json;
			$this->catalogs[ $key ]['updated_at_gmt'] = (string) $now_gmt;

			return (int) $this->catalogs[ $key ]['id'];
		}

		$this->catalogs[ $key ] = array(
			'id'             => $this->catalog_auto_id,
			'plugin_slug'    => $key,
			'domain'         => (string) $domain,
			'headers_json'   => (string) $headers_json,
			'created_at_gmt' => (string) $now_gmt,
			'updated_at_gmt' => (string) $now_gmt,
		);

		++$this->catalog_auto_id;

		return (int) $this->catalogs[ $key ]['id'];
	}

	/**
	 * Upserts one source entry row.
	 *
	 * @param array<string, mixed> $entry Entry payload.
	 * @return string
	 */
	public function upsert_source_entry( array $entry ) {
		$key = (string) $entry['catalog_id']
			. '|' . (string) ( isset( $entry['msgctxt'] ) ? $entry['msgctxt'] : '' )
			. '|' . (string) $entry['msgid'];

		if ( ! isset( $this->entries[ $key ] ) ) {
			$this->entries[ $key ] = $entry;

			return 'inserted';
		}

		$existing = $this->entries[ $key ];

		$unchanged = (string) $existing['catalog_id'] === (string) $entry['catalog_id']
			&& (string) $existing['msgctxt'] === (string) $entry['msgctxt']
			&& (string) $existing['msgid'] === (string) $entry['msgid']
			&& (string) $existing['msgid_plural'] === (string) $entry['msgid_plural']
			&& (string) $existing['comments_json'] === (string) $entry['comments_json']
			&& (string) $existing['references_json'] === (string) $entry['references_json']
			&& (string) $existing['flags_json'] === (string) $entry['flags_json']
			&& (string) $existing['status'] === (string) $entry['status'];

		if ( $unchanged ) {
			$this->entries[ $key ] = array_merge(
				$existing,
				array(
					'last_seen_at_gmt' => $entry['last_seen_at_gmt'],
				)
			);

			return 'unchanged';
		}

		$entry['status']       = 'active';
		$this->entries[ $key ] = array_merge( $existing, $entry );

		return 'updated';
	}

	/**
	 * Marks active entries as obsolete when not seen in current import.
	 *
	 * @param int    $catalog_id Catalog ID.
	 * @param string $now_gmt Update datetime.
	 * @return int
	 */
	public function mark_obsolete_entries_not_seen( $catalog_id, $now_gmt ) {
		$obsoleted = 0;

		foreach ( $this->entries as $key => $entry ) {
			if ( (int) $entry['catalog_id'] !== (int) $catalog_id ) {
				continue;
			}

			if ( 'active' !== (string) $entry['status'] ) {
				continue;
			}

			if ( isset( $entry['last_seen_at_gmt'] ) && '' !== (string) $entry['last_seen_at_gmt'] ) {
				continue;
			}

			$this->entries[ $key ]['status']         = 'obsolete';
			$this->entries[ $key ]['updated_at_gmt'] = (string) $now_gmt;
			++$obsoleted;
		}

		return $obsoleted;
	}

	/**
	 * Clears last_seen marker for active entries in one catalog.
	 *
	 * @param int $catalog_id Catalog ID.
	 * @return void
	 */
	public function reset_last_seen_for_catalog( $catalog_id ) {
		foreach ( $this->entries as $key => $entry ) {
			if ( (int) $entry['catalog_id'] !== (int) $catalog_id ) {
				continue;
			}

			if ( 'active' !== (string) $entry['status'] ) {
				continue;
			}

			$this->entries[ $key ]['last_seen_at_gmt'] = null;
		}
	}
}

// phpcs:enable
