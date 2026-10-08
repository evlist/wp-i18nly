<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Admin registration tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\Support\AdminRegistration;

/**
 * Tests that translations are restricted to administrators.
 */
class AdminRegistrationTest extends TestCase {
	/**
	 * The post type does not use the default post capabilities.
	 *
	 * @return void
	 */
	public function test_post_type_has_its_own_capabilities_reserved_to_administrators() {
		global $i18nly_test_registered_post_types;

		$i18nly_test_registered_post_types = array();

		( new AdminRegistration() )->register_post_type();

		$args = $i18nly_test_registered_post_types['i18nly_translation'];

		$this->assertNotSame( 'post', $args['capability_type'] );
		$this->assertTrue( $args['map_meta_cap'] );
		$this->assertNotEmpty( $args['capabilities'] );

		foreach ( array( 'edit_posts', 'edit_others_posts', 'publish_posts', 'delete_posts', 'create_posts' ) as $capability ) {
			$this->assertSame( 'manage_options', $args['capabilities'][ $capability ], $capability );
		}

		$this->assertSame( array( 'manage_options' ), array_values( array_unique( $args['capabilities'] ) ) );
	}
}
