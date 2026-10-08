<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation resource cleaner tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.Files.OneObjectStructurePerFile

/**
 * Tests cleanup of translation resources when translation posts are deleted.
 */
class TranslationResourceCleanerTest extends TestCase {
	/**
	 * Deletes the resource of a permanently deleted translation post.
	 *
	 * @return void
	 */
	public function test_deleting_a_translation_post_deletes_its_resource() {
		$repository = new I18nly_Test_Recording_Translation_Repository();
		$cleaner    = new \WP_I18nly\Support\TranslationResourceCleaner( $repository );

		$cleaner->handle_before_delete_post( 42, (object) array( 'post_type' => 'i18nly_translation' ) );

		$this->assertSame( array( 42 ), $repository->deleted_ids );
	}

	/**
	 * Ignores posts of other types.
	 *
	 * @return void
	 */
	public function test_deleting_another_post_type_is_ignored() {
		$repository = new I18nly_Test_Recording_Translation_Repository();
		$cleaner    = new \WP_I18nly\Support\TranslationResourceCleaner( $repository );

		$cleaner->handle_before_delete_post( 42, (object) array( 'post_type' => 'post' ) );
		$cleaner->handle_before_delete_post( 43, null );

		$this->assertSame( array(), $repository->deleted_ids );
	}
}

/**
 * Repository double recording deleted translation IDs.
 */
class I18nly_Test_Recording_Translation_Repository extends \WP_I18nly\LinguisticResources\TranslationResourceRepository {
	/**
	 * Deleted translation IDs.
	 *
	 * @var array<int, int>
	 */
	public $deleted_ids = array();

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/**
	 * Records one deletion.
	 *
	 * @param int $translation_id Translation ID.
	 * @return bool
	 */
	public function delete_translation_resource( $translation_id ) {
		$this->deleted_ids[] = (int) $translation_id;

		return true;
	}
}

// phpcs:enable
