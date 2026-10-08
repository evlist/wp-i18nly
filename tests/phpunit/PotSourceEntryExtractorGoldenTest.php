<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * POT source entry extractor golden test.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

/**
 * Pins the complete output of the extractor on a plugin mixing every supported source type.
 */
class PotSourceEntryExtractorGoldenTest extends TestCase {
	/**
	 * Temporary plugins root.
	 *
	 * @var string
	 */
	private $plugins_root = '';

	/**
	 * Creates the composite plugin.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->plugins_root = sys_get_temp_dir() . '/i18nly-golden-' . uniqid( '', true );

		$files = array(
			'composite/composite.php'               => <<<'PHPCODE'
<?php
/*
Plugin Name: Composite Plugin
*/

// translators: %s is a user name.
printf( esc_html__( 'Welcome, %s', 'composite' ), $name );
__( 'Shared string', 'composite' );
_e( 'Echoed text', 'composite' );
_x( 'Post', 'noun', 'composite' );
_ex( 'Archive', 'menu', 'composite' );
_n( '%d comment', '%d comments', $count, 'composite' );
_nx( '%d step', '%d steps', $n, 'workflow', 'composite' );
esc_html_x( 'Back', 'navigation', 'composite' );
esc_attr_e( 'Attribute text', 'composite' );
__( $dynamic, 'composite' );
__( "Double quoted \"escaped\"\n", 'composite' );
$object->__( 'Method call', 'composite' );
\__( 'Namespaced call', 'composite' );
__( '', 'composite' );
PHPCODE
			,
			'composite/includes/helpers.php'        => <<<'PHPCODE'
<?php
/* translators: 1: first value, 2: second value. */
$message = sprintf( __( '%1$s and %2$s', 'composite' ), __( 'Inner string', 'composite' ), __( 'Shared string', 'composite' ) );

/**
 * translators: Docblock style comment.
 */
_n_noop( '%s noop singular', '%s noop plural', 'composite' );
_nx_noop( '%s noop ctx singular', '%s noop ctx plural', 'noop context', 'composite' );
esc_xml__( 'XML escaped', 'composite' );
esc_xml_e( 'XML echoed', 'composite' );
esc_xml_x( 'XML context', 'xml', 'composite' );
__ngettext( '%s old singular', '%s old plural', 2, 'composite' );
__ngettext_noop( '%s old noop singular', '%s old noop plural', 'composite' );
_c( 'Deprecated singular', 'composite' );
_nc( '%s deprecated singular', '%s deprecated plural', 2, 'composite' );
_( 'Compat gettext call', 'composite' );
ESC_HTML__( 'Upper case function', 'composite' );
PHPCODE
			,
			'composite/assets/js/app.js'            => <<<'JSCODE'
// translators: Label of the main button.
const direct = __( 'Direct label', 'composite' );
/* translators: Dialog title. */
const member = wp.i18n._x( 'Open', 'verb', 'composite' );
const plural = _n( '%d row', '%d rows', rows, 'composite' );
const contextPlural = _nx( '%d file', '%d files', files, 'noun', 'composite' );
const template = __( `Template literal label`, 'composite' );
const webpack = Object( u.__ )( 'Webpack object call', 'composite' );
const babel = (0, _i18n.__)( 'Babel indirect call', 'composite' );
const shared = __( 'Shared string', 'composite' );
const dynamic = __( label, 'composite' );
eval("__( 'Eval extracted', 'composite' );");
console.log( direct, member, plural, contextPlural, template, webpack, babel, shared, dynamic );
JSCODE
			,
			'composite/assets/js/view.tsx'          => <<<'JSCODE'
type Props = { count: number };

export function View( { count }: Props ) {
	return (
		<section title={ __( 'Section title', 'composite' ) }>
			{ /* translators: Number of items. */ }
			<p>{ _n( '%d item', '%d items', count, 'composite' ) }</p>
		</section>
	);
}
JSCODE
			,
			'composite/assets/js/bundle.js.map'     => json_encode(
				array(
					'version'        => 3,
					'file'           => 'bundle.js',
					'sources'        => array( 'source-a.js', 'source-b.js' ),
					'names'          => array(),
					'mappings'       => '',
					'sourcesContent' => array(
						"// translators: From source map.\n__( 'From sourcemap source', 'composite' );\n__( 'Shared string', 'composite' );",
						"_n( '%d mapped', '%d mapped items', n, 'composite' );",
					),
				)
			),
			'composite/block.json'                  => json_encode(
				array(
					'title'       => 'Composite block',
					'description' => 'Composite block description',
					'keywords'    => array( 'alpha keyword', 'beta keyword' ),
					'styles'      => array(
						array(
							'name'  => 'outline',
							'label' => 'Outline style',
						),
					),
					'variations'  => array(
						array(
							'name'        => 'compact',
							'title'       => 'Compact variation',
							'description' => 'Compact variation description',
						),
					),
				)
			),
			'composite/theme.json'                  => json_encode(
				array(
					'settings' => array(
						'color'      => array(
							'palette' => array(
								array(
									'name' => 'Palette name',
									'slug' => 'palette',
								),
							),
						),
						'typography' => array(
							'fontSizes' => array(
								array(
									'name' => 'Theme font size',
									'slug' => 'theme-size',
								),
							),
						),
					),
					'title'    => 'Theme title',
				)
			),
			'composite/styles/dark.json'            => json_encode(
				array(
					'title'    => 'Dark style',
					'settings' => array(
						'color' => array(
							'palette' => array(
								array(
									'name' => 'Dark palette name',
									'slug' => 'dark',
								),
							),
						),
					),
				)
			),
			'composite/package.json'                => json_encode( array( 'title' => 'Not translatable' ) ),
			'composite/resources/views/page.blade.php' => <<<'BLADE'
<h1>{{ __( 'Blade heading', 'composite' ) }}</h1>
{!! esc_html__( 'Shared string', 'composite' ) !!}
<x-alert :label="__( 'Blade component label', 'composite' )" />
@php
echo _n( '%d blade item', '%d blade items', 2, 'composite' );
@endphp
BLADE
			,
		);

		foreach ( $files as $relative_path => $contents ) {
			$path = $this->plugins_root . '/' . $relative_path;

			if ( ! is_dir( dirname( $path ) ) ) {
				mkdir( dirname( $path ), 0755, true );
			}

			file_put_contents( $path, $contents . "\n" );
		}
	}

	/**
	 * Removes the temporary plugin.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->plugins_root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}

		rmdir( $this->plugins_root );
	}

	/**
	 * Keeps the whole extraction output unchanged.
	 *
	 * The expected output was captured from the extractor before it was split into collaborators.
	 * It pins current behavior, limits included: fully qualified PHP calls such as `\__()` and
	 * TypeScript files with type annotations yield no entry.
	 *
	 * @return void
	 */
	public function test_extraction_output_on_a_composite_plugin_is_stable() {
		$entries = ( new \WP_I18nly\Build\PotSourceEntryExtractor( $this->plugins_root ) )->extract_from_source_slug( 'composite/composite.php' );

		$this->assertSame( require __DIR__ . '/fixtures/pot-extractor-composite-expected.php', $entries );
	}
}
