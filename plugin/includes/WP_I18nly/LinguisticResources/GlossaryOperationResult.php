<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Glossary operation result.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

defined( 'ABSPATH' ) || exit;

/**
 * Outcome of a glossary write: the ID of the stored resource or entry, or the errors that prevented it.
 */
class GlossaryOperationResult {
	/**
	 * Stored resource or entry ID, 0 on failure.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Error messages indexed by error code.
	 *
	 * @var array<string, string>
	 */
	private $errors;

	/**
	 * Constructor.
	 *
	 * @param int                   $id Stored resource or entry ID.
	 * @param array<string, string> $errors Error messages indexed by error code.
	 */
	public function __construct( $id, array $errors = array() ) {
		$this->id     = array() === $errors ? max( 0, (int) $id ) : 0;
		$this->errors = $errors;
	}

	/**
	 * Tells whether the operation succeeded.
	 *
	 * @return bool
	 */
	public function is_success() {
		return array() === $this->errors;
	}

	/**
	 * Returns the stored resource or entry ID; 0 when the operation failed.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
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
