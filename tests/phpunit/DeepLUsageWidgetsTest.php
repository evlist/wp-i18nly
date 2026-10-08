<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * DeepL usage widgets tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests DeepL usage gauge rendering in the edit meta box and dashboard widget.
 */
class DeepLUsageWidgetsTest extends TestCase {
	/**
	 * Builds widgets backed by a deterministic status provider.
	 *
	 * @return \WP_I18nly\Admin\DeepLUsageWidgets
	 */
	private function build_widgets() {
		$provider = new class() {
			/**
			 * Returns deterministic usage status.
			 *
			 * @return array<string, mixed>
			 */
			public function get_status() {
				return array(
					'success'         => true,
					'used_characters' => 250,
					'character_limit' => 1000,
					'percent_used'    => 25,
					'state'           => 'ok',
					'fetched_at'      => 1713412800,
					'is_stale'        => false,
					'message'         => '',
				);
			}
		};

		return new \WP_I18nly\Admin\DeepLUsageWidgets( $provider );
	}

	/**
	 * Renders the usage gauge in the translation edit meta box.
	 *
	 * @return void
	 */
	public function test_render_meta_box_outputs_usage_gauge() {
		ob_start();
		$this->build_widgets()->render_meta_box();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'DeepL monthly usage', $html );
		$this->assertStringContainsString( 'role="progressbar"', $html );
		$this->assertStringContainsString( 'width:25%', $html );
	}

	/**
	 * Renders the same usage gauge in the dashboard widget.
	 *
	 * @return void
	 */
	public function test_render_dashboard_widget_outputs_usage_gauge() {
		ob_start();
		$this->build_widgets()->render_dashboard_widget();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'role="progressbar"', $html );
		$this->assertStringContainsString( 'width:25%', $html );
	}
}
