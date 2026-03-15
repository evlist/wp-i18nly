<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\AI\DeepLCredentialsValidator;

/**
 * Tests DeepL credential validation behavior.
 */
class DeepLCredentialsValidatorTest extends TestCase {
	/**
	 * Empty keys are rejected before HTTP.
	 *
	 * @return void
	 */
	public function test_validate_credentials_rejects_empty_key() {
		$validator = new DeepLCredentialsValidator(
			function () {
				$this->fail( 'HTTP transport should not be called for an empty key.' );
			}
		);

		$result = $validator->validate_credentials( '' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'empty', strtolower( (string) $result['message'] ) );
	}

	/**
	 * Free keys must call the free endpoint.
	 *
	 * @return void
	 */
	public function test_validate_credentials_uses_free_endpoint_for_fx_keys() {
		$calls = array();

		$validator = new DeepLCredentialsValidator(
			function ( $url, array $args ) use ( &$calls ) {
				$calls[] = array(
					'url'  => (string) $url,
					'args' => $args,
				);

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"character_count":10,"character_limit":1000}',
				);
			}
		);

		$result = $validator->validate_credentials( 'abc123:fx' );

		$this->assertTrue( $result['success'] );
		$this->assertCount( 1, $calls );
		$this->assertSame( 'https://api-free.deepl.com/v2/usage', $calls[0]['url'] );
		$this->assertSame( 'DeepL-Auth-Key abc123:fx', $calls[0]['args']['headers']['Authorization'] );
	}

	/**
	 * Pro keys must call the standard endpoint.
	 *
	 * @return void
	 */
	public function test_validate_credentials_uses_pro_endpoint_for_standard_keys() {
		$calls = array();

		$validator = new DeepLCredentialsValidator(
			function ( $url, array $args ) use ( &$calls ) {
				$calls[] = array(
					'url'  => (string) $url,
					'args' => $args,
				);

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{}',
				);
			}
		);

		$result = $validator->validate_credentials( 'abc123' );

		$this->assertTrue( $result['success'] );
		$this->assertCount( 1, $calls );
		$this->assertSame( 'https://api.deepl.com/v2/usage', $calls[0]['url'] );
		$this->assertSame( 'DeepL-Auth-Key abc123', $calls[0]['args']['headers']['Authorization'] );
	}

	/**
	 * HTTP auth errors are mapped to a human message.
	 *
	 * @return void
	 */
	public function test_validate_credentials_maps_unauthorized_http_status() {
		$validator = new DeepLCredentialsValidator(
			function () {
				return array(
					'response' => array( 'code' => 403 ),
					'body'     => '',
				);
			}
		);

		$result = $validator->validate_credentials( 'abc123' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'rejected', strtolower( (string) $result['message'] ) );
	}
}
