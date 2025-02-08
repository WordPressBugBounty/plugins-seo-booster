<?php

namespace Cleverplugins\SEOBooster\Reports;

class Missing404Pages
{

    public static function get_data()
    {
        global $wpdb;


        $ignored = \Cleverplugins\SEOBooster\SB404_Errors::get_ignored_urls_for_404();

        // Base query with specific columns and aliases
        $sql = "SELECT 
                lp as url,
                visits as count,
                lastseen as last_seen,
                firstseen as first_seen,
                referer
            FROM {$wpdb->prefix}sb2_404 WHERE 1=1";

        // Arrays to hold exact matches and wildcard patterns
        $exact_matches = [];
        $wildcard_patterns = [];
        $prepare_values = [];
        $where_clauses = [];

        // Separate the ignored URLs into exact matches and wildcard patterns
        foreach ($ignored as $url) {
            if (strpos($url, '*') !== false) {
                $where_clauses[] = "lp NOT LIKE %s";
                $prepare_values[] = str_replace('*', '%', $url);
            } else {
                $exact_matches[] = $url;
            }
        }

        // Add NOT IN clause for exact matches if any exist
        if (!empty($exact_matches)) {
            $placeholders = array_fill(0, count($exact_matches), '%s');
            $where_clauses[] = "lp NOT IN (" . implode(',', $placeholders) . ")";
            $prepare_values = array_merge($prepare_values, $exact_matches);
        }

        // Add WHERE clauses if we have any
        if (!empty($where_clauses)) {
            $sql .= " AND " . implode(' AND ', $where_clauses);
        }

        if (!empty($prepare_values)) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    $sql,
                    $prepare_values
                )
            );
        } else {
            // When no values to prepare, use the base query directly
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * 
                        FROM {$wpdb->prefix}sb2_404 
                        WHERE %s = %s",
                    '1',
                    '1'
                )
            );
        }
        // Prefix each result with the site URL
        $modified_results = [];
        foreach ($results as $result) {            
            $result->url = site_url($result->url);
            $modified_results[] = $result;
        }
        $results = $modified_results;
        unset($result); 
        return $results;
    }

    /**
     * Clear the 404 pages report cache
     * 
     * @return void
     */
    public static function clear_cache()
    {
        wp_cache_delete('sb2_missing_404_pages_report');
    }
}
