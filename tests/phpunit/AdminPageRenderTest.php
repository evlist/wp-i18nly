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
		$this->assertSame( 'i18nly-translations', $menus[0]['menu_slug'] );

		$this->assertCount( 3, $submenus );
		$this->assertSame( 'All translations', $submenus[0]['menu_title'] );
		$this->assertSame( 'i18nly-translations', $submenus[0]['menu_slug'] );
		$this->assertSame( 'Add translation', $submenus[1]['menu_title'] );
		$this->assertSame( 'i18nly-add-translation', $submenus[1]['menu_slug'] );
		$this->assertSame( 'Edit translation', $submenus[2]['menu_title'] );
		$this->assertSame( 'i18nly-edit-translation', $submenus[2]['menu_slug'] );
	}

	/**
	 * Renders the all translations page for authorized users.
	 *
	 * @return void
	 */
	public function test_render_all_translations_page_outputs_translations_heading() {
		i18nly_test_set_can_manage_options( true );

		$page = new I18nly_Admin_Page();

		ob_start();
		$page->render_all_translations_page();
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertStringContainsString( '<h1>Translations</h1>', $html );
		$this->assertStringContainsString( 'id="i18nly-translations-list"', $html );
		$this->assertStringContainsString( 'wp-list-table widefat fixed striped table-view-list', $html );
		$this->assertStringContainsString( '>Source<', $html );
		$this->assertStringContainsString( '>Target language<', $html );
		$this->assertStringContainsString( '>Created<', $html );
		$this->assertStringContainsString( '>No translations found.<', $html );
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

		$page = new I18nly_Admin_Page();

		ob_start();
		$page->render_all_translations_page();
		$html = ob_get_clean();

		$this->assertSame( '', $html );
	}
}
