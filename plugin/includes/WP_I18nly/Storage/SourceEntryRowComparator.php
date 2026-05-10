<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Source entry row comparator.
 *
 * @package I18nly
 */

namespace WP_I18nly\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Compares persisted source-entry rows with candidate payloads.
 */
class SourceEntryRowComparator {
	/**
	 * Returns whether existing DB row and candidate entry are equivalent.
	 *
	 * @param array<string, mixed>|null $existing Existing DB row.
	 * @param array<string, mixed>      $entry Candidate entry payload.
	 * @param array<int, string>        $identity_columns Identity columns.
	 * @param array<int, string>        $content_columns Content columns.
	 * @return bool
	 */
	public function rows_are_equal( $existing, array $entry, array $identity_columns, array $content_columns ) {
		if ( ! is_array( $existing ) ) {
			return false;
		}

		foreach ( array_merge( $identity_columns, $content_columns ) as $column ) {
			$existing_value = array_key_exists( $column, $existing ) ? $existing[ $column ] : null;
			$entry_value    = array_key_exists( $column, $entry ) ? $entry[ $column ] : null;

			if ( (string) $existing_value !== (string) $entry_value ) {
				return false;
			}
		}

		return true;
	}
}
