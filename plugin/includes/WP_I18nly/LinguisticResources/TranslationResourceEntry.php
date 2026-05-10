<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation linguistic resource entry.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

defined( 'ABSPATH' ) || exit;

/**
 * Concrete linguistic resource entry for translations.
 */
class TranslationResourceEntry extends AbstractLinguisticResourceEntry {
	/**
	 * Returns entry kind.
	 *
	 * @return string
	 */
	public function get_entry_kind() {
		return 'translation';
	}

	/**
	 * Returns a cloned entry enriched with plural metadata.
	 *
	 * @param array<int, array<string, mixed>> $forms Ordered forms metadata.
	 * @param array<int, string>               $form_labels Labels by form index.
	 * @param array<int, string>               $form_markers Markers by form index.
	 * @param array<int, string>               $form_tooltips Tooltips by form index.
	 * @return self
	 */
	public function with_plural_metadata( array $forms, array $form_labels, array $form_markers, array $form_tooltips ) {
		$entry = $this->with_row_value( 'forms', $forms );
		$entry = $entry->with_row_value( 'form_labels', $form_labels );
		$entry = $entry->with_row_value( 'form_markers', $form_markers );
		$entry = $entry->with_row_value( 'form_tooltips', $form_tooltips );

		return $entry;
	}
}