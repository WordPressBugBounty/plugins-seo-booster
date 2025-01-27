<?php

namespace Cleverplugins\SEOBooster\Reports;

class TopPerformingKeywords {

    public static function get_data() {
        global $wpdb;
        // Try to get cached data first
        $cache_key = 'sb2_top_performing_keywords_30days';
        // $results = wp_cache_get($cache_key);
        $results = false;
        if (false === $results) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT 
                        qk.query, 
                        qk.page, 
                        SUM(h.impressions) AS total_impressions, 
                        SUM(h.clicks) AS total_clicks, 
                        AVG(h.position) AS avg_position, 
                        AVG(h.ctr) AS avg_ctr,
                        MAX(h.date) AS last_seen,
                        MIN(h.date) AS first_seen
                    FROM 
                        {$wpdb->prefix}sb2_query_keywords AS qk
                    INNER JOIN 
                        {$wpdb->prefix}sb2_query_keywords_history AS h ON qk.id = h.query_keywords_id
                    WHERE 
                        h.date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
                    GROUP BY 
                        qk.query, qk.page
                    ORDER BY 
                        total_clicks DESC
                    LIMIT %d",
                    1000
                ),
                OBJECT
            );
            
            // Cache the results for 1 hour (3600 seconds)
            wp_cache_set($cache_key, $results, '', 3600);
        }

        return $results;
    }
}