<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * POT source entry extractor tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

// phpcs:disable WordPress.WP.AlternativeFunctions

/**
 * Tests source extraction from plugin PHP files.
 */
class PotSourceEntryExtractorTest extends TestCase {
	/**
	 * Extracts singular, contextual and plural strings.
	 *
	 * @return void
	 */
	public function test_extract_from_source_slug_collects_gettext_entries() {
		$plugins_root = sys_get_temp_dir() . '/i18nly-extractor-' . uniqid( '', true );
		$plugin_dir   = $plugins_root . '/sample-plugin';
		$main_file    = $plugin_dir . '/sample-plugin.php';

		mkdir( $plugin_dir, 0755, true );
		file_put_contents(
			$main_file,
			"<?php\n/* translators: Greeting shown on welcome panel. */\nprintf( __( 'Hello world', 'sample-plugin' ) );\n_x( 'Open', 'verb', 'sample-plugin' );\n_n( '%s item', '%s items', 2, 'sample-plugin' );\n"
		);

		$extractor = new \WP_I18nly\Build\PotSourceEntryExtractor( $plugins_root );
		$entries   = $extractor->extract_from_source_slug( 'sample-plugin/sample-plugin.php' );

		$this->assertCount( 3, $entries );

		$originals = array_map(
			static function ( $entry ) {
				return $entry['original'];
			},
			$entries
		);

		$this->assertContains( 'Hello world', $originals );
		$this->assertContains( 'Open', $originals );
		$this->assertContains( '%s item', $originals );

		$plural_entry = null;
		$hello_entry  = null;
		foreach ( $entries as $entry ) {
			if ( 'Hello world' === $entry['original'] ) {
				$hello_entry = $entry;
			}

			if ( '%s item' === $entry['original'] ) {
				$plural_entry = $entry;
			}
		}

		$this->assertIsArray( $plural_entry );
		$this->assertSame( '%s items', $plural_entry['plural'] );
		$this->assertIsArray( $hello_entry );
		$this->assertArrayHasKey( 'comments', $hello_entry );
		$this->assertContains( 'translators: Greeting shown on welcome panel.', $hello_entry['comments'] );

		unlink( $main_file );
		rmdir( $plugin_dir );
		rmdir( $plugins_root );
	}

	/**
	 * Extracting from a root-level plugin file must not scan sibling plugins.
	 *
	 * @return void
	 */
	public function test_extract_from_root_level_plugin_file_does_not_scan_plugins_root() {
		$plugins_root       = sys_get_temp_dir() . '/i18nly-extractor-root-' . uniqid( '', true );
		$root_plugin_file   = $plugins_root . '/hello.php';
		$sibling_plugin_dir = $plugins_root . '/akismet';
		$sibling_file       = $sibling_plugin_dir . '/akismet.php';

		mkdir( $plugins_root, 0755, true );
		mkdir( $sibling_plugin_dir, 0755, true );

		file_put_contents(
			$root_plugin_file,
			"<?php\n__( 'Hello Dolly only', 'hello' );\n"
		);

		file_put_contents(
			$sibling_file,
			"<?php\n__( 'Akismet only', 'akismet' );\n"
		);

		$extractor = new \WP_I18nly\Build\PotSourceEntryExtractor( $plugins_root );
		$entries   = $extractor->extract_from_source_slug( 'hello.php' );

		$this->assertCount( 1, $entries );
		$this->assertSame( 'Hello Dolly only', $entries[0]['original'] );

		unlink( $sibling_file );
		rmdir( $sibling_plugin_dir );
		unlink( $root_plugin_file );
		rmdir( $plugins_root );
	}

