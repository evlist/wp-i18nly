<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Abstract linguistic resource model.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

defined( 'ABSPATH' ) || exit;

/**
 * Base value object for one linguistic resource.
 */
abstract class AbstractLinguisticResource {
	/**
	 * Resource ID.
	 *
	 * @var int
	 */
	private $resource_id;

	/**
	 * Source locale.
	 *
	 * @var string
	 */
	private $source_locale;

	/**
	 * Target locale.
	 *
	 * @var string
	 */
	private $target_locale;

	/**
	 * Ordered resource entries.
	 *
	 * @var array<int, AbstractLinguisticResourceEntry>
	 */
	private $entries = array();

	/**
	 * Constructor.
	 *
	 * @param int                                    $resource_id Resource ID.
	 * @param string                                 $source_locale Source locale.
	 * @param string                                 $target_locale Target locale.
	 * @param array<int, AbstractLinguisticResourceEntry> $entries Resource entries.
	 */
	public function __construct( $resource_id, $source_locale, $target_locale, array $entries = array() ) {
		$this->resource_id   = max( 0, (int) $resource_id );
		$this->source_locale = (string) $source_locale;
		$this->target_locale = (string) $target_locale;
		$this->entries       = $entries;
	}

	/**
	 * Returns resource ID.
	 *
	 * @return int
	 */
	public function get_resource_id() {
		return $this->resource_id;
	}

	/**
	 * Returns resource kind.
	 *
	 * @return string
	 */
	abstract public function get_resource_kind();

	/**
	 * Returns source locale.
	 *
	 * @return string
	 */
	public function get_source_locale() {
		return $this->source_locale;
	}

	/**
	 * Returns target locale.
	 *
	 * @return string
	 */
	public function get_target_locale() {
		return $this->target_locale;
	}

	/**
	 * Returns ordered entries.
	 *
	 * @return array<int, AbstractLinguisticResourceEntry>
	 */
	public function get_entries() {
		return $this->entries;
	}
}