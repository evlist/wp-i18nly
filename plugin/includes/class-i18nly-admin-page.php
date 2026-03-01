<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * I18nly admin page class.
 *
 * @package I18nly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the I18nly admin pages.
 */
class I18nly_Admin_Page {
	/**
	 * Source locale used by the current MVP.
	 */
	private const SOURCE_LOCALE = 'en_US';

	/**
	 * The top-level menu slug.
	 */
	private const MENU_SLUG = 'i18nly-translations';

	/**
	 * The add translation submenu slug.
	 */
	private const ADD_MENU_SLUG = 'i18nly-add-translation';

	/**
	 * Returns installed plugins as options for the selector.
	 *
	 * @return array<string, string>
	 */
	private function get_plugin_options() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		$options = array();

		foreach ( $plugins as $plugin_file => $plugin_data ) {
			if ( empty( $plugin_data['Name'] ) ) {
				continue;
			}

			$options[ $plugin_file ] = (string) $plugin_data['Name'];
		}

		asort( $options );

		return $options;
	}

	/**
	 * Returns language options for target language selector.
	 *
	 * Installed languages are listed first, then a separator, then all
	 * remaining languages.
	 *
	 * @return array<int, array{value: string, label: string, disabled: bool}>
	 */
	private function get_target_language_options() {
		$source_locale     = self::SOURCE_LOCALE;
		$installed_locales = array();
		$all_translations  = array();
		$preferred_options = array();
		$remaining_options = array();
		$ordered_options   = array();

		if ( ! function_exists( 'wp_get_available_translations' ) ) {
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		}

		if ( function_exists( 'get_available_languages' ) ) {
			$installed_locales = get_available_languages();
		}

		if ( function_exists( 'wp_get_available_translations' ) ) {
			$all_translations = wp_get_available_translations();
		}

		foreach ( $installed_locales as $locale ) {
			if ( $source_locale === $locale ) {
				continue;
			}

			$preferred_options[ $locale ] = $this->get_locale_label( $locale, $all_translations );
		}

		asort( $preferred_options );

		foreach ( $all_translations as $locale => $translation ) {
			if ( $source_locale === $locale || isset( $preferred_options[ $locale ] ) ) {
				continue;
			}

			unset( $translation );
			$remaining_options[ $locale ] = $this->get_locale_label( $locale, $all_translations );
		}

		asort( $remaining_options );

		foreach ( $preferred_options as $locale => $label ) {
			$ordered_options[] = array(
				'value'    => (string) $locale,
				'label'    => (string) $label,
				'disabled' => false,
			);
		}

		if ( ! empty( $preferred_options ) && ! empty( $remaining_options ) ) {
			$ordered_options[] = array(
				'value'    => '',
				'label'    => '──────────',
				'disabled' => true,
			);
		}

		foreach ( $remaining_options as $locale => $label ) {
			$ordered_options[] = array(
				'value'    => (string) $locale,
				'label'    => (string) $label,
				'disabled' => false,
			);
		}

		return $ordered_options;
	}

	/**
	 * Returns a human-readable locale label.
	 *
	 * @param string                              $locale Locale code.
	 * @param array<string, array<string, mixed>> $all_translations Available translations.
	 * @return string
	 */
	private function get_locale_label( $locale, array $all_translations ) {
		if ( isset( $all_translations[ $locale ]['native_name'] ) && '' !== (string) $all_translations[ $locale ]['native_name'] ) {
			return (string) $all_translations[ $locale ]['native_name'];
		}

		return (string) $locale;
	}

	/**
	 * Registers hooks used by the admin page.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Registers the admin menu entries.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Translations', 'i18nly' ),
			__( 'Translations', 'i18nly' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_all_translations_page' ),
			'dashicons-translation',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'All translations', 'i18nly' ),
			__( 'All translations', 'i18nly' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_all_translations_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Add translation', 'i18nly' ),
			__( 'Add translation', 'i18nly' ),
			'manage_options',
			self::ADD_MENU_SLUG,
			array( $this, 'render_add_translation_page' )
		);
	}

	/**
	 * Renders the all translations page.
	 *
	 * @return void
	 */
	public function render_all_translations_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Translations', 'i18nly' ); ?></h1>
			<div id="i18nly-translations-list" aria-live="polite"></div>
		</div>
		<?php
	}

	/**
	 * Renders the add translation page.
	 *
	 * @return void
	 */
	public function render_add_translation_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plugin_options   = $this->get_plugin_options();
		$target_languages = $this->get_target_language_options();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Add translation', 'i18nly' ); ?></h1>
			<div id="i18nly-translation-create" aria-live="polite">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="i18nly-plugin-selector"><?php echo esc_html__( 'Plugin', 'i18nly' ); ?></label>
							</th>
							<td>
								<select id="i18nly-plugin-selector" name="i18nly_plugin_selector">
									<option value=""><?php echo esc_html__( 'Select a plugin', 'i18nly' ); ?></option>
									<?php foreach ( $plugin_options as $plugin_file => $plugin_name ) : ?>
										<option value="<?php echo esc_attr( $plugin_file ); ?>"><?php echo esc_html( $plugin_name ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="i18nly-target-language-selector"><?php echo esc_html__( 'Target language', 'i18nly' ); ?></label>
							</th>
							<td>
								<select id="i18nly-target-language-selector" name="i18nly_target_language_selector">
									<option value=""><?php echo esc_html__( 'Select a target language', 'i18nly' ); ?></option>
									<?php foreach ( $target_languages as $target_language ) : ?>
										<option value="<?php echo esc_attr( $target_language['value'] ); ?>"<?php echo disabled( true, (bool) $target_language['disabled'], false ); ?>><?php echo esc_html( $target_language['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
