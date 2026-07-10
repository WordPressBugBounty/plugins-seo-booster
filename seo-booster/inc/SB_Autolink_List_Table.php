<?php
namespace Cleverplugins\SEOBooster;

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_Autolink_List_Table extends \WP_List_Table {


	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'url',
				'plural'   => 'urls',
				'ajax'     => true,
			)
		);
	}


	/** Text displayed when no customer data is available */
	public function no_items() {
		esc_html_e( 'No keyword to links made.', 'seo-booster' );
	}

	/**
	 * @var array
	 *
	 * Array contains slug columns that you want hidden
	 *
	 */

	private $hidden_columns = array(
		'id',
	);


	/**
	 * column_cb.
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Wednesday, March 27th, 2024.
	 * @access  protected
	 * @param   mixed   $item
	 * @return  mixed
	 */
	protected function column_cb( $item ) {

		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			'alid',
			rawurlencode( $item['id'] )
		);
	}

	/**
	 * get_columns.
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Wednesday, March 27th, 2024.
	 * @access  public
	 * @return  mixed
	 */
	public function get_columns() {

		$columns = array(
			'cb'       => '<input type="checkbox" />',
			'keyword'  => _x( 'Keyword', 'Column label', 'seo-booster' ),
			'pointing' => '', // cannot call it arrow because of CSS clashes
			'url'      => _x( 'Target URL', 'Column label', 'seo-booster' ),
			'lastseen' => _x( 'Last Used On', 'Column label', 'seo-booster' ), // Add this line

		);

		return $columns;
	}

	/**
	 * get_sortable_columns.
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Wednesday, March 27th, 2024.
	 * @access  protected
	 * @return  mixed
	 */
	protected function get_sortable_columns() {
		$sortable_columns = array(
			'keyword' => array( 'keyword', false ),
			'url'     => array( 'url', false ),
		);
		return $sortable_columns;
	}


	/**
	 * Render a single table row as HTML (used by AJAX add handler).
	 *
	 * @param array $item Row data (id, keyword, url, lastseen).
	 * @return string
	 */
	public function render_row( array $item ): string {
		$columns               = $this->get_columns();
		$hidden                = $this->hidden_columns;
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		ob_start();
		$this->single_row( $item );
		return (string) ob_get_clean();
	}

	/**
	 * Render a single table row via a fresh list-table instance.
	 *
	 * @param array $item Row data (id, keyword, url, lastseen).
	 * @return string
	 */
	public static function render_row_html( array $item ): string {
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		$table = new self();
		return $table->render_row( $item );
	}

	/**
	 * Row actions for the primary (keyword) column.
	 *
	 * @param array  $item        Row data.
	 * @param string $column_name Current column.
	 * @param string $primary     Primary column name.
	 * @return string
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( 'keyword' !== $column_name || $primary !== $column_name ) {
			return '';
		}

		$item_id = absint( $item['id'] );
		$actions = array(
			'delete' => sprintf(
				'<a href="#" class="sb-delete-keyword" data-id="%1$d" data-nonce="%2$s">%3$s</a>',
				$item_id,
				esc_attr( wp_create_nonce( 'sb_delete_kw_' . $item_id ) ),
				_x( 'Delete', 'List table row action', 'seo-booster' )
			),
		);

		return $this->row_actions( $actions );
	}

	/**
	 * column_default.
	 *
	 * @param mixed $item        Row data.
	 * @param mixed $column_name Column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'keyword':
				return sprintf(
					'<span class="editable-field keyword-field" data-id="%d">%s</span>',
					$item['id'],
					esc_html( $item['keyword'] )
				);
			case 'url':
				return sprintf(
					'<span class="editable-field url-field" data-id="%d">%s</span>',
					$item['id'],
					esc_url( $item['url'] )
				);
			case 'pointing':
				return '<span class="dashicons dashicons-arrow-right-alt"></span>';
			default:
				return isset( $item[ $column_name ] ) && is_scalar( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
		}
	}

	/**
	 * column_pointing.
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Wednesday, March 27th, 2024.
	 * @access  protected
	 * @param   mixed   $item
	 * @return  string
	 */
	protected function column_pointing( $item ) {
		return '<span class="dashicons dashicons-arrow-right-alt"></span>';
	}

	/**
	 * column_lastseen.
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Wednesday, March 27th, 2024.
	 * @access  protected
	 * @param   mixed   $item
	 * @return  mixed
	 */
	protected function column_lastseen( $item ) {
		$lastseen = maybe_unserialize( $item['lastseen'] );
		if ( ! is_array( $lastseen ) ) {
			return '';
		}

		$outstr = '';
		foreach ( $lastseen as $ls ) {
			if ( $this->should_hide_lastseen_path( $ls ) ) {
				continue;
			}
			$outstr .= '<span class="listitem"><a href="' . esc_url( site_url( $ls ) ) . '" target="_blank">' . esc_html( $ls ) . '</a>, </span>';
		}

		return $outstr;
	}

	/**
	 * Whether a stored lastseen path should be hidden from the admin list.
	 *
	 * @param string $path Relative path stored in lastseen.
	 * @return bool
	 */
	private function should_hide_lastseen_path( $path ) {
		return $this->is_search_path( $path ) || Utils::is_rest_api_path( $path );
	}

	/**
	 * Detect whether a stored relative path is a search-results URL.
	 *
	 * Matches the native WordPress `?s=` query parameter and the pretty
	 * `/search/...` permalink form (also used by SearchWP / Relevanssi).
	 *
	 * @param string $path Relative path stored in lastseen, e.g. "/?s=foo".
	 * @return bool
	 */
	private function is_search_path( $path ) {
		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}

		// Pretty search permalink, e.g. /search/term/
		if ( preg_match( '#(^|/)search/#i', $path ) ) {
			return true;
		}

		// Query-string search, e.g. /?s=term or /page/2/?s=term
		$query = wp_parse_url( $path, PHP_URL_QUERY );
		if ( ! empty( $query ) ) {
			$args = array();
			wp_parse_str( $query, $args );
			if ( isset( $args['s'] ) ) {
				return true;
			}
		}

		return false;
	}


	protected function get_bulk_actions() {

		$actions = array(
			'delete' => _x( 'Delete', 'List table bulk action', 'seo-booster' ),
		);
		return $actions;
	}



	/**
	 * process_bulk_action.
	 *
	 * @author  Unknown
	 * @since   v0.0.1
	 * @version v1.0.0  Tuesday, November 30th, 2021.
	 * @access  protected
	 * @return  void
	 */
	protected function process_bulk_action() {
		// Security check
		if ( isset( $_GET['_wpnonce'] ) && ! empty( $_GET['_wpnonce'] ) ) {
			$nonce  = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
			$action = 'bulk-' . $this->_args['plural'];

			if ( ! wp_verify_nonce( $nonce, $action ) ) {
				wp_die( esc_html__( 'Nope! Security check failed!', 'seo-booster' ) );
			}
		}

		// Check user has permission
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nope! Security check failed!', 'seo-booster' ) );
		}

		if ( 'delete' === $this->current_action() ) {
			global $wpdb;
			if ( isset( $_GET['alid'] ) ) {
				$alidsan = $_GET['alid'];
				if ( is_array( $alidsan ) ) {
					foreach ( $alidsan as $alid ) {
						$alid = absint( $alid );
						$wpdb->delete( $wpdb->prefix . 'sb2_autolink', array( 'id' => $alid ), array( '%d' ) );
					}
					Seobooster2::flush_autolink_caches();
				}
			}
		}
	}

	protected function sanitize_orderby( $orderby ) {
		$valid_column_names = array(
			'keyword',
			'url',
		);

		if ( in_array( $orderby, $valid_column_names, true ) ) {
			return $orderby;
		}

		return 'keyword';
	}

	/**
	 * sanitize_order.
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Wednesday, March 27th, 2024.
	 * @access  protected
	 * @param   mixed   $order
	 * @return  string
	 */
	protected function sanitize_order( $order ) {
		if ( in_array( strtoupper( $order ), array( 'ASC', 'DESC' ), true ) ) {
			return $order;
		}

		return 'ASC';
	}


	/**
	 * prepare_items.
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Wednesday, March 27th, 2024.
	 * @return  void
	 */
	function prepare_items() {
		global $wpdb;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- List table pagination, search, and sort reads only.

		// Get per_page from URL parameter first, then user meta, then default to 25
		$url_per_page  = isset( $_GET['per_page'] ) ? (int) wp_unslash( $_GET['per_page'] ) : 0;
		$user_per_page = get_user_meta( get_current_user_id(), 'sb_autolink_per_page', true );
		$per_page      = $url_per_page ?: ( ! empty( $user_per_page ) ? (int) $user_per_page : 25 );

		$columns  = $this->get_columns();
		$hidden   = $this->hidden_columns;
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();

		$paged = ( isset( $_GET['paged'] ) ) ? sanitize_text_field( wp_unslash( $_GET['paged'] ) ) : 1;

		$offset = ( $paged * $per_page ) - $per_page;

		$search = ( isset( $_REQUEST['s'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : false;

		if ( $search ) {
			$do_search = $wpdb->prepare(
				' AND (keyword LIKE %s OR url LIKE %s) ',
				'%' . $wpdb->esc_like( $search ) . '%',
				'%' . $wpdb->esc_like( $search ) . '%'
			);
		} else {
			$do_search = '';
		}

		$orderby = filter_input( INPUT_GET, 'orderby' );

		$orderby = ! empty( $orderby ) ? esc_sql( sanitize_text_field( $orderby ) ) : 'keyword';
		$orderby = $this->sanitize_orderby( $orderby );

		$order = filter_input( INPUT_GET, 'order' );
		$order = ! empty( $order ) ? esc_sql( strtoupper( sanitize_text_field( $order ) ) ) : 'ASC';
		$order = $this->sanitize_order( $order );

		$table_name = esc_sql( $wpdb->prefix . 'sb2_autolink' );

		$data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, keyword, url, lastseen
			FROM {$table_name} 
			WHERE 1 = 1 
			{$do_search} 
			ORDER BY %1\$s %2\$s 
			LIMIT %3\$d, %4\$d",
				$orderby,
				$order,
				$offset,
				$per_page
			),
			ARRAY_A
		);

		$current_page = $this->get_pagenum();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $do_search built via $wpdb->prepare() with esc_like() above.
		$total_items = $wpdb->get_var( "SELECT count(id) FROM {$wpdb->prefix}sb2_autolink WHERE 1=1 $do_search;" );

		$this->items = $data;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);

		// phpcs:enable WordPress.Security.NonceVerification.Recommended
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
		$orderby = ! empty( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'lastseen';

		$order = ! empty( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'desc';

		$result = strcmp( $a[ $orderby ], $b[ $orderby ] );

		return ( 'asc' === $order ) ? $result : -$result;
	}




	public static function ajax_update_per_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'add-keyword-nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		$per_page = isset( $_POST['per_page'] ) ? (int) $_POST['per_page'] : 25;
		update_user_meta( get_current_user_id(), 'sb_autolink_per_page', $per_page );
		wp_send_json_success();
	}
}
