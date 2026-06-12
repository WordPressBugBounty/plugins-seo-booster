<?php

/**
 * Google API.
 *
 * @package SEO_Booster
 */

namespace Cleverplugins\SEOBooster;

use voku\helper\HtmlDomParser;
use Cleverplugins\SEOBooster\Utils;
use Cleverplugins\SEOBooster\Seobooster2;

class Google_API {
    private static $google_email;

    private static $access_token = null;

    private static $token_refreshed = false;

    // Add this class property
    public static function init() {
        self::$google_email = get_option( 'seobooster_google_email' );
    }

    /**
     * sb_gsc_import_data.
     *
     * @author  Lars Koudal
     * @since   v0.0.1
     * @version v1.0.0 Monday, November 11th, 2024.
     * @access  public static
     * @return  void
     */
    public static function sb_gsc_import_data() {
        // Verify nonce
        if ( !check_ajax_referer( 'sb_gsc_nonce', 'nonce', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'seo-booster' ) );
        }
        // Check user capabilities
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'You do not have permission to perform this action.', 'seo-booster' ) );
        }
        
        // Get parameters from AJAX request
        $step = isset($_POST['step']) ? intval($_POST['step']) : 0;
        $startRow = $step * 1000; // Calculate startRow from step
        
        
        global $wpdb;
        
        // Ensure database connection uses UTF-8
        $wpdb->query( "SET NAMES utf8mb4" );
        $wpdb->query( "SET CHARACTER SET utf8mb4" );
        
        Utils::timerstart( 'sb_gsc_import_data' );
        $step = ( isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 0 );
        $days = ( isset( $_POST['days'] ) ? absint( $_POST['days'] ) : 90 );
        $days += 2;
        // Adjust for GSC's usual delay.
        $site_url = '';
        if ( isset( $_POST['site_url'] ) ) {
            $posted_url = wp_unslash( $_POST['site_url'] );
            $site_url = ( strpos( $posted_url, 'sc-domain:' ) === 0 ? sanitize_text_field( $posted_url ) : esc_url_raw( $posted_url ) );
        }
        if ( empty( $site_url ) ) {
            wp_send_json_error( __( 'Invalid site URL.', 'seo-booster' ) );
        }
        if ( 0 === $step ) {
            update_option( 'seobooster_selected_site', $site_url, 'no' );
        }
        if ( seobooster_fs()->can_use_premium_code() && 1 === $step ) {
            do_action( 'sb_gsc_schedule_all_pages' );
            Utils::log( 'Scheduled keyword analysis for first batch of pages', 5 );
        }
        try {
            $access_token = self::get_access_token();
            if ( !$access_token || is_wp_error( $access_token ) ) {
                /* translators: %1$s: error message from access token */
                $error_message = ( is_wp_error( $access_token ) ? sprintf( __( 'Access token error: %1$s', 'seo-booster' ), $access_token->get_error_message() ) : sprintf( __( 'Access token is not set or invalid: %1$s', 'seo-booster' ), $access_token ) );
                Utils::log( 'Access token error in sb_gsc_import_data: ' . $error_message, 2 );
                throw new \Exception($error_message);
            }
            // startRow and step are now from AJAX request
            $rowLimit = 1000;
            $totalFetched = 0;
            $lastKeyword = '';
            $lastDate = '';
            $api_site_url = $site_url;
            if ( strpos( $site_url, 'sc-domain:' ) === 0 ) {
                // For domain properties, we need to keep the sc-domain: prefix and encode the domain part
                $domain_part = substr( $site_url, strlen( 'sc-domain:' ) );
                $api_site_url = 'sc-domain:' . urlencode( $domain_part );
            } else {
                // For regular URLs, encode the entire URL
                $api_site_url = urlencode( $site_url );
            }
            
            $start_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
            $end_date = gmdate( 'Y-m-d' );
            
            $response = wp_remote_post( "https://www.googleapis.com/webmasters/v3/sites/" . $api_site_url . "/searchAnalytics/query", [
            'headers'     => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body'        => wp_json_encode( [
                'startDate'  => gmdate( 'Y-m-d', strtotime( "-{$days} days" ) ),
                'endDate'    => gmdate( 'Y-m-d' ),
                'dimensions' => ['query', 'page', 'date'],
                'rowLimit'   => $rowLimit,
                'startRow'   => $startRow,
            ] ),
            'method'      => 'POST',
            'data_format' => 'body',
            'timeout'     => 60,
        ] );
            if ( is_wp_error( $response ) ) {
                // Only attempt token refresh once per import process
                if ( !self::$token_refreshed ) {
                    // Store original error for logging
                    $original_error = $response->get_error_message();
                    // Try to get a new access token and retry once
                    Utils::log( 'API request failed. Attempting to refresh access token...', 3 );
                    $new_access_token = self::get_access_token( true );
                    // Force new token
                    if ( $new_access_token && !is_wp_error( $new_access_token ) ) {
                        Utils::log( 'Access token refreshed. Retrying API request...', 3 );
                        self::$token_refreshed = true;
                        // Mark that we've tried refreshing
                        // Retry the request with new token
                        $response = wp_remote_post( "https://www.googleapis.com/webmasters/v3/sites/" . $api_site_url . "/searchAnalytics/query", [
                            'headers'     => [
                                'Authorization' => 'Bearer ' . $new_access_token,
                                'Content-Type'  => 'application/json',
                            ],
                            'body'        => wp_json_encode( [
                                'startDate'  => gmdate( 'Y-m-d', strtotime( "-{$days} days" ) ),
                                'endDate'    => gmdate( 'Y-m-d' ),
                                'dimensions' => ['query', 'page', 'date'],
                                'rowLimit'   => $rowLimit,
                                'startRow'   => $startRow,
                            ] ),
                            'method'      => 'POST',
                            'data_format' => 'body',
                            'timeout'     => 60,
                        ] );
                        // If retry succeeded, continue
                        if ( !is_wp_error( $response ) ) {
                            Utils::log( 'API request successful after token refresh', 3 );
                            return $response;
                        }
                    }
                    // If we get here, both attempts failed
                    Utils::log( 'Error fetching query keywords (Original error: ' . $original_error . '). Retry also failed: ' . (( is_wp_error( $response ) ? $response->get_error_message() : 'Unknown error' )), 3 );
                } else {
                    Utils::log( 'API request failed. Token already refreshed once, not retrying: ' . $response->get_error_message(), 3 );
                }
                throw new \Exception($response->get_error_message());
            }
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            
            $total_rows = isset( $data['rows'] ) ? count( $data['rows'] ) : 0;
            
            
            if ( !$data || !is_array( $data ) || !isset( $data['rows'] ) || !is_array( $data['rows'] ) ) {
                $error_details = '';
                if ( $data === null ) {
                    $error_details = 'Received null data';
                } elseif ( !is_array( $data ) ) {
                    $error_details = 'Received non-array data type: ' . gettype( $data );
                } elseif ( !isset( $data['rows'] ) ) {
                    // Log first 200 chars of response for debugging
                    $data_preview = substr( wp_json_encode( $data ), 0, 200 );
                    $error_details = "Missing 'rows' key in response. Data preview: " . $data_preview;
                } elseif ( !is_array( $data['rows'] ) ) {
                    $error_details = "'rows' is not an array, type: " . gettype( $data['rows'] );
                }
                Utils::log( sprintf( 
                    /* translators: %s: error details */
                    __( 'Invalid data received from Google Search Console API: %s', 'seo-booster' ),
                    $error_details
                 ), 3 );
                throw new \Exception(sprintf( 
                    /* translators: %s: error details */
                    __( 'Invalid data received from Google Search Console API: %s', 'seo-booster' ),
                    $error_details
                 ));
            }
            $processed_count = 0;
            $skipped_count = 0;
            foreach ( $data['rows'] as $row_index => $row ) {
                if ( !isset( $row['keys'][0], $row['keys'][1], $row['keys'][2] ) ) {
                    $skipped_count++;
                    continue;
                }
                $query = sanitize_text_field( $row['keys'][0] );
                $url_parts = explode( '#', $row['keys'][1], 2 );
                $page = esc_url_raw( $url_parts[0] );
                $date = sanitize_text_field( $row['keys'][2] );
                
                
                // Skip entries with empty or invalid data
                if ( empty( $query ) || empty( $page ) || empty( $date ) ) {
                    $skipped_count++;
                    continue;
                }
                
                // Skip entries with query too long for database field (65,535 chars for TEXT field)
                if ( mb_strlen( $query, 'UTF-8' ) > 65535 ) {
                    $skipped_count++;
                    continue;
                }
                
                // Skip entries with page URL too long for database field (1024 chars)
                if ( strlen( $page ) > 1024 ) {
                    $skipped_count++;
                    continue;
                }
                $cache_key = 'sb2_keyword_id_' . md5( $query . $page );
                $existing_keyword_id = wp_cache_get( $cache_key );
                if ( false === $existing_keyword_id ) {
                    $wpdb->query( 'START TRANSACTION' );
                    $existing_keyword_id = $wpdb->get_var( $wpdb->prepare( "SELECT id 
                    FROM {$wpdb->prefix}sb2_query_keywords 
                    WHERE query = %s AND page = %s", $query, $page ) );
                    if ( $existing_keyword_id ) {
                        wp_cache_set(
                            $cache_key,
                            $existing_keyword_id,
                            '',
                            HOUR_IN_SECONDS
                        );
                    } else {
                        $insert_result = $wpdb->insert( "{$wpdb->prefix}sb2_query_keywords", [
                            'query'           => $query,
                            'page'            => $page,
                            'first_seen_date' => $date,
                            'latest_date'     => $date,
                        ] );
                        
                        if ( $insert_result === false ) {
                            Utils::log( 'Failed to insert keyword: ' . wp_json_encode( $row ) . ' - Error: ' . $wpdb->last_error . ' - Query: ' . $query . ' - Page: ' . $page, 3 );
                            $wpdb->query( 'ROLLBACK' );
                            continue;
                        }
                        
                        $existing_keyword_id = $wpdb->insert_id;
                        wp_cache_set(
                            $cache_key,
                            $existing_keyword_id,
                            '',
                            HOUR_IN_SECONDS
                        );
                    }
                    $wpdb->query( 'COMMIT' );
                }
                if ( $existing_keyword_id ) {
                    $wpdb->update( "{$wpdb->prefix}sb2_query_keywords", [
                        'latest_date' => $date,
                    ], [
                        'id' => $existing_keyword_id,
                    ] );
                    $keyword_id = $existing_keyword_id;
                    
                    $existing_history_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sb2_query_keywords_history 
                     WHERE query_keywords_id = %d AND date = %s", $keyword_id, $date ) );
                    $history_data = [
                        'clicks'      => absint( $row['clicks'] ),
                        'impressions' => absint( $row['impressions'] ),
                        'ctr'         => floatval( $row['ctr'] ),
                        'position'    => floatval( $row['position'] ),
                    ];
                    if ( $existing_history_id ) {
                        $result_history = $wpdb->update( "{$wpdb->prefix}sb2_query_keywords_history", $history_data, [
                            'id' => $existing_history_id,
                        ] );
                    } else {
                        $history_data['query_keywords_id'] = $keyword_id;
                        $history_data['date'] = $date;
                        $result_history = $wpdb->insert( "{$wpdb->prefix}sb2_query_keywords_history", $history_data );
                    }
                    if ( $result_history === false ) {
                        Utils::log( 'Failed to insert/update history entry: ' . wp_json_encode( $row ) . ' - Error: ' . $wpdb->last_error, 3 );
                    } else {
                        $totalFetched++;
                        $lastKeyword = $query;
                        $lastDate = $date;
                    }
                } else {
                    Utils::log( 'Failed to insert/update keyword entry: ' . wp_json_encode( $row ) . ' - Error: ' . $wpdb->last_error, 3 );
                }
                
                $processed_count++;
            }
            $more_results = $total_rows >= $rowLimit;
            
            
            /* translators: 1: number of processed records, 2: current keyword being processed, 3: date of the keyword */
            Utils::log( sprintf(
                __( 'Processed %1$s records. Current keyword: "%2$s" (%3$s)', 'seo-booster' ),
                number_format_i18n( $totalFetched ),
                $lastKeyword,
                $lastDate
            ), 10 );
        
        $total_keywords = $wpdb->get_var( "SELECT COUNT(DISTINCT query) FROM {$wpdb->prefix}sb2_query_keywords" );
            

            
        $response = [
            'message'            => $more_results ? 
                sprintf( __( 'Processed %1$s records. Current keyword: "%2$s" (%3$s)', 'seo-booster' ), 
                    number_format_i18n( $totalFetched ), $lastKeyword, $lastDate ) :
                __( 'Import complete!', 'seo-booster' ),
            'time'               => Utils::timerstop( 'sb_gsc_import_data' ),
            'more_results'       => $more_results,
            'last_keyword'       => $lastKeyword,
            'total_keywords'     => number_format_i18n( $total_keywords ),
            'last_date'          => $lastDate,
            'keywords_processed' => number_format_i18n( $totalFetched ),
            'total_entries'      => number_format_i18n( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sb2_query_keywords_history;" ) ),
            'next_step'          => $more_results ? ( $step + 1 ) : 0,
        ];
            
            if ( !$more_results ) {
                Utils::log( 'Import complete!', 10 );
                if ( !wp_next_scheduled( 'sb_gsc_schedule_all_pages' ) ) {
                    wp_schedule_single_event( time(), 'sb_gsc_schedule_all_pages' );
                    Utils::log( 'Scheduled keyword analysis for all pages', 5 );
                }
                update_option( 'sb_gsc_last_refreshed', current_time( 'mysql' ) );
                email_status::send_email_update( 7, true );
            }
            wp_send_json_success( $response );
        } catch ( \Exception $e ) {
            Utils::log( 'Error fetching query keywords: ' . $e->getMessage(), 2 );
            wp_send_json_error( esc_html( $e->getMessage() ) );
        }
    }

    /**
     * load_adminbar_js.
     *
     * @author	Unknown
     * @since	v0.0.1
     * @version	v1.0.0	Tuesday, September 10th, 2024.
     * @access	public static
     * @return	void
     */
    public static function load_adminbar_js() {
        if ( !is_admin_bar_showing() ) {
            return;
        }
        
        // Check if we should load winbox-related files based on GET parameter
        $should_load_winbox = isset( $_GET['seobooster_showdetails'] ) && 
                             ( $_GET['seobooster_showdetails'] === 'true' || $_GET['seobooster_showdetails'] === '1' );
        
        // Always load basic admin bar functionality
        $plugin_version = Utils::get_plugin_version();
        
        // Only load winbox-related files if the parameter is set
        if ( $should_load_winbox ) {
            wp_enqueue_script(
                'winbox',
                SEOBOOSTER_PLUGINURL . 'js/min/winbox.bundle.min.js',
                ['jquery'],
                filemtime(SEOBOOSTER_PLUGINPATH . 'js/min/winbox.bundle.min.js'),
                true
            );
            
            wp_enqueue_style('tabulator', 
            SEOBOOSTER_PLUGINURL . 'js/tabulator/dist/css/tabulator.min.css',
            [],
            filemtime(SEOBOOSTER_PLUGINPATH . 'js/tabulator/dist/css/tabulator.min.css'),
            'all'
        );
            
            wp_enqueue_script(
                'tabulator',
                SEOBOOSTER_PLUGINURL . 'js/tabulator/dist/js/tabulator.min.js',
                ['jquery'],
                filemtime(SEOBOOSTER_PLUGINPATH . 'js/tabulator/dist/js/tabulator.min.js'),
                true
            );
            
            wp_enqueue_style(
                'seobooster-adminbar',
                SEOBOOSTER_PLUGINURL . 'css/sb-adminbar.css',
                [],
                filemtime(SEOBOOSTER_PLUGINPATH . 'css/sb-adminbar.css'),
            );

            wp_enqueue_script(
                'seobooster-adminbar',
                SEOBOOSTER_PLUGINURL . 'js/seobooster-adminbar.js',
                ['jquery', 'winbox', 'tabulator'],
                filemtime(SEOBOOSTER_PLUGINPATH . 'js/seobooster-adminbar.js'),
                true
            );
            
            wp_enqueue_style('uPlot', SEOBOOSTER_PLUGINURL . 'js/uPlot/uPlot.min.css');
            wp_enqueue_script('uPlot', SEOBOOSTER_PLUGINURL . 'js/uPlot/uPlot.iife.min.js', ['jquery'], Utils::get_plugin_version(), true);

            wp_enqueue_script(
                'clipboardjs',
                SEOBOOSTER_PLUGINURL . 'js/min/clipboard.min.js',
                [],
                filemtime(SEOBOOSTER_PLUGINPATH . 'js/min/clipboard.min.js'),
                true
            );
        }
        
        $current_page = (( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? "https" : "http" )) . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
        $current_page = remove_query_arg( ['seobooster_showdetails', 'seobooster_showlinks'], $current_page );

        $current_url = Utils::seobooster_currenturl(true);
        if (is_string($current_url) && strpos($current_url, '?') !== false) {
            $current_url = substr($current_url, 0, strpos($current_url, '?'));
        }

        // Only localize script if we're loading the adminbar script
        if ( $should_load_winbox ) {
            wp_localize_script( 'seobooster-adminbar', 'seobooster_adminbar', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'security' => wp_create_nonce( 'sb_gsc_nonce' ),
                'public_url' => $current_url,
                'content_type' => '',
                'item_id' => '',
                'text'     => [
                    'search'                     => __( 'Search', 'seo-booster' ),
                    'error'                      => __( 'Error', 'seo-booster' ),
                    'loading'                    => __( 'Loading...', 'seo-booster' ),
                    'query'                      => __( 'Query', 'seo-booster' ),
                    'used'                       => __( 'Used', 'seo-booster' ),
                    'stats'                      => __( 'Stats', 'seo-booster' ),
                    'avg_position'               => __( 'Avg. position', 'seo-booster' ),
                    'not_found'                  => __( 'Not found', 'seo-booster' ),
                    'clicks'                     => __( 'Clicks', 'seo-booster' ),
                    'impressions'                => __( 'Impressions', 'seo-booster' ),
                    'ctr'                        => __( 'CTR', 'seo-booster' ),
                    'avg_position'               => __( 'Avg. pos', 'seo-booster' ),
                ],
            ) );
        }
    }

    /**
     * Identifies the active SEO plugin.
     *
     * This method checks for the presence and activation of popular SEO plugins.
     *
     * @since 0.0.1
     *
     * @return array|null An array containing the name and file of the active SEO plugin, or null if none found.
     */
    public static function identify_active_seo_plugin() {
        $seo_plugins = [
            'Yoast SEO'           => 'wordpress-seo/wp-seo.php',
            'All in One SEO Pack' => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
            'Rank Math SEO'       => 'seo-by-rank-math/rank-math.php',
            'SEOPress'            => 'wp-seopress/seopress.php',
            'The SEO Framework'   => 'autodescription/autodescription.php',
            'Squirrly SEO'        => 'squirrly-seo/squirrly.php',
        ];
        $installed_plugins = get_plugins();
        $active_plugins = get_option( 'active_plugins' );
        foreach ( $seo_plugins as $plugin_name => $plugin_file ) {
            if ( isset( $installed_plugins[$plugin_file] ) && in_array( $plugin_file, $active_plugins, true ) ) {
                return [
                    'name' => $plugin_name,
                    'file' => $plugin_file,
                ];
            }
        }
        return null;
    }

    /**
     * Get the focus keyword(s) for a given post ID from the active SEO plugin.
     *
     * @since v0.0.1
     * @access public static
     * @param int $post_id The ID of the post.
     * @return array An array of focus keywords.
     */
    public static function get_focus_keywords( $post_id, $post_url = '' ) {
        if ( !is_int( $post_id ) || $post_id <= 0 ) {
            return [];
        }
        $active_plugin = self::identify_active_seo_plugin();
        if ( !$active_plugin ) {
            return [];
        }
        $focus_keywords = [];
        switch ( $active_plugin['name'] ) {
            case 'Yoast SEO':
                $focus_keyword = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
                if ( !empty( $focus_keyword ) ) {
                    $focus_keywords[] = sanitize_text_field( $focus_keyword );
                }
                break;
            case 'All in One SEO Pack':
                $focus_keyword = get_post_meta( $post_id, '_aioseop_keywords', true );
                if ( !empty( $focus_keyword ) ) {
                    $focus_keywords[] = sanitize_text_field( $focus_keyword );
                }
                break;
            case 'Rank Math SEO':
                $focus_keyword = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
                if ( !empty( $focus_keyword ) ) {
                    // Rank Math can store multiple keywords comma-separated
                    $keywords = explode( ',', $focus_keyword );
                    $focus_keywords = array_merge( $focus_keywords, array_map( 'sanitize_text_field', array_map( 'trim', $keywords ) ) );
                }
                break;
            case 'SEOPress':
                $focus_keyword = get_post_meta( $post_id, '_seopress_analysis_target_kw', true );
                if ( !empty( $focus_keyword ) ) {
                    $keywords = explode( ',', $focus_keyword );
                    $focus_keywords = array_merge( $focus_keywords, array_map( 'sanitize_text_field', $keywords ) );
                }
                break;
            case 'The SEO Framework':
                $autodescription = get_post_meta( $post_id, '_autodescription', true );
                if ( isset( $autodescription['focus_keyword'] ) ) {
                    $focus_keywords[] = sanitize_text_field( $autodescription['focus_keyword'] );
                }
                break;
            case 'Squirrly SEO':
                $sq_keywords = get_post_meta($post_id, '_sq_keywords', true);
                if (!empty($sq_keywords)) {
                    $keywords = explode(',', $sq_keywords);
                    $focus_keywords = array_merge($focus_keywords, array_map('sanitize_text_field', $keywords));
                }
                break;
        }
        return array_unique( $focus_keywords );
    }

    /**
     * Get SEO title and description for a post.
     *
     * @param int $post_id The ID of the post.
     * @return array An array containing 'title' and 'description'.
     *
     * @since v0.0.1
     * @access public static
     */
    public static function get_seo_title_and_description( $post_id ) {
        if ( !current_user_can( 'edit_post', $post_id ) ) {
            return [
                'title'       => '',
                'description' => '',
            ];
        }
        $post_id = absint( $post_id );
        $active_plugin = self::identify_active_seo_plugin();
        if ( !$active_plugin ) {
            return [
                'title'       => '',
                'description' => '',
            ];
        }
        $title = '';
        $description = '';
        switch ( $active_plugin['name'] ) {
            case 'Yoast SEO':
                $title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
                $description = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
                break;
            case 'All in One SEO Pack':
                $title = get_post_meta( $post_id, '_aioseop_title', true );
                $description = get_post_meta( $post_id, '_aioseop_description', true );
                break;
            case 'Rank Math SEO':
                $title = get_post_meta( $post_id, 'rank_math_title', true );
                $description = get_post_meta( $post_id, 'rank_math_description', true );
                break;
            case 'SEOPress':
                $title = get_post_meta( $post_id, '_seopress_titles_title', true );
                $description = get_post_meta( $post_id, '_seopress_titles_desc', true );
                break;
            case 'The SEO Framework':
                $autodescription = get_post_meta( $post_id, '_autodescription', true );
                if ( is_array( $autodescription ) ) {
                    $title = ( isset( $autodescription['title'] ) ? $autodescription['title'] : '' );
                    $description = ( isset( $autodescription['description'] ) ? $autodescription['description'] : '' );
                }
                break;
            case 'Squirrly SEO':
                $title = get_post_meta($post_id, '_sq_title', true);
                $description = get_post_meta($post_id, '_sq_description', true);
                break;
        }
        return [
            'title'       => sanitize_text_field( $title ),
            'description' => sanitize_textarea_field( $description ),
        ];
    }

    /**
     * Get SEO plugin meta key names for title and description.
     *
     * @param string $field_type 'title' or 'description'.
     * @return array Array with 'title_key' and 'description_key', or empty array if plugin not found.
     *
     * @since v0.0.1
     * @access public static
     */
    public static function get_seo_plugin_meta_keys( $field_type = 'both' ) {
        $active_plugin = self::identify_active_seo_plugin();
        if ( !$active_plugin ) {
            return [];
        }
        
        $keys = [];
        switch ( $active_plugin['name'] ) {
            case 'Yoast SEO':
                if ( $field_type === 'title' || $field_type === 'both' ) {
                    $keys['title_key'] = '_yoast_wpseo_title';
                }
                if ( $field_type === 'description' || $field_type === 'both' ) {
                    $keys['description_key'] = '_yoast_wpseo_metadesc';
                }
                break;
            case 'All in One SEO Pack':
                if ( $field_type === 'title' || $field_type === 'both' ) {
                    $keys['title_key'] = '_aioseop_title';
                }
                if ( $field_type === 'description' || $field_type === 'both' ) {
                    $keys['description_key'] = '_aioseop_description';
                }
                break;
            case 'Rank Math SEO':
                if ( $field_type === 'title' || $field_type === 'both' ) {
                    $keys['title_key'] = 'rank_math_title';
                }
                if ( $field_type === 'description' || $field_type === 'both' ) {
                    $keys['description_key'] = 'rank_math_description';
                }
                break;
            case 'SEOPress':
                if ( $field_type === 'title' || $field_type === 'both' ) {
                    $keys['title_key'] = '_seopress_titles_title';
                }
                if ( $field_type === 'description' || $field_type === 'both' ) {
                    $keys['description_key'] = '_seopress_titles_desc';
                }
                break;
            case 'Squirrly SEO':
                if ( $field_type === 'title' || $field_type === 'both' ) {
                    $keys['title_key'] = '_sq_title';
                }
                if ( $field_type === 'description' || $field_type === 'both' ) {
                    $keys['description_key'] = '_sq_description';
                }
                break;
            // The SEO Framework uses serialized array, skip for duplicate checks
        }
        return $keys;
    }



    	/**
	 * Check keyword occurrences in post content and metadata.
	 *
	 * @author  Unknown
	 * @since   v0.0.1
	 * @version v1.0.0  Monday, August 26th, 2024.
	 * @access  public static
	 * @param   string  $content    The post content to check.
	 * @param   string  $keyword    The keyword to search for.
	 * @param   int     $post_id    The ID of the post being checked.
	 * @return  array|false         Array of occurrences or false if none found.
	 */
	public static function check_keyword_occurrences($content, $keyword, $post_id)
	{
		if (empty($content)) {
			// Translators: %d is the post ID
			Utils::log(sprintf('Empty content for post ID: %d', absint($post_id)), 5);
			return false;
		}

		if (is_object($content) && isset($content->content)) {
			$content = $content->content;
		}

		if (!is_string($content)) {
			/* translators: %d is the post ID */
			$log_message = sprintf(__('Invalid content type for post ID: %d', 'seo-booster'), absint($post_id));
			Utils::log($log_message, 5);
			return false;
		}

		$html = HtmlDomParser::str_get_html($content);
		if (!$html) {
			// Translators: %d is the post ID
			Utils::log(sprintf('Failed to parse HTML content for post ID: %d', absint($post_id)), 2);
			return false;
		}

		// Normalize keyword for accurate comparison
		$normalized_keyword = strtolower(sanitize_text_field($keyword));
		
		// Create normalized versions of the keyword (with spaces and with hyphens)
		$keyword_variations = self::get_keyword_variations($normalized_keyword);
		
		$occurrences = [];
		$contentExcludingHeadlines = $html->plaintext;

		// Check if keyword is within an href tag
		foreach ($html->find('a') as $a) {
			foreach ($keyword_variations as $variation) {
				if (stripos($a->href, $variation) !== false) {
					$occurrences[] = [
						'location_id' => 1,
						'message'  => __('Href attribute', 'seo-booster'),
					];
					break; // Found a match, no need to check other variations
				}
			}
			
			foreach ($keyword_variations as $variation) {
				if (stripos($a->innertext(), $variation) !== false) {
					$occurrences[] = [
						'location_id' => 2,
						'message'  => __('In a link', 'seo-booster'),
					];
					break; // Found a match, no need to check other variations
				}
			}
		}

		// Enhanced check for headlines to consider nested structures
		foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
			foreach ($html->find($tag) as $heading) {
				$heading_text = $heading->plaintext;
				foreach ($keyword_variations as $variation) {
					if (stripos($heading_text, $variation) !== false) {
						$occurrences[] = [
							'location_id' => 3,
							'message'  => __('Headline', 'seo-booster'),
						];
						$contentExcludingHeadlines = str_ireplace($heading_text, '', $contentExcludingHeadlines);
						break; // Found a match, no need to check other variations
					}
				}
			}
		}

		// Check if keyword is within an img alt attribute
		foreach ($html->find('img') as $img) {
			foreach ($keyword_variations as $variation) {
				if (stripos($img->alt, $variation) !== false) {
					$occurrences[] = [
						'location_id' => 4,
						'message'  => __('Img alt= attribute', 'seo-booster'),
					];
					break; // Found a match, no need to check other variations
				}
			}
		}

		// Check for keyword in the title tag and meta description
		$seo_data = self::get_seo_title_and_description(absint($post_id));
		foreach ($keyword_variations as $variation) {
			if (stripos($seo_data['title'], $variation) !== false) {
				$occurrences[] = [
					'location_id' => 6,
					'message'  => __('Title tag', 'seo-booster'),
				];
				break; // Found a match, no need to check other variations
			}
		}
		
		foreach ($keyword_variations as $variation) {
			if (stripos($seo_data['description'], $variation) !== false) {
				$occurrences[] = [
					'location_id' => 7,
					'message'  => __('Meta description', 'seo-booster'),
				];
				break; // Found a match, no need to check other variations
			}
		}

		// Check if keyword is within the normal content, excluding headlines
		foreach ($keyword_variations as $variation) {
			if (stripos($contentExcludingHeadlines, $variation) !== false) {
				$occurrences[] = [
					'location_id' => 5,
					'message'  => __('Content', 'seo-booster'),
				];
				break; // Found a match, no need to check other variations
			}
		}

		return !empty($occurrences) ? $occurrences : false;
	}
	
	/**
	 * Generate variations of a keyword to account for hyphens and spaces.
	 * 
	 * @author  Lars Koudal
	 * @since   v1.0.0
	 * @version v1.0.0  Wednesday, March 27th, 2024.
	 * @access  private static
	 * @param   string  $keyword    The keyword to generate variations for.
	 * @return  array               Array of keyword variations.
	 */
	private static function get_keyword_variations($keyword) {
		// Start with the original keyword
		$variations = [$keyword];
		
		// If the keyword contains spaces, add a hyphenated version
		if (strpos($keyword, ' ') !== false) {
			$variations[] = str_replace(' ', '-', $keyword);
		}
		
		// If the keyword contains hyphens, add a space version
		if (strpos($keyword, '-') !== false) {
			$variations[] = str_replace('-', ' ', $keyword);
		}
		
		// Handle partially hyphenated keywords (e.g., "seo-bureau københavn")
		$words = preg_split('/[\s-]+/', $keyword);
		if (count($words) > 2) {
			// Generate variations with different hyphenation patterns
			// For example, "seo bureau københavn" -> "seo-bureau københavn", "seo bureau-københavn", "seo-bureau-københavn"
			$variations[] = $words[0] . '-' . $words[1] . ' ' . $words[2];
			$variations[] = $words[0] . ' ' . $words[1] . '-' . $words[2];
			$variations[] = $words[0] . '-' . $words[1] . '-' . $words[2];
		}
		
		// Remove duplicates and return
		return array_unique($variations);
	}


    
    
    /**
     * Fetch chart data via AJAX request
     *
     * @return void
     */
    public static function fetch_chart_data_ajax() {
        check_ajax_referer( 'seobooster-nonce', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized user', 'seo-booster' ) );
        }
        global $wpdb;
        $range = ( isset( $_POST['range'] ) ? sanitize_text_field( $_POST['range'] ) : '90_days' );

        // Determine start date based on range
        switch ( $range ) {
            case '28_days':
                $start_date = gmdate( 'Y-m-d', strtotime( '-28 days' ) );
                break;
            case '3_months':
                $start_date = gmdate( 'Y-m-d', strtotime( '-3 months' ) );
                break;
            case '6_months':
                $start_date = gmdate( 'Y-m-d', strtotime( '-6 months' ) );
                break;
            case '12_months':
                $start_date = gmdate( 'Y-m-d', strtotime( '-12 months' ) );
                break;
            case 'all':
                $start_date = gmdate( 'Y-m-d', strtotime( '-36 months' ) );
                break;
            default:
                $start_date = gmdate( 'Y-m-d', strtotime( '-14 days' ) );
        }
        $end_date = gmdate( 'Y-m-d' );
        // Prepare and execute query using wpdb->prepare to prevent SQL injection
        $query = $wpdb->prepare( "
         SELECT 
         history.date,
         SUM(history.clicks) AS total_clicks,
         SUM(history.impressions) AS total_impressions,
         (SUM(history.clicks) / SUM(history.impressions)) * 100 AS ctr,
         COUNT(DISTINCT keywords.query) AS unique_keywords
         FROM 
        {$wpdb->prefix}sb2_query_keywords AS keywords
         INNER JOIN 
        {$wpdb->prefix}sb2_query_keywords_history AS history
         ON 
         keywords.id = history.query_keywords_id
         WHERE 
         history.date BETWEEN %s AND %s
         GROUP BY 
         history.date
         ORDER BY 
         history.date DESC
         LIMIT 999;", $start_date, $end_date );
        $results = $wpdb->get_results( $query );
        $results = array_reverse( $results );
        if ( !$results ) {
            wp_send_json_error( __( 'No data found', 'seo-booster' ) );
        }
        $data = [
            'labels'         => [],
            'clicks'         => [],
            'impressions'    => [],
            'ctr'            => [],
            'uniqueKeywords' => [],
        ];
        foreach ( $results as $row ) {
            $data['labels'][] = $row->date;
            $data['clicks'][] = intval( $row->total_clicks );
            $data['impressions'][] = intval( $row->total_impressions );
            $data['ctr'][] = round( floatval( $row->ctr ), 2 );
            $data['uniqueKeywords'][] = intval( $row->unique_keywords );
        }
        wp_send_json_success( $data );
    }

    /**
     * fetch_gsc_data_cron.
     *
     * @author  Unknown
     * @since   v0.0.1
     * @version v1.0.1 Tuesday, January 14th, 2025.
     * @access	public static
     * @return	void
     */
    public static function fetch_gsc_data_cron() {
        $selected_site = get_option( 'seobooster_selected_site' );
        if ( !$selected_site ) {
            $selected_site = get_option( 'siteurl' );
        }
        
        Utils::log( 'Fetching GSC data via cron selected site: ' . $selected_site, 5 );
        
        if ( $selected_site ) {
            Utils::log( 'Fetching GSC data for ' . $selected_site, 5 );
            
            // Check if we have valid authentication before proceeding
            $access_token = self::get_access_token();
            if (is_wp_error($access_token)) {
                Utils::log('Cron job failed: ' . $access_token->get_error_message(), 2);
                
                // Set the reauth flag if it's an authentication error
                if (in_array($access_token->get_error_code(), ['invalid_grant', 'authentication_required'])) {
                    update_option('seobooster_needs_reauth', '1');
                    Utils::log('Authentication required - setting reauth flag', 2);
                }
                return;
            }
            
            if (!$access_token) {
                Utils::log('Cron job failed: No access token available', 2);
                update_option('seobooster_needs_reauth', '1');
                return;
            }
            
            // Proceed with data fetch
            try {
                self::fetch_and_store_query_keywords( $selected_site, true );
                Utils::log('Cron job completed successfully', 3);
            } catch (\Exception $e) {
                Utils::log('Cron job failed with exception: ' . $e->getMessage(), 2);
                
                // Check if it's an authentication error
                if (strpos($e->getMessage(), '401') !== false || 
                    strpos($e->getMessage(), 'invalid authentication') !== false ||
                    strpos($e->getMessage(), 'Token expired') !== false) {
                    update_option('seobooster_needs_reauth', '1');
                    Utils::log('Authentication error detected - setting reauth flag', 2);
                }
            }
        } else {
            Utils::log('Cron job failed: No selected site available', 2);
        }
    }


    /**
     * Display the size of SEO Booster data tables.
     *
     * This method calculates and displays the size and record count of SEO Booster-specific
     * database tables. It also shows the percentage of space these tables occupy compared
     * to all tables in the database.
     *
     * @since 1.0.0
     * @access public static
     * @return void
     */
    public static function display_data_size() {
        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }
        global $wpdb;
        $prefix = $wpdb->prefix;
        // Query to get all tables with the prefix
        $escaped_prefix = $wpdb->esc_like( $prefix ) . '%';
        $all_tables = $wpdb->get_results( "SHOW TABLE STATUS LIKE '{$escaped_prefix}'", ARRAY_A );
        // Define our tables
        $our_tables = [
            'sb2_query_keywords'         => $prefix . 'sb2_query_keywords',
            'sb2_query_keywords_history' => $prefix . 'sb2_query_keywords_history',
            'sb2_log'                    => $prefix . 'sb2_log',
            'sb2_autolink'               => $prefix . 'sb2_autolink',
            'sb2_seo_urls'               => $prefix . 'sb2_seo_urls',
            'sb2_seo_analysis'           => $prefix . 'sb2_seo_analysis',
            'sb2_seo_issues'             => $prefix . 'sb2_seo_issues',
            'sb2_ai_requests'            => $prefix . 'sb2_ai_requests',
            'sb2_improvements_tracking'  => $prefix . 'sb2_improvements_tracking',
        ];
        // Calculate total size of all tables
        $total_size_all_tables = array_sum( array_column( $all_tables, 'Data_length' ) ) + array_sum( array_column( $all_tables, 'Index_length' ) );
        // Start table structure
        echo '<table class="wp-list-table widefat fixed striped table-view-list"><thead><tr><th>' . esc_html__( 'Table Name', 'seo-booster' ) . '</th><th>' . esc_html__( 'Number of Records', 'seo-booster' ) . '</th><th>' . esc_html__( 'Size (MB)', 'seo-booster' ) . '</th></tr></thead><tbody>';
        $total_size_our_tables = 0;
        $total_records_our_tables = 0;
        foreach ( $our_tables as $key => $table ) {
            $size_query = $wpdb->prepare( "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) 
            FROM information_schema.TABLES 
            WHERE table_schema = %s 
            AND table_name = %s", $wpdb->dbname, $table );
            $size = $wpdb->get_var( $size_query );
            $rows_query = "SELECT COUNT(*) FROM `{$table}`";
            $rows = $wpdb->get_var( $rows_query );
            $total_size_our_tables += $size;
            $total_records_our_tables += $rows;
            // Output each row
            echo '<tr><td>' . esc_html( $key ) . '</td><td>' . esc_html( number_format_i18n( $rows ) ) . '</td><td>' . esc_html( $size ) . ' MB</td></tr>';
        }
        // Calculate the percentage of our tables
        $total_percentage = $total_size_our_tables * 1024 * 1024 / $total_size_all_tables * 100;
        // Output total size row
        echo '<tr><td><strong>' . esc_html__( 'Total', 'seo-booster' ) . '</strong></td><td><strong>' . esc_html( number_format_i18n( $total_records_our_tables ) ) . '</strong></td><td><strong>' . esc_html( $total_size_our_tables ) . ' MB (' . esc_html( number_format_i18n( $total_percentage, 2 ) ) . '% ' . esc_html__( 'of total db size', 'seo-booster' ) . ')</strong></td></tr>';
        // End table structure
        echo '</tbody></table>';
    }


   
    /**
     * Batch add or update keyword entries in the database.
     *
     * @param array $entries An array of keyword entries to process.
     * @return void
     */
    public static function batch_add_or_update_keyword_entries( $entries ) {
        // Start the process and log it
        $start_time = microtime( true );
        global $wpdb;
        $query_keywords_table = $wpdb->prefix . 'sb2_query_keywords';
        $query_keywords_history_table = $wpdb->prefix . 'sb2_query_keywords_history';
        // Process each entry sequentially
        foreach ( $entries as $entry ) {
            // Sanitize and validate input data
            $sanitized_query = sanitize_text_field( $entry['query'] );
            $sanitized_page = esc_url_raw( $entry['page'] );
            $sanitized_date = sanitize_text_field( $entry['date'] );
            $existing_entry = $wpdb->get_row( $wpdb->prepare( "SELECT id, latest_date FROM {$query_keywords_table} WHERE query = %s AND page = %s", $sanitized_query, $sanitized_page ), ARRAY_A );
            if ( $existing_entry ) {
                // Update the existing keyword entry if needed
                if ( $existing_entry['latest_date'] !== $sanitized_date ) {
                    $result = $wpdb->update(
                        $query_keywords_table,
                        [
                            'latest_date' => $sanitized_date,
                        ],
                        [
                            'id' => $existing_entry['id'],
                        ],
                        ['%s'],
                        ['%d']
                    );
                    if ( $result === false ) {
                        // Translators: %s is the entry details
                        Utils::log( sprintf( 'Failed to update keyword entry: %s', wp_json_encode( $entry ) ), 2 );
                        continue;
                        // Skip to the next entry on failure
                    }
                }
                $query_keywords_id = $existing_entry['id'];
            } else {
                // Insert a new keyword entry
                $result = $wpdb->insert( $query_keywords_table, [
                    'query'           => $sanitized_query,
                    'page'            => $sanitized_page,
                    'first_seen_date' => $sanitized_date,
                    'latest_date'     => $sanitized_date,
                ], [
                    '%s',
                    '%s',
                    '%s',
                    '%s'
                ] );
                if ( $result === false ) {
                    // Translators: %s is the entry details
                    Utils::log( sprintf( 'Failed to insert new keyword entry: %s', wp_json_encode( $entry ) ), 2 );
                    continue;
                    // Skip to the next entry on failure
                }
                $query_keywords_id = $wpdb->insert_id;
            }
            // Check for existing history entry before inserting
            $existing_history_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$query_keywords_history_table} WHERE query_keywords_id = %d AND date = %s", $query_keywords_id, $sanitized_date ) );
            $history_data = [
                'clicks'      => absint( $entry['clicks'] ),
                'impressions' => absint( $entry['impressions'] ),
                'ctr'         => floatval( $entry['ctr'] ),
                'position'    => floatval( $entry['position'] ),
                'date'        => $sanitized_date,
            ];
            if ( $existing_history_id ) {
                // Update the existing history entry
                $result = $wpdb->update(
                    $query_keywords_history_table,
                    $history_data,
                    [
                        'id' => $existing_history_id,
                    ],
                    [
                        '%d',
                        '%d',
                        '%f',
                        '%f',
                        '%s'
                    ],
                    ['%d']
                );
                if ( $result === false ) {
                    // Translators: %s is the entry details
                    Utils::log( sprintf( 'Failed to update history entry: %s', wp_json_encode( $entry ) ), 2 );
                }
            } else {
                // Insert a new history entry
                $history_data['query_keywords_id'] = $query_keywords_id;
                $result = $wpdb->insert( $query_keywords_history_table, $history_data, [
                    '%d',
                    '%d',
                    '%d',
                    '%f',
                    '%f',
                    '%s'
                ] );
                if ( $result === false ) {
                    // Translators: %s is the entry details
                    Utils::log( sprintf( 'Failed to insert new history entry: %s', wp_json_encode( $entry ) ), 2 );
                }
            }
        }
        // End the process and log it
        $end_time = microtime( true );
        $duration = $end_time - $start_time;
        // Translators: %s is the duration in seconds
        Utils::log( sprintf( 'Finished batch_add_or_update_keyword_entries in %s seconds', number_format( $duration, 4 ) ), 10 );
    }

    /**
     * Fetch and store query keywords for a given site URL
     *
     * @author  Unknown
     * @since   v0.0.1
     * @version v1.0.1  Tuesday, January 14th, 2025.
     * @access  public static
     * @param   string  $site_url               The site URL to fetch data for
     * @param   boolean $bypass_permission_check Whether to bypass permission check. Default: false
     * @param   integer $days                   Number of days to fetch data for. Default: 3
     * @return  void
     */
    public static function fetch_and_store_query_keywords( $site_url, $bypass_permission_check = false, $days = 3 ) {
        /* translators: 1: site URL, 2: number of days */
        $message = sprintf( esc_html__( 'Fetching query keywords for %1$s for the past %2$d days', 'seo-booster' ), esc_url( $site_url ), $days );
        Utils::log( $message, 1 );
        
        if ( !$bypass_permission_check && !current_user_can( 'manage_options' ) ) {
            Utils::log( 'User does not have permission to fetch query keywords', 5 );
            return;
        }
        
        try {
            Utils::log('Starting fetch_and_store_query_keywords process', 1);
            
            // Temporarily disable Query Monitor to prevent backtrace memory issues
            if (class_exists('QueryMonitor')) {
                remove_action('wp_redirect', array('QM_Dispatcher_Redirect', 'filter_wp_redirect'));
                Utils::log('Temporarily disabled Query Monitor redirect tracking', 1);
            }
            
            // Log initial memory usage
            $initial_memory = memory_get_usage();
            Utils::log(sprintf('Initial memory usage: %s MB', number_format($initial_memory / 1024 / 1024, 2)), 1);
            
            $access_token = self::get_access_token();
            if ( !$access_token || is_wp_error( $access_token ) ) {
                $error_message = ( is_wp_error( $access_token ) ? sprintf( esc_html__( 'Access token error: %1$s', 'seo-booster' ), $access_token->get_error_message() ) : sprintf( esc_html__( 'Access token is not set or invalid: %1$s', 'seo-booster' ), $access_token ) );
                Utils::log( $error_message, 5 );
                
                // Set reauth flag for authentication errors
                if (is_wp_error($access_token) && in_array($access_token->get_error_code(), ['auth_expired', 'invalid_grant', 'authentication_required'], true)) {
                    update_option('seobooster_needs_reauth', '1');
                }
                
                throw new \Exception($error_message);
            }

            // Get install ID for token decryption
            try {
                $install_id = self::get_installation_id(); // Use our fallback function
            } catch (\Exception $e) {
                Utils::log('Failed to get install ID: ' . $e->getMessage(), 5);
                throw $e;
            }
            
            // Use token directly (no decryption for now)
            $decrypted_token = $access_token;
            
            global $wpdb;
            $start_row = 0;
            $row_limit = 100; // Much smaller API requests
            $total_fetched = 0;
            $max_retries = 2; // Maximum number of token refresh attempts
            $retry_count = 0;
            
            // Simple batch processing
            $batch_size = 25; // Even smaller SQL batches
            $current_batch = [];
            
            // Progress tracking
            $progress_key = 'seobooster_gsc_progress_' . md5($site_url);
            $saved_progress = get_transient($progress_key);
            if ($saved_progress) {
                $start_row = $saved_progress['start_row'];
                $total_fetched = $saved_progress['total_fetched'];
                Utils::log(sprintf('Resuming from row %d with %d records already processed', $start_row, $total_fetched), 1);
            }
            
            do {
                // Check memory before each API call
                $current_memory = memory_get_usage();
                $memory_limit = self::get_memory_limit_bytes();
                if ( $current_memory > ( $memory_limit * 0.85 ) ) {
                    Utils::log( sprintf( 
                        'Memory usage high (%s MB), pausing for cleanup...', 
                        number_format( $current_memory / 1024 / 1024, 2 )
                    ), 2 );
                    
                    // Force aggressive cleanup
                    if ( function_exists( 'gc_collect_cycles' ) ) {
                        gc_collect_cycles();
                    }
                    
                    // Clear any cached data
                    wp_cache_flush();
                    
                    // Small pause to let system recover
                    usleep(500000); // 0.5 second pause
                    
                    // Check memory again after cleanup
                    $current_memory = memory_get_usage();
                    if ( $current_memory > ( $memory_limit * 0.9 ) ) {
                        Utils::log( 'Memory still high after cleanup, stopping for safety', 2 );
                        break;
                    }
                }
                
                try {
                    $api_site_url = $site_url;
                    if ( strpos( $site_url, 'sc-domain:' ) === 0 ) {
                        // For domain properties, we need to keep the sc-domain: prefix and encode the domain part
                        $domain_part = substr( $site_url, strlen( 'sc-domain:' ) );
                        $api_site_url = 'sc-domain:' . urlencode( $domain_part );
                    } else {
                        // For regular URLs, encode the entire URL
                        $api_site_url = urlencode( $site_url );
                    }
                    
                    $header_arr = [
                        'headers'     => [
                            'Authorization' => 'Bearer ' . $decrypted_token,
                            'Content-Type'  => 'application/json',
                        ],
                        'body'        => wp_json_encode( [
                            'startDate'  => gmdate( 'Y-m-d', strtotime( "-{$days} days" ) ),
                            'endDate'    => gmdate( 'Y-m-d' ),
                            'dimensions' => ['query', 'page', 'date'],
                            'rowLimit'   => $row_limit,
                            'startRow'   => $start_row,
                        ] ),
                        'timeout'     => 60,
                        'method'      => 'POST',
                        'data_format' => 'body',
                    ];
                    
                    $response = wp_remote_post( "https://www.googleapis.com/webmasters/v3/sites/" . $api_site_url . "/searchAnalytics/query", $header_arr );

                    if ( is_wp_error( $response ) ) {
                        /* translators: %1$s: error message from access token */
                        Utils::log( sprintf( 'Error fetching query keywords: %1$s', $response->get_error_message() ), 2 );
                        throw new \Exception($response->get_error_message());
                    }
                    
                    $data = json_decode( wp_remote_retrieve_body( $response ), true );

                    // Handle 401 authentication errors with retry logic
                    if ( $data && is_array( $data ) && isset( $data['error'] ) && is_array( $data['error'] ) && isset( $data['error']['code'] ) && $data['error']['code'] === 401 ) {
                        if ($retry_count < $max_retries) {
                            $retry_count++;
                            Utils::log( sprintf( 'Token expired, attempting refresh (attempt %d of %d)', $retry_count, $max_retries ), 3 );
                            
                            // Force token refresh and retry using get_access_token with force refresh
                            $new_access_token = self::get_access_token(true);
                            if ($new_access_token && !is_wp_error($new_access_token)) {
                                Utils::log('GSC API retry - Fresh token obtained successfully, retrying request', 3);
                                
                                // Verify the token is actually different
                                if ($new_access_token !== $decrypted_token) {
                                    Utils::log('GSC API retry - Token verified as different, updating', 3);
                                    // Update the static property to use the fresh token immediately
                                    self::$access_token = $new_access_token;
                                    $decrypted_token = $new_access_token;
                                    $header_arr['headers']['Authorization'] = 'Bearer ' . $decrypted_token;
                                    continue; // Retry the request
                                } else {
                                    Utils::log('GSC API retry - Token is the same, not updating', 2);
                                }
                            } else {
                                Utils::log('GSC API retry - Failed to get fresh token: ' . (is_wp_error($new_access_token) ? $new_access_token->get_error_message() : 'Unknown error'), 2);
                            }
                        }
                        
                        // If we get here, all retries failed
                        update_option('seobooster_needs_reauth', '1'); // set the flag for reauthentication.
                        Utils::log( 'Token expired or invalid after retry attempts. Please try to re-authenticate with Google', 2 );
                        return;
                    }

                    if ( $data && is_array( $data ) && isset( $data['rows'] ) && is_array( $data['rows'] ) ) {
                        foreach ( $data['rows'] as $row ) {
                            if ( !isset( $row['keys'][0], $row['keys'][1], $row['keys'][2] ) ) {
                                continue;
                            }
                            
                            // Add to batch
                            $current_batch[] = $row;
                            
                            // Process batch when full
                            if ( count( $current_batch ) >= $batch_size ) {
                                self::process_simple_batch( $current_batch, $wpdb );
                                $total_fetched += count( $current_batch );
                                $current_batch = [];
                                
                                // Simple cleanup
                                if ( function_exists( 'gc_collect_cycles' ) ) {
                                    gc_collect_cycles();
                                }
                            }
                        }
                        
                        // Process remaining batch
                        if ( !empty( $current_batch ) ) {
                            self::process_simple_batch( $current_batch, $wpdb );
                            $total_fetched += count( $current_batch );
                            $current_batch = [];
                        }
                        if ( count( $data['rows'] ) === $row_limit ) {
                            $start_row += $row_limit;
                            Utils::log( sprintf( 
                                /* translators: %s: formatted row number */
                                esc_html__( 'More than %d results found, fetching more keywords starting from row %s', 'seo-booster' ),
                                $row_limit,
                                number_format_i18n( $start_row )
                             ), 10 );
                            
                            // Save progress (less frequently)
                            if ($start_row % 1000 === 0) { // Only save every 1000 rows
                                set_transient($progress_key, [
                                    'start_row' => $start_row,
                                    'total_fetched' => $total_fetched
                                ], HOUR_IN_SECONDS);
                            }
                            
                            // Force cleanup between API calls
                            if ( function_exists( 'gc_collect_cycles' ) ) {
                                gc_collect_cycles();
                            }
                        } else {
                            // Clear progress when complete
                            delete_transient($progress_key);
                            break;
                        }
                    } else {
                        break;
                    }
                } catch ( \Exception $e ) {
                    /* translators: %s: error message from access token */
                    Utils::log( sprintf( 'Error fetching query keywords: %s', $e->getMessage() ), 2 );
                    printf( '<div class="seobooster-notice notice notice-error"><p>%s</p></div>', esc_html( $e->getMessage() ) );
                }
            } while ( true );
            /* translators: 1: formatted number of records, 2: site URL */
            Utils::log( sprintf( 'Processed %1$s records from %2$s', number_format_i18n( $total_fetched ), $site_url ), 10 );
            
            // Log final memory usage
            $final_memory = memory_get_usage();
            $peak_memory = memory_get_peak_usage();
            Utils::log(sprintf('Final memory usage: %s MB (Peak: %s MB)', 
                number_format($final_memory / 1024 / 1024, 2),
                number_format($peak_memory / 1024 / 1024, 2)
            ), 1);
            
            as_schedule_single_action( time(), 'sb_gsc_schedule_all_pages' );
            Utils::log( 'Scheduled keyword analysis for all pages', 10 );
        } catch ( \Exception $e ) {
            /* translators: %s: error message from access token */
            Utils::log( sprintf( 'Error fetching query keywords: %s', $e->getMessage() ), 2 );
            printf( '<div class="seobooster-notice notice notice-error"><p>%s</p></div>', esc_html( $e->getMessage() ) );
        }
    }

    /**
     * Get memory limit in bytes
     * 
     * @return int Memory limit in bytes
     */
    private static function get_memory_limit_bytes() {
        $memory_limit = ini_get('memory_limit');
        if ($memory_limit == -1) {
            return PHP_INT_MAX; // Unlimited
        }
        
        $value = trim($memory_limit);
        $last_char = strtolower(substr($value, -1));
        $numeric_value = (int) substr($value, 0, -1);
        
        switch ($last_char) {
            case 'g':
                return $numeric_value * 1024 * 1024 * 1024;
            case 'm':
                return $numeric_value * 1024 * 1024;
            case 'k':
                return $numeric_value * 1024;
            default:
                return (int) $memory_limit;
        }
    }

    /**
     * Process a batch of rows with bulk SQL operations to minimize queries
     * 
     * @param array $batch Array of rows to process
     * @param wpdb $wpdb WordPress database object
     */
    private static function process_simple_batch($batch, $wpdb) {
        if (empty($batch)) {
            return;
        }
        
        // Prepare data for bulk operations
        $queries = [];
        $pages = [];
        $dates = [];
        $clicks = [];
        $impressions = [];
        $ctrs = [];
        $positions = [];
        
        foreach ($batch as $row) {
            $queries[] = sanitize_text_field($row['keys'][0]);
            $url_parts = explode('#', $row['keys'][1], 2);
            $pages[] = esc_url_raw($url_parts[0]);
            $dates[] = sanitize_text_field($row['keys'][2]);
            $clicks[] = absint($row['clicks']);
            $impressions[] = absint($row['impressions']);
            $ctrs[] = floatval($row['ctr']);
            $positions[] = floatval($row['position']);
        }
        
        // Build bulk query to check existing keywords
        $placeholders = [];
        $values = [];
        foreach ($queries as $i => $query) {
            $placeholders[] = "(%s, %s)";
            $values[] = $query;
            $values[] = $pages[$i];
        }
        
        // Single query to get all existing keywords
        $existing_keywords = [];
        if (!empty($placeholders)) {
            $sql = "SELECT id, query, page FROM {$wpdb->prefix}sb2_query_keywords WHERE (query, page) IN (" . implode(',', $placeholders) . ")";
            $results = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
            
            foreach ($results as $result) {
                $key = $result['query'] . '|' . $result['page'];
                $existing_keywords[$key] = $result['id'];
            }
        }
        
        // Prepare bulk inserts and updates
        $keyword_inserts = [];
        $keyword_updates = [];
        $history_inserts = [];
        
        foreach ($queries as $i => $query) {
            $page = $pages[$i];
            $date = $dates[$i];
            $key = $query . '|' . $page;
            
            if (isset($existing_keywords[$key])) {
                // Will update existing
                $keyword_updates[] = [
                    'id' => $existing_keywords[$key],
                    'date' => $date
                ];
                $keyword_id = $existing_keywords[$key];
            } else {
                // Will insert new
                $keyword_inserts[] = [
                    'query' => $query,
                    'page' => $page,
                    'first_seen_date' => $date,
                    'latest_date' => $date
                ];
                $keyword_id = 'NEW_' . count($keyword_inserts);
            }
            
            // Prepare history insert
            $history_inserts[] = [
                'keyword_id' => $keyword_id,
                'clicks' => $clicks[$i],
                'impressions' => $impressions[$i],
                'ctr' => $ctrs[$i],
                'position' => $positions[$i],
                'date' => $date
            ];
        }
        
        // Bulk insert new keywords
        if (!empty($keyword_inserts)) {
            $insert_values = [];
            $insert_placeholders = [];
            foreach ($keyword_inserts as $insert) {
                $insert_placeholders[] = "(%s, %s, %s, %s)";
                $insert_values[] = $insert['query'];
                $insert_values[] = $insert['page'];
                $insert_values[] = $insert['first_seen_date'];
                $insert_values[] = $insert['latest_date'];
            }
            
            $sql = "INSERT IGNORE INTO {$wpdb->prefix}sb2_query_keywords (query, page, first_seen_date, latest_date) VALUES " . implode(',', $insert_placeholders);
            $wpdb->query($wpdb->prepare($sql, $insert_values));
            
            // Get the new IDs for history inserts
            $new_ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}sb2_query_keywords ORDER BY id DESC LIMIT " . count($keyword_inserts));
            $new_ids = array_reverse($new_ids); // Reverse to match insertion order
            
            // Update history inserts with real IDs
            foreach ($history_inserts as $i => $history) {
                if (is_string($history['keyword_id']) && strpos($history['keyword_id'], 'NEW_') === 0) {
                    $new_index = (int)substr($history['keyword_id'], 4) - 1;
                    $history_inserts[$i]['keyword_id'] = $new_ids[$new_index];
                }
            }
        }
        
        // Bulk update existing keywords
        if (!empty($keyword_updates)) {
            foreach ($keyword_updates as $update) {
                $wpdb->update(
                    "{$wpdb->prefix}sb2_query_keywords",
                    ['latest_date' => $update['date']],
                    ['id' => $update['id']],
                    ['%s'], ['%d']
                );
            }
        }
        
        // Bulk insert history (ignore duplicates)
        if (!empty($history_inserts)) {
            $history_values = [];
            $history_placeholders = [];
            foreach ($history_inserts as $history) {
                $history_placeholders[] = "(%d, %d, %d, %f, %f, %s)";
                $history_values[] = $history['keyword_id'];
                $history_values[] = $history['clicks'];
                $history_values[] = $history['impressions'];
                $history_values[] = $history['ctr'];
                $history_values[] = $history['position'];
                $history_values[] = $history['date'];
            }
            
            $sql = "INSERT IGNORE INTO {$wpdb->prefix}sb2_query_keywords_history (query_keywords_id, clicks, impressions, ctr, position, date) VALUES " . implode(',', $history_placeholders);
            $wpdb->query($wpdb->prepare($sql, $history_values));
        }
    }

    /**
     * Display authentication status and handle site selection.
     *
     * @since   v0.0.1
     * @version v1.0.0  Sunday, September 1st, 2024.
     * @return  void
     */
        public static function display_auth_status() {        
        // Authentication content has been moved to views/dashboard.php in the .col1 section
        return '';
    }


    /**
     * Get access token with caching and refresh logic.
     *
     * @since   v0.0.1
     * @version v1.0.2  Tuesday, January 14th, 2025.
     * @access  public static
     * @param   boolean $force_refresh Whether to force a token refresh.
     * @return  string|WP_Error Access token if successful, WP_Error on failure.
     */
    public static function get_access_token( $force_refresh = false ) {
        
        // Check if we need to proactively refresh the token
        if (!$force_refresh) {
            $force_refresh = self::should_refresh_token();
        }
        
        if ($force_refresh) {
            self::$access_token = null;
            // Clear cached token (streamlined to options only)
            delete_option('seobooster_access_token');
        }
        
        if (!$force_refresh && !is_null(self::$access_token)) {
            return self::$access_token;
        }
        
        if (!$force_refresh && !isset(self::$access_token)) {  
            // Check options only (streamlined to one storage location)
            $access_token = get_option( 'seobooster_access_token' );
            if (isset($access_token) && !empty($access_token)) {
                // Check if cached token is encrypted (has EN_ prefix) and clear it
                if (strpos($access_token, 'EN_') === 0) {
                    delete_option('seobooster_access_token');
                    delete_option('seobooster_access_token_expiration');
                } else {
                    // Check if we have expiration info for this cached token
                    $expiration_timestamp = get_option('seobooster_access_token_expiration');
                    if ($expiration_timestamp) {
                        return $access_token;
                    } else {
                        Utils::log('get_access_token - Found cached token but no expiration info, forcing refresh', 3);
                        // Force refresh to get expiration info
                        $force_refresh = true;
                    }
                }
            }
        }
        
        $access_token = self::get_fresh_token_from_api();
        
        if (is_wp_error($access_token)) {
            // Auth expired (401/invalid_grant from API) or legacy invalid_grant: prompt re-auth
            $code = $access_token->get_error_code();
            if ( $code === 'auth_expired' || $code === 'invalid_grant' ) {
                Utils::log( 'Google connection expired - re-authentication required', 2 );
                self::reset_authentication();
                return new \WP_Error(
                    $code,
                    $access_token->get_error_message()
                );
            }
            return $access_token;
        }
        
        // Store in static property for immediate use
        self::$access_token = $access_token;
        return $access_token;
    }

    /**
     * Get fresh token directly from seoboosterauth.com API.
     *
     * @since   v1.0.2
     * @version v1.0.1  Tuesday, January 14th, 2025.
     * @access  private static
     * @return  string|WP_Error Access token if successful, WP_Error on failure.
     */
    private static function get_fresh_token_from_api() {
        // Initialize plugin settings and variables
        self::init();
        
        // Do not call the API without a linked Google account (user must complete OAuth first)
        if ( empty( self::$google_email ) ) {
            Utils::log( 'get_fresh_token_from_api - No Google email stored; user must complete Connect with Google first', 2 );
            return new \WP_Error( 'no_google_email', __( 'No Google account linked. Please connect with Google first.', 'seo-booster' ) );
        }
        
        // Get necessary IDs and keys using fallback methods
        $install_id = self::get_installation_id();
        $site_private_key = self::get_site_private_key();
        
        $nonce = current_time('Y-m-d', true);
        $pk_hash = hash('sha512', $site_private_key . '|' . $nonce);
        $authentication_string = base64_encode($pk_hash . '|' . $nonce);

        // When making the API request, indicate if this is an alternative ID
        $is_alternative = !function_exists('seobooster_fs') || !seobooster_fs()->is_registered();
        
        Utils::log('Calling seoboosterauth.com API to get access token', 3);

        
        $response = wp_remote_post('https://seoboosterauth.com/get-access-token', array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . sanitize_text_field($authentication_string),
            ),
            'body' => wp_json_encode(array(
                'google_email' => sanitize_email(self::$google_email),
                'install_id'   => $install_id,
                'auth_token'   => sanitize_text_field($authentication_string),
                'is_alternative' => $is_alternative,
            )),
            'timeout' => 15, // Increased timeout
        ));

        // Handle errors in the response
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ($response_code !== 200) {
            $error_message = isset($data['error']) ? $data['error'] : 'HTTP ' . $response_code;
            $details = isset($data['details']) ? $data['details'] : '';
            // 401 or auth-related body: treat as auth expired so UI can show "Reconnect with Google"
            if ( $response_code === 401 || in_array( $details, array( 'invalid_grant', 'invalid_token' ), true ) ) {
                return new \WP_Error(
                    'auth_expired',
                    isset($data['message']) ? $data['message'] : __( 'Google connection expired. Please reconnect with Google.', 'seo-booster' ),
                    array( 'status' => $response_code, 'details' => $details )
                );
            }
            return new \WP_Error('api_error', $error_message);
        }

        // Check for expiration information - try different field names
        $expires_at_timestamp = null;
        
        if (isset($data['expires_at_unix']) && is_numeric($data['expires_at_unix'])) {
            // Use the Unix timestamp directly if available
            $expires_at_timestamp = (int)$data['expires_at_unix'];
        } elseif (isset($data['expires_at']) && !empty($data['expires_at'])) {
            // Parse the ISO date string
            $expires_at_timestamp = strtotime($data['expires_at']);
        } elseif (isset($data['expires_at_date']) && !empty($data['expires_at_date'])) {
            // Fallback to old field name
            $expires_at_timestamp = strtotime($data['expires_at_date']);
        }
        
        if ($expires_at_timestamp !== null) {
            // Validate the parsed timestamp - check for reasonable year range
            if ($expires_at_timestamp === false || $expires_at_timestamp < 0 || $expires_at_timestamp > 2147483647) {
                // Don't store invalid expiration, but continue with token
                // Clear any existing invalid expiration
                delete_option('seobooster_access_token_expiration');
            } else {
                $current_timestamp = time(); // Current UTC timestamp
                $time_until_expiry = $expires_at_timestamp - $current_timestamp;
                
                Utils::log('seoboosterauth.com API - Time until expiry: ' . round($time_until_expiry / 60, 2) . ' minutes', 3);
                
                // Check if token has already expired
                if ($time_until_expiry <= 0) {
                    return new \WP_Error('token_expired', 'Token has already expired');
                }
                
                // Store the expiration timestamp for later use
                update_option('seobooster_access_token_expiration', $expires_at_timestamp, false);
            }
        }
        // Check if the access token is returned and save it
        if ( isset( $data['access_token'] ) ) {
            $access_token = sanitize_text_field( $data['access_token'] );
            
            // Save the plain token (no encryption for now)
            update_option( 'seobooster_access_token', $access_token, false );

            if ( isset( $time_until_expiry ) ) {
                $time_until_expiry_minutes = round($time_until_expiry / 60, 2);
                Utils::log( sprintf( 'Token updated, will expire in %0.2f minutes', $time_until_expiry_minutes ), 3 );
            }
            
            Utils::log('Access token obtained and saved successfully', 3);
            return $access_token;
        }

        return new \WP_Error('no_token', 'No access token received from API');
    }

    /**
     * Check if the current token should be refreshed proactively.
     *
     * @since   v1.0.2
     * @version v1.0.1  Tuesday, January 14th, 2025.
     * @access  private static
     * @return  boolean True if token should be refreshed, false otherwise.
     */
    private static function should_refresh_token() {
        $expiration_timestamp = get_option('seobooster_access_token_expiration');
        
        if (!$expiration_timestamp) {
            // Check if we have a token but no expiration - this indicates a problem
            $access_token = get_option('seobooster_access_token');
            if ($access_token && !empty($access_token)) {
                Utils::log('should_refresh_token - Found access token but no expiration timestamp - this indicates a problem', 2);
            }
            return false; // No expiration info, don't force refresh
        }
        
        
        $current_timestamp = time();
        $time_until_expiry = $expiration_timestamp - $current_timestamp;
        
        
        // Refresh token if it expires within the next 30 minutes
        $refresh_threshold = 15 * MINUTE_IN_SECONDS;
        
        if ($time_until_expiry <= $refresh_threshold) {
            Utils::log(sprintf('Token expires in %0.2f minutes, proactively refreshing', $time_until_expiry / 60), 3);
            return true;
        }

        return false;
    }

    /**
     * Fetch sites from Google Search Console.
     *
     * @since   v0.0.1
     * @version v1.0.1  Tuesday, August 20th, 2024.
     * @access  public static
     * @return  array|WP_Error Array of sites if successful, WP_Error on failure.
     */
    public static function fetch_sites() {
        Utils::log('Starting fetch_sites()', 1);
        // Get installation ID using fallback mechanism
        $install_id = self::get_installation_id();
        
        if (!$install_id) {
            Utils::log('No install ID found in fetch_sites()', 5);
            return new \WP_Error('no_install_id', esc_html__('No install ID found', 'seo-booster'));
        }
        
        Utils::log('Getting access token for sites fetch', 1);
        $access_token = self::get_access_token();
        
        if (is_wp_error($access_token)) {
            Utils::log('Error getting access token: ' . $access_token->get_error_message(), 5);
            return $access_token;
        }
        
        if (!$access_token) {
            Utils::log('No access token available', 5);
            return new \WP_Error('no_access_token', esc_html__('No access token available', 'seo-booster'));
        }
        
        // Use token directly (no decryption for now)
        $decrypted_token = $access_token;
        
        Utils::log('Fetching sites using decrypted token', 10);

        $response = wp_remote_get('https://www.googleapis.com/webmasters/v3/sites', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $decrypted_token,
            ),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            Utils::log('WP_Error in fetch_sites(): ' . $response->get_error_message(), 2);
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check specifically for authentication issues (401 Unauthorized)
        if ($status_code === 401) {
            Utils::log('Authentication failed with 401 status code', 5);
            
            // Check for specific error message about invalid credentials
            if (isset($data['error']) && isset($data['error']['message']) && 
                (strpos($data['error']['message'], 'invalid authentication credentials') !== false || 
                 strpos($data['error']['message'], 'Invalid Credentials') !== false)) {
                
                Utils::log('Invalid credentials detected. User needs to re-authenticate.', 5);
                
                // Clear existing tokens to force re-auth
                delete_option('seobooster_access_token');
                delete_option('seobooster_gsc_access_token');
                delete_transient('seobooster_access_token');
                
                return new \WP_Error(
                    'authentication_required', 
                    esc_html__('Your Google authentication has expired or is invalid. Please re-authenticate with Google.', 'seo-booster')
                );
            }
        }
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Utils::log('JSON decode error in fetch_sites(): ' . json_last_error_msg(), 2);
            return new \WP_Error('json_decode_error', esc_html__('Error decoding JSON response', 'seo-booster'));
        }
        
        $sitelist = array();

        if (isset($data['siteEntry']) && is_array($data['siteEntry'])) {
            $sitelist = array_map(function($site) {
                $siteUrl = $site['siteUrl'];
                $permissionLevel = isset($site['permissionLevel']) ? sanitize_text_field($site['permissionLevel']) : '';
                
                // If it's a domain property (starts with sc-domain:), don't use esc_url_raw
                if (strpos($siteUrl, 'sc-domain:') === 0) {
                    $url = sanitize_text_field($siteUrl);
                } else {
                    // Otherwise it's a URL, so use esc_url_raw
                    $url = esc_url_raw($siteUrl);
                }
                
                return [
                    'siteUrl' => $url,
                    'permissionLevel' => $permissionLevel
                ];
            }, $data['siteEntry']);
        }
        
        $sitelist_count = count($sitelist);
        
        // Extract only the URLs for logging to avoid array to string conversion
        $site_urls_for_log = array_map(function($site) {
            return is_array($site) ? $site['siteUrl'] : $site;
        }, array_slice($sitelist, -3));
        
        Utils::log('Entries in sitelist fetched: ' . $sitelist_count . 
                   '. Last three sites: ' . implode(', ', $site_urls_for_log), 10);
        
        update_option('seobooster_gsc_sites', $sitelist, false);
        return $sitelist;
    }


    /**
     * Reset authentication settings.
     *
     * @since   v0.0.1
     * @version v1.0.2  Tuesday, January 14th, 2025.
     * @access  public static
     * @return  void
     */
    public static function reset_authentication() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Delete all token-related options
        delete_option('seobooster_access_token');
        delete_option('seobooster_access_token_expiration');
        delete_transient('seobooster_access_token');
        
        // Delete GSC specific options
        delete_option('seobooster_gsc_access_token');
        delete_option('seobooster_gsc_sites');
        
        // Delete user selection and preferences
        delete_option('seobooster_selected_site');
        delete_option('seobooster_google_email');
        
        // Clear reauth flag
        self::clear_reauth_flag();
        
        // Delete alternative IDs (optional - you might want to keep these)
        // delete_option('seobooster_alt_install_id');
        // delete_option('seobooster_alt_site_key');
        
        // Reset cached token in memory for current request
        self::$access_token = null;
        self::$token_refreshed = false;
        
        Utils::log('Authentication settings have been completely reset.', 10);
    }


    /**
     * Handle reset authentication action.
     *
     * @since   v0.0.1
     * @version v1.0.1  Tuesday, September 3rd, 2024.
     * @access  public static
     * @return  void
     */
    public static function handle_reset_authentication() {
        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'reset_authentication' ) {
            check_admin_referer( 'reset_authentication_nonce' );
            Utils::log( 'Resetting authentication settings.', 5 );
            self::reset_authentication();
            wp_safe_redirect( add_query_arg( 'reset', '1', admin_url( 'admin.php?page=sb2_settings' ) ) );
            exit;
        }
    }

    /**
     * Get installation ID - either from Freemius or generate a fallback
     * 
     * @return string|int Installation ID to use for API communication
     */
    private static function get_installation_id() {
        // First try to get Freemius installation ID
        if (function_exists('seobooster_fs') && seobooster_fs()->is_registered()) {
            try {
                $site = seobooster_fs()->get_site();
                if ($site && !empty($site->id)) {
                    return (string) $site->id; // Cast to string to be safe
                }
            } catch (\Exception $e) {
                Utils::log('Error retrieving Freemius install ID: ' . $e->getMessage(), 3);
            }
        }
        
        // Fall back to stored alternative ID
        $alt_install_id = get_option('seobooster_alt_install_id');
        
        // If no alternative ID exists, create one
        if (empty($alt_install_id)) {
            // Create a unique, reproducible ID based on site URL and a salt
            $salt = 'seobooster_' . substr(wp_generate_password(8, false, false), 0, 8);
            $site_url = site_url();
            $alt_install_id = 'ALT_' . substr(md5($site_url . $salt), 0, 16);
            
            // Store it for future use
            update_option('seobooster_alt_install_id', $alt_install_id, false);
            Utils::log('Created alternative installation ID: ' . $alt_install_id, 1);
        }
        
        return $alt_install_id;
    }

    /**
     * Get site private key - either from Freemius or generate a fallback
     * 
     * @return string Site private key to use for API authentication
     */
    private static function get_site_private_key() {
        // First try to get Freemius site key
        if (function_exists('seobooster_fs') && seobooster_fs()->is_registered()) {
            try {
                $site = seobooster_fs()->get_site();
                if ($site && !empty($site->secret_key)) {
                    return $site->secret_key;
                }
            } catch (\Exception $e) {
                Utils::log('Error retrieving Freemius site key: ' . $e->getMessage(), 3);
            }
        }
        
        // Fall back to stored alternative key
        $alt_site_key = get_option('seobooster_alt_site_key');
        
        // If no alternative key exists, create one
        if (empty($alt_site_key)) {
            // Create a strong, unique key
            $alt_site_key = wp_generate_password(32, true, true);
            
            // Store it for future use
            update_option('seobooster_alt_site_key', $alt_site_key, false);
            Utils::log('Created alternative site private key', 1);
        }
        
        return $alt_site_key;
    }

    /**
     * Build OAuth authentication parameters for seoboosterauth.com links.
     *
     * @param string|null $return_to Optional return URL after OAuth.
     * @return array{install_id: string, auth_token: string, return_to: string}
     */
    public static function get_oauth_auth_params( $return_to = null ) {
        if ( null === $return_to ) {
            $return_to = admin_url( 'admin.php?page=sb2_dashboard&auth=1' );
        }

        $install_id         = self::get_installation_id();
        $site_private_key   = self::get_site_private_key();
        $nonce              = gmdate( 'Y-m-d' );
        $pk_hash            = hash( 'sha512', $site_private_key . '|' . $nonce );
        $authentication_string = base64_encode( $pk_hash . '|' . $nonce );

        return array(
            'install_id' => $install_id,
            'auth_token' => $authentication_string,
            'return_to'  => $return_to,
        );
    }

    /**
     * Display information about local domain authentication limitations.
     * 
     * @since   v6.1.16
     * @return  string HTML content with local domain authentication information
     */
    public static function get_local_domain_info() {
        $local_domains = array(
            '.local' => esc_html__('Used by Bonjour/mDNS for local network service discovery', 'seo-booster'),
            '.localhost' => esc_html__('Reserved domain for localhost usage', 'seo-booster'),
            '.test' => esc_html__('Reserved domain for testing environments', 'seo-booster'),
            '.dev' => esc_html__('Now owned by Google, used for development environments', 'seo-booster'),
            '.example' => esc_html__('Reserved domain for examples', 'seo-booster'),
            '.invalid' => esc_html__('Reserved domain that will never resolve', 'seo-booster'),
            'localhost' => esc_html__('Standard hostname for the local machine', 'seo-booster'),
            '127.0.0.1' => esc_html__('Loopback IP address', 'seo-booster')
        );
        
        $output = '<div class="local-domain-info">';
 
        $output .= '<p>' . esc_html__('You are attempting to authenticate with Google from a local development domain. OAuth providers typically reject authentication callbacks to local domains for security reasons:', 'seo-booster') . '</p>';
        
        $output .= '<p>' . esc_html__('Authentication may still work in some cases.', 'seo-booster') . '</p>';

        $output .= '<p>' . esc_html__('Please note that HTTP websites will always fail authentication. Only SSL links are allowed for security reasons.', 'seo-booster') . '</p>';
        
        $output .= '<p><strong>' . esc_html__('Problematic domains include:', 'seo-booster') . '</strong></p>';
        
        $output .= '<ul class="local-domains-list">';
        foreach ($local_domains as $domain => $description) {
            $output .= '<li><code>' . esc_html($domain) . '</code> - ' . $description . '</li>';
        }
        $output .= '</ul>';
        
        $output .= '<p><a href="' . Utils::generate_cp_web_link('readmore_local_domains', '/docs/errors-and-troubleshooting/google-authentication-fails/') . '" target="_blank">' . esc_html__('Learn more about authentication with local domains', 'seo-booster') . '</a></p>';
        
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Clear the reauthentication flag when authentication is successful.
     *
     * @since   v1.0.2
     * @version v1.0.0  Tuesday, January 14th, 2025.
     * @access  public static
     * @return  void
     */
    public static function clear_reauth_flag() {
        delete_option('seobooster_needs_reauth');
        delete_option('seobooster_last_fresh_token_attempt');
        Utils::log('Reauthentication flag and fresh token attempt timestamp cleared', 3);
    }

    /**
     * Clear fresh token attempt timestamp to allow immediate retry.
     *
     * @since   v1.0.2
     * @version v1.0.1  Tuesday, January 14th, 2025.
     * @access  public static
     * @return  void
     */
    public static function clear_fresh_token_attempt_timestamp() {
        delete_option('seobooster_last_fresh_token_attempt');
        Utils::log('Fresh token attempt timestamp cleared', 3);
    }

    /**
     * Load GSC highlighting scripts and styles when highlighting is requested.
     *
     * @author	Lars Koudal
     * @since	v0.0.1
     * @version	v1.0.0	Friday, January 17th, 2025.
     * @access	public static
     * @return	void
     */
    public static function load_gsc_highlight_scripts() {
        // Check if GSC highlighting is requested
        if (!isset($_GET['seobooster_showgsc']) || $_GET['seobooster_showgsc'] !== '1') {
            return;
        }

        // Only load on frontend
        if (is_admin()) {
            return;
        }

        $plugin_version = Utils::get_plugin_version();

        // Get the current page permalink for accurate URL matching
        $current_permalink = get_permalink();
        if (is_home() || is_front_page()) {
            $current_permalink = home_url('/');
        } elseif (is_archive()) {
            $current_permalink = get_permalink(get_queried_object_id());
        } elseif (!$current_permalink) {
            // Fallback to current URL
            $current_permalink = home_url($_SERVER['REQUEST_URI']);
        }

        // Enqueue GSC highlighting CSS (use minified version)
        wp_enqueue_style(
            'seobooster-gsc-highlight',
            SEOBOOSTER_PLUGINURL . 'css/sb-gsc-highlight.css',
            [],
            filemtime(SEOBOOSTER_PLUGINPATH . 'css/sb-gsc-highlight.css'),
            'all'
        );

        // Enqueue GSC highlighting JavaScript (use minified version)
        wp_enqueue_script(
            'seobooster-gsc-highlight',
            SEOBOOSTER_PLUGINURL . 'js/seobooster-gsc-highlight.js',
            ['jquery'],
            filemtime(SEOBOOSTER_PLUGINPATH . 'js/seobooster-gsc-highlight.js'),
            true
        );

        // Localize script with necessary data
        wp_localize_script('seobooster-gsc-highlight', 'seobooster_gsc_highlight', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('seobooster_gsc_highlight_nonce'),
            'current_permalink' => $current_permalink,
            'strings' => array(
                'loading' => __('Loading GSC keywords...', 'seo-booster'),
                'error' => __('Error loading GSC keywords', 'seo-booster'),
                'no_keywords' => __('No GSC keywords found for this page', 'seo-booster'),
            ),
        ));
    }

    /**
     * Validate token with rate limiting and tracking.
     *
     * @since   v1.0.2
     * @version v1.0.1  Tuesday, January 14th, 2025.
     * @access  public static
     * @param   boolean $force_check Force check even if rate limited.
     * @return  array|WP_Error Array with validation status or WP_Error on failure.
     */
    public static function validate_token($force_check = false) {
        $last_check = get_option('seobooster_token_last_check', 0);
        $check_interval = 6 * HOUR_IN_SECONDS; // 6 hours between checks
        $current_time = time();
        
        Utils::log('Token validation started - Force check: ' . ($force_check ? 'Yes' : 'No'), 3);
        
        // Rate limiting - don't check too frequently unless forced
        if (!$force_check && ($current_time - $last_check) < $check_interval) {
            $time_until_next = $check_interval - ($current_time - $last_check);
            $hours_until_next = round($time_until_next / HOUR_IN_SECONDS, 1);
            
            Utils::log('Token validation skipped due to rate limiting. Next check in ' . $hours_until_next . ' hours', 3);
            
            return array(
                'valid' => true,
                'message' => sprintf(__('Token validation skipped. Next check in %s hours.', 'seo-booster'), $hours_until_next),
                'last_check' => $last_check,
                'next_check' => $last_check + $check_interval,
                'skipped' => true
            );
        }
        
        // Get current token
        $access_token = self::get_access_token();
        if (is_wp_error($access_token)) {
            Utils::log('Token validation failed - Error getting access token: ' . $access_token->get_error_message(), 2);
            update_option('seobooster_token_last_check', $current_time);
            
            // Check if we should attempt to get a fresh token
            $last_fresh_attempt = get_option('seobooster_last_fresh_token_attempt', 0);
            $fresh_attempt_interval = 15 * MINUTE_IN_SECONDS; // 15 minutes between fresh token attempts
            
            if (($current_time - $last_fresh_attempt) >= $fresh_attempt_interval) {
                Utils::log('Token validation failed - Attempting to get fresh token from API server after access token error', 2);
                update_option('seobooster_last_fresh_token_attempt', $current_time);
                
                // Attempt to get a fresh token using get_access_token with force refresh
                $fresh_token = self::get_access_token(true);
                
                if (!is_wp_error($fresh_token)) {
                    Utils::log('Token validation - Fresh token obtained successfully after access token error', 2);
                    
                    // Update the static property to use the fresh token immediately
                    self::$access_token = $fresh_token;
                    
                    // Test the fresh token
                    $fresh_google_api_url = 'https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=' . urlencode($fresh_token);
                    $fresh_test_response = wp_remote_get($fresh_google_api_url, array(
                        'timeout' => 10,
                        'headers' => array(
                            'User-Agent' => 'SEOBooster/1.0'
                        )
                    ));
                    
                    if (!is_wp_error($fresh_test_response)) {
                        $fresh_response_code = wp_remote_retrieve_response_code($fresh_test_response);
                        if ($fresh_response_code === 200) {
                            Utils::log('Token validation - Fresh token is valid after access token error', 2);
                            self::clear_reauth_flag();
                            
                            return array(
                                'valid' => true,
                                'message' => __('Token refreshed successfully after access token error', 'seo-booster'),
                                'last_check' => $current_time,
                                'next_check' => $current_time + $check_interval,
                                'refreshed' => true
                            );
                        }
                    }
                    
                    Utils::log('Token validation - Fresh token also failed after access token error', 2);
                } else {
                    Utils::log('Token validation - Failed to get fresh token after access token error: ' . $fresh_token->get_error_message(), 2);
                }
            } else {
                $minutes_until_next_attempt = round(($fresh_attempt_interval - ($current_time - $last_fresh_attempt)) / 60, 1);
                Utils::log('Token validation - Skipping fresh token attempt after access token error. Next attempt in ' . $minutes_until_next_attempt . ' minutes', 2);
            }
            
            // Set reauth flag if token is invalid and fresh token attempt failed or was skipped
            update_option('seobooster_needs_reauth', '1');
            
            return array(
                'valid' => false,
                'message' => $access_token->get_error_message(),
                'last_check' => $current_time,
                'next_check' => $current_time + $check_interval,
                'skipped' => false
            );
        }
        
        Utils::log('Token validation - Got access token, testing with Google OAuth API', 3);
        
        // Test token by making a simple API call to Google OAuth API
        $google_api_url = 'https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=' . urlencode($access_token);
        Utils::log('Token validation - Calling Google OAuth API: ' . $google_api_url, 3);
        
        $test_response = wp_remote_get($google_api_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'SEOBooster/1.0'
            )
        ));
        
        update_option('seobooster_token_last_check', $current_time);
        
        if (is_wp_error($test_response)) {
            Utils::log('Token validation failed - Network error calling Google OAuth API: ' . $test_response->get_error_message(), 2);
            
            // Check if we should attempt to get a fresh token
            $last_fresh_attempt = get_option('seobooster_last_fresh_token_attempt', 0);
            $fresh_attempt_interval = 15 * MINUTE_IN_SECONDS; // 15 minutes between fresh token attempts
            
            if (($current_time - $last_fresh_attempt) >= $fresh_attempt_interval) {
                Utils::log('Token validation failed - Attempting to get fresh token from API server after network error', 2);
                update_option('seobooster_last_fresh_token_attempt', $current_time);
                
                // Attempt to get a fresh token using get_access_token with force refresh
                $fresh_token = self::get_access_token(true);
                
                if (!is_wp_error($fresh_token)) {
                    Utils::log('Token validation - Fresh token obtained successfully after network error', 2);
                    
                    // Update the static property to use the fresh token immediately
                    self::$access_token = $fresh_token;
                    
                    // Test the fresh token
                    $fresh_google_api_url = 'https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=' . urlencode($fresh_token);
                    $fresh_test_response = wp_remote_get($fresh_google_api_url, array(
                        'timeout' => 10,
                        'headers' => array(
                            'User-Agent' => 'SEOBooster/1.0'
                        )
                    ));
                    
                    if (!is_wp_error($fresh_test_response)) {
                        $fresh_response_code = wp_remote_retrieve_response_code($fresh_test_response);
                        if ($fresh_response_code === 200) {
                            Utils::log('Token validation - Fresh token is valid after network error', 2);
                            self::clear_reauth_flag();
                            
                            return array(
                                'valid' => true,
                                'message' => __('Token refreshed successfully after network error', 'seo-booster'),
                                'last_check' => $current_time,
                                'next_check' => $current_time + $check_interval,
                                'refreshed' => true
                            );
                        }
                    }
                    
                    Utils::log('Token validation - Fresh token also failed after network error', 2);
                } else {
                    Utils::log('Token validation - Failed to get fresh token after network error: ' . $fresh_token->get_error_message(), 2);
                }
            } else {
                $minutes_until_next_attempt = round(($fresh_attempt_interval - ($current_time - $last_fresh_attempt)) / 60, 1);
                Utils::log('Token validation - Skipping fresh token attempt after network error. Next attempt in ' . $minutes_until_next_attempt . ' minutes', 2);
            }
            
            // Set reauth flag if network error occurs and fresh token attempt failed or was skipped
            update_option('seobooster_needs_reauth', '1');
            
            return array(
                'valid' => false,
                'message' => $test_response->get_error_message(),
                'last_check' => $current_time,
                'next_check' => $current_time + $check_interval,
                'skipped' => false
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($test_response);
        $response_body = wp_remote_retrieve_body($test_response);
        $response_data = json_decode($response_body, true);
        
        if ($response_code === 200 && isset($response_data['expires_in'])) {
            $expires_in = $response_data['expires_in'];
            $hours_until_expiry = round($expires_in / 3600, 1);
            
            Utils::log('Token validation - Token expires in ' . $hours_until_expiry . ' hours', 3);
            
            // If token expires within 24 hours, refresh it
            if ($expires_in < 24 * 3600) {
                Utils::log('Token validation - Token expires soon, refreshing...', 3);
                $refreshed_token = self::get_access_token(true);
                if (!is_wp_error($refreshed_token)) {
                    Utils::log('Token validation - Token refreshed successfully', 3);
                    
                    // Clear reauth flag and fresh token attempt timestamp if token refresh succeeded
                    self::clear_reauth_flag();
                    
                    return array(
                        'valid' => true,
                        'message' => sprintf(__('Token refreshed successfully. New token expires in %s hours.', 'seo-booster'), $hours_until_expiry),
                        'last_check' => $current_time,
                        'next_check' => $current_time + $check_interval,
                        'refreshed' => true,
                        'expires_in_hours' => $hours_until_expiry
                    );
                } else {
                    Utils::log('Token validation - Failed to refresh token: ' . $refreshed_token->get_error_message(), 2);
                }
            }
            
            Utils::log('Token validation - Token is valid', 3);
            
            // Clear reauth flag and fresh token attempt timestamp if token is valid
            self::clear_reauth_flag();
            
            return array(
                'valid' => true,
                'message' => sprintf(__('Token is valid. Expires in %s hours.', 'seo-booster'), $hours_until_expiry),
                'last_check' => $current_time,
                'next_check' => $current_time + $check_interval,
                'expires_in_hours' => $hours_until_expiry
            );
        } else {
            $error_message = __('Token validation failed', 'seo-booster');
            
            // Try to get more specific error information
            if (isset($response_data['error'])) {
                $error_message = $response_data['error'];
                if (isset($response_data['error_description'])) {
                    $error_message .= ': ' . $response_data['error_description'];
                }
            } elseif (isset($response_data['error_description'])) {
                $error_message = $response_data['error_description'];
            }
            
            Utils::log('Token validation failed - Google OAuth API error: ' . $error_message, 2);
            Utils::log('Token validation failed - Google OAuth API response code: ' . $response_code, 2);
            
            // Check if we should attempt to get a fresh token
            $last_fresh_attempt = get_option('seobooster_last_fresh_token_attempt', 0);
            $fresh_attempt_interval = 15 * MINUTE_IN_SECONDS; // 15 minutes between fresh token attempts
            
            if (($current_time - $last_fresh_attempt) >= $fresh_attempt_interval) {
                Utils::log('Token validation failed - Attempting to get fresh token from API server', 2);
                update_option('seobooster_last_fresh_token_attempt', $current_time);
                
                // Attempt to get a fresh token using get_access_token with force refresh
                $fresh_token = self::get_access_token(true);
                
                if (!is_wp_error($fresh_token)) {
                    Utils::log('Token validation - Fresh token obtained successfully', 2);
                    
                    // Update the static property to use the fresh token immediately
                    self::$access_token = $fresh_token;
                    
                    // Test the fresh token
                    $fresh_google_api_url = 'https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=' . urlencode($fresh_token);
                    $fresh_test_response = wp_remote_get($fresh_google_api_url, array(
                        'timeout' => 10,
                        'headers' => array(
                            'User-Agent' => 'SEOBooster/1.0'
                        )
                    ));
                    
                    if (!is_wp_error($fresh_test_response)) {
                        $fresh_response_code = wp_remote_retrieve_response_code($fresh_test_response);
                        if ($fresh_response_code === 200) {
                            Utils::log('Token validation - Fresh token is valid', 2);
                            self::clear_reauth_flag();
                            
                            return array(
                                'valid' => true,
                                'message' => __('Token refreshed successfully after validation failure', 'seo-booster'),
                                'last_check' => $current_time,
                                'next_check' => $current_time + $check_interval,
                                'refreshed' => true
                            );
                        }
                    }
                    
                    Utils::log('Token validation - Fresh token also failed validation', 2);
                } else {
                    Utils::log('Token validation - Failed to get fresh token: ' . $fresh_token->get_error_message(), 2);
                }
            } else {
                $minutes_until_next_attempt = round(($fresh_attempt_interval - ($current_time - $last_fresh_attempt)) / 60, 1);
                Utils::log('Token validation - Skipping fresh token attempt. Next attempt in ' . $minutes_until_next_attempt . ' minutes', 2);
            }
            
            // Set reauth flag if API returns error and fresh token attempt failed or was skipped
            update_option('seobooster_needs_reauth', '1');
            
            return array(
                'valid' => false,
                'message' => $error_message,
                'last_check' => $current_time,
                'next_check' => $current_time + $check_interval,
                'skipped' => false
            );
        }
    }

    /**
     * Manual token refresh via AJAX.
     *
     * @since   v1.0.2
     * @version v1.0.1  Tuesday, January 14th, 2025.
     * @access  public static
     * @return  void
     */
    public static function ajax_manual_token_refresh() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'seobooster_token_refresh')) {
            wp_die(__('Security check failed', 'seo-booster'));
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'seo-booster'));
        }
        
        $result = self::validate_token(true);
        
        wp_send_json($result);
    }





    /**
     * Automatically validate token and clear reauth flag if successful.
     * This is called during page load to improve user experience.
     *
     * @since   v1.0.2
     * @version v1.0.1  Tuesday, January 14th, 2025.
     * @access  public static
     * @return  void
     */
    public static function auto_validate_and_clear_reauth() {
        // Only run this if reauth flag is set
        if (!get_option('seobooster_needs_reauth')) {
            return;
        }
        
        // Check if we have basic authentication data
        $access_token = self::get_access_token();
        $google_email = get_option('seobooster_google_email');
        
        if (!$access_token || is_wp_error($access_token) || empty($google_email)) {
            return; // Don't have basic auth data, leave flag set
        }
        
        // Try to validate token (this will clear flag if successful)
        $result = self::validate_token(true);
        
        if ($result['valid']) {
            Utils::log('Auto validation successful - cleared reauth flag', 3);
            
            // Now that we have a valid token, check for missing data and fetch if needed
            self::check_and_fetch_missing_data();
        } else {
            Utils::log('Auto validation failed - keeping reauth flag: ' . $result['message'], 2);
        }
    }

    /**
     * Check for missing GSC data and fetch from the latest available date.
     * This ensures we don't miss any data when the token is valid.
     *
     * @since   v1.0.2
     * @version v1.0.1  Tuesday, January 14th, 2025.
     * @access  public static
     * @return  void
     */
    public static function check_and_fetch_missing_data() {
        global $wpdb;
        
        // Get the selected site
        $selected_site = get_option('seobooster_selected_site');
        if (empty($selected_site)) {
            Utils::log('No site selected, skipping data fetch check', 3);
            return;
        }
        
        // Get the latest date from the history table
        $latest_date = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(date) 
                FROM {$wpdb->prefix}sb2_query_keywords_history 
                WHERE %s = %s",
                '1', '1'
            )
        );
        
        if (!$latest_date) {
            Utils::log('No existing data found, skipping data fetch check', 3);
            return;
        }
        
        // Calculate how many days since the latest data
        $latest_timestamp = strtotime($latest_date);
        $current_timestamp = time();
        $days_since_latest = floor(($current_timestamp - $latest_timestamp) / (24 * 60 * 60));
        
        Utils::log('Latest data date: ' . $latest_date . ', days since: ' . $days_since_latest, 3);
        
        // If we have data from today or yesterday, we're up to date
        if ($days_since_latest <= 1) {
            Utils::log('Data is up to date (within 1 day), no fetch needed', 3);
            return;
        }
        
        // Calculate the number of days to fetch (from latest date to today, including the latest date)
        $days_to_fetch = $days_since_latest + 1; // +1 to include the latest date itself
        
        // Limit to a reasonable number of days to avoid overwhelming the API
        $max_days_to_fetch = 7; // Maximum 7 days at once
        if ($days_to_fetch > $max_days_to_fetch) {
            $days_to_fetch = $max_days_to_fetch;
            Utils::log('Limiting fetch to ' . $max_days_to_fetch . ' days to avoid API overload', 3);
        }
        
        Utils::log('Fetching missing data for ' . $days_to_fetch . ' days from ' . $selected_site, 1);
        
        try {
            // Fetch data from the latest date onwards
            self::fetch_and_store_query_keywords($selected_site, true, $days_to_fetch);
            Utils::log('Successfully fetched missing data for ' . $days_to_fetch . ' days', 1);
        } catch (\Exception $e) {
            Utils::log('Failed to fetch missing data: ' . $e->getMessage(), 2);
        }
    }

    /**
     * Inspect a single URL using Google Search Console URL Inspection API.
     *
     * @since 6.2.0
     * @param string $url The URL to inspect (fully qualified).
     * @param string $site_url The site URL as defined in Search Console.
     * @param string $language_code Optional language code for translated issue messages (default: 'en-US').
     * @return array|WP_Error Inspection result data or WP_Error on failure.
     */
    public static function inspect_url($url, $site_url, $language_code = 'en-US')
    {
        // Validate and sanitize inputs
        $url = esc_url_raw($url);
        $site_url = sanitize_text_field($site_url);
        $language_code = sanitize_text_field($language_code);

        if (empty($url) || empty($site_url)) {
            return new \WP_Error('invalid_params', __('URL and site URL are required.', 'seo-booster'));
        }

        // Get access token
        $access_token = self::get_access_token();
        if (!$access_token || is_wp_error($access_token)) {
            $error_message = is_wp_error($access_token) 
                ? $access_token->get_error_message() 
                : __('Access token is not set or invalid.', 'seo-booster');
            Utils::log('Access token error in inspect_url: ' . $error_message, 2);
            return new \WP_Error('auth_failed', $error_message);
        }

        // Prepare API site URL (handle domain properties)
        $api_site_url = $site_url;
        if (strpos($site_url, 'sc-domain:') === 0) {
            $domain_part = substr($site_url, strlen('sc-domain:'));
            $api_site_url = 'sc-domain:' . urlencode($domain_part);
        } else {
            $api_site_url = urlencode($site_url);
        }

        // Prepare request body
        $request_body = [
            'inspectionUrl' => $url,
            'siteUrl' => $site_url,
            'languageCode' => $language_code,
        ];

        // Make API request
        $response = wp_remote_post(
            'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($request_body),
                'method' => 'POST',
                'data_format' => 'body',
                'timeout' => 30,
            ]
        );

        // Handle errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            Utils::log('URL Inspection API request failed: ' . $error_message, 2);
            
            // Try token refresh once
            if (!self::$token_refreshed) {
                Utils::log('Attempting to refresh access token...', 3);
                $new_access_token = self::get_access_token(true);
                if ($new_access_token && !is_wp_error($new_access_token)) {
                    self::$token_refreshed = true;
                    // Retry with new token
                    $response = wp_remote_post(
                        'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect',
                        [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $new_access_token,
                                'Content-Type' => 'application/json',
                            ],
                            'body' => wp_json_encode($request_body),
                            'method' => 'POST',
                            'data_format' => 'body',
                            'timeout' => 30,
                        ]
                    );
                    if (!is_wp_error($response)) {
                        Utils::log('URL Inspection API request successful after token refresh', 3);
                    }
                }
            }
            
            if (is_wp_error($response)) {
                return $response;
            }
        }

        // Check HTTP response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $response_body = wp_remote_retrieve_body($response);
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : sprintf(__('API request failed with status code: %d', 'seo-booster'), $response_code);
            Utils::log('URL Inspection API error: ' . $error_message, 2);
            return new \WP_Error('api_error', $error_message);
        }

        // Parse response
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (!$data || !is_array($data)) {
            Utils::log('Invalid response from URL Inspection API', 2);
            return new \WP_Error('invalid_response', __('Invalid response from Google Search Console API.', 'seo-booster'));
        }

        return $data;
    }

    /**
     * Batch inspect multiple URLs with rate limiting.
     *
     * @since 6.2.0
     * @param array $urls Array of URLs to inspect.
     * @param string $site_url The site URL as defined in Search Console.
     * @param int $rate_limit_per_minute Maximum requests per minute (default: 100).
     * @param int $rate_limit_per_day Maximum requests per day (default: 1000).
     * @return array Results array with 'success' and 'failed' keys.
     */
    public static function batch_inspect_urls($urls, $site_url, $rate_limit_per_minute = 100, $rate_limit_per_day = 1000)
    {
        if (!current_user_can('manage_options')) {
            return [
                'success' => [],
                'failed' => [],
                'error' => __('You do not have permission to perform this action.', 'seo-booster'),
            ];
        }

        // Validate inputs
        if (empty($urls) || !is_array($urls)) {
            return [
                'success' => [],
                'failed' => [],
                'error' => __('Invalid URLs array.', 'seo-booster'),
            ];
        }

        $site_url = sanitize_text_field($site_url);
        if (empty($site_url)) {
            return [
                'success' => [],
                'failed' => [],
                'error' => __('Site URL is required.', 'seo-booster'),
            ];
        }

        $results = [
            'success' => [],
            'failed' => [],
        ];

        // Get daily quota tracking (use transient)
        $daily_quota_key = 'sb_gsc_daily_quota_' . date('Y-m-d');
        $daily_count = get_transient($daily_quota_key) ?: 0;

        // Get minute quota tracking (use transient with 60 second expiration)
        $minute_quota_key = 'sb_gsc_minute_quota_' . date('Y-m-d-H-i');
        $minute_count = get_transient($minute_quota_key) ?: 0;

        $processed = 0;
        $total_urls = count($urls);

        foreach ($urls as $url) {
            // Check daily quota
            if ($daily_count >= $rate_limit_per_day) {
                Utils::log(sprintf('Daily quota limit reached (%d/%d). Stopping batch inspection.', $daily_count, $rate_limit_per_day), 2);
                $results['error'] = sprintf(__('Daily quota limit reached (%d/%d). Please try again tomorrow.', 'seo-booster'), $daily_count, $rate_limit_per_day);
                break;
            }

            // Check minute quota - wait if needed
            if ($minute_count >= $rate_limit_per_minute) {
                Utils::log('Minute quota limit reached. Waiting 60 seconds...', 3);
                sleep(60);
                // Reset minute counter
                $minute_count = 0;
                delete_transient($minute_quota_key);
            }

            // Inspect URL
            $inspection_result = self::inspect_url($url, $site_url);

            if (is_wp_error($inspection_result)) {
                $results['failed'][] = [
                    'url' => $url,
                    'error' => $inspection_result->get_error_message(),
                ];
            } else {
                $results['success'][] = [
                    'url' => $url,
                    'data' => $inspection_result,
                ];
            }

            // Update quotas
            $daily_count++;
            $minute_count++;
            set_transient($daily_quota_key, $daily_count, DAY_IN_SECONDS);
            set_transient($minute_quota_key, $minute_count, 60);

            $processed++;

            // Small delay between requests to avoid hitting limits
            if ($processed < $total_urls) {
                usleep(600000); // 0.6 seconds between requests
            }
        }

        Utils::log(sprintf('Batch inspection completed: %d success, %d failed out of %d total', 
            count($results['success']), 
            count($results['failed']), 
            $total_urls
        ), 3);

        return $results;
    }

}