	/**
	 * Extracts JS gettext entries across direct and transpiled call forms.
	 *
	 * @return void
	 */
	public function test_extract_from_source_slug_collects_js_gettext_entries_across_supported_forms() {
		$plugins_root = sys_get_temp_dir() . '/i18nly-extractor-js-' . uniqid( '', true );
		$plugin_dir   = $plugins_root . '/sample-plugin';
		$main_file    = $plugin_dir . '/sample-plugin.php';
		$assets_dir   = $plugin_dir . '/assets/js';
		$script_file  = $assets_dir . '/editor.js';

		mkdir( $assets_dir, 0755, true );

		file_put_contents(
			$main_file,
			"<?php\n/*\nPlugin Name: Sample Plugin\n*/\n"
		);

		file_put_contents(
			$script_file,
			"( function () {\n"
			. "\tvar direct = __( 'Direct label', 'sample-plugin' );\n"
			. "\tvar member = wp.i18n._x( 'Open', 'verb', 'sample-plugin' );\n"
			. "\tvar plural = _n( '%d modified row no longer matches active filters.', '%d modified rows no longer match active filters.', hiddenRowsCount, 'sample-plugin' );\n"
			. "\tvar contextPlural = _nx( '%d file', '%d files', fileCount, 'noun', 'sample-plugin' );\n"
			. "\tvar templateLiteral = __( `Template literal label`, 'sample-plugin' );\n"
			. "\tvar webpackObject = Object( u.__ )( 'Webpack object call', 'sample-plugin' );\n"
			. "\tvar babelIndirect = (0, _i18n.__)( 'Babel indirect call', 'sample-plugin' );\n"
			. "\teval(\"__( 'Eval extracted', 'sample-plugin' );\" );\n"
			. "\tconsole.log( direct, member, plural, contextPlural, templateLiteral, webpackObject, babelIndirect );\n"
			. "}() );\n"
		);

		$extractor = new \WP_I18nly\Build\PotSourceEntryExtractor( $plugins_root );
		$entries   = $extractor->extract_from_source_slug( 'sample-plugin/sample-plugin.php' );

		$entries_by_original = array();
		foreach ( $entries as $entry ) {
			if ( isset( $entry['original'] ) && is_string( $entry['original'] ) ) {
				$entries_by_original[ $entry['original'] ] = $entry;
			}
		}

		$this->assertArrayHasKey( 'Direct label', $entries_by_original );
		$this->assertArrayHasKey( 'Open', $entries_by_original );
		$this->assertArrayHasKey( '%d modified row no longer matches active filters.', $entries_by_original );
		$this->assertArrayHasKey( '%d file', $entries_by_original );
		$this->assertArrayHasKey( 'Template literal label', $entries_by_original );
		$this->assertArrayHasKey( 'Webpack object call', $entries_by_original );
		$this->assertArrayHasKey( 'Babel indirect call', $entries_by_original );
		$this->assertArrayHasKey( 'Eval extracted', $entries_by_original );

		$plural_entry = $entries_by_original['%d modified row no longer matches active filters.'];
		$this->assertSame( '%d modified rows no longer match active filters.', $plural_entry['plural'] );

		$context_entry = $entries_by_original['%d file'];
		$this->assertSame( '%d files', $context_entry['plural'] );
		$this->assertSame( 'noun', $context_entry['context'] );

		$member_entry = $entries_by_original['Open'];
		$this->assertSame( 'verb', $member_entry['context'] );

		$references = isset( $plural_entry['references'] ) && is_array( $plural_entry['references'] )
			? $plural_entry['references']
			: array();

		$this->assertNotEmpty( $references );
		$this->assertSame( 'assets/js/editor.js', $references[0]['file'] );

		unlink( $script_file );
		rmdir( $assets_dir );
		unlink( $main_file );
		rmdir( $plugin_dir . '/assets' );
		rmdir( $plugin_dir );
		rmdir( $plugins_root );
	}

