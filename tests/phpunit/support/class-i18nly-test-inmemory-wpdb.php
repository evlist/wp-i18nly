<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * In-memory wpdb double for repository tests.
 *
 * @package I18nly
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile, WordPress.DB, WordPress.Security.EscapeOutput

/**
 * Simulates the three I18nly business tables in memory.
 *
 * It supports the write API (insert, update, delete), transactions with rollback, case-insensitive
 * unique keys like a utf8mb4 *_ci collation, and the simple SELECT statements issued by the
 * repositories: a column list with optional aliases, equality and IS NULL conditions joined by AND,
 * ORDER BY and LIMIT.
 */
class I18nly_Test_InMemory_Wpdb extends I18nly_Test_WPDB_Stub {
	/**
	 * Rows by short table name, indexed by ID.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private $tables = array(
		'i18nly_linguistic_resources'        => array(),
		'i18nly_linguistic_resource_entries' => array(),
		'i18nly_linguistic_resource_targets' => array(),
	);

	/**
	 * Last auto-increment value by short table name.
	 *
	 * @var array<string, int>
	 */
	private $auto_increment = array();

	/**
	 * Column defaults applied by inserts.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $defaults = array(
		'i18nly_linguistic_resources'        => array(
			'anchor_post_id' => 0,
			'target_locale'  => '',
		),
		'i18nly_linguistic_resource_entries' => array(
			'match_mode' => 'exact',
			'status'     => 'active',
		),
		'i18nly_linguistic_resource_targets' => array(
			'status'      => 'draft',
			'used_ai'     => 0,
			'used_manual' => 1,
		),
	);

	/**
	 * Unique keys by short table name.
	 *
	 * @var array<string, array<int, array<int, string>>>
	 */
	private $unique_keys = array(
		'i18nly_linguistic_resources'        => array(
			array( 'resource_kind', 'source_slug', 'target_locale', 'anchor_post_id' ),
		),
		'i18nly_linguistic_resource_entries' => array(
			array( 'resource_id', 'msgctxt', 'msgid' ),
		),
		'i18nly_linguistic_resource_targets' => array(
			array( 'resource_id', 'source_entry_id', 'form_index' ),
		),
	);

	/**
	 * Snapshot taken by START TRANSACTION.
	 *
	 * @var array<string, mixed>|null
	 */
	private $snapshot = null;

	/**
	 * Number of upcoming inserts to fail, by short table name.
	 *
	 * @var array<string, int>
	 */
	private $failing_inserts = array();

	/**
	 * Executed statements.
	 *
	 * @var array<int, string>
	 */
	public $statements = array();

	/**
	 * Makes the next inserts into a table fail.
	 *
	 * @param string $table Short table name.
	 * @param int    $skip Number of inserts allowed before the failure.
	 * @return void
	 */
	public function fail_insert_into( $table, $skip = 0 ) {
		$this->failing_inserts[ $table ] = (int) $skip;
	}

	/**
	 * Returns every row of a table.
	 *
	 * @param string $table Short table name.
	 * @return array<int, array<string, mixed>>
	 */
	public function rows( $table ) {
		return array_values( $this->tables[ $table ] );
	}

	/**
	 * Inserts a row.
	 *
	 * @param string               $table Table name.
	 * @param array<string, mixed> $data Row data.
	 * @param array<int, string>   $format Ignored.
	 * @return int|false
	 */
	public function insert( $table, $data, $format = null ) {
		unset( $format );

		$name = $this->short_name( $table );

		if ( isset( $this->failing_inserts[ $name ] ) ) {
			if ( $this->failing_inserts[ $name ] <= 0 ) {
				unset( $this->failing_inserts[ $name ] );

				return false;
			}

			--$this->failing_inserts[ $name ];
		}

		$row = array_merge( $this->defaults[ $name ], $data );

		if ( $this->violates_unique_key( $name, $row, 0 ) ) {
			return false;
		}

		$this->auto_increment[ $name ] = ( isset( $this->auto_increment[ $name ] ) ? $this->auto_increment[ $name ] : 0 ) + 1;
		$row['id']                     = $this->auto_increment[ $name ];
		$this->insert_id               = $row['id'];

		$this->tables[ $name ][ $row['id'] ] = $row;

		return 1;
	}

	/**
	 * Updates rows matching the where clause.
	 *
	 * @param string               $table Table name.
	 * @param array<string, mixed> $data New values.
	 * @param array<string, mixed> $where Match conditions.
	 * @param array<int, string>   $format Ignored.
	 * @param array<int, string>   $where_format Ignored.
	 * @return int|false
	 */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		unset( $format, $where_format );

		$name    = $this->short_name( $table );
		$updated = 0;

