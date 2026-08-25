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
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SB_GSC_List_Table
 *
 * @package Cleverplugins\SEOBooster
 */
class SB_GSC_List_Table extends \WP_List_Table {



	/**
	 * Constructor for the SB_GSC_List_Table class.
	 *
	 * @return void
	 */
	public function __construct() {
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
	public function no_items() {
		esc_html_e( 'Nothing found.', 'seo-booster' );
	}



	/**
	 * Get the hidden columns for the list table.
	 *
	 * @return array An array of hidden column names.
	 */
	protected function get_hidden_columns() {
		$screen = \get_current_screen();
		$hidden = get_user_option( "manage{$screen->id}columnshidden" );
		return is_array( $hidden ) ? $hidden : array();
	}



	/**
	 * Get the columns for the list table.
	 *
	 * @return array An associative array of column identifiers and labels.
	 */
	public function get_columns() {
		$columns = array(
			'cb'          => '<input type="checkbox" />',
			'query'       => esc_html__( 'Query', 'seo-booster' ),
			'page'        => esc_html__( 'Page', 'seo-booster' ),
			'clicks'      => esc_html__( 'Clicks', 'seo-booster' ),
			'impressions' => esc_html__( 'Impressions', 'seo-booster' ),
			// 'ctr'         => esc_html__('CTR', 'seo-booster'),
			// 'position'    => esc_html__('Average Pos', 'seo-booster'),
			'trends'      => esc_html__( 'Trends', 'seo-booster' ),
		);

		$hidden_columns = $this->get_hidden_columns();
		foreach ( $hidden_columns as $hidden_column ) {
			if ( isset( $columns[ $hidden_column ] ) ) {
				unset( $columns[ $hidden_column ] );
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
	protected function sanitize_orderby( $orderby ) {
		$valid_column_names = array(
			'query',
			'clicks',
			'impressions',
			'ctr',
			'position',
			'latest_date',
		);

		if ( in_array( $orderby, $valid_column_names, true ) ) {
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
	protected function get_default_primary_column_name() {
		return 'impressions';
	}


	/**
	 * Get the sortable columns for the list table.
	 *
	 * @return array An associative array of sortable column identifiers and their sorting criteria.
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'query'       => array( 'query', false ),
			'clicks'      => array( 'clicks', false ),
			'impressions' => array( 'impressions', true ),
			'ctr'         => array( 'ctr', false ),
			'position'    => array( 'position', false ),
			// 'first_seen_date'       => array('first_seen_date', false),
			'latest_date' => array( 'latest_date', false ),
		);

		return $sortable_columns;
	}





	/**
	 * Get the CSS classes for a table row
	 *
	 * @param array $item The current item.
	 * @return string CSS classes for the row.
	 */
	protected function get_row_class( $item ) {
		$classes = array();

		// Check if keyword has no recent traffic (over 30 days)
		if ( isset( $item['latest_date'] ) && ! empty( $item['latest_date'] ) ) {
			$stored_time = strtotime( get_date_from_gmt( $item['latest_date'] ) );
			$time_diff   = current_time( 'timestamp' ) - $stored_time;

			if ( $time_diff > ( 30 * 24 * 60 * 60 ) ) {
				$classes[] = 'sb-inactive-keyword';
			}
		}

		return implode( ' ', $classes );
	}

	/**
	 * Display a single row
	 *
	 * @param array $item The current item.
	 */
	public function single_row( $item ) {
		$row_class = $this->get_row_class( $item );
		echo '<tr class="' . esc_attr( $row_class ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Render a column for the list table.
	 *
	 * @param array  $item        The current item.
	 * @param string $column_name The name of the column to render.
	 *
	 * @return string The rendered column content.
	 */
	protected function column_default( $item, $column_name ) {
		// get the current post id

		global $wpdb;

		switch ( $column_name ) {
			case 'query':
				// Check if this is a cannibalizing keyword for indentation
				$keyword_id       = intval( $item['id'] );
				$competition_data = get_transient( 'sb_competition_' . $keyword_id );

				// Add indentation class for non-leader competing keywords
				$keyword_class = 'sb-keyword';
				if ( $competition_data && $competition_data['is_competing'] && ! $competition_data['is_leader'] ) {
					$keyword_class .= ' sb-cannibalizing-keyword';
				}

				$output = '<span class="' . $keyword_class . '">' . esc_html( $item[ $column_name ] ) . '</span>';

				$output .= '<div class="sbkbd">';
				// Add "Used" label if keyword is used in content
				if ( isset( $item['kw_used'] ) && 1 == $item['kw_used'] ) {
					$output .= sprintf(
						' <span class="label label-ok used" title="%s">%s</span>',
						esc_html__( 'This keyword is used in the content', 'seo-booster' ),
						esc_html__( 'In use', 'seo-booster' )
					);
				}

				// Add "Last visit: X days ago" label if over 14 days
				if ( isset( $item['latest_date'] ) && ! empty( $item['latest_date'] ) ) {
					$stored_time = strtotime( get_date_from_gmt( $item['latest_date'] ) );
					$time_diff   = current_time( 'timestamp' ) - $stored_time;

					if ( $time_diff > ( 14 * 24 * 60 * 60 ) ) {
						$days_diff = floor( $time_diff / 86400 );
						$output   .= ' <span class="sb-last-visit-label" title="Last visit for this keyword">' . esc_html( $days_diff ) . ' days ago</span>';
					}
				}

				// Add competition indicators
				$keyword_id       = intval( $item['id'] );
				$competition_data = get_transient( 'sb_competition_' . $keyword_id );

				if ( false === $competition_data ) {
					// First time - calculate and store
					$competition_data = $this->calculate_keyword_competition( $keyword_id );
					set_transient( 'sb_competition_' . $keyword_id, $competition_data, DAY_IN_SECONDS );
				}

				// Display competition indicators with enhanced tooltips
				if ( $competition_data['is_competing'] ) {
					if ( $competition_data['is_leader'] ) {
						$tooltip = sprintf(
							'<strong>Traffic Leader</strong><br>This page gets the most clicks for "%s"<br><br>📊 <strong>Your Performance:</strong><br>• %d clicks<br>• %d pages competing<br><br>💡 <em>This page is winning the traffic battle for this keyword</em>',
							esc_html( $item['query'] ),
							$competition_data['current_clicks'],
							$competition_data['page_count']
						);
						$output .= ' <span class="sb-competition-indicator sb-leader" data-tooltip-html="' . esc_attr( $tooltip ) . '">🟢</span>';
					} else {
						$tooltip = sprintf(
							'<strong>Keyword Cannibalization</strong><br>This page is competing with %d other pages for "%s"<br><br>📊 <strong>Performance Comparison:</strong><br>• Your clicks: %d<br>• Leader clicks: %d<br>• Gap: %d clicks<br><br>⚠️ <em>Consider consolidating or improving this page to avoid traffic cannibalization</em>',
							$competition_data['page_count'] - 1,
							esc_html( $item['query'] ),
							$competition_data['current_clicks'],
							$competition_data['leader_clicks'],
							$competition_data['leader_clicks'] - $competition_data['current_clicks']
						);
						$output .= ' <span class="sb-competition-indicator sb-cannibalizing" data-tooltip-html="' . esc_attr( $tooltip ) . '">🟡</span>';
					}
				}

				$output .= '</div>';

				return $output;

			case 'clicks':
			case 'impressions':
				return $item[ $column_name ];

			case 'ctr':
				$value = $item[ $column_name ];
				return 0.0 === (float) $value ? '0' : number_format_i18n( $value, 4 );

			case 'first_seen_date':
				$stored_time    = strtotime( get_date_from_gmt( $item[ $column_name ] ) );
				$formatted_date = date_i18n( get_option( 'date_format' ), $stored_time );
				return $formatted_date;

			case 'trends':
				return '<div class="uplot-chart-placeholder" data-sb-kwid="' . intval( $item['id'] ) . '">
					<div class="sb-chart-placeholder">
						<span class="dashicons dashicons-chart-line"></span>
						<span class="sb-placeholder-text">' . esc_html__( 'Loading chart…', 'seo-booster' ) . '</span>
					</div>
				</div>';

			case 'position':
				return '<span title="' . esc_attr( $item[ $column_name ] ) . '">' . number_format_i18n( $item[ $column_name ] ) . '</span>';

			case 'page':
				$url  = $item['page'];
				$path = wp_parse_url( $url, PHP_URL_PATH ); // Extract path from URL
				$path = trailingslashit( $path ); // Ensure path ends with a slash

				$post_id = url_to_postid( $url );

				$edit_link = false;
				if ( $post_id ) {
					$edit_link = get_edit_post_link( $post_id );
				}

				$parsed_url   = wp_parse_url( $url );
				$stripped_url = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';

				$return  = '';
				$return .= sprintf(
					'<a href="%1$s">%2$s</a>',
					$url,
					$stripped_url
				);
				if ( $edit_link ) {
					$return .= '<div class="row-actions"><span class="edit"><a href="' . esc_url( $edit_link ) . '">' . esc_html__( 'Edit', 'seo-booster' ) . '</a> | <span class="view"><a href="' . esc_url( $url ) . '" target="_blank" rel="bookmark">' . esc_html__( 'View', 'seo-booster' ) . '</a></span></div>';
				} else {
					$return .= '<div class="row-actions"><span class="view"><a href="' . esc_url( $url ) . '" target="_blank" rel="bookmark">' . esc_html__( 'View', 'seo-booster' ) . '</a></span></div>';

				}
				return $return;

			default:
				return esc_html( var_export( $item, true ) );
		}
	}

	/**
	 * Sanitize the order parameter.
	 *
	 * @param string $order The order parameter.
	 *
	 * @return string The sanitized order parameter.
	 */
	protected function sanitize_order( $order ) {
		if ( in_array( strtoupper( $order ), array( 'ASC', 'DESC' ), true ) ) {
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
	protected function prepare_if_not_empty( $query, $value ) {
		return ! empty( $value ) ? $this->db->prepare( $query, $value ) : '';
	}









	/**
	 * Calculate keyword competition data for a specific keyword ID
	 *
	 * @param int $keyword_id The keyword ID to analyze
	 * @return array Competition data
	 */
	private function calculate_keyword_competition( $keyword_id ) {
		global $wpdb;

		// First, get the keyword query text for this ID
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prefixed table; value uses %d placeholder.
		$keyword_query = $wpdb->prepare(
			"
			SELECT query FROM {$wpdb->prefix}sb2_query_keywords WHERE id = %d
		",
			$keyword_id
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared above.
		$keyword_text = $wpdb->get_var( $keyword_query );

		if ( empty( $keyword_text ) ) {
			return array(
				'is_competing'    => false,
				'is_leader'       => false,
				'page_count'      => 0,
				'calculated_date' => current_time( 'Y-m-d H:i:s' ),
			);
		}

		// Now find all pages using this same keyword query
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prefixed tables; value uses %s placeholder.
		$query = $wpdb->prepare(
			"
			SELECT 
				qk.id,
				qk.page,
				COALESCE(SUM(qkh.clicks), 0) as total_clicks
			FROM {$wpdb->prefix}sb2_query_keywords AS qk
			LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh 
				ON qk.id = qkh.query_keywords_id
			WHERE qk.query = %s
			GROUP BY qk.id, qk.page
			ORDER BY total_clicks DESC
		",
			$keyword_text
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared above.
		$pages_data = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $pages_data ) ) {
			return array(
				'is_competing'    => false,
				'is_leader'       => false,
				'page_count'      => 0,
				'calculated_date' => current_time( 'Y-m-d H:i:s' ),
			);
		}

		$page_count   = count( $pages_data );
		$is_competing = $page_count > 1;

		// Find current page data
		$current_page_data = null;
		foreach ( $pages_data as $page_data ) {
			if ( $page_data['id'] == $keyword_id ) {
				$current_page_data = $page_data;
				break;
			}
		}

		if ( ! $current_page_data ) {
			return array(
				'is_competing'    => false,
				'is_leader'       => false,
				'page_count'      => 0,
				'calculated_date' => current_time( 'Y-m-d H:i:s' ),
			);
		}

		$current_page   = $current_page_data['page'];
		$current_clicks = $current_page_data['total_clicks'];

		// Find the traffic leader
		$leader_data   = $pages_data[0];
		$leader_page   = $leader_data['page'];
		$leader_clicks = $leader_data['total_clicks'];

		$is_leader = ( $current_page === $leader_page );

		return array(
			'is_competing'    => $is_competing,
			'is_leader'       => $is_leader,
			'page_count'      => $page_count,
			'current_page'    => $current_page,
			'current_clicks'  => $current_clicks,
			'leader_page'     => $leader_page,
			'leader_clicks'   => $leader_clicks,
			'calculated_date' => current_time( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Prepare the items for the list table.
	 *
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- List table pagination, search, and sort reads only.

		$per_page = 50;

		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();

		$current_page = $this->get_pagenum();
		$offset       = ( $current_page * $per_page ) - $per_page;

		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		$is_exact_match = isset( $_REQUEST['exact_match'] ) && 1 == $_REQUEST['exact_match'];

		$search_sql  = '';
		$search_args = array();
		if ( ! empty( $search ) ) {
			if ( $is_exact_match ) {
				$search_sql    = ' AND qk.query = %s ';
				$search_args[] = $search;
			} else {
				$like        = '%' . $wpdb->esc_like( $search ) . '%';
				$search_sql  = ' AND (qk.query LIKE %s OR qk.page LIKE %s OR qk.first_seen_date LIKE %s OR qk.latest_date LIKE %s) ';
				$search_args = array( $like, $like, $like, $like );
			}
		}

		$lp_filter_query = '';
		$lp_filter       = isset( $_GET['filter_options'] ) ? filter_var( wp_unslash( $_GET['filter_options'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : '';

		if ( $lp_filter ) {
			switch ( $lp_filter ) {
				case 'new_keywords':
					$lp_filter_query = ' AND qk.latest_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ';
					break;
				case 'not_seen':
					$lp_filter_query = ' AND qk.latest_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) ';
					break;
				case 'keywords_used':
					$lp_filter_query = " AND qk.is_used_in_content = '1' ";
					break;
				case 'keywords_unused':
					$lp_filter_query = ' AND (qk.is_used_in_content = -1 OR qk.is_used_in_content IS NULL) ';
					break;
				case 'high_position':
					$lp_filter_query = ' AND qkh.position BETWEEN 1 AND 10 ';
					break;
				case 'medium_position':
					$lp_filter_query = ' AND qkh.position BETWEEN 11 AND 50 ';
					break;
				case 'low_position':
					$lp_filter_query = ' AND qkh.position > 50 ';
					break;
			}
		}

		// Sanitize and validate the order parameter
		$order = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';
		$order = strtoupper( $order ); // Convert to uppercase for comparison
		$order = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';

		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'impressions';
		$orderby = $this->sanitize_orderby( $orderby );

		$where_sql = "WHERE 1=1 {$search_sql} {$lp_filter_query}";

		$needs_history_join_for_count = in_array( $lp_filter, array( 'high_position', 'medium_position', 'low_position' ), true );

		if ( $needs_history_join_for_count ) {
			$total_filtered_query = "SELECT COUNT(DISTINCT qk.id) FROM {$wpdb->prefix}sb2_query_keywords AS qk LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh ON qk.id = qkh.query_keywords_id {$where_sql}";
		} else {
			$total_filtered_query = "SELECT COUNT(*) FROM {$wpdb->prefix}sb2_query_keywords AS qk {$where_sql}";
		}
		if ( empty( $search_args ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Filter fragments are internally built with sanitized values; no search input here.
			$total_filtered = $wpdb->get_var( $total_filtered_query );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $search_sql uses %s placeholders passed to $wpdb->prepare().
			$total_filtered = $wpdb->get_var( $wpdb->prepare( $total_filtered_query, $search_args ) );
		}

		$query = "SELECT qk.id, qk.query,
		qk.is_used_in_content as kw_used, 
		qk.page, 
                 COALESCE(SUM(qkh.clicks), 0) as clicks, 
                 COALESCE(SUM(qkh.impressions), 0) as impressions, 
                 COALESCE(AVG(qkh.position), 0) as position, 
                 COALESCE(AVG(qkh.ctr), 0) as ctr, 
                 MAX(qkh.date) as latest_date
			  FROM {$wpdb->prefix}sb2_query_keywords AS qk
			  LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh 
			  ON qk.id = qkh.query_keywords_id
			  {$where_sql}
			  GROUP BY qk.query, qk.page, qk.is_used_in_content
			  ORDER BY {$orderby} {$order} 
			  LIMIT %d, %d";

		$query_args = array_merge( $search_args, array( $offset, $per_page ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefixed tables, sanitized orderby/order and %s/%d placeholders passed to $wpdb->prepare().
		$data = $wpdb->get_results( $wpdb->prepare( $query, $query_args ), ARRAY_A );

		$this->items = $data;

		$this->set_pagination_args(
			array(
				'total_items' => $total_filtered,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_filtered / $per_page ),
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}
}
