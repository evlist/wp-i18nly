<?php
/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Generates the HTML fixtures used by the JavaScript tests from the real PHP renderers.
 *
 * Usage: php tests/js/bin/generate-fixture.php
 *
 * @package I18nly
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions, Generic.Files.OneObjectStructurePerFile

require_once __DIR__ . '/../../phpunit/bootstrap.php';

/**
 * Exposes the row and table navigation rendering of the entries list table.
 */
$fixture_table_class = new class( array() ) extends \WP_I18nly\Admin\UI\TranslationEntriesListTable {
	/**
	 * Renders the table body rows.
	 *
	 * @return string
	 */
	public function render_body_rows() {
		$this->prepare_items();

		ob_start();

		foreach ( $this->items as $item ) {
			$status = isset( $item['source_status'] ) ? (string) $item['source_status'] : 'active';

			echo '<tr class="i18nly-translation-entry" data-entry-status="' . esc_attr( $status ) . '">';

			foreach ( array_keys( $this->get_columns() ) as $column_name ) {
				$method = 'column_' . $column_name;

				echo '<td>';
				echo method_exists( $this, $method ) ? $this->{$method}( $item ) : $this->column_default( $item, $column_name );
				echo '</td>';
			}

			echo '</tr>';
		}

		return (string) ob_get_clean();
	}

	/**
	 * Renders the top or bottom table navigation.
	 *
	 * @param string $which top|bottom.
	 * @return string
	 */
	public function render_tablenav( $which ) {
		ob_start();
		$this->display_tablenav( $which );

		return (string) ob_get_clean();
	}

	/**
	 * Renders the table header cells.
	 *
	 * @return string
	 */
	public function render_header_cells() {
		$cells = '';

		foreach ( $this->get_columns() as $label ) {
			$cells .= '<th scope="col">' . $label . '</th>';
		}

		return $cells;
	}
};

$forms = array(
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
);

$rows = array(
	array(
		'source_entry_id' => 11,
		'msgctxt'         => '',
		'msgid'           => 'Hello',
		'msgid_plural'    => '',
		'source_status'   => 'active',
		'translations'    => array(
			array(
				'form_index'  => 0,
				'translation' => 'Bonjour',
				'status'      => 'validated',
				'used_ai'     => 0,
				'used_manual' => 1,
			),
		),
	),
	array(
		'source_entry_id' => 12,
		'msgctxt'         => '',
		'msgid'           => '%d apple',
		'msgid_plural'    => '%d apples',
		'source_status'   => 'active',
		'forms'           => $forms,
		'form_labels'     => array( 'one', 'other' ),
		'form_markers'    => array( 'a', 'b' ),
		'form_tooltips'   => array( 'one', 'other' ),
		'translations'    => array(
			array(
				'form_index'  => 0,
				'translation' => '%d pomme',
				'status'      => 'draft',
				'used_ai'     => 1,
				'used_manual' => 0,
			),
			array(
				'form_index'  => 1,
				'translation' => '',
				'status'      => 'draft',
				'used_ai'     => 0,
				'used_manual' => 0,
			),
		),
	),
	array(
		'source_entry_id' => 13,
		'msgctxt'         => 'legacy',
		'msgid'           => 'Legacy',
		'msgid_plural'    => '',
		'source_status'   => 'obsolete',
		'translations'    => array(
			array(
				'form_index'  => 0,
				'translation' => 'Hérité',
				'status'      => 'suspect',
				'used_ai'     => 1,
				'used_manual' => 0,
			),
		),
	),
	array(
		'source_entry_id' => 14,
		'msgctxt'         => '',
		'msgid'           => 'Settings',
		'msgid_plural'    => '',
		'source_status'   => 'active',
		'translations'    => array(
			array(
				'form_index'  => 0,
				'translation' => '',
				'status'      => 'draft',
				'used_ai'     => 0,
				'used_manual' => 0,
			),
		),
	),
);

$table = new ( get_class( $fixture_table_class ) )( $rows );
$table->prepare_items();

$table_markup = $table->render_tablenav( 'top' )
	. '<table class="wp-list-table widefat fixed striped"><thead><tr>' . $table->render_header_cells() . '</tr></thead>'
	. '<tbody>' . $table->render_body_rows() . '</tbody></table>'
	. $table->render_tablenav( 'bottom' );

ob_start();
( new \WP_I18nly\Admin\UI\TranslationMetaBoxRenderer() )->render_translation_meta_box(
	array( 'akismet/akismet.php' => 'Akismet' ),
	array(
		array(
			'value'    => 'fr_FR',
			'label'    => 'Français',
			'disabled' => false,
		),
	),
	'akismet/akismet.php',
	'fr_FR',
	true
);
$meta_box = (string) ob_get_clean();

$loading = '<div id="i18nly-source-entries-table">';
$start   = strpos( $meta_box, $loading );
$end     = strpos( $meta_box, '</div>', $start );

if ( false === $start || false === $end ) {
	fwrite( STDERR, "Could not locate the entries table container in the meta box markup.\n" );
	exit( 1 );
}

$page_markup = substr( $meta_box, 0, $start + strlen( $loading ) ) . '__TABLE__' . substr( $meta_box, $end );

$directory = __DIR__ . '/../fixtures';
$license   = "<!-- SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com> -->\n<!-- SPDX-License-Identifier: GPL-3.0-or-later -->\n";

file_put_contents( $directory . '/entries-table.html', $license . $table_markup . "\n" );
file_put_contents( $directory . '/edit-screen.html', $license . $page_markup . "\n" );

echo "Fixtures written to tests/js/fixtures.\n";
