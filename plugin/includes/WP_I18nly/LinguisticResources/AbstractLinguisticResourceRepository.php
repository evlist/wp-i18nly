<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Abstract linguistic resource repository.
 *
 * @package I18nly
 */

namespace WP_I18nly\LinguisticResources;

use WP_I18nly\Storage\SourceSchemaManager;
use WP_I18nly\Storage\SourceWpdbRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Base repository for linguistic resources backed by the current wpdb storage.
 */
abstract class AbstractLinguisticResourceRepository {
	/**
	 * Underlying wpdb repository.
	 *
	 * @var SourceWpdbRepository
	 */
	private $storage_repository;

	/**
	 * Constructor.
	 *
	 * @param SourceWpdbRepository|null $storage_repository Optional storage repository.
	 * @param SourceSchemaManager|null  $schema_manager Optional schema manager.
	 */
	public function __construct( SourceWpdbRepository $storage_repository = null, SourceSchemaManager $schema_manager = null ) {
		if ( $storage_repository instanceof SourceWpdbRepository ) {
			$this->storage_repository = $storage_repository;
			return;
		}

		$schema_manager = $schema_manager instanceof SourceSchemaManager
			? $schema_manager
			: new SourceSchemaManager();

		$schema_manager->maybe_upgrade();
		$this->storage_repository = new SourceWpdbRepository( $schema_manager );
	}

	/**
	 * Returns the resource kind handled by the concrete repository.
	 *
	 * @return string
	 */
	abstract public function get_resource_kind();

	/**
	 * Returns the underlying storage repository.
	 *
	 * @return SourceWpdbRepository
	 */
	protected function get_storage_repository() {
		return $this->storage_repository;
	}
}
