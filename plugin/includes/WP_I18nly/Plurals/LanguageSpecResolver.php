<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Plural language spec resolver.
 *
 * @package I18nly
 */

namespace WP_I18nly\Plurals;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves one language provider from locale.
 */
final class LanguageSpecResolver {
	/**
	 * Returns spec provider output for one locale.
	 *
	 * @param string $locale Locale string.
	 * @return array<string, mixed>
	 */
	public function resolve_spec_for_locale( $locale ) {
		$provider_fqcn = $this->resolve_provider_fqcn_for_locale( $locale );

		if ( is_subclass_of( $provider_fqcn, LanguageSpecProvider::class ) ) {
			return $provider_fqcn::get_spec();
		}

		return LanguageSpecDefault::get_spec();
	}

	/**
	 * Resolves generated provider FQCN for one locale.
	 *
	 * @param string $locale Locale string.
	 * @return class-string
	 */
	private function resolve_provider_fqcn_for_locale( $locale ) {
		$normalized_locale = $this->normalize_locale( $locale );

		if ( '' === $normalized_locale ) {
			return LanguageSpecDefault::class;
		}

		$class_name = $this->locale_to_class_name( $normalized_locale );
		$fqcn       = 'WP_I18nly\\Plurals\\Languages\\' . $class_name;

		if ( class_exists( $fqcn ) ) {
			return $fqcn;
		}

		return LanguageSpecDefault::class;
	}

	/**
	 * Normalizes locale string.
	 *
	 * @param string $locale Locale string.
	 * @return string
	 */
	private function normalize_locale( $locale ) {
		$locale = str_replace( '-', '_', strtolower( trim( (string) $locale ) ) );
		$locale = preg_replace( '/[^a-z0-9_]/', '', $locale );

		if ( ! is_string( $locale ) || '' === $locale ) {
			return '';
		}

		if ( ! preg_match( '/^[a-z]{2,3}(?:_[a-z0-9]{2,})*$/', $locale ) ) {
			return '';
		}

		return $locale;
	}

	/**
	 * Converts normalized locale key to generated class name.
	 *
	 * @param string $normalized_locale Normalized locale key.
	 * @return string
	 */
	private function locale_to_class_name( $normalized_locale ) {
		$parts = explode( '_', $normalized_locale );
		$camel = '';

		foreach ( $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}

			$camel .= strtoupper( substr( $part, 0, 1 ) ) . strtolower( substr( $part, 1 ) );
		}

		return 'Lang' . $camel;
	}
}
