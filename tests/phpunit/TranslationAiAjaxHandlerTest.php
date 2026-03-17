<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\AI\TranslationAiAjaxHandler;

/**
 * Tests AI translation AJAX handler behavior.
 */
class TranslationAiAjaxHandlerTest extends TestCase {
	/**
	 * Resets test state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		i18nly_test_reset_last_json_response();
		i18nly_test_set_can_manage_options( true );
		$_POST = array();
	}

	/**
	 * Missing required parameters return an error response.
	 *
	 * @return void
	 */
	public function test_handle_translate_entry_rejects_missing_parameters() {
		$handler = $this->make_handler();

		$handler->handle_translate_entry();

		$response = i18nly_test_get_last_json_response();
		$this->assertFalse( $response['success'] );
		$this->assertSame( 400, $response['status'] );
	}

	/**
	 * Insufficient capability returns a 403 error.
	 *
	 * @return void
	 */
	public function test_handle_translate_entry_rejects_insufficient_capability() {
		i18nly_test_set_can_manage_options( false );
		$_POST = $this->valid_post( 7 );

		$handler = $this->make_handler();
		$handler->handle_translate_entry();

		$response = i18nly_test_get_last_json_response();
		$this->assertFalse( $response['success'] );
		$this->assertSame( 403, $response['status'] );
	}

	/**
	 * Invalid nonce returns a 403 error.
	 *
	 * @return void
	 */
	public function test_handle_translate_entry_rejects_invalid_nonce() {
		$_POST          = $this->valid_post( 7 );
		$_POST['nonce'] = 'bad-nonce';

		$handler = $this->make_handler();
		$handler->handle_translate_entry();

		$response = i18nly_test_get_last_json_response();
		$this->assertFalse( $response['success'] );
		$this->assertSame( 403, $response['status'] );
	}

	/**
	 * Missing target locale in translation row returns a 400 error.
	 *
	 * @return void
	 */
	public function test_handle_translate_entry_rejects_missing_target_locale() {
		$_POST = $this->valid_post( 7 );

		$handler = $this->make_handler(
			function () {
				return array( 'source_slug' => 'myplugin/myplugin.php' );
			}
		);
		$handler->handle_translate_entry();

		$response = i18nly_test_get_last_json_response();
		$this->assertFalse( $response['success'] );
		$this->assertSame( 400, $response['status'] );
	}

	/**
	 * Missing API key returns a 400 error.
	 *
	 * @return void
	 */
	public function test_handle_translate_entry_rejects_missing_api_key() {
		$_POST = $this->valid_post( 7 );

		$handler = $this->make_handler(
			null,
			function () {
				return '';
			}
		);
		$handler->handle_translate_entry();

		$response = i18nly_test_get_last_json_response();
		$this->assertFalse( $response['success'] );
		$this->assertSame( 400, $response['status'] );
	}

	/**
	 * Successful translation returns translation text and review token.
	 *
	 * @return void
	 */
	public function test_handle_translate_entry_returns_translation_on_success() {
		$_POST = $this->valid_post( 7 );

		$handler = $this->make_handler(
			null,
			null,
			function () {
				return array(
					'success'      => true,
					'translation'  => 'Bonjour monde',
					'review_token' => 'draft_ai',
				);
			}
		);
		$handler->handle_translate_entry();

		$response = i18nly_test_get_last_json_response();
		$this->assertTrue( $response['success'] );
		$this->assertSame( 'Bonjour monde', $response['data']['translation'] );
		$this->assertSame( 'draft_ai', $response['data']['review_token'] );
		$this->assertSame( 3, $response['data']['source_entry_id'] );
		$this->assertSame( 0, $response['data']['form_index'] );
	}

	/**
	 * Failed translation from DeepL client is forwarded as error.
	 *
	 * @return void
	 */
	public function test_handle_translate_entry_forwards_client_error() {
		$_POST = $this->valid_post( 7 );

		$handler = $this->make_handler(
			null,
			null,
			function () {
				return array(
					'success' => false,
					'message' => 'DeepL rejected the key.',
				);
			}
		);
		$handler->handle_translate_entry();

		$response = i18nly_test_get_last_json_response();
		$this->assertFalse( $response['success'] );
	}

	/**
	 * Witness value is translated into numeric-placeholder context.
	 *
	 * @return void
	 */
	public function test_handle_translate_entry_builds_context_from_witness() {
		$_POST                = $this->valid_post( 7 );
		$_POST['source_text'] = '%s translations not updated';
		$_POST['witness_n']   = '2';

		$received_context = '';

		$handler = $this->make_handler(
			null,
			null,
			function ( $source_text, $source_locale, $target_locale, $context = '' ) use ( &$received_context ) {
				unset( $source_text, $source_locale, $target_locale );
				$received_context = (string) $context;

				return array(
					'success'      => true,
					'translation'  => 'ok',
					'review_token' => 'draft_ai',
				);
			}
		);

		$handler->handle_translate_entry();

		$this->assertStringContainsString( 'numeric count', strtolower( $received_context ) );
		$this->assertStringContainsString( 'n=2', strtolower( $received_context ) );
	}

