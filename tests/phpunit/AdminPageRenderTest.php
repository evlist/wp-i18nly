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

		$this->assertCount( 2, $submenus );
		$this->assertSame( 'All translations', $submenus[0]['menu_title'] );
		$this->assertSame( 'i18nly-translations', $submenus[0]['menu_slug'] );
		$this->assertSame( 'Add translation', $submenus[1]['menu_title'] );
		$this->assertSame( 'i18nly-add-translation', $submenus[1]['menu_slug'] );
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
	}

	/**
	 * Renders the add translation page for authorized users.
	 *
	 * @return void
	 */
	public function test_render_add_translation_page_outputs_add_translation_heading() {
		i18nly_test_set_can_manage_options( true );

		$page = new I18nly_Admin_Page();

		ob_start();
		$page->render_add_translation_page();
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertStringContainsString( '<h1>Add translation</h1>', $html );
		$this->assertStringContainsString( 'id="i18nly-translation-create"', $html );
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
