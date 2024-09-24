<?php
/**
 * WP List Table Example class
 *
 * @package   WPListTableExample
 * @author    Matt van Andel
 * @copyright 2016 Matthew van Andel
 * @license   GPL-2.0+
 */

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_FOF_List_Table extends WP_List_Table {

	public function __construct() {
		// Set parent defaults.
		parent::__construct(
			array(
				'singular' => 'fof',     // Singular name of the listed records.
				'plural'   => 'fofs',    // Plural name of the listed records.
				'ajax'     => false,       // Does this table support ajax?
			)
		);
	}

	/**
	 * no_items.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Wednesday, March 27th, 2024.
	 * @access	public
	 * @return	void
	 */
	public function no_items() {
		esc_html_e( 'Nothing found.', 'seo-booster' );
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
			'cb'       => '<input type="checkbox" />', // Render a checkbox instead of text.
			'lp'       => _x( 'Landing Page', 'Column label', 'seo-booster' ),
			'visits'   => _x( 'Visitors', 'Column label', 'seo-booster' ),
			'lastseen' => _x( 'Latest Visit', 'Column label', 'seo-booster' ),
			'referer'  => _x( 'Referrer', 'Column label', 'seo-booster' ),

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
			'lp'       => array( 'lp', false ),
			'visits'   => array( 'visits', false ),
			'lastseen' => array( 'lastseen', false ),
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
			case 'visits':
				return $item[ $column_name ];
			case 'lastseen':
				return $item[ $column_name ];
			case 'referer':
				return $item[ $column_name ];

			default:
				return print_r( $item, true ); // Show the whole array for troubleshooting purposes.
		}
	}

	/**
	 * column_cb.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Wednesday, March 27th, 2024.
	 * @access	protected
	 * @param	mixed	$item	
	 * @return	mixed
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			$this->_args['singular'],  // Let's simply repurpose the table's singular label ("movie").
			$item['id']                // The value of the checkbox should be the record's ID.
		);
	}


	/**
	 * column_lp.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Wednesday, March 27th, 2024.
	 * @access	protected
	 * @param	mixed	$item	
	 * @return	mixed
	 */
	protected function column_lp( $item ) {

		$page = sanitize_text_field( $_REQUEST['page'] ); 

		// Build delete row action.
		$delete_query_args = array(
			'page'   => $page,
			'action' => 'delete',
			'fof'    => $item['id'],
		);
		$actions['delete'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( wp_nonce_url( add_query_arg( $delete_query_args, 'admin.php' ), 'sbp-nonce') ), _x( 'Delete', 'List table row action', 'seo-booster' )
		);

		// Return the title contents.
		return sprintf(
			'<a href="%1$s" target="_blank">%2$s</a> <span style="color:silver;">(#%3$s)</span>%4$s',
			site_url( $item['lp'] ),
			$item['lp'],
			$item['id'],
			$this->row_actions( $actions )
		);
	}

	/**
	 * get_bulk_actions.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Wednesday, March 27th, 2024.
	 * @access	protected
	 * @return	mixed
	 */
	protected function get_bulk_actions() {

		$actions = array(
			'delete' => _x( 'Delete', 'List table bulk action', 'seo-booster' ),
		);
		return $actions;
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
			// check the sbp-nonce
			check_admin_referer( 'sbp-nonce' );
	
			// check if user has permission to delete
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}	
			global $seobooster2, $wpdb;
			$seobooster2->log( __( 'Resetting 404 Errors', 'seo-booster' ) );
			$wpdb->query( "TRUNCATE TABLE `{$wpdb->prefix}sb2_404`;" );
			$myquery = "TRUNCATE TABLE `{$wpdb->prefix}sb2_404`;";
		}

		if ( 'delete' === $this->current_action() ) {
			check_admin_referer( 'sbp-nonce' );
			// check if user has permission to delete
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			global $wpdb;
			if ( isset( $_GET['fof'] ) ) {
				$fofsan = sanitize_text_field( $_GET['fof'] );
				if ( is_array( $fofsan ) ) {
					foreach ( $fofsan as $fofid ) {
						$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sb2_404 WHERE id=%d limit 1;", $fofid) );
					}
				} else {
					$fofid = intval( $_GET['fof'] );
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sb2_404 WHERE id=%d limit 1;", $fofid) );
				}
			}
		}
	}



	/**
	 * sanitize_orderby.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Wednesday, March 27th, 2024.
	 * @access	protected
	 * @param	mixed	$orderby	
	 * @return	string
	 */
	protected function sanitize_orderby( $orderby ) {
		$valid_column_names = [
			'visits',
			'lp',
			'lastseen',
		];

		if ( in_array( $orderby, $valid_column_names, true ) ) {
			return $orderby;
		}

		return 'visits';
	}

	/**
	 * sanitize_order.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Wednesday, March 27th, 2024.
	 * @access	protected
	 * @param	mixed	$order	
	 * @return	string
	 */
	protected function sanitize_order( $order ) {
		if ( in_array( strtoupper( $order ), [ 'ASC', 'DESC' ], true ) ) {
			return $order;
		}

		return 'ASC';
	}



	/**
	 * prepare_items.
	 *
	 * @author	Unknown
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Tuesday, November 30th, 2021.	
	 * @version	v1.0.1	Wednesday, March 27th, 2024.
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

		$paged = ( isset( $_GET['paged'] ) ) ? intval( $_GET[ 'paged' ] ) : 1;

		$offset = ( $paged * $per_page ) - $per_page;

		$search = ( isset( $_REQUEST['s'] ) ) ? sanitize_key( $_REQUEST['s'] ) : false;

		$do_search = ( $search ) ? $wpdb->prepare( " AND lp LIKE '%%%s%%' ", $search ) : '';

		$orderby = filter_input( INPUT_GET, 'orderby' );

		$orderby = ! empty( $orderby ) ? esc_sql( sanitize_text_field( $orderby ) ) : 'visits';
		$orderby = $this->sanitize_orderby( $orderby );

		$order = filter_input( INPUT_GET, 'order' );
		$order = ! empty( $order ) ? esc_sql( strtoupper( sanitize_text_field( $order ) ) ) : 'ASC';
		$order = $this->sanitize_order( $order );

		$daquery = "SELECT * FROM {$wpdb->prefix}sb2_404 WHERE 1=1 $do_search ORDER BY {$orderby} {$order} LIMIT $offset, $per_page;";

		$data = $wpdb->get_results( $daquery, ARRAY_A );

		$current_page = $this->get_pagenum();

		$total_items = $wpdb->get_var( "SELECT count(*) FROM {$wpdb->prefix}sb2_404;" );

		$this->items = $data;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,                     // WE have to calculate the total number of items.
				'per_page'    => $per_page,                        // WE have to determine how many items to show on a page.
				'total_pages' => ceil( $total_items / $per_page ), // WE have to calculate the total number of pages.
			)
		);
	}
}
