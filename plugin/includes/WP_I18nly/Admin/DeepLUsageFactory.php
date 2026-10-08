<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * DeepL usage factory.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin;

use WP_I18nly\AI\DeepLUsageStatusProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the DeepL usage status provider bound to the saved plugin settings.
 */
class DeepLUsageFactory {
	/**
	 * Settings page exposing the saved values.
	 *
	 * @var TranslationSettingsPage|null
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param TranslationSettingsPage|null $settings Optional settings page.
	 */
	public function __construct( TranslationSettingsPage $settings = null ) {
		$this->settings = $settings;
	}

	/**
	 * Creates a status provider reading the saved API key and reserved characters.
	 *
	 * @param callable|null $http_get Optional HTTP GET transport, mainly for tests.
	 * @return DeepLUsageStatusProvider
	 */
	public function create_status_provider( $http_get = null ) {
		$settings = $this->settings instanceof TranslationSettingsPage
			? $this->settings
			: new TranslationSettingsPage();

		return new DeepLUsageStatusProvider(
			function () use ( $settings ) {
				return $settings->get_saved_api_key();
			},
			$http_get,
			function () use ( $settings ) {
				return $settings->get_saved_reserved_characters();
			}
		);
	}
}
