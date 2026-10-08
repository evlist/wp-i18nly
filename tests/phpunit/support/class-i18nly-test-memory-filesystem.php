<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * In-memory filesystem test double.
 *
 * @package I18nly
 */

/**
 * In-memory filesystem with the methods of WP_Filesystem_Base used by the installer.
 */
class I18nly_Test_Memory_Filesystem {
	/**
	 * Files indexed by path.
	 *
	 * @var array<string, string>
	 */
	public $files = array();

	/**
	 * Existing directories.
	 *
	 * @var array<string, bool>
	 */
	public $directories = array();

	/**
	 * Whether writing fails.
	 *
	 * @var bool
	 */
	public $fail_writes = false;

	/**
	 * Tells whether a path is a directory.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	public function is_dir( $path ) {
		return isset( $this->directories[ $path ] );
	}

	/**
	 * Creates a directory.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	public function mkdir( $path ) {
		$this->directories[ $path ] = true;

		return true;
	}

	/**
	 * Tells whether a file exists.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	public function exists( $path ) {
		return isset( $this->files[ $path ] );
	}

	/**
	 * Reads a file.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	public function get_contents( $path ) {
		return $this->files[ $path ];
	}

	/**
	 * Writes a file.
	 *
	 * @param string $path Path.
	 * @param string $contents Contents.
	 * @return bool
	 */
	public function put_contents( $path, $contents ) {
		if ( $this->fail_writes ) {
			return false;
		}

		$this->files[ $path ] = $contents;

		return true;
	}

	/**
	 * Moves a file.
	 *
	 * @param string $source Source.
	 * @param string $destination Destination.
	 * @return bool
	 */
	public function move( $source, $destination ) {
		$this->files[ $destination ] = $this->files[ $source ];
		unset( $this->files[ $source ] );

		return true;
	}
}
