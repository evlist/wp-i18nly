<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation duplicate guard tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests duplicate translation detection.
 */
class TranslationDuplicateGuardTest extends TestCase {
	/**
	 * Builds a guard configured like the admin page.
	 *
	 * @return \WP_I18nly\Admin\TranslationDuplicateGuard
	 */
	private function build_guard() {
		return new \WP_I18nly\Admin\TranslationDuplicateGuard(
			'i18nly_translation',
			'_i18nly_source_slug',
			'_i18nly_target_language',
			'post-new.php?post_type=i18nly_translation'
		);
	}

	/**
	 * Finds another translation with the same source slug and target language.
	 *
	 * @return void
	 */
	public function test_find_duplicate_returns_other_translation_with_same_identity() {
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'              => 100,
					'source_slug'     => 'akismet/akismet.php',
					'target_language' => 'fr_FR',
				),
				array(
					'id'              => 101,
					'source_slug'     => 'akismet/akismet.php',
					'target_language' => 'de_DE',
				),
			)
		);

		$guard = $this->build_guard();

		$this->assertSame( 100, $guard->find_duplicate_translation_id( 'akismet/akismet.php', 'fr_FR', 42 ) );
		$this->assertSame( 101, $guard->find_duplicate_translation_id( 'akismet/akismet.php', 'de_DE', 42 ) );
	}

	/**
	 * Ignores the translation being saved and non-matching identities.
	 *
	 * @return void
	 */
	public function test_find_duplicate_ignores_current_post_and_other_identities() {
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'              => 100,
					'source_slug'     => 'akismet/akismet.php',
					'target_language' => 'fr_FR',
				),
			)
		);

		$guard = $this->build_guard();

		$this->assertSame( 0, $guard->find_duplicate_translation_id( 'akismet/akismet.php', 'fr_FR', 100 ) );
		$this->assertSame( 0, $guard->find_duplicate_translation_id( 'akismet/akismet.php', 'es_ES', 42 ) );
		$this->assertSame( 0, $guard->find_duplicate_translation_id( 'hello-dolly/hello.php', 'fr_FR', 42 ) );
	}

	/**
	 * Restoring a trashed translation is refused when an active one exists for the same pair.
	 *
	 * @return void
	 */
	public function test_restoring_a_duplicate_from_the_trash_is_refused() {
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'              => 100,
					'source_slug'     => 'akismet/akismet.php',
					'target_language' => 'fr_FR',
				),
			)
		);

		update_post_meta( 55, '_i18nly_source_slug', 'akismet/akismet.php' );
		update_post_meta( 55, '_i18nly_target_language', 'fr_FR' );

		$trashed = (object) array(
			'ID'        => 55,
			'post_type' => 'i18nly_translation',
		);

		try {
			$this->build_guard()->block_restoring_a_duplicate( null, $trashed );
			$this->fail( 'The restoration should have been refused.' );
		} catch ( \RuntimeException $exception ) {
			$this->assertStringStartsWith( 'wp_die:409:', $exception->getMessage() );
			$this->assertStringContainsString( 'post=100', $exception->getMessage() );
		}
	}

	/**
	 * Restoring is allowed without an active duplicate, and other post types are untouched.
	 *
	 * @return void
	 */
	public function test_restoring_is_allowed_without_active_duplicate() {
		i18nly_test_set_translations_rows( array() );

		update_post_meta( 55, '_i18nly_source_slug', 'akismet/akismet.php' );
		update_post_meta( 55, '_i18nly_target_language', 'fr_FR' );

		$guard = $this->build_guard();

		$this->assertNull(
			$guard->block_restoring_a_duplicate(
				null,
				(object) array(
					'ID' => 55,
					'post_type' => 'i18nly_translation',
				)
			)
		);
		$this->assertNull(
			$guard->block_restoring_a_duplicate(
				null,
				(object) array(
					'ID' => 56,
					'post_type' => 'post',
				)
			)
		);
	}
}
