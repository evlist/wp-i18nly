<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * POT workspace orchestration service.
 *
 * @package I18nly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates temporary workspace creation and POT generation.
 */
class I18nly_Pot_Workspace_Service {
	/**
	 * Temporary storage manager.
	 *
	 * @var I18nly_Temporary_Storage
	 */
	private $temporary_storage;

	/**
	 * POT generator.
	 *
	 * @var I18nly_Pot_Generator
	 */
	private $pot_generator;

	/**
	 * Constructor.
	 *
	 * @param I18nly_Temporary_Storage|null $temporary_storage Storage service.
	 * @param I18nly_Pot_Generator|null     $pot_generator POT generator service.
	 */
	public function __construct( $temporary_storage = null, $pot_generator = null ) {
		$this->temporary_storage = $temporary_storage instanceof I18nly_Temporary_Storage
			? $temporary_storage
			: new I18nly_Temporary_Storage();

		$this->pot_generator = $pot_generator instanceof I18nly_Pot_Generator
			? $pot_generator
			: new I18nly_Pot_Generator();
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
