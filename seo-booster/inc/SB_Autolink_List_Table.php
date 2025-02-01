<?php
namespace Cleverplugins\SEOBooster;
// don't load directly
if (!defined('ABSPATH')) {
	exit;
}

class SB_Autolink_List_Table extends \WP_List_Table {


	public function __construct()
	{
		parent::__construct(
			array(
				'singular' => 'url',
				'plural'   => 'urls',
				'ajax'     => true,
			)
		);
	}


	/** Text displayed when no customer data is available */
	public function no_items()
	{
		esc_html_e('No keyword to links made.', 'seo-booster');
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
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Wednesday, March 27th, 2024.
	 * @access	protected
	 * @param	mixed	$item	
	 * @return	mixed
	 */
	protected function column_cb($item)
	{

		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			'alid',
			rawurlencode($item['id'])
		);
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
	public function get_columns()
	{

		$columns = array(
			'cb'       => '<input type="checkbox" />',
			'keyword'  => _x('Keyword', 'Column label', 'seo-booster'),
			'pointing' => '', // cannot call it arrow because of CSS clashes
			'url'      => _x('Target URL', 'Column label', 'seo-booster'),
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
	protected function get_sortable_columns()
	{
		$sortable_columns = array(
			'keyword' => array('keyword', false),
			'url'     => array('url', false),
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
	protected function column_default($item, $column_name)
	{
		switch ($column_name) {
			case 'keyword':
				return sprintf(
					'<span class="editable-field keyword-field" data-id="%d">%s</span>',
					$item['id'],
					esc_html($item['keyword'])
				);
			case 'url':
				return sprintf(
					'<span class="editable-field url-field" data-id="%d">%s</span>',
					$item['id'],
					esc_url($item['url'])
				);
			case 'pointing':
				return '<span class="dashicons dashicons-arrow-right-alt"></span>';
			default:
				return print_r($item, true);
		}
	}

	/**
	 * column_pointing.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Wednesday, March 27th, 2024.
	 * @access	protected
	 * @param	mixed	$item	
	 * @return	string
	 */
	protected function column_pointing($item)
	{
		return '<span class="dashicons dashicons-arrow-right-alt"></span>';
	}

	/**
	 * column_lastseen.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Wednesday, March 27th, 2024.
	 * @access	protected
	 * @param	mixed	$item	
	 * @return	mixed
	 */
	protected function column_lastseen($item)
	{

		$lastseen = maybe_unserialize($item['lastseen']);
		if (!is_array($lastseen)) {
			return '';
		}
		$outstr = '';
		foreach ($lastseen as $ls) {
			$outstr .= '<span class="listitem"><a href="' . site_url($ls) . '" target="_blank">' . $ls . '</a>, </span>';
		}
		$outstr = rtrim($outstr, ', </span>');
		return $outstr;
	}


	protected function column_lp($item)
	{

		$page = isset($_REQUEST['page']) ? sanitize_text_field(wp_unslash($_REQUEST['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Build delete row action.
		$delete_query_args = array(
			'page'   => $page,
			'action' => 'delete',
			'alid'   => $item['id'],
		);
		$actions['delete'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url(wp_nonce_url(
				add_query_arg($delete_query_args, 'admin.php'),
				'deletelink_' . $item['id']
			)),
			_x('Delete', 'List table row action', 'seo-booster')
		);

		// Return the title contents.
		return sprintf(
			'<a href="%1$s" target="_blank">%2$s</a> <span style="color:silver;">(#%3$s)</span>%4$s',
			site_url($item['lp']),
			$item['lp'],
			$item['id'],
			$this->row_actions($actions)
		);
	}

	protected function get_bulk_actions()
	{

		$actions = array(
			'delete' => _x('Delete', 'List table bulk action', 'seo-booster'),
		);
		return $actions;
	}



	/**
	 * process_bulk_action.
	 *
	 * @author	Unknown
	 * @since	v0.0.1
	 * @version	v1.0.0	Tuesday, November 30th, 2021.
	 * @access	protected
	 * @return	void
	 */
	protected function process_bulk_action()
	{
		// Security check
		if (isset($_GET['_wpnonce']) && !empty($_GET['_wpnonce'])) {
			$nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
			$action = 'bulk-' . $this->_args['plural'];

			if (!wp_verify_nonce($nonce, $action)) {
				wp_die(esc_html__('Nope! Security check failed!', 'seo-booster'));
			}
		}

		// Check user has permission
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Nope! Security check failed!', 'seo-booster'));
		}

		if ('delete' === $this->current_action()) {
			global $wpdb;
			if (isset($_GET['alid'])) {
				$alidsan = $_GET['alid'];
				if (is_array($alidsan)) {
					foreach ($alidsan as $alid) {
						$alid = absint($alid);
						$wpdb->delete($wpdb->prefix . 'sb2_autolink', ['id' => $alid], ['%d']);
					}
				}
			}
		}
	}

	protected function sanitize_orderby( $orderby ) {
		$valid_column_names = [
			'keyword',
			'url',
		];

		if (in_array($orderby, $valid_column_names, true)) {
			return $orderby;
		}

		return 'keyword';
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
	protected function sanitize_order($order)
	{
		if (in_array(strtoupper($order), ['ASC', 'DESC'], true)) {
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
	function prepare_items()
	{
		global $wpdb;

		// Get per_page from URL parameter first, then user meta, then default to 25
		$url_per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 0;
		$user_per_page = get_user_meta(get_current_user_id(), 'sb_autolink_per_page', true);
		$per_page = $url_per_page ?: (!empty($user_per_page) ? (int) $user_per_page : 25);

		$columns  = $this->get_columns();
		$hidden   = $this->hidden_columns;
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array($columns, $hidden, $sortable);

		$this->process_bulk_action();

		$paged = (isset($_GET['paged'])) ? sanitize_text_field(wp_unslash($_GET['paged'])) : 1;

		$offset = ($paged * $per_page) - $per_page;

		$search = (isset($_REQUEST['s'])) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : false;

		if ($search) {
			$do_search = $wpdb->prepare(
				' AND (keyword LIKE %s OR url LIKE %s) ',
				'%' . $wpdb->esc_like($search) . '%',
				'%' . $wpdb->esc_like($search) . '%'
			);
		} else {
			$do_search = '';
		}

		$orderby = filter_input(INPUT_GET, 'orderby');

		$orderby = !empty($orderby) ? esc_sql(sanitize_text_field($orderby)) : 'keyword';
		$orderby = $this->sanitize_orderby($orderby);

		$order = filter_input(INPUT_GET, 'order');
		$order = !empty($order) ? esc_sql(strtoupper(sanitize_text_field($order))) : 'ASC';
		$order = $this->sanitize_order($order);

		$table_name = esc_sql($wpdb->prefix . 'sb2_autolink');

		$data = $wpdb->get_results($wpdb->prepare(
			"SELECT id, keyword, url 
			FROM {$table_name} 
			WHERE 1 = 1 
			{$do_search} 
			ORDER BY %1\$s %2\$s 
			LIMIT %3\$d, %4\$d",
			$orderby,
			$order,
			$offset,
			$per_page
		), ARRAY_A);

		$current_page = $this->get_pagenum();

		$total_items = $wpdb->get_var("SELECT count(id) FROM {$wpdb->prefix}sb2_autolink WHERE 1=1 $do_search;");

		$this->items = $data;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil($total_items / $per_page),
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
	protected function usort_reorder($a, $b)
	{
		$orderby = !empty($_REQUEST['orderby']) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'lastseen';

		$order = !empty($_REQUEST['order']) ? sanitize_text_field(wp_unslash($_REQUEST['order'])) : 'desc';

		$result = strcmp($a[$orderby], $b[$orderby]);

		return ('asc' === $order) ? $result : -$result;
	}




	public static function ajax_update_per_page() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error('Permission denied');
		}

		$nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
		if (!wp_verify_nonce($nonce, 'add-keyword-nonce')) {
			wp_send_json_error('Invalid nonce');
		}

		$per_page = isset($_POST['per_page']) ? (int) $_POST['per_page'] : 25;
		update_user_meta(get_current_user_id(), 'sb_autolink_per_page', $per_page);
		wp_send_json_success();
	}


}
