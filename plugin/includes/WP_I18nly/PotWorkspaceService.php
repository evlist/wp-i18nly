<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * POT workspace orchestration service.
 *
 * @package I18nly
 */

namespace WP_I18nly;

use WP_I18nly\Build\PotGenerator as BuildPotGenerator;
use WP_I18nly\Storage\TemporaryStorage;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates temporary workspace creation and POT generation.
 */
class PotWorkspaceService {
	/**
	 * Temporary storage manager.
	 *
	 * @var TemporaryStorage
	 */
	private $temporary_storage;

	/**
	 * POT generator.
	 *
	 * @var BuildPotGenerator
	 */
	private $pot_generator;

	/**
	 * Constructor.
	 *
	 * @param TemporaryStorage|null  $temporary_storage Storage service.
	 * @param BuildPotGenerator|null $pot_generator POT generator service.
	 */
	public function __construct( $temporary_storage = null, $pot_generator = null ) {
		$this->temporary_storage = $temporary_storage instanceof TemporaryStorage
			? $temporary_storage
			: new TemporaryStorage();

		$this->pot_generator = $pot_generator instanceof BuildPotGenerator
			? $pot_generator
			: new BuildPotGenerator();
	}

	/**
	 * Generates a temporary POT file for one translation workspace.
	 *
	 * @param int                              $translation_post_id Translation post ID.
	 * @param string                           $text_domain Text domain for the POT file.
	 * @param array<int, array<string, mixed>> $entries Extracted translation entries.
	 * @param array<string, string>            $header_overrides Header values overriding defaults.
	 * @return string Absolute generated POT path.
	 */
	public function generate_temporary_pot( $translation_post_id, $text_domain, array $entries, array $header_overrides = array() ) {
		$this->temporary_storage->ensure_translation_workspace( $translation_post_id );

		$pot_file_path = $this->temporary_storage->get_pot_file_path( $translation_post_id );
		$this->pot_generator->generate( $pot_file_path, $text_domain, $entries, $header_overrides );

		return $pot_file_path;
	}
}
