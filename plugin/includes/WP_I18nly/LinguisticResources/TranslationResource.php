<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation linguistic resource.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

defined( 'ABSPATH' ) || exit;

/**
 * Concrete linguistic resource for one translation.
 */
class TranslationResource extends AbstractLinguisticResource {
	/**
	 * Source slug.
	 *
	 * @var string
	 */
	private $source_slug;

	/**
	 * Translation post ID anchoring this resource.
	 *
	 * @var int
	 */
	private $translation_id;

	/**
	 * Constructor.
	 *
	 * @param int                                  $resource_id Storage resource ID.
	 * @param int                                  $translation_id Translation post ID.
	 * @param string                               $source_slug Source slug.
	 * @param string                               $source_locale Source locale.
	 * @param string                               $target_locale Target locale.
	 * @param array<int, TranslationResourceEntry> $entries Translation entries.
	 */
	public function __construct( $resource_id, $translation_id, $source_slug, $source_locale, $target_locale, array $entries = array() ) {
		parent::__construct( $resource_id, $source_locale, $target_locale, $entries );
		$this->translation_id = max( 0, (int) $translation_id );
		$this->source_slug    = (string) $source_slug;
	}

	/**
	 * Returns the translation post ID anchoring this resource.
	 *
	 * @return int
	 */
	public function get_translation_id() {
		return $this->translation_id;
	}

	/**
	 * Returns resource kind.
	 *
	 * @return string
	 */
	public function get_resource_kind() {
		return 'translation';
	}

	/**
	 * Returns source slug.
	 *
	 * @return string
	 */
	public function get_source_slug() {
		return $this->source_slug;
	}
}
