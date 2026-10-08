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
	 * Status badge renderer.
	 *
	 * @var EntryStatusBadges
	 */
	private $badges;

	/**
	 * Constructor.
	 *
	 * @param array<int, array<string, mixed>> $rows Translation entry rows.
	 */
	public function __construct( array $rows ) {
		$this->rows   = $rows;
		$this->badges = new EntryStatusBadges();

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
			'mark_as_draft'               => __( 'Mark as draft', 'i18nly' ),
			'mark_as_suspect'             => __( 'Mark as suspect', 'i18nly' ),
			'mark_as_validated'           => __( 'Mark as validated', 'i18nly' ),
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

		if ( '' !== $comment ) {
			$source = sprintf(
				'%1$s<p class="i18nly-translator-comment">%2$s</p>',
				$source,
				esc_html( $comment )
			);
		}

		return $source . $this->render_glossary_hints( isset( $item['glossary_matches'] ) && is_array( $item['glossary_matches'] ) ? $item['glossary_matches'] : array() );
	}

	/**
	 * Renders the glossary terms found in a source text, with the translation to use.
	 *
	 * A term whose translation is not used by a translated text is flagged.
	 *
	 * @param array<int, array<string, mixed>> $matches Matches as built by GlossaryMatcher.
	 * @return string
	 */
	private function render_glossary_hints( array $matches ) {
		if ( empty( $matches ) ) {
			return '';
		}

		$html = '<ul class="i18nly-glossary-hints">';

		foreach ( $matches as $match ) {
			$alternates = isset( $match['alternates'] ) && is_array( $match['alternates'] ) ? $match['alternates'] : array();
			$state      = isset( $match['qa'] ) ? (string) $match['qa'] : 'pending';
			$flag       = '';

			if ( 'missing' === $state ) {
				$flag = ' <span class="i18nly-glossary-flag">' . esc_html__( 'Not used in the translation', 'i18nly' ) . '</span>';
			} elseif ( 'ok' === $state ) {
				$flag = ' <span class="i18nly-glossary-flag" aria-label="' . esc_attr__( 'Used in the translation', 'i18nly' ) . '">&#10003;</span>';
			}

			$html .= sprintf(
				'<li class="i18nly-glossary-hint i18nly-glossary-hint--%1$s" title="%2$s"><strong>%3$s</strong> &rarr; %4$s%5$s%6$s%7$s</li>',
				esc_attr( $state ),
				esc_attr( isset( $match['glossary'] ) ? (string) $match['glossary'] : '' ),
				esc_html( isset( $match['term'] ) ? (string) $match['term'] : '' ),
				esc_html( isset( $match['preferred'] ) ? (string) $match['preferred'] : '' ),
				empty( $alternates ) ? '' : ' <span class="i18nly-glossary-alternates">(' . esc_html( implode( ' | ', array_map( 'strval', $alternates ) ) ) . ')</span>',
				isset( $match['note'] ) && '' !== trim( (string) $match['note'] ) ? ' <em class="i18nly-glossary-note">' . esc_html( (string) $match['note'] ) . '</em>' : '',
				$flag
			);
		}

		return $html . '</ul>';
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
		$forms         = PluralFormPresenter::from_item( $item );

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

			$form_label   = $forms->label( $form_index );
			$form_marker  = $forms->marker( $form_index );
			$form_tooltip = $forms->tooltip( $form_index );
			$witness      = $forms->witness_example( $form_index );
			$input_label  = '' !== trim( $form_tooltip ) ? $form_tooltip : $form_label;
			$input_html   = $this->render_translation_input(
				$input_id,
				$source_entry,
				$form_index,
				$value,
				$input_label,
				$forms->source_text( $form_index, $singular, $source_plural ),
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
		$source_entry  = isset( $item['source_entry_id'] ) ? absint( $item['source_entry_id'] ) : 0;
		$source_status = isset( $item['source_status'] )
			? (string) $item['source_status']
			: ( isset( $item['status'] ) ? (string) $item['status'] : 'active' );
		$is_obsolete   = 'obsolete' === $source_status;
		$source_plural = isset( $item['msgid_plural'] ) ? (string) $item['msgid_plural'] : '';
		$has_plural    = '' !== trim( $source_plural );
		$translations  = isset( $item['translations'] ) && is_array( $item['translations'] )
			? $item['translations']
			: array();

		if ( $source_entry <= 0 ) {
			return '';
		}

		$lines = array();

		foreach ( $translations as $translation_row ) {
			if ( ! is_array( $translation_row ) ) {
				continue;
			}

			$form_index          = isset( $translation_row['form_index'] ) ? absint( $translation_row['form_index'] ) : 0;
			$current_translation = isset( $translation_row['translation'] ) ? (string) $translation_row['translation'] : '';
			$current_status      = $this->badges->normalize_status( isset( $translation_row['status'] ) ? (string) $translation_row['status'] : '' );
			$used_ai             = isset( $translation_row['used_ai'] ) ? (int) $translation_row['used_ai'] : 0;
			$used_manual         = isset( $translation_row['used_manual'] )
				? (int) $translation_row['used_manual']
				: ( '' !== trim( $current_translation ) ? 1 : 0 );
			$input_id            = sprintf( 'i18nly-translation-%d-%d', $source_entry, $form_index );

			if ( '' === trim( $current_translation ) ) {
				$current_status = '';
				$used_ai        = 0;
				$used_manual    = 0;
			}

			if ( '' === $current_status && '' !== trim( $current_translation ) ) {
				$current_status = 'draft';
			}

			$badge_html = $this->badges->render_quality_badge( $input_id, $current_status )
				. $this->badges->render_provenance_badges( $input_id, $used_ai, $used_manual )
				. $this->badges->render_obsolete_badge( $is_obsolete );

			if ( ! $has_plural ) {
				$lines[] = sprintf( '<p class="i18nly-form-line">%s</p>', $badge_html );
				continue;
			}

			$lines[] = sprintf( '<p class="i18nly-form-line">%s</p>', $badge_html );
		}

		if ( empty( $lines ) ) {
			$lines[] = sprintf(
				'<p class="i18nly-form-line">%s</p>',
				$this->badges->render_quality_badge( sprintf( 'i18nly-translation-%d-0', $source_entry ), '' )
				. $this->badges->render_provenance_badges( sprintf( 'i18nly-translation-%d-0', $source_entry ), 0, 0 )
				. $this->badges->render_obsolete_badge( $is_obsolete )
			);
		}

		if ( ! $has_plural ) {
			return (string) reset( $lines );
		}

		return implode( '', $lines );
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
		$status = isset( $item['source_status'] )
			? (string) $item['source_status']
			: ( isset( $item['status'] ) ? (string) $item['status'] : 'active' );

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
