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
	 * @param array<int, array<string, mixed>> $rows Rows already saved.
	 * @return TranslationResourceRepository
	 */
	private function repository( array $rows = array() ) {
		return new class( $rows ) extends TranslationResourceRepository {
			/**
			 * Rows already saved.
			 *
			 * @var array<int, array<string, mixed>>
			 */
			private $rows;

			/**
			 * Saved targets.
			 *
			 * @var array<int, array<string, mixed>>
			 */
			public $saved = array();

			/**
			 * Does not need storage.
			 *
			 * @param array<int, array<string, mixed>> $rows Rows already saved.
			 */
			public function __construct( array $rows = array() ) {
				$this->rows = $rows;
			}

			/**
			 * Returns the rows already saved.
			 *
			 * @param int    $translation_id Translation ID.
			 * @param string $source_slug Source slug.
			 * @param int    $limit Limit.
			 * @param int    $plural_forms_count Forms.
			 * @return array<int, array<string, mixed>>
			 */
			public function list_translation_rows( $translation_id, $source_slug, $limit, $plural_forms_count ) {
				return $this->rows;
			}

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

	/**
	 * Builds the saved row of one entry with one form.
	 *
	 * @param int    $entry_id Entry ID.
	 * @param string $text Saved text.
	 * @param string $updated_at Saved at.
	 * @return array<string, mixed>
	 */
	private function saved_row( $entry_id, $text, $updated_at ) {
		return array(
			'source_entry_id' => $entry_id,
			'translations'    => array(
				array(
					'form_index'     => 0,
					'translation'    => $text,
					'updated_at_gmt' => $updated_at,
				),
			),
		);
	}

	/**
	 * Posts the same form for entries 1 to 4 and returns the number of conflicts and the saved IDs.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows already saved.
	 * @param string                           $loaded_at Load time.
	 * @return array{0: int, 1: int[]}
	 */
	private function persist_with( array $rows, $loaded_at ) {
		$repository = $this->repository( $rows );
		$payload    = array();

		foreach ( array( 1, 2, 3, 4 ) as $entry_id ) {
			$payload[ (string) $entry_id ] = array( 'forms' => array( 0 => 'mine' ) );
		}

		$conflicts = ( new TranslationEntriesPersister( $repository ) )->persist( 42, 'sample/sample.php', $payload, $loaded_at );

		return array( $conflicts, array_column( $repository->saved, 0 ) );
	}

	/**
	 * A form saved by someone else after the load time is not overwritten.
	 *
	 * @return void
	 */
	public function test_forms_changed_by_someone_else_after_loading_are_kept() {
		$rows = array(
			$this->saved_row( 1, 'theirs', '2026-10-08 10:05:00' ),
			$this->saved_row( 2, 'theirs', '2026-10-08 09:00:00' ),
			$this->saved_row( 3, 'mine', '2026-10-08 10:05:00' ),
			$this->saved_row( 4, '', '2026-10-08 10:05:00' ),
		);

		list( $conflicts, $saved ) = $this->persist_with( $rows, '2026-10-08 10:00:00' );

		$this->assertSame( 1, $conflicts );
		$this->assertSame( array( 2, 3, 4 ), $saved );
	}

	/**
	 * Without a load time nothing is checked.
	 *
	 * @return void
	 */
	public function test_without_load_time_everything_is_saved() {
		$rows = array( $this->saved_row( 1, 'theirs', '2026-10-08 10:05:00' ) );

		list( $conflicts, $saved ) = $this->persist_with( $rows, '' );

		$this->assertSame( 0, $conflicts );
		$this->assertSame( array( 1, 2, 3, 4 ), $saved );
	}
}
