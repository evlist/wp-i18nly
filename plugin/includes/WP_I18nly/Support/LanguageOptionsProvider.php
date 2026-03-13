<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Language options provider.
 *
 * @package I18nly
 */

namespace WP_I18nly\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Provides language/locale options for translation selectors.
 */
class LanguageOptionsProvider {
	/**
	 * Returns language options for target language selector.
	 *
	 * @param string $source_locale Source locale.
	 * @return array<int, array{value: string, label: string, disabled: bool}>
	 */
	public function get_target_language_options( $source_locale ) {
		$source_locale_key = $this->normalize_locale_key( $source_locale );
		$all_translations = array();
		$ordered_options  = array();
		$supported_labels = array();

		if ( ! function_exists( 'wp_get_available_translations' ) ) {
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		}

		if ( function_exists( 'wp_get_available_translations' ) ) {
			$all_translations = wp_get_available_translations();
		}

		foreach ( GeneratedTargetLocales::all() as $locale ) {
			$normalized_locale = $this->normalize_locale_key( $locale );

			if ( '' === $normalized_locale || $source_locale_key === $normalized_locale ) {
				continue;
			}

			$supported_labels[ (string) $locale ] = $this->get_locale_label( (string) $locale, $all_translations );
		}

		asort( $supported_labels );

		foreach ( $supported_labels as $locale => $label ) {
			$ordered_options[] = array(
				'value'    => (string) $locale,
				'label'    => (string) $label,
				'disabled' => false,
			);
		}

		return $ordered_options;
	}

	/**
	 * Returns a human-readable locale label.
	 *
	 * @param string                              $locale Locale code.
	 * @param array<string, array<string, mixed>> $all_translations Available translations.
	 * @return string
	 */
	public function get_locale_label( $locale, array $all_translations ) {
		if ( isset( $all_translations[ $locale ]['native_name'] ) && '' !== (string) $all_translations[ $locale ]['native_name'] ) {
			return (string) $all_translations[ $locale ]['native_name'];
		}

		return (string) $locale;
	}

	/**
	 * Normalizes locale key used by generated locale list.
	 *
	 * @param string $locale Locale string.
	 * @return string
	 */
	private function normalize_locale_key( $locale ) {
		$locale = str_replace( '-', '_', strtolower( trim( (string) $locale ) ) );
		$locale = preg_replace( '/[^a-z0-9_]/', '', $locale );

		if ( ! is_string( $locale ) ) {
			return '';
		}

		return $locale;
	}
}
