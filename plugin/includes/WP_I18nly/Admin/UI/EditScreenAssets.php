<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation edit screen assets handler.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Manages edit screen JavaScript and CSS assets.
 */
class EditScreenAssets {
	/**
	 * Scripts of the translation edit screen, in loading order.
	 *
	 * The generic linguistic resource editor comes first, then the translation specific code, then the
	 * entry point. The dependencies are the handles that must be loaded before a script because it uses
	 * their classes while loading.
	 *
	 * @var array<int, array{handle: string, path: string, deps: array<int, string>}>
	 */
	private const SCRIPTS = array(
		array(
			'handle' => 'i18nly-ajax-client',
			'path'   => 'assets/js/linguistic-resource/ajax-client.js',
			'deps'   => array(),
		),
		array(
			'handle' => 'i18nly-ui-text',
			'path'   => 'assets/js/linguistic-resource/ui-text.js',
			'deps'   => array( 'wp-i18n' ),
		),
		array(
			'handle' => 'i18nly-entry-badges',
			'path'   => 'assets/js/linguistic-resource/entry-badges.js',
			'deps'   => array(),
		),
		array(
			'handle' => 'i18nly-abstract-row',
			'path'   => 'assets/js/linguistic-resource/abstract-row.js',
			'deps'   => array( 'i18nly-entry-badges' ),
		),
		array(
			'handle' => 'i18nly-entry-filter-bar',
			'path'   => 'assets/js/linguistic-resource/entry-filter-bar.js',
			'deps'   => array(),
		),
		array(
			'handle' => 'i18nly-modified-rows-tracker',
			'path'   => 'assets/js/linguistic-resource/modified-rows-tracker.js',
			'deps'   => array(),
		),
		array(
			'handle' => 'i18nly-abstract-editor',
			'path'   => 'assets/js/linguistic-resource/abstract-editor.js',
			'deps'   => array( 'i18nly-ajax-client', 'i18nly-ui-text', 'i18nly-entry-badges', 'i18nly-entry-filter-bar', 'i18nly-modified-rows-tracker' ),
		),
		array(
			'handle' => 'i18nly-translation-row',
			'path'   => 'assets/js/translation/translation-row.js',
			'deps'   => array( 'i18nly-abstract-row' ),
		),
		array(
			'handle' => 'i18nly-ai-error-dialog',
			'path'   => 'assets/js/translation/ai-error-dialog.js',
			'deps'   => array(),
		),
		array(
			'handle' => 'i18nly-deepl-usage-gauge',
			'path'   => 'assets/js/translation/deepl-usage-gauge.js',
			'deps'   => array(),
		),
		array(
			'handle' => 'i18nly-ai-batch-translation',
			'path'   => 'assets/js/translation/ai-batch-translation.js',
			'deps'   => array( 'i18nly-ajax-client', 'i18nly-ai-error-dialog', 'i18nly-deepl-usage-gauge' ),
		),
		array(
			'handle' => 'i18nly-translation-editor',
			'path'   => 'assets/js/translation/translation-editor.js',
			'deps'   => array( 'i18nly-abstract-editor', 'i18nly-translation-row', 'i18nly-ai-batch-translation', 'i18nly-ai-error-dialog', 'i18nly-entry-badges', 'i18nly-ajax-client' ),
		),
		array(
			'handle' => 'i18nly-translation-edit',
			'path'   => 'assets/js/translation-edit.js',
			'deps'   => array( 'i18nly-translation-editor' ),
		),
	);

	/**
	 * Returns the scripts of the translation edit screen, dependencies first.
	 *
	 * The last script is the entry point; the editor configuration is attached to it.
	 *
	 * @return array<int, array{handle: string, src: string, deps: array<int, string>}>
	 */
	public function get_script_definitions() {
		$definitions = array();

		foreach ( self::SCRIPTS as $script ) {
			$definitions[] = array(
				'handle' => $script['handle'],
				'src'    => $this->get_asset_url( $script['path'] ),
				'deps'   => $script['deps'],
			);
		}

		return $definitions;
	}

	/**
	 * Returns the URL of one plugin asset.
	 *
	 * @param string $path Asset path relative to the plugin directory.
	 * @return string
	 */
	private function get_asset_url( $path ) {
		if ( defined( 'I18NLY_PLUGIN_FILE' ) && function_exists( 'plugin_dir_url' ) ) {
			return plugin_dir_url( I18NLY_PLUGIN_FILE ) . $path;
		}

		return $path;
	}

	/**
	 * Returns translation edit style URL.
	 *
	 * @return string
	 */
	public function get_style_url() {
		return $this->get_asset_url( 'assets/css/translation-edit.css' );
	}

	/**
	 * Builds translation edit script configuration.
	 *
	 * @param int $translation_id Translation ID.
	 * @return array<string, mixed>
	 */
	public function build_script_config( $translation_id ) {
		return array(
			'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
			'translationId'     => (int) $translation_id,
			'generateAction'    => 'i18nly_generate_translation_pot',
			'generateNonce'     => wp_create_nonce( 'i18nly_generate_translation_pot_' . (int) $translation_id ),
			'refreshAction'     => 'i18nly_get_translation_entries_table',
			'refreshNonce'      => wp_create_nonce( 'i18nly_get_translation_entries_table_' . (int) $translation_id ),
			'tableContainerId'  => 'i18nly-source-entries-table',
			'contentTypeHeader' => 'application/x-www-form-urlencoded; charset=UTF-8',
			'i18n'              => array(
				'showOnlyModifiedRowsLabel' => __( 'Show only these modified rows', 'i18nly' ),
				'applyFiltersAndCloseLabel' => __( 'Apply filters and close', 'i18nly' ),
			),
		);
	}
}
