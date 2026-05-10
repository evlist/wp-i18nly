<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Abstract linguistic resource editor model.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

defined( 'ABSPATH' ) || exit;

/**
 * Base editor model exposing one resource to UI-oriented consumers.
 */
abstract class AbstractLinguisticResourceEditorModel {
	/**
	 * Resource value object.
	 *
	 * @var AbstractLinguisticResource
	 */
	private $resource;

	/**
	 * Constructor.
	 *
	 * @param AbstractLinguisticResource $resource Resource object.
	 */
	public function __construct( AbstractLinguisticResource $resource ) {
		$this->resource = $resource;
	}

	/**
	 * Returns resource object.
	 *
	 * @return AbstractLinguisticResource
	 */
	public function get_resource() {
		return $this->resource;
	}

	/**
	 * Returns UI-ready rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	abstract public function to_rows();
}