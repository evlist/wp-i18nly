<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Admin page rendering tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests admin page rendering.
 */
class AdminPageRenderTest extends TestCase {
	/**
	 * Registers a WordPress-style translations menu and submenus.
	 *
	 * @return void
	 */
	public function test_register_menu_registers_translations_menu_structure() {
		i18nly_test_reset_admin_menu_capture();

		$page = new I18nly_Admin_Page();
		$page->register_menu();

		$menus    = i18nly_test_get_menu_pages();
		$submenus = i18nly_test_get_submenu_pages();

		$this->assertCount( 1, $menus );
		$this->assertSame( 'Translations', $menus[0]['menu_title'] );
		$this->assertSame( 'edit.php?post_type=i18nly_translation', $menus[0]['menu_slug'] );

		$this->assertCount( 2, $submenus );
		$this->assertSame( 'All translations', $submenus[0]['menu_title'] );
		$this->assertSame( 'edit.php?post_type=i18nly_translation', $submenus[0]['menu_slug'] );
		$this->assertSame( 'Add translation', $submenus[1]['menu_title'] );
		$this->assertSame( 'post-new.php?post_type=i18nly_translation', $submenus[1]['menu_slug'] );
	}

	/**
	 * Renders the all translations page for authorized users.
	 *
	 * @return void
	 */
	public function test_render_all_translations_page_redirects_to_native_list_screen() {
		i18nly_test_set_can_manage_options( true );
		i18nly_test_reset_last_redirect_url();

		$page = new I18nly_Admin_Page();

		ob_start();
		$page->render_all_translations_page();
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertSame( '', $html );
		$this->assertSame(
			'https://example.test/wp-admin/edit.php?post_type=i18nly_translation',
			i18nly_test_get_last_redirect_url()
		);
	}

	/**
	 * Renders nothing on all translations page without capability.
	 *
	 * @return void
	 */
	public function test_render_all_translations_page_outputs_nothing_without_capability() {
		i18nly_test_set_can_manage_options( false );
		i18nly_test_reset_last_redirect_url();

		$page = new I18nly_Admin_Page();

		ob_start();
		$page->render_all_translations_page();
		$html = ob_get_clean();

		$this->assertSame( '', $html );
		$this->assertSame( '', i18nly_test_get_last_redirect_url() );
	}

	/**
	 * Adds source and target columns to native translations list.
	 *
	 * @return void
	 */
	public function test_filter_translation_list_columns_adds_source_and_target_columns() {
		$page = new I18nly_Admin_Page();

		$columns = $page->filter_translation_list_columns(
			array(
				'cb'    => '<input type="checkbox">',
				'title' => 'Title',
				'date'  => 'Date',
			)
		);

		$this->assertArrayHasKey( 'source_slug', $columns );
		$this->assertArrayHasKey( 'target_language', $columns );
		$this->assertSame( 'Source', $columns['source_slug'] );
		$this->assertSame( 'Target language', $columns['target_language'] );
	}

