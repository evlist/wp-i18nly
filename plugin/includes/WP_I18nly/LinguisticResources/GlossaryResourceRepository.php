<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Glossary linguistic resource repository.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

use WP_I18nly\Storage\SourceSchemaManager;
use WP_I18nly\Storage\SourceWpdbRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Glossary-specific repository backed by the resource-centric wpdb storage.
 *
 * It creates, reads and deletes glossaries and their terms. Writes are validated by the
 * GlossaryValidator and return a GlossaryOperationResult instead of throwing.
 *
 * The slug and the languages of a glossary are fixed when it is created, like the identity of a
 * translation.
 */
class GlossaryResourceRepository extends AbstractLinguisticResourceRepository {
	/**
	 * Validator.
	 *
	 * @var GlossaryValidator
	 */
	private $validator;

	/**
	 * Constructor.
	 *
	 * @param SourceWpdbRepository|null $storage_repository Optional storage repository.
	 * @param SourceSchemaManager|null  $schema_manager Optional schema manager.
	 * @param GlossaryValidator|null    $validator Optional validator.
	 */
	public function __construct( SourceWpdbRepository $storage_repository = null, SourceSchemaManager $schema_manager = null, GlossaryValidator $validator = null ) {
		parent::__construct( $storage_repository, $schema_manager );

		$this->validator = $validator instanceof GlossaryValidator ? $validator : new GlossaryValidator();
	}

	/**
	 * Returns the resource kind.
	 *
	 * @return string
	 */
	public function get_resource_kind() {
		return 'glossary';
	}

	/**
	 * Creates a glossary.
	 *
	 * @param string      $slug Glossary slug.
	 * @param string      $source_locale Source locale.
	 * @param string      $target_locale Target locale.
	 * @param string|null $now_gmt Datetime in GMT, now by default.
	 * @return GlossaryOperationResult Result holding the glossary ID.
	 */
	public function create_glossary( $slug, $source_locale, $target_locale, $now_gmt = null ) {
		$validation = $this->validator->validate_identity( $slug, $source_locale, $target_locale );

		if ( ! $validation->is_valid() ) {
			return new GlossaryOperationResult( 0, $validation->get_errors() );
		}

		$values = $validation->get_values();

		if ( $this->find_glossary_id( $values['slug'], $values['target_locale'] ) > 0 ) {
			return new GlossaryOperationResult( 0, array( 'duplicate_glossary' => __( 'A glossary with this identifier already exists for this target language.', 'i18nly' ) ) );
		}

		$glossary_id = $this->get_storage_repository()->insert_glossary_resource( $values['slug'], $values['source_locale'], $values['target_locale'], $this->resolve_datetime( $now_gmt ) );

		if ( $glossary_id <= 0 ) {
			return new GlossaryOperationResult( 0, $this->storage_error() );
		}

		return new GlossaryOperationResult( $glossary_id );
	}

	/**
	 * Finds the ID of a glossary.
	 *
	 * @param string $slug Glossary slug.
	 * @param string $target_locale Target locale.
	 * @return int Glossary ID, or 0 when none exists.
	 */
	public function find_glossary_id( $slug, $target_locale ) {
		return $this->get_storage_repository()->find_glossary_resource_id( strtolower( trim( (string) $slug ) ), trim( (string) $target_locale ) );
	}

	/**
	 * Lists the glossaries, ordered by slug then target language.
	 *
	 * @return array<int, array{id: int, slug: string, source_locale: string, target_locale: string}>
	 */
	public function list_glossaries() {
		$glossaries = array();

		foreach ( $this->get_storage_repository()->list_glossary_resource_rows() as $row ) {
			$glossaries[] = array(
				'id'            => (int) $row['id'],
				'slug'          => (string) $row['source_slug'],
				'source_locale' => (string) $row['source_locale'],
				'target_locale' => (string) $row['target_locale'],
			);
		}

		return $glossaries;
	}

