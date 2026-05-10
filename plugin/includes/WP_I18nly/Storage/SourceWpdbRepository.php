<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Source entries wpdb repository.
 *
 * @package I18nly
 */

namespace WP_I18nly\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Persists source catalogs and entries in WordPress tables.
 */
class SourceWpdbRepository {
	use SourceWpdbRepositoryDbAccessTrait;
	use SourceWpdbRepositorySourceResourceTrait;
	use SourceWpdbRepositoryTargetResourceTrait;

	/**
	 * Source entry identity columns.
	 *
	 * @var array<int, string>
	 */
	private const ENTRY_IDENTITY_COLUMNS = array(
		'resource_id',
		'msgctxt',
		'msgid',
	);

	/**
	 * Resource kind used for imported source catalogs.
	 */
	private const SOURCE_CATALOG_RESOURCE_KIND = 'source_catalog';

	/**
	 * Default plural forms count used when locale rules are unknown.
	 */
	private const DEFAULT_PLURAL_FORMS_COUNT = 2;

	/**
	 * Source entry content columns tracked for unchanged detection.
	 *
	 * @var array<int, string>
	 */
	private const ENTRY_CONTENT_COLUMNS = array(
		'msgid_plural',
		'translator_comment',
		'comments_json',
		'references_json',
		'flags_json',
		'status',
	);

	/**
	 * WordPress database object.
	 *
	 * @var object
	 */
	private $wpdb;

	/**
	 * Schema manager.
	 *
	 * @var SourceSchemaManager
	 */
	private $schema_manager;

	/**
	 * Constructor.
	 *
	 * @param SourceSchemaManager $schema_manager Source schema manager.
	 * @param object|null         $wpdb Optional wpdb object.
	 */
	public function __construct( SourceSchemaManager $schema_manager, $wpdb = null ) {
		$this->schema_manager = $schema_manager;

		if ( null !== $wpdb ) {
			$this->wpdb = $wpdb;
			return;
		}

		global $wpdb;

		$this->wpdb = $wpdb;
	}
}
