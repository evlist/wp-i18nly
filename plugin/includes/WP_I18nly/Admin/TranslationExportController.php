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

use WP_I18nly\Admin\UI\TranslationExportMetaBox;
use WP_I18nly\Export\ScriptTranslationsBuilder;
use WP_I18nly\Export\TranslationCatalogBuilder;
use WP_I18nly\Export\TranslationFileExporter;
use WP_I18nly\Export\TranslationInstaller;
use WP_I18nly\LinguisticResources\TranslationResourceRepository;
use WP_I18nly\Plurals\PluralFormsRegistry;
use WP_I18nly\Support\PluginMetadataProvider;
use WP_I18nly\Support\TranslationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Lets an administrator download or install the PO and MO files of a translation.
 *
 * Translations whose status is not validated (drafts, AI suggestions, suspect ones) are doubtful: when
 * there are some, the administrator must explicitly choose to include them or to leave them out; there
 * is no default, and a request without a choice is refused.
 */
class TranslationExportController {
	/**
	 * Admin-post action downloading a file.
	 */
	public const ACTION = 'i18nly_export_translation';

	/**
	 * Admin-post action installing the files in the languages directory.
	 */
	public const INSTALL_ACTION = 'i18nly_install_translation';

	/**
	 * Value of the choice field to include doubtful translations.
	 */
	public const CHOICE_INCLUDE = 'include';

	/**
	 * Value of the choice field to leave doubtful translations out.
	 */
	public const CHOICE_EXCLUDE = 'exclude';

	/**
	 * Result code when doubtful translations exist and no choice was made.
	 */
	public const CONFIRMATION_REQUIRED = 'confirmation_required';

	/**
	 * Query argument carrying the result of an installation.
	 */
	public const RESULT_ARG = 'i18nly_install';

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
	 * Returns the nonce action shared by the forms of a translation.
	 *
	 * @param int $translation_id Translation ID.
	 * @return string
	 */
	public static function get_nonce_action( $translation_id ) {
		return 'i18nly_translation_files_' . (int) $translation_id;
	}

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public function register() {
		$meta_box = new TranslationExportMetaBox( $this );

		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_export' ) );
		add_action( 'admin_post_' . self::INSTALL_ACTION, array( $this, 'handle_install' ) );
		add_action( 'admin_notices', array( $meta_box, 'render_notice' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $meta_box, 'register' ) );
	}

	/**
	 * Returns the source and language of a translation.
	 *
	 * @param int $translation_id Translation ID.
	 * @return array<string, mixed>|null
	 */
	public function get_translation( $translation_id ) {
		return ( new TranslationRepository() )->get_translation( (int) $translation_id, self::POST_TYPE, self::META_SOURCE_SLUG, self::META_TARGET_LANGUAGE );
	}

	/**
	 * Handles the download request.
	 *
	 * @return void
	 */
	public function handle_export() {
		$translation_id = isset( $_POST['translation_id'] ) ? absint( $_POST['translation_id'] ) : 0;
		$nonce          = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( $translation_id <= 0 || ! current_user_can( 'edit_post', $translation_id ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'i18nly' ), 403 );
		}

		if ( ! wp_verify_nonce( $nonce, self::get_nonce_action( $translation_id ) ) ) {
			wp_die( esc_html__( 'Invalid request.', 'i18nly' ), 400 );
		}

		$format  = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : '';
		$choice  = isset( $_POST['unvalidated'] ) ? sanitize_key( wp_unslash( $_POST['unvalidated'] ) ) : '';
		$include = $this->resolve_choice( $translation_id, $choice );

		if ( null === $include ) {
			$this->redirect_to_edit_screen( $translation_id, self::CONFIRMATION_REQUIRED );
		}

		$file = $this->build_file( $translation_id, $format, $include );

		if ( null === $file ) {
			wp_die( esc_html__( 'This translation cannot be exported.', 'i18nly' ), 400 );
		}

