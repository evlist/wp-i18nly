<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Reference to the plugin or theme a translation is about.
 *
 * @package I18nly
 */

namespace WP_I18nly\Support;

defined( 'ABSPATH' ) || exit;

/**
 * A source is a plugin or a theme, identified by a string stored with the translation: "plugin:{plugin file}" or "theme:{stylesheet}".
 */
class SourceReference {
	const TYPE_PLUGIN = 'plugin';
	const TYPE_THEME  = 'theme';

	/**
	 * Source type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Plugin file (relative to the plugins directory) or theme stylesheet (folder name).
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Constructor.
	 *
	 * @param string $type TYPE_PLUGIN or TYPE_THEME.
	 * @param string $key Plugin file or theme stylesheet.
	 */
	private function __construct( $type, $key ) {
		$this->type = $type;
		$this->key  = $key;
	}

	/**
	 * Creates the reference of a plugin.
	 *
	 * @param string $plugin_file Plugin file, such as "akismet/akismet.php".
	 * @return self
	 */
	public static function plugin( $plugin_file ) {
		return new self( self::TYPE_PLUGIN, ltrim( (string) $plugin_file, '/\\' ) );
	}

	/**
	 * Creates the reference of a theme.
	 *
	 * @param string $stylesheet Theme folder name.
	 * @return self
	 */
	public static function theme( $stylesheet ) {
		return new self( self::TYPE_THEME, trim( (string) $stylesheet, '/\\' ) );
	}

	/**
	 * Reads a stored identifier. A value without prefix is taken for a plugin file.
	 *
	 * @param string $identifier Stored identifier.
	 * @return self
	 */
	public static function from_identifier( $identifier ) {
		$identifier = (string) $identifier;

		if ( 0 === strpos( $identifier, self::TYPE_THEME . ':' ) ) {
			return self::theme( substr( $identifier, strlen( self::TYPE_THEME ) + 1 ) );
		}

		if ( 0 === strpos( $identifier, self::TYPE_PLUGIN . ':' ) ) {
			return self::plugin( substr( $identifier, strlen( self::TYPE_PLUGIN ) + 1 ) );
		}

		return self::plugin( $identifier );
	}

	/**
	 * Returns the identifier to store.
	 *
	 * @return string
	 */
	public function get_identifier() {
		return $this->type . ':' . $this->key;
	}

	/**
	 * Returns the type.
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * Returns the plugin file or the theme stylesheet.
	 *
	 * @return string
	 */
	public function get_key() {
		return $this->key;
	}

	/**
	 * Tells whether the source is a theme.
	 *
	 * @return bool
	 */
	public function is_theme() {
		return self::TYPE_THEME === $this->type;
	}

	/**
	 * Tells whether the source is a folder (a theme, or a plugin whose file is in a folder).
	 *
	 * @return bool
	 */
	public function is_directory() {
		return $this->is_theme() || false !== strpos( $this->key, '/' );
	}

	/**
	 * Returns the sub-directory of the languages directory where WordPress looks for its translations.
	 *
	 * @return string "plugins" or "themes".
	 */
	public function get_languages_subdirectory() {
		return $this->is_theme() ? 'themes' : 'plugins';
	}
}
