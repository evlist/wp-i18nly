<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * I18nly admin page class.
 *
 * @package I18nly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the I18nly admin pages.
 */
class I18nly_Admin_Page {
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
	private function get_plugin_options() {
		return I18nly_Admin_Page_Helper::get_plugin_options();
	}

	/**
	 * Returns language options for target language selector.
	 *
	 * Installed languages are listed first, then a separator, then all
	 * remaining languages.
	 *
	 * @return array<int, array{value: string, label: string, disabled: bool}>
	 */
	private function get_target_language_options() {
		return I18nly_Admin_Page_Helper::get_target_language_options( self::SOURCE_LOCALE );
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
		return I18nly_Admin_Page_Helper::filter_translation_post_updated_messages( $messages, self::POST_TYPE );
	}

	/**
	 * Replaces default bulk update messages for translation posts.
	 *
	 * @param array<string, array<string, string>> $bulk_messages Current bulk messages.
	 * @param array<string, int>                   $bulk_counts Item counts.
	 * @return array<string, array<string, string>>
	 */
	public function filter_translation_bulk_updated_messages( array $bulk_messages, array $bulk_counts ) {
		return I18nly_Admin_Page_Helper::filter_translation_bulk_updated_messages( $bulk_messages, $bulk_counts, self::POST_TYPE );
	}

