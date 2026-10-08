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

use WP_I18nly\Plurals\PluralFormsRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the editor rows of one translation resource.
 */
class TranslationEditorRowsProvider {
	/**
	 * Maximum number of rows loaded for the editor.
	 */
	private const ROW_LIMIT = 500;

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
	 * Constructor.
	 *
	 * @param TranslationResourceRepository|null $repository Optional repository.
	 * @param string                             $source_locale Source locale.
	 */
	public function __construct( TranslationResourceRepository $repository = null, $source_locale = 'en_US' ) {
		$this->repository    = $repository;
		$this->source_locale = (string) $source_locale;
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

		return $model->to_rows();
	}
}
