<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Plural form presenter.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the label, marker, tooltip, source text and witness number of the plural forms of an entry.
 */
class PluralFormPresenter {
	/**
	 * Ordered locale form metadata (marker, label, tooltip, examples).
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $forms;

	/**
	 * Ordered form labels.
	 *
	 * @var array<int, mixed>
	 */
	private $labels;

	/**
	 * Ordered marker symbols.
	 *
	 * @var array<int, mixed>
	 */
	private $markers;

	/**
	 * Ordered form tooltips.
	 *
	 * @var array<int, mixed>
	 */
	private $tooltips;

	/**
	 * Constructor.
	 *
	 * @param array<int, array<string, mixed>> $forms Ordered locale form metadata.
	 * @param array<int, mixed>                $labels Ordered form labels.
	 * @param array<int, mixed>                $markers Ordered marker symbols.
	 * @param array<int, mixed>                $tooltips Ordered form tooltips.
	 */
	public function __construct( array $forms = array(), array $labels = array(), array $markers = array(), array $tooltips = array() ) {
		$this->forms    = array_values( $forms );
		$this->labels   = array_values( $labels );
		$this->markers  = array_values( $markers );
		$this->tooltips = array_values( $tooltips );
	}

	/**
	 * Builds a presenter from the plural form metadata carried by one entry row.
	 *
	 * @param array<string, mixed> $item Entry row.
	 * @return self
	 */
	public static function from_item( array $item ) {
		return new self(
			isset( $item['forms'] ) && is_array( $item['forms'] ) ? $item['forms'] : array(),
			isset( $item['form_labels'] ) && is_array( $item['form_labels'] ) ? $item['form_labels'] : array(),
			isset( $item['form_markers'] ) && is_array( $item['form_markers'] ) ? $item['form_markers'] : array(),
			isset( $item['form_tooltips'] ) && is_array( $item['form_tooltips'] ) ? $item['form_tooltips'] : array()
		);
	}

	/**
	 * Resolves the label of one plural form.
	 *
	 * @param int $form_index Plural form index.
	 * @return string
	 */
	public function label( $form_index ) {
		return $this->resolve_text( $form_index, 'label', $this->labels, (string) $form_index );
	}

	/**
	 * Resolves the marker symbol of one plural form.
	 *
	 * @param int $form_index Plural form index.
	 * @return string
	 */
	public function marker( $form_index ) {
		return $this->resolve_text( $form_index, 'marker', $this->markers, (string) $form_index );
	}

	/**
	 * Resolves the tooltip of one plural form.
	 *
	 * @param int $form_index Plural form index.
	 * @return string
	 */
	public function tooltip( $form_index ) {
		return $this->resolve_text( $form_index, 'tooltip', $this->tooltips, '' );
	}

	/**
	 * Returns the source text matching one target form.
	 *
	 * @param int    $form_index Plural form index.
	 * @param string $singular Singular source string.
	 * @param string $plural Plural source string.
	 * @return string
	 */
	public function source_text( $form_index, $singular, $plural ) {
		$examples = $this->examples( $form_index );

		if ( ! empty( $examples ) ) {
			foreach ( $examples as $example ) {
				if ( 1 === (int) $example ) {
					return $singular;
				}
			}

			return $plural;
		}

		return 0 === (int) $form_index ? $singular : $plural;
	}

	/**
	 * Returns one representative witness number for one target form.
	 *
	 * @param int $form_index Plural form index.
	 * @return int
	 */
	public function witness_example( $form_index ) {
		$examples = $this->examples( $form_index );

		if ( ! empty( $examples ) ) {
			foreach ( $examples as $example ) {
				if ( 1 === (int) $example ) {
					return 1;
				}
			}

			return (int) $examples[0];
		}

		return 0 === (int) $form_index ? 1 : 2;
	}

	/**
	 * Resolves one text of a form: the locale form metadata first, then the flat list, then a fallback.
	 *
	 * @param int               $form_index Plural form index.
	 * @param string            $key Key in the locale form metadata.
	 * @param array<int, mixed> $list Flat list indexed by form index.
	 * @param string            $fallback Value used when neither source has a non-blank text.
	 * @return string
	 */
	private function resolve_text( $form_index, $key, array $list, $fallback ) {
		if ( isset( $this->forms[ $form_index ] ) && is_array( $this->forms[ $form_index ] ) && isset( $this->forms[ $form_index ][ $key ] ) ) {
			$text = (string) $this->forms[ $form_index ][ $key ];

			if ( '' !== trim( $text ) ) {
				return $text;
			}
		}

		if ( isset( $list[ $form_index ] ) ) {
			$text = (string) $list[ $form_index ];

			if ( '' !== trim( $text ) ) {
				return $text;
			}
		}

		return $fallback;
	}

	/**
	 * Returns the example numbers of one plural form.
	 *
	 * @param int $form_index Plural form index.
	 * @return array<int, mixed>
	 */
	private function examples( $form_index ) {
		if ( isset( $this->forms[ $form_index ] ) && is_array( $this->forms[ $form_index ] ) && isset( $this->forms[ $form_index ]['examples'] ) && is_array( $this->forms[ $form_index ]['examples'] ) ) {
			return array_values( $this->forms[ $form_index ]['examples'] );
		}

		return array();
	}
}
