<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation batch translator tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\AI\TranslationBatchTranslator;

/**
 * Tests the batch translation logic without any HTTP request.
 */
class TranslationBatchTranslatorTest extends TestCase {
	/**
	 * Builds a translator whose provider uppercases texts and records the calls.
	 *
	 * @param array<string, mixed> $options Callbacks overrides (batch, persist, throttle, rate, post).
	 * @return TranslationBatchTranslator
	 */
	private function build( array $options = array() ) {
		return new TranslationBatchTranslator(
			static function () {
				return 'key';
			},
			null,
			isset( $options['persist'] ) ? $options['persist'] : null,
			isset( $options['throttle'] ) ? $options['throttle'] : null,
			isset( $options['batch'] ) ? $options['batch'] : static function ( array $items ) {
				return array(
					'success' => true,
					'items'   => array_map(
						static function ( array $item ) {
							return array(
								'success'      => true,
								'translation'  => strtoupper( $item['text'] ),
								'review_token' => 'draft_ai',
							);
						},
						$items
					),
				);
			},
			isset( $options['rate'] ) ? $options['rate'] : null,
			isset( $options['post'] ) ? $options['post'] : null
		);
	}

	/**
	 * Translates items and reports one result per item.
	 *
	 * @return void
	 */
	public function test_translates_items_and_maps_review_tokens_to_statuses() {
		$result = $this->build()->translate(
			42,
			'fr_FR',
			array(
				array(
					'source_entry_id' => 7,
					'form_index'      => 0,
					'source_text'     => 'Hello',
				),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame(
			array(
				array(
					'source_entry_id' => 7,
					'form_index'      => 0,
					'success'         => true,
					'translation'     => 'HELLO',
					'review_token'    => 'draft_ai',
					'message'         => '',
				),
			),
			$result['results']
		);
	}

	/**
	 * Reports invalid items without calling the provider.
	 *
	 * @return void
	 */
	public function test_invalid_items_are_reported_without_calling_the_provider() {
		$calls      = 0;
		$translator = $this->build(
			array(
				'batch' => static function () use ( &$calls ) {
					++$calls;

					return array(
						'success' => true,
						'items' => array(),
					);
				},
			)
		);

		$result = $translator->translate(
			42,
			'fr_FR',
			array(
				array(
					'source_entry_id' => 0,
					'source_text' => 'x',
				),
				array(
					'source_entry_id' => 3,
					'source_text' => '',
				),
				'not-an-item',
			)
		);

		$this->assertSame( 0, $calls );
		$this->assertTrue( $result['success'] );
		$this->assertCount( 2, $result['results'] );
		$this->assertFalse( $result['results'][0]['success'] );
		$this->assertSame( 'Invalid item payload.', $result['results'][0]['message'] );
	}

	/**
	 * Replaces a lone placeholder by the witness number and restores it.
	 *
	 * @return void
	 */
	public function test_single_placeholder_is_replaced_by_the_witness_and_restored() {
		$sent = array();

		$result = $this->build(
			array(
				'batch' => static function ( array $items ) use ( &$sent ) {
					$sent = $items;

					return array(
						'success' => true,
						'items'   => array(
							array(
								'success'      => true,
								'translation'  => '5 pommes',
								'review_token' => 'draft_ai',
							),
						),
					);
				},
			)
		)->translate(
			42,
			'fr_FR',
			array(
				array(
					'source_entry_id' => 7,
					'form_index' => 1,
					'source_text' => '%d apples',
					'witness_n' => 5,
				),
			)
		);

		$this->assertSame( '5 apples', $sent[0]['text'] );
		$this->assertStringContainsString( 'n=5', $sent[0]['context'] );
		$this->assertSame( '%d pommes', $result['results'][0]['translation'] );
		$this->assertSame( 'draft_ai', $result['results'][0]['review_token'] );
	}

	/**
	 * Flags a translation as suspect when the witness cannot be restored.
	 *
	 * @return void
	 */
	public function test_translation_is_suspect_when_the_placeholder_cannot_be_restored() {
		$result = $this->build(
			array(
				'batch' => static function () {
					return array(
						'success' => true,
						'items'   => array(
							array(
								'success'      => true,
								'translation'  => 'plusieurs pommes',
								'review_token' => 'draft_ai',
							),
						),
					);
				},
			)
		)->translate(
			42,
			'fr_FR',
			array(
				array(
					'source_entry_id' => 7,
					'form_index' => 1,
					'source_text' => '%d apples',
					'witness_n' => 5,
				),
			)
		);

		$this->assertSame( 'suspect', $result['results'][0]['review_token'] );
	}

	/**
	 * Persists each translation with its status and calls the hooks around the provider.
	 *
	 * @return void
	 */
	public function test_persists_results_and_calls_throttle_and_post_batch_hooks() {
		$persisted = array();
		$events    = array();

		$translator = new TranslationBatchTranslator(
			static function () {
				return 'key';
			},
			null,
			static function ( $translation_id, $source_entry_id, $form_index, $translation, $status ) use ( &$persisted ) {
				$persisted[] = array( $translation_id, $source_entry_id, $form_index, $translation, $status );
			},
			static function () use ( &$events ) {
				$events[] = 'throttle';
			},
			static function ( array $items ) use ( &$events ) {
				$events[] = 'provider';

				return array(
					'success' => true,
					'items'   => array(
						array(
							'success' => true,
							'translation' => 'Un',
							'review_token' => 'validated',
						),
						array(
							'success' => false,
							'message' => 'Quota',
						),
					),
				);
			},
			null,
			static function ( array $meta ) use ( &$events ) {
				$events[] = 'post:' . $meta['items_count'] . ':' . $meta['success_count'];

				return array(
					'usage_status' => array( 'percent_used' => 10 ),
					'usage_html'   => '<div></div>',
				);
			}
		);

		$result = $translator->translate(
			42,
			'fr_FR',
			array(
				array(
					'source_entry_id' => 1,
					'form_index' => 0,
					'source_text' => 'One',
				),
				array(
					'source_entry_id' => 2,
					'form_index' => 0,
					'source_text' => 'Two',
				),
			)
		);

		$this->assertSame( array( 'throttle', 'provider', 'post:2:1' ), $events );
		$this->assertSame( array( array( 42, 1, 0, 'Un', 'validated' ) ), $persisted, 'Only successful translations are persisted.' );
		$this->assertTrue( $result['results'][0]['success'] );
		$this->assertFalse( $result['results'][1]['success'] );
		$this->assertSame( 'Quota', $result['results'][1]['message'] );
		$this->assertSame( array( 'percent_used' => 10 ), $result['usage_status'] );
		$this->assertSame( '<div></div>', $result['usage_html'] );
	}

	/**
	 * Reports a rate limit with the delay adjusted by the callback.
	 *
	 * @return void
	 */
	public function test_rate_limit_is_reported_with_the_adjusted_delay() {
		$result = $this->build(
			array(
				'batch' => static function () {
					return array(
						'success'        => false,
						'rate_limited'   => true,
						'retry_after_ms' => 1000,
						'message'        => 'Too many requests',
					);
				},
				'rate'  => static function ( $retry_after_ms ) {
					return $retry_after_ms * 2;
				},
			)
		)->translate(
			42,
			'fr_FR',
			array(
				array(
					'source_entry_id' => 1,
					'form_index' => 0,
					'source_text' => 'One',
				),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertTrue( $result['rate_limited'] );
		$this->assertSame( 2000, $result['retry_after_ms'] );
		$this->assertSame( 'Too many requests', $result['message'] );
	}

	/**
	 * Marks every item as failed when the provider fails or answers inconsistently.
	 *
	 * @return void
	 */
	public function test_provider_failure_marks_every_item_as_failed() {
		$items = array(
			array(
				'source_entry_id' => 1,
				'form_index' => 0,
				'source_text' => 'One',
			),
			array(
				'source_entry_id' => 2,
				'form_index' => 0,
				'source_text' => 'Two',
			),
		);

		$failed = $this->build(
			array(
				'batch' => static function () {
					return array(
						'success' => false,
						'message' => 'Provider down',
					);
				},
			)
		)->translate( 42, 'fr_FR', $items );

		$this->assertTrue( $failed['success'] );
		$this->assertSame( array( false, false ), array_column( $failed['results'], 'success' ) );
		$this->assertSame( 'Provider down', $failed['results'][0]['message'] );

		$short = $this->build(
			array(
				'batch' => static function () {
					return array(
						'success' => true,
						'items' => array(
							array(
								'success' => true,
								'translation' => 'Un',
							),
						),
					);
				},
			)
		)->translate( 42, 'fr_FR', $items );

		$this->assertSame( array( false, false ), array_column( $short['results'], 'success' ), 'A short provider answer fails the whole batch.' );
		$this->assertSame( 'Translation failed.', $short['results'][0]['message'] );
	}

	/**
	 * Maps legacy review tokens to stored statuses.
	 *
	 * @return void
	 */
	public function test_review_tokens_are_mapped_to_statuses() {
		$statuses = array();

		foreach ( array( 'suspect', 'ai_draft_needs_fix', 'draft_ai_suspect', 'ai_draft_ok', 'draft', 'validated', 'unknown', '' ) as $token ) {
			$result = $this->build(
				array(
					'batch' => static function () use ( $token ) {
						return array(
							'success' => true,
							'items' => array(
								array(
									'success' => true,
									'translation' => 'T',
									'review_token' => $token,
								),
							),
						);
					},
				)
			)->translate(
				42,
				'fr_FR',
				array(
					array(
						'source_entry_id' => 1,
						'form_index' => 0,
						'source_text' => 'One',
					),
				)
			);

			$statuses[ $token ] = $result['results'][0]['review_token'];
		}

		$this->assertSame(
			array(
				'suspect'            => 'suspect',
				'ai_draft_needs_fix' => 'suspect',
				'draft_ai_suspect'   => 'suspect',
				'ai_draft_ok'        => 'draft_ai',
				'draft'              => 'draft',
				'validated'          => 'validated',
				'unknown'            => 'draft_ai',
				''                   => 'draft_ai',
			),
			$statuses
		);
	}

	/**
	 * Adapts a single-item callable to the batch provider interface.
	 *
	 * @return void
	 */
	public function test_single_item_callable_is_adapted_to_a_batch() {
		$translator = new TranslationBatchTranslator(
			static function () {
				return 'key';
			},
			static function ( $text, $source_locale, $target_locale, $context ) {
				return array(
					'success' => true,
					'translation' => $source_locale . '>' . $target_locale . ':' . $text . '|' . $context,
					'review_token' => 'draft',
				);
			}
		);

		$result = $translator->translate(
			42,
			'de_DE',
			array(
				array(
					'source_entry_id' => 1,
					'form_index' => 0,
					'source_text' => 'One',
				),
				array(
					'source_entry_id' => 2,
					'form_index' => 0,
					'source_text' => '%s items',
				),
			)
		);

		$this->assertSame( 'en_US>de_DE:One|', $result['results'][0]['translation'] );
		$this->assertStringStartsWith( 'en_US>de_DE:%s items|Software UI message.', $result['results'][1]['translation'] );
		$this->assertSame( 'draft', $result['results'][0]['review_token'] );
	}
}
