<?php
/**
 * WP List Table Example class
 *
 * @package   WPListTableExample
 * @author    Matt van Andel
 * @copyright 2016 Matthew van Andel
 * @license   GPL-2.0+
 */

namespace Cleverplugins\SEOBooster;

// don't load directly
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class SB_GSC_List_Table
 *
 * @package Cleverplugins\SEOBooster
 */
class SB_GSC_List_Table extends \WP_List_Table
{


	/**
	 * Constructor for the SB_GSC_List_Table class.
	 *
	 * @return void
	 */
	public function __construct()
	{
		// Set parent defaults.
		parent::__construct(
			array(
				'singular' => 'gsc',
				'plural'   => 'gscs',
				'ajax'     => true,   
			)
		);
	}


	/**
	 * Display a message when no items are found.
	 *
	 * @return void
	 */
	public function no_items()
	{
		esc_html_e('Nothing found.', 'seo-booster');
	}



	/**
	 * Get the hidden columns for the list table.
	 *
	 * @return array An array of hidden column names.
	 */
	protected function get_hidden_columns()
	{
		$screen = get_current_screen();
		$hidden = get_user_option("manage{$screen->id}columnshidden");
		return is_array($hidden) ? $hidden : [];
	}



	/**
	 * Get the columns for the list table.
	 *
	 * @return array An associative array of column identifiers and labels.
	 */
	public function get_columns()
	{
		$columns = array(
			'cb'          => '<input type="checkbox" />',
			'query'       => esc_html__('Query', 'seo-booster'),
			'page'        => esc_html__('Page', 'seo-booster'),
			'clicks'      => esc_html__('Clicks', 'seo-booster'),
			'impressions' => esc_html__('Impressions', 'seo-booster'),
			'ctr'         => esc_html__('CTR', 'seo-booster'),
			'position'    => esc_html__('Average Pos', 'seo-booster'),
			'latest_date' => esc_html__('Last Visitor', 'seo-booster'),
		);

		$hidden_columns = $this->get_hidden_columns();
		foreach ($hidden_columns as $hidden_column) {
			if (isset($columns[$hidden_column])) {
				unset($columns[$hidden_column]);
			}
		}

		return $columns;
	}



	/**
	 * Sanitize the orderby parameter.
	 *
	 * @param string $orderby The orderby parameter.
	 *
	 * @return string The sanitized orderby parameter.
	 */
	protected function sanitize_orderby($orderby)
	{
		$valid_column_names = [
			'query',
			'clicks',
			'impressions',
			'ctr',
			'position',
			// 'first_seen_date',
			'latest_date',
			'unique_landing_pages', // Add new column
		];

		if (in_array($orderby, $valid_column_names, true)) {
			return $orderby;
		}

		return 'impressions';
	}

	// Set the default primary column
	/**
	 * Get the default primary column name.
	 *
	 * @return string The default primary column name.
	 */
	protected function get_default_primary_column_name()
	{
		return 'impressions';
	}


	/**
	 * Get the sortable columns for the list table.
	 *
	 * @return array An associative array of sortable column identifiers and their sorting criteria.
	 */
	public function get_sortable_columns()
	{
		$sortable_columns = array(
			'query'      => array('query', false),
			'clicks'     => array('clicks', false),
			'impressions' => array('impressions', true),
			'ctr'        => array('ctr', false),
			'position'   => array('position', false),
			// 'first_seen_date'       => array('first_seen_date', false),
			'latest_date'       => array('latest_date', false),
		);

		return $sortable_columns;
	}





	/**
	 * Render a column for the list table.
	 *
	 * @param array  $item        The current item.
	 * @param string $column_name The name of the column to render.
	 *
	 * @return string The rendered column content.
	 */
	protected function column_default($item, $column_name)
	{
		// get the current post id

		global $wpdb;

		switch ($column_name) {
			case 'query':
				$output = esc_html($item[$column_name]);
				if (isset($item['kw_used']) && $item['kw_used'] == 1) {
					$output .= sprintf(
						' <span class="label label-ok used">%s</span>',
						esc_html__('Used', 'seo-booster')
					);
				}
				return $output;

			case 'clicks':
			case 'impressions':
				return $item[$column_name];

			case 'clicks':
				return $item[$column_name];

			case 'ctr':
				$value = $item[$column_name];
				return (float)$value === 0.0 ? '0' : number_format_i18n($value, 4);

			case 'first_seen_date':
				$stored_time = strtotime(get_date_from_gmt($item[$column_name]));
				$formatted_date = date_i18n(get_option('date_format'), $stored_time);
				return $formatted_date;

			case 'latest_date':
				// Convert the stored time from GMT to the site's timezone
				$stored_time = strtotime(get_date_from_gmt($item[$column_name]));

				// Calculate the time difference in seconds
				$time_diff = current_time('timestamp') - $stored_time;

				// Format the stored time according to the site's date and time format
				$formatted_date = date_i18n(get_option('date_format'), $stored_time);

				// Check if the time difference is more than 30 days (30 * 24 * 60 * 60)
				if ($time_diff > (30 * 24 * 60 * 60)) {
					// Calculate the human-readable time difference
					//$time_diff_human = human_time_diff($stored_time, current_time('timestamp'));
					$days_diff = floor((current_time('timestamp') - $stored_time) / 86400);

					// Show the date with human-readable time difference if more than 4 days
					return $formatted_date . ' <span class="label oldquery">' . esc_html($days_diff) . ' days ago</span>';
				}
				return $formatted_date;

			case 'position':

				return '<span title="' . esc_attr($item[$column_name]) . '">' . number_format_i18n($item[$column_name]) . '</span>';

				return $item[$column_name];

			case 'page':
				$url = $item['page'];
				$path = wp_parse_url($url, PHP_URL_PATH); // Extract path from URL
				$path = trailingslashit($path); // Ensure path ends with a slash

				$post_id = url_to_postid($url);
				$edit_link = get_edit_post_link($post_id);

				$parsed_url = wp_parse_url($url);
				$stripped_url = isset($parsed_url['path']) ? $parsed_url['path'] : '';

				
				$return = '';
				$return .= sprintf(
					'<a href="%1$s">%2$s</a>',
					$url,
					$stripped_url
				);
				if ($edit_link) {
					$return .= ' | <a href="' . esc_url($edit_link) . '" class="post-' . esc_attr($post_id) . '">' . esc_html__('Edit', 'seo-booster') . '</a>';
				}
				return $return;


			default:
				return print_r($item, true); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}
	}

