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
	 * Name of the button field telling what to do after saving: "install" or "download".
	 */
	public const AFTER_SAVE_FIELD = 'i18nly_after_save';

	/**
	 * Name of the field holding the choice about doubtful translations.
	 */
	public const CHOICE_FIELD = 'i18nly_unvalidated';

	/**
	 * Query argument asking the edit screen to start the download.
	 */
	public const DOWNLOAD_ARG = 'i18nly_download';

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
	 * Installer of the files.
	 *
	 * @var TranslationInstaller|null
	 */
	private $installer;

	/**
	 * Constructor.
	 *
	 * @param TranslationResourceRepository|null $repository Optional repository.
	 * @param TranslationInstaller|null          $installer Optional installer of the files.
	 */
	public function __construct( TranslationResourceRepository $repository = null, TranslationInstaller $installer = null ) {
		$this->repository = $repository;
		$this->installer  = $installer;
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
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'handle_save_and_export' ), 20, 1 );
		add_filter( 'redirect_post_location', array( $this, 'filter_redirect_location' ), 20, 1 );
		add_action( 'admin_notices', array( $meta_box, 'render_notice' ) );
		add_action( 'admin_footer', array( $meta_box, 'render_download_trigger' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $meta_box, 'register' ) );
	}

	/**
	 * Arguments to add to the address the browser is sent to after the save.
	 *
	 * @var array<string, string>
	 */
	private $redirect_arguments = array();

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
	 * Returns the signed address that downloads the archive of a translation.
	 *
	 * @param int  $translation_id Translation ID.
	 * @param bool $include_unvalidated Whether to include the translations that are not validated.
	 * @return string
	 */
	public function get_download_url( $translation_id, $include_unvalidated ) {
		// Not wp_nonce_url(): it HTML-escapes the address (&amp;), which breaks it when used in a script.
		return add_query_arg(
			array(
				'action'         => self::ACTION,
				'translation_id' => (int) $translation_id,
				'unvalidated'    => $include_unvalidated ? self::CHOICE_INCLUDE : self::CHOICE_EXCLUDE,
				'_wpnonce'       => wp_create_nonce( self::get_nonce_action( $translation_id ) ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Handles the download request: the browser is sent here after the translation was saved.
	 *
	 * @return void
	 */
	public function handle_export() {
		$translation_id = isset( $_GET['translation_id'] ) ? absint( $_GET['translation_id'] ) : 0;
		$nonce          = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( $translation_id <= 0 || ! current_user_can( 'edit_post', $translation_id ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'i18nly' ), 403 );
		}

		if ( ! wp_verify_nonce( $nonce, self::get_nonce_action( $translation_id ) ) ) {
			wp_die( esc_html__( 'Invalid request.', 'i18nly' ), 400 );
		}

		$choice = isset( $_GET['unvalidated'] ) ? sanitize_key( wp_unslash( $_GET['unvalidated'] ) ) : '';
		$file   = $this->build_file( $translation_id, 'bundle', self::CHOICE_INCLUDE === $choice );

		if ( null === $file ) {
			wp_die( esc_html__( 'There is nothing to export: no string is translated, or the translated ones were left out.', 'i18nly' ), 400 );
		}

		$this->send_file( $file );
	}

	/**
	 * Runs after the translation was saved by one of the "Save and ..." buttons: installs the files, or asks the edit screen to start the download.
	 *
	 * Runs after the entries were saved (priority 20), inside the request that saves the post, so the
	 * nonce and the capability checked are those of the save.
	 *
	 * @param int $post_id Translation post ID.
	 * @return void
	 */
	public function handle_save_and_export( $post_id ) {
		$after = isset( $_POST[ self::AFTER_SAVE_FIELD ] ) ? sanitize_key( wp_unslash( $_POST[ self::AFTER_SAVE_FIELD ] ) ) : '';
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! in_array( $after, array( 'install', 'download' ), true ) || wp_is_post_autosave( (int) $post_id ) || wp_is_post_revision( (int) $post_id ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'update-post_' . (int) $post_id ) || ! current_user_can( 'edit_post', (int) $post_id ) ) {
			return;
		}

		if ( 'install' === $after && ! current_user_can( 'install_languages' ) ) {
			$this->redirect_arguments = array( self::RESULT_ARG => 'forbidden' );
			return;
		}

		$choice  = isset( $_POST[ self::CHOICE_FIELD ] ) ? sanitize_key( wp_unslash( $_POST[ self::CHOICE_FIELD ] ) ) : '';
		$include = $this->resolve_choice( (int) $post_id, $choice );

		if ( null === $include ) {
			$this->redirect_arguments = array( self::RESULT_ARG => self::CONFIRMATION_REQUIRED );
		} elseif ( 'install' === $after ) {
			$this->redirect_arguments = array( self::RESULT_ARG => $this->install( (int) $post_id, $include ) );
		} else {
			$this->redirect_arguments = array( self::DOWNLOAD_ARG => $include ? self::CHOICE_INCLUDE : self::CHOICE_EXCLUDE );
		}
	}

	/**
	 * Adds the result of the export to the address the browser is sent to after the save.
	 *
	 * @param string $location Address.
	 * @return string
	 */
	public function filter_redirect_location( $location ) {
		return empty( $this->redirect_arguments ) ? $location : add_query_arg( $this->redirect_arguments, $location );
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

		$text_domain = ( new PluginMetadataProvider() )->resolve_text_domain( $source_slug );

		if ( 'bundle' === $format ) {
			return $this->build_bundle( $translation_id, $text_domain, $locale, $include_unvalidated );
		}

		$catalog = $this->build_catalog( $translation_id, $source_slug, $locale, $text_domain, $format, $include_unvalidated );

		// Without any translated string (left out ones included) there is nothing worth a file.
		if ( 'po' === $format && 0 === count( $catalog ) ) {
			return null;
		}

		return array(
			'text_domain' => $text_domain,
			'locale'      => $locale,
			'name'        => TranslationFileExporter::get_file_name( $text_domain, $locale, $format ),
			'mime'        => TranslationFileExporter::get_mime_type( $format ),
			'contents'    => ( new TranslationFileExporter() )->generate( $catalog, $format ),
		);
	}

	/**
	 * Builds the gettext catalog of a translation for a format.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $source_slug Source slug.
	 * @param string $locale Locale.
	 * @param string $text_domain Text domain.
	 * @param string $format "po" or "mo".
	 * @param bool   $include_unvalidated Whether to include the translations that are not validated.
	 * @return \Gettext\Translations
	 */
	private function build_catalog( $translation_id, $source_slug, $locale, $text_domain, $format, $include_unvalidated ) {
		$metadata = new PluginMetadataProvider();
		$spec     = PluralFormsRegistry::get_spec_for_locale( $locale );
		$headers  = $metadata->build_pot_header_overrides( $source_slug, $text_domain );

		$headers['X-Generator'] = 'I18nly ' . ( defined( 'I18NLY_VERSION' ) ? I18NLY_VERSION : '' );

		return ( new TranslationCatalogBuilder() )->build(
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
	}

	/**
	 * Builds the archive of every generated file: MO, PO and the JSON files of the scripts.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $text_domain Text domain.
	 * @param string $locale Locale.
	 * @param bool   $include_unvalidated Whether to include the translations that are not validated.
	 * @return array{text_domain: string, locale: string, name: string, mime: string, contents: string}|null Null when there is nothing to export or archives are not available.
	 */
	private function build_bundle( $translation_id, $text_domain, $locale, $include_unvalidated ) {
		$mo_file = $this->build_file( $translation_id, 'mo', $include_unvalidated );
		$po_file = $this->build_file( $translation_id, 'po', $include_unvalidated );

		if ( null === $mo_file || null === $po_file ) {
			return null;
		}

		$files   = array(
			$mo_file['name'] => $mo_file['contents'],
			$po_file['name'] => $po_file['contents'],
		) + $this->build_script_files( $translation_id, $include_unvalidated );
		$archive = ( new TranslationFileExporter() )->zip( $files );

		if ( null === $archive ) {
			return null;
		}

		return array(
			'text_domain' => $text_domain,
			'locale'      => $locale,
			'name'        => TranslationFileExporter::get_file_name( $text_domain, $locale, 'bundle' ),
			'mime'        => TranslationFileExporter::get_mime_type( 'bundle' ),
			'contents'    => $archive,
		);
	}

	/**
	 * Counts the translated texts of a translation (every non-empty text of every form, whatever its status).
	 *
	 * @param int $translation_id Translation ID.
	 * @return int
	 */
	public function count_translated_strings( $translation_id ) {
		$translation = $this->get_translation( $translation_id );

		if ( null === $translation || '' === $translation['source_slug'] || '' === $translation['target_language'] ) {
			return 0;
		}

		$count = 0;

		foreach ( $this->get_rows( $translation_id, $translation['source_slug'], $translation['target_language'] ) as $row ) {
			foreach ( isset( $row['translations'] ) && is_array( $row['translations'] ) ? $row['translations'] : array() as $target ) {
				if ( isset( $target['translation'] ) && '' !== (string) $target['translation'] ) {
					++$count;
				}
			}
		}

		return $count;
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

		$installer = $installer instanceof TranslationInstaller ? $installer : ( $this->installer instanceof TranslationInstaller ? $this->installer : new TranslationInstaller() );

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
