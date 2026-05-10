<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Abstract linguistic resource entry model.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

defined( 'ABSPATH' ) || exit;

/**
 * Base value object for one linguistic resource entry.
 */
abstract class AbstractLinguisticResourceEntry {
	/**
	 * Raw normalized row.
	 *
	 * @var array<string, mixed>
	 */
	private $row;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $row Raw row data.
	 */
	public function __construct( array $row ) {
		$this->row = $row;
	}

	/**
	 * Returns resource entry kind.
	 *
	 * @return string
	 */
	abstract public function get_entry_kind();

	/**
	 * Returns source entry ID.
	 *
	 * @return int
	 */
	public function get_source_entry_id() {
		return isset( $this->row['source_entry_id'] ) ? max( 0, (int) $this->row['source_entry_id'] ) : 0;
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
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	protected function get_row_value( $key, $default = null ) {
		if ( ! array_key_exists( (string) $key, $this->row ) ) {
			return $default;
		}

		return $this->row[ $key ];
	}

	/**
	 * Returns a cloned entry with one overridden value.
	 *
	 * @param string $key Row key.
	 * @param mixed  $value Row value.
	 * @return static
	 */
	protected function with_row_value( $key, $value ) {
		$row = $this->row;
		$row[ (string) $key ] = $value;

		return new static( $row );
	}
}