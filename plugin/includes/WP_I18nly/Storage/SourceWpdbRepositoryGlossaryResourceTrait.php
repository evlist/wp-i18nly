<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Source wpdb repository glossary-resource helpers.
 *
 * @package I18nly
 */

namespace WP_I18nly\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Shares glossary persistence and read helpers for the source wpdb repository.
 *
 * A glossary is a resource row of kind glossary, identified by its slug and target locale. Its terms
 * are entry rows (the term is the msgid, with an empty msgctxt so that the unique key applies) and
 * the translations of a term are target rows whose form index is the rank of the variant: 0 for the
 * preferred translation, 1 and above for the alternates.
 */
trait SourceWpdbRepositoryGlossaryResourceTrait {
	/**
	 * Finds the glossary resource ID for one slug and target locale.
	 *
	 * @param string $slug Glossary slug.
	 * @param string $target_locale Target locale.
	 * @return int Glossary resource ID, or 0 when none exists.
	 */
	public function find_glossary_resource_id( $slug, $target_locale ) {
		$table = $this->escape_table_name( $this->schema_manager->get_resources_table_name() );

		if ( '' === $table ) {
			return 0;
		}

		return (int) $this->db_get_var(
			$this->wpdb->prepare(
				'SELECT id FROM %i WHERE resource_kind = %s AND source_slug = %s AND target_locale = %s',
				$table,
				self::GLOSSARY_RESOURCE_KIND,
				(string) $slug,
				(string) $target_locale
			)
		);
	}

