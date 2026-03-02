<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Test wpdb stub.
 *
 * @package I18nly
 */

/**
 * Minimal wpdb test stub.
 */
class I18nly_Test_WPDB {
	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public $prefix = 'wp_';

	/**
	 * Stored translation rows.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $translations = array();

	/**
	 * Last inserted ID.
	 *
	 * @var int
	 */
	public $insert_id = 0;

	/**
	 * Sets translation rows returned by get_results.
	 *
	 * @param array<int, array<string, mixed>> $translations Translation rows.
	 * @return void
	 */
	public function set_translations( array $translations ) {
		$this->translations = $translations;
	}

	/**
	 * Simulates wpdb::prepare.
	 *
	 * @param string $query SQL query with placeholders.
	 * @param mixed  ...$args Placeholder arguments.
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		$normalized = str_replace( '%i', '%s', (string) $query );

		return (string) vsprintf( $normalized, $args );
	}

	/**
	 * Simulates wpdb::get_results.
	 *
	 * @param string $query SQL query.
	 * @param string $output_type Output type.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_results( $query, $output_type ) {
		unset( $query, $output_type );

		return $this->translations;
	}

	/**
	 * Simulates wpdb::get_row.
	 *
	 * @param string $query SQL query.
	 * @param string $output_type Output type.
	 * @return array<string, mixed>|null
	 */
	public function get_row( $query, $output_type ) {
		unset( $output_type );

		if ( ! preg_match( '/WHERE id = (\d+)/', (string) $query, $matches ) ) {
			return null;
		}

		$translation_id = (int) $matches[1];

		foreach ( $this->translations as $translation ) {
			if ( isset( $translation['id'] ) && $translation_id === (int) $translation['id'] ) {
				return $translation;
			}
		}

		return null;
	}

	/**
	 * Simulates wpdb::insert.
	 *
	 * @param string               $table_name Table name.
	 * @param array<string, mixed> $data Insert data.
	 * @param array<int, string>   $formats Value formats.
	 * @return int|false
	 */
	public function insert( $table_name, array $data, array $formats ) {
		unset( $table_name, $formats );

		++$this->insert_id;
		$data['id']           = $this->insert_id;
		$this->translations[] = $data;

		return 1;
	}
}
