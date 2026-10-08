<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * DeepL usage widgets.
 *
 * @package I18nly
 */

namespace WP_I18nly\Admin;

use WP_I18nly\Admin\UI\DeepLUsageGaugeRenderer;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the DeepL monthly usage gauge on the dashboard and on the translation edit screen.
 *
 * @psalm-suppress PossiblyUnusedMethod Methods are wired as WordPress hook callbacks.
 */
class DeepLUsageWidgets {
	/**
	 * Usage status provider.
	 *
	 * @var object|null
	 */
	private $status_provider;

	/**
	 * Gauge renderer.
	 *
	 * @var DeepLUsageGaugeRenderer|null
	 */
	private $renderer;

	/**
	 * Translation post type hosting the meta box.
	 *
	 * @var string
	 */
	private $post_type = '';

	/**
	 * Constructor.
	 *
	 * @param object|null                  $status_provider Optional provider exposing get_status().
	 * @param DeepLUsageGaugeRenderer|null $renderer Optional gauge renderer.
	 */
	public function __construct( $status_provider = null, DeepLUsageGaugeRenderer $renderer = null ) {
		$this->status_provider = $status_provider;
		$this->renderer        = $renderer;
	}

	/**
	 * Registers the dashboard widget and the edit-screen meta box hooks.
	 *
	 * @param string $post_type Translation post type.
	 * @return void
	 */
	public function register( $post_type ) {
		$this->post_type = (string) $post_type;

		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		add_action( 'add_meta_boxes_' . $this->post_type, array( $this, 'register_meta_box' ) );
	}

	/**
	 * Registers the usage widget on the dashboard.
	 *
	 * @return void
	 */
	public function register_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! function_exists( 'wp_add_dashboard_widget' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'i18nly_deepl_monthly_usage',
			esc_html__( 'DeepL monthly usage', 'i18nly' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Renders the dashboard widget content.
	 *
	 * @return void
	 */
	public function render_dashboard_widget() {
		$this->render_gauge();
	}

	/**
	 * Registers the usage meta box on the translation edit screen.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		add_meta_box(
			'i18nly-deepl-monthly-usage',
			esc_html__( 'DeepL monthly usage', 'i18nly' ),
			array( $this, 'render_meta_box' ),
			$this->post_type,
			'side',
			'high'
		);
	}

	/**
	 * Renders the meta box content.
	 *
	 * @return void
	 */
	public function render_meta_box() {
		$this->render_gauge();
	}

	/**
	 * Renders the reusable usage gauge.
	 *
	 * @return void
	 */
	private function render_gauge() {
		$status = $this->get_status_provider()->get_status();

		$this->get_renderer()->render(
			is_array( $status ) ? $status : array(),
			esc_html__( 'DeepL monthly usage', 'i18nly' )
		);
	}

	/**
	 * Returns the usage status provider.
	 *
	 * @return object
	 */
	private function get_status_provider() {
		if ( null === $this->status_provider ) {
			$this->status_provider = ( new DeepLUsageFactory() )->create_status_provider();
		}

		return $this->status_provider;
	}

	/**
	 * Returns the gauge renderer.
	 *
	 * @return DeepLUsageGaugeRenderer
	 */
	private function get_renderer() {
		if ( ! $this->renderer instanceof DeepLUsageGaugeRenderer ) {
			$this->renderer = new DeepLUsageGaugeRenderer();
		}

		return $this->renderer;
	}
}
