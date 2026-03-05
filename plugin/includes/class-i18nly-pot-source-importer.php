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

		$inserted  = 0;
		$updated   = 0;
		$unchanged = 0;
		$obsoleted = 0;

		if ( method_exists( $this->repository, 'reset_last_seen_for_catalog' ) ) {
			$this->repository->reset_last_seen_for_catalog( $catalog_id );
		}

		foreach ( $translations as $translation ) {
			$msgid = (string) $translation->getOriginal();

			if ( '' === $msgid ) {
				continue;
			}

			$msgctxt      = $translation->getContext();
			$msgid_plural = $translation->getPlural();

			$result = (string) $this->repository->upsert_source_entry(
				array(
					'catalog_id'       => $catalog_id,
					'msgctxt'          => null !== $msgctxt ? (string) $msgctxt : null,
					'msgid'            => $msgid,
					'msgid_plural'     => null !== $msgid_plural ? (string) $msgid_plural : null,
					'comments_json'    => $this->encode_json(
						array(
							'comments'           => $translation->getComments()->toArray(),
							'extracted_comments' => $translation->getExtractedComments()->toArray(),
						)
					),
					'references_json'  => $this->encode_json( $translation->getReferences()->toArray() ),
					'flags_json'       => $this->encode_json( $translation->getFlags()->toArray() ),
					'status'           => 'active',
					'last_seen_at_gmt' => $now_gmt,
					'created_at_gmt'   => $now_gmt,
					'updated_at_gmt'   => $now_gmt,
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

		if ( method_exists( $this->repository, 'mark_obsolete_entries_not_seen' ) ) {
			$obsoleted = (int) $this->repository->mark_obsolete_entries_not_seen( $catalog_id, $now_gmt );
		}

		return array(
			'catalog_id' => $catalog_id,
			'inserted'   => $inserted,
			'updated'    => $updated,
			'unchanged'  => $unchanged,
			'obsoleted'  => $obsoleted,
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
		$encoded = wp_json_encode( $value );

		if ( false === $encoded ) {
			return '{}';
		}

		return $encoded;
	}
}