	/**
	 * Renders source and target values from post meta.
	 *
	 * @return void
	 */
	public function test_render_translation_list_column_outputs_meta_values() {
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'               => 42,
					'source_slug'      => 'akismet/akismet.php',
					'target_language'  => 'fr_FR',
					'created_at_gmt'   => '0000-00-00 00:00:00',
					'created_at_local' => '2026-03-02 11:15:00',
				),
			)
		);

		$page = new I18nly_Admin_Page();

		ob_start();
		$page->render_translation_list_column( 'source_slug', 42 );
		$source = ob_get_clean();

		ob_start();
		$page->render_translation_list_column( 'target_language', 42 );
		$target = ob_get_clean();

		$this->assertSame( 'akismet/akismet.php', $source );
		$this->assertSame( 'fr_FR', $target );
	}

	/**
	 * Declares source and target as sortable columns.
	 *
	 * @return void
	 */
	public function test_filter_translation_sortable_columns_adds_source_and_target() {
		$page = new I18nly_Admin_Page();

		$columns = $page->filter_translation_sortable_columns( array() );

		$this->assertSame( 'source_slug', $columns['source_slug'] );
		$this->assertSame( 'target_language', $columns['target_language'] );
	}

	/**
	 * Renders translation meta box fields on native editor screen.
	 *
	 * @return void
	 */
	public function test_render_translation_meta_box_outputs_plugin_and_language_selectors() {
		i18nly_test_set_plugins(
			array(
				'akismet/akismet.php' => array(
					'Name' => 'Akismet',
				),
			)
		);
		i18nly_test_set_available_languages(
			array(
				'fr_FR',
			)
		);
		i18nly_test_set_available_translations(
			array(
				'fr_FR' => array(
					'native_name' => 'Français',
				),
			)
		);
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'               => 42,
					'source_slug'      => 'akismet/akismet.php',
					'target_language'  => 'fr_FR',
					'created_at_gmt'   => '2026-03-02 00:00:00',
					'created_at_local' => '2026-03-02 00:00:00',
				),
			)
		);

		$page = new I18nly_Admin_Page();

		ob_start();
		$page->render_translation_meta_box( (object) array( 'ID' => 42 ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'id="i18nly-plugin-selector"', $html );
		$this->assertStringContainsString( 'id="i18nly-target-language-selector"', $html );
		$this->assertStringContainsString( 'selected="selected"', $html );
	}

	/**
	 * Saves meta and auto-generates title when current title is empty.
	 *
	 * @return void
	 */
	public function test_save_translation_meta_box_sets_meta_and_autogenerates_title_when_empty() {
		i18nly_test_set_can_manage_options( true );
		i18nly_test_reset_last_updated_post();

		$_POST['i18nly_translation_meta_box_nonce'] = 'nonce-i18nly_translation_meta_box';
		$_POST['i18nly_plugin_selector']            = 'akismet/akismet.php';
		$_POST['i18nly_target_language_selector']   = 'fr_FR';

		$page = new I18nly_Admin_Page();
		$page->save_translation_meta_box(
			42,
			(object) array(
				'post_type'  => 'i18nly_translation',
				'post_title' => '',
			),
			false
		);

		$this->assertSame( 'akismet/akismet.php', get_post_meta( 42, '_i18nly_source_slug', true ) );
		$this->assertSame( 'fr_FR', get_post_meta( 42, '_i18nly_target_language', true ) );
		$this->assertSame(
			array(
				'ID'         => 42,
				'post_title' => 'akismet/akismet.php → fr_FR',
			),
			i18nly_test_get_last_updated_post()
		);

		unset( $_POST['i18nly_translation_meta_box_nonce'], $_POST['i18nly_plugin_selector'], $_POST['i18nly_target_language_selector'] );
	}

	/**
	 * Does not override existing title on save.
	 *
	 * @return void
	 */
	public function test_save_translation_meta_box_keeps_existing_title() {
		i18nly_test_set_can_manage_options( true );
		i18nly_test_reset_last_updated_post();

		$_POST['i18nly_translation_meta_box_nonce'] = 'nonce-i18nly_translation_meta_box';
		$_POST['i18nly_plugin_selector']            = 'akismet/akismet.php';
		$_POST['i18nly_target_language_selector']   = 'fr_FR';

		$page = new I18nly_Admin_Page();
		$page->save_translation_meta_box(
			42,
			(object) array(
				'post_type'  => 'i18nly_translation',
				'post_title' => 'Manual title',
			),
			true
		);

		$this->assertSame( array(), i18nly_test_get_last_updated_post() );

		unset( $_POST['i18nly_translation_meta_box_nonce'], $_POST['i18nly_plugin_selector'], $_POST['i18nly_target_language_selector'] );
	}

	/**
	 * Maps source sort to source meta key.
	 *
	 * @return void
	 */
	public function test_apply_translation_sorting_maps_source_slug_to_meta_ordering() {
		$page  = new I18nly_Admin_Page();
		$query = new class() {
			/**
			 * Query vars.
			 *
			 * @var array<string, mixed>
			 */
			private $vars = array(
				'post_type' => 'i18nly_translation',
				'orderby'   => 'source_slug',
			);

			/**
			 * Gets one query var.
			 *
			 * @param string $key Var key.
			 * @return mixed
			 */
			public function get( $key ) {
				return isset( $this->vars[ $key ] ) ? $this->vars[ $key ] : null;
			}

			/**
			 * Sets one query var.
			 *
			 * @param string $key Var key.
			 * @param mixed  $value Var value.
			 * @return void
			 */
			public function set( $key, $value ) {
				$this->vars[ $key ] = $value;
			}

			/**
			 * Returns whether this is main query.
			 *
			 * @return bool
			 */
			public function is_main_query() {
				return true;
			}
		};

		$page->apply_translation_sorting( $query );

		$this->assertSame( '_i18nly_source_slug', $query->get( 'meta_key' ) );
		$this->assertSame( 'meta_value', $query->get( 'orderby' ) );
	}

	/**
	 * Maps target sort to target meta key.
	 *
	 * @return void
	 */
	public function test_apply_translation_sorting_maps_target_language_to_meta_ordering() {
		$page  = new I18nly_Admin_Page();
		$query = new class() {
			/**
			 * Query vars.
			 *
			 * @var array<string, mixed>
			 */
			private $vars = array(
				'post_type' => 'i18nly_translation',
				'orderby'   => 'target_language',
			);

			/**
			 * Gets one query var.
			 *
			 * @param string $key Var key.
			 * @return mixed
			 */
			public function get( $key ) {
				return isset( $this->vars[ $key ] ) ? $this->vars[ $key ] : null;
			}

			/**
			 * Sets one query var.
			 *
			 * @param string $key Var key.
			 * @param mixed  $value Var value.
			 * @return void
			 */
			public function set( $key, $value ) {
				$this->vars[ $key ] = $value;
			}

			/**
			 * Returns whether this is main query.
			 *
			 * @return bool
			 */
			public function is_main_query() {
				return true;
			}
		};

		$page->apply_translation_sorting( $query );

		$this->assertSame( '_i18nly_target_language', $query->get( 'meta_key' ) );
		$this->assertSame( 'meta_value', $query->get( 'orderby' ) );
	}
}
