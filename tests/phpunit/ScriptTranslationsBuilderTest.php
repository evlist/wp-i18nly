<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Script translations builder tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\Export\ScriptTranslationsBuilder;
use WP_I18nly\Export\TranslationFileExporter;

/**
 * Tests the JSON files of the scripts.
 */
class ScriptTranslationsBuilderTest extends TestCase {
	/**
	 * Builds a row.
	 *
	 * @param string                         $msgid Singular.
	 * @param array<int, string>             $forms Translated forms.
	 * @param array<string, array<int, int>> $references Source references.
	 * @param string                         $plural Plural.
	 * @param string                         $context Context.
	 * @param string                         $status Status.
	 * @return array<string, mixed>
	 */
	private function row( $msgid, array $forms, array $references, $plural = '', $context = '', $status = 'validated' ) {
		$translations = array();

		foreach ( $forms as $index => $text ) {
			$translations[] = array(
				'form_index'  => $index,
				'translation' => $text,
				'status'      => $status,
			);
		}

		return array(
			'msgctxt'      => $context,
			'msgid'        => $msgid,
			'msgid_plural' => $plural,
			'references'   => $references,
			'translations' => $translations,
		);
	}

	/**
	 * Builds the files for a set of rows.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @param bool                             $include_unvalidated Whether to include doubtful translations.
	 * @return array<string, string>
	 */
	private function files( array $rows, $include_unvalidated = false ) {
		return ( new ScriptTranslationsBuilder() )->build( $rows, 'fr_FR', 'sample', 2, '(n > 1)', $include_unvalidated, 'I18nly test' );
	}

	/**
	 * Script paths are unminified and relative, and file names use their MD5.
	 *
	 * @return void
	 */
	public function test_script_paths_and_file_names() {
		$this->assertSame( 'assets/js/app.js', ScriptTranslationsBuilder::normalize_script_path( 'assets/js/app.min.js' ) );
		$this->assertSame( 'assets/js/app.js', ScriptTranslationsBuilder::normalize_script_path( '/assets\\js/app.js' ) );
		$this->assertSame( '', ScriptTranslationsBuilder::normalize_script_path( 'includes/helpers.php' ) );
		$this->assertSame( '', ScriptTranslationsBuilder::normalize_script_path( 'assets/js/app.js.map' ) );
		$this->assertSame( 'sample-fr_FR-' . md5( 'assets/js/app.js' ) . '.json', ScriptTranslationsBuilder::get_file_name( 'sample', 'fr_FR', 'assets/js/app.js' ) );
	}

	/**
	 * Each script gets its own strings, and PHP-only strings go nowhere.
	 *
	 * @return void
	 */
	public function test_one_file_per_script_with_its_own_strings() {
		$files = $this->files(
			array(
				$this->row(
					'Shared',
					array( 'Partagé' ),
					array(
						'assets/js/app.js' => array( 3 ),
						'assets/js/other.js' => array( 9 ),
						'includes/a.php' => array( 1 ),
					)
				),
				$this->row( 'Only app', array( 'Seulement app' ), array( 'assets/js/app.min.js' => array( 5 ) ) ),
				$this->row( 'PHP only', array( 'PHP' ), array( 'includes/a.php' => array( 2 ) ) ),
				$this->row( 'No reference', array( 'Rien' ), array() ),
			)
		);

		$app   = json_decode( $files[ ScriptTranslationsBuilder::get_file_name( 'sample', 'fr_FR', 'assets/js/app.js' ) ], true );
		$other = json_decode( $files[ ScriptTranslationsBuilder::get_file_name( 'sample', 'fr_FR', 'assets/js/other.js' ) ], true );

		$this->assertCount( 2, $files );
		$this->assertSame( array( 'Partagé' ), $app['locale_data']['sample']['Shared'] );
		$this->assertSame( array( 'Seulement app' ), $app['locale_data']['sample']['Only app'] );
		$this->assertArrayNotHasKey( 'PHP only', $app['locale_data']['sample'] );
		$this->assertArrayNotHasKey( 'Only app', $other['locale_data']['sample'] );
		$this->assertSame( 'assets/js/app.js', $app['source'] );
		$this->assertSame( 'sample', $app['domain'] );
	}

	/**
	 * The document follows the Jed format WordPress expects.
	 *
	 * @return void
	 */
	public function test_jed_document_with_header_context_and_plural() {
		$files = $this->files(
			array(
				$this->row( 'Open', array( 'Ouvrir' ), array( 'a.js' => array( 1 ) ), '', 'verb' ),
				$this->row( '%d item', array( '%d élément', '%d éléments' ), array( 'a.js' => array( 2 ) ), '%d items' ),
			)
		);

		$document = json_decode( reset( $files ), true );
		$data     = $document['locale_data']['sample'];

		$this->assertSame( 'I18nly test', $document['generator'] );
		$this->assertSame(
			array(
				'domain'       => 'sample',
				'lang'         => 'fr_FR',
				'plural-forms' => 'nplurals=2; plural=(n > 1);',
			),
			$data['']
		);
		$this->assertSame( array( 'Ouvrir' ), $data["verb\x04Open"] );
		$this->assertSame( array( '%d élément', '%d éléments' ), $data['%d item'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}\+0000$/', $document['translation-revision-date'] );
	}

	/**
	 * Untranslated and partly translated plural entries are left out, and scripts without strings get no file.
	 *
	 * @return void
	 */
	public function test_leaves_out_untranslated_and_incomplete_entries() {
		$files = $this->files(
			array(
				$this->row( 'Empty', array( '' ), array( 'a.js' => array( 1 ) ) ),
				$this->row( '%d tag', array( '%d tag', '' ), array( 'b.js' => array( 1 ) ), '%d tags' ),
			)
		);

		$this->assertSame( array(), $files );
	}

	/**
	 * Doubtful translations are included only when asked.
	 *
	 * @return void
	 */
	public function test_doubtful_translations_follow_the_choice() {
		$rows = array( $this->row( 'Draft', array( 'Brouillon' ), array( 'a.js' => array( 1 ) ), '', '', 'draft_ai' ) );

		$this->assertSame( array(), $this->files( $rows, false ) );
		$this->assertCount( 1, $this->files( $rows, true ) );
	}

	/**
	 * Files are packed into a readable archive.
	 *
	 * @return void
	 */
	public function test_zip_archive_contains_the_files() {
		if ( ! TranslationFileExporter::can_create_archives() ) {
			$this->markTestSkipped( 'ZipArchive is not available.' );
		}

		$zip_contents = ( new TranslationFileExporter() )->zip(
			array(
				'one.json' => '{"a":1}',
				'two.json' => '{"b":2}',
			)
		);
		$path         = tempnam( sys_get_temp_dir(), 'zip' );

		file_put_contents( $path, $zip_contents ); // phpcs:ignore

		$archive = new ZipArchive();

		$this->assertTrue( $archive->open( $path ) );
		$this->assertSame( 2, $archive->count() );
		$this->assertSame( '{"a":1}', $archive->getFromName( 'one.json' ) );

		$archive->close();
		unlink( $path ); // phpcs:ignore
		$this->assertNull( ( new TranslationFileExporter() )->zip( array() ) );
	}
}
