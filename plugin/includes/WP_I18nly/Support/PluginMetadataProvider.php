<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Plugin metadata provider.
 *
 * @package I18nly
 */

namespace WP_I18nly\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Provides access to installed plugin metadata and POT header generation.
 */
class PluginMetadataProvider {
	/**
	 * Returns installed plugins as options for selector.
	 *
	 * @return array<string, string>
	 */
	public function get_plugin_options() {
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
	 * Returns source plugin metadata from installed plugins list.
	 *
	 * @param string $source_slug Source slug.
	 * @return array<string, string>
	 */
	public function get_plugin_data( $source_slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

		if ( isset( $plugins[ $source_slug ] ) && is_array( $plugins[ $source_slug ] ) ) {
			return array_map( 'strval', $plugins[ $source_slug ] );
		}

		return array();
	}

	/**
	 * Infers text domain from source slug.
	 *
	 * @param string $source_slug Source slug.
	 * @return string
	 */
	public function infer_text_domain( $source_slug ) {
		$parts = explode( '/', trim( (string) $source_slug, '/\\' ) );

		if ( empty( $parts[0] ) ) {
			return 'i18nly';
		}

		return sanitize_text_field( (string) $parts[0] );
	}

	/**
	 * Builds POT header overrides from source plugin metadata.
	 *
	 * @param string $source_slug Source slug.
	 * @param string $text_domain Text domain.
	 * @return array<string, string>
	 */
	public function build_pot_header_overrides( $source_slug, $text_domain ) {
		$plugin_data = $this->get_plugin_data( $source_slug );

		$project_id_version = $text_domain;
		if ( ! empty( $plugin_data['Name'] ) && ! empty( $plugin_data['Version'] ) ) {
			$project_id_version = sanitize_text_field( $plugin_data['Name'] . ' ' . $plugin_data['Version'] );
		} elseif ( ! empty( $plugin_data['Version'] ) ) {
			$project_id_version = sanitize_text_field( $text_domain . ' ' . $plugin_data['Version'] );
		}

		$bugs_url = '';
		if ( ! empty( $plugin_data['PluginURI'] ) ) {
			$bugs_url = esc_url_raw( (string) $plugin_data['PluginURI'] );
		} elseif ( ! empty( $plugin_data['AuthorURI'] ) ) {
			$bugs_url = esc_url_raw( (string) $plugin_data['AuthorURI'] );
		}

		return array(
			'Project-Id-Version'   => (string) $project_id_version,
			'Report-Msgid-Bugs-To' => (string) $bugs_url,
		);
	}
}
