<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation entries WP_List_Table tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

/**
 * Locks expected WP_List_Table-based rendering contract for translation entries.
 */
class TranslationEntriesListTableTest extends TestCase {
	/**
	 * Expects a dedicated list table class for translation entries.
	 *
	 * @return void
	 */
	public function test_translation_entries_list_table_class_exists_and_extends_wp_list_table() {
		$this->assertTrue(
			class_exists( 'I18nly_Translation_Entries_List_Table', false ),
			'Expected I18nly_Translation_Entries_List_Table class to be defined.'
		);

		$this->assertTrue(
			is_subclass_of( 'I18nly_Translation_Entries_List_Table', 'WP_List_Table' ),
			'Expected I18nly_Translation_Entries_List_Table to extend WP_List_Table.'
		);
	}

	/**
	 * Expects WP_List_Table navigation markup to be rendered with entries table HTML.
	 *
	 * @return void
	 */
	public function test_ajax_entries_table_html_contains_wp_list_table_navigation_markup() {
		i18nly_test_set_can_manage_options( true );
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'               => 42,
					'source_slug'      => 'akismet/akismet.php',
					'target_language'  => 'fr_FR',
					'created_at_gmt'   => '2026-03-02 11:15:00',
					'created_at_local' => '2026-03-02 12:15:00',
				),
			)
		);
		i18nly_test_reset_last_json_response();

		$_POST['translation_id'] = '42';
		$_POST['nonce']          = 'nonce-i18nly_get_translation_entries_table_42';

		$page = new class() extends I18nly_Admin_Page {
			/**
			 * Returns deterministic entries for test rendering.
			 *
			 * @param int    $translation_id Translation ID.
			 * @param string $source_slug Source slug.
			 * @return array<int, array<string, mixed>>
			 */
			protected function get_translation_source_entries( $translation_id, $source_slug ) {
				unset( $translation_id, $source_slug );

				return array(
					array(
						'msgctxt'            => 'email',
						'msgid'              => 'Welcome',
						'translation'        => 'Bienvenue',
						'msgid_plural'       => 'Welcomes',
						'translation_plural' => 'Bienvenues',
						'status'             => 'active',
					),
				);
			}
		};

		$page->ajax_get_translation_entries_table();

		$response = i18nly_test_get_last_json_response();

		$this->assertArrayHasKey( 'data', $response );
		$this->assertIsString( $response['data']['html'] );
		$this->assertStringContainsString( 'tablenav', $response['data']['html'] );

		unset( $_POST['translation_id'], $_POST['nonce'] );
	}

	/**
	 * Keeps compact rendering when entry has no plural value.
	 *
	 * @return void
	 */
	public function test_list_table_renders_compact_cells_without_plural_values() {
		$list_table = new I18nly_Translation_Entries_List_Table(
			array(
				array(
					'msgctxt'            => '',
					'msgid'              => 'Hello',
					'translation'        => 'Bonjour',
					'msgid_plural'       => '',
					'translation_plural' => '',
					'status'             => 'active',
				),
			)
		);

		ob_start();
		$list_table->prepare_items();
		$list_table->display();
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'Hello', $html );
		$this->assertStringContainsString( 'Bonjour', $html );
		$this->assertStringNotContainsString( 'i18nly-form-marker', $html );
	}

	/**
	 * Uses source plural presence to decide stacked translation rendering.
	 *
	 * @return void
	 */
	public function test_translation_column_uses_source_plural_to_enable_stacked_rendering() {
		$list_table = new I18nly_Translation_Entries_List_Table(
			array(
				array(
					'msgctxt'            => '',
					'msgid'              => '%s item',
					'translation'        => '%s article',
					'msgid_plural'       => '%s items',
					'translation_plural' => '',
					'status'             => 'active',
				),
			)
		);

		ob_start();
		$list_table->prepare_items();
		$list_table->display();
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'class="i18nly-form-marker"', $html );
		$this->assertStringContainsString( 'title="Singular form"', $html );
		$this->assertStringContainsString( 'title="Plural form"', $html );
		$this->assertStringContainsString( '>1</span>', $html );
		$this->assertStringContainsString( '>n</span>', $html );
		$this->assertStringContainsString( '%s article', $html );
	}
}