		$this->send_file( $file );
	}

	/**
	 * Handles the request installing the files in the languages directory.
	 *
	 * @return void
	 */
	public function handle_install() {
		$translation_id = isset( $_POST['translation_id'] ) ? absint( $_POST['translation_id'] ) : 0;
		$nonce          = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( $translation_id <= 0 || ! current_user_can( 'edit_post', $translation_id ) || ! current_user_can( 'install_languages' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'i18nly' ), 403 );
		}

		if ( ! wp_verify_nonce( $nonce, self::get_nonce_action( $translation_id ) ) ) {
			wp_die( esc_html__( 'Invalid request.', 'i18nly' ), 400 );
		}

		$choice  = isset( $_POST['unvalidated'] ) ? sanitize_key( wp_unslash( $_POST['unvalidated'] ) ) : '';
		$include = $this->resolve_choice( $translation_id, $choice );

		$this->redirect_to_edit_screen( $translation_id, null === $include ? self::CONFIRMATION_REQUIRED : $this->install( $translation_id, $include ) );
	}

	/**
	 * Interprets the choice about doubtful translations.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $choice Posted choice.
	 * @return bool|null True to include them, false to leave them out, null when a choice is required and was not made.
	 */
	private function resolve_choice( $translation_id, $choice ) {
		if ( 0 === $this->count_unvalidated( $translation_id ) ) {
			return false;
		}

		if ( self::CHOICE_INCLUDE === $choice ) {
			return true;
		}

		return self::CHOICE_EXCLUDE === $choice ? false : null;
	}

	/**
	 * Redirects to the edit screen of a translation with a result code, and stops.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $result Result code.
	 * @return void
	 */
	private function redirect_to_edit_screen( $translation_id, $result ) {
		wp_safe_redirect( add_query_arg( self::RESULT_ARG, $result, admin_url( 'post.php?post=' . (int) $translation_id . '&action=edit' ) ) );
		exit;
	}

	/**
	 * Returns the number of entries having a translation that is not validated.
	 *
	 * @param int $translation_id Translation ID.
	 * @return int
	 */
	public function count_unvalidated( $translation_id ) {
		$translation = $this->get_translation( $translation_id );

		if ( null === $translation || '' === $translation['source_slug'] || '' === $translation['target_language'] ) {
			return 0;
		}

		return ( new TranslationCatalogBuilder() )->count_unvalidated( $this->get_rows( $translation_id, $translation['source_slug'], $translation['target_language'] ) );
	}

	/**
	 * Reads all the rows of a translation.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $source_slug Source slug.
	 * @param string $locale Locale.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_rows( $translation_id, $source_slug, $locale ) {
		$repository = $this->repository instanceof TranslationResourceRepository ? $this->repository : new TranslationResourceRepository();
		$rows       = $repository->list_translation_rows( (int) $translation_id, $source_slug, self::ROW_LIMIT, (int) PluralFormsRegistry::get_spec_for_locale( $locale )['nplurals'] );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Builds the file of a translation.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $format Format.
	 * @param bool   $include_unvalidated Whether to include the translations that are not validated.
	 * @return array{text_domain: string, locale: string, name: string, mime: string, contents: string}|null Null when the translation or the format is invalid.
	 */
	public function build_file( $translation_id, $format, $include_unvalidated = false ) {
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
		$text_domain = $metadata->resolve_text_domain( $source_slug );
		$spec        = PluralFormsRegistry::get_spec_for_locale( $locale );
		$headers     = $metadata->build_pot_header_overrides( $source_slug, $text_domain );

		if ( 'json' === $format ) {
			$scripts = $this->build_script_files( $translation_id, $include_unvalidated );
			$archive = ( new TranslationFileExporter() )->zip( $scripts );

			return null === $archive ? null : array(
				'text_domain' => $text_domain,
				'locale'      => $locale,
				'name'        => TranslationFileExporter::get_file_name( $text_domain, $locale, 'json' ),
				'mime'        => TranslationFileExporter::get_mime_type( 'json' ),
				'contents'    => $archive,
			);
		}

		$headers['X-Generator'] = 'I18nly ' . ( defined( 'I18NLY_VERSION' ) ? I18NLY_VERSION : '' );

		$catalog = ( new TranslationCatalogBuilder() )->build(
			$this->get_rows( $translation_id, $source_slug, $locale ),
			$locale,
			$text_domain,
			(int) $spec['nplurals'],
			(string) $spec['plural_expression'],
			$headers,
			array(
				'complete_plurals_only' => 'mo' === $format,
				'include_unvalidated'   => (bool) $include_unvalidated,
				// In the PO file the doubtful translations stay flagged for the next person reading it.
				'fuzzy_unvalidated'     => 'po' === $format,
			)
		);

		return array(
			'text_domain' => $text_domain,
			'locale'      => $locale,
			'name'        => TranslationFileExporter::get_file_name( $text_domain, $locale, $format ),
			'mime'        => TranslationFileExporter::get_mime_type( $format ),
			'contents'    => ( new TranslationFileExporter() )->generate( $catalog, $format ),
		);
	}

	/**
	 * Builds the JSON files of the scripts of a translation.
	 *
	 * @param int  $translation_id Translation ID.
	 * @param bool $include_unvalidated Whether to include the translations that are not validated.
	 * @return array<string, string> Contents indexed by file name.
	 */
	public function build_script_files( $translation_id, $include_unvalidated = false ) {
		$translation = $this->get_translation( $translation_id );

		if ( null === $translation || '' === $translation['source_slug'] || '' === $translation['target_language'] ) {
			return array();
		}

		$locale = $translation['target_language'];
		$spec   = PluralFormsRegistry::get_spec_for_locale( $locale );

		return ( new ScriptTranslationsBuilder() )->build(
			$this->get_rows( $translation_id, $translation['source_slug'], $locale ),
			$locale,
			( new PluginMetadataProvider() )->resolve_text_domain( $translation['source_slug'] ),
			(int) $spec['nplurals'],
			(string) $spec['plural_expression'],
			(bool) $include_unvalidated,
			'I18nly ' . ( defined( 'I18NLY_VERSION' ) ? I18NLY_VERSION : '' )
		);
	}

	/**
	 * Tells whether some script of the plugin has translated strings (whatever their status), so that a JSON archive is worth offering.
	 *
	 * @param int $translation_id Translation ID.
	 * @return bool
	 */
	public function has_script_files( $translation_id ) {
		return 'available' === $this->get_script_files_state( $translation_id );
	}

	/**
	 * Tells whether the JSON archive can be offered, and why not when it cannot.
	 *
	 * @param int $translation_id Translation ID.
	 * @return string "available", "no_archive_support" (the PHP zip extension is missing) or "no_translated_script_string" (no JavaScript string is translated yet).
	 */
	public function get_script_files_state( $translation_id ) {
		if ( ! TranslationFileExporter::can_create_archives() ) {
			return 'no_archive_support';
		}

		return array() === $this->build_script_files( $translation_id, true ) ? 'no_translated_script_string' : 'available';
	}

	/**
	 * Installs the files of a translation.
	 *
	 * @param int                       $translation_id Translation ID.
	 * @param bool                      $include_unvalidated Whether to include the translations that are not validated.
	 * @param TranslationInstaller|null $installer Optional installer.
	 * @return string Result code: a TranslationInstaller constant or "not_exportable".
	 */
	public function install( $translation_id, $include_unvalidated = false, TranslationInstaller $installer = null ) {
		$mo_file = $this->build_file( $translation_id, 'mo', $include_unvalidated );
		$po_file = $this->build_file( $translation_id, 'po', $include_unvalidated );

		if ( null === $mo_file || null === $po_file ) {
			return 'not_exportable';
		}

		$installer = $installer instanceof TranslationInstaller ? $installer : new TranslationInstaller();

		return $installer->install( $mo_file['text_domain'], $mo_file['locale'], $mo_file['contents'], $po_file['contents'], $this->build_script_files( $translation_id, $include_unvalidated ) );
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
