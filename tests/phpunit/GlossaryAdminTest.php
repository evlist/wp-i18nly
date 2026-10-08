<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Glossary administration tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;
use WP_I18nly\Admin\Glossary\GlossaryAdminController;
use WP_I18nly\Admin\Glossary\GlossaryScreen;
use WP_I18nly\LinguisticResources\GlossaryResourceRepository;
use WP_I18nly\Storage\SourceSchemaManager;
use WP_I18nly\Storage\SourceWpdbRepository;

/**
 * Tests the glossary screens and form handlers on the in-memory storage.
 */
class GlossaryAdminTest extends TestCase {
	/**
	 * Repository.
	 *
	 * @var GlossaryResourceRepository
	 */
	private $repository;

	/**
	 * Controller whose redirections throw.
	 *
	 * @var GlossaryAdminController
	 */
	private $controller;

	/**
	 * Builds the repository and the controller, and resets the request.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		global $i18nly_test_transients;

		$wpdb_double = new I18nly_Test_InMemory_Wpdb();
		$manager     = new SourceSchemaManager( $wpdb_double );

		$this->repository = new GlossaryResourceRepository( new SourceWpdbRepository( $manager, $wpdb_double ), $manager );
		$this->controller = new class( $this->repository ) extends GlossaryAdminController {
			/**
			 * Throws the target URL instead of redirecting.
			 *
			 * @param string $url URL.
			 * @return void
			 * @throws RuntimeException Always.
			 */
			protected function redirect( $url ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test double.
				throw new RuntimeException( 'redirect:' . $url );
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		};

		$i18nly_test_transients = array();
		$_POST                  = array();
		$_GET                   = array();