	/**
	 * Extracts extended PHP gettext signatures and format flags.
	 *
	 * @return void
	 */
	public function test_extract_from_source_slug_collects_extended_php_signatures_and_flags() {
		$plugins_root = sys_get_temp_dir() . '/i18nly-extractor-php-extended-' . uniqid( '', true );
		$plugin_dir   = $plugins_root . '/sample-plugin';
		$main_file    = $plugin_dir . '/sample-plugin.php';

		mkdir( $plugin_dir, 0755, true );

		file_put_contents(
			$main_file,
			"<?php\n"
			. "/* translators: Placeholder-rich message. */\n"
			. "esc_xml__( 'XML escaped value %s', 'sample-plugin' );\n"
			. "_n_noop( '%s noop singular', '%s noop plural', 'sample-plugin' );\n"
			. "_nx_noop( '%s noop ctx singular', '%s noop ctx plural', 'noop context', 'sample-plugin' );\n"
			. "_( 'Compat gettext call', 'sample-plugin' );\n"
			. "_c( 'Deprecated singular', 'sample-plugin' );\n"
			. "_nc( '%s deprecated singular', '%s deprecated plural', 2, 'sample-plugin' );\n"
			. "__ngettext( '%s old singular', '%s old plural', 2, 'sample-plugin' );\n"
			. "__ngettext_noop( '%s old noop singular', '%s old noop plural', 'sample-plugin' );\n"
		);

		$extractor = new \WP_I18nly\Build\PotSourceEntryExtractor( $plugins_root );
		$entries   = $extractor->extract_from_source_slug( 'sample-plugin/sample-plugin.php' );

		$entries_by_original = array();
		foreach ( $entries as $entry ) {
			if ( isset( $entry['original'] ) && is_string( $entry['original'] ) ) {
				$entries_by_original[ $entry['original'] ] = $entry;
			}
		}

		$this->assertArrayHasKey( 'XML escaped value %s', $entries_by_original );
		$this->assertArrayHasKey( '%s noop singular', $entries_by_original );
		$this->assertArrayHasKey( '%s noop ctx singular', $entries_by_original );
		$this->assertArrayHasKey( 'Compat gettext call', $entries_by_original );
		$this->assertArrayHasKey( 'Deprecated singular', $entries_by_original );
		$this->assertArrayHasKey( '%s deprecated singular', $entries_by_original );
		$this->assertArrayHasKey( '%s old singular', $entries_by_original );
		$this->assertArrayHasKey( '%s old noop singular', $entries_by_original );

		$xml_entry = $entries_by_original['XML escaped value %s'];
		$this->assertArrayHasKey( 'flags', $xml_entry );
		$this->assertContains( 'php-format', $xml_entry['flags'] );
		$this->assertArrayHasKey( 'comments', $xml_entry );
		$this->assertContains( 'translators: Placeholder-rich message.', $xml_entry['comments'] );

		$noop_ctx_entry = $entries_by_original['%s noop ctx singular'];
		$this->assertSame( '%s noop ctx plural', $noop_ctx_entry['plural'] );
		$this->assertSame( 'noop context', $noop_ctx_entry['context'] );

		unlink( $main_file );
		rmdir( $plugin_dir );
		rmdir( $plugins_root );
	}

	/**
	 * Extracts JS translator comments, format flags and sourcemap content.
	 *
	 * @return void
	 */
	public function test_extract_from_source_slug_collects_js_comments_flags_and_map_sources() {
		$plugins_root = sys_get_temp_dir() . '/i18nly-extractor-js-map-' . uniqid( '', true );
		$plugin_dir   = $plugins_root . '/sample-plugin';
		$main_file    = $plugin_dir . '/sample-plugin.php';
		$assets_dir   = $plugin_dir . '/assets/js';
		$script_file  = $assets_dir . '/bundle.js';
		$map_file     = $assets_dir . '/bundle.js.map';

		mkdir( $assets_dir, 0755, true );

		file_put_contents( $main_file, "<?php\n/*\nPlugin Name: Sample Plugin\n*/\n" );

		file_put_contents(
			$script_file,
			"/* translators: Primary JS message. */\n"
			. "const msg = __( 'JS string with %d placeholder', 'sample-plugin' );\n"
		);

		$map_payload = array(
			'version'        => 3,
			'file'           => 'bundle.js',
			'sources'        => array( 'source-a.js' ),
			'names'          => array(),
			'mappings'       => '',
			'sourcesContent' => array( "__( 'From sourcemap source', 'sample-plugin' );" ),
		);

		file_put_contents( $map_file, wp_json_encode( $map_payload ) );

		$extractor = new \WP_I18nly\Build\PotSourceEntryExtractor( $plugins_root );
		$entries   = $extractor->extract_from_source_slug( 'sample-plugin/sample-plugin.php' );

		$entries_by_original = array();
		foreach ( $entries as $entry ) {
			if ( isset( $entry['original'] ) && is_string( $entry['original'] ) ) {
				$entries_by_original[ $entry['original'] ] = $entry;
			}
		}

		$this->assertArrayHasKey( 'JS string with %d placeholder', $entries_by_original );
		$this->assertArrayHasKey( 'From sourcemap source', $entries_by_original );

		$js_entry = $entries_by_original['JS string with %d placeholder'];
		$this->assertArrayHasKey( 'comments', $js_entry );
		$this->assertContains( 'Primary JS message.', $js_entry['comments'] );
		$this->assertArrayHasKey( 'flags', $js_entry );
		$this->assertContains( 'js-format', $js_entry['flags'] );

		$map_entry  = $entries_by_original['From sourcemap source'];
		$references = isset( $map_entry['references'] ) && is_array( $map_entry['references'] ) ? $map_entry['references'] : array();
		$this->assertNotEmpty( $references );
		$this->assertSame( 'assets/js/bundle.js', $references[0]['file'] );

		unlink( $map_file );
		unlink( $script_file );
		unlink( $main_file );
		rmdir( $assets_dir );
		rmdir( $plugin_dir . '/assets' );
		rmdir( $plugin_dir );
		rmdir( $plugins_root );
	}

