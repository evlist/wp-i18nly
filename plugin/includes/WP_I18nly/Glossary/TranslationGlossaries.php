<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Glossaries linked to a translation.
 *
 * @package I18nly
 */

namespace WP_I18nly\Glossary;

use WP_I18nly\LinguisticResources\GlossaryResourceRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Knows which glossaries a translation uses and builds the matcher of their terms.
 *
 * The links are stored in the post meta of the translation (a translation is a post): the list of the
 * glossary IDs. A link to a glossary that no longer exists, or whose target language is not the one of
 * the translation, is ignored.
 *
 * Ordering rule: the glossaries are used in the order of their identifier (slug), and when a term is in
 * several of them the first glossary wins. The order does not depend on the order of the links.
 */
class TranslationGlossaries {
	/**
	 * Post meta key holding the IDs of the linked glossaries.
	 */
	public const META_KEY = '_i18nly_glossary_ids';

	/**
	 * Repository.
	 *
	 * @var GlossaryResourceRepository|null
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param GlossaryResourceRepository|null $repository Optional repository.
	 */
	public function __construct( GlossaryResourceRepository $repository = null ) {
		$this->repository = $repository;
	}

	/**
	 * Returns the repository.
	 *
	 * @return GlossaryResourceRepository
	 */
	private function repository() {
		if ( ! $this->repository instanceof GlossaryResourceRepository ) {
			$this->repository = new GlossaryResourceRepository();
		}

		return $this->repository;
	}

	/**
	 * Returns the IDs of the glossaries linked to a translation.
	 *
	 * @param int $translation_id Translation post ID.
	 * @return int[]
	 */
	public function get_linked_ids( $translation_id ) {
		$stored = get_post_meta( (int) $translation_id, self::META_KEY, true );

		return is_array( $stored ) ? array_values( array_unique( array_filter( array_map( 'absint', $stored ) ) ) ) : array();
	}

	/**
	 * Links glossaries to a translation, keeping only the IDs of glossaries that can be used by it.
	 *
	 * @param int    $translation_id Translation post ID.
	 * @param int[]  $glossary_ids Glossary IDs.
	 * @param string $target_locale Locale of the translation.
	 * @return void
	 */
	public function set_linked_ids( $translation_id, array $glossary_ids, $target_locale ) {
		$usable = array();

		foreach ( $this->list_usable_glossaries( $target_locale ) as $glossary ) {
			$usable[] = (int) $glossary['id'];
		}

		update_post_meta( (int) $translation_id, self::META_KEY, array_values( array_intersect( array_map( 'absint', $glossary_ids ), $usable ) ) );
	}

	/**
	 * Lists the glossaries a translation into a language can use: those whose target language is that language.
	 *
	 * @param string $target_locale Locale of the translation.
	 * @return array<int, array{id: int, slug: string, source_locale: string, target_locale: string}>
	 */
	public function list_usable_glossaries( $target_locale ) {
		return array_values(
			array_filter(
				$this->repository()->list_glossaries(),
				static function ( $glossary ) use ( $target_locale ) {
					return (string) $glossary['target_locale'] === (string) $target_locale;
				}
			)
		);
	}

	/**
	 * Builds the matcher of the terms of the glossaries linked to a translation.
	 *
	 * @param int    $translation_id Translation post ID.
	 * @param string $target_locale Locale of the translation.
	 * @return GlossaryMatcher
	 */
	public function build_matcher( $translation_id, $target_locale ) {
		$linked = $this->get_linked_ids( $translation_id );
		$terms  = array();

		if ( empty( $linked ) ) {
			return new GlossaryMatcher( $terms );
		}

		// list_glossaries() is ordered by slug, which is the order of precedence.
		foreach ( $this->list_usable_glossaries( $target_locale ) as $row ) {
			if ( ! in_array( (int) $row['id'], $linked, true ) ) {
				continue;
			}

			$glossary = $this->repository()->get_glossary( (int) $row['id'] );

			if ( null === $glossary ) {
				continue;
			}

			foreach ( $glossary->get_entries() as $entry ) {
				$preferred = $entry->get_preferred_target();

				if ( null === $preferred ) {
					continue;
				}

				$terms[] = array(
					'glossary'   => $glossary->get_slug(),
					'term'       => $entry->get_term(),
					'mode'       => $entry->get_match_mode(),
					'preferred'  => $preferred->get_text(),
					'alternates' => array_map(
						static function ( $target ) {
							return $target->get_text();
						},
						$entry->get_alternate_targets()
					),
					'note'       => $entry->get_note(),
				);
			}
		}

		return new GlossaryMatcher( $terms );
	}
}
