<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

declare(strict_types=1);

namespace I18nly\Scripts\Plurals;

/**
 * Default project overrides.
 *
 * This scaffold keeps behavior conservative and deterministic.
 * Add project rules here in next slices.
 */
final class ProjectPluralSpecOverrides implements PluralSpecOverrides {
	/**
	 * Applies default project overrides.
	 *
	 * @param string               $language Language code (normalized lowercase).
	 * @param array<string, mixed> $spec     Normalized language spec.
	 * @return array<string, mixed>
	 */
	public function apply( string $language, array $spec ): array {
		unset( $language );

		$categories = isset( $spec['categories'] ) && is_array( $spec['categories'] )
			? array_values( array_map( 'strval', $spec['categories'] ) )
			: array();

		if ( ! isset( $spec['forms'] ) || ! is_array( $spec['forms'] ) ) {
			$spec['forms'] = $this->build_default_forms( $categories );
		}

		return $spec;
	}

	/**
	 * Builds default forms from category labels.
	 *
	 * @param array<int, string> $categories Categories.
	 * @return array<int, array{marker: string, label: string, tooltip: string}>
	 */
	private function build_default_forms( array $categories ): array {
		$forms = array();

		foreach ( $categories as $index => $category ) {
			$forms[] = array(
				'marker'  => $this->marker_from_index( (int) $index ),
				'label'   => (string) $category,
				'tooltip' => ucfirst( (string) $category ),
			);
		}

		return $forms;
	}

	/**
	 * Returns alphabetical marker for one index.
	 *
	 * @param int $index Marker index.
	 * @return string
	 */
	private function marker_from_index( int $index ): string {
		$index  = max( 0, $index );
		$marker = '';

		do {
			$marker = chr( 97 + ( $index % 26 ) ) . $marker;
			$index  = (int) floor( $index / 26 ) - 1;
		} while ( $index >= 0 );

		return $marker;
	}
}
