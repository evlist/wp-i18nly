<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Plugin data removal.
 *
 * @package I18nly
 */

namespace WP_I18nly\Support;

use WP_I18nly\Storage\SourceSchemaManager;

defined( 'ABSPATH' ) || exit;

/**
 * Removes the data of the plugin when it is deleted from the Plugins screen.
 *
 * The translations are the work of the translators, so nothing is removed unless the administrator
 * ticked "Delete all data when the plugin is deleted" in Settings > Translations.
 */
class PluginUninstaller {
	/**
	 * Option storing the settings, including the delete flag.
	 */
	public const SETTINGS_OPTION = 'i18nly_translation_settings';

	/**
	 * Settings key of the delete flag.
	 */
	public const DELETE_FLAG = 'delete_data_on_uninstall';

	/**
	 * Translation post type.
	 */
	private const POST_TYPE = 'i18nly_translation';

	/**
	 * Options removed with the data.
	 *
	 * @var string[]
	 */
	private const OPTIONS = array( self::SETTINGS_OPTION, 'i18nly_deepl_usage_status_cache' );

	/**
	 * Removes the data of every site where the delete flag is set.
	 *
	 * @return void
	 */
	public function run() {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {
				switch_to_blog( (int) $site_id );
				$this->uninstall_current_site();
				restore_current_blog();
			}

			return;
		}

		$this->uninstall_current_site();
	}

	/**
	 * Tells whether the administrator asked to delete the data of the current site.
	 *
	 * @return bool
	 */
	private function is_deletion_requested() {
		$settings = get_option( self::SETTINGS_OPTION, array() );

		return is_array( $settings ) && ! empty( $settings[ self::DELETE_FLAG ] );
	}

	/**
	 * Removes the translations, tables, options and throttle files of the current site.
	 *
	 * @return void
	 */
	private function uninstall_current_site() {
		if ( ! $this->is_deletion_requested() ) {
			return;
		}

		$translation_ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $translation_ids as $translation_id ) {
			wp_delete_post( (int) $translation_id, true );
		}

		( new SourceSchemaManager() )->drop_tables();

		foreach ( self::OPTIONS as $option ) {
			delete_option( $option );
		}

		foreach ( (array) glob( rtrim( sys_get_temp_dir(), '/\\' ) . '/i18nly_throttle_*.lock' ) as $lock_file ) {
			wp_delete_file( $lock_file );
		}
	}
}
