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
		switch ( $language ) {
			case 'en':
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
