<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Tests for the reference to a plugin or a theme.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\Support\SourceReference;

/**
 * Tests for SourceReference.
 */
class SourceReferenceTest extends TestCase {
	/**
	 * Plugins and themes have distinct identifiers, even with the same name.
	 *
	 * @return void
	 */
	public function test_identifiers_carry_the_type() {
		$this->assertSame( 'plugin:akismet/akismet.php', SourceReference::plugin( 'akismet/akismet.php' )->get_identifier() );
		$this->assertSame( 'theme:twentytwentyfive', SourceReference::theme( 'twentytwentyfive' )->get_identifier() );
		$this->assertNotSame( SourceReference::plugin( 'hello.php' )->get_identifier(), SourceReference::theme( 'hello.php' )->get_identifier() );
	}

	/**
	 * Stored identifiers are read back, an identifier without prefix being a plugin.
	 *
	 * @return void
	 */
	public function test_identifiers_are_read_back() {
		$theme = SourceReference::from_identifier( 'theme:twentytwentyfive' );

		$this->assertTrue( $theme->is_theme() );
		$this->assertTrue( $theme->is_directory() );
		$this->assertSame( 'twentytwentyfive', $theme->get_key() );
		$this->assertSame( 'themes', $theme->get_languages_subdirectory() );

		$plugin = SourceReference::from_identifier( 'plugin:akismet/akismet.php' );

		$this->assertFalse( $plugin->is_theme() );
		$this->assertSame( 'akismet/akismet.php', $plugin->get_key() );
		$this->assertSame( 'plugins', $plugin->get_languages_subdirectory() );
		$this->assertFalse( SourceReference::from_identifier( 'plugin:hello.php' )->is_directory() );
		$this->assertSame( 'hello.php', SourceReference::from_identifier( 'hello.php' )->get_key() );
	}
}
