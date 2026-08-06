<?php

namespace Cleverplugins\SEOBooster;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Class SEO_Issues_Ajax
 *
 * Handles AJAX requests for the SEO Possibilities page.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */
class SEO_Issues_Ajax {
    /**
     * Initialize AJAX handlers.
     *
     * @since 6.1.26
     * @return void
     */
    public static function init() {
        add_action( 'wp_ajax_sb_analyze_direct', array(__CLASS__, 'ajax_analyze_direct') );
        add_action( 'wp_ajax_sb_get_analysis_progress', array(__CLASS__, 'ajax_get_analysis_progress') );
        add_action( 'wp_ajax_sb_search_possibilities', array(__CLASS__, 'ajax_search_possibilities') );
        add_action( 'wp_ajax_sb_stop_analysis', array(__CLASS__, 'ajax_stop_analysis') );
        add_action( 'wp_ajax_sb_get_current_analysis', array(__CLASS__, 'ajax_get_current_analysis') );
        add_action( 'wp_ajax_sb_get_url_issues', array(__CLASS__, 'ajax_get_url_possibilities') );
        add_action( 'wp_ajax_sb_run_sitewide_analysis', array(__CLASS__, 'ajax_run_sitewide_analysis') );
        add_action( 'wp_ajax_sb_update_issue_status', array(__CLASS__, 'ajax_update_issue_status') );
    }

    /**
     * AJAX handler for direct analysis (immediate execution).
     *
     * @since 6.1.26
     * @return void
     */
    public static function ajax_analyze_direct() {
        Utils::log( 'AJAX analyze_direct called', 5 );
        check_ajax_referer( 'sb_seo_issues_nonce', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            Utils::log( 'Permission denied for analyze_direct', 2 );
            wp_send_json_error( array(
                'message' => __( 'Permission denied', 'seo-booster' ),
            ) );
        }
        $batch_size = get_option( 'seobooster_seo_analysis_batch_size', 1 );
        // Use configurable batch size
        // Get current stats BEFORE processing
        $stats_before = SEO_Issues_Manager::get_analysis_stats();
        $urls = SEO_Issues_Manager::get_publishable_urls();
        $urls_to_analyze_count = count( $urls );
        Utils::log( 'Found ' . $urls_to_analyze_count . ' URLs to analyze', 5 );
        if ( empty( $urls ) ) {
            Utils::log( 'No URLs to analyze - all content analyzed', 5 );
            wp_send_json_success( array(
                'message'             => __( 'All content has been analyzed!', 'seo-booster' ),
                'completed'           => true,
                'remaining'           => 0,
                'stats'               => $stats_before,
                'analyzed'            => $stats_before['total_analyzed'],
                'pending'             => $stats_before['total_pending'],
                'progress_percentage' => 100,
            ) );
        }
        // Calculate total URLs: already analyzed + URLs that need to be analyzed
        $total_urls = $stats_before['total_analyzed'] + $urls_to_analyze_count;
        // Take first batch and execute immediately
        $batch = array_slice( $urls, 0, $batch_size );
        $processed = 0;
        $current_url = '';
        $current_title = '';
        foreach ( $batch as $url_data ) {
            try {
                $current_url = $url_data['url'];
                $current_title = $url_data['title'] ?? basename( parse_url( $current_url, PHP_URL_PATH ) );
                // Execute analysis directly instead of scheduling (logs one success/failure line).
                seobooster2::process_seo_analysis_for_url( $url_data );
                ++$processed;
            } catch ( \Exception $e ) {
                Utils::log( sprintf( 'SEO analysis failed for %s: %s', $current_url, $e->getMessage() ), 2 );
            }
        }
        // Recalculate remaining URLs after processing
        $remaining_urls = SEO_Issues_Manager::get_publishable_urls();
        $remaining = count( $remaining_urls );
        // Get current stats for the response AFTER processing
        $stats = SEO_Issues_Manager::get_analysis_stats();
        // Calculate progress percentage: analyzed / total (clamped to 0-100)
        // Total = analyzed before + URLs that needed analysis
        if ( $total_urls > 0 ) {
            $progress_percentage = round( $stats['total_analyzed'] / $total_urls * 100 );
            // Clamp to 0-100 to prevent display issues
            $progress_percentage = max( 0, min( 100, $progress_percentage ) );
        } else {
            $progress_percentage = 100;
        }
        Utils::log( 'Analysis batch complete. Processed: ' . $processed . ', Remaining: ' . $remaining, 5 );
        wp_send_json_success( array(
            'message'             => sprintf( __( 'Directly analyzed %1$d URLs. %2$d remaining.', 'seo-booster' ), $processed, $remaining ),
            'processed'           => $processed,
            'remaining'           => $remaining,
            'completed'           => $remaining <= 0,
            'stats'               => $stats,
            'analyzed'            => $stats['total_analyzed'],
            'pending'             => $stats['total_pending'],
            'progress_percentage' => $progress_percentage,
            'current_url'         => $current_url,
            'current_title'       => $current_title,
        ) );
    }

