<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation list table columns handler.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Manages translation list table columns, sorting, and row actions.
 */
class TranslationListColumns {
	/**
	 * Filters translation list table columns.
	 *
	 * @param array<string, string> $columns Current columns.
	 * @return array<string, string>
	 */
	public function filter_columns( array $columns ) {
		$filtered_columns = array();

		foreach ( $columns as $column_key => $column_label ) {
			$filtered_columns[ $column_key ] = $column_label;

			if ( 'title' !== $column_key ) {
				continue;
			}

			$filtered_columns['source_slug']     = __( 'Source', 'i18nly' );
			$filtered_columns['target_language'] = __( 'Target language', 'i18nly' );
		}

		if ( ! isset( $filtered_columns['source_slug'] ) ) {
			$filtered_columns['source_slug'] = __( 'Source', 'i18nly' );
		}

		if ( ! isset( $filtered_columns['target_language'] ) ) {
			$filtered_columns['target_language'] = __( 'Target language', 'i18nly' );
		}

		return $filtered_columns;
	}

	/**
	 * Returns custom column value for translation rows.
	 *
	 * @param string $column_name Column key.
	 * @param int    $post_id Translation post ID.
	 * @param string $meta_source_key Source slug meta key.
	 * @param string $meta_target_key Target language meta key.
	 * @return string
	 */
	public function get_column_value( $column_name, $post_id, $meta_source_key, $meta_target_key ) {
		if ( 'source_slug' === $column_name ) {
			return (string) get_post_meta( (int) $post_id, $meta_source_key, true );
		}

		if ( 'target_language' === $column_name ) {
			return (string) get_post_meta( (int) $post_id, $meta_target_key, true );
		}

		return '';
	}

	/**
	 * Filters sortable columns for translation list table.
	 *
	 * @param array<string, string> $columns Current sortable columns.
	 * @return array<string, string>
	 */
	public function filter_sortable_columns( array $columns ) {
		$columns['source_slug']     = 'source_slug';
		$columns['target_language'] = 'target_language';

		return $columns;
	}

	/**
	 * Applies meta sorting for translation custom columns.
	 *
	 * @param object $query Current query object.
	 * @param string $post_type Translation post type.
	 * @param string $meta_source_key Source slug meta key.
	 * @param string $meta_target_key Target language meta key.
	 * @return void
	 */
	public function apply_sorting( $query, $post_type, $meta_source_key, $meta_target_key ) {
		if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) || ! method_exists( $query, 'set' ) ) {
			return;
		}

		if ( method_exists( $query, 'is_main_query' ) && ! $query->is_main_query() ) {
			return;
		}

		$queried_post_type = (string) $query->get( 'post_type' );
		if ( $post_type !== $queried_post_type ) {
			return;
		}

		$orderby = (string) $query->get( 'orderby' );

		if ( 'source_slug' === $orderby ) {
			$query->set( 'meta_key', $meta_source_key );
			$query->set( 'orderby', 'meta_value' );
		}

		if ( 'target_language' === $orderby ) {
			$query->set( 'meta_key', $meta_target_key );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	/**
	 * Filters row actions to remove quick edit.
	 *
	 * @param array<string, string> $actions Actions.
	 * @param object                $post Post object.
	 * @param string                $post_type Translation post type.
	 * @return array<string, string>
	 */
	public function filter_row_actions( array $actions, $post, $post_type ) {
		if ( ! isset( $post->post_type ) || $post_type !== (string) $post->post_type ) {
			return $actions;
		}

		unset( $actions['inline hide-if-no-js'] );

		return $actions;
	}
}
