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
		$installed_locales = array();
		$all_translations  = array();
		$preferred_options = array();
		$remaining_options = array();
		$ordered_options   = array();

		if ( ! function_exists( 'wp_get_available_translations' ) ) {
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		}

		if ( function_exists( 'get_available_languages' ) ) {
			$installed_locales = get_available_languages();
		}

		if ( function_exists( 'wp_get_available_translations' ) ) {
			$all_translations = wp_get_available_translations();
		}

		foreach ( $installed_locales as $locale ) {
			if ( $source_locale === $locale ) {
				continue;
			}

			$preferred_options[ $locale ] = $this->get_locale_label( $locale, $all_translations );
		}

		asort( $preferred_options );

		foreach ( $all_translations as $locale => $translation ) {
			if ( $source_locale === $locale || isset( $preferred_options[ $locale ] ) ) {
				continue;
			}

			unset( $translation );
			$remaining_options[ $locale ] = $this->get_locale_label( $locale, $all_translations );
		}

		asort( $remaining_options );

		foreach ( $preferred_options as $locale => $label ) {
			$ordered_options[] = array(
				'value'    => (string) $locale,
				'label'    => (string) $label,
				'disabled' => false,
			);
		}

		if ( ! empty( $preferred_options ) && ! empty( $remaining_options ) ) {
			$ordered_options[] = array(
				'value'    => '',
				'label'    => '──────────',
				'disabled' => true,
			);
		}

		foreach ( $remaining_options as $locale => $label ) {
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
}
