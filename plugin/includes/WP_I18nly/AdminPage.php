<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * I18nly admin page class.
 *
 * @package I18nly
 */

namespace WP_I18nly;

use WP_I18nly\Admin\TranslationEditController;
use WP_I18nly\Admin\UI\TranslationListColumns;
use WP_I18nly\Admin\UI\TranslationMessages;
use WP_I18nly\Admin\UI\EditScreenAssets;
use WP_I18nly\Support\AdminRegistration;
use WP_I18nly\Support\CurrentEditTranslationResolver;
use WP_I18nly\Support\TranslationRepository;
use WP_I18nly\Support\PluginMetadataProvider;
use WP_I18nly\Support\LanguageOptionsProvider;
use WP_I18nly\Support\TranslationEntriesPersister;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the I18nly admin pages.
 */
class AdminPage {
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
	 * Native WordPress list screen slug for translations.
	 */
	private const LIST_SCREEN_SLUG = 'edit.php?post_type=i18nly_translation';

	/**
	 * Source locale used by the current MVP.
	 */
	private const SOURCE_LOCALE = 'en_US';

	/**
	 * Native WordPress new translation screen slug.
	 */
	private const NEW_SCREEN_SLUG = 'post-new.php?post_type=i18nly_translation';

	/**
	 * Returns installed plugins as options for the selector.
	 *
	 * @return array<string, string>
	 */
	protected function get_plugin_options() {
		return $this->get_plugin_metadata_provider()->get_plugin_options();
	}

	/**
	 * Returns language options for target language selector.
	 *
	 * Installed languages are listed first, then a separator, then all
	 * remaining languages.
	 *
	 * @return array<int, array{value: string, label: string, disabled: bool}>
	 */
	protected function get_target_language_options() {
		return $this->get_language_options_provider()->get_target_language_options( self::SOURCE_LOCALE );
	}

	/**
	 * Registers hooks used by the admin page.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'render_translation_edit_pot_generation_script' ) );
		add_action( 'wp_ajax_i18nly_generate_translation_pot', array( $this, 'ajax_generate_translation_pot' ) );
		add_action( 'wp_ajax_i18nly_get_translation_entries_table', array( $this, 'ajax_get_translation_entries_table' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'register_translation_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_translation_meta_box' ), 10, 3 );
		add_filter( 'post_updated_messages', array( $this, 'filter_translation_post_updated_messages' ) );
		add_filter( 'bulk_post_updated_messages', array( $this, 'filter_translation_bulk_updated_messages' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'filter_translation_row_actions' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_columns', array( $this, 'filter_translation_list_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_translation_list_column' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( $this, 'filter_translation_sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_translation_sorting' ) );
	}

	/**
	 * Replaces default post update messages for translation posts.
	 *
	 * @param array<string, array<int, string>> $messages Current messages.
	 * @return array<string, array<int, string>>
	 */
	public function filter_translation_post_updated_messages( array $messages ) {
		return $this->get_translation_messages()->filter_post_updated_messages( $messages, self::POST_TYPE );
	}

	/**
	 * Replaces default bulk update messages for translation posts.
	 *
	 * @param array<string, array<string, string>> $bulk_messages Current bulk messages.
	 * @param array<string, int>                   $bulk_counts Item counts.
	 * @return array<string, array<string, string>>
	 */
	public function filter_translation_bulk_updated_messages( array $bulk_messages, array $bulk_counts ) {
		return $this->get_translation_messages()->filter_bulk_updated_messages( $bulk_messages, $bulk_counts, self::POST_TYPE );
	}

	/**
	 * Registers translation meta box on native editor screens.
	 *
	 * @return void
	 */
	public function register_translation_meta_box() {
		$this->get_translation_edit_controller()->register_translation_meta_box(
			self::POST_TYPE,
			array( $this, 'render_translation_meta_box' )
		);
	}

	/**
	 * Renders translation meta box fields.
	 *
	 * @param object $post Current post object.
	 * @return void
	 */
	public function render_translation_meta_box( $post ) {
		$this->get_translation_edit_controller()->handle_render_translation_meta_box(
			$post,
			self::META_SOURCE_SLUG,
			self::META_TARGET_LANGUAGE
		);
	}

