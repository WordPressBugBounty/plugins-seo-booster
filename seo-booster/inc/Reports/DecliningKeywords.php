<?php

namespace Cleverplugins\SEOBooster\Reports;

class DecliningKeywords {

    public static function get_data() {
        global $wpdb;

        // Match the cache key format from report page
        $cache_key = 'seobooster_report_declining_keywords';
        $results = wp_cache_get($cache_key);

        if (false === $results) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT 
                        qk.query, 
                        qk.page,
                        MAX(h.position) AS current_position,
                        MAX(h.date) as last_activity_date,
                        DATEDIFF(CURDATE(), MAX(h.date)) as days_inactive,
                        SUM(h.clicks) as total_clicks,
                        SUM(h.impressions) as total_impressions,
                        AVG(h.ctr) as avg_ctr
                    FROM 
                        {$wpdb->prefix}sb2_query_keywords AS qk
                    LEFT JOIN 
                        {$wpdb->prefix}sb2_query_keywords_history AS h 
                        ON qk.id = h.query_keywords_id
                        AND h.date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY 
                        qk.query, qk.page
                    HAVING 
                        SUM(h.clicks) IS NULL OR SUM(h.clicks) = 0
                    ORDER BY 
                        days_inactive DESC, total_clicks DESC
                    LIMIT %d",
                    100  // LIMIT value
                ),
                OBJECT
            );

            // Format the results
            $formatted_results = array_map(function($row) {
                $display_url = preg_replace('#^https?://#', '', $row->page);
                return (object)[
                    'query' => $row->query,
                    'page' => [
                        'display' => $display_url,
                        'url' => $row->page,
                        'html' => sprintf(
                            '<a href="%s" target="_blank">%s</a>',
                            esc_url($row->page),
                            esc_html($display_url)
                        )
                    ],
                    'current_position' => round($row->current_position, 1),
                    'days_inactive' => $row->days_inactive,
                    'last_activity' => $row->last_activity_date,
                    'total_clicks' => $row->total_clicks,
                    'total_impressions' => $row->total_impressions,
                    'avg_ctr' => round($row->avg_ctr * 100, 2)
                ];
            }, $results);

            // Use the cache time from the report page (86400)
            wp_cache_set($cache_key, $formatted_results, '', 86400);
            return $formatted_results;
        }

        return $results;
    }

    /**
     * Clear the declining keywords report cache
     * 
     * @return void
     */
    public static function clear_cache() {
        wp_cache_delete('seobooster_report_declining_keywords');
    }
}