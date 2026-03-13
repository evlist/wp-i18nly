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
	 * @param string               $locale_key Locale key (normalized lowercase).
	 * @param array<string, mixed> $spec       Normalized locale spec.
	 * @return array<string, mixed>
	 */
	public function apply( string $locale_key, array $spec ): array {
		switch ( $locale_key ) {
			case 'en':
			case 'en_us':
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

				break;

			// Add more languages here as needed.
		}

		return $spec;
	}
}
