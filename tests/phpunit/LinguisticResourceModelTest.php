<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Linguistic resource model tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

/**
 * Locks the first translation-backed linguistic resource model behavior.
 */
class LinguisticResourceModelTest extends TestCase {
	/**
	 * Expects the first linguistic resource classes to exist.
	 *
	 * @return void
	 */
	public function test_translation_linguistic_resource_classes_exist() {
		$this->assertTrue( class_exists( 'WP_I18nly\\LinguisticResources\\AbstractLinguisticResource' ) );
		$this->assertTrue( class_exists( 'WP_I18nly\\LinguisticResources\\AbstractLinguisticResourceEntry' ) );
		$this->assertTrue( class_exists( 'WP_I18nly\\LinguisticResources\\AbstractLinguisticResourceEditorModel' ) );
		$this->assertTrue( class_exists( 'WP_I18nly\\LinguisticResources\\AbstractLinguisticResourceRepository' ) );
		$this->assertTrue( class_exists( 'WP_I18nly\\LinguisticResources\\TranslationResource' ) );
		$this->assertTrue( class_exists( 'WP_I18nly\\LinguisticResources\\TranslationResourceEntry' ) );
		$this->assertTrue( class_exists( 'WP_I18nly\\LinguisticResources\\TranslationEditorModel' ) );
		$this->assertTrue( class_exists( 'WP_I18nly\\LinguisticResources\\TranslationResourceRepository' ) );
	}

	/**
	 * Expects repository rows to round-trip through the translation editor model.
	 *
	 * @return void
	 */
	public function test_translation_editor_model_round_trips_rows_with_plural_metadata() {
		$model = \WP_I18nly\LinguisticResources\TranslationEditorModel::from_repository_rows(
			42,
			'akismet/akismet.php',
			'en_US',
			'fr_FR',
			array(
				array(
					'source_entry_id' => 61,
					'msgctxt'         => '',
					'msgid'           => '%s apple',
					'msgid_plural'    => '%s apples',
					'translations'    => array(
						array(
							'form_index'  => 0,
							'translation' => '%s pomme',
						),
						array(
							'form_index'  => 1,
							'translation' => '%s pommes',
						),
					),
				),
			),
			array(
				array(
					'marker'   => 'a',
					'label'    => 'one',
					'tooltip'  => 'one',
					'examples' => array( 1 ),
				),
				array(
					'marker'   => 'b',
					'label'    => 'other',
					'tooltip'  => 'other',
					'examples' => array( 2 ),
				),
			),
			array( 'one', 'other' ),
			array( 'a', 'b' ),
			array( 'one', 'other' )
		);

		$resource = $model->get_translation_resource();
		$rows     = $model->to_rows();

		$this->assertSame( 'translation', $resource->get_resource_kind() );
		$this->assertSame( 42, $resource->get_resource_id() );
		$this->assertSame( 'akismet/akismet.php', $resource->get_source_slug() );
		$this->assertSame( 'en_US', $resource->get_source_locale() );
		$this->assertSame( 'fr_FR', $resource->get_target_locale() );
		$this->assertCount( 1, $rows );
		$this->assertSame( 61, $rows[0]['source_entry_id'] );
		$this->assertSame( array( 'one', 'other' ), $rows[0]['form_labels'] );
		$this->assertSame( array( 'a', 'b' ), $rows[0]['form_markers'] );
		$this->assertSame( array( 'one', 'other' ), $rows[0]['form_tooltips'] );
		$this->assertIsArray( $rows[0]['forms'] );
	}
}