	/**
	 * Returns translation meta box renderer.
	 *
	 * @return \WP_I18nly\Admin\UI\TranslationMetaBoxRenderer
	 */
	protected function get_meta_box_renderer() {
		return new \WP_I18nly\Admin\UI\TranslationMetaBoxRenderer();
	}

	/**
	 * Saves translation fields from native post editor.
	 *
	 * @param int    $post_id Post ID.
	 * @param object $post Current post object.
	 * @param bool   $update Whether this is an update.
	 * @return void
	 */
	public function save_translation_meta_box( $post_id, $post, $update ) {
		$this->get_translation_edit_controller()->handle_save_translation_meta_box( $post_id, $post, $update );
	}

	/**
	 * Returns save handler.
	 *
	 * @return \WP_I18nly\TranslationSaveHandler
	 */
	protected function get_save_handler() {
		return new \WP_I18nly\TranslationSaveHandler(
			self::POST_TYPE,
			self::META_SOURCE_SLUG,
			self::META_TARGET_LANGUAGE,
			function ( $translation_id, $source_slug, array $entries_payload ) {
				$this->persist_translation_entries( $translation_id, $source_slug, $entries_payload );
			},
			function ( $source_slug, $target_language, $current_post_id ) {
				return $this->find_duplicate_translation_id( $source_slug, $target_language, $current_post_id );
			},
			function ( $new_post_id, $existing_translation_id, $source_slug, $target_language ) {
				$this->handle_duplicate_translation_creation( $new_post_id, $existing_translation_id, $source_slug, $target_language );
			}
		);
	}

	/**
	 * Persists translation entries payload.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $source_slug Source slug.
	 * @param array  $entries_payload Entries payload.
	 * @return void
	 */
	protected function persist_translation_entries( $translation_id, $source_slug, array $entries_payload ) {
		$this->get_translation_entries_persister()->persist( (int) $translation_id, (string) $source_slug, $entries_payload );
	}

