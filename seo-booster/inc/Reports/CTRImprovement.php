<?php

namespace Cleverplugins\SEOBooster\Reports;
class CTRImprovement {

    public static function get_data() {
        global $wpdb;

        // Try to get cached data first
        $cache_key = 'sb2_ctr_improvement_report';
        $results = wp_cache_get($cache_key);

        if (false === $results) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT 
                        qk.query, 
                        qk.page, 
                        SUM(h.clicks) AS clicks,
                        SUM(CAST(h.impressions AS UNSIGNED)) AS impressions, 
                        AVG(h.ctr) AS ctr
                    FROM 
                        {$wpdb->prefix}sb2_query_keywords AS qk
                    INNER JOIN 
                        {$wpdb->prefix}sb2_query_keywords_history AS h ON qk.id = h.query_keywords_id
                    GROUP BY 
                        qk.query, qk.page
                    HAVING 
                        impressions > %d AND ctr < %f
                    ORDER BY 
                        impressions DESC
                    LIMIT %d",
                    1000,   // minimum impressions
                    0.02,   // maximum CTR
                    100     // limit
                ),
                OBJECT
            );

            // Cache the results for 1 hour (3600 seconds)
            wp_cache_set($cache_key, $results, '', 3600);
        }

        return $results;
    }
}
