<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation meta box renderer interface.
 *
 * @package I18nly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contract for translation meta box rendering.
 */
interface I18nly_Translation_Meta_Box_Renderer_Interface {
	/**
	 * Renders translation meta box fields.
	 *
	 * @param array<string, string>                                           $plugin_options Plugin selector options.
	 * @param array<int, array{value: string, label: string, disabled: bool}> $target_languages Target language options.
	 * @param string                                                          $selected_source Selected source slug.
	 * @param string                                                          $selected_language Selected target language.
	 * @param bool                                                            $is_locked Whether source/language are locked.
	 * @return void
	 */
	public function render_translation_meta_box( array $plugin_options, array $target_languages, $selected_source, $selected_language, $is_locked );

	/**
	 * Renders source entries table markup.
	 *
	 * @param array<int, array<string, mixed>> $source_entries Source entries rows.
	 * @return string
	 */
	public function render_source_entries_table_markup( array $source_entries );
}
