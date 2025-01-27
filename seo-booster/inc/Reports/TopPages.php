<?php

namespace Cleverplugins\SEOBooster\Reports;

class TopPages {

    public static function get_data() {
        global $wpdb;
        
        $cache_key = 'sb2_top_pages_report';
        $results = wp_cache_get($cache_key);

        if (false === $results) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT 
                        qk.page AS page,
                        SUM(h.clicks) AS clicks,
                        SUM(h.impressions) AS impressions,
                        AVG(h.ctr) AS ctr,
                        AVG(h.position) AS position
                    FROM 
                        {$wpdb->prefix}sb2_query_keywords AS qk
                    INNER JOIN 
                        {$wpdb->prefix}sb2_query_keywords_history AS h ON qk.id = h.query_keywords_id
                    GROUP BY 
                        qk.page
                    ORDER BY 
                        clicks DESC
                    LIMIT %d",
                    100
                ),
                OBJECT
            );

            wp_cache_set($cache_key, $results, '', 3600);
        }

        return $results;
    }

    public static function clear_cache() {
        wp_cache_delete('sb2_top_pages_report');
    }
}
