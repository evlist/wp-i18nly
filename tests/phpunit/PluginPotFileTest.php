<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Plugin POT file freshness test.
 *
 * @package I18nly
 */

use Gettext\Loader\PoLoader;
use PHPUnit\Framework\TestCase;
use WP_I18nly\Build\PotSourceEntryExtractor;

/**
 * Keeps plugin/languages/i18nly.pot in step with the strings of the plugin.
 */
class PluginPotFileTest extends TestCase {
	/**
	 * Fails when a string was added, changed or removed without regenerating the POT file.
	 *
	 * @return void
	 */
	public function test_the_pot_file_lists_the_strings_of_the_plugin() {
		$root = dirname( __DIR__, 2 );

		$extracted = array();

		foreach ( ( new PotSourceEntryExtractor( $root ) )->extract_from_source_slug( 'plugin/i18nly.php' ) as $entry ) {
			// The gettext PoLoader drops a msgid that is exactly "0" (it tests it with empty()): compare without it.
			if ( '0' === $entry['original'] ) {
				continue;
			}

			$extracted[] = ( isset( $entry['context'] ) ? $entry['context'] : '' ) . "\x04" . $entry['original'] . "\x04" . ( isset( $entry['plural'] ) ? $entry['plural'] : '' );
		}

		$listed = array();

		foreach ( ( new PoLoader() )->loadFile( $root . '/plugin/languages/i18nly.pot' ) as $translation ) {
			if ( '' !== $translation->getOriginal() ) {
				$listed[] = (string) $translation->getContext() . "\x04" . $translation->getOriginal() . "\x04" . (string) $translation->getPlural();
			}
		}

		sort( $extracted );
		sort( $listed );

		$this->assertSame( $extracted, $listed, 'Regenerate the file with: php scripts/generate-pot.php' );
	}
}
