<?php

/**
 * Plugin Name: SEO Booster
 * Version: 6.1.3
 * Plugin URI: https://seeboosterpro.com/
 * Description: SEO Booster integrates with Google Search Console data - bringing the data to life on your website like never before. Optimize keywords and content, track your rankings, and get actionable insights to improve your SEO.
 * Author: cleverplugins.com
 * Author URI: https://seeboosterpro.com/
 * Text Domain: seo-booster
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 *
 * This plugin uses the following 3rd party MIT licensed projects - Thank you for making other developer lives easier :-)
 * Jose Solorzano (https://sourceforge.net/projects/php-html/) for the Simple HTML DOM parser.
 * Thank you Matt van Andel for the WP List Table Example class - https://github.com/Veraxus/wp-list-table-example/blob/master/list-table-example.php
 * The email template (heavily modified by us) is brought to you by EmailOctopus https://emailoctopus.com/ - email marketing for less, via Amazon SES. MIT License
 * https://github.com/themefoundation/custom-meta-box-template/blob/master/custom-meta-box-template.php
 * https://github.com/lukehaas/css-loaders
 * Copyright 2008-2024 cleverplugins.com
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
// don't load directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
if ( function_exists( 'seobooster_fs' ) ) {
    seobooster_fs()->set_basename( false, __FILE__ );
    return;
}
define( 'SEOBOOSTER_PLUGINPATH', plugin_dir_path( __FILE__ ) );
define( 'SEOBOOSTER_PLUGINURL', plugin_dir_url( __FILE__ ) );
define( 'SEOBOOSTER_DB_VERSION', '6.0' );
// Last time database was updated
define( 'SEOBOOSTER_FREEMIUS_STATE', 'seobooster_freemius_state' );
if ( !function_exists( 'seobooster_fs' ) ) {
    // Create a helper function for easy SDK access.
    function seobooster_fs() {
        global $seobooster_fs;
        if ( !isset( $seobooster_fs ) ) {
            // Include Freemius SDK.
            include_once __DIR__ . '/vendor/freemius/wordpress-sdk/start.php';
            $seobooster_fs = fs_dynamic_init( array(
                'id'              => '987',
                'slug'            => 'seo-booster',
                'type'            => 'plugin',
                'public_key'      => 'pk_a58b7605ac6e9e90cd7bd9458cfbc',
                'is_premium'      => false,
                'premium_suffix'  => 'Premium',
                'has_addons'      => false,
                'has_paid_plans'  => true,
                'trial'           => array(
                    'days'               => 14,
                    'is_require_payment' => true,
                ),
                'has_affiliation' => 'all',
                'menu'            => array(
                    'slug'    => 'sb2_dashboard',
                    'support' => false,
                ),
                'is_live'         => true,
            ) );
        }
        return $seobooster_fs;
    }

    // Init Freemius.
    seobooster_fs();
    // Signal that SDK was initiated.
    do_action( 'seobooster_fs_loaded' );
}
seobooster_fs()->add_filter( 'handle_gdpr_admin_notice', '__return_true' );
seobooster_fs()->add_action( 'after_uninstall', 'seobooster_do_after_uninstall' );
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
require_once SEOBOOSTER_PLUGINPATH . 'inc/CacheManager.php';
// @todo - premium only
require_once SEOBOOSTER_PLUGINPATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
// @todo - premium only
require_once SEOBOOSTER_PLUGINPATH . 'inc/SB_GSC_Metaboxes.php';
require_once SEOBOOSTER_PLUGINPATH . 'inc/SB_GSC_Processor.php';
require_once SEOBOOSTER_PLUGINPATH . 'inc/email_status.php';
if ( !class_exists( 'seobooster2' ) ) {
    class Seobooster2 {
        /**
         * Plugin version
         *
         * @var integer
         */
        public static $version = null;

        private static $processed_keywords = [];

        private static $search_replace_arr = [];

        /**
         * Plugin version
         *
         * @var integer
         */
        public function __construct() {
            add_action( 'wp_ajax_sb_gsc_import_data', [Google_API::class, 'sb_gsc_import_data'] );
            add_action( 'wp_ajax_sb_log_table', [__CLASS__, 'sb_log_table'] );
            add_action( 'wp_ajax_sb_gsc_table', [__CLASS__, 'sb_gsc_table'] );
            add_action( 'wp_ajax_weeklyemailsignup', [Utils::class, 'process_weekly_email_signup'] );
            add_action( 'wp_head', array(__CLASS__, 'seo_booster_add_inline_css') );
            add_action( 'add_meta_boxes', array(__CLASS__, 'do_custom_meta') );
            // Adds helpscout permission to Freemius
            if ( function_exists( 'seobooster_fs' ) ) {
                seobooster_fs()->add_filter( 'permission_list', array(__CLASS__, 'add_freemius_permission') );
            }
            add_action( 'seobooster_email_update', array(email_status::class, 'send_email_update') );
            add_action( 'admin_notices', array(__CLASS__, 'do_admin_notices') );
            add_action( 'admin_init', array(Google_API::class, 'handle_manual_update') );
            add_action( 'seobooster_gsc_data_fetch', array(Google_API::class, 'fetch_gsc_data_cron') );
            add_action( 'wp_ajax_fetch_chart_data', array(Google_API::class, 'fetch_chart_data_ajax') );
            add_action( 'wp_ajax_seobooster_gsc_make_auto_link', array(Utils::class, 'gsc_make_auto_link') );
            add_action( 'seobooster_dailymaintenance', array(Utils::class, 'do_seobooster_dailymaintenance') );
            add_action( 'admin_init', array(__CLASS__, 'admin_init') );
            add_action( 'admin_init', array(Google_API::class, 'handle_reset_authentication') );
            add_action( 'admin_init', array(__CLASS__, 'handle_oauth_callback') );
            add_action( 'init', array(__CLASS__, 'on_init') );
            add_action( 'plugins_loaded', array(__CLASS__, 'do_plugins_loaded') );
            add_action( 'wp', array(Utils::class, 'prefixsetupschedule') );
            // add_filter('the_content', array(__CLASS__, 'do_filter_the_content'), PHP_INT_MAX);
            // add_filter('the_excerpt', array(__CLASS__, 'do_filter_the_content'), PHP_INT_MAX);
            add_filter( 'the_content', array(__CLASS__, 'process_the_content'), self::get_filter_priority( 'the_content' ) );
            add_filter( 'the_excerpt', array(__CLASS__, 'process_the_excerpt'), self::get_filter_priority( 'the_content' ) );
            add_action( 'template_redirect', array(__CLASS__, 'template_redirect_action') );
            // todo - test om vi kan bruge options her til at slå feature til eller fra.
            add_action( 'save_post', array(__CLASS__, 'do_meta_save') );
            add_action( 'wp_dashboard_setup', array(__CLASS__, 'add_dashboard_widget') );
            add_action( 'admin_menu', array(__CLASS__, 'add_pages') );
            add_action( 'admin_enqueue_scripts', array(__CLASS__, 'do_admin_enqueue_scripts') );
            add_action( 'admin_enqueue_scripts', [SB_GSC_Metaboxes::class, 'enqueue_scripts'] );
            add_action( 'wp_ajax_ajax_add_keyword', array(__CLASS__, 'wp_ajax_ajax_add_keyword_callback') );
            // Autolink - add new link AJAX
            // Incoming Keywords table callback function
            add_action( 'wpmu_drop_tables', array(__CLASS__, 'on_delete_blog') );
            // On deactivation
            // Adds links in the plugins page
            add_filter(
                'plugin_action_links',
                array(Utils::class, 'add_settings_link'),
                10,
                5
            );
            register_activation_hook( __FILE__, array(__CLASS__, 'seobooster_activate') );
            register_deactivation_hook( __FILE__, array(__CLASS__, 'seobooster_deactivate') );
            add_action( 'admin_footer', array(__CLASS__, 'do_action_admin_footer') );
            add_filter( 'fl_builder_ui_bar_buttons', array(__CLASS__, 'add_bb_diag_button') );
        }

        private static function get_filter_priority( $filter_name ) {
            $priorities = [
                'the_content'                       => 999,
                'the_excerpt'                       => 999,
                'term_description'                  => 999,
                'render_block'                      => 20,
                'elementor/widget/render_content'   => 15,
                'fl_builder_before_render_content'  => 15,
                'et_builder_render_layout'          => 20,
                'oxygen_vsb_after_render_content'   => 20,
                'thrive_architect_template_content' => 15,
                'wpb_content'                       => 15,
                'fusion_component_content'          => 20,
                'cornerstone_content'               => 15,
                'bricks/frontend/render_content'    => 20,
                'woocommerce_short_description'     => 11,
                'kadence_blocks_render_content'     => 15,
                'generate_blocks_content'           => 15,
                'the_content_feed'                  => PHP_INT_MAX,
            ];
            return ( isset( $priorities[$filter_name] ) ? $priorities[$filter_name] : 10 );
        }

        /**
         * Check if we should skip filtering for a given content and context
         *
         * @author	Unknown
         * @since	v0.0.1
         * @version	v1.0.0	Thursday, January 23rd, 2025.
         * @access	private static
         * @param	mixed 	$content	
         * @param	string	$context	Default: ''
         * @return	boolean
         */
        private static function should_skip_filtering( $content, $context = '' ) {
            if ( empty( $content ) || !is_string( $content ) ) {
                return true;
            }
            // Add this debug check
            if ( !is_singular() ) {
                return true;
            }
            // Reset processed keywords on main content
            if ( $context === 'content' ) {
                self::$processed_keywords = [];
            }
            // Skip processing if content contains specific builder markers
            $skip_markers = [
                'data-elementor-type',
                'fl-builder-template',
                'et_pb_section',
                '[vc_row',
                'fusion-builder-container',
                // Avada Builder
                'oxygen-builder',
                // Oxygen Builder
                'data-bricks',
                // Bricks Builder
                'data-piotnet-editor',
            ];
            foreach ( $skip_markers as $marker ) {
                if ( strpos( $content, $marker ) !== false ) {
                    return true;
                }
            }
            // Context-specific checks
            switch ( $context ) {
                case 'block':
                    // Skip processing for specific block contexts
                    if ( doing_filter( 'render_block_core/template-part' ) ) {
                        return true;
                    }
                    break;
                case 'content':
                    // Skip processing in specific content contexts
                    if ( is_admin() || wp_is_json_request() ) {
                        return true;
                    }
                    break;
            }
            return false;
        }

        /**
         * process_the_content.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0    Tuesday, October 29th, 2024.
         * @access  public static
         * @param   mixed $content
         * @return  void
         */
        public static function process_the_content( $content ) {
            static $processing = false;
            static $processed_contents = [];
            // Generate a unique ID for this content
            $content_hash = md5( $content );
            // If we're already processing content or this content was already processed, return it as-is
            if ( $processing || isset( $processed_contents[$content_hash] ) ) {
                return $content;
            }
            $processing = true;
            // Debug the search_replace_arr
            if ( !empty( self::$search_replace_arr ) ) {
            } else {
                self::rebuild_keyword_array();
            }
            // Process the content using do_filter_the_content
            $processed_content = self::do_filter_the_content( $content );
            // Mark this content as processed
            $processed_contents[$content_hash] = true;
            $processing = false;
            // Always return either the processed content or original content
            return ( $processed_content ?: $content );
        }

        private static function rebuild_keyword_array() {
            global $wpdb;
            // Get keywords using the existing function
            self::$search_replace_arr = self::return_autolinks();
            if ( self::$search_replace_arr ) {
            } else {
                self::$search_replace_arr = [];
            }
        }

        /**
         * process_the_excerpt.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0    Tuesday, October 29th, 2024.
         * @access  public static
         * @param   mixed $excerpt
         * @return  mixed
         */
        public static function process_the_excerpt( $excerpt ) {
            // if (!is_main_query() || is_feed()) {
            //     return $excerpt;
            // }
            $new_excerpt = self::do_filter_the_content( $excerpt );
            return $new_excerpt;
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
                wp_send_json_error( [
                    'message' => __( 'Permission denied.', 'seo-booster' ),
                ] );
            }
            // Verify nonce
            if ( !isset( $_GET['_nonce'] ) || !wp_verify_nonce( sanitize_text_field( $_GET['_nonce'] ), 'sb_gsc_nonce' ) ) {
                wp_send_json_error( [
                    'message' => __( 'Nonce verification failed.', 'seo-booster' ),
                ] );
            }
            global $wpdb;
            // Set up pagination parameters
            $page = ( isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1 );
            $page_size = ( isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 50 );
            $offset = ($page - 1) * $page_size;
            // Set up sorting parameters
            $orderby = ( isset( $_GET['sort_field'] ) ? sanitize_text_field( $_GET['sort_field'] ) : 'impressions' );
            $order = ( isset( $_GET['sort_order'] ) && in_array( strtoupper( $_GET['sort_order'] ), ['ASC', 'DESC'], true ) ? strtoupper( sanitize_text_field( $_GET['sort_order'] ) ) : 'DESC' );
            // Base query: Select all keywords
            $query = "\n\t\t\t\tSELECT \n\t\t\t\t\tqk.id, \n\t\t\t\t\tqk.query, \n\t\t\t\t\tqk.page, \n\t\t\t\t\tqk.first_seen_date, \n\t\t\t\t\tqk.latest_date,\n\t\t\t\t\tCOALESCE(SUM(qkh.clicks), 0) as clicks,\n\t\t\t\t\tCOALESCE(SUM(qkh.impressions), 0) as impressions,\n\t\t\t\t\tCOALESCE(AVG(qkh.ctr), 0) as ctr,\n\t\t\t\t\tCOALESCE(AVG(qkh.position), 0) as position\n\t\t\t\tFROM {$wpdb->prefix}sb2_query_keywords AS qk\n\t\t\t\tLEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh ON qk.id = qkh.query_keywords_id\n\t\t\t\tWHERE 1=1";
            // Conditionally append search clause
            if ( !empty( $_GET['search'] ) ) {
                $search = '%' . $wpdb->esc_like( sanitize_text_field( $_GET['search'] ) ) . '%';
                $query .= $wpdb->prepare( " AND (qk.query LIKE %s OR qk.page LIKE %s)", $search, $search );
            }
            // Conditionally append filter clause
            if ( !empty( $_GET['lp_filter'] ) ) {
                $lp_filter = sanitize_text_field( $_GET['lp_filter'] );
                $query .= $wpdb->prepare( " AND qk.page = %s", $lp_filter );
            }
            // Append group by, order by, and limit clauses
            $query .= "\n\t\t\t\tGROUP BY qk.id, qk.page\n\t\t\t\tORDER BY {$orderby} {$order}\n\t\t\t\tLIMIT %d, %d";
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
                $count_query .= $wpdb->prepare( " AND (qk.query LIKE %s OR qk.page LIKE %s)", $search, $search );
            }
            if ( !empty( $_GET['lp_filter'] ) ) {
                $count_query .= $wpdb->prepare( " AND qk.page = %s", $lp_filter );
            }
            // Get the total filtered count
            $total_count = $wpdb->get_var( "SELECT COUNT(DISTINCT qk.id) {$count_query}" );
            // Get the total unfiltered count
            $total_count_unfiltered = $wpdb->get_var( "SELECT COUNT(DISTINCT id) FROM {$wpdb->prefix}sb2_query_keywords" );
            // Prepare response
            $response = [
                "data"           => $logs,
                "last_page"      => ceil( $total_count / $page_size ),
                "total_filtered" => $total_count,
                "total"          => $total_count_unfiltered,
            ];
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
                wp_send_json_error( [
                    'message' => 'Permission denied.',
                ] );
            }
            // Verify nonce
            if ( !isset( $_GET['_nonce'] ) || !wp_verify_nonce( sanitize_text_field( $_GET['_nonce'] ), 'sb_log_nonce' ) ) {
                wp_send_json_error( [
                    'message' => 'Nonce verification failed.',
                ] );
            }
            // Sanitize and process input
            $page = ( isset( $_GET['page'] ) ? intval( $_GET['page'] ) : 1 );
            $page_size = ( isset( $_GET['page_size'] ) ? intval( $_GET['page_size'] ) : 20 );
            $offset = ($page - 1) * $page_size;
            $search = ( isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '' );
            global $wpdb;
            // Build the base query
            $base_query = "FROM {$wpdb->prefix}sb2_log";
            $where = "";
            // If there's a search term, add it to the query
            if ( !empty( $search ) ) {
                $where = $wpdb->prepare( " WHERE `log` LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' );
            }
            // Fetch total record count considering the search filter
            $total_count = $wpdb->get_var( "SELECT COUNT(*) {$base_query} {$where}" );
            $total_count_unfiltered = $wpdb->get_var( "SELECT COUNT(*) {$base_query}" );
            // Fetch logs with pagination and search filtering
            $logs = $wpdb->get_results( $wpdb->prepare( "\n\t\t\t\tSELECT `logtime`, `prio`, `log`\n\t\t\t\t{$base_query}\n\t\t\t\t{$where}\n\t\t\t\tORDER BY `ID` DESC\n\t\t\t\tLIMIT %d OFFSET %d;", $page_size, $offset ), ARRAY_A );
            $priority_map = [
                0  => __( 'Normal', 'seo-booster' ),
                1  => __( 'Debug', 'seo-booster' ),
                2  => __( 'Error', 'seo-booster' ),
                3  => __( 'Warning', 'seo-booster' ),
                5  => __( 'Info', 'seo-booster' ),
                10 => __( 'Success', 'seo-booster' ),
            ];
            foreach ( $logs as &$log ) {
                $log['prio_text'] = $priority_map[$log['prio']] ?? __( 'Unknown', 'seo-booster' );
            }
            $response = [
                'status'         => 'success',
                'data'           => $logs,
                'last_page'      => (int) ceil( $total_count / $page_size ),
                'total_filtered' => (int) $total_count,
                'total'          => (int) $total_count_unfiltered,
            ];
            wp_send_json( $response );
            exit;
        }

        /**
         * Process category descriptions.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0    Saturday, July 27th, 2024.
         * @access  public static
         * @param   mixed $desc
         * @param   mixed $cat_id
         * @return  mixed
         */
        public static function do_filter_cat_description( $desc, $cat_id ) {
            // @todo - make it possible to turn this feature on or off.
            $newcontent = self::do_filter_the_content( $desc );
            return $newcontent;
        }

        /**
         * Filter Elementor widget content
         *
         * @param string $content Widget content
         * @param object $widget Widget instance
         * @return string Filtered content
         */
        public static function filter_elementor_content( $content, $widget ) {
            // Skip empty content
            if ( empty( $content ) ) {
                return $content;
            }
            // Skip certain widget types that shouldn't be processed
            $skip_widgets = ['code', 'shortcode'];
            if ( in_array( $widget->get_name(), $skip_widgets, true ) ) {
                return $content;
            }
            return self::do_filter_the_content( $content, true );
        }

        /**
         * Filter Beaver Builder content
         *
         * @param string $content The module content
         * @return string Filtered content
         */
        public static function filter_beaver_content( $content ) {
            if ( empty( $content ) ) {
                return $content;
            }
            return self::do_filter_the_content( $content, true );
        }

        /**
         * Filter WooCommerce short description
         *
         * @param string $short_description Product short description
         * @return string Filtered short description
         */
        public static function do_filter_wc_short_description( $short_description ) {
            if ( empty( $short_description ) ) {
                return $short_description;
            }
            return self::do_filter_the_content( $short_description, true );
        }

        /**
         * Filter Gutenberg block content
         *
         * @param string $block_content The block content about to be appended
         * @param array  $block        The full block, including name and attributes
         * @return string Filtered block content
         */
        public static function do_filter_render_block_filter( $block_content, $block ) {
            // Skip empty content
            if ( empty( $block_content ) ) {
                return $block_content;
            }
            // Skip certain block types
            $skip_blocks = [
                'core/shortcode',
                'core/html',
                'core/code',
                'core/preformatted',
                'core/template-part'
            ];
            if ( isset( $block['blockName'] ) && in_array( $block['blockName'], $skip_blocks, true ) ) {
                return $block_content;
            }
            // Process WooCommerce blocks differently if needed
            if ( isset( $block['blockName'] ) && strpos( $block['blockName'], 'woocommerce/' ) === 0 ) {
                // Special handling for WooCommerce blocks if needed
            }
            return self::do_filter_the_content( $block_content, true );
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
                wp_redirect( admin_url( 'admin.php?page=sb2_dashboard' ) );
                exit;
            }
        }

        public static function seo_booster_add_inline_css() {
            if ( isset( $_GET['seobooster_showlinks'] ) && $_GET['seobooster_showlinks'] == '1' ) {
                echo '<style>
                .seo-booster-highlighted-links {
                    background-color: #ffff99; /* Light yellow background */
                    color: #333; /* Darker text color for contrast */
                    transition: background-color 0.3s, color 0.3s; /* Smooth transition for hover effect */
                }
                .seo-booster-highlighted-links:hover {
                    background-color: #ffd700; /* Gold background on hover */
                    color: #000; /* Black text color on hover */
                    text-decoration: underline; /* Underline text on hover */
                }
            </style>';
            }
        }

        /**
         * add_freemius_permission.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @param   mixed $permissions
         * @return  mixed
         */
        public static function add_freemius_permission( $permissions ) {
            $permissions['newsletter'] = array(
                'icon-class' => 'dashicons dashicons-email-alt2',
                'label'      => __( 'Newsletter', 'seo-booster' ),
                'desc'       => __( 'Your email is added to the user newsletter.', 'seo-booster' ),
                'priority'   => 17,
            );
            return $permissions;
        }

        /**
         * do_action_admin_footer.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function do_action_admin_footer() {
            $is_sb2_admin_page = self::is_sb2_admin_page();
            if ( !$is_sb2_admin_page ) {
                return;
            }
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
            $query = $wpdb->prepare( "SELECT keyword as kw, url as lp, id FROM {$wpdb->prefix}sb2_autolink ORDER BY kw DESC LIMIT %d;", $lookupkwlimit );
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
            $is_sb2_admin_page = self::is_sb2_admin_page();
            if ( !$is_sb2_admin_page ) {
                return;
            }
        }

        /**
         * Fetch plugin version from plugin PHP header
         *
         * @author  Lars Koudal
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, January 13th, 2021.
         * @version v1.0.1  Thursday, June 20th, 2024.
         * @access  public static
         * @return  mixed
         */
        public static function get_plugin_version() {
            if ( null !== self::$version ) {
                return self::$version;
            }
            $plugin_data = get_file_data( __FILE__, array(
                'version' => 'Version',
            ), 'plugin' );
            self::$version = $plugin_data['version'];
            return $plugin_data['version'];
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
                    'answer' => sprintf( __( 'Error - Keyword <code>%s</code> is already used.', 'seo-booster' ), esc_attr( $keyword ) ),
                    'error'  => 'kwused',
                ) );
            }
            // Check URL is valid
            if ( filter_var( sanitize_text_field( $_POST['targeturl'] ), FILTER_VALIDATE_URL ) === false ) {
                wp_send_json( array(
                    'answer' => sprintf( __( 'Error - <code>%s</code> is not a valid URL.', 'seo-booster' ), esc_attr( sanitize_text_field( $_POST['targeturl'] ) ) ),
                    'error'  => 'malurl',
                ) );
            }
            // Insert the new keyword link
            if ( $keyword && $targeturl ) {
                $wpdb->insert( "{$wpdb->prefix}sb2_autolink", array(
                    'keyword' => sanitize_text_field( $_POST['newkeyword'] ),
                    'url'     => sanitize_text_field( $_POST['targeturl'] ),
                ), array('%s', '%s') );
                $last_insert_id = $wpdb->insert_id;
                if ( $last_insert_id ) {
                    wp_send_json( array(
                        'answer'  => sprintf( __( 'Success! <code>%1$s</code> now links to <code>%2$s</code>.', 'seo-booster' ), $keyword, $targeturl ),
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
                    <small><?php 
                esc_html_e( 'Feature is disabled. Enable in SEO Booster settings.', 'seo-booster' );
                ?></small>
                <?php 
            } else {
                $autolink_url = admin_url( 'admin.php?page=sb2_autolink' );
                ?>
                    <small><?php 
                // translators: 1: opening link tag, 2: closing link tag
                printf( esc_html__( 'Change keywords and links in %1$sAutolink%2$s', 'seo-booster' ), '<a href="' . esc_url( $autolink_url ) . '" target="_blank">', '</a>' );
                ?></small>
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
            // @todo - samle i en setting
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
                'utm_campaign' => esc_attr( 'seobooster_v' . Seobooster2::get_plugin_version() ),
            ), $params );
            $out = $base_url . $page . '?' . http_build_query( $parts, '', '&amp;' );
            return $out;
        }

        /**
         * from shkspr.mobi - https://shkspr.mobi/blog/2012/09/a-utf-8-aware-substr_replace-for-use-in-app-net/
         *
         * @var public  stati
         */
        public static function utf8_substr_replace(
            $original,
            $replacement,
            $position,
            $length
        ) {
            $start_string = mb_substr(
                $original,
                0,
                $position,
                'UTF-8'
            );
            $end_string = mb_substr(
                $original,
                $position + $length,
                mb_strlen( $original ),
                'UTF-8'
            );
            $out = $start_string . $replacement . $end_string;
            return $out;
        }

        /**
         * do_filter_the_content.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, November 30th, 2021.
         * @access  public static
         * @param   mixed   $content
         * @param   boolean $forced  Default: false
         * @return  mixed
         */
        public static function do_filter_the_content( $content, $forced = false ) {
            $start_time = microtime( true );
            $replace_notes = [];
            // Changed to array to track unique keywords
            // Early return if content is empty or not valid
            if ( empty( $content ) || !is_string( $content ) ) {
                return $content;
            }
            // Check if we should process this content
            if ( !$forced && self::should_skip_filtering( $content, 'content' ) ) {
                return $content;
            }
            global $post;
            $replacecontent = false;
            // Original permission checks remain the same
            $seobooster_internal_linking = get_option( 'seobooster_internal_linking', false );
            $seobooster_replace_cat_desc = get_option( 'seobooster_replace_cat_desc', false );
            if ( isset( $post ) && 'page' === $post->post_type && $seobooster_internal_linking ) {
                $replacecontent = true;
            }
            if ( isset( $post->post_type ) && 'post' === $post->post_type && $seobooster_internal_linking ) {
                $replacecontent = true;
            }
            if ( is_post_type_archive() && $seobooster_replace_cat_desc ) {
                $replacecontent = true;
            }
            if ( $forced || $replacecontent ) {
                $thispostid = get_the_id();
                $currenturl = Utils::seobooster_currenturl();
                $currenturl = strtok( $currenturl, '?' );
                $fullcurrenturl = site_url( $currenturl );
                // Initialize search_replace_arr before using it
                $search_replace_arr = self::return_autolinks();
                if ( !is_array( $search_replace_arr ) ) {
                    return $content;
                }
                $replace_limit = intval( get_option( 'seobooster_replace_kw_limit' ) );
                if ( !$replace_limit ) {
                    $replace_limit = 5;
                }
                // Early returns for no keywords
                if ( empty( $search_replace_arr ) ) {
                    return $content;
                }
                $html = \voku\helper\HtmlDomParser::str_get_html( $content );
                if ( !$html ) {
                    return $content;
                }
                try {
                    $replace_count = 0;
                    // Initialize only once here
                    $total_step_count = 0;
                    $replace_notes = [];
                    $class = '';
                    if ( isset( $_GET['seobooster_showlinks'] ) && $_GET['seobooster_showlinks'] === '1' ) {
                        $class = 'class="seo-booster-highlighted-links"';
                    }
                    // Ensure $search_replace_arr is always initialized as an array.
                    $search_replace_arr = ( is_array( $search_replace_arr ) ? $search_replace_arr : array() );
                    $html_occurrences = [];
                    foreach ( $html->find( 'text' ) as $index => $text ) {
                        if ( $index < 3 ) {
                            $html_occurrences[] = sprintf( "[%s] '%s'", $text->parent()->tag, $text->outertext );
                        } else {
                            break;
                        }
                    }
                    global $wpdb;
                    // Get the multiple replacement setting
                    $replace_kw_multiple = get_option( 'seobooster_replace_kw_multiple' );
                    foreach ( $html->find( 'text' ) as $step_count => $text ) {
                        $total_step_count++;
                        $original_text = strval( $text->outertext );
                        $current_text = $original_text;
                        // Keep track of current state
                        // Skip if parent node is a heading, link, or list element
                        $parent = $text->parent();
                        if ( $parent ) {
                            $tag = strtolower( $parent->tag );
                            // Skip headings (h1-h6), links, and list elements
                            if ( preg_match( '/^h[1-6]$|^a$|^li$/', $tag ) ) {
                                continue;
                            }
                            // Also skip if any ancestor is a list
                            $ancestor = $parent;
                            while ( $ancestor ) {
                                if ( in_array( strtolower( $ancestor->tag ), array('ul', 'ol') ) ) {
                                    continue 2;
                                    // Skip this text node entirely
                                }
                                $ancestor = $ancestor->parent();
                            }
                        }
                        if ( '' !== $original_text && ' ' !== $original_text && $replace_count < $replace_limit ) {
                            foreach ( $search_replace_arr as $key => $found_kw ) {
                                // Skip if already processed this keyword
                                $keyword_hash = md5( $found_kw['kw'] . '-' . $found_kw['lp'] );
                                if ( in_array( $keyword_hash, self::$processed_keywords, true ) ) {
                                    continue;
                                }
                                if ( $found_kw['lp'] !== $fullcurrenturl ) {
                                    // First find the position of the keyword in the text
                                    $pos = mb_stripos( $original_text, $found_kw['kw'] );
                                    // Only proceed if we found a valid position
                                    if ( $pos !== false ) {
                                        $keyword_text = $found_kw['kw'];
                                        // Create single link with matched text
                                        $replacestring = '<a href="' . esc_url( $found_kw['lp'] ) . '"' . $class . '>' . esc_html( $keyword_text ) . '</a>';
                                        // Only attempt replacement if we have a valid position
                                        $replacedtext = self::utf8_substr_replace(
                                            $original_text,
                                            $replacestring,
                                            $pos,
                                            mb_strlen( $keyword_text )
                                        );
                                        // Update the text node's content
                                        $text->outertext = $replacedtext;
                                        $replace_count++;
                                    }
                                }
                            }
                        }
                    }
                    // At the end where we log the results
                    if ( isset( $html ) && $replace_count > 0 ) {
                        $processed_content = $html->save();
                        if ( $processed_content ) {
                            $content = $processed_content;
                            $logmessage = sprintf(
                                __( 'Created %1$d links on %2$s (%3$s sec. %4$s loops)', 'seo-booster' ),
                                $replace_count,
                                $currenturl,
                                number_format( microtime( true ) - $start_time, 2 ),
                                number_format_i18n( $total_step_count )
                            );
                        }
                    }
                } finally {
                    // Always clean up the HTML object
                    if ( isset( $html ) ) {
                        unset($html);
                    }
                }
            }
            return $content;
        }

        /**
         * Load language files
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Friday, February 5th, 2021.
         * @access  public static
         * @return  void
         */
        public static function do_plugins_loaded() {
            load_plugin_textdomain( 'seo-booster', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }

        /**
         * Runs on action "template_redirect" - 404 detection
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, November 30th, 2021.
         * @access  public static
         * @return  void
         */
        public static function template_redirect_action() {
            if ( empty( $_POST ) && defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || defined( 'XMLRPC_REQUEST' ) || defined( 'DOING_AUTOSAVE' ) || defined( 'REST_REQUEST' ) ) {
                return;
            }
            $fof_monitoring = get_option( 'seobooster_fof_monitoring' );
            if ( 'on' !== $fof_monitoring ) {
                return;
                // 404 error monitoring is turned off so ...
            }
            $currurl = Utils::seobooster_currenturl();
            if ( isset( $currenturl ) ) {
                $currenturl = strtok( $currenturl, '?' );
                // Strips parameters
            }
            // List of args to ignore in query strings on 404 pages.
            $ignore_args = array('wordfence_lh');
            $ignored_parts = $ignore_args;
            $parts = wp_parse_url( $currurl );
            if ( isset( $parts['query'] ) ) {
                parse_str( $parts['query'], $query_parms );
            }
            // Extracting the part after the domain name to run matches against ignore 404 parts
            $query = wp_parse_url( $currurl );
            $extractedpath = '';
            if ( isset( $query['path'] ) ) {
                $extractedpath .= $query['path'];
            }
            if ( isset( $query['query'] ) ) {
                $extractedpath .= $query['query'];
            }
            $extractedpath = strtolower( $extractedpath );
            global $wpdb;
            if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
                $parsedurl = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
                $domain = $parsedurl['host'];
            }
            if ( is_404() ) {
                if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
                    $referer = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
                } else {
                    $referer = '';
                }
                // Filtrer .css og .js filer fra
                $pathinfo = pathinfo( $currurl );
                if ( isset( $pathinfo['extension'] ) ) {
                    $extension = strtolower( $pathinfo['extension'] );
                    if ( in_array( $extension, array('css', 'js'), true ) ) {
                        return;
                        // filter out unwanted .js and .css
                    }
                }
                $excistingentry = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sb2_404 WHERE lp = %s", $currurl ) );
                $rightnow = gmdate( 'Y-m-d H:i:s' );
                if ( $excistingentry ) {
                    $lastcount = $wpdb->get_var( $wpdb->prepare( "SELECT visits FROM {$wpdb->prefix}sb2_404 WHERE id = %d", $excistingentry ) );
                    $rows_affected = $wpdb->query( $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}sb2_404 SET visits = %d, lastseen = %s WHERE id = %s",
                        $lastcount + 1,
                        $rightnow,
                        $excistingentry
                    ) );
                } else {
                    $wpdb->insert( $wpdb->prefix . 'sb2_404', array(
                        'lp'        => $currurl,
                        'firstseen' => $rightnow,
                        'lastseen'  => $rightnow,
                        'visits'    => 1,
                        'referer'   => $referer,
                    ), array(
                        '%s',
                        '%s',
                        '%s',
                        '%d',
                        '%s'
                    ) );
                    if ( isset( $referer ) && '' !== $referer ) {
                        Utils::log( sprintf( 
                            // translators:
                            __( 'New 404 - %1$s Referer: %2$s', 'seo-booster' ),
                            esc_url( $currurl ),
                            esc_url( $referer )
                         ), 3 );
                    } else {
                        // translators:
                        Utils::log( sprintf( __( 'New 404 - %1$s', 'seo-booster' ), esc_url( $currurl ) ), 3 );
                    }
                }
            }
        }

        /**
         * Returns true if $url is a local installation - Thanks EDD
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Sunday, November 7th, 2021.
         * @access  public static
         * @param   string $url Default: ''
         * @return  mixed
         */
        public static function is_local_url( $url = '' ) {
            $is_local_url = false;
            $url = strtolower( trim( $url ) );
            if ( false === strpos( $url, 'http://' ) && false === strpos( $url, 'https://' ) ) {
                $url = 'http://' . $url;
            }
            $url_parts = wp_parse_url( $url );
            $host = ( !empty( $url_parts['host'] ) ? $url_parts['host'] : false );
            if ( !empty( $url ) && !empty( $host ) ) {
                if ( false !== ip2long( $host ) ) {
                    if ( !filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                        $is_local_url = true;
                    }
                } elseif ( 'localhost' === $host ) {
                    $is_local_url = true;
                }
                $tlds_to_check = array('.dev', '.local', '.loc');
                foreach ( $tlds_to_check as $tld ) {
                    if ( false !== strpos( $host, $tld ) ) {
                        $is_local_url = true;
                        continue;
                    }
                }
                if ( substr_count( $host, '.' ) > 1 ) {
                    $subdomains_to_check = array('dev.', 'staging.');
                    foreach ( $subdomains_to_check as $subdomain ) {
                        if ( 0 === strpos( $host, $subdomain ) ) {
                            $is_local_url = true;
                            continue;
                        }
                    }
                }
            }
            return $is_local_url;
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
        public static function is_sb2_admin_page() {
            $screen = get_current_screen();
            if ( is_object( $screen ) && 'toplevel_page_sb2_dashboard' === $screen->id || 'seo-booster_page_sb2_debug' === $screen->id || 'seo-booster_page_sb2_log' === $screen->id || 'seo-booster_page_sb2_settings' === $screen->id || 'seo-booster_page_sb2_gsc' === $screen->id || 'seo-booster_page_sb2_404' === $screen->id || 'seo-booster_page_sb2_reports' === $screen->id || 'seo-booster_page_sb2_autolink' === $screen->id || 'seo-sb2_dashboard' === $screen->id || 'admin_page_seo-booster-oauth2' === $screen->id ) {
                return $screen->id;
            }
            return false;
        }

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
            $is_sb2_admin_page = self::is_sb2_admin_page();
            if ( $is_sb2_admin_page ) {
                wp_enqueue_script( 'jquery' );
                wp_localize_script( 'jquery', 'sb_gsc_ajax', array(
                    'ajaxurl'       => admin_url( 'admin-ajax.php' ),
                    'security'      => wp_create_nonce( 'sb_gsc_nonce' ),
                    'dashboard_url' => admin_url( 'admin.php?page=sb2_dashboard&gsc_updated=1' ),
                ) );
                // Indlæser specifikke data om bruger til Helpscout Beacon.
                wp_register_script(
                    'seoboosterjs',
                    plugins_url( '/js/min/seo-booster-min.js', __FILE__ ),
                    array('jquery', 'chart-js'),
                    filemtime( plugin_dir_path( __FILE__ ) . 'js/min/seo-booster-min.js' ),
                    true
                );
                $current_user = wp_get_current_user();
                $usermail = $current_user->user_email;
                $username = $current_user->display_name;
                if ( function_exists( 'seobooster_fs' ) ) {
                    if ( seobooster_fs()->is_registered() ) {
                        $get_user = seobooster_fs()->get_user();
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
                        'pleaseWait'                                   => __( 'Please wait', 'seo-booster' ),
                        'uniqueKeywordsImported'                       => __( 'Unique keywords imported', 'seo-booster' ),
                        'totalEntriesProcessed'                        => __( 'Total entries processed', 'seo-booster' ),
                        'lastImportKeyword'                            => __( 'Last import keyword', 'seo-booster' ),
                        'lastBatch'                                    => __( 'Last batch', 'seo-booster' ),
                        'seconds'                                      => __( 'seconds', 'seo-booster' ),
                        'completed'                                    => __( 'Completed', 'seo-booster' ),
                        'importComplete'                               => __( 'Import complete!', 'seo-booster' ),
                        'totalTime'                                    => __( 'Total time', 'seo-booster' ),
                        'goToDashboard'                                => __( 'Go to Dashboard', 'seo-booster' ),
                        'retry'                                        => __( 'Retry', 'seo-booster' ),
                        'errorProcessingRequest'                       => __( 'An error occurred while processing the request.', 'seo-booster' ),
                        'savingSiteAndLoadingData'                     => __( 'Saving site and loading data. This can take several minutes.', 'seo-booster' ),
                        'elapsedTime'                                  => __( 'Elapsed time', 'seo-booster' ),
                        'pleaseWait'                                   => __( 'Please wait...', 'seo-booster' ),
                        'showMore'                                     => __( 'Show more', 'seo-booster' ),
                        'showLess'                                     => __( 'Show less', 'seo-booster' ),
                        'lastImportKeyword'                            => __( 'Last imported keyword', 'seo-booster' ),
                        'importComplete'                               => __( 'Import complete', 'seo-booster' ),
                        'errorProcessingRequest'                       => __( 'Error processing request', 'seo-booster' ),
                        'last28Days'                                   => __( 'Last 28 days', 'seo-booster' ),
                        'last3Months'                                  => __( 'Last 3 months', 'seo-booster' ),
                        'last6Months'                                  => __( 'Last 6 months', 'seo-booster' ),
                        'last12Months'                                 => __( 'Last 12 months', 'seo-booster' ),
                        'allTime'                                      => __( 'All time', 'seo-booster' ),
                        'refresh'                                      => __( 'Refresh', 'seo-booster' ),
                        'clicks'                                       => __( 'Clicks', 'seo-booster' ),
                        'impressions'                                  => __( 'Impressions', 'seo-booster' ),
                        'ctr'                                          => __( 'CTR', 'seo-booster' ),
                        'seoBoosterDataVisualization'                  => __( 'SEO Booster Data Visualization', 'seo-booster' ),
                        'date'                                         => __( 'Date', 'seo-booster' ),
                        'value'                                        => __( 'Value', 'seo-booster' ),
                        'errorFetchingData'                            => __( 'Error fetching data', 'seo-booster' ),
                        'tryAgainLater'                                => __( 'Please try again later.', 'seo-booster' ),
                        'visitTroubleshootingGuide'                    => __( 'Visit our', 'seo-booster' ),
                        'troubleshootingGuide'                         => __( 'Troubleshooting Guide', 'seo-booster' ),
                        'youHaveToAllowHelpscoutBeaconToLoadToGetHelp' => __( 'You have to allow Helpscout Beacon to load to get help', 'seo-booster' ),
                        'timestamp'                                    => __( 'Timestamp', 'seo-booster' ),
                        'priority'                                     => __( 'Priority', 'seo-booster' ),
                        'logEntry'                                     => __( 'Log Entry', 'seo-booster' ),
                        'tabulatorNotLoaded'                           => __( 'Tabulator library is not loaded.', 'seo-booster' ),
                        'uniqueKeywords'                               => __( 'Unique Keywords', 'seo-booster' ),
                    ),
                );
                // Do not load this on admin dashboard
                wp_enqueue_script(
                    'chart-js',
                    SEOBOOSTER_PLUGINURL . '/js/chartjs/package/dist/chart.umd.js',
                    ['jquery'],
                    Seobooster2::get_plugin_version(),
                    [
                        'strategy'  => 'defer',
                        'in_footer' => true,
                    ]
                );
                wp_enqueue_script( 'jquery' );
                // Enqueue Tabulator CSS and JS
                wp_enqueue_style( 'tabulator', SEOBOOSTER_PLUGINURL . 'js/tabulator/dist/css/tabulator.min.css' );
                wp_enqueue_script(
                    'tabulator',
                    SEOBOOSTER_PLUGINURL . 'js/tabulator/dist/js/tabulator.min.js',
                    ['jquery'],
                    Seobooster2::get_plugin_version(),
                    true
                );
                wp_register_script(
                    'seobooster-logpage',
                    SEOBOOSTER_PLUGINURL . '/js/min/seobooster-logpage-min.js',
                    array('jquery', 'tabulator'),
                    filemtime( plugin_dir_path( __FILE__ ) . 'js/min/seobooster-logpage-min.js' ),
                    [
                        'strategy'  => 'defer',
                        'in_footer' => true,
                    ]
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
                    SEOBOOSTER_PLUGINURL . '/js/min/seobooster-gsc-page-min.js',
                    array('jquery', 'tabulator'),
                    filemtime( plugin_dir_path( __FILE__ ) . 'js/min/seobooster-gsc-page-min.js' ),
                    [
                        'strategy'  => 'defer',
                        'in_footer' => true,
                    ]
                );
                wp_localize_script( 'seobooster-gscpage', 'sbgscdata', array(
                    'ajaxurl'  => admin_url( 'admin-ajax.php' ),
                    'security' => wp_create_nonce( 'sb_gsc_nonce' ),
                    'strings'  => array(
                        'pleaseWait'                                   => __( 'Please wait...', 'seo-booster' ),
                        'showMore'                                     => __( 'Show more', 'seo-booster' ),
                        'showLess'                                     => __( 'Show less', 'seo-booster' ),
                        'uniqueKeywordsImported'                       => __( 'Unique keywords imported', 'seo-booster' ),
                        'totalEntriesProcessed'                        => __( 'Total entries processed', 'seo-booster' ),
                        'lastImportKeyword'                            => __( 'Last imported keyword', 'seo-booster' ),
                        'lastBatch'                                    => __( 'Last batch', 'seo-booster' ),
                        'seconds'                                      => __( 'seconds', 'seo-booster' ),
                        'completed'                                    => __( 'Completed', 'seo-booster' ),
                        'importComplete'                               => __( 'Import complete', 'seo-booster' ),
                        'goToDashboard'                                => __( 'Go to Dashboard', 'seo-booster' ),
                        'retry'                                        => __( 'Retry', 'seo-booster' ),
                        'pleaseEnterAtLeastOneEmail'                   => __( 'Please enter at least one email address.', 'seo-booster' ),
                        'errorProcessingRequest'                       => __( 'Error processing request', 'seo-booster' ),
                        'elapsedTime'                                  => __( 'Elapsed time', 'seo-booster' ),
                        'savingSiteAndLoadingData'                     => __( 'Saving site and loading data', 'seo-booster' ),
                        'last28Days'                                   => __( 'Last 28 days', 'seo-booster' ),
                        'last3Months'                                  => __( 'Last 3 months', 'seo-booster' ),
                        'last6Months'                                  => __( 'Last 6 months', 'seo-booster' ),
                        'last12Months'                                 => __( 'Last 12 months', 'seo-booster' ),
                        'allTime'                                      => __( 'All time', 'seo-booster' ),
                        'refresh'                                      => __( 'Refresh', 'seo-booster' ),
                        'clicks'                                       => __( 'Clicks', 'seo-booster' ),
                        'impressions'                                  => __( 'Impressions', 'seo-booster' ),
                        'ctr'                                          => __( 'CTR', 'seo-booster' ),
                        'seoBoosterDataVisualization'                  => __( 'SEO Booster Data Visualization', 'seo-booster' ),
                        'date'                                         => __( 'Date', 'seo-booster' ),
                        'value'                                        => __( 'Value', 'seo-booster' ),
                        'refreshKeywordAnalysis'                       => __( 'Refresh Keyword Analysis', 'seo-booster' ),
                        'errorDeletingTransients'                      => __( 'Error deleting transients', 'seo-booster' ),
                        'seconds'                                      => __( 'seconds', 'seo-booster' ),
                        'errorFetchingData'                            => __( 'Error fetching data', 'seo-booster' ),
                        'tryAgainLater'                                => __( 'Please try again later.', 'seo-booster' ),
                        'visitTroubleshootingGuide'                    => __( 'Visit our', 'seo-booster' ),
                        'troubleshootingGuide'                         => __( 'Troubleshooting Guide', 'seo-booster' ),
                        'youHaveToAllowHelpscoutBeaconToLoadToGetHelp' => __( 'You have to allow Helpscout Beacon to load to get help', 'seo-booster' ),
                        'timestamp'                                    => __( 'Timestamp', 'seo-booster' ),
                        'priority'                                     => __( 'Priority', 'seo-booster' ),
                        'logEntry'                                     => __( 'Log Entry', 'seo-booster' ),
                        'tabulatorNotLoaded'                           => __( 'Tabulator library is not loaded.', 'seo-booster' ),
                    ),
                ) );
                wp_enqueue_script( 'seobooster-gscpage' );
                $current_screen = get_current_screen();
                if ( 'seo-booster_page_sb2_reports' === $current_screen->id ) {
                    wp_enqueue_style(
                        'sb-report-page',
                        SEOBOOSTER_PLUGINURL . '/css/sb-report-page.css',
                        array(),
                        filemtime( plugin_dir_path( __FILE__ ) . 'css/sb-report-page.css' )
                    );
                    wp_enqueue_script(
                        'seobooster-reports',
                        SEOBOOSTER_PLUGINURL . '/js/min/seobooster-reports-min.js',
                        array('jquery'),
                        filemtime( plugin_dir_path( __FILE__ ) . 'js/min/seobooster-reports-min.js' ),
                        [
                            'strategy'  => 'defer',
                            'in_footer' => true,
                        ]
                    );
                    wp_localize_script( 'seobooster-reports', 'sbReportData', array(
                        'nonce'   => wp_create_nonce( 'sb_report_nonce' ),
                        'strings' => array(
                            'loadError' => __( 'Error loading data', 'seo-booster' ),
                            'noData'    => __( 'No data available', 'seo-booster' ),
                            'loading'   => __( 'Loading...', 'seo-booster' ),
                        ),
                    ) );
                }
                // if ( 'dashboard' !== $screen->id ) {
                wp_localize_script( 'seoboosterjs', 'sbdata', $sbdata_array );
                wp_enqueue_script( 'seoboosterjs' );
                wp_enqueue_style(
                    'seoboostercss',
                    SEOBOOSTER_PLUGINURL . '/css/min/seo-booster-min.css',
                    array(),
                    filemtime( plugin_dir_path( __FILE__ ) . 'css/min/seo-booster-min.css' )
                );
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
            // Registers data for Gutenberg - https://wordpress.org/gutenberg/handbook/block-api/attributes/#meta
            register_meta( 'post', 'seo_booster_metabox', array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
            ) );
            // Adds extra permission to Freemius
            if ( function_exists( '\\CleverPlugins\\Plugin\\seobooster_fs' ) ) {
                seobooster_fs()->add_filter( 'permission_list', array(__NAMESPACE__ . '\\Seobooster2', 'add_freemius_extra_permission') );
            }
            // TESTING AND FIXING DATABASE TABLES
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
            if ( !wp_next_scheduled( 'seobooster_email_update' ) ) {
                wp_schedule_event( time(), 'weekly', 'seobooster_email_update' );
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
                    Utils::create_database_tables();
                    restore_current_blog();
                    // translators:
                    Utils::log( sprintf( __( 'Created database tables on blog id <code>%s</code>.', 'seo-booster' ), $blog_id ) );
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
                    $wpdb->prefix . 'sb2_query_keywords_history'
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
                    }
                } else {
                    // This is not multisite
                    foreach ( $table_array as $tablename ) {
                        $wpdb->query( "DROP TABLE IF EXISTS {$tablename}" );
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
            // register setting group @todo
            register_setting( 'seobooster', 'seobooster_selected_site' );
            register_setting( 'seobooster', 'seobooster_gsc_sites' );
            register_setting( 'seobooster', 'seobooster_replace_kw_limit' );
            register_setting( 'seobooster', 'seobooster_replace_kw_multiple' );
            register_setting( 'seobooster', 'seobooster_showsearch_queries' );
            register_setting( 'seobooster', 'seobooster_weekly_email' );
            register_setting( 'seobooster', 'seobooster_weekly_email_recipient' );
            register_setting( 'seobooster', 'seobooster_fof_monitoring' );
            register_setting( 'seobooster', 'seobooster_ignorelist' );
            register_setting( 'seobooster', 'seobooster_debug_logging' );
            register_setting( 'seobooster', 'seobooster_replace_cat_desc' );
            register_setting( 'seobooster', 'seobooster_woocommerce' );
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
                Utils::log( esc_html__( 'Selected site was reset and keyword history erased.', 'seo-booster' ), 5 );
                wp_redirect( admin_url( 'admin.php?page=sb2_dashboard' ) );
                exit;
            }
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
                foreach ( $sites as $site ) {
                    $label = esc_html( $site['siteUrl'] );
                    if ( strpos( $site['siteUrl'], 'sc-domain:' ) === 0 ) {
                        $label .= ' ' . esc_html__( '(domain verified)', 'seo-booster' );
                    }
                    echo '<option value="' . esc_attr( $site['siteUrl'] ) . '">' . $label . '</option>';
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
                __( 'Reports', 'seo-booster' ),
                __( 'Keyword Reports', 'seo-booster' ),
                'manage_options',
                'sb2_reports',
                array(__CLASS__, 'add_seobooster2_reports')
            );
            add_submenu_page(
                'sb2_dashboard',
                __( '404s', 'seo-booster' ),
                __( '404 Errors', 'seo-booster' ),
                'manage_options',
                'sb2_404',
                array(__CLASS__, 'add_seobooster2_404page__premium_only')
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
            include SEOBOOSTER_PLUGINPATH . 'seo-booster-seobooster2.php';
        }

        public static function add_seobooster2_settings() {
            include SEOBOOSTER_PLUGINPATH . 'seo-booster-settings.php';
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
        public static function add_seobooster2_reports() {
            include SEOBOOSTER_PLUGINPATH . 'seo-booster-report-page.php';
        }

        public static function add_seobooster2_logpage() {
            include SEOBOOSTER_PLUGINPATH . 'seo-booster-log.php';
        }

        public static function add_seobooster2_gscpage() {
            include SEOBOOSTER_PLUGINPATH . 'seo-booster-gsc.php';
        }

        /**
         * add_dashboard_widget.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function add_dashboard_widget() {
            wp_add_dashboard_widget( 'add_dashboard_widget', 'News from cleverplugins.com', array(__CLASS__, 'dashboard_widget') );
        }

        /**
         * dashboard_widget.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Sunday, December 19th, 2021.
         * @version v1.0.1  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function dashboard_widget() {
            global $wpdb;
            ?>
            <div style="float:right;">
                <a href="https://cleverplugins.com/" target="_blank"><img src="
            <?php 
            echo esc_url( plugin_dir_url( __FILE__ ) . 'images/cleverpluginslogo.png' );
            ?>
            " height="27" width="150" alt="cleverplugins.com"></a>
            </div>


<?php 
            $feeds = array(array(
                'url'          => 'https://cleverplugins.com/feed/',
                'items'        => 2,
                'show_summary' => 1,
                'show_author'  => 0,
                'show_date'    => 0,
            ));
            wp_dashboard_primary_output( 'dw_dashboard_widget_news', $feeds );
        }

    }

    // end class seobooster2
}
global $seobooster2;
if ( class_exists( 'Cleverplugins\\SEOBooster\\seobooster2' ) && !$seobooster2 ) {
    $seobooster2 = new seobooster2();
    include_once SEOBOOSTER_PLUGINPATH . 'inc/Utils.php';
}
register_deactivation_hook( __FILE__, array('Cleverplugins\\SEOBooster\\CacheManager', 'cleanup_on_deactivate') );