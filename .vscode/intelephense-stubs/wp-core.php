<?php

// SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later OR MIT

// Auto-generated stub for VS Code / Intelephense only.
// This file is NOT loaded by WordPress at runtime. It's here for editor intelligence.

if ( ! class_exists( 'wpdb' ) ) {
	/**
	 * Minimal wpdb API surface for editor IntelliSense.
	 */
	class wpdb {
		/**
		 * WordPress table prefix.
		 *
		 * @var string
		 */
		public $prefix = 'wp_';

		/**
		 * Returns the charset/collation SQL fragment.
		 *
		 * @return string
		 */
		public function get_charset_collate() {
			return '';
		}

		/**
		 * Prepares SQL with placeholders.
		 *
		 * @param string $query SQL query.
		 * @param mixed  ...$args Placeholder arguments.
		 * @return string
		 */
		public function prepare( $query, ...$args ) {
			return (string) $query;
		}

		/**
		 * Executes a SQL query.
		 *
		 * @param string $query SQL query.
		 * @return int|false
		 */
		public function query( $query ) {
			return 0;
		}
	}
}

if ( ! class_exists( 'I18nly_Intelephense_wpdb' ) && class_exists( 'wpdb' ) ) {
	/**
	 * Local helper subclass for editor-only instantiation.
	 */
	class I18nly_Intelephense_wpdb extends wpdb {
		/**
		 * No-op constructor for static analysis.
		 */
		public function __construct() {}
	}
}

if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
	/**
	 * Global WordPress database abstraction instance.
	 *
	 * @var wpdb
	 */
	$wpdb = new I18nly_Intelephense_wpdb();
}
