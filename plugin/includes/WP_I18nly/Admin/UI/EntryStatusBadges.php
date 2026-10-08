<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Entry status badges.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the quality, obsolete and provenance badges displayed next to the target inputs of an entry.
 */
class EntryStatusBadges {
	/**
	 * Renders one status badge associated to one translation input.
	 *
	 * @param string $input_id Translation input ID.
	 * @param string $status Normalized status token.
	 * @return string
	 */
	public function render_quality_badge( $input_id, $status ) {
		$status_map = array(
			'draft'     => array(
				'class' => 'i18nly-entry-status--draft',
				'label' => __( 'Draft', 'i18nly' ),
			),
			'suspect'   => array(
				'class' => 'i18nly-entry-status--suspect',
				'label' => __( 'Suspect', 'i18nly' ),
			),
			'validated' => array(
				'class' => 'i18nly-entry-status--validated',
				'label' => __( 'Validated', 'i18nly' ),
			),
		);

		$status_token = isset( $status_map[ $status ] ) ? $status : '__empty__';
		$status_meta  = isset( $status_map[ $status ] ) ? $status_map[ $status ] : array(
			'class' => 'i18nly-entry-status--placeholder',
			'label' => '&nbsp;',
		);

		$toggle_html = sprintf(
			'<button type="button" class="i18nly-quality-toggle" aria-haspopup="true" aria-expanded="false"%1$s><span class="i18nly-quality-label">%2$s</span><span class="i18nly-quality-caret" aria-hidden="true">&#9662;</span></button>',
			'__empty__' === $status_token ? ' disabled="disabled"' : '',
			'__empty__' === $status_token ? '&nbsp;' : esc_html( (string) $status_meta['label'] )
		);

		$menu_html = sprintf(
			'<span class="i18nly-quality-menu" role="menu" hidden><button type="button" class="i18nly-quality-option" role="menuitemradio" data-quality-token="suspect">%1$s</button><button type="button" class="i18nly-quality-option" role="menuitemradio" data-quality-token="draft">%2$s</button><button type="button" class="i18nly-quality-option" role="menuitemradio" data-quality-token="validated">%3$s</button></span>',
			esc_html( (string) $status_map['suspect']['label'] ),
			esc_html( (string) $status_map['draft']['label'] ),
			esc_html( (string) $status_map['validated']['label'] )
		);

		return sprintf(
			'<span class="i18nly-entry-status i18nly-entry-status--quality %1$s" data-for="%2$s" data-status-token="%3$s">%4$s%5$s</span>',
			esc_attr( (string) $status_meta['class'] ),
			esc_attr( $input_id ),
			esc_attr( $status_token ),
			$toggle_html,
			$menu_html
		);
	}

	/**
	 * Renders the obsolete badge for source entries marked as obsolete.
	 *
	 * @param bool $is_obsolete Whether the source entry is obsolete.
	 * @return string
	 */
	public function render_obsolete_badge( $is_obsolete ) {
		if ( ! $is_obsolete ) {
			return '';
		}

		return sprintf(
			'<span class="i18nly-entry-status i18nly-entry-status--obsolete" data-status-token="obsolete">%s</span>',
			esc_html__( 'Obsolete', 'i18nly' )
		);
	}

	/**
	 * Renders provenance chips associated to one translation input.
	 *
	 * @param string $input_id Translation input ID.
	 * @param int    $used_ai Whether AI was used.
	 * @param int    $used_manual Whether manual editing was used.
	 * @return string
	 */
	public function render_provenance_badges( $input_id, $used_ai, $used_manual ) {
		$chips = array();

		if ( 1 === (int) $used_ai ) {
			$chips[] = sprintf(
				'<span class="i18nly-entry-status i18nly-entry-status--provenance-ai" data-for="%1$s" data-provenance-token="ai">%2$s</span>',
				esc_attr( $input_id ),
				esc_html__( 'AI', 'i18nly' )
			);
		}

		if ( 1 === (int) $used_manual ) {
			$chips[] = sprintf(
				'<span class="i18nly-entry-status i18nly-entry-status--provenance-manual" data-for="%1$s" data-provenance-token="manual">%2$s</span>',
				esc_attr( $input_id ),
				esc_html__( 'Manual', 'i18nly' )
			);
		}

		return implode( ' ', $chips );
	}

	/**
	 * Normalizes translated quality status values.
	 *
	 * @param string $status Raw status.
	 * @return string
	 */
	public function normalize_status( $status ) {
		$status = (string) $status;

		if ( 'draft_ai' === $status || 'ai_draft_ok' === $status ) {
			return 'draft';
		}

		if ( in_array( $status, array( 'draft', 'suspect', 'validated' ), true ) ) {
			return $status;
		}

		return 'draft';
	}
}
