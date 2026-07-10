<?php

namespace Cleverplugins\SEOBooster;

/**
 * SEO Booster GSC AJAX Handler
 *
 * @package SEO_Booster
 */
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// Import required classes
// require_once(SEOBOOSTER_PATH . 'inc/Utils.php');
/**
 * Class SB_GSC_Ajax
 * Handles AJAX requests for Google Search Console data
 */
class SB_GSC_Ajax {
    /**
     * Initialize the class
     */
    public static function init() {
        // Register AJAX actions
        add_action( 'wp_ajax_sb_gsc_get_keywords', array(__CLASS__, 'ajax_get_keywords') );
        add_action( 'wp_ajax_sb_adminbar_get_keywords', array(__CLASS__, 'ajax_adminbar_get_keywords') );
        add_action( 'wp_ajax_sb_get_keyword_history', array(__CLASS__, 'ajax_get_keyword_history') );
        add_action( 'wp_ajax_sb_get_full_keyword_history', array(__CLASS__, 'ajax_get_full_keyword_history') );
        add_action( 'wp_ajax_sb_get_gsc_keywords_for_highlighting', array(__CLASS__, 'ajax_get_gsc_keywords_for_highlighting') );
    }

    /**
     * Get keyword history data
     * Retrieves historical data for specified keyword IDs, including:
     * First and latest entries
     * Average position, clicks, and impressions per day
     *
     * @author  Lars Koudal
     * @since   v0.0.1
     * @version v1.0.0  Thursday, May 8th, 2025.
     * @access  public static
     * @return  void
     */
    public static function ajax_get_keyword_history() {
        check_ajax_referer( 'sb_gsc_nonce', 'security' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'You do not have permission to view the keywords.', 'seo-booster' ) );
        }
        // Check if keyword IDs are provided
        if ( !isset( $_POST['keyword_ids'] ) || !is_array( $_POST['keyword_ids'] ) || empty( $_POST['keyword_ids'] ) ) {
            wp_send_json_error( 'No keyword IDs provided' );
            return;
        }
        // Sanitize keyword IDs
        $keyword_ids = array_map( 'intval', $_POST['keyword_ids'] );
        sort( $keyword_ids );
        // Sort to ensure consistent cache key
        // Generate cache key based on keyword IDs with version for cache busting
        $cache_key = 'sb_gsc_keyword_history_v2_' . md5( implode( '_', $keyword_ids ) );
        // Check if we should clear cache (for debugging)
        if ( isset( $_POST['clear_cache'] ) && $_POST['clear_cache'] === '1' ) {
            delete_transient( $cache_key );
        }
        // Try to get cached data
        $cached_data = get_transient( $cache_key );
        if ( false !== $cached_data ) {
            wp_send_json_success( $cached_data );
            return;
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'sb2_query_keywords_history';
        // Sanitize keyword IDs and create placeholders for prepared statement
        $keyword_ids = array_map( 'intval', $keyword_ids );
        $keyword_ids = array_filter( $keyword_ids, function ( $id ) {
            return $id > 0;
        } );
        if ( empty( $keyword_ids ) ) {
            wp_send_json_error( 'Invalid keyword IDs provided' );
            return;
        }
        $placeholders = implode( ',', array_fill( 0, count( $keyword_ids ), '%d' ) );
        // First, check if we have any data for these keywords (without date filter)
        $count_check = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE query_keywords_id IN ({$placeholders})", ...$keyword_ids ) );
        if ( $count_check === 0 ) {
            $response = array_fill_keys( $keyword_ids, array() );
            set_transient( $cache_key, $response, HOUR_IN_SECONDS );
            wp_send_json_success( $response );
            return;
        }
        // Get all data for these keywords in a single query, ordered by date
        // Use 60-day filter for optimization, but ensure we get at least one entry per keyword
        $all_entries = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} \n                WHERE query_keywords_id IN ({$placeholders})\n                AND date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)\n                ORDER BY query_keywords_id, date ASC", ...$keyword_ids ) );
        // Check which keywords have recent data
        $keywords_with_recent_data = array();
        foreach ( $all_entries as $entry ) {
            $keywords_with_recent_data[$entry->query_keywords_id] = true;
        }
        // Find keywords that don't have recent data
        $keywords_needing_historical_data = array_diff( $keyword_ids, array_keys( $keywords_with_recent_data ) );
        // If any keywords don't have recent data, get ALL historical data for them
        if ( !empty( $keywords_needing_historical_data ) ) {
            $historical_keyword_ids = array_map( 'intval', $keywords_needing_historical_data );
            $historical_keyword_ids = array_filter( $historical_keyword_ids, function ( $id ) {
                return $id > 0;
            } );
            if ( !empty( $historical_keyword_ids ) ) {
                $historical_placeholders = implode( ',', array_fill( 0, count( $historical_keyword_ids ), '%d' ) );
                $historical_entries = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} \n                        WHERE query_keywords_id IN ({$historical_placeholders})\n                        ORDER BY query_keywords_id, date ASC", ...$historical_keyword_ids ) );
            } else {
                $historical_entries = array();
            }
            // Merge recent and historical data
            $all_entries = array_merge( $all_entries, $historical_entries );
        }
        // Group entries by keyword_id
        $grouped_entries = array();
        foreach ( $all_entries as $entry ) {
            $grouped_entries[$entry->query_keywords_id][] = $entry;
        }
        // Process each keyword's data
        $result = array();
        foreach ( $keyword_ids as $keyword_id ) {
            $result[$keyword_id] = array();
            // Skip if no data for this keyword
            if ( !isset( $grouped_entries[$keyword_id] ) || empty( $grouped_entries[$keyword_id] ) ) {
                continue;
            }
            $entries = $grouped_entries[$keyword_id];
            $total_entries = count( $entries );
            // Get the last visit date for this keyword
            $last_entry = end( $entries );
            $last_visit_date = $last_entry->date;
            $days_since_last_visit = (strtotime( 'today' ) - strtotime( $last_visit_date )) / (24 * 60 * 60);
            // Calculate data ranges for UI
            $first_entry = reset( $entries );
            $first_date = $first_entry->date;
            $last_date = $last_entry->date;
            $total_days = (strtotime( $last_date ) - strtotime( $first_date )) / (24 * 60 * 60);
            // Calculate 120 days ago from today
            $cutoff_date = gmdate( 'Y-m-d', strtotime( '-120 days' ) );
            // Split data into recent (120 days) and historical
            $recent_entries = array();
            $historical_entries = array();
            foreach ( $entries as $entry ) {
                if ( $entry->date >= $cutoff_date ) {
                    $recent_entries[] = $entry;
                } else {
                    $historical_entries[] = $entry;
                }
            }
            // Determine which dataset to use for display
            $display_entries = ( !empty( $recent_entries ) ? $recent_entries : $entries );
            $is_using_historical = empty( $recent_entries );
            $has_historical_data = !empty( $historical_entries );
            // If we have 99 or fewer entries, use all of them
            if ( count( $display_entries ) <= 99 ) {
                foreach ( $display_entries as $entry ) {
                    $formatted_entry = self::format_entry( $entry );
                    $formatted_entry['last_visit_date'] = $last_visit_date;
                    $formatted_entry['days_since_last_visit'] = $days_since_last_visit;
                    $formatted_entry['is_historical'] = $is_using_historical;
                    $formatted_entry['has_historical_data'] = $has_historical_data;
                    $formatted_entry['total_days'] = $total_days;
                    $formatted_entry['first_date'] = $first_date;
                    $formatted_entry['last_date'] = $last_date;
                    $result[$keyword_id][] = $formatted_entry;
                }
            } else {
                // Get first entry
                $first_entry = self::format_entry( $display_entries[0] );
                $first_entry['last_visit_date'] = $last_visit_date;
                $first_entry['days_since_last_visit'] = $days_since_last_visit;
                $first_entry['is_historical'] = $is_using_historical;
                $first_entry['has_historical_data'] = $has_historical_data;
                $first_entry['total_days'] = $total_days;
                $first_entry['first_date'] = $first_date;
                $first_entry['last_date'] = $last_date;
                $result[$keyword_id][] = $first_entry;
                // Get evenly distributed entries (up to 97)
                $step = floor( count( $display_entries ) / 99 );
                for ($i = 1; $i < 97 && $i * $step < count( $display_entries ) - 1; $i++) {
                    $index = $i * $step;
                    $formatted_entry = self::format_entry( $display_entries[$index] );
                    $formatted_entry['last_visit_date'] = $last_visit_date;
                    $formatted_entry['days_since_last_visit'] = $days_since_last_visit;
                    $formatted_entry['is_historical'] = $is_using_historical;
                    $formatted_entry['has_historical_data'] = $has_historical_data;
                    $formatted_entry['total_days'] = $total_days;
                    $formatted_entry['first_date'] = $first_date;
                    $formatted_entry['last_date'] = $last_date;
                    $result[$keyword_id][] = $formatted_entry;
                }
                // Get last entry
                $last_formatted_entry = self::format_entry( $display_entries[count( $display_entries ) - 1] );
                $last_formatted_entry['last_visit_date'] = $last_visit_date;
                $last_formatted_entry['days_since_last_visit'] = $days_since_last_visit;
                $last_formatted_entry['is_historical'] = $is_using_historical;
                $last_formatted_entry['has_historical_data'] = $has_historical_data;
                $last_formatted_entry['total_days'] = $total_days;
                $last_formatted_entry['first_date'] = $first_date;
                $last_formatted_entry['last_date'] = $last_date;
                $result[$keyword_id][] = $last_formatted_entry;
            }
        }
        // Cache the results for 1 hour
        set_transient( $cache_key, $result, HOUR_IN_SECONDS );
        wp_send_json_success( $result );
    }

    /**
     * Clear old cache entries for keyword history
     *
     * @return void
     */
    public static function clear_keyword_history_cache() {
        global $wpdb;
        // Get all transients that match the old pattern
        $old_cache_keys = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} \n            WHERE option_name LIKE '_transient_sb_gsc_keyword_history_%' \n            AND option_name NOT LIKE '_transient_sb_gsc_keyword_history_v2_%'" );
        // Delete old cache entries
        foreach ( $old_cache_keys as $cache_key ) {
            $transient_name = str_replace( '_transient_', '', $cache_key );
            delete_transient( $transient_name );
        }
    }

    /**
     * Format a database entry for JSON response
     *
     * @param object $entry Database entry
     * @return array Formatted entry
     */
    private static function format_entry( $entry ) {
        return array(
            'date'        => $entry->date,
            'position'    => floatval( $entry->position ),
            'clicks'      => intval( $entry->clicks ),
            'impressions' => intval( $entry->impressions ),
        );
    }

    /**
     * ajax_get_keywords.
     *
     * @author  Lars Koudal
     * @since   v0.0.1
     * @version v1.0.0  Wednesday, October 16th, 2024.
     * @access  public static
     * @return  void
     */
    public static function ajax_get_keywords() {
        check_ajax_referer( 'sb_gsc_nonce', 'security' );
        Utils::timerstart( 'ajax_get_keywords' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'You do not have permission to view the keywords.', 'seo-booster' ) );
        }
        if ( !isset( $_POST['public_url'] ) ) {
            wp_send_json_error( __( 'No URL provided.', 'seo-booster' ) );
        }
        $public_url = sanitize_url( $_POST['public_url'] );
        // Remove Beaver Builder and other editor query parameters
        $url_parts = wp_parse_url( $public_url );
        if ( isset( $url_parts['query'] ) ) {
            parse_str( $url_parts['query'], $query_params );
            unset($query_params['fl_builder']);
            unset($query_params['fl_builder_preview']);
            unset($query_params['fl_builder_ui_iframe']);
            // Rebuild URL without editor parameters
            $public_url = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];
            if ( !empty( $query_params ) ) {
                $public_url .= '?' . http_build_query( $query_params );
            }
        }
        if ( !$public_url ) {
            wp_send_json_error( __( 'Invalid post URL.', 'seo-booster' ) );
        }
        if ( strpos( $public_url, 'wp-admin/post.php' ) !== false ) {
            $url_parts = wp_parse_url( $public_url );
            parse_str( $url_parts['query'], $query_params );
            if ( isset( $query_params['post'] ) ) {
                $post_id = intval( $query_params['post'] );
                $public_url = get_permalink( $post_id );
                if ( !$public_url ) {
                    wp_send_json_error( __( 'Could not determine the permalink for the given post ID.', 'seo-booster' ) );
                }
            } else {
                wp_send_json_error( __( 'Could not determine the post ID from the given URL.', 'seo-booster' ) );
            }
        } else {
            $post_id = url_to_postid( $public_url );
        }
        global $wpdb;
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT k.id, k.query, k.page, k.first_seen_date, k.latest_date,\n        k.is_used_in_content, k.last_checked,\n        SUM(h.clicks) AS clicks,\n        SUM(h.impressions) AS total_impressions,\n        AVG(h.ctr) AS average_ctr,\n        AVG(h.position) AS average_position\n        FROM {$wpdb->prefix}sb2_query_keywords AS k\n        INNER JOIN {$wpdb->prefix}sb2_query_keywords_history AS h \n            ON k.id = h.query_keywords_id\n        WHERE k.page = %s\n        GROUP BY k.query, k.page\n        ORDER BY total_impressions DESC", $public_url ), ARRAY_A );
        $prepared_query = $wpdb->prepare( "SELECT k.id, k.query, k.page, k.first_seen_date, k.latest_date,\n        k.is_used_in_content, k.last_checked,\n        SUM(h.clicks) AS clicks,\n        SUM(h.impressions) AS total_impressions,\n        AVG(h.ctr) AS average_ctr,\n        AVG(h.position) AS average_position\n        FROM {$wpdb->prefix}sb2_query_keywords AS k\n        INNER JOIN {$wpdb->prefix}sb2_query_keywords_history AS h \n            ON k.id = h.query_keywords_id\n        WHERE k.page = %s\n        GROUP BY k.query, k.page\n        ORDER BY total_impressions DESC", $public_url );
        if ( empty( $results ) ) {
            wp_send_json_success( array(
                'message'        => __( 'No keywords found for this URL.', 'seo-booster' ),
                'status'         => 'no_keywords',
                'last_refreshed' => get_option( 'sb_gsc_last_refreshed' ),
                'time'           => Utils::timerstop( 'ajax_get_keywords' ),
            ) );
        } else {
            $response = array();
            // Get a list of all auto links
            $auto_links = array();
            foreach ( $results as $res ) {
                $keyword_id = intval( $res['id'] );
                $keyword_query = esc_html( $res['query'] ?? '' );
                // Use 'is_used_in_content' directly from the results
                if ( $res['is_used_in_content'] === '1' ) {
                    $position_intext = '1';
                    $position_details = '&#10004; ' . __( 'Used', 'seo-booster' );
                } elseif ( $res['is_used_in_content'] === '-1' ) {
                    $position_intext = '-1';
                    $position_details = '&#10005; ' . __( 'Not used', 'seo-booster' );
                } else {
                    $position_intext = '0';
                    $position_details = '&#10068; ' . __( 'Not analyzed yet', 'seo-booster' );
                }
                $newrow = array(
                    'id'               => $keyword_id,
                    'query'            => $keyword_query,
                    'clicks'           => ( isset( $res['clicks'] ) ? $res['clicks'] : 0 ),
                    'impressions'      => ( isset( $res['total_impressions'] ) ? $res['total_impressions'] : 0 ),
                    'ctr'              => ( isset( $res['average_ctr'] ) ? number_format( $res['average_ctr'], 2 ) . '%' : '' ),
                    'position'         => number_format( $res['average_position'], 2 ) ?? 0,
                    'position_intext'  => $position_intext,
                    'position_details' => $position_details,
                    'autolink'         => '<span class="label label-info" title="' . esc_attr__( 'Create internal links to this keyword with one click', 'seo-booster' ) . '">' . esc_html__( 'Pro', 'seo-booster' ) . '</span>',
                );
                // Get historical data for charts
                $history = $wpdb->get_results( $wpdb->prepare( "SELECT \n                        date,\n                        clicks,\n                        impressions,\n                        position\n                    FROM {$wpdb->prefix}sb2_query_keywords_history\n                    WHERE query_keywords_id = %d\n                    ORDER BY date ASC", $keyword_id ), ARRAY_A );
                // If no recent data, get the most recent entry to show at least something
                if ( empty( $history ) ) {
                    $latest_entry = $wpdb->get_row( $wpdb->prepare( "SELECT \n                            date,\n                            clicks,\n                            impressions,\n                            position\n                        FROM {$wpdb->prefix}sb2_query_keywords_history\n                        WHERE query_keywords_id = %d\n                        ORDER BY date DESC\n                        LIMIT 1", $keyword_id ), ARRAY_A );
                    if ( $latest_entry ) {
                        $history = array($latest_entry);
                    }
                }
                // Apply data reduction at server level
                if ( count( $history ) > 100 ) {
                    // Calculate target number of points (between 10 and 20)
                    $target_points = min( 100, max( 10, count( $history ) ) );
                    // If we have more points than needed, reduce the data
                    if ( count( $history ) > $target_points ) {
                        $reduced_history = array();
                        // Always keep the first point
                        $reduced_history[] = $history[0];
                        // Calculate step size for even distribution
                        $step = (count( $history ) - 1) / ($target_points - 1);
                        // Select evenly distributed points
                        for ($i = 1; $i < $target_points - 1; $i++) {
                            $index = round( $i * $step );
                            $reduced_history[] = $history[$index];
                        }
                        // Always keep the last point
                        $reduced_history[] = $history[count( $history ) - 1];
                        $history = $reduced_history;
                    }
                }
                // Add last visit information
                if ( !empty( $history ) ) {
                    $last_entry = end( $history );
                    $last_visit_date = $last_entry['date'];
                    $days_since_last_visit = (strtotime( 'today' ) - strtotime( $last_visit_date )) / (24 * 60 * 60);
                    // Add last visit info to each entry
                    foreach ( $history as &$entry ) {
                        $entry['last_visit_date'] = $last_visit_date;
                        $entry['days_since_last_visit'] = $days_since_last_visit;
                    }
                }
                $newrow['history'] = $history;
                $newrow['curves'] = '';
                $response[] = $newrow;
            }
            wp_send_json_success( array(
                'keywords'       => $response,
                'last_refreshed' => get_option( 'sb_gsc_last_refreshed' ),
                'time'           => Utils::timerstop( 'ajax_get_keywords' ),
            ) );
        }
    }

    /**
     * ajax_adminbar_get_keywords.
     *
     * @author  Lars Koudal
     * @since   v0.0.1
     * @version v1.0.0  Sunday, April 20th, 2025.
     * @access  public static
     * @return  void
     */
    public static function ajax_adminbar_get_keywords() {
        Utils::timerstart( 'ajax_adminbar_get_keywords' );
        check_ajax_referer( 'sb_gsc_nonce', 'security' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'You do not have permission to view the keywords.', 'seo-booster' ) );
        }
        $post_url = sanitize_url( $_POST['post_url'] );
        // Remove Beaver Builder and other editor query parameters
        $url_parts = wp_parse_url( $post_url );
        if ( isset( $url_parts['query'] ) ) {
            parse_str( $url_parts['query'], $query_params );
            unset($query_params['fl_builder']);
            unset($query_params['fl_builder_preview']);
            unset($query_params['fl_builder_ui_iframe']);
            // Rebuild URL without editor parameters
            $post_url = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];
            if ( !empty( $query_params ) ) {
                $post_url .= '?' . http_build_query( $query_params );
            }
        }
        if ( !$post_url ) {
            wp_send_json_error( __( 'Invalid post URL.', 'seo-booster' ) );
        }
        if ( strpos( $post_url, 'wp-admin/post.php' ) !== false ) {
            $url_parts = wp_parse_url( $post_url );
            parse_str( $url_parts['query'], $query_params );
            if ( isset( $query_params['post'] ) ) {
                $post_id = intval( $query_params['post'] );
                $post_url = get_permalink( $post_id );
                if ( !$post_url ) {
                    wp_send_json_error( __( 'Could not determine the permalink for the given post ID.', 'seo-booster' ) );
                }
            } else {
                wp_send_json_error( __( 'Could not determine the post ID from the given URL.', 'seo-booster' ) );
            }
        } else {
            $post_id = url_to_postid( $post_url );
        }
        global $wpdb;
        $lookup_query = $wpdb->prepare( "SELECT k.id, k.query, k.page, k.first_seen_date, k.latest_date, \n        k.is_used_in_content, k.last_checked, SUM(h.clicks) AS clicks, \n        SUM(h.impressions) AS total_impressions, \n        AVG(h.ctr) AS average_ctr, \n        AVG(h.position) AS average_position\n        FROM {$wpdb->prefix}sb2_query_keywords AS k\n        INNER JOIN {$wpdb->prefix}sb2_query_keywords_history AS h \n            ON k.id = h.query_keywords_id\n        WHERE k.page = %s\n        GROUP BY k.query, k.page\n        ORDER BY total_impressions DESC", $post_url );
        $results = $wpdb->get_results( $lookup_query, ARRAY_A );
        if ( empty( $results ) ) {
            wp_send_json_success( array(
                'message'        => __( 'No keywords found for this URL.', 'seo-booster' ),
                'status'         => 'no_keywords',
                'last_refreshed' => get_option( 'sb_gsc_last_refreshed' ),
                'time'           => Utils::timerstop( 'ajax_get_keywords' ),
            ) );
        } else {
            $response = array();
            $post_modified_time = get_post_modified_time( 'U', false, $post_id );
            // Fetch all transients for the given post_id
            $transients = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value \n                    FROM {$wpdb->options} \n                    WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_sb_gsc_keyword_usage_' . $post_id . '_' ) . '%' ), OBJECT_K );
            // Convert transients to a more accessible format
            $transient_data = array();
            foreach ( $transients as $option_name => $option_value ) {
                $transient_data[$option_name] = maybe_unserialize( $option_value->option_value );
            }
            foreach ( $results as $res ) {
                $keyword_id = intval( $res['id'] );
                $keyword_query = esc_html( $res['query'] ?? '' );
                // Use 'is_used_in_content' directly from the results
                $position_intext = ( $res['is_used_in_content'] ? __( 'Used in content', 'seo-booster' ) : __( 'Not used in content', 'seo-booster' ) );
                $position_details = ( $res['is_used_in_content'] ? '&#10004; ' . __( 'Used', 'seo-booster' ) : '&#10005; ' . __( 'Not used', 'seo-booster' ) );
                $newrow = array(
                    'id'               => $keyword_id,
                    'query'            => $keyword_query,
                    'clicks'           => $res['clicks'] ?? 0,
                    'impressions'      => $res['total_impressions'] ?? 0,
                    'ctr'              => ( isset( $res['average_ctr'] ) ? number_format( $res['average_ctr'], 2 ) . '%' : '' ),
                    'position'         => $res['average_position'] ?? 0,
                    'position_intext'  => $position_intext,
                    'position_details' => $position_details,
                );
                $response[] = $newrow;
            }
            wp_send_json_success( array(
                'keywords'       => $response,
                'last_refreshed' => get_option( 'sb_gsc_last_refreshed' ),
                'time'           => Utils::timerstop( 'ajax_adminbar_get_keywords' ),
            ) );
        }
    }

    /**
     * AJAX handler for getting GSC keywords for highlighting
     *
     * @author  Unknown
     * @since   v0.0.1
     * @version v1.0.0  Monday, June 23rd, 2025.
     * @access  public static
     * @return  void
     */
    public static function ajax_get_gsc_keywords_for_highlighting() {
        // Verify nonce
        if ( !wp_verify_nonce( $_POST['_wpnonce'], 'seobooster_gsc_highlight_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
            return;
        }
        // Check user capabilities
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'You do not have permission to perform this action.' );
            return;
        }
        // Get the permalink from the request
        $permalink = '';
        if ( isset( $_POST['page_url'] ) ) {
            $raw_url = sanitize_url( $_POST['page_url'] );
            // Remove fragment (#hash) and query params (including UTM)
            $parsed = wp_parse_url( $raw_url );
            $permalink = '';
            if ( !empty( $parsed['scheme'] ) && !empty( $parsed['host'] ) ) {
                $permalink = $parsed['scheme'] . '://' . $parsed['host'];
                if ( !empty( $parsed['port'] ) ) {
                    $permalink .= ':' . $parsed['port'];
                }
                if ( !empty( $parsed['path'] ) ) {
                    $permalink .= $parsed['path'];
                }
                // Do NOT add query or fragment
            }
        }
        if ( empty( $permalink ) ) {
            wp_send_json_error( __( 'No page URL provided.', 'seo-booster' ) );
            return;
            exit;
        }
        global $wpdb;
        // Fetch GSC keywords for the current page, ordered by length (longest first) and performance
        $query = "SELECT DISTINCT k.query, k.id,\n                SUM(h.clicks) AS total_clicks,\n                SUM(h.impressions) AS total_impressions,\n                AVG(h.position) AS avg_position\n            FROM {$wpdb->prefix}sb2_query_keywords AS k\n            INNER JOIN {$wpdb->prefix}sb2_query_keywords_history AS h \n                ON k.id = h.query_keywords_id\n            WHERE k.page = %s\n                AND LENGTH(k.query) >= 3\n            GROUP BY k.query, k.id\n            ORDER BY LENGTH(k.query) DESC, total_impressions DESC, avg_position ASC\n            LIMIT 100";
        $prepared_query = $wpdb->prepare( $query, $permalink );
        $results = $wpdb->get_results( $prepared_query, ARRAY_A );
        if ( empty( $results ) ) {
            wp_send_json_success( array(
                'keywords' => array(),
                'message'  => __( 'No GSC keywords found for this page.', 'seo-booster' ),
            ) );
            return;
        }
        // Process and format keywords for highlighting
        $keywords = array();
        foreach ( $results as $result ) {
            $keyword = trim( $result['query'] );
            // Skip empty or very short keywords
            if ( strlen( $keyword ) < 3 ) {
                continue;
            }
            // Calculate a simple score based on performance
            $score = $result['total_impressions'] * 0.4 + $result['total_clicks'] * 0.4 + (100 - $result['avg_position']) * 0.2;
            $keywords[] = array(
                'id'          => intval( $result['id'] ),
                'keyword'     => $keyword,
                'length'      => strlen( $keyword ),
                'impressions' => intval( $result['total_impressions'] ),
                'clicks'      => intval( $result['total_clicks'] ),
                'position'    => round( $result['avg_position'], 1 ),
                'score'       => round( $score, 2 ),
            );
        }
        // Sort by length (longest first) and then by score
        usort( $keywords, function ( $a, $b ) {
            if ( $a['length'] !== $b['length'] ) {
                return $b['length'] - $a['length'];
                // Longest first
            }
            return $b['score'] - $a['score'];
            // Highest score first
        } );
        wp_send_json_success( array(
            'keywords' => $keywords,
            'count'    => count( $keywords ),
            'url'      => $permalink,
        ) );
    }

    /**
     * AJAX handler for getting full historical data for a keyword
     */
    public static function ajax_get_full_keyword_history() {
        check_ajax_referer( 'sb_gsc_nonce', 'security' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'You do not have permission to view the keywords.', 'seo-booster' ) );
        }
        // Get keyword ID
        $keyword_id = intval( $_POST['keyword_id'] );
        if ( !$keyword_id ) {
            wp_send_json_error( 'Invalid keyword ID' );
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'sb2_query_keywords_history';
        // Get all historical data for this keyword
        $entries = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} \n                WHERE query_keywords_id = %d\n                ORDER BY date ASC", $keyword_id ) );
        if ( empty( $entries ) ) {
            wp_send_json_error( 'No data found for this keyword' );
        }
        // Format the data
        $formatted_data = array();
        foreach ( $entries as $entry ) {
            $formatted_entry = self::format_entry( $entry );
            $formatted_data[] = $formatted_entry;
        }
        wp_send_json_success( $formatted_data );
    }

}

// Initialize the class
SB_GSC_Ajax::init();
// Clear old cache entries on plugin load
SB_GSC_Ajax::clear_keyword_history_cache();