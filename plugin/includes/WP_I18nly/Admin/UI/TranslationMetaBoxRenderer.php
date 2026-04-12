<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation meta box renderer.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Renders translation meta box and entries table markup.
 */
class TranslationMetaBoxRenderer {
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
	public function render_translation_meta_box( array $plugin_options, array $target_languages, $selected_source, $selected_language, $is_locked ) {
		wp_nonce_field( 'i18nly_translation_meta_box', 'i18nly_translation_meta_box_nonce' );
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="i18nly-plugin-selector"><?php echo esc_html__( 'Plugin', 'i18nly' ); ?></label>
					</th>
					<td>
						<select id="i18nly-plugin-selector" name="i18nly_plugin_selector" required<?php echo disabled( (bool) $is_locked, true, false ); ?>>
							<option value=""><?php echo esc_html__( 'Select a plugin', 'i18nly' ); ?></option>
							<?php foreach ( $plugin_options as $plugin_file => $plugin_name ) : ?>
								<option value="<?php echo esc_attr( $plugin_file ); ?>"<?php selected( (string) $selected_source, (string) $plugin_file ); ?>><?php echo esc_html( $plugin_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="i18nly-target-language-selector"><?php echo esc_html__( 'Target language', 'i18nly' ); ?></label>
					</th>
					<td>
						<select id="i18nly-target-language-selector" name="i18nly_target_language_selector" required<?php echo disabled( (bool) $is_locked, true, false ); ?>>
							<option value=""><?php echo esc_html__( 'Select a target language', 'i18nly' ); ?></option>
							<?php foreach ( $target_languages as $target_language ) : ?>
								<option value="<?php echo esc_attr( $target_language['value'] ); ?>"<?php echo disabled( true, (bool) $target_language['disabled'], false ); ?><?php selected( (string) $selected_language, (string) $target_language['value'] ); ?>><?php echo esc_html( $target_language['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</tbody>
		</table>
		<?php if ( $is_locked ) : ?>
			<p class="description"><?php echo esc_html__( 'Plugin and target language are locked after creation.', 'i18nly' ); ?></p>
		<?php endif; ?>

		<?php if ( $is_locked ) : ?>
			<h3><?php echo esc_html__( 'Translation entries', 'i18nly' ); ?></h3>
			<div class="i18nly-entry-filters" role="group" aria-label="<?php echo esc_attr__( 'Translation entry filters', 'i18nly' ); ?>">
				<div class="i18nly-entry-filter-group">
					<span class="i18nly-entry-filter-title"><?php echo esc_html__( 'Entries', 'i18nly' ); ?></span>
					<label for="i18nly-filter-entry-status-active">
						<input type="checkbox" id="i18nly-filter-entry-status-active" class="i18nly-filter-entry-status" value="active" checked="checked" />
						<?php echo esc_html__( 'Active', 'i18nly' ); ?>
					</label>
					<label for="i18nly-filter-entry-status-obsolete">
						<input type="checkbox" id="i18nly-filter-entry-status-obsolete" class="i18nly-filter-entry-status" value="obsolete" />
						<?php echo esc_html__( 'Obsolete', 'i18nly' ); ?>
					</label>
				</div>
				<div class="i18nly-entry-filter-group">
					<span class="i18nly-entry-filter-title"><?php echo esc_html__( 'Status', 'i18nly' ); ?></span>
					<label for="i18nly-filter-quality-status-suspect">
						<input type="checkbox" id="i18nly-filter-quality-status-suspect" class="i18nly-filter-quality-status" value="suspect" checked="checked" />
						<?php echo esc_html__( 'Suspect', 'i18nly' ); ?>
					</label>
					<label for="i18nly-filter-quality-status-draft">
						<input type="checkbox" id="i18nly-filter-quality-status-draft" class="i18nly-filter-quality-status" value="draft" checked="checked" />
						<?php echo esc_html__( 'Draft', 'i18nly' ); ?>
					</label>
					<label for="i18nly-filter-quality-status-validated">
						<input type="checkbox" id="i18nly-filter-quality-status-validated" class="i18nly-filter-quality-status" value="validated" checked="checked" />
						<?php echo esc_html__( 'Validated', 'i18nly' ); ?>
					</label>
					<label for="i18nly-filter-quality-status-empty">
						<input type="checkbox" id="i18nly-filter-quality-status-empty" class="i18nly-filter-quality-status" value="empty" checked="checked" />
						<?php echo esc_html__( 'Non translated', 'i18nly' ); ?>
					</label>
				</div>
			</div>
			<div id="i18nly-source-entries-table">
				<p><?php echo esc_html__( 'Loading translation entries…', 'i18nly' ); ?></p>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders source entries table markup.
	 *
	 * @param array<int, array<string, mixed>> $source_entries Source entries rows.
	 * @return string
	 */
	public function render_source_entries_table_markup( array $source_entries ) {
		ob_start();
		$list_table = new TranslationEntriesListTable( $source_entries );
		$list_table->prepare_items();
		$list_table->display();

		$html = ob_get_clean();

		if ( ! is_string( $html ) ) {
			return '';
		}

		return $html;
	}
}