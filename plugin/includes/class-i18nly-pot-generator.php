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
		$destination_file = (string) $destination_file;
		$text_domain      = (string) $text_domain;

		$directory = dirname( $destination_file );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Runtime utility writes local generated artifacts.
		if ( ! is_dir( $directory ) && ! mkdir( $directory, 0755, true ) && ! is_dir( $directory ) ) {
			throw new RuntimeException( 'Unable to create destination directory for POT file.' );
		}

		$content = $this->build_pot_content( $text_domain, $entries );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Runtime utility writes local generated artifacts.
		if ( false === file_put_contents( $destination_file, $content ) ) {
			throw new RuntimeException( 'Unable to write POT file to destination.' );
		}
	}

	/**
	 * Builds POT file content.
	 *
	 * @param string                           $text_domain Text domain.
	 * @param array<int, array<string, mixed>> $entries POT entries.
	 * @return string
	 */
	private function build_pot_content( $text_domain, array $entries ) {
		$lines   = array();
		$headers = $this->build_headers( $text_domain );

		$lines[] = 'msgid ""';
		$lines[] = 'msgstr ""';

		foreach ( $headers as $header_name => $header_value ) {
			$lines[] = sprintf( '"%s: %s\\n"', $header_name, $header_value );
		}

		$lines[] = '';

		foreach ( $entries as $entry ) {
			if ( empty( $entry['original'] ) || ! is_string( $entry['original'] ) ) {
				continue;
			}

			$lines   = array_merge( $lines, $this->build_entry_lines( $entry ) );
			$lines[] = '';
		}

		return implode( "\n", $lines );
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
	 * Builds one entry block lines.
	 *
	 * @param array<string, mixed> $entry Entry descriptor.
	 * @return array<int, string>
	 */
	private function build_entry_lines( array $entry ) {
		$lines = array();

		if ( ! empty( $entry['comments'] ) && is_array( $entry['comments'] ) ) {
			foreach ( $entry['comments'] as $comment ) {
				if ( is_string( $comment ) && '' !== trim( $comment ) ) {
					$lines[] = '#. ' . trim( $comment );
				}
			}
		}

		if ( ! empty( $entry['references'] ) && is_array( $entry['references'] ) ) {
			foreach ( $entry['references'] as $reference ) {
				if ( is_array( $reference ) && ! empty( $reference['file'] ) ) {
					$file = (string) $reference['file'];
					$line = isset( $reference['line'] ) ? (int) $reference['line'] : 0;

					$lines[] = '#: ' . $file . ( $line > 0 ? ':' . $line : '' );
				}
			}
		}

		if ( ! empty( $entry['context'] ) && is_string( $entry['context'] ) ) {
			$lines[] = 'msgctxt ' . $this->quote( $entry['context'] );
		}

		$lines[] = 'msgid ' . $this->quote( $entry['original'] );

		if ( ! empty( $entry['plural'] ) && is_string( $entry['plural'] ) ) {
			$lines[] = 'msgid_plural ' . $this->quote( $entry['plural'] );
			$lines[] = 'msgstr[0] ""';
			$lines[] = 'msgstr[1] ""';
		} else {
			$lines[] = 'msgstr ""';
		}

		return $lines;
	}

	/**
	 * Quotes and escapes a string for PO/POT format.
	 *
	 * @param string $value Raw string.
	 * @return string
	 */
	private function quote( $value ) {
		return '"' . strtr(
			(string) $value,
			array(
				'\\' => '\\\\',
				'"'  => '\\"',
				"\n" => '\\n',
				"\r" => '\\r',
				"\t" => '\\t',
			)
		) . '"';
	}
}
