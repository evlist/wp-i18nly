<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * POT file generator.
 *
 * @package I18nly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generates POT files from extracted entries.
 */
class I18nly_Pot_Generator {
	/**
	 * Generates a POT file on disk.
	 *
	 * @param string                          $destination_file Absolute destination file path.
	 * @param string                          $text_domain Text domain for generated headers.
	 * @param array<int, array<string,mixed>> $entries Extracted entries.
	 * @return void
	 * @throws RuntimeException When destination directory or file cannot be written.
	 */
	public function generate( $destination_file, $text_domain, array $entries ) {
		$this->ensure_gettext_classes_are_available();

		$destination_file = (string) $destination_file;
		$text_domain      = (string) $text_domain;
		$translations     = $this->build_translations( $text_domain, $entries );

		$directory = dirname( $destination_file );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Runtime utility writes local generated artifacts.
		if ( ! is_dir( $directory ) && ! mkdir( $directory, 0755, true ) && ! is_dir( $directory ) ) {
			throw new RuntimeException( 'Unable to create destination directory for POT file.' );
		}

		$generator = new \Gettext\Generator\PoGenerator();
		if ( ! $generator->generateFile( $translations, $destination_file ) ) {
			throw new RuntimeException( 'Unable to write POT file to destination.' );
		}
	}

	/**
	 * Builds gettext translations object from extracted entries.
	 *
	 * @param string                           $text_domain Text domain.
	 * @param array<int, array<string, mixed>> $entries POT entries.
	 * @return \Gettext\Translations
	 */
	private function build_translations( $text_domain, array $entries ) {
		$translations = \Gettext\Translations::create();
		$headers = $this->build_headers( $text_domain );

		foreach ( $headers as $header_name => $header_value ) {
			$translations->getHeaders()->set( (string) $header_name, (string) $header_value );
		}

		foreach ( $entries as $entry ) {
			if ( empty( $entry['original'] ) || ! is_string( $entry['original'] ) ) {
				continue;
			}

			$translation = $this->build_translation_entry( $entry );
			$translations->add( $translation );
		}

		return $translations;
	}

	/**
	 * Builds default POT headers.
	 *
	 * @param string $text_domain Text domain.
	 * @return array<string, string>
	 */
	private function build_headers( $text_domain ) {
		$creation_date = gmdate( 'Y-m-d H:i+0000' );
		$headers       = array();

		$headers['Project-Id-Version']        = $text_domain;
		$headers['Report-Msgid-Bugs-To']      = '';
		$headers['POT-Creation-Date']         = $creation_date;
		$headers['PO-Revision-Date']          = 'YEAR-MO-DA HO:MI+ZONE';
		$headers['Last-Translator']           = 'FULL NAME <EMAIL@ADDRESS>';
		$headers['Language-Team']             = 'LANGUAGE <LL@li.org>';
		$headers['Language']                  = '';
		$headers['MIME-Version']              = '1.0';
		$headers['Content-Type']              = 'text/plain; charset=UTF-8';
		$headers['Content-Transfer-Encoding'] = '8bit';
		$headers['X-Domain']                  = $text_domain;

		return $headers;
	}

	/**
	 * Builds one gettext translation entry.
	 *
	 * @param array<string, mixed> $entry Entry descriptor.
	 * @return \Gettext\Translation
	 */
	private function build_translation_entry( array $entry ) {
		$context     = ( ! empty( $entry['context'] ) && is_string( $entry['context'] ) ) ? $entry['context'] : null;
		$translation = \Gettext\Translation::create( $context, (string) $entry['original'] );

		if ( ! empty( $entry['comments'] ) && is_array( $entry['comments'] ) ) {
			foreach ( $entry['comments'] as $comment ) {
				if ( is_string( $comment ) && '' !== trim( $comment ) ) {
					$translation->getExtractedComments()->add( trim( $comment ) );
				}
			}
		}

		if ( ! empty( $entry['references'] ) && is_array( $entry['references'] ) ) {
			foreach ( $entry['references'] as $reference ) {
				if ( is_array( $reference ) && ! empty( $reference['file'] ) ) {
					$file = (string) $reference['file'];
					$line = isset( $reference['line'] ) ? absint( $reference['line'] ) : 0;

					$translation->getReferences()->add( $file, $line > 0 ? $line : null );
				}
			}
		}

		if ( ! empty( $entry['flags'] ) && is_array( $entry['flags'] ) ) {
			foreach ( $entry['flags'] as $flag ) {
				if ( is_string( $flag ) && '' !== trim( $flag ) ) {
					$translation->getFlags()->add( trim( $flag ) );
				}
			}
		}

		if ( ! empty( $entry['plural'] ) && is_string( $entry['plural'] ) ) {
			$translation->setPlural( $entry['plural'] );
		}

		return $translation;
	}

	/**
	 * Ensures gettext classes can be autoloaded.
	 *
	 * @return void
	 */
	private function ensure_gettext_classes_are_available() {
		if ( class_exists( '\\Gettext\\Generator\\PoGenerator' ) && class_exists( '\\Gettext\\Translations' ) ) {
			return;
		}

		require_once dirname( __DIR__ ) . '/third-party/vendor/autoload.php';
	}
}