	/**
	 * Sanitize the order parameter.
	 *
	 * @param string $order The order parameter.
	 *
	 * @return string The sanitized order parameter.
	 */
	protected function sanitize_order($order)
	{
		if (in_array(strtoupper($order), ['ASC', 'DESC'], true)) {
			return $order;
		}

		return 'ASC';
	}




	/**
	 * Prepare a query condition if the value is not empty.
	 *
	 * @param string $query The query condition.
	 * @param mixed  $value The value to check.
	 *
	 * @return string The prepared query condition or an empty string if the value is empty.
	 */
	protected function prepare_if_not_empty($query, $value)
	{
		return !empty($value) ? $this->db->prepare($query, $value) : '';
	}









	/**
	 * Prepare the items for the list table.
	 *
	 * @return void
	 */
	public function prepare_items()
	{
		global $wpdb;

		$per_page = 50;

		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array($columns, $hidden, $sortable);

		$this->process_bulk_action();

		$current_page = $this->get_pagenum();
		$offset = ($current_page * $per_page) - $per_page;

		$search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';

		$is_exact_match = isset($_REQUEST['exact_match']) && $_REQUEST['exact_match'] == 1;

		$do_search = '';
		if (!empty($search)) {
			$do_search = $is_exact_match
				? $wpdb->prepare(" AND qk.query = %s ", $search)
				: $wpdb->prepare(" AND (qk.query LIKE %s OR qk.page LIKE %s OR qk.first_seen_date LIKE %s OR qk.latest_date LIKE %s) ", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%");
		}

		$lp_filter_query = '';
		$lp_filter = isset($_GET['filter_options']) ? filter_var(wp_unslash($_GET['filter_options']), FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';

		if ($lp_filter) {
			switch ($lp_filter) {
				case 'new_keywords':
					$lp_filter_query = " AND qk.latest_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ";
					break;
				case 'not_seen':
					$lp_filter_query = " AND qk.latest_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) ";
					break;
				case 'keywords_used':
					$lp_filter_query = " AND qk.is_used_in_content = 1 ";
					break;
				case 'keywords_unused':
					$lp_filter_query = " AND (qk.is_used_in_content = 0 OR qk.is_used_in_content IS NULL) ";
					break;
				case 'high_position':
					$lp_filter_query = " AND qkh.position BETWEEN 1 AND 10 ";
					break;
				case 'medium_position':
					$lp_filter_query = " AND qkh.position BETWEEN 11 AND 50 ";
					break;
				case 'low_position':
					$lp_filter_query = " AND qkh.position > 50 ";
					break;
			}
		}

		// Verify nonce
		// $nonce_action = 'gsc_list_table_nonce';
		// if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], $nonce_action)) {
		// 	wp_die(__('Invalid request.', 'seo-booster'));
		// }

		// Sanitize and validate the order parameter
		$order = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : 'DESC';
		$order = strtoupper($order); // Convert to uppercase for comparison
		$order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';

		$orderby = isset($_GET['orderby']) ? esc_sql(sanitize_text_field(wp_unslash($_GET['orderby']))) : 'impressions';

		// Fetch the filtered and searched count of items
		$total_filtered_query = "SELECT COUNT(DISTINCT qk.id) FROM {$wpdb->prefix}sb2_query_keywords AS qk LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh ON qk.id = qkh.query_keywords_id WHERE 1=1 $do_search $lp_filter_query";

		$total_filtered = $wpdb->get_var($total_filtered_query);

		$query = "SELECT qk.query,
		qk.is_used_in_content as kw_used, 
		qk.page, 
                 SUM(qkh.clicks) as clicks, 
                 SUM(qkh.impressions) as impressions, 
                 AVG(qkh.position) as position, 
									 AVG(qkh.ctr) as ctr, 
                 MAX(qkh.date) as latest_date
			  FROM {$wpdb->prefix}sb2_query_keywords AS qk
			  LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh 
			  ON qk.id = qkh.query_keywords_id
			  WHERE 1=1 $do_search $lp_filter_query
			  GROUP BY qk.query, qk.page
			  ORDER BY {$orderby} {$order} 
			  LIMIT %d, %d";

		$data = $wpdb->get_results($wpdb->prepare($query, $offset, $per_page), ARRAY_A);

		$this->items = $data;

		$this->set_pagination_args(array(
			'total_items' => $total_filtered,
			'per_page'    => $per_page,
			'total_pages' => ceil($total_filtered / $per_page),
		));
	}
}
