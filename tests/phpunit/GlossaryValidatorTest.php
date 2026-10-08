<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Glossary validator tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\LinguisticResources\GlossaryValidator;

/**
 * Tests the glossary specific validation rules.
 */
class GlossaryValidatorTest extends TestCase {
	/**
	 * Builds a valid term input with optional overrides.
	 *
	 * @param array<string, mixed> $overrides Overrides.
	 * @return array<string, mixed>
	 */
	private function term( array $overrides = array() ) {
		return array_merge(
			array(
				'term'       => 'Block editor',
				'match_mode' => 'exact',
				'preferred'  => 'Éditeur de blocs',
				'alternates' => array(),
				'note'       => '',
			),
			$overrides
		);
	}

	/**
	 * Accepts and normalizes a glossary identity.
	 *
	 * @return void
	 */
	public function test_identity_is_normalized() {
		$result = ( new GlossaryValidator() )->validate_identity( '  Brand-Terms ', 'en_US', 'fr_FR' );

		$this->assertTrue( $result->is_valid() );
		$this->assertSame(
			array(
				'slug'          => 'brand-terms',
				'source_locale' => 'en_US',
				'target_locale' => 'fr_FR',
			),
			$result->get_values()
		);
	}

	/**
	 * Rejects slugs that are empty, too long or contain unsupported characters.
	 *
	 * @return void
	 */
	public function test_identity_rejects_invalid_slugs() {
		$validator = new GlossaryValidator();

		foreach ( array( '', 'a b', '-leading', 'under/score', str_repeat( 'a', 101 ) ) as $slug ) {
			$this->assertTrue( $validator->validate_identity( $slug, 'en_US', 'fr_FR' )->has_error( 'invalid_slug' ), 'Slug "' . $slug . '" should be rejected.' );
		}

		$this->assertTrue( $validator->validate_identity( str_repeat( 'a', 100 ), 'en_US', 'fr_FR' )->is_valid() );
		$this->assertTrue( $validator->validate_identity( 'wp_core-2', 'en_US', 'fr_FR' )->is_valid() );
	}

	/**
	 * Rejects malformed locales and identical source and target locales.
	 *
	 * @return void
	 */
	public function test_identity_rejects_invalid_and_identical_locales() {
		$validator = new GlossaryValidator();

		$this->assertTrue( $validator->validate_identity( 'terms', 'english', 'fr_FR' )->has_error( 'invalid_source_locale' ) );
		$this->assertTrue( $validator->validate_identity( 'terms', 'en_US', 'fr-FR' )->has_error( 'invalid_target_locale' ) );
		$this->assertTrue( $validator->validate_identity( 'terms', 'en_US', '' )->has_error( 'invalid_target_locale' ) );
		$this->assertTrue( $validator->validate_identity( 'terms', 'fr_FR', 'fr_FR' )->has_error( 'same_locale' ) );
		$this->assertTrue( $validator->validate_identity( 'terms', 'en_US', 'de_DE_formal' )->is_valid() );
		$this->assertTrue( $validator->validate_identity( 'terms', 'en_US', 'ary' )->is_valid() );
	}

	/**
	 * Reports every identity problem at once.
	 *
	 * @return void
	 */
	public function test_identity_reports_all_errors() {
		$result = ( new GlossaryValidator() )->validate_identity( '', 'x', 'y' );

		$this->assertSame( array( 'invalid_slug', 'invalid_source_locale', 'invalid_target_locale' ), array_keys( $result->get_errors() ) );
	}

	/**
	 * Normalizes whitespace in terms and targets.
	 *
	 * @return void
	 */
	public function test_term_whitespace_is_collapsed() {
		$result = ( new GlossaryValidator() )->validate_term(
			$this->term(
				array(
					'term'       => "  Block \n\t editor ",
					'preferred'  => '  Éditeur   de blocs ',
					'alternates' => array( "  Éditeur\tblocs  " ),
				)
			)
		);

		$this->assertTrue( $result->is_valid() );
		$this->assertSame( 'Block editor', $result->get_values()['term'] );
		$this->assertSame( 'Éditeur de blocs', $result->get_values()['preferred'] );
		$this->assertSame( array( 'Éditeur blocs' ), $result->get_values()['alternates'] );
	}

	/**
	 * Requires a term and a preferred target.
	 *
	 * @return void
	 */
	public function test_term_and_preferred_target_are_required() {
		$validator = new GlossaryValidator();
		$result    = $validator->validate_term(
			$this->term(
				array(
					'term'      => '   ',
					'preferred' => '',
				)
			)
		);

		$this->assertSame( array( 'missing_term', 'missing_preferred' ), array_keys( $result->get_errors() ) );
	}

