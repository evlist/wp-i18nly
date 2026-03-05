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
	 * The top-level menu slug.
	 */
	private const MENU_SLUG = 'i18nly-translations';

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
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		$options = array();

		foreach ( $plugins as $plugin_file => $plugin_data ) {
			if ( empty( $plugin_data['Name'] ) ) {
				continue;
			}

			$options[ $plugin_file ] = (string) $plugin_data['Name'];
		}

		asort( $options );

		return $options;
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
		$source_locale     = self::SOURCE_LOCALE;
		$installed_locales = array();
		$all_translations  = array();
		$preferred_options = array();
		$remaining_options = array();
		$ordered_options   = array();

		if ( ! function_exists( 'wp_get_available_translations' ) ) {
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		}

		if ( function_exists( 'get_available_languages' ) ) {
			$installed_locales = get_available_languages();
		}

		if ( function_exists( 'wp_get_available_translations' ) ) {
			$all_translations = wp_get_available_translations();
		}

		foreach ( $installed_locales as $locale ) {
			if ( $source_locale === $locale ) {
				continue;
			}

			$preferred_options[ $locale ] = $this->get_locale_label( $locale, $all_translations );
		}

		asort( $preferred_options );

		foreach ( $all_translations as $locale => $translation ) {
			if ( $source_locale === $locale || isset( $preferred_options[ $locale ] ) ) {
				continue;
			}

			unset( $translation );
			$remaining_options[ $locale ] = $this->get_locale_label( $locale, $all_translations );
		}

		asort( $remaining_options );

		foreach ( $preferred_options as $locale => $label ) {
			$ordered_options[] = array(
				'value'    => (string) $locale,
				'label'    => (string) $label,
				'disabled' => false,
			);
		}

		if ( ! empty( $preferred_options ) && ! empty( $remaining_options ) ) {
			$ordered_options[] = array(
				'value'    => '',
				'label'    => '──────────',
				'disabled' => true,
			);
		}

		foreach ( $remaining_options as $locale => $label ) {
			$ordered_options[] = array(
				'value'    => (string) $locale,
				'label'    => (string) $label,
				'disabled' => false,
			);
		}

		return $ordered_options;
	}

	/**
	 * Returns a human-readable locale label.
	 *
	 * @param string                              $locale Locale code.
	 * @param array<string, array<string, mixed>> $all_translations Available translations.
	 * @return string
	 */
	private function get_locale_label( $locale, array $all_translations ) {
		if ( isset( $all_translations[ $locale ]['native_name'] ) && '' !== (string) $all_translations[ $locale ]['native_name'] ) {
			return (string) $all_translations[ $locale ]['native_name'];
		}

		return (string) $locale;
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
		$messages[ self::POST_TYPE ] = array(
			0  => '',
			1  => __( 'Translation updated.', 'i18nly' ),
			2  => __( 'Custom field updated.', 'i18nly' ),
			3  => __( 'Custom field deleted.', 'i18nly' ),
			4  => __( 'Translation updated.', 'i18nly' ),
			5  => __( 'Translation restored to revision.', 'i18nly' ),
			6  => __( 'Translation published.', 'i18nly' ),
			7  => __( 'Translation saved.', 'i18nly' ),
			8  => __( 'Translation submitted.', 'i18nly' ),
			9  => __( 'Translation scheduled.', 'i18nly' ),
			10 => __( 'Translation draft updated.', 'i18nly' ),
		);

		return $messages;
	}

	/**
	 * Replaces default bulk update messages for translation posts.
	 *
	 * @param array<string, array<string, string>> $bulk_messages Current bulk messages.
	 * @param array<string, int>                   $bulk_counts Item counts.
	 * @return array<string, array<string, string>>
	 */
	public function filter_translation_bulk_updated_messages( array $bulk_messages, array $bulk_counts ) {
		$updated_count   = isset( $bulk_counts['updated'] ) ? (int) $bulk_counts['updated'] : 0;
		$locked_count    = isset( $bulk_counts['locked'] ) ? (int) $bulk_counts['locked'] : 0;
		$deleted_count   = isset( $bulk_counts['deleted'] ) ? (int) $bulk_counts['deleted'] : 0;
		$trashed_count   = isset( $bulk_counts['trashed'] ) ? (int) $bulk_counts['trashed'] : 0;
		$untrashed_count = isset( $bulk_counts['untrashed'] ) ? (int) $bulk_counts['untrashed'] : 0;

		$bulk_messages[ self::POST_TYPE ] = array(
			/* translators: %s: Number of translations. */
			'updated'   => sprintf( _n( '%s translation updated.', '%s translations updated.', $updated_count, 'i18nly' ), number_format_i18n( $updated_count ) ),
			/* translators: %s: Number of translations. */
			'locked'    => sprintf( _n( '%s translation not updated, somebody is editing it.', '%s translations not updated, somebody is editing them.', $locked_count, 'i18nly' ), number_format_i18n( $locked_count ) ),
			/* translators: %s: Number of translations. */
			'deleted'   => sprintf( _n( '%s translation permanently deleted.', '%s translations permanently deleted.', $deleted_count, 'i18nly' ), number_format_i18n( $deleted_count ) ),
			/* translators: %s: Number of translations. */
			'trashed'   => sprintf( _n( '%s translation moved to the Trash.', '%s translations moved to the Trash.', $trashed_count, 'i18nly' ), number_format_i18n( $trashed_count ) ),
			/* translators: %s: Number of translations. */
			'untrashed' => sprintf( _n( '%s translation restored from the Trash.', '%s translations restored from the Trash.', $untrashed_count, 'i18nly' ), number_format_i18n( $untrashed_count ) ),
		);

		return $bulk_messages;
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
	 * Filters translation list table columns.
	 *
	 * @param array<string, string> $columns Current columns.
	 * @return array<string, string>
	 */
	public function filter_translation_list_columns( array $columns ) {
		$filtered_columns = array();

		foreach ( $columns as $column_key => $column_label ) {
			$filtered_columns[ $column_key ] = $column_label;

			if ( 'title' !== $column_key ) {
				continue;
			}

			$filtered_columns['source_slug']     = __( 'Source', 'i18nly' );
			$filtered_columns['target_language'] = __( 'Target language', 'i18nly' );
		}

		if ( ! isset( $filtered_columns['source_slug'] ) ) {
			$filtered_columns['source_slug'] = __( 'Source', 'i18nly' );
		}

		if ( ! isset( $filtered_columns['target_language'] ) ) {
			$filtered_columns['target_language'] = __( 'Target language', 'i18nly' );
		}

		return $filtered_columns;
	}

	/**
	 * Renders custom column content for translation rows.
	 *
	 * @param string $column_name Column key.
	 * @param int    $post_id Translation post ID.
	 * @return void
	 */
	public function render_translation_list_column( $column_name, $post_id ) {
		if ( 'source_slug' === $column_name ) {
			echo esc_html( (string) get_post_meta( (int) $post_id, self::META_SOURCE_SLUG, true ) );

			return;
		}

		if ( 'target_language' === $column_name ) {
			echo esc_html( (string) get_post_meta( (int) $post_id, self::META_TARGET_LANGUAGE, true ) );
		}
	}

	/**
	 * Filters sortable columns for translation list table.
	 *
	 * @param array<string, string> $columns Current sortable columns.
	 * @return array<string, string>
	 */
	public function filter_translation_sortable_columns( array $columns ) {
		$columns['source_slug']     = 'source_slug';
		$columns['target_language'] = 'target_language';

		return $columns;
	}

	/**
	 * Applies meta sorting for translation custom columns.
	 *
	 * @param object $query Current query object.
	 * @return void
	 */
	public function apply_translation_sorting( $query ) {
		if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) || ! method_exists( $query, 'set' ) ) {
			return;
		}

		if ( method_exists( $query, 'is_main_query' ) && ! $query->is_main_query() ) {
			return;
		}

		$post_type = (string) $query->get( 'post_type' );
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		$orderby = (string) $query->get( 'orderby' );

		if ( 'source_slug' === $orderby ) {
			$query->set( 'meta_key', self::META_SOURCE_SLUG );
			$query->set( 'orderby', 'meta_value' );
		}

		if ( 'target_language' === $orderby ) {
			$query->set( 'meta_key', self::META_TARGET_LANGUAGE );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	/**
	 * Registers the translation custom post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'        => __( 'Translations', 'i18nly' ),
				'labels'       => array(
					'name'                  => __( 'Translations', 'i18nly' ),
					'singular_name'         => __( 'Translation', 'i18nly' ),
					'add_new'               => __( 'Add translation', 'i18nly' ),
					'add_new_item'          => __( 'Add translation', 'i18nly' ),
					'edit_item'             => __( 'Edit translation', 'i18nly' ),
					'new_item'              => __( 'Translation', 'i18nly' ),
					'view_item'             => __( 'View translation', 'i18nly' ),
					'view_items'            => __( 'View translations', 'i18nly' ),
					'search_items'          => __( 'Search translations', 'i18nly' ),
					'not_found'             => __( 'No translations found.', 'i18nly' ),
					'not_found_in_trash'    => __( 'No translations found in Trash.', 'i18nly' ),
					'all_items'             => __( 'All translations', 'i18nly' ),
					'archives'              => __( 'Translation archives', 'i18nly' ),
					'attributes'            => __( 'Translation attributes', 'i18nly' ),
					'insert_into_item'      => __( 'Insert into translation', 'i18nly' ),
					'uploaded_to_this_item' => __( 'Uploaded to this translation', 'i18nly' ),
					'filter_items_list'     => __( 'Filter translations list', 'i18nly' ),
					'items_list_navigation' => __( 'Translations list navigation', 'i18nly' ),
					'items_list'            => __( 'Translations list', 'i18nly' ),
					'item_published'        => __( 'Translation published.', 'i18nly' ),
					'item_updated'          => __( 'Translation updated.', 'i18nly' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title' ),
				'map_meta_cap' => true,
			)
		);
	}

	/**
	 * Registers the admin menu entries.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Translations', 'i18nly' ),
			__( 'Translations', 'i18nly' ),
			'manage_options',
			self::LIST_SCREEN_SLUG,
			'',
			'dashicons-translation',
			58
		);

		add_submenu_page(
			self::LIST_SCREEN_SLUG,
			__( 'All translations', 'i18nly' ),
			__( 'All translations', 'i18nly' ),
			'manage_options',
			self::LIST_SCREEN_SLUG
		);

		add_submenu_page(
			self::LIST_SCREEN_SLUG,
			__( 'Add translation', 'i18nly' ),
			__( 'Add translation', 'i18nly' ),
			'manage_options',
			self::NEW_SCREEN_SLUG
		);
	}

	/**
	 * Filters row actions to remove Quick Edit for translation posts.
	 *
	 * @param array<string, string> $actions Current row actions.
	 * @param object                $post Current post object.
	 * @return array<string, string>
	 */
	public function filter_translation_row_actions( array $actions, $post ) {
		if ( ! isset( $post->post_type ) || self::POST_TYPE !== (string) $post->post_type ) {
			return $actions;
		}

		unset( $actions['inline hide-if-no-js'] );

		return $actions;
	}

	/**
	 * Creates one translation row.
	 *
	 * @param string $source_slug Source slug identifier.
	 * @param string $target_language Target language code.
	 * @return int
	 */
	private function create_translation( $source_slug, $target_language ) {
		$created_at_local = current_time( 'mysql' );
		$created_at_gmt   = current_time( 'mysql', true );

		$translation_post_id = wp_insert_post(
			array(
				'post_type'     => self::POST_TYPE,
				'post_status'   => 'draft',
				'post_title'    => $source_slug . ' → ' . $target_language,
				'post_date'     => $created_at_local,
				'post_date_gmt' => $created_at_gmt,
			),
			true
		);

		if ( is_wp_error( $translation_post_id ) || $translation_post_id <= 0 ) {
			return 0;
		}

		update_post_meta( $translation_post_id, self::META_SOURCE_SLUG, $source_slug );
		update_post_meta( $translation_post_id, self::META_TARGET_LANGUAGE, $target_language );

		return (int) $translation_post_id;
	}

	/**
	 * Returns one translation row by ID.
	 *
	 * @param int $translation_id Translation ID.
	 * @return array<string, mixed>|null
	 */
	private function get_translation( $translation_id ) {
		$translation_post = get_post( (int) $translation_id );
		if ( ! $translation_post || self::POST_TYPE !== $translation_post->post_type ) {
			return null;
		}

		return array(
			'id'              => (int) $translation_post->ID,
			'source_slug'     => (string) get_post_meta( (int) $translation_post->ID, self::META_SOURCE_SLUG, true ),
			'target_language' => (string) get_post_meta( (int) $translation_post->ID, self::META_TARGET_LANGUAGE, true ),
			'created_at'      => $this->get_post_created_at( $translation_post ),
		);
	}

	/**
	 * Returns a stable creation date for one post.
	 *
	 * Draft posts can keep `post_date_gmt` to zero in WordPress internals,
	 * so we fall back to `post_date` when needed.
	 *
	 * @param object $translation_post Translation post object.
	 * @return string
	 */
	private function get_post_created_at( $translation_post ) {
		$post_date_gmt = isset( $translation_post->post_date_gmt ) ? (string) $translation_post->post_date_gmt : '';
		if ( '' !== $post_date_gmt && '0000-00-00 00:00:00' !== $post_date_gmt ) {
			return $post_date_gmt;
		}

		return isset( $translation_post->post_date ) ? (string) $translation_post->post_date : '';
	}

	/**
	 * Returns the standard WordPress edit post URL for one translation.
	 *
	 * @param int $translation_id Translation ID.
	 * @return string
	 */
	private function get_standard_edit_translation_url( $translation_id ) {
		$edit_url = add_query_arg( 'post', (string) $translation_id, admin_url( 'post.php' ) );

		return add_query_arg( 'action', 'edit', $edit_url );
	}

	/**
	 * Returns the native WordPress list page URL for translations.
	 *
	 * @return string
	 */
	private function get_native_list_url() {
		return admin_url( self::LIST_SCREEN_SLUG );
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
		if ( defined( 'I18NLY_PLUGIN_FILE' ) && function_exists( 'plugin_dir_url' ) ) {
			return plugin_dir_url( I18NLY_PLUGIN_FILE ) . 'assets/js/translation-edit.js';
		}

		return 'assets/js/translation-edit.js';
	}

	/**
	 * Builds translation edit script configuration.
	 *
	 * @param int $translation_id Translation ID.
	 * @return array<string, mixed>
	 */
	private function build_translation_edit_script_config( $translation_id ) {
		return array(
			'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
			'translationId'     => (int) $translation_id,
			'generateAction'    => 'i18nly_generate_translation_pot',
			'generateNonce'     => wp_create_nonce( 'i18nly_generate_translation_pot_' . (int) $translation_id ),
			'refreshAction'     => 'i18nly_get_translation_entries_table',
			'refreshNonce'      => wp_create_nonce( 'i18nly_get_translation_entries_table_' . (int) $translation_id ),
			'tableContainerId'  => 'i18nly-source-entries-table',
			'contentTypeHeader' => 'application/x-www-form-urlencoded; charset=UTF-8',
		);
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
		$parts = explode( '/', trim( (string) $source_slug, '/\\' ) );

		if ( empty( $parts[0] ) ) {
			return 'i18nly';
		}

		return sanitize_text_field( (string) $parts[0] );
	}

	/**
	 * Builds POT header overrides from source plugin metadata.
	 *
	 * @param string $source_slug Source slug.
	 * @param string $text_domain Text domain.
	 * @return array<string, string>
	 */
	private function build_pot_header_overrides_from_source_slug( $source_slug, $text_domain ) {
		$plugin_data = $this->get_source_plugin_data( $source_slug );

		$project_id_version = $text_domain;
		if ( ! empty( $plugin_data['Name'] ) && ! empty( $plugin_data['Version'] ) ) {
			$project_id_version = sanitize_text_field( $plugin_data['Name'] . ' ' . $plugin_data['Version'] );
		} elseif ( ! empty( $plugin_data['Version'] ) ) {
			$project_id_version = sanitize_text_field( $text_domain . ' ' . $plugin_data['Version'] );
		}

		$bugs_url = '';
		if ( ! empty( $plugin_data['PluginURI'] ) ) {
			$bugs_url = esc_url_raw( (string) $plugin_data['PluginURI'] );
		} elseif ( ! empty( $plugin_data['AuthorURI'] ) ) {
			$bugs_url = esc_url_raw( (string) $plugin_data['AuthorURI'] );
		}

		return array(
			'Project-Id-Version'   => (string) $project_id_version,
			'Report-Msgid-Bugs-To' => (string) $bugs_url,
		);
	}

	/**
	 * Returns source plugin metadata from installed plugins list.
	 *
	 * @param string $source_slug Source slug.
	 * @return array<string, string>
	 */
	private function get_source_plugin_data( $source_slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

		if ( isset( $plugins[ $source_slug ] ) && is_array( $plugins[ $source_slug ] ) ) {
			return array_map( 'strval', $plugins[ $source_slug ] );
		}

		return array();
	}

	/**
	 * Returns translation id when current screen is translation edit.
	 *
	 * @return int
	 */
	private function get_current_edit_translation_id() {
		$action_raw = $this->get_query_parameter( 'action' );
		$post_raw   = $this->get_query_parameter( 'post' );

		if ( '' === $action_raw || '' === $post_raw ) {
			return 0;
		}

		$action = sanitize_text_field( wp_unslash( $action_raw ) );
		if ( 'edit' !== $action ) {
			return 0;
		}

		$translation_id = absint( wp_unslash( $post_raw ) );
		if ( $translation_id <= 0 ) {
			return 0;
		}

		$translation = $this->get_translation( $translation_id );

		return null === $translation ? 0 : $translation_id;
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
