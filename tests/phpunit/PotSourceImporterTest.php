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

// phpcs:disable WordPress.WP.AlternativeFunctions

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
		$importer    = new I18nly_Pot_Source_Importer( $schema_stub, $repository );

		$first_summary = $importer->import_file( 'sample-plugin/sample.php', $temp_file );

		$this->assertSame( 3, $first_summary['inserted'] );
		$this->assertSame( 0, $first_summary['updated'] );
		$this->assertSame( 0, $first_summary['unchanged'] );
		$this->assertCount( 3, $repository->entries );

		$second_summary = $importer->import_file( 'sample-plugin/sample.php', $temp_file );

		$this->assertSame( 0, $second_summary['inserted'] );
		$this->assertSame( 0, $second_summary['updated'] );
		$this->assertSame( 3, $second_summary['unchanged'] );
		$this->assertCount( 3, $repository->entries );

		unlink( $temp_file );
	}
}

/**
 * In-memory schema manager stub.
 */
class I18nly_Test_Source_Schema_Manager_Stub extends I18nly_Source_Schema_Manager {
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
			. '|' . (string) $entry['msgid']
			. '|' . (string) $entry['plural_index'];

		if ( ! isset( $this->entries[ $key ] ) ) {
			$this->entries[ $key ] = $entry;

			return 'inserted';
		}

		$existing = $this->entries[ $key ];

		if ( (string) $existing['msgid_plural'] === (string) $entry['msgid_plural']
			&& (string) $existing['comments_json'] === (string) $entry['comments_json']
			&& (string) $existing['references_json'] === (string) $entry['references_json']
			&& (string) $existing['flags_json'] === (string) $entry['flags_json']
			&& (string) $existing['status'] === (string) $entry['status'] ) {
			return 'unchanged';
		}

		$this->entries[ $key ] = array_merge( $existing, $entry );

		return 'updated';
	}
}

// phpcs:enable
