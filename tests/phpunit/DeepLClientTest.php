<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\AI\DeepLClient;

/**
 * Tests DeepL single-item translation behavior.
 */
class DeepLClientTest extends TestCase {
	/**
	 * Empty source text is rejected before HTTP.
	 *
	 * @return void
	 */
	public function test_translate_item_rejects_empty_source_text() {
		$client = new DeepLClient(
			'dummy-key',
			function () {
				$this->fail( 'HTTP transport should not be called for empty source text.' );
			}
		);

		$result = $client->translate_item( '', 'en_US', 'fr_FR' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'empty', strtolower( (string) $result['message'] ) );
	}

	/**
	 * Free keys use the free endpoint.
	 *
	 * @return void
	 */
	public function test_translate_item_uses_free_endpoint_for_fx_keys() {
		$calls = array();

		$client = new DeepLClient(
			'mykey:fx',
			function ( $url, array $args ) use ( &$calls ) {
				$calls[] = array(
					'url'  => (string) $url,
					'args' => $args,
				);

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"translations":[{"text":"Bonjour"}]}',
				);
			}
		);

		$result = $client->translate_item( 'Hello', 'en_US', 'fr_FR' );

		$this->assertTrue( $result['success'] );
		$this->assertCount( 1, $calls );
		$this->assertSame( 'https://api-free.deepl.com/v2/translate', $calls[0]['url'] );
		$this->assertSame( 'DeepL-Auth-Key mykey:fx', $calls[0]['args']['headers']['Authorization'] );
	}

	/**
	 * Pro keys use the standard endpoint.
	 *
	 * @return void
	 */
	public function test_translate_item_uses_pro_endpoint_for_standard_keys() {
		$calls = array();

		$client = new DeepLClient(
			'prokey',
			function ( $url, array $args ) use ( &$calls ) {
				$calls[] = array(
					'url'  => (string) $url,
					'args' => $args,
				);

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"translations":[{"text":"Bonjour"}]}',
				);
			}
		);

		$client->translate_item( 'Hello', 'en_US', 'fr_FR' );

		$this->assertCount( 1, $calls );
		$this->assertSame( 'https://api.deepl.com/v2/translate', $calls[0]['url'] );
	}

	/**
	 * Successful response returns translation and ai_draft_ok token.
	 *
	 * @return void
	 */
	public function test_translate_item_returns_translation_and_review_token() {
		$client = new DeepLClient(
			'prokey',
			function () {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"translations":[{"text":"Monde"}]}',
				);
			}
		);

		$result = $client->translate_item( 'World', 'en_US', 'fr_FR' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'Monde', $result['translation'] );
		$this->assertSame( 'ai_draft_ok', $result['review_token'] );
	}

	/**
	 * HTTP transport error is normalized to a failure result.
	 *
	 * @return void
	 */
	public function test_translate_item_handles_wp_error() {
		$client = new DeepLClient(
			'prokey',
			function () {
				return new \WP_Error( 'http_request_failed', 'Connection refused' );
			}
		);

		$result = $client->translate_item( 'Hello', 'en_US', 'fr_FR' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Connection refused', (string) $result['message'] );
	}

	/**
	 * HTTP 401 maps to an auth error message.
	 *
	 * @return void
	 */
	public function test_translate_item_maps_unauthorized_status() {
		$client = new DeepLClient(
			'badkey',
			function () {
				return array(
					'response' => array( 'code' => 401 ),
					'body'     => '',
				);
			}
		);

		$result = $client->translate_item( 'Hello', 'en_US', 'fr_FR' );

		$this->assertFalse( $result['success'] );
	}

	/**
	 * Source locale is always normalized to DeepL two-letter code.
	 *
	 * @return void
	 */
	public function test_translate_item_sends_correct_source_lang() {
		$calls  = array();
		$client = new DeepLClient(
			'prokey',
			function ( $url, array $args ) use ( &$calls ) {
				$calls[] = $args;

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"translations":[{"text":"ok"}]}',
				);
			}
		);

		$client->translate_item( 'Hello', 'en_US', 'de_DE' );

		$body = isset( $calls[0]['body'] ) ? (string) $calls[0]['body'] : '';
		$this->assertStringContainsString( 'source_lang=EN', $body );
		$this->assertStringContainsString( 'target_lang=DE', $body );
	}

	/**
	 * English target locale maps to regional variant.
	 *
	 * @return void
	 */
	public function test_translate_item_maps_en_us_target_locale() {
		$calls  = array();
		$client = new DeepLClient(
			'prokey',
			function ( $url, array $args ) use ( &$calls ) {
				$calls[] = $args;

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"translations":[{"text":"ok"}]}',
				);
			}
		);

		$client->translate_item( 'Hello', 'en_US', 'en_US' );

		$body = isset( $calls[0]['body'] ) ? (string) $calls[0]['body'] : '';
		$this->assertStringContainsString( 'target_lang=EN-US', $body );
	}

	/**
	 * Brazilian Portuguese target locale maps to PT-BR.
	 *
	 * @return void
	 */
	public function test_translate_item_maps_pt_br_target_locale() {
		$calls  = array();
		$client = new DeepLClient(
			'prokey',
			function ( $url, array $args ) use ( &$calls ) {
				$calls[] = $args;

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"translations":[{"text":"ok"}]}',
				);
			}
		);

		$client->translate_item( 'Hello', 'en_US', 'pt_BR' );

		$body = isset( $calls[0]['body'] ) ? (string) $calls[0]['body'] : '';
		$this->assertStringContainsString( 'target_lang=PT-BR', $body );
	}
}
