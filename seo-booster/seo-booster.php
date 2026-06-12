<?php

/**
 * Plugin Name: SEO Booster
 * Version: 7.2
 * Plugin URI: https://seoboosterpro.com/
 * Description: SEO Booster integrates with Google Search Console data - bringing the data to life on your website like never before. Optimize keywords and content, track your rankings, and get actionable insights to improve your SEO.
 * Author: seoboosterpro.com
 * Author URI: https://seoboosterpro.com/
 * Text Domain: seo-booster
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 *
 * This plugin uses the following 3rd party MIT licensed projects - Thank you for making other developer lives easier :-)
 *
 * Jose Solorzano (https://sourceforge.net/projects/php-html/) for the Simple HTML DOM parser.
 *
 * The email template (heavily modified by us) is brought to you by EmailOctopus https://emailoctopus.com/ - email marketing for less, via Amazon SES. MIT License
 *
 * Copyright 2008-2025 cleverplugins.com and seoboosterpro.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
namespace Cleverplugins\SEOBooster;

use Cleverplugins\SEOBooster\Utils;
use Cleverplugins\SEOBooster\SB_GSC_Metaboxes;
use Cleverplugins\SEOBooster\Google_API;
use Cleverplugins\SEOBooster\SB_Autolink_Ajax;
// don't load directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
require_once __DIR__ . '/vendor/autoload.php';
// Function to load ActionScheduler only when needed
if ( !class_exists( 'ActionScheduler_Versions' ) ) {
    require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
}
define( 'SEOBOOSTER_PLUGINPATH', plugin_dir_path( __FILE__ ) );
define( 'SEOBOOSTER_PLUGINURL', plugin_dir_url( __FILE__ ) );
define( 'SEOBOOSTER_DB_VERSION', '7.2.0' );
if ( function_exists( 'seobooster_fs' ) ) {
    $fs_instance = seobooster_fs();
    if ( $fs_instance && is_object( $fs_instance ) ) {
        $fs_instance->set_basename( false, __FILE__ );
    }
} else {
    if ( !function_exists( 'seobooster_fs' ) ) {
        // Create a helper function for easy SDK access.
        function seobooster_fs() {
            global $seobooster_fs;
            if ( !isset( $seobooster_fs ) ) {
                try {
                    // Include Freemius SDK.
                    include_once __DIR__ . '/vendor/freemius/wordpress-sdk/start.php';
                    // Check if fs_dynamic_init function exists
                    if ( !function_exists( 'fs_dynamic_init' ) ) {
                        return false;
                    }
                    $seobooster_fs = fs_dynamic_init( array(
                        'id'               => '987',
                        'slug'             => 'seo-booster',
                        'type'             => 'plugin',
                        'public_key'       => 'pk_a58b7605ac6e9e90cd7bd9458cfbc',
                        'is_premium'       => false,
                        'premium_suffix'   => 'Premium',
                        'has_addons'       => false,
                        'has_paid_plans'   => true,
                        'trial'            => array(
                            'days'               => 30,
                            'is_require_payment' => true,
                        ),
                        'has_affiliation'  => false,
                        'menu'             => array(
                            'slug'         => 'sb2_dashboard',
                            'first-path'   => 'admin.php?page=sb2_dashboard',
                            'support'      => false,
                            'contact'      => false,
                            'is_top_level' => true,
                        ),
                        'is_live'          => true,
                        'is_org_compliant' => true,
                    ) );
                } catch ( \Exception $e ) {
                    $seobooster_fs = false;
                }
            }
            return $seobooster_fs;
        }

        // Init Freemius with error handling.
        try {
            $fs_instance = seobooster_fs();
            if ( $fs_instance && is_object( $fs_instance ) ) {
                // Signal that SDK was initiated.
                do_action( 'seobooster_fs_loaded' );
            }
        } catch ( \Exception $e ) {
            // Freemius initialization failed; plugin continues without SDK.
        }
    }
    // Add Freemius filters with safety checks
    try {
        $fs_instance = seobooster_fs();
        if ( $fs_instance && is_object( $fs_instance ) ) {
            $fs_instance->add_filter( 'handle_gdpr_admin_notice', '__return_true' );
            $fs_instance->add_filter( 'after_uninstall', array(__NAMESPACE__ . '\\Seobooster2', 'seobooster_do_after_uninstall') );
        }
    } catch ( \Exception $e ) {
        // Freemius filter registration failed; plugin continues without SDK filters.
    }
    // Prevent cannot redeclare
    if ( !function_exists( 'seobooster_do_after_uninstall' ) ) {
        function seobooster_do_after_uninstall() {
            wp_clear_scheduled_hook( 'sbp_dailymaintenance' );
            wp_clear_scheduled_hook( 'seobooster_email_update' );
            wp_clear_scheduled_hook( 'sbp_crawl_internal' );
            delete_option( 'sbp_review_notice' );
            delete_option( 'seobooster_selected_site' );
            delete_option( 'seobooster_access_token' );
        }

    }
    // loads persistent admin notices
    require_once SEOBOOSTER_PLUGINPATH . 'inc/Google_API.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/SB_GSC_Ajax.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/SB_GSC_Metaboxes.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/SB_GSC_Processor.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/email_status.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/SB_SEO_Metabox.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/SEO_Output.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/SEO_Plugin_Integration.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/AI_Writing_Outline.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/Admin_Columns.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/Form_Processor.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/Media/AI_Image_Generator.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/Media/Media_Library_Enhancements.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/Credits_Service.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/Credits_REST_Controller.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/Bulk_SEO_Analysis.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/Tools/Tools_Image_Scanner.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/Tools/Tools_Image_Batch.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/Tools/Tools_Page.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/SEO_Issues_Manager.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/SEO_Issues_Ajax.php';
    require_once SEOBOOSTER_PLUGINPATH . 'inc/SB_GSC_Issues_Processor.php';
    if ( !class_exists( 'seobooster2' ) ) {
        class Seobooster2 {
            /**
             * Plugin version
             *
             * @var integer
             */
            public static $version = null;

            // Only use this variable for database update tracking
            private static $replaced_keywords_tracking = array();

            /**
             * Plugin version
             *
             * @var integer
             */
            public function __construct() {
                include_once SEOBOOSTER_PLUGINPATH . 'inc/Utils.php';
                include_once SEOBOOSTER_PLUGINPATH . 'inc/CacheManager.php';
                include_once SEOBOOSTER_PLUGINPATH . 'inc/SEO_Analysis.php';
                add_action( 'init', array('\\Cleverplugins\\SEOBooster\\CacheManager', 'init') );
                add_action( 'wp_ajax_seobooster_gsc_make_auto_link', array(Utils::class, 'gsc_make_auto_link') );
                add_action( 'seobooster_gsc_data_fetch', array(Google_API::class, 'fetch_gsc_data_cron') );
                add_action( 'seobooster_token_validation', array(Google_API::class, 'validate_token') );
                add_action( 'wp_ajax_fetch_chart_data', array(Google_API::class, 'fetch_chart_data_ajax') );
                add_action( 'wp_ajax_sb_gsc_import_data', array(Google_API::class, 'sb_gsc_import_data') );
                add_action( 'wp_ajax_manual_token_refresh', array(Google_API::class, 'ajax_manual_token_refresh') );
                add_action( 'wp_ajax_sb_log_table', array(__CLASS__, 'sb_log_table') );
                add_action( 'wp_ajax_sb_gsc_table', array(__CLASS__, 'sb_gsc_table') );
                add_action( 'wp_ajax_weeklyemailsignup', array(Utils::class, 'process_weekly_email_signup') );
                add_action( 'wp_ajax_sb_seo_get_active_plugin', array(__CLASS__, 'ajax_get_active_seo_plugin') );
                add_action( 'wp_head', array(__CLASS__, 'seo_booster_add_inline_css') );
                add_action( 'add_meta_boxes', array(__CLASS__, 'do_custom_meta') );
                // Initialize SEO Output
                SEO_Output::init();
                // Initialize AI Writing Outline
                AI_Writing_Outline::init();
                // Initialize AI Image Generator
                AI_Image_Generator::init();
                // Initialize Credits REST Controller
                Credits_REST_Controller::init();
                // Initialize Media Library Enhancements
                Media_Library_Enhancements::init();
                // Initialize Bulk SEO Analysis
                Bulk_SEO_Analysis::init();
                // Initialize Tools page
                \Cleverplugins\SEOBooster\Tools\Tools_Page::init();
                // Initialize Admin Columns (if enabled)
                if ( get_option( 'seobooster_enable_admin_columns', 1 ) ) {
                    Admin_Columns::init();
                }
                // Initialize SEO Possibilities AJAX
                SEO_Issues_Ajax::init();
                add_action( 'seobooster_email_update', array(email_status::class, 'send_email_update') );
                add_action( 'seobooster_dailymaintenance', array(Utils::class, 'do_seobooster_dailymaintenance') );
                add_action( 'admin_notices', array(__CLASS__, 'do_admin_notices') );
                add_action( 'sb_analyze_seo_for_url', array(__CLASS__, 'process_seo_analysis_for_url') );
                add_action( 'sb_seo_possibilities_auto_scan', array(__CLASS__, 'process_pending_seo_analysis') );
                add_action( 'admin_init', array(__CLASS__, 'admin_init') );
                add_action( 'admin_init', array(Google_API::class, 'handle_reset_authentication') );
                add_action( 'admin_init', array(__CLASS__, 'handle_oauth_callback') );
                add_action( 'init', array(__CLASS__, 'on_init') );
                add_action( 'wp', array(Utils::class, 'prefixsetupschedule') );
                add_action( 'save_post', array(__CLASS__, 'do_meta_save') );
                add_action( 'edited_term', array(__CLASS__, 'do_term_save') );
                add_action( 'admin_menu', array(__CLASS__, 'add_pages') );
                add_action( 'admin_enqueue_scripts', array(__CLASS__, 'do_admin_enqueue_scripts') );
                add_action( 'admin_enqueue_scripts', array(SB_GSC_Metaboxes::class, 'enqueue_scripts') );
                add_action( 'init', array(SB_Autolink_Ajax::class, 'init') );
                add_action( 'wp_ajax_ajax_add_keyword', array(__CLASS__, 'wp_ajax_ajax_add_keyword_callback') );
                add_action( 'wpmu_drop_tables', array(__CLASS__, 'on_delete_blog') );
                register_activation_hook( __FILE__, array(__CLASS__, 'seobooster_activate') );
                register_deactivation_hook( __FILE__, array(__CLASS__, 'seobooster_deactivate') );
                add_filter( 'fl_builder_ui_bar_buttons', array(__CLASS__, 'add_bb_diag_button') );
                add_action( 'admin_bar_menu', array(__CLASS__, 'add_seobooster_admin_bar'), 999 );
                add_action( 'admin_enqueue_scripts', array(__NAMESPACE__ . '\\Google_API', 'load_adminbar_js') );
                add_action( 'wp_enqueue_scripts', array(__NAMESPACE__ . '\\Google_API', 'load_adminbar_js') );
                // Load GSC keyword highlighting scripts when feature is active
                add_action( 'wp_enqueue_scripts', array(__NAMESPACE__ . '\\Google_API', 'load_gsc_highlight_scripts') );
                // Automatic linking: full-page output buffer (ContentProcessing on final HTML).
                if ( !is_admin() ) {
                    add_action( 'template_redirect', array(__CLASS__, 'start_output_buffer'), 1 );
                    add_action( 'shutdown', array(__CLASS__, 'end_output_buffer'), 0 );
                }
                // Add new action for Elementor editor
                add_action( 'elementor/editor/before_enqueue_scripts', array($this, 'enqueue_elementor_editor_scripts') );
                // Fix: Add conditional script loading
                add_action( 'wp_enqueue_scripts', function () {
                    // Only load frontend scripts when needed
                    if ( !is_admin() && !is_admin_bar_showing() ) {
                        return;
                    }
                    Google_API::load_adminbar_js();
                }, 100 );
                // Fix: Ensure admin scripts are loaded with proper priority
                add_action( 'admin_enqueue_scripts', array(__CLASS__, 'do_admin_enqueue_scripts'), 100 );
                // Add admin bar styles
                add_action( 'wp_head', array(__CLASS__, 'add_admin_bar_styles') );
                add_action( 'admin_head', array(__CLASS__, 'add_admin_bar_styles') );
                // WordPress core action hooks
                add_action( 'template_redirect', array(__CLASS__, 'template_redirect_action'), 1 );
            }

            /**
             * Enqueue scripts only in Elementor editor
             */
            public function enqueue_elementor_editor_scripts() {
                if ( !class_exists( '\\Elementor\\Plugin' ) ) {
                    return;
                }
                // First load WinBox library
                wp_enqueue_script(
                    'winbox',
                    SEOBOOSTER_PLUGINURL . 'js/min/winbox.bundle.min.js',
                    array('jquery'),
                    filemtime( SEOBOOSTER_PLUGINPATH . 'js/min/winbox.bundle.min.js' ),
                    true
                );
                // Load Tabulator library and styles
                wp_enqueue_style(
                    'tabulator',
                    SEOBOOSTER_PLUGINURL . 'js/tabulator/dist/css/tabulator.min.css',
                    array(),
                    filemtime( SEOBOOSTER_PLUGINPATH . 'js/tabulator/dist/css/tabulator.min.css' ),
                    'all'
                );
                wp_enqueue_script(
                    'tabulator',
                    SEOBOOSTER_PLUGINURL . 'js/tabulator/dist/js/tabulator.min.js',
                    array('jquery'),
                    filemtime( SEOBOOSTER_PLUGINPATH . 'js/tabulator/dist/js/tabulator.min.js' ),
                    true
                );
                // Then load the adminbar script
                wp_enqueue_script(
                    'seobooster-adminbar',
                    SEOBOOSTER_PLUGINURL . 'js/seobooster-adminbar.js',
                    array('jquery', 'winbox', 'tabulator'),
                    filemtime( SEOBOOSTER_PLUGINPATH . 'js/seobooster-adminbar.js' ),
                    true
                );
                // Pass necessary data to adminbar script
                wp_localize_script( 'seobooster-adminbar', 'seobooster_adminbar', array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'post_url' => get_permalink(),
                    'security' => wp_create_nonce( 'sb_gsc_nonce' ),
                    'text'     => array(
                        'query'        => __( 'Query', 'seo-booster' ),
                        'impressions'  => __( 'Impressions', 'seo-booster' ),
                        'clicks'       => __( 'Clicks', 'seo-booster' ),
                        'ctr'          => __( 'CTR', 'seo-booster' ),
                        'avg_position' => __( 'Position', 'seo-booster' ),
                        'used'         => __( 'Used', 'seo-booster' ),
                        'error'        => __( 'Error', 'seo-booster' ),
                        'search'       => __( 'Search...', 'seo-booster' ),
                    ),
                ) );
                // Load the admin bar CSS file
                wp_enqueue_style(
                    'seobooster-adminbar',
                    SEOBOOSTER_PLUGINURL . 'css/sb-adminbar.css',
                    array(),
                    filemtime( SEOBOOSTER_PLUGINPATH . 'css/sb-adminbar.css' )
                );
                // Finally load the Elementor editor script, with dependency on adminbar script
                wp_enqueue_script(
                    'seobooster-elementor-editor',
                    SEOBOOSTER_PLUGINURL . 'js/seobooster-elementor-editor.js',
                    array('jquery', 'seobooster-adminbar'),
                    filemtime( SEOBOOSTER_PLUGINPATH . 'js/seobooster-elementor-editor.js' ),
                    true
                );
                // Pass plugin icon URL to Elementor editor script
                wp_localize_script( 'seobooster-elementor-editor', 'seoboosterData', array(
                    'pluginIconUrl' => SEOBOOSTER_PLUGINURL . 'images/sblogo25.png',
                ) );
            }

            /**
             * Handle Google Search Console table data
             *
             * @author  Unknown
             * @since   v0.0.1
             * @version v1.0.0    Tuesday, August 20th, 2024.
             * @access  public static
             * @return  void
             */
            public static function sb_gsc_table() {
                // Check user capabilities
                if ( !current_user_can( 'manage_options' ) ) {
                    wp_send_json_error( array(
                        'message' => __( 'Permission denied.', 'seo-booster' ),
                    ) );
                }
                // Verify nonce
                if ( !isset( $_GET['_nonce'] ) || !wp_verify_nonce( sanitize_text_field( $_GET['_nonce'] ), 'sb_gsc_nonce' ) ) {
                    wp_send_json_error( array(
                        'message' => __( 'Nonce verification failed.', 'seo-booster' ),
                    ) );
                }
                global $wpdb;
                // Set up pagination parameters
                $page = ( isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1 );
                $page_size = ( isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 50 );
                $offset = ($page - 1) * $page_size;
                // Set up sorting parameters
                $orderby = ( isset( $_GET['sort_field'] ) ? sanitize_text_field( $_GET['sort_field'] ) : 'impressions' );
                $order = ( isset( $_GET['sort_order'] ) && in_array( strtoupper( $_GET['sort_order'] ), array('ASC', 'DESC'), true ) ? strtoupper( sanitize_text_field( $_GET['sort_order'] ) ) : 'DESC' );
                // Base query: Select all keywords
                $query = "\n                SELECT \n                qk.id, \n                qk.query, \n                qk.page, \n                qk.first_seen_date, \n                qk.latest_date,\n                COALESCE(SUM(qkh.clicks), 0) as clicks,\n                COALESCE(SUM(qkh.impressions), 0) as impressions,\n                COALESCE(AVG(qkh.ctr), 0) as ctr,\n                COALESCE(AVG(qkh.position), 0) as position\n                FROM {$wpdb->prefix}sb2_query_keywords AS qk\n                LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh ON qk.id = qkh.query_keywords_id\n                WHERE 1=1";
                // Conditionally append search clause
                if ( !empty( $_GET['search'] ) ) {
                    $search = '%' . $wpdb->esc_like( sanitize_text_field( $_GET['search'] ) ) . '%';
                    $query .= $wpdb->prepare( ' AND (qk.query LIKE %s OR qk.page LIKE %s)', $search, $search );
                }
                // Conditionally append filter clause
                if ( !empty( $_GET['lp_filter'] ) ) {
                    $lp_filter = sanitize_text_field( $_GET['lp_filter'] );
                    $query .= $wpdb->prepare( ' AND qk.page = %s', $lp_filter );
                }
                // Append group by, order by, and limit clauses
                $query .= "GROUP BY qk.id, qk.page ORDER BY {$orderby} {$order} LIMIT %d, %d";
                // Execute query and get results
                $logs = $wpdb->get_results( $wpdb->prepare( $query, $offset, $page_size ), ARRAY_A );
                // Process logs to include additional data/formatting as needed
                foreach ( $logs as &$log ) {
                    $log['query'] = esc_html( $log['query'] );
                    $log['page'] = '<a href="' . esc_url( $log['page'] ) . '" target="_blank">' . esc_html( parse_url( $log['page'], PHP_URL_PATH ) ) . '</a>';
                    $log['first_seen_date'] = date_i18n( get_option( 'date_format' ), strtotime( $log['first_seen_date'] ) );
                    $log['latest_date'] = date_i18n( get_option( 'date_format' ), strtotime( $log['latest_date'] ) );
                    $log['ctr'] = number_format_i18n( $log['ctr'], 2 );
                    $log['position'] = number_format_i18n( $log['position'], 2 );
                }
                // Prepare the count queries
                $count_query = "FROM {$wpdb->prefix}sb2_query_keywords AS qk WHERE 1=1";
                if ( !empty( $_GET['search'] ) ) {
                    $count_query .= $wpdb->prepare( ' AND (qk.query LIKE %s OR qk.page LIKE %s)', $search, $search );
                }
                if ( !empty( $_GET['lp_filter'] ) ) {
                    $count_query .= $wpdb->prepare( ' AND qk.page = %s', $lp_filter );
                }
                // Get the total filtered count
                $total_count = $wpdb->get_var( "SELECT COUNT(DISTINCT qk.id) {$count_query}" );
                // Get the total unfiltered count
                $total_count_unfiltered = $wpdb->get_var( "SELECT COUNT(DISTINCT id) FROM {$wpdb->prefix}sb2_query_keywords" );
                // Prepare response
                $response = array(
                    'data'           => $logs,
                    'last_page'      => ceil( $total_count / $page_size ),
                    'total_filtered' => $total_count,
                    'total'          => $total_count_unfiltered,
                );
                // Send the logs along with the total count
                wp_send_json_success( $response );
            }

            /**
             * Handle log table data
             *
             * @author  Unknown
             * @since   v0.0.1
             * @version v1.0.0    Monday, August 26th, 2024.
             * @access  public static
             * @return  void
             */
            public static function sb_log_table() {
                // Check user capabilities
                if ( !current_user_can( 'manage_options' ) ) {
                    wp_send_json_error( array(
                        'message' => 'Permission denied.',
                    ) );
                }
                // Verify nonce
                if ( !isset( $_GET['_nonce'] ) || !wp_verify_nonce( sanitize_text_field( $_GET['_nonce'] ), 'sb_log_nonce' ) ) {
                    wp_send_json_error( array(
                        'message' => 'Nonce verification failed.',
                    ) );
                }
                // Sanitize and process input
                $page = ( isset( $_GET['page'] ) ? intval( $_GET['page'] ) : 1 );
                $page_size = ( isset( $_GET['page_size'] ) ? intval( $_GET['page_size'] ) : 20 );
                $offset = ($page - 1) * $page_size;
                $search = ( isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '' );
                global $wpdb;
                // Build the base query
                $base_query = "FROM {$wpdb->prefix}sb2_log";
                $where = '';
                // If there's a search term, add it to the query
                if ( !empty( $search ) ) {
                    $where = $wpdb->prepare( ' WHERE `log` LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
                }
                // Fetch total record count considering the search filter
                $total_count = $wpdb->get_var( "SELECT COUNT(*) {$base_query} {$where}" );
                $total_count_unfiltered = $wpdb->get_var( "SELECT COUNT(*) {$base_query}" );
                // Fetch logs with pagination and search filtering
                $logs = $wpdb->get_results( $wpdb->prepare( "SELECT `logtime`, `prio`, `log` {$base_query} {$where} ORDER BY `ID` DESC LIMIT %d OFFSET %d;", $page_size, $offset ), ARRAY_A );
                $priority_map = array(
                    0  => __( 'Normal', 'seo-booster' ),
                    1  => __( 'Debug', 'seo-booster' ),
                    2  => __( 'Error', 'seo-booster' ),
                    3  => __( 'Warning', 'seo-booster' ),
                    5  => __( 'Info', 'seo-booster' ),
                    10 => __( 'Success', 'seo-booster' ),
                );
                foreach ( $logs as &$log ) {
                    // Ensure prio is a valid integer
                    $prio = ( isset( $log['prio'] ) ? intval( $log['prio'] ) : 0 );
                    // Get priority text from map, fallback to Unknown
                    $log['prio_text'] = ( isset( $priority_map[$prio] ) ? $priority_map[$prio] : __( 'Unknown', 'seo-booster' ) );
                    // Ensure prio is set correctly
                    $log['prio'] = $prio;
                }
                $response = array(
                    'status'         => 'success',
                    'data'           => $logs,
                    'last_page'      => (int) ceil( $total_count / $page_size ),
                    'total_filtered' => (int) $total_count,
                    'total'          => (int) $total_count_unfiltered,
                );
                wp_send_json( $response );
                exit;
            }

            /**
             * Integration with Beaver Builder
             *
             * @author  Unknown
             * @since   v0.0.1
             * @version v1.0.0    Monday, August 26th, 2024.
             * @access  public static
             * @param   mixed $inlist
             * @return  mixed
             */
            public static function add_bb_diag_button( $inlist ) {
                $inlist['seobooster'] = array(
                    'label' => 'SEO Booster',
                    'show'  => true,
                );
                return $inlist;
            }

            /**
             * handle_oauth_callback.
             *
             * @author  Unknown
             * @since   v0.0.1
             * @version v1.0.0    Monday, August 26th, 2024.
             * @access  public static
             * @return  void
             */
            public static function handle_oauth_callback() {
                if ( isset( $_GET['access_token'] ) ) {
                    // Validate and sanitize the access token
                    $access_token = sanitize_text_field( $_GET['access_token'] );
                    if ( empty( $access_token ) ) {
                        wp_die( esc_html__( 'Missing something.', 'seo-booster' ) );
                    }
                    update_option( 'seobooster_access_token', $access_token, false );
                    $google_email = sanitize_text_field( $_GET['google_email'] );
                    update_option( 'seobooster_google_email', $google_email, false );
                    // Get the list of sites
                    $sites = Google_API::fetch_sites( $access_token );
                    update_option( 'seobooster_gsc_sites', $sites, false );
                    // Delete the reauth flag since authentication was successful
                    delete_option( 'seobooster_needs_reauth' );
                    wp_redirect( admin_url( 'admin.php?page=sb2_dashboard' ) );
                    exit;
                }
            }

            public static function seo_booster_add_inline_css() {
                echo '<style>
                    /* Existing styles for highlighted links */
                    .seo-booster-highlighted-links,
                    a[data-sbfb="1"] {
                        text-decoration:underline;
                        background-color: #ffff99;
                        color: #333;
                        transition: background-color 0.3s, color 0.3s;
                    }
                    .seo-booster-highlighted-links:hover,
                    a[data-sbfb="1"]:hover {
                        background-color: #ffd700;
                        color: #000;
                    }
                </style>';
            }

            /**
             * Returns links from the autolink database
             *
             * @author  Unknown
             * @since   v0.0.1
             * @version v1.0.0  Monday, October 11th, 2021.
             * @access  public static
             * @return  array|false
             */
            public static function return_autolinks() {
                global $wpdb;
                $lookupkwlimit = 500;
                $search_replace_arr = array();
                // Debug the SQL query
                $query = $wpdb->prepare( "SELECT keyword as kw, url as lp, id FROM {$wpdb->prefix}sb2_autolink ORDER BY LENGTH(keyword) DESC, kw DESC LIMIT %d;", $lookupkwlimit );
                $internalkeywords = $wpdb->get_results( $query );
                if ( $internalkeywords ) {
                    $step_count = 0;
                    foreach ( $internalkeywords as $kw ) {
                        if ( filter_var( $kw->lp, FILTER_VALIDATE_URL ) ) {
                            $search_replace_arr[$step_count] = array(
                                'kw' => $kw->kw,
                                'lp' => $kw->lp,
                                'id' => $kw->id,
                            );
                            ++$step_count;
                        }
                    }
                    return $search_replace_arr;
                }
                return false;
            }

            /**
             * do_admin_notices.
             *
             * @author  Unknown
             * @since   v0.0.1
             * @version v1.0.0  Saturday, August 7th, 2021.
             * @access  public static
             * @return  void
             */
            public static function do_admin_notices() {
                $is_sb2_admin_page = Utils::is_sb2_admin_page();
                if ( !$is_sb2_admin_page ) {
                    return;
                }
            }

            /**
             * wp_ajax_ajax_add_keyword_callback.
             *
             * @author  Lars Koudal
             * @since   v0.0.1
             * @version v1.0.0  Sunday, August 1st, 2021.
             * @version v1.0.1  Tuesday, June 25th, 2024.
             * @access  public static
             * @return  void
             */
            public static function wp_ajax_ajax_add_keyword_callback() {
                check_ajax_referer( 'add-keyword-nonce', 'add-keyword-nonce', true );
                if ( !current_user_can( 'manage_options' ) ) {
                    wp_send_json_error( array(
                        'success' => false,
                        'message' => esc_html__( 'You do not have permission to add keywords.', 'seo-booster' ),
                    ) );
                }
                global $wpdb;
                $keyword = sanitize_text_field( $_POST['newkeyword'] );
                $targeturl = sanitize_text_field( wp_unslash( $_POST['targeturl'] ) );
                $keyword_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sb2_autolink WHERE keyword = %s", $keyword ) );
                if ( $keyword_id ) {
                    wp_send_json( array(
                        'answer' => sprintf( __( 'Error - Keyword <code>%s</code> is already used.', 'seo-booster' ), esc_html( $keyword ) ),
                        'error'  => 'kwused',
                    ) );
                }
                // Check URL is valid
                if ( filter_var( $targeturl, FILTER_VALIDATE_URL ) === false ) {
                    wp_send_json( array(
                        'answer' => sprintf( __( 'Error - <code>%s</code> is not a valid URL.', 'seo-booster' ), esc_html( $targeturl ) ),
                        'error'  => 'malurl',
                    ) );
                }
                // Insert the new keyword link
                if ( $keyword && $targeturl ) {
                    $wpdb->insert( "{$wpdb->prefix}sb2_autolink", array(
                        'keyword' => $keyword,
                        'url'     => esc_url_raw( $targeturl ),
                    ), array('%s', '%s') );
                    $last_insert_id = $wpdb->insert_id;
                    if ( $last_insert_id ) {
                        wp_send_json( array(
                            'answer'  => sprintf( __( 'Success! <code>%1$s</code> now links to <code>%2$s</code>.', 'seo-booster' ), esc_html( $keyword ), esc_html( $targeturl ) ),
                            'success' => true,
                        ) );
                    }
                }
                exit;
            }

            /**
             * do_custom_meta.
             *
             * @author  Unknown
             * @since   v0.0.1
             * @version v1.0.0  Tuesday, November 30th, 2021.
             * @access  public static
             * @return  void
             */
            public static function do_custom_meta() {
                $post_types = get_post_types( array(
                    'public'   => true,
                    '_builtin' => false,
                ) );
                array_push( $post_types, 'post', 'page' );
                add_meta_box(
                    'sbp_meta',
                    __( 'SEO Booster', 'seo-booster' ),
                    array(__CLASS__, 'sbp_meta_callback'),
                    $post_types,
                    'side',
                    'default',
                    null
                );
            }

            /**
             * sbp_meta_callback.
             *
             * @author  Unknown
             * @since   v0.0.1
             * @version v1.0.0  Tuesday, November 30th, 2021.
             * @access  public static
             * @param   mixed $post
             * @return  void
             */
            public static function sbp_meta_callback( $post ) {
                wp_nonce_field( basename( __FILE__ ), 'sbp_nonce' );
                $sbp_stored_meta = get_post_meta( $post->ID, '_sbp-autolink', true );
                // first time - lets set the default value to yes, so to replace keywords to links automatically.
                if ( 'auto-draft' === $post->post_status ) {
                    $sbp_stored_meta = 'yes';
                }
                if ( !$sbp_stored_meta ) {
                    update_post_meta( $post->ID, '_sbp-autolink', 'yes' );
                    $sbp_stored_meta = 'yes';
                }
                ?>
				<strong>
					<?php 
                esc_html_e( 'Automatic Linking', 'seo-booster' );
                ?>
				</strong>
				<p>
					<label for="sbp-autolink">
						<input type="checkbox" name="sbp-autolink" id="sbp-autolink" value="yes"
							<?php 
                if ( isset( $sbp_stored_meta ) ) {
                    checked( $sbp_stored_meta, 'yes' );
                }
                ?> />
						<?php 
                esc_html_e( 'Change keywords on this page to links.', 'seo-booster' );
                ?>
					</label>
					<?php 
                $seobooster_internal_linking = get_option( 'seobooster_internal_linking' );
                if ( !$seobooster_internal_linking ) {
                    ?>
						<small>
							<?php 
                    esc_html_e( 'Feature is disabled. Enable in SEO Booster settings.', 'seo-booster' );
                    ?>
						</small>
					<?php 
                } else {
                    $autolink_url = admin_url( 'admin.php?page=sb2_autolink' );
                    ?>
						<small>
							<?php 
                    // translators: 1: opening link tag, 2: closing link tag
                    printf( esc_html__( 'Change keywords and links in %1$sAutolink%2$s', 'seo-booster' ), '<a href="' . esc_url( $autolink_url ) . '" target="_blank">', '</a>' );
                    ?>
						</small>
					<?php 
                }
                ?>
				</p>
			<?php 
            }

            /**
             * Saves the custom meta input
             *
             * @param  int
             * @return [type]
             */
            public static function do_meta_save( $post_id ) {
                // Checks save status
                $is_autosave = wp_is_post_autosave( $post_id );
                $is_revision = wp_is_post_revision( $post_id );
                $is_valid_nonce = ( isset( $_POST['sbp_nonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['sbp_nonce'] ), basename( __FILE__ ) ) ? 'true' : 'false' );
                // Exits script depending on save status
                if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
                    return;
                }
                if ( isset( $_POST['sbp-autolink'] ) ) {
                    update_post_meta( $post_id, '_sbp-autolink', 'yes' );
                } else {
                    update_post_meta( $post_id, '_sbp-autolink', 'no' );
                }
                // Appending keywords to this page??
                if ( isset( $_POST['sbp-appendkeywords'] ) ) {
                    update_post_meta( $post_id, '_sbp-appendkeywords', 'yes' );
                } else {
                    update_post_meta( $post_id, '_sbp-appendkeywords', 'no' );
                }
                // Mark SEO analysis as pending for reanalysis
                if ( class_exists( '\\Cleverplugins\\SEOBooster\\SEO_Issues_Manager' ) ) {
                    \Cleverplugins\SEOBooster\SEO_Issues_Manager::mark_as_pending( $post_id, 'post' );
                }
            }

            /**
             * Handle term save to mark SEO analysis as pending.
             *
             * @since 6.1.26
             * @param int $term_id Term ID.
             * @return void
             */
            public static function do_term_save( $term_id ) {
                // Mark SEO analysis as pending for reanalysis
                if ( class_exists( '\\Cleverplugins\\SEOBooster\\SEO_Issues_Manager' ) ) {
                    \Cleverplugins\SEOBooster\SEO_Issues_Manager::mark_as_pending( $term_id, 'term' );
                }
            }

            /**
             * Process SEO analysis for a URL via Action Scheduler.
             *
             * @since 6.1.26
             * @param string|array $url URL string, or full args array for backward compatibility.
             * @param int|null      $object_id Optional object ID when $url is a string.
             * @param string|null   $object_type Optional object type when $url is a string.
             * @return void
             */
            public static function process_seo_analysis_for_url( $url = null, $object_id = null, $object_type = null ) {
                // Action Scheduler passes array values as separate args; support legacy single-array call too.
                if ( is_array( $url ) && array_key_exists( 'url', $url ) ) {
                    $object_id = $url['object_id'] ?? null;
                    $object_type = $url['object_type'] ?? null;
                    $url = $url['url'] ?? '';
                } else {
                    $url = ( $url !== null ? trim( (string) $url ) : '' );
                }
                Utils::log( 'Starting SEO analysis for URL: ' . $url . ' (Object ID: ' . $object_id . ', Type: ' . $object_type . ')', 5 );
                if ( empty( $url ) ) {
                    Utils::log( 'SEO analysis: No URL provided', 2 );
                    return;
                }
                // Exclude private posts and WooCommerce special pages
                if ( $object_id && $object_type === 'post' && \Cleverplugins\SEOBooster\SEO_Issues_Manager::should_exclude_from_analysis( $object_id ) ) {
                    Utils::log( "Skipping SEO analysis for excluded post ID: {$object_id}", 5 );
                    return;
                }
                try {
                    // Create analysis instance
                    if ( $object_id && $object_type ) {
                        $analysis = new \Cleverplugins\SEOBooster\SEO_Analysis($object_id, $object_type);
                    } else {
                        // URL-only analysis - create a temporary analysis
                        $analysis = new \Cleverplugins\SEOBooster\SEO_Analysis(0, 'url');
                    }
                    // Run analysis with full page content
                    $results = $analysis->analyze( true, false );
                    Utils::log( sprintf( 'SEO analysis completed for URL: %s with %d possibilities', $url, count( $results['issues'] ?? [] ) ), 5 );
                } catch ( \Exception $e ) {
                    Utils::log( 'SEO analysis failed: ' . $e->getMessage(), 2 );
                }
            }

            /**
             * Process pending SEO analysis via Action Scheduler recurring action.
             * Discovers URLs from imported GSC data and schedules analysis for URLs that haven't been analyzed yet.
             * Only schedules new actions if auto-scan is enabled and no other analysis is running.
             *
             * @since 6.1.26
             * @return void
             */
            public static function process_pending_seo_analysis() {
                // Check if auto-scan is enabled
                $enabled = get_option( 'seobooster_seo_possibilities_enabled', 'on' );
                if ( $enabled !== 'on' ) {
                    return;
                }
                if ( !class_exists( '\\Cleverplugins\\SEOBooster\\SEO_Issues_Manager' ) ) {
                    return;
                }
                // Check if Action Scheduler functions are available
                if ( !function_exists( 'as_has_scheduled_action' ) ) {
                    return;
                }
                // Check if any sb_analyze_seo_for_url actions are pending or in-progress
                global $wpdb;
                $running_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions a\n\t\t\t\t\t INNER JOIN {$wpdb->prefix}actionscheduler_groups g ON a.group_id = g.group_id\n\t\t\t\t\t WHERE a.hook = 'sb_analyze_seo_for_url' \n\t\t\t\t\t AND a.status IN ('pending', 'in-progress')\n\t\t\t\t\t AND g.slug = 'seo-booster'" );
                // Only proceed if queue is idle
                if ( $running_count > 0 ) {
                    Utils::log( sprintf( 'Skipping SEO analysis scheduling - %d actions already in queue', $running_count ), 5 );
                    return;
                }
                // Get batch size from settings
                $batch_size = get_option( 'seobooster_seo_analysis_batch_size', 1 );
                $batch_size = max( 1, min( 5, intval( $batch_size ) ) );
                $query_keywords_table = $wpdb->prefix . 'sb2_query_keywords';
                $urls_table = $wpdb->prefix . 'sb2_seo_urls';
                $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
                // Discover URLs from GSC data that haven't been analyzed yet
                // Query finds unique URLs from sb2_query_keywords that don't have analysis records with status='analyzed'
                // Handles both cases: URLs that don't exist in sb2_seo_urls yet, and URLs that exist but have no analyzed analysis
                $query = $wpdb->prepare( "SELECT DISTINCT k.page as url\n\t\t\t\t\t FROM {$query_keywords_table} k\n\t\t\t\t\t LEFT JOIN {$urls_table} u ON u.url = k.page\n\t\t\t\t\t LEFT JOIN (\n\t\t\t\t\t\tSELECT DISTINCT url_id \n\t\t\t\t\t\tFROM {$analysis_table} \n\t\t\t\t\t\tWHERE status = 'analyzed'\n\t\t\t\t\t ) a ON u.id = a.url_id\n\t\t\t\t\t WHERE k.page IS NOT NULL \n\t\t\t\t\t AND k.page != ''\n\t\t\t\t\t AND (u.id IS NULL OR a.url_id IS NULL)\n\t\t\t\t\t LIMIT %d", $batch_size );
                $discovered_urls = $wpdb->get_results( $query, OBJECT );
                if ( empty( $discovered_urls ) ) {
                    Utils::log( 'No URLs discovered from GSC data that need analysis', 5 );
                    return;
                }
                Utils::log( sprintf( 'Discovered %d URLs from GSC data that need analysis', count( $discovered_urls ) ), 5 );
                $scheduled_count = 0;
                foreach ( $discovered_urls as $url_data ) {
                    // Access URL from result object - the query uses 'url' as alias for 'k.page'
                    // Try both 'url' (alias) and 'page' (original field) in case alias doesn't work
                    $url = '';
                    if ( is_object( $url_data ) ) {
                        $url = ( isset( $url_data->url ) ? trim( $url_data->url ) : (( isset( $url_data->page ) ? trim( $url_data->page ) : '' )) );
                    } elseif ( is_array( $url_data ) ) {
                        $url = ( isset( $url_data['url'] ) ? trim( $url_data['url'] ) : (( isset( $url_data['page'] ) ? trim( $url_data['page'] ) : '' )) );
                    }
                    if ( empty( $url ) ) {
                        $debug_info = ( is_object( $url_data ) ? 'Object keys: ' . implode( ', ', array_keys( get_object_vars( $url_data ) ) ) : (( is_array( $url_data ) ? 'Array keys: ' . implode( ', ', array_keys( $url_data ) ) : 'Unknown type' )) );
                        Utils::log( sprintf(
                            'Skipping empty URL in discovered URLs. Type: %s, %s, Full data: %s',
                            gettype( $url_data ),
                            $debug_info,
                            wp_json_encode( $url_data )
                        ), 3 );
                        continue;
                    }
                    // Ensure URL is properly formatted (GSC URLs might be relative paths)
                    // Convert relative URLs to absolute if needed
                    if ( !preg_match( '/^https?:\\/\\//', $url ) ) {
                        // It's a relative URL, make it absolute
                        $url = home_url( $url );
                    }
                    // Final validation - don't proceed if URL is still empty
                    if ( empty( $url ) ) {
                        Utils::log( sprintf( 'Skipping - URL is empty after processing. Original data: %s', wp_json_encode( $url_data ) ), 3 );
                        continue;
                    }
                    // Determine object_id and object_type from URL
                    $object_id = null;
                    $object_type = null;
                    // Check if it's a post URL
                    $post_id = url_to_postid( $url );
                    if ( $post_id ) {
                        $object_id = $post_id;
                        $object_type = 'post';
                    } else {
                        // Check if it's a term URL
                        $term = get_term_by( 'slug', basename( $url ) );
                        if ( $term && !is_wp_error( $term ) ) {
                            $object_id = $term->term_id;
                            $object_type = 'term';
                        }
                    }
                    // Check exclusion status for posts
                    if ( $object_id && $object_type === 'post' ) {
                        if ( \Cleverplugins\SEOBooster\SEO_Issues_Manager::should_exclude_from_analysis( $object_id ) ) {
                            Utils::log( sprintf( 'Skipping excluded post ID: %d for URL: %s', $object_id, $url ), 5 );
                            continue;
                        }
                    }
                    // Note: We already checked if queue is idle at the top, so no need to check for individual URL actions here
                    // Final check before scheduling - ensure URL is not empty
                    if ( empty( $url ) ) {
                        Utils::log( 'Attempted to schedule analysis with empty URL; skipping.', 2 );
                        continue;
                    }
                    // Schedule individual analysis
                    $action_id = as_schedule_single_action(
                        time(),
                        'sb_analyze_seo_for_url',
                        [
                            'url'         => $url,
                            'object_id'   => $object_id,
                            'object_type' => $object_type,
                        ],
                        'seo-booster'
                    );
                    if ( $action_id ) {
                        Utils::log( sprintf(
                            'Scheduled SEO analysis (Action ID: %d) for URL: %s (Object ID: %s, Type: %s)',
                            $action_id,
                            $url,
                            $object_id ?? 'null',
                            $object_type ?? 'null'
                        ), 5 );
                        $scheduled_count++;
                    } else {
                        Utils::log( sprintf( 'Failed to schedule SEO analysis for URL: %s', $url ), 2 );
                    }
                }
                if ( $scheduled_count > 0 ) {
                    Utils::log( sprintf( 'Scheduled %d new SEO analyses from GSC data (batch size: %d)', $scheduled_count, $batch_size ), 5 );
                }
            }

            /**
             * Helper function to generate tagged links
             *
             * @param  string $placement [description]
             * @param  string $page      [description]
             * @param  array  $params    [description]
             * @return string            Full URL with utm_ parameters added
             */
            public static function gen_web_link( $placement = '', $page = '/', $params = array() ) {
                $base_url = 'https://seoboosterpro.com';
                if ( '/' !== $page ) {
                    $page = '/' . trim( $page, '/' ) . '/';
                }
                $utm_source = 'seobooster_free';
                $parts = array_merge( array(
                    'utm_source'   => esc_attr( $utm_source ),
                    'utm_medium'   => 'plugin',
                    'utm_content'  => esc_attr( $placement ),
                    'utm_campaign' => esc_attr( 'seobooster_v' . Utils::get_plugin_version() ),
                ), $params );
                $out = $base_url . $page . '?' . http_build_query( $parts, '', '&amp;' );
                return $out;
            }

            /**
             * Track 404 errors on template_redirect hook
             *
             * @author  Cleverplugins
             * @since   v1.0.0
             * @version v1.1.0
             * @access  public static
             * @return  void
             */
            public static function template_redirect_action() {
                // Skip if doing AJAX, CRON, etc.
                if ( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || defined( 'XMLRPC_REQUEST' ) || defined( 'DOING_AUTOSAVE' ) || defined( 'REST_REQUEST' ) ) {
                    return;
                }
                // Skip if it's a POST request
                if ( !empty( $_POST ) ) {
                    return;
                }
                $currurl = Utils::seobooster_currenturl();
            }

            /**
             * When deleting a blog in multisite - returns array of tables to delete
             *
             * @author  Unknown
             * @since   v0.0.1
             * @version v1.0.0  Tuesday, November 30th, 2021.
             * @access  public static
             * @param   mixed $tables
             * @return  mixed
             */
            public static function on_delete_blog( $tables ) {
                global $wpdb;
                $tables = array();
                $tables[] = $wpdb->prefix . 'sb2_autolink';
                $tables[] = $wpdb->prefix . 'sb2_log';
                $tables[] = $wpdb->prefix . 'sb2_404';
                $tables[] = $wpdb->prefix . 'sb2_query_keywords';
                $tables[] = $wpdb->prefix . 'sb2_query_keywords_history';
                return $tables;
            }

            /**
             * Returns true if on an admin page
             *
             * @author  Unknown
             * @since   v0.0.1
             * @version v1.0.0  Sunday, November 7th, 2021.
             * @access  public static
             * @return  boolean
             */
            /**
             * do_admin_enqueue_scripts.
             *
             * @author  Unknown
             * @since   v0.0.1
             * @version v1.0.0  Saturday, August 7th, 2021.
             * @version v1.0.1  Sunday, November 7th, 2021.
             * @access  public static
             * @return  void
             */
            public static function do_admin_enqueue_scripts() {
                $is_sb2_admin_page = Utils::is_sb2_admin_page();
                if ( $is_sb2_admin_page ) {
                    wp_enqueue_script( 'jquery' );
                    wp_localize_script( 'jquery', 'sb_gsc_ajax', array(
                        'ajaxurl'       => admin_url( 'admin-ajax.php' ),
                        'security'      => wp_create_nonce( 'sb_gsc_nonce' ),
                        'dashboard_url' => admin_url( 'admin.php?page=sb2_dashboard&gsc_updated=1' ),
                    ) );
                    wp_register_script(
                        'seoboosterjs',
                        plugins_url( '/js/seo-booster.js', __FILE__ ) . '?v=' . filemtime( plugin_dir_path( __FILE__ ) . 'js/seo-booster.js' ),
                        array('jquery', 'chart-js'),
                        filemtime( plugin_dir_path( __FILE__ ) . 'js/seo-booster.js' ),
                        true
                    );
                    $current_user = wp_get_current_user();
                    $usermail = $current_user->user_email;
                    $username = $current_user->display_name;
                    if ( function_exists( 'seobooster_fs' ) ) {
                        $fs_instance = seobooster_fs();
                        if ( $fs_instance && is_object( $fs_instance ) && $fs_instance->is_registered() ) {
                            $get_user = $fs_instance->get_user();
                            $usermail = $get_user->email;
                            $username = $get_user->first . ' ' . $get_user->last;
                        }
                    }
                    // Used on dashboard page
                    $sbdata_array = array(
                        'ajaxurl'       => admin_url( 'admin-ajax.php' ),
                        'user_name'     => $username,
                        'email'         => $usermail,
                        'website'       => esc_url_raw( site_url() ),
                        'enablecontact' => false,
                        'nonce'         => wp_create_nonce( 'seobooster-nonce' ),
                        'strings'       => array(
                            'pleaseSelectSite'            => __( 'Please select a site', 'seo-booster' ),
                            'areYouSure'                  => __( 'Are you sure you want to do this?', 'seo-booster' ),
                            'pleaseWait'                  => __( 'Please wait', 'seo-booster' ),
                            'uniqueKeywordsImported'      => __( 'Unique keywords imported', 'seo-booster' ),
                            'totalEntriesProcessed'       => __( 'Total entries processed', 'seo-booster' ),
                            'lastImportKeyword'           => __( 'Last import keyword', 'seo-booster' ),
                            'lastBatch'                   => __( 'Last batch', 'seo-booster' ),
                            'seconds'                     => __( 'seconds', 'seo-booster' ),
                            'completed'                   => __( 'Completed', 'seo-booster' ),
                            'importComplete'              => __( 'Import complete!', 'seo-booster' ),
                            'totalTime'                   => __( 'Total time', 'seo-booster' ),
                            'goToDashboard'               => __( 'Go to Dashboard', 'seo-booster' ),
                            'retry'                       => __( 'Retry', 'seo-booster' ),
                            'errorProcessingRequest'      => __( 'An error occurred while processing the request.', 'seo-booster' ),
                            'savingSiteAndLoadingData'    => __( 'Saving site and loading data. This can take several minutes.', 'seo-booster' ),
                            'elapsedTime'                 => __( 'Elapsed time', 'seo-booster' ),
                            'pleaseWait'                  => __( 'Please wait...', 'seo-booster' ),
                            'showMore'                    => __( 'Show more', 'seo-booster' ),
                            'showLess'                    => __( 'Show less', 'seo-booster' ),
                            'lastImportKeyword'           => __( 'Last imported keyword', 'seo-booster' ),
                            'importComplete'              => __( 'Import complete', 'seo-booster' ),
                            'errorProcessingRequest'      => __( 'Error processing request', 'seo-booster' ),
                            'last28Days'                  => __( 'Last 28 days', 'seo-booster' ),
                            'last3Months'                 => __( 'Last 3 months', 'seo-booster' ),
                            'last6Months'                 => __( 'Last 6 months', 'seo-booster' ),
                            'last12Months'                => __( 'Last 12 months', 'seo-booster' ),
                            'allTime'                     => __( 'All time', 'seo-booster' ),
                            'refresh'                     => __( 'Refresh', 'seo-booster' ),
                            'clicks'                      => __( 'Clicks', 'seo-booster' ),
                            'impressions'                 => __( 'Impressions', 'seo-booster' ),
                            'ctr'                         => __( 'CTR', 'seo-booster' ),
                            'seoBoosterDataVisualization' => __( 'SEO Booster Data Visualization', 'seo-booster' ),
                            'date'                        => __( 'Date', 'seo-booster' ),
                            'value'                       => __( 'Value', 'seo-booster' ),
                            'errorFetchingData'           => __( 'Error fetching data', 'seo-booster' ),
                            'tryAgainLater'               => __( 'Please try again later.', 'seo-booster' ),
                            'visitTroubleshootingGuide'   => __( 'Visit our', 'seo-booster' ),
                            'troubleshootingGuide'        => __( 'Troubleshooting Guide', 'seo-booster' ),
                            'timestamp'                   => __( 'Timestamp', 'seo-booster' ),
                            'priority'                    => __( 'Priority', 'seo-booster' ),
                            'logEntry'                    => __( 'Log Entry', 'seo-booster' ),
                            'tabulatorNotLoaded'          => __( 'Tabulator library is not loaded.', 'seo-booster' ),
                            'uniqueKeywords'              => __( 'Unique Keywords', 'seo-booster' ),
                            'show_info_text'              => __( 'Show More Information', 'seo-booster' ),
                            'hide_info_text'              => __( 'Hide Information', 'seo-booster' ),
                        ),
                    );
                    // Do not load this on admin dashboard
                    wp_enqueue_script(
                        'chart-js',
                        SEOBOOSTER_PLUGINURL . '/js/chartjs/package/dist/chart.umd.js',
                        array('jquery'),
                        Utils::get_plugin_version(),
                        array(
                            'strategy'  => 'defer',
                            'in_footer' => true,
                        )
                    );
                    wp_enqueue_script( 'jquery' );
                    // Enqueue Tabulator CSS and JS
                    wp_enqueue_style( 'tabulator', SEOBOOSTER_PLUGINURL . 'js/tabulator/dist/css/tabulator.min.css' );
                    wp_enqueue_script(
                        'tabulator',
                        SEOBOOSTER_PLUGINURL . 'js/tabulator/dist/js/tabulator.min.js',
                        array('jquery'),
                        Utils::get_plugin_version(),
                        true
                    );
                    wp_register_script(
                        'seobooster-logpage',
                        SEOBOOSTER_PLUGINURL . '/js/seobooster-logpage.js',
                        array('jquery', 'tabulator'),
                        filemtime( plugin_dir_path( __FILE__ ) . 'js/seobooster-logpage.js' ),
                        array(
                            'strategy'  => 'defer',
                            'in_footer' => true,
                        )
                    );
                    wp_localize_script( 'seobooster-logpage', 'sblogdata', array(
                        'ajaxurl'  => admin_url( 'admin-ajax.php' ),
                        'security' => wp_create_nonce( 'sb_log_nonce' ),
                        'strings'  => array(
                            'timestamp'          => __( 'Timestamp', 'seo-booster' ),
                            'priority'           => __( 'Priority', 'seo-booster' ),
                            'logEntry'           => __( 'Log Entry', 'seo-booster' ),
                            'tabulatorNotLoaded' => __( 'Tabulator library is not loaded.', 'seo-booster' ),
                        ),
                    ) );
                    wp_enqueue_script( 'seobooster-logpage' );
                    wp_register_script(
                        'seobooster-gscpage',
                        SEOBOOSTER_PLUGINURL . '/js/seobooster-gsc-page.js',
                        array('jquery', 'tabulator'),
                        filemtime( plugin_dir_path( __FILE__ ) . 'js/seobooster-gsc-page.js' ),
                        array(
                            'strategy'  => 'defer',
                            'in_footer' => true,
                        )
                    );
                    wp_localize_script( 'seobooster-gscpage', 'sbgscdata', array(
                        'ajaxurl'  => admin_url( 'admin-ajax.php' ),
                        'security' => wp_create_nonce( 'sb_gsc_nonce' ),
                        'strings'  => array(
                            'show_info_text'              => esc_js( __( 'Show More Information', 'seo-booster' ) ),
                            'hide_info_text'              => esc_js( __( 'Hide Information', 'seo-booster' ) ),
                            'pleaseWait'                  => __( 'Please wait...', 'seo-booster' ),
                            'showMore'                    => __( 'Show more', 'seo-booster' ),
                            'showLess'                    => __( 'Show less', 'seo-booster' ),
                            'uniqueKeywordsImported'      => __( 'Unique keywords imported', 'seo-booster' ),
                            'totalEntriesProcessed'       => __( 'Total entries processed', 'seo-booster' ),
                            'lastImportKeyword'           => __( 'Last imported keyword', 'seo-booster' ),
                            'lastBatch'                   => __( 'Last batch', 'seo-booster' ),
                            'seconds'                     => __( 'seconds', 'seo-booster' ),
                            'completed'                   => __( 'Completed', 'seo-booster' ),
                            'importComplete'              => __( 'Import complete', 'seo-booster' ),
                            'goToDashboard'               => __( 'Go to Dashboard', 'seo-booster' ),
                            'retry'                       => __( 'Retry', 'seo-booster' ),
                            'pleaseEnterAtLeastOneEmail'  => __( 'Please enter at least one email address.', 'seo-booster' ),
                            'errorProcessingRequest'      => __( 'Error processing request', 'seo-booster' ),
                            'elapsedTime'                 => __( 'Elapsed time', 'seo-booster' ),
                            'savingSiteAndLoadingData'    => __( 'Saving site and loading data', 'seo-booster' ),
                            'last28Days'                  => __( 'Last 28 days', 'seo-booster' ),
                            'last3Months'                 => __( 'Last 3 months', 'seo-booster' ),
                            'last6Months'                 => __( 'Last 6 months', 'seo-booster' ),
                            'last12Months'                => __( 'Last 12 months', 'seo-booster' ),
                            'allTime'                     => __( 'All time', 'seo-booster' ),
                            'refresh'                     => __( 'Refresh', 'seo-booster' ),
                            'clicks'                      => __( 'Clicks', 'seo-booster' ),
                            'impressions'                 => __( 'Impressions', 'seo-booster' ),
                            'ctr'                         => __( 'CTR', 'seo-booster' ),
                            'seoBoosterDataVisualization' => __( 'SEO Booster Data Visualization', 'seo-booster' ),
                            'date'                        => __( 'Date', 'seo-booster' ),
                            'value'                       => __( 'Value', 'seo-booster' ),
                            'refreshKeywordAnalysis'      => __( 'Refresh Keyword Analysis', 'seo-booster' ),
                            'errorDeletingTransients'     => __( 'Error deleting transients', 'seo-booster' ),
                            'seconds'                     => __( 'seconds', 'seo-booster' ),
                            'errorFetchingData'           => __( 'Error fetching data', 'seo-booster' ),
                            'tryAgainLater'               => __( 'Please try again later.', 'seo-booster' ),
                            'visitTroubleshootingGuide'   => __( 'Visit our', 'seo-booster' ),
                            'troubleshootingGuide'        => __( 'Troubleshooting Guide', 'seo-booster' ),
                            'timestamp'                   => __( 'Timestamp', 'seo-booster' ),
                            'priority'                    => __( 'Priority', 'seo-booster' ),
                            'logEntry'                    => __( 'Log Entry', 'seo-booster' ),
                            'tabulatorNotLoaded'          => __( 'Tabulator library is not loaded.', 'seo-booster' ),
                            'uniqueKeywords'              => __( 'Unique Keywords', 'seo-booster' ),
                            'show_info_text'              => __( 'Show More Information', 'seo-booster' ),
                            'hide_info_text'              => __( 'Hide Information', 'seo-booster' ),
                        ),
                    ) );
                    wp_enqueue_script( 'seobooster-gscpage' );
                    $current_screen = get_current_screen();
                    // Enqueue uPlot library specifically for GSC page
                    if ( 'seo-booster_page_sb2_gsc' === $current_screen->id ) {
                        wp_enqueue_script(
                            'uplot',
                            SEOBOOSTER_PLUGINURL . '/js/uPlot/uPlot.iife.min.js',
                            array('jquery'),
                            Utils::get_plugin_version(),
                            true
                        );
                        wp_enqueue_style(
                            'uplot-css',
                            SEOBOOSTER_PLUGINURL . '/js/uPlot/uPlot.min.css',
                            array(),
                            Utils::get_plugin_version()
                        );
                    }
                    wp_localize_script( 'seoboosterjs', 'sbdata', $sbdata_array );
                    wp_enqueue_script( 'seoboosterjs' );
                    wp_enqueue_style(
                        'seoboostercss',
                        SEOBOOSTER_PLUGINURL . '/css/seo-booster.css',
                        array(),
                        filemtime( plugin_dir_path( __FILE__ ) . 'css/seo-booster.css' )
                    );
                }
                // Enqueue SEO Possibilities scripts and styles
                if ( isset( $_GET['page'] ) && $_GET['page'] === 'sb2_seo_issues' ) {
                    wp_enqueue_script(
                        'sb-seo-issues-js',
                        SEOBOOSTER_PLUGINURL . 'js/sb-seo-issues.js',
                        array('jquery'),
                        filemtime( plugin_dir_path( __FILE__ ) . 'js/sb-seo-issues.js' ),
                        true
                    );
                    wp_enqueue_script(
                        'sb-seo-issues-ui-js',
                        SEOBOOSTER_PLUGINURL . 'js/sb-seo-issues-ui.js',
                        array('jquery', 'sb-seo-issues-js'),
                        filemtime( plugin_dir_path( __FILE__ ) . 'js/sb-seo-issues-ui.js' ),
                        true
                    );
                    // Load base styles first
                    wp_enqueue_style(
                        'seoboostercss',
                        SEOBOOSTER_PLUGINURL . '/css/seo-booster.css',
                        array(),
                        filemtime( plugin_dir_path( __FILE__ ) . 'css/seo-booster.css' )
                    );
                    // Then load page-specific styles
                    wp_enqueue_style(
                        'sb-seo-issues-css',
                        SEOBOOSTER_PLUGINURL . 'css/sb-seo-issues.css',
                        array('seoboostercss'),
                        filemtime( plugin_dir_path( __FILE__ ) . 'css/sb-seo-issues.css' )
                    );
                    wp_localize_script( 'sb-seo-issues-js', 'sb_seo_issues', array(
                        'ajaxurl' => admin_url( 'admin-ajax.php' ),
                        'nonce'   => wp_create_nonce( 'sb_seo_issues_nonce' ),
                        'strings' => array(
                            'no_recent' => __( 'No recent analysis', 'seo-booster' ),
                            'analyzing' => __( 'Analyzing...', 'seo-booster' ),
                            'completed' => __( 'Analysis completed', 'seo-booster' ),
                            'error'     => __( 'An error occurred', 'seo-booster' ),
                        ),
                    ) );
                }
            }

            /**
             * on_init.
             *
             * @author  Unknown
             * @since   v0.0.1
             * @version v1.0.0  Saturday, August 7th, 2021.
             * @access  public static
             * @return  void
             */
            public static function on_init() {
                // Add custom cron schedule for token validation
                add_filter( 'cron_schedules', function ( $schedules ) {
                    $schedules['sixhours'] = array(
                        'interval' => 6 * HOUR_IN_SECONDS,
                        'display'  => __( 'Every 6 Hours', 'seo-booster' ),
                    );
                    return $schedules;
                } );
                // Registers data for Gutenberg - https://wordpress.org/gutenberg/handbook/block-api/attributes/#meta
                register_meta( 'post', 'seo_booster_metabox', array(
                    'type'         => 'string',
                    'single'       => true,
                    'show_in_rest' => true,
                ) );
                // Database table maintenance and updates
                if ( isset( $_POST['page'] ) && 'sb2_dashboard' === sanitize_text_field( $_POST['page'] ) ) {
                    $nonce = sanitize_text_field( $_REQUEST['_wpnonce'] );
                    if ( !wp_verify_nonce( $nonce, 'fixdbtables' ) ) {
                        die( esc_html__( 'Security check failed.', 'seo-booster' ) );
                    } elseif ( !current_user_can( 'manage_options' ) ) {
                        die( esc_html__( 'Permission denied.', 'seo-booster' ) );
                    } else {
                        Utils::create_database_tables();
                    }
                }
                // Add reanalysis action handler
            }

            /**
             * seobooster_activate.
             *
             * @author  Unknown
             * @since   v0.0.1
             * @version v1.0.0  Saturday, August 7th, 2021.
             * @access  public static
             * @param   mixed $network_wide
             * @return  void
             */
            public static function seobooster_activate( $network_wide ) {
                global $wpdb;
                // Set default options
                // Enable internal linking by default
                if ( !wp_next_scheduled( 'seobooster_email_update' ) ) {
                    wp_schedule_event( time(), 'weekly', 'seobooster_email_update' );
                }
                // Schedule cache cleanup
                \Cleverplugins\SEOBooster\CacheManager::schedule_cache_cleanup();
                // Schedule daily maintenance
                if ( !wp_next_scheduled( 'seobooster_dailymaintenance' ) ) {
                    wp_schedule_event( time(), 'daily', 'seobooster_dailymaintenance' );
                }
                // Schedule SEO Possibilities auto-scan using Action Scheduler (if enabled)
                if ( function_exists( 'as_schedule_recurring_action' ) ) {
                    $enabled = get_option( 'seobooster_seo_possibilities_enabled', 'on' );
                    $frequency = get_option( 'seobooster_seo_possibilities_frequency', 60 );
                    if ( $enabled === 'on' ) {
                        as_schedule_recurring_action(
                            time(),
                            $frequency,
                            'sb_seo_possibilities_auto_scan',
                            [],
                            'seo-booster'
                        );
                    }
                }
                // Cancel any existing WordPress cron for SEO analysis processing
                $wp_cron_timestamp = wp_next_scheduled( 'seobooster_seo_analysis_processing' );
                if ( $wp_cron_timestamp ) {
                    wp_unschedule_event( $wp_cron_timestamp, 'seobooster_seo_analysis_processing' );
                }
                // Multisite
                if ( is_multisite() ) {
                    $blogs = get_sites();
                    foreach ( $blogs as $keys => $blog ) {
                        // Cast $blog as an array instead of WP_Site object
                        if ( is_object( $blog ) ) {
                            $blog = (array) $blog;
                        }
                        $blog_id = $blog['blog_id'];
                        switch_to_blog( $blog_id );
                        // Add for each blog
                        Utils::create_database_tables();
                        restore_current_blog();
                        // translators:
                        Utils::log( sprintf( 'Created database tables on blog id %s.', $blog_id ), 10 );
                    }
                } else {
                    Utils::create_database_tables();
                }
            }

            /**
             * add_freemius_extra_permission.
             *
             * @author  Lars Koudal
             * @since   v0.0.1
             * @version v1.0.0    Monday, August 12th, 2024.
             * @access  public static
             * @param   mixed $permissions
             * @return  mixed
             */
            public static function add_freemius_extra_permission( $permissions ) {
                $permissions['newsletter'] = array(
                    'icon-class' => 'dashicons dashicons-email-alt2',
                    'label'      => __( 'Newsletter', 'seo-booster' ),
                    'desc'       => __( 'You are added to our newsletter. Unsubscribe anytime.', 'seo-booster' ),
                    'priority'   => 18,
                );
                return $permissions;
            }

            /**
             * seobooster_deactivate.
             *
             * @author  Unknown
             * @since   v0.0.1
             * @version v1.0.0  Saturday, August 7th, 2021.
             * @access  public static
             * @param   mixed $network_wide
             * @return  void
             */
            public static function seobooster_deactivate( $network_wide ) {
                global $wpdb;
                $seobooster_delete_deactivate = get_option( 'seobooster_delete_deactivate' );
                if ( $seobooster_delete_deactivate ) {
                    $table_array = array(
                        $wpdb->prefix . 'sb2_autolink',
                        $wpdb->prefix . 'sb2_404',
                        $wpdb->prefix . 'sb2_log',
                        $wpdb->prefix . 'sb2_query_keywords',
                        $wpdb->prefix . 'sb2_query_keywords_history',
                        $wpdb->prefix . 'sb2_seo_analysis',
                        $wpdb->prefix . 'sb2_seo_issues',
                        $wpdb->prefix . 'sb2_improvements_tracking'
                    );
                    // Multisite
                    if ( is_multisite() ) {
                        $blogs = get_sites();
                        foreach ( $blogs as $keys => $blog ) {
                            // Cast $blog as an array instead of WP_Site object
                            if ( is_object( $blog ) ) {
                                $blog = (array) $blog;
                            }
                            $blog_id = $blog['blog_id'];
                            switch_to_blog( $blog_id );
                            foreach ( $table_array as $tablename ) {
                                $wpdb->query( "DROP TABLE IF EXISTS {$tablename}" );
                            }
                            restore_current_blog();
                            $timestamp = wp_next_scheduled( 'seobooster_email_update' );
                            if ( $timestamp ) {
                                wp_unschedule_event( $timestamp, 'seobooster_email_update' );
                            }
                            $timestamp = wp_next_scheduled( 'seobooster_gsc_data_fetch' );
                            if ( $timestamp ) {
                                wp_unschedule_event( $timestamp, 'seobooster_gsc_data_fetch' );
                            }
                            $timestamp = wp_next_scheduled( 'seobooster_cache_cleanup' );
                            if ( $timestamp ) {
                                wp_unschedule_event( $timestamp, 'seobooster_cache_cleanup' );
                            }
                            $timestamp = wp_next_scheduled( 'seobooster_dailymaintenance' );
                            if ( $timestamp ) {
                                wp_unschedule_event( $timestamp, 'seobooster_dailymaintenance' );
                            }
                            // Cancel Action Scheduler recurring action for SEO Possibilities
                            if ( function_exists( 'as_unschedule_all_actions' ) ) {
                                as_unschedule_all_actions( 'sb_seo_possibilities_auto_scan', [], 'seo-booster' );
                                as_unschedule_all_actions( 'sb_gsc_schedule_traffic_pages', [], 'seo-booster' );
                                as_unschedule_all_actions( 'sb_gsc_inspect_url', [], 'seo-booster' );
                            }
                        }
                    } else {
                        // This is not multisite
                        foreach ( $table_array as $tablename ) {
                            $wpdb->query( "DROP TABLE IF EXISTS {$tablename}" );
                        }
                        // Clean up cron jobs for non-multisite
                        $timestamp = wp_next_scheduled( 'seobooster_email_update' );
                        if ( $timestamp ) {
                            wp_unschedule_event( $timestamp, 'seobooster_email_update' );
                        }
                        $timestamp = wp_next_scheduled( 'seobooster_gsc_data_fetch' );
                        if ( $timestamp ) {
                            wp_unschedule_event( $timestamp, 'seobooster_gsc_data_fetch' );
                        }
                        $timestamp = wp_next_scheduled( 'seobooster_cache_cleanup' );
                        if ( $timestamp ) {
                            wp_unschedule_event( $timestamp, 'seobooster_cache_cleanup' );
                        }
                        $timestamp = wp_next_scheduled( 'seobooster_dailymaintenance' );
                        if ( $timestamp ) {
                            wp_unschedule_event( $timestamp, 'seobooster_dailymaintenance' );
                        }
                        // Cancel Action Scheduler recurring action for SEO Possibilities
                        if ( function_exists( 'as_unschedule_all_actions' ) ) {
                            as_unschedule_all_actions( 'sb_seo_possibilities_auto_scan', [], 'seo-booster' );
                            as_unschedule_all_actions( 'sb_gsc_schedule_traffic_pages', [], 'seo-booster' );
                            as_unschedule_all_actions( 'sb_gsc_inspect_url', [], 'seo-booster' );
                        }
                    }
                    delete_option( 'seobooster_delete_deactivate' );
                }
            }

            /**
             * admin_init.
             *
             * @author  Unknown
             * @since   v0.0.1
             * @version v1.0.0  Wednesday, February 23rd, 2022.
             * @access  public static
             * @return  void
             */
            public static function admin_init() {
                if ( current_user_can( 'manage_options' ) ) {
                    $installed_db_version = get_option( 'SEOBOOSTER_INSTALLED_DB_VERSION', '0' );
                    if ( version_compare( $installed_db_version, SEOBOOSTER_DB_VERSION, '<' ) ) {
                        Utils::log( 'Auto-updating database tables', 10 );
                        Utils::create_database_tables();
                    }
                }
                // One-time migration: map legacy "openai" provider to "wordpress" (WP 7 Connectors)
                if ( get_option( 'seobooster_ai_provider' ) === 'openai' ) {
                    update_option( 'seobooster_ai_provider', 'wordpress' );
                    delete_option( 'seobooster_ai_openai_key' );
                    delete_option( 'seobooster_ai_openai_model' );
                }
                // Remove deprecated per-method automatic linking option (single pipeline only).
                if ( false !== get_option( 'seobooster_link_processing_method', false ) ) {
                    delete_option( 'seobooster_link_processing_method' );
                }
                // Load admin-only classes
                if ( !class_exists( 'WP_List_Table' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
                }
                require_once SEOBOOSTER_PLUGINPATH . 'inc/SEO_Issues_List_Table.php';
                // register setting group @todo
                register_setting( 'seobooster', 'seobooster_selected_site' );
                register_setting( 'seobooster', 'seobooster_gsc_sites' );
                register_setting( 'seobooster', 'seobooster_replace_kw_limit' );
                register_setting( 'seobooster', 'seobooster_replace_kw_multiple' );
                register_setting( 'seobooster', 'seobooster_match_capitalization' );
                register_setting( 'seobooster', 'seobooster_showsearch_queries' );
                register_setting( 'seobooster', 'seobooster_weekly_email' );
                register_setting( 'seobooster', 'seobooster_weekly_email_recipient' );
                register_setting( 'seobooster', 'seobooster_ignorelist' );
                register_setting( 'seobooster', 'seobooster_debug_logging' );
                register_setting( 'seobooster', 'seobooster_replace_cat_desc' );
                register_setting( 'seobooster', 'seobooster_woocommerce' );
                register_setting( 'seobooster', 'seobooster_fof_monitoring', array(
                    'type'    => 'string',
                    'default' => 'on',
                ) );
                if ( isset( $_POST['seobooster_selected_site_nonce'], $_POST['seobooster_selected_site'] ) && wp_verify_nonce( $_POST['seobooster_selected_site_nonce'], 'seobooster_save_selected_site' ) ) {
                    // Sanitize the input.
                    $seobooster_selected_days = 90;
                    $seobooster_selected_site = sanitize_text_field( $_POST['seobooster_selected_site'] );
                    update_option( 'seobooster_selected_site', $seobooster_selected_site, false );
                }
                // ** RESETTING SELECTED SITE
                if ( isset( $_POST['submit_gsc_change_site'] ) && $_POST['submit_gsc_change_site'] ) {
                    $nonce = sanitize_text_field( $_REQUEST['_wpnonce'] );
                    if ( !wp_verify_nonce( $nonce, 'seobooster_do_actions' ) ) {
                        die( esc_html__( 'Security check failed.', 'seo-booster' ) );
                    }
                    if ( !current_user_can( 'manage_options' ) ) {
                        die( esc_html__( 'Permission denied.', 'seo-booster' ) );
                    }
                    delete_option( 'seobooster_selected_site' );
                    global $wpdb;
                    $table_array = array($wpdb->prefix . 'sb2_query_keywords', $wpdb->prefix . 'sb2_query_keywords_history');
                    foreach ( $table_array as $table ) {
                        $wpdb->query( "TRUNCATE TABLE {$table}" );
                    }
                    CacheManager::cleanup_old_cache_files();
                    Utils::log( 'Selected site was reset and keyword history erased.', 5 );
                    wp_redirect( admin_url( 'admin.php?page=sb2_dashboard' ) );
                    exit;
                }
                // Handle settings form processing
                Form_Processor::process_settings_form();
            }

            public static function seo_booster_oauth2_page() {
                if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
                    check_admin_referer( 'seo_booster_oauth2_nonce' );
                    if ( isset( $_POST['selected_site'] ) ) {
                        $selected_site = sanitize_text_field( wp_unslash( $_POST['selected_site'] ) );
                    }
                    update_option( 'seobooster_selected_site', $selected_site, false );
                    echo '<div class="seobooster-notice notice notice-success is-dismissible"><p>' . esc_html__( 'Site selected successfully.', 'seo-booster' ) . '</p></div>';
                }
                $sites = Google_API::fetch_sites();
                if ( is_wp_error( $sites ) ) {
                    echo '<div class="seobooster-notice notice notice-error"><p>' . esc_html( $sites->get_error_message() ) . '</p></div>';
                } else {
                    echo '<form method="post" action="">';
                    wp_nonce_field( 'seo_booster_oauth2_nonce' );
                    echo '<label for="selected_site">' . esc_html__( 'Select Site:', 'seo-booster' ) . '</label>';
                    echo '<select name="selected_site" id="selected_site">';
                    // Add a non-selectable placeholder option
                    echo '<option value="" disabled selected>' . esc_html__( 'Please select a site to continue.', 'seo-booster' ) . '</option>';
                    // Group sites by permission level
                    $available_sites = array();
                    $unavailable_sites = array();
                    foreach ( $sites as $site ) {
                        if ( empty( $site ) ) {
                            continue;
                        }
                        // Handle both array and string formats
                        $site_data = array();
                        $site_data['url'] = ( is_array( $site ) ? $site['siteUrl'] : $site );
                        $site_data['permission'] = ( is_array( $site ) && isset( $site['permissionLevel'] ) ? $site['permissionLevel'] : '' );
                        // Sites with siteUnverifiedUser permission don't have enough access
                        if ( $site_data['permission'] === 'siteUnverifiedUser' ) {
                            $unavailable_sites[] = $site_data;
                        } else {
                            $available_sites[] = $site_data;
                        }
                    }
                    // Sort both arrays alphabetically
                    usort( $available_sites, function ( $a, $b ) {
                        $a_clean = str_replace( 'sc-domain:', '', $a['url'] );
                        $b_clean = str_replace( 'sc-domain:', '', $b['url'] );
                        return strcasecmp( $a_clean, $b_clean );
                    } );
                    usort( $unavailable_sites, function ( $a, $b ) {
                        $a_clean = str_replace( 'sc-domain:', '', $a['url'] );
                        $b_clean = str_replace( 'sc-domain:', '', $b['url'] );
                        return strcasecmp( $a_clean, $b_clean );
                    } );
                    // Display available sites first
                    foreach ( $available_sites as $site_data ) {
                        $value = $site_data['url'];
                        $permission = $site_data['permission'];
                        // Create label
                        $label = esc_html( $value );
                        if ( strpos( $value, 'sc-domain:' ) === 0 ) {
                            $label = str_replace( 'sc-domain:', '', $label ) . ' ' . esc_html__( '(domain verified)', 'seo-booster' );
                        }
                        // Add permission level if available
                        if ( !empty( $permission ) ) {
                            $label .= ' - ' . esc_html( $permission );
                        }
                        echo '<option value="' . esc_attr( $value ) . '">' . $label . '</option>';
                    }
                    // Only add divider and unavailable sites if there are any
                    if ( !empty( $unavailable_sites ) ) {
                        // Add a divider
                        echo '<option disabled>───────────────────</option>';
                        // Display unavailable sites
                        foreach ( $unavailable_sites as $site_data ) {
                            $value = $site_data['url'];
                            $permission = $site_data['permission'];
                            // Create label
                            $label = esc_html( $value );
                            if ( strpos( $value, 'sc-domain:' ) === 0 ) {
                                $label = str_replace( 'sc-domain:', '', $label ) . ' ' . esc_html__( '(domain verified)', 'seo-booster' );
                            }
                            // Add permission level if available
                            if ( !empty( $permission ) ) {
                                $label .= ' - ' . esc_html( $permission ) . ' (' . esc_html__( 'insufficient permissions', 'seo-booster' ) . ')';
                            }
                            echo '<option value="' . esc_attr( $value ) . '">' . $label . '</option>';
                        }
                    }
                    echo '</select>';
                    echo '<button type="submit" class="button button-primary">' . esc_html__( 'Save Site', 'seo-booster' ) . '</button>';
                    echo '</form>';
                }
                echo '<a href="' . esc_url( admin_url( 'admin.php?page=sb2_dashboard' ) ) . '" class="button">' . esc_html__( 'Go to SEO Booster Dashboard', 'seo-booster' ) . '</a>';
            }

            /**
             * add_pages() -
             *
             * @author  Unknown
             * @since   v0.0.1
             * @version v1.0.0  Wednesday, February 23rd, 2022.
             * @access  public static
             * @return  void
             */
            public static function add_pages() {
                add_menu_page(
                    __( 'SEO Booster', 'seo-booster' ) . ' ' . __( 'Dashboard', 'seo-booster' ),
                    __( 'SEO Booster', 'seo-booster' ),
                    'manage_options',
                    'sb2_dashboard',
                    array(__CLASS__, 'add_seobooster2_main'),
                    Utils::get_icon_svg()
                );
                // Oauth page
                add_submenu_page(
                    '',
                    'SEO Booster OAuth2 Redirect',
                    'SEO Booster OAuth2 Redirect',
                    'manage_options',
                    'seo-booster-oauth2',
                    array(__CLASS__, 'seo_booster_oauth2_page')
                );
                $hook = add_submenu_page(
                    'sb2_dashboard',
                    __( 'GSC Overview', 'seo-booster' ),
                    __( 'GSC Overview', 'seo-booster' ),
                    'manage_options',
                    'sb2_gsc',
                    array(__CLASS__, 'add_seobooster2_gscpage')
                );
                add_submenu_page(
                    'sb2_dashboard',
                    __( 'Automatic Links', 'seo-booster' ),
                    __( 'Automatic Links', 'seo-booster' ),
                    'manage_options',
                    'sb2_autolink',
                    array(__CLASS__, 'add_seobooster2_autolink')
                );
                add_submenu_page(
                    'sb2_dashboard',
                    __( 'SEO Possibilities', 'seo-booster' ),
                    __( 'SEO Possibilities', 'seo-booster' ),
                    'edit_posts',
                    'sb2_seo_issues',
                    array(__CLASS__, 'render_seo_possibilities_page')
                );
                add_submenu_page(
                    'sb2_dashboard',
                    __( 'Tools', 'seo-booster' ),
                    __( 'Tools', 'seo-booster' ),
                    'edit_posts',
                    'sb2_tools',
                    array('\\Cleverplugins\\SEOBooster\\Tools\\Tools_Page', 'render')
                );
                add_submenu_page(
                    'sb2_dashboard',
                    __( 'Settings', 'seo-booster' ),
                    __( 'Settings', 'seo-booster' ),
                    'manage_options',
                    'sb2_settings',
                    array(__CLASS__, 'add_seobooster2_settings')
                );
                add_submenu_page(
                    'sb2_dashboard',
                    __( 'Debug Log', 'seo-booster' ),
                    __( 'Debug Log', 'seo-booster' ),
                    'manage_options',
                    'sb2_log',
                    array(__CLASS__, 'add_seobooster2_logpage')
                );
                global $wp_version;
            }

            public static function add_seobooster2_main() {
                include SEOBOOSTER_PLUGINPATH . 'views/dashboard.php';
            }

            public static function add_seobooster2_settings() {
                // Enqueue styles and scripts for settings page
                wp_enqueue_style(
                    'seobooster-settings',
                    plugin_dir_url( __FILE__ ) . 'css/sb-settings.css',
                    array(),
                    '7.2'
                );
                wp_enqueue_media();
                // This is required for wp.media to work
                wp_enqueue_script(
                    'seobooster-settings',
                    plugin_dir_url( __FILE__ ) . 'js/sb-settings.js',
                    array('jquery', 'media-upload', 'thickbox'),
                    '7.2',
                    true
                );
                // Localize script with AJAX data
                wp_localize_script( 'seobooster-settings', 'sbSettings', array(
                    'ajaxurl'         => admin_url( 'admin-ajax.php' ),
                    'flushNonce'      => wp_create_nonce( 'flush_rewrite_rules' ),
                    'gscImportNonce'  => wp_create_nonce( 'sb_gsc_nonce' ),
                    'gscSelectedSite' => get_option( 'seobooster_selected_site', '' ),
                    'strings'         => array(
                        'flushing'             => __( 'Flushing...', 'seo-booster' ),
                        'flushed'              => __( 'Flushed!', 'seo-booster' ),
                        'error'                => __( 'Error!', 'seo-booster' ),
                        'importing'            => __( 'Importing...', 'seo-booster' ),
                        'importCompleted'      => __( 'Import completed successfully!', 'seo-booster' ),
                        'importCompletedShort' => __( 'Import completed!', 'seo-booster' ),
                        'retry'                => __( 'Retry', 'seo-booster' ),
                        'noGscSite'            => __( 'No GSC site selected. Please select a site first.', 'seo-booster' ),
                        'importError'          => __( 'An error occurred while processing the request.', 'seo-booster' ),
                        'importGscData'        => __( 'Import GSC Data', 'seo-booster' ),
                    ),
                ) );
                include SEOBOOSTER_PLUGINPATH . 'views/settings.php';
            }

            public static function add_seobooster2_autolink() {
                include SEOBOOSTER_PLUGINPATH . 'seo-booster-autolink.php';
            }

            /**
             * Page that shows the reports
             *
             * @author  Unknown
             * @since   v0.0.1
             * @version v1.0.0    Monday, August 26th, 2024.
             * @access  public static
             * @return  void
             */
            public static function render_seo_possibilities_page() {
                include SEOBOOSTER_PLUGINPATH . 'seo-booster-issues.php';
                \Cleverplugins\SEOBooster\render_seo_issues_page();
            }

            public static function add_seobooster2_logpage() {
                include SEOBOOSTER_PLUGINPATH . 'seo-booster-log.php';
            }

            public static function add_seobooster2_gscpage() {
                include SEOBOOSTER_PLUGINPATH . 'seo-booster-gsc.php';
            }

            /**
             * add_seobooster_admin_bar.
             *
             * @author  Lars Koudal
             * @since   v0.0.1
             * @version v1.0.0  Friday, April 18th, 2025.
             * @access  public static
             * @param   mixed   $wp_admin_bar
             * @return  boolean
             */
            public static function add_seobooster_admin_bar( $wp_admin_bar ) {
                if ( !current_user_can( 'manage_options' ) ) {
                    return;
                }
                // Main menu item
                $wp_admin_bar->add_node( array(
                    'id'    => 'seobooster',
                    'title' => 'SEO Booster',
                    'href'  => admin_url( 'admin.php?page=sb2_dashboard' ),
                ) );
                // Show Details submenu - toggles the seobooster_showdetails parameter
                $current_url = home_url( $_SERVER['REQUEST_URI'] );
                $is_show_details = isset( $_GET['seobooster_showdetails'] ) && ($_GET['seobooster_showdetails'] === 'true' || $_GET['seobooster_showdetails'] === '1');
                $wp_admin_bar->add_node( array(
                    'parent' => 'seobooster',
                    'id'     => 'seobooster-details',
                    'title'  => ( $is_show_details ? __( 'Hide Details', 'seo-booster' ) : __( 'Show Details', 'seo-booster' ) ),
                    'href'   => ( $is_show_details ? remove_query_arg( 'seobooster_showdetails', $current_url ) : add_query_arg( 'seobooster_showdetails', '1', $current_url ) ),
                ) );
                if ( !is_admin() ) {
                    // Get current URL and check if highlighting is active
                    $current_url = home_url( $_SERVER['REQUEST_URI'] );
                    $is_highlighting = isset( $_GET['seobooster_showlinks'] ) && $_GET['seobooster_showlinks'] === '1';
                    $is_gsc_highlighting = isset( $_GET['seobooster_showgsc'] ) && $_GET['seobooster_showgsc'] === '1';
                    // Show Internal Links submenu as a toggle
                    $wp_admin_bar->add_node( array(
                        'parent' => 'seobooster',
                        'id'     => 'seobooster-show-links',
                        'title'  => ( $is_highlighting ? __( 'Disable Keyword Highlighting', 'seo-booster' ) : __( 'Highlight Automatic Links', 'seo-booster' ) ),
                        'href'   => ( $is_highlighting ? remove_query_arg( 'seobooster_showlinks', $current_url ) : add_query_arg( 'seobooster_showlinks', '1', $current_url ) ),
                    ) );
                    // Show GSC Keywords submenu as a toggle
                    $wp_admin_bar->add_node( array(
                        'parent' => 'seobooster',
                        'id'     => 'seobooster-show-gsc',
                        'title'  => ( $is_gsc_highlighting ? __( 'Disable GSC Keyword Highlighting', 'seo-booster' ) : __( 'Highlight GSC Keywords', 'seo-booster' ) ),
                        'href'   => ( $is_gsc_highlighting ? remove_query_arg( 'seobooster_showgsc', $current_url ) : add_query_arg( 'seobooster_showgsc', '1', $current_url ) ),
                    ) );
                }
                // Other menu items below
                $wp_admin_bar->add_node( array(
                    'parent' => 'seobooster',
                    'id'     => 'seobooster-gsc',
                    'title'  => __( 'GSC Overview', 'seo-booster' ),
                    'href'   => admin_url( 'admin.php?page=sb2_gsc' ),
                ) );
                $wp_admin_bar->add_node( array(
                    'parent' => 'seobooster',
                    'id'     => 'seobooster-autolink',
                    'title'  => __( 'Automatic Links', 'seo-booster' ),
                    'href'   => admin_url( 'admin.php?page=sb2_autolink' ),
                ) );
            }

            /**
             * Detect which page builder is being used
             *
             * @author  Lars Koudal
             * @since   v0.0.1
             * @version v1.0.0  Friday, March 28th, 2025.
             * @version v1.0.1  Friday, March 28th, 2025.
             * @access  private static
             * @param   mixed   $post
             * @return  string
             */
            /**
             * Add admin bar styles
             */
            public static function add_admin_bar_styles() {
                if ( !is_admin_bar_showing() ) {
                    return;
                }
                ?>
				<style>
					.seobooster-debug-content {
						background: #fff;
						padding: 10px;
						border: 1px solid #ccc;
						border-radius: 4px;
						margin: 5px 0;
						font-size: 12px;
						line-height: 1.5;
						color: #333;
						box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
						max-width: 300px;
					}

					.seobooster-debug-content strong {
						color: #0073aa;
						display: inline-block;
						min-width: 140px;
					}

					#wpadminbar .seobooster-debug-info .ab-item:focus .seobooster-debug-content,
					#wpadminbar .seobooster-debug-info:hover .seobooster-debug-content {
						display: block;
					}

					#wpadminbar .seobooster-debug-info .seobooster-debug-content {
						position: absolute;
						right: 0;
						margin-top: 6px;
					}
				</style>
<?php 
            }

            /**
             * Update database with last usage information for keywords
             *
             * @param array $replaced_keywords Array of replaced keywords
             * @param string $current_url Current page URL
             */
            public static function update_keywords_last_usage( $replaced_keywords, $current_url ) {
                global $wpdb;
                // Get the relative path from the full URL
                $site_url = site_url();
                $current_path = str_replace( $site_url, '', $current_url );
                // Ensure it starts with a slash
                if ( substr( $current_path, 0, 1 ) !== '/' ) {
                    $current_path = '/' . $current_path;
                }
                foreach ( $replaced_keywords as $keyword_data ) {
                    // Skip if keyword or URL is missing
                    if ( empty( $keyword_data['keyword'] ) || empty( $keyword_data['url'] ) ) {
                        continue;
                    }
                    // Get current value from lastseen
                    $query = $wpdb->prepare( "SELECT id, lastseen FROM {$wpdb->prefix}sb2_autolink \n                         WHERE keyword = %s AND url = %s", $keyword_data['keyword'], $keyword_data['url'] );
                    $row = $wpdb->get_row( $query );
                    if ( $row ) {
                        // Parse existing URLs or initialize new array
                        $urls = array();
                        if ( !empty( $row->lastseen ) ) {
                            $unserialized = maybe_unserialize( $row->lastseen );
                            if ( is_array( $unserialized ) ) {
                                $urls = $unserialized;
                            } else {
                                // If not an array, it might be a single URL from previous version
                                $urls = array($row->lastseen);
                            }
                        }
                        // Remove current path if it exists (to move it to the beginning)
                        $current_path_index = array_search( $current_path, $urls );
                        if ( false !== $current_path_index ) {
                            unset($urls[$current_path_index]);
                        }
                        // Add current URL to the beginning
                        array_unshift( $urls, $current_path );
                        // Reindex array
                        $urls = array_values( $urls );
                        // Keep only the last 5 unique URLs
                        $urls = array_slice( $urls, 0, 5 );
                        // Update the database
                        $wpdb->update(
                            $wpdb->prefix . 'sb2_autolink',
                            array(
                                'lastseen' => serialize( $urls ),
                            ),
                            array(
                                'id' => $row->id,
                            ),
                            array('%s'),
                            array('%d')
                        );
                    } else {
                        // Insert new record with current URL as first in array
                        $wpdb->insert( $wpdb->prefix . 'sb2_autolink', array(
                            'keyword'  => $keyword_data['keyword'],
                            'url'      => $keyword_data['url'],
                            'lastseen' => serialize( array($current_path) ),
                        ), array('%s', '%s', '%s') );
                    }
                }
            }

            /**
             * Start output buffering for automatic linking (full HTML via ContentProcessing).
             *
             * @since  6.1.15
             * @access public static
             * @return void
             */
            public static function start_output_buffer() {
                // Don't buffer admin, ajax, etc
                if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || defined( 'REST_REQUEST' ) && REST_REQUEST ) {
                    return;
                }
                // Don't buffer feeds, sitemap, etc
                if ( is_feed() || is_robots() || is_trackback() ) {
                    return;
                }
                // Don't buffer if internal linking is disabled
                $seobooster_internal_linking = get_option( 'seobooster_internal_linking', false );
                if ( !$seobooster_internal_linking ) {
                    return;
                }
                ob_start( array(__CLASS__, 'process_output_buffer') );
            }

            /**
             * Process the buffered output and inject keyword links
             *
             * @since  6.1.15
             * @access public static
             * @param  string $buffer The buffered HTML output
             * @return string         The processed HTML with keyword links
             */
            public static function process_output_buffer( $buffer ) {
                if ( empty( $buffer ) ) {
                    return $buffer;
                }
                // Skip if this is not HTML
                if ( !preg_match( '/<html[^>]*>/i', $buffer ) ) {
                    return $buffer;
                }
                // Skip admin, ajax, cron, and rest requests
                if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || defined( 'REST_REQUEST' ) && REST_REQUEST ) {
                    return $buffer;
                }
                // Get autolinks
                $autolinks = self::return_autolinks();
                if ( empty( $autolinks ) ) {
                    return $buffer;
                }
                // Check current post settings
                if ( is_singular() ) {
                    global $post;
                    $sbp_stored_meta = get_post_meta( $post->ID, '_sbp-autolink', true );
                    if ( $sbp_stored_meta !== 'yes' ) {
                        return $buffer;
                    }
                }
                return ContentProcessing::process_content( $buffer );
            }

            /**
             * End output buffering
             *
             * @since  6.1.15
             * @access public static
             * @return void
             */
            public static function end_output_buffer() {
                // This is normally not needed as ob_start callback handles it
                // But we include it for safety
                if ( ob_get_level() > 0 && ob_get_length() > 0 ) {
                    ob_end_flush();
                }
            }

            /**
             * AJAX handler for getting active SEO plugin information.
             *
             * @since 6.1.26
             * @return void
             */
            public static function ajax_get_active_seo_plugin() {
                check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );
                if ( !current_user_can( 'edit_posts' ) ) {
                    wp_send_json_error( [
                        'message' => __( 'Permission denied', 'seo-booster' ),
                    ] );
                }
                $plugin_info = SEO_Plugin_Integration::get_active_seo_plugin();
                wp_send_json_success( $plugin_info );
            }

            // end class seobooster2
        }

        global $seobooster2;
        if ( class_exists( 'Cleverplugins\\SEOBooster\\seobooster2' ) && !$seobooster2 ) {
            $seobooster2 = new seobooster2();
        }
        register_deactivation_hook( __FILE__, array('Cleverplugins\\SEOBooster\\CacheManager', 'cleanup_on_deactivate') );
    }
}