	/**
	 * Limits the length of terms and targets.
	 *
	 * @return void
	 */
	public function test_term_and_targets_have_a_maximum_length() {
		$validator = new GlossaryValidator();

		$this->assertTrue( $validator->validate_term( $this->term( array( 'term' => str_repeat( 'é', 255 ) ) ) )->is_valid(), 'The limit counts characters, not bytes.' );
		$this->assertTrue( $validator->validate_term( $this->term( array( 'term' => str_repeat( 'a', 256 ) ) ) )->has_error( 'term_too_long' ) );
		$this->assertTrue( $validator->validate_term( $this->term( array( 'preferred' => str_repeat( 'a', 256 ) ) ) )->has_error( 'preferred_too_long' ) );
		$this->assertTrue( $validator->validate_term( $this->term( array( 'alternates' => array( str_repeat( 'a', 256 ) ) ) ) )->has_error( 'alternate_too_long' ) );
	}

	/**
	 * Accepts the exact and partial match modes only, defaulting to exact.
	 *
	 * @return void
	 */
	public function test_match_mode_is_restricted() {
		$validator = new GlossaryValidator();
		$input     = $this->term();

		unset( $input['match_mode'] );

		$this->assertSame( 'exact', $validator->validate_term( $input )->get_values()['match_mode'] );
		$this->assertSame( 'partial', $validator->validate_term( $this->term( array( 'match_mode' => 'partial' ) ) )->get_values()['match_mode'] );
		$this->assertTrue( $validator->validate_term( $this->term( array( 'match_mode' => 'fuzzy' ) ) )->has_error( 'invalid_match_mode' ) );
	}

	/**
	 * Cleans up alternates and rejects duplicates and the preferred target.
	 *
	 * @return void
	 */
	public function test_alternates_are_cleaned_and_checked() {
		$validator = new GlossaryValidator();

		$cleaned = $validator->validate_term( $this->term( array( 'alternates' => array( 'Éditeur', '', '  ', 'Bloc' ) ) ) );
		$this->assertSame( array( 'Éditeur', 'Bloc' ), $cleaned->get_values()['alternates'] );

		$duplicated = $validator->validate_term( $this->term( array( 'alternates' => array( 'Bloc', 'bloc' ) ) ) );
		$this->assertTrue( $duplicated->has_error( 'duplicate_alternate' ) );

		$same = $validator->validate_term( $this->term( array( 'alternates' => array( 'ÉDITEUR DE BLOCS' ) ) ) );
		$this->assertTrue( $same->has_error( 'alternate_equals_preferred' ) );

		$this->assertTrue( $validator->validate_term( $this->term( array( 'alternates' => 'not-a-list' ) ) )->has_error( 'invalid_alternates' ) );
	}

	/**
	 * Limits the number of alternates.
	 *
	 * @return void
	 */
	public function test_alternates_are_limited() {
		$validator = new GlossaryValidator();
		$ten       = array_map( 'strval', range( 1, 10 ) );
		$eleven    = array_map( 'strval', range( 1, 11 ) );

		$this->assertTrue( $validator->validate_term( $this->term( array( 'alternates' => $ten ) ) )->is_valid() );
		$this->assertTrue( $validator->validate_term( $this->term( array( 'alternates' => $eleven ) ) )->has_error( 'too_many_alternates' ) );
	}

	/**
	 * Keeps notes readable and bounded.
	 *
	 * @return void
	 */
	public function test_note_is_trimmed_and_bounded() {
		$validator = new GlossaryValidator();
		$result    = $validator->validate_term( $this->term( array( 'note' => "  Use in UI only.\nKeep capitalization.  " ) ) );

		$this->assertSame( "Use in UI only.\nKeep capitalization.", $result->get_values()['note'] );
		$this->assertTrue( $validator->validate_term( $this->term( array( 'note' => str_repeat( 'a', 1001 ) ) ) )->has_error( 'note_too_long' ) );
	}

	/**
	 * Rejects a term that already exists, ignoring case.
	 *
	 * @return void
	 */
	public function test_term_must_be_unique_ignoring_case() {
		$validator = new GlossaryValidator();

		$this->assertTrue( $validator->validate_term( $this->term( array( 'term' => 'BLOCK EDITOR' ) ), array( 'Plugin', 'block editor' ) )->has_error( 'duplicate_term' ) );
		$this->assertTrue( $validator->validate_term( $this->term(), array( 'Plugin' ) )->is_valid() );
		$this->assertTrue( $validator->validate_term( $this->term( array( 'term' => 'Éditeur' ) ), array( 'éditeur' ) )->has_error( 'duplicate_term' ), 'Case folding is multibyte aware.' );
	}

	/**
	 * Exposes translated messages for the errors.
	 *
	 * @return void
	 */
	public function test_errors_carry_messages() {
		$errors = ( new GlossaryValidator() )->validate_term( $this->term( array( 'term' => '' ) ) )->get_errors();

		$this->assertNotSame( '', $errors['missing_term'] );
	}

	/**
	 * Does not return normalized values as valid when something is wrong.
	 *
	 * @return void
	 */
	public function test_invalid_result_is_not_valid() {
		$result = ( new GlossaryValidator() )->validate_term( $this->term( array( 'term' => '' ) ) );

		$this->assertFalse( $result->is_valid() );
		$this->assertFalse( $result->has_error( 'duplicate_term' ) );
	}
}
