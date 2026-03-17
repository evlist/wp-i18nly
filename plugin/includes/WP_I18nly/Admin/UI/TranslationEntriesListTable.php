<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation entries list table.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin\UI;

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
			'cb'          => '<input type="checkbox" class="i18nly-bulk-select-all" aria-label="' . esc_attr__( 'Select all translation entries', 'i18nly' ) . '" />',
			'msgctxt'     => __( 'Context', 'i18nly' ),
			'msgid'       => __( 'Source string', 'i18nly' ),
			'translation' => __( 'Translation', 'i18nly' ),
			'status'      => __( 'Status', 'i18nly' ),
		);
	}

	/**
	 * Returns available bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return array(
			'clear_selected_translations' => __( 'Clear selected translations', 'i18nly' ),
			'copy_source_to_translation'  => __( 'Copy source to translation', 'i18nly' ),
			'ai_translate_selected'       => __( 'Translate selected with AI', 'i18nly' ),
		);
	}

	/**
	 * Renders row selection checkbox.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	public function column_cb( $item ) {
		$source_entry_id = isset( $item['source_entry_id'] ) ? absint( $item['source_entry_id'] ) : 0;

		if ( $source_entry_id <= 0 ) {
			return '';
		}

		return sprintf(
			'<input type="checkbox" class="i18nly-entry-checkbox" value="%1$d" aria-label="%2$s" />',
			$source_entry_id,
			esc_attr(
				sprintf(
					/* translators: %d: source entry ID. */
					__( 'Select translation entry %d', 'i18nly' ),
					$source_entry_id
				)
			)
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
		$comment    = isset( $item['translator_comment'] ) ? trim( (string) $item['translator_comment'] ) : '';
		$source     = $this->render_stacked_text_pair( $singular, $plural, $has_plural );

		if ( '' === $comment ) {
			return $source;
		}

		return sprintf(
			'%1$s<p class="i18nly-translator-comment">%2$s</p>',
			$source,
			esc_html( $comment )
		);
	}

	/**
	 * Renders translation singular/plural values in one stacked cell.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	public function column_translation( $item ) {
		$singular      = isset( $item['msgid'] ) ? (string) $item['msgid'] : '';
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

			if ( ! $has_plural ) {
				$lines[] = sprintf(
					'<p class="i18nly-form-line">%s</p>',
					$this->render_translation_input(
						$input_id,
						$source_entry,
						$form_index,
						$value,
						__( 'Translation', 'i18nly' ),
						$singular
					)
				);
				continue;
			}

			$form_label   = $this->resolve_form_label( $form_index, $forms, $form_labels );
			$form_marker  = $this->resolve_form_marker( $form_index, $forms, $form_markers );
			$form_tooltip = $this->resolve_form_tooltip( $form_index, $forms, $form_tooltips );
			$witness      = $this->resolve_form_witness_example( $form_index, $forms );
			$input_label  = '' !== trim( $form_tooltip ) ? $form_tooltip : $form_label;
			$input_html   = $this->render_translation_input(
				$input_id,
				$source_entry,
				$form_index,
				$value,
				$input_label,
				$this->get_source_text_for_form( $form_index, $singular, $source_plural, $forms ),
				$witness
			);

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
					__( 'Translation', 'i18nly' ),
					$singular,
					1
				)
			);
		}

		if ( ! $has_plural ) {
			return (string) reset( $lines );
		}

		return implode( '', $lines );
	}

	/**
	 * Renders status cell.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	public function column_status( $item ) {
		$status       = isset( $item['status'] ) ? (string) $item['status'] : 'active';
		$status_class = 'obsolete' === $status ? 'i18nly-entry-status--obsolete' : 'i18nly-entry-status--active';
		$label        = 'obsolete' === $status ? __( 'Obsolete', 'i18nly' ) : __( 'Active', 'i18nly' );

		return sprintf(
			'<span class="i18nly-entry-status %1$s">%2$s</span>',
			esc_attr( $status_class ),
			esc_html( $label )
		);
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
	 * @return string
	 */
	private function resolve_form_tooltip( $form_index, array $forms, array $form_tooltips ) {
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

		return '';
	}

	/**
	 * Renders one translation text input.
	 *
	 * @param string $input_id Input ID.
	 * @param int    $source_entry Source entry ID.
	 * @param int    $form_index Plural form index.
	 * @param string $input_value Input value.
	 * @param string $input_label Accessible label.
	 * @param string $source_text Source text used by client-side bulk actions.
	 * @param int    $witness_example Representative numeric witness for this form.
	 * @return string
	 */
	private function render_translation_input( $input_id, $source_entry, $form_index, $input_value, $input_label, $source_text, $witness_example = 0 ) {
		$input_html = sprintf(
			'<input type="text" class="regular-text i18nly-translation-input" id="%1$s" value="%2$s" data-i18nly-source-entry-id="%3$d" data-i18nly-form-index="%4$d" data-i18nly-source-text="%5$s" data-i18nly-witness="%6$d" aria-label="%7$s"/>',
			esc_attr( $input_id ),
			esc_attr( $input_value ),
			(int) $source_entry,
			(int) $form_index,
			esc_attr( $source_text ),
			(int) $witness_example,
			esc_attr( $input_label )
		);

		$translate_button = sprintf(
			'<button type="button" class="i18nly-translate-btn" data-for="%s" aria-label="%s">🤖</button>',
			esc_attr( $input_id ),
			esc_attr__( 'Translate with AI', 'i18nly' )
		);

		return $input_html . ' ' . $translate_button;
	}

	/**
	 * Returns the source text matching one target form.
	 *
	 * @param int                              $form_index Plural form index.
	 * @param string                           $singular Singular source string.
	 * @param string                           $plural Plural source string.
	 * @param array<int, array<string, mixed>> $forms Ordered locale form metadata.
	 * @return string
	 */
	private function get_source_text_for_form( $form_index, $singular, $plural, array $forms = array() ) {
		if ( isset( $forms[ $form_index ] ) && is_array( $forms[ $form_index ] ) && isset( $forms[ $form_index ]['examples'] ) && is_array( $forms[ $form_index ]['examples'] ) ) {
			$examples = array_values( $forms[ $form_index ]['examples'] );

			if ( ! empty( $examples ) ) {
				$witness = (int) $examples[0];

				return 1 === $witness ? $singular : $plural;
			}
		}

		return 0 === (int) $form_index ? $singular : $plural;
	}

	/**
	 * Returns one representative witness number for one target form.
	 *
	 * @param int                              $form_index Plural form index.
	 * @param array<int, array<string, mixed>> $forms Ordered locale form metadata.
	 * @return int
	 */
	private function resolve_form_witness_example( $form_index, array $forms ) {
		if ( isset( $forms[ $form_index ] ) && is_array( $forms[ $form_index ] ) && isset( $forms[ $form_index ]['examples'] ) && is_array( $forms[ $form_index ]['examples'] ) ) {
			$examples = array_values( $forms[ $form_index ]['examples'] );

			if ( ! empty( $examples ) ) {
				return (int) $examples[0];
			}
		}

		return 0 === (int) $form_index ? 1 : 2;
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
	 * Renders one table row with status metadata.
	 *
	 * @param array<string, mixed> $item Current row.
	 * @return void
	 */
	public function single_row( $item ) {
		$status = isset( $item['status'] ) ? (string) $item['status'] : 'active';

		echo '<tr class="i18nly-translation-entry" data-entry-status="' . esc_attr( $status ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
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

		$source_forms     = \WP_I18nly\Plurals\PluralFormsRegistry::get_forms_for_locale( 'en_US' );
		$singular_form    = ( isset( $source_forms[0] ) && is_array( $source_forms[0] ) ) ? $source_forms[0] : array();
		$plural_form      = ( isset( $source_forms[1] ) && is_array( $source_forms[1] ) ) ? $source_forms[1] : array();
		$singular_symbol  = isset( $singular_form['marker'] ) ? (string) $singular_form['marker'] : '';
		$singular_tooltip = isset( $singular_form['tooltip'] ) ? (string) $singular_form['tooltip'] : '';
		$plural_symbol    = isset( $plural_form['marker'] ) ? (string) $plural_form['marker'] : '';
		$plural_tooltip   = isset( $plural_form['tooltip'] ) ? (string) $plural_form['tooltip'] : '';

		$singular_marker = $this->render_form_marker(
			$singular_symbol,
			$singular_tooltip
		);
		$plural_marker   = $this->render_form_marker(
			$plural_symbol,
			$plural_tooltip
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
		$bulk_actions = $this->get_bulk_actions();

		echo '<div class="tablenav ' . esc_attr( (string) $which ) . '">';
		echo '<div class="alignleft actions bulkactions">';
		echo '<label for="bulk-action-selector-' . esc_attr( (string) $which ) . '" class="screen-reader-text">' . esc_html__( 'Select bulk action', 'i18nly' ) . '</label>';
		echo '<select id="bulk-action-selector-' . esc_attr( (string) $which ) . '" class="i18nly-bulk-action-selector">';
		echo '<option value="">' . esc_html__( 'Bulk actions', 'i18nly' ) . '</option>';

		foreach ( $bulk_actions as $action => $label ) {
			echo '<option value="' . esc_attr( $action ) . '">' . esc_html( $label ) . '</option>';
		}

		echo '</select>';
		echo '<button type="button" class="button action i18nly-bulk-apply" disabled="disabled" aria-disabled="true">' . esc_html__( 'Apply', 'i18nly' ) . '</button>';
		echo '</div>';
		echo '<br class="clear" />';
		echo '</div>';
	}
}
