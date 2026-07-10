<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SEO_Issues_List_Table
 *
 * Displays SEO possibilities in a WordPress list table format.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */
class SEO_Issues_List_Table extends \WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @since 6.1.26
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'issue',
				'plural'   => 'issues',
				'ajax'     => true,
			)
		);
	}

	/**
	 * Get columns.
	 *
	 * @since 6.1.26
	 * @return array Column definitions.
	 */
	public function get_columns() {
		return array(
			'url'     => __( 'URL', 'seo-booster' ),
			'score'   => __( 'SEO Score', 'seo-booster' ),
			'issues'  => __( 'Possibilities', 'seo-booster' ),
			'actions' => __( 'Actions', 'seo-booster' ),
		);
	}

	/**
	 * Get column info.
	 *
	 * @since 6.1.26
	 * @return array
	 */
	public function get_column_info() {
		if ( isset( $this->_column_headers ) ) {
			return $this->_column_headers;
		}

		$columns  = $this->get_columns();
		$hidden   = get_hidden_columns( $this->screen );
		$sortable = $this->get_sortable_columns();
		$primary  = $this->get_primary_column_name();

		$this->_column_headers = array( $columns, $hidden, $sortable, $primary );

		return $this->_column_headers;
	}

	/**
	 * Get unique possibility types for filter dropdown.
	 *
	 * @since 6.1.26
	 * @return array
	 */
	public function get_issue_types() {
		global $wpdb;

		$issues_table = $wpdb->prefix . 'sb2_seo_issues';
		$results      = $wpdb->get_results(
			"SELECT DISTINCT issue_key, message FROM {$issues_table} ORDER BY issue_key"
		);

		if ( ! is_array( $results ) ) {
			return array();
		}

		$issue_types = array();
		foreach ( $results as $row ) {
			$issue_types[ $row->issue_key ] = $row->message;
		}

		return $issue_types;
	}

	/**
	 * Get sortable columns.
	 *
	 * @since 6.1.26
	 * @return array Sortable column definitions.
	 */
	public function get_sortable_columns() {
		return array(
			'url'    => array( 'url', false ),
			'score'  => array( 'score', false ),
			'issues' => array( 'total_issues', false ),
		);
	}

	/**
	 * Prepare items for display.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public function prepare_items() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- List table pagination, search, and sort reads only.

		$per_page     = 50;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// Get filters from request
		$filters = array(
			'severity'       => isset( $_GET['severity'] ) ? sanitize_text_field( wp_unslash( $_GET['severity'] ) ) : '',
			'status'         => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '',
			'issue_type'     => isset( $_GET['issue_type'] ) ? sanitize_text_field( wp_unslash( $_GET['issue_type'] ) ) : '',
			'issue_category' => isset( $_GET['issue_category'] ) ? sanitize_text_field( wp_unslash( $_GET['issue_category'] ) ) : '',
			'search'         => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		);

		// Remove empty filters
		$filters = array_filter( $filters );

		// Get sort parameters
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'total_issues';
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';

		// Validate orderby
		$allowed_orderby = array( 'url', 'score', 'total_issues' );
		if ( ! in_array( $orderby, $allowed_orderby ) ) {
			$orderby = 'total_issues';
		}

		// Validate order
		if ( ! in_array( strtoupper( $order ), array( 'ASC', 'DESC' ) ) ) {
			$order = 'DESC';
		}

		// Get total count
		$total_items = SEO_Issues_Manager::get_urls_with_issues_count( $filters );

		// Get items with sorting
		$this->items = SEO_Issues_Manager::get_urls_with_issues( $filters, $per_page, $offset, $orderby, $order );

		// Set pagination
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
	 * Get total number of items.
	 *
	 * @since 6.1.26
	 * @param array $filters Filter conditions.
	 * @return int Total item count.
	 */
	private function get_total_items( $filters ) {
		global $wpdb;

		$issues_table = $wpdb->prefix . 'sb2_seo_issues';

		$where_conditions = array( '1=1' );
		$where_values     = array();

		// Severity filter
		if ( ! empty( $filters['severity'] ) ) {
			$where_conditions[] = 'severity = %s';
			$where_values[]     = $filters['severity'];
		}

		// Status filter - default to active possibilities if no status filter provided
		if ( ! empty( $filters['status'] ) ) {
			if ( $filters['status'] === 'active' ) {
				$where_conditions[] = '(user_status = %s OR user_status = %s OR user_status IS NULL)';
				$where_values[]     = 'active';
				$where_values[]     = '0';
			} else {
				$where_conditions[] = 'user_status = %s';
				$where_values[]     = $filters['status'];
			}
		} else {
			// Default to active possibilities only when no status filter is provided
			$where_conditions[] = '(user_status = %s OR user_status = %s OR user_status IS NULL)';
			$where_values[]     = 'active';
			$where_values[]     = '0';
		}

		// Issue type filter
		if ( ! empty( $filters['issue_type'] ) ) {
			$where_conditions[] = 'issue_key = %s';
			$where_values[]     = $filters['issue_type'];
		}

		// Search filter
		if ( ! empty( $filters['search'] ) ) {
			$where_conditions[] = 'message LIKE %s';
			$search_term        = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where_values[]     = $search_term;
		}

		$where_clause = implode( ' AND ', $where_conditions );

		$sql = "SELECT COUNT(*) FROM {$issues_table} WHERE {$where_clause}";

		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, $where_values );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get items for display.
	 *
	 * @since 6.1.26
	 * @param array $filters Filter conditions.
	 * @param int $per_page Items per page.
	 * @param int $offset Offset for pagination.
	 * @return array Items data.
	 */
	private function get_items( $filters, $per_page, $offset ) {
		global $wpdb;

		$issues_table = $wpdb->prefix . 'sb2_seo_issues';

		$where_conditions = array( '1=1' );
		$where_values     = array();

		// Severity filter
		if ( ! empty( $filters['severity'] ) ) {
			$where_conditions[] = 'severity = %s';
			$where_values[]     = $filters['severity'];
		}

		// Status filter - default to active possibilities if no status filter provided
		if ( ! empty( $filters['status'] ) ) {
			if ( $filters['status'] === 'active' ) {
				$where_conditions[] = '(user_status = %s OR user_status = %s OR user_status IS NULL)';
				$where_values[]     = 'active';
				$where_values[]     = '0';
			} else {
				$where_conditions[] = 'user_status = %s';
				$where_values[]     = $filters['status'];
			}
		} else {
			// Default to active possibilities only when no status filter is provided
			$where_conditions[] = '(user_status = %s OR user_status = %s OR user_status IS NULL)';
			$where_values[]     = 'active';
			$where_values[]     = '0';
		}

		// Issue type filter
		if ( ! empty( $filters['issue_type'] ) ) {
			$where_conditions[] = 'issue_key = %s';
			$where_values[]     = $filters['issue_type'];
		}

		// Search filter
		if ( ! empty( $filters['search'] ) ) {
			$where_conditions[] = 'message LIKE %s';
			$search_term        = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where_values[]     = $search_term;
		}

		$where_clause = implode( ' AND ', $where_conditions );

		// Get sort order
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'severity';
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC';

		// Validate orderby
		$allowed_orderby = array( 'message', 'severity', 'user_status', 'created_at' );
		if ( ! in_array( $orderby, $allowed_orderby ) ) {
			$orderby = 'severity';
		}

		// Validate order
		if ( ! in_array( strtoupper( $order ), array( 'ASC', 'DESC' ) ) ) {
			$order = 'DESC';
		}

		$sql = "SELECT * FROM {$issues_table} 
                WHERE {$where_clause} 
                ORDER BY {$orderby} {$order}
                LIMIT %d OFFSET %d";

		$values = array_merge( $where_values, array( $per_page, $offset ) );
		$sql    = $wpdb->prepare( $sql, $values );

		return $wpdb->get_results( $sql );
	}

	/**
	 * Display URL column.
	 *
	 * @since 6.1.26
	 * @param object $item Current item.
	 * @return string URL HTML.
	 */
	public function column_url( $item ) {
		$url   = esc_url( $item->url );
		$title = ! empty( $item->post_title ) ? $item->post_title : basename( $item->url );
		$title = esc_html( $title );

		// Try to get edit link
		$edit_link = null;
		if ( $item->object_id && $item->object_type === 'post' ) {
			$edit_link = get_edit_post_link( $item->object_id );
		} elseif ( $item->object_id && $item->object_type === 'term' ) {
			$edit_link = get_edit_term_link( $item->object_id );
		}

		$links = array();

		if ( $edit_link ) {
			$links[] = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $edit_link ), __( 'Edit', 'seo-booster' ) );
		}

		$links[] = sprintf( '<a href="%s" target="_blank">%s</a>', $url, __( 'View', 'seo-booster' ) );

		// Check for GSC inspection data
		$gsc_badges = '';

		$html = sprintf(
			'<strong><a href="%s" target="_blank">%s</a></strong>%s<div class="row-actions">%s</div>',
			$url,
			$title,
			$gsc_badges ? '<div class="sb-gsc-status-badges">' . $gsc_badges . '</div>' : '',
			implode( ' | ', $links )
		);

		// Add expandable indicator
		$html .= '<div class="sb-url-expand" data-url-id="' . $item->id . '">';
		$html .= '<span class="dashicons dashicons-arrow-down-alt2"></span> ';
		$html .= sprintf( _n( '%d possibility', '%d possibilities', $item->total_issues, 'seo-booster' ), $item->total_issues );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Display score column.
	 *
	 * @since 7.0.3
	 * @param object $item Current item.
	 * @return string Score HTML.
	 */
	public function column_score( $item ) {
		$score = isset( $item->score ) ? intval( $item->score ) : null;

		if ( $score === null ) {
			return '<span class="sb-seo-score-null">—</span>';
		}

		// Determine score class based on thresholds
		$score_class = 'sb-seo-score';
		if ( $score >= 80 ) {
			$score_class .= ' sb-seo-score-good';
		} elseif ( $score >= 60 ) {
			$score_class .= ' sb-seo-score-medium';
		} else {
			$score_class .= ' sb-seo-score-poor';
		}

		return sprintf(
			'<span class="%s" title="%s">%d%%</span>',
			esc_attr( $score_class ),
			esc_attr__( 'SEO Analysis Score', 'seo-booster' ),
			esc_html( $score )
		);
	}

	/**
	 * Display issues column with all counts combined.
	 *
	 * @since 6.1.26
	 * @param object $item Current item.
	 * @return string Issues HTML.
	 */
	public function column_issues( $item ) {
		$total        = (int) $item->total_issues;
		$critical     = (int) $item->critical_count;
		$high         = (int) $item->high_count;
		$medium       = (int) $item->medium_count;
		$low          = (int) $item->low_count;
		$opportunity  = isset( $item->opportunity_count ) ? (int) $item->opportunity_count : 0;

		$html  = '<div class="sb-issues-column">';
		$html .= sprintf( '<strong class="sb-issues-total">%d</strong>', $total );

		$parts = array();
		if ( $critical > 0 ) {
			$parts[] = sprintf( '<span class="sb-severity-badge sb-severity-critical">Important: %d</span>', $critical );
		}
		if ( $high > 0 ) {
			$parts[] = sprintf( '<span class="sb-severity-badge sb-severity-high">High: %d</span>', $high );
		}
		if ( $medium > 0 ) {
			$parts[] = sprintf( '<span class="sb-severity-badge sb-severity-medium">Medium: %d</span>', $medium );
		}
		if ( $low > 0 ) {
			$parts[] = sprintf( '<span class="sb-severity-badge sb-severity-low">Low: %d</span>', $low );
		}
		if ( $opportunity > 0 ) {
			$parts[] = sprintf( '<span class="sb-severity-badge sb-severity-opportunity">Suggestions: %d</span>', $opportunity );
		}

		if ( ! empty( $parts ) ) {
			$html .= implode( ' ', $parts );
		}

		$html .= '</div>';

		return $html;
	}


	/**
	 * Display actions column.
	 *
	 * @since 6.1.26
	 * @param object $item Current item.
	 * @return string Actions HTML.
	 */
	public function column_actions( $item ) {
		$html  = '<div class="sb-url-actions">';
		$html .= sprintf(
			'<button type="button" class="button button-small sb-expand-url" data-url-id="%d">%s</button>',
			$item->id,
			__( 'View Issues', 'seo-booster' )
		);
		$html .= '</div>';

		return $html;
	}



	/**
	 * Display when no items found.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	/**
	 * Display the rows of records in the table.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public function display_rows() {
		if ( empty( $this->items ) ) {
			$this->no_items();
			return;
		}

		foreach ( $this->items as $item ) {
			$this->single_row( $item );
		}
	}

	/**
	 * Display a single row.
	 *
	 * @since 6.1.26
	 * @param object $item The current item.
	 * @return void
	 */
	public function single_row( $item ) {
		$columns = $this->get_columns();

		// Add transition class and data attributes for URL expansion
		$row_class = 'sb-url-row';
		$row_data  = 'data-url-id="' . $item->id . '"';

		echo '<tr class="' . $row_class . '" ' . $row_data . '>';
		foreach ( $columns as $column_name => $column_display_name ) {
			$class      = "class='$column_name column-$column_name'";
			$attributes = $class;

			echo "<td $attributes>";
			echo $this->column_default( $item, $column_name );
			echo '</td>';
		}
		echo '</tr>';
	}

	/**
	 * Handle default column display.
	 *
	 * @since 6.1.26
	 * @param object $item Current item.
	 * @param string $column_name Column name.
	 * @return string Column output.
	 */
	public function column_default( $item, $column_name ) {
		// Try to call custom column method
		$method_name = 'column_' . $column_name;
		if ( method_exists( $this, $method_name ) ) {
			return call_user_func( array( $this, $method_name ), $item );
		}

		// Fallback to parent method
		return parent::column_default( $item, $column_name );
	}

	/**
	 * Print column headers.
	 *
	 * @since 6.1.26
	 * @param bool $with_id Whether to set the id attribute or not.
	 * @return void
	 */
	public function print_column_headers( $with_id = true ) {
		list($columns, $hidden, $sortable, $primary) = $this->get_column_info();

		$current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		$current_url = remove_query_arg( 'paged', $current_url );

		if ( isset( $_GET['orderby'] ) ) {
			$current_orderby = $_GET['orderby'];
		} else {
			$current_orderby = '';
		}

		if ( isset( $_GET['order'] ) && 'desc' === $_GET['order'] ) {
			$current_order = 'desc';
		} else {
			$current_order = 'asc';
		}

		foreach ( $columns as $column_key => $column_display_name ) {
			$class = array( 'manage-column', "column-$column_key" );

			if ( in_array( $column_key, $hidden ) ) {
				$class[] = 'hidden';
			}

			if ( in_array( $column_key, array( 'posts', 'comments', 'links' ) ) ) {
				$class[] = 'num';
			}

			if ( $column_key === $primary ) {
				$class[] = 'column-primary';
			}

			if ( isset( $sortable[ $column_key ] ) ) {
				list($orderby, $desc_first) = $sortable[ $column_key ];

				if ( $current_orderby === $orderby ) {
					$order   = 'asc' === $current_order ? 'desc' : 'asc';
					$class[] = 'sorted';
					$class[] = $current_order;
				} else {
					$order   = $desc_first ? 'desc' : 'asc';
					$class[] = 'sortable';
					$class[] = $desc_first ? 'asc' : 'desc';
				}

				$column_display_name = '<a href="' . esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ) . '"><span>' . $column_display_name . '</span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a>';
			}

			$tag   = 'th';
			$scope = 'scope="col"';
			$id    = $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) ) {
				$class = "class='" . join( ' ', $class ) . "'";
			}

			echo "<$tag $scope $id $class>$column_display_name</$tag>";
		}
	}

	/**
	 * Display the table.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public function display() {
		$singular = $this->_args['singular'];

		$this->display_tablenav( 'top' );

		$this->screen->render_screen_reader_content( 'heading_list' );
		?>
		<table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>">
			<thead>
			<tr>
				<?php $this->print_column_headers(); ?>
			</tr>
			</thead>

			<tbody id="the-list"
			<?php
			if ( $singular ) {
				echo " data-wp-lists='list:$singular'";
			}
			?>
			>
				<?php $this->display_rows_or_placeholder(); ?>
			</tbody>

			<tfoot>
			<tr>
				<?php $this->print_column_headers( false ); ?>
			</tr>
			</tfoot>

		</table>
		<?php
		$this->display_tablenav( 'bottom' );
	}

	/**
	 * Display extra tablenav.
	 *
	 * @since 6.1.26
	 * @param string $which Top or bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( $which === 'top' ) {
			$issue_types = $this->get_issue_types();
			if ( ! is_array( $issue_types ) ) {
				$issue_types = array();
			}
			$current_issue_type = isset( $_GET['issue_type'] ) ? $_GET['issue_type'] : '';
			$current_category   = isset( $_GET['issue_category'] ) ? sanitize_text_field( wp_unslash( $_GET['issue_category'] ) ) : '';
			$current_severity   = isset( $_GET['severity'] ) ? $_GET['severity'] : '';
			$current_status     = isset( $_GET['status'] ) ? $_GET['status'] : '';
			?>
			<div class="alignleft actions">
				<select name="issue_type" id="issue_type_filter">
					<option value=""><?php _e( 'All Possibility Types', 'seo-booster' ); ?></option>
					<?php foreach ( $issue_types as $key => $message ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_issue_type, $key ); ?>>
							<?php echo esc_html( $message ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<select name="issue_category" id="issue_category_filter">
					<option value=""><?php esc_html_e( 'All categories', 'seo-booster' ); ?></option>
					<option value="ai_readiness" <?php selected( $current_category, 'ai_readiness' ); ?>><?php esc_html_e( 'AI Readiness', 'seo-booster' ); ?></option>
				</select>
				<?php
				/*
				<select name="severity" id="severity_filter">
					<option value=""><?php _e('All Severities', 'seo-booster'); ?></option>
					<option value="critical" <?php selected($current_severity, 'critical'); ?>><?php _e('Critical', 'seo-booster'); ?></option>
					<option value="high" <?php selected($current_severity, 'high'); ?>><?php _e('High', 'seo-booster'); ?></option>
					<option value="medium" <?php selected($current_severity, 'medium'); ?>><?php _e('Medium', 'seo-booster'); ?></option>
					<option value="low" <?php selected($current_severity, 'low'); ?>><?php _e('Low', 'seo-booster'); ?></option>
				</select>

				<select name="status" id="status_filter">
					<option value=""><?php _e('All Statuses', 'seo-booster'); ?></option>
					<option value="active" <?php selected($current_status, 'active'); ?>><?php _e('Active', 'seo-booster'); ?></option>
					<option value="fixed" <?php selected($current_status, 'fixed'); ?>><?php _e('Fixed', 'seo-booster'); ?></option>
					<option value="ignored_temp" <?php selected($current_status, 'ignored_temp'); ?>><?php _e('Ignored', 'seo-booster'); ?></option>
				</select>
				 */
				?>
				<input type="submit" class="button" value="<?php _e( 'Filter', 'seo-booster' ); ?>" />
			</div>
			<?php
		}
	}

	public function no_items() {
		_e( 'No SEO issues found.', 'seo-booster' );
	}


	/**
	 * Display filter controls.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	private function display_filters() {
		$current_severity = isset( $_GET['severity'] ) ? sanitize_text_field( $_GET['severity'] ) : '';
		$current_status   = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$current_search   = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

		echo '<div class="alignleft actions">';

		// Severity filter
		echo '<select name="severity" id="filter-severity">';
		echo '<option value="">' . __( 'All Severities', 'seo-booster' ) . '</option>';
		$severities = array(
			'critical' => __( 'Critical', 'seo-booster' ),
			'high'     => __( 'High', 'seo-booster' ),
			'medium'   => __( 'Medium', 'seo-booster' ),
			'low'      => __( 'Low', 'seo-booster' ),
		);
		foreach ( $severities as $value => $label ) {
			printf( '<option value="%s"%s>%s</option>', $value, selected( $current_severity, $value, false ), $label );
		}
		echo '</select>';

		// Status filter
		echo '<select name="status" id="filter-status">';
		echo '<option value="">' . __( 'All Statuses', 'seo-booster' ) . '</option>';
		$statuses = array(
			'active'            => __( 'Active', 'seo-booster' ),
			'fixed'             => __( 'Fixed', 'seo-booster' ),
			'ignored_temp'      => __( 'Ignored (Temp)', 'seo-booster' ),
			'ignored_permanent' => __( 'Ignored (Perm)', 'seo-booster' ),
		);
		foreach ( $statuses as $value => $label ) {
			printf( '<option value="%s"%s>%s</option>', $value, selected( $current_status, $value, false ), $label );
		}
		echo '</select>';

		// Search box
		printf(
			'<input type="text" name="s" id="search-input" value="%s" placeholder="%s" />',
			esc_attr( $current_search ),
			__( 'Search issues, URLs...', 'seo-booster' )
		);

		submit_button( __( 'Filter', 'seo-booster' ), 'button', 'filter_action', false );

		// Clear filters button
		if ( $current_severity || $current_status || $current_search ) {
			$clear_url = remove_query_arg( array( 'severity', 'status', 's' ) );
			printf( '<a href="%s" class="button">%s</a>', esc_url( $clear_url ), __( 'Clear Filters', 'seo-booster' ) );
		}

		echo '</div>';
	}
}
