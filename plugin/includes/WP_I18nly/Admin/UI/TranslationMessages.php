<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation post update messages handler.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Provides customized post update messages for translation posts.
 */
class TranslationMessages {
	/**
	 * Replaces default post update messages for translation posts.
	 *
	 * @param array<string, array<int, string>> $messages Current messages.
	 * @param string                            $post_type Translation post type.
	 * @return array<string, array<int, string>>
	 */
	public function filter_post_updated_messages( array $messages, $post_type ) {
		$messages[ $post_type ] = array(
			0  => '',
			1  => __( 'Translation updated.', 'i18nly' ),
			2  => __( 'Custom field updated.', 'i18nly' ),
			3  => __( 'Custom field deleted.', 'i18nly' ),
			4  => __( 'Translation updated.', 'i18nly' ),
			5  => __( 'Translation restored to revision.', 'i18nly' ),
			6  => __( 'Translation published.', 'i18nly' ),
			7  => __( 'Translation saved.', 'i18nly' ),
			8  => __( 'Translation submitted.', 'i18nly' ),
			9  => __( 'Translation scheduled.', 'i18nly' ),
			10 => __( 'Translation draft updated.', 'i18nly' ),
		);

		return $messages;
	}

	/**
	 * Replaces default bulk update messages for translation posts.
	 *
	 * @param array<string, array<string, string>> $bulk_messages Current bulk messages.
	 * @param array<string, int>                   $bulk_counts Item counts.
	 * @param string                               $post_type Translation post type.
	 * @return array<string, array<string, string>>
	 */
	public function filter_bulk_updated_messages( array $bulk_messages, array $bulk_counts, $post_type ) {
		$updated_count   = isset( $bulk_counts['updated'] ) ? (int) $bulk_counts['updated'] : 0;
		$locked_count    = isset( $bulk_counts['locked'] ) ? (int) $bulk_counts['locked'] : 0;
		$deleted_count   = isset( $bulk_counts['deleted'] ) ? (int) $bulk_counts['deleted'] : 0;
		$trashed_count   = isset( $bulk_counts['trashed'] ) ? (int) $bulk_counts['trashed'] : 0;
		$untrashed_count = isset( $bulk_counts['untrashed'] ) ? (int) $bulk_counts['untrashed'] : 0;

		$bulk_messages[ $post_type ] = array(
			/* translators: %s is the number of translations. */
			'updated'   => sprintf( _n( '%s translation updated.', '%s translations updated.', $updated_count, 'i18nly' ), number_format_i18n( $updated_count ) ),
			/* translators: %s is the number of translations. */
			'locked'    => sprintf( _n( '%s translation not updated, somebody is editing it.', '%s translations not updated, somebody is editing them.', $locked_count, 'i18nly' ), number_format_i18n( $locked_count ) ),
			/* translators: %s is the number of translations. */
			'deleted'   => sprintf( _n( '%s translation permanently deleted.', '%s translations permanently deleted.', $deleted_count, 'i18nly' ), number_format_i18n( $deleted_count ) ),
			/* translators: %s is the number of translations. */
			'trashed'   => sprintf( _n( '%s translation moved to the Trash.', '%s translations moved to the Trash.', $trashed_count, 'i18nly' ), number_format_i18n( $trashed_count ) ),
			/* translators: %s is the number of translations. */
			'untrashed' => sprintf( _n( '%s translation restored from the Trash.', '%s translations restored from the Trash.', $untrashed_count, 'i18nly' ), number_format_i18n( $untrashed_count ) ),
		);

		return $bulk_messages;
	}
}
