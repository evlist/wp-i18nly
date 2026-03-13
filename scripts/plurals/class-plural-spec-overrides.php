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
 * Applies project-specific transformations to one language spec.
 */
interface PluralSpecOverrides {
	/**
	 * Applies project-specific transformations.
	 *
	 * @param string               $locale Canonical locale (for example: en_US, fr_FR, ja).
	 * @param array<string, mixed> $spec   Normalized locale spec.
	 * @return array<string, mixed>
	 */
	public function apply( string $locale, array $spec ): array;
}