	/**
	 * Replaces one single placeholder with witness before translation and restores it afterwards.
	 *
	 * @return void
	 */
	public function test_handle_translate_entry_replaces_and_restores_single_placeholder_with_witness() {
		$_POST                = $this->valid_post( 7 );
		$_POST['source_text'] = '%s translations moved to the Trash.';
		$_POST['witness_n']   = '0';

		$captured_source = '';

		$handler = $this->make_handler(
			null,
			null,
			function ( $source_text ) use ( &$captured_source ) {
				$captured_source = (string) $source_text;

				return array(
					'success'      => true,
					'translation'  => '0 traductions ont ete deplacees dans la corbeille.',
					'review_token' => 'draft_ai',
				);
			}
		);

		$handler->handle_translate_entry();

		$response = i18nly_test_get_last_json_response();
		$this->assertSame( '0 translations moved to the Trash.', $captured_source );
		$this->assertSame( '%s traductions ont ete deplacees dans la corbeille.', $response['data']['translation'] );
	}

	/**
	 * Keeps original source text when multiple placeholders are present.
	 *
	 * @return void
	 */
	public function test_handle_translate_entry_does_not_replace_when_multiple_placeholders_exist() {
		$_POST                = $this->valid_post( 7 );
		$_POST['source_text'] = '%1$s moved %2$d items.';
		$_POST['witness_n']   = '2';

		$captured_source = '';

		$handler = $this->make_handler(
			null,
			null,
			function ( $source_text ) use ( &$captured_source ) {
				$captured_source = (string) $source_text;

				return array(
					'success'      => true,
					'translation'  => 'ok',
					'review_token' => 'draft_ai',
				);
			}
		);

		$handler->handle_translate_entry();

		$this->assertSame( '%1$s moved %2$d items.', $captured_source );
	}

	/**
	 * Persists translated text and mapped status through optional callback.
	 *
	 * @return void
	 */
	public function test_handle_translate_entry_persists_translation_with_mapped_status() {
		$_POST                = $this->valid_post( 7 );
		$_POST['source_text'] = '%s translations moved to the Trash.';
		$_POST['witness_n']   = '0';

		$persist_calls = array();

		$handler = $this->make_handler(
			null,
			null,
			function () {
				return array(
					'success'      => true,
					'translation'  => 'aucune traduction n\'a ete deplacee dans la corbeille.',
					'review_token' => 'draft_ai',
				);
			},
			function ( $translation_id, $source_entry_id, $form_index, $translation, $status ) use ( &$persist_calls ) {
				$persist_calls[] = array(
					$translation_id,
					$source_entry_id,
					$form_index,
					$translation,
					$status,
				);
			}
		);

		$handler->handle_translate_entry();

		$this->assertCount( 1, $persist_calls );
		$this->assertSame( array( 7, 3, 0, 'aucune traduction n\'a ete deplacee dans la corbeille.', 'draft_ai_needs_fix' ), $persist_calls[0] );
	}

	/**
	 * Builds a valid POST payload for translation_id.
	 *
	 * @param int $translation_id Translation ID.
	 * @return array<string, mixed>
	 */
	private function valid_post( $translation_id ) {
		return array(
			'translation_id'  => (string) $translation_id,
			'source_entry_id' => '3',
			'form_index'      => '0',
			'source_text'     => 'Hello world',
			'nonce'           => 'nonce-i18nly_translate_entry_' . $translation_id,
		);
	}

	/**
	 * Builds a handler with optional callback overrides.
	 *
	 * @param callable|null $get_translation Override for translation callback.
	 * @param callable|null $get_api_key Override for API key callback.
	 * @param callable|null $translate Override for translate callable.
	 * @param callable|null $persist Override for persist callback.
	 * @return \WP_I18nly\AI\TranslationAiAjaxHandler
	 */
	private function make_handler( $get_translation = null, $get_api_key = null, $translate = null, $persist = null ) {
		$get_translation = $get_translation ?? function () {
			return array(
				'source_slug'     => 'myplugin/myplugin.php',
				'target_language' => 'fr_FR',
			);
		};

		$get_api_key = $get_api_key ?? function () {
			return 'prokey-abc';
		};

		$translate = $translate ?? function () {
			return array(
				'success'      => true,
				'translation'  => 'Bonjour monde',
				'review_token' => 'draft_ai',
			);
		};

		return new TranslationAiAjaxHandler( $get_translation, $get_api_key, $translate, $persist );
	}
}
