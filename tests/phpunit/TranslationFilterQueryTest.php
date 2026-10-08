<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation filter query tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests translation edit-screen filter query handling.
 */
class TranslationFilterQueryTest extends TestCase {
	/**
	 * Builds a filter query reading from an in-memory request.
	 *
	 * @param array<string, string> $request Request parameters.
	 * @return \WP_I18nly\Admin\TranslationFilterQuery
	 */
	private function build( array $request ) {
		return new \WP_I18nly\Admin\TranslationFilterQuery(
			function ( $key ) use ( $request ) {
				return isset( $request[ $key ] ) ? $request[ $key ] : '';
			}
		);
	}

	/**
	 * Keeps search text content while trimming it.
	 *
	 * @return void
	 */
	public function test_sanitize_value_keeps_search_text_content() {
		$query = $this->build( array() );

		$this->assertSame( 'Error #42: Missing key?', $query->sanitize_value( '  Error #42: Missing key?  ', 'i18nly_filter_search' ) );
	}

	/**
	 * Normalizes token filters to lowercase tokens separated by commas.
	 *
	 * @return void
	 */
	public function test_sanitize_value_normalizes_token_filters() {
		$query = $this->build( array() );

		$this->assertSame( 'draft,suspect', $query->sanitize_value( ' Draft, Suspect! ,', 'i18nly_filter_statuses' ) );
	}

	/**
	 * Reads values directly from request parameters.
	 *
	 * @return void
	 */
	public function test_get_values_reads_request_parameters() {
		$query = $this->build(
			array(
				'i18nly_filter_statuses' => 'draft',
				'i18nly_filter_search'   => ' hello ',
			)
		);

		$this->assertSame(
			array(
				'i18nly_filter_entries'       => '',
				'i18nly_filter_statuses'      => 'draft',
				'i18nly_filter_provenance'    => '',
				'i18nly_filter_search'        => 'hello',
				'i18nly_filter_search_fields' => '',
			),
			$query->get_values()
		);
	}

	/**
	 * Falls back to the referer query string for missing values only.
	 *
	 * @return void
	 */
	public function test_get_values_falls_back_to_referer_for_missing_values() {
		$query = $this->build(
			array(
				'i18nly_filter_statuses' => 'validated',
				'_wp_http_referer'       => '/wp-admin/post.php?post=5&i18nly_filter_statuses=draft&i18nly_filter_entries=active',
			)
		);

		$values = $query->get_values();

		$this->assertSame( 'validated', $values['i18nly_filter_statuses'] );
		$this->assertSame( 'active', $values['i18nly_filter_entries'] );
	}

	/**
	 * Appends non-empty filters to a redirect location.
	 *
	 * @return void
	 */
	public function test_append_to_location_adds_only_non_empty_filters() {
		$query = $this->build( array( 'i18nly_filter_statuses' => 'draft' ) );

		$location = $query->append_to_location( 'post.php?post=5' );

		$this->assertStringContainsString( 'i18nly_filter_statuses=draft', $location );
		$this->assertStringNotContainsString( 'i18nly_filter_entries', $location );
	}
}