	/**
	 * Extracts common translatable fields from block.json and theme.json metadata.
	 *
	 * @return void
	 */
	public function test_extract_from_source_slug_collects_json_metadata_entries() {
		$plugins_root = sys_get_temp_dir() . '/i18nly-extractor-json-' . uniqid( '', true );
		$plugin_dir   = $plugins_root . '/sample-plugin';
		$main_file    = $plugin_dir . '/sample-plugin.php';
		$styles_dir   = $plugin_dir . '/styles';

		mkdir( $styles_dir, 0755, true );

		file_put_contents( $main_file, "<?php\n/*\nPlugin Name: Sample Plugin\n*/\n" );

		$block_json = array(
			'title'       => 'Sample block title',
			'description' => 'Sample block description',
			'keywords'    => array( 'alpha keyword', 'beta keyword' ),
			'styles'      => array(
				array( 'name' => 'outline', 'label' => 'Outline style' ),
			),
			'variations'  => array(
				array( 'name' => 'compact', 'title' => 'Compact variation', 'description' => 'Compact variation description' ),
			),
		);

		$theme_json = array(
			'settings' => array(
				'color'      => array(
					'palette'   => array( array( 'name' => 'Palette name', 'slug' => 'palette' ) ),
					'gradients' => array( array( 'name' => 'Gradient name', 'slug' => 'gradient' ) ),
				),
				'typography' => array(
					'fontFamilies' => array( array( 'name' => 'Theme font family', 'slug' => 'theme-font' ) ),
				),
			),
		);

		$style_variation_json = array(
			'settings' => array(
				'color' => array(
					'palette' => array( array( 'name' => 'Style variation palette', 'slug' => 'style-palette' ) ),
				),
			),
		);

		file_put_contents( $plugin_dir . '/block.json', wp_json_encode( $block_json ) );
		file_put_contents( $plugin_dir . '/theme.json', wp_json_encode( $theme_json ) );
		file_put_contents( $styles_dir . '/sunrise.json', wp_json_encode( $style_variation_json ) );

		$extractor = new \WP_I18nly\Build\PotSourceEntryExtractor( $plugins_root );
		$entries   = $extractor->extract_from_source_slug( 'sample-plugin/sample-plugin.php' );

		$entries_by_original = array();
		foreach ( $entries as $entry ) {
			if ( isset( $entry['original'] ) && is_string( $entry['original'] ) ) {
				$entries_by_original[ $entry['original'] ] = $entry;
			}
		}

		$this->assertArrayHasKey( 'Sample block title', $entries_by_original );
		$this->assertArrayHasKey( 'Sample block description', $entries_by_original );
		$this->assertArrayHasKey( 'alpha keyword', $entries_by_original );
		$this->assertArrayHasKey( 'Outline style', $entries_by_original );
		$this->assertArrayHasKey( 'Compact variation', $entries_by_original );
		$this->assertArrayHasKey( 'Compact variation description', $entries_by_original );
		$this->assertArrayHasKey( 'Palette name', $entries_by_original );
		$this->assertArrayHasKey( 'Gradient name', $entries_by_original );
		$this->assertArrayHasKey( 'Theme font family', $entries_by_original );
		$this->assertArrayHasKey( 'Style variation palette', $entries_by_original );

		$this->assertSame( 'block title', $entries_by_original['Sample block title']['context'] );
		$this->assertSame( 'color name', $entries_by_original['Palette name']['context'] );

		$style_refs = isset( $entries_by_original['Style variation palette']['references'] ) ? $entries_by_original['Style variation palette']['references'] : array();
		$this->assertNotEmpty( $style_refs );
		$this->assertSame( 'styles/sunrise.json', $style_refs[0]['file'] );

		unlink( $styles_dir . '/sunrise.json' );
		unlink( $plugin_dir . '/theme.json' );
		unlink( $plugin_dir . '/block.json' );
		unlink( $main_file );
		rmdir( $styles_dir );
		rmdir( $plugin_dir );
		rmdir( $plugins_root );
	}
}

// phpcs:enable
