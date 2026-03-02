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
		$this->assertSame( 'i18nly-add-translation', $submenus[1]['menu_slug'] );
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
	 * Renders the add translation page for authorized users.
	 *
	 * @return void
	 */
	public function test_render_add_translation_page_outputs_add_translation_heading() {
		i18nly_test_set_can_manage_options( true );
		i18nly_test_set_plugins(
			array(
				'akismet/akismet.php'   => array(
					'Name' => 'Akismet',
				),
				'hello-dolly/hello.php' => array(
					'Name' => 'Hello Dolly',
				),
			)
		);
		i18nly_test_set_available_languages(
			array(
				'en_US',
				'fr_FR',
			)
		);
		i18nly_test_set_available_translations(
			array(
				'en_US' => array(
					'native_name' => 'English (United States)',
				),
				'fr_FR' => array(
					'native_name' => 'Français',
				),
				'de_DE' => array(
					'native_name' => 'Deutsch',
				),
			)
		);

		$page = new I18nly_Admin_Page();

		ob_start();
		$page->render_add_translation_page();
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertStringContainsString( '<h1>Add translation</h1>', $html );
		$this->assertStringContainsString( 'id="i18nly-translation-create"', $html );
		$this->assertStringContainsString( 'method="post"', $html );
		$this->assertStringContainsString( 'name="action" value="i18nly_add_translation"', $html );
		$this->assertStringContainsString( 'id="i18nly-plugin-selector"', $html );
		$this->assertStringContainsString( '>Select a plugin<', $html );
		$this->assertStringContainsString( 'value="akismet/akismet.php"', $html );
		$this->assertStringContainsString( '>Akismet<', $html );
		$this->assertStringContainsString( 'id="i18nly-target-language-selector"', $html );
		$this->assertStringContainsString( '>Select a target language<', $html );
		$this->assertStringContainsString( 'value="fr_FR"', $html );
		$this->assertStringContainsString( '>Français<', $html );
		$this->assertStringContainsString( 'disabled="disabled">──────────<', $html );
		$this->assertStringContainsString( 'value="de_DE"', $html );
		$this->assertStringContainsString( '>Deutsch<', $html );
		$this->assertStringNotContainsString( 'value="en_US"', $html );
		$this->assertStringContainsString( 'id="i18nly-add-translation-submit"', $html );
		$this->assertStringContainsString( '>Add<', $html );
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