	/**
	 * Loads a glossary with its terms, ordered by term, and their translations.
	 *
	 * @param int $glossary_id Glossary ID.
	 * @return GlossaryResource|null Null when the glossary does not exist.
	 */
	public function get_glossary( $glossary_id ) {
		$storage = $this->get_storage_repository();
		$row     = $storage->get_glossary_resource_row( (int) $glossary_id );

		if ( null === $row ) {
			return null;
		}

		$translations = array();

		foreach ( $storage->list_glossary_target_rows( (int) $glossary_id ) as $target_row ) {
			$translations[ (int) $target_row['source_entry_id'] ][] = array(
				'form_index'  => (int) $target_row['form_index'],
				'translation' => (string) $target_row['translation'],
				'status'      => (string) $target_row['status'],
				'used_ai'     => (int) $target_row['used_ai'],
				'used_manual' => (int) $target_row['used_manual'],
			);
		}

		$entries = array();

		foreach ( $storage->list_glossary_term_rows( (int) $glossary_id ) as $term_row ) {
			$entry_id  = (int) $term_row['source_entry_id'];
			$entries[] = new GlossaryResourceEntry(
				array(
					'source_entry_id' => $entry_id,
					'term'            => (string) $term_row['term'],
					'match_mode'      => (string) $term_row['match_mode'],
					'note'            => (string) $term_row['note'],
					'status'          => (string) $term_row['status'],
					'translations'    => isset( $translations[ $entry_id ] ) ? $translations[ $entry_id ] : array(),
				)
			);
		}

		return new GlossaryResource( (int) $row['id'], (string) $row['source_slug'], (string) $row['source_locale'], (string) $row['target_locale'], $entries );
	}

	/**
	 * Creates or updates one term of a glossary.
	 *
	 * @param int                  $glossary_id Glossary ID.
	 * @param array<string, mixed> $input Term input: term, match_mode, preferred, alternates and note.
	 * @param int                  $entry_id Term ID to update, or 0 to create a term.
	 * @param string|null          $now_gmt Datetime in GMT, now by default.
	 * @return GlossaryOperationResult Result holding the term ID.
	 */
	public function save_term( $glossary_id, array $input, $entry_id = 0, $now_gmt = null ) {
		$storage = $this->get_storage_repository();

		if ( null === $storage->get_glossary_resource_row( (int) $glossary_id ) ) {
			return new GlossaryOperationResult( 0, array( 'unknown_glossary' => __( 'The glossary does not exist.', 'i18nly' ) ) );
		}

		if ( (int) $entry_id > 0 && ! $storage->glossary_term_exists( (int) $glossary_id, (int) $entry_id ) ) {
			return new GlossaryOperationResult( 0, array( 'unknown_term' => __( 'The term does not exist in this glossary.', 'i18nly' ) ) );
		}

		$other_terms = array();

		foreach ( $storage->list_glossary_term_rows( (int) $glossary_id ) as $term_row ) {
			if ( (int) $term_row['source_entry_id'] !== (int) $entry_id ) {
				$other_terms[] = (string) $term_row['term'];
			}
		}

		$validation = $this->validator->validate_term( $input, $other_terms );

		if ( ! $validation->is_valid() ) {
			return new GlossaryOperationResult( 0, $validation->get_errors() );
		}

		$values            = $validation->get_values();
		$values['targets'] = array_merge( array( $values['preferred'] ), $values['alternates'] );

		$saved_id = $storage->save_glossary_term( (int) $glossary_id, (int) $entry_id, $values, $this->resolve_datetime( $now_gmt ) );

		if ( $saved_id <= 0 ) {
			return new GlossaryOperationResult( 0, $this->storage_error() );
		}

		return new GlossaryOperationResult( $saved_id );
	}

	/**
	 * Deletes one term of a glossary with its translations.
	 *
	 * @param int $glossary_id Glossary ID.
	 * @param int $entry_id Term ID.
	 * @return bool True when the term existed in the glossary and was deleted.
	 */
	public function delete_term( $glossary_id, $entry_id ) {
		return $this->get_storage_repository()->delete_glossary_term( (int) $glossary_id, (int) $entry_id );
	}

	/**
	 * Deletes a glossary with its terms and translations.
	 *
	 * @param int $glossary_id Glossary ID.
	 * @return bool True when the glossary existed and was deleted.
	 */
	public function delete_glossary( $glossary_id ) {
		return $this->get_storage_repository()->delete_glossary_resource( (int) $glossary_id );
	}

	/**
	 * Returns the current datetime in GMT unless one is given.
	 *
	 * @param string|null $now_gmt Datetime in GMT.
	 * @return string
	 */
	private function resolve_datetime( $now_gmt ) {
		return null === $now_gmt ? gmdate( 'Y-m-d H:i:s' ) : (string) $now_gmt;
	}

	/**
	 * Returns the error reported when the database refuses a write.
	 *
	 * @return array<string, string>
	 */
	private function storage_error() {
		return array( 'storage_error' => __( 'The glossary could not be saved.', 'i18nly' ) );
	}
}
