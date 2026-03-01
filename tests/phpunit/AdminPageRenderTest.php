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
	 * Renders the admin page and verifies the File menu and New action.
	 *
	 * @return void
	 */
	public function test_render_page_outputs_file_menu_with_new_action() {
		i18nly_test_set_can_manage_options( true );

		$page = new I18nly_Admin_Page();

		ob_start();
		$page->render_page();
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertStringContainsString( '<summary>File</summary>', $html );
		$this->assertStringContainsString( 'id="i18nly-action-new"', $html );
		$this->assertMatchesRegularExpression( '/>\s*New\s*</', $html );
	}

	/**
	 * Renders nothing when capability check fails.
	 *
	 * @return void
	 */
	public function test_render_page_outputs_nothing_without_capability() {
		i18nly_test_set_can_manage_options( false );

		$page = new I18nly_Admin_Page();

		ob_start();
		$page->render_page();
		$html = ob_get_clean();

		$this->assertSame( '', $html );
	}
}
