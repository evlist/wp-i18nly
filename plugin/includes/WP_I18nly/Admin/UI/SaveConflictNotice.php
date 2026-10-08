<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Notice shown when a save skipped translations changed by someone else.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Remembers, for the user who saved, how many translations were not saved because someone else changed
 * them after the editor was loaded, and shows it once on the next screen.
 */
class SaveConflictNotice {
	/**
	 * Remembers the number of skipped translations.
	 *
	 * @param int $post_id Translation post ID.
	 * @param int $count Number of skipped forms.
	 * @return void
	 */
	public static function remember( $post_id, $count ) {
		set_transient( self::key( $post_id ), (int) $count, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Registers the hook.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Shows the notice once, on the edit screen of the translation.
	 *
	 * @return void
	 */
	public function render() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! is_object( $screen ) || 'i18nly_translation' !== $screen->post_type || ! isset( $_GET['post'] ) ) {
			return;
		}

		$post_id = absint( $_GET['post'] );
		$count   = (int) get_transient( self::key( $post_id ) );

		if ( $count <= 0 ) {
			return;
		}

		delete_transient( self::key( $post_id ) );

		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html(
			sprintf(
				/* translators: %d: number of translations. */
				_n(
					'%d translation was not saved because someone else changed it after you opened this screen. Their version was kept; the screen shows the current texts.',
					'%d translations were not saved because someone else changed them after you opened this screen. Their versions were kept; the screen shows the current texts.',
					$count,
					'i18nly'
				),
				$count
			)
		) . '</p></div>';
	}

	/**
	 * Returns the transient key.
	 *
	 * @param int $post_id Translation post ID.
	 * @return string
	 */
	private static function key( $post_id ) {
		return 'i18nly_save_conflicts_' . (int) $post_id . '_' . get_current_user_id();
	}
}
