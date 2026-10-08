<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Glossary linguistic resource entry.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

defined( 'ABSPATH' ) || exit;

/**
 * One term of a glossary with its preferred and alternate translations.
 */
class GlossaryResourceEntry extends AbstractLinguisticResourceEntry {
	/**
	 * Returns entry kind.
	 *
	 * @return string
	 */
	public function get_entry_kind() {
		return 'glossary';
	}

	/**
	 * Returns the source term.
	 *
	 * @return string
	 */
	public function get_term() {
		return (string) $this->get_row_value( 'term', '' );
	}

	/**
	 * Returns the match mode: exact or partial.
	 *
	 * @return string
	 */
	public function get_match_mode() {
		return (string) $this->get_row_value( 'match_mode', 'exact' );
	}

	/**
	 * Returns the note attached to the term.
	 *
	 * @return string
	 */
	public function get_note() {
		return (string) $this->get_row_value( 'note', '' );
	}

	/**
	 * Returns the lifecycle status of the term.
	 *
	 * @return string
	 */
	public function get_status() {
		return (string) $this->get_row_value( 'status', 'active' );
	}

	/**
	 * Returns the preferred translation, if the term has one.
	 *
	 * @return GlossaryResourceTarget|null
	 */
	public function get_preferred_target() {
		foreach ( $this->get_ordered_targets() as $target ) {
			if ( $target->is_preferred() ) {
				return $target;
			}
		}

		return null;
	}

	/**
	 * Returns the alternate translations, in rank order.
	 *
	 * @return array<int, GlossaryResourceTarget>
	 */
	public function get_alternate_targets() {
		return array_values(
			array_filter(
				$this->get_ordered_targets(),
				static function ( GlossaryResourceTarget $target ) {
					return ! $target->is_preferred();
				}
			)
		);
	}

	/**
	 * Returns every translation of the term, preferred first, in rank order.
	 *
	 * @return array<int, GlossaryResourceTarget>
	 */
	public function get_ordered_targets() {
		$targets = array();

		foreach ( $this->get_targets() as $target ) {
			if ( $target instanceof GlossaryResourceTarget ) {
				$targets[] = $target;
			}
		}

		usort(
			$targets,
			static function ( GlossaryResourceTarget $left, GlossaryResourceTarget $right ) {
				return $left->get_rank() <=> $right->get_rank();
			}
		);

		return $targets;
	}

	/**
	 * Creates one glossary target from a raw target row.
	 *
	 * @param array<string, mixed> $row Raw target row.
	 * @return GlossaryResourceTarget
	 */
	protected function create_target( array $row ) {
		return new GlossaryResourceTarget( $row );
	}
}
