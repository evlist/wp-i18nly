<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation save handler tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\Admin\TranslationSaveHandler;

/**
 * Tests the validation of the posted identity and the integrity of the posted translations.
 */
class TranslationSaveHandlerTest extends TestCase {
	/**
	 * Entries received by the persist callback.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $persisted = array();

	/**
	 * Resets the request, the post meta and the capability.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		global $i18nly_test_post_meta;

		$i18nly_test_post_meta = array();
		$this->persisted       = array();
		$_POST                 = array( 'i18nly_translation_meta_box_nonce' => 'nonce-i18nly_translation_meta_box' );

		i18nly_test_set_can_manage_options( true );
	}

	/**
	 * Clears the request.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_POST = array();
	}

	/**
	 * Builds the handler with plugin "good/good.php" and language "fr_FR" as the only valid values.
	 *
	 * @return TranslationSaveHandler
	 */
	private function handler() {
		return new TranslationSaveHandler(
			'i18nly_translation',
			'_source',
			'_language',
			function ( $translation_id, $source_slug, array $entries_payload ) {
				$this->persisted[] = array( $translation_id, $source_slug, $entries_payload );
			},
			static function () {
				return 0;
			},
			static function () {},
			static function ( $source_slug ) {
				return 'good/good.php' === $source_slug;
			},
			static function ( $language ) {
				return 'fr_FR' === $language;
			}
		);
	}

	/**
	 * Runs the save for post 10.
	 *
	 * @return void
	 */
	private function save() {
		$this->handler()->handle_save( 10, (object) array( 'post_type' => 'i18nly_translation' ), true );
	}

	/**
	 * Valid identity is stored.
	 *
	 * @return void
	 */
	public function test_stores_a_valid_source_and_language() {
		$_POST['i18nly_plugin_selector']          = 'good/good.php';
		$_POST['i18nly_target_language_selector'] = 'fr_FR';

		$this->save();

		$this->assertSame( 'good/good.php', get_post_meta( 10, '_source', true ) );
		$this->assertSame( 'fr_FR', get_post_meta( 10, '_language', true ) );
	}

	/**
	 * A slug which is not an installed plugin is dropped, and so is its payload.
	 *
	 * @return void
	 */
	public function test_ignores_a_source_slug_which_is_not_an_installed_plugin() {
		$_POST['i18nly_plugin_selector']                = '../../wp-config.php';
		$_POST['i18nly_target_language_selector']       = 'fr_FR';
		$_POST['i18nly_translation_entries_payload'] = '{"1":{"forms":{"0":"x"}}}';

		$this->save();

		$this->assertSame( '', get_post_meta( 10, '_source', true ) );
		$this->assertSame( array(), $this->persisted );
	}

	/**
	 * An unknown language is dropped.
	 *
	 * @return void
	 */
	public function test_ignores_an_unknown_language() {
		$_POST['i18nly_plugin_selector']          = 'good/good.php';
		$_POST['i18nly_target_language_selector'] = 'xx_XX';

		$this->save();

		$this->assertSame( '', get_post_meta( 10, '_language', true ) );
	}

	/**
	 * Nothing is saved without the capability.
	 *
	 * @return void
	 */
	public function test_does_nothing_without_the_capability() {
		i18nly_test_set_can_manage_options( false );

		$_POST['i18nly_plugin_selector']          = 'good/good.php';
		$_POST['i18nly_target_language_selector'] = 'fr_FR';

		$this->save();

		$this->assertSame( '', get_post_meta( 10, '_source', true ) );
	}

	/**
	 * Translations are stored as typed: markup, line breaks, tabs, spaces, placeholders and backslashes.
	 *
	 * @return void
	 */
	public function test_translations_reach_the_storage_unaltered() {
		$forms = array(
			"Cliquez <a href=\"/x\">ici</a> pour %1\$s\net <strong>%2\$s</strong>",
			"  deux  espaces\t et tabulation  ",
			'Chemin C:\\dossier "cité" 100% sûr %ab',
			"Ligne 1\nLigne 2",
		);

		$payload = array( '5' => array( 'forms' => array_values( $forms ) ) );

		$_POST['i18nly_plugin_selector']             = 'good/good.php';
		$_POST['i18nly_target_language_selector']    = 'fr_FR';
		// WordPress adds slashes to the request: the handler must remove them exactly once.
		$_POST['i18nly_translation_entries_payload'] = addslashes( wp_json_encode( $payload ) );

		$this->save();

		$this->assertCount( 1, $this->persisted );
		$this->assertSame( $forms, array_values( $this->persisted[0][2]['5']['forms'] ) );
	}
}
