<?php

namespace Cleverplugins\SEOBooster;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Form processing utilities for settings pages
 *
 * This class handles all form processing logic for the settings pages,
 * including validation, sanitization, and saving of form data.
 */
class Form_Processor {
    /**
     * Process all settings form submissions
     *
     * @return void
     */
    public static function process_settings_form() {
        // Check if this is a settings form submission
        if ( !isset( $_POST['page'] ) || 'sb2_settings' !== sanitize_text_field( wp_unslash( $_POST['page'] ) ) ) {
            return;
        }
        // Verify nonce
        if ( !isset( $_REQUEST['_wpnonce'] ) ) {
            return;
        }
        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
        if ( !wp_verify_nonce( $nonce, 'seobooster_save_settings' ) ) {
            die( 'Security check failed.' );
        }
        // Handle current tab preservation
        if ( isset( $_POST['current_tab'] ) ) {
            $current_tab = sanitize_text_field( wp_unslash( $_POST['current_tab'] ) );
        }
        // Handle special action buttons first
        self::process_action_buttons();
        // Process all form fields
        self::process_basic_settings();
        self::process_seo_settings();
        self::process_social_media_settings();
        self::process_schema_settings();
        self::process_organization_settings();
        self::process_weekly_email_settings();
        self::process_cache_settings();
        self::process_content_type_settings();
        self::process_ai_settings();
        self::process_seo_possibilities_settings();
    }

