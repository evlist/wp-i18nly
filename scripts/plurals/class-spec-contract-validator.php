<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

declare(strict_types=1);

namespace I18nly\Scripts\Plurals;

use InvalidArgumentException;

/**
 * Validates plural spec contract before generation.
 */
final class SpecContractValidator {
	/**
	 * Validates one language spec contract.
	 *
	 * @param string               $language
	 * @param array<string, mixed> $spec
	 */
	public function validate_language_spec( string $language, array $spec ): void {
		if ( '' === $language ) {
			throw new InvalidArgumentException('Language code must not be empty.');
		}

		if ( ! isset( $spec['nplurals'] ) || ! is_int( $spec['nplurals'] ) || $spec['nplurals'] < 1 ) {
			throw new InvalidArgumentException( sprintf( 'Language %s: nplurals must be an int >= 1.', $language ) );
		}

		if ( ! isset( $spec['categories'] ) || ! is_array( $spec['categories'] ) ) {
			throw new InvalidArgumentException( sprintf( 'Language %s: categories must be an array.', $language ) );
		}

		foreach ( $spec['categories'] as $index => $category ) {
			if ( ! is_string( $category ) || '' === trim( $category ) ) {
				throw new InvalidArgumentException(
					sprintf( 'Language %s: categories[%d] must be a non-empty string.', $language, (int) $index )
				);
			}
		}

		if ( ! isset( $spec['plural_expression'] ) || ! is_string( $spec['plural_expression'] ) || '' === trim( $spec['plural_expression'] ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Language %s: plural_expression must be a non-empty string.', $language )
			);
		}

		if ( isset( $spec['forms'] ) && ! is_array( $spec['forms'] ) ) {
			throw new InvalidArgumentException( sprintf( 'Language %s: forms must be an array when provided.', $language ) );
		}
	}
}
