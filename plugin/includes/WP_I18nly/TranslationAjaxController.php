<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation AJAX controller.
 *
 * @package I18nly
 */

namespace WP_I18nly;

defined( 'ABSPATH' ) || exit;

/**
 * Handles translation edit AJAX actions.
 */
class TranslationAjaxController {
	/**
	 * Callback returning one translation row.
	 *
	 * @var callable
	 */
	private $get_translation_callback;

	/**
	 * Callback inferring text domain from source slug.
	 *
	 * @var callable
	 */
	private $infer_text_domain_callback;

	/**
	 * Callback building POT header overrides.
	 *
	 * @var callable
	 */
	private $build_header_overrides_callback;

	/**
	 * Callback returning translation source entries.
	 *
	 * @var callable
	 */
	private $get_source_entries_callback;

	/**
	 * Callback rendering source entries table markup.
	 *
	 * @var callable
	 */
	private $render_entries_table_callback;

	/**
	 * Constructor.
	 *
	 * @param callable $get_translation_callback Callback returning one translation row.
	 * @param callable $infer_text_domain_callback Callback inferring text domain.
	 * @param callable $build_header_overrides_callback Callback building POT header overrides.
	 * @param callable $get_source_entries_callback Callback returning source entries.
	 * @param callable $render_entries_table_callback Callback rendering source entries table.
	 */
	public function __construct(
		callable $get_translation_callback,
		callable $infer_text_domain_callback,
		callable $build_header_overrides_callback,
		callable $get_source_entries_callback,
		callable $render_entries_table_callback
	) {
		$this->get_translation_callback        = $get_translation_callback;
		$this->infer_text_domain_callback      = $infer_text_domain_callback;
		$this->build_header_overrides_callback = $build_header_overrides_callback;
		$this->get_source_entries_callback     = $get_source_entries_callback;
		$this->render_entries_table_callback   = $render_entries_table_callback;
	}

	/**
	 * Handles AJAX request to generate temporary POT for one translation.
	 *
	 * @return void
	 */
	public function handle_generate_translation_pot() {
		if ( ! isset( $_POST['translation_id'], $_POST['nonce'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing parameters.' ), 400 );
			return;
		}

		$translation_id = absint( wp_unslash( $_POST['translation_id'] ) );
		$nonce          = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

		if ( $translation_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid translation id.' ), 400 );
			return;
		}

		if ( ! current_user_can( 'edit_post', $translation_id ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'i18nly_generate_translation_pot_' . $translation_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
			return;
		}

		$get_translation = $this->get_translation_callback;
		$translation     = $get_translation( $translation_id );
		if ( null === $translation || empty( $translation['source_slug'] ) ) {
			wp_send_json_error( array( 'message' => 'Translation source is missing.' ), 400 );
			return;
		}

		$source_slug       = (string) $translation['source_slug'];
		$source_extractor  = new PotSourceEntryExtractor();
		$entries           = $source_extractor->extract_from_source_slug( $source_slug );
		$infer_text_domain = $this->infer_text_domain_callback;
		$build_headers     = $this->build_header_overrides_callback;
		$text_domain       = $infer_text_domain( $source_slug );
		$header_overrides  = $build_headers( $source_slug, $text_domain );
		$pot_workspace     = new PotWorkspaceService();
		$pot_importer      = new PotSourceImporter();

		try {
			$pot_file_path  = $pot_workspace->generate_temporary_pot( $translation_id, $text_domain, $entries, $header_overrides );
			$import_summary = $pot_importer->import_file( $source_slug, $pot_file_path );
		} catch ( \RuntimeException $exception ) {
			wp_send_json_error( array( 'message' => $exception->getMessage() ), 500 );
			return;
		}

		wp_send_json_success(
			array(
				'translation_id' => $translation_id,
				'entries_count'  => count( $entries ),
				'import_summary' => $import_summary,
				'pot_file_path'  => $pot_file_path,
			)
		);
	}

	/**
	 * Handles AJAX request to fetch source entries table HTML.
	 *
	 * @return void
	 */
	public function handle_get_translation_entries_table() {
		if ( ! isset( $_POST['translation_id'], $_POST['nonce'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing parameters.' ), 400 );
			return;
		}

		$translation_id = absint( wp_unslash( $_POST['translation_id'] ) );
		$nonce          = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

		if ( $translation_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid translation id.' ), 400 );
			return;
		}

		if ( ! current_user_can( 'edit_post', $translation_id ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'i18nly_get_translation_entries_table_' . $translation_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
			return;
		}

		$get_translation = $this->get_translation_callback;
		$translation     = $get_translation( $translation_id );
		if ( null === $translation || empty( $translation['source_slug'] ) ) {
			wp_send_json_error( array( 'message' => 'Translation source is missing.' ), 400 );
			return;
		}

		$source_slug          = (string) $translation['source_slug'];
		$get_source_entries   = $this->get_source_entries_callback;
		$render_entries_table = $this->render_entries_table_callback;
		$source_entries       = $get_source_entries( $translation_id, $source_slug );

		wp_send_json_success(
			array(
				'translation_id' => $translation_id,
				'entries_count'  => count( $source_entries ),
				'html'           => $render_entries_table( $source_entries ),
			)
		);
	}
}
