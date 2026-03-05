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

		wp_nonce_field( 'i18nly_translation_meta_box', 'i18nly_translation_meta_box_nonce' );
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="i18nly-plugin-selector"><?php echo esc_html__( 'Plugin', 'i18nly' ); ?></label>
					</th>
					<td>
						<select id="i18nly-plugin-selector" name="i18nly_plugin_selector" required<?php echo disabled( $is_locked, true, false ); ?>>
							<option value=""><?php echo esc_html__( 'Select a plugin', 'i18nly' ); ?></option>
							<?php foreach ( $plugin_options as $plugin_file => $plugin_name ) : ?>
								<option value="<?php echo esc_attr( $plugin_file ); ?>"<?php selected( $selected_source, (string) $plugin_file ); ?>><?php echo esc_html( $plugin_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="i18nly-target-language-selector"><?php echo esc_html__( 'Target language', 'i18nly' ); ?></label>
					</th>
					<td>
						<select id="i18nly-target-language-selector" name="i18nly_target_language_selector" required<?php echo disabled( $is_locked, true, false ); ?>>
							<option value=""><?php echo esc_html__( 'Select a target language', 'i18nly' ); ?></option>
							<?php foreach ( $target_languages as $target_language ) : ?>
								<option value="<?php echo esc_attr( $target_language['value'] ); ?>"<?php echo disabled( true, (bool) $target_language['disabled'], false ); ?><?php selected( $selected_language, (string) $target_language['value'] ); ?>><?php echo esc_html( $target_language['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</tbody>
		</table>
		<?php if ( $is_locked ) : ?>
			<p class="description"><?php echo esc_html__( 'Plugin and target language are locked after creation.', 'i18nly' ); ?></p>
		<?php endif; ?>

		<h3><?php echo esc_html__( 'Translation entries', 'i18nly' ); ?></h3>
		<div id="i18nly-source-entries-table">
			<p><?php echo esc_html__( 'Loading translation entries…', 'i18nly' ); ?></p>
		</div>
		<?php
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
		unset( $update );

		if ( ! isset( $post->post_type ) || self::POST_TYPE !== (string) $post->post_type ) {
			return;
		}

		if ( ! isset( $_POST['i18nly_translation_meta_box_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['i18nly_translation_meta_box_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'i18nly_translation_meta_box' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', (int) $post_id ) ) {
			return;
		}

		$existing_source   = (string) get_post_meta( (int) $post_id, self::META_SOURCE_SLUG, true );
		$existing_language = (string) get_post_meta( (int) $post_id, self::META_TARGET_LANGUAGE, true );
		$is_locked         = '' !== $existing_source || '' !== $existing_language;

		$source_slug = $existing_source;
		if ( ! $is_locked && isset( $_POST['i18nly_plugin_selector'] ) ) {
			$source_slug = sanitize_text_field( wp_unslash( $_POST['i18nly_plugin_selector'] ) );
		}

		$target_language = $existing_language;
		if ( ! $is_locked && isset( $_POST['i18nly_target_language_selector'] ) ) {
			$target_language = sanitize_text_field( wp_unslash( $_POST['i18nly_target_language_selector'] ) );
		}

		update_post_meta( (int) $post_id, self::META_SOURCE_SLUG, $source_slug );
		update_post_meta( (int) $post_id, self::META_TARGET_LANGUAGE, $target_language );

		if ( '' !== $source_slug ) {
			$entries_payload = array();
			$payload_json    = filter_input( INPUT_POST, 'i18nly_translation_entries_payload', FILTER_UNSAFE_RAW );

			if ( ! is_string( $payload_json ) && isset( $_POST['i18nly_translation_entries_payload'] ) ) {
				$payload_json = sanitize_textarea_field( wp_unslash( $_POST['i18nly_translation_entries_payload'] ) );
			}

			if ( is_string( $payload_json ) && '' !== $payload_json ) {
				$decoded_payload = json_decode( wp_unslash( $payload_json ), true );

				if ( is_array( $decoded_payload ) ) {
					$entries_payload = $decoded_payload;
				}
			}

			if ( ! empty( $entries_payload ) ) {
				$this->persist_translation_entries( (int) $post_id, $source_slug, I18nly_Admin_Page_Helper::normalize_translation_entries_payload( $entries_payload ) );
			}
		}

		$current_title = isset( $post->post_title ) ? (string) $post->post_title : '';
		if ( '' !== trim( $current_title ) || '' === $source_slug || '' === $target_language ) {
			return;
		}

		remove_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_translation_meta_box' ), 10 );

		wp_update_post(
			array(
				'ID'         => (int) $post_id,
				'post_title' => $source_slug . ' → ' . $target_language,
			)
		);

		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_translation_meta_box' ), 10, 3 );
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
		I18nly_Admin_Page_Helper::register_post_type( self::POST_TYPE );
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

		$translation = $this->get_translation( $translation_id );
		if ( null === $translation || empty( $translation['source_slug'] ) ) {
			wp_send_json_error( array( 'message' => 'Translation source is missing.' ), 400 );
			return;
		}

		$source_slug      = (string) $translation['source_slug'];
		$source_extractor = new I18nly_Pot_Source_Entry_Extractor();
		$entries          = $source_extractor->extract_from_source_slug( $source_slug );
		$text_domain      = $this->infer_text_domain_from_source_slug( $source_slug );
		$header_overrides = $this->build_pot_header_overrides_from_source_slug( $source_slug, $text_domain );

		$pot_workspace = new I18nly_Pot_Workspace_Service();
		$pot_importer  = new I18nly_Pot_Source_Importer();

		try {
			$pot_file_path  = $pot_workspace->generate_temporary_pot( $translation_id, $text_domain, $entries, $header_overrides );
			$import_summary = $pot_importer->import_file( $source_slug, $pot_file_path );
		} catch ( RuntimeException $exception ) {
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
	public function ajax_get_translation_entries_table() {
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

		$translation = $this->get_translation( $translation_id );
		if ( null === $translation || empty( $translation['source_slug'] ) ) {
			wp_send_json_error( array( 'message' => 'Translation source is missing.' ), 400 );
			return;
		}

		$source_slug    = (string) $translation['source_slug'];
		$source_entries = $this->get_translation_source_entries( $translation_id, $source_slug );

		wp_send_json_success(
			array(
				'translation_id' => $translation_id,
				'entries_count'  => count( $source_entries ),
				'html'           => $this->render_translation_source_entries_table_markup( $source_entries ),
			)
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

		$repository = new I18nly_Source_Wpdb_Repository( $schema_manager );
		$now_gmt    = gmdate( 'Y-m-d H:i:s' );

		if ( method_exists( $repository, 'ensure_translated_entries_for_translation' ) ) {
			$repository->ensure_translated_entries_for_translation( (int) $translation_id, (string) $source_slug, $now_gmt );
		}

		if ( method_exists( $repository, 'list_translation_entries_by_plugin_slug' ) ) {
			return $repository->list_translation_entries_by_plugin_slug( (int) $translation_id, (string) $source_slug );
		}

		if ( ! method_exists( $repository, 'list_source_entries_by_plugin_slug' ) ) {
			return array();
		}

		return $repository->list_source_entries_by_plugin_slug( $source_slug );
	}

	/**
	 * Renders source entries table markup.
	 *
	 * @param array<int, array<string, mixed>> $source_entries Source entries rows.
	 * @return string
	 */
	private function render_translation_source_entries_table_markup( array $source_entries ) {
		ob_start();
		$this->render_translation_source_entries_table( $source_entries );

		$html = ob_get_clean();

		if ( ! is_string( $html ) ) {
			return '';
		}

		return $html;
	}

	/**
	 * Outputs source entries table markup.
	 *
	 * @param array<int, array<string, mixed>> $source_entries Source entries rows.
	 * @return void
	 */
	private function render_translation_source_entries_table( array $source_entries ) {
		$list_table = new I18nly_Translation_Entries_List_Table( $source_entries );
		$list_table->prepare_items();
		$list_table->display();
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
