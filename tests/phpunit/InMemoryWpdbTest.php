<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * In-memory wpdb double tests.
 *
 * @package I18nly
 */

// phpcs:disable WordPress.DB

use PHPUnit\Framework\TestCase;

/**
 * Checks the behaviors the repository tests rely on.
 */
class InMemoryWpdbTest extends TestCase {
	/**
	 * Entries table name.
	 *
	 * @var string
	 */
	private $entries = 'wp_i18nly_linguistic_resource_entries';

	/**
	 * Emulates a case-insensitive unique key.
	 *
	 * @return void
	 */
	public function test_unique_keys_ignore_case_and_null_keys() {
		$wpdb = new I18nly_Test_InMemory_Wpdb();

		$this->assertSame(
			1,
			$wpdb->insert(
				$this->entries,
				array(
					'resource_id' => 1,
					'msgctxt'     => '',
					'msgid'       => 'API',
				)
			)
		);
		$this->assertFalse(
			$wpdb->insert(
				$this->entries,
				array(
					'resource_id' => 1,
					'msgctxt'     => '',
					'msgid'       => 'api',
				)
			)
		);
		$this->assertSame(
			1,
			$wpdb->insert(
				$this->entries,
				array(
					'resource_id' => 2,
					'msgctxt'     => '',
					'msgid'       => 'api',
				)
			)
		);
		$this->assertSame(
			1,
			$wpdb->insert(
				$this->entries,
				array(
					'resource_id' => 1,
					'msgctxt'     => null,
					'msgid'       => 'API',
				)
			),
			'A NULL key part never conflicts.'
		);
	}

	/**
	 * Applies column defaults and auto-increment.
	 *
	 * @return void
	 */
	public function test_insert_applies_defaults_and_ids() {
		$wpdb = new I18nly_Test_InMemory_Wpdb();

		$wpdb->insert(
			$this->entries,
			array(
				'resource_id' => 1,
				'msgctxt'     => '',
				'msgid'       => 'One',
			)
		);
		$wpdb->insert(
			$this->entries,
			array(
				'resource_id' => 1,
				'msgctxt'     => '',
				'msgid'       => 'Two',
			)
		);

		$this->assertSame( 2, $wpdb->insert_id );
		$this->assertSame( 'exact', $wpdb->rows( 'i18nly_linguistic_resource_entries' )[0]['match_mode'] );
	}

	/**
	 * Runs SELECT statements with aliases, conditions, ordering and limit.
	 *
	 * @return void
	 */
	public function test_select_supports_aliases_conditions_ordering_and_limit() {
		$wpdb = new I18nly_Test_InMemory_Wpdb();

		foreach ( array( 'banana', 'Apple', 'cherry' ) as $term ) {
			$wpdb->insert(
				$this->entries,
				array(
					'resource_id' => 1,
					'msgctxt'     => '',
					'msgid'       => $term,
				)
			);
		}

		$wpdb->insert(
			$this->entries,
			array(
				'resource_id' => 2,
				'msgctxt'     => '',
				'msgid'       => 'other',
			)
		);

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id AS entry_id, msgid AS term FROM %i WHERE resource_id = %d ORDER BY msgid ASC, id ASC', $this->entries, 1 ), ARRAY_A );

		$this->assertSame( array( 'Apple', 'banana', 'cherry' ), array_column( $rows, 'term' ) );
		$this->assertSame( array( 'entry_id', 'term' ), array_keys( $rows[0] ) );

		$this->assertSame( '3', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE resource_id = %d AND msgid = %s', $this->entries, 1, 'CHERRY' ) ), 'Text comparison is case-insensitive.' );
		$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE resource_id = %d AND msgid = %s', $this->entries, 1, 'other' ) ) );
		$this->assertSame( 'cherry', $wpdb->get_row( $wpdb->prepare( 'SELECT msgid FROM %i WHERE resource_id = %d ORDER BY msgid DESC LIMIT 1', $this->entries, 1 ), ARRAY_A )['msgid'] );
	}

	/**
	 * Rolls a transaction back.
	 *
	 * @return void
	 */
	public function test_rollback_restores_the_rows() {
		$wpdb = new I18nly_Test_InMemory_Wpdb();

		$wpdb->insert(
			$this->entries,
			array(
				'resource_id' => 1,
				'msgctxt'     => '',
				'msgid'       => 'kept',
			)
		);
		$wpdb->query( 'START TRANSACTION' );
		$wpdb->insert(
			$this->entries,
			array(
				'resource_id' => 1,
				'msgctxt'     => '',
				'msgid'       => 'dropped',
			)
		);
		$wpdb->delete( $this->entries, array( 'msgid' => 'kept' ) );
		$wpdb->query( 'ROLLBACK' );

		$this->assertSame( array( 'kept' ), array_column( $wpdb->rows( 'i18nly_linguistic_resource_entries' ), 'msgid' ) );

		$wpdb->query( 'START TRANSACTION' );
		$wpdb->insert(
			$this->entries,
			array(
				'resource_id' => 1,
				'msgctxt'     => '',
				'msgid'       => 'committed',
			)
		);
		$wpdb->query( 'COMMIT' );

		$this->assertCount( 2, $wpdb->rows( 'i18nly_linguistic_resource_entries' ) );
	}

	/**
	 * Updates and deletes by conditions, and can be told to fail an insert.
	 *
	 * @return void
	 */
	public function test_update_delete_and_forced_insert_failure() {
		$wpdb = new I18nly_Test_InMemory_Wpdb();

		$wpdb->insert(
			$this->entries,
			array(
				'resource_id' => 1,
				'msgctxt'     => '',
				'msgid'       => 'one',
			)
		);

		$this->assertSame( 1, $wpdb->update( $this->entries, array( 'msgid' => 'uno' ), array( 'id' => 1 ) ) );
		$this->assertSame( 0, $wpdb->update( $this->entries, array( 'msgid' => 'uno' ), array( 'id' => 1 ) ), 'Nothing changed.' );

		$wpdb->fail_insert_into( 'i18nly_linguistic_resource_entries', 1 );

		$this->assertSame(
			1,
			$wpdb->insert(
				$this->entries,
				array(
					'resource_id' => 1,
					'msgctxt'     => '',
					'msgid'       => 'two',
				)
			)
		);
		$this->assertFalse(
			$wpdb->insert(
				$this->entries,
				array(
					'resource_id' => 1,
					'msgctxt'     => '',
					'msgid'       => 'three',
				)
			)
		);
		$this->assertSame(
			1,
			$wpdb->insert(
				$this->entries,
				array(
					'resource_id' => 1,
					'msgctxt'     => '',
					'msgid'       => 'four',
				)
			)
		);
		$this->assertSame( 3, $wpdb->delete( $this->entries, array( 'resource_id' => 1 ) ) );
	}
}

// phpcs:enable
