<?php

namespace Cleverplugins\SEOBooster\Reports;

class QuestionQueries {

    public static function get_data() {
        global $wpdb;

        // Try to get cached data first
        $cache_key = 'sb2_question_queries_report';
        $results = wp_cache_get($cache_key);

        if (false === $results) {
            $default_phrases = [
                // English
                'how to ', 'what is ', 'why ', 'who ', 'where ', 'when ', 'can ', 
                'does ', 'do ', 'is ', 'are ', 'should ', 'could ', 'would ',
                
                // Danish
                'hvordan ', 'hvad er ', 'hvorfor ', 'hvem ', 'hvor ', 'hvornår ', 
                'kan ', 'gør ', 'gør ', 'er ', 'er ', 'skal ', 'kunne ', 'ville ',
                
                // Swedish
                'hur ', 'vad är ', 'varför ', 'vem ', 'var ', 'när ', 'kan ', 
                'gør ', 'gør ', 'är ', 'är ', 'ska ', 'kunde ', 'skulle ',
                
                // Norwegian
                'hvordan ', 'hva er ', 'hvorfor ', 'hvem ', 'hvor ', 'når ', 'kan ', 
                'gjør ', 'gjør ', 'er ', 'er ', 'skal ', 'kunne ', 'ville '
            ];

            $question_phrases = apply_filters('seo_booster_question_phrases', $default_phrases);

            // Build the query parts
            $placeholders = array_fill(0, count($question_phrases), 'qk.query LIKE %s');
            $values = array_map(function($phrase) use ($wpdb) {
                return $wpdb->esc_like($phrase) . '%';
            }, $question_phrases);

            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT 
                        qk.query AS query,
                        qk.page AS page,
                        AVG(h.position) AS position,
                        SUM(h.clicks) AS clicks,
                        SUM(h.impressions) AS impressions,
                        AVG(h.ctr) AS ctr
                    FROM 
                        {$wpdb->prefix}sb2_query_keywords AS qk
                    INNER JOIN 
                        {$wpdb->prefix}sb2_query_keywords_history AS h ON qk.id = h.query_keywords_id
                    WHERE " . implode(' OR ', $placeholders) . "
                    GROUP BY 
                        qk.query, qk.page
                    ORDER BY 
                        clicks DESC
                    LIMIT 100",
                    $values
                ),
                OBJECT
            );

            // Cache the results for 1 hour
            wp_cache_set($cache_key, $results, '', 3600);
        }

        return $results;
    }

    /**
     * Clear the question queries report cache
     * 
     * @return void
     */
    public static function clear_cache() {
        wp_cache_delete('sb2_question_queries_report');
    }
}