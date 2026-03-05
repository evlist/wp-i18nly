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
			'msgctxt'            => __( 'Context', 'i18nly' ),
			'msgid'              => __( 'Source string', 'i18nly' ),
			'translation'        => __( 'Translation', 'i18nly' ),
			'msgid_plural'       => __( 'Plural', 'i18nly' ),
			'translation_plural' => __( 'Plural translation', 'i18nly' ),
			'status'             => __( 'Status', 'i18nly' ),
		);
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
	 * Outputs text when there are no rows.
	 *
	 * @return void
	 */
	public function no_items() {
		echo esc_html__( 'No translation entries available yet.', 'i18nly' );
	}
}
