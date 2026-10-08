<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Source wpdb repository target-resource helpers.
 *
 * @package I18nly
 */

namespace WP_I18nly\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Shares target-resource persistence and read helpers for the source wpdb repository.
 */
trait SourceWpdbRepositoryTargetResourceTrait {
	/**
	 * Ensures the translation resource row exists for one translation post.
	 *
	 * @param int    $translation_id Translation post ID used as anchor.
	 * @param string $source_slug Source slug.
	 * @param string $target_locale Target locale.
	 * @param string $now_gmt Current GMT datetime.
	 * @return int Translation resource ID, or 0 when it cannot be stored.
	 */
	public function ensure_translation_resource( $translation_id, $source_slug, $target_locale, $now_gmt ) {
		$existing_id = $this->find_translation_resource_id( $translation_id );

		if ( $existing_id > 0 ) {
			return $existing_id;
		}

		$table = $this->escape_table_name( $this->schema_manager->get_resources_table_name() );

		if ( '' === $table || (int) $translation_id <= 0 ) {
			return 0;
		}

		$result = $this->wpdb->insert(
			$table,
			array(
				'resource_kind'  => self::TRANSLATION_RESOURCE_KIND,
				'source_slug'    => (string) $source_slug,
				'source_locale'  => 'en_US',
				'target_locale'  => (string) $target_locale,
				'anchor_post_id' => (int) $translation_id,
				'created_at_gmt' => (string) $now_gmt,
				'updated_at_gmt' => (string) $now_gmt,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return 0;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Finds the translation resource ID anchored on one translation post.
	 *
	 * @param int $translation_id Translation post ID.
	 * @return int Translation resource ID, or 0 when none exists.
	 */
	public function find_translation_resource_id( $translation_id ) {
		$table = $this->escape_table_name( $this->schema_manager->get_resources_table_name() );

		if ( '' === $table || (int) $translation_id <= 0 ) {
			return 0;
		}

		return (int) $this->db_get_var(
			$this->wpdb->prepare(
				'SELECT id FROM %i WHERE resource_kind = %s AND anchor_post_id = %d',
				$table,
				self::TRANSLATION_RESOURCE_KIND,
				(int) $translation_id
			)
		);
	}

	/**
	 * Deletes the translation resource row anchored on one post, with its target rows.
	 *
	 * @param int $translation_id Translation post ID.
	 * @return bool True when a resource row was deleted.
	 */
	public function delete_translation_resource( $translation_id ) {
		$resource_id   = $this->find_translation_resource_id( $translation_id );
		$resources_tbl = $this->escape_table_name( $this->schema_manager->get_resources_table_name() );
		$targets_tbl   = $this->escape_table_name( $this->schema_manager->get_resource_targets_table_name() );

		if ( $resource_id <= 0 || '' === $resources_tbl || '' === $targets_tbl ) {
			return false;
		}

		$this->wpdb->delete( $targets_tbl, array( 'resource_id' => $resource_id ), array( '%d' ) );

		$deleted = $this->wpdb->delete( $resources_tbl, array( 'id' => $resource_id ), array( '%d' ) );

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Ensures target rows exist for all source entries of one translation.
	 *
	 * @param int    $translation_id Translation post ID.
	 * @param string $plugin_slug Plugin slug.
	 * @param string $target_locale Target locale.
	 * @param string $now_gmt Current GMT datetime.
	 * @param int    $plural_forms_count Number of target plural forms.
	 * @return int Number of inserted rows.
	 */
	public function ensure_translation_target_rows( $translation_id, $plugin_slug, $target_locale, $now_gmt, $plural_forms_count = self::DEFAULT_PLURAL_FORMS_COUNT ) {
		$entries_table   = $this->escape_table_name( $this->schema_manager->get_resource_entries_table_name() );
		$resources_table = $this->escape_table_name( $this->schema_manager->get_resources_table_name() );
		$targets_table   = $this->escape_table_name( $this->schema_manager->get_resource_targets_table_name() );

		if ( '' === $entries_table || '' === $resources_table || '' === $targets_table ) {
			return 0;
		}

		$target_resource_id = $this->ensure_translation_resource( $translation_id, $plugin_slug, $target_locale, $now_gmt );

		if ( $target_resource_id <= 0 ) {
			return 0;
		}

		$max_forms = max( 1, (int) $plural_forms_count );

		$source_rows = $this->db_get_results(
			$this->wpdb->prepare(
				'SELECT e.id AS source_entry_id, e.msgid_plural FROM %i e INNER JOIN %i c ON c.id = e.resource_id WHERE c.resource_kind = %s AND c.source_slug = %s ORDER BY e.id ASC',
				$entries_table,
				$resources_table,
				self::SOURCE_CATALOG_RESOURCE_KIND,
				(string) $plugin_slug
			),
			ARRAY_A
		);

		$inserted = 0;

		foreach ( $source_rows as $source_row ) {
			$source_entry_id = isset( $source_row['source_entry_id'] ) ? (int) $source_row['source_entry_id'] : 0;

			if ( $source_entry_id <= 0 ) {
				continue;
			}

			$has_plural     = isset( $source_row['msgid_plural'] ) && '' !== trim( (string) $source_row['msgid_plural'] );
			$required_forms = $has_plural ? $max_forms : 1;

			for ( $form_index = 0; $form_index < $required_forms; $form_index++ ) {
				$existing_target_id = (int) $this->db_get_var(
					$this->wpdb->prepare(
						'SELECT id FROM %i WHERE resource_id = %d AND source_entry_id = %d AND form_index = %d',
						$targets_table,
						$target_resource_id,
						$source_entry_id,
						$form_index
					)
				);

				if ( $existing_target_id > 0 ) {
					continue;
				}

				$result = $this->wpdb->insert(
					$targets_table,
					array(
						'resource_id'     => $target_resource_id,
						'source_entry_id' => $source_entry_id,
						'form_index'      => $form_index,
						'target_text'     => '',
						'status'          => 'draft',
						'comment'         => '',
						'created_at_gmt'  => (string) $now_gmt,
						'updated_at_gmt'  => (string) $now_gmt,
					),
					array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
				);

				if ( false !== $result ) {
					++$inserted;
				}
			}
		}

		return $inserted;
	}

	/**
	 * Lists source entries joined with translated values for one translation.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $plugin_slug Plugin slug.
	 * @param int    $limit Maximum row count.
	 * @param int    $plural_forms_count Number of target plural forms.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_translation_rows( $translation_id, $plugin_slug, $limit = 500, $plural_forms_count = self::DEFAULT_PLURAL_FORMS_COUNT ) {
		$entries_table      = $this->escape_table_name( $this->schema_manager->get_resource_entries_table_name() );
		$resources_table    = $this->escape_table_name( $this->schema_manager->get_resources_table_name() );
		$targets_table      = $this->escape_table_name( $this->schema_manager->get_resource_targets_table_name() );
		$target_resource_id = $this->find_translation_resource_id( $translation_id );

		if ( '' === $entries_table || '' === $resources_table || '' === $targets_table ) {
			return array();
		}

		$max_rows = max( 1, (int) $limit );

		$query = $this->wpdb->prepare(
			'SELECT e.id AS source_entry_id, e.msgctxt, e.msgid, e.msgid_plural, e.translator_comment, e.references_json, e.status AS source_status, e.last_seen_at_gmt, e.updated_at_gmt, t.form_index, t.target_text AS translation, t.status AS translated_status, t.used_ai, t.used_manual, t.comment, t.updated_at_gmt AS translation_updated_at_gmt FROM %i e INNER JOIN %i c ON c.id = e.resource_id LEFT JOIN %i t ON t.source_entry_id = e.id AND t.resource_id = %d WHERE c.resource_kind = %s AND c.source_slug = %s ORDER BY e.msgid ASC, e.id ASC, t.form_index ASC LIMIT %d',
			$entries_table,
			$resources_table,
			$targets_table,
			$target_resource_id,
			self::SOURCE_CATALOG_RESOURCE_KIND,
			(string) $plugin_slug,
			$max_rows
		);

		$rows             = $this->db_get_results( $query, ARRAY_A );
		$normalized_rows  = array();
		$max_plural_forms = max( 1, (int) $plural_forms_count );

		foreach ( $rows as $row ) {
			$source_entry_id = isset( $row['source_entry_id'] ) ? absint( $row['source_entry_id'] ) : 0;

			if ( $source_entry_id <= 0 ) {
				continue;
			}

			if ( ! isset( $normalized_rows[ $source_entry_id ] ) ) {
				$normalized_rows[ $source_entry_id ] = array(
					'source_entry_id'    => $source_entry_id,
					'msgctxt'            => isset( $row['msgctxt'] ) ? (string) $row['msgctxt'] : '',
					'msgid'              => isset( $row['msgid'] ) ? (string) $row['msgid'] : '',
					'msgid_plural'       => isset( $row['msgid_plural'] ) ? (string) $row['msgid_plural'] : '',
					'translator_comment' => isset( $row['translator_comment'] ) ? (string) $row['translator_comment'] : '',
					'references'         => $this->decode_references( isset( $row['references_json'] ) ? $row['references_json'] : '' ),
					'source_status'      => isset( $row['source_status'] ) ? (string) $row['source_status'] : '',
					'last_seen_at_gmt'   => isset( $row['last_seen_at_gmt'] ) ? (string) $row['last_seen_at_gmt'] : '',
					'updated_at_gmt'     => isset( $row['updated_at_gmt'] ) ? (string) $row['updated_at_gmt'] : '',
					'translations'       => array(),
				);
			}

			if ( isset( $row['form_index'] ) && '' !== (string) $row['form_index'] ) {
				$form_index = max( 0, (int) $row['form_index'] );

				$normalized_rows[ $source_entry_id ]['translations'][ $form_index ] = array(
					'source_entry_id' => $source_entry_id,
					'form_index'      => $form_index,
					'translation'     => isset( $row['translation'] ) ? (string) $row['translation'] : '',
					'status'          => isset( $row['translated_status'] ) ? (string) $row['translated_status'] : 'draft',
					'used_ai'         => isset( $row['used_ai'] ) ? (int) $row['used_ai'] : 0,
					'used_manual'     => isset( $row['used_manual'] ) ? (int) $row['used_manual'] : 1,
				);
			}
		}

		foreach ( $normalized_rows as &$normalized_row ) {
			$has_plural     = '' !== trim( (string) $normalized_row['msgid_plural'] );
			$required_forms = $has_plural ? $max_plural_forms : 1;

			for ( $form_index = 0; $form_index < $required_forms; $form_index++ ) {
				if ( isset( $normalized_row['translations'][ $form_index ] ) ) {
					continue;
				}

				$normalized_row['translations'][ $form_index ] = array(
					'source_entry_id' => (int) $normalized_row['source_entry_id'],
					'form_index'      => $form_index,
					'translation'     => '',
					'status'          => 'draft',
					'used_ai'         => 0,
					'used_manual'     => 1,
				);
			}

			ksort( $normalized_row['translations'] );
			$normalized_row['translations'] = array_values( $normalized_row['translations'] );
		}
		unset( $normalized_row );

		return array_values( $normalized_rows );
	}

	/**
	 * Upserts one translated entry value row.
	 *
	 * @param int         $translation_id Translation ID.
	 * @param int         $source_entry_id Source entry ID.
	 * @param int         $form_index Target plural form index.
	 * @param string      $translation Translated value.
	 * @param string      $now_gmt Current GMT datetime.
	 * @param string|null $status Translated entry status. Null preserves existing status.
	 * @param int|null    $used_ai AI provenance flag. Null preserves existing value.
	 * @param int|null    $used_manual Manual provenance flag. Null preserves existing value.
	 * @return bool
	 */
	public function upsert_translation_target( $translation_id, $source_entry_id, $form_index, $translation, $now_gmt, $status = null, $used_ai = null, $used_manual = null ) {
		$targets_table      = $this->escape_table_name( $this->schema_manager->get_resource_targets_table_name() );
		$target_resource_id = $this->find_translation_resource_id( $translation_id );

		if ( '' === $targets_table || $target_resource_id <= 0 ) {
			return false;
		}
		$target_id = (int) $this->db_get_var(
			$this->wpdb->prepare(
				'SELECT id FROM %i WHERE resource_id = %d AND source_entry_id = %d AND form_index = %d',
				$targets_table,
				$target_resource_id,
				(int) $source_entry_id,
				(int) $form_index
			)
		);

		if ( $target_id > 0 ) {
			$update_data   = array(
				'target_text'    => (string) $translation,
				'updated_at_gmt' => (string) $now_gmt,
			);
			$update_format = array( '%s', '%s' );

			if ( null !== $status ) {
				$update_data['status'] = (string) $status;
				$update_format[]       = '%s';
			}

			if ( null !== $used_ai ) {
				$update_data['used_ai'] = max( 0, min( 1, (int) $used_ai ) );
				$update_format[]        = '%d';
			}

			if ( null !== $used_manual ) {
				$update_data['used_manual'] = max( 0, min( 1, (int) $used_manual ) );
				$update_format[]            = '%d';
			}

			$result = $this->wpdb->update( $targets_table, $update_data, array( 'id' => (int) $target_id ), $update_format, array( '%d' ) );

			return false !== $result;
		}

		$result = $this->wpdb->insert(
			$targets_table,
			array(
				'resource_id'     => $target_resource_id,
				'source_entry_id' => (int) $source_entry_id,
				'form_index'      => (int) $form_index,
				'target_text'     => (string) $translation,
				'status'          => null === $status ? 'draft' : (string) $status,
				'used_ai'         => null === $used_ai ? 0 : max( 0, min( 1, (int) $used_ai ) ),
				'used_manual'     => null === $used_manual ? 1 : max( 0, min( 1, (int) $used_manual ) ),
				'comment'         => '',
				'created_at_gmt'  => (string) $now_gmt,
				'updated_at_gmt'  => (string) $now_gmt,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Decodes the source references stored as JSON: file path => line numbers.
	 *
	 * @param mixed $references_json Stored JSON.
	 * @return array<string, int[]>
	 */
	private function decode_references( $references_json ) {
		$decoded    = is_string( $references_json ) && '' !== $references_json ? json_decode( $references_json, true ) : null;
		$references = array();

		if ( ! is_array( $decoded ) ) {
			return $references;
		}

		foreach ( $decoded as $file => $lines ) {
			if ( is_string( $file ) && '' !== $file ) {
				$references[ $file ] = array_values( array_map( 'intval', (array) $lines ) );
			}
		}

		return $references;
	}
}
