<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation entries list table.
 *
 * @package I18nly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders translation entries using WP_List_Table conventions.
 */
class I18nly_Translation_Entries_List_Table extends WP_List_Table {
	/**
	 * Table rows.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $rows;

	/**
	 * Constructor.
	 *
	 * @param array<int, array<string, mixed>> $rows Translation entry rows.
	 */
	public function __construct( array $rows ) {
		$this->rows = $rows;

		if ( is_callable( array( 'WP_List_Table', '__construct' ) ) ) {
			parent::__construct(
				array(
					'singular' => 'i18nly_translation_entry',
					'plural'   => 'i18nly_translation_entries',
					'ajax'     => false,
				)
			);
		}
	}

	/**
	 * Returns table columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'msgctxt'     => __( 'Context', 'i18nly' ),
			'msgid'       => __( 'Source string', 'i18nly' ),
			'translation' => __( 'Translation', 'i18nly' ),
			'status'      => __( 'Status', 'i18nly' ),
		);
	}

	/**
	 * Renders source singular/plural values in one stacked cell.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	public function column_msgid( $item ) {
		$singular = isset( $item['msgid'] ) ? (string) $item['msgid'] : '';
		$plural   = isset( $item['msgid_plural'] ) ? (string) $item['msgid_plural'] : '';

		return $this->render_stacked_text_pair( $singular, $plural );
	}

	/**
	 * Renders translation singular/plural values in one stacked cell.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	public function column_translation( $item ) {
		$singular = isset( $item['translation'] ) ? (string) $item['translation'] : '';
		$plural   = isset( $item['translation_plural'] ) ? (string) $item['translation_plural'] : '';

		return $this->render_stacked_text_pair( $singular, $plural );
	}

	/**
	 * Prepares rows for display.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->items = array_values( $this->rows );

		if ( property_exists( $this, '_column_headers' ) ) {
			$this->_column_headers = array( $this->get_columns(), array(), array() );
		}
	}

	/**
	 * Renders one default column value.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @param string               $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		if ( ! isset( $item[ $column_name ] ) ) {
			return '';
		}

		return esc_html( (string) $item[ $column_name ] );
	}

	/**
	 * Renders singular/plural values as two paragraphs in one cell.
	 *
	 * @param string $singular Singular value.
	 * @param string $plural Plural value.
	 * @return string
	 */
	private function render_stacked_text_pair( $singular, $plural ) {
		return sprintf(
			'<p><strong>%1$s:</strong> %2$s</p><p><strong>%3$s:</strong> %4$s</p>',
			esc_html__( 'Singular', 'i18nly' ),
			esc_html( $singular ),
			esc_html__( 'Plural', 'i18nly' ),
			esc_html( $plural )
		);
	}

	/**
	 * Outputs text when there are no rows.
	 *
	 * @return void
	 */
	public function no_items() {
		echo esc_html__( 'No translation entries available yet.', 'i18nly' );
	}
}
