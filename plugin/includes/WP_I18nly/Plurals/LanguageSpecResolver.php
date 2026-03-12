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
	 * @var array<string, class-string<LanguageSpecProvider>>|null
	 */
	private static $provider_map = null;

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

		$provider_map = self::get_provider_map();

		if ( '' !== $language && isset( $provider_map[ $language ] ) ) {
			$provider_fqcn = $provider_map[ $language ];
		}

		if ( is_subclass_of( $provider_fqcn, LanguageSpecProvider::class ) ) {
			return $provider_fqcn::get_spec();
		}

		return LanguageSpecDefault::get_spec();
	}

	/**
	 * Returns cached provider map with auto-discovery of language classes.
	 *
	 * Dynamically discovers all Language/*.php files and builds a map
	 * from language codes to provider class names. This allows generation
	 * of new language classes without requiring code changes here.
	 *
	 * @return array<string, class-string<LanguageSpecProvider>>
	 */
	private static function get_provider_map(): array {
		if ( null !== self::$provider_map ) {
			return self::$provider_map;
		}

		self::$provider_map = array();

		$lang_dir = __DIR__ . '/Languages';
		if ( ! is_dir( $lang_dir ) ) {
			return self::$provider_map;
		}

		$files = glob( $lang_dir . '/*.php' );
		if ( false === $files ) {
			return self::$provider_map;
		}

		foreach ( $files as $file ) {
			$basename  = basename( $file, '.php' );
			$classname = strtolower( $basename );

			if ( ! preg_match( '/^lang([a-z]{2})$/', $classname, $matches ) ) {
				continue; // Skip non-language files.
			}

			$language_code = $matches[1];
			$fqcn          = 'WP_I18nly\\Plurals\\Languages\\' . $basename;
			if ( class_exists( $fqcn ) && is_subclass_of( $fqcn, LanguageSpecProvider::class ) ) {
				self::$provider_map[ $language_code ] = $fqcn;
			}
		}

		return self::$provider_map;
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
