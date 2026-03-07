<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Temporary storage service for unpublished translation artifacts.
 *
 * @package I18nly
 */

namespace WP_I18nly;

defined( 'ABSPATH' ) || exit;

/**
 * Manages temporary filesystem storage for draft/unpublished translations.
 */
class TemporaryStorage {
	/**
	 * Base directory for temporary artifacts.
	 *
	 * @var string
	 */
	private $base_directory;

	/**
	 * Constructor.
	 *
	 * @param string $base_directory Optional custom base directory for storage.
	 */
	public function __construct( $base_directory = '' ) {
		if ( '' !== (string) $base_directory ) {
			$this->base_directory = rtrim( (string) $base_directory, '/\\' );
			return;
		}

		$this->base_directory = $this->resolve_default_base_directory();
	}

	/**
	 * Returns the workspace directory for one translation post.
	 *
	 * @param int $translation_post_id Translation post ID.
	 * @return string
	 */
	public function get_translation_directory( $translation_post_id ) {
		return $this->base_directory . '/translation-' . (int) $translation_post_id;
	}

	/**
	 * Returns the generated POT path for one translation post.
	 *
	 * @param int $translation_post_id Translation post ID.
	 * @return string
	 */
	public function get_pot_file_path( $translation_post_id ) {
		return $this->get_translation_directory( $translation_post_id ) . '/messages.pot';
	}

	/**
	 * Ensures base and translation directories exist.
	 *
	 * @param int $translation_post_id Translation post ID.
	 * @return string Translation workspace directory.
	 * @throws \RuntimeException When directory creation fails.
	 */
	public function ensure_translation_workspace( $translation_post_id ) {
		$this->ensure_directory( $this->base_directory );

		$translation_directory = $this->get_translation_directory( $translation_post_id );
		$this->ensure_directory( $translation_directory );

		return $translation_directory;
	}

	/**
	 * Removes one translation workspace recursively.
	 *
	 * @param int $translation_post_id Translation post ID.
	 * @return void
	 */
	public function cleanup_translation_workspace( $translation_post_id ) {
		$directory = $this->get_translation_directory( $translation_post_id );

		if ( ! is_dir( $directory ) ) {
			return;
		}

		$this->delete_directory_recursively( $directory );
	}

	/**
	 * Resolves default base directory under WordPress uploads.
	 *
	 * @return string
	 */
	private function resolve_default_base_directory() {
		if ( function_exists( 'wp_upload_dir' ) ) {
			$uploads = wp_upload_dir();

			if ( is_array( $uploads ) && ! empty( $uploads['basedir'] ) ) {
				return rtrim( (string) $uploads['basedir'], '/\\' ) . '/i18nly/staging';
			}
		}

		return rtrim( (string) sys_get_temp_dir(), '/\\' ) . '/i18nly/staging';
	}

	/**
	 * Ensures one directory exists.
	 *
	 * @param string $directory Directory path.
	 * @return void
	 * @throws \RuntimeException When directory creation fails.
	 */
	private function ensure_directory( $directory ) {
		if ( is_dir( $directory ) ) {
			return;
		}

		if ( ! wp_mkdir_p( $directory ) && ! is_dir( $directory ) ) {
			throw new \RuntimeException( 'Unable to create temporary storage directory.' );
		}
	}

	/**
	 * Deletes a directory and all descendants.
	 *
	 * @param string $directory Directory path.
	 * @return void
	 */
	private function delete_directory_recursively( $directory ) {
		$children = glob( $directory . '/*' );

		if ( false !== $children ) {
			foreach ( $children as $child ) {
				if ( is_dir( $child ) ) {
					$this->delete_directory_recursively( $child );
					continue;
				}

				wp_delete_file( $child );
			}
		}

		global $wp_filesystem;

		if ( ! is_object( $wp_filesystem ) && function_exists( 'WP_Filesystem' ) ) {
			WP_Filesystem();
		}

		if ( is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'rmdir' ) ) {
			$wp_filesystem->rmdir( $directory );
		}
	}
}
