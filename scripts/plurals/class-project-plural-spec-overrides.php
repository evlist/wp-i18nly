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
	 * @param string               $locale Canonical locale (for example: en_US).
	 * @param array<string, mixed> $spec   Normalized locale spec.
	 * @return array<string, mixed>
	 */
	public function apply( string $locale, array $spec ): array {
		if ( $this->does_nplurals_equal( $spec, 1 ) ) {
			$spec['forms'] = array(
				array(
					'label'   => '*',
					'tooltip' => 'Any number',
				),
			);
		} elseif (
			$this->does_nplurals_equal( $spec, 2 )
			&& $this->does_plural_expression_equal( $spec, '(n != 1)' )
		) {
			$spec['forms'] = array(
				array(
					'label'   => '1',
					'tooltip' => 'One',
				),
				array(
					'label'   => 'n',
					'tooltip' => 'Other than one',
				),
			);
		}

		return $spec;
	}

	/**
	 * Checks whether spec nplurals equals expected value.
	 *
	 * @param array<string, mixed> $spec Locale spec.
	 * @param int                  $expected Expected nplurals value.
	 * @return bool
	 */
	private function does_nplurals_equal( array $spec, int $expected ): bool {
		$nplurals = isset( $spec['nplurals'] ) ? (int) $spec['nplurals'] : 0;

		return $expected === $nplurals;
	}

	/**
	 * Checks whether spec plural expression equals expected value.
	 *
	 * @param array<string, mixed> $spec Locale spec.
	 * @param string               $expected Expected plural expression.
	 * @return bool
	 */
	private function does_plural_expression_equal( array $spec, string $expected ): bool {
		$expression = isset( $spec['plural_expression'] ) ? (string) $spec['plural_expression'] : '';

		return $expected === $expression;
	}
}
