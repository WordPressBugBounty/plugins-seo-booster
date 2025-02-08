<?php

namespace Cleverplugins\SEOBooster\Reports;

class KeywordCannibalization {

    public static function get_data() {
        global $wpdb;

            // Simpler SQL query without GROUP_CONCAT
            $query = $wpdb->prepare(
                "SELECT 
                    qk.query,
                    qk.page,
                    AVG(h.position) as avg_position,
                    SUM(h.clicks) as page_clicks,
                    SUM(h.impressions) as page_impressions,
                    AVG(h.ctr) as page_ctr
                FROM {$wpdb->prefix}sb2_query_keywords AS qk
                INNER JOIN {$wpdb->prefix}sb2_query_keywords_history AS h 
                    ON qk.id = h.query_keywords_id
                GROUP BY qk.query, qk.page
                ORDER BY qk.query, page_clicks DESC",
                []
            );
            
            $raw_results = $wpdb->get_results($query);
            
            // Process results in PHP
            $grouped_results = [];
            $current_query = null;
            $current_group = null;
            
            foreach ($raw_results as $row) {
                if ($current_query !== $row->query) {
                    // If we have a previous group with multiple pages, add it to results
                    if ($current_group && count($current_group['competing_pages']) > 1) {
                        // Ensure only 7 competing pages
                        $current_group['competing_pages'] = array_slice($current_group['competing_pages'], 0, 7);
                        $grouped_results[] = $current_group;
                    }
                    
                    // Start new group
                    $current_query = $row->query;
                    $current_group = [
                        'query' => $row->query,
                        'competing_pages' => [],
                        'page_count' => 0,
                        'total_clicks' => 0,
                        'total_impressions' => 0,
                        'avg_ctr' => 0
                    ];
                }
                
                // Add page to current group
                $current_group['competing_pages'][] = [
                    'url' => $row->page,
                    'position' => round($row->avg_position, 1),
                    'clicks' => intval($row->page_clicks)
                ];
                
                // Update group totals
                $current_group['page_count']++;
                $current_group['total_clicks'] += intval($row->page_clicks);
                $current_group['total_impressions'] += intval($row->page_impressions);
                $current_group['avg_ctr'] = $current_group['total_impressions'] > 0 ? 
                    ($current_group['total_clicks'] / $current_group['total_impressions']) : 0;
            }
            
            // Add the last group if it has multiple pages
            if ($current_group && count($current_group['competing_pages']) > 1) {
                // Ensure only 7 competing pages for the last group too
                $current_group['competing_pages'] = array_slice($current_group['competing_pages'], 0, 7);
                $grouped_results[] = $current_group;
            }
            
            // Sort groups by total clicks
            usort($grouped_results, function($a, $b) {
                return $b['total_clicks'] - $a['total_clicks'];
            });
            
            // Take only top 10 groups
            $results = array_slice($grouped_results, 0, 10);
            
            // Convert to objects for consistency with existing code
            $results = array_map(function($group) {
                return (object) [
                    'query' => $group['query'],
                    'competing_pages' => array_slice($group['competing_pages'], 0, 7), // Extra safety check
                    'page_count' => $group['page_count'],
                    'total_clicks' => $group['total_clicks'],
                    'total_impressions' => $group['total_impressions'],
                    'avg_ctr' => $group['avg_ctr']
                ];
            }, $results);

            // Before setting cache, ensure we have max 7 competing pages
            $results = array_map(function($group) {
                $group->competing_pages = array_slice($group->competing_pages, 0, 7);
                return $group;
            }, $results);

            return $results;
        

   
    }

    public static function clear_cache() {
        wp_cache_delete('sb2_keyword_cannibalization_report');
    }
}
