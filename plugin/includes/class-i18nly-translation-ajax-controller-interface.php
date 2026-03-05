<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation AJAX controller interface.
 *
 * @package I18nly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contract for translation AJAX actions.
 */
interface I18nly_Translation_Ajax_Controller_Interface {
	/**
	 * Handles AJAX request to generate temporary POT for one translation.
	 *
	 * @return void
	 */
	public function handle_generate_translation_pot();

	/**
	 * Handles AJAX request to fetch source entries table HTML.
	 *
	 * @return void
	 */
	public function handle_get_translation_entries_table();
}
