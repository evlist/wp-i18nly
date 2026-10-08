<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translatable string extraction from block and theme JSON files.
 *
 * @package I18nly
 */

namespace WP_I18nly\Build;

defined( 'ABSPATH' ) || exit;

/**
 * Extracts translatable strings from block.json, theme.json and theme style variation files.
 */
class JsonI18nExtractor {
	/**
	 * Extracts entries from translatable JSON metadata files.
	 *
	 * @param string $json_contents JSON content.
	 * @param string $relative_json_path Relative JSON path.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_from_file_contents( $json_contents, $relative_json_path ) {
		$decoded = json_decode( $json_contents, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$basename = strtolower( (string) basename( $relative_json_path ) );
		$entries  = array();

		if ( 'block.json' === $basename ) {
			$this->extract_json_entries_using_schema( $entries, $relative_json_path, $this->get_block_json_i18n_schema(), $decoded );
		}

		if ( 'theme.json' === $basename || 0 === strpos( str_replace( '\\', '/', strtolower( $relative_json_path ) ), 'styles/' ) ) {
			$this->extract_json_entries_using_schema( $entries, $relative_json_path, $this->get_theme_json_i18n_schema(), $decoded );
		}

		return $entries;
	}

	/**
	 * Returns local block.json i18n schema.
	 *
	 * @return array<string, mixed>
	 */
	private function get_block_json_i18n_schema() {
		return array(
			'title'       => 'block title',
			'description' => 'block description',
			'keywords'    => array( 'block keyword' ),
			'styles'      => array(
				array(
					'label' => 'block style label',
				),
			),
			'variations'  => array(
				array(
					'title'       => 'block variation title',
					'description' => 'block variation description',
				),
			),
		);
	}

	/**
	 * Returns local theme.json i18n schema.
	 *
	 * @return array<string, mixed>
	 */
	private function get_theme_json_i18n_schema() {
		return array(
			'settings' => array(
				'color'      => array(
					'palette'   => array( array( 'name' => 'color name' ) ),
					'gradients' => array( array( 'name' => 'gradient name' ) ),
				),
				'typography' => array(
					'fontFamilies' => array( array( 'name' => 'font family name' ) ),
					'fontSizes'    => array( array( 'name' => 'font size name' ) ),
				),
			),
			'styles'   => array(
				'elements' => array(
					'*' => array(
						'typography' => array(
							'fontFamily' => 'font family value',
						),
					),
				),
			),
		);
	}

	/**
	 * Recursively extracts JSON translation entries using an i18n schema.
	 *
	 * @param array<int, array<string, mixed>> &$entries Accumulator.
	 * @param string                           $relative_path Relative file path.
	 * @param mixed                            $schema Schema node.
	 * @param mixed                            $settings Settings node.
	 * @return void
	 */
	private function extract_json_entries_using_schema( array &$entries, $relative_path, $schema, $settings ) {
		if ( is_string( $schema ) && is_string( $settings ) && '' !== trim( $settings ) ) {
			$entries[] = array(
				'original'   => $settings,
				'context'    => $schema,
				'references' => array(
					array(
						'file' => $relative_path,
						'line' => 1,
					),
				),
			);

			return;
		}

		if ( is_array( $schema ) && ! empty( $schema ) && array_is_list( $schema ) && is_array( $settings ) ) {
			foreach ( $settings as $entry ) {
				$this->extract_json_entries_using_schema( $entries, $relative_path, $schema[0], $entry );
			}

			return;
		}

		if ( is_array( $schema ) && is_array( $settings ) ) {
			foreach ( $settings as $key => $value ) {
				if ( isset( $schema[ $key ] ) ) {
					$this->extract_json_entries_using_schema( $entries, $relative_path, $schema[ $key ], $value );
					continue;
				}

				if ( isset( $schema['*'] ) ) {
					$this->extract_json_entries_using_schema( $entries, $relative_path, $schema['*'], $value );
				}
			}
		}
	}
}
