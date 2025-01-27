<?php

namespace Cleverplugins\SEOBooster;

if (!defined('ABSPATH')) {
    exit;
}

class email_status
{

    public static function init() {}



    /**
     * send_email_update.
     *
     * @author	Unknown
     * @author	Lars Koudal
     * @since	v0.0.1
     * @version	v1.0.0	Tuesday, November 30th, 2021.	
     * @version	v1.0.1	Wednesday, August 14th, 2024.
     * @access	public static
     * @param	integer	$days  	Default: 7
     * @param	boolean	$forced	Default: false
     * @return	void
     */
    public static function send_email_update($days = 7, $forced = false)
    {
        $seobooster_weekly_email = get_option('seobooster_weekly_email');
        if ('on' !== $seobooster_weekly_email && !$forced) {
            return;
        }

        $seobooster_weekly_email_recipient = get_option('seobooster_weekly_email_recipient');
        if (strpos($seobooster_weekly_email_recipient, ',') !== false) {
            $email_recipients = array_map('trim', explode(',', $seobooster_weekly_email_recipient));
        } else {
            $email_recipients = [trim($seobooster_weekly_email_recipient)];
        }

        if (!is_int($days)) {
            $days = 7;
        }

        global $wpdb;
        $query_keywords_table = $wpdb->prefix . 'sb2_query_keywords';
        $query_keywords_history_table = $wpdb->prefix . 'sb2_query_keywords_history';

        $content = ''; 
        $intro_summary = '';

        // 1. New Keywords in the Past 7 Days
        $cache_key = 'new_keywords_' . $days;
        $new_keywords = wp_cache_get($cache_key);
        if (false === $new_keywords) {
            $new_keywords = $wpdb->get_results($wpdb->prepare("
            SELECT k.query, k.page, AVG(h.position) as avg_position, SUM(h.clicks) as total_clicks, SUM(h.impressions) as total_impressions
            FROM $query_keywords_table k
            JOIN $query_keywords_history_table h ON k.id = h.query_keywords_id
            WHERE k.first_seen_date >= CURDATE() - INTERVAL %d DAY
            GROUP BY k.page, k.query
            ORDER BY k.first_seen_date DESC
            LIMIT 10", $days), ARRAY_A);
            wp_cache_set($cache_key, $new_keywords, '', 3600); // Cache for 1 hour
        }

        // Correct count of new keywords in the past 7 days
        $cache_key = 'total_new_keywords_' . $days;
        $total_new_keywords = wp_cache_get($cache_key);
        if (false === $total_new_keywords) {
            $total_new_keywords = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT k.query) as total_new_keywords
            FROM $query_keywords_table k
            WHERE k.first_seen_date >= CURDATE() - INTERVAL %d DAY", $days));
            wp_cache_set($cache_key, $total_new_keywords, '', 3600); // Cache for 1 hour
        }

        // Total new keywords in the past 30 days
        $total_new_keywords_30_days = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT k.query) as total_new_keywords
        FROM $query_keywords_table k
        WHERE k.first_seen_date >= CURDATE() - INTERVAL %d DAY", 30));

        // 2. Trend Analysis (Impressions and Clicks) over the Last 14 Days
        $cache_key = 'trends_analysis_14_days';
        $trends = wp_cache_get($cache_key);
        if (false === $trends) {
            $trends = $wpdb->get_results($wpdb->prepare("
            SELECT 
                k.query, 
                k.page, 
                SUM(CASE WHEN h.date >= CURDATE() - INTERVAL 7 DAY THEN h.impressions ELSE 0 END) as current_week_impressions,
                SUM(CASE WHEN h.date >= CURDATE() - INTERVAL 7 DAY THEN h.clicks ELSE 0 END) as current_week_clicks,
                SUM(CASE WHEN h.date >= CURDATE() - INTERVAL 14 DAY AND h.date < CURDATE() - INTERVAL 7 DAY THEN h.impressions ELSE 0 END) as previous_week_impressions,
                SUM(CASE WHEN h.date >= CURDATE() - INTERVAL 14 DAY AND h.date < CURDATE() - INTERVAL 7 DAY THEN h.clicks ELSE 0 END) as previous_week_clicks
            FROM {$query_keywords_table} k
            JOIN {$query_keywords_history_table} h ON k.id = h.query_keywords_id
            WHERE h.date >= CURDATE() - INTERVAL 14 DAY
            GROUP BY k.query, k.page
            ORDER BY current_week_impressions DESC
            LIMIT %d", 25), ARRAY_A);
            wp_cache_set($cache_key, $trends, '', 3600); // Cache for 1 hour
        }

        $current_week_impressions = array_sum(array_column($trends, 'current_week_impressions'));
        $previous_week_impressions = array_sum(array_column($trends, 'previous_week_impressions'));

        // 3. Keyword Cannibalization Detection
        $cannibalized_keywords = $wpdb->get_results($wpdb->prepare("
        SELECT 
            k.query, 
            k.page, 
            COUNT(DISTINCT k.page) as page_count,
            SUM(h.impressions) as total_impressions, 
            SUM(h.clicks) as total_clicks, 
            AVG(h.position) as avg_position
        FROM {$query_keywords_table} k
        JOIN {$query_keywords_history_table} h ON k.id = h.query_keywords_id
        GROUP BY k.query
        HAVING page_count > 1
        ORDER BY total_impressions DESC
        LIMIT %d", 10), ARRAY_A);

        // 4. Refined Ranking Anomalies Detection
        $anomalies = $wpdb->get_results($wpdb->prepare("
        SELECT 
            k.query, 
            k.page, 
            MIN(h.position) as min_position, 
            MAX(h.position) as max_position, 
            MAX(h.date) as max_position_date, 
            (SELECT h2.position 
             FROM {$query_keywords_history_table} h2 
             WHERE h2.query_keywords_id = h.query_keywords_id 
             ORDER BY h2.date DESC LIMIT 1) as current_position,
            SUM(h.impressions) as total_impressions, 
            SUM(h.clicks) as total_clicks
        FROM {$query_keywords_history_table} h
        JOIN {$query_keywords_table} k ON k.id = h.query_keywords_id
        WHERE h.date >= CURDATE() - INTERVAL 30 DAY
        GROUP BY k.query, k.page
        HAVING (MAX(h.position) - MIN(h.position)) > 10 AND total_impressions > 0
        ORDER BY total_impressions DESC
        LIMIT %d", 10), ARRAY_A);

        $ignored_urls = SB404_Errors::get_ignored_urls_for_404();

        // 5. 404 Errors
        $errors_404 = $wpdb->get_results($wpdb->prepare("
        SELECT * 
        FROM {$wpdb->prefix}sb2_404 
        LIMIT %d", 1000));
        $filtered_errors = array_filter($errors_404, function($error) use ($ignored_urls) {
            return !in_array($error->lp, $ignored_urls);
        });
        $total_404s = count($filtered_errors);

        // Executive Summary
        $intro_summary .= "<h2>" . __('Executive Summary', 'seo-booster') . "</h2>";
        $intro_summary .= "<ul>";
        $intro_summary .= "<li>" . sprintf(__('You have %s new keywords discovered in the past 7 days.', 'seo-booster'), number_format_i18n($total_new_keywords)) . "</li>";
        $intro_summary .= "<li>" . sprintf(__('Total new keywords in the past 30 days: %s', 'seo-booster'), number_format_i18n($total_new_keywords_30_days)) . "</li>";
        $intro_summary .= "<li>" . sprintf(__('Trend Analysis: %s impressions this week, %s last week.', 'seo-booster'), number_format_i18n($current_week_impressions), number_format_i18n($previous_week_impressions)) . "</li>";
        $intro_summary .= "<li>" . sprintf(__('Keyword Cannibalization: %s instances detected.', 'seo-booster'), number_format_i18n(count($cannibalized_keywords))) . "</li>";
        $intro_summary .= "<li>" . sprintf(__('Ranking Anomalies: %s anomalies detected.', 'seo-booster'), number_format_i18n(count($anomalies))) . "</li>";
        $intro_summary .= "<li>" . sprintf(__('404 Errors: %s errors detected.', 'seo-booster'), number_format_i18n($total_404s)) . "</li>";
        $intro_summary .= "</ul>";
        $intro_summary .= '<p><a href="' . esc_url(admin_url('admin.php?page=sb2_reports')) . '">' . __('View full report', 'seo-booster') . '</a></p>';
        $intro_summary .= "<hr>";

        // Detailed Sections
        $content .= "<h2>" . __('New Keywords in the Past 7 Days', 'seo-booster') . "</h2>";
        if (!empty($new_keywords)) {
            $content .= '<p>' . __('The following keywords were used to find content on your website for the first time in the past 7 days.', 'seo-booster') . '</p>';
            foreach (array_slice($new_keywords, 0, 3) as $keyword) { // Limit to top 3
                $content .= '<p>' . __('Keyword:', 'seo-booster') . " <strong>" . esc_html($keyword['query']) . "</strong><br/>";
                $content .= "<a href='" . esc_url($keyword['page']) . "' target='_blank'>" . esc_html($keyword['page']) . "</a><br/>";
                $content .= "<small>";
                $content .= __('Avg. Position:', 'seo-booster') . " " . number_format_i18n(floor($keyword['avg_position'])) . ' ';
                $content .= __('Clicks:', 'seo-booster') . " " . number_format_i18n($keyword['total_clicks']) . ' ';
                $content .= __('Impressions:', 'seo-booster') . " " . number_format_i18n($keyword['total_impressions']) . "</small></p>";
            }
            $content .= '<p><a href="' . esc_url(admin_url('admin.php?page=sb2_reports')) . '">' . __('View full report', 'seo-booster') . '</a></p>';
        } else {
            $content .= "<p>" . __('No new keywords found in the past 7 days.', 'seo-booster') . "</p>";
        }

        // Trend Analysis Section
        $content .= "<h2>" . __('Trend Analysis', 'seo-booster') . "</h2>";
        if (!empty($trends)) {
            $content .= '<p>' . __('Here are the top trends in impressions and clicks over the last 14 days.', 'seo-booster') . '</p>';
            foreach (array_slice($trends, 0, 3) as $trend) { // Limit to top 3
                $content .= '<p>' . __('Query:', 'seo-booster') . " <strong>" . esc_html($trend['query']) . "</strong><br/>";
                $content .= "<a href='" . esc_url($trend['page']) . "' target='_blank'>" . esc_html($trend['page']) . "</a><br/>";
                $content .= "<small>";
                $content .= __('Current Week Impressions:', 'seo-booster') . " " . number_format_i18n($trend['current_week_impressions']) . ' ';
                $content .= __('Current Week Clicks:', 'seo-booster') . " " . number_format_i18n($trend['current_week_clicks']) . ' ';
                $content .= __('Previous Week Impressions:', 'seo-booster') . " " . number_format_i18n($trend['previous_week_impressions']) . ' ';
                $content .= __('Previous Week Clicks:', 'seo-booster') . " " . number_format_i18n($trend['previous_week_clicks']) . "</small></p>";
            }
            $content .= '<p><a href="' . esc_url(admin_url('admin.php?page=sb2_reports')) . '">' . __('View full report', 'seo-booster') . '</a></p>';
        } else {
            $content .= "<p>" . __('No significant trends found in the past 14 days.', 'seo-booster') . "</p>";
        }

        // Keyword Cannibalization Section
        $content .= "<h2>" . __('Keyword Cannibalization', 'seo-booster') . "</h2>";
        if (!empty($cannibalized_keywords)) {
            $content .= '<p>' . __('The following keywords are causing cannibalization issues across multiple pages.', 'seo-booster') . '</p>';
            foreach (array_slice($cannibalized_keywords, 0, 3) as $keyword) { // Limit to top 3
                $content .= '<p>' . __('Keyword:', 'seo-booster') . " <strong>" . esc_html($keyword['query']) . "</strong><br/>";
                $content .= "<a href='" . esc_url($keyword['page']) . "' target='_blank'>" . esc_html($keyword['page']) . "</a><br/>";
                $content .= "<small>";
                $content .= __('Total Impressions:', 'seo-booster') . " " . number_format_i18n($keyword['total_impressions']) . ' ';
                $content .= __('Total Clicks:', 'seo-booster') . " " . number_format_i18n($keyword['total_clicks']) . ' ';
                $content .= __('Avg. Position:', 'seo-booster') . " " . number_format_i18n($keyword['avg_position']) . "</small></p>";
            }
            $content .= '<p><a href="' . esc_url(admin_url('admin.php?page=sb2_reports')) . '">' . __('View full report', 'seo-booster') . '</a></p>';
        } else {
            $content .= "<p>" . __('No keyword cannibalization detected.', 'seo-booster') . "</p>";
        }

        // Ranking Anomalies Section
        $content .= "<h2>" . __('Ranking Anomalies', 'seo-booster') . "</h2>";
        if (!empty($anomalies)) {
            $content .= '<p>' . __('The following keywords have shown significant ranking fluctuations.', 'seo-booster') . '</p>';
            foreach (array_slice($anomalies, 0, 3) as $anomaly) { // Limit to top 3
                $content .= '<p>' . __('Keyword:', 'seo-booster') . " <strong>" . esc_html($anomaly['query']) . "</strong><br/>";
                $content .= "<a href='" . esc_url($anomaly['page']) . "' target='_blank'>" . esc_html($anomaly['page']) . "</a><br/>";
                $content .= "<small>";
                $content .= __('Impressions:', 'seo-booster') . " " . number_format_i18n($anomaly['total_impressions']) . ' ';
                $content .= __('Clicks:', 'seo-booster') . " " . number_format_i18n($anomaly['total_clicks']) . ' ';
                $content .= '<span style="color:red;">' . __('Position fluctuation:', 'seo-booster') . " " . number_format_i18n($anomaly['min_position']) . " - " . number_format_i18n($anomaly['max_position']) . "</span><br/>";
                $date_format = get_option('date_format');
                $content .= sprintf(
                    // translators: 1: date, 2: current position
                    __('Current Position (as of %1$s): %2$s', 'seo-booster'),
                    date_i18n($date_format, strtotime($anomaly['max_position_date'])),
                    number_format_i18n(floor($anomaly['current_position']))
                );
                $content .= "</small></p>";
            }
            $content .= '<p><a href="' . esc_url(admin_url('admin.php?page=sb2_reports')) . '">' . __('View full report', 'seo-booster') . '</a></p>';
        } else {
            $content .= "<p>" . __('No significant ranking anomalies detected.', 'seo-booster') . "</p>";
        }

        // 404 Errors Section
        $content .= '<h2>' . __('404 Errors - Not found content', 'seo-booster') . '</h2>';
        if (!empty($filtered_errors)) {
            $content .= '<p>' . sprintf(__('A total of %s not found 404 errors have been detected. The following URLs have been reported as 404 errors. This means that the content was not found on your website.', 'seo-booster'), number_format_i18n($total_404s)) . '</p>';
            foreach (array_slice($filtered_errors, 0, 3) as $error) { // Limit to top 3
                $content .=  esc_html(site_url($error->lp)) . '<br><small>' . esc_html($error->visits) . ' ' . __('times', 'seo-booster');
                if (!empty($error->referer)) {
                    $content .= ' (' . __('Referrer:', 'seo-booster') . ') ' . esc_html($error->referer);
                } else {
                    $content .= ' ('.__('Unknown origin', 'seo-booster').')';
                }
                $content .= '</small><br><br>';
            }
            $content .= '<p>' . __('Please review these URLs. If they are from links inside your website, you should fix them immediately. Any external link you cannot control you can consider redirecting them to relevant content on your website.', 'seo-booster') . '</p>';
            $content .= '<p><a href="' . esc_url(admin_url('admin.php?page=sb2_reports')) . '">' . __('View full report', 'seo-booster') . '</a></p>';
        } else {
            $content .= '<p>' . __('No 404 errors found.', 'seo-booster') . '</p>';
        }

        // Final Email Assembly
        $dashboardlink = admin_url('?page=sb2_dashboard');
        $subjectline = sprintf("Your Weekly SEO Update: New Keywords, Trends, and Performance Insights - %s - %s", date_i18n('F j, Y'), Utils::remove_http(site_url()));
        $emailtitle = __('Report from SEO Booster on ', 'seo-booster') . ' ' . Utils::remove_http(site_url());
        $dashboardlinkanchor = __('SEO Booster Dashboard', 'seo-booster');
        $emailintrotext = __('Hello! We’ve prepared your weekly SEO report, providing insights on new keywords, trends, potential issues, and recommendations.', 'seo-booster');
        $my_replacements = array(
            '%%emailintrotext%%' => $emailintrotext,
            '%%websitedomain%%' => Utils::remove_http(site_url()),
            '%%dashboardlink%%' => $dashboardlink,
            '%%dashboardlinkanchor%%' => $dashboardlinkanchor,
            '%%emailtitle%%' => $emailtitle,
            '%%emailcontent%%' => nl2br($intro_summary . $content),
        );

        // Get WP_Filesystem instance
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $template_path = SEOBOOSTER_PLUGINPATH . 'templates/email/report.php';
        if ($wp_filesystem->exists($template_path)) {
            $html = $wp_filesystem->get_contents($template_path);
        } else {
            // Handle error - template file not found
            Utils::log(esc_html__('Email template file not found', 'seo-booster'), 2);
            return;
        }

        foreach ($my_replacements as $needle => $replacement) {
            $html = str_replace($needle, $replacement, $html);
        }
        $headers = array('Content-Type: text/html; charset=UTF-8');

        if (empty($email_recipients)) {
            Utils::log(esc_html__('No valid email recipients found for status email', 'seo-booster'), 2);
            return;
        }

        foreach ($email_recipients as $email) {
            if (!is_email($email)) {
                Utils::log(sprintf(
                    // translators: 1: Invalid email address
                    esc_html__('Invalid email address: %1$s', 'seo-booster'),
                    esc_html($email)
                ), 2);
                continue;
            }

            $sendresult = wp_mail($email, $subjectline, $html, $headers);
            if ($sendresult) {
                Utils::log(sprintf(
                    // translators: 1: Email address of the recipient
                    esc_html__('Status email was sent to %1$s', 'seo-booster'),
                    esc_html($email)
                ), 10);
            } else {
                Utils::log(sprintf(
                    // translators: 1: Email address of the recipient
                    esc_html__('Status email was not sent to %1$s', 'seo-booster'),
                    esc_html($email)
                ), 2);
            }
        }
    }
}
