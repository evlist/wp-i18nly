<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation file export (download of PO and MO files).
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin;

use WP_I18nly\Export\TranslationCatalogBuilder;
use WP_I18nly\Export\TranslationFileExporter;
use WP_I18nly\LinguisticResources\TranslationResourceRepository;
use WP_I18nly\Plurals\PluralFormsRegistry;
use WP_I18nly\Support\PluginMetadataProvider;
use WP_I18nly\Support\TranslationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Lets an administrator download the PO and MO files of a translation.
 */
class TranslationExportController {
	/**
	 * Admin-post action.
	 */
	public const ACTION = 'i18nly_export_translation';

	/**
	 * Translation post type.
	 */
	private const POST_TYPE = 'i18nly_translation';

	/**
	 * Source slug post meta key.
	 */
	private const META_SOURCE_SLUG = '_i18nly_source_slug';

	/**
	 * Target language post meta key.
	 */
	private const META_TARGET_LANGUAGE = '_i18nly_target_language';

	/**
	 * Maximum number of source entries exported.
	 */
	private const ROW_LIMIT = 100000;

	/**
	 * Repository listing the translated rows.
	 *
	 * @var TranslationResourceRepository|null
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param TranslationResourceRepository|null $repository Optional repository.
	 */
	public function __construct( TranslationResourceRepository $repository = null ) {
		$this->repository = $repository;
	}

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_export' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'register_meta_box' ) );
	}

	/**
	 * Registers the side meta box with the download links.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		add_meta_box( 'i18nly-translation-export', __( 'Export', 'i18nly' ), array( $this, 'render_meta_box' ), self::POST_TYPE, 'side', 'default' );
	}

	/**
	 * Renders the download links.
	 *
	 * @param object $post Translation post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		$translation_id = (int) $post->ID;
		$translation    = $this->get_translation( $translation_id );

		if ( null === $translation || '' === $translation['source_slug'] || '' === $translation['target_language'] ) {
			echo '<p>' . esc_html__( 'Save the translation with a plugin and a language to enable the export.', 'i18nly' ) . '</p>';
			return;
		}

		echo '<p>' . esc_html__( 'Download the translated strings as a file ready to be used by WordPress.', 'i18nly' ) . '</p>';
		echo '<p>';
		echo '<a class="button" href="' . esc_url( $this->get_download_url( $translation_id, 'mo' ) ) . '">' . esc_html__( 'Download MO', 'i18nly' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( $this->get_download_url( $translation_id, 'po' ) ) . '">' . esc_html__( 'Download PO', 'i18nly' ) . '</a>';
		echo '</p>';
		echo '<p class="description">' . esc_html__( 'Entries without translation are left out. In the MO file, plural entries are left out unless all their forms are translated.', 'i18nly' ) . '</p>';
	}

	/**
	 * Returns the signed download URL.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $format Format.
	 * @return string
	 */
	public function get_download_url( $translation_id, $format ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'         => self::ACTION,
					'translation_id' => (int) $translation_id,
					'format'         => (string) $format,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . (int) $translation_id
		);
	}

	/**
	 * Handles the download request.
	 *
	 * @return void
	 */
	public function handle_export() {
		$translation_id = isset( $_GET['translation_id'] ) ? absint( $_GET['translation_id'] ) : 0;
		$format         = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : '';
		$nonce          = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( $translation_id <= 0 || ! current_user_can( 'edit_post', $translation_id ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'i18nly' ), 403 );
		}

		if ( ! wp_verify_nonce( $nonce, self::ACTION . '_' . $translation_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'i18nly' ), 400 );
		}

		$file = $this->build_file( $translation_id, $format );

		if ( null === $file ) {
			wp_die( esc_html__( 'This translation cannot be exported.', 'i18nly' ), 400 );
		}

		$this->send_file( $file );
	}

	/**
	 * Builds the file of a translation.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $format Format.
	 * @return array{name: string, mime: string, contents: string}|null Null when the translation or the format is invalid.
	 */
	public function build_file( $translation_id, $format ) {
		$translation = $this->get_translation( $translation_id );

		if ( null === $translation || ! TranslationFileExporter::is_supported_format( $format ) ) {
			return null;
		}

		$source_slug = $translation['source_slug'];
		$locale      = $translation['target_language'];

		if ( '' === $source_slug || '' === $locale ) {
			return null;
		}

		$metadata    = new PluginMetadataProvider();
		$text_domain = $metadata->infer_text_domain( $source_slug );
		$spec        = PluralFormsRegistry::get_spec_for_locale( $locale );
		$rows        = ( $this->repository instanceof TranslationResourceRepository ? $this->repository : new TranslationResourceRepository() )->list_translation_rows( (int) $translation_id, $source_slug, self::ROW_LIMIT, (int) $spec['nplurals'] );

		$headers = $metadata->build_pot_header_overrides( $source_slug, $text_domain );

		$headers['X-Generator'] = 'I18nly ' . ( defined( 'I18NLY_VERSION' ) ? I18NLY_VERSION : '' );

		$catalog = ( new TranslationCatalogBuilder() )->build(
			is_array( $rows ) ? $rows : array(),
			$locale,
			$text_domain,
			(int) $spec['nplurals'],
			(string) $spec['plural_expression'],
			$headers,
			'mo' === $format
		);

		return array(
			'name'     => TranslationFileExporter::get_file_name( $text_domain, $locale, $format ),
			'mime'     => TranslationFileExporter::get_mime_type( $format ),
			'contents' => ( new TranslationFileExporter() )->generate( $catalog, $format ),
		);
	}

	/**
	 * Returns the source and language of a translation.
	 *
	 * @param int $translation_id Translation ID.
	 * @return array<string, mixed>|null
	 */
	private function get_translation( $translation_id ) {
		return ( new TranslationRepository() )->get_translation( (int) $translation_id, self::POST_TYPE, self::META_SOURCE_SLUG, self::META_TARGET_LANGUAGE );
	}

	/**
	 * Sends the file to the browser and stops.
	 *
	 * @param array{name: string, mime: string, contents: string} $file File.
	 * @return void
	 */
	private function send_file( array $file ) {
		nocache_headers();
		header( 'Content-Type: ' . $file['mime'] );
		header( 'Content-Disposition: attachment; filename="' . $file['name'] . '"' );
		header( 'Content-Length: ' . strlen( $file['contents'] ) );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- binary file download, not HTML.
		echo $file['contents'];
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		exit;
	}
}
