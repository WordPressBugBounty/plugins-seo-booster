<?php

namespace Cleverplugins\SEOBooster;

use Cleverplugins\SEOBooster\Tools\Tools_GSC_Helper;
use Cleverplugins\SEOBooster\Tools\Tools_Image_Scanner;
use Cleverplugins\SEOBooster\Tools\Tools_Llms_Txt;
use Cleverplugins\SEOBooster\Tools\Tools_Meta_Scanner;
use Cleverplugins\SEOBooster\Tools\Tools_Page;
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * First-run setup wizard — orchestrates existing GSC, email, autolink, scan, and AI flows.
 *
 * @package Cleverplugins\SEOBooster
 * @since 7.4.0
 */
class Setup_Wizard {
    const OPTION_STATUS = 'seobooster_setup_status';

    const OPTION_STEP = 'seobooster_setup_step';

    const OPTION_COMPLETED_AT = 'seobooster_setup_completed_at';

    const STATUS_PENDING = 'pending';

    const STATUS_COMPLETED = 'completed';

    const STATUS_DISMISSED = 'dismissed';

    /**
     * Ordered step ids.
     *
     * @return string[]
     */
    public static function get_step_ids() {
        return array(
            'welcome',
            'gsc',
            'email',
            'autolink',
            'scan',
            'ai',
            'health',
            'workplace',
            'ready'
        );
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function init() {
        add_action( 'admin_menu', array(__CLASS__, 'register_page'), 5 );
        add_action( 'admin_init', array(__CLASS__, 'maybe_bootstrap_status'), 1 );
        add_action( 'admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets') );
        add_filter( 'admin_body_class', array(__CLASS__, 'admin_body_class') );
        add_action( 'wp_ajax_sb_setup_get_state', array(__CLASS__, 'ajax_get_state') );
        add_action( 'wp_ajax_sb_setup_set_step', array(__CLASS__, 'ajax_set_step') );
        add_action( 'wp_ajax_sb_setup_save_email', array(__CLASS__, 'ajax_save_email') );
        add_action( 'wp_ajax_sb_setup_enable_autolink', array(__CLASS__, 'ajax_enable_autolink') );
        add_action( 'wp_ajax_sb_setup_health_counts', array(__CLASS__, 'ajax_health_counts') );
        add_action( 'wp_ajax_sb_setup_enable_ai', array(__CLASS__, 'ajax_enable_ai') );
        add_action( 'wp_ajax_sb_setup_get_autolink_suggestions', array(__CLASS__, 'ajax_get_autolink_suggestions') );
        add_action( 'wp_ajax_sb_setup_save_selected_site', array(__CLASS__, 'ajax_save_selected_site') );
        add_action( 'wp_ajax_sb_setup_save_bot_tracking', array(__CLASS__, 'ajax_save_bot_tracking') );
        add_action( 'admin_post_sb_setup_restart', array(__CLASS__, 'handle_restart') );
    }

    /**
     * Set pending on fresh activate; mark completed for already-configured sites.
     *
     * @return void
     */
    public static function on_activate() {
        $existing = get_option( self::OPTION_STATUS, false );
        if ( false !== $existing ) {
            return;
        }
        if ( self::site_looks_configured() ) {
            update_option( self::OPTION_STATUS, self::STATUS_COMPLETED, false );
            return;
        }
        update_option( self::OPTION_STATUS, self::STATUS_PENDING, false );
        update_option( self::OPTION_STEP, 'welcome', false );
    }

    /**
     * For upgrades that skip activation: don't force wizard on established sites.
     *
     * @return void
     */
    public static function maybe_bootstrap_status() {
        if ( false !== get_option( self::OPTION_STATUS, false ) ) {
            return;
        }
        if ( self::site_looks_configured() ) {
            update_option( self::OPTION_STATUS, self::STATUS_COMPLETED, false );
        }
    }

    /**
     * Whether the install already has meaningful setup.
     *
     * @return bool
     */
    private static function site_looks_configured() {
        if ( get_option( 'seobooster_access_token' ) ) {
            return true;
        }
        if ( get_option( 'seobooster_selected_site' ) ) {
            return true;
        }
        if ( 'on' === get_option( 'seobooster_weekly_email', '' ) ) {
            return true;
        }
        if ( 'on' === get_option( 'seobooster_internal_linking', '' ) ) {
            return true;
        }
        return false;
    }