	/**
	 * Finds existing translation with same source and target language.
	 *
	 * @param string $source_slug Source slug.
	 * @param string $target_language Target language.
	 * @param int    $current_post_id Current post ID.
	 * @return int
	 */
	protected function find_duplicate_translation_id( $source_slug, $target_language, $current_post_id ) {
		$posts = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
			)
		);

		foreach ( $posts as $post ) {
			if ( ! is_object( $post ) || ! isset( $post->ID, $post->post_type ) ) {
				continue;
			}

			if ( self::POST_TYPE !== (string) $post->post_type ) {
				continue;
			}

			$post_id = (int) $post->ID;
			if ( $post_id <= 0 || $post_id === (int) $current_post_id ) {
				continue;
			}

			if (
				(string) get_post_meta( $post_id, self::META_SOURCE_SLUG, true ) === $source_slug
				&& (string) get_post_meta( $post_id, self::META_TARGET_LANGUAGE, true ) === $target_language
			) {
				return $post_id;
			}
		}

		return 0;
	}

	/**
	 * Handles duplicate translation creation attempt.
	 *
	 * @param int    $new_post_id New post ID.
	 * @param int    $existing_translation_id Existing translation ID.
	 * @param string $source_slug Source slug.
	 * @param string $target_language Target language.
	 * @return void
	 */
	protected function handle_duplicate_translation_creation( $new_post_id, $existing_translation_id, $source_slug, $target_language ) {
		wp_trash_post( (int) $new_post_id );

		$open_url   = $this->get_translation_repository()->get_edit_url( (int) $existing_translation_id );
		$cancel_url = admin_url( self::NEW_SCREEN_SLUG );

		$message = sprintf(
			/* translators: 1: source slug, 2: target language. */
			__( 'A translation already exists for %1$s in %2$s.', 'i18nly' ),
			esc_html( $source_slug ),
			esc_html( $target_language )
		);

		$message .= '<p>';
		$message .= '<a class="button button-primary" href="' . esc_url( $open_url ) . '">' . esc_html__( 'Open existing translation', 'i18nly' ) . '</a> ';
		$message .= '<a class="button" href="' . esc_url( $cancel_url ) . '">' . esc_html__( 'Cancel', 'i18nly' ) . '</a>';
		$message .= '</p>';

		wp_die( wp_kses_post( $message ), esc_html__( 'Duplicate translation', 'i18nly' ), array( 'response' => 409 ) );
	}

	/**
	 * Filters translation list table columns.
	 *
	 * @param array<string, string> $columns Current columns.
	 * @return array<string, string>
	 */
	public function filter_translation_list_columns( array $columns ) {
		return $this->get_list_columns()->filter_columns( $columns );
	}

	/**
	 * Renders custom column content for translation rows.
	 *
	 * @param string $column_name Column key.
	 * @param int    $post_id Translation post ID.
	 * @return void
	 */
	public function render_translation_list_column( $column_name, $post_id ) {
		echo esc_html( $this->get_list_columns()->get_column_value( $column_name, $post_id, self::META_SOURCE_SLUG, self::META_TARGET_LANGUAGE ) );
	}

	/**
	 * Filters sortable columns for translation list table.
	 *
	 * @param array<string, string> $columns Current sortable columns.
	 * @return array<string, string>
	 */
	public function filter_translation_sortable_columns( array $columns ) {
		return $this->get_list_columns()->filter_sortable_columns( $columns );
	}

	/**
	 * Applies meta sorting for translation custom columns.
	 *
	 * @param object $query Current query object.
	 * @return void
	 */
	public function apply_translation_sorting( $query ) {
		$this->get_list_columns()->apply_sorting( $query, self::POST_TYPE, self::META_SOURCE_SLUG, self::META_TARGET_LANGUAGE );
	}

	/**
	 * Registers the translation custom post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$this->get_admin_registration()->register_post_type();
	}

	/**
	 * Registers the admin menu entries.
	 *
	 * @return void
	 */
	public function register_menu() {
		$this->get_admin_registration()->register_menu( self::LIST_SCREEN_SLUG, self::NEW_SCREEN_SLUG );
	}

	/**
	 * Filters row actions to remove Quick Edit for translation posts.
	 *
	 * @param array<string, string> $actions Current row actions.
	 * @param object                $post Current post object.
	 * @return array<string, string>
	 */
	public function filter_translation_row_actions( array $actions, $post ) {
		return $this->get_list_columns()->filter_row_actions( $actions, $post, self::POST_TYPE );
	}

	/**
	 * Returns one translation row by ID.
	 *
	 * @param int $translation_id Translation ID.
	 * @return array<string, mixed>|null
	 */
	protected function get_translation( $translation_id ) {
		return $this->get_translation_repository()->get_translation(
			$translation_id,
			self::POST_TYPE,
			self::META_SOURCE_SLUG,
			self::META_TARGET_LANGUAGE
		);
	}

	/**
	 * Returns the native WordPress list page URL for translations.
	 *
	 * @return string
	 */
	private function get_native_list_url() {
		return $this->get_translation_repository()->get_list_url( self::LIST_SCREEN_SLUG );
	}

	/**
	 * Renders the all translations page.
	 *
	 * @return void
	 */
	public function render_all_translations_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_safe_redirect( $this->get_native_list_url() );
	}

	/**
	 * Renders a tiny script that triggers POT generation on edit screen open.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function render_translation_edit_pot_generation_script( $hook_suffix = '' ) {
		$this->get_translation_edit_controller()->render_translation_edit_pot_generation_script(
			$hook_suffix,
			defined( 'I18NLY_VERSION' ) ? I18NLY_VERSION : '0.1.0'
		);
	}

	/**
	 * Returns translation edit script URL.
	 *
	 * @return string
	 */
	private function get_translation_edit_script_url() {
		return $this->get_edit_screen_assets()->get_script_url();
	}

	/**
	 * Returns translation edit style URL.
	 *
	 * @return string
	 */
	private function get_translation_edit_style_url() {
		return $this->get_edit_screen_assets()->get_style_url();
	}

	/**
	 * Builds translation edit script configuration.
	 *
	 * @param int $translation_id Translation ID.
	 * @return array<string, mixed>
	 */
	private function build_translation_edit_script_config( $translation_id ) {
		return $this->get_edit_screen_assets()->build_script_config( $translation_id );
	}

	/**
	 * Handles AJAX request to generate temporary POT for one translation.
	 *
	 * @return void
	 */
	public function ajax_generate_translation_pot() {
		$this->get_translation_edit_controller()->ajax_generate_translation_pot();
	}

	/**
	 * Handles AJAX request to fetch source entries table HTML.
	 *
	 * @return void
	 */
	public function ajax_get_translation_entries_table() {
		$this->get_translation_edit_controller()->ajax_get_translation_entries_table();
	}

	/**
	 * Returns translation edit controller.
	 *
	 * @return \WP_I18nly\Admin\TranslationEditController
	 */
	protected function get_translation_edit_controller() {
		return new TranslationEditController(
			function () {
				return $this->get_plugin_options();
			},
			function () {
				return $this->get_target_language_options();
			},
			function () {
				return $this->get_meta_box_renderer();
			},
			function () {
				return $this->get_save_handler();
			},
			function () {
				return $this->get_current_edit_translation_id();
			},
			function () {
				return $this->get_translation_edit_script_url();
			},
			function () {
				return $this->get_translation_edit_style_url();
			},
			function ( $translation_id ) {
				return $this->build_translation_edit_script_config( (int) $translation_id );
			},
			function () {
				return $this->get_ajax_controller();
			}
		);
	}

	/**
	 * Returns translation list columns handler.
	 *
	 * @return \WP_I18nly\Admin\UI\TranslationListColumns
	 */
	protected function get_list_columns() {
		return new TranslationListColumns();
	}

	/**
	 * Returns translation messages handler.
	 *
	 * @return \WP_I18nly\Admin\UI\TranslationMessages
	 */
	protected function get_translation_messages() {
		return new TranslationMessages();
	}

	/**
	 * Returns edit screen assets handler.
	 *
	 * @return \WP_I18nly\Admin\UI\EditScreenAssets
	 */
	protected function get_edit_screen_assets() {
		return new EditScreenAssets();
	}

	/**
	 * Returns translation AJAX controller.
	 *
	 * @return \WP_I18nly\TranslationAjaxController
	 */
	protected function get_ajax_controller() {
		return new \WP_I18nly\TranslationAjaxController(
			function ( $translation_id ) {
				return $this->get_translation( $translation_id );
			},
			function ( $source_slug ) {
				return $this->infer_text_domain_from_source_slug( $source_slug );
			},
			function ( $source_slug, $text_domain ) {
				return $this->build_pot_header_overrides_from_source_slug( $source_slug, $text_domain );
			},
			function ( $translation_id, $source_slug ) {
				return $this->get_translation_source_entries( $translation_id, $source_slug );
			},
			function ( array $source_entries ) {
				return $this->get_meta_box_renderer()->render_source_entries_table_markup( $source_entries );
			}
		);
	}

	/**
	 * Returns source entries rows for one translation.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $source_slug Source slug.
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_translation_source_entries( $translation_id, $source_slug ) {
		$schema_manager = new \WP_I18nly\Storage\SourceSchemaManager();
		$schema_manager->maybe_upgrade();

		$repository  = new \WP_I18nly\Storage\SourceWpdbRepository( $schema_manager );
		$now_gmt     = gmdate( 'Y-m-d H:i:s' );
		$translation = $this->get_translation( $translation_id );
		$locale      = is_array( $translation ) && isset( $translation['target_language'] )
			? (string) $translation['target_language']
			: '';
		$form_count  = \WP_I18nly\Plurals\PluralFormsRegistry::get_plural_forms_count_for_locale( $locale );

		if ( method_exists( $repository, 'ensure_translated_entries_for_translation' ) ) {
			$repository->ensure_translated_entries_for_translation( (int) $translation_id, (string) $source_slug, $now_gmt, $form_count );
		}

		if ( method_exists( $repository, 'list_translation_entries_by_plugin_slug' ) ) {
			$entries       = $repository->list_translation_entries_by_plugin_slug( (int) $translation_id, (string) $source_slug, 500, $form_count );
			$forms         = \WP_I18nly\Plurals\PluralFormsRegistry::get_forms_for_locale( $locale );
			$form_labels   = \WP_I18nly\Plurals\PluralFormsRegistry::get_form_labels_for_locale( $locale );
			$form_markers  = \WP_I18nly\Plurals\PluralFormsRegistry::get_form_markers_for_locale( $locale );
			$form_tooltips = \WP_I18nly\Plurals\PluralFormsRegistry::get_form_tooltips_for_locale( $locale );

			foreach ( $entries as &$entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}

				$entry['forms']         = $forms;
				$entry['form_labels']   = $form_labels;
				$entry['form_markers']  = $form_markers;
				$entry['form_tooltips'] = $form_tooltips;
			}
			unset( $entry );

			return $entries;
		}

		if ( ! method_exists( $repository, 'list_source_entries_by_plugin_slug' ) ) {
			return array();
		}

		return $repository->list_source_entries_by_plugin_slug( $source_slug );
	}

	/**
	 * Infers text domain from source slug.
	 *
	 * @param string $source_slug Source slug.
	 * @return string
	 */
	private function infer_text_domain_from_source_slug( $source_slug ) {
		return $this->get_plugin_metadata_provider()->infer_text_domain( $source_slug );
	}

	/**
	 * Builds POT header overrides from source plugin metadata.
	 *
	 * @param string $source_slug Source slug.
	 * @param string $text_domain Text domain.
	 * @return array<string, string>
	 */
	private function build_pot_header_overrides_from_source_slug( $source_slug, $text_domain ) {
		return $this->get_plugin_metadata_provider()->build_pot_header_overrides( $source_slug, $text_domain );
	}

	/**
	 * Returns translation id when current screen is translation edit.
	 *
	 * @return int
	 */
	private function get_current_edit_translation_id() {
		return $this->get_current_edit_translation_resolver()->resolve(
			function ( $key ) {
				return $this->get_query_parameter( $key );
			},
			function ( $translation_id ) {
				return $this->get_translation( $translation_id );
			}
		);
	}

	/**
	 * Returns one GET query parameter.
	 *
	 * @param string $key Query parameter key.
	 * @return string
	 */
	protected function get_query_parameter( $key ) {
		$value = filter_input( INPUT_GET, (string) $key, FILTER_UNSAFE_RAW );

		if ( is_string( $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Returns translation repository.
	 *
	 * @return \WP_I18nly\Support\TranslationRepository
	 */
	protected function get_translation_repository() {
		return new TranslationRepository();
	}

	/**
	 * Returns plugin metadata provider.
	 *
	 * @return \WP_I18nly\Support\PluginMetadataProvider
	 */
	protected function get_plugin_metadata_provider() {
		return new PluginMetadataProvider();
	}

	/**
	 * Returns language options provider.
	 *
	 * @return \WP_I18nly\Support\LanguageOptionsProvider
	 */
	protected function get_language_options_provider() {
		return new LanguageOptionsProvider();
	}

	/**
	 * Returns translation entries persister.
	 *
	 * @return \WP_I18nly\Support\TranslationEntriesPersister
	 */
	protected function get_translation_entries_persister() {
		return new TranslationEntriesPersister();
	}

	/**
	 * Returns admin registration service.
	 *
	 * @return \WP_I18nly\Support\AdminRegistration
	 */
	protected function get_admin_registration() {
		return new AdminRegistration();
	}

	/**
	 * Returns current edit translation resolver.
	 *
	 * @return \WP_I18nly\Support\CurrentEditTranslationResolver
	 */
	protected function get_current_edit_translation_resolver() {
		return new CurrentEditTranslationResolver();
	}
}
