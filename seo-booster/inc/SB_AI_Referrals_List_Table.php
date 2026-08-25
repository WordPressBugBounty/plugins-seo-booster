<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List table for AI referral visits on the AI Bots admin page.
 */
class SB_AI_Referrals_List_Table extends \WP_List_Table {

	/** @var int */
	private $filter_days = 30;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'ai_referral',
				'plural'   => 'ai_referrals',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Set reporting window in days.
	 *
	 * @param int $days Days.
	 * @return void
	 */
	public function set_filter_days( $days ) {
		$allowed           = array( 7, 30, 90 );
		$this->filter_days = in_array( (int) $days, $allowed, true ) ? (int) $days : 30;
	}

	public function no_items() {
		esc_html_e( 'No AI referral visits recorded for this period.', 'seo-booster' );
	}

	public function get_columns() {
		return array(
			'source'       => _x( 'Source', 'Column label', 'seo-booster' ),
			'landing_page' => _x( 'Landing page', 'Column label', 'seo-booster' ),
			'object_type'  => _x( 'Type', 'Column label', 'seo-booster' ),
			'visits'       => _x( 'Visits', 'Column label', 'seo-booster' ),
			'last_seen'    => _x( 'Last seen', 'Column label', 'seo-booster' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'source'    => array( 'source', false ),
			'visits'    => array( 'visits', true ),
			'last_seen' => array( 'last_seen', true ),
		);
	}

	public function prepare_items() {
		$per_page = 20;
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$paged    = $this->get_pagenum();

		$result = AI_Referral_Tracker::get_referral_log(
			$this->filter_days,
			array(
				'search' => $search,
				'offset' => ( $paged - 1 ) * $per_page,
				'limit'  => $per_page,
			)
		);

		$this->items = $result['items'];
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / $per_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'source':
				return '<strong>' . esc_html( $item['source'] ) . '</strong>';
			case 'landing_page':
				return $this->render_landing_page( $item );
			case 'object_type':
				return esc_html( $this->format_object_type( isset( $item['object_type'] ) ? $item['object_type'] : '' ) );
			case 'visits':
				return esc_html( number_format_i18n( (int) $item['visits'] ) );
			case 'last_seen':
				return esc_html( $item['last_seen'] );
			default:
				return '';
		}
	}

	/**
	 * Render landing page cell with view link when available.
	 *
	 * @param array<string, mixed> $item Row data.
	 * @return string
	 */
	private function render_landing_page( $item ) {
		$object_id   = isset( $item['object_id'] ) ? (int) $item['object_id'] : 0;
		$object_type = isset( $item['object_type'] ) ? (string) $item['object_type'] : '';
		$label       = AI_Bot_Tracker::resolve_object_label( $object_id, $object_type );

		if ( ! empty( $label['title'] ) ) {
			$title = esc_html( $label['title'] );
			if ( ! empty( $label['view_url'] ) ) {
				return '<a href="' . esc_url( $label['view_url'] ) . '" target="_blank" rel="noopener noreferrer">' . $title . '</a>';
			}
			return $title;
		}

		$url = ! empty( $item['normalized_url'] ) ? $item['normalized_url'] : ( isset( $item['landing_path'] ) ? $item['landing_path'] : '' );
		if ( '' === $url ) {
			return '';
		}

		$display = esc_html( AI_Bot_Tracker::truncate_display( $url, 70 ) );
		if ( ! empty( $item['normalized_url'] ) ) {
			return '<a href="' . esc_url( $item['normalized_url'] ) . '" target="_blank" rel="noopener noreferrer">' . $display . '</a>';
		}

		return $display;
	}

	/**
	 * Human-readable object type label.
	 *
	 * @param string $object_type Object type key.
	 * @return string
	 */
	private function format_object_type( $object_type ) {
		switch ( $object_type ) {
			case 'post':
				return __( 'Post', 'seo-booster' );
			case 'term':
				return __( 'Term', 'seo-booster' );
			case 'archive':
				return __( 'Archive', 'seo-booster' );
			case 'home':
				return __( 'Home', 'seo-booster' );
			default:
				return '' !== $object_type ? $object_type : __( 'URL', 'seo-booster' );
		}
	}
}
