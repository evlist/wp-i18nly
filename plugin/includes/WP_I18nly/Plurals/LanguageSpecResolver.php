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

use WP_I18nly\Plurals\Languages\En;
use WP_I18nly\Plurals\Languages\Fr;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves one language provider from locale.
 */
final class LanguageSpecResolver {
	/**
	 * @var array<string, class-string<LanguageSpecProvider>>
	 */
	private const PROVIDER_MAP = array(
		'en' => En::class,
		'fr' => Fr::class,
	);

	/**
	 * Returns spec provider output for one locale.
	 *
	 * Language normalization intentionally uses only first two letters.
	 *
	 * @param string $locale Locale string.
	 * @return array<string, mixed>
	 */
	public function resolve_spec_for_locale( $locale ) {
		$language      = $this->normalize_language( $locale );
		$provider_fqcn = LanguageSpecDefault::class;

		if ( '' !== $language && isset( self::PROVIDER_MAP[ $language ] ) ) {
			$provider_fqcn = self::PROVIDER_MAP[ $language ];
		}

		if ( is_subclass_of( $provider_fqcn, LanguageSpecProvider::class ) ) {
			return $provider_fqcn::get_spec();
		}

		return LanguageSpecDefault::get_spec();
	}

	/**
	 * Extracts language code from locale.
	 *
	 * Only first two letters are used (ISO 639-1 approximation).
	 *
	 * @param string $locale Locale string.
	 * @return string
	 */
	private function normalize_language( $locale ) {
		$locale = strtolower( (string) $locale );

		if ( '' === $locale ) {
			return '';
		}

		if ( ! preg_match( '/^[a-z]{2}/', $locale, $matches ) ) {
			return '';
		}

		return isset( $matches[0] ) ? (string) $matches[0] : '';
	}
}
