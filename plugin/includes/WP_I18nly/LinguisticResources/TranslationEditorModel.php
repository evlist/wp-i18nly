<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation editor model.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

defined( 'ABSPATH' ) || exit;

/**
 * UI-facing editor model for translation resources.
 */
class TranslationEditorModel extends AbstractLinguisticResourceEditorModel {
	/**
	 * Builds a model from repository rows.
	 *
	 * @param int                              $translation_id Translation ID.
	 * @param string                           $source_slug Source slug.
	 * @param string                           $source_locale Source locale.
	 * @param string                           $target_locale Target locale.
	 * @param array<int, array<string, mixed>> $rows Repository rows.
	 * @param array<int, array<string, mixed>> $forms Ordered forms metadata.
	 * @param array<int, string>               $form_labels Labels by form index.
	 * @param array<int, string>               $form_markers Markers by form index.
	 * @param array<int, string>               $form_tooltips Tooltips by form index.
	 * @return self
	 */
	public static function from_repository_rows( $translation_id, $source_slug, $source_locale, $target_locale, array $rows, array $forms, array $form_labels, array $form_markers, array $form_tooltips ) {
		$entries = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$entry = new TranslationResourceEntry( $row );
			$entries[] = $entry->with_plural_metadata( $forms, $form_labels, $form_markers, $form_tooltips );
		}

		$resource = new TranslationResource( $translation_id, $source_slug, $source_locale, $target_locale, $entries );

		return new self( $resource );
	}

	/**
	 * Returns the translation resource.
	 *
	 * @return TranslationResource
	 */
	public function get_translation_resource() {
		/** @var TranslationResource $resource */
		$resource = $this->get_resource();

		return $resource;
	}

	/**
	 * Returns UI-ready rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function to_rows() {
		$rows = array();

		foreach ( $this->get_translation_resource()->get_entries() as $entry ) {
			if ( ! $entry instanceof TranslationResourceEntry ) {
				continue;
			}

			$rows[] = $entry->to_array();
		}

		return $rows;
	}
}