    /**
     * Hidden admin page.
     *
     * Registered under options.php so the page stays out of the sidebar menu
     * while WordPress can still resolve a non-null admin page title (avoids
     * strip_tags( null ) deprecations on PHP 8.1+).
     *
     * @return void
     */
    public static function register_page() {
        add_submenu_page(
            'options.php',
            __( 'SEO Booster Setup', 'seo-booster' ),
            __( 'SEO Booster Setup', 'seo-booster' ),
            'manage_options',
            'sb2_setup',
            array(__CLASS__, 'render')
        );
    }

    /**
     * Wizard URL, optionally with step.
     *
     * @param string $step Step id.
     * @return string
     */
    public static function get_wizard_url( $step = '' ) {
        $args = array(
            'page' => 'sb2_setup',
        );
        if ( $step !== '' ) {
            $args['step'] = sanitize_key( $step );
        }
        return admin_url( 'admin.php?' . http_build_query( $args ) );
    }

    /**
     * @return string
     */
    public static function get_status() {
        $status = get_option( self::OPTION_STATUS, '' );
        if ( !in_array( $status, array(self::STATUS_PENDING, self::STATUS_COMPLETED, self::STATUS_DISMISSED), true ) ) {
            return '';
        }
        return $status;
    }

    /**
     * @return string
     */
    public static function get_current_step() {
        $step = get_option( self::OPTION_STEP, 'welcome' );
        if ( !in_array( $step, self::get_step_ids(), true ) ) {
            return 'welcome';
        }
        return $step;
    }

    /**
     * Body class for immersive chrome.
     *
     * @param string $classes Classes.
     * @return string
     */
    public static function admin_body_class( $classes ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = ( isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '' );
        if ( 'sb2_setup' === $page ) {
            $classes .= ' sb-setup-wizard';
        }
        return $classes;
    }

    /**
     * Enqueue wizard assets.
     *
     * @param string $hook Hook suffix.
     * @return void
     */
    public static function enqueue_assets( $hook ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = ( isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '' );
        if ( 'sb2_setup' !== $page && 'admin_page_sb2_setup' !== $hook ) {
            return;
        }
        Utils::enqueue_modal_assets();
        $ui_css_path = SEOBOOSTER_PLUGINPATH . 'css/sb-ui.css';
        $setup_css_path = SEOBOOSTER_PLUGINPATH . 'css/sb-setup.css';
        $js_path = SEOBOOSTER_PLUGINPATH . 'js/sb-setup.js';
        wp_enqueue_style(
            'sb-ui',
            SEOBOOSTER_PLUGINURL . 'css/sb-ui.css',
            array(),
            ( file_exists( $ui_css_path ) ? (string) filemtime( $ui_css_path ) : '7.4.0' )
        );
        wp_enqueue_style(
            'sb-setup',
            SEOBOOSTER_PLUGINURL . 'css/sb-setup.css',
            array('sb-ui'),
            ( file_exists( $setup_css_path ) ? (string) filemtime( $setup_css_path ) : '7.4.0' )
        );
        wp_enqueue_script(
            'sb-setup',
            SEOBOOSTER_PLUGINURL . 'js/sb-setup.js',
            array('jquery', 'sb-modal'),
            ( file_exists( $js_path ) ? (string) filemtime( $js_path ) : '7.4.0' ),
            true
        );
        wp_localize_script( 'sb-setup', 'sbSetup', self::get_js_data() );
    }