	/**
	 * Registers translation meta box on native editor screens.
	 *
	 * @return void
	 */
	public function register_translation_meta_box() {
		add_meta_box(
			'i18nly-translation-settings',
			__( 'Translation', 'i18nly' ),
			array( $this, 'render_translation_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Renders translation meta box fields.
	 *
	 * @param object $post Current post object.
	 * @return void
	 */
	public function render_translation_meta_box( $post ) {
		$plugin_options    = $this->get_plugin_options();
		$target_languages  = $this->get_target_language_options();
		$selected_source   = (string) get_post_meta( (int) $post->ID, self::META_SOURCE_SLUG, true );
		$selected_language = (string) get_post_meta( (int) $post->ID, self::META_TARGET_LANGUAGE, true );
		$is_locked         = '' !== $selected_source || '' !== $selected_language;

		$this->get_meta_box_renderer()->render_translation_meta_box(
			$plugin_options,
			$target_languages,
			$selected_source,
			$selected_language,
			$is_locked
		);
	}

	/**
	 * Returns translation meta box renderer.
	 *
	 * @return I18nly_Translation_Meta_Box_Renderer_Interface
	 */
	protected function get_meta_box_renderer() {
		return new I18nly_Translation_Meta_Box_Renderer();
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
		$this->get_save_handler()->handle_save( (int) $post_id, $post, (bool) $update );
	}

	/**
	 * Returns save handler.
	 *
	 * @return I18nly_Translation_Save_Handler
	 */
	protected function get_save_handler() {
		return new I18nly_Translation_Save_Handler(
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
		I18nly_Admin_Page_Helper::persist_translation_entries( (int) $translation_id, (string) $source_slug, $entries_payload );
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

		$open_url   = I18nly_Admin_Page_Helper::get_standard_edit_translation_url( (int) $existing_translation_id );
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
		return I18nly_Admin_Page_Helper::filter_translation_list_columns( $columns );
	}

	/**
	 * Renders custom column content for translation rows.
	 *
	 * @param string $column_name Column key.
	 * @param int    $post_id Translation post ID.
	 * @return void
	 */
	public function render_translation_list_column( $column_name, $post_id ) {
		echo esc_html( I18nly_Admin_Page_Helper::get_translation_list_column_value( $column_name, $post_id, self::META_SOURCE_SLUG, self::META_TARGET_LANGUAGE ) );
	}

	/**
	 * Filters sortable columns for translation list table.
	 *
	 * @param array<string, string> $columns Current sortable columns.
	 * @return array<string, string>
	 */
	public function filter_translation_sortable_columns( array $columns ) {
		return I18nly_Admin_Page_Helper::filter_translation_sortable_columns( $columns );
	}

	/**
	 * Applies meta sorting for translation custom columns.
	 *
	 * @param object $query Current query object.
	 * @return void
	 */
	public function apply_translation_sorting( $query ) {
		I18nly_Admin_Page_Helper::apply_translation_sorting( $query, self::POST_TYPE, self::META_SOURCE_SLUG, self::META_TARGET_LANGUAGE );
	}

	/**
	 * Registers the translation custom post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		I18nly_Admin_Page_Helper::register_post_type();
	}

	/**
	 * Registers the admin menu entries.
	 *
	 * @return void
	 */
	public function register_menu() {
		I18nly_Admin_Page_Helper::register_menu( self::LIST_SCREEN_SLUG, self::NEW_SCREEN_SLUG );
	}

	/**
	 * Filters row actions to remove Quick Edit for translation posts.
	 *
	 * @param array<string, string> $actions Current row actions.
	 * @param object                $post Current post object.
	 * @return array<string, string>
	 */
	public function filter_translation_row_actions( array $actions, $post ) {
		return I18nly_Admin_Page_Helper::filter_translation_row_actions( $actions, $post, self::POST_TYPE );
	}

	/**
	 * Creates one translation row.
	 *
	 * @param string $source_slug Source slug identifier.
	 * @param string $target_language Target language code.
	 * @return int
	 */
	private function create_translation( $source_slug, $target_language ) {
		return I18nly_Admin_Page_Helper::create_translation(
			$source_slug,
			$target_language,
			self::POST_TYPE,
			self::META_SOURCE_SLUG,
			self::META_TARGET_LANGUAGE
		);
	}

	/**
	 * Returns one translation row by ID.
	 *
	 * @param int $translation_id Translation ID.
	 * @return array<string, mixed>|null
	 */
	private function get_translation( $translation_id ) {
		return I18nly_Admin_Page_Helper::get_translation(
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
		return I18nly_Admin_Page_Helper::get_native_list_url( self::LIST_SCREEN_SLUG );
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
		if ( '' !== (string) $hook_suffix && 'post.php' !== (string) $hook_suffix ) {
			return;
		}

		$translation_id = $this->get_current_edit_translation_id();

		if ( $translation_id <= 0 ) {
			return;
		}

		$script_handle = 'i18nly-translation-edit';
		$style_handle  = 'i18nly-translation-edit-style';

		wp_enqueue_style(
			$style_handle,
			$this->get_translation_edit_style_url(),
			array(),
			defined( 'I18NLY_VERSION' ) ? I18NLY_VERSION : '0.1.0'
		);

		wp_enqueue_script(
			$script_handle,
			$this->get_translation_edit_script_url(),
			array(),
			defined( 'I18NLY_VERSION' ) ? I18NLY_VERSION : '0.1.0',
			true
		);

		$config_json = wp_json_encode( $this->build_translation_edit_script_config( $translation_id ) );

		if ( false === $config_json ) {
			return;
		}

		wp_add_inline_script(
			$script_handle,
			'window.i18nlyTranslationEditConfig = ' . $config_json . ';',
			'before'
		);
	}

	/**
	 * Returns translation edit script URL.
	 *
	 * @return string
	 */
	private function get_translation_edit_script_url() {
		return I18nly_Admin_Page_Helper::get_translation_edit_script_url();
	}

	/**
	 * Returns translation edit style URL.
	 *
	 * @return string
	 */
	private function get_translation_edit_style_url() {
		return I18nly_Admin_Page_Helper::get_translation_edit_style_url();
	}

	/**
	 * Builds translation edit script configuration.
	 *
	 * @param int $translation_id Translation ID.
	 * @return array<string, mixed>
	 */
	private function build_translation_edit_script_config( $translation_id ) {
		return I18nly_Admin_Page_Helper::build_translation_edit_script_config( $translation_id );
	}

	/**
	 * Handles AJAX request to generate temporary POT for one translation.
	 *
	 * @return void
	 */
	public function ajax_generate_translation_pot() {
		$this->get_ajax_controller()->handle_generate_translation_pot();
	}

	/**
	 * Handles AJAX request to fetch source entries table HTML.
	 *
	 * @return void
	 */
	public function ajax_get_translation_entries_table() {
		$this->get_ajax_controller()->handle_get_translation_entries_table();
	}

	/**
	 * Returns translation AJAX controller.
	 *
	 * @return I18nly_Translation_Ajax_Controller_Interface
	 */
	protected function get_ajax_controller() {
		return new I18nly_Translation_Ajax_Controller(
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
		$schema_manager = new I18nly_Source_Schema_Manager();
		$schema_manager->maybe_upgrade();

		$repository  = new I18nly_Source_Wpdb_Repository( $schema_manager );
		$now_gmt     = gmdate( 'Y-m-d H:i:s' );
		$translation = $this->get_translation( $translation_id );
		$locale      = is_array( $translation ) && isset( $translation['target_language'] )
			? (string) $translation['target_language']
			: '';
		$form_count  = I18nly_Admin_Page_Helper::get_plural_forms_count_for_locale( $locale );

		if ( method_exists( $repository, 'ensure_translated_entries_for_translation' ) ) {
			$repository->ensure_translated_entries_for_translation( (int) $translation_id, (string) $source_slug, $now_gmt, $form_count );
		}

		if ( method_exists( $repository, 'list_translation_entries_by_plugin_slug' ) ) {
			$entries       = $repository->list_translation_entries_by_plugin_slug( (int) $translation_id, (string) $source_slug, 500, $form_count );
			$form_labels   = I18nly_Plural_Forms_Registry::get_form_labels_for_locale( $locale );
			$form_markers  = I18nly_Plural_Forms_Registry::get_form_markers_for_locale( $locale );
			$form_tooltips = I18nly_Plural_Forms_Registry::get_form_tooltips_for_locale( $locale );

			foreach ( $entries as &$entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}

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
		return I18nly_Admin_Page_Helper::infer_text_domain_from_source_slug( $source_slug );
	}

	/**
	 * Builds POT header overrides from source plugin metadata.
	 *
	 * @param string $source_slug Source slug.
	 * @param string $text_domain Text domain.
	 * @return array<string, string>
	 */
	private function build_pot_header_overrides_from_source_slug( $source_slug, $text_domain ) {
		return I18nly_Admin_Page_Helper::build_pot_header_overrides_from_source_slug( $source_slug, $text_domain );
	}

	/**
	 * Returns translation id when current screen is translation edit.
	 *
	 * @return int
	 */
	private function get_current_edit_translation_id() {
		return I18nly_Admin_Page_Helper::get_current_edit_translation_id(
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
}
