<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Glossary validation result.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

defined( 'ABSPATH' ) || exit;

/**
 * Normalized values and errors produced by the glossary validation.
 */
class GlossaryValidationResult {
	/**
	 * Normalized values.
	 *
	 * @var array<string, mixed>
	 */
	private $values;

	/**
	 * Error messages indexed by error code.
	 *
	 * @var array<string, string>
	 */
	private $errors;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed>  $values Normalized values.
	 * @param array<string, string> $errors Error messages indexed by error code.
	 */
	public function __construct( array $values, array $errors ) {
		$this->values = $values;
		$this->errors = $errors;
	}

	/**
	 * Tells whether the validated input has no error.
	 *
	 * @return bool
	 */
	public function is_valid() {
		return array() === $this->errors;
	}

	/**
	 * Returns the normalized values; only meaningful for a valid result.
	 *
	 * @return array<string, mixed>
	 */
	public function get_values() {
		return $this->values;
	}

	/**
	 * Returns the error messages indexed by error code.
	 *
	 * @return array<string, string>
	 */
	public function get_errors() {
		return $this->errors;
	}

	/**
	 * Tells whether one error code was reported.
	 *
	 * @param string $code Error code.
	 * @return bool
	 */
	public function has_error( $code ) {
		return isset( $this->errors[ (string) $code ] );
	}
}
