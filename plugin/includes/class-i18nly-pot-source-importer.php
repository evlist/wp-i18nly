<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * POT source importer.
 *
 * @package I18nly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Imports source entries from one POT file into source tables.
 */
class I18nly_Pot_Source_Importer {
	/**
	 * Source schema manager.
	 *
	 * @var I18nly_Source_Schema_Manager
	 */
	private $schema_manager;

	/**
	 * Repository receiving upsert calls.
	 *
	 * @var object
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param I18nly_Source_Schema_Manager|null $schema_manager Source schema manager.
	 * @param object|null                       $repository Repository with upsert methods.
	 */
	public function __construct( $schema_manager = null, $repository = null ) {
		$this->schema_manager = $schema_manager instanceof I18nly_Source_Schema_Manager
			? $schema_manager
			: new I18nly_Source_Schema_Manager();

		$this->repository = null !== $repository
			? $repository
			: new I18nly_Source_Wpdb_Repository( $this->schema_manager );
	}

	/**
	 * Imports one POT file for one plugin slug.
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @param string $pot_file_path Absolute POT file path.
	 * @return array<string, int> Import summary counters.
	 */
	public function import_file( $plugin_slug, $pot_file_path ) {
		$this->ensure_gettext_loader_is_available();

		$this->schema_manager->maybe_upgrade();

		$plugin_slug   = (string) $plugin_slug;
		$pot_file_path = (string) $pot_file_path;

		$loader       = new \Gettext\Loader\PoLoader();
		$translations = $loader->loadFile( $pot_file_path );
		$headers      = $translations->getHeaders()->toArray();

		$domain  = isset( $headers['X-Domain'] ) ? (string) $headers['X-Domain'] : '';
		$now_gmt = gmdate( 'Y-m-d H:i:s' );

		$catalog_id = (int) $this->repository->upsert_catalog(
			$plugin_slug,
			$domain,
			$this->encode_json( $headers ),
			$now_gmt
		);

		$plural_form  = $translations->getHeaders()->getPluralForm();
		$plural_count = is_array( $plural_form ) && isset( $plural_form[0] )
			? max( 1, (int) $plural_form[0] )
			: 2;

		$inserted  = 0;
		$updated   = 0;
		$unchanged = 0;

		foreach ( $translations as $translation ) {
			$msgid = (string) $translation->getOriginal();

			if ( '' === $msgid ) {
				continue;
			}

			$msgctxt      = $translation->getContext();
			$msgid_plural = $translation->getPlural();
			$entry_count  = null !== $msgid_plural ? $plural_count : 1;

			for ( $plural_index = 0; $plural_index < $entry_count; $plural_index++ ) {
				$result = (string) $this->repository->upsert_source_entry(
					array(
						'catalog_id'      => $catalog_id,
						'msgctxt'         => null !== $msgctxt ? (string) $msgctxt : null,
						'msgid'           => $msgid,
						'msgid_plural'    => null !== $msgid_plural ? (string) $msgid_plural : null,
						'plural_index'    => $plural_index,
						'comments_json'   => $this->encode_json(
							array(
								'comments'           => $translation->getComments()->toArray(),
								'extracted_comments' => $translation->getExtractedComments()->toArray(),
							)
						),
						'references_json' => $this->encode_json( $translation->getReferences()->toArray() ),
						'flags_json'      => $this->encode_json( $translation->getFlags()->toArray() ),
						'status'          => 'active',
						'created_at_gmt'  => $now_gmt,
						'updated_at_gmt'  => $now_gmt,
					)
				);

				if ( 'inserted' === $result ) {
					++$inserted;
				} elseif ( 'updated' === $result ) {
					++$updated;
				} else {
					++$unchanged;
				}
			}
		}

		return array(
			'catalog_id' => $catalog_id,
			'inserted'   => $inserted,
			'updated'    => $updated,
			'unchanged'  => $unchanged,
		);
	}

	/**
	 * Ensures gettext loader classes are autoloadable.
	 *
	 * @return void
	 */
	private function ensure_gettext_loader_is_available() {
		if ( class_exists( '\\Gettext\\Loader\\PoLoader' ) ) {
			return;
		}

		require_once dirname( __DIR__ ) . '/third-party/vendor/autoload.php';
	}

	/**
	 * Encodes one value to JSON.
	 *
	 * @param mixed $value Value to encode.
	 * @return string
	 */
	private function encode_json( $value ) {
		if ( function_exists( 'wp_json_encode' ) ) {
			$encoded = wp_json_encode( $value );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Used only as fallback outside WordPress runtime.
			$encoded = json_encode( $value );
		}

		if ( false === $encoded ) {
			return '{}';
		}

		return $encoded;
	}
}
