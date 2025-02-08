<?php

namespace Cleverplugins\SEOBooster;

use voku\helper\HtmlDomParser;
use Cleverplugins\SEOBooster\Utils;
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
        global $wpdb;
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
                Utils::log( esc_html__( 'Access token error in sb_gsc_import_data: ', 'seo-booster' ) . esc_html( $error_message ), 5 );
                throw new \Exception($error_message);
            }
            $startRow = $step * 1000;
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
            foreach ( $data['rows'] as $row ) {
                if ( !isset( $row['keys'][0], $row['keys'][1], $row['keys'][2] ) ) {
                    Utils::log( 'Skipped incomplete data row: ' . wp_json_encode( $row ), 3 );
                    continue;
                }
                $query = sanitize_text_field( $row['keys'][0] );
                $url_parts = explode( '#', $row['keys'][1], 2 );
                $page = esc_url_raw( $url_parts[0] );
                $date = sanitize_text_field( $row['keys'][2] );
                $cache_key = 'sb2_keyword_id_' . md5( $query . $page );
                $existing_keyword_id = wp_cache_get( $cache_key );
                if ( false === $existing_keyword_id ) {
                    $wpdb->query( 'START TRANSACTION' );
                    $existing_keyword_id = $wpdb->get_var( $wpdb->prepare( "SELECT id \n\t\t\t\t\t\t\tFROM {$wpdb->prefix}sb2_query_keywords \n\t\t\t\t\t\t\tWHERE query = %s AND page = %s", $query, $page ) );
                    if ( $existing_keyword_id ) {
                        wp_cache_set(
                            $cache_key,
                            $existing_keyword_id,
                            '',
                            HOUR_IN_SECONDS
                        );
                    } else {
                        $wpdb->insert( "{$wpdb->prefix}sb2_query_keywords", [
                            'query'           => $query,
                            'page'            => $page,
                            'first_seen_date' => $date,
                            'latest_date'     => $date,
                        ] );
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
                }
                $existing_history_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sb2_query_keywords_history \n\t\t\t\t\t WHERE query_keywords_id = %d AND date = %s", $keyword_id, $date ) );
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
                    Utils::log( 'Failed to insert/update history entry: ' . wp_json_encode( $row ), 3 );
                } else {
                    $totalFetched++;
                    $lastKeyword = $query;
                    $lastDate = $date;
                }
            }
            $more_results = $totalFetched === $rowLimit;
            /* translators: 1: number of processed records, 2: current keyword being processed, 3: date of the keyword */
            Utils::log( sprintf(
                __( 'Processed %1$s records. Current keyword: "%2$s" (%3$s)', 'seo-booster' ),
                number_format_i18n( $totalFetched ),
                $lastKeyword,
                $lastDate
            ), 10 );
            $total_keywords = $wpdb->get_var( "SELECT COUNT(DISTINCT query) FROM {$wpdb->prefix}sb2_query_keywords" );
            $response = [
                'message'            => ( $more_results ? '<strong>' . __( 'There is more data to import. Do not leave this page.', 'seo-booster' ) . '</strong>' : __( 'Import complete!', 'seo-booster' ) ),
                'time'               => Utils::timerstop( 'sb_gsc_import_data' ),
                'more_results'       => $more_results,
                'last_keyword'       => $lastKeyword,
                'total_keywords'     => number_format_i18n( $total_keywords ),
                'last_date'          => $lastDate,
                'keywords_processed' => number_format_i18n( $totalFetched ),
                'total_entries'      => number_format_i18n( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sb2_query_keywords_history;" ) ),
            ];
            if ( !$more_results ) {
                Utils::log( __( 'Import complete!', 'seo-booster' ), 10 );
                if ( !wp_next_scheduled( 'sb_gsc_schedule_all_pages' ) ) {
                    wp_schedule_single_event( time(), 'sb_gsc_schedule_all_pages' );
                    Utils::log( 'Scheduled immediate keyword analysis for all pages' );
                }
                update_option( 'sb_gsc_last_refreshed', current_time( 'mysql' ) );
                email_status::send_email_update( 7, true );
            }
            wp_send_json_success( $response );
        } catch ( \Exception $e ) {
            Utils::log( __( 'Error fetching query keywords: ', 'seo-booster' ) . esc_html( $e->getMessage() ), 3 );
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
        // Check if we are in the admin area and on an edit screen
        // Proceed with enqueuing scripts
        $plugin_version = Seobooster2::get_plugin_version();
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
            SEOBOOSTER_PLUGINURL . 'css/min/sb-adminbar-min.css',
            [],
            filemtime(SEOBOOSTER_PLUGINPATH . 'css/min/sb-adminbar-min.css'),
        );

        
        wp_enqueue_script(
            'seobooster-adminbar',
            SEOBOOSTER_PLUGINURL . 'js/min/seobooster-adminbar-min.js',
            ['jquery', 'winbox', 'tabulator'],
            filemtime(SEOBOOSTER_PLUGINPATH . 'js/min/seobooster-adminbar-min.js'),
            true
        );

        wp_enqueue_script(
            'clipboardjs',
            SEOBOOSTER_PLUGINURL . 'js/min/clipboard.min.js',
            [],
            filemtime(SEOBOOSTER_PLUGINPATH . 'js/min/clipboard.min.js'),
            true
        );
        $current_page = (( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? "https" : "http" )) . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
        $current_page = remove_query_arg( ['seobooster_showdetails', 'seobooster_showlinks'], $current_page );
        wp_localize_script( 'seobooster-adminbar', 'seobooster_adminbar', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'post_url' => $current_page,
            'security' => wp_create_nonce( 'sb_gsc_nonce' ),
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
                'no_keywords_data_available' => __( 'No keywords data available.', 'seo-booster' ),
            ],
        ) );
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
    public static function get_focus_keywords( $post_id ) {
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
                $focus_keyword = get_post_meta( $post_id, '_rank_math_focus_keyword', true );
                if ( !empty( $focus_keyword ) ) {
                    $focus_keywords[] = sanitize_text_field( $focus_keyword );
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
        }
        return [
            'title'       => sanitize_text_field( $title ),
            'description' => sanitize_textarea_field( $description ),
        ];
    }

    /**
     * Fetch chart data via AJAX request
     *
     * @return void
     */
    public static function fetch_chart_data_ajax() {
        // Verify nonce for security
        check_ajax_referer( 'seobooster-nonce', 'nonce' );
        // Check user capabilities
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized user', 'seo-booster' ) );
        }
        global $wpdb;
        // Sanitize and validate date range
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
        $query = $wpdb->prepare( "\n\t\t SELECT \n\t\t\t history.date,\n\t\t\t SUM(history.clicks) AS total_clicks,\n\t\t\t SUM(history.impressions) AS total_impressions,\n\t\t\t (SUM(history.clicks) / SUM(history.impressions)) * 100 AS ctr,\n\t\t\t COUNT(DISTINCT keywords.query) AS unique_keywords\n\t\t FROM \n\t\t\t{$wpdb->prefix}sb2_query_keywords AS keywords\n\t\t INNER JOIN \n\t\t\t{$wpdb->prefix}sb2_query_keywords_history AS history\n\t\t ON \n\t\t\t keywords.id = history.query_keywords_id\n\t\t WHERE \n\t\t\t history.date BETWEEN %s AND %s\n\t\t GROUP BY \n\t\t\t history.date\n\t\t ORDER BY \n\t\t\t history.date DESC\n\t\t LIMIT 999;", $start_date, $end_date );
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
     * @version v1.0.0 Monday, August 26th, 2024.
     * @access	public static
     * @return	void
     */
    public static function fetch_gsc_data_cron() {
        $selected_site = get_option( 'seobooster_selected_site' );
        if ( !$selected_site ) {
            $selected_site = get_option( 'siteurl' );
        }
        Utils::log( 'Fetching GSC data via cron selected site: ' . $selected_site );
        if ( $selected_site ) {
            Utils::log( 'Fetching GSC data for ' . $selected_site );
            self::fetch_and_store_query_keywords( $selected_site, true );
        }
    }

    /**
     * Handle manual update request for Google Search Console data.
     *
     * This method processes a manual update request, verifies the nonce,
     * and triggers the data fetch process for the selected site.
     *
     * @since 1.0.0
     * @access public static
     * @return void
     */
    public static function handle_manual_update() {
        if ( !isset( $_POST['manual_update'] ) || !current_user_can( 'manage_options' ) ) {
            return;
        }
        check_admin_referer( 'manual_update_nonce', 'manual_update_nonce_field' );
        Utils::log( 'Handling manual update request' );
        $selected_site = get_option( 'seobooster_selected_site' );
        if ( $selected_site ) {
            $reimport_days = ( isset( $_POST['reimport_days'] ) ? absint( $_POST['reimport_days'] ) : 7 );
            $reimport_days = max( 1, min( $reimport_days, 92 ) );
            // Ensure value is between 1 and 92
            Utils::log( 'Manual update triggered for ' . esc_url( $selected_site ) );
            self::fetch_and_store_query_keywords( $selected_site, false, $reimport_days );
            wp_safe_redirect( add_query_arg( 'gsc_updated', '1', admin_url( 'admin.php?page=sb2_dashboard' ) ) );
            exit;
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
        ];
        // Calculate total size of all tables
        $total_size_all_tables = array_sum( array_column( $all_tables, 'Data_length' ) ) + array_sum( array_column( $all_tables, 'Index_length' ) );
        // Start table structure
        echo '<table class="wp-list-table widefat fixed striped table-view-list"><thead><tr><th>' . esc_html__( 'Table Name', 'seo-booster' ) . '</th><th>' . esc_html__( 'Number of Records', 'seo-booster' ) . '</th><th>' . esc_html__( 'Size (MB)', 'seo-booster' ) . '</th></tr></thead><tbody>';
        $total_size_our_tables = 0;
        $total_records_our_tables = 0;
        foreach ( $our_tables as $key => $table ) {
            $size_query = $wpdb->prepare( "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) \n\t\t\t\tFROM information_schema.TABLES \n\t\t\t\tWHERE table_schema = %s \n\t\t\t\tAND table_name = %s", $wpdb->dbname, $table );
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
     * Fetches the last 15 changes for a given keyword from the history table.
     *
     * Retrieves the history of changes for a specific keyword by first finding the keyword's ID
     * in the `wp_sb2_query_keywords` table, then fetching the last 15 entries from the
     * `wp_sb2_query_keywords_history` table related to that keyword ID.
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @param string $keyword The keyword to search for its history.
     * @return array An array of the last 15 changes for the specified keyword, or an empty array if not found.
     */
    public static function get_keyword_history( $keyword ) {
        global $wpdb;
        // Sanitize the input keyword
        $sanitized_keyword = sanitize_text_field( $keyword );
        // Prepare and execute the query to get the keyword ID.
        $keyword_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sb2_query_keywords WHERE query = %s", $sanitized_keyword ) );
        // If the keyword ID was not found, return an empty array.
        if ( !$keyword_id ) {
            return array();
        }
        // Prepare and execute the query to get the last 15 history records for the keyword ID.
        $history = $wpdb->get_results( $wpdb->prepare( "SELECT clicks, impressions, ctr, position, date \n\t\t\t FROM {$wpdb->prefix}sb2_query_keywords_history \n\t\t\t WHERE query_keywords_id = %d \n\t\t\t GROUP BY date \n\t\t\t ORDER BY date DESC \n\t\t\t LIMIT 15", $keyword_id ), ARRAY_A );
        // Sanitize the output data
        $sanitized_history = array_map( function ( $row ) {
            return array(
                'clicks'      => intval( $row['clicks'] ),
                'impressions' => intval( $row['impressions'] ),
                'ctr'         => floatval( $row['ctr'] ),
                'position'    => floatval( $row['position'] ),
                'date'        => sanitize_text_field( $row['date'] ),
            );
        }, $history );
        return $sanitized_history;
    }

    /**
     * Get keywords data for a specific page URL.
     *
     * @param string $page_url The URL of the page to fetch keywords for.
     * @return array An array of keyword data for the specified page.
     */
    public static function get_keywords_data_for_page( $page_url ) {
        global $wpdb;
        $query_keywords_table = $wpdb->prefix . 'sb2_query_keywords';
        $sanitized_page_url = esc_url_raw( $page_url );
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$query_keywords_table} WHERE page = %s ORDER BY first_seen_date DESC", $sanitized_page_url ), ARRAY_A );
        return array_map( 'wp_kses_post', $results );
    }

    /**
     * Fetch Google Search Console data for the selected site.
     *
     * @return void
     */
    public static function fetch_gsc_data() {
        $selected_site = get_option( 'seobooster_selected_site' );
        if ( $selected_site && wp_http_validate_url( $selected_site ) ) {
            self::fetch_and_store_query_keywords( $selected_site, true );
        } else {
            Utils::log( 'Invalid or missing selected site URL in fetch_gsc_data', 3 );
        }
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
                        Utils::log( sprintf( __( 'Failed to update keyword entry: %s', 'seo-booster' ), print_r( $entry, true ) ), 2 );
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
                    Utils::log( sprintf( __( 'Failed to insert new keyword entry: %s', 'seo-booster' ), print_r( $entry, true ) ), 2 );
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
                    Utils::log( sprintf( __( 'Failed to update history entry: %s', 'seo-booster' ), print_r( $entry, true ) ), 2 );
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
                    Utils::log( sprintf( __( 'Failed to insert new history entry: %s', 'seo-booster' ), print_r( $entry, true ) ), 2 );
                }
            }
        }
        // End the process and log it
        $end_time = microtime( true );
        $duration = $end_time - $start_time;
        // Translators: %s is the duration in seconds
        Utils::log( sprintf( __( 'Finished batch_add_or_update_keyword_entries in %s seconds', 'seo-booster' ), number_format( $duration, 4 ) ), 10 );
    }

    /**
     * Fetch and store query keywords for a given site URL
     *
     * @author  Unknown
     * @since   v0.0.1
     * @version v1.0.0  Monday, August 26th, 2024.
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
            Utils::log( esc_html__( 'User does not have permission to fetch query keywords', 'seo-booster' ), 5 );
            return;
        }
        try {
            $access_token = self::get_access_token();
            if ( !$access_token || is_wp_error( $access_token ) ) {
                $error_message = ( is_wp_error( $access_token ) ? sprintf( esc_html__( 'Access token error: %1$s', 'seo-booster' ), $access_token->get_error_message() ) : sprintf( esc_html__( 'Access token is not set or invalid: %1$s', 'seo-booster' ), $access_token ) );
                Utils::log( $error_message, 5 );
                throw new \Exception($error_message);
            }
            global $wpdb;
            $start_row = 0;
            $row_limit = 1000;
            $total_fetched = 0;
            do {
                $api_site_url = $site_url;
                if ( strpos( $site_url, 'sc-domain:' ) === 0 ) {
                    // For domain properties, we need to keep the sc-domain: prefix and encode the domain part
                    $domain_part = substr( $site_url, strlen( 'sc-domain:' ) );
                    $api_site_url = 'sc-domain:' . urlencode( $domain_part );
                } else {
                    // For regular URLs, encode the entire URL
                    $api_site_url = urlencode( $site_url );
                }
                $response = wp_remote_post( "https://www.googleapis.com/webmasters/v3/sites/" . $api_site_url . "/searchAnalytics/query", [
                    'headers'     => [
                        'Authorization' => 'Bearer ' . $access_token,
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
                ] );
                if ( is_wp_error( $response ) ) {
                    /* translators: %1$s: error message from access token */
                    Utils::log( sprintf( esc_html__( 'Error fetching query keywords: %1$s', 'seo-booster' ), $response->get_error_message() ), 5 );
                    throw new \Exception($response->get_error_message());
                }
                $data = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( $data && is_array( $data ) && isset( $data['rows'] ) && is_array( $data['rows'] ) ) {
                    foreach ( $data['rows'] as $row ) {
                        if ( !isset( $row['keys'][0], $row['keys'][1], $row['keys'][2] ) ) {
                            /* translators: %1$s: row data */
                            Utils::log( sprintf( esc_html__( 'Skipped incomplete data row: %1$s', 'seo-booster' ), wp_json_encode( $row ) ), 3 );
                            continue;
                        }
                        $query = sanitize_text_field( $row['keys'][0] );
                        $url_parts = explode( '#', $row['keys'][1], 2 );
                        $page = esc_url_raw( $url_parts[0] );
                        $date = sanitize_text_field( $row['keys'][2] );
                        $cache_key = 'sb2_keyword_id_' . md5( $query . $page );
                        $existing_keyword_id = wp_cache_get( $cache_key );
                        if ( false === $existing_keyword_id ) {
                            $wpdb->query( 'START TRANSACTION' );
                            $existing_keyword_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sb2_query_keywords WHERE query = %s AND page = %s", $query, $page ) );
                            if ( $existing_keyword_id ) {
                                wp_cache_set(
                                    $cache_key,
                                    $existing_keyword_id,
                                    '',
                                    HOUR_IN_SECONDS
                                );
                            } else {
                                $wpdb->insert( "{$wpdb->prefix}sb2_query_keywords", [
                                    'query'           => $query,
                                    'page'            => $page,
                                    'first_seen_date' => $date,
                                    'latest_date'     => $date,
                                ], [
                                    '%s',
                                    '%s',
                                    '%s',
                                    '%s'
                                ] );
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
                            $wpdb->update(
                                "{$wpdb->prefix}sb2_query_keywords",
                                [
                                    'latest_date' => $date,
                                ],
                                [
                                    'id' => $existing_keyword_id,
                                ],
                                ['%s'],
                                ['%d']
                            );
                            $keyword_id = $existing_keyword_id;
                        }
                        $history_cache_key = 'sb2_history_id_' . md5( $keyword_id . $date );
                        $existing_history_id = wp_cache_get( $history_cache_key );
                        if ( false === $existing_history_id ) {
                            $existing_history_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sb2_query_keywords_history \n\t\t\t\t\t\t\t\t WHERE query_keywords_id = %d AND date = %s", $keyword_id, $date ) );
                            if ( $existing_history_id ) {
                                wp_cache_set(
                                    $history_cache_key,
                                    $existing_history_id,
                                    '',
                                    HOUR_IN_SECONDS
                                );
                            } else {
                                $wpdb->insert( "{$wpdb->prefix}sb2_query_keywords_history", [
                                    'query_keywords_id' => $keyword_id,
                                    'clicks'            => absint( $row['clicks'] ),
                                    'impressions'       => absint( $row['impressions'] ),
                                    'ctr'               => floatval( $row['ctr'] ),
                                    'position'          => floatval( $row['position'] ),
                                    'date'              => $date,
                                ], [
                                    '%d',
                                    '%d',
                                    '%d',
                                    '%f',
                                    '%f',
                                    '%s'
                                ] );
                                wp_cache_set(
                                    $history_cache_key,
                                    $wpdb->insert_id,
                                    '',
                                    HOUR_IN_SECONDS
                                );
                            }
                        }
                        $total_fetched++;
                    }
                    if ( count( $data['rows'] ) === $row_limit ) {
                        if ( $start_row === 0 ) {
                            as_schedule_single_action( time(), 'sb_gsc_schedule_all_pages' );
                            Utils::log( esc_html__( 'Started keyword analysis for first batch of keywords', 'seo-booster' ), 10 );
                        }
                        $start_row += $row_limit;
                        Utils::log( sprintf( 
                            /* translators: %s: formatted row number */
                            esc_html__( 'More than 1000 results found, fetching more keywords starting from row %s', 'seo-booster' ),
                            number_format_i18n( $start_row )
                         ), 10 );
                    } else {
                        break;
                    }
                } else {
                    break;
                }
            } while ( true );
            /* translators: 1: formatted number of records, 2: site URL */
            Utils::log( sprintf( esc_html__( 'Processed %1$s records from %2$s', 'seo-booster' ), number_format_i18n( $total_fetched ), esc_url( $site_url ) ), 10 );
            as_schedule_single_action( time(), 'sb_gsc_schedule_all_pages' );
            Utils::log( esc_html__( 'Scheduled keyword analysis for all pages', 'seo-booster' ), 10 );
        } catch ( \Exception $e ) {
            /* translators: %s: error message from access token */
            Utils::log( sprintf( esc_html__( 'Error fetching query keywords: %s', 'seo-booster' ), esc_html( $e->getMessage() ) ), 5 );
            printf( '<div class="seobooster-notice notice notice-error"><p>%s</p></div>', esc_html( $e->getMessage() ) );
        }
        // Clear all report caches when new data is imported
        Reports::clear_report_caches();
    }

    /**
     * Display authentication status and handle site selection.
     *
     * @since   v0.0.1
     * @version v1.0.0  Sunday, September 1st, 2024.
     * @return  void
     */
    public static function display_auth_status() {
        if ( !seobooster_fs()->is_registered() ) {
            Utils::log( esc_html__( 'Authentication status cannot be displayed. You need to register a SEO Booster account to use this feature.', 'seo-booster' ), 5 );
            echo '<p>' . esc_html__( 'Authentication status cannot be displayed. You need to register a SEO Booster account to use this feature.', 'seo-booster' ) . '</p>';
            return;
        }
        $selected_site = get_option( 'seobooster_selected_site' );
        $access_token = get_option( 'seobooster_access_token' );
        // $sites = get_option('seobooster_gsc_sites');
        $sites = [];
        if ( $access_token ) {
            if ( is_wp_error( $sites ) || empty( $sites ) ) {
                $sites = self::fetch_sites();
                if ( is_wp_error( $sites ) || empty( $sites ) ) {
                    Utils::log( esc_html__( 'Failed to fetch sites or no sites available.', 'seo-booster' ), 5 );
                    $sites = [];
                }
            }
        } else {
            Utils::log( esc_html__( 'Cannot fetch sites without access token. Please authenticate.', 'seo-booster' ), 5 );
        }
        // Retrieve license and install details
        $install_id = seobooster_fs()->get_site()->id;
        $site_private_key = seobooster_fs()->get_site()->secret_key;
        $nonce = date( 'Y-m-d' );
        $return_to = admin_url( 'admin.php?page=sb2_dashboard&auth=1' );
        $pk_hash = hash( 'sha512', $site_private_key . '|' . $nonce );
        $authentication_string = base64_encode( $pk_hash . '|' . $nonce );
        ?>
		<div class="sb2authenticate">
			<?php 
        if ( $access_token && $selected_site ) {
            ?>
				<p><?php 
            esc_html_e( 'You have connected the following site:', 'seo-booster' );
            ?></p>
				<p><strong><?php 
            echo esc_html( $selected_site );
            ?></strong></p>
			<?php 
        }
        $google_email = get_option( 'seobooster_google_email' );
        ?>

			<?php 
        if ( $access_token && !$selected_site && $google_email && !empty( $sites ) ) {
            ?>
				<h2><?php 
            esc_html_e( 'Select Google Search Console Site', 'seo-booster' );
            ?></h2>
				<p><?php 
            printf( 
                /* translators: %s: Google email address */
                esc_html__( 'Great, you have now connected your Google account (%s).', 'seo-booster' ),
                '<strong>' . esc_html( $google_email ) . '</strong>'
             );
            ?></p>

				<p><?php 
            esc_html_e( 'Please select a site to continue.', 'seo-booster' );
            ?></p>
				<form method="post">
					<div id="choosecont">
						<div class="col">
							<?php 
            $site_url = esc_url( site_url( '/' ) );
            ?>
							<select name="seobooster_selected_site">
								<?php 
            // Sort sites alphabetically, ignoring 'sc-domain:' prefix
            usort( $sites, function ( $a, $b ) {
                $a_clean = str_replace( 'sc-domain:', '', $a );
                $b_clean = str_replace( 'sc-domain:', '', $b );
                return strcasecmp( $a_clean, $b_clean );
            } );
            foreach ( $sites as $site ) {
                if ( !empty( $site ) ) {
                    // Keep full value for the option value
                    $value = $site;
                    // Remove 'sc-domain:' from display label
                    $label = str_replace( 'sc-domain:', '', $site );
                    // Add domain verified text if it's a domain property
                    if ( strpos( $site, 'sc-domain:' ) === 0 ) {
                        $label .= ' ' . esc_html__( '(domain verified)', 'seo-booster' );
                    }
                    ?>
										<option value="<?php 
                    echo esc_attr( $value );
                    ?>" <?php 
                    selected( $value, $site_url );
                    ?>><?php 
                    echo esc_html( $label );
                    ?></option>
								<?php 
                }
            }
            ?>
							</select>
						</div>

						<div class="col">
							<?php 
            wp_nonce_field( 'seobooster_save_selected_site', 'seobooster_selected_site_nonce' );
            ?>
							<input type="hidden" name="seobooster_selected_days" value="90">
							<input type="submit" name="submit" value="<?php 
            esc_attr_e( 'Get keyword data', 'seo-booster' );
            ?>" class="button button-primary" id="seobooster2selectsite">
						</div>
					</div>

				</form>

				<div id="seobooster-api-error"></div>
				<div id="seobooster-api-status"><span class="spinner is-active"></span></div>
				<?php 
            if ( $selected_site ) {
                ?>
					<p><small><?php 
                esc_html_e( 'Made a mistake? You can reauthenticate with another account here:', 'seo-booster' );
                ?> <a href="<?php 
                echo esc_url( add_query_arg( array(
                    'install_id' => $install_id,
                    'auth_token' => $authentication_string,
                    'return_to'  => $return_to,
                ), 'https://seoboosterauth.com/auth' ) );
                ?>" target="_blank" class=""><?php 
                esc_html_e( 'Reauthenticate with Google', 'seo-booster' );
                ?></a></small></p>
			<?php 
            }
        }
        if ( !$sites && $access_token ) {
            Utils::log( __( 'No sites found but access token is set.', 'seo-booster' ), 5 );
            echo '<p>' . esc_html__( 'No sites found but access token is set.', 'seo-booster' ) . '</p>';
            echo '<p>' . esc_html__( 'Please try to reauthenticate with Google.', 'seo-booster' ) . '</p>';
            printf( 
                // translators: %1$s: URL to reauthenticate with Google, %2$s: text for reauthenticating with Google
                '<p><a href="%1$s" target="_blank" class="">%2$s</a></p>',
                esc_url( add_query_arg( array(
                    'install_id' => $install_id,
                    'auth_token' => $authentication_string,
                    'return_to'  => $return_to,
                ), 'https://seoboosterauth.com/auth' ) ),
                esc_html__( 'Reauthenticate with Google', 'seo-booster' )
             );
        }
        ?>

			<?php 
        if ( !$access_token ) {
            ?>
				<h3><?php 
            if ( !empty( $selected_site ) ) {
                esc_html_e( 'Reauthenticate with Google', 'seo-booster' );
            } else {
                esc_html_e( 'Authenticate with Google', 'seo-booster' );
            }
            ?></h3>
				<?php 
            $google_email = get_option( 'seobooster_google_email' );
            if ( $google_email ) {
                echo ' (' . esc_html( $google_email ) . ')';
            }
            ?>

				<p><a href="<?php 
            echo esc_url( add_query_arg( array(
                'install_id' => $install_id,
                'auth_token' => $authentication_string,
                'return_to'  => $return_to,
            ), 'https://seoboosterauth.com/auth' ) );
            ?>" class="button button-primary button-hero"><?php 
            esc_html_e( 'Start Authentication', 'seo-booster' );
            ?></a></p>
				<p><?php 
            esc_html_e( 'You will be taken to Google to authorize your account.', 'seo-booster' );
            ?></p>
				<p><?php 
            esc_html_e( 'Connect to the API to gather data from GSC via seoboosterauth.com.', 'seo-booster' );
            ?></p>
			<?php 
        }
        if ( $access_token && $selected_site ) {
            ?>
				<p><small><a href="<?php 
            echo esc_url( add_query_arg( array(
                'install_id' => $install_id,
                'auth_token' => $authentication_string,
                'return_to'  => $return_to,
            ), 'https://seoboosterauth.com/auth' ) );
            ?>" target="_blank" class=""><?php 
            esc_html_e( 'Reauthenticate with Google', 'seo-booster' );
            ?></a></small></p>
			<?php 
        }
        ?>
		</div>



<?php 
    }

    /**
     * Get post ID by URL.
     *
     * @since   v0.0.1
     * @version v1.0.0  Friday, June 28th, 2024.
     * @param   string $url The URL to get the post ID for.
     * @return  int|false Post ID on success, false on failure.
     */
    public static function get_post_id_by_url( $url ) {
        $parsed_url = wp_parse_url( $url );
        $path = ( isset( $parsed_url['path'] ) ? $parsed_url['path'] : '' );
        return url_to_postid( $path );
    }

    /**
     * Fetch and search keywords.
     *
     * @since   v0.0.1
     * @version v1.0.1  Sunday, September 1st, 2024.
     * @access  public static
     * @return  array|string Array of keywords not found in content, or error message.
     */
    public static function fetch_and_search_keywords() {
        global $wpdb;
        Utils::timerstart( 'fetch_and_search_keywords' );
        // Fetch all keywords without time or click/impression restrictions
        $keywords_query = "\n\t\t\tSELECT q.query, q.page, h.clicks, h.impressions, h.ctr, h.position, h.date\n\t\t\tFROM {$wpdb->prefix}sb2_query_keywords AS q\n\t\t\tINNER JOIN {$wpdb->prefix}sb2_query_keywords_history AS h ON q.id = h.query_keywords_id\n\t\t\tGROUP BY page, query\n\t\t\tORDER BY h.date DESC, h.impressions DESC\n\t\t";
        $keywords_results = $wpdb->get_results( $keywords_query, ARRAY_A );
        if ( empty( $keywords_results ) ) {
            return __( 'No keywords found in the database.', 'seo-booster' );
        }
        $checked_keywords = [];
        $not_found_keywords_by_url = [];
        foreach ( $keywords_results as $keyword_data ) {
            $keyword = sanitize_text_field( $keyword_data['query'] );
            $page_url = esc_url_raw( $keyword_data['page'] );
            // Group by page and query to ensure each keyword is checked for each page
            $page_query_key = md5( $page_url . $keyword );
            // Skip if the keyword for this page has already been checked
            if ( isset( $checked_keywords[$page_query_key] ) ) {
                continue;
            }
            $checked_keywords[$page_query_key] = true;
            $slug = sanitize_title( basename( wp_parse_url( $page_url, PHP_URL_PATH ) ) );
            // Find the post ID based on the slug
            $post_id = $wpdb->get_var( $wpdb->prepare( "\n\t\t\t\tSELECT ID FROM {$wpdb->posts}\n\t\t\t\tWHERE post_name = %s AND post_status = 'publish'\n\t\t\t", $slug ) );
            if ( $post_id ) {
                $post_id = ( isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : url_to_postid( $page_url ) );
                // Get the full content of the post - store automatically in cache
                $cache_response = CacheManager::fetch_and_cache_url_content( $page_url, [
                    'post_id' => $post_id,
                ] );
                $post_content = '';
                if ( $cache_response && isset( $cache_response['content'] ) ) {
                    $post_content = $cache_response['content'];
                }
                if ( stripos( $post_content, $keyword ) === false && stripos( get_the_title( $post_id ), $keyword ) === false ) {
                    if ( !isset( $not_found_keywords_by_url[$page_url] ) ) {
                        $not_found_keywords_by_url[$page_url] = [
                            'url'      => $page_url,
                            'keywords' => [],
                        ];
                    }
                    $not_found_keywords_by_url[$page_url]['keywords'][] = [
                        'keyword'     => $keyword,
                        'clicks'      => intval( $keyword_data['clicks'] ),
                        'impressions' => intval( $keyword_data['impressions'] ),
                        'ctr'         => floatval( $keyword_data['ctr'] ),
                        'position'    => floatval( $keyword_data['position'] ),
                        'date'        => sanitize_text_field( $keyword_data['date'] ),
                        'post_id'     => $post_id,
                    ];
                } else {
                    Utils::log( sprintf( 
                        /* translators: 1: keyword, 2: post permalink */
                        __( 'Keyword %1$s found in %2$s', 'seo-booster' ),
                        $keyword,
                        get_permalink( $post_id )
                     ) );
                }
            } else {
                if ( !isset( $not_found_keywords_by_url[$page_url] ) ) {
                    $not_found_keywords_by_url[$page_url] = [
                        'url'      => $page_url,
                        'keywords' => [],
                    ];
                }
                $not_found_keywords_by_url[$page_url]['keywords'][] = [
                    'keyword'     => $keyword,
                    'clicks'      => intval( $keyword_data['clicks'] ),
                    'impressions' => intval( $keyword_data['impressions'] ),
                    'ctr'         => floatval( $keyword_data['ctr'] ),
                    'position'    => floatval( $keyword_data['position'] ),
                    'date'        => sanitize_text_field( $keyword_data['date'] ),
                ];
            }
        }
        $fetch_and_search_keywords = Utils::timerstop( 'fetch_and_search_keywords', 2 );
        Utils::log( sprintf( 
            /* translators: %s: time in seconds */
            __( 'fetch_and_search_keywords took %s seconds', 'seo-booster' ),
            $fetch_and_search_keywords
         ), 10 );
        return $not_found_keywords_by_url;
    }

    private static function maybe_decrypt_token( $token, $install_id ) {
        // Return as-is if not encrypted
        if ( strpos( $token, 'EN_' ) !== 0 ) {
            return $token;
        }
        try {
            // Remove 'EN_' prefix
            $encrypted = substr( $token, 3 );
            // Create key from install_id (matching JS side)
            $key = str_pad( $install_id, 32, $install_id );
            $iv = str_repeat( "\x00", 16 );
            // Fixed IV matching JS side
            // Decrypt
            $decrypted = openssl_decrypt(
                $encrypted,
                'aes-256-cbc',
                $key,
                0,
                // Remove OPENSSL_RAW_DATA flag
                $iv
            );
            return ( $decrypted !== false ? $decrypted : $token );
        } catch ( \Exception $e ) {
            return $token;
        }
    }

    /**
     * Get the access token for Google API authentication.
     *
     * @since   v0.0.1
     * @version v1.0.1  Tuesday, August 20th, 2024.
     * @access  public static
     * @return  string|WP_Error Access token if successful, WP_Error on failure.
     */
    public static function get_access_token( $force_refresh = false ) {
        if ( $force_refresh ) {
            self::$access_token = null;
            // Clear cached token
        }
        // Check if the user has registered
        if ( !seobooster_fs()->is_registered() ) {
            Utils::log( 'get_access_token(): User not registered', 10 );
            return false;
        }
        // Initialize plugin settings and variables
        self::init();
        // Get necessary IDs and keys
        $install_id = seobooster_fs()->get_site()->id;
        $site_private_key = seobooster_fs()->get_site()->public_key;
        $nonce = current_time( 'Y-m-d', true );
        $pk_hash = hash( 'sha512', $site_private_key . '|' . $nonce );
        $authentication_string = base64_encode( $pk_hash . '|' . $nonce );
        // Check if we have a valid access token stored in self::$access_token and return that if different than null
        if ( self::$access_token !== null ) {
            return self::maybe_decrypt_token( self::$access_token, $install_id );
        }
        // Check for required data
        if ( !self::$google_email ) {
            return new \WP_Error('missing_data', __( 'Missing Google email', 'seo-booster' ));
        }
        // Prepare the request to get the access token
        $response = wp_remote_post( 'https://seoboosterauth.com/get-access-token', array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . sanitize_text_field( $authentication_string ),
            ),
            'body'    => wp_json_encode( array(
                'google_email' => sanitize_email( self::$google_email ),
                'install_id'   => absint( $install_id ),
                'auth_token'   => sanitize_text_field( $authentication_string ),
            ) ),
            'timeout' => 30,
        ) );
        // Handle errors in the response
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        // Check if the access token is returned and save it
        if ( isset( $data['access_token_enc'] ) ) {
            $access_token = sanitize_text_field( $data['access_token_enc'] );
            $access_token_encrypted = 'EN_' . $access_token;
            // Save the encrypted token with a prefix
            set_transient( 'seobooster_access_token', $access_token_encrypted, 10 * MINUTE_IN_SECONDS );
            update_option( 'seobooster_access_token', $access_token_encrypted, false );
            self::$access_token = $access_token_encrypted;
            if ( !isset( $data['access_token_enc'] ) ) {
                Utils::log( 'Access Token data (encrypted): ' . print_r( $data, true ), 2 );
            }
            return self::maybe_decrypt_token( $access_token, $install_id );
        }
        // Log the access token output
        if ( isset( $data['access_token_enc'] ) ) {
            // Utils::log('Access Token Response: ' . print_r($data, true), 1);
        } else {
            Utils::log( 'Access Token Error: ' . print_r( $data, true ), 2 );
            // Check for invalid_grant error
            if ( isset( $data['details'] ) && $data['details'] === 'invalid_grant' ) {
                Utils::log( esc_html__( 'Invalid grant detected - resetting authentication', 'seo-booster' ), 2 );
                self::reset_authentication();
                return new \WP_Error('invalid_grant', esc_html__( 'Authentication expired. Please re-authenticate with Google.', 'seo-booster' ));
            }
        }
        return new \WP_Error('token_error', esc_html__( 'Error retrieving access token', 'seo-booster' ));
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
        $install_id = seobooster_fs()->get_site()->id;
        $my_access_token = self::get_access_token();
        if ( is_wp_error( $my_access_token ) ) {
            Utils::log( esc_html__( 'WP_Error encountered while getting the access token in fetch_sites()', 'seo-booster' ), 5 );
            return $my_access_token;
        }
        
        $access_token = self::maybe_decrypt_token( $my_access_token, $install_id );
        if ( is_wp_error( $access_token ) ) {
            Utils::log( esc_html__( 'Problem getting the access token in fetch_sites()', 'seo-booster' ), 5 );
            return $access_token;
        }
        Utils::log( esc_html__( 'Fetching sites', 'seo-booster' ), 10 );
        $response = wp_remote_get( 'https://www.googleapis.com/webmasters/v3/sites', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
            'timeout' => 30,
        ) );
        if ( is_wp_error( $response ) ) {
            // translators: 1: Error message
            Utils::log( sprintf( esc_html__( 'WP_Error in fetch_sites(): %s', 'seo-booster' ), $response->get_error_message() ), 5 );
            return $response;
        }
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            // translators: %1$s: error message from json_last_error_msg()
            Utils::log( sprintf( esc_html__( 'JSON decode error in fetch_sites(): %s', 'seo-booster' ), json_last_error_msg() ), 5 );
            return new \WP_Error('json_decode_error', esc_html__( 'Error decoding JSON response', 'seo-booster' ));
        }
        $sitelist = array();
        if ( isset( $data['siteEntry'] ) && is_array( $data['siteEntry'] ) ) {
            $sitelist = array_map( function ( $site ) {
                $siteUrl = $site['siteUrl'];
                // If it's a domain property (starts with sc-domain:), don't use esc_url_raw
                if ( strpos( $siteUrl, 'sc-domain:' ) === 0 ) {
                    return sanitize_text_field( $siteUrl );
                }
                // Otherwise it's a URL, so use esc_url_raw
                return esc_url_raw( $siteUrl );
            }, $data['siteEntry'] );
        }
        $sitelist_count = count( $sitelist );
        // translators: %1$d: number of entries in sitelist, %2$s: last three sites in sitelist
        Utils::log( sprintf( esc_html__( 'Entries in sitelist fetched: %1$d. Last three sites: %2$s', 'seo-booster' ), $sitelist_count, implode( ', ', array_slice( $sitelist, -3 ) ) ), 10 );
        update_option( 'seobooster_gsc_sites', $sitelist, false );
        return $sitelist;
    }

    /**
     * Sanitize user input.
     *
     * @since   v0.0.1
     * @version v1.0.0  Tuesday, August 20th, 2024.
     * @access  public static
     * @param   string $input The input to sanitize.
     * @return  string The sanitized input.
     */
    public static function sanitize_user_input( $input ) {
        return sanitize_text_field( $input );
    }

    /**
     * Sanitize email input.
     *
     * @since   v0.0.1
     * @version v1.0.0  Tuesday, August 20th, 2024.
     * @access  public static
     * @param   string $input The email to sanitize.
     * @return  string The sanitized email.
     */
    public static function sanitize_email_input( $input ) {
        return sanitize_email( $input );
    }

    /**
     * Redirect to OAuth page.
     *
     * @since   v0.0.1
     * @version v1.0.0  Tuesday, August 20th, 2024.
     * @access  public static
     * @return  void
     */
    public static function redirect_to_oauth_page() {
        wp_safe_redirect( admin_url( 'admin.php?page=seo_booster_oauth2_page' ) );
        exit;
    }

    /**
     * Get Google Search Console sites.
     *
     * @since   v0.0.1
     * @version v1.0.1  Tuesday, September 3rd, 2024.
     * @access  public static
     * @return  array   List of GSC sites or empty array on failure.
     */
    public static function get_gsc_sites() {
        try {
            // Check if the sites are already stored in the options
            $seobooster_gsc_sites = get_option( 'seobooster_gsc_sites' );
            if ( $seobooster_gsc_sites ) {
                $sites = json_decode( $seobooster_gsc_sites, true );
                if ( is_array( $sites ) ) {
                    return $sites;
                    // Return the list of sites
                }
            }
            $token_data = get_option( 'seobooster_gsc_access_token' );
            if ( !$token_data ) {
                Utils::log( esc_html__( 'get_gsc_sites(): Access token does not exist.', 'seo-booster' ), 5 );
                throw new \Exception(esc_html__( 'Access token does not exist.', 'seo-booster' ));
            }
            $access_token = self::get_access_token();
            if ( !$access_token ) {
                Utils::log( esc_html__( 'get_gsc_sites(): Access token is not set.', 'seo-booster' ), 5 );
                throw new \Exception(esc_html__( 'Access token is not set.', 'seo-booster' ));
            }
            $response = wp_remote_get( 'https://www.googleapis.com/webmasters/v3/sites', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                ],
            ] );
            if ( is_wp_error( $response ) ) {
                throw new \Exception($response->get_error_message());
            }
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            if ( !isset( $data['siteEntry'] ) || !is_array( $data['siteEntry'] ) ) {
                throw new \Exception(esc_html__( 'Invalid response from Google Search Console API.', 'seo-booster' ));
            }
            $sites = $data['siteEntry'];
            update_option( 'seobooster_gsc_sites', wp_json_encode( $sites ), 'no' );
            return $sites;
        } catch ( \Exception $e ) {
            // translators: %s: error message from get_gsc_sites()
            Utils::log( sprintf( esc_html__( 'Error in get_gsc_sites(): %s', 'seo-booster' ), $e->getMessage() ), 5 );
            return [];
        }
    }

    /**
     * Display refresh link for GSC data.
     *
     * @since   v0.0.1
     * @version v1.0.1  Tuesday, September 3rd, 2024.
     * @access  public static
     * @return  void
     */
    public static function display_refresh_link() {
        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }
        $current_url = esc_url( admin_url( basename( $_SERVER['REQUEST_URI'] ) ) );
        $url = wp_nonce_url( add_query_arg( 'action', 'refresh_gsc_data', $current_url ), 'refresh_gsc_data_nonce' );
        echo '<p><a href="' . esc_url( $url ) . '" class="button button-secondary">' . esc_html__( 'Click to refresh', 'seo-booster' ) . '</a></p>';
    }

    /**
     * Reset authentication settings.
     *
     * @since   v0.0.1
     * @version v1.0.1  Tuesday, September 3rd, 2024.
     * @access  public static
     * @return  void
     */
    public static function reset_authentication() {
        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }
        delete_option( 'seobooster_access_token' );
        delete_option( 'seobooster_gsc_refresh_token' );
        // delete_option('seobooster_selected_site'); Lets not delete this, it could be set and the authentication needs to reset, not the selected site
        delete_option( 'seobooster_gsc_access_token' );
        Utils::log( esc_html__( 'Authentication settings have been reset.', 'seo-booster' ), 10 );
    }

    /**
     * Format keyword history tooltip.
     *
     * @since   0.0.1
     * @version 1.0.2  Tuesday, September 3rd, 2024.
     * @access  public
     * @param   array $keyword_history Array of keyword history entries.
     * @return  string Formatted tooltip content.
     */
    public static function format_keyword_history_tooltip( $keyword_history ) {
        if ( !is_array( $keyword_history ) || empty( $keyword_history ) ) {
            return '';
        }
        $output = '';
        $max_days = 14;
        foreach ( array_slice( $keyword_history, 0, $max_days ) as $entry ) {
            if ( !isset( $entry['date'], $entry['position'] ) ) {
                continue;
            }
            $date = sanitize_text_field( $entry['date'] );
            $position = floatval( $entry['position'] );
            $output .= sprintf( 
                /* translators: 1: date, 2: average position */
                esc_html__( '%1$s Avg Pos: %2$s', 'seo-booster' ) . "\n",
                esc_html( $date ),
                esc_html( number_format_i18n( $position, 1 ) )
             );
        }
        return wp_kses_post( $output );
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
            Utils::log( esc_html__( 'Resetting authentication settings.', 'seo-booster' ) );
            self::reset_authentication();
            wp_safe_redirect( add_query_arg( 'reset', '1', admin_url( 'admin.php?page=sb2_settings' ) ) );
            exit;
        }
    }

}
