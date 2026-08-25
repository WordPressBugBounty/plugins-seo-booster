<?php

namespace Cleverplugins\SEOBooster;

use Cleverplugins\SEOBooster\Tools\Tools_GSC_Helper;
use Cleverplugins\SEOBooster\Tools\Tools_Page;
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class Email_Status {
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
        $clicks_change = 0;
        $impressions_change = 0;
        $clicks_percentage = 0;
        $impressions_percentage = 0;
        $past_7_days = null;
        $previous_7_days = null;
        $has_pulse = false;
        $latest_date = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(date)\n                FROM {$wpdb->prefix}sb2_query_keywords_history\n                WHERE %s = %s", '1', '1' ) );
        if ( $latest_date ) {
            $past_7_days_data = $wpdb->get_results( $wpdb->prepare( "SELECT\n                        SUM(h.impressions) AS total_impressions,\n                        SUM(h.clicks) AS total_clicks\n                    FROM\n                        {$wpdb->prefix}sb2_query_keywords_history AS h\n                    WHERE\n                        h.date BETWEEN DATE_SUB(%s, INTERVAL 7 DAY) AND %s", $latest_date, $latest_date ), OBJECT );
            $previous_7_days_data = $wpdb->get_results( $wpdb->prepare( "SELECT\n                        SUM(h.impressions) AS total_impressions,\n                        SUM(h.clicks) AS total_clicks\n                    FROM\n                        {$wpdb->prefix}sb2_query_keywords_history AS h\n                    WHERE\n                        h.date BETWEEN DATE_SUB(%s, INTERVAL 14 DAY) AND DATE_SUB(%s, INTERVAL 7 DAY)", $latest_date, $latest_date ), OBJECT );
            if ( $past_7_days_data && $previous_7_days_data && is_array( $past_7_days_data ) && !empty( $past_7_days_data ) && is_array( $previous_7_days_data ) && !empty( $previous_7_days_data ) ) {
                $past_7_days = $past_7_days_data[0];
                $previous_7_days = $previous_7_days_data[0];
                if ( $past_7_days && $previous_7_days ) {
                    $impressions_change = (float) $past_7_days->total_impressions - (float) $previous_7_days->total_impressions;
                    $clicks_change = (float) $past_7_days->total_clicks - (float) $previous_7_days->total_clicks;
                    $impressions_percentage = ( (float) $previous_7_days->total_impressions > 0 ? round( $impressions_change / (float) $previous_7_days->total_impressions * 100, 1 ) : 0 );
                    $clicks_percentage = ( (float) $previous_7_days->total_clicks > 0 ? round( $clicks_change / (float) $previous_7_days->total_clicks * 100, 1 ) : 0 );
                    $has_pulse = (float) $past_7_days->total_impressions > 0 || (float) $past_7_days->total_clicks > 0 || (float) $previous_7_days->total_impressions > 0 || (float) $previous_7_days->total_clicks > 0;
                }
            }
        }
        $cache_key = 'new_keywords_' . $days;
        $new_keywords = wp_cache_get( $cache_key );
        if ( false === $new_keywords ) {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
            $new_keywords = $wpdb->get_results( $wpdb->prepare( "\n            SELECT k.query, k.page, AVG(h.position) as avg_position, SUM(h.clicks) as total_clicks, SUM(h.impressions) as total_impressions\n            FROM {$query_keywords_table} k\n            JOIN {$query_keywords_history_table} h ON k.id = h.query_keywords_id\n            WHERE k.first_seen_date >= CURDATE() - INTERVAL %d DAY\n            GROUP BY k.page, k.query\n            ORDER BY k.first_seen_date DESC\n            LIMIT 10", $days ), ARRAY_A );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            wp_cache_set(
                $cache_key,
                $new_keywords,
                '',
                3600
            );
        }
        if ( !is_array( $new_keywords ) ) {
            $new_keywords = array();
        }
        $cache_key = 'total_new_keywords_' . $days;
        $total_new_keywords = wp_cache_get( $cache_key );
        if ( false === $total_new_keywords ) {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
            $total_new_keywords = $wpdb->get_var( $wpdb->prepare( "\n            SELECT COUNT(DISTINCT k.query) as total_new_keywords\n            FROM {$query_keywords_table} k\n            WHERE k.first_seen_date >= CURDATE() - INTERVAL %d DAY", $days ) );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            wp_cache_set(
                $cache_key,
                $total_new_keywords,
                '',
                3600
            );
        }
        $total_new_keywords = (int) $total_new_keywords;
        if ( !$total_new_keywords ) {
            $total_new_keywords = 0;
        }
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
            $top_possibilities = SEO_Issues_Manager::get_top_possibilities_for_dashboard( 3 );
            $possibilities_stats = SEO_Issues_Manager::get_analysis_stats();
            if ( !is_array( $top_possibilities ) ) {
                $top_possibilities = array();
            }
            if ( !is_array( $possibilities_stats ) ) {
                $possibilities_stats = array(
                    'total_issues' => 0,
                );
            }
            $possibilities_stats['total_issues'] = ( isset( $possibilities_stats['total_issues'] ) ? (int) $possibilities_stats['total_issues'] : 0 );
            $possibilities_stats['critical'] = ( isset( $possibilities_stats['critical'] ) ? (int) $possibilities_stats['critical'] : 0 );
            $possibilities_stats['error'] = ( isset( $possibilities_stats['error'] ) ? (int) $possibilities_stats['error'] : 0 );
            $possibilities_stats['high'] = ( isset( $possibilities_stats['high'] ) ? (int) $possibilities_stats['high'] : 0 );
        }
        $possibilities_count = (int) ($possibilities_stats['total_issues'] ?? 0);
        $has_possibilities = $possibilities_count > 0 && !empty( $top_possibilities );
        $has_new_keywords = !empty( $new_keywords );
        $decay_count = 0;
        $opps_count = 0;
        $has_pro = false;
        $has_anything = $has_pulse || $has_possibilities || $has_new_keywords || $has_pro;
        if ( !$has_anything && !$forced ) {
            return;
        }
        $intro_summary = '';
        $content = '';
        if ( $has_pulse ) {
            $intro_summary .= '<h2>' . esc_html__( 'Google Search this week', 'seo-booster' ) . '</h2>';
            $intro_summary .= '<ul>';
            $intro_summary .= '<li>' . sprintf( 
                /* translators: 1: signed click change, 2: signed percent change */
                __( 'Clicks from Google Search: %1$s (%2$s%%)', 'seo-booster' ),
                ( $clicks_change >= 0 ? '+' . number_format_i18n( $clicks_change ) : number_format_i18n( $clicks_change ) ),
                ( $clicks_percentage >= 0 ? '+' . number_format_i18n( $clicks_percentage ) : number_format_i18n( $clicks_percentage ) )
             ) . '</li>';
            $intro_summary .= '<li>' . sprintf( 
                /* translators: 1: signed impressions change, 2: signed percent change */
                __( 'Impressions in search results: %1$s (%2$s%%)', 'seo-booster' ),
                ( $impressions_change >= 0 ? '+' . number_format_i18n( $impressions_change ) : number_format_i18n( $impressions_change ) ),
                ( $impressions_percentage >= 0 ? '+' . number_format_i18n( $impressions_percentage ) : number_format_i18n( $impressions_percentage ) )
             ) . '</li>';
            if ( $total_new_keywords > 0 ) {
                $intro_summary .= '<li>' . sprintf( 
                    /* translators: %s: number of new keywords */
                    __( '%s new keywords discovered', 'seo-booster' ),
                    '<strong>' . number_format_i18n( $total_new_keywords ) . '</strong>'
                 ) . '</li>';
            }
            if ( $possibilities_count > 0 ) {
                $severity_breakdown = array();
                if ( !empty( $possibilities_stats['critical'] ) ) {
                    $severity_breakdown[] = number_format_i18n( $possibilities_stats['critical'] ) . ' ' . __( 'critical', 'seo-booster' );
                }
                if ( !empty( $possibilities_stats['error'] ) ) {
                    $severity_breakdown[] = number_format_i18n( $possibilities_stats['error'] ) . ' ' . __( 'errors', 'seo-booster' );
                }
                if ( !empty( $possibilities_stats['high'] ) ) {
                    $severity_breakdown[] = number_format_i18n( $possibilities_stats['high'] ) . ' ' . __( 'high', 'seo-booster' );
                }
                $severity_text = ( !empty( $severity_breakdown ) ? ' (' . implode( ', ', $severity_breakdown ) . ')' : '' );
                $intro_summary .= '<li>' . sprintf( 
                    /* translators: 1: total issues, 2: optional severity breakdown */
                    __( '%1$s SEO possibilities%2$s', 'seo-booster' ),
                    '<strong>' . number_format_i18n( $possibilities_count ) . '</strong>',
                    $severity_text
                 ) . '</li>';
            }
            $intro_summary .= '</ul>';
            $intro_summary .= '<hr>';
        } elseif ( $possibilities_count > 0 || $total_new_keywords > 0 ) {
            $intro_summary .= '<h2>' . esc_html__( 'Summary', 'seo-booster' ) . '</h2>';
            $intro_summary .= '<ul>';
            if ( $total_new_keywords > 0 ) {
                $intro_summary .= '<li>' . sprintf( 
                    /* translators: %s: number of new keywords */
                    __( '%s new keywords discovered', 'seo-booster' ),
                    '<strong>' . number_format_i18n( $total_new_keywords ) . '</strong>'
                 ) . '</li>';
            }
            if ( $possibilities_count > 0 ) {
                $intro_summary .= '<li>' . sprintf( 
                    /* translators: %s: total SEO possibilities */
                    __( '%s SEO possibilities to review', 'seo-booster' ),
                    '<strong>' . number_format_i18n( $possibilities_count ) . '</strong>'
                 ) . '</li>';
            }
            $intro_summary .= '</ul>';
            $intro_summary .= '<hr>';
        }
        if ( $has_possibilities ) {
            $content .= '<h2>' . esc_html__( 'Top SEO Possibilities', 'seo-booster' ) . '</h2>';
            $content .= '<p>' . sprintf( 
                /* translators: %d: number of possibilities listed */
                esc_html__( 'Here are the top %d SEO possibilities to address:', 'seo-booster' ),
                min( 3, count( $top_possibilities ) )
             ) . '</p>';
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
                    $content .= ' <small>(' . sprintf( 
                        /* translators: %d: number of pages */
                        _n(
                            '%d page affected',
                            '%d pages affected',
                            $affected_urls,
                            'seo-booster'
                        ),
                        $affected_urls
                     ) . ')</small>';
                }
                $content .= '</p>';
            }
            $content .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=sb2_seo_issues' ) ) . '">' . esc_html__( 'View All SEO Possibilities', 'seo-booster' ) . '</a></p>';
        }
        if ( $has_new_keywords ) {
            $content .= '<h2>' . esc_html__( 'New Keywords', 'seo-booster' ) . '</h2>';
            $content .= '<p>' . esc_html__( 'Top keywords discovered in the past 7 days:', 'seo-booster' ) . '</p>';
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
                $content .= esc_html__( 'Position:', 'seo-booster' ) . ' ' . number_format_i18n( floor( $avg_position ) ) . ' • ';
                $content .= esc_html__( 'Clicks:', 'seo-booster' ) . ' ' . number_format_i18n( $total_clicks ) . ' • ';
                $content .= esc_html__( 'Impressions:', 'seo-booster' ) . ' ' . number_format_i18n( $total_impressions ) . '</small></p>';
            }
            if ( $total_new_keywords > 3 ) {
                $content .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=sb2_gsc' ) ) . '">' . sprintf( 
                    /* translators: %s: total new keywords */
                    esc_html__( 'View all %s new keywords', 'seo-booster' ),
                    number_format_i18n( $total_new_keywords )
                 ) . '</a></p>';
            } else {
                $content .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=sb2_gsc' ) ) . '">' . esc_html__( 'Open Google Search Console overview', 'seo-booster' ) . '</a></p>';
            }
        }
        if ( !$has_anything ) {
            $content .= '<p>' . esc_html__( 'Nothing new this week. Open your dashboard when you are ready to review SEO progress.', 'seo-booster' ) . '</p>';
            $content .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=sb2_dashboard' ) ) . '">' . esc_html__( 'Open SEO Booster Dashboard', 'seo-booster' ) . '</a></p>';
        }
        $dashboardlink = admin_url( 'admin.php?page=sb2_dashboard' );
        $subjectline = self::build_subject_line( $has_pulse, $clicks_percentage, $possibilities_count );
        $emailtitle = __( 'SEO Update from SEO Booster', 'seo-booster' ) . ' - ' . Utils::remove_http( site_url() );
        $dashboardlinkanchor = __( 'SEO Booster Dashboard', 'seo-booster' );
        $emailintrotext = __( 'Here\'s your weekly SEO update with key insights and opportunities.', 'seo-booster' );
        $emailintrotext .= '<br><small>' . __( 'Google Search figures are from Search Console and do not represent your full website traffic.', 'seo-booster' ) . '</small>';
        $my_replacements = array(
            '%%emailintrotext%%'      => $emailintrotext,
            '%%websitedomain%%'       => Utils::remove_http( site_url() ),
            '%%dashboardlink%%'       => $dashboardlink,
            '%%dashboardlinkanchor%%' => $dashboardlinkanchor,
            '%%emailtitle%%'          => $emailtitle,
            '%%emailcontent%%'        => nl2br( $intro_summary . $content ),
        );
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        $template_path = SEOBOOSTER_PLUGINPATH . 'templates/email/report.html';
        if ( $wp_filesystem->exists( $template_path ) ) {
            $html = $wp_filesystem->get_contents( $template_path );
        } else {
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

    /**
     * Build a skim-friendly action-led subject line.
     *
     * @param bool  $has_pulse            Whether Google Search WoW pulse data exists.
     * @param float $clicks_percentage    Signed week-over-week clicks percent change.
     * @param int   $possibilities_count  Total SEO possibilities.
     * @return string
     */
    private static function build_subject_line( $has_pulse, $clicks_percentage, $possibilities_count ) {
        $site = Utils::remove_http( site_url() );
        $parts = array();
        if ( $has_pulse ) {
            if ( $clicks_percentage > 0 ) {
                $parts[] = sprintf( 
                    /* translators: %s: percent change */
                    __( 'Clicks from Google Search up %s%%', 'seo-booster' ),
                    number_format_i18n( abs( $clicks_percentage ) )
                 );
            } elseif ( $clicks_percentage < 0 ) {
                $parts[] = sprintf( 
                    /* translators: %s: percent change */
                    __( 'Clicks from Google Search down %s%%', 'seo-booster' ),
                    number_format_i18n( abs( $clicks_percentage ) )
                 );
            }
        }
        if ( $possibilities_count > 0 ) {
            $parts[] = sprintf( 
                /* translators: %d: number of SEO opportunities */
                _n(
                    '%d SEO opportunity',
                    '%d SEO opportunities',
                    $possibilities_count,
                    'seo-booster'
                ),
                $possibilities_count
             );
        }
        if ( !empty( $parts ) ) {
            return implode( ': ', $parts ) . ' · ' . $site;
        }
        return sprintf( 
            /* translators: 1: formatted date, 2: site domain */
            __( 'Your Weekly SEO Update - %1$s - %2$s', 'seo-booster' ),
            date_i18n( 'F j, Y' ),
            $site
         );
    }

}