		foreach ( $this->tables[ $name ] as $id => $row ) {
			if ( ! $this->row_matches( $row, $where ) ) {
				continue;
			}

			$new_row = array_merge( $row, $data );

			if ( $this->violates_unique_key( $name, $new_row, $id ) ) {
				return false;
			}

			if ( $new_row !== $row ) {
				$this->tables[ $name ][ $id ] = $new_row;
				++$updated;
			}
		}

		return $updated;
	}

	/**
	 * Deletes rows matching the where clause.
	 *
	 * @param string               $table Table name.
	 * @param array<string, mixed> $where Match conditions.
	 * @param array<int, string>   $where_format Ignored.
	 * @return int|false
	 */
	public function delete( $table, $where, $where_format = null ) {
		unset( $where_format );

		$name    = $this->short_name( $table );
		$deleted = 0;

		foreach ( $this->tables[ $name ] as $id => $row ) {
			if ( $this->row_matches( $row, $where ) ) {
				unset( $this->tables[ $name ][ $id ] );
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * Handles transaction statements.
	 *
	 * @param string $sql SQL statement.
	 * @return int
	 */
	public function query( $sql ) {
		$statement          = strtoupper( trim( (string) $sql ) );
		$this->statements[] = $statement;

		if ( 'START TRANSACTION' === $statement ) {
			$this->snapshot = array(
				'tables'         => $this->tables,
				'auto_increment' => $this->auto_increment,
			);
		} elseif ( 'ROLLBACK' === $statement && null !== $this->snapshot ) {
			$this->tables         = $this->snapshot['tables'];
			$this->auto_increment = $this->snapshot['auto_increment'];
			$this->snapshot       = null;
		} elseif ( 'COMMIT' === $statement ) {
			$this->snapshot = null;
		}

		return 1;
	}

	/**
	 * Returns the first column of the first matching row.
	 *
	 * @param string $query SQL query.
	 * @return mixed
	 */
	public function get_var( $query ) {
		$rows = $this->select( (string) $query );

		if ( array() === $rows ) {
			return null;
		}

		$first = reset( $rows );

		return reset( $first );
	}

	/**
	 * Returns the first matching row.
	 *
	 * @param string $query SQL query.
	 * @param string $output Ignored, rows are always associative arrays.
	 * @return array<string, mixed>|null
	 */
	public function get_row( $query, $output = ARRAY_A ) {
		unset( $output );

		$rows = $this->select( (string) $query );

		return array() === $rows ? null : reset( $rows );
	}

	/**
	 * Returns every matching row.
	 *
	 * @param string $query SQL query.
	 * @param string $output Ignored, rows are always associative arrays.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_results( $query, $output = ARRAY_A ) {
		unset( $output );

		return $this->select( (string) $query );
	}

	/**
	 * Executes a simple SELECT statement.
	 *
	 * @param string $query SQL query.
	 * @return array<int, array<string, mixed>>
	 * @throws \RuntimeException When the table or the statement is not supported.
	 */
	private function select( $query ) {
		$pattern = '/^\s*SELECT\s+(.+?)\s+FROM\s+`?(\w+)`?(?:\s+WHERE\s+(.+?))?(?:\s+ORDER\s+BY\s+(.+?))?(?:\s+LIMIT\s+(\d+))?\s*$/is';

		if ( 1 !== preg_match( $pattern, $query, $matches ) ) {
			throw new \RuntimeException( 'Unsupported query in the in-memory wpdb: ' . $query );
		}

		$name       = $this->short_name( $matches[2] );
		$conditions = isset( $matches[3] ) && '' !== $matches[3] ? $this->parse_conditions( $matches[3] ) : array();
		$rows       = array();

		foreach ( $this->tables[ $name ] as $row ) {
			if ( $this->row_matches( $row, $conditions ) ) {
				$rows[] = $row;
			}
		}

		if ( isset( $matches[4] ) && '' !== $matches[4] ) {
			$this->sort_rows( $rows, $matches[4] );
		}

		if ( isset( $matches[5] ) && '' !== $matches[5] ) {
			$rows = array_slice( $rows, 0, (int) $matches[5] );
		}

		return array_map(
			function ( array $row ) use ( $matches ) {
				return $this->project( $row, $matches[1] );
			},
			$rows
		);
	}

	/**
	 * Projects a row on a column list with optional aliases.
	 *
	 * @param array<string, mixed> $row Row.
	 * @param string               $columns Column list.
	 * @return array<string, mixed>
	 */
	private function project( array $row, $columns ) {
		if ( '*' === trim( $columns ) ) {
			return $row;
		}

		$projected = array();

		foreach ( explode( ',', $columns ) as $column ) {
			if ( 1 === preg_match( '/^\s*(\w+)(?:\s+AS\s+(\w+))?\s*$/i', $column, $parts ) ) {
				$projected[ isset( $parts[2] ) && '' !== $parts[2] ? $parts[2] : $parts[1] ] = array_key_exists( $parts[1], $row ) ? $row[ $parts[1] ] : null;
			}
		}

		return $projected;
	}

	/**
	 * Parses WHERE conditions joined by AND.
	 *
	 * @param string $where WHERE clause.
	 * @return array<string, mixed> Conditions by column; null means IS NULL.
	 * @throws \RuntimeException When the table or the statement is not supported.
	 */
	private function parse_conditions( $where ) {
		$conditions = array();

		foreach ( preg_split( '/\s+AND\s+/i', $where ) as $condition ) {
			if ( 1 === preg_match( '/^\s*`?(\w+)`?\s+IS\s+NULL\s*$/i', $condition, $parts ) ) {
				$conditions[ $parts[1] ] = null;
			} elseif ( 1 === preg_match( "/^\s*`?(\w+)`?\s*=\s*'((?:[^'\\\\\\\\]|\\\\\\\\.)*)'\s*$/s", $condition, $parts ) ) {
				$conditions[ $parts[1] ] = stripslashes( $parts[2] );
			} elseif ( 1 === preg_match( '/^\s*`?(\w+)`?\s*=\s*(-?\d+)\s*$/', $condition, $parts ) ) {
				$conditions[ $parts[1] ] = (int) $parts[2];
			} else {
				throw new \RuntimeException( 'Unsupported condition in the in-memory wpdb: ' . $condition );
			}
		}

		return $conditions;
	}

	/**
	 * Sorts rows according to an ORDER BY clause.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows, sorted by reference.
	 * @param string                           $order_by ORDER BY clause.
	 * @return void
	 */
	private function sort_rows( array &$rows, $order_by ) {
		$sorts = array();

		foreach ( explode( ',', $order_by ) as $part ) {
			if ( 1 === preg_match( '/^\s*`?(\w+)`?(?:\s+(ASC|DESC))?\s*$/i', $part, $parts ) ) {
				$sorts[] = array( $parts[1], isset( $parts[2] ) && 'DESC' === strtoupper( $parts[2] ) ? -1 : 1 );
			}
		}

		usort(
			$rows,
			function ( array $left, array $right ) use ( $sorts ) {
				foreach ( $sorts as $sort ) {
					$a = isset( $left[ $sort[0] ] ) ? $left[ $sort[0] ] : null;
					$b = isset( $right[ $sort[0] ] ) ? $right[ $sort[0] ] : null;

					$result = is_numeric( $a ) && is_numeric( $b )
						? $a <=> $b
						: strcmp( mb_strtolower( (string) $a, 'UTF-8' ), mb_strtolower( (string) $b, 'UTF-8' ) );

					if ( 0 !== $result ) {
						return $result * $sort[1];
					}
				}

				return 0;
			}
		);
	}

	/**
	 * Tells whether a row satisfies equality conditions, comparing text like a *_ci collation.
	 *
	 * @param array<string, mixed> $row Row.
	 * @param array<string, mixed> $conditions Conditions by column; null means IS NULL.
	 * @return bool
	 */
	private function row_matches( array $row, array $conditions ) {
		foreach ( $conditions as $column => $expected ) {
			$actual = array_key_exists( $column, $row ) ? $row[ $column ] : null;

			if ( null === $expected || null === $actual ) {
				if ( $expected !== $actual ) {
					return false;
				}

				continue;
			}

			if ( $this->fold( $actual ) !== $this->fold( $expected ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Tells whether a row would break a unique key; keys containing a NULL never conflict.
	 *
	 * @param string               $name Short table name.
	 * @param array<string, mixed> $row Candidate row.
	 * @param int                  $ignored_id ID of the row being updated.
	 * @return bool
	 */
	private function violates_unique_key( $name, array $row, $ignored_id ) {
		foreach ( $this->unique_keys[ $name ] as $columns ) {
			$candidate = array();

			foreach ( $columns as $column ) {
				if ( ! array_key_exists( $column, $row ) || null === $row[ $column ] ) {
					continue 2;
				}

				$candidate[ $column ] = $row[ $column ];
			}

			foreach ( $this->tables[ $name ] as $id => $existing ) {
				if ( (int) $id !== (int) $ignored_id && $this->row_matches( $existing, $candidate ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Folds a value for case-insensitive comparison.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function fold( $value ) {
		return mb_strtolower( (string) $value, 'UTF-8' );
	}

	/**
	 * Returns the table name without the prefix.
	 *
	 * @param string $table Table name.
	 * @return string
	 * @throws \RuntimeException When the table or the statement is not supported.
	 */
	private function short_name( $table ) {
		$name = preg_replace( '/^' . preg_quote( $this->prefix, '/' ) . '/', '', trim( (string) $table, '`' ) );

		if ( ! isset( $this->tables[ $name ] ) ) {
			throw new \RuntimeException( 'Unknown table in the in-memory wpdb: ' . $table );
		}

		return $name;
	}
}

// phpcs:enable