		i18nly_test_set_can_manage_options( true );
	}

	/**
	 * Clears the request.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_POST = array();
		$_GET  = array();
	}

	/**
	 * Runs a handler and returns the URL it redirects to.
	 *
	 * @param string               $handler Method name.
	 * @param string               $action Form action (for the nonce).
	 * @param int                  $glossary_id Glossary ID used by the nonce.
	 * @param array<string, mixed> $fields Posted fields.
	 * @return string
	 */
	private function submit( $handler, $action, $glossary_id, array $fields ) {
		$_POST = $fields + array( '_wpnonce' => 'nonce-' . GlossaryAdminController::get_nonce_action( $action, $glossary_id ) );

		try {
			$this->controller->$handler();
		} catch ( RuntimeException $exception ) {
			return substr( $exception->getMessage(), strlen( 'redirect:' ) );
		}

		$this->fail( 'The handler should have redirected.' );
	}

	/**
	 * Creates a glossary and goes to its screen.
	 *
	 * @return void
	 */
	public function test_creating_a_glossary() {
		$url = $this->submit(
			'handle_create',
			GlossaryAdminController::ACTION_CREATE,
			0,
			array(
				'slug'          => 'Brand-Terms',
				'source_locale' => 'en_US',
				'target_locale' => 'fr_FR',
			)
		);

		$glossaries = $this->repository->list_glossaries();

		$this->assertCount( 1, $glossaries );
		$this->assertSame( 'brand-terms', $glossaries[0]['slug'] );
		$this->assertStringContainsString( 'glossary=' . $glossaries[0]['id'], $url );
		$this->assertStringContainsString( 'i18nly_glossary_notice=glossary_created', $url );
	}

	/**
	 * Invalid input creates nothing, and the form is shown again with the messages and the values.
	 *
	 * @return void
	 */
	public function test_invalid_glossary_is_refused_and_remembered() {
		$this->submit(
			'handle_create',
			GlossaryAdminController::ACTION_CREATE,
			0,
			array(
				'slug'          => 'Not valid!',
				'source_locale' => 'en_US',
				'target_locale' => 'en_US',
			)
		);

		$this->assertSame( array(), $this->repository->list_glossaries() );

		ob_start();
		( new GlossaryScreen( $this->controller ) )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'value="Not valid!"', $html );
	}

	/**
	 * A term is saved with its alternatives, shown in the table, edited, and deleted.
	 *
	 * @return void
	 */
	public function test_term_life_cycle() {
		$glossary_id = $this->repository->create_glossary( 'brand-terms', 'en_US', 'fr_FR' )->get_id();

		$this->submit(
			'handle_save_term',
			GlossaryAdminController::ACTION_SAVE_TERM,
			$glossary_id,
			array(
				'glossary_id' => (string) $glossary_id,
				'term_id'     => '0',
				'term'        => 'Cart',
				'match_mode'  => 'exact',
				'preferred'   => 'Panier',
				'alternates'  => "Caddie\n\n  Chariot  \n",
				'note'        => 'Shop <b>basket</b>',
			)
		);

		$entries = $this->repository->get_glossary( $glossary_id )->get_entries();
		$term_id = $entries[0]->get_source_entry_id();

		$this->assertCount( 1, $entries );
		$this->assertSame( 'Panier', $entries[0]->get_preferred_target()->get_text() );
		$this->assertSame(
			array( 'Caddie', 'Chariot' ),
			array_map(
				static function ( $target ) {
					return $target->get_text();
				},
				$entries[0]->get_alternate_targets()
			)
		);

		$_GET['glossary'] = (string) $glossary_id;

		ob_start();
		( new GlossaryScreen( $this->controller ) )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( '<td>Cart</td>', $html );
		$this->assertStringContainsString( 'Caddie | Chariot', $html );
		$this->assertStringContainsString( 'Shop &lt;b&gt;basket&lt;/b&gt;', $html );

		$this->submit(
			'handle_save_term',
			GlossaryAdminController::ACTION_SAVE_TERM,
			$glossary_id,
			array(
				'glossary_id' => (string) $glossary_id,
				'term_id'     => (string) $term_id,
				'term'        => 'Cart',
				'match_mode'  => 'partial',
				'preferred'   => 'Chariot',
				'alternates'  => '',
				'note'        => '',
			)
		);

		$updated = $this->repository->get_glossary( $glossary_id )->get_entries();

		$this->assertCount( 1, $updated );
		$this->assertSame( 'partial', $updated[0]->get_match_mode() );

		$this->submit(
			'handle_delete_term',
			GlossaryAdminController::ACTION_DELETE_TERM,
			$glossary_id,
			array(
				'glossary_id' => (string) $glossary_id,
				'term_id' => (string) $term_id,
			)
		);

		$this->assertSame( array(), $this->repository->get_glossary( $glossary_id )->get_entries() );
	}

	/**
	 * A duplicate term is refused and the form shows the message with the typed values.
	 *
	 * @return void
	 */
	public function test_invalid_term_is_refused_and_remembered() {
		$glossary_id = $this->repository->create_glossary( 'brand-terms', 'en_US', 'fr_FR' )->get_id();

		$fields = array(
			'glossary_id' => (string) $glossary_id,
			'term_id'     => '0',
			'term'        => 'Cart',
			'match_mode'  => 'exact',
			'preferred'   => 'Panier',
			'alternates'  => '',
			'note'        => '',
		);

		$this->submit( 'handle_save_term', GlossaryAdminController::ACTION_SAVE_TERM, $glossary_id, $fields );
		$this->submit(
			'handle_save_term',
			GlossaryAdminController::ACTION_SAVE_TERM,
			$glossary_id,
			array(
				'term' => 'cart',
				'preferred' => 'Autre',
			) + $fields
		);

		$_GET['glossary'] = (string) $glossary_id;

		ob_start();
		( new GlossaryScreen( $this->controller ) )->render();
		$html = ob_get_clean();

		$this->assertCount( 1, $this->repository->get_glossary( $glossary_id )->get_entries() );
		$this->assertStringContainsString( 'This term already exists', $html );
		$this->assertStringContainsString( 'value="Autre"', $html );
	}

	/**
	 * Deleting a glossary removes it.
	 *
	 * @return void
	 */
	public function test_deleting_a_glossary() {
		$glossary_id = $this->repository->create_glossary( 'brand-terms', 'en_US', 'fr_FR' )->get_id();

		$url = $this->submit( 'handle_delete', GlossaryAdminController::ACTION_DELETE, $glossary_id, array( 'glossary_id' => (string) $glossary_id ) );

		$this->assertSame( array(), $this->repository->list_glossaries() );
		$this->assertStringContainsString( 'glossary_deleted', $url );
	}

	/**
	 * Requests without capability or with a wrong nonce change nothing.
	 *
	 * @return void
	 */
	public function test_requests_need_the_capability_and_a_valid_nonce() {
		$fields = array(
			'slug'          => 'brand-terms',
			'source_locale' => 'en_US',
			'target_locale' => 'fr_FR',
		);

		$_POST = $fields + array( '_wpnonce' => 'wrong' );

		try {
			$this->controller->handle_create();
			$this->fail( 'A wrong nonce should be refused.' );
		} catch ( RuntimeException $exception ) {
			$this->assertStringStartsWith( 'wp_die:400', $exception->getMessage() );
		}

		i18nly_test_set_can_manage_options( false );

		$_POST = $fields + array( '_wpnonce' => 'nonce-' . GlossaryAdminController::get_nonce_action( GlossaryAdminController::ACTION_CREATE ) );

		try {
			$this->controller->handle_create();
			$this->fail( 'A user without capability should be refused.' );
		} catch ( RuntimeException $exception ) {
			$this->assertStringStartsWith( 'wp_die:403', $exception->getMessage() );
		}

		$this->assertSame( array(), $this->repository->list_glossaries() );
	}

	/**
	 * Nothing is drawn without capability.
	 *
	 * @return void
	 */
	public function test_screen_is_empty_without_capability() {
		i18nly_test_set_can_manage_options( false );

		ob_start();
		( new GlossaryScreen( $this->controller ) )->render();

		$this->assertSame( '', ob_get_clean() );
	}
}
