<?php

namespace Cleverplugins\SEOBooster;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SEO_Issues_Manager
 *
 * Handles database operations for SEO possibilities and analysis results.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */
class SEO_Issues_Manager
{
    /**
     * Save analysis results to the database.
     *
     * @since 6.1.26
     * @param int|null $object_id Post or term ID (null for URL-only analysis).
     * @param string|null $object_type 'post', 'term', or null.
     * @param string $url The URL being analyzed.
     * @param array $results Analysis results from SEO_Analysis.
     * @return int|false Analysis ID on success, false on failure.
     */
    public static function save_analysis_to_db($object_id, $object_type, $url, $results)
    {
        global $wpdb;

        // Exclude private posts and WooCommerce special pages
        if ($object_id && $object_type === 'post' && self::should_exclude_from_analysis($object_id)) {
            Utils::log("Skipping analysis save for excluded post ID: {$object_id}", 5);
            return false;
        }

        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';

        // Get post title for display
        $post_title = null;
        $is_attachment = false;
        if ($object_id && $object_type === 'post') {
            $post = get_post($object_id);
            $post_title = $post ? $post->post_title : null;
            $is_attachment = $post && $post->post_type === 'attachment';
        } elseif ($object_id && $object_type === 'term') {
            $term = get_term($object_id);
            $post_title = $term && !is_wp_error($term) ? $term->name : null;
        }

        // Create URL hash for efficient lookups
        $url_hash = hash('sha256', $url);

        // Get or create URL record
        $url_record = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$urls_table} WHERE url_hash = %s",
            $url_hash
        ));

        if (!$url_record) {
            // Insert new URL record
            $url_result = $wpdb->insert($urls_table, [
                'url' => $url,
                'url_hash' => $url_hash,
                'object_id' => $object_id,
                'object_type' => $object_type,
                'post_title' => $post_title,
                'last_analyzed' => current_time('mysql'),
                'analysis_count' => 1
            ]);
            
            if ($url_result === false) {
                Utils::log('Failed to save URL record to database', 2);
                return false;
            }
            
            $url_id = $wpdb->insert_id;
        } else {
            $url_id = $url_record->id;
            
            // Update URL record
            $wpdb->update($urls_table, [
                'object_id' => $object_id,
                'object_type' => $object_type,
                'post_title' => $post_title,
                'last_analyzed' => current_time('mysql'),
                'analysis_count' => $wpdb->get_var($wpdb->prepare(
                    "SELECT analysis_count FROM {$urls_table} WHERE id = %d", $url_id
                )) + 1
            ], ['id' => $url_id]);
        }

        // Count total possibilities (excluding "good" severity - those are positive indicators, not issues)
        // Don't save possibilities for attachments - they shouldn't be listed as having possibilities
        $possibility_count = 0;
        if (!$is_attachment && !empty($results['issues'])) {
            foreach ($results['issues'] as $issue) {
                // Only count issues that are not "good" severity
                if (isset($issue['severity']) && $issue['severity'] !== 'good') {
                    $possibility_count++;
                }
            }
        }

        // Build insert data - improvements and good items are now stored in sb2_seo_issues table only
        $insert_data = [
            'url_id' => $url_id,
            'score' => $results['score'] ?? 0,
            'issue_count' => $possibility_count,
            'status' => 'analyzed'
        ];

        // Clear any pending analysis records for this URL before inserting new one
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$analysis_table} WHERE url_id = %d AND status = 'pending'",
            $url_id
        ));

        // Insert new analysis record
        $analysis_result = $wpdb->insert($analysis_table, $insert_data);

        if ($analysis_result === false) {
            Utils::log('Failed to save SEO analysis to database', 2);
            return false;
        }

        $analysis_id = $wpdb->insert_id;

        // First, remove ALL active possibilities for this URL to prevent duplicates
        // This ensures we start fresh with each analysis
        // Only remove active possibilities (not user-dismissed ones)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$issues_table} 
             WHERE url_id = %d 
             AND (user_status = 'active' OR user_status = '0' OR user_status IS NULL)",
            $url_id
        ));

        // Don't save possibilities for attachments
        if (!$is_attachment) {
            // Collect unique possibility keys from the new analysis (deduplicate)
            $new_possibility_keys = [];
            $unique_possibilities = [];
            if (!empty($results['issues'])) {
                foreach ($results['issues'] as $possibility) {
                    $possibility_key = $possibility['key'] ?? '';
                    if (!empty($possibility_key)) {
                        // Only keep the first occurrence of each possibility_key
                        if (!isset($unique_possibilities[$possibility_key])) {
                            $unique_possibilities[$possibility_key] = $possibility;
                            $new_possibility_keys[] = $possibility_key;
                        }
                    }
                }
            }

            // Insert the unique possibilities
            foreach ($unique_possibilities as $possibility_key => $possibility) {
                $severity = self::map_severity($possibility['severity'] ?? 'warning');
                $message = $possibility['message'] ?? '';
                $extra_data = isset($possibility['extra_data']) ? json_encode($possibility['extra_data']) : null;
                
                $wpdb->insert($issues_table, [
                    'analysis_id' => $analysis_id,
                    'url_id' => $url_id,
                    'is_sitewide' => 0,
                    'issue_key' => $possibility_key,
                    'message' => $message,
                    'severity' => $severity,
                    'user_status' => 'active',
                    'extra_data' => $extra_data
                ]);
            }

            // Also save improvements to the issues table (with lower severity)
            if (!empty($results['improvements'])) {
                foreach ($results['improvements'] as $improvement) {
                    $improvement_key = $improvement['key'] ?? '';
                    if (!empty($improvement_key)) {
                        $extra_data = isset($improvement['extra_data']) ? json_encode($improvement['extra_data']) : null;
                        
                        $wpdb->insert($issues_table, [
                            'analysis_id' => $analysis_id,
                            'url_id' => $url_id,
                            'is_sitewide' => 0,
                            'issue_key' => $improvement_key,
                            'message' => $improvement['message'] ?? '',
                            'severity' => 'low',
                            'user_status' => 'active',
                            'extra_data' => $extra_data
                        ]);
                    }
                }
            }

            // Also save good items to the issues table
            if (!empty($results['good'])) {
                foreach ($results['good'] as $good_item) {
                    $good_key = $good_item['key'] ?? '';
                    if (!empty($good_key)) {
                        $extra_data = isset($good_item['extra_data']) ? json_encode($good_item['extra_data']) : null;
                        
                        $wpdb->insert($issues_table, [
                            'analysis_id' => $analysis_id,
                            'url_id' => $url_id,
                            'is_sitewide' => 0,
                            'issue_key' => $good_key,
                            'message' => $good_item['message'] ?? '',
                            'severity' => 'good',
                            'user_status' => 'active',
                            'extra_data' => $extra_data
                        ]);
                    }
                }
            }
        }

        // Clear cache
        self::clear_stats_cache();

        Utils::log("Saved SEO analysis for URL: {$url} with {$possibility_count} possibilities", 3);
        return $analysis_id;
    }

    /**
     * Get saved analysis results from the database.
     *
     * @since 6.1.26
     * @param int|null $object_id Post or term ID.
     * @param string|null $object_type 'post', 'term', or null.
     * @return array|null Saved analysis results or null if not found.
     */
    public static function get_saved_analysis($object_id, $object_type = 'post')
    {
        global $wpdb;

        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';

        // Check if there's a pending analysis first
        $has_pending = self::has_pending_analysis($object_id, $object_type);

        // Get the most recent analyzed analysis for this object
        // First try by object_id and object_type - prefer 'analyzed' status, but also check for any status with a score
        $analysis = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, u.url, u.post_title 
             FROM {$analysis_table} a 
             JOIN {$urls_table} u ON a.url_id = u.id 
             WHERE u.object_id = %d AND u.object_type = %s 
             AND (a.status = 'analyzed' OR (a.status IS NOT NULL AND a.score IS NOT NULL))
             ORDER BY CASE WHEN a.status = 'analyzed' THEN 0 ELSE 1 END, a.analyzed_at DESC LIMIT 1",
            $object_id, $object_type
        ));

        // DEBUG: Log first query result
        if (!$analysis && $object_id) {
            
            // Check if URL record exists but with different object_id/object_type
            $object_url = null;
            if ($object_type === 'post') {
                $object_url = get_permalink($object_id);
            } elseif ($object_type === 'term') {
                $term_link = get_term_link($object_id);
                if (!is_wp_error($term_link)) {
                    $object_url = $term_link;
                }
            }
            
            if ($object_url) {
                $url_hash = hash('sha256', $object_url);
                
                // Also check if URL record exists with this hash but different object_id
                $url_record_check = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, object_id, object_type, url FROM {$urls_table} WHERE url_hash = %s",
                    $url_hash
                ));
                

                $analysis = $wpdb->get_row($wpdb->prepare(
                    "SELECT a.*, u.url, u.post_title 
                     FROM {$analysis_table} a 
                     JOIN {$urls_table} u ON a.url_id = u.id 
                     WHERE u.url_hash = %s 
                     AND (a.status = 'analyzed' OR (a.status IS NOT NULL AND a.score IS NOT NULL))
                     ORDER BY CASE WHEN a.status = 'analyzed' THEN 0 ELSE 1 END, a.analyzed_at DESC LIMIT 1",
                    $url_hash
                ));
                

            }
        }

        if (!$analysis) {
            return null;
        }

        // Get ALL active items for this URL (issues, improvements, good), grouped by issue_key to avoid duplicates
        // This ensures consistency with the possibilities page which also groups by issue_key
        // Single source of truth: all items come from sb2_seo_issues table
        $all_items = $wpdb->get_results($wpdb->prepare(
            "SELECT i.* 
             FROM {$issues_table} i
             WHERE i.url_id = %d 
             AND (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL)
             GROUP BY i.issue_key
             ORDER BY i.severity DESC, i.created_at DESC",
            $analysis->url_id
        ));

        // Separate items by severity into issues, improvements, and good
        $saved_issues = [];
        $saved_improvements = [];
        $saved_good = [];
        
        foreach ($all_items as $item) {
            $item_data = [
                'key' => $item->issue_key,
                'message' => $item->message,
                'severity' => $item->severity
            ];
            
            // Add extra_data if it exists
            if (!empty($item->extra_data)) {
                $item_data['extra_data'] = json_decode($item->extra_data, true);
            }
            
            // Categorize by severity
            if (in_array($item->severity, ['critical', 'error', 'high', 'warning', 'medium'])) {
                $saved_issues[] = $item_data;
            } elseif ($item->severity === 'low') {
                $saved_improvements[] = $item_data;
            } elseif ($item->severity === 'good') {
                $saved_good[] = $item_data;
            }
        }

        // Convert analyzed_at MySQL datetime to Unix timestamp for JavaScript compatibility
        $analyzed_timestamp = 0;
        if (!empty($analysis->analyzed_at)) {
            $analyzed_timestamp = strtotime($analysis->analyzed_at);
        }

        // Use saved data instead of running fresh analysis
        $results = [
            'score' => $analysis->score, // Use saved score
            'issues' => $saved_issues,   // Use saved issues
            'improvements' => $saved_improvements, // Use saved improvements
            'good' => $saved_good,                 // Use saved good practices
            'content_changed' => $has_pending, // Flag indicating if content has changed since last analysis
            'metadata' => [
                'timestamp' => $analyzed_timestamp, // Unix timestamp for JavaScript compatibility
                'is_full_page' => false, // Will be determined by presence of full page content
                'url' => $analysis->url
            ],
            'db_metadata' => [
                'analysis_id' => $analysis->id,
                'url_id' => $analysis->url_id,
                'analyzed_at' => $analysis->analyzed_at,
                'score' => $analysis->score,
                'issue_count' => $analysis->issue_count,
                'url' => $analysis->url,
                'post_title' => $analysis->post_title
            ]
        ];

        return $results;
    }

    /**
     * Get filtered possibilities from the database.
     *
     * @since 6.1.26
     * @param array $filters Filter options.
     * @return array Possibilities data.
     */
    public static function get_issues($filters = [])
    {
        global $wpdb;

        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';

        $where_conditions = ['1=1'];
        $where_values = [];

        // Severity filter
        if (!empty($filters['severity'])) {
            $where_conditions[] = 'i.severity = %s';
            $where_values[] = $filters['severity'];
        }

        // Status filter - default to active possibilities if no status filter provided
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $where_conditions[] = '(i.user_status = %s OR i.user_status = %s OR i.user_status IS NULL)';
                $where_values[] = 'active';
                $where_values[] = '0';
            } else {
                $where_conditions[] = 'i.user_status = %s';
                $where_values[] = $filters['status'];
            }
        } else {
            // Default to active possibilities only when no status filter is provided
            $where_conditions[] = '(i.user_status = %s OR i.user_status = %s OR i.user_status IS NULL)';
            $where_values[] = 'active';
            $where_values[] = '0';
        }

        // Search filter
        if (!empty($filters['search'])) {
            $where_conditions[] = '(i.message LIKE %s OR u.url LIKE %s OR u.post_title LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        // URL filter
        if (!empty($filters['url'])) {
            $where_conditions[] = 'u.url = %s';
            $where_values[] = $filters['url'];
        }

        // Exclude sitewide issues from regular per-page issues list (only if column exists)
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        // Validate table name and sanitize column name
        $allowed_tables = [$wpdb->prefix . 'sb2_seo_issues'];
        if (in_array($issues_table, $allowed_tables, true)) {
            $table_name_escaped = esc_sql($issues_table);
            $column_name_escaped = esc_sql('is_sitewide');
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM `{$table_name_escaped}` LIKE %s",
                $column_name_escaped
            ));
            if (!empty($column_exists)) {
                $where_conditions[] = 'i.is_sitewide = 0';
            }
        }

        $exclusion_condition = self::get_exclusion_sql_condition('p');
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT i.*, u.url, u.post_title, a.score, a.analyzed_at
                FROM {$issues_table} i 
                JOIN {$urls_table} u ON i.url_id = u.id
                JOIN {$analysis_table} a ON i.analysis_id = a.id 
                LEFT JOIN {$wpdb->posts} p ON u.object_type = 'post' AND u.object_id = p.ID
                WHERE {$where_clause} 
                AND (u.object_type != 'post' OR (p.post_type != 'attachment' AND {$exclusion_condition}) OR p.post_type IS NULL)
                ORDER BY i.severity DESC, i.created_at DESC";

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Update possibility status (bulk or single).
     *
     * @since 6.1.26
     * @param array|int $issue_ids Issue ID(s) to update.
     * @param string $status New status.
     * @return bool True on success, false on failure.
     */
    public static function update_issue_status($issue_ids, $status)
    {
        global $wpdb;

        $issues_table = $wpdb->prefix . 'sb2_seo_issues';

        if (!is_array($issue_ids)) {
            $issue_ids = [$issue_ids];
        }

        $placeholders = implode(',', array_fill(0, count($issue_ids), '%d'));
        // Values must be in order: status first, then issue IDs (to match the SQL: SET user_status = %s ... WHERE id IN (%d,%d,...))
        $values = array_merge([$status], $issue_ids);

        Utils::log("Updating issue status - Table: {$issues_table}, Issue IDs: " . implode(',', $issue_ids) . ", Status: {$status}", 5);
        
        // Track improvement if status is being changed to 'fixed'
        if ($status === 'fixed') {
            self::track_improvement($issue_ids, 'local');
        }
        
        // Check if the table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$issues_table}'");
        if (!$table_exists) {
            Utils::log("Table {$issues_table} does not exist!", 2);
            return false;
        }

        // Check if the issue exists
        $issue_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$issues_table} WHERE id IN ({$placeholders})",
            $issue_ids
        ));
        
        Utils::log("Issue exists check - Found {$issue_exists} matching records", 5);

        if ($issue_exists == 0) {
            Utils::log("No issues found with the provided IDs", 2);
            return false;
        }

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$issues_table} SET user_status = %s, status_updated_at = CURRENT_TIMESTAMP WHERE id IN ({$placeholders})",
            $values
        ));

        Utils::log("Update query result: " . ($result !== false ? $result : 'false') . ", Last error: " . $wpdb->last_error, 5);
        Utils::log("Rows affected: " . $wpdb->rows_affected, 5);

        // Check if query succeeded AND actually updated rows
        if ($result !== false && $wpdb->rows_affected > 0) {
            self::clear_stats_cache();
            Utils::log("Successfully updated {$wpdb->rows_affected} issue(s) to status: {$status}", 5);
            return true;
        }

        if ($result === false) {
            Utils::log("Update query failed: " . $wpdb->last_error, 2);
        } else if ($wpdb->rows_affected == 0) {
            Utils::log("Update query succeeded but no rows were affected. Issue IDs may not exist or status may already be set.", 2);
        }

        return false;
    }

    /**
     * Get analysis statistics.
     *
     * @since 6.1.26
     * @return array Statistics data.
     */
    public static function get_analysis_stats()
    {
        $cache_key = 'sb_seo_analysis_stats';
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;

        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        
        $exclusion_condition = self::get_exclusion_sql_condition('p');

        // Get total analyzed URLs (excluding private/WooCommerce pages)
        $total_analyzed = $wpdb->get_var(
            "SELECT COUNT(DISTINCT a.id)
             FROM {$analysis_table} a
             JOIN {$urls_table} u ON a.url_id = u.id
             LEFT JOIN {$wpdb->posts} p ON u.object_type = 'post' AND u.object_id = p.ID
             WHERE a.status = 'analyzed'
             AND (u.object_type != 'post' OR p.ID IS NULL OR {$exclusion_condition})"
        );

        // Get pending URLs (excluding private/WooCommerce pages)
        $total_pending = $wpdb->get_var(
            "SELECT COUNT(DISTINCT a.id)
             FROM {$analysis_table} a
             JOIN {$urls_table} u ON a.url_id = u.id
             LEFT JOIN {$wpdb->posts} p ON u.object_type = 'post' AND u.object_id = p.ID
             WHERE a.status = 'pending'
             AND (u.object_type != 'post' OR p.ID IS NULL OR {$exclusion_condition})"
        );

        // Get possibility counts by severity (excluding private/WooCommerce pages and "good" severity)
        // "Good" practices are not counted as "possibilities" - they're positive indicators
        // Only count active issues (excludes fixed, ignored_temp, ignored_permanent)
        $severity_counts = $wpdb->get_results(
            "SELECT i.severity, COUNT(*) as count 
             FROM {$issues_table} i
             JOIN {$urls_table} u ON i.url_id = u.id
             LEFT JOIN {$wpdb->posts} p ON u.object_type = 'post' AND u.object_id = p.ID
             WHERE (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL)
             AND i.severity != 'good'
             AND i.is_sitewide = 0
             AND (u.object_type != 'post' OR p.ID IS NULL OR {$exclusion_condition})
             GROUP BY i.severity"
        );

        $stats = [
            'total_analyzed' => (int) $total_analyzed,
            'total_pending' => (int) $total_pending,
            'total_issues' => 0,
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0
        ];

        foreach ($severity_counts as $row) {
            $stats[$row->severity] = (int) $row->count;
            $stats['total_issues'] += (int) $row->count;
        }

        // Cache for 5 minutes
        set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);

        return $stats;
    }


    /**
     * Get all publishable URLs that need analysis.
     *
     * @since 6.1.26
     * @return array URLs that need analysis.
     */
    public static function get_publishable_urls()
    {
        global $wpdb;

        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $urls = [];
        $url_hashes = []; // Track hashes to prevent duplicates

        // Get published posts
        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_type 
             FROM {$wpdb->posts} 
             WHERE post_status = 'publish' 
             AND post_type IN ('post', 'page') 
             ORDER BY post_modified DESC"
        );

        foreach ($posts as $post) {
            // Exclude private posts and WooCommerce special pages
            if (self::should_exclude_from_analysis($post->ID)) {
                continue;
            }

            $url = get_permalink($post->ID);
            if ($url) {
                $url_hash = hash('sha256', $url);
                
                // Skip if already in our list
                if (in_array($url_hash, $url_hashes)) {
                    continue;
                }
                
                // Check if already analyzed using new structure
                $analyzed = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) 
                     FROM {$urls_table} u 
                     JOIN {$analysis_table} a ON u.id = a.url_id 
                     WHERE u.url_hash = %s AND a.status = 'analyzed'",
                    $url_hash
                ));

                if (!$analyzed) {
                    $urls[] = [
                        'url' => $url,
                        'object_id' => $post->ID,
                        'object_type' => 'post',
                        'title' => $post->post_title
                    ];
                    $url_hashes[] = $url_hash;
                }
            }
        }


        return $urls;
    }

    /**
     * Mark analysis as pending for reanalysis.
     *
     * @since 6.1.26
     * @param int|null $object_id Post or term ID.
     * @param string|null $object_type 'post', 'term', or null.
     * @return bool True on success, false on failure.
     */
    public static function mark_as_pending($object_id, $object_type)
    {
        global $wpdb;

        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';

        // First, find the URL record that matches the object_id and object_type
        $url_record = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$urls_table} WHERE object_id = %s AND object_type = %s",
            $object_id, $object_type
        ));

        if (!$url_record) {
            // No URL record found for this object, nothing to mark as pending
            return true;
        }

        // Update all analysis records for this URL to pending status
        $result = $wpdb->update(
            $analysis_table,
            ['status' => 'pending'],
            ['url_id' => $url_record->id]
        );

        if ($result !== false) {
            self::clear_stats_cache();
            return true;
        }

        return false;
    }

    /**
     * Check if analysis is pending for an object.
     *
     * @since 6.1.26
     * @param int|null $object_id Post or term ID.
     * @param string|null $object_type 'post', 'term', or null.
     * @return bool True if analysis is pending, false otherwise.
     */
    public static function has_pending_analysis($object_id, $object_type)
    {
        global $wpdb;

        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';

        // Find the URL record that matches the object_id and object_type
        $url_record = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$urls_table} WHERE object_id = %s AND object_type = %s",
            $object_id, $object_type
        ));

        if (!$url_record) {
            // No URL record found, no pending analysis
            return false;
        }

        // Check if there's a pending analysis for this URL
        $pending_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$analysis_table} WHERE url_id = %d AND status = 'pending'",
            $url_record->id
        ));

        if ($pending_count > 0) {
            return true;
        }

        // Also check if post content has changed since last analysis
        if ($object_type === 'post' && $object_id) {
            $post = get_post($object_id);
            if ($post && $post->post_modified) {
                // Get the most recent analyzed analysis
                $latest_analysis = $wpdb->get_row($wpdb->prepare(
                    "SELECT analyzed_at FROM {$analysis_table} 
                     WHERE url_id = %d 
                     AND (status = 'analyzed' OR (status IS NOT NULL AND score IS NOT NULL))
                     ORDER BY analyzed_at DESC LIMIT 1",
                    $url_record->id
                ));

                if ($latest_analysis && $latest_analysis->analyzed_at) {
                    $post_modified_time = strtotime($post->post_modified);
                    $analysis_time = strtotime($latest_analysis->analyzed_at);
                    
                    // If post was modified after analysis, content has changed
                    if ($post_modified_time > $analysis_time) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Map severity levels to standardized values.
     *
     * @since 6.1.26
     * @param string $severity Original severity.
     * @return string Mapped severity.
     */
    private static function map_severity($severity)
    {
        $mapping = [
            'error' => 'critical',
            'warning' => 'high',
            'improvement' => 'medium',
            'good' => 'low'
        ];

        return $mapping[$severity] ?? 'medium';
    }

    /**
     * Get URLs with their possibility counts and details.
     *
     * @since 6.1.26
     * @param array $filters Filter options.
     * @param int $per_page Items per page.
     * @param int $offset Offset for pagination.
     * @return array URLs with possibility data.
     */
    public static function get_urls_with_issues($filters = [], $per_page = 50, $offset = 0, $orderby = 'total_issues', $order = 'DESC')
    {
        global $wpdb;

        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';

        $where_conditions = ['1=1'];
        $where_values = [];

        // Only show URLs with possibilities
        $where_conditions[] = 'i.id IS NOT NULL';

        // Severity filter
        if (!empty($filters['severity'])) {
            $where_conditions[] = 'i.severity = %s';
            $where_values[] = $filters['severity'];
        }

        // Status filter - only apply if explicitly requested
        // When no status filter, show URLs with ANY issues (active or fixed) but count only active ones
        // Always exclude permanently ignored issues unless explicitly requested
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $where_conditions[] = '(i.user_status = %s OR i.user_status = %s OR i.user_status IS NULL)';
                $where_values[] = 'active';
                $where_values[] = '0';
            } elseif ($filters['status'] === 'ignored_permanent') {
                $where_conditions[] = 'i.user_status = %s';
                $where_values[] = 'ignored_permanent';
            } else {
                $where_conditions[] = 'i.user_status = %s';
                $where_values[] = $filters['status'];
            }
        } else {
            // Default: exclude permanently ignored issues
            $where_conditions[] = '(i.user_status != %s OR i.user_status IS NULL)';
            $where_values[] = 'ignored_permanent';
        }

        // Search filter
        if (!empty($filters['search'])) {
            $where_conditions[] = '(u.url LIKE %s OR u.post_title LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // Build the JOIN condition - include ALL issues (active and fixed) when no status filter
        // When status filter is provided, filter accordingly
        // Always exclude permanently ignored issues unless explicitly requested
        $issue_join_condition = '';
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $issue_join_condition = "AND (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL)";
            } elseif ($filters['status'] === 'ignored_permanent') {
                $issue_join_condition = "AND i.user_status = 'ignored_permanent'";
            } else {
                $issue_join_condition = "AND i.user_status = '" . esc_sql($filters['status']) . "'";
            }
        } else {
            // Default: exclude permanently ignored issues
            $issue_join_condition = "AND (i.user_status != 'ignored_permanent' OR i.user_status IS NULL)";
        }
        // If no status filter, include ALL issues (active and fixed) so URLs with fixed issues still appear

        $exclusion_condition = self::get_exclusion_sql_condition('p');
        
        $sql = "SELECT 
                    u.id,
                    u.url,
                    u.post_title,
                    u.object_id,
                    u.object_type,
                    u.last_analyzed,
                    COUNT(DISTINCT CASE WHEN i.severity != 'good' AND (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL) AND i.issue_key IS NOT NULL THEN i.issue_key END) as total_issues,
                    COUNT(DISTINCT CASE WHEN i.severity = 'critical' AND (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL) AND i.issue_key IS NOT NULL THEN i.issue_key END) as critical_count,
                    COUNT(DISTINCT CASE WHEN i.severity = 'high' AND (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL) AND i.issue_key IS NOT NULL THEN i.issue_key END) as high_count,
                    COUNT(DISTINCT CASE WHEN i.severity = 'medium' AND (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL) AND i.issue_key IS NOT NULL THEN i.issue_key END) as medium_count,
                    COUNT(DISTINCT CASE WHEN i.severity = 'low' AND (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL) AND i.issue_key IS NOT NULL THEN i.issue_key END) as low_count,
                    a.score,
                    a.analyzed_at
                FROM {$urls_table} u
                LEFT JOIN {$analysis_table} a ON u.id = a.url_id AND a.status = 'analyzed'
                LEFT JOIN {$issues_table} i ON u.id = i.url_id AND i.severity != 'good' {$issue_join_condition}
                LEFT JOIN {$wpdb->posts} p ON u.object_type = 'post' AND u.object_id = p.ID
                WHERE {$where_clause}
                AND (u.object_type != 'post' OR (p.post_type != 'attachment' AND {$exclusion_condition}) OR p.post_type IS NULL)
                GROUP BY u.id";
        
        // Show URLs that have ANY issues (active or fixed), but counts only include active issues
        $sql .= " HAVING COUNT(DISTINCT CASE WHEN (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL) AND i.issue_key IS NOT NULL THEN i.issue_key END) > 0";
        
        // Handle sorting
        $order_safe = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        
        // Validate orderby to prevent SQL injection
        $allowed_orderby = ['url', 'score', 'total_issues'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'total_issues';
        }
        
        switch ($orderby) {
            case 'url':
                $orderby_safe = 'u.url';
                $sql .= " ORDER BY {$orderby_safe} {$order_safe}, u.last_analyzed DESC";
                break;
            case 'score':
                // For score sorting, use COALESCE to put NULLs last (treat NULL as -1)
                // Higher scores first for DESC, lower scores first for ASC
                if ($order_safe === 'DESC') {
                    $sql .= " ORDER BY COALESCE(a.score, -1) DESC, u.last_analyzed DESC";
                } else {
                    $sql .= " ORDER BY COALESCE(a.score, 999) ASC, u.last_analyzed DESC";
                }
                break;
            case 'total_issues':
            default:
                $orderby_safe = 'total_issues';
                $sql .= " ORDER BY {$orderby_safe} {$order_safe}, u.last_analyzed DESC";
                break;
        }
        
        $sql .= " LIMIT %d OFFSET %d";

        $values = array_merge($where_values, [$per_page, $offset]);
        $sql = $wpdb->prepare($sql, $values);

        return $wpdb->get_results($sql);
    }

    /**
     * Get total count of URLs with possibilities.
     *
     * @since 6.1.26
     * @param array $filters Filter options.
     * @return int Total count.
     */
    public static function get_urls_with_issues_count($filters = [])
    {
        global $wpdb;

        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';

        $where_conditions = ['1=1'];
        $where_values = [];

        // Only show URLs with possibilities
        $where_conditions[] = 'i.id IS NOT NULL';

        // Severity filter
        if (!empty($filters['severity'])) {
            $where_conditions[] = 'i.severity = %s';
            $where_values[] = $filters['severity'];
        }

        // Status filter - only apply if explicitly requested
        // When no status filter, show URLs with ANY issues (active or fixed) but count only active ones
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $where_conditions[] = '(i.user_status = %s OR i.user_status = %s OR i.user_status IS NULL)';
                $where_values[] = 'active';
                $where_values[] = '0';
            } else {
                $where_conditions[] = 'i.user_status = %s';
                $where_values[] = $filters['status'];
            }
        }
        // No default status filter - we want to show URLs with fixed issues too

        // Search filter
        if (!empty($filters['search'])) {
            $where_conditions[] = '(u.url LIKE %s OR u.post_title LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // Build the JOIN condition - include ALL issues (active and fixed) when no status filter
        // When status filter is provided, filter accordingly
        $issue_join_condition = '';
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $issue_join_condition = "AND (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL)";
            } else {
                $issue_join_condition = "AND i.user_status = '" . esc_sql($filters['status']) . "'";
            }
        }
        // If no status filter, include ALL issues (active and fixed) so URLs with fixed issues still appear

        $exclusion_condition = self::get_exclusion_sql_condition('p');
        
        // Only show URLs with possibilities (excluding "good" severity)
        $sql = "SELECT COUNT(*) FROM (
            SELECT u.id
            FROM {$urls_table} u
            LEFT JOIN {$analysis_table} a ON u.id = a.url_id AND a.status = 'analyzed'
            LEFT JOIN {$issues_table} i ON u.id = i.url_id AND i.severity != 'good' {$issue_join_condition}
            LEFT JOIN {$wpdb->posts} p ON u.object_type = 'post' AND u.object_id = p.ID
            WHERE {$where_clause}
            AND (u.object_type != 'post' OR (p.post_type != 'attachment' AND {$exclusion_condition}) OR p.post_type IS NULL)
            GROUP BY u.id
            HAVING COUNT(DISTINCT i.id) > 0
        ) as url_counts";

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get all possibilities for a specific URL.
     *
     * @since 6.1.26
     * @param int $url_id URL ID.
     * @return array Possibilities grouped by severity.
     */
    public static function get_issues_for_url($url_id)
    {
        global $wpdb;

        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';

        $exclusion_condition = self::get_exclusion_sql_condition('p');
        
        $sql = "SELECT i.id, i.issue_key, i.message, i.severity, i.user_status, i.extra_data, i.created_at
                FROM {$issues_table} i
                JOIN {$urls_table} u ON i.url_id = u.id
                LEFT JOIN {$wpdb->posts} p ON u.object_type = 'post' AND u.object_id = p.ID
                WHERE i.url_id = %d 
                AND i.severity in ('critical', 'high', 'medium', 'low', 'good')
                AND (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL)
                AND (u.object_type != 'post' OR (p.post_type != 'attachment' AND {$exclusion_condition}) OR p.post_type IS NULL)
                GROUP BY i.issue_key
                ORDER BY i.severity DESC, i.created_at DESC";

        $issues = $wpdb->get_results($wpdb->prepare($sql, $url_id));

        // Convert to array format for merging
        $local_issues = [];
        foreach ($issues as $issue) {
            $local_issues[] = (array) $issue;
        }

        // GSC issues are now integrated into individual SEO analysis
        $merged_issues = $local_issues;

        // Group by severity
        $grouped_issues = [
            'critical' => [],
            'high' => [],
            'medium' => [],
            'low' => [],
            'good' => []
        ];

        foreach ($merged_issues as $issue) {
            $severity = isset($issue['severity']) ? $issue['severity'] : 'medium';
            if (isset($grouped_issues[$severity])) {
                // Convert to object if needed for consistency
                $issue_obj = is_array($issue) ? (object) $issue : $issue;
                $grouped_issues[$severity][] = $issue_obj;
            }
        }

        return $grouped_issues;
    }

    /**
     * Check if a post should be excluded from SEO analysis.
     *
     * @since 6.1.26
     * @param int $post_id Post ID to check.
     * @return bool True if post should be excluded, false otherwise.
     */
    public static function should_exclude_from_analysis($post_id)
    {
        // Return false if post_id is invalid
        if (!$post_id || $post_id <= 0) {
            return false;
        }

        // Check user-defined exclusion preference first (highest priority)
        $user_excluded = get_post_meta($post_id, '_sb_exclude_from_analysis', true);
        if ($user_excluded === '1') {
            return true;
        }

        // Check if post status is 'private'
        $post_status = get_post_status($post_id);
        if ($post_status === 'private') {
            return true;
        }

        // Check if WooCommerce is active and this is a WooCommerce special page
        if (function_exists('wc_get_page_id')) {
            $wc_page_types = ['cart', 'checkout', 'myaccount', 'shop'];
            foreach ($wc_page_types as $page_type) {
                $wc_page_id = wc_get_page_id($page_type);
                if ($wc_page_id && $post_id === $wc_page_id) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a post is auto-excluded (due to WooCommerce or private status).
     *
     * @since 7.0.0
     * @param int $post_id Post ID to check.
     * @return bool True if post is auto-excluded, false otherwise.
     */
    public static function is_auto_excluded($post_id)
    {
        // Return false if post_id is invalid
        if (!$post_id || $post_id <= 0) {
            return false;
        }

        // Check if post status is 'private'
        $post_status = get_post_status($post_id);
        if ($post_status === 'private') {
            return true;
        }

        // Check if WooCommerce is active and this is a WooCommerce special page
        if (function_exists('wc_get_page_id')) {
            $wc_page_types = ['cart', 'checkout', 'myaccount', 'shop'];
            foreach ($wc_page_types as $page_type) {
                $wc_page_id = wc_get_page_id($page_type);
                if ($wc_page_id && $post_id === $wc_page_id) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get SQL condition to exclude private posts, WooCommerce special pages, and user-excluded posts.
     *
     * @since 6.1.26
     * @param string $post_table_alias Table alias for posts table (default: 'p').
     * @return string SQL condition string.
     */
    private static function get_exclusion_sql_condition($post_table_alias = 'p')
    {
        global $wpdb;
        
        $exclusions = [];
        
        // Exclude private posts
        $exclusions[] = "{$post_table_alias}.post_status != 'private'";
        
        // Exclude trash posts
        $exclusions[] = "{$post_table_alias}.post_status != 'trash'";
        
        // Exclude WooCommerce special pages
        if (function_exists('wc_get_page_id')) {
            $wc_page_ids = [];
            $wc_page_types = ['cart', 'checkout', 'myaccount', 'shop'];
            foreach ($wc_page_types as $page_type) {
                $wc_page_id = wc_get_page_id($page_type);
                if ($wc_page_id) {
                    $wc_page_ids[] = (int) $wc_page_id;
                }
            }
            
            if (!empty($wc_page_ids)) {
                $wc_page_ids_str = implode(',', array_map('intval', $wc_page_ids));
                $exclusions[] = "{$post_table_alias}.ID NOT IN ({$wc_page_ids_str})";
            }
        }
        
        // Exclude posts with user-defined exclusion preference
        $exclusions[] = "NOT EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pm 
            WHERE pm.post_id = {$post_table_alias}.ID 
            AND pm.meta_key = '_sb_exclude_from_analysis' 
            AND pm.meta_value = '1'
        )";
        
        if (empty($exclusions)) {
            return '1=1'; // No exclusions needed
        }
        
        return '(' . implode(' AND ', $exclusions) . ')';
    }

    /**
     * Save sitewide analysis results to the database.
     *
     * @since 7.0.0
     * @param array $results Analysis results from SEO_Sitewide_Analysis.
     * @return int|false Analysis ID on success, false on failure.
     */
    public static function save_sitewide_analysis_to_db($results)
    {
        global $wpdb;

        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';

        $homepage_url = home_url('/');
        $url_hash = hash('sha256', $homepage_url);

        // Get or create URL record for homepage (sitewide issues use homepage URL)
        $url_record = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$urls_table} WHERE url_hash = %s",
            $url_hash
        ));

        if (!$url_record) {
            // Insert new URL record for homepage
            $url_result = $wpdb->insert($urls_table, [
                'url' => $homepage_url,
                'url_hash' => $url_hash,
                'object_id' => null,
                'object_type' => null,
                'post_title' => __('Homepage (Sitewide)', 'seo-booster'),
                'last_analyzed' => current_time('mysql'),
                'analysis_count' => 1
            ]);
            
            if ($url_result === false) {
                Utils::log('Failed to save homepage URL record for sitewide analysis', 2);
                return false;
            }
            
            $url_id = $wpdb->insert_id;
        } else {
            $url_id = $url_record->id;
            
            // Update URL record
            $wpdb->update($urls_table, [
                'last_analyzed' => current_time('mysql'),
                'analysis_count' => $wpdb->get_var($wpdb->prepare(
                    "SELECT analysis_count FROM {$urls_table} WHERE id = %d", $url_id
                )) + 1
            ], ['id' => $url_id]);
        }

        // Count total possibilities (excluding "good" severity - those are positive indicators, not issues)
        $possibility_count = 0;
        if (!empty($results['issues'])) {
            foreach ($results['issues'] as $issue) {
                // Only count issues that are not "good" severity
                if (isset($issue['severity']) && $issue['severity'] !== 'good') {
                    $possibility_count++;
                }
            }
        }

        // Build insert data - improvements and good items are now stored in sb2_seo_issues table only
        $insert_data = [
            'url_id' => $url_id,
            'score' => $results['score'] ?? 0,
            'issue_count' => $possibility_count,
            'status' => 'analyzed'
        ];

        // Insert new analysis record
        $analysis_result = $wpdb->insert($analysis_table, $insert_data);

        if ($analysis_result === false) {
            Utils::log('Failed to save sitewide SEO analysis to database', 2);
            return false;
        }

        $analysis_id = $wpdb->insert_id;

        // Remove ALL active sitewide possibilities to prevent duplicates
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$issues_table} 
             WHERE url_id = %d 
             AND is_sitewide = 1
             AND (user_status = 'active' OR user_status = '0' OR user_status IS NULL)",
            $url_id
        ));

        // Collect unique possibility keys (we save "good" severity items too, but they're not counted)
        $unique_possibilities = [];
        if (!empty($results['issues'])) {
            foreach ($results['issues'] as $possibility) {
                $possibility_key = $possibility['key'] ?? '';
                if (!empty($possibility_key) && !isset($unique_possibilities[$possibility_key])) {
                    $unique_possibilities[$possibility_key] = $possibility;
                }
            }
        }

        // Insert the unique possibilities as sitewide issues
        // Note: We save "good" severity items too, but they're not counted in possibility_count
        foreach ($unique_possibilities as $possibility_key => $possibility) {
            $severity = self::map_severity($possibility['severity'] ?? 'warning');
            $message = $possibility['message'] ?? '';
            $extra_data = isset($possibility['extra_data']) ? json_encode($possibility['extra_data']) : null;
            
            $wpdb->insert($issues_table, [
                'analysis_id' => $analysis_id,
                'url_id' => $url_id,
                'is_sitewide' => 1,
                'issue_key' => $possibility_key,
                'message' => $message,
                'severity' => $severity,
                'user_status' => 'active',
                'extra_data' => $extra_data
            ]);
        }

        // Also save improvements and good practices as sitewide issues (with lower severity)
        if (!empty($results['improvements'])) {
            foreach ($results['improvements'] as $improvement) {
                $improvement_key = $improvement['key'] ?? '';
                if (!empty($improvement_key)) {
                    $wpdb->insert($issues_table, [
                        'analysis_id' => $analysis_id,
                        'url_id' => $url_id,
                        'is_sitewide' => 1,
                        'issue_key' => $improvement_key,
                        'message' => $improvement['message'] ?? '',
                        'severity' => 'low',
                        'user_status' => 'active',
                        'extra_data' => null
                    ]);
                }
            }
        }

        if (!empty($results['good'])) {
            foreach ($results['good'] as $good) {
                $good_key = $good['key'] ?? '';
                if (!empty($good_key)) {
                    $wpdb->insert($issues_table, [
                        'analysis_id' => $analysis_id,
                        'url_id' => $url_id,
                        'is_sitewide' => 1,
                        'issue_key' => $good_key,
                        'message' => $good['message'] ?? '',
                        'severity' => 'good',
                        'user_status' => 'active',
                        'extra_data' => null
                    ]);
                }
            }
        }

        // Clear cache
        self::clear_stats_cache();

        Utils::log("Saved sitewide SEO analysis with {$possibility_count} possibilities", 3);
        return $analysis_id;
    }

    /**
     * Get sitewide issues from the database.
     *
     * @since 7.0.0
     * @return array Sitewide issues data.
     */
    public static function get_sitewide_issues()
    {
        global $wpdb;

        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        
        // Check if is_sitewide column exists - validate table name
        $allowed_tables = [$wpdb->prefix . 'sb2_seo_issues'];
        $column_exists = [];
        if (in_array($issues_table, $allowed_tables, true)) {
            $table_name_escaped = esc_sql($issues_table);
            $column_name_escaped = esc_sql('is_sitewide');
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM `{$table_name_escaped}` LIKE %s",
                $column_name_escaped
            ));
        }
        
        if (empty($column_exists)) {
            // Column doesn't exist yet, trigger migration
            Utils::create_database_tables();
            // Re-check after migration
            if (in_array($issues_table, $allowed_tables, true)) {
                $table_name_escaped = esc_sql($issues_table);
                $column_name_escaped = esc_sql('is_sitewide');
                $column_exists = $wpdb->get_results($wpdb->prepare(
                    "SHOW COLUMNS FROM `{$table_name_escaped}` LIKE %s",
                    $column_name_escaped
                ));
            }
            if (empty($column_exists)) {
                // Still doesn't exist, return empty array
                Utils::log('is_sitewide column does not exist and migration failed', 2);
                return [];
            }
        }

        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';

        $sql = "SELECT i.*, u.url, u.post_title, a.score, a.analyzed_at
                FROM {$issues_table} i 
                JOIN {$urls_table} u ON i.url_id = u.id
                JOIN {$analysis_table} a ON i.analysis_id = a.id 
                WHERE i.is_sitewide = 1
                AND (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL)
                ORDER BY 
                    CASE i.severity 
                        WHEN 'critical' THEN 1 
                        WHEN 'error' THEN 2 
                        WHEN 'high' THEN 3 
                        WHEN 'warning' THEN 4 
                        WHEN 'medium' THEN 5 
                        WHEN 'low' THEN 6 
                        WHEN 'good' THEN 7 
                        ELSE 8 
                    END,
                    i.created_at DESC";

        return $wpdb->get_results($sql);
    }

    /**
     * Get sitewide analysis stats.
     *
     * @since 7.0.0
     * @return array Statistics about sitewide issues.
     */
    public static function get_sitewide_stats()
    {
        global $wpdb;

        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        
        // Check if is_sitewide column exists - validate table name
        $allowed_tables = [$wpdb->prefix . 'sb2_seo_issues'];
        $column_exists = [];
        if (in_array($issues_table, $allowed_tables, true)) {
            $table_name_escaped = esc_sql($issues_table);
            $column_name_escaped = esc_sql('is_sitewide');
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM `{$table_name_escaped}` LIKE %s",
                $column_name_escaped
            ));
        }
        
        if (empty($column_exists)) {
            // Column doesn't exist yet, trigger migration
            Utils::create_database_tables();
            // Re-check after migration
            if (in_array($issues_table, $allowed_tables, true)) {
                $table_name_escaped = esc_sql($issues_table);
                $column_name_escaped = esc_sql('is_sitewide');
                $column_exists = $wpdb->get_results($wpdb->prepare(
                    "SHOW COLUMNS FROM `{$table_name_escaped}` LIKE %s",
                    $column_name_escaped
                ));
            }
            if (empty($column_exists)) {
                // Still doesn't exist, return empty stats
                Utils::log('is_sitewide column does not exist and migration failed', 2);
                return [
                    'total_issues' => 0,
                    'critical' => 0,
                    'error' => 0,
                    'high' => 0,
                    'warning' => 0,
                    'medium' => 0,
                    'low' => 0,
                    'good' => 0
                ];
            }
        }

        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_issues,
                SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
                SUM(CASE WHEN severity = 'error' THEN 1 ELSE 0 END) as error,
                SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high,
                SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) as warning,
                SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium,
                SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low,
                SUM(CASE WHEN severity = 'good' THEN 1 ELSE 0 END) as good
            FROM {$issues_table}
            WHERE is_sitewide = 1
            AND (user_status = 'active' OR user_status = '0' OR user_status IS NULL)",
            ARRAY_A
        );

        return $stats ?: [
            'total_issues' => 0,
            'critical' => 0,
            'error' => 0,
            'high' => 0,
            'warning' => 0,
            'medium' => 0,
            'low' => 0,
            'good' => 0
        ];
    }

    /**
     * Get top possibilities for dashboard display.
     * Returns the most interesting/important possibilities grouped by type.
     *
     * @since 6.1.26
     * @param int $limit Maximum number of possibility types to return.
     * @return array Top possibilities grouped by type with affected URL counts.
     */
    public static function get_top_possibilities_for_dashboard($limit = 5)
    {
        global $wpdb;

        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';

        $exclusion_condition = self::get_exclusion_sql_condition('p');
        
        // Get possibilities grouped by type, prioritizing by severity and affected URLs
        $sql = "SELECT 
                    i.issue_key,
                    i.message,
                    i.severity,
                    COUNT(DISTINCT i.url_id) as affected_urls,
                    COUNT(i.id) as total_instances,
                    GROUP_CONCAT(DISTINCT u.post_title ORDER BY u.post_title SEPARATOR ', ') as sample_titles
                FROM {$issues_table} i
                JOIN {$urls_table} u ON i.url_id = u.id
                LEFT JOIN {$wpdb->posts} p ON u.object_type = 'post' AND u.object_id = p.ID
                WHERE (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL)
                AND i.severity != 'good'
                AND i.is_sitewide = 0
                AND (u.object_type != 'post' OR (p.post_type != 'attachment' AND {$exclusion_condition}) OR p.post_type IS NULL)
                GROUP BY i.issue_key, i.message, i.severity
                ORDER BY 
                    CASE i.severity 
                        WHEN 'critical' THEN 1 
                        WHEN 'error' THEN 2 
                        WHEN 'high' THEN 3 
                        WHEN 'warning' THEN 4 
                        WHEN 'medium' THEN 5 
                        WHEN 'low' THEN 6 
                        ELSE 7 
                    END,
                    affected_urls DESC
                LIMIT %d";

        $results = $wpdb->get_results($wpdb->prepare($sql, $limit));

        // Format results for display
        $formatted = [];
        foreach ($results as $result) {
            $formatted[] = [
                'issue_key' => $result->issue_key,
                'message' => $result->message,
                'severity' => $result->severity,
                'affected_urls' => (int) $result->affected_urls,
                'total_instances' => (int) $result->total_instances,
                'sample_titles' => $result->sample_titles
            ];
        }

        return $formatted;
    }

    /**
     * Clear statistics cache.
     *
     * @since 6.1.26
     * @return void
     */
    private static function clear_stats_cache()
    {
        delete_transient('sb_seo_analysis_stats');
    }


    /**
     * Track improvement when issues are fixed.
     *
     * @since 6.2.0
     * @param array|int $issue_ids Issue ID(s) that were fixed.
     * @param string $issue_type Type of issue (deprecated, kept for backward compatibility).
     * @return void
     */
    public static function track_improvement($issue_ids, $issue_type = 'local')
    {
        global $wpdb;

        if (!is_array($issue_ids)) {
            $issue_ids = [$issue_ids];
        }

        if (empty($issue_ids)) {
            return;
        }

        $tracking_table = $wpdb->prefix . 'sb2_improvements_tracking';
        $today = current_time('Y-m-d');

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$tracking_table}'");
        if (!$table_exists) {
            return; // Table doesn't exist yet, skip tracking
        }

        // Get or create today's record
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tracking_table} WHERE date = %s",
            $today
        ));

        $count = count($issue_ids);

        if ($record) {
            // Update existing record
            $update_data = [
                'improvements_count' => $record->improvements_count + $count,
                'total_issues_resolved' => $record->total_issues_resolved + $count,
            ];

            $update_data['local_issues_fixed'] = $record->local_issues_fixed + $count;

            $wpdb->update(
                $tracking_table,
                $update_data,
                ['id' => $record->id],
                ['%d', '%d', '%d'],
                ['%d']
            );
        } else {
            // Create new record
            $insert_data = [
                'date' => $today,
                'improvements_count' => $count,
                'total_issues_resolved' => $count,
                'gsc_issues_fixed' => 0,
                'local_issues_fixed' => $count,
            ];

            $wpdb->insert(
                $tracking_table,
                $insert_data,
                ['%s', '%d', '%d', '%d', '%d']
            );
        }
    }
}

