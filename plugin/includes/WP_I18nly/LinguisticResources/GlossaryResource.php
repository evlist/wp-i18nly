<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Glossary linguistic resource.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

defined( 'ABSPATH' ) || exit;

/**
 * A glossary: user-authored terms with their preferred translations for one language pair.
 */
class GlossaryResource extends AbstractLinguisticResource {
	/**
	 * Glossary slug.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Constructor.
	 *
	 * @param int                               $resource_id Storage resource ID.
	 * @param string                            $slug Glossary slug.
	 * @param string                            $source_locale Source locale.
	 * @param string                            $target_locale Target locale.
	 * @param array<int, GlossaryResourceEntry> $entries Glossary terms.
	 */
	public function __construct( $resource_id, $slug, $source_locale, $target_locale, array $entries = array() ) {
		parent::__construct( $resource_id, $source_locale, $target_locale, $entries );
		$this->slug = (string) $slug;
	}

	/**
	 * Returns resource kind.
	 *
	 * @return string
	 */
	public function get_resource_kind() {
		return 'glossary';
	}

	/**
	 * Returns the glossary slug.
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->slug;
	}
}
