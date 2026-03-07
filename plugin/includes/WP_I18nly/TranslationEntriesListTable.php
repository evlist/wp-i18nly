<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation entries list table.
 *
 * @package I18nly
 */

namespace WP_I18nly;

defined( 'ABSPATH' ) || exit;

/**
 * Renders translation entries using WP_List_Table conventions.
 */
class TranslationEntriesListTable extends \WP_List_Table {
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
		$source_plural = isset( $item['msgid_plural'] ) ? (string) $item['msgid_plural'] : '';
		$has_plural    = '' !== trim( $source_plural );
		$source_entry  = isset( $item['source_entry_id'] ) ? absint( $item['source_entry_id'] ) : 0;
		$translations  = isset( $item['translations'] ) && is_array( $item['translations'] )
			? $item['translations']
			: array();
		$form_labels   = isset( $item['form_labels'] ) && is_array( $item['form_labels'] )
			? array_values( $item['form_labels'] )
			: array();
		$forms         = isset( $item['forms'] ) && is_array( $item['forms'] )
			? array_values( $item['forms'] )
			: array();
		$form_markers  = isset( $item['form_markers'] ) && is_array( $item['form_markers'] )
			? array_values( $item['form_markers'] )
			: array();
		$form_tooltips = isset( $item['form_tooltips'] ) && is_array( $item['form_tooltips'] )
			? array_values( $item['form_tooltips'] )
			: array();

		if ( $source_entry <= 0 ) {
			return '';
		}

		$lines = array();

		foreach ( $translations as $translation_row ) {
			if ( ! is_array( $translation_row ) ) {
				continue;
			}

			$form_index = isset( $translation_row['form_index'] ) ? absint( $translation_row['form_index'] ) : 0;
			$value      = isset( $translation_row['translation'] ) ? (string) $translation_row['translation'] : '';
			$input_id   = sprintf( 'i18nly-translation-%d-%d', $source_entry, $form_index );

			$input_html = $this->render_translation_input(
				$input_id,
				$source_entry,
				$form_index,
				$value,
				sprintf(
					/* translators: %d is plural form index. */
					_x( 'Translation form %d', 'input label for one translation plural form', 'i18nly' ),
					$form_index
				)
			);

			if ( ! $has_plural ) {
				$lines[] = sprintf( '<p class="i18nly-form-line">%s</p>', $input_html );
				continue;
			}

			$form_label   = $this->resolve_form_label( $form_index, $forms, $form_labels );
			$form_marker  = $this->resolve_form_marker( $form_index, $forms, $form_markers );
			$form_tooltip = $this->resolve_form_tooltip( $form_index, $forms, $form_tooltips, $form_label );

			$lines[] = sprintf(
				'<p class="i18nly-form-line">%1$s %2$s</p>',
				$this->render_form_marker(
					$form_marker,
					$form_tooltip
				),
				$input_html
			);
		}

		if ( empty( $lines ) ) {
			$lines[] = sprintf(
				'<p class="i18nly-form-line">%s</p>',
				$this->render_translation_input(
					sprintf( 'i18nly-translation-%d-0', $source_entry ),
					$source_entry,
					0,
					'',
					_x( 'Translation form 0', 'input label for one translation form', 'i18nly' )
				)
			);
		}

		if ( ! $has_plural ) {
			return (string) reset( $lines );
		}

		return implode( '', $lines );
	}

	/**
	 * Resolves one form marker label.
	 *
	 * @param int                              $form_index Plural form index.
	 * @param array<int, array<string, mixed>> $forms Ordered locale form metadata.
	 * @param array<int, mixed>                $form_labels Ordered locale form labels.
	 * @return string
	 */
	private function resolve_form_label( $form_index, array $forms, array $form_labels ) {
		if ( isset( $forms[ $form_index ] ) && is_array( $forms[ $form_index ] ) && isset( $forms[ $form_index ]['label'] ) ) {
			$label = (string) $forms[ $form_index ]['label'];

			if ( '' !== trim( $label ) ) {
				return $label;
			}
		}

		if ( isset( $form_labels[ $form_index ] ) ) {
			$label = (string) $form_labels[ $form_index ];

			if ( '' !== trim( $label ) ) {
				return $label;
			}
		}

		return (string) $form_index;
	}

	/**
	 * Resolves one marker symbol for one form index.
	 *
	 * @param int                              $form_index Plural form index.
	 * @param array<int, array<string, mixed>> $forms Ordered locale form metadata.
	 * @param array<int, mixed>                $form_markers Ordered marker symbols.
	 * @return string
	 */
	private function resolve_form_marker( $form_index, array $forms, array $form_markers ) {
		if ( isset( $forms[ $form_index ] ) && is_array( $forms[ $form_index ] ) && isset( $forms[ $form_index ]['marker'] ) ) {
			$marker = (string) $forms[ $form_index ]['marker'];

			if ( '' !== trim( $marker ) ) {
				return $marker;
			}
		}

		if ( isset( $form_markers[ $form_index ] ) ) {
			$marker = (string) $form_markers[ $form_index ];

			if ( '' !== trim( $marker ) ) {
				return $marker;
			}
		}

		return (string) $form_index;
	}

	/**
	 * Resolves one tooltip for one form index.
	 *
	 * @param int                              $form_index Plural form index.
	 * @param array<int, array<string, mixed>> $forms Ordered locale form metadata.
	 * @param array<int, mixed>                $form_tooltips Ordered form tooltips.
	 * @param string                           $fallback_label Fallback label.
	 * @return string
	 */
	private function resolve_form_tooltip( $form_index, array $forms, array $form_tooltips, $fallback_label ) {
		if ( isset( $forms[ $form_index ] ) && is_array( $forms[ $form_index ] ) && isset( $forms[ $form_index ]['tooltip'] ) ) {
			$tooltip = (string) $forms[ $form_index ]['tooltip'];

			if ( '' !== trim( $tooltip ) ) {
				return $tooltip;
			}
		}

		if ( isset( $form_tooltips[ $form_index ] ) ) {
			$tooltip = (string) $form_tooltips[ $form_index ];

			if ( '' !== trim( $tooltip ) ) {
				return $tooltip;
			}
		}

		if ( '' !== trim( $fallback_label ) ) {
			return $fallback_label;
		}

		return sprintf(
			/* translators: %d is plural form index. */
			__( 'Plural form %d', 'i18nly' ),
			$form_index
		);
	}

	/**
	 * Renders one translation text input.
	 *
	 * @param string $input_id Input ID.
	 * @param int    $source_entry Source entry ID.
	 * @param int    $form_index Plural form index.
	 * @param string $input_value Input value.
	 * @param string $input_label Accessible label.
	 * @return string
	 */
	private function render_translation_input( $input_id, $source_entry, $form_index, $input_value, $input_label ) {
		return sprintf(
			'<input type="text" class="regular-text i18nly-translation-input" id="%1$s" value="%2$s" data-i18nly-source-entry-id="%3$d" data-i18nly-form-index="%4$d" aria-label="%5$s"/>',
			esc_attr( $input_id ),
			esc_attr( $input_value ),
			(int) $source_entry,
			(int) $form_index,
			esc_attr( $input_label )
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

	/**
	 * Displays lightweight table navigation wrapper.
	 *
	 * Avoids injecting `_wpnonce` and `_wp_http_referer` hidden fields inside
	 * the post edit form, which can override WordPress core post nonce values.
	 *
	 * @param string $which Table nav location.
	 * @return void
	 */
	protected function display_tablenav( $which ) {
		echo '<div class="tablenav ' . esc_attr( (string) $which ) . '"></div>';
	}
}
