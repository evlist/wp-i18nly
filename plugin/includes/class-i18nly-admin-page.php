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
	 * Action used by Add translation form submissions.
	 */
	private const ADD_ACTION = 'i18nly_add_translation';

	/**
	 * Translation post type.
	 */
	private const POST_TYPE = 'i18nly_translation';

	/**
	 * Source slug post meta key.
	 */
	private const META_SOURCE_SLUG = '_i18nly_source_slug';

	/**
	 * Target language post meta key.
	 */
	private const META_TARGET_LANGUAGE = '_i18nly_target_language';

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
	 * The edit translation page slug.
	 */
	private const EDIT_MENU_SLUG = 'i18nly-edit-translation';

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
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_' . self::ADD_ACTION, array( $this, 'handle_add_translation_submission' ) );
	}

	/**
	 * Registers the translation custom post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'        => __( 'Translations', 'i18nly' ),
				'labels'       => array(
					'name'          => __( 'Translations', 'i18nly' ),
					'singular_name' => __( 'Translation', 'i18nly' ),
				),
				'public'       => false,
				'show_ui'      => false,
				'supports'     => array( 'title' ),
				'map_meta_cap' => true,
			)
		);
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

		add_submenu_page(
			null,
			__( 'Edit translation', 'i18nly' ),
			__( 'Edit translation', 'i18nly' ),
			'manage_options',
			self::EDIT_MENU_SLUG,
			array( $this, 'render_edit_translation_page' )
		);
	}

	/**
	 * Handles Add translation form submission.
	 *
	 * @return void
	 */
	public function handle_add_translation_submission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$add_page_url = $this->get_admin_page_url( self::ADD_MENU_SLUG );

		check_admin_referer( self::ADD_ACTION );

		$source_slug = '';
		if ( isset( $_POST['i18nly_plugin_selector'] ) ) {
			$source_slug = sanitize_text_field( wp_unslash( $_POST['i18nly_plugin_selector'] ) );
		}

		$target_language = '';
		if ( isset( $_POST['i18nly_target_language_selector'] ) ) {
			$target_language = sanitize_text_field( wp_unslash( $_POST['i18nly_target_language_selector'] ) );
		}

		if ( '' === $source_slug || '' === $target_language ) {
			wp_safe_redirect( add_query_arg( 'i18nly_error', 'missing_required_fields', $add_page_url ) );
			exit;
		}

		$translation_id = $this->create_translation( $source_slug, $target_language );
		if ( $translation_id <= 0 ) {
			wp_safe_redirect( add_query_arg( 'i18nly_error', 'insert_failed', $add_page_url ) );
			exit;
		}

		wp_safe_redirect(
			$this->get_edit_translation_url( $translation_id )
		);
		exit;
	}

	/**
	 * Creates one translation row.
	 *
	 * @param string $source_slug Source slug identifier.
	 * @param string $target_language Target language code.
	 * @return int
	 */
	private function create_translation( $source_slug, $target_language ) {
		$translation_post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => $source_slug . ' → ' . $target_language,
			),
			true
		);

		if ( is_wp_error( $translation_post_id ) || $translation_post_id <= 0 ) {
			return 0;
		}

		update_post_meta( $translation_post_id, self::META_SOURCE_SLUG, $source_slug );
		update_post_meta( $translation_post_id, self::META_TARGET_LANGUAGE, $target_language );

		return (int) $translation_post_id;
	}

	/**
	 * Returns one translation row by ID.
	 *
	 * @param int $translation_id Translation ID.
	 * @return array<string, mixed>|null
	 */
	private function get_translation( $translation_id ) {
		$translation_post = get_post( (int) $translation_id );
		if ( ! $translation_post || self::POST_TYPE !== $translation_post->post_type ) {
			return null;
		}

		return array(
			'id'              => (int) $translation_post->ID,
			'source_slug'     => (string) get_post_meta( (int) $translation_post->ID, self::META_SOURCE_SLUG, true ),
			'target_language' => (string) get_post_meta( (int) $translation_post->ID, self::META_TARGET_LANGUAGE, true ),
			'created_at'      => (string) $translation_post->post_date_gmt,
		);
	}

	/**
	 * Returns translations for the all translations list.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_translations_for_list() {
		$translation_posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'draft', 'publish' ),
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);

		$translations = array();
		foreach ( $translation_posts as $translation_post ) {
			$translations[] = array(
				'id'              => (int) $translation_post->ID,
				'source_slug'     => (string) get_post_meta( (int) $translation_post->ID, self::META_SOURCE_SLUG, true ),
				'target_language' => (string) get_post_meta( (int) $translation_post->ID, self::META_TARGET_LANGUAGE, true ),
				'created_at'      => (string) $translation_post->post_date_gmt,
			);
		}

		return $translations;
	}

	/**
	 * Returns an admin URL for one plugin page.
	 *
	 * @param string $page_slug Page slug.
	 * @return string
	 */
	private function get_admin_page_url( $page_slug ) {
		return admin_url( 'admin.php?page=' . rawurlencode( $page_slug ) );
	}

	/**
	 * Returns the edit translation page URL for one translation.
	 *
	 * @param int $translation_id Translation ID.
	 * @return string
	 */
	private function get_edit_translation_url( $translation_id ) {
		return add_query_arg(
			'translation_id',
			(string) $translation_id,
			$this->get_admin_page_url( self::EDIT_MENU_SLUG )
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

		$translations = $this->get_translations_for_list();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Translations', 'i18nly' ); ?></h1>
			<div id="i18nly-translations-list" aria-live="polite">
				<table class="wp-list-table widefat fixed striped table-view-list i18nly-translations-table">
					<thead>
						<tr>
							<th scope="col" class="column-primary"><?php echo esc_html__( 'Source', 'i18nly' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Target language', 'i18nly' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Created', 'i18nly' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $translations ) ) : ?>
							<tr>
								<td colspan="3"><?php echo esc_html__( 'No translations found.', 'i18nly' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $translations as $translation ) : ?>
								<tr>
									<td class="column-primary"><strong><a href="<?php echo esc_url( $this->get_edit_translation_url( (int) $translation['id'] ) ); ?>"><?php echo esc_html( (string) $translation['source_slug'] ); ?></a></strong></td>
									<td><?php echo esc_html( (string) $translation['target_language'] ); ?></td>
									<td><?php echo esc_html( (string) $translation['created_at'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
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
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of query error flag. ?>
			<?php if ( isset( $_GET['i18nly_error'] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html__( 'Plugin and target language are required.', 'i18nly' ); ?></p></div>
			<?php endif; ?>
			<div id="i18nly-translation-create" aria-live="polite">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ADD_ACTION ); ?>">
					<?php wp_nonce_field( self::ADD_ACTION ); ?>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="i18nly-plugin-selector"><?php echo esc_html__( 'Plugin', 'i18nly' ); ?></label>
								</th>
								<td>
									<select id="i18nly-plugin-selector" name="i18nly_plugin_selector" required>
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
									<select id="i18nly-target-language-selector" name="i18nly_target_language_selector" required>
										<option value=""><?php echo esc_html__( 'Select a target language', 'i18nly' ); ?></option>
										<?php foreach ( $target_languages as $target_language ) : ?>
											<option value="<?php echo esc_attr( $target_language['value'] ); ?>"<?php echo disabled( true, (bool) $target_language['disabled'], false ); ?>><?php echo esc_html( $target_language['label'] ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						</tbody>
					</table>
					<p class="submit">
						<button type="submit" class="button button-primary" id="i18nly-add-translation-submit"><?php echo esc_html__( 'Add', 'i18nly' ); ?></button>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the edit translation page.
	 *
	 * @return void
	 */
	public function render_edit_translation_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$translation_id = 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only lookup parameter.
		if ( isset( $_GET['translation_id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only lookup parameter.
			$translation_id = absint( wp_unslash( $_GET['translation_id'] ) );
		}

		$translation = $this->get_translation( $translation_id );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Edit translation', 'i18nly' ); ?></h1>
			<?php if ( ! is_array( $translation ) ) : ?>
				<p><?php echo esc_html__( 'Translation not found.', 'i18nly' ); ?></p>
			<?php else : ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Plugin', 'i18nly' ); ?></th>
							<td><?php echo esc_html( (string) $translation['source_slug'] ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Target language', 'i18nly' ); ?></th>
							<td><?php echo esc_html( (string) $translation['target_language'] ); ?></td>
						</tr>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
