<?php

namespace Cleverplugins\SEOBooster;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class email_status {
    public static function init() {
    }

    /**
     * send_email_update.
     *
     * @author  Unknown
     * @author  Lars Koudal
     * @since   v0.0.1
     * @version v1.0.0  Tuesday, November 30th, 2021.
     * @version v1.0.1  Wednesday, August 14th, 2024.
     * @access  public static
     * @param   integer $days   Default: 7
     * @param   boolean $forced Default: false
     * @return  void
     */
    public static function send_email_update( $days = 7, $forced = false ) {
        $seobooster_weekly_email = get_option( 'seobooster_weekly_email' );
        if ( 'on' !== $seobooster_weekly_email && !$forced ) {
            return;
        }
        $seobooster_weekly_email_recipient = get_option( 'seobooster_weekly_email_recipient' );
        if ( strpos( $seobooster_weekly_email_recipient, ',' ) !== false ) {
            $email_recipients = array_map( 'trim', explode( ',', $seobooster_weekly_email_recipient ) );
        } else {
            $email_recipients = array(trim( $seobooster_weekly_email_recipient ));
        }
        if ( !is_int( $days ) ) {
            $days = 7;
        }
        if ( empty( $email_recipients ) ) {
            return;
        }
        global $wpdb;
        $query_keywords_table = $wpdb->prefix . 'sb2_query_keywords';
        $query_keywords_history_table = $wpdb->prefix . 'sb2_query_keywords_history';
        $content = '';
        $intro_summary = '';
        $latest_date = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(date) \n                FROM {$wpdb->prefix}sb2_query_keywords_history \n                WHERE %s = %s", '1', '1' ) );
        if ( $latest_date ) {
            $past_7_days_data = $wpdb->get_results( $wpdb->prepare( "SELECT \n                        SUM(h.impressions) AS total_impressions, \n                        SUM(h.clicks) AS total_clicks, \n                        AVG(h.position) AS avg_position, \n                        AVG(h.ctr) AS avg_ctr\n                    FROM \n                        {$wpdb->prefix}sb2_query_keywords_history AS h\n                    WHERE \n                        h.date BETWEEN DATE_SUB(%s, INTERVAL 7 DAY) AND %s", $latest_date, $latest_date ), OBJECT );
            $previous_7_days_data = $wpdb->get_results( $wpdb->prepare( "SELECT \n                        SUM(h.impressions) AS total_impressions, \n                        SUM(h.clicks) AS total_clicks, \n                        AVG(h.position) AS avg_position, \n                        AVG(h.ctr) AS avg_ctr\n                    FROM \n                        {$wpdb->prefix}sb2_query_keywords_history AS h\n                    WHERE \n                        h.date BETWEEN DATE_SUB(%s, INTERVAL 14 DAY) AND DATE_SUB(%s, INTERVAL 7 DAY)", $latest_date, $latest_date ), OBJECT );
        }
        // Initialize default values
        $impressions_change = 0;
        $clicks_change = 0;
        $position_change = 0;
        $ctr_change = 0;
        $impressions_percentage = 0;
        $clicks_percentage = 0;
        $position_percentage = 0;
        $ctr_percentage = 0;
        $past_7_days = null;
        $previous_7_days = null;
        if ( $past_7_days_data && $previous_7_days_data && is_array( $past_7_days_data ) && !empty( $past_7_days_data ) && is_array( $previous_7_days_data ) && !empty( $previous_7_days_data ) ) {
            $past_7_days = $past_7_days_data[0];
            $previous_7_days = $previous_7_days_data[0];
            if ( $past_7_days && $previous_7_days ) {
                $impressions_change = (float) $past_7_days->total_impressions - (float) $previous_7_days->total_impressions;
                $clicks_change = (float) $past_7_days->total_clicks - (float) $previous_7_days->total_clicks;
                $position_change = (float) $past_7_days->avg_position - (float) $previous_7_days->avg_position;
                $ctr_change = ((float) $past_7_days->avg_ctr - (float) $previous_7_days->avg_ctr) * 100;
                // Convert to percentage
                // Calculate percentage changes
                $impressions_percentage = ( (float) $previous_7_days->total_impressions > 0 ? round( $impressions_change / (float) $previous_7_days->total_impressions * 100, 1 ) : 0 );
                $clicks_percentage = ( (float) $previous_7_days->total_clicks > 0 ? round( $clicks_change / (float) $previous_7_days->total_clicks * 100, 1 ) : 0 );
                $position_percentage = ( (float) $previous_7_days->avg_position > 0 ? round( $position_change / (float) $previous_7_days->avg_position * 100, 1 ) : 0 );
                $ctr_percentage = ( (float) $previous_7_days->avg_ctr > 0 ? round( $ctr_change / ((float) $previous_7_days->avg_ctr * 100) * 100, 1 ) : 0 );
            }
        }
        // 1. New Keywords in the Past 7 Days
        $cache_key = 'new_keywords_' . $days;
        $new_keywords = wp_cache_get( $cache_key );
        if ( false === $new_keywords ) {
            $new_keywords = $wpdb->get_results( $wpdb->prepare( "\n            SELECT k.query, k.page, AVG(h.position) as avg_position, SUM(h.clicks) as total_clicks, SUM(h.impressions) as total_impressions\n            FROM {$query_keywords_table} k\n            JOIN {$query_keywords_history_table} h ON k.id = h.query_keywords_id\n            WHERE k.first_seen_date >= CURDATE() - INTERVAL %d DAY\n            GROUP BY k.page, k.query\n            ORDER BY k.first_seen_date DESC\n            LIMIT 10", $days ), ARRAY_A );
            wp_cache_set(
                $cache_key,
                $new_keywords,
                '',
                3600
            );
            // Cache for 1 hour
        }
        // Correct count of new keywords in the past 7 days
        $cache_key = 'total_new_keywords_' . $days;
        $total_new_keywords = wp_cache_get( $cache_key );
        if ( false === $total_new_keywords ) {
            $total_new_keywords = $wpdb->get_var( $wpdb->prepare( "\n            SELECT COUNT(DISTINCT k.query) as total_new_keywords\n            FROM {$query_keywords_table} k\n            WHERE k.first_seen_date >= CURDATE() - INTERVAL %d DAY", $days ) );
            wp_cache_set(
                $cache_key,
                $total_new_keywords,
                '',
                3600
            );
            // Cache for 1 hour
        }
        $total_new_keywords = ( (int) $total_new_keywords ?: 0 );
        // Total new keywords in the past 30 days
        $total_new_keywords_30_days = $wpdb->get_var( $wpdb->prepare( "\n        SELECT COUNT(DISTINCT k.query) as total_new_keywords\n        FROM {$query_keywords_table} k\n        WHERE k.first_seen_date >= CURDATE() - INTERVAL %d DAY", 30 ) );
        $total_new_keywords_30_days = ( (int) $total_new_keywords_30_days ?: 0 );
        // 2. Trend Analysis (Impressions and Clicks) over the Last 14 Days
        $cache_key = 'trends_analysis_14_days';
        $trends = wp_cache_get( $cache_key );
        if ( false === $trends ) {
            $trends = $wpdb->get_results( $wpdb->prepare( "\n            SELECT \n                k.query, \n                k.page, \n                SUM(CASE WHEN h.date >= CURDATE() - INTERVAL 7 DAY THEN h.impressions ELSE 0 END) as current_week_impressions,\n                SUM(CASE WHEN h.date >= CURDATE() - INTERVAL 7 DAY THEN h.clicks ELSE 0 END) as current_week_clicks,\n                SUM(CASE WHEN h.date >= CURDATE() - INTERVAL 14 DAY AND h.date < CURDATE() - INTERVAL 7 DAY THEN h.impressions ELSE 0 END) as previous_week_impressions,\n                SUM(CASE WHEN h.date >= CURDATE() - INTERVAL 14 DAY AND h.date < CURDATE() - INTERVAL 7 DAY THEN h.clicks ELSE 0 END) as previous_week_clicks\n            FROM {$query_keywords_table} k\n            JOIN {$query_keywords_history_table} h ON k.id = h.query_keywords_id\n            WHERE h.date >= CURDATE() - INTERVAL 14 DAY\n            GROUP BY k.query, k.page\n            ORDER BY current_week_impressions DESC\n            LIMIT %d", 25 ), ARRAY_A );
            wp_cache_set(
                $cache_key,
                $trends,
                '',
                3600
            );
            // Cache for 1 hour
        }
        $current_week_impressions = array_sum( array_column( $trends, 'current_week_impressions' ) );
        $previous_week_impressions = array_sum( array_column( $trends, 'previous_week_impressions' ) );
        // 3. Keyword Cannibalization Detection (only pages competing in last 30 days)
        $cannibalized_keywords = $wpdb->get_results( $wpdb->prepare( "\n        SELECT \n            k.query, \n            k.page, \n            COUNT(DISTINCT k.page) as page_count,\n            SUM(h.impressions) as total_impressions, \n            SUM(h.clicks) as total_clicks, \n            AVG(h.position) as avg_position\n        FROM {$query_keywords_table} k\n        JOIN {$query_keywords_history_table} h ON k.id = h.query_keywords_id\n        WHERE h.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)\n        GROUP BY k.query\n        HAVING page_count > 1\n        ORDER BY total_impressions DESC\n        LIMIT %d", 10 ), ARRAY_A );
        // 4. Refined Ranking Anomalies Detection
        $anomalies = $wpdb->get_results( $wpdb->prepare( "\n        SELECT \n            k.query, \n            k.page, \n            MIN(h.position) as min_position, \n            MAX(h.position) as max_position, \n            MAX(h.date) as max_position_date, \n            (SELECT h2.position \n             FROM {$query_keywords_history_table} h2 \n             WHERE h2.query_keywords_id = h.query_keywords_id \n             ORDER BY h2.date DESC LIMIT 1) as current_position,\n            SUM(h.impressions) as total_impressions, \n            SUM(h.clicks) as total_clicks\n        FROM {$query_keywords_history_table} h\n        JOIN {$query_keywords_table} k ON k.id = h.query_keywords_id\n        WHERE h.date >= CURDATE() - INTERVAL 30 DAY\n        GROUP BY k.query, k.page\n        HAVING (MAX(h.position) - MIN(h.position)) > 10 AND total_impressions > 0\n        ORDER BY total_impressions DESC\n        LIMIT %d", 10 ), ARRAY_A );
        // 5. SEO Possibilities Data
        $top_possibilities = array();
        $possibilities_stats = array(
            'total_issues' => 0,
            'critical'     => 0,
            'error'        => 0,
            'high'         => 0,
            'warning'      => 0,
            'medium'       => 0,
            'low'          => 0,
        );
        if ( class_exists( '\\Cleverplugins\\SEOBooster\\SEO_Issues_Manager' ) ) {
            $top_possibilities = SEO_Issues_Manager::get_top_possibilities_for_dashboard( 5 );
            $possibilities_stats = SEO_Issues_Manager::get_analysis_stats();
            // Ensure we have valid arrays
            if ( !is_array( $top_possibilities ) ) {
                $top_possibilities = array();
            }
            if ( !is_array( $possibilities_stats ) ) {
                $possibilities_stats = array(
                    'total_issues' => 0,
                    'critical'     => 0,
                    'error'        => 0,
                    'high'         => 0,
                    'warning'      => 0,
                    'medium'       => 0,
                    'low'          => 0,
                );
            }
            // Ensure numeric values
            $possibilities_stats['total_issues'] = ( isset( $possibilities_stats['total_issues'] ) ? (int) $possibilities_stats['total_issues'] : 0 );
            $possibilities_stats['critical'] = ( isset( $possibilities_stats['critical'] ) ? (int) $possibilities_stats['critical'] : 0 );
            $possibilities_stats['error'] = ( isset( $possibilities_stats['error'] ) ? (int) $possibilities_stats['error'] : 0 );
            $possibilities_stats['high'] = ( isset( $possibilities_stats['high'] ) ? (int) $possibilities_stats['high'] : 0 );
            $possibilities_stats['warning'] = ( isset( $possibilities_stats['warning'] ) ? (int) $possibilities_stats['warning'] : 0 );
            $possibilities_stats['medium'] = ( isset( $possibilities_stats['medium'] ) ? (int) $possibilities_stats['medium'] : 0 );
            $possibilities_stats['low'] = ( isset( $possibilities_stats['low'] ) ? (int) $possibilities_stats['low'] : 0 );
        }
        // Executive Summary
        $intro_summary .= '<h2>' . __( 'Summary', 'seo-booster' ) . '</h2>';
        // Key metrics in bullet format
        if ( $past_7_days && $previous_7_days ) {
            $intro_summary .= '<ul>';
            $intro_summary .= '<li>' . sprintf( __( 'Impressions: %1$s (%2$s%%)', 'seo-booster' ), ( $impressions_change >= 0 ? '+' . number_format_i18n( $impressions_change ) : number_format_i18n( $impressions_change ) ), ( $impressions_percentage >= 0 ? '+' . number_format_i18n( $impressions_percentage ) : number_format_i18n( $impressions_percentage ) ) ) . '</li>';
            $intro_summary .= '<li>' . sprintf( __( 'Clicks: %1$s (%2$s%%)', 'seo-booster' ), ( $clicks_change >= 0 ? '+' . number_format_i18n( $clicks_change ) : number_format_i18n( $clicks_change ) ), ( $clicks_percentage >= 0 ? '+' . number_format_i18n( $clicks_percentage ) : number_format_i18n( $clicks_percentage ) ) ) . '</li>';
            $intro_summary .= '<li>' . sprintf( __( 'Avg Position: %1$s (%2$s%%)', 'seo-booster' ), ( $position_change <= 0 ? number_format_i18n( abs( $position_change ), 2 ) . ' ' . __( 'improved', 'seo-booster' ) : number_format_i18n( $position_change, 2 ) . ' ' . __( 'worsened', 'seo-booster' ) ), ( $position_percentage <= 0 ? number_format_i18n( abs( $position_percentage ) ) : number_format_i18n( $position_percentage ) ) ) . '</li>';
            $intro_summary .= '<li>' . sprintf( __( 'CTR: %1$s%% (%2$s%%)', 'seo-booster' ), ( $ctr_change >= 0 ? '+' . number_format_i18n( $ctr_change, 2 ) : number_format_i18n( $ctr_change, 2 ) ), ( $ctr_percentage >= 0 ? '+' . number_format_i18n( $ctr_percentage ) : number_format_i18n( $ctr_percentage ) ) ) . '</li>';
            $intro_summary .= '</ul>';
        }
        $intro_summary .= '<ul>';
        $intro_summary .= '<li>' . sprintf( __( '%s new keywords discovered in the past 7 days', 'seo-booster' ), '<strong>' . number_format_i18n( $total_new_keywords ) . '</strong>' ) . '</li>';
        if ( $possibilities_stats['total_issues'] > 0 ) {
            $severity_breakdown = array();
            if ( $possibilities_stats['critical'] > 0 ) {
                $severity_breakdown[] = number_format_i18n( $possibilities_stats['critical'] ) . ' ' . __( 'critical', 'seo-booster' );
            }
            if ( $possibilities_stats['error'] > 0 ) {
                $severity_breakdown[] = number_format_i18n( $possibilities_stats['error'] ) . ' ' . __( 'errors', 'seo-booster' );
            }
            if ( $possibilities_stats['high'] > 0 ) {
                $severity_breakdown[] = number_format_i18n( $possibilities_stats['high'] ) . ' ' . __( 'high', 'seo-booster' );
            }
            $severity_text = ( !empty( $severity_breakdown ) ? ' (' . implode( ', ', $severity_breakdown ) . ')' : '' );
            $intro_summary .= '<li>' . sprintf( __( '%1$s SEO possibilities found%2$s', 'seo-booster' ), '<strong>' . number_format_i18n( $possibilities_stats['total_issues'] ) . '</strong>', $severity_text ) . '</li>';
        }
        $intro_summary .= '</ul>';
        $intro_summary .= '<hr>';
        // Detailed Sections
        // SEO Possibilities Section
        if ( !empty( $top_possibilities ) && is_array( $top_possibilities ) && isset( $possibilities_stats['total_issues'] ) && $possibilities_stats['total_issues'] > 0 ) {
            $content .= '<h2>' . __( 'Top SEO Possibilities', 'seo-booster' ) . '</h2>';
            $content .= '<p>' . sprintf( __( 'Here are the top %d SEO possibilities to address:', 'seo-booster' ), min( 5, count( $top_possibilities ) ) ) . '</p>';
            $severity_labels = array(
                'critical' => __( 'Critical', 'seo-booster' ),
                'error'    => __( 'Error', 'seo-booster' ),
                'high'     => __( 'High', 'seo-booster' ),
                'warning'  => __( 'Warning', 'seo-booster' ),
                'medium'   => __( 'Medium', 'seo-booster' ),
                'low'      => __( 'Low', 'seo-booster' ),
            );
            foreach ( $top_possibilities as $possibility ) {
                if ( !is_array( $possibility ) ) {
                    continue;
                }
                $severity = ( isset( $possibility['severity'] ) ? $possibility['severity'] : 'medium' );
                $severity_label = ( isset( $severity_labels[$severity] ) ? $severity_labels[$severity] : ucfirst( $severity ) );
                $affected_urls = ( isset( $possibility['affected_urls'] ) ? (int) $possibility['affected_urls'] : 0 );
                $message = ( isset( $possibility['message'] ) && !empty( $possibility['message'] ) ? $possibility['message'] : __( 'SEO issue detected', 'seo-booster' ) );
                $content .= '<p><strong>' . esc_html( $severity_label ) . ':</strong> ' . esc_html( $message );
                if ( $affected_urls > 0 ) {
                    $content .= ' <small>(' . sprintf( _n(
                        '%d page affected',
                        '%d pages affected',
                        $affected_urls,
                        'seo-booster'
                    ), $affected_urls ) . ')</small>';
                }
                $content .= '</p>';
            }
            $content .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=sb2_seo_issues' ) ) . '">' . __( 'View All SEO Possibilities', 'seo-booster' ) . '</a></p>';
        }
        // New Keywords Section
        $content .= '<h2>' . __( 'New Keywords', 'seo-booster' ) . '</h2>';
        if ( !empty( $new_keywords ) && is_array( $new_keywords ) ) {
            $content .= '<p>' . __( 'Top keywords discovered in the past 7 days:', 'seo-booster' ) . '</p>';
            foreach ( array_slice( $new_keywords, 0, 3 ) as $keyword ) {
                if ( !isset( $keyword['query'] ) || !isset( $keyword['page'] ) ) {
                    continue;
                }
                $content .= '<p><strong>' . esc_html( $keyword['query'] ) . '</strong><br/>';
                $content .= "<a href='" . esc_url( $keyword['page'] ) . "' target='_blank'>" . esc_html( $keyword['page'] ) . '</a><br/>';
                $content .= '<small>';
                $avg_position = ( isset( $keyword['avg_position'] ) ? (float) $keyword['avg_position'] : 0 );
                $total_clicks = ( isset( $keyword['total_clicks'] ) ? (int) $keyword['total_clicks'] : 0 );
                $total_impressions = ( isset( $keyword['total_impressions'] ) ? (int) $keyword['total_impressions'] : 0 );
                $content .= __( 'Position:', 'seo-booster' ) . ' ' . number_format_i18n( floor( $avg_position ) ) . ' • ';
                $content .= __( 'Clicks:', 'seo-booster' ) . ' ' . number_format_i18n( $total_clicks ) . ' • ';
                $content .= __( 'Impressions:', 'seo-booster' ) . ' ' . number_format_i18n( $total_impressions ) . '</small></p>';
            }
            if ( $total_new_keywords > 3 ) {
                $content .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=sb2_dashboard' ) ) . '">' . sprintf( __( 'View all %s new keywords', 'seo-booster' ), number_format_i18n( $total_new_keywords ) ) . '</a></p>';
            }
        } else {
            $content .= '<p>' . __( 'No new keywords found in the past 7 days.', 'seo-booster' ) . '</p>';
        }
        // Final Email Assembly
        $dashboardlink = admin_url( '?page=sb2_dashboard' );
        $subjectline = sprintf( __( 'Your Weekly SEO Update - %1$s - %2$s', 'seo-booster' ), date_i18n( 'F j, Y' ), Utils::remove_http( site_url() ) );
        $emailtitle = __( 'SEO Update from SEO Booster', 'seo-booster' ) . ' - ' . Utils::remove_http( site_url() );
        $dashboardlinkanchor = __( 'SEO Booster Dashboard', 'seo-booster' );
        $emailintrotext = __( 'Here\'s your weekly SEO update with key insights and opportunities.', 'seo-booster' );
        $emailintrotext .= '<br><small>' . __( 'Data is based on Google Search Console and does not represent your full website traffic.', 'seo-booster' ) . '</small>';
        $my_replacements = array(
            '%%emailintrotext%%'      => $emailintrotext,
            '%%websitedomain%%'       => Utils::remove_http( site_url() ),
            '%%dashboardlink%%'       => $dashboardlink,
            '%%dashboardlinkanchor%%' => $dashboardlinkanchor,
            '%%emailtitle%%'          => $emailtitle,
            '%%emailcontent%%'        => nl2br( $intro_summary . $content ),
        );
        // Get WP_Filesystem instance
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        $template_path = SEOBOOSTER_PLUGINPATH . 'templates/email/report.html';
        if ( $wp_filesystem->exists( $template_path ) ) {
            $html = $wp_filesystem->get_contents( $template_path );
        } else {
            // Handle error - template file not found
            Utils::log( 'Email template file not found', 2 );
            return;
        }
        foreach ( $my_replacements as $needle => $replacement ) {
            $html = str_replace( $needle, $replacement, $html );
        }
        $headers = array('Content-Type: text/html; charset=UTF-8');
        if ( empty( $email_recipients ) ) {
            Utils::log( 'No valid email recipients found for status email', 2 );
            return;
        }
        foreach ( $email_recipients as $email ) {
            if ( !is_email( $email ) ) {
                Utils::log( sprintf( 
                    // translators: 1: Invalid email address
                    esc_html__( 'Invalid email address: %1$s', 'seo-booster' ),
                    esc_html( $email )
                 ), 2 );
                continue;
            }
            $sendresult = wp_mail(
                $email,
                $subjectline,
                $html,
                $headers
            );
            if ( $sendresult ) {
                Utils::log( sprintf( 
                    // translators: 1: Email address of the recipient
                    esc_html__( 'Status email was sent to %1$s', 'seo-booster' ),
                    esc_html( $email )
                 ), 10 );
            } else {
                Utils::log( sprintf( 
                    // translators: 1: Email address of the recipient
                    esc_html__( 'Status email was not sent to %1$s', 'seo-booster' ),
                    esc_html( $email )
                 ), 2 );
            }
        }
    }

}