    /**
     * AJAX handler for getting analysis progress.
     *
     * @since 6.1.26
     * @return void
     */
    public static function ajax_get_analysis_progress() {
        check_ajax_referer( 'sb_seo_issues_nonce', 'nonce' );
        if ( !current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied', 'seo-booster' ),
            ) );
        }
        $stats = SEO_Issues_Manager::get_analysis_stats();
        $urls = SEO_Issues_Manager::get_publishable_urls();
        $total_urls = count( $urls );
        // Calculate progress percentage based on actual analyzed vs total
        $progress_percentage = 0;
        if ( $total_urls > 0 ) {
            $progress_percentage = round( $stats['total_analyzed'] / $total_urls * 100 );
        } elseif ( $stats['total_analyzed'] > 0 ) {
            $progress_percentage = 100;
            // If no pending URLs but we have analyzed some, we're done
        }
        // Debug: Log the stats to see what's happening
        Utils::log( 'Analysis progress stats: ' . wp_json_encode( $stats ), 5 );
        Utils::log( 'Total URLs: ' . $total_urls . ', Progress: ' . $progress_percentage . '%', 5 );
        // Get current analysis status
        $current_analysis = null;
        $pending_count = 0;
        if ( function_exists( 'as_has_scheduled_action' ) ) {
            global $wpdb;
            // Validate table name
            $actions_table = $wpdb->prefix . 'actionscheduler_actions';
            $allowed_tables = array($wpdb->prefix . 'actionscheduler_actions');
            if ( in_array( $actions_table, $allowed_tables, true ) ) {
                // Get currently running analysis (if any)
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
                $running_actions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$actions_table} \n                         WHERE hook = %s \n                         AND status = %s \n                         ORDER BY scheduled_date_gmt ASC \n                         LIMIT 1", 'sb_analyze_seo_for_url', 'in-progress' ) );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                if ( !empty( $running_actions ) ) {
                    $action = $running_actions[0];
                    $args = maybe_unserialize( $action->args );
                    if ( is_array( $args ) && isset( $args[0] ) ) {
                        $args = $args[0];
                    }
                    $current_analysis = array(
                        'url'         => ( isset( $args['url'] ) ? esc_url( $args['url'] ) : '' ),
                        'object_id'   => ( isset( $args['object_id'] ) ? intval( $args['object_id'] ) : null ),
                        'object_type' => ( isset( $args['object_type'] ) ? sanitize_text_field( $args['object_type'] ) : null ),
                        'started_at'  => $action->scheduled_date_gmt,
                    );
                }
                // Get pending count
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
                $pending_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$actions_table} \n                         WHERE hook = %s \n                         AND status = %s", 'sb_analyze_seo_for_url', 'pending' ) );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            } else {
                $pending_count = 0;
            }
        }
        // Get recent analysis activity
        global $wpdb;
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        // Validate table names
        $allowed_tables = array($wpdb->prefix . 'sb2_seo_analysis', $wpdb->prefix . 'sb2_seo_urls');
        if ( in_array( $analysis_table, $allowed_tables, true ) && in_array( $urls_table, $allowed_tables, true ) ) {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
            $recent_analysis = $wpdb->get_results( $wpdb->prepare( "SELECT u.url, u.post_title, a.analyzed_at \n                     FROM {$analysis_table} a\n                     JOIN {$urls_table} u ON a.url_id = u.id\n                     WHERE a.status = %s \n                     ORDER BY a.analyzed_at DESC \n                     LIMIT 5", 'analyzed' ) );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        } else {
            $recent_analysis = array();
        }
        $recent_items = array();
        foreach ( $recent_analysis as $item ) {
            $title = ( !empty( $item->post_title ) ? esc_html( $item->post_title ) : esc_html( basename( $item->url ) ) );
            $recent_items[] = array(
                'title' => $title,
                'url'   => esc_url( $item->url ),
                'time'  => esc_html( human_time_diff( strtotime( $item->analyzed_at ), current_time( 'timestamp' ) ) . ' ago' ),
            );
        }
        wp_send_json_success( array(
            'stats'               => $stats,
            'total_urls'          => $total_urls,
            'analyzed'            => $stats['total_analyzed'],
            'pending'             => $stats['total_pending'],
            'recent_analysis'     => $recent_items,
            'progress_percentage' => $progress_percentage,
            'current_analysis'    => $current_analysis,
            'pending_count'       => (int) $pending_count,
            'is_running'          => !empty( $current_analysis ),
        ) );
    }

    /**
     * AJAX handler for searching issues.
     *
     * @since 6.1.26
     * @return void
     */
    public static function ajax_search_possibilities() {
        check_ajax_referer( 'sb_seo_issues_nonce', 'nonce' );
        if ( !current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied', 'seo-booster' ),
            ) );
        }
        $search_term = ( isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '' );
        $severity = ( isset( $_POST['severity'] ) ? sanitize_text_field( $_POST['severity'] ) : '' );
        $status = ( isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '' );
        $filters = array_filter( array(
            'search'   => $search_term,
            'severity' => $severity,
            'status'   => $status,
        ) );
        $issues = SEO_Issues_Manager::get_issues( $filters );
        // Format results for display
        $formatted_issues = array();
        foreach ( $issues as $issue ) {
            $formatted_issues[] = array(
                'id'       => intval( $issue->id ),
                'message'  => esc_html( $issue->message ),
                'url'      => esc_url( $issue->url ),
                'title'    => ( !empty( $issue->post_title ) ? esc_html( $issue->post_title ) : esc_html( basename( $issue->url ) ) ),
                'severity' => esc_html( $issue->severity ),
                'status'   => esc_html( $issue->user_status ),
            );
        }
        wp_send_json_success( array(
            'issues'        => $formatted_issues,
            'possibilities' => $formatted_issues,
            'count'         => count( $formatted_issues ),
        ) );
    }

    /**
     * AJAX handler for stopping analysis.
     *
     * @since 6.1.26
     * @return void
     */
    public static function ajax_stop_analysis() {
        check_ajax_referer( 'sb_seo_issues_nonce', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied', 'seo-booster' ),
            ) );
        }
        // Cancel all pending SEO analysis actions
        if ( function_exists( 'as_unschedule_all_actions' ) ) {
            $cancelled = as_unschedule_all_actions( 'sb_analyze_seo_for_url', array(), 'seo-booster' );
            wp_send_json_success( array(
                'message'   => sprintf( __( 'Analysis stopped. %d pending actions cancelled.', 'seo-booster' ), $cancelled ),
                'cancelled' => $cancelled,
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Action Scheduler not available', 'seo-booster' ),
            ) );
        }
    }

    /**
     * AJAX handler for getting current analysis status.
     *
     * @since 6.1.26
     * @return void
     */
    public static function ajax_get_current_analysis() {
        check_ajax_referer( 'sb_seo_issues_nonce', 'nonce' );
        if ( !current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied', 'seo-booster' ),
            ) );
        }
        $current_analysis = null;
        $pending_count = 0;
        if ( function_exists( 'as_has_scheduled_action' ) ) {
            global $wpdb;
            // Validate table name
            $actions_table = $wpdb->prefix . 'actionscheduler_actions';
            $allowed_tables = array($wpdb->prefix . 'actionscheduler_actions');
            if ( in_array( $actions_table, $allowed_tables, true ) ) {
                // Get currently running analysis (if any)
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
                $running_actions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$actions_table} \n                         WHERE hook = %s \n                         AND status = %s \n                         ORDER BY scheduled_date_gmt ASC \n                         LIMIT 1", 'sb_analyze_seo_for_url', 'in-progress' ) );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                if ( !empty( $running_actions ) ) {
                    $action = $running_actions[0];
                    $args = maybe_unserialize( $action->args );
                    if ( is_array( $args ) && isset( $args[0] ) ) {
                        $args = $args[0];
                    }
                    $current_analysis = array(
                        'url'         => ( isset( $args['url'] ) ? esc_url( $args['url'] ) : '' ),
                        'object_id'   => ( isset( $args['object_id'] ) ? intval( $args['object_id'] ) : null ),
                        'object_type' => ( isset( $args['object_type'] ) ? sanitize_text_field( $args['object_type'] ) : null ),
                        'started_at'  => $action->scheduled_date_gmt,
                    );
                }
                // Get pending count
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
                $pending_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$actions_table} \n                         WHERE hook = %s \n                         AND status = %s", 'sb_analyze_seo_for_url', 'pending' ) );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            } else {
                $pending_count = 0;
            }
        }
        wp_send_json_success( array(
            'current_analysis' => $current_analysis,
            'pending_count'    => (int) $pending_count,
            'is_running'       => !empty( $current_analysis ),
        ) );
    }

    /**
     * AJAX handler for getting issues for a specific URL.
     *
     * @since 6.1.26
     * @return void
     */
    public static function ajax_get_url_possibilities() {
        check_ajax_referer( 'sb_seo_issues_nonce', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied', 'seo-booster' ),
            ) );
        }
        $url_id = ( isset( $_POST['url_id'] ) ? intval( $_POST['url_id'] ) : 0 );
        if ( !$url_id ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid URL ID', 'seo-booster' ),
            ) );
        }
        $buckets = SEO_Issues_Manager::get_issues_for_url( $url_id );
        $edit_url = self::get_edit_url_for_url_id( $url_id );
        $issues = self::enrich_issue_rows( $buckets['issues'], $edit_url );
        $opps = self::enrich_issue_rows( $buckets['opportunities'], $edit_url );
        wp_send_json_success( array(
            'issues'        => $issues,
            'opportunities' => $opps,
            'possibilities' => array_merge( $issues, $opps ),
            'edit_url'      => $edit_url,
        ) );
    }

    /**
     * AJAX: mark a possibility as done or ignored.
     *
     * @since 7.4.1
     * @return void
     */
    public static function ajax_update_issue_status() {
        check_ajax_referer( 'sb_seo_issues_nonce', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied', 'seo-booster' ),
            ) );
        }
        $can_triage = false;
        if ( function_exists( __NAMESPACE__ . '\\seobooster_fs' ) ) {
        }
        if ( !$can_triage ) {
            wp_send_json_error( array(
                'message' => __( 'Possibilities triage requires SEO Booster Pro.', 'seo-booster' ),
            ) );
        }
        $issue_id = ( isset( $_POST['issue_id'] ) ? (int) $_POST['issue_id'] : 0 );
        $status = ( isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '' );
        if ( $issue_id <= 0 || !in_array( $status, array('fixed', 'ignored_permanent', 'active'), true ) ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid request', 'seo-booster' ),
            ) );
        }
        $ok = SEO_Issues_Manager::update_issue_user_status( $issue_id, $status );
        if ( !$ok ) {
            wp_send_json_error( array(
                'message' => __( 'Could not update status', 'seo-booster' ),
            ) );
        }
        wp_send_json_success( array(
            'issue_id'   => $issue_id,
            'status'     => $status,
            'stats'      => SEO_Issues_Manager::get_analysis_stats(),
            'urls_count' => SEO_Issues_Manager::get_urls_with_issues_count(),
        ) );
    }

    /**
     * Resolve edit link for a stored URL row.
     *
     * @param int $url_id URL ID.
     * @return string Empty if none.
     */
    private static function get_edit_url_for_url_id( $url_id ) {
        global $wpdb;
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prefixed internal table.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT object_id, object_type FROM {$urls_table} WHERE id = %d", (int) $url_id ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( !$row || empty( $row->object_id ) ) {
            return '';
        }
        if ( 'post' === $row->object_type ) {
            $link = get_edit_post_link( (int) $row->object_id, 'raw' );
            return ( $link ? $link : '' );
        }
        if ( 'term' === $row->object_type ) {
            $link = get_edit_term_link( (int) $row->object_id );
            return ( $link && !is_wp_error( $link ) ? $link : '' );
        }
        return '';
    }

    /**
     * Attach tool deep-link and edit_url to issue rows.
     *
     * @param array  $rows     Issue objects/arrays.
     * @param string $edit_url Edit URL for the parent page.
     * @return array
     */
    private static function enrich_issue_rows( $rows, $edit_url ) {
        $out = array();
        foreach ( (array) $rows as $row ) {
            $item = ( is_object( $row ) ? (array) $row : $row );
            $key = ( isset( $item['key'] ) ? (string) $item['key'] : (string) ($item['issue_key'] ?? '') );
            $tool = SEO_Issues_Manager::get_tool_for_issue_key( $key );
            $item['issue_key'] = $key;
            $item['edit_url'] = $edit_url;
            $item['tool'] = $tool;
            $out[] = $item;
        }
        return $out;
    }

    /**
     * AJAX handler for running sitewide SEO analysis.
     *
     * @since 7.0.0
     * @return void
     */
    public static function ajax_run_sitewide_analysis() {
        check_ajax_referer( 'sb_seo_issues_nonce', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied', 'seo-booster' ),
            ) );
        }
        try {
            // Run sitewide analysis
            $sitewide_analysis = new \Cleverplugins\SEOBooster\SEO_Sitewide_Analysis();
            $results = $sitewide_analysis->analyze();
            // Save results to database
            $analysis_id = SEO_Issues_Manager::save_sitewide_analysis_to_db( $results );
            if ( $analysis_id === false ) {
                wp_send_json_error( array(
                    'message' => __( 'Failed to save sitewide analysis results', 'seo-booster' ),
                ) );
            }
            update_option( 'sb_sitewide_analysis_run', true );
            $sitewide_stats = SEO_Issues_Manager::get_sitewide_stats();
            $sitewide_issues = SEO_Issues_Manager::get_sitewide_issues();
            $page_stats = SEO_Issues_Manager::get_analysis_stats();
            $urls_count = SEO_Issues_Manager::get_urls_with_issues_count();
            $last_run = '';
            $last_run_display = __( 'Not run yet', 'seo-booster' );
            if ( !empty( $sitewide_issues[0]->analyzed_at ) ) {
                $last_run = $sitewide_issues[0]->analyzed_at;
                $last_run_display = sprintf( 
                    /* translators: %s: human-readable date/time */
                    __( 'Last run: %s', 'seo-booster' ),
                    mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_run )
                 );
            }
            $checklist = array();
            foreach ( $sitewide_issues as $issue ) {
                $checklist[] = array(
                    'severity' => $issue->severity,
                    'message'  => $issue->message,
                );
            }
            wp_send_json_success( array(
                'message'          => __( 'Sitewide SEO analysis completed successfully', 'seo-booster' ),
                'results'          => $results,
                'stats'            => $sitewide_stats,
                'checklist'        => $checklist,
                'last_run'         => $last_run,
                'last_run_display' => $last_run_display,
                'page_stats'       => $page_stats,
                'urls_count'       => $urls_count,
                'analysis_id'      => $analysis_id,
            ) );
        } catch ( \Exception $e ) {
            Utils::log( 'Sitewide analysis failed: ' . $e->getMessage(), 2 );
            wp_send_json_error( array(
                'message' => __( 'Something went wrong. Check the SEO Booster debug log for details.', 'seo-booster' ),
            ) );
        }
    }

}
