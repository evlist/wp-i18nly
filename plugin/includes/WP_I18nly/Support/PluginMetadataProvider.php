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
	 * Returns installed plugins and themes as options for selector, indexed by source identifier.
	 *
	 * @return array<string, string>
	 */
	public function get_plugin_options() {
		$groups = $this->get_source_groups();

		return $groups['plugins'] + $groups['themes'];
	}

	/**
	 * Returns the installed plugins and themes, in two groups of options indexed by source identifier.
	 *
	 * @return array{plugins: array<string, string>, themes: array<string, string>}
	 */
	public function get_source_groups() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = array();

		foreach ( get_plugins() as $plugin_file => $plugin_data ) {
			if ( ! empty( $plugin_data['Name'] ) ) {
				$plugins[ SourceReference::plugin( $plugin_file )->get_identifier() ] = (string) $plugin_data['Name'];
			}
		}

		$themes = array();

		if ( function_exists( 'wp_get_themes' ) ) {
			foreach ( wp_get_themes() as $stylesheet => $theme ) {
				$name = (string) $theme->get( 'Name' );

				if ( '' !== $name ) {
					$themes[ SourceReference::theme( $stylesheet )->get_identifier() ] = $name;
				}
			}
		}

		asort( $plugins );
		asort( $themes );

		return array(
			'plugins' => $plugins,
			'themes'  => $themes,
		);
	}

	/**
	 * Returns the header data of a plugin or of a theme, with the keys of get_plugins().
	 *
	 * @param string $source_slug Source identifier.
	 * @return array<string, string>
	 */
	public function get_plugin_data( $source_slug ) {
		$source = SourceReference::from_identifier( $source_slug );

		if ( $source->is_theme() ) {
			return $this->get_theme_data( $source->get_key() );
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

		if ( isset( $plugins[ $source->get_key() ] ) && is_array( $plugins[ $source->get_key() ] ) ) {
			return array_map( 'strval', $plugins[ $source->get_key() ] );
		}

		return array();
	}

	/**
	 * Returns the header data of a theme, with the keys of get_plugins() (the theme URI is the "PluginURI").
	 *
	 * @param string $stylesheet Theme folder name.
	 * @return array<string, string>
	 */
	private function get_theme_data( $stylesheet ) {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return array();
		}

		$theme = wp_get_theme( $stylesheet );

		if ( ! is_object( $theme ) || ! $theme->exists() ) {
			return array();
		}

		return array(
			'Name'       => (string) $theme->get( 'Name' ),
			'Version'    => (string) $theme->get( 'Version' ),
			'TextDomain' => (string) $theme->get( 'TextDomain' ),
			'DomainPath' => (string) $theme->get( 'DomainPath' ),
			'PluginURI'  => (string) $theme->get( 'ThemeURI' ),
			'AuthorURI'  => (string) $theme->get( 'AuthorURI' ),
		);
	}

	/**
	 * Infers text domain from source slug.
	 *
	 * @param string $source_slug Source slug.
	 * @return string
	 */
	public function infer_text_domain( $source_slug ) {
		$parts = explode( '/', trim( SourceReference::from_identifier( $source_slug )->get_key(), '/\\' ) );

		if ( empty( $parts[0] ) ) {
			return 'i18nly';
		}

		return sanitize_text_field( (string) $parts[0] );
	}

	/**
	 * Returns the text domain a plugin declares in its header, or the one inferred from its folder.
	 *
	 * Translation files must be named after the real text domain to be found by WordPress.
	 *
	 * @param string $source_slug Source slug.
	 * @return string
	 */
	public function resolve_text_domain( $source_slug ) {
		$plugin_data = $this->get_plugin_data( $source_slug );

		if ( ! empty( $plugin_data['TextDomain'] ) ) {
			return sanitize_key( $plugin_data['TextDomain'] );
		}

		return $this->infer_text_domain( $source_slug );
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