	/**
	 * Inserts one glossary resource row.
	 *
	 * @param string $slug Glossary slug.
	 * @param string $source_locale Source locale.
	 * @param string $target_locale Target locale.
	 * @param string $now_gmt Current GMT datetime.
	 * @return int Glossary resource ID, or 0 when it cannot be stored.
	 */
	public function insert_glossary_resource( $slug, $source_locale, $target_locale, $now_gmt ) {
		$table = $this->escape_table_name( $this->schema_manager->get_resources_table_name() );

		if ( '' === $table ) {
			return 0;
		}

		$result = $this->wpdb->insert(
			$table,
			array(
				'resource_kind'  => self::GLOSSARY_RESOURCE_KIND,
				'source_slug'    => (string) $slug,
				'source_locale'  => (string) $source_locale,
				'target_locale'  => (string) $target_locale,
				'created_at_gmt' => (string) $now_gmt,
				'updated_at_gmt' => (string) $now_gmt,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $result ? 0 : (int) $this->wpdb->insert_id;
	}

	/**
	 * Returns the row of one glossary resource.
	 *
	 * @param int $glossary_id Glossary resource ID.
	 * @return array<string, mixed>|null Row with id, source_slug, source_locale and target_locale.
	 */
	public function get_glossary_resource_row( $glossary_id ) {
		$table = $this->escape_table_name( $this->schema_manager->get_resources_table_name() );

		if ( '' === $table ) {
			return null;
		}

		return $this->db_get_row(
			$this->wpdb->prepare(
				'SELECT id, source_slug, source_locale, target_locale FROM %i WHERE id = %d AND resource_kind = %s',
				$table,
				(int) $glossary_id,
				self::GLOSSARY_RESOURCE_KIND
			),
			ARRAY_A
		);
	}

	/**
	 * Lists every glossary resource row.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_glossary_resource_rows() {
		$table = $this->escape_table_name( $this->schema_manager->get_resources_table_name() );

		if ( '' === $table ) {
			return array();
		}

		return $this->db_get_results(
			$this->wpdb->prepare(
				'SELECT id, source_slug, source_locale, target_locale FROM %i WHERE resource_kind = %s ORDER BY source_slug ASC, target_locale ASC, id ASC',
				$table,
				self::GLOSSARY_RESOURCE_KIND
			),
			ARRAY_A
		);
	}

	/**
	 * Deletes one glossary resource with its terms and translations.
	 *
	 * @param int $glossary_id Glossary resource ID.
	 * @return bool True when the glossary existed and was deleted.
	 */
	public function delete_glossary_resource( $glossary_id ) {
		$resources_table = $this->escape_table_name( $this->schema_manager->get_resources_table_name() );
		$entries_table   = $this->escape_table_name( $this->schema_manager->get_resource_entries_table_name() );
		$targets_table   = $this->escape_table_name( $this->schema_manager->get_resource_targets_table_name() );

		if ( '' === $resources_table || '' === $entries_table || '' === $targets_table || null === $this->get_glossary_resource_row( $glossary_id ) ) {
			return false;
		}

		return (bool) $this->run_in_transaction(
			function () use ( $glossary_id, $resources_table, $entries_table, $targets_table ) {
				if ( false === $this->wpdb->delete( $targets_table, array( 'resource_id' => (int) $glossary_id ), array( '%d' ) ) ) {
					return false;
				}

				if ( false === $this->wpdb->delete( $entries_table, array( 'resource_id' => (int) $glossary_id ), array( '%d' ) ) ) {
					return false;
				}

				$deleted = $this->wpdb->delete(
					$resources_table,
					array(
						'id'            => (int) $glossary_id,
						'resource_kind' => self::GLOSSARY_RESOURCE_KIND,
					),
					array( '%d', '%s' )
				);

				return false !== $deleted && $deleted > 0;
			}
		);
	}

	/**
	 * Lists the term rows of one glossary.
	 *
	 * @param int $glossary_id Glossary resource ID.
	 * @return array<int, array<string, mixed>> Rows with source_entry_id, term, match_mode, note and status.
	 */
	public function list_glossary_term_rows( $glossary_id ) {
		$table = $this->escape_table_name( $this->schema_manager->get_resource_entries_table_name() );

		if ( '' === $table ) {
			return array();
		}

		return $this->db_get_results(
			$this->wpdb->prepare(
				'SELECT id AS source_entry_id, msgid AS term, match_mode, translator_comment AS note, status FROM %i WHERE resource_id = %d ORDER BY msgid ASC, id ASC',
				$table,
				(int) $glossary_id
			),
			ARRAY_A
		);
	}

	/**
	 * Lists the translation rows of every term of one glossary.
	 *
	 * @param int $glossary_id Glossary resource ID.
	 * @return array<int, array<string, mixed>> Rows with source_entry_id, form_index, translation, status, used_ai and used_manual.
	 */
	public function list_glossary_target_rows( $glossary_id ) {
		$table = $this->escape_table_name( $this->schema_manager->get_resource_targets_table_name() );

		if ( '' === $table ) {
			return array();
		}

		return $this->db_get_results(
			$this->wpdb->prepare(
				'SELECT source_entry_id, form_index, target_text AS translation, status, used_ai, used_manual FROM %i WHERE resource_id = %d ORDER BY source_entry_id ASC, form_index ASC',
				$table,
				(int) $glossary_id
			),
			ARRAY_A
		);
	}

	/**
	 * Inserts or updates one term with all its translations, in one transaction.
	 *
	 * @param int                  $glossary_id Glossary resource ID.
	 * @param int                  $entry_id Term entry ID to update, or 0 to insert a new term.
	 * @param array<string, mixed> $values Normalized term values: term, match_mode, note and targets (preferred first).
	 * @param string               $now_gmt Current GMT datetime.
	 * @return int Term entry ID, or 0 when nothing could be stored.
	 */
	public function save_glossary_term( $glossary_id, $entry_id, array $values, $now_gmt ) {
		$entries_table = $this->escape_table_name( $this->schema_manager->get_resource_entries_table_name() );
		$targets_table = $this->escape_table_name( $this->schema_manager->get_resource_targets_table_name() );

		if ( '' === $entries_table || '' === $targets_table ) {
			return 0;
		}

		return (int) $this->run_in_transaction(
			function () use ( $glossary_id, $entry_id, $values, $now_gmt, $entries_table, $targets_table ) {
				$saved_id = $this->write_glossary_term_entry( (int) $glossary_id, (int) $entry_id, $values, (string) $now_gmt, $entries_table );

				if ( $saved_id <= 0 ) {
					return 0;
				}

				if ( false === $this->wpdb->delete(
					$targets_table,
					array(
						'resource_id'     => (int) $glossary_id,
						'source_entry_id' => $saved_id,
					),
					array( '%d', '%d' )
				) ) {
					return 0;
				}

				foreach ( array_values( $values['targets'] ) as $rank => $text ) {
					$result = $this->wpdb->insert(
						$targets_table,
						array(
							'resource_id'     => (int) $glossary_id,
							'source_entry_id' => $saved_id,
							'form_index'      => (int) $rank,
							'target_text'     => (string) $text,
							'created_at_gmt'  => (string) $now_gmt,
							'updated_at_gmt'  => (string) $now_gmt,
						),
						array( '%d', '%d', '%d', '%s', '%s', '%s' )
					);

					if ( false === $result ) {
						return 0;
					}
				}

				return $saved_id;
			}
		);
	}

	/**
	 * Writes the entry row of a glossary term.
	 *
	 * @param int                  $glossary_id Glossary resource ID.
	 * @param int                  $entry_id Entry ID to update, or 0 to insert.
	 * @param array<string, mixed> $values Normalized term values.
	 * @param string               $now_gmt Current GMT datetime.
	 * @param string               $entries_table Escaped entries table name.
	 * @return int Entry ID, or 0 on failure.
	 */
	private function write_glossary_term_entry( $glossary_id, $entry_id, array $values, $now_gmt, $entries_table ) {
		if ( $entry_id > 0 ) {
			$updated = $this->wpdb->update(
				$entries_table,
				array(
					'msgid'              => (string) $values['term'],
					'match_mode'         => (string) $values['match_mode'],
					'translator_comment' => (string) $values['note'],
					'updated_at_gmt'     => $now_gmt,
				),
				array(
					'id'          => $entry_id,
					'resource_id' => $glossary_id,
				),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d', '%d' )
			);

			return false === $updated ? 0 : $entry_id;
		}

		$inserted = $this->wpdb->insert(
			$entries_table,
			array(
				'resource_id'        => $glossary_id,
				'msgctxt'            => '',
				'msgid'              => (string) $values['term'],
				'match_mode'         => (string) $values['match_mode'],
				'translator_comment' => (string) $values['note'],
				'status'             => 'active',
				'created_at_gmt'     => $now_gmt,
				'updated_at_gmt'     => $now_gmt,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $inserted ? 0 : (int) $this->wpdb->insert_id;
	}

	/**
	 * Deletes one term of a glossary with its translations.
	 *
	 * @param int $glossary_id Glossary resource ID.
	 * @param int $entry_id Term entry ID.
	 * @return bool True when the term existed in this glossary and was deleted.
	 */
	public function delete_glossary_term( $glossary_id, $entry_id ) {
		$entries_table = $this->escape_table_name( $this->schema_manager->get_resource_entries_table_name() );
		$targets_table = $this->escape_table_name( $this->schema_manager->get_resource_targets_table_name() );

		if ( '' === $entries_table || '' === $targets_table || ! $this->glossary_term_exists( $glossary_id, $entry_id ) ) {
			return false;
		}

		return (bool) $this->run_in_transaction(
			function () use ( $glossary_id, $entry_id, $entries_table, $targets_table ) {
				if ( false === $this->wpdb->delete(
					$targets_table,
					array(
						'resource_id'     => (int) $glossary_id,
						'source_entry_id' => (int) $entry_id,
					),
					array( '%d', '%d' )
				) ) {
					return false;
				}

				$deleted = $this->wpdb->delete(
					$entries_table,
					array(
						'id'          => (int) $entry_id,
						'resource_id' => (int) $glossary_id,
					),
					array( '%d', '%d' )
				);

				return false !== $deleted && $deleted > 0;
			}
		);
	}

	/**
	 * Tells whether a term entry belongs to a glossary.
	 *
	 * @param int $glossary_id Glossary resource ID.
	 * @param int $entry_id Term entry ID.
	 * @return bool
	 */
	public function glossary_term_exists( $glossary_id, $entry_id ) {
		$table = $this->escape_table_name( $this->schema_manager->get_resource_entries_table_name() );

		if ( '' === $table || (int) $entry_id <= 0 ) {
			return false;
		}

		return (int) $this->db_get_var(
			$this->wpdb->prepare(
				'SELECT id FROM %i WHERE id = %d AND resource_id = %d',
				$table,
				(int) $entry_id,
				(int) $glossary_id
			)
		) > 0;
	}
}
