<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation file export tests.
 *
 * @package I18nly
 */

use Gettext\Loader\MoLoader;
use Gettext\Loader\PoLoader;
use PHPUnit\Framework\TestCase;
use WP_I18nly\Export\TranslationCatalogBuilder;
use WP_I18nly\Export\TranslationFileExporter;

/**
 * Tests the catalog built from translation rows and the PO and MO files generated from it.
 */
class TranslationFileExporterTest extends TestCase {
	/**
	 * Polish plural expression, three forms.
	 */
	private const POLISH = '((n == 1) ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : 2))';

	/**
	 * Builds a row.
	 *
	 * @param string            $msgid Singular.
	 * @param array<int,string> $forms Translated forms.
	 * @param string            $plural Plural.
	 * @param string            $context Context.
	 * @param string            $status Status of the translated forms.
	 * @return array<string, mixed>
	 */
	private function row( $msgid, array $forms, $plural = '', $context = '', $status = 'validated' ) {
		$translations = array();

		foreach ( $forms as $index => $text ) {
			$translations[] = array(
				'form_index'  => $index,
				'translation' => $text,
				'status'      => $status,
			);
		}

		return array(
			'msgctxt'            => $context,
			'msgid'              => $msgid,
			'msgid_plural'       => $plural,
			'translator_comment' => '',
			'translations'       => $translations,
		);
	}

	/**
	 * Builds a catalog for the tests.
	 *
	 * @param bool $complete_plurals_only Whether to leave out partly translated plurals.
	 * @return \Gettext\Translations
	 */
	private function catalog( $complete_plurals_only = false ) {
		$rows = array(
			$this->row( 'Hello', array( 'Bonjour <b>%s</b>' . "\n" . 'ligne 2' ) ),
			$this->row( 'Open', array( 'Ouvrir' ), '', 'verb' ),
			$this->row( '%d file', array( '%d plik', '%d pliki', '%d plików' ), '%d files' ),
			$this->row( '%d tag', array( '%d tag', '', '' ), '%d tags' ),
			$this->row( 'Untranslated', array( '' ) ),
		);

		return ( new TranslationCatalogBuilder() )->build( $rows, 'pl_PL', 'sample', 3, self::POLISH, array( 'Project-Id-Version' => 'Sample 1.0' ), array( 'complete_plurals_only' => $complete_plurals_only ) );
	}

	/**
	 * Untranslated entries are left out and headers come from the plugin plural data.
	 *
	 * @return void
	 */
	public function test_catalog_has_translated_entries_and_plugin_plural_header() {
		$catalog = $this->catalog();

		$this->assertCount( 4, $catalog );
		$this->assertSame( 'pl_PL', $catalog->getHeaders()->getLanguage() );
		$this->assertSame( array( 3, self::POLISH ), $catalog->getHeaders()->getPluralForm() );
		$this->assertSame( 'Sample 1.0', $catalog->getHeaders()->get( 'Project-Id-Version' ) );
	}

	/**
	 * The PO file keeps text exactly and can be read back.
	 *
	 * @return void
	 */
	public function test_po_round_trip_keeps_texts_context_and_plurals() {
		$po     = ( new TranslationFileExporter() )->generate( $this->catalog(), 'po' );
		$loaded = ( new PoLoader() )->loadString( $po );

		$this->assertStringContainsString( 'Plural-Forms: nplurals=3; plural=' . self::POLISH . ';', $po );
		$this->assertSame( "Bonjour <b>%s</b>\nligne 2", $loaded->find( null, 'Hello' )->getTranslation() );
		$this->assertSame( 'Ouvrir', $loaded->find( 'verb', 'Open' )->getTranslation() );

		$plural = $loaded->find( null, '%d file' );

		$this->assertSame( '%d plik', $plural->getTranslation() );
		$this->assertSame( array( '%d pliki', '%d plików' ), $plural->getPluralTranslations() );
	}

	/**
	 * The MO file keeps the same data and leaves out partly translated plurals.
	 *
	 * @return void
	 */
	public function test_mo_round_trip_and_incomplete_plurals() {
		$mo     = ( new TranslationFileExporter() )->generate( $this->catalog( true ), 'mo' );
		$loaded = ( new MoLoader() )->loadString( $mo );

		$this->assertNull( $loaded->find( null, '%d tag' ) );
		$this->assertSame( 'Ouvrir', $loaded->find( 'verb', 'Open' )->getTranslation() );
		$this->assertSame( array( '%d pliki', '%d plików' ), $loaded->find( null, '%d file' )->getPluralTranslations() );
		$this->assertSame( array( 3, self::POLISH ), $loaded->getHeaders()->getPluralForm() );
	}

	/**
	 * Formats and names.
	 *
	 * @return void
	 */
	public function test_formats_and_file_names() {
		$this->assertTrue( TranslationFileExporter::is_supported_format( 'mo' ) );
		$this->assertFalse( TranslationFileExporter::is_supported_format( 'php' ) );
		$this->assertSame( 'sample-pl_PL.mo', TranslationFileExporter::get_file_name( 'sample', 'pl_PL', 'mo' ) );
	}

	/**
	 * Entries having a translation that is not validated are counted, ignoring empty forms.
	 *
	 * @return void
	 */
	public function test_counts_doubtful_entries() {
		$builder = new TranslationCatalogBuilder();
		$rows    = array(
			$this->row( 'A', array( 'a' ) ),
			$this->row( 'B', array( 'b' ), '', '', 'draft_ai' ),
			$this->row( 'C', array( 'c' ), '', '', 'suspect' ),
			$this->row( 'D', array( '' ), '', '', 'draft' ),
		);

		$this->assertSame( 2, $builder->count_unvalidated( $rows ) );
	}

	/**
	 * Doubtful entries can be left out.
	 *
	 * @return void
	 */
	public function test_doubtful_entries_can_be_left_out() {
		$rows    = array( $this->row( 'A', array( 'a' ) ), $this->row( 'B', array( 'b' ), '', '', 'draft_ai' ) );
		$catalog = ( new TranslationCatalogBuilder() )->build( $rows, 'fr_FR', 'sample', 2, '(n > 1)', array(), array( 'include_unvalidated' => false ) );

		$this->assertCount( 1, $catalog );
		$this->assertNotNull( $catalog->find( null, 'A' ) );
	}

	/**
	 * Doubtful entries can be included and flagged fuzzy in the PO file only.
	 *
	 * @return void
	 */
	public function test_doubtful_entries_can_be_flagged_fuzzy() {
		$rows    = array( $this->row( 'A', array( 'a' ) ), $this->row( 'B', array( 'b' ), '', '', 'draft' ) );
		$catalog = ( new TranslationCatalogBuilder() )->build( $rows, 'fr_FR', 'sample', 2, '(n > 1)', array(), array( 'fuzzy_unvalidated' => true ) );
		$po      = ( new TranslationFileExporter() )->generate( $catalog, 'po' );

		$this->assertCount( 2, $catalog );
		$this->assertSame( array(), $catalog->find( null, 'A' )->getFlags()->toArray() );
		$this->assertSame( array( 'fuzzy' ), $catalog->find( null, 'B' )->getFlags()->toArray() );
		$this->assertStringContainsString( "#, fuzzy\nmsgid \"B\"", $po );
	}
}
