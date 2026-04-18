<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * DeepL monthly usage gauge renderer.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a reusable DeepL monthly usage gauge box.
 */
class DeepLUsageGaugeRenderer {
	/**
	 * Whether CSS has already been printed in current request.
	 *
	 * @var bool
	 */
	private static $styles_printed = false;

	/**
	 * Renders the usage gauge.
	 *
	 * @param array<string, mixed> $status Usage status payload.
	 * @param string               $title Box title.
	 * @return void
	 */
	public function render( array $status, $title ) {
		$this->print_styles_once();

		$used       = isset( $status['used_characters'] ) ? max( 0, (int) $status['used_characters'] ) : 0;
		$limit      = isset( $status['character_limit'] ) ? max( 0, (int) $status['character_limit'] ) : 0;
		$percent    = isset( $status['percent_used'] ) ? max( 0, min( 100, (int) $status['percent_used'] ) ) : 0;
		$state      = isset( $status['state'] ) ? (string) $status['state'] : 'unavailable';
		$is_stale   = ! empty( $status['is_stale'] );
		$message    = isset( $status['message'] ) ? (string) $status['message'] : '';
		$fetched_at = isset( $status['fetched_at'] ) ? (int) $status['fetched_at'] : 0;
		$state      = in_array( $state, array( 'ok', 'warning', 'critical', 'unavailable' ), true ) ? $state : 'unavailable';

		echo '<div class="i18nly-deepl-usage-box i18nly-deepl-usage-box--' . esc_attr( $state ) . '">';
		echo '<h3 class="i18nly-deepl-usage-title">' . esc_html( (string) $title ) . '</h3>';

		if ( $limit > 0 ) {
			echo '<div class="i18nly-deepl-usage-values">';
			echo '<strong>' . esc_html( $this->format_number( $used ) ) . '</strong>';
			echo ' / ';
			echo '<span>' . esc_html( $this->format_number( $limit ) ) . '</span>';
			echo '<span class="i18nly-deepl-usage-unit">';
			echo esc_html__( 'characters this month', 'i18nly' );
			echo '</span>';
			echo '</div>';

			echo '<div class="i18nly-deepl-usage-progress" role="progressbar" aria-label="' . esc_attr__( 'DeepL monthly usage', 'i18nly' ) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( (string) $percent ) . '">';
			echo '<span class="i18nly-deepl-usage-progress-fill" style="width:' . esc_attr( (string) $percent ) . '%"></span>';
			echo '</div>';

			echo '<p class="i18nly-deepl-usage-percent">' . esc_html( (string) $percent ) . '%</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'Usage limit is unavailable for this key.', 'i18nly' ) . '</p>';
		}

		if ( '' !== trim( $message ) ) {
			echo '<p class="description">' . esc_html( $message ) . '</p>';
		}

		if ( $fetched_at > 0 ) {
			$fetched_label = function_exists( 'wp_date' )
				? wp_date( 'Y-m-d H:i', $fetched_at )
				: gmdate( 'Y-m-d H:i', $fetched_at );

			/* translators: %s: date and time of last refresh. */
			$refresh_label = sprintf( __( 'Last refresh: %s', 'i18nly' ), $fetched_label );
			if ( $is_stale ) {
				$refresh_label .= ' - ' . __( 'stale values', 'i18nly' );
			}
			echo '<p class="description i18nly-deepl-usage-fetched-at">' . esc_html( $refresh_label ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Prints styles once per request.
	 *
	 * @return void
	 */
	private function print_styles_once() {
		if ( self::$styles_printed ) {
			return;
		}

		self::$styles_printed = true;

		echo '<style>';
		echo '.i18nly-deepl-usage-box{border:1px solid #dcdcde;border-radius:4px;padding:12px;background:#fff;margin-top:8px}';
		echo '.i18nly-deepl-usage-title{margin:0 0 8px 0;font-size:14px;line-height:1.4}';
		echo '.i18nly-deepl-usage-values{margin-bottom:8px;font-size:13px}';
		echo '.i18nly-deepl-usage-unit{margin-left:4px;color:#50575e}';
		echo '.i18nly-deepl-usage-progress{position:relative;height:10px;border-radius:999px;background:#f0f0f1;overflow:hidden}';
		echo '.i18nly-deepl-usage-progress-fill{display:block;height:100%;background:#2271b1}';
		echo '.i18nly-deepl-usage-box--warning .i18nly-deepl-usage-progress-fill{background:#dba617}';
		echo '.i18nly-deepl-usage-box--critical .i18nly-deepl-usage-progress-fill{background:#d63638}';
		echo '.i18nly-deepl-usage-box--unavailable .i18nly-deepl-usage-progress-fill{background:#8c8f94}';
		echo '.i18nly-deepl-usage-percent{margin:8px 0 0 0;font-size:12px;color:#50575e}';
		echo '.i18nly-deepl-usage-fetched-at{margin-top:8px}';
		echo '</style>';
	}

	/**
	 * Formats numbers with WordPress i18n when available.
	 *
	 * @param int $value Number to format.
	 * @return string
	 */
	private function format_number( $value ) {
		if ( function_exists( 'number_format_i18n' ) ) {
			return (string) number_format_i18n( (int) $value );
		}

		return number_format( (int) $value );
	}
}
