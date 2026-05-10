<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Source wpdb repository DB access helpers.
 *
 * @package I18nly
 */

namespace WP_I18nly\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Shares low-level `wpdb` helper methods for the source repository.
 */
trait SourceWpdbRepositoryDbAccessTrait {
	/**
	 * Validates and escapes a table name.
	 *
	 * @param string $table_name Raw table name.
	 * @return string
	 */
	private function escape_table_name( $table_name ) {
		$table_name = (string) $table_name;

		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $table_name ) ) {
			return '';
		}

		if ( function_exists( 'esc_sql' ) ) {
			return (string) esc_sql( $table_name );
		}

		return $table_name;
	}

	/**
	 * Executes one scalar read query.
	 *
	 * @param string $query Prepared query.
	 * @return mixed
	 */
	private function db_get_var( $query ) {
		$method = 'get_var';

		return $this->wpdb->{$method}( $query );
	}

	/**
	 * Executes one row read query.
	 *
	 * @param string $query Prepared query.
	 * @param string $output Output type.
	 * @return array<string, mixed>|null
	 */
	private function db_get_row( $query, $output = OBJECT ) {
		$method = 'get_row';
		$result = $this->wpdb->{$method}( $query, $output );

		return is_array( $result ) ? $result : null;
	}

	/**
	 * Executes one multi-row read query.
	 *
	 * @param string $query Prepared query.
	 * @param string $output Output type.
	 * @return array<int, array<string, mixed>>
	 */
	private function db_get_results( $query, $output = OBJECT ) {
		$method = 'get_results';

		if ( ! method_exists( $this->wpdb, $method ) ) {
			return array();
		}

		$results = $this->wpdb->{$method}( $query, $output );

		if ( ! is_array( $results ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $results as $row ) {
			if ( is_array( $row ) ) {
				$normalized[] = $row;
			}
		}

		return $normalized;
	}

	/**
	 * Executes one write query.
	 *
	 * @param string $query Prepared query.
	 * @return int|false
	 */
	private function db_query( $query ) {
		$method = 'query';

		return $this->wpdb->{$method}( $query );
	}
}