    /**
     * Data for sb-setup.js.
     *
     * @return array
     */
    public static function get_js_data() {
        $current_user = wp_get_current_user();
        return array(
            'ajaxurl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'sb_setup_nonce' ),
            'oauth_nonce'    => wp_create_nonce( 'seobooster_oauth_prepare' ),
            'gsc_nonce'      => wp_create_nonce( 'sb_gsc_nonce' ),
            'issues_nonce'   => wp_create_nonce( 'sb_seo_issues_nonce' ),
            'site_nonce'     => wp_create_nonce( 'seobooster_save_selected_site' ),
            'steps'          => self::get_step_ids(),
            'state'          => self::build_state(),
            'connectors_url' => admin_url( 'options-connectors.php' ),
            'dashboard_url'  => admin_url( 'admin.php?page=sb2_dashboard&sb_setup_stay=1' ),
            'issues_url'     => admin_url( 'admin.php?page=sb2_seo_issues' ),
            'autolink_url'   => admin_url( 'admin.php?page=sb2_autolink' ),
            'tools_url'      => Tools_Page::get_page_url(),
            'tools_meta'     => Tools_Page::get_page_url( 'meta' ),
            'tools_image'    => Tools_Page::get_page_url( 'image' ),
            'tools_llms'     => Tools_Page::get_page_url( 'llms' ),
            'tools_focus'    => Tools_Page::get_page_url( 'focus-keyword' ),
            'tools_decay'    => Tools_Page::get_page_url( 'content-decay' ),
            'tools_opps'     => Tools_Page::get_page_url( 'gsc-opportunities' ),
            'user_email'     => $current_user->user_email,
            'plugin_url'     => SEOBOOSTER_PLUGINURL,
            'strings'        => array(
                'continue'           => __( 'Continue', 'seo-booster' ),
                'skip'               => __( 'Skip this step', 'seo-booster' ),
                'pleaseWait'         => __( 'Please wait…', 'seo-booster' ),
                'importing'          => __( 'Importing…', 'seo-booster' ),
                'scanning'           => __( 'Scanning…', 'seo-booster' ),
                'importComplete'     => __( 'Import complete', 'seo-booster' ),
                'importBackground'   => __( 'Import keeps running while you continue. You don’t need to wait here.', 'seo-booster' ),
                'importDoneHint'     => __( 'Keyword import finished. You can continue.', 'seo-booster' ),
                'connectError'       => __( 'Could not start Google authentication. Please try again.', 'seo-booster' ),
                'dismissing'         => __( 'Leaving setup…', 'seo-booster' ),
                'selectSite'         => __( 'Please select a site', 'seo-booster' ),
                'errorGeneric'       => __( 'Something went wrong. Please try again.', 'seo-booster' ),
                'emailSaved'         => __( 'Weekly email enabled', 'seo-booster' ),
                'autolinkEnabled'    => __( 'Automatic links enabled', 'seo-booster' ),
                'aiEnabled'          => __( 'AI suggestions enabled', 'seo-booster' ),
                'scanBackground'     => __( 'This keeps going in the background. You can continue.', 'seo-booster' ),
                'allSet'             => __( 'All set', 'seo-booster' ),
                'openTool'           => __( 'Open tool', 'seo-booster' ),
                'review'             => __( 'Review', 'seo-booster' ),
                'possibilitiesTitle' => __( 'SEO possibilities to review', 'seo-booster' ),
                'llmsLive'           => __( 'Live', 'seo-booster' ),
                'llmsOff'            => __( 'Not enabled yet', 'seo-booster' ),
                'uniqueKeywords'     => __( 'Unique keywords', 'seo-booster' ),
                'totalEntries'       => __( 'Entries processed', 'seo-booster' ),
                'pagesAnalyzed'      => __( 'Pages analyzed', 'seo-booster' ),
                'pagesRemaining'     => __( 'Remaining', 'seo-booster' ),
                'skipSetup'          => __( 'Exit setup', 'seo-booster' ),
                'back'               => __( 'Back', 'seo-booster' ),
                'close'              => __( 'Close', 'seo-booster' ),
            ),
        );
    }

