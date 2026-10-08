<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Abstract linguistic resource target.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

defined( 'ABSPATH' ) || exit;

/**
 * Base value object for one target value of a linguistic resource entry.
 */
abstract class AbstractLinguisticResourceTarget {
	/**
	 * Raw normalized row.
	 *
	 * @var array<string, mixed>
	 */
	private $row;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $row Raw target row.
	 */
	public function __construct( array $row ) {
		$this->row = $row;
	}

	/**
	 * Returns resource target kind.
	 *
	 * @return string
	 */
	abstract public function get_target_kind();

	/**
	 * Returns the plural form index.
	 *
	 * @return int
	 */
	public function get_form_index() {
		return isset( $this->row['form_index'] ) ? max( 0, (int) $this->row['form_index'] ) : 0;
	}

	/**
	 * Returns the target text.
	 *
	 * @return string
	 */
	public function get_text() {
		return isset( $this->row['translation'] ) ? (string) $this->row['translation'] : '';
	}

	/**
	 * Returns raw row.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return $this->row;
	}

	/**
	 * Returns one row value.
	 *
	 * @param string $key Row key.
	 * @param mixed  $fallback Fallback value.
	 * @return mixed
	 */
	protected function get_row_value( $key, $fallback = null ) {
		if ( ! array_key_exists( (string) $key, $this->row ) ) {
			return $fallback;
		}

		return $this->row[ $key ];
	}
}
