<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation entries persister tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\LinguisticResources\TranslationResourceRepository;
use WP_I18nly\Support\TranslationEntriesPersister;

/**
 * Tests that translations reach the repository exactly as typed.
 */
class TranslationEntriesPersisterTest extends TestCase {
	/**
	 * Repository recording the saved targets.
	 *
	 * @return TranslationResourceRepository
	 */
	private function repository() {
		return new class() extends TranslationResourceRepository {
			/**
			 * Saved targets.
			 *
			 * @var array<int, array<string, mixed>>
			 */
			public $saved = array();

			/**
			 * Does not need storage.
			 */
			public function __construct() {}

			/**
			 * Does nothing.
			 *
			 * @param int    $translation_id Translation ID.
			 * @param string $source_slug Source slug.
			 * @param string $target_locale Locale.
			 * @param string $now_gmt Now.
			 * @param int    $plural_forms_count Forms.
			 * @return int
			 */
			public function ensure_translation_targets( $translation_id, $source_slug, $target_locale, $now_gmt, $plural_forms_count ) {
				return 0;
			}

			/**
			 * Records one target.
			 *
			 * @param int         $translation_id Translation ID.
			 * @param int         $source_entry_id Source entry ID.
			 * @param int         $form_index Form index.
			 * @param string      $translation Text.
			 * @param string      $now_gmt Now.
			 * @param string|null $status Status.
			 * @param int|null    $used_ai AI flag.
			 * @param int|null    $used_manual Manual flag.
			 * @return bool
			 */
			public function upsert_translation_target( $translation_id, $source_entry_id, $form_index, $translation, $now_gmt, $status = null, $used_ai = null, $used_manual = null ) {
				$this->saved[] = array( $source_entry_id, $form_index, $translation, $status );

				return true;
			}
		};
	}

	/**
	 * Markup, line breaks, spacing and placeholders are saved unchanged.
	 *
	 * @return void
	 */
	public function test_translations_are_saved_unaltered() {
		$repository = $this->repository();
		$text       = "Cliquez <a href=\"/x\">ici</a>\n  pour %1\$s \t100% %ab";

		( new TranslationEntriesPersister( $repository ) )->persist(
			42,
			'sample/sample.php',
			array(
				'7' => array(
					'forms'    => array( 0 => $text ),
					'statuses' => array( 0 => 'draft' ),
				),
			)
		);

		$this->assertSame( array( array( 7, 0, $text, 'draft' ) ), $repository->saved );
	}
}