    /**
     * Process action buttons (database update, etc.)
     *
     * @return void
     */
    private static function process_action_buttons() {
        // Handle database update button
        if ( isset( $_POST['submit_dbupdates'] ) ) {
            if ( !current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'seo-booster' ) );
            }
            // Create/update database tables
            Utils::create_database_tables();
            self::add_settings_notice( __( 'Database tables updated successfully!', 'seo-booster' ) );
        }
        // Handle restart keyword scanning button
        if ( isset( $_POST['schedule_all_pages'] ) ) {
            self::handle_restart_keyword_scanning();
        }
        // Handle reset log button
        if ( isset( $_POST['submit_reset_log'] ) ) {
            self::handle_reset_seo_booster_log();
        }
        // Handle clear all data and options button
        if ( isset( $_POST['submit_allempty'] ) ) {
            self::handle_clear_all_data_and_options();
        }
        // Handle send status email button
        if ( isset( $_POST['submit_send_email'] ) ) {
            if ( !current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'seo-booster' ) );
            }
            // Send the actual weekly email update (forced)
            require_once SEOBOOSTER_PLUGINPATH . 'inc/email_status.php';
            email_status::send_email_update( 7, true );
            // Add success notice
            add_action( 'admin_notices', function () {
                echo '<div class="notice notice-success is-dismissible"><p>';
                esc_html_e( 'Weekly email sent successfully!', 'seo-booster' );
                echo '</p></div>';
            } );
        }
        // Handle reset keyword data button
        if ( isset( $_POST['submit_dbempty'] ) ) {
            if ( !current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'seo-booster' ) );
            }
            global $wpdb;
            // Clear keyword data tables - validate table names against whitelist
            $allowed_tables = array($wpdb->prefix . 'sb2_query_keywords', $wpdb->prefix . 'sb2_query_keywords_history');
            $tables = array($wpdb->prefix . 'sb2_query_keywords', $wpdb->prefix . 'sb2_query_keywords_history');
            foreach ( $tables as $table ) {
                // Only truncate if table is in whitelist
                if ( in_array( $table, $allowed_tables, true ) ) {
                    // Use esc_sql for table name since prepare doesn't support table names
                    $table_name_escaped = esc_sql( $table );
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Whitelisted table name escaped with esc_sql(); TRUNCATE has no placeholders.
                    $wpdb->query( "TRUNCATE TABLE `{$table_name_escaped}`" );
                }
            }
            // Add success notice
            add_action( 'admin_notices', function () {
                echo '<div class="notice notice-success is-dismissible"><p>';
                esc_html_e( 'Keyword data cleared successfully!', 'seo-booster' );
                echo '</p></div>';
            } );
        }
    }

    /**
     * Restart GSC keyword scanning for all imported pages.
     *
     * @return void
     */
    private static function handle_restart_keyword_scanning() {
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'seo-booster' ) );
        }
        if ( function_exists( 'as_unschedule_all_actions' ) ) {
            $group = Utils::get_action_scheduler_group_slug();
            as_unschedule_all_actions( 'sb_gsc_process_url_keywords', array(), $group );
            as_unschedule_all_actions( 'sb_gsc_process_url_keywords' );
            as_unschedule_all_actions( 'sb_gsc_process_keywords_batch', array(), $group );
            as_unschedule_all_actions( 'sb_gsc_process_keywords_batch' );
        }
        if ( function_exists( 'as_schedule_single_action' ) ) {
            as_schedule_single_action( time(), 'sb_gsc_schedule_all_pages' );
        } else {
            wp_clear_scheduled_hook( 'sb_gsc_schedule_all_pages' );
            wp_schedule_single_event( time(), 'sb_gsc_schedule_all_pages' );
        }
        self::add_settings_notice( __( 'Keyword scanning has been restarted. A full rescan of imported pages has been queued.', 'seo-booster' ) );
    }

    /**
     * Clear SEO Booster internal debug log table (sb2_log).
     *
     * @return void
     */
    private static function handle_reset_seo_booster_log() {
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'seo-booster' ) );
        }
        $tables = Utils::get_plugin_table_names();
        $log_table = ( isset( $tables['sb2_log'] ) ? $tables['sb2_log'] : '' );
        if ( $log_table && self::truncate_plugin_table( $log_table ) ) {
            self::add_settings_notice( __( 'SEO Booster Debug Log cleared successfully.', 'seo-booster' ) );
            return;
        }
        self::add_settings_notice( __( 'SEO Booster Debug Log could not be cleared. The log table may be missing.', 'seo-booster' ), 'error' );
    }

    /**
     * Destructive wipe of plugin data and GSC options while preserving autolink rules.
     *
     * @return void
     */
    private static function handle_clear_all_data_and_options() {
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'seo-booster' ) );
        }
        $tables_truncated = 0;
        $preserve_slugs = array('sb2_autolink');
        foreach ( Utils::get_plugin_table_names() as $slug => $table_name ) {
            if ( in_array( $slug, $preserve_slugs, true ) ) {
                continue;
            }
            if ( self::truncate_plugin_table( $table_name ) ) {
                ++$tables_truncated;
            }
        }
        Google_API::reset_authentication();
        delete_option( 'sb_gsc_last_refreshed' );
        $cleared_cache_files = self::clear_seo_booster_cache_files();
        self::cancel_all_plugin_cron_events();
        self::cancel_all_plugin_action_scheduler_jobs();
        self::add_settings_notice( sprintf( 
            /* translators: 1: number of database tables truncated, 2: number of cache files removed */
            __( 'Clear complete: %1$d database tables reset, GSC authentication removed, %2$d cache files deleted, and background jobs cancelled. Automatic link rules were preserved.', 'seo-booster' ),
            $tables_truncated,
            $cleared_cache_files
         ) );
    }

    /**
     * Truncate a whitelisted plugin table.
     *
     * @param string $table_name Full table name including prefix.
     * @return bool
     */
    private static function truncate_plugin_table( $table_name ) {
        global $wpdb;
        $allowed_tables = array_values( Utils::get_plugin_table_names() );
        if ( !in_array( $table_name, $allowed_tables, true ) || !Utils::plugin_table_exists( $table_name ) ) {
            return false;
        }
        $table_name_escaped = esc_sql( $table_name );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Whitelisted table name escaped with esc_sql(); TRUNCATE has no placeholders.
        return false !== $wpdb->query( "TRUNCATE TABLE `{$table_name_escaped}`" );
    }

    /**
     * Delete SEO Booster page-analysis cache files.
     *
     * @return int Number of cache files removed.
     */
    private static function clear_seo_booster_cache_files() {
        $cache_dir = WP_CONTENT_DIR . '/cache/seo-booster/';
        $cleared_count = 0;
        if ( !is_dir( $cache_dir ) ) {
            return 0;
        }
        $files = glob( $cache_dir . '*.txt' );
        if ( !is_array( $files ) ) {
            return 0;
        }
        foreach ( $files as $file ) {
            if ( is_file( $file ) && unlink( $file ) ) {
                ++$cleared_count;
            }
        }
        $index_file = $cache_dir . 'index.php';
        if ( !file_exists( $index_file ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Creating protective index file in cache directory.
            file_put_contents( $index_file, '<?php // Silence is golden' );
        }
        return $cleared_count;
    }

    /**
     * Cancel all SEO Booster WP-Cron events.
     *
     * @return void
     */
    private static function cancel_all_plugin_cron_events() {
        $cron_jobs = _get_cron_array();
        if ( empty( $cron_jobs ) || !is_array( $cron_jobs ) ) {
            return;
        }
        foreach ( $cron_jobs as $timestamp => $cron ) {
            foreach ( $cron as $hook => $events ) {
                if ( !Utils::is_plugin_cron_hook( $hook ) ) {
                    continue;
                }
                foreach ( $events as $event ) {
                    $args = ( isset( $event['args'] ) ? $event['args'] : array() );
                    wp_unschedule_event( $timestamp, $hook, $args );
                }
            }
        }
    }

    /**
     * Cancel pending SEO Booster Action Scheduler jobs.
     *
     * @return void
     */
    private static function cancel_all_plugin_action_scheduler_jobs() {
        if ( !function_exists( 'as_unschedule_all_actions' ) ) {
            return;
        }
        $group = Utils::get_action_scheduler_group_slug();
        $hooks = array(
            'sb_gsc_process_url_keywords',
            'sb_gsc_process_keywords_batch',
            'sb_gsc_schedule_all_pages',
            'sb_gsc_analyze_post_keywords',
            'sb_gsc_schedule_traffic_pages',
            'sb_gsc_inspect_url',
            'sb_seo_possibilities_auto_scan',
            'sb_analyze_seo_for_url'
        );
        foreach ( $hooks as $hook ) {
            as_unschedule_all_actions( $hook, array(), $group );
            as_unschedule_all_actions( $hook );
        }
    }

    /**
     * Queue an admin notice on the settings screen.
     *
     * @param string $message Notice message.
     * @param string $type    notice-success|notice-error|notice-warning.
     * @return void
     */
    private static function add_settings_notice( $message, $type = 'success' ) {
        $allowed_types = array(
            'success',
            'error',
            'warning',
            'info'
        );
        if ( !in_array( $type, $allowed_types, true ) ) {
            $type = 'success';
        }
        add_action( 'admin_notices', function () use($message, $type) {
            printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $message ) );
        } );
    }

    /**
     * Process basic settings
     *
     * @return void
     */
    private static function process_basic_settings() {
        // Internal linking settings
        if ( isset( $_POST['seobooster_internal_linking'] ) ) {
            update_option( 'seobooster_internal_linking', sanitize_text_field( wp_unslash( $_POST['seobooster_internal_linking'] ) ) );
        } else {
            delete_option( 'seobooster_internal_linking' );
        }
        if ( isset( $_POST['seobooster_replace_kw_multiple'] ) ) {
            update_option( 'seobooster_replace_kw_multiple', sanitize_text_field( wp_unslash( $_POST['seobooster_replace_kw_multiple'] ) ) );
        } else {
            delete_option( 'seobooster_replace_kw_multiple' );
        }
        if ( isset( $_POST['seobooster_match_capitalization'] ) ) {
            update_option( 'seobooster_match_capitalization', sanitize_text_field( wp_unslash( $_POST['seobooster_match_capitalization'] ) ) );
        } else {
            delete_option( 'seobooster_match_capitalization' );
        }
        // Excluded elements settings
        if ( isset( $_POST['seobooster_excluded_elements'] ) && is_array( $_POST['seobooster_excluded_elements'] ) ) {
            $excluded_elements = array_map( 'sanitize_text_field', wp_unslash( $_POST['seobooster_excluded_elements'] ) );
            update_option( 'seobooster_excluded_elements', $excluded_elements );
        } else {
            // Ensure mandatory elements are always excluded
            update_option( 'seobooster_excluded_elements', array(
                'a'      => 1,
                'script' => 1,
                'style'  => 1,
            ) );
        }
        // Other basic settings
        if ( isset( $_POST['seobooster_replace_kw_limit'] ) ) {
            update_option( 'seobooster_replace_kw_limit', intval( $_POST['seobooster_replace_kw_limit'] ) );
        }
        if ( isset( $_POST['seobooster_delete_deactivate'] ) ) {
            update_option( 'seobooster_delete_deactivate', sanitize_key( $_POST['seobooster_delete_deactivate'] ) );
        } else {
            delete_option( 'seobooster_delete_deactivate' );
        }
        if ( isset( $_POST['seobooster_ignorelist'] ) ) {
            update_option( 'seobooster_ignorelist', sanitize_text_field( wp_unslash( $_POST['seobooster_ignorelist'] ) ) );
        }
        if ( isset( $_POST['seobooster_ai_bot_tracking'] ) ) {
            update_option( 'seobooster_ai_bot_tracking', 'on' );
        } else {
            update_option( 'seobooster_ai_bot_tracking', 'off' );
        }
        if ( isset( $_POST['seobooster_ai_referral_tracking'] ) ) {
            update_option( 'seobooster_ai_referral_tracking', 'on' );
        } else {
            update_option( 'seobooster_ai_referral_tracking', 'off' );
        }
        if ( isset( $_POST['seobooster_ai_bot_track_mapped_only'] ) ) {
            update_option( 'seobooster_ai_bot_track_mapped_only', 'on' );
        } else {
            update_option( 'seobooster_ai_bot_track_mapped_only', 'off' );
        }
        if ( isset( $_POST['seobooster_ai_bot_retention_days'] ) ) {
            $retention_days = (int) $_POST['seobooster_ai_bot_retention_days'];
            if ( $retention_days < 7 ) {
                $retention_days = 7;
            }
            if ( $retention_days > 365 ) {
                $retention_days = 365;
            }
            update_option( 'seobooster_ai_bot_retention_days', $retention_days );
        }
    }

    /**
     * Process SEO settings
     *
     * @return void
     */
    private static function process_seo_settings() {
        // Site basics
        if ( isset( $_POST['seobooster_site_name'] ) ) {
            update_option( 'seobooster_site_name', sanitize_text_field( wp_unslash( $_POST['seobooster_site_name'] ) ) );
        }
        if ( isset( $_POST['seobooster_site_tagline'] ) ) {
            update_option( 'seobooster_site_tagline', sanitize_text_field( wp_unslash( $_POST['seobooster_site_tagline'] ) ) );
        }
        if ( isset( $_POST['seobooster_alternate_site_name'] ) ) {
            update_option( 'seobooster_alternate_site_name', sanitize_text_field( wp_unslash( $_POST['seobooster_alternate_site_name'] ) ) );
        }
        if ( isset( $_POST['seobooster_global_title_separator'] ) ) {
            update_option( 'seobooster_global_title_separator', sanitize_text_field( wp_unslash( $_POST['seobooster_global_title_separator'] ) ) );
        }
        if ( isset( $_POST['seobooster_site_image'] ) ) {
            update_option( 'seobooster_site_image', sanitize_text_field( wp_unslash( $_POST['seobooster_site_image'] ) ) );
        }
        // Global SEO templates
        if ( isset( $_POST['seobooster_global_title_template'] ) ) {
            update_option( 'seobooster_global_title_template', sanitize_text_field( wp_unslash( $_POST['seobooster_global_title_template'] ) ) );
        }
        if ( isset( $_POST['seobooster_global_description_template'] ) ) {
            update_option( 'seobooster_global_description_template', sanitize_textarea_field( wp_unslash( $_POST['seobooster_global_description_template'] ) ) );
        }
        // SEO visibility settings
        if ( isset( $_POST['seobooster_seo_default_noindex_post_types'] ) && is_array( $_POST['seobooster_seo_default_noindex_post_types'] ) ) {
            update_option( 'seobooster_seo_default_noindex_post_types', array_map( 'sanitize_text_field', $_POST['seobooster_seo_default_noindex_post_types'] ) );
        } else {
            delete_option( 'seobooster_seo_default_noindex_post_types' );
        }
        if ( isset( $_POST['seobooster_seo_default_noindex_taxonomies'] ) && is_array( $_POST['seobooster_seo_default_noindex_taxonomies'] ) ) {
            update_option( 'seobooster_seo_default_noindex_taxonomies', array_map( 'sanitize_text_field', $_POST['seobooster_seo_default_noindex_taxonomies'] ) );
        } else {
            delete_option( 'seobooster_seo_default_noindex_taxonomies' );
        }
    }

    /**
     * Process social media settings
     *
     * @return void
     */
    private static function process_social_media_settings() {
        if ( isset( $_POST['seobooster_global_og_image'] ) ) {
            update_option( 'seobooster_global_og_image', esc_url_raw( wp_unslash( $_POST['seobooster_global_og_image'] ) ) );
        }
        if ( isset( $_POST['seobooster_global_og_type'] ) ) {
            update_option( 'seobooster_global_og_type', sanitize_text_field( wp_unslash( $_POST['seobooster_global_og_type'] ) ) );
        }
        if ( isset( $_POST['seobooster_twitter_card_type'] ) ) {
            update_option( 'seobooster_twitter_card_type', sanitize_text_field( wp_unslash( $_POST['seobooster_twitter_card_type'] ) ) );
        }
        if ( isset( $_POST['seobooster_twitter_username'] ) ) {
            update_option( 'seobooster_twitter_username', sanitize_text_field( wp_unslash( $_POST['seobooster_twitter_username'] ) ) );
        }
    }

    /**
     * Process schema settings
     *
     * @return void
     */
    private static function process_schema_settings() {
        if ( isset( $_POST['seobooster_schema_enabled'] ) ) {
            update_option( 'seobooster_schema_enabled', sanitize_text_field( wp_unslash( $_POST['seobooster_schema_enabled'] ) ) );
        } else {
            delete_option( 'seobooster_schema_enabled' );
        }
        if ( isset( $_POST['seobooster_site_representation'] ) ) {
            update_option( 'seobooster_site_representation', sanitize_text_field( wp_unslash( $_POST['seobooster_site_representation'] ) ) );
        }
        // Site policies
        if ( isset( $_POST['seobooster_publishing_principles'] ) ) {
            update_option( 'seobooster_publishing_principles', intval( $_POST['seobooster_publishing_principles'] ) );
        }
        if ( isset( $_POST['seobooster_ownership_funding'] ) ) {
            update_option( 'seobooster_ownership_funding', intval( $_POST['seobooster_ownership_funding'] ) );
        }
        if ( isset( $_POST['seobooster_feedback_policy'] ) ) {
            update_option( 'seobooster_feedback_policy', intval( $_POST['seobooster_feedback_policy'] ) );
        }
        if ( isset( $_POST['seobooster_corrections_policy'] ) ) {
            update_option( 'seobooster_corrections_policy', intval( $_POST['seobooster_corrections_policy'] ) );
        }
        if ( isset( $_POST['seobooster_ethics_policy'] ) ) {
            update_option( 'seobooster_ethics_policy', intval( $_POST['seobooster_ethics_policy'] ) );
        }
        if ( isset( $_POST['seobooster_diversity_policy'] ) ) {
            update_option( 'seobooster_diversity_policy', intval( $_POST['seobooster_diversity_policy'] ) );
        }
        if ( isset( $_POST['seobooster_diversity_staffing'] ) ) {
            update_option( 'seobooster_diversity_staffing', intval( $_POST['seobooster_diversity_staffing'] ) );
        }
    }

    /**
     * Process organization settings
     *
     * @return void
     */
    private static function process_organization_settings() {
        if ( isset( $_POST['seobooster_organization_name'] ) ) {
            update_option( 'seobooster_organization_name', sanitize_text_field( wp_unslash( $_POST['seobooster_organization_name'] ) ) );
        }
        if ( isset( $_POST['seobooster_organization_alternate_name'] ) ) {
            update_option( 'seobooster_organization_alternate_name', sanitize_text_field( wp_unslash( $_POST['seobooster_organization_alternate_name'] ) ) );
        }
        if ( isset( $_POST['seobooster_organization_logo'] ) ) {
            update_option( 'seobooster_organization_logo', esc_url_raw( wp_unslash( $_POST['seobooster_organization_logo'] ) ) );
        }
        if ( isset( $_POST['seobooster_organization_description'] ) ) {
            update_option( 'seobooster_organization_description', sanitize_textarea_field( wp_unslash( $_POST['seobooster_organization_description'] ) ) );
        }
        if ( isset( $_POST['seobooster_organization_url'] ) ) {
            update_option( 'seobooster_organization_url', esc_url_raw( wp_unslash( $_POST['seobooster_organization_url'] ) ) );
        }
        if ( isset( $_POST['seobooster_organization_email'] ) ) {
            update_option( 'seobooster_organization_email', sanitize_email( wp_unslash( $_POST['seobooster_organization_email'] ) ) );
        }
        if ( isset( $_POST['seobooster_organization_phone'] ) ) {
            update_option( 'seobooster_organization_phone', sanitize_text_field( wp_unslash( $_POST['seobooster_organization_phone'] ) ) );
        }
        if ( isset( $_POST['seobooster_organization_legal_name'] ) ) {
            update_option( 'seobooster_organization_legal_name', sanitize_text_field( wp_unslash( $_POST['seobooster_organization_legal_name'] ) ) );
        }
        if ( isset( $_POST['seobooster_organization_founding_date'] ) ) {
            update_option( 'seobooster_organization_founding_date', sanitize_text_field( wp_unslash( $_POST['seobooster_organization_founding_date'] ) ) );
        }
    }

    /**
     * Process weekly email settings
     *
     * @return void
     */
    private static function process_weekly_email_settings() {
        if ( isset( $_POST['seobooster_weekly_email'] ) ) {
            update_option( 'seobooster_weekly_email', sanitize_text_field( wp_unslash( $_POST['seobooster_weekly_email'] ) ) );
        } else {
            delete_option( 'seobooster_weekly_email' );
        }
        if ( isset( $_POST['seobooster_weekly_email_recipient'] ) ) {
            update_option( 'seobooster_weekly_email_recipient', sanitize_text_field( wp_unslash( $_POST['seobooster_weekly_email_recipient'] ) ) );
        } else {
            delete_option( 'seobooster_weekly_email_recipient' );
        }
    }

    /**
     * Process cache settings
     *
     * @return void
     */
    private static function process_cache_settings() {
        // Handle cache clearing
        if ( isset( $_POST['clear_seo_cache'] ) ) {
            $cache_dir = WP_CONTENT_DIR . '/cache/seo-booster/';
            $cleared_count = 0;
            $cleared_size = 0;
            $errors = array();
            if ( is_dir( $cache_dir ) ) {
                // Only get .txt files (cache files)
                $files = glob( $cache_dir . '*.txt' );
                foreach ( $files as $file ) {
                    if ( is_file( $file ) ) {
                        $file_size = filesize( $file );
                        $cleared_size += $file_size;
                        if ( unlink( $file ) ) {
                            ++$cleared_count;
                        } else {
                            $errors[] = basename( $file );
                        }
                    }
                }
            } else {
                $errors[] = 'Cache directory does not exist: ' . $cache_dir;
            }
            // Store results for display
            if ( $cleared_count > 0 ) {
                add_action( 'admin_notices', function () use($cleared_count, $cleared_size) {
                    echo '<div class="notice notice-success is-dismissible"><p>';
                    printf( esc_html__( 'Cache cleared successfully! Removed %1$d files (%2$s).', 'seo-booster' ), $cleared_count, size_format( $cleared_size ) );
                    echo '</p></div>';
                } );
            }
            if ( !empty( $errors ) ) {
                add_action( 'admin_notices', function () use($errors) {
                    echo '<div class="notice notice-error is-dismissible"><p>';
                    esc_html_e( 'Some files could not be deleted:', 'seo-booster' );
                    echo ' ' . esc_html( implode( ', ', $errors ) );
                    echo '</p></div>';
                } );
            }
            // Recreate index.php file to prevent directory browsing
            $index_file = $cache_dir . 'index.php';
            if ( !file_exists( $index_file ) ) {
                file_put_contents( $index_file, '<?php // Silence is golden' );
            }
        }
    }

    /**
     * Process content type settings
     *
     * @return void
     */
    private static function process_content_type_settings() {
        // Process post type templates
        $post_types = get_post_types( array(
            'public' => true,
        ), 'objects' );
        foreach ( $post_types as $post_type ) {
            $type = $post_type->name;
            if ( isset( $_POST['seobooster_ct_' . $type . '_title_template'] ) ) {
                update_option( 'seobooster_ct_' . $type . '_title_template', sanitize_text_field( wp_unslash( $_POST['seobooster_ct_' . $type . '_title_template'] ) ) );
            }
            if ( isset( $_POST['seobooster_ct_' . $type . '_description_template'] ) ) {
                update_option( 'seobooster_ct_' . $type . '_description_template', sanitize_textarea_field( wp_unslash( $_POST['seobooster_ct_' . $type . '_description_template'] ) ) );
            }
            // Process schema settings
            if ( isset( $_POST['seobooster_ct_' . $type . '_schema_page_type'] ) ) {
                update_option( 'seobooster_ct_' . $type . '_schema_page_type', sanitize_text_field( wp_unslash( $_POST['seobooster_ct_' . $type . '_schema_page_type'] ) ) );
            }
            if ( isset( $_POST['seobooster_ct_' . $type . '_schema_article_type'] ) ) {
                update_option( 'seobooster_ct_' . $type . '_schema_article_type', sanitize_text_field( wp_unslash( $_POST['seobooster_ct_' . $type . '_schema_article_type'] ) ) );
            }
        }
        // Process taxonomy templates
        $taxonomies = get_taxonomies( array(
            'public' => true,
        ), 'objects' );
        foreach ( $taxonomies as $taxonomy ) {
            $type = $taxonomy->name;
            if ( isset( $_POST['seobooster_tax_' . $type . '_title_template'] ) ) {
                update_option( 'seobooster_tax_' . $type . '_title_template', sanitize_text_field( wp_unslash( $_POST['seobooster_tax_' . $type . '_title_template'] ) ) );
            }
            if ( isset( $_POST['seobooster_tax_' . $type . '_description_template'] ) ) {
                update_option( 'seobooster_tax_' . $type . '_description_template', sanitize_textarea_field( wp_unslash( $_POST['seobooster_tax_' . $type . '_description_template'] ) ) );
            }
        }
    }

    /**
     * Process AI/LLM settings.
     *
     * @since 6.1.26
     * @return void
     */
    private static function process_ai_settings() {
        if ( isset( $_POST['seobooster_ai_provider'] ) ) {
            $ai_provider = sanitize_text_field( wp_unslash( $_POST['seobooster_ai_provider'] ) );
            $allowed_providers = array('disabled', 'wordpress');
            if ( \Cleverplugins\SEOBooster\Credits_Service::is_ai_provider_available() ) {
                $allowed_providers[] = 'seobooster';
            }
            if ( in_array( $ai_provider, $allowed_providers, true ) ) {
                update_option( 'seobooster_ai_provider', $ai_provider );
            }
        }
    }

    /**
     * Process SEO Possibilities settings.
     *
     * @since 6.1.26
     * @return void
     */
    private static function process_seo_possibilities_settings() {
        // Process enable/disable setting
        $old_enabled = get_option( 'seobooster_seo_possibilities_enabled', 'on' );
        if ( isset( $_POST['seobooster_seo_possibilities_enabled'] ) ) {
            update_option( 'seobooster_seo_possibilities_enabled', sanitize_text_field( wp_unslash( $_POST['seobooster_seo_possibilities_enabled'] ) ) );
        } else {
            delete_option( 'seobooster_seo_possibilities_enabled' );
        }
        $new_enabled = get_option( 'seobooster_seo_possibilities_enabled', 'on' );
        // Process frequency setting
        $old_frequency = get_option( 'seobooster_seo_possibilities_frequency', 60 );
        if ( isset( $_POST['seobooster_seo_possibilities_frequency'] ) ) {
            $frequency = intval( $_POST['seobooster_seo_possibilities_frequency'] );
            // Ensure valid frequency values
            $valid_frequencies = array(
                60,
                300,
                900,
                1800,
                3600
            );
            if ( in_array( $frequency, $valid_frequencies, true ) ) {
                update_option( 'seobooster_seo_possibilities_frequency', $frequency );
            }
        }
        $new_frequency = get_option( 'seobooster_seo_possibilities_frequency', 60 );
        // Process batch size setting
        if ( isset( $_POST['seobooster_seo_analysis_batch_size'] ) ) {
            $batch_size = intval( $_POST['seobooster_seo_analysis_batch_size'] );
            // Ensure value is between 1-5
            $batch_size = max( 1, min( 5, $batch_size ) );
            update_option( 'seobooster_seo_analysis_batch_size', $batch_size );
        }
        // Handle Action Scheduler recurring action scheduling/unscheduling
        if ( !function_exists( 'as_unschedule_all_actions' ) || !function_exists( 'as_schedule_recurring_action' ) ) {
            return;
        }
        // Cancel existing WordPress cron if it exists
        $wp_cron_timestamp = wp_next_scheduled( 'seobooster_seo_analysis_processing' );
        if ( $wp_cron_timestamp ) {
            wp_unschedule_event( $wp_cron_timestamp, 'seobooster_seo_analysis_processing' );
        }
        // Cancel existing Action Scheduler recurring action
        as_unschedule_all_actions( 'sb_seo_possibilities_auto_scan', array(), 'seo-booster' );
        // Schedule new Action Scheduler recurring action if enabled
        if ( $new_enabled === 'on' ) {
            as_schedule_recurring_action(
                time(),
                $new_frequency,
                'sb_seo_possibilities_auto_scan',
                array(),
                'seo-booster'
            );
        }
    }

}
