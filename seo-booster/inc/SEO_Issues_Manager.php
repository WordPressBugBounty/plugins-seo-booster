<?php

namespace Cleverplugins\SEOBooster;

use Cleverplugins\SEOBooster\Analysis\Ai_Readiness_Registry;
use Cleverplugins\SEOBooster\Analysis\Page_Reachability;
use Cleverplugins\SEOBooster\Analysis\Severity;
use Cleverplugins\SEOBooster\Tools\Tools_Page;
if ( !defined( 'ABSPATH' ) ) {
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
class SEO_Issues_Manager {
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
    public static function save_analysis_to_db(
        $object_id,
        $object_type,
        $url,
        $results
    ) {
        global $wpdb;
        // Exclude private posts and WooCommerce special pages
        if ( $object_id && $object_type === 'post' && self::should_exclude_from_analysis( $object_id ) ) {
            return false;
        }
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        // Get post title for display
        $post_title = null;
        $is_attachment = false;
        if ( $object_id && $object_type === 'post' ) {
            $post = get_post( $object_id );
            $post_title = ( $post ? $post->post_title : null );
            $is_attachment = $post && $post->post_type === 'attachment';
        } elseif ( $object_id && $object_type === 'term' ) {
            $term = get_term( $object_id );
            $post_title = ( $term && !is_wp_error( $term ) ? $term->name : null );
        }
        // Create URL hash for efficient lookups
        $url_hash = hash( 'sha256', $url );
        $reachability_fields = self::extract_reachability_fields( $results );
        // Get or create URL record
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
        $url_record = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$urls_table} WHERE url_hash = %s", $url_hash ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( !$url_record ) {
            // Insert new URL record
            $url_result = $wpdb->insert( $urls_table, array_merge( array(
                'url'            => $url,
                'url_hash'       => $url_hash,
                'object_id'      => $object_id,
                'object_type'    => $object_type,
                'post_title'     => $post_title,
                'last_analyzed'  => current_time( 'mysql' ),
                'analysis_count' => 1,
            ), $reachability_fields ) );
            if ( $url_result === false ) {
                return false;
            }
            $url_id = $wpdb->insert_id;
        } else {
            $url_id = $url_record->id;
            // Update URL record
            $wpdb->update( $urls_table, array_merge( array(
                'object_id'      => $object_id,
                'object_type'    => $object_type,
                'post_title'     => $post_title,
                'last_analyzed'  => current_time( 'mysql' ),
                'analysis_count' => $wpdb->get_var( $wpdb->prepare( "SELECT analysis_count FROM {$urls_table} WHERE id = %d", $url_id ) ) + 1,
            ), $reachability_fields ), array(
                'id' => $url_id,
            ) );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        // When bulk analysis deferred URL Inspection, keep prior inspection findings (do not replace with N/A).
        $preserve_gsc_inspection = self::results_deferred_gsc_inspection( $results );
        $preserved_gsc_keys = array();
        if ( $preserve_gsc_inspection && $url_id ) {
            $preserved_gsc_keys = self::get_active_gsc_inspection_issue_keys( $url_id );
        }
        // Count total possibilities (excluding "good" severity - those are positive indicators, not issues)
        // Don't save possibilities for attachments - they shouldn't be listed as having possibilities
        $possibility_count = 0;
        if ( !$is_attachment && !empty( $results['issues'] ) ) {
            foreach ( $results['issues'] as $issue ) {
                // Only count issues that are not "good" severity
                if ( isset( $issue['severity'] ) && $issue['severity'] !== 'good' ) {
                    ++$possibility_count;
                }
            }
        }
        if ( !$is_attachment && !empty( $preserved_gsc_keys ) ) {
            $possibility_count += self::count_issue_severity_rows_for_keys( $url_id, $preserved_gsc_keys );
        }
        // Build insert data - improvements and good items are now stored in sb2_seo_issues table only
        $insert_data = array(
            'url_id'      => $url_id,
            'score'       => $results['score'] ?? 0,
            'issue_count' => $possibility_count,
            'status'      => 'analyzed',
        );
        // Clear any pending analysis records for this URL before inserting new one
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$analysis_table} WHERE url_id = %d AND status = 'pending'", $url_id ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // Insert new analysis record
        $analysis_result = $wpdb->insert( $analysis_table, $insert_data );
        if ( $analysis_result === false ) {
            return false;
        }
        $analysis_id = $wpdb->insert_id;
        // Remove active per-URL possibilities only: never wipe sitewide rows that share this url_id.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
        if ( !empty( $preserved_gsc_keys ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $preserved_gsc_keys ), '%s' ) );
            $params = array_merge( array($url_id), array_values( $preserved_gsc_keys ) );
            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic IN list built from count($preserved_gsc_keys).
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$issues_table}\n             WHERE url_id = %d\n             AND is_sitewide = 0\n             AND (user_status = 'active' OR user_status = '0' OR user_status IS NULL)\n             AND issue_key NOT IN ({$placeholders})", ...$params ) );
        } else {
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$issues_table} \n             WHERE url_id = %d \n             AND is_sitewide = 0\n             AND (user_status = 'active' OR user_status = '0' OR user_status IS NULL)", $url_id ) );
        }
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // Don't save possibilities for attachments
        if ( !$is_attachment ) {
            // Collect unique issue keys (deduplicate across buckets).
            // Preserve user-marked fixed/ignored keys so re-analysis does not resurrect them.
            $inserted_keys = array();
            foreach ( self::get_protected_issue_keys_for_url( $url_id ) as $protected_key ) {
                $inserted_keys[$protected_key] = true;
            }
            foreach ( $preserved_gsc_keys as $gsc_key ) {
                $inserted_keys[$gsc_key] = true;
            }
            self::insert_analysis_items(
                $issues_table,
                $analysis_id,
                $url_id,
                $results['issues'] ?? array(),
                $inserted_keys,
                true
            );
            self::insert_analysis_items(
                $issues_table,
                $analysis_id,
                $url_id,
                $results['opportunities'] ?? array(),
                $inserted_keys,
                false,
                'opportunity'
            );
            self::insert_analysis_items(
                $issues_table,
                $analysis_id,
                $url_id,
                $results['good'] ?? array(),
                $inserted_keys,
                false,
                'good'
            );
            self::insert_analysis_items(
                $issues_table,
                $analysis_id,
                $url_id,
                $results['not_applicable'] ?? array(),
                $inserted_keys,
                false,
                'not_applicable'
            );
            // Backward compatibility: legacy analyses may only have improvements without opportunities.
            if ( empty( $results['opportunities'] ) && !empty( $results['improvements'] ) ) {
                self::insert_analysis_items(
                    $issues_table,
                    $analysis_id,
                    $url_id,
                    $results['improvements'],
                    $inserted_keys,
                    false,
                    'opportunity'
                );
            }
        }
        // Clear cache
        self::clear_stats_cache();
        return $analysis_id;
    }

    /**
     * Whether this save payload deferred GSC URL Inspection (bulk, no cache).
     *
     * @param array $results Analysis results payload.
     * @return bool
     */
    private static function results_deferred_gsc_inspection( $results ) {
        foreach ( $results['not_applicable'] ?? array() as $item ) {
            if ( isset( $item['key'] ) && 'gsc_status_skipped_bulk_deferred' === $item['key'] ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Active inspection-related GSC issue keys for a URL (to preserve on bulk defer).
     *
     * @param int $url_id URL row ID.
     * @return string[]
     */
    private static function get_active_gsc_inspection_issue_keys( $url_id ) {
        global $wpdb;
        $url_id = (int) $url_id;
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug.
        $keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT issue_key FROM {$issues_table}\n\t\t\t\tWHERE url_id = %d\n\t\t\t\tAND is_sitewide = 0\n\t\t\t\tAND (user_status = 'active' OR user_status = '0' OR user_status IS NULL)\n\t\t\t\tAND (\n\t\t\t\t\tissue_key LIKE 'gsc_indexing_%%'\n\t\t\t\t\tOR issue_key LIKE 'gsc_structured_%%'\n\t\t\t\t\tOR issue_key LIKE 'gsc_fetch_%%'\n\t\t\t\t\tOR issue_key LIKE 'gsc_coverage_%%'\n\t\t\t\t\tOR issue_key LIKE 'gsc_canonical_%%'\n\t\t\t\t\tOR issue_key LIKE 'gsc_soft_404%%'\n\t\t\t\t\tOR issue_key LIKE 'gsc_robots_%%'\n\t\t\t\t)", $url_id ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( empty( $keys ) || !is_array( $keys ) ) {
            return array();
        }
        return array_values( array_filter( array_map( 'strval', $keys ) ) );
    }

    /**
     * Count preserved rows that are scored as issues (not good / N/A / opportunity-only).
     *
     * @param int      $url_id URL row ID.
     * @param string[] $keys   Issue keys.
     * @return int
     */
    private static function count_issue_severity_rows_for_keys( $url_id, array $keys ) {
        global $wpdb;
        if ( empty( $keys ) ) {
            return 0;
        }
        $url_id = (int) $url_id;
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        $placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
        $params = array_merge( array($url_id), array_values( $keys ) );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug.
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic IN list.
        $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$issues_table}\n\t\t\t\tWHERE url_id = %d\n\t\t\t\tAND is_sitewide = 0\n\t\t\t\tAND (user_status = 'active' OR user_status = '0' OR user_status IS NULL)\n\t\t\t\tAND issue_key IN ({$placeholders})\n\t\t\t\tAND severity IN ('critical','error','high','warning','medium')", ...$params ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $count;
    }

    /**
     * Get saved analysis results from the database.
     *
     * @since 6.1.26
     * @param int|null $object_id Post or term ID.
     * @param string|null $object_type 'post', 'term', or null.
     * @return array|null Saved analysis results or null if not found.
     */
    public static function get_saved_analysis( $object_id, $object_type = 'post' ) {
        global $wpdb;
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        // Check if there's a pending analysis first
        $has_pending = self::has_pending_analysis( $object_id, $object_type );
        // Get the most recent analyzed analysis for this object
        // First try by object_id and object_type - prefer 'analyzed' status, but also check for any status with a score
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
        $analysis = $wpdb->get_row( $wpdb->prepare( "SELECT a.*, u.url, u.post_title \n             FROM {$analysis_table} a \n             JOIN {$urls_table} u ON a.url_id = u.id \n             WHERE u.object_id = %d AND u.object_type = %s \n             AND (a.status = 'analyzed' OR (a.status IS NOT NULL AND a.score IS NOT NULL))\n             ORDER BY CASE WHEN a.status = 'analyzed' THEN 0 ELSE 1 END, a.analyzed_at DESC LIMIT 1", $object_id, $object_type ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // Fallback lookup when object_id/object_type join misses an existing analysis row.
        if ( !$analysis && $object_id ) {
            // Check if URL record exists but with different object_id/object_type
            $object_url = null;
            if ( $object_type === 'post' ) {
                $object_url = get_permalink( $object_id );
            } elseif ( $object_type === 'term' ) {
                $term_link = get_term_link( $object_id );
                if ( !is_wp_error( $term_link ) ) {
                    $object_url = $term_link;
                }
            }
            if ( $object_url ) {
                $url_hash = hash( 'sha256', $object_url );
                // Also check if URL record exists with this hash but different object_id
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
                $url_record_check = $wpdb->get_row( $wpdb->prepare( "SELECT id, object_id, object_type, url FROM {$urls_table} WHERE url_hash = %s", $url_hash ) );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
                $analysis = $wpdb->get_row( $wpdb->prepare( "SELECT a.*, u.url, u.post_title \n                     FROM {$analysis_table} a \n                     JOIN {$urls_table} u ON a.url_id = u.id \n                     WHERE u.url_hash = %s \n                     AND (a.status = 'analyzed' OR (a.status IS NOT NULL AND a.score IS NOT NULL))\n                     ORDER BY CASE WHEN a.status = 'analyzed' THEN 0 ELSE 1 END, a.analyzed_at DESC LIMIT 1", $url_hash ) );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
        }
        if ( !$analysis ) {
            return null;
        }
        // Get ALL active items for this URL (issues, improvements, good), latest row per issue_key.
        // Single source of truth: all items come from sb2_seo_issues table
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
        $all_items = $wpdb->get_results( $wpdb->prepare( "SELECT i.*\n             FROM {$issues_table} i\n             INNER JOIN (\n                 SELECT issue_key, MAX(id) AS max_id\n                 FROM {$issues_table}\n                 WHERE url_id = %d\n                 AND (user_status = 'active' OR user_status = '0' OR user_status IS NULL)\n                 GROUP BY issue_key\n             ) latest ON i.id = latest.max_id\n             ORDER BY i.severity DESC, i.created_at DESC", $analysis->url_id ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // Separate items by severity into issues, improvements, opportunities, good, and not applicable.
        $saved_issues = array();
        $saved_improvements = array();
        $saved_opportunities = array();
        $saved_good = array();
        $saved_not_applicable = array();
        foreach ( $all_items as $item ) {
            $item_data = array(
                'id'          => ( isset( $item->id ) ? (int) $item->id : 0 ),
                'key'         => $item->issue_key,
                'message'     => $item->message,
                'severity'    => $item->severity,
                'user_status' => ( isset( $item->user_status ) ? (string) $item->user_status : 'active' ),
            );
            // Add extra_data if it exists
            if ( !empty( $item->extra_data ) ) {
                $item_data['extra_data'] = json_decode( $item->extra_data, true );
            }
            if ( in_array( $item->severity, array(
                'critical',
                'error',
                'high',
                'warning',
                'medium'
            ), true ) ) {
                $saved_issues[] = $item_data;
            } elseif ( in_array( $item->severity, array('opportunity', 'low'), true ) ) {
                $saved_opportunities[] = $item_data;
                $saved_improvements[] = $item_data;
            } elseif ( $item->severity === 'good' ) {
                $saved_good[] = $item_data;
            } elseif ( $item->severity === 'not_applicable' ) {
                $saved_not_applicable[] = $item_data;
            }
        }
        // Convert analyzed_at MySQL datetime to Unix timestamp for JavaScript compatibility
        $analyzed_timestamp = 0;
        if ( !empty( $analysis->analyzed_at ) ) {
            $analyzed_timestamp = strtotime( $analysis->analyzed_at );
        }
        // Use saved data instead of running fresh analysis
        $results = array(
            'score'           => $analysis->score,
            'issues'          => $saved_issues,
            'improvements'    => $saved_improvements,
            'opportunities'   => $saved_opportunities,
            'good'            => $saved_good,
            'not_applicable'  => $saved_not_applicable,
            'content_changed' => $has_pending,
            'metadata'        => array(
                'timestamp'    => $analyzed_timestamp,
                'is_full_page' => false,
                'url'          => $analysis->url,
            ),
            'db_metadata'     => array(
                'analysis_id' => $analysis->id,
                'url_id'      => $analysis->url_id,
                'analyzed_at' => $analysis->analyzed_at,
                'score'       => $analysis->score,
                'issue_count' => $analysis->issue_count,
                'url'         => $analysis->url,
                'post_title'  => $analysis->post_title,
            ),
        );
        return $results;
    }

    /**
     * Get filtered possibilities from the database.
     *
     * @since 6.1.26
     * @param array $filters Filter options.
     * @return array Possibilities data.
     */
    public static function get_issues( $filters = array() ) {
        global $wpdb;
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $where_conditions = array('1=1');
        $where_values = array();
        // Severity filter
        if ( !empty( $filters['severity'] ) ) {
            $where_conditions[] = 'i.severity = %s';
            $where_values[] = $filters['severity'];
        }
        // Status filter - default to active possibilities if no status filter provided
        if ( !empty( $filters['status'] ) ) {
            if ( $filters['status'] === 'active' ) {
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
        if ( !empty( $filters['search'] ) ) {
            $where_conditions[] = '(i.message LIKE %s OR u.url LIKE %s OR u.post_title LIKE %s)';
            $search_term = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        // URL filter
        if ( !empty( $filters['url'] ) ) {
            $where_conditions[] = 'u.url = %s';
            $where_values[] = $filters['url'];
        }
        // Exclude sitewide issues from regular per-page issues list (only if column exists)
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        // Validate table name and sanitize column name
        $allowed_tables = array($wpdb->prefix . 'sb2_seo_issues');
        if ( in_array( $issues_table, $allowed_tables, true ) ) {
            $table_name_escaped = esc_sql( $issues_table );
            $column_name_escaped = esc_sql( 'is_sitewide' );
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
            $column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_name_escaped}` LIKE %s", $column_name_escaped ) );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ( !empty( $column_exists ) ) {
                $where_conditions[] = 'i.is_sitewide = 0';
            }
        }
        $exclusion_condition = self::get_exclusion_sql_condition( 'p' );
        $where_clause = implode( ' AND ', $where_conditions );
        $sql = "SELECT i.*, u.url, u.post_title, a.score, a.analyzed_at\n                FROM {$issues_table} i \n                JOIN {$urls_table} u ON i.url_id = u.id\n                JOIN {$analysis_table} a ON i.analysis_id = a.id \n                LEFT JOIN {$wpdb->posts} p ON u.object_type = 'post' AND u.object_id = p.ID\n                WHERE {$where_clause} \n                AND (u.object_type != 'post' OR (p.post_type != 'attachment' AND {$exclusion_condition}) OR p.post_type IS NULL)\n                ORDER BY i.severity DESC, i.created_at DESC";
        if ( !empty( $where_values ) ) {
            $sql = $wpdb->prepare( $sql, $where_values );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built with prefixed tables / allowlisted ORDER BY; values prepared.
        }
        return $wpdb->get_results( $sql );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built with prefixed tables / allowlisted ORDER BY; values prepared.
    }

    /**
     * Get analysis statistics.
     *
     * @since 6.1.26
     * @return array Statistics data.
     */
    public static function get_analysis_stats() {
        $cache_key = 'sb_seo_analysis_stats';
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }
        global $wpdb;
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $exclusion_condition = self::get_exclusion_sql_condition( 'p' );
        // Get total analyzed URLs (excluding private/WooCommerce pages)
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
        $total_analyzed = $wpdb->get_var( "SELECT COUNT(DISTINCT a.id)\n             FROM {$analysis_table} a\n             JOIN {$urls_table} u ON a.url_id = u.id\n             LEFT JOIN {$wpdb->posts} p ON u.object_type = 'post' AND u.object_id = p.ID\n             WHERE a.status = 'analyzed'\n             AND (u.object_type != 'post' OR p.ID IS NULL OR {$exclusion_condition})" );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // Get pending URLs (excluding private/WooCommerce pages)
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
        $total_pending = $wpdb->get_var( "SELECT COUNT(DISTINCT a.id)\n             FROM {$analysis_table} a\n             JOIN {$urls_table} u ON a.url_id = u.id\n             LEFT JOIN {$wpdb->posts} p ON u.object_type = 'post' AND u.object_id = p.ID\n             WHERE a.status = 'pending'\n             AND (u.object_type != 'post' OR p.ID IS NULL OR {$exclusion_condition})" );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // Actionable issue counts only (excludes good, opportunity, not_applicable, and reachability status).
        $actionable_sql = Severity::sql_in_actionable( 'i.severity' );
        $exclude_reach_sql = self::sql_exclude_reachability_issues( 'i.issue_key' );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
        $severity_counts = $wpdb->get_results( "SELECT i.severity, COUNT(*) as count \n             FROM {$issues_table} i\n             JOIN {$urls_table} u ON i.url_id = u.id\n             LEFT JOIN {$wpdb->posts} p ON u.object_type = 'post' AND u.object_id = p.ID\n             WHERE (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL)\n             AND {$actionable_sql}\n             AND {$exclude_reach_sql}\n             AND i.is_sitewide = 0\n             AND (u.object_type != 'post' OR p.ID IS NULL OR {$exclusion_condition})\n             GROUP BY i.severity" );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $stats = array(
            'total_analyzed' => (int) $total_analyzed,
            'total_pending'  => (int) $total_pending,
            'total_issues'   => 0,
            'critical'       => 0,
            'high'           => 0,
            'medium'         => 0,
            'low'            => 0,
        );
        foreach ( $severity_counts as $row ) {
            $stats[$row->severity] = (int) $row->count;
            $stats['total_issues'] += (int) $row->count;
        }
        // Cache for 5 minutes
        set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );
        return $stats;
    }

    /**
     * Get all publishable URLs that need analysis.
     *
     * @since 6.1.26
     * @return array URLs that need analysis.
     */
    public static function get_publishable_urls() {
        global $wpdb;
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $urls = array();
        $url_hashes = array();
        // Track hashes to prevent duplicates
        // Get published posts
        $posts = $wpdb->get_results( "SELECT ID, post_title, post_type \n             FROM {$wpdb->posts} \n             WHERE post_status = 'publish' \n             AND post_type IN ('post', 'page') \n             ORDER BY post_modified DESC" );
        foreach ( $posts as $post ) {
            // Exclude private posts and WooCommerce special pages
            if ( self::should_exclude_from_analysis( $post->ID ) ) {
                continue;
            }
            $url = get_permalink( $post->ID );
            if ( $url ) {
                $url_hash = hash( 'sha256', $url );
                // Skip if already in our list
                if ( in_array( $url_hash, $url_hashes ) ) {
                    continue;
                }
                // Check if already analyzed using new structure
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
                $analyzed = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) \n                     FROM {$urls_table} u \n                     JOIN {$analysis_table} a ON u.id = a.url_id \n                     WHERE u.url_hash = %s AND a.status = 'analyzed'", $url_hash ) );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                if ( !$analyzed ) {
                    $urls[] = array(
                        'url'         => $url,
                        'object_id'   => $post->ID,
                        'object_type' => 'post',
                        'title'       => $post->post_title,
                    );
                    $url_hashes[] = $url_hash;
                }
            }
        }
        return $urls;
    }

    /**
     * Derive reachability columns from analysis results / issue keys.
     *
     * @since 7.4.1
     * @param array $results Analysis results.
     * @return array<string, mixed>
     */
    private static function extract_reachability_fields( array $results ) {
        $reachability = Page_Reachability::STATUS_LIVE;
        $http_status = null;
        $redirect_to = '';
        $buckets = array('issues', 'opportunities', 'improvements');
        foreach ( $buckets as $bucket ) {
            if ( empty( $results[$bucket] ) || !is_array( $results[$bucket] ) ) {
                continue;
            }
            foreach ( $results[$bucket] as $issue ) {
                $key = ( isset( $issue['key'] ) ? (string) $issue['key'] : '' );
                if ( Page_Reachability::ISSUE_UNREACHABLE === $key ) {
                    $reachability = Page_Reachability::STATUS_UNREACHABLE;
                } elseif ( Page_Reachability::ISSUE_REDIRECTED === $key ) {
                    $reachability = Page_Reachability::STATUS_REDIRECTED;
                } else {
                    continue;
                }
                $extra = ( isset( $issue['extra_data'] ) && is_array( $issue['extra_data'] ) ? $issue['extra_data'] : array() );
                if ( isset( $extra['status_code'] ) ) {
                    $http_status = (int) $extra['status_code'];
                }
                if ( !empty( $extra['redirect_to'] ) ) {
                    $redirect_to = (string) $extra['redirect_to'];
                }
                break 2;
            }
        }
        if ( Page_Reachability::STATUS_LIVE === $reachability ) {
            $http_status = ( null !== $http_status ? $http_status : 200 );
            $redirect_to = '';
        }
        return array(
            'reachability'            => $reachability,
            'http_status'             => $http_status,
            'redirect_to'             => ( $redirect_to !== '' ? $redirect_to : null ),
            'reachability_checked_at' => current_time( 'mysql' ),
        );
    }

    /**
     * SQL fragment excluding reachability-only issue keys from actionable totals.
     *
     * @since 7.4.1
     * @param string $column Issue key column expression.
     * @return string
     */
    public static function sql_exclude_reachability_issues( $column = 'i.issue_key' ) {
        $keys = array_map( 'esc_sql', Page_Reachability::reachability_issue_keys() );
        return $column . " NOT IN ('" . implode( "','", $keys ) . "')";
    }

    /**
     * Mark analysis as pending for reanalysis by URL string.
     *
     * @since 7.4.1
     * @param string $url Absolute URL.
     * @return bool True on success (including when no row exists).
     */
    public static function mark_as_pending_by_url( $url ) {
        global $wpdb;
        $url = ( is_string( $url ) ? trim( $url ) : '' );
        if ( '' === $url ) {
            return true;
        }
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $url_hash = hash( 'sha256', $url );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
        $url_record = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$urls_table} WHERE url_hash = %s", $url_hash ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( !$url_record ) {
            return true;
        }
        $result = $wpdb->update( $analysis_table, array(
            'status' => 'pending',
        ), array(
            'url_id' => $url_record->id,
        ) );
        if ( $result !== false ) {
            self::clear_stats_cache();
            return true;
        }
        return false;
    }

    /**
     * Get a URL row by absolute URL.
     *
     * @since 7.4.1
     * @param string $url Absolute URL.
     * @return object|null
     */
    public static function get_url_record_by_url( $url ) {
        global $wpdb;
        $url = ( is_string( $url ) ? trim( $url ) : '' );
        if ( '' === $url ) {
            return null;
        }
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $url_hash = hash( 'sha256', $url );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$urls_table} WHERE url_hash = %s", $url_hash ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * Mark a URL as unreachable without an HTTP probe (trash/delete).
     *
     * @since 7.4.1
     * @param string $url Absolute URL.
     * @param int|null $object_id Object ID if known.
     * @param string|null $object_type Object type if known.
     * @param string $reason Human-readable reason for the message.
     * @return bool
     */
    public static function mark_url_unreachable(
        $url,
        $object_id = null,
        $object_type = null,
        $reason = ''
    ) {
        $url = ( is_string( $url ) ? trim( $url ) : '' );
        if ( '' === $url ) {
            return false;
        }
        $message = ( $reason !== '' ? $reason : __( 'URL not found or unavailable.', 'seo-booster' ) );
        $results = array(
            'score'  => 0,
            'issues' => array(array(
                'key'        => Page_Reachability::ISSUE_UNREACHABLE,
                'message'    => $message,
                'severity'   => Severity::ERROR,
                'extra_data' => array(
                    'status_code'  => 0,
                    'redirect_to'  => '',
                    'reachability' => Page_Reachability::STATUS_UNREACHABLE,
                ),
            )),
        );
        // Keep the last known title on the URL row, but detach the WP object so trash/private
        // exclusion SQL does not hide this status from Possibilities.
        $post_title = null;
        if ( $object_id && 'post' === $object_type ) {
            $post = get_post( $object_id );
            if ( $post ) {
                $post_title = $post->post_title;
            }
        } elseif ( $object_id && 'term' === $object_type ) {
            $term = get_term( $object_id );
            if ( $term && !is_wp_error( $term ) ) {
                $post_title = $term->name;
            }
        }
        $analysis_id = self::save_analysis_to_db(
            null,
            'url',
            $url,
            $results
        );
        if ( $analysis_id && null !== $post_title && '' !== $post_title ) {
            global $wpdb;
            $urls_table = $wpdb->prefix . 'sb2_seo_urls';
            $url_hash = hash( 'sha256', $url );
            $wpdb->update( $urls_table, array(
                'post_title' => $post_title,
            ), array(
                'url_hash' => $url_hash,
            ) );
        }
        return (bool) $analysis_id;
    }

    /**
     * Resolve post/term for a public URL with path verification for terms.
     *
     * @since 7.4.1
     * @param string $url Absolute URL.
     * @return array{object_id:?int,object_type:?string}
     */
    public static function resolve_object_from_url( $url ) {
        $object_id = null;
        $object_type = null;
        $url = ( is_string( $url ) ? trim( $url ) : '' );
        if ( '' === $url ) {
            return array(
                'object_id'   => null,
                'object_type' => null,
            );
        }
        $post_id = url_to_postid( $url );
        if ( !$post_id ) {
            $post_id = self::resolve_special_page_from_url( $url );
        }
        if ( $post_id ) {
            return array(
                'object_id'   => (int) $post_id,
                'object_type' => 'post',
            );
        }
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        $slug = basename( untrailingslashit( $path ) );
        if ( '' === $slug || '/' === $slug ) {
            return array(
                'object_id'   => null,
                'object_type' => null,
            );
        }
        $term = get_term_by( 'slug', $slug );
        if ( $term && !is_wp_error( $term ) ) {
            $term_link = get_term_link( $term );
            if ( !is_wp_error( $term_link ) ) {
                $term_path = (string) wp_parse_url( $term_link, PHP_URL_PATH );
                if ( untrailingslashit( $term_path ) === untrailingslashit( $path ) ) {
                    $object_id = (int) $term->term_id;
                    $object_type = 'term';
                }
            }
        }
        return array(
            'object_id'   => $object_id,
            'object_type' => $object_type,
        );
    }

    /**
     * Resolve front page / posts page URLs that url_to_postid() often misses.
     *
     * @since 7.4.1
     * @param string $url Absolute URL.
     * @return int Post ID or 0.
     */
    private static function resolve_special_page_from_url( $url ) {
        $normalized = untrailingslashit( esc_url_raw( $url ) );
        if ( '' === $normalized ) {
            return 0;
        }
        $candidates = array_filter( array_map( 'absint', array(get_option( 'page_on_front' ), get_option( 'page_for_posts' )) ) );
        foreach ( $candidates as $candidate_id ) {
            $permalink = get_permalink( $candidate_id );
            if ( !$permalink ) {
                continue;
            }
            if ( untrailingslashit( $permalink ) === $normalized ) {
                return (int) $candidate_id;
            }
        }
        if ( 'page' === get_option( 'show_on_front' ) ) {
            $front_id = absint( get_option( 'page_on_front' ) );
            if ( $front_id > 0 ) {
                $home = untrailingslashit( home_url( '/' ) );
                if ( $normalized === $home || $normalized === untrailingslashit( home_url() ) ) {
                    return $front_id;
                }
            }
        }
        return 0;
    }

    /**
     * Schedule a single SEO analysis Action Scheduler job (deduped).
     *
     * @since 7.4.1
     * @param string     $url Absolute URL.
     * @param int|null   $object_id Object ID.
     * @param string|null $object_type Object type.
     * @return int|false Action ID or false.
     */
    public static function schedule_seo_analysis_for_url( $url, $object_id = null, $object_type = null ) {
        $url = ( is_string( $url ) ? trim( $url ) : '' );
        if ( '' === $url || !function_exists( 'as_schedule_single_action' ) ) {
            return false;
        }
        $args = array(
            'url'         => $url,
            'object_id'   => $object_id,
            'object_type' => $object_type,
        );
        if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( 'sb_analyze_seo_for_url', $args, 'seo-booster' ) ) {
            return false;
        }
        return as_schedule_single_action(
            time() + 5,
            'sb_analyze_seo_for_url',
            $args,
            'seo-booster'
        );
    }

    /**
     * Mark analysis as pending for reanalysis.
     *
     * @since 6.1.26
     * @param int|null $object_id Post or term ID.
     * @param string|null $object_type 'post', 'term', or null.
     * @return bool True on success, false on failure.
     */
    public static function mark_as_pending( $object_id, $object_type ) {
        global $wpdb;
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        // First, find the URL record that matches the object_id and object_type
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
        $url_record = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$urls_table} WHERE object_id = %s AND object_type = %s", $object_id, $object_type ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( !$url_record ) {
            // Recreated content often gets a new object_id; fall back to permalink/term link hash.
            $fallback_url = '';
            if ( 'post' === $object_type && $object_id ) {
                $fallback_url = (string) get_permalink( $object_id );
            } elseif ( 'term' === $object_type && $object_id ) {
                $term_link = get_term_link( (int) $object_id );
                if ( !is_wp_error( $term_link ) ) {
                    $fallback_url = (string) $term_link;
                }
            }
            if ( $fallback_url !== '' ) {
                $row = self::get_url_record_by_url( $fallback_url );
                if ( $row ) {
                    $wpdb->update( $urls_table, array(
                        'object_id'   => $object_id,
                        'object_type' => $object_type,
                    ), array(
                        'id' => $row->id,
                    ) );
                }
                return self::mark_as_pending_by_url( $fallback_url );
            }
            return true;
        }
        // Update all analysis records for this URL to pending status
        $result = $wpdb->update( $analysis_table, array(
            'status' => 'pending',
        ), array(
            'url_id' => $url_record->id,
        ) );
        if ( $result !== false ) {
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
    public static function has_pending_analysis( $object_id, $object_type ) {
        global $wpdb;
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        // Find the URL record that matches the object_id and object_type
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
        $url_record = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$urls_table} WHERE object_id = %s AND object_type = %s", $object_id, $object_type ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( !$url_record ) {
            // No URL record found, no pending analysis
            return false;
        }
        // Check if there's a pending analysis for this URL
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
        $pending_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$analysis_table} WHERE url_id = %d AND status = 'pending'", $url_record->id ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $pending_count > 0 ) {
            return true;
        }
        // Also check if post content has changed since last analysis
        if ( $object_type === 'post' && $object_id ) {
            $post = get_post( $object_id );
            if ( $post && $post->post_modified ) {
                // Get the most recent analyzed analysis
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
                $latest_analysis = $wpdb->get_row( $wpdb->prepare( "SELECT analyzed_at FROM {$analysis_table} \n                     WHERE url_id = %d \n                     AND (status = 'analyzed' OR (status IS NOT NULL AND score IS NOT NULL))\n                     ORDER BY analyzed_at DESC LIMIT 1", $url_record->id ) );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                if ( $latest_analysis && $latest_analysis->analyzed_at ) {
                    $post_modified_time = strtotime( $post->post_modified );
                    $analysis_time = strtotime( $latest_analysis->analyzed_at );
                    // If post was modified after analysis, content has changed
                    if ( $post_modified_time > $analysis_time ) {
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
    private static function map_severity( $severity ) {
        $mapping = array(
            'error'          => 'critical',
            'warning'        => 'high',
            'opportunity'    => 'opportunity',
            'improvement'    => 'opportunity',
            'good'           => 'good',
            'not_applicable' => 'not_applicable',
            'low'            => 'opportunity',
        );
        return $mapping[$severity] ?? 'medium';
    }

    /**
     * Issue keys marked fixed or ignored (must not be re-inserted as active).
     *
     * @since 7.4.1
     * @param int  $url_id      URL row ID.
     * @param bool $is_sitewide Whether to look up sitewide rows.
     * @return string[]
     */
    private static function get_protected_issue_keys_for_url( $url_id, $is_sitewide = false ) {
        global $wpdb;
        $url_id = (int) $url_id;
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        $sitewide = ( $is_sitewide ? 1 : 0 );
        if ( $url_id <= 0 ) {
            return array();
        }
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prefixed internal table.
        $keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT issue_key FROM {$issues_table}\n\t\t\t\tWHERE url_id = %d\n\t\t\t\tAND is_sitewide = %d\n\t\t\t\tAND user_status IN ( 'fixed', 'ignored_permanent' )\n\t\t\t\tAND issue_key != ''", $url_id, $sitewide ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return ( is_array( $keys ) ? array_values( array_filter( array_map( 'strval', $keys ) ) ) : array() );
    }

    /**
     * Insert analysis result rows with deduplication by issue_key.
     *
     * @param string $issues_table Issues table name.
     * @param int    $analysis_id Analysis ID.
     * @param int    $url_id URL ID.
     * @param array  $items Result items.
     * @param array  $inserted_keys Keys already inserted (by reference).
     * @param bool   $map_severity Whether to map issue severities.
     * @param string $fixed_severity Optional fixed DB severity.
     * @return void
     */
    private static function insert_analysis_items(
        $issues_table,
        $analysis_id,
        $url_id,
        array $items,
        array &$inserted_keys,
        $map_severity = true,
        $fixed_severity = ''
    ) {
        global $wpdb;
        foreach ( $items as $item ) {
            $key = $item['key'] ?? '';
            if ( empty( $key ) || isset( $inserted_keys[$key] ) ) {
                continue;
            }
            $inserted_keys[$key] = true;
            $severity = $fixed_severity;
            if ( empty( $severity ) ) {
                $severity = ( $map_severity ? self::map_severity( $item['severity'] ?? 'warning' ) : $item['severity'] ?? 'medium' );
            }
            $extra_data = ( isset( $item['extra_data'] ) ? wp_json_encode( $item['extra_data'] ) : null );
            $wpdb->insert( $issues_table, array(
                'analysis_id' => $analysis_id,
                'url_id'      => $url_id,
                'is_sitewide' => 0,
                'issue_key'   => $key,
                'message'     => $item['message'] ?? '',
                'severity'    => $severity,
                'user_status' => 'active',
                'extra_data'  => $extra_data,
            ) );
        }
    }

    /**
     * Map an issue_key to a Tools tab deep link when one exists.
     *
     * @since 7.4.1
     * @param string $issue_key Issue key.
     * @return array{tab: string, label: string, url: string}|null
     */
    public static function get_tool_for_issue_key( $issue_key ) {
        $issue_key = (string) $issue_key;
        if ( '' === $issue_key ) {
            return null;
        }
        $map = array(
            'title_missing'                   => array(
                'tab'   => 'meta',
                'label' => __( 'Fix with Bulk meta', 'seo-booster' ),
            ),
            'description_missing'             => array(
                'tab'   => 'meta',
                'label' => __( 'Fix with Bulk meta', 'seo-booster' ),
            ),
            'duplicate_title'                 => array(
                'tab'   => 'meta',
                'label' => __( 'Fix with Bulk meta', 'seo-booster' ),
            ),
            'duplicate_meta_description'      => array(
                'tab'   => 'meta',
                'label' => __( 'Fix with Bulk meta', 'seo-booster' ),
            ),
            'no_alt_text'                     => array(
                'tab'   => 'image',
                'label' => __( 'Fix with Image metadata', 'seo-booster' ),
            ),
            'some_alt_text'                   => array(
                'tab'   => 'image',
                'label' => __( 'Fix with Image metadata', 'seo-booster' ),
            ),
            'all_empty_alt_text'              => array(
                'tab'   => 'image',
                'label' => __( 'Fix with Image metadata', 'seo-booster' ),
            ),
            'some_empty_alt_text'             => array(
                'tab'   => 'image',
                'label' => __( 'Fix with Image metadata', 'seo-booster' ),
            ),
            'keyword_missing'                 => array(
                'tab'   => 'focus-keyword',
                'label' => __( 'Fix with Focus keywords', 'seo-booster' ),
            ),
            'no_internal_links'               => array(
                'tab'   => 'autolink-opportunities',
                'label' => __( 'Fix with Autolink opportunities', 'seo-booster' ),
            ),
            'gsc_low_ctr_good_position'       => array(
                'tab'   => 'gsc-opportunities',
                'label' => __( 'Open GSC opportunities', 'seo-booster' ),
            ),
            'gsc_high_impressions_low_clicks' => array(
                'tab'   => 'gsc-opportunities',
                'label' => __( 'Open GSC opportunities', 'seo-booster' ),
            ),
            'gsc_content_freshness'           => array(
                'tab'   => 'content-decay',
                'label' => __( 'Open Content decay', 'seo-booster' ),
            ),
        );
        if ( !isset( $map[$issue_key] ) ) {
            return null;
        }
        $entry = $map[$issue_key];
        // Free installs only get free Tools deep links (Bulk meta, Image metadata).
        $free_tabs = array('meta', 'image');
        if ( !in_array( $entry['tab'], $free_tabs, true ) ) {
            $can_use_pro_tools = false;
            if ( function_exists( __NAMESPACE__ . '\\seobooster_fs' ) ) {
            }
            if ( !$can_use_pro_tools ) {
                return null;
            }
        }
        if ( !class_exists( Tools_Page::class ) ) {
            return null;
        }
        return array(
            'tab'   => $entry['tab'],
            'label' => $entry['label'],
            'url'   => Tools_Page::get_page_url( $entry['tab'] ),
        );
    }

    /**
     * Whether the current user can use Possibilities triage (Pro).
     *
     * @since 7.4.1
     * @return bool
     */
    public static function user_can_triage_possibilities() {
        $can = false;
        if ( function_exists( __NAMESPACE__ . '\\seobooster_fs' ) ) {
        }
        return $can;
    }

    /**
     * Update user_status for a single possibility row.
     *
     * @since 7.4.1
     * @param int    $issue_id Issue row ID.
     * @param string $status   active|fixed|ignored_permanent.
     * @return bool
     */
    public static function update_issue_user_status( $issue_id, $status ) {
        global $wpdb;
        $issue_id = (int) $issue_id;
        $allowed = array('active', 'fixed', 'ignored_permanent');
        if ( $issue_id <= 0 || !in_array( $status, $allowed, true ) ) {
            return false;
        }
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        $updated = $wpdb->update(
            $issues_table,
            array(
                'user_status'       => $status,
                'status_updated_at' => current_time( 'mysql' ),
            ),
            array(
                'id' => $issue_id,
            ),
            array('%s', '%s'),
            array('%d')
        );
        if ( false === $updated ) {
            return false;
        }
        self::clear_stats_cache();
        return true;
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
    /**
     * Normalize user_status filter to an allowlisted value.
     *
     * @param string $status Raw status.
     * @return string Empty string if invalid.
     */
    private static function normalize_user_status_filter( $status ) {
        $allowed = array(
            'active',
            'fixed',
            'ignored_temp',
            'ignored_permanent'
        );
        return ( in_array( $status, $allowed, true ) ? $status : '' );
    }

    public static function get_urls_with_issues(
        $filters = array(),
        $per_page = 50,
        $offset = 0,
        $orderby = 'total_issues',
        $order = 'DESC'
    ) {
        global $wpdb;
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        $where_conditions = array('1=1');
        $where_values = array();
        // Only show URLs with possibilities
        $where_conditions[] = 'i.id IS NOT NULL';
        // Severity filter
        if ( !empty( $filters['severity'] ) ) {
            $where_conditions[] = 'i.severity = %s';
            $where_values[] = $filters['severity'];
        }
        // Status filter: default to active so overview/list counts match the triage worklist.
        // Explicit status filters remain available for fixed/ignored records.
        $status_filter = self::normalize_user_status_filter( $filters['status'] ?? '' );
        if ( '' === $status_filter ) {
            $status_filter = 'active';
        }
        if ( 'active' === $status_filter ) {
            $where_conditions[] = '(i.user_status = %s OR i.user_status = %s OR i.user_status IS NULL)';
            $where_values[] = 'active';
            $where_values[] = '0';
        } elseif ( 'ignored_permanent' === $status_filter ) {
            $where_conditions[] = 'i.user_status = %s';
            $where_values[] = 'ignored_permanent';
        } elseif ( in_array( $status_filter, array('fixed', 'ignored_temp'), true ) ) {
            $where_conditions[] = 'i.user_status = %s';
            $where_values[] = $status_filter;
        }
        // Search filter
        if ( !empty( $filters['search'] ) ) {
            $where_conditions[] = '(u.url LIKE %s OR u.post_title LIKE %s)';
            $search_term = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        list( $issue_filter_sql, $issue_filter_values ) = self::build_issue_key_filter_sql( $filters, 'i.issue_key' );
        if ( $issue_filter_sql !== '' ) {
            $where_conditions[] = $issue_filter_sql;
            $where_values = array_merge( $where_values, $issue_filter_values );
        }
        $where_clause = implode( ' AND ', $where_conditions );
        $issue_join_condition = '';
        if ( 'active' === $status_filter ) {
            $issue_join_condition = "AND (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL)";
        } elseif ( 'ignored_permanent' === $status_filter ) {
            $issue_join_condition = "AND i.user_status = 'ignored_permanent'";
        } elseif ( in_array( $status_filter, array('fixed', 'ignored_temp'), true ) ) {
            $issue_join_condition = "AND i.user_status = '{$status_filter}'";
        }
        $exclusion_condition = self::get_exclusion_sql_condition( 'p' );
        $actionable_sql = Severity::sql_in_actionable( 'i.severity' );
        $opportunity_sql = Severity::sql_in_opportunity( 'i.severity' );
        $active_status_sql = "(i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL)";
        $qk_table = $wpdb->prefix . 'sb2_query_keywords';
        $qkh_table = $wpdb->prefix . 'sb2_query_keywords_history';
        $gsc_join = '';
        $gsc_select = '0 as gsc_clicks';
        if ( 'severity_traffic' === $orderby ) {
            $gsc_select = 'COALESCE(MAX(gsc.gsc_clicks), 0) as gsc_clicks';
            $gsc_join = "LEFT JOIN (\n\t\t\t\tSELECT qk.page AS page_url, COALESCE(SUM(qkh.clicks), 0) AS gsc_clicks\n\t\t\t\tFROM {$qk_table} qk\n\t\t\t\tLEFT JOIN {$qkh_table} qkh ON qk.id = qkh.query_keywords_id\n\t\t\t\tGROUP BY qk.page\n\t\t\t) gsc ON gsc.page_url = u.url";
        }
        $sql = "SELECT \n                    u.id,\n                    u.url,\n                    u.post_title,\n                    u.object_id,\n                    u.object_type,\n                    u.last_analyzed,\n                    u.reachability,\n                    u.http_status,\n                    u.redirect_to,\n                    COUNT(DISTINCT CASE WHEN {$actionable_sql} AND {$active_status_sql} AND i.issue_key IS NOT NULL THEN i.issue_key END) as total_issues,\n                    COUNT(DISTINCT CASE WHEN i.severity = 'critical' AND {$active_status_sql} AND i.issue_key IS NOT NULL THEN i.issue_key END) as critical_count,\n                    COUNT(DISTINCT CASE WHEN i.severity = 'high' AND {$active_status_sql} AND i.issue_key IS NOT NULL THEN i.issue_key END) as high_count,\n                    COUNT(DISTINCT CASE WHEN i.severity = 'medium' AND {$active_status_sql} AND i.issue_key IS NOT NULL THEN i.issue_key END) as medium_count,\n                    COUNT(DISTINCT CASE WHEN i.severity = 'low' AND {$active_status_sql} AND i.issue_key IS NOT NULL THEN i.issue_key END) as low_count,\n                    COUNT(DISTINCT CASE WHEN {$opportunity_sql} AND {$active_status_sql} AND i.issue_key IS NOT NULL THEN i.issue_key END) as opportunity_count,\n                    {$gsc_select},\n                    a.score,\n                    a.analyzed_at\n                FROM {$urls_table} u\n                LEFT JOIN {$analysis_table} a ON u.id = a.url_id\n                    AND a.status = 'analyzed'\n                    AND a.id = (\n                        SELECT MAX(a2.id) FROM {$analysis_table} a2\n                        WHERE a2.url_id = u.id AND a2.status = 'analyzed'\n                    )\n                LEFT JOIN {$issues_table} i ON u.id = i.url_id AND i.severity != 'good' AND i.is_sitewide = 0 {$issue_join_condition}\n                LEFT JOIN {$wpdb->posts} p ON u.object_type = 'post' AND u.object_id = p.ID\n                {$gsc_join}\n                WHERE {$where_clause}\n                AND (u.object_type != 'post' OR (p.post_type != 'attachment' AND {$exclusion_condition}) OR p.post_type IS NULL)\n                GROUP BY u.id, u.url, u.post_title, u.object_id, u.object_type, u.last_analyzed, u.reachability, u.http_status, u.redirect_to, a.score, a.analyzed_at";
        // Active-default worklist: only URLs with at least one matching active finding.
        $sql .= " HAVING COUNT(DISTINCT CASE WHEN (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL) AND i.issue_key IS NOT NULL THEN i.issue_key END) > 0";
        // Handle sorting
        $order_safe = ( strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC' );
        // Validate orderby to prevent SQL injection
        $allowed_orderby = array(
            'url',
            'score',
            'total_issues',
            'severity_traffic'
        );
        if ( !in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'total_issues';
        }
        switch ( $orderby ) {
            case 'url':
                $orderby_safe = 'u.url';
                $sql .= " ORDER BY {$orderby_safe} {$order_safe}, u.last_analyzed DESC";
                break;
            case 'score':
                // For score sorting, use COALESCE to put NULLs last (treat NULL as -1)
                // Higher scores first for DESC, lower scores first for ASC
                if ( $order_safe === 'DESC' ) {
                    $sql .= ' ORDER BY COALESCE(a.score, -1) DESC, u.last_analyzed DESC';
                } else {
                    $sql .= ' ORDER BY COALESCE(a.score, 999) ASC, u.last_analyzed DESC';
                }
                break;
            case 'severity_traffic':
                // Critical first, then high, then GSC clicks, then remaining issue count.
                $sql .= ' ORDER BY
					CASE
						WHEN COUNT(DISTINCT CASE WHEN i.severity = \'critical\' AND ' . $active_status_sql . ' AND i.issue_key IS NOT NULL THEN i.issue_key END) > 0 THEN 1
						WHEN COUNT(DISTINCT CASE WHEN i.severity = \'high\' AND ' . $active_status_sql . ' AND i.issue_key IS NOT NULL THEN i.issue_key END) > 0 THEN 2
						WHEN COUNT(DISTINCT CASE WHEN i.severity = \'medium\' AND ' . $active_status_sql . ' AND i.issue_key IS NOT NULL THEN i.issue_key END) > 0 THEN 3
						ELSE 4
					END ASC,
					gsc_clicks DESC,
					total_issues DESC,
					u.last_analyzed DESC';
                break;
            case 'total_issues':
            default:
                $orderby_safe = 'total_issues';
                $sql .= " ORDER BY {$orderby_safe} {$order_safe}, u.last_analyzed DESC";
                break;
        }
        $sql .= ' LIMIT %d OFFSET %d';
        $values = array_merge( $where_values, array($per_page, $offset) );
        $sql = $wpdb->prepare( $sql, $values );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built with prefixed tables / allowlisted ORDER BY; values prepared.
        return $wpdb->get_results( $sql );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built with prefixed tables / allowlisted ORDER BY; values prepared.
    }

    /**
     * Get total count of URLs with possibilities.
     *
     * @since 6.1.26
     * @param array $filters Filter options.
     * @return int Total count.
     */
    public static function get_urls_with_issues_count( $filters = array() ) {
        global $wpdb;
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        $where_conditions = array('1=1');
        $where_values = array();
        // Only show URLs with possibilities
        $where_conditions[] = 'i.id IS NOT NULL';
        // Severity filter
        if ( !empty( $filters['severity'] ) ) {
            $where_conditions[] = 'i.severity = %s';
            $where_values[] = $filters['severity'];
        }
        // Status filter: default to active so overview URL count matches the worklist.
        $status_filter = self::normalize_user_status_filter( $filters['status'] ?? '' );
        if ( '' === $status_filter ) {
            $status_filter = 'active';
        }
        if ( 'active' === $status_filter ) {
            $where_conditions[] = '(i.user_status = %s OR i.user_status = %s OR i.user_status IS NULL)';
            $where_values[] = 'active';
            $where_values[] = '0';
        } elseif ( in_array( $status_filter, array('fixed', 'ignored_temp', 'ignored_permanent'), true ) ) {
            $where_conditions[] = 'i.user_status = %s';
            $where_values[] = $status_filter;
        }
        // Search filter
        if ( !empty( $filters['search'] ) ) {
            $where_conditions[] = '(u.url LIKE %s OR u.post_title LIKE %s)';
            $search_term = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        list( $issue_filter_sql, $issue_filter_values ) = self::build_issue_key_filter_sql( $filters, 'i.issue_key' );
        if ( $issue_filter_sql !== '' ) {
            $where_conditions[] = $issue_filter_sql;
            $where_values = array_merge( $where_values, $issue_filter_values );
        }
        $where_clause = implode( ' AND ', $where_conditions );
        $issue_join_condition = '';
        if ( 'active' === $status_filter ) {
            $issue_join_condition = "AND (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL)";
        } elseif ( in_array( $status_filter, array('fixed', 'ignored_temp', 'ignored_permanent'), true ) ) {
            $issue_join_condition = "AND i.user_status = '{$status_filter}'";
        }
        $exclusion_condition = self::get_exclusion_sql_condition( 'p' );
        // Only show URLs with per-page possibilities (excluding sitewide and "good" severity)
        $sql = "SELECT COUNT(*) FROM (\n            SELECT u.id\n            FROM {$urls_table} u\n            LEFT JOIN {$issues_table} i ON u.id = i.url_id AND i.severity != 'good' AND i.is_sitewide = 0 {$issue_join_condition}\n            LEFT JOIN {$wpdb->posts} p ON u.object_type = 'post' AND u.object_id = p.ID\n            WHERE {$where_clause}\n            AND (u.object_type != 'post' OR (p.post_type != 'attachment' AND {$exclusion_condition}) OR p.post_type IS NULL)\n            GROUP BY u.id\n            HAVING COUNT(DISTINCT i.id) > 0\n        ) as url_counts";
        if ( !empty( $where_values ) ) {
            $sql = $wpdb->prepare( $sql, $where_values );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built with prefixed tables / allowlisted ORDER BY; values prepared.
        }
        return (int) $wpdb->get_var( $sql );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built with prefixed tables / allowlisted ORDER BY; values prepared.
    }

    /**
     * Bucket DB issue rows into metabox-shaped groups.
     *
     * @param array $rows Issue rows (objects or arrays with issue_key, message, severity, extra_data, id, user_status).
     * @return array<string, array>
     */
    private static function bucket_analysis_items( array $rows ) {
        $issues = array();
        $opportunities = array();
        $not_applicable = array();
        $good = array();
        foreach ( $rows as $row ) {
            $item = ( is_array( $row ) ? $row : (array) $row );
            $item_data = array(
                'id'          => ( isset( $item['id'] ) ? (int) $item['id'] : 0 ),
                'key'         => $item['issue_key'] ?? $item['key'] ?? '',
                'message'     => $item['message'] ?? '',
                'severity'    => $item['severity'] ?? 'medium',
                'user_status' => $item['user_status'] ?? 'active',
            );
            if ( !empty( $item['extra_data'] ) ) {
                $item_data['extra_data'] = ( is_array( $item['extra_data'] ) ? $item['extra_data'] : json_decode( $item['extra_data'], true ) );
            }
            $severity = $item_data['severity'];
            if ( Severity::counts_as_issue( $severity ) ) {
                $issues[] = (object) $item_data;
            } elseif ( Severity::is_opportunity_bucket( $severity ) ) {
                $opportunities[] = (object) $item_data;
            } elseif ( Severity::is_not_applicable_bucket( $severity ) ) {
                $not_applicable[] = (object) $item_data;
            } elseif ( Severity::is_good_bucket( $severity ) ) {
                $good[] = (object) $item_data;
            }
        }
        return array(
            'issues'         => $issues,
            'opportunities'  => $opportunities,
            'not_applicable' => $not_applicable,
            'good'           => $good,
        );
    }

    /**
     * Get all possibilities for a specific URL.
     *
     * @since 6.1.26
     * @param int $url_id URL ID.
     * @return array Possibilities grouped by bucket (issues, opportunities, not_applicable, good).
     */
    public static function get_issues_for_url( $url_id ) {
        global $wpdb;
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $exclusion_condition = self::get_exclusion_sql_condition( 'p' );
        $sql = "SELECT i.id, i.issue_key, i.message, i.severity, i.user_status, i.extra_data, i.created_at\n                FROM {$issues_table} i\n                INNER JOIN (\n                    SELECT issue_key, MAX(id) AS max_id\n                    FROM {$issues_table}\n                    WHERE url_id = %d\n                    AND (user_status = 'active' OR user_status = '0' OR user_status IS NULL)\n                    GROUP BY issue_key\n                ) latest ON i.id = latest.max_id\n                JOIN {$urls_table} u ON i.url_id = u.id\n                LEFT JOIN {$wpdb->posts} p ON u.object_type = 'post' AND u.object_id = p.ID\n                WHERE (u.object_type != 'post' OR (p.post_type != 'attachment' AND {$exclusion_condition}) OR p.post_type IS NULL)\n                ORDER BY i.severity DESC, i.created_at DESC";
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $url_id ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built with prefixed tables / allowlisted ORDER BY; values prepared.
        return self::bucket_analysis_items( $rows );
    }

    /**
     * Check if a post should be excluded from SEO analysis.
     *
     * @since 6.1.26
     * @param int $post_id Post ID to check.
     * @return bool True if post should be excluded, false otherwise.
     */
    public static function should_exclude_from_analysis( $post_id ) {
        // Return false if post_id is invalid
        if ( !$post_id || $post_id <= 0 ) {
            return false;
        }
        // Check user-defined exclusion preference first (highest priority)
        $user_excluded = get_post_meta( $post_id, '_sb_exclude_from_analysis', true );
        if ( $user_excluded === '1' ) {
            return true;
        }
        // Check if post status is 'private'
        $post_status = get_post_status( $post_id );
        if ( $post_status === 'private' ) {
            return true;
        }
        // Check if WooCommerce is active and this is a WooCommerce special page
        if ( function_exists( 'wc_get_page_id' ) ) {
            $wc_page_types = array(
                'cart',
                'checkout',
                'myaccount',
                'shop'
            );
            foreach ( $wc_page_types as $page_type ) {
                $wc_page_id = wc_get_page_id( $page_type );
                if ( $wc_page_id && $post_id === $wc_page_id ) {
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
    public static function is_auto_excluded( $post_id ) {
        // Return false if post_id is invalid
        if ( !$post_id || $post_id <= 0 ) {
            return false;
        }
        // Check if post status is 'private'
        $post_status = get_post_status( $post_id );
        if ( $post_status === 'private' ) {
            return true;
        }
        // Check if WooCommerce is active and this is a WooCommerce special page
        if ( function_exists( 'wc_get_page_id' ) ) {
            $wc_page_types = array(
                'cart',
                'checkout',
                'myaccount',
                'shop'
            );
            foreach ( $wc_page_types as $page_type ) {
                $wc_page_id = wc_get_page_id( $page_type );
                if ( $wc_page_id && $post_id === $wc_page_id ) {
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
    private static function get_exclusion_sql_condition( $post_table_alias = 'p' ) {
        global $wpdb;
        $exclusions = array();
        // Exclude private posts
        $exclusions[] = "{$post_table_alias}.post_status != 'private'";
        // Exclude trash posts
        $exclusions[] = "{$post_table_alias}.post_status != 'trash'";
        // Exclude WooCommerce special pages
        if ( function_exists( 'wc_get_page_id' ) ) {
            $wc_page_ids = array();
            $wc_page_types = array(
                'cart',
                'checkout',
                'myaccount',
                'shop'
            );
            foreach ( $wc_page_types as $page_type ) {
                $wc_page_id = wc_get_page_id( $page_type );
                if ( $wc_page_id ) {
                    $wc_page_ids[] = (int) $wc_page_id;
                }
            }
            if ( !empty( $wc_page_ids ) ) {
                $wc_page_ids_str = implode( ',', array_map( 'intval', $wc_page_ids ) );
                $exclusions[] = "{$post_table_alias}.ID NOT IN ({$wc_page_ids_str})";
            }
        }
        // Exclude posts with user-defined exclusion preference
        $exclusions[] = "NOT EXISTS (\n            SELECT 1 FROM {$wpdb->postmeta} pm \n            WHERE pm.post_id = {$post_table_alias}.ID \n            AND pm.meta_key = '_sb_exclude_from_analysis' \n            AND pm.meta_value = '1'\n        )";
        if ( empty( $exclusions ) ) {
            return '1=1';
            // No exclusions needed
        }
        return '(' . implode( ' AND ', $exclusions ) . ')';
    }

    /**
     * Save sitewide analysis results to the database.
     *
     * @since 7.0.0
     * @param array $results Analysis results from SEO_Sitewide_Analysis.
     * @return int|false Analysis ID on success, false on failure.
     */
    public static function save_sitewide_analysis_to_db( $results ) {
        global $wpdb;
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        $homepage_url = home_url( '/' );
        $url_hash = hash( 'sha256', $homepage_url );
        // Get or create URL record for homepage (sitewide issues use homepage URL)
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
        $url_record = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$urls_table} WHERE url_hash = %s", $url_hash ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( !$url_record ) {
            // Insert new URL record for homepage
            $url_result = $wpdb->insert( $urls_table, array(
                'url'            => $homepage_url,
                'url_hash'       => $url_hash,
                'object_id'      => null,
                'object_type'    => null,
                'post_title'     => __( 'Homepage (Sitewide)', 'seo-booster' ),
                'last_analyzed'  => current_time( 'mysql' ),
                'analysis_count' => 1,
            ) );
            if ( $url_result === false ) {
                Utils::log( 'Failed to save homepage URL record for sitewide analysis', 2 );
                return false;
            }
            $url_id = $wpdb->insert_id;
        } else {
            $url_id = $url_record->id;
            // Update URL record
            $wpdb->update( $urls_table, array(
                'last_analyzed'  => current_time( 'mysql' ),
                'analysis_count' => $wpdb->get_var( $wpdb->prepare( "SELECT analysis_count FROM {$urls_table} WHERE id = %d", $url_id ) ) + 1,
            ), array(
                'id' => $url_id,
            ) );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        // Count total possibilities (excluding "good" severity - those are positive indicators, not issues)
        $possibility_count = 0;
        if ( !empty( $results['issues'] ) ) {
            foreach ( $results['issues'] as $issue ) {
                // Only count issues that are not "good" severity
                if ( isset( $issue['severity'] ) && $issue['severity'] !== 'good' ) {
                    ++$possibility_count;
                }
            }
        }
        // Build insert data - improvements and good items are now stored in sb2_seo_issues table only
        $insert_data = array(
            'url_id'      => $url_id,
            'score'       => $results['score'] ?? 0,
            'issue_count' => $possibility_count,
            'status'      => 'analyzed',
        );
        // Insert new analysis record
        $analysis_result = $wpdb->insert( $analysis_table, $insert_data );
        if ( $analysis_result === false ) {
            Utils::log( 'Sitewide SEO analysis failed: could not save analysis record', 2 );
            return false;
        }
        $analysis_id = $wpdb->insert_id;
        // Remove ALL active sitewide possibilities to prevent duplicates
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$issues_table} \n             WHERE url_id = %d \n             AND is_sitewide = 1\n             AND (user_status = 'active' OR user_status = '0' OR user_status IS NULL)", $url_id ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // Collect unique possibility keys (we save "good" severity items too, but they're not counted).
        // Preserve user-marked fixed/ignored sitewide keys so re-runs do not resurrect them.
        $protected_keys = array();
        foreach ( self::get_protected_issue_keys_for_url( $url_id, true ) as $protected_key ) {
            $protected_keys[$protected_key] = true;
        }
        $unique_possibilities = array();
        if ( !empty( $results['issues'] ) ) {
            foreach ( $results['issues'] as $possibility ) {
                $possibility_key = $possibility['key'] ?? '';
                if ( empty( $possibility_key ) || isset( $protected_keys[$possibility_key] ) || isset( $unique_possibilities[$possibility_key] ) ) {
                    continue;
                }
                $unique_possibilities[$possibility_key] = $possibility;
            }
        }
        // Insert the unique possibilities as sitewide issues
        // Note: We save "good" severity items too, but they're not counted in possibility_count
        foreach ( $unique_possibilities as $possibility_key => $possibility ) {
            $severity = self::map_severity( $possibility['severity'] ?? 'warning' );
            $message = $possibility['message'] ?? '';
            $extra_data = ( isset( $possibility['extra_data'] ) ? wp_json_encode( $possibility['extra_data'] ) : null );
            $wpdb->insert( $issues_table, array(
                'analysis_id' => $analysis_id,
                'url_id'      => $url_id,
                'is_sitewide' => 1,
                'issue_key'   => $possibility_key,
                'message'     => $message,
                'severity'    => $severity,
                'user_status' => 'active',
                'extra_data'  => $extra_data,
            ) );
        }
        // Also save improvements and good practices as sitewide issues (with lower severity)
        if ( !empty( $results['improvements'] ) ) {
            foreach ( $results['improvements'] as $improvement ) {
                $improvement_key = $improvement['key'] ?? '';
                if ( empty( $improvement_key ) || isset( $protected_keys[$improvement_key] ) ) {
                    continue;
                }
                $protected_keys[$improvement_key] = true;
                $extra_data = ( isset( $improvement['extra_data'] ) ? wp_json_encode( $improvement['extra_data'] ) : null );
                $wpdb->insert( $issues_table, array(
                    'analysis_id' => $analysis_id,
                    'url_id'      => $url_id,
                    'is_sitewide' => 1,
                    'issue_key'   => $improvement_key,
                    'message'     => $improvement['message'] ?? '',
                    'severity'    => 'low',
                    'user_status' => 'active',
                    'extra_data'  => $extra_data,
                ) );
            }
        }
        if ( !empty( $results['good'] ) ) {
            foreach ( $results['good'] as $good ) {
                $good_key = $good['key'] ?? '';
                if ( empty( $good_key ) || isset( $protected_keys[$good_key] ) ) {
                    continue;
                }
                $protected_keys[$good_key] = true;
                $extra_data = ( isset( $good['extra_data'] ) ? wp_json_encode( $good['extra_data'] ) : null );
                $wpdb->insert( $issues_table, array(
                    'analysis_id' => $analysis_id,
                    'url_id'      => $url_id,
                    'is_sitewide' => 1,
                    'issue_key'   => $good_key,
                    'message'     => $good['message'] ?? '',
                    'severity'    => 'good',
                    'user_status' => 'active',
                    'extra_data'  => $extra_data,
                ) );
            }
        }
        // Clear cache
        self::clear_stats_cache();
        return $analysis_id;
    }

    /**
     * Get sitewide issues from the database.
     *
     * @since 7.0.0
     * @return array Sitewide issues data.
     */
    public static function get_sitewide_issues() {
        global $wpdb;
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        // Check if is_sitewide column exists - validate table name
        $allowed_tables = array($wpdb->prefix . 'sb2_seo_issues');
        $column_exists = array();
        if ( in_array( $issues_table, $allowed_tables, true ) ) {
            $table_name_escaped = esc_sql( $issues_table );
            $column_name_escaped = esc_sql( 'is_sitewide' );
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
            $column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_name_escaped}` LIKE %s", $column_name_escaped ) );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        if ( empty( $column_exists ) ) {
            // Column doesn't exist yet, trigger migration
            Utils::create_database_tables();
            // Re-check after migration
            if ( in_array( $issues_table, $allowed_tables, true ) ) {
                $table_name_escaped = esc_sql( $issues_table );
                $column_name_escaped = esc_sql( 'is_sitewide' );
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
                $column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_name_escaped}` LIKE %s", $column_name_escaped ) );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
            if ( empty( $column_exists ) ) {
                // Still doesn't exist, return empty array
                Utils::log( 'is_sitewide column does not exist and migration failed', 2 );
                return array();
            }
        }
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
        $sql = "SELECT i.*, u.url, u.post_title, a.score, a.analyzed_at\n                FROM {$issues_table} i \n                JOIN {$urls_table} u ON i.url_id = u.id\n                JOIN {$analysis_table} a ON i.analysis_id = a.id \n                WHERE i.is_sitewide = 1\n                AND (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL)\n                ORDER BY \n                    CASE i.severity \n                        WHEN 'critical' THEN 1 \n                        WHEN 'error' THEN 2 \n                        WHEN 'high' THEN 3 \n                        WHEN 'warning' THEN 4 \n                        WHEN 'medium' THEN 5 \n                        WHEN 'low' THEN 6 \n                        WHEN 'good' THEN 7 \n                        ELSE 8 \n                    END,\n                    i.created_at DESC";
        return $wpdb->get_results( $sql );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built with prefixed tables / allowlisted ORDER BY; values prepared.
    }

    /**
     * Get sitewide analysis stats.
     *
     * @since 7.0.0
     * @return array Statistics about sitewide issues.
     */
    public static function get_sitewide_stats() {
        global $wpdb;
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        // Check if is_sitewide column exists - validate table name
        $allowed_tables = array($wpdb->prefix . 'sb2_seo_issues');
        $column_exists = array();
        if ( in_array( $issues_table, $allowed_tables, true ) ) {
            $table_name_escaped = esc_sql( $issues_table );
            $column_name_escaped = esc_sql( 'is_sitewide' );
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
            $column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_name_escaped}` LIKE %s", $column_name_escaped ) );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        if ( empty( $column_exists ) ) {
            // Column doesn't exist yet, trigger migration
            Utils::create_database_tables();
            // Re-check after migration
            if ( in_array( $issues_table, $allowed_tables, true ) ) {
                $table_name_escaped = esc_sql( $issues_table );
                $column_name_escaped = esc_sql( 'is_sitewide' );
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
                $column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_name_escaped}` LIKE %s", $column_name_escaped ) );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
            if ( empty( $column_exists ) ) {
                // Still doesn't exist, return empty stats
                Utils::log( 'is_sitewide column does not exist and migration failed', 2 );
                return array(
                    'total_issues' => 0,
                    'critical'     => 0,
                    'error'        => 0,
                    'high'         => 0,
                    'warning'      => 0,
                    'medium'       => 0,
                    'low'          => 0,
                    'good'         => 0,
                );
            }
        }
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
        $stats = $wpdb->get_row( "SELECT \n                COUNT(*) as total_issues,\n                SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,\n                SUM(CASE WHEN severity = 'error' THEN 1 ELSE 0 END) as error,\n                SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high,\n                SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) as warning,\n                SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium,\n                SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low,\n                SUM(CASE WHEN severity = 'good' THEN 1 ELSE 0 END) as good\n            FROM {$issues_table}\n            WHERE is_sitewide = 1\n            AND (user_status = 'active' OR user_status = '0' OR user_status IS NULL)", ARRAY_A );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return ( $stats ?: array(
            'total_issues' => 0,
            'critical'     => 0,
            'error'        => 0,
            'high'         => 0,
            'warning'      => 0,
            'medium'       => 0,
            'low'          => 0,
            'good'         => 0,
        ) );
    }

    /**
     * Get top possibilities for dashboard display.
     * Returns the most interesting/important possibilities grouped by type.
     *
     * @since 6.1.26
     * @param int $limit Maximum number of possibility types to return.
     * @return array Top possibilities grouped by type with affected URL counts.
     */
    public static function get_top_possibilities_for_dashboard( $limit = 5 ) {
        return self::get_issue_types_summary( $limit );
    }

    /**
     * Possibility types summary for the By type triage view.
     *
     * @since 7.4.1
     * @param int   $limit   Max types (0 = no limit).
     * @param array $filters Optional filters (severity).
     * @return array
     */
    public static function get_issue_types_summary( $limit = 12, $filters = array() ) {
        global $wpdb;
        $limit = max( 0, (int) $limit );
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $exclusion_condition = self::get_exclusion_sql_condition( 'p' );
        $actionable_sql = Severity::sql_in_actionable( 'i.severity' );
        $exclude_reach_sql = self::sql_exclude_reachability_issues( 'i.issue_key' );
        $where_extra = '';
        $values = array();
        if ( !empty( $filters['severity'] ) ) {
            $where_extra = ' AND i.severity = %s';
            $values[] = sanitize_text_field( $filters['severity'] );
        }
        $sql = "SELECT \n                    i.issue_key,\n                    rep.message,\n                    rep.severity,\n                    COUNT(DISTINCT i.url_id) as affected_urls,\n                    COUNT(i.id) as total_instances,\n                    GROUP_CONCAT(DISTINCT u.post_title ORDER BY u.post_title SEPARATOR ', ') as sample_titles\n                FROM {$issues_table} i\n                INNER JOIN (\n                    SELECT issue_key, url_id, MAX(id) AS max_id\n                    FROM {$issues_table}\n                    WHERE (user_status = 'active' OR user_status = '0' OR user_status IS NULL)\n                    AND is_sitewide = 0\n                    GROUP BY issue_key, url_id\n                ) latest ON i.id = latest.max_id\n                INNER JOIN (\n                    SELECT i2.issue_key, i2.message, i2.severity\n                    FROM {$issues_table} i2\n                    INNER JOIN (\n                        SELECT issue_key, MAX(id) AS max_id\n                        FROM {$issues_table}\n                        WHERE (user_status = 'active' OR user_status = '0' OR user_status IS NULL)\n                        AND is_sitewide = 0\n                        GROUP BY issue_key\n                    ) latest_key ON i2.id = latest_key.max_id\n                ) rep ON rep.issue_key = i.issue_key\n                JOIN {$urls_table} u ON i.url_id = u.id\n                LEFT JOIN {$wpdb->posts} p ON u.object_type = 'post' AND u.object_id = p.ID\n                WHERE {$actionable_sql}\n                AND {$exclude_reach_sql}\n                AND (u.object_type != 'post' OR (p.post_type != 'attachment' AND {$exclusion_condition}) OR p.post_type IS NULL)\n                {$where_extra}\n                GROUP BY i.issue_key, rep.message, rep.severity\n                ORDER BY \n                    CASE rep.severity\n                        WHEN 'critical' THEN 1 \n                        WHEN 'error' THEN 2 \n                        WHEN 'high' THEN 3 \n                        WHEN 'warning' THEN 4 \n                        WHEN 'medium' THEN 5 \n                        WHEN 'low' THEN 6 \n                        ELSE 7 \n                    END,\n                    affected_urls DESC";
        if ( $limit > 0 ) {
            $sql .= ' LIMIT %d';
            $values[] = $limit;
        }
        if ( !empty( $values ) ) {
            $results = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built with prefixed tables / allowlisted ORDER BY; values prepared.
        } else {
            $results = $wpdb->get_results( $sql );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built with prefixed tables / allowlisted ORDER BY; no user values.
        }
        $formatted = array();
        if ( !is_array( $results ) ) {
            return $formatted;
        }
        foreach ( $results as $result ) {
            $formatted[] = array(
                'issue_key'       => $result->issue_key,
                'message'         => $result->message,
                'severity'        => $result->severity,
                'affected_urls'   => (int) $result->affected_urls,
                'total_instances' => (int) $result->total_instances,
                'sample_titles'   => $result->sample_titles,
                'tool'            => self::get_tool_for_issue_key( $result->issue_key ),
            );
        }
        return $formatted;
    }

    /**
     * Latest human-readable message for an issue key.
     *
     * @since 7.4.1
     * @param string $issue_key Issue key.
     * @return string Empty if not found.
     */
    public static function get_issue_type_message( $issue_key ) {
        global $wpdb;
        $issue_key = sanitize_text_field( (string) $issue_key );
        if ( '' === $issue_key ) {
            return '';
        }
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prefixed internal table.
        $message = $wpdb->get_var( $wpdb->prepare( "SELECT message FROM {$issues_table}\n\t\t\t\tWHERE issue_key = %s\n\t\t\t\tAND is_sitewide = 0\n\t\t\t\tAND (user_status = 'active' OR user_status = '0' OR user_status IS NULL)\n\t\t\t\tORDER BY id DESC\n\t\t\t\tLIMIT 1", $issue_key ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return ( is_string( $message ) ? $message : '' );
    }

    /**
     * Build filter URL for the Possibilities page.
     *
     * @since 7.4.1
     * @param array $args Query args (severity, issue_type, view).
     * @return string
     */
    public static function get_possibilities_page_url( $args = array() ) {
        $url = admin_url( 'admin.php?page=sb2_seo_issues' );
        $allowed = array(
            'severity',
            'issue_type',
            'view',
            'orderby',
            'order'
        );
        $query = array();
        foreach ( $allowed as $key ) {
            if ( !empty( $args[$key] ) ) {
                $query[$key] = sanitize_text_field( (string) $args[$key] );
            }
        }
        if ( !empty( $query ) ) {
            $url = add_query_arg( $query, $url );
        }
        return $url;
    }

    /**
     * Priority URLs with actionable issues for the site AI advisor.
     *
     * @since 7.4.0
     * @param int $limit Max rows.
     * @return array<int, array{url: string, title: string, severity: string, message: string, issue_key: string}>
     */
    public static function get_priority_urls_for_assistant( $limit = 8 ) {
        global $wpdb;
        $limit = max( 1, min( 15, (int) $limit ) );
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        $exclusion_condition = self::get_exclusion_sql_condition( 'p' );
        $actionable_sql = Severity::sql_in_actionable( 'i.severity' );
        $exclude_reach_sql = self::sql_exclude_reachability_issues( 'i.issue_key' );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prefixed tables; Severity helper; prepared limit.
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT u.url, u.post_title, i.severity, i.message, i.issue_key\n\t\t\t\tFROM {$issues_table} i\n\t\t\t\tJOIN {$urls_table} u ON i.url_id = u.id\n\t\t\t\tLEFT JOIN {$wpdb->posts} p ON u.object_type = 'post' AND u.object_id = p.ID\n\t\t\t\tWHERE (i.user_status = 'active' OR i.user_status = '0' OR i.user_status IS NULL)\n\t\t\t\tAND {$actionable_sql}\n\t\t\t\tAND {$exclude_reach_sql}\n\t\t\t\tAND i.is_sitewide = 0\n\t\t\t\tAND (u.object_type != 'post' OR (p.post_type != 'attachment' AND {$exclusion_condition}) OR p.post_type IS NULL)\n\t\t\t\tORDER BY\n\t\t\t\t\tCASE i.severity\n\t\t\t\t\t\tWHEN 'critical' THEN 1\n\t\t\t\t\t\tWHEN 'error' THEN 2\n\t\t\t\t\t\tWHEN 'high' THEN 3\n\t\t\t\t\t\tWHEN 'warning' THEN 4\n\t\t\t\t\t\tWHEN 'medium' THEN 5\n\t\t\t\t\t\tELSE 6\n\t\t\t\t\tEND,\n\t\t\t\t\ti.created_at DESC\n\t\t\t\tLIMIT %d", $limit ), ARRAY_A );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( !is_array( $rows ) ) {
            return array();
        }
        $out = array();
        foreach ( $rows as $row ) {
            $url = ( isset( $row['url'] ) ? (string) $row['url'] : '' );
            if ( $url === '' ) {
                continue;
            }
            $out[] = array(
                'url'       => $url,
                'title'     => ( isset( $row['post_title'] ) ? (string) $row['post_title'] : '' ),
                'severity'  => ( isset( $row['severity'] ) ? (string) $row['severity'] : '' ),
                'message'   => ( isset( $row['message'] ) ? (string) $row['message'] : '' ),
                'issue_key' => ( isset( $row['issue_key'] ) ? (string) $row['issue_key'] : '' ),
            );
        }
        return $out;
    }

    /**
     * Build SQL fragment for issue key / category filters.
     *
     * @since 7.2.3
     * @param array  $filters Request filters.
     * @param string $column  Qualified issue_key column (e.g. i.issue_key).
     * @return array{0: string, 1: array<int, string>} SQL fragment and placeholder values.
     */
    public static function build_issue_key_filter_sql( $filters, $column = 'issue_key' ) {
        if ( !empty( $filters['issue_type'] ) ) {
            return array("{$column} = %s", array($filters['issue_type']));
        }
        if ( !empty( $filters['issue_category'] ) && $filters['issue_category'] === 'ai_readiness' ) {
            $keys = Ai_Readiness_Registry::get_issue_keys();
            if ( empty( $keys ) ) {
                return array('1=0', array());
            }
            $placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
            return array("{$column} IN ({$placeholders})", $keys);
        }
        return array('', array());
    }

    /**
     * Clear statistics cache.
     *
     * @since 6.1.26
     * @return void
     */
    private static function clear_stats_cache() {
        delete_transient( 'sb_seo_analysis_stats' );
    }

}
