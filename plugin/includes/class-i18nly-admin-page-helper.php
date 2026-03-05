<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Admin page helper methods.
 *
 * @package I18nly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides extracted helper methods for admin page logic.
 */
class I18nly_Admin_Page_Helper {
	/**
	 * Returns installed plugins as options for the selector.
	 *
	 * @return array<string, string>
	 */
	public static function get_plugin_options() {
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
	 * @param string $source_locale Source locale.
	 * @return array<int, array{value: string, label: string, disabled: bool}>
	 */
	public static function get_target_language_options( $source_locale ) {
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

			$preferred_options[ $locale ] = self::get_locale_label( $locale, $all_translations );
		}

		asort( $preferred_options );

		foreach ( $all_translations as $locale => $translation ) {
			if ( $source_locale === $locale || isset( $preferred_options[ $locale ] ) ) {
				continue;
			}

			unset( $translation );
			$remaining_options[ $locale ] = self::get_locale_label( $locale, $all_translations );
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
	public static function get_locale_label( $locale, array $all_translations ) {
		if ( isset( $all_translations[ $locale ]['native_name'] ) && '' !== (string) $all_translations[ $locale ]['native_name'] ) {
			return (string) $all_translations[ $locale ]['native_name'];
		}

		return (string) $locale;
	}

	/**
	 * Replaces default post update messages for translation posts.
	 *
	 * @param array<string, array<int, string>> $messages Current messages.
	 * @param string                            $post_type Translation post type.
	 * @return array<string, array<int, string>>
	 */
	public static function filter_translation_post_updated_messages( array $messages, $post_type ) {
		$messages[ $post_type ] = array(
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
	 * @param string                               $post_type Translation post type.
	 * @return array<string, array<string, string>>
	 */
	public static function filter_translation_bulk_updated_messages( array $bulk_messages, array $bulk_counts, $post_type ) {
		$updated_count   = isset( $bulk_counts['updated'] ) ? (int) $bulk_counts['updated'] : 0;
		$locked_count    = isset( $bulk_counts['locked'] ) ? (int) $bulk_counts['locked'] : 0;
		$deleted_count   = isset( $bulk_counts['deleted'] ) ? (int) $bulk_counts['deleted'] : 0;
		$trashed_count   = isset( $bulk_counts['trashed'] ) ? (int) $bulk_counts['trashed'] : 0;
		$untrashed_count = isset( $bulk_counts['untrashed'] ) ? (int) $bulk_counts['untrashed'] : 0;

		$bulk_messages[ $post_type ] = array(
			/* translators: %s is the number of translations. */
			'updated'   => sprintf( _n( '%s translation updated.', '%s translations updated.', $updated_count, 'i18nly' ), number_format_i18n( $updated_count ) ),
			/* translators: %s is the number of translations. */
			'locked'    => sprintf( _n( '%s translation not updated, somebody is editing it.', '%s translations not updated, somebody is editing them.', $locked_count, 'i18nly' ), number_format_i18n( $locked_count ) ),
			/* translators: %s is the number of translations. */
			'deleted'   => sprintf( _n( '%s translation permanently deleted.', '%s translations permanently deleted.', $deleted_count, 'i18nly' ), number_format_i18n( $deleted_count ) ),
			/* translators: %s is the number of translations. */
			'trashed'   => sprintf( _n( '%s translation moved to the Trash.', '%s translations moved to the Trash.', $trashed_count, 'i18nly' ), number_format_i18n( $trashed_count ) ),
			/* translators: %s is the number of translations. */
			'untrashed' => sprintf( _n( '%s translation restored from the Trash.', '%s translations restored from the Trash.', $untrashed_count, 'i18nly' ), number_format_i18n( $untrashed_count ) ),
		);

		return $bulk_messages;
	}

	/**
	 * Filters translation list table columns.
	 *
	 * @param array<string, string> $columns Current columns.
	 * @return array<string, string>
	 */
	public static function filter_translation_list_columns( array $columns ) {
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
	 * Returns custom column value for translation rows.
	 *
	 * @param string $column_name Column key.
	 * @param int    $post_id Translation post ID.
	 * @param string $meta_source_key Source slug meta key.
	 * @param string $meta_target_key Target language meta key.
	 * @return string
	 */
	public static function get_translation_list_column_value( $column_name, $post_id, $meta_source_key, $meta_target_key ) {
		if ( 'source_slug' === $column_name ) {
			return (string) get_post_meta( (int) $post_id, $meta_source_key, true );
		}

		if ( 'target_language' === $column_name ) {
			return (string) get_post_meta( (int) $post_id, $meta_target_key, true );
		}

		return '';
	}

	/**
	 * Filters sortable columns for translation list table.
	 *
	 * @param array<string, string> $columns Current sortable columns.
	 * @return array<string, string>
	 */
	public static function filter_translation_sortable_columns( array $columns ) {
		$columns['source_slug']     = 'source_slug';
		$columns['target_language'] = 'target_language';

		return $columns;
	}

	/**
	 * Applies meta sorting for translation custom columns.
	 *
	 * @param object $query Current query object.
	 * @param string $post_type Translation post type.
	 * @param string $meta_source_key Source slug meta key.
	 * @param string $meta_target_key Target language meta key.
	 * @return void
	 */
	public static function apply_translation_sorting( $query, $post_type, $meta_source_key, $meta_target_key ) {
		if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) || ! method_exists( $query, 'set' ) ) {
			return;
		}

		if ( method_exists( $query, 'is_main_query' ) && ! $query->is_main_query() ) {
			return;
		}

		$queried_post_type = (string) $query->get( 'post_type' );
		if ( $post_type !== $queried_post_type ) {
			return;
		}

		$orderby = (string) $query->get( 'orderby' );

		if ( 'source_slug' === $orderby ) {
			$query->set( 'meta_key', $meta_source_key );
			$query->set( 'orderby', 'meta_value' );
		}

		if ( 'target_language' === $orderby ) {
			$query->set( 'meta_key', $meta_target_key );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	/**
	 * Returns translation edit script URL.
	 *
	 * @return string
	 */
	public static function get_translation_edit_script_url() {
		if ( defined( 'I18NLY_PLUGIN_FILE' ) && function_exists( 'plugin_dir_url' ) ) {
			return plugin_dir_url( I18NLY_PLUGIN_FILE ) . 'assets/js/translation-edit.js';
		}

		return 'assets/js/translation-edit.js';
	}

	/**
	 * Returns translation edit style URL.
	 *
	 * @return string
	 */
	public static function get_translation_edit_style_url() {
		if ( defined( 'I18NLY_PLUGIN_FILE' ) && function_exists( 'plugin_dir_url' ) ) {
			return plugin_dir_url( I18NLY_PLUGIN_FILE ) . 'assets/css/translation-edit.css';
		}

		return 'assets/css/translation-edit.css';
	}

	/**
	 * Builds translation edit script configuration.
	 *
	 * @param int $translation_id Translation ID.
	 * @return array<string, mixed>
	 */
	public static function build_translation_edit_script_config( $translation_id ) {
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
	 * Infers text domain from source slug.
	 *
	 * @param string $source_slug Source slug.
	 * @return string
	 */
	public static function infer_text_domain_from_source_slug( $source_slug ) {
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
	public static function build_pot_header_overrides_from_source_slug( $source_slug, $text_domain ) {
		$plugin_data = self::get_source_plugin_data( $source_slug );

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
	public static function get_source_plugin_data( $source_slug ) {
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
	 * Returns translation ID from current edit request.
	 *
	 * @param callable $query_reader Callback reading one GET parameter.
	 * @param callable $translation_reader Callback returning translation row.
	 * @return int
	 */
	public static function get_current_edit_translation_id( callable $query_reader, callable $translation_reader ) {
		$action_raw = (string) call_user_func( $query_reader, 'action' );
		$post_raw   = (string) call_user_func( $query_reader, 'post' );

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

		$translation = call_user_func( $translation_reader, $translation_id );

		return null === $translation ? 0 : $translation_id;
	}

	/**
	 * Registers translation post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return void
	 */
	public static function register_post_type( $post_type ) {
		register_post_type(
			$post_type,
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
	 * Registers admin menu entries.
	 *
	 * @param string $list_screen_slug List screen slug.
	 * @param string $new_screen_slug New screen slug.
	 * @return void
	 */
	public static function register_menu( $list_screen_slug, $new_screen_slug ) {
		add_menu_page(
			__( 'Translations', 'i18nly' ),
			__( 'Translations', 'i18nly' ),
			'manage_options',
			$list_screen_slug,
			'',
			'dashicons-translation',
			58
		);

		add_submenu_page(
			$list_screen_slug,
			__( 'All translations', 'i18nly' ),
			__( 'All translations', 'i18nly' ),
			'manage_options',
			$list_screen_slug
		);

		add_submenu_page(
			$list_screen_slug,
			__( 'Add translation', 'i18nly' ),
			__( 'Add translation', 'i18nly' ),
			'manage_options',
			$new_screen_slug
		);
	}

	/**
	 * Filters row actions to remove quick edit.
	 *
	 * @param array<string, string> $actions Actions.
	 * @param object                $post Post object.
	 * @param string                $post_type Translation post type.
	 * @return array<string, string>
	 */
	public static function filter_translation_row_actions( array $actions, $post, $post_type ) {
		if ( ! isset( $post->post_type ) || $post_type !== (string) $post->post_type ) {
			return $actions;
		}

		unset( $actions['inline hide-if-no-js'] );

		return $actions;
	}

	/**
	 * Creates one translation row.
	 *
	 * @param string $source_slug Source slug.
	 * @param string $target_language Target language.
	 * @param string $post_type Translation post type.
	 * @param string $meta_source_key Source meta key.
	 * @param string $meta_target_key Target meta key.
	 * @return int
	 */
	public static function create_translation( $source_slug, $target_language, $post_type, $meta_source_key, $meta_target_key ) {
		$created_at_local = current_time( 'mysql' );
		$created_at_gmt   = current_time( 'mysql', true );

		$translation_post_id = wp_insert_post(
			array(
				'post_type'     => $post_type,
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

		update_post_meta( $translation_post_id, $meta_source_key, $source_slug );
		update_post_meta( $translation_post_id, $meta_target_key, $target_language );

		return (int) $translation_post_id;
	}

	/**
	 * Returns one translation row by ID.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $post_type Translation post type.
	 * @param string $meta_source_key Source meta key.
	 * @param string $meta_target_key Target meta key.
	 * @return array<string, mixed>|null
	 */
	public static function get_translation( $translation_id, $post_type, $meta_source_key, $meta_target_key ) {
		$translation_post = get_post( (int) $translation_id );
		if ( ! $translation_post || $post_type !== $translation_post->post_type ) {
			return null;
		}

		return array(
			'id'              => (int) $translation_post->ID,
			'source_slug'     => (string) get_post_meta( (int) $translation_post->ID, $meta_source_key, true ),
			'target_language' => (string) get_post_meta( (int) $translation_post->ID, $meta_target_key, true ),
			'created_at'      => self::get_post_created_at( $translation_post ),
		);
	}

	/**
	 * Returns stable creation date for one post.
	 *
	 * @param object $translation_post Post object.
	 * @return string
	 */
	public static function get_post_created_at( $translation_post ) {
		$post_date_gmt = isset( $translation_post->post_date_gmt ) ? (string) $translation_post->post_date_gmt : '';
		if ( '' !== $post_date_gmt && '0000-00-00 00:00:00' !== $post_date_gmt ) {
			return $post_date_gmt;
		}

		return isset( $translation_post->post_date ) ? (string) $translation_post->post_date : '';
	}

	/**
	 * Returns standard edit URL for translation.
	 *
	 * @param int $translation_id Translation ID.
	 * @return string
	 */
	public static function get_standard_edit_translation_url( $translation_id ) {
		$edit_url = add_query_arg( 'post', (string) $translation_id, admin_url( 'post.php' ) );

		return add_query_arg( 'action', 'edit', $edit_url );
	}

	/**
	 * Returns native list URL for translations.
	 *
	 * @param string $list_screen_slug List screen slug.
	 * @return string
	 */
	public static function get_native_list_url( $list_screen_slug ) {
		return admin_url( $list_screen_slug );
	}

	/**
	 * Persists translation entry values.
	 *
	 * @param int                                     $translation_id Translation ID.
	 * @param string                                  $source_slug Source slug.
	 * @param array<int|string, array<string, mixed>> $entries_payload Posted entries payload.
	 * @return void
	 */
	public static function persist_translation_entries( $translation_id, $source_slug, array $entries_payload ) {
		$schema_manager = new I18nly_Source_Schema_Manager();
		$schema_manager->maybe_upgrade();

		$repository = new I18nly_Source_Wpdb_Repository( $schema_manager );
		$now_gmt    = gmdate( 'Y-m-d H:i:s' );

		if ( method_exists( $repository, 'ensure_translated_entries_for_translation' ) ) {
			$repository->ensure_translated_entries_for_translation( (int) $translation_id, (string) $source_slug, $now_gmt );
		}

		if ( ! method_exists( $repository, 'upsert_translated_entry' ) ) {
			return;
		}

		foreach ( $entries_payload as $source_entry_id => $entry_payload ) {
			if ( ! is_array( $entry_payload ) ) {
				continue;
			}

			$normalized_source_entry_id = absint( $source_entry_id );
			if ( $normalized_source_entry_id <= 0 ) {
				continue;
			}

			$translation        = isset( $entry_payload['translation'] )
				? sanitize_text_field( (string) $entry_payload['translation'] )
				: '';
			$translation_plural = isset( $entry_payload['translation_plural'] )
				? sanitize_text_field( (string) $entry_payload['translation_plural'] )
				: '';

			$repository->upsert_translated_entry(
				(int) $translation_id,
				$normalized_source_entry_id,
				$translation,
				$translation_plural,
				$now_gmt
			);
		}
	}
}
