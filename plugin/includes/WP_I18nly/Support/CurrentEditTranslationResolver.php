<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Current edit translation resolver.
 *
 * @package I18nly
 */

namespace WP_I18nly\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the current translation ID from edit-screen query parameters.
 */
class CurrentEditTranslationResolver {
	/**
	 * Returns translation ID from current edit request.
	 *
	 * @param callable $query_reader Callback reading one GET parameter.
	 * @param callable $translation_reader Callback returning translation row.
	 * @return int
	 */
	public function resolve( callable $query_reader, callable $translation_reader ) {
		$action_raw = (string) call_user_func( $query_reader, 'action' );
		$post_raw   = (string) call_user_func( $query_reader, 'post' );

		if ( '' === $action_raw || '' === $post_raw ) {
			return 0;
		}

		$action = sanitize_text_field( wp_unslash( $action_raw ) );
		if ( 'edit' !== $action ) {
			return 0;
		}

		$translation_id = absint( wp_unslash( $post_raw ) );
		if ( $translation_id <= 0 ) {
			return 0;
		}

		$translation = call_user_func( $translation_reader, $translation_id );

		return null === $translation ? 0 : $translation_id;
	}
}
