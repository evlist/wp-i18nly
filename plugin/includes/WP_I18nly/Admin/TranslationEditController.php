<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Translation edit screen controller.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates translation edit screen behavior.
 */
class TranslationEditController {
	/**
	 * Callback returning plugin options.
	 *
	 * @var callable
	 */
	private $plugin_options_reader;

	/**
	 * Callback returning target language options.
	 *
	 * @var callable
	 */
	private $target_language_options_reader;

	/**
	 * Callback returning meta box renderer instance.
	 *
	 * @var callable
	 */
	private $meta_box_renderer_provider;

	/**
	 * Callback returning save handler instance.
	 *
	 * @var callable
	 */
	private $save_handler_provider;

	/**
	 * Callback returning translation id from current edit request.
	 *
	 * @var callable
	 */
	private $current_edit_translation_id_reader;

	/**
	 * Callback returning translation edit script URL.
	 *
	 * @var callable
	 */
	private $script_url_provider;

	/**
	 * Callback returning translation edit style URL.
	 *
	 * @var callable
	 */
	private $style_url_provider;

	/**
	 * Callback building translation edit script config.
	 *
	 * @var callable
	 */
	private $script_config_builder;

	/**
	 * Callback returning translation AJAX controller.
	 *
	 * @var callable
	 */
	private $ajax_controller_provider;

	/**
	 * Constructor.
	 *
	 * @param callable $plugin_options_reader Callback returning plugin options.
	 * @param callable $target_language_options_reader Callback returning target language options.
	 * @param callable $meta_box_renderer_provider Callback returning meta box renderer.
	 * @param callable $save_handler_provider Callback returning save handler.
	 * @param callable $current_edit_translation_id_reader Callback returning edit translation id.
	 * @param callable $script_url_provider Callback returning script URL.
	 * @param callable $style_url_provider Callback returning style URL.
	 * @param callable $script_config_builder Callback building script config.
	 * @param callable $ajax_controller_provider Callback returning AJAX controller.
	 */
	public function __construct(
		callable $plugin_options_reader,
		callable $target_language_options_reader,
		callable $meta_box_renderer_provider,
		callable $save_handler_provider,
		callable $current_edit_translation_id_reader,
		callable $script_url_provider,
		callable $style_url_provider,
		callable $script_config_builder,
		callable $ajax_controller_provider
	) {
		$this->plugin_options_reader              = $plugin_options_reader;
		$this->target_language_options_reader     = $target_language_options_reader;
		$this->meta_box_renderer_provider         = $meta_box_renderer_provider;
		$this->save_handler_provider              = $save_handler_provider;
		$this->current_edit_translation_id_reader = $current_edit_translation_id_reader;
		$this->script_url_provider                = $script_url_provider;
		$this->style_url_provider                 = $style_url_provider;
		$this->script_config_builder              = $script_config_builder;
		$this->ajax_controller_provider           = $ajax_controller_provider;
	}

	/**
	 * Registers translation meta box on native editor screens.
	 *
	 * @param string   $post_type Translation post type.
	 * @param callable $render_callback Callback rendering the meta box.
	 * @return void
	 */
	public function register_translation_meta_box( $post_type, callable $render_callback ) {
		add_meta_box(
			'i18nly-translation-settings',
			__( 'Translation', 'i18nly' ),
			$render_callback,
			(string) $post_type,
			'normal',
			'high'
		);
	}

	/**
	 * Renders translation meta box fields.
	 *
	 * @param object $post Current post object.
	 * @param string $meta_source_key Source slug post meta key.
	 * @param string $meta_target_key Target language post meta key.
	 * @return void
	 */
	public function handle_render_translation_meta_box( $post, $meta_source_key, $meta_target_key ) {
		$plugin_options    = call_user_func( $this->plugin_options_reader );
		$target_languages  = call_user_func( $this->target_language_options_reader );
		$selected_source   = (string) get_post_meta( (int) $post->ID, (string) $meta_source_key, true );
		$selected_language = (string) get_post_meta( (int) $post->ID, (string) $meta_target_key, true );
		$is_locked         = '' !== $selected_source || '' !== $selected_language;

		$renderer = call_user_func( $this->meta_box_renderer_provider );
		if ( ! is_object( $renderer ) || ! method_exists( $renderer, 'render_translation_meta_box' ) ) {
			return;
		}

		$renderer->render_translation_meta_box(
			is_array( $plugin_options ) ? $plugin_options : array(),
			is_array( $target_languages ) ? $target_languages : array(),
			$selected_source,
			$selected_language,
			$is_locked
		);
	}

	/**
	 * Saves translation fields from native post editor.
	 *
	 * @param int    $post_id Post ID.
	 * @param object $post Current post object.
	 * @param bool   $update Whether this is an update.
	 * @return void
	 */
	public function handle_save_translation_meta_box( $post_id, $post, $update ) {
		$save_handler = call_user_func( $this->save_handler_provider );

		if ( ! is_object( $save_handler ) || ! method_exists( $save_handler, 'handle_save' ) ) {
			return;
		}

		$save_handler->handle_save( (int) $post_id, $post, (bool) $update );
	}

	/**
	 * Renders a tiny script that triggers POT generation on edit screen open.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @param string $asset_version Version for script and style cache busting.
	 * @return void
	 */
	public function render_translation_edit_pot_generation_script( $hook_suffix, $asset_version ) {
		if ( '' !== (string) $hook_suffix && 'post.php' !== (string) $hook_suffix ) {
			return;
		}

		$translation_id = (int) call_user_func( $this->current_edit_translation_id_reader );
		if ( $translation_id <= 0 ) {
			return;
		}

		$script_handle = 'i18nly-translation-edit';
		$style_handle  = 'i18nly-translation-edit-style';

		wp_enqueue_style(
			$style_handle,
			(string) call_user_func( $this->style_url_provider ),
			array(),
			(string) $asset_version
		);

		wp_enqueue_script(
			$script_handle,
			(string) call_user_func( $this->script_url_provider ),
			array(),
			(string) $asset_version,
			true
		);

		$config_json = wp_json_encode( call_user_func( $this->script_config_builder, $translation_id ) );
		if ( false === $config_json ) {
			return;
		}

		wp_add_inline_script(
			$script_handle,
			'window.i18nlyTranslationEditConfig = ' . $config_json . ';',
			'before'
		);
	}

	/**
	 * Handles AJAX request to generate temporary POT for one translation.
	 *
	 * @return void
	 */
	public function ajax_generate_translation_pot() {
		$controller = call_user_func( $this->ajax_controller_provider );
		if ( is_object( $controller ) && method_exists( $controller, 'handle_generate_translation_pot' ) ) {
			$controller->handle_generate_translation_pot();
		}
	}

	/**
	 * Handles AJAX request to fetch source entries table HTML.
	 *
	 * @return void
	 */
	public function ajax_get_translation_entries_table() {
		$controller = call_user_func( $this->ajax_controller_provider );
		if ( is_object( $controller ) && method_exists( $controller, 'handle_get_translation_entries_table' ) ) {
			$controller->handle_get_translation_entries_table();
		}
	}
}
