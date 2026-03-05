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
		$singular   = isset( $item['msgid'] ) ? (string) $item['msgid'] : '';
		$plural     = isset( $item['msgid_plural'] ) ? (string) $item['msgid_plural'] : '';
		$has_plural = '' !== trim( $plural );

		return $this->render_stacked_text_pair( $singular, $plural, $has_plural );
	}

	/**
	 * Renders translation singular/plural values in one stacked cell.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	public function column_translation( $item ) {
		$singular      = isset( $item['translation'] ) ? (string) $item['translation'] : '';
		$plural        = isset( $item['translation_plural'] ) ? (string) $item['translation_plural'] : '';
		$source_plural = isset( $item['msgid_plural'] ) ? (string) $item['msgid_plural'] : '';
		$has_plural    = '' !== trim( $source_plural );

		return $this->render_stacked_text_pair( $singular, $plural, $has_plural );
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
	 * @param bool   $has_plural Whether source has plural form.
	 * @return string
	 */
	private function render_stacked_text_pair( $singular, $plural, $has_plural ) {
		if ( ! $has_plural ) {
			return esc_html( $singular );
		}

		$singular_marker = $this->render_form_marker(
			_x( '1', 'grammar form marker for singular translation row', 'i18nly' ),
			_x( 'Singular form', 'tooltip for singular translation form marker', 'i18nly' )
		);
		$plural_marker   = $this->render_form_marker(
			_x( 'n', 'grammar form marker for plural translation row', 'i18nly' ),
			_x( 'Plural form', 'tooltip for plural translation form marker', 'i18nly' )
		);

		return sprintf(
			'<p class="i18nly-form-line">%1$s %2$s</p><p class="i18nly-form-line">%3$s %4$s</p>',
			$singular_marker,
			esc_html( $singular ),
			$plural_marker,
			esc_html( $plural )
		);
	}

	/**
	 * Renders one compact grammar form marker.
	 *
	 * @param string $symbol Marker symbol.
	 * @param string $label Accessible label and tooltip.
	 * @return string
	 */
	private function render_form_marker( $symbol, $label ) {
		return sprintf(
			'<span class="i18nly-form-marker" title="%1$s" aria-label="%1$s">%2$s</span><span class="screen-reader-text">%1$s</span>',
			esc_attr( $label ),
			esc_html( $symbol )
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
