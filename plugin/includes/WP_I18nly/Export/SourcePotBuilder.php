<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * POT file of the installed version of a plugin.
 *
 * @package I18nly
 */

namespace WP_I18nly\Export;

use WP_I18nly\Build\PotGenerator;
use WP_I18nly\Build\PotSourceEntryExtractor;
use WP_I18nly\Support\PluginMetadataProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the POT file matching the installed version of the plugin, which may be more recent than the one the plugin ships.
 */
class SourcePotBuilder {
	/**
	 * Plugins root directory, empty for the WordPress one.
	 *
	 * @var string
	 */
	private $plugins_root;

	/**
	 * Constructor.
	 *
	 * @param string $plugins_root Optional plugins root directory.
	 */
	public function __construct( $plugins_root = '' ) {
		$this->plugins_root = (string) $plugins_root;
	}

	/**
	 * Builds the POT file.
	 *
	 * @param string $source_slug Plugin source slug.
	 * @return array<string, string> Contents indexed by file name; empty when the plugin has no translatable string.
	 */
	public function build( $source_slug ) {
		$metadata    = new PluginMetadataProvider();
		$text_domain = $metadata->resolve_text_domain( $source_slug );
		$entries     = ( new PotSourceEntryExtractor( $this->plugins_root ) )->extract_from_source_slug( $source_slug );

		if ( empty( $entries ) ) {
			return array();
		}

		$headers                = $metadata->build_pot_header_overrides( $source_slug, $text_domain );
		$headers['X-Generator'] = 'I18nly ' . ( defined( 'I18NLY_VERSION' ) ? I18NLY_VERSION : '' );

		return array(
			sanitize_file_name( $text_domain . '.pot' ) => ( new PotGenerator() )->generate_string( $text_domain, $entries, $headers ),
		);
	}
}
