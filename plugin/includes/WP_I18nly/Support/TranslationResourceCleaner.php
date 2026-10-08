<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation resource cleaner.
 *
 * @package I18nly
 */

namespace WP_I18nly\Support;

use WP_I18nly\LinguisticResources\TranslationResourceRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Removes stored translation resources when their anchor post is permanently deleted.
 */
class TranslationResourceCleaner {
	/**
	 * Translation post type.
	 */
	private const POST_TYPE = 'i18nly_translation';

	/**
	 * Translation resource repository.
	 *
	 * @var TranslationResourceRepository|null
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param TranslationResourceRepository|null $repository Optional repository.
	 */
	public function __construct( TranslationResourceRepository $repository = null ) {
		$this->repository = $repository;
	}

	/**
	 * Deletes the stored resource of one translation post about to be deleted.
	 *
	 * @param int         $post_id Post ID.
	 * @param object|null $post Post object.
	 * @return void
	 */
	public function handle_before_delete_post( $post_id, $post = null ) {
		if ( ! is_object( $post ) || ! isset( $post->post_type ) || self::POST_TYPE !== (string) $post->post_type ) {
			return;
		}

		$repository = $this->repository instanceof TranslationResourceRepository
			? $this->repository
			: new TranslationResourceRepository();

		$repository->delete_translation_resource( (int) $post_id );
	}
}
