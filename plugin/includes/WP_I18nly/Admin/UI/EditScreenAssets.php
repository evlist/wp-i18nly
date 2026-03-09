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
	 * Returns translation edit script URL.
	 *
	 * @return string
	 */
	public function get_script_url() {
		if ( defined( 'I18NLY_PLUGIN_FILE' ) && function_exists( 'plugin_dir_url' ) ) {
			return plugin_dir_url( I18NLY_PLUGIN_FILE ) . 'assets/js/translation-edit.js';
		}

		return 'assets/js/translation-edit.js';
	}

	/**
	 * Returns translation edit style URL.
	 *
	 * @return string
	 */
	public function get_style_url() {
		if ( defined( 'I18NLY_PLUGIN_FILE' ) && function_exists( 'plugin_dir_url' ) ) {
			return plugin_dir_url( I18NLY_PLUGIN_FILE ) . 'assets/css/translation-edit.css';
		}

		return 'assets/css/translation-edit.css';
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
		);
	}
}
