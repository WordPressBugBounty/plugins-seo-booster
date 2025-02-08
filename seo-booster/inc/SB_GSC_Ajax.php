<?php

namespace Cleverplugins\SEOBooster;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class SB_GSC_Ajax {
    public static function init() {
        add_action( 'wp_ajax_sb_gsc_get_keywords', [__CLASS__, 'ajax_get_keywords'] );
        add_action( 'wp_ajax_sb_adminbar_get_keywords', [__CLASS__, 'ajax_adminbar_get_keywords'] );
    }

    /**
     * ajax_get_keywords.
     *
     * @author	Lars Koudal
     * @since	v0.0.1
     * @version	v1.0.0	Wednesday, October 16th, 2024.
     * @access	public static
     * @return	void
     */
    public static function ajax_get_keywords() {
        Utils::timerstart( 'ajax_get_keywords' );
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
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT k.id, k.query, k.page, k.first_seen_date, k.latest_date,
        k.is_used_in_content, k.last_checked,
        SUM(h.clicks) AS clicks,
        SUM(h.impressions) AS total_impressions,
        AVG(h.ctr) AS average_ctr,
        AVG(h.position) AS average_position
        FROM {$wpdb->prefix}sb2_query_keywords AS k
        INNER JOIN {$wpdb->prefix}sb2_query_keywords_history AS h 
            ON k.id = h.query_keywords_id
        WHERE k.page = %s
        GROUP BY k.query, k.page
        ORDER BY total_impressions DESC", $post_url ), ARRAY_A );
        if ( empty( $results ) ) {
            wp_send_json_success( [
                'message'        => __( 'No keywords found for this URL.', 'seo-booster' ),
                'status'         => 'no_keywords',
                'last_refreshed' => get_option( 'sb_gsc_last_refreshed' ),
                'time'           => Utils::timerstop( 'ajax_get_keywords' ),
            ] );
        } else {
            $response = [];
            $post_modified_time = get_post_modified_time( 'U', false, $post_id );
            // Get a list of all auto links
            $auto_links = [];
            foreach ( $results as $res ) {
                $keyword_id = intval( $res['id'] );
                $keyword_query = esc_html( $res['query'] ?? '' );
                // Use 'is_used_in_content' directly from the results
                $position_intext = ( $res['is_used_in_content'] ? '1' : '' );
                $position_details = ( $res['is_used_in_content'] ? '&#10004; ' . __( 'Used', 'seo-booster' ) : '&#10005; ' . __( 'Not used', 'seo-booster' ) );
                $newrow = [
                    'id'               => $keyword_id,
                    'query'            => $keyword_query,
                    'clicks'           => ( isset( $res['clicks'] ) ? $res['clicks'] : 0 ),
                    'impressions'      => ( isset( $res['total_impressions'] ) ? $res['total_impressions'] : 0 ),
                    'ctr'              => ( isset( $res['average_ctr'] ) ? number_format( $res['average_ctr'], 2 ) . '%' : '' ),
                    'position'         => number_format( $res['average_position'], 2 ) ?? 0,
                    'position_intext'  => $position_intext,
                    'position_details' => $position_details,
                    'autolink'         => '<span class="label label-info" title="' . esc_attr__( 'Create internal links to this keyword with one click', 'seo-booster' ) . '">' . esc_html__( 'Pro', 'seo-booster' ) . '</span>',
                ];
                $response[] = $newrow;
            }
            wp_send_json_success( [
                'keywords'       => $response,
                'last_refreshed' => get_option( 'sb_gsc_last_refreshed' ),
                'time'           => Utils::timerstop( 'ajax_get_keywords' ),
            ] );
        }
    }

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
        $lookup_query = $wpdb->prepare( "SELECT k.id, k.query, k.page, k.first_seen_date, k.latest_date,\n                   k.is_used_in_content, k.last_checked,\n                   SUM(h.clicks) AS clicks,\n                   SUM(h.impressions) AS total_impressions,\n                   AVG(h.ctr) AS average_ctr,\n                   AVG(h.position) AS average_position\n            FROM {$wpdb->prefix}sb2_query_keywords AS k\n            INNER JOIN {$wpdb->prefix}sb2_query_keywords_history AS h \n                ON k.id = h.query_keywords_id\n            WHERE k.page = %s\n            GROUP BY k.query, k.page\n            ORDER BY total_impressions DESC", $post_url );
        $results = $wpdb->get_results( $lookup_query, ARRAY_A );
        if ( empty( $results ) ) {
            wp_send_json_success( [
                'message'        => __( 'No keywords found for this URL.', 'seo-booster' ),
                'status'         => 'no_keywords',
                'last_refreshed' => get_option( 'sb_gsc_last_refreshed' ),
                'time'           => Utils::timerstop( 'ajax_get_keywords' ),
            ] );
        } else {
            $response = [];
            $post_modified_time = get_post_modified_time( 'U', false, $post_id );
            // Fetch all transients for the given post_id
            $transients = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value \n                    FROM {$wpdb->options} \n                    WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_sb_gsc_keyword_usage_' . $post_id . '_' ) . '%' ), OBJECT_K );
            // Convert transients to a more accessible format
            $transient_data = [];
            foreach ( $transients as $option_name => $option_value ) {
                $transient_data[$option_name] = maybe_unserialize( $option_value->option_value );
            }
            foreach ( $results as $res ) {
                $keyword_id = intval( $res['id'] );
                $keyword_query = esc_html( $res['query'] ?? '' );
                // Use 'is_used_in_content' directly from the results
                $position_intext = ( $res['is_used_in_content'] ? __( 'Used in content', 'seo-booster' ) : __( 'Not used in content', 'seo-booster' ) );
                $position_details = ( $res['is_used_in_content'] ? '&#10004; ' . __( 'Used', 'seo-booster' ) : '&#10005; ' . __( 'Not used', 'seo-booster' ) );
                $newrow = [
                    'id'               => $keyword_id,
                    'query'            => $keyword_query,
                    'clicks'           => $res['clicks'] ?? 0,
                    'impressions'      => $res['total_impressions'] ?? 0,
                    'ctr'              => ( isset( $res['average_ctr'] ) ? number_format( $res['average_ctr'], 2 ) . '%' : '' ),
                    'position'         => $res['average_position'] ?? 0,
                    'position_intext'  => $position_intext,
                    'position_details' => $position_details,
                ];
                $response[] = $newrow;
            }
            wp_send_json_success( [
                'keywords'       => $response,
                'last_refreshed' => get_option( 'sb_gsc_last_refreshed' ),
                'time'           => Utils::timerstop( 'ajax_adminbar_get_keywords' ),
            ] );
        }
    }

}

SB_GSC_Ajax::init();