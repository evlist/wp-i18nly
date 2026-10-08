<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Typed script call scanner tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\Build\JsGettextExtractor;
use WP_I18nly\Build\TypedScriptCallScanner;

/**
 * Tests the extraction of gettext calls from code the parser cannot read.
 */
class TypedScriptCallScannerTest extends TestCase {
	/**
	 * Scans a code with the usual gettext functions.
	 *
	 * @param string $code Code.
	 * @return array<int, array{name: string, args: string[], line: int}>
	 */
	private function scan( $code ) {
		return ( new TypedScriptCallScanner() )->scan(
			$code,
			static function ( $name ) {
				return in_array( $name, array( '__', '_x', '_n', '_nx' ), true );
			}
		);
	}

	/**
	 * Finds calls, with literal arguments, lines and member calls.
	 *
	 * @return void
	 */
	public function test_finds_calls_with_their_literal_arguments() {
		$calls = $this->scan( "const a: string = __( 'One', 'd' );\nconst b = wp.i18n._x( \"Two\", 'ctx', 'd' );\nconst c = _n( `%d row`, `%d rows`, count, 'd' );\n" );

		$this->assertSame(
			array(
				array(
					'name' => '__',
					'args' => array( 'One', 'd' ),
					'line' => 1,
				),
				array(
					'name' => '_x',
					'args' => array( 'Two', 'ctx', 'd' ),
					'line' => 2,
				),
				array(
					'name' => '_n',
					'args' => array( '%d row', '%d rows', '', 'd' ),
					'line' => 3,
				),
			),
			$calls
		);
	}

	/**
	 * Escapes are decoded, other expressions give empty arguments, and template expressions are not literals.
	 *
	 * @return void
	 */
	public function test_decodes_escapes_and_ignores_expressions() {
		$calls = $this->scan( "__( 'It\\'s\\n\\u00e9', 'd' ); __( variable, 'd' ); __( `Hello \${name}`, 'd' ); __( 'a' + 'b', 'd' ); _x( fn( 1, 2 ), 'c', 'd' );" );

		$this->assertSame( array( "It's\n\u{e9}", 'd' ), $calls[0]['args'] );
		$this->assertSame( array( '', 'd' ), $calls[1]['args'] );
		$this->assertSame( array( '', 'd' ), $calls[2]['args'] );
		$this->assertSame( array( '', 'd' ), $calls[3]['args'] );
		$this->assertSame( array( '', 'c', 'd' ), $calls[4]['args'] );
	}

	/**
	 * Calls in comments and strings, declarations, and other functions are ignored.
	 *
	 * @return void
	 */
	public function test_ignores_comments_strings_declarations_and_other_functions() {
		$calls = $this->scan( "// __( 'comment', 'd' )\n/* __( 'block', 'd' ) */\nconst s = \"__( 'in string', 'd' )\";\nfunction __( text: string, domain: string ): string { return text; }\nother( 'x' );\n__( 'real', 'd' );" );

		$this->assertCount( 1, $calls );
		$this->assertSame( 'real', $calls[0]['args'][0] );
		$this->assertSame( 6, $calls[0]['line'] );
	}

	/**
	 * A stray apostrophe does not hide the following lines.
	 *
	 * @return void
	 */
	public function test_a_stray_apostrophe_does_not_swallow_the_next_lines() {
		$calls = $this->scan( "<p>Don't panic</p>\n<b>{ __( 'Safe', 'd' ) }</b>" );

		$this->assertCount( 1, $calls );
		$this->assertSame( 'Safe', $calls[0]['args'][0] );
	}

	/**
	 * The extractor falls back to the scanner on TypeScript with types, keeping translator comments.
	 *
	 * @return void
	 */
	public function test_extractor_reads_typed_code() {
		$entries = ( new JsGettextExtractor() )->extract_from_code(
			"type Props = { count: number };\n\nexport function View( { count }: Props ) {\n\t// translators: Number of items.\n\treturn _n( '%d item', '%d items', count, 'd' );\n}\n",
			'src/view.ts'
		);

		$this->assertCount( 1, $entries );
		$this->assertSame( '%d item', $entries[0]['original'] );
		$this->assertSame( '%d items', $entries[0]['plural'] );
		$this->assertSame( array( 'Number of items.' ), $entries[0]['comments'] );
		$this->assertSame( 5, $entries[0]['references'][0]['line'] );
	}
}
