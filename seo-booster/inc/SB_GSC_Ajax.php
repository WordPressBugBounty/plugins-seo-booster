<?php

/**
 * SEO Booster GSC AJAX Handler
 *
 * @package SEO_Booster
 */
namespace Cleverplugins\SEOBooster;

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
        $cache_key = 'sb_gsc_keyword_history_v3_' . md5( implode( '_', $keyword_ids ) );
        // Check if we should clear cache (for debugging)
        if ( isset( $_POST['clear_cache'] ) && '1' === $_POST['clear_cache'] ) {
            delete_transient( $cache_key );
        }
        // Try to get cached data
        $cached_data = get_transient( $cache_key );
        if ( false !== $cached_data ) {
            wp_send_json_success( $cached_data );
            return;
        }
        $keyword_ids = array_values( array_filter( array_map( 'intval', $keyword_ids ), static function ( $id ) {
            return $id > 0;
        } ) );
        if ( empty( $keyword_ids ) ) {
            wp_send_json_error( 'Invalid keyword IDs provided' );
            return;
        }
        $result = GSC_History::get_chart_series( $keyword_ids );
        // Ensure every requested ID has a key (empty array when no history).
        foreach ( $keyword_ids as $keyword_id ) {
            if ( !isset( $result[$keyword_id] ) ) {
                $result[$keyword_id] = array();
            }
        }
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
        $old_cache_keys = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} \n            WHERE option_name LIKE '_transient_sb_gsc_keyword_history_%' \n            AND option_name NOT LIKE '_transient_sb_gsc_keyword_history_v3_%'" );
        // Delete old cache entries
        foreach ( $old_cache_keys as $cache_key ) {
            $transient_name = str_replace( '_transient_', '', $cache_key );
            delete_transient( $transient_name );
        }
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
                if ( '1' === $res['is_used_in_content'] ) {
                    $position_intext = '1';
                    $position_details = '&#10004; ' . __( 'Used', 'seo-booster' );
                } elseif ( '-1' === $res['is_used_in_content'] ) {
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
                    'history'          => array(),
                    'curves'           => '',
                );
                $response[] = $newrow;
            }
            $keyword_ids = array_map( static function ( $row ) {
                return (int) ($row['id'] ?? 0);
            }, $response );
            $chart_series = GSC_History::get_chart_series( $keyword_ids );
            foreach ( $response as &$row ) {
                $kid = (int) ($row['id'] ?? 0);
                $row['history'] = ( isset( $chart_series[$kid] ) ? $chart_series[$kid] : array() );
            }
            unset($row);
            wp_send_json_success( array(
                'keywords'       => $response,
                'last_refreshed' => get_option( 'sb_gsc_last_refreshed' ),
                'time'           => Utils::timerstop( 'ajax_get_keywords' ),
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
        if ( !isset( $_POST['_wpnonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'seobooster_gsc_highlight_nonce' ) ) {
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
            $raw_url = sanitize_url( wp_unslash( $_POST['page_url'] ) );
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
        }
        global $wpdb;
        // Fetch GSC keywords for the current page, ordered by length (longest first) and performance
        $insight_days = (int) GSC_History::INSIGHT_WINDOW_DAYS;
        $query = "SELECT DISTINCT k.query, k.id,\n                SUM(h.clicks) AS total_clicks,\n                SUM(h.impressions) AS total_impressions,\n                AVG(h.position) AS avg_position\n            FROM {$wpdb->prefix}sb2_query_keywords AS k\n            INNER JOIN {$wpdb->prefix}sb2_query_keywords_history AS h \n                ON k.id = h.query_keywords_id\n                AND h.date >= DATE_SUB(CURDATE(), INTERVAL {$insight_days} DAY)\n            WHERE k.page = %s\n                AND LENGTH(k.query) >= 3\n            GROUP BY k.query, k.id\n            ORDER BY LENGTH(k.query) DESC, total_impressions DESC, avg_position ASC\n            LIMIT 100";
        $prepared_query = $wpdb->prepare( $query, $permalink );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built with prefixed tables / allowlisted ORDER BY; values prepared.
        $results = $wpdb->get_results( $prepared_query, ARRAY_A );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built with prefixed tables / allowlisted ORDER BY; values prepared.
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
        $formatted_data = GSC_History::get_full_chart_series( $keyword_id );
        if ( empty( $formatted_data ) ) {
            wp_send_json_error( 'No data found for this keyword' );
        }
        wp_send_json_success( $formatted_data );
    }

}

// Initialize the class
SB_GSC_Ajax::init();
// Clear old cache entries on plugin load
SB_GSC_Ajax::clear_keyword_history_cache();