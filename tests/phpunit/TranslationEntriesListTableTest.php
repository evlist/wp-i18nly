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
			class_exists( 'WP_I18nly\\Admin\\UI\\TranslationEntriesListTable' ),
			'Expected WP_I18nly\\Admin\\UI\\TranslationEntriesListTable class to be defined.'
		);

		$this->assertTrue(
			is_subclass_of( 'WP_I18nly\\Admin\\UI\\TranslationEntriesListTable', 'WP_List_Table' ),
			'Expected WP_I18nly\\Admin\\UI\\TranslationEntriesListTable to extend WP_List_Table.'
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

		$page = new class() extends \WP_I18nly\Admin\AdminPage {
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
						'source_entry_id' => 11,
						'msgctxt'         => 'email',
						'msgid'           => 'Welcome',
						'msgid_plural'    => 'Welcomes',
						'status'          => 'active',
						'translations'    => array(
							array(
								'form_index'  => 0,
								'translation' => 'Bienvenue',
							),
							array(
								'form_index'  => 1,
								'translation' => 'Bienvenues',
							),
						),
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
		$list_table = new \WP_I18nly\Admin\UI\TranslationEntriesListTable(
			array(
				array(
					'source_entry_id'    => 21,
					'msgctxt'            => '',
					'msgid'              => 'Hello',
					'msgid_plural'       => '',
					'translator_comment' => 'Shown in dashboard header.',
					'status'             => 'active',
					'translations'       => array(
						array(
							'form_index'  => 0,
							'translation' => 'Bonjour',
						),
					),
				),
			)
		);

		ob_start();
		$list_table->prepare_items();
		$list_table->display();
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'Hello', $html );
		$this->assertStringContainsString( 'data-i18nly-source-entry-id="21"', $html );
		$this->assertStringContainsString( 'data-i18nly-form-index="0"', $html );
		$this->assertStringContainsString( 'value="Bonjour"', $html );
		$this->assertStringContainsString( 'class="i18nly-translator-comment"', $html );
		$this->assertStringContainsString( 'Shown in dashboard header.', $html );
		$this->assertStringNotContainsString( 'i18nly-form-marker', $html );
	}

	/**
	 * Uses source plural presence to decide stacked translation rendering.
	 *
	 * @return void
	 */
	public function test_translation_column_uses_source_plural_to_enable_stacked_rendering() {
		$list_table = new \WP_I18nly\Admin\UI\TranslationEntriesListTable(
			array(
				array(
					'source_entry_id' => 31,
					'msgctxt'         => '',
					'msgid'           => '%s item',
					'msgid_plural'    => '%s items',
					'status'          => 'active',
					'forms'           => array(
						array(
							'marker'  => 'a',
							'label'   => 'one',
							'tooltip' => 'Zero or one',
						),
						array(
							'marker'  => 'b',
							'label'   => 'other',
							'tooltip' => 'More than one',
						),
					),
					'form_labels'     => array( 'one', 'other' ),
					'form_markers'    => array( 'a', 'b' ),
					'form_tooltips'   => array( 'Zero or one', 'More than one' ),
					'translations'    => array(
						array(
							'form_index'  => 0,
							'translation' => '%s article',
						),
						array(
							'form_index'  => 1,
							'translation' => '%s articles',
						),
					),
				),
			)
		);

		ob_start();
		$list_table->prepare_items();
		$list_table->display();
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'class="i18nly-form-marker"', $html );
		$this->assertStringContainsString( 'title="Zero or one"', $html );
		$this->assertStringContainsString( 'title="More than one"', $html );
		$this->assertStringContainsString( '>a</span>', $html );
		$this->assertStringContainsString( '>b</span>', $html );
		$this->assertStringContainsString( 'data-i18nly-source-entry-id="31"', $html );
		$this->assertStringContainsString( 'data-i18nly-form-index="0"', $html );
		$this->assertStringContainsString( 'data-i18nly-form-index="1"', $html );
		$this->assertStringContainsString( 'value="%s article"', $html );
		$this->assertStringContainsString( 'value="%s articles"', $html );
	}

	/**
	 * Uses witness examples to map each target plural form to singular or plural source text.
	 *
	 * @return void
	 */
	public function test_translation_inputs_use_witness_examples_for_source_text_mapping() {
		$list_table = new \WP_I18nly\Admin\UI\TranslationEntriesListTable(
			array(
				array(
					'source_entry_id' => 61,
					'msgctxt'         => '',
					'msgid'           => '%s apple',
					'msgid_plural'    => '%s apples',
					'status'          => 'active',
					'forms'           => array(
						array(
							'marker'   => 'a',
							'label'    => 'a',
							'tooltip'  => 'one',
							'examples' => array( 1 ),
						),
						array(
							'marker'   => 'b',
							'label'    => 'b',
							'tooltip'  => 'few',
							'examples' => array( 2 ),
						),
						array(
							'marker'   => 'c',
							'label'    => 'c',
							'tooltip'  => 'special singular witness',
							'examples' => array( 1 ),
						),
					),
					'translations'    => array(
						array(
							'form_index'  => 0,
							'translation' => '',
						),
						array(
							'form_index'  => 1,
							'translation' => '',
						),
						array(
							'form_index'  => 2,
							'translation' => '',
						),
					),
				),
			)
		);

		ob_start();
		$list_table->prepare_items();
		$list_table->display();
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'id="i18nly-translation-61-0"', $html );
		$this->assertStringContainsString( 'id="i18nly-translation-61-1"', $html );
		$this->assertStringContainsString( 'id="i18nly-translation-61-2"', $html );
		$this->assertStringContainsString( 'id="i18nly-translation-61-1" value="" data-i18nly-source-entry-id="61" data-i18nly-form-index="1" data-i18nly-source-text="%s apples"', $html );
		$this->assertStringContainsString( 'id="i18nly-translation-61-2" value="" data-i18nly-source-entry-id="61" data-i18nly-form-index="2" data-i18nly-source-text="%s apple"', $html );
	}

	/**
	 * Treats form examples containing 1 as singular, even when 0 appears first.
	 *
	 * @return void
	 */
	public function test_translation_inputs_use_singular_when_examples_include_one_after_zero() {
		$list_table = new \WP_I18nly\Admin\UI\TranslationEntriesListTable(
			array(
				array(
					'source_entry_id' => 62,
					'msgctxt'         => '',
					'msgid'           => '%s translation restored from the Trash.',
					'msgid_plural'    => '%s translations restored from the Trash.',
					'status'          => 'active',
					'forms'           => array(
						array(
							'marker'   => 'a',
							'label'    => 'a',
							'tooltip'  => 'zero or one',
							'examples' => array( 0, 1 ),
						),
					),
					'translations'    => array(
						array(
							'form_index'  => 0,
							'translation' => '',
						),
					),
				),
			)
		);

		ob_start();
		$list_table->prepare_items();
		$list_table->display();
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'data-i18nly-source-text="%s translation restored from the Trash."', $html );
		$this->assertStringContainsString( 'data-i18nly-witness="1"', $html );
	}

	/**
	 * Renders selection and bulk actions with status metadata.
	 *
	 * @return void
	 */
	public function test_list_table_renders_bulk_actions_and_selection_controls() {
		$list_table = new \WP_I18nly\Admin\UI\TranslationEntriesListTable(
			array(
				array(
					'source_entry_id' => 41,
					'msgctxt'         => '',
					'msgid'           => 'Hello',
					'msgid_plural'    => '',
					'status'          => 'obsolete',
					'source_status'   => 'obsolete',
					'translations'    => array(
						array(
							'form_index'  => 0,
							'translation' => 'Bonjour',
							'status'      => 'draft',
						),
					),
				),
			)
		);

		ob_start();
		$list_table->prepare_items();
		$list_table->display();
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'class="i18nly-bulk-select-all"', $html );
		$this->assertStringContainsString( 'class="i18nly-entry-checkbox"', $html );
		$this->assertStringContainsString( 'i18nly-entry-status--draft', $html );
		$this->assertStringContainsString( 'data-i18nly-source-text="Hello"', $html );
	}

	/**
	 * Displays translated status badges independently from source entry lifecycle status.
	 *
	 * @return void
	 */
	public function test_list_table_renders_translated_status_badge() {
		$list_table = new \WP_I18nly\Admin\UI\TranslationEntriesListTable(
			array(
				array(
					'source_entry_id' => 73,
					'msgctxt'         => '',
					'msgid'           => 'Needs review',
					'msgid_plural'    => '',
					'source_status'   => 'active',
					'translations'    => array(
						array(
							'form_index'  => 0,
							'translation' => 'A verifier',
							'status'      => 'draft_ai_needs_fix',
						),
					),
				),
			)
		);

		ob_start();
		$list_table->prepare_items();
		$list_table->display();
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'i18nly-entry-status--needs-fix', $html );
	}

	/**
	 * Declares AI translate among available bulk actions.
	 *
	 * @return void
	 */
	public function test_list_table_declares_ai_translate_bulk_action() {
		$list_table = new class( array() ) extends \WP_I18nly\Admin\UI\TranslationEntriesListTable {
			/**
			 * Exposes protected bulk actions for test purposes.
			 *
			 * @return array<string, string>
			 */
			public function exposed_get_bulk_actions() {
				return $this->get_bulk_actions();
			}
		};

		$bulk_actions = $list_table->exposed_get_bulk_actions();

		$this->assertArrayHasKey( 'ai_translate_selected', $bulk_actions );
	}

	/**
	 * Renders one AI translate button bound to each translation input.
	 *
	 * @return void
	 */
	public function test_list_table_renders_ai_translate_button_with_input_binding() {
		$list_table = new \WP_I18nly\Admin\UI\TranslationEntriesListTable(
			array(
				array(
					'source_entry_id' => 51,
					'msgctxt'         => '',
					'msgid'           => 'Translate me',
					'msgid_plural'    => '',
					'status'          => 'active',
					'translations'    => array(
						array(
							'form_index'  => 0,
							'translation' => '',
						),
					),
				),
			)
		);

		ob_start();
		$list_table->prepare_items();
		$list_table->display();
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'class="i18nly-translate-btn"', $html );
		$this->assertMatchesRegularExpression( '/data-for="i18nly-translation-51-0"/', $html );
	}
}
