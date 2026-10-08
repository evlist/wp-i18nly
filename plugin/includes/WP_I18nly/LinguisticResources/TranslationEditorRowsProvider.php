<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation editor rows provider.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

use WP_I18nly\Glossary\TranslationGlossaries;
use WP_I18nly\Plurals\PluralFormsRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the editor rows of one translation resource.
 */
class TranslationEditorRowsProvider {
	/**
	 * Maximum number of rows loaded for the editor.
	 */
	public const ROW_LIMIT = 500;

	/**
	 * Translation resource repository.
	 *
	 * @var TranslationResourceRepository|null
	 */
	private $repository;

	/**
	 * Source locale.
	 *
	 * @var string
	 */
	private $source_locale;

	/**
	 * Glossaries linked to translations.
	 *
	 * @var TranslationGlossaries|null
	 */
	private $glossaries;

	/**
	 * Constructor.
	 *
	 * @param TranslationResourceRepository|null $repository Optional repository.
	 * @param string                             $source_locale Source locale.
	 * @param TranslationGlossaries|null         $glossaries Optional glossaries of translations.
	 */
	public function __construct( TranslationResourceRepository $repository = null, $source_locale = 'en_US', TranslationGlossaries $glossaries = null ) {
		$this->repository    = $repository;
		$this->source_locale = (string) $source_locale;
		$this->glossaries    = $glossaries;
	}

	/**
	 * Returns editor rows for one translation, creating missing target rows first.
	 *
	 * @param int    $translation_id Translation post ID.
	 * @param string $source_slug Source slug.
	 * @param string $target_locale Target locale.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_rows( $translation_id, $source_slug, $target_locale ) {
		$repository = $this->repository instanceof TranslationResourceRepository
			? $this->repository
			: new TranslationResourceRepository();
		$form_count = PluralFormsRegistry::get_plural_forms_count_for_locale( $target_locale );

		$repository->ensure_translation_targets( (int) $translation_id, (string) $source_slug, (string) $target_locale, gmdate( 'Y-m-d H:i:s' ), $form_count );
		$entries = $repository->list_translation_rows( (int) $translation_id, (string) $source_slug, self::ROW_LIMIT, $form_count );

		$forms         = PluralFormsRegistry::get_forms_for_locale( $target_locale );
		$form_labels   = PluralFormsRegistry::get_form_labels_for_locale( $target_locale );
		$form_markers  = PluralFormsRegistry::get_form_markers_for_locale( $target_locale );
		$form_tooltips = PluralFormsRegistry::get_form_tooltips_for_locale( $target_locale );

		$model = TranslationEditorModel::from_repository_rows(
			(int) $repository->get_translation_resource_id( (int) $translation_id ),
			(int) $translation_id,
			(string) $source_slug,
			$this->source_locale,
			(string) $target_locale,
			is_array( $entries ) ? $entries : array(),
			is_array( $forms ) ? $forms : array(),
			is_array( $form_labels ) ? $form_labels : array(),
			is_array( $form_markers ) ? $form_markers : array(),
			is_array( $form_tooltips ) ? $form_tooltips : array()
		);

		return $this->add_glossary_matches( $model->to_rows(), (int) $translation_id, (string) $target_locale );
	}

	/**
	 * Adds to every row the terms of the linked glossaries found in its source text, with the result of the check of its translations.
	 *
	 * @param array<int, array<string, mixed>> $rows Editor rows.
	 * @param int                              $translation_id Translation post ID.
	 * @param string                           $target_locale Target locale.
	 * @return array<int, array<string, mixed>>
	 */
	private function add_glossary_matches( array $rows, $translation_id, $target_locale ) {
		$glossaries = $this->glossaries instanceof TranslationGlossaries ? $this->glossaries : new TranslationGlossaries();
		$matcher    = $glossaries->build_matcher( $translation_id, $target_locale );

		if ( 0 === $matcher->count() ) {
			return $rows;
		}

		foreach ( $rows as $index => $row ) {
			$texts = array();

			foreach ( isset( $row['translations'] ) && is_array( $row['translations'] ) ? $row['translations'] : array() as $form ) {
				$texts[] = isset( $form['translation'] ) ? (string) $form['translation'] : '';
			}

			$matches = $matcher->match_entry(
				isset( $row['msgid'] ) ? (string) $row['msgid'] : '',
				isset( $row['msgid_plural'] ) ? (string) $row['msgid_plural'] : '',
				$texts
			);

			if ( ! empty( $matches ) ) {
				$rows[ $index ]['glossary_matches'] = $matches;
			}
		}

		return $rows;
	}
}
