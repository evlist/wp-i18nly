<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation linguistic resource target.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

defined( 'ABSPATH' ) || exit;

/**
 * Concrete linguistic resource target for translations.
 */
class TranslationResourceTarget extends AbstractLinguisticResourceTarget {
	/**
	 * Returns target kind.
	 *
	 * @return string
	 */
	public function get_target_kind() {
		return 'translation';
	}

	/**
	 * Returns the quality status.
	 *
	 * @return string
	 */
	public function get_status() {
		return (string) $this->get_row_value( 'status', 'draft' );
	}

	/**
	 * Tells whether an AI provider contributed to this value.
	 *
	 * @return bool
	 */
	public function is_ai_generated() {
		return 1 === (int) $this->get_row_value( 'used_ai', 0 );
	}

	/**
	 * Tells whether a person contributed to this value.
	 *
	 * @return bool
	 */
	public function is_manual() {
		return 1 === (int) $this->get_row_value( 'used_manual', 1 );
	}
}
