<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Admin registration support.
 *
 * @package I18nly
 */

namespace WP_I18nly\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Registers WordPress admin entities used by I18nly.
 */
class AdminRegistration {
	/**
	 * Registers translation post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			'i18nly_translation',
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
				// Own capability type: the default "post" type would let Contributors and Authors edit translations.
				'capability_type' => array( 'i18nly_translation', 'i18nly_translations' ),
				'capabilities'    => self::get_capabilities(),
				'map_meta_cap'    => true,
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
	public function register_menu( $list_screen_slug, $new_screen_slug ) {
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
	 * Returns the capabilities of the translation post type: all of them require manage_options.
	 *
	 * The meta capabilities (edit_post, delete_post, read_post) are mapped by WordPress to these primitive ones.
	 *
	 * @return array<string, string>
	 */
	public static function get_capabilities() {
		$primitives = array(
			'edit_posts',
			'edit_others_posts',
			'edit_private_posts',
			'edit_published_posts',
			'publish_posts',
			'read_private_posts',
			'delete_posts',
			'delete_private_posts',
			'delete_published_posts',
			'delete_others_posts',
			'create_posts',
		);

		return array_fill_keys( $primitives, 'manage_options' );
	}
}
