<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Builds the JSON translation files of JavaScript scripts.
 *
 * @package I18nly
 */

namespace WP_I18nly\Export;

defined( 'ABSPATH' ) || exit;

/**
 * Produces the Jed JSON files WordPress loads for scripts (wp_set_script_translations()).
 *
 * WordPress looks for {text-domain}-{locale}-{md5( path )}.json, where the path is the script path
 * relative to the plugin folder, with ".min.js" replaced by ".js". Each file holds only the strings
 * referenced by that script, so one file is built for every JavaScript file referenced by the entries.
 */
class ScriptTranslationsBuilder {
	/**
	 * Catalog builder.
	 *
	 * @var TranslationCatalogBuilder
	 */
	private $catalog_builder;

	/**
	 * Constructor.
	 *
	 * @param TranslationCatalogBuilder|null $catalog_builder Optional catalog builder.
	 */
	public function __construct( TranslationCatalogBuilder $catalog_builder = null ) {
		$this->catalog_builder = $catalog_builder instanceof TranslationCatalogBuilder ? $catalog_builder : new TranslationCatalogBuilder();
	}

	/**
	 * Returns the path under which WordPress identifies a script: unminified, relative to the plugin folder.
	 *
	 * @param string $reference_file Reference file as stored in the source references.
	 * @return string Empty string when the reference is not a JavaScript file.
	 */
	public static function normalize_script_path( $reference_file ) {
		$path = ltrim( str_replace( '\\', '/', (string) $reference_file ), '/' );

		if ( 1 !== preg_match( '/\.js$/i', $path ) ) {
			return '';
		}

		return (string) preg_replace( '/\.min\.js$/i', '.js', $path );
	}

	/**
	 * Returns the file name WordPress expects for a script.
	 *
	 * @param string $text_domain Text domain.
	 * @param string $locale Locale.
	 * @param string $script_path Normalized script path.
	 * @return string
	 */
	public static function get_file_name( $text_domain, $locale, $script_path ) {
		return sanitize_file_name( $text_domain . '-' . $locale . '-' . md5( $script_path ) . '.json' );
	}

	/**
	 * Builds the JSON files.
	 *
	 * Entries without translation are left out, and so are plural entries having an empty form. Scripts
	 * left without any string get no file.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows as returned by list_translation_rows(), with their references.
	 * @param string                           $locale Target locale.
	 * @param string                           $text_domain Text domain.
	 * @param int                              $plural_count Number of plural forms.
	 * @param string                           $plural_expression Gettext plural expression.
	 * @param bool                             $include_unvalidated Whether to include the translations that are not validated.
	 * @param string                           $generator Generator name.
	 * @return array<string, string> JSON contents indexed by file name.
	 */
	public function build( array $rows, $locale, $text_domain, $plural_count, $plural_expression, $include_unvalidated, $generator ) {
		$rows_by_script = array();

		foreach ( $rows as $row ) {
			if ( ! isset( $row['references'] ) || ! is_array( $row['references'] ) ) {
				continue;
			}

			foreach ( array_keys( $row['references'] ) as $reference_file ) {
				$script_path = self::normalize_script_path( $reference_file );

				if ( '' !== $script_path ) {
					$rows_by_script[ $script_path ][] = $row;
				}
			}
		}

		ksort( $rows_by_script );

		$files = array();

		foreach ( $rows_by_script as $script_path => $script_rows ) {
			$catalog = $this->catalog_builder->build(
				$script_rows,
				$locale,
				$text_domain,
				$plural_count,
				$plural_expression,
				array(),
				array(
					'complete_plurals_only' => true,
					'include_unvalidated'   => $include_unvalidated,
				)
			);

			if ( 0 === count( $catalog ) ) {
				continue;
			}

			$files[ self::get_file_name( $text_domain, $locale, $script_path ) ] = $this->encode( $catalog, $script_path, $locale, $text_domain, $plural_count, $plural_expression, $generator );
		}

		return $files;
	}

	/**
	 * Encodes one catalog as a Jed document.
	 *
	 * @param \Gettext\Translations $catalog Catalog.
	 * @param string                $script_path Script path.
	 * @param string                $locale Locale.
	 * @param string                $text_domain Text domain.
	 * @param int                   $plural_count Number of plural forms.
	 * @param string                $plural_expression Plural expression.
	 * @param string                $generator Generator name.
	 * @return string
	 */
	private function encode( $catalog, $script_path, $locale, $text_domain, $plural_count, $plural_expression, $generator ) {
		$messages = array(
			'' => array(
				'domain'       => $text_domain,
				'lang'         => $locale,
				'plural-forms' => sprintf( 'nplurals=%d; plural=%s;', $plural_count, $plural_expression ),
			),
		);

		foreach ( $catalog as $translation ) {
			$key = $translation->getOriginal();

			if ( null !== $translation->getContext() ) {
				$key = $translation->getContext() . "\x04" . $key;
			}

			$forms = array( (string) $translation->getTranslation() );

			if ( null !== $translation->getPlural() ) {
				$forms = array_merge( $forms, $translation->getPluralTranslations( max( 0, $plural_count - 1 ) ) );
			}

			$messages[ $key ] = $forms;
		}

		$document = array(
			'translation-revision-date' => gmdate( 'Y-m-d H:iO' ),
			'generator'                 => $generator,
			'source'                    => $script_path,
			'domain'                    => $text_domain,
			'locale_data'               => array( $text_domain => $messages ),
		);

		return (string) wp_json_encode( $document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}