    /**
     * Render wizard page.
     *
     * @return void
     */
    public static function render() {
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'seo-booster' ) );
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $requested = ( isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '' );
        if ( $requested && in_array( $requested, self::get_step_ids(), true ) ) {
            update_option( self::OPTION_STEP, $requested, false );
        }
        if ( self::STATUS_PENDING !== self::get_status() && self::STATUS_DISMISSED !== self::get_status() ) {
            // Viewing after complete is fine (soft restart keeps completed until the user advances).
            if ( self::STATUS_COMPLETED === self::get_status() ) {
                // no-op
            }
        }
        $state = self::build_state();
        include SEOBOOSTER_PLUGINPATH . 'views/setup-wizard.php';
    }

    /**
     * Full state payload for UI.
     *
     * @return array
     */
    public static function build_state() {
        $access_token = (string) get_option( 'seobooster_access_token', '' );
        $selected_site = (string) get_option( 'seobooster_selected_site', '' );
        $google_email = (string) get_option( 'seobooster_google_email', '' );
        $has_gsc_data = Tools_GSC_Helper::has_gsc_data();
        $sites = self::get_gsc_site_urls();
        $publishable = SEO_Issues_Manager::get_publishable_urls();
        $stats = SEO_Issues_Manager::get_analysis_stats();
        $seo_label = SEO_Plugin_Registry::get_active_label();
        $ai_variant = self::get_ai_variant();
        $completions = array(
            'welcome'   => true,
            'gsc'       => $access_token !== '' && $selected_site !== '' && $has_gsc_data,
            'email'     => 'on' === get_option( 'seobooster_weekly_email', '' ),
            'autolink'  => 'on' === get_option( 'seobooster_internal_linking', '' ),
            'scan'      => !empty( $stats['total_analyzed'] ) && (int) $stats['total_analyzed'] > 0,
            'ai'        => 'WordPress' === LLM_Helper::get_selected_ai_provider() && LLM_Helper::wp_ai_is_available(),
            'health'    => false,
            'workplace' => false,
            'ready'     => self::STATUS_COMPLETED === self::get_status(),
        );
        $is_local = false;
        foreach ( array(
            '.local',
            '.test',
            '.dev',
            '.localhost',
            'localhost',
            '127.0.0.1'
        ) as $local_domain ) {
            if ( false !== strpos( site_url(), $local_domain ) ) {
                $is_local = true;
                break;
            }
        }
        $gsc_phase = 'connect';
        if ( $access_token !== '' && $selected_site === '' ) {
            $gsc_phase = 'site';
        } elseif ( $access_token !== '' && $selected_site !== '' && !$has_gsc_data ) {
            $gsc_phase = 'import';
        } elseif ( $has_gsc_data ) {
            $gsc_phase = 'done';
        }
        $recent_post = get_posts( array(
            'numberposts' => 1,
            'post_status' => 'publish',
            'post_type'   => array('post', 'page'),
        ) );
        $edit_url = ( !empty( $recent_post[0] ) ? get_edit_post_link( $recent_post[0]->ID, 'raw' ) : admin_url( 'edit.php' ) );
        $has_premium = false;
        if ( function_exists( __NAMESPACE__ . '\\seobooster_fs' ) ) {
            $fs = seobooster_fs();
            if ( $fs && is_object( $fs ) && method_exists( $fs, 'can_use_premium_code' ) && $fs->can_use_premium_code() ) {
                $has_premium = true;
            }
        }
        return array(
            'status'            => self::get_status(),
            'step'              => self::get_current_step(),
            'completions'       => $completions,
            'seo_plugin_label'  => ( $seo_label ? $seo_label : '' ),
            'google_email'      => $google_email,
            'access_token'      => $access_token !== '',
            'selected_site'     => $selected_site,
            'sites'             => $sites,
            'has_gsc_data'      => $has_gsc_data,
            'gsc_phase'         => $gsc_phase,
            'is_local'          => $is_local,
            'weekly_email'      => 'on' === get_option( 'seobooster_weekly_email', '' ),
            'weekly_recipient'  => (string) get_option( 'seobooster_weekly_email_recipient', '' ),
            'autolink_on'       => 'on' === get_option( 'seobooster_internal_linking', '' ),
            'publishable_count' => ( is_array( $publishable ) ? count( $publishable ) : 0 ),
            'analysis_stats'    => $stats,
            'ai_variant'        => $ai_variant,
            'ai_provider'       => LLM_Helper::get_selected_ai_provider(),
            'has_premium'       => $has_premium,
            'edit_post_url'     => ( $edit_url ? $edit_url : admin_url( 'edit.php' ) ),
            'bot_tracking_on'   => 'off' !== get_option( 'seobooster_ai_bot_tracking', 'on' ),
            'ai_bots_url'       => admin_url( 'admin.php?page=sb2_ai_bots' ),
            'destinations'      => self::get_destinations( $completions, $has_gsc_data ),
        );
    }

    /**
     * AI step variant.
     *
     * @return string ready|needs_connector|preview
     */
    public static function get_ai_variant() {
        if ( LLM_Helper::wp_ai_is_available() ) {
            return 'ready';
        }
        if ( LLM_Helper::wp_ai_environment_ready() ) {
            return 'needs_connector';
        }
        return 'preview';
    }

    /**
     * Destination buttons for ready step.
     *
     * @param array $completions  Completion map.
     * @param bool  $has_gsc_data Whether GSC keyword data exists.
     * @return array<int, array{label: string, url: string, primary?: bool}>
     */
    private static function get_destinations( array $completions, $has_gsc_data = false ) {
        $dest = array(array(
            'label'   => __( 'Open Dashboard', 'seo-booster' ),
            'url'     => admin_url( 'admin.php?page=sb2_dashboard&sb_setup_stay=1' ),
            'primary' => true,
        ), array(
            'label' => ( $has_gsc_data ? __( 'GSC Overview', 'seo-booster' ) : __( 'Dive into keyword data', 'seo-booster' ) ),
            'url'   => admin_url( 'admin.php?page=sb2_gsc' ),
        ));
        if ( !empty( $completions['scan'] ) || !empty( $completions['gsc'] ) || $has_gsc_data ) {
            $dest[] = array(
                'label' => __( 'View SEO Possibilities', 'seo-booster' ),
                'url'   => admin_url( 'admin.php?page=sb2_seo_issues' ),
            );
        }
        return array_slice( $dest, 0, 4 );
    }

    /**
     * Capability check for AJAX.
     *
     * @return void
     */
    private static function assert_ajax() {
        check_ajax_referer( 'sb_setup_nonce', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied', 'seo-booster' ),
            ) );
        }
    }

    /**
     * @return void
     */
    public static function ajax_get_state() {
        self::assert_ajax();
        wp_send_json_success( self::build_state() );
    }

    /**
     * Persist step / dismiss / complete.
     *
     * @return void
     */
    public static function ajax_set_step() {
        self::assert_ajax();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in Setup_Wizard::assert_ajax() before this runs.
        $action_type = ( isset( $_POST['setup_action'] ) ? sanitize_key( wp_unslash( $_POST['setup_action'] ) ) : 'step' );
        $step = ( isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '' );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if ( 'dismiss' === $action_type ) {
            $status = self::resolve_status_on_exit();
            wp_send_json_success( array(
                'status'       => $status,
                'redirect_url' => admin_url( 'admin.php?page=sb2_dashboard&sb_setup_stay=1' ),
            ) );
        }
        if ( 'complete' === $action_type ) {
            update_option( self::OPTION_STATUS, self::STATUS_COMPLETED, false );
            update_option( self::OPTION_COMPLETED_AT, time(), false );
            update_option( self::OPTION_STEP, 'ready', false );
            wp_send_json_success( array(
                'status' => self::STATUS_COMPLETED,
                'state'  => self::build_state(),
            ) );
        }
        if ( $step && in_array( $step, self::get_step_ids(), true ) ) {
            update_option( self::OPTION_STEP, $step, false );
            $status = self::get_status();
            // Advancing through the wizard marks setup in progress (including a review of a completed install).
            if ( '' === $status || self::STATUS_DISMISSED === $status || self::STATUS_COMPLETED === $status ) {
                update_option( self::OPTION_STATUS, self::STATUS_PENDING, false );
            }
            wp_send_json_success( array(
                'step'  => $step,
                'state' => self::build_state(),
            ) );
        }
        wp_send_json_error( array(
            'message' => __( 'Invalid step', 'seo-booster' ),
        ) );
    }

    /**
     * Enable weekly email.
     *
     * @return void
     */
    public static function ajax_save_email() {
        self::assert_ajax();
        $email_recipient = ( isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '' );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in Setup_Wizard::assert_ajax() before this runs.
        if ( $email_recipient === '' ) {
            $current = wp_get_current_user();
            $email_recipient = $current->user_email;
        }
        $email_addresses = array_map( 'trim', explode( ',', $email_recipient ) );
        $valid_emails = array_filter( $email_addresses, 'is_email' );
        if ( empty( $valid_emails ) ) {
            wp_send_json_error( array(
                'message' => __( 'No valid email addresses provided', 'seo-booster' ),
            ) );
        }
        $email_recipient = implode( ',', $valid_emails );
        update_option( 'seobooster_weekly_email', 'on', true );
        update_option( 'seobooster_weekly_email_recipient', $email_recipient, true );
        wp_send_json_success( array(
            'message' => __( 'Weekly email enabled', 'seo-booster' ),
            'state'   => self::build_state(),
        ) );
    }

    /**
     * Enable autolink and optionally create rules from suggestions.
     *
     * @return void
     */
    public static function ajax_enable_autolink() {
        self::assert_ajax();
        update_option( 'seobooster_internal_linking', 'on', true );
        $created = 0;
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in Setup_Wizard::assert_ajax() before this runs.
        $suggestions = ( isset( $_POST['suggestions'] ) && is_array( $_POST['suggestions'] ) ? wp_unslash( $_POST['suggestions'] ) : array() );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        global $wpdb;
        $table = $wpdb->prefix . 'sb2_autolink';
        foreach ( $suggestions as $row ) {
            if ( !is_array( $row ) ) {
                continue;
            }
            $query_id = ( isset( $row['query_id'] ) ? absint( $row['query_id'] ) : 0 );
            $post_id = ( isset( $row['post_id'] ) ? absint( $row['post_id'] ) : 0 );
            if ( $query_id <= 0 || $post_id <= 0 ) {
                continue;
            }
            $keyword = $wpdb->get_var( $wpdb->prepare( "SELECT query FROM {$wpdb->prefix}sb2_query_keywords WHERE id = %d", $query_id ) );
            $page_url = get_permalink( $post_id );
            if ( !$keyword || !$page_url ) {
                continue;
            }
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefixed table; value uses %s placeholder.
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE keyword = %s LIMIT 1", $keyword ) );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ( $exists ) {
                continue;
            }
            $inserted = $wpdb->insert( $table, array(
                'keyword' => $keyword,
                'url'     => $page_url,
            ), array('%s', '%s') );
            if ( $inserted ) {
                ++$created;
            }
        }
        wp_send_json_success( array(
            'created' => $created,
            'state'   => self::build_state(),
        ) );
    }

    /**
     * Top GSC keyword → post suggestions for autolink step.
     *
     * @return void
     */
    public static function ajax_get_autolink_suggestions() {
        self::assert_ajax();
        wp_send_json_success( array(
            'suggestions' => self::get_autolink_suggestions( 5 ),
        ) );
    }

    /**
     * Build autolink suggestions from GSC keywords.
     *
     * @param int $limit Max suggestions.
     * @return array<int, array>
     */
    public static function get_autolink_suggestions( $limit = 5 ) {
        if ( !Tools_GSC_Helper::has_gsc_data() ) {
            return array();
        }
        global $wpdb;
        $limit = max( 1, min( 10, (int) $limit ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT qk.id AS query_id, qk.query, qk.page, SUM(qkh.clicks) AS clicks\n\t\t\t\tFROM {$wpdb->prefix}sb2_query_keywords qk\n\t\t\t\tLEFT JOIN {$wpdb->prefix}sb2_query_keywords_history qkh ON qk.id = qkh.query_keywords_id\n\t\t\t\tWHERE qk.page IS NOT NULL AND qk.page != ''\n\t\t\t\tGROUP BY qk.id\n\t\t\t\tORDER BY clicks DESC\n\t\t\t\tLIMIT %d", $limit * 4 ), ARRAY_A );
        if ( !is_array( $rows ) ) {
            return array();
        }
        $out = array();
        $seen_kw = array();
        $autolink = $wpdb->prefix . 'sb2_autolink';
        foreach ( $rows as $row ) {
            $query = ( isset( $row['query'] ) ? (string) $row['query'] : '' );
            $page = ( isset( $row['page'] ) ? (string) $row['page'] : '' );
            if ( $query === '' || $page === '' ) {
                continue;
            }
            $key = strtolower( $query );
            if ( isset( $seen_kw[$key] ) ) {
                continue;
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefixed table; value uses %s placeholder.
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$autolink} WHERE keyword = %s LIMIT 1", $query ) );
            if ( $exists ) {
                continue;
            }
            $post_id = (int) url_to_postid( $page );
            if ( $post_id <= 0 ) {
                continue;
            }
            $post = get_post( $post_id );
            if ( !$post || 'publish' !== $post->post_status ) {
                continue;
            }
            $target = Tools_GSC_Helper::describe_autolink_target( $post_id, $page );
            $seen_kw[$key] = true;
            $out[] = array(
                'query_id' => (int) $row['query_id'],
                'keyword'  => $query,
                'post_id'  => $post_id,
                'title'    => $target['title'],
                'slug'     => $target['slug'],
                'path'     => $target['path'],
                'url'      => get_permalink( $post_id ),
                'clicks'   => (int) ($row['clicks'] ?? 0),
            );
            if ( count( $out ) >= $limit ) {
                break;
            }
        }
        return $out;
    }

    /**
     * Save selected GSC site (before import).
     *
     * @return void
     */
    public static function ajax_save_selected_site() {
        self::assert_ajax();
        $raw = ( isset( $_POST['site_url'] ) ? wp_unslash( $_POST['site_url'] ) : '' );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in Setup_Wizard::assert_ajax() before this runs.
        // Domain properties use sc-domain:host — esc_url_raw() would strip them.
        $site = ( is_string( $raw ) && 0 === strpos( $raw, 'sc-domain:' ) ? sanitize_text_field( $raw ) : esc_url_raw( $raw ) );
        if ( $site === '' ) {
            wp_send_json_error( array(
                'message' => __( 'Please select a site', 'seo-booster' ),
            ) );
        }
        $allowed = self::get_gsc_site_urls();
        if ( !in_array( $site, $allowed, true ) ) {
            wp_send_json_error( array(
                'message' => __( 'Please select a site', 'seo-booster' ),
            ) );
        }
        update_option( 'seobooster_selected_site', $site, false );
        wp_send_json_success( array(
            'state' => self::build_state(),
        ) );
    }

    /**
     * GSC property URLs for the wizard select (strings only).
     *
     * `seobooster_gsc_sites` stores `{ siteUrl, permissionLevel }` entries from Google_API::fetch_sites().
     *
     * @return string[]
     */
    public static function get_gsc_site_urls() {
        $sites = get_option( 'seobooster_gsc_sites', array() );
        if ( !is_array( $sites ) ) {
            return array();
        }
        $urls = array();
        foreach ( $sites as $site ) {
            if ( is_array( $site ) ) {
                $url = ( isset( $site['siteUrl'] ) ? (string) $site['siteUrl'] : '' );
                $permission = ( isset( $site['permissionLevel'] ) ? (string) $site['permissionLevel'] : '' );
                if ( 'siteUnverifiedUser' === $permission ) {
                    continue;
                }
            } else {
                $url = (string) $site;
            }
            $url = trim( $url );
            if ( $url === '' ) {
                continue;
            }
            $urls[] = $url;
        }
        $urls = array_values( array_unique( $urls ) );
        usort( $urls, static function ( $a, $b ) {
            $a_clean = str_replace( 'sc-domain:', '', $a );
            $b_clean = str_replace( 'sc-domain:', '', $b );
            return strcasecmp( $a_clean, $b_clean );
        } );
        return $urls;
    }

    /**
     * Health peek counts.
     *
     * @return void
     */
    /**
     * Health peek counts for setup wizard and dashboard Do next.
     *
     * @return array{counts: array, pro: array}
     */
    public static function get_health_counts_payload() {
        $llms = Tools_Llms_Txt::get_settings();
        $llms_on = !empty( $llms['enabled'] );
        $analysis_stats = SEO_Issues_Manager::get_analysis_stats();
        $seo_possibilities = ( isset( $analysis_stats['total_issues'] ) ? (int) $analysis_stats['total_issues'] : 0 );
        $counts = array(
            'seo_possibilities'     => $seo_possibilities,
            'missing_focus_keyword' => Tools_Meta_Scanner::count_missing_focus_keyword(),
            'missing_meta'          => Tools_Meta_Scanner::count_missing_title_or_description(),
            'empty_alt'             => Tools_Image_Scanner::count_empty_alt(),
            'llms_enabled'          => $llms_on,
        );
        $pro = array();
        return array(
            'counts' => $counts,
            'pro'    => $pro,
        );
    }

    /**
     * AJAX: health peek counts.
     *
     * @return void
     */
    public static function ajax_health_counts() {
        self::assert_ajax();
        wp_send_json_success( self::get_health_counts_payload() );
    }

    /**
     * Enable WordPress AI provider when available.
     *
     * @return void
     */
    public static function ajax_enable_ai() {
        self::assert_ajax();
        if ( !LLM_Helper::wp_ai_is_available() ) {
            wp_send_json_error( array(
                'message' => __( 'AI is not ready yet.', 'seo-booster' ),
            ) );
        }
        update_option( 'seobooster_ai_provider', 'WordPress', true );
        wp_send_json_success( array(
            'state' => self::build_state(),
        ) );
    }

    /**
     * Save AI bot tracking preference from the AI step.
     *
     * @return void
     */
    public static function ajax_save_bot_tracking() {
        self::assert_ajax();
        $enabled = isset( $_POST['enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enabled'] ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in Setup_Wizard::assert_ajax() before this runs.
        update_option( 'seobooster_ai_bot_tracking', ( $enabled ? 'on' : 'off' ), true );
        wp_send_json_success( array(
            'enabled' => $enabled,
            'state'   => self::build_state(),
        ) );
    }

    /**
     * Nonced URL to restart the setup wizard.
     *
     * @return string
     */
    public static function get_restart_url() {
        return wp_nonce_url( admin_url( 'admin-post.php?action=sb_setup_restart' ), 'sb_setup_restart' );
    }

    /**
     * Restart wizard from Settings / Dashboard / Tools (admin-post).
     *
     * Opens welcome without flipping status — exiting with no progress must not
     * re-open the dashboard setup CTA on an already-configured site.
     *
     * @return void
     */
    public static function handle_restart() {
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'seo-booster' ) );
        }
        check_admin_referer( 'sb_setup_restart' );
        update_option( self::OPTION_STEP, 'welcome', false );
        wp_safe_redirect( self::get_wizard_url( 'welcome' ) );
        exit;
    }

    /**
     * Mark setup as pending from Clear All / activate (hard reset).
     *
     * @return void
     */
    public static function reset_to_pending() {
        update_option( self::OPTION_STATUS, self::STATUS_PENDING, false );
        update_option( self::OPTION_STEP, 'welcome', false );
        delete_option( self::OPTION_COMPLETED_AT );
    }

    /**
     * Exit setup: keep completed when the site is already configured; otherwise dismiss.
     *
     * @return string New status (completed|dismissed).
     */
    private static function resolve_status_on_exit() {
        if ( self::STATUS_COMPLETED === self::get_status() || self::site_looks_configured() ) {
            update_option( self::OPTION_STATUS, self::STATUS_COMPLETED, false );
            if ( !get_option( self::OPTION_COMPLETED_AT ) ) {
                update_option( self::OPTION_COMPLETED_AT, time(), false );
            }
            return self::STATUS_COMPLETED;
        }
        update_option( self::OPTION_STATUS, self::STATUS_DISMISSED, false );
        return self::STATUS_DISMISSED;
    }

    /**
     * Heal idle “Run setup wizard” restarts that left status pending on welcome
     * without any progress (legacy hard-restart behavior).
     *
     * @return void
     */
    private static function maybe_heal_idle_restart() {
        if ( self::STATUS_PENDING !== self::get_status() ) {
            return;
        }
        if ( !self::site_looks_configured() ) {
            return;
        }
        $step = (string) get_option( self::OPTION_STEP, 'welcome' );
        if ( 'welcome' !== $step ) {
            return;
        }
        update_option( self::OPTION_STATUS, self::STATUS_COMPLETED, false );
        if ( !get_option( self::OPTION_COMPLETED_AT ) ) {
            update_option( self::OPTION_COMPLETED_AT, time(), false );
        }
    }

    /**
     * Whether Dashboard should show the primary setup / GSC call-to-action.
     *
     * Shown when setup is pending/dismissed, or GSC is not connected.
     *
     * @param string $access_token Current GSC access token (empty if disconnected).
     * @return bool
     */
    public static function should_show_dashboard_cta( $access_token = '' ) {
        if ( !current_user_can( 'manage_options' ) ) {
            return false;
        }
        self::maybe_heal_idle_restart();
        $status = self::get_status();
        if ( in_array( $status, array(self::STATUS_PENDING, self::STATUS_DISMISSED), true ) ) {
            return true;
        }
        return '' === (string) $access_token;
    }

    /**
     * Quiet Dashboard chip when dismissed (legacy helper).
     *
     * @return bool
     */
    public static function should_show_dashboard_chip() {
        return self::STATUS_DISMISSED === self::get_status() && current_user_can( 'manage_options' );
    }

}
