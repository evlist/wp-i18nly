<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation editor rows provider tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.Files.OneObjectStructurePerFile

/**
 * Tests assembly of editor rows for one translation.
 */
class TranslationEditorRowsProviderTest extends TestCase {
	/**
	 * Ensures targets, then returns rows enriched with plural metadata.
	 *
	 * @return void
	 */
	public function test_get_rows_ensures_targets_and_adds_plural_metadata() {
		$repository = new I18nly_Test_Recording_Rows_Repository();
		$provider   = new \WP_I18nly\LinguisticResources\TranslationEditorRowsProvider( $repository, 'en_US' );

		$rows = $provider->get_rows( 42, 'akismet/akismet.php', 'fr_FR' );

		$this->assertCount( 1, $repository->ensure_calls );
		$this->assertSame( 42, $repository->ensure_calls[0]['translation_id'] );
		$this->assertSame( 'akismet/akismet.php', $repository->ensure_calls[0]['source_slug'] );
		$this->assertSame( 'fr_FR', $repository->ensure_calls[0]['target_locale'] );
		$this->assertGreaterThan( 0, $repository->ensure_calls[0]['plural_forms_count'] );

		$this->assertCount( 1, $rows );
		$this->assertSame( 61, $rows[0]['source_entry_id'] );
		$this->assertNotEmpty( $rows[0]['forms'] );
		$this->assertCount( count( $rows[0]['forms'] ), $rows[0]['form_labels'] );
	}

	/**
	 * Returns no rows when the repository returns none.
	 *
	 * @return void
	 */
	public function test_get_rows_returns_empty_array_without_repository_rows() {
		$repository       = new I18nly_Test_Recording_Rows_Repository();
		$repository->rows = array();
		$provider         = new \WP_I18nly\LinguisticResources\TranslationEditorRowsProvider( $repository, 'en_US' );

		$this->assertSame( array(), $provider->get_rows( 42, 'akismet/akismet.php', 'fr_FR' ) );
	}
}

/**
 * Repository double recording ensure calls and returning fixed rows.
 */
class I18nly_Test_Recording_Rows_Repository extends \WP_I18nly\LinguisticResources\TranslationResourceRepository {
	/**
	 * Recorded ensure calls.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public $ensure_calls = array();

	/**
	 * Rows returned by list_translation_rows().
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public $rows = array(
		array(
			'source_entry_id' => 61,
			'msgid'           => '%s apple',
			'msgid_plural'    => '%s apples',
			'translations'    => array(),
		),
	);

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/**
	 * Records one ensure call.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $source_slug Source slug.
	 * @param string $target_locale Target locale.
	 * @param string $now_gmt Datetime in GMT.
	 * @param int    $plural_forms_count Plural forms count.
	 * @return int
	 */
	public function ensure_translation_targets( $translation_id, $source_slug, $target_locale, $now_gmt, $plural_forms_count ) {
		$this->ensure_calls[] = array(
			'translation_id'     => $translation_id,
			'source_slug'        => $source_slug,
			'target_locale'      => $target_locale,
			'now_gmt'            => $now_gmt,
			'plural_forms_count' => $plural_forms_count,
		);

		return 0;
	}

	/**
	 * Returns the fixed rows.
	 *
	 * @param int    $translation_id Translation ID.
	 * @param string $source_slug Source slug.
	 * @param int    $limit Maximum number of rows.
	 * @param int    $plural_forms_count Plural forms count.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_translation_rows( $translation_id, $source_slug, $limit, $plural_forms_count ) {
		unset( $translation_id, $source_slug, $limit, $plural_forms_count );

		return $this->rows;
	}

	/**
	 * Returns a fixed storage resource ID.
	 *
	 * @param int $translation_id Translation ID.
	 * @return int
	 */
	public function get_translation_resource_id( $translation_id ) {
		unset( $translation_id );

		return 7;
	}
}

// phpcs:enable
