<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Admin page rendering tests.
 *
 * @package I18nly
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests admin page rendering.
 */
class AdminPageRenderTest extends TestCase {
	/**
	 * Registers a WordPress-style translations menu and submenus.
	 *
	 * @return void
	 */
	public function test_register_menu_registers_translations_menu_structure() {
		i18nly_test_reset_admin_menu_capture();

		$page = new \WP_I18nly\AdminPage();
		$page->register_menu();

		$menus    = i18nly_test_get_menu_pages();
		$submenus = i18nly_test_get_submenu_pages();

		$this->assertCount( 1, $menus );
		$this->assertSame( 'Translations', $menus[0]['menu_title'] );
		$this->assertSame( 'edit.php?post_type=i18nly_translation', $menus[0]['menu_slug'] );

		$this->assertCount( 2, $submenus );
		$this->assertSame( 'All translations', $submenus[0]['menu_title'] );
		$this->assertSame( 'edit.php?post_type=i18nly_translation', $submenus[0]['menu_slug'] );
		$this->assertSame( 'Add translation', $submenus[1]['menu_title'] );
		$this->assertSame( 'post-new.php?post_type=i18nly_translation', $submenus[1]['menu_slug'] );
	}

	/**
	 * Renders the all translations page for authorized users.
	 *
	 * @return void
	 */
	public function test_render_all_translations_page_redirects_to_native_list_screen() {
		i18nly_test_set_can_manage_options( true );
		i18nly_test_reset_last_redirect_url();

		$page = new \WP_I18nly\AdminPage();

		ob_start();
		$page->render_all_translations_page();
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertSame( '', $html );
		$this->assertSame(
			'https://example.test/wp-admin/edit.php?post_type=i18nly_translation',
			i18nly_test_get_last_redirect_url()
		);
	}

	/**
	 * Renders nothing on all translations page without capability.
	 *
	 * @return void
	 */
	public function test_render_all_translations_page_outputs_nothing_without_capability() {
		i18nly_test_set_can_manage_options( false );
		i18nly_test_reset_last_redirect_url();

		$page = new \WP_I18nly\AdminPage();

		ob_start();
		$page->render_all_translations_page();
		$html = ob_get_clean();

		$this->assertSame( '', $html );
		$this->assertSame( '', i18nly_test_get_last_redirect_url() );
	}

	/**
	 * Adds source and target columns to native translations list.
	 *
	 * @return void
	 */
	public function test_filter_translation_list_columns_adds_source_and_target_columns() {
		$page = new \WP_I18nly\AdminPage();

		$columns = $page->filter_translation_list_columns(
			array(
				'cb'    => '<input type="checkbox">',
				'title' => 'Title',
				'date'  => 'Date',
			)
		);

		$this->assertArrayHasKey( 'source_slug', $columns );
		$this->assertArrayHasKey( 'target_language', $columns );
		$this->assertSame( 'Source', $columns['source_slug'] );
		$this->assertSame( 'Target language', $columns['target_language'] );
	}

