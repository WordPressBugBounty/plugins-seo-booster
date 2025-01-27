<?php

namespace Cleverplugins\SEOBooster\Reports;

class LongTailKeywords {

    public static function get_data() {
        global $wpdb;

        // Try to get cached data first
        $cache_key = 'sb2_long_tail_keywords_report';
        $results = wp_cache_get($cache_key);

        if (false === $results) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT 
                        qk.query, 
                        qk.page, 
                        SUM(h.impressions) AS impressions, 
                        SUM(h.clicks) AS clicks, 
                        AVG(h.position) AS avg_position, 
                        AVG(h.ctr) AS ctr,
                        (
                            SELECT h2.position 
                            FROM {$wpdb->prefix}sb2_query_keywords_history AS h2 
                            WHERE h2.query_keywords_id = h.query_keywords_id 
                            ORDER BY h2.date DESC 
                            LIMIT %d
                        ) AS latest_position
                    FROM 
                        {$wpdb->prefix}sb2_query_keywords AS qk
                    INNER JOIN 
                        {$wpdb->prefix}sb2_query_keywords_history AS h ON qk.id = h.query_keywords_id
                    WHERE 
                        LENGTH(qk.query) - LENGTH(REPLACE(qk.query, ' ', '')) + 1 >= %d
                    GROUP BY 
                        qk.query, qk.page
                    ORDER BY 
                        impressions DESC
                    LIMIT %d",
                    1,      // LIMIT for subquery
                    3,      // Minimum word count
                    100      // Main query LIMIT
                ),
                OBJECT
            );

            // Cache the results for 1 hour (3600 seconds)
            wp_cache_set($cache_key, $results, '', 3600);
        }

        return $results;
    }

    /**
     * Clear the long tail keywords report cache
     * 
     * @return void
     */
    public static function clear_cache() {
        wp_cache_delete('sb2_long_tail_keywords_report');
    }
}