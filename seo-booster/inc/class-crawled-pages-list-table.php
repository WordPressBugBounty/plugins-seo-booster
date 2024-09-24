<?php
// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_Crawled_List_Table extends WP_List_Table {


	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'url',
				'plural'   => 'urls',
				'ajax'     => false,
			)
		);
	}


	/** Text displayed when no customer data is available */
	public function no_items() {
		echo 'No crawl data available.';
	}


	/**
	 * get_columns.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Wednesday, March 27th, 2024.
	 * @access	public
	 * @return	mixed
	 */
	public function get_columns() {
		$columns = array(
			'url'       => 'URL',
			'visits'    => 'Robot Crawls',
			'lastcrawl' => 'Last Crawled',
			'crawlers'  => 'Crawled by',
		);

		return $columns;
	}

	/**
	 * get_sortable_columns.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Wednesday, March 27th, 2024.
	 * @access	protected
	 * @return	mixed
	 */
	protected function get_sortable_columns() {
		$sortable_columns = array(
			'url'       => array( 'url', false ),
			'visits'    => array( 'ttlvisits', false ),
			'lastcrawl' => array( 'lastcrawl', false ),
		);

		return $sortable_columns;
	}


	/**
	 * column_default.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Wednesday, March 27th, 2024.
	 * @access	protected
	 * @param	mixed	$item       	
	 * @param	mixed	$column_name	
	 * @return	void
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'url':
				return $item[ $column_name ];
			case 'lastcrawl':
				return $item[ $column_name ];
			case 'crawlers':
				return $item[ $column_name ];
			case 'visits':
				return $item[ $column_name ];
			default:
				return print_r( $item, true ); // Show the whole array for troubleshooting purposes.
		}
	}


	/**
	 * column_url.
	 *
	 * @author	Unknown
	 * @since	v0.0.1
	 * @version	v1.0.0	Tuesday, November 30th, 2021.
	 * @access	protected
	 * @param	mixed	$item	
	 * @return	mixed
	 */
	protected function column_url( $item ) {

		// Return the title contents.
		return sprintf(
			'<a href="%1$s" target="_blank">%2$s</a>',
			site_url( $item['url'] ),
			$item['url'],
			rawurlencode( $item['url'] )
		);
	}

	/**
	 * process_bulk_action.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Wednesday, March 27th, 2024.
	 * @access	protected
	 * @return	void
	 */
	protected function process_bulk_action() {

		if ( 'deleteall' === $this->current_action() ) {
			global $seobooster2, $wpdb;
			$seobooster2->log( 'Resetting Crawl Data' );
			$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}sb2_crawl;" );
		}
	}


	protected function sanitize_orderby( $orderby ) {
		$valid_column_names = [
			'url',
			'ttlvisits',
			'lastcrawl'
		];

		if ( in_array( $orderby, $valid_column_names, true ) ) {
			return $orderby;
		}

		return 'url';
	}

	protected function sanitize_order( $order ) {
		if ( in_array( strtoupper( $order ), [ 'ASC', 'DESC' ], true ) ) {
			return $order;
		}

		return 'ASC';
	}




	/**
	 * prepare_items.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Wednesday, March 27th, 2024.
	 * @return	void
	 */
	function prepare_items() {

		global $wpdb;
		$per_page = 50;
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();

		$paged = ( isset( $_GET['paged'] ) ) ? sanitize_text_field( $_GET['paged'] ) : 1;

		$offset = ( $paged * $per_page ) - $per_page;

		$search = ( isset( $_REQUEST['s'] ) ) ? sanitize_text_field( $_REQUEST['s'] ) : false;

		if ( $search ) {
			$do_search = $wpdb->prepare( 
				' AND url LIKE %s ', 
				'%' . $wpdb->esc_like( $search ) . '%' 
			);
		} else {
			$do_search = '';
		}

		$orderby = filter_input( INPUT_GET, 'orderby' );

		$orderby = ! empty( $orderby ) ? esc_sql( sanitize_text_field( $orderby ) ) : 'url';
		$orderby = $this->sanitize_orderby( $orderby );

		$order = filter_input( INPUT_GET, 'order' );
		$order = ! empty( $order ) ? esc_sql( strtoupper( sanitize_text_field( $order ) ) ) : 'ASC';
		$order = $this->sanitize_order( $order );

		$daquery = "SELECT url, lastcrawl, visits, SUM(visits) as ttlvisits FROM {$wpdb->prefix}sb2_crawl WHERE 1 = 1 $do_search GROUP BY url ORDER BY $orderby $order LIMIT $offset, $per_page;";

		$data = $wpdb->get_results( $daquery, ARRAY_A );

		if ( $data ) {
			$newdat = array();
			foreach ( $data as $da ) {
				$daurl    = $da['url'];
				$crawlers = $wpdb->get_results( $wpdb->prepare( "SELECT id,engine,SUM(visits) as visits,lastcrawl FROM {$wpdb->prefix}sb2_crawl WHERE url=%s GROUP BY engine;", $daurl) );

				//$crawlout = '##todo##';
				if ( $crawlers ) {

					$crawlout = '<ul class="crawllist">';
					$tv       = 0;
					foreach ( $crawlers as $cr ) {
						$tv        = $tv + $cr->visits;
						$crawlout .= '<li>' . ucfirst( $cr->engine ) . ' <span>' . sprintf( esc_html( _n( '%d visit', '%d visits', $cr->visits, 'seo-booster' ) ), $cr->visits ) . '. Last: ' . $cr->lastcrawl . '</span></li>';
					}
					$da['visits'] = $tv;
					$crawlout    .= '</ul><!-- .crawllist -->';
				}
				$da['crawlers'] = $crawlout;
				$newdat[]       = $da;
			} // foreach
			$data = $newdat;
		} // if ($data)

		$current_page = $this->get_pagenum();

		$total_items = $wpdb->get_var( "SELECT count(DISTINCT(url)) FROM {$wpdb->prefix}sb2_crawl WHERE 1=1 {$do_search};" );

		$this->items = $data;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Callback to allow sorting of example data.
	 *
	 * @param string $a First value.
	 * @param string $b Second value.
	 *
	 * @return int
	 */
	protected function usort_reorder( $a, $b ) {
		$orderby = ! empty( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : 'lastcrawl';

		$order = ! empty( $_REQUEST['order'] ) ? sanitize_key( $_REQUEST['order'] ) : 'desc';

		$result = strcmp( $a[ $orderby ], $b[ $orderby ] );

		return ( 'asc' === $order ) ? $result : - $result;
	}
}
