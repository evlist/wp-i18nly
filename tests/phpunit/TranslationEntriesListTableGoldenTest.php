<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation entries list table golden test.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

/**
 * Pins the complete HTML produced by the translation entries list table.
 */
class TranslationEntriesListTableGoldenTest extends TestCase {
	/**
	 * Builds the entries covering every rendering branch.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function entries() {
		$french_forms = array(
			array(
				'marker'   => '1',
				'label'    => 'one',
				'tooltip'  => 'One (0, 1)',
				'examples' => array( 0, 1 ),
			),
			array(
				'marker'   => 'n',
				'label'    => 'other',
				'tooltip'  => 'Other than one',
				'examples' => array( 2, 3 ),
			),
		);

		return array(
			array(
				'source_entry_id' => 11,
				'msgctxt'         => '',
				'msgid'           => 'Hello',
				'msgid_plural'    => '',
				'source_status'   => 'active',
				'translations'    => array(
					array(
						'form_index'  => 0,
						'translation' => 'Bonjour',
						'status'      => 'validated',
						'used_ai'     => 0,
						'used_manual' => 1,
					),
				),
			),
			array(
				'source_entry_id' => 12,
				'msgctxt'         => '',
				'msgid'           => 'Settings',
				'msgid_plural'    => '',
				'source_status'   => 'active',
				'translations'    => array(
					array(
						'form_index'  => 0,
						'translation' => '',
						'status'      => 'draft',
						'used_ai'     => 1,
						'used_manual' => 1,
					),
				),
			),
			array(
				'source_entry_id' => 13,
				'msgctxt'         => 'shop',
				'msgid'           => '%d item',
				'msgid_plural'    => '%d items',
				'source_status'   => 'active',
				'forms'           => $french_forms,
				'form_labels'     => array( 'one', 'other' ),
				'form_markers'    => array( '1', 'n' ),
				'form_tooltips'   => array( 'One (0, 1)', 'Other than one' ),
				'translations'    => array(
					array(
						'form_index'  => 0,
						'translation' => '%d article',
						'status'      => 'draft_ai',
						'used_ai'     => 1,
						'used_manual' => 0,
					),
					array(
						'form_index'  => 1,
						'translation' => '%d articles',
						'status'      => 'suspect',
						'used_ai'     => 1,
						'used_manual' => 1,
					),
				),
			),
			array(
				'source_entry_id' => 14,
				'msgctxt'         => '',
				'msgid'           => '%d day',
				'msgid_plural'    => '%d days',
				'source_status'   => 'active',
				'form_labels'     => array( 'few', '', 'many' ),
				'form_markers'    => array( 'f', '', 'm' ),
				'form_tooltips'   => array( 'Few', '', '' ),
				'translations'    => array(
					array(
						'form_index'  => 0,
						'translation' => 'a',
					),
					array(
						'form_index'  => 1,
						'translation' => 'b',
						'status'      => 'validated',
					),
					array(
						'form_index'  => 2,
						'translation' => 'c',
						'status'      => 'unknown-token',
						'used_ai'     => 1,
					),
				),
			),
			array(
				'source_entry_id'    => 15,
				'msgctxt'            => '',
				'msgid'              => 'Tom & "Jerry" <b>',
				'msgid_plural'       => '',
				'source_status'      => 'obsolete',
				'translator_comment' => 'Keep <em>markup</em> & quotes "as is".',
				'translations'       => array(
					array(
						'form_index'  => 0,
						'translation' => 'Tom & « Jerry » <b>',
						'status'      => 'ai_draft_ok',
						'used_ai'     => 1,
						'used_manual' => 0,
					),
				),
			),
			array(
				'source_entry_id' => 16,
				'msgctxt'         => '',
				'msgid'           => 'No translation rows',
				'msgid_plural'    => '',
				'status'          => 'active',
			),
			array(
				'source_entry_id' => 17,
				'msgctxt'         => '',
				'msgid'           => '%s file',
				'msgid_plural'    => '%s files',
				'source_status'   => 'obsolete',
				'forms'           => $french_forms,
				'translations'    => array(),
			),
			array(
				'source_entry_id' => 0,
				'msgctxt'         => '',
				'msgid'           => 'Entry without ID',
				'msgid_plural'    => '',
			),
		);
	}

	/**
	 * Renders the table pieces in a stable form.
	 *
	 * @return string
	 */
	private function render() {
		$table = new class( $this->entries() ) extends \WP_I18nly\Admin\UI\TranslationEntriesListTable {
			/**
			 * Renders every part of the table.
			 *
			 * @return string
			 */
			public function render_everything() {
				$this->prepare_items();

				$html = '';

				ob_start();
				$this->display_tablenav( 'top' );
				$html .= (string) ob_get_clean();

				foreach ( $this->items as $item ) {
					$status = isset( $item['source_status'] ) ? (string) $item['source_status'] : ( isset( $item['status'] ) ? (string) $item['status'] : 'active' );
					$html  .= '<tr data-entry-status="' . $status . '">';

					foreach ( array_keys( $this->get_columns() ) as $column_name ) {
						$method = 'column_' . $column_name;
						$html  .= '<td data-column="' . $column_name . '">'
							. ( method_exists( $this, $method ) ? $this->{$method}( $item ) : $this->column_default( $item, $column_name ) )
							. '</td>';
					}

					$html .= "</tr>\n";
				}

				return $html;
			}
		};

		return $table->render_everything();
	}

	/**
	 * Keeps the whole table markup unchanged.
	 *
	 * The expected markup was captured before the helpers of the table were extracted into
	 * collaborators. The first two lines of the fixture are its license header.
	 *
	 * @return void
	 */
	public function test_table_markup_is_stable() {
		$expected = implode( '', array_slice( (array) file( __DIR__ . '/fixtures/entries-list-table-expected.html' ), 2 ) );

		$this->assertSame( $expected, $this->render() );
	}
}
