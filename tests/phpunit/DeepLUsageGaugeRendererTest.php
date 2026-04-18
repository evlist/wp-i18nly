<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\Admin\UI\DeepLUsageGaugeRenderer;

/**
 * Tests DeepL usage gauge renderer output.
 */
class DeepLUsageGaugeRendererTest extends TestCase {
	/**
	 * Renders progress values for available usage status.
	 *
	 * @return void
	 */
	public function test_render_outputs_progress_and_values() {
		$renderer = new DeepLUsageGaugeRenderer();

		ob_start();
		$renderer->render(
			array(
				'success'         => true,
				'used_characters' => 120,
				'character_limit' => 1000,
				'percent_used'    => 12,
				'state'           => 'ok',
				'fetched_at'      => 1713412800,
				'is_stale'        => false,
				'message'         => '',
			),
			'DeepL monthly usage'
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( 'DeepL monthly usage', $html );
		$this->assertStringContainsString( 'role="progressbar"', $html );
		$this->assertStringContainsString( 'width:12%', $html );
		$this->assertStringContainsString( '120', $html );
		$this->assertStringContainsString( '1,000', $html );
	}

	/**
	 * Renders unavailable messages when limit is missing.
	 *
	 * @return void
	 */
	public function test_render_outputs_unavailable_message_when_limit_missing() {
		$renderer = new DeepLUsageGaugeRenderer();

		ob_start();
		$renderer->render(
			array(
				'success'         => false,
				'used_characters' => 0,
				'character_limit' => 0,
				'percent_used'    => 0,
				'state'           => 'unavailable',
				'fetched_at'      => 0,
				'is_stale'        => false,
				'message'         => 'No DeepL API key configured.',
			),
			'DeepL monthly usage'
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Usage limit is unavailable for this key.', $html );
		$this->assertStringContainsString( 'No DeepL API key configured.', $html );
	}

	/**
	 * Renders over-limit state with capped progress bar width.
	 *
	 * @return void
	 */
	public function test_render_caps_bar_width_but_keeps_percent_label_above_100() {
		$renderer = new DeepLUsageGaugeRenderer();

		ob_start();
		$renderer->render(
			array(
				'success'         => true,
				'used_characters' => 1050,
				'character_limit' => 1000,
				'percent_used'    => 105,
				'state'           => 'over_limit',
				'fetched_at'      => 1713412800,
				'is_stale'        => false,
				'message'         => '',
			),
			'DeepL monthly usage'
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( 'i18nly-deepl-usage-box--over_limit', $html );
		$this->assertStringContainsString( 'width:100%', $html );
		$this->assertStringContainsString( '>105%</p>', $html );
	}
}
