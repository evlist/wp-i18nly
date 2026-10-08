<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Glossary linguistic resource target.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

defined( 'ABSPATH' ) || exit;

/**
 * One translation of a glossary term.
 *
 * Glossary terms have no plural forms: the form index of a target row is the rank of the variant,
 * 0 for the preferred translation and 1 and above for the alternates.
 */
class GlossaryResourceTarget extends AbstractLinguisticResourceTarget {
	/**
	 * Returns target kind.
	 *
	 * @return string
	 */
	public function get_target_kind() {
		return 'glossary';
	}

	/**
	 * Returns the rank of the variant: 0 for the preferred translation.
	 *
	 * @return int
	 */
	public function get_rank() {
		return $this->get_form_index();
	}

	/**
	 * Tells whether this is the preferred translation of the term.
	 *
	 * @return bool
	 */
	public function is_preferred() {
		return 0 === $this->get_rank();
	}
}