	/**
	 * Renders source and target values from post meta.
	 *
	 * @return void
	 */
	public function test_render_translation_list_column_outputs_meta_values() {
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'               => 42,
					'source_slug'      => 'akismet/akismet.php',
					'target_language'  => 'fr_FR',
					'created_at_gmt'   => '0000-00-00 00:00:00',
					'created_at_local' => '2026-03-02 11:15:00',
				),
			)
		);

		$page = new \WP_I18nly\AdminPage();

		ob_start();
		$page->render_translation_list_column( 'source_slug', 42 );
		$source = ob_get_clean();

		ob_start();
		$page->render_translation_list_column( 'target_language', 42 );
		$target = ob_get_clean();

		$this->assertSame( 'akismet/akismet.php', $source );
		$this->assertSame( 'fr_FR', $target );
	}

	/**
	 * Declares source and target as sortable columns.
	 *
	 * @return void
	 */
	public function test_filter_translation_sortable_columns_adds_source_and_target() {
		$page = new \WP_I18nly\AdminPage();

		$columns = $page->filter_translation_sortable_columns( array() );

		$this->assertSame( 'source_slug', $columns['source_slug'] );
		$this->assertSame( 'target_language', $columns['target_language'] );
	}

	/**
	 * Renders translation meta box fields on native editor screen.
	 *
	 * @return void
	 */
	public function test_render_translation_meta_box_outputs_plugin_and_language_selectors() {
		i18nly_test_set_plugins(
			array(
				'akismet/akismet.php' => array(
					'Name' => 'Akismet',
				),
			)
		);
		i18nly_test_set_available_languages(
			array(
				'fr_FR',
			)
		);
		i18nly_test_set_available_translations(
			array(
				'fr_FR' => array(
					'native_name' => 'Français',
				),
			)
		);
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'               => 42,
					'source_slug'      => 'akismet/akismet.php',
					'target_language'  => 'fr_FR',
					'created_at_gmt'   => '2026-03-02 00:00:00',
					'created_at_local' => '2026-03-02 00:00:00',
				),
			)
		);

		$page = new \WP_I18nly\AdminPage();

		ob_start();
		$page->render_translation_meta_box( (object) array( 'ID' => 42 ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'id="i18nly-plugin-selector"', $html );
		$this->assertStringContainsString( 'id="i18nly-target-language-selector"', $html );
		$this->assertStringContainsString( 'selected="selected"', $html );
		$this->assertStringContainsString( 'disabled="disabled"', $html );
	}

	/**
	 * Renders loading placeholder for source entries in translation meta box.
	 *
	 * @return void
	 */
	public function test_render_translation_meta_box_outputs_source_entries_loading_placeholder() {
		i18nly_test_set_plugins(
			array(
				'akismet/akismet.php' => array(
					'Name' => 'Akismet',
				),
			)
		);
		i18nly_test_set_available_languages( array( 'fr_FR' ) );
		i18nly_test_set_available_translations(
			array(
				'fr_FR' => array(
					'native_name' => 'Français',
				),
			)
		);
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'               => 42,
					'source_slug'      => 'akismet/akismet.php',
					'target_language'  => 'fr_FR',
					'created_at_gmt'   => '2026-03-02 00:00:00',
					'created_at_local' => '2026-03-02 00:00:00',
				),
			)
		);

		$page = new \WP_I18nly\AdminPage();

		ob_start();
		$page->render_translation_meta_box( (object) array( 'ID' => 42 ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'id="i18nly-source-entries-table"', $html );
		$this->assertStringContainsString( 'Loading translation entries…', $html );
	}

	/**
	 * Does not render entries block on creation mode.
	 *
	 * @return void
	 */
	public function test_render_translation_meta_box_hides_entries_block_on_creation_mode() {
		i18nly_test_set_plugins(
			array(
				'akismet/akismet.php' => array(
					'Name' => 'Akismet',
				),
			)
		);
		i18nly_test_set_available_languages( array( 'fr_FR' ) );
		i18nly_test_set_available_translations(
			array(
				'fr_FR' => array(
					'native_name' => 'Francais',
				),
			)
		);

		$page = new \WP_I18nly\AdminPage();

		ob_start();
		$page->render_translation_meta_box( (object) array( 'ID' => 777 ) );
		$html = ob_get_clean();

		$this->assertIsString( $html );
		$this->assertStringNotContainsString( 'id="i18nly-source-entries-table"', $html );
		$this->assertStringNotContainsString( 'Loading translation entries…', $html );
	}

	/**
	 * Saves meta and auto-generates title when current title is empty.
	 *
	 * @return void
	 */
	public function test_save_translation_meta_box_sets_meta_and_autogenerates_title_when_empty() {
		i18nly_test_set_can_manage_options( true );
		i18nly_test_reset_last_updated_post();

		$_POST['i18nly_translation_meta_box_nonce'] = 'nonce-i18nly_translation_meta_box';
		$_POST['i18nly_plugin_selector']            = 'akismet/akismet.php';
		$_POST['i18nly_target_language_selector']   = 'fr_FR';

		$page = new \WP_I18nly\AdminPage();
		$page->save_translation_meta_box(
			42,
			(object) array(
				'post_type'  => 'i18nly_translation',
				'post_title' => '',
			),
			false
		);

		$this->assertSame( 'akismet/akismet.php', get_post_meta( 42, '_i18nly_source_slug', true ) );
		$this->assertSame( 'fr_FR', get_post_meta( 42, '_i18nly_target_language', true ) );
		$this->assertSame(
			array(
				'ID'         => 42,
				'post_title' => 'akismet/akismet.php → fr_FR',
			),
			i18nly_test_get_last_updated_post()
		);

		unset( $_POST['i18nly_translation_meta_box_nonce'], $_POST['i18nly_plugin_selector'], $_POST['i18nly_target_language_selector'] );
	}

	/**
	 * Does not override existing title on save.
	 *
	 * @return void
	 */
	public function test_save_translation_meta_box_keeps_existing_title() {
		i18nly_test_set_can_manage_options( true );
		i18nly_test_reset_last_updated_post();

		$_POST['i18nly_translation_meta_box_nonce'] = 'nonce-i18nly_translation_meta_box';
		$_POST['i18nly_plugin_selector']            = 'akismet/akismet.php';
		$_POST['i18nly_target_language_selector']   = 'fr_FR';

		$page = new \WP_I18nly\AdminPage();
		$page->save_translation_meta_box(
			42,
			(object) array(
				'post_type'  => 'i18nly_translation',
				'post_title' => 'Manual title',
			),
			true
		);

		$this->assertSame( array(), i18nly_test_get_last_updated_post() );

		unset( $_POST['i18nly_translation_meta_box_nonce'], $_POST['i18nly_plugin_selector'], $_POST['i18nly_target_language_selector'] );
	}

	/**
	 * Does not allow plugin or language changes once set.
	 *
	 * @return void
	 */
	public function test_save_translation_meta_box_keeps_existing_source_and_language_on_edit() {
		i18nly_test_set_can_manage_options( true );
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'               => 42,
					'source_slug'      => 'akismet/akismet.php',
					'target_language'  => 'fr_FR',
					'created_at_gmt'   => '2026-03-02 00:00:00',
					'created_at_local' => '2026-03-02 00:00:00',
				),
			)
		);

		$_POST['i18nly_translation_meta_box_nonce'] = 'nonce-i18nly_translation_meta_box';
		$_POST['i18nly_plugin_selector']            = 'hello-dolly/hello.php';
		$_POST['i18nly_target_language_selector']   = 'de_DE';

		$page = new \WP_I18nly\AdminPage();
		$page->save_translation_meta_box(
			42,
			(object) array(
				'post_type'  => 'i18nly_translation',
				'post_title' => 'Manual title',
			),
			true
		);

		$this->assertSame( 'akismet/akismet.php', get_post_meta( 42, '_i18nly_source_slug', true ) );
		$this->assertSame( 'fr_FR', get_post_meta( 42, '_i18nly_target_language', true ) );

		unset( $_POST['i18nly_translation_meta_box_nonce'], $_POST['i18nly_plugin_selector'], $_POST['i18nly_target_language_selector'] );
	}

	/**
	 * Persists translation entry inputs sent from edit form.
	 *
	 * @return void
	 */
	public function test_save_translation_meta_box_passes_translation_entries_payload_to_persistence() {
		i18nly_test_set_can_manage_options( true );
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'               => 42,
					'source_slug'      => 'akismet/akismet.php',
					'target_language'  => 'fr_FR',
					'created_at_gmt'   => '2026-03-02 00:00:00',
					'created_at_local' => '2026-03-02 00:00:00',
				),
			)
		);

		$_POST['i18nly_translation_meta_box_nonce']  = 'nonce-i18nly_translation_meta_box';
		$_POST['i18nly_translation_entries_payload'] = '{"101":{"forms":{"0":"Bienvenue","1":"Bienvenues"}}}';

		$page = new class() extends \WP_I18nly\AdminPage {
			/**
			 * Captured save payload.
			 *
			 * @var array<string, mixed>
			 */
			public $captured_payload = array();

			/**
			 * Captures persistence arguments.
			 *
			 * @param int    $translation_id Translation ID.
			 * @param string $source_slug Source slug.
			 * @param array  $entries_payload Posted entries payload.
			 * @return void
			 */
			protected function persist_translation_entries( $translation_id, $source_slug, array $entries_payload ) {
				$this->captured_payload = array(
					'translation_id'  => (int) $translation_id,
					'source_slug'     => (string) $source_slug,
					'entries_payload' => $entries_payload,
				);
			}
		};

		$page->save_translation_meta_box(
			42,
			(object) array(
				'post_type'  => 'i18nly_translation',
				'post_title' => 'Manual title',
			),
			true
		);

		$this->assertSame( 42, $page->captured_payload['translation_id'] );
		$this->assertSame( 'akismet/akismet.php', $page->captured_payload['source_slug'] );
		$this->assertArrayHasKey( '101', $page->captured_payload['entries_payload'] );
		$this->assertArrayHasKey( 'forms', $page->captured_payload['entries_payload']['101'] );
		$this->assertSame( 'Bienvenue', $page->captured_payload['entries_payload']['101']['forms'][0] );
		$this->assertSame( 'Bienvenues', $page->captured_payload['entries_payload']['101']['forms'][1] );

		unset( $_POST['i18nly_translation_meta_box_nonce'], $_POST['i18nly_translation_entries_payload'] );
	}

	/**
	 * Accepts compact JSON payload for translation entries.
	 *
	 * @return void
	 */
	public function test_save_translation_meta_box_accepts_json_translation_entries_payload() {
		i18nly_test_set_can_manage_options( true );
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'               => 42,
					'source_slug'      => 'akismet/akismet.php',
					'target_language'  => 'fr_FR',
					'created_at_gmt'   => '2026-03-02 00:00:00',
					'created_at_local' => '2026-03-02 00:00:00',
				),
			)
		);

		$_POST['i18nly_translation_meta_box_nonce']  = 'nonce-i18nly_translation_meta_box';
		$_POST['i18nly_translation_entries_payload'] = '{"101":{"forms":{"0":"Bienvenue","1":"Bienvenues"}}}';

		$page = new class() extends \WP_I18nly\AdminPage {
			/**
			 * Captured save payload.
			 *
			 * @var array<string, mixed>
			 */
			public $captured_payload = array();

			/**
			 * Captures persistence arguments.
			 *
			 * @param int    $translation_id Translation ID.
			 * @param string $source_slug Source slug.
			 * @param array  $entries_payload Posted entries payload.
			 * @return void
			 */
			protected function persist_translation_entries( $translation_id, $source_slug, array $entries_payload ) {
				$this->captured_payload = array(
					'translation_id'  => (int) $translation_id,
					'source_slug'     => (string) $source_slug,
					'entries_payload' => $entries_payload,
				);
			}
		};

		$page->save_translation_meta_box(
			42,
			(object) array(
				'post_type'  => 'i18nly_translation',
				'post_title' => 'Manual title',
			),
			true
		);

		$this->assertSame( 42, $page->captured_payload['translation_id'] );
		$this->assertSame( 'akismet/akismet.php', $page->captured_payload['source_slug'] );
		$this->assertArrayHasKey( '101', $page->captured_payload['entries_payload'] );
		$this->assertArrayHasKey( 'forms', $page->captured_payload['entries_payload']['101'] );
		$this->assertSame( 'Bienvenue', $page->captured_payload['entries_payload']['101']['forms'][0] );
		$this->assertSame( 'Bienvenues', $page->captured_payload['entries_payload']['101']['forms'][1] );

		unset( $_POST['i18nly_translation_meta_box_nonce'], $_POST['i18nly_translation_entries_payload'] );
	}

	/**
	 * Prevents duplicate translation creation for same source and language.
	 *
	 * @return void
	 */
	public function test_save_translation_meta_box_prevents_duplicate_translation_creation() {
		i18nly_test_set_can_manage_options( true );
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'               => 100,
					'source_slug'      => 'akismet/akismet.php',
					'target_language'  => 'fr_FR',
					'created_at_gmt'   => '2026-03-02 00:00:00',
					'created_at_local' => '2026-03-02 00:00:00',
				),
			)
		);

		$_POST['i18nly_translation_meta_box_nonce'] = 'nonce-i18nly_translation_meta_box';
		$_POST['i18nly_plugin_selector']            = 'akismet/akismet.php';
		$_POST['i18nly_target_language_selector']   = 'fr_FR';

		$page = new class() extends \WP_I18nly\AdminPage {
			/**
			 * Captured duplicate payload.
			 *
			 * @var array<string, mixed>
			 */
			public $captured_duplicate = array();

			/**
			 * Captures duplicate handling call.
			 *
			 * @param int    $new_post_id New post ID.
			 * @param int    $existing_translation_id Existing translation ID.
			 * @param string $source_slug Source slug.
			 * @param string $target_language Target language.
			 * @return void
			 */
			protected function handle_duplicate_translation_creation( $new_post_id, $existing_translation_id, $source_slug, $target_language ) {
				$this->captured_duplicate = array(
					'new_post_id'             => (int) $new_post_id,
					'existing_translation_id' => (int) $existing_translation_id,
					'source_slug'             => (string) $source_slug,
					'target_language'         => (string) $target_language,
				);
			}
		};

		$page->save_translation_meta_box(
			42,
			(object) array(
				'post_type'  => 'i18nly_translation',
				'post_title' => '',
			),
			false
		);

		$this->assertSame( 42, $page->captured_duplicate['new_post_id'] );
		$this->assertSame( 100, $page->captured_duplicate['existing_translation_id'] );
		$this->assertSame( 'akismet/akismet.php', $page->captured_duplicate['source_slug'] );
		$this->assertSame( 'fr_FR', $page->captured_duplicate['target_language'] );

		unset( $_POST['i18nly_translation_meta_box_nonce'], $_POST['i18nly_plugin_selector'], $_POST['i18nly_target_language_selector'] );
	}

	/**
	 * Delegates translation meta box registration to edit controller.
	 *
	 * @return void
	 */
	public function test_register_translation_meta_box_delegates_to_translation_edit_controller() {
		$controller = new class() {
			/**
			 * Whether registration was delegated.
			 *
			 * @var bool
			 */
			public $called = false;

			/**
			 * Captured post type.
			 *
			 * @var string
			 */
			public $captured_post_type = '';

			/**
			 * Captures registration call.
			 *
			 * @param string   $post_type Translation post type.
			 * @param callable $render_callback Render callback.
			 * @return void
			 */
			public function register_translation_meta_box( $post_type, callable $render_callback ) {
				$this->called             = true;
				$this->captured_post_type = (string) $post_type;

				unset( $render_callback );
			}
		};

		$page = new class( $controller ) extends \WP_I18nly\AdminPage {
			/**
			 * Edit controller test double.
			 *
			 * @var object
			 */
			private $test_controller;

			/**
			 * Constructor.
			 *
			 * @param object $test_controller Edit controller test double.
			 */
			public function __construct( $test_controller ) {
				$this->test_controller = $test_controller;
			}

			/**
			 * Returns edit controller test double.
			 *
			 * @return object
			 */
			protected function get_translation_edit_controller() {
				return $this->test_controller;
			}
		};

		$page->register_translation_meta_box();

		$this->assertTrue( $controller->called );
		$this->assertSame( 'i18nly_translation', $controller->captured_post_type );
	}

	/**
	 * Delegates translation meta box rendering to edit controller.
	 *
	 * @return void
	 */
	public function test_render_translation_meta_box_delegates_to_translation_edit_controller() {
		$controller = new class() {
			/**
			 * Whether rendering was delegated.
			 *
			 * @var bool
			 */
			public $called = false;

			/**
			 * Captured render arguments.
			 *
			 * @var array<string, mixed>
			 */
			public $captured = array();

			/**
			 * Captures render delegation.
			 *
			 * @param object $post Current post object.
			 * @param string $meta_source_key Source key.
			 * @param string $meta_target_key Target key.
			 * @return void
			 */
			public function handle_render_translation_meta_box( $post, $meta_source_key, $meta_target_key ) {
				$this->called   = true;
				$this->captured = array(
					'post_id'         => isset( $post->ID ) ? (int) $post->ID : 0,
					'meta_source_key' => (string) $meta_source_key,
					'meta_target_key' => (string) $meta_target_key,
				);
			}
		};

		$page = new class( $controller ) extends \WP_I18nly\AdminPage {
			/**
			 * Edit controller test double.
			 *
			 * @var object
			 */
			private $test_controller;

			/**
			 * Constructor.
			 *
			 * @param object $test_controller Edit controller test double.
			 */
			public function __construct( $test_controller ) {
				$this->test_controller = $test_controller;
			}

			/**
			 * Returns edit controller test double.
			 *
			 * @return object
			 */
			protected function get_translation_edit_controller() {
				return $this->test_controller;
			}
		};

		$page->render_translation_meta_box( (object) array( 'ID' => 42 ) );

		$this->assertTrue( $controller->called );
		$this->assertSame( 42, $controller->captured['post_id'] );
		$this->assertSame( '_i18nly_source_slug', $controller->captured['meta_source_key'] );
		$this->assertSame( '_i18nly_target_language', $controller->captured['meta_target_key'] );
	}

	/**
	 * Delegates save handling to translation edit controller.
	 *
	 * @return void
	 */
	public function test_save_translation_meta_box_delegates_to_translation_edit_controller() {
		$controller = new class() {
			/**
			 * Whether save was delegated.
			 *
			 * @var bool
			 */
			public $called = false;

			/**
			 * Captured arguments.
			 *
			 * @var array<string, mixed>
			 */
			public $captured = array();

			/**
			 * Captures delegated save call.
			 *
			 * @param int    $post_id Post ID.
			 * @param object $post Post object.
			 * @param bool   $update Update flag.
			 * @return void
			 */
			public function handle_save_translation_meta_box( $post_id, $post, $update ) {
				$this->called   = true;
				$this->captured = array(
					'post_id' => (int) $post_id,
					'update'  => (bool) $update,
					'type'    => isset( $post->post_type ) ? (string) $post->post_type : '',
				);
			}
		};

		$page = new class( $controller ) extends \WP_I18nly\AdminPage {
			/**
			 * Edit controller test double.
			 *
			 * @var object
			 */
			private $test_controller;

			/**
			 * Constructor.
			 *
			 * @param object $test_controller Edit controller test double.
			 */
			public function __construct( $test_controller ) {
				$this->test_controller = $test_controller;
			}

			/**
			 * Returns edit controller test double.
			 *
			 * @return object
			 */
			protected function get_translation_edit_controller() {
				return $this->test_controller;
			}
		};

		$page->save_translation_meta_box(
			42,
			(object) array(
				'post_type' => 'i18nly_translation',
			),
			true
		);

		$this->assertTrue( $controller->called );
		$this->assertSame( 42, $controller->captured['post_id'] );
		$this->assertSame( true, $controller->captured['update'] );
		$this->assertSame( 'i18nly_translation', $controller->captured['type'] );
	}

	/**
	 * Delegates POT script rendering to translation edit controller.
	 *
	 * @return void
	 */
	public function test_render_translation_edit_pot_generation_script_delegates_to_translation_edit_controller() {
		$controller = new class() {
			/**
			 * Whether assets rendering was delegated.
			 *
			 * @var bool
			 */
			public $called = false;

			/**
			 * Captured arguments.
			 *
			 * @var array<string, string>
			 */
			public $captured = array();

			/**
			 * Captures delegated script render call.
			 *
			 * @param string $hook_suffix Current hook suffix.
			 * @param string $asset_version Asset version.
			 * @return void
			 */
			public function render_translation_edit_pot_generation_script( $hook_suffix, $asset_version ) {
				$this->called   = true;
				$this->captured = array(
					'hook_suffix'   => (string) $hook_suffix,
					'asset_version' => (string) $asset_version,
				);
			}
		};

		$page = new class( $controller ) extends \WP_I18nly\AdminPage {
			/**
			 * Edit controller test double.
			 *
			 * @var object
			 */
			private $test_controller;

			/**
			 * Constructor.
			 *
			 * @param object $test_controller Edit controller test double.
			 */
			public function __construct( $test_controller ) {
				$this->test_controller = $test_controller;
			}

			/**
			 * Returns edit controller test double.
			 *
			 * @return object
			 */
			protected function get_translation_edit_controller() {
				return $this->test_controller;
			}
		};

		$page->render_translation_edit_pot_generation_script( 'post.php' );

		$this->assertTrue( $controller->called );
		$this->assertSame( 'post.php', $controller->captured['hook_suffix'] );
		$this->assertSame( '0.1.0', $controller->captured['asset_version'] );
	}

	/**
	 * Delegates translation save flow to dedicated save handler.
	 *
	 * @return void
	 */
	public function test_save_translation_meta_box_delegates_to_save_handler() {
		$handler = new class() {
			/**
			 * Whether handler was called.
			 *
			 * @var bool
			 */
			public $called = false;

			/**
			 * Captured arguments.
			 *
			 * @var array<string, mixed>
			 */
			public $captured = array();

			/**
			 * Captures save call.
			 *
			 * @param int    $post_id Post ID.
			 * @param object $post Post object.
			 * @param bool   $update Update flag.
			 * @return void
			 */
			public function handle_save( $post_id, $post, $update ) {
				$this->called   = true;
				$this->captured = array(
					'post_id' => (int) $post_id,
					'update'  => (bool) $update,
					'type'    => isset( $post->post_type ) ? (string) $post->post_type : '',
				);
			}
		};

		$page = new class( $handler ) extends \WP_I18nly\AdminPage {
			/**
			 * Save handler test double.
			 *
			 * @var object
			 */
			private $test_handler;

			/**
			 * Constructor.
			 *
			 * @param object $test_handler Save handler test double.
			 */
			public function __construct( $test_handler ) {
				$this->test_handler = $test_handler;
			}

			/**
			 * Returns save handler.
			 *
			 * @return object
			 */
			protected function get_save_handler() {
				return $this->test_handler;
			}
		};

		$page->save_translation_meta_box(
			42,
			(object) array(
				'post_type' => 'i18nly_translation',
			),
			true
		);

		$this->assertTrue( $handler->called );
		$this->assertSame( 42, $handler->captured['post_id'] );
		$this->assertSame( true, $handler->captured['update'] );
		$this->assertSame( 'i18nly_translation', $handler->captured['type'] );
	}

	/**
	 * Delegates POT generation AJAX action to dedicated controller.
	 *
	 * @return void
	 */
	public function test_ajax_generate_translation_pot_delegates_to_ajax_controller() {
		$controller = new class() {
			/**
			 * Whether POT action was delegated.
			 *
			 * @var bool
			 */
			public $called_generate = false;

			/**
			 * Whether entries table action was delegated.
			 *
			 * @var bool
			 */
			public $called_table = false;

			/**
			 * Captures POT generation action call.
			 *
			 * @return void
			 */
			public function handle_generate_translation_pot() {
				$this->called_generate = true;
			}

			/**
			 * Captures entries table action call.
			 *
			 * @return void
			 */
			public function handle_get_translation_entries_table() {
				$this->called_table = true;
			}
		};

		$page = new class( $controller ) extends \WP_I18nly\AdminPage {
			/**
			 * AJAX controller test double.
			 *
			 * @var object
			 */
			private $test_controller;

			/**
			 * Constructor.
			 *
			 * @param object $test_controller AJAX controller test double.
			 */
			public function __construct( $test_controller ) {
				$this->test_controller = $test_controller;
			}

			/**
			 * Returns AJAX controller.
			 *
			 * @return object
			 */
			protected function get_ajax_controller() {
				return $this->test_controller;
			}
		};

		$page->ajax_generate_translation_pot();

		$this->assertTrue( $controller->called_generate );
		$this->assertFalse( $controller->called_table );
	}

	/**
	 * Delegates entries table AJAX action to dedicated controller.
	 *
	 * @return void
	 */
	public function test_ajax_get_translation_entries_table_delegates_to_ajax_controller() {
		$controller = new class() {
			/**
			 * Whether POT action was delegated.
			 *
			 * @var bool
			 */
			public $called_generate = false;

			/**
			 * Whether entries table action was delegated.
			 *
			 * @var bool
			 */
			public $called_table = false;

			/**
			 * Captures POT generation action call.
			 *
			 * @return void
			 */
			public function handle_generate_translation_pot() {
				$this->called_generate = true;
			}

			/**
			 * Captures entries table action call.
			 *
			 * @return void
			 */
			public function handle_get_translation_entries_table() {
				$this->called_table = true;
			}
		};

		$page = new class( $controller ) extends \WP_I18nly\AdminPage {
			/**
			 * AJAX controller test double.
			 *
			 * @var object
			 */
			private $test_controller;

			/**
			 * Constructor.
			 *
			 * @param object $test_controller AJAX controller test double.
			 */
			public function __construct( $test_controller ) {
				$this->test_controller = $test_controller;
			}

			/**
			 * Returns AJAX controller.
			 *
			 * @return object
			 */
			protected function get_ajax_controller() {
				return $this->test_controller;
			}
		};

		$page->ajax_get_translation_entries_table();

		$this->assertFalse( $controller->called_generate );
		$this->assertTrue( $controller->called_table );
	}

	/**
	 * Delegates translation meta box rendering to dedicated renderer.
	 *
	 * @return void
	 */
	public function test_render_translation_meta_box_delegates_to_meta_box_renderer() {
		i18nly_test_set_plugins(
			array(
				'akismet/akismet.php' => array(
					'Name' => 'Akismet',
				),
			)
		);
		i18nly_test_set_available_languages( array( 'fr_FR' ) );
		i18nly_test_set_available_translations(
			array(
				'fr_FR' => array(
					'native_name' => 'Francais',
				),
			)
		);
		i18nly_test_set_translations_rows(
			array(
				array(
					'id'               => 42,
					'source_slug'      => 'akismet/akismet.php',
					'target_language'  => 'fr_FR',
					'created_at_gmt'   => '2026-03-02 00:00:00',
					'created_at_local' => '2026-03-02 00:00:00',
				),
			)
		);

		$renderer = new class() {
			/**
			 * Whether render was called.
			 *
			 * @var bool
			 */
			public $called = false;

			/**
			 * Captured render payload.
			 *
			 * @var array<string, mixed>
			 */
			public $captured = array();

			/**
			 * Captures meta box rendering call.
			 *
			 * @param array<string, string>                                           $plugin_options Plugin options.
			 * @param array<int, array{value: string, label: string, disabled: bool}> $target_languages Target language options.
			 * @param string                                                          $selected_source Selected source.
			 * @param string                                                          $selected_language Selected language.
			 * @param bool                                                            $is_locked Lock flag.
			 * @return void
			 */
			public function render_translation_meta_box( array $plugin_options, array $target_languages, $selected_source, $selected_language, $is_locked ) {
				$this->called   = true;
				$this->captured = array(
					'plugin_options'    => $plugin_options,
					'target_languages'  => $target_languages,
					'selected_source'   => (string) $selected_source,
					'selected_language' => (string) $selected_language,
					'is_locked'         => (bool) $is_locked,
				);
			}

			/**
			 * Returns placeholder table markup in test double.
			 *
			 * @param array<int, array<string, mixed>> $source_entries Source entries.
			 * @return string
			 */
			public function render_source_entries_table_markup( array $source_entries ) {
				unset( $source_entries );

				return '';
			}
		};

		$page = new class( $renderer ) extends \WP_I18nly\AdminPage {
			/**
			 * Renderer test double.
			 *
			 * @var object
			 */
			private $test_renderer;

			/**
			 * Constructor.
			 *
			 * @param object $test_renderer Renderer test double.
			 */
			public function __construct( $test_renderer ) {
				$this->test_renderer = $test_renderer;
			}

			/**
			 * Returns renderer test double.
			 *
			 * @return object
			 */
			protected function get_meta_box_renderer() {
				return $this->test_renderer;
			}
		};

		$page->render_translation_meta_box( (object) array( 'ID' => 42 ) );

		$this->assertTrue( $renderer->called );
		$this->assertArrayHasKey( 'akismet/akismet.php', $renderer->captured['plugin_options'] );
		$this->assertSame( 'akismet/akismet.php', $renderer->captured['selected_source'] );
		$this->assertSame( 'fr_FR', $renderer->captured['selected_language'] );
		$this->assertSame( true, $renderer->captured['is_locked'] );
	}

	/**
	 * Maps source sort to source meta key.
	 *
	 * @return void
	 */
	public function test_apply_translation_sorting_maps_source_slug_to_meta_ordering() {
		$page  = new \WP_I18nly\AdminPage();
		$query = new class() {
			/**
			 * Query vars.
			 *
			 * @var array<string, mixed>
			 */
			private $vars = array(
				'post_type' => 'i18nly_translation',
				'orderby'   => 'source_slug',
			);

			/**
			 * Gets one query var.
			 *
			 * @param string $key Var key.
			 * @return mixed
			 */
			public function get( $key ) {
				return isset( $this->vars[ $key ] ) ? $this->vars[ $key ] : null;
			}

			/**
			 * Sets one query var.
			 *
			 * @param string $key Var key.
			 * @param mixed  $value Var value.
			 * @return void
			 */
			public function set( $key, $value ) {
				$this->vars[ $key ] = $value;
			}

			/**
			 * Returns whether this is main query.
			 *
			 * @return bool
			 */
			public function is_main_query() {
				return true;
			}
		};

		$page->apply_translation_sorting( $query );

		$this->assertSame( '_i18nly_source_slug', $query->get( 'meta_key' ) );
		$this->assertSame( 'meta_value', $query->get( 'orderby' ) );
	}

	/**
	 * Maps target sort to target meta key.
	 *
	 * @return void
	 */
	public function test_apply_translation_sorting_maps_target_language_to_meta_ordering() {
		$page  = new \WP_I18nly\AdminPage();
		$query = new class() {
			/**
			 * Query vars.
			 *
			 * @var array<string, mixed>
			 */
			private $vars = array(
				'post_type' => 'i18nly_translation',
				'orderby'   => 'target_language',
			);

			/**
			 * Gets one query var.
			 *
			 * @param string $key Var key.
			 * @return mixed
			 */
			public function get( $key ) {
				return isset( $this->vars[ $key ] ) ? $this->vars[ $key ] : null;
			}

			/**
			 * Sets one query var.
			 *
			 * @param string $key Var key.
			 * @param mixed  $value Var value.
			 * @return void
			 */
			public function set( $key, $value ) {
				$this->vars[ $key ] = $value;
			}

			/**
			 * Returns whether this is main query.
			 *
			 * @return bool
			 */
			public function is_main_query() {
				return true;
			}
		};

		$page->apply_translation_sorting( $query );

		$this->assertSame( '_i18nly_target_language', $query->get( 'meta_key' ) );
		$this->assertSame( 'meta_value', $query->get( 'orderby' ) );
	}
}
