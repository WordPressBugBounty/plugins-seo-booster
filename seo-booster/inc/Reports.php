<?php

namespace Cleverplugins\SEOBooster;

use Cleverplugins\SEOBooster\Reports\TopPages;
use Cleverplugins\SEOBooster\Reports\CTRImprovement;
use Cleverplugins\SEOBooster\Reports\KeywordCannibalization;
use Cleverplugins\SEOBooster\Reports\LongTailKeywords;
use Cleverplugins\SEOBooster\Reports\TopPerformingKeywords;
use Cleverplugins\SEOBooster\Reports\DecliningKeywords;
use Cleverplugins\SEOBooster\Reports\Missing404Pages;
use Cleverplugins\SEOBooster\Reports\QuestionQueries;

include_once SEOBOOSTER_PLUGINPATH . 'inc/Reports/CTRImprovement.php';
include_once SEOBOOSTER_PLUGINPATH . 'inc/Reports/KeywordCannibalization.php';
include_once SEOBOOSTER_PLUGINPATH . 'inc/Reports/LongTailKeywords.php';
include_once SEOBOOSTER_PLUGINPATH . 'inc/Reports/TopPages.php';
include_once SEOBOOSTER_PLUGINPATH . 'inc/Reports/TopPerformingKeywords.php';
include_once SEOBOOSTER_PLUGINPATH . 'inc/Reports/DecliningKeywords.php';
include_once SEOBOOSTER_PLUGINPATH . 'inc/Reports/Missing404Pages.php';
include_once SEOBOOSTER_PLUGINPATH . 'inc/Reports/QuestionQueries.php';


use \League\Plates\Engine as PlatesEngine;

class Reports {

    /**
     * @var PlatesEngine $templates The Plates templating engine instance.
     */
    private static $templates;

    /**
     * Initializes the Reports class.
     *
     * @return void
     */
    public static function init() {
        // Initialize the Plates templating engine
        self::$templates = new \League\Plates\Engine(plugin_dir_path(__FILE__) . '../templates');
        
        // Add AJAX handlers
        add_action('wp_ajax_sb_get_report_table', [__CLASS__, 'handle_ajax_report_table']);
        add_action('wp_ajax_sb_check_cache_status', [__CLASS__, 'handle_ajax_cache_status']);
    }

    /**
     * Renders a template with the given data.
     *
     * @param string $template The name of the template to render.
     * @param array $data The data to pass to the template.
     * @return string The rendered template.
     * @throws \RuntimeException If templates are not initialized.
     */
    public static function render($template, $data) {
        if (self::$templates === null) {
            self::init();
        }
        
        if (self::$templates === null) {
            throw new \RuntimeException('Templates engine failed to initialize');
        }

        return self::$templates->render($template, $data);
    }
  
    /**
     * Renders the dashboard report template.
     *
     * @param array $data The data to pass to the template.
     * @return string The rendered dashboard report.
     */
    public static function render_dashboard_report($data) {
        if (self::$templates === null) {
            self::init();
        }
        
        if (self::$templates === null) {
            return 'Error: Template engine not initialized';
        }

        return self::$templates->render('dashboard/report', $data);
    }

    /**
     * Renders the email report template.
     *
     * @param array $data The data to pass to the template.
     * @return string The rendered email report.
     */
    public static function render_email_report($data) {
        return self::$templates->render('email/report', $data);
    }

    /**
     * Renders the PDF report template.
     *
     * @param array $data The data to pass to the template.
     * @return string The rendered PDF report.
     */
    public static function render_pdf_report($data) {
        return self::$templates->render('pdf/report', $data);
    }

    /**
     * Retrieves keyword cannibalization data.
     *
     * @return array|false An array of cannibalized keywords or false if no data is found.
     */
    public static function keyword_canabalisations() {
        global $wpdb;

        $cache_key = 'sb_report_keyword_canabalisations';
        $cached_data = get_transient($cache_key);
        
        if (false !== $cached_data) {
            return $cached_data;
        }

        $enhanced_queries_results = $wpdb->get_results( $wpdb->prepare(
            "
        SELECT 
            qk.query, 
            qk.page, 
            AVG(h.position) AS avg_position, 
            SUM(h.impressions) AS total_impressions, 
            SUM(h.clicks) AS total_clicks, 
            AVG(h.ctr) AS avg_ctr
        FROM 
            {$wpdb->prefix}sb2_query_keywords AS qk
        INNER JOIN 
            {$wpdb->prefix}sb2_query_keywords_history AS h ON qk.id = h.query_keywords_id
        WHERE 
            h.date >= DATE_SUB(NOW(), INTERVAL %d DAY)
        GROUP BY 
            qk.query, qk.page
        ORDER BY 
            qk.query, avg_position ASC",
            30  // Number of days to look back
        ), OBJECT);
        
        if ($enhanced_queries_results) {
            $result = self::identify_cannibalization($enhanced_queries_results);
            set_transient($cache_key, $result, self::get_cache_time('keyword-canabalisations'));
            return $result;
        }

        return false;
    }
    
    /**
     * Identifies keyword cannibalization issues from query results.
     *
     * @param array $results An array of objects containing query data.
     * @return array An associative array of cannibalized keywords and their corresponding pages.
     */
    private static function identify_cannibalization($results) {
        $query_urls = [];
        $cannibalized_keywords = [];
    
        // Group results by query and base URL
        foreach ($results as $result) {
            $base_page = strtok($result->page, '#'); // Remove everything after '#'
    
            if (!isset($query_urls[$result->query])) {
                $query_urls[$result->query] = [];
            }
    
            // If this base page is not already in the list for this query, add it
            if (!array_key_exists($base_page, $query_urls[$result->query])) {
                $query_urls[$result->query][$base_page] = [
                    'page' => $result->page,
                    'avg_position' => $result->avg_position,
                    'total_impressions' => $result->total_impressions,
                    'total_clicks' => $result->total_clicks,
                    'avg_ctr' => $result->avg_ctr
                ];
            }
        }
    
        // Identify queries with more than one unique URL
        foreach ($query_urls as $query => $pages) {
            if (count($pages) > 1) {
                // Cannibalization detected
                $cannibalized_keywords[$query] = array_values($pages); // Convert to indexed array for easier iteration
            }
        }
    
        return $cannibalized_keywords;
    }


  /**
   * Retrieves new keywords that have appeared in the past 30 days.
   *
   * @since 1.0.0
   * @access public
   * @static
   *
   * @global wpdb $wpdb WordPress database abstraction object.
   *
   * @return array|null Database query results, or null on failure.
   */
  public static function get_new_keywords_past_x_days() {
    global $wpdb;
    
    $cache_key = 'sb_report_new_keywords';
    $cached_data = get_transient($cache_key);
    
    if (false !== $cached_data) {
        return $cached_data;
    }

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT query, page, first_seen_date 
        FROM {$wpdb->prefix}sb2_query_keywords 
        WHERE first_seen_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
        30
    ), OBJECT);

    if ($results) {
        set_transient($cache_key, $results, self::get_cache_time('new-keywords'));
    }

    return $results;
  }


/**
 * Retrieves inactive keywords for a specified number of days.
 *
 * This function fetches keywords that have had no clicks within the specified time period.
 *
 * @since 1.0.0
 * @access public
 * @static
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int $days The number of days to look back for inactive keywords.
 * @return array|null Database query results, or null on failure.
 */
public static function get_inactive_keywords($days) {
    global $wpdb;
    
    $cache_key = 'sb_report_inactive_keywords_' . $days;
    $cached_data = wp_cache_get($cache_key);
    
    if (false !== $cached_data) {
        return $cached_data;
    }

    $query = $wpdb->prepare(
        "SELECT qk.query, qk.page, 
                qk.latest_date, 
                (SELECT h1.position 
                 FROM {$wpdb->prefix}sb2_query_keywords_history AS h1 
                 WHERE h1.query_keywords_id = qk.id 
                 ORDER BY h1.date DESC 
                 LIMIT 1) AS last_known_position
        FROM {$wpdb->prefix}sb2_query_keywords AS qk
        LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS h 
            ON qk.id = h.query_keywords_id
        WHERE qk.latest_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
        GROUP BY qk.page, qk.query
        HAVING SUM(h.clicks) = 0",
        $days
    );

    $results = $wpdb->get_results($query, OBJECT);

    if ($results) {
        wp_cache_set($cache_key, $results, '', self::get_cache_time('inactive-keywords'));
    }

    return $results;
}



/**
 * Fetches keyword terms with the most improvements over a specified time period.
 *
 * This function retrieves keywords that have shown the greatest positive change in search position,
 * along with changes in CTR and impressions.
 *
 * @since 1.0.0
 * @access public
 * @static
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int $days The number of days to look back for keyword improvements.
 * @return array|null Database query results, or null on failure.
 */
public static function get_most_improved_keywords($days) {
    global $wpdb;
    
    $cache_key = 'sb_report_most_improved_keywords_' . $days;
    $cached_data = wp_cache_get($cache_key);
    
    if (false !== $cached_data) {
        return $cached_data;
    }

    $query = $wpdb->prepare(
        "SELECT qk.query, qk.page, 
            MIN(h.position) AS top_position,
            MAX(h.position) AS bottom_position,
            (MAX(h.position) - MIN(h.position)) AS position_change,
            (MAX(h.ctr) - MIN(h.ctr)) AS ctr_change,
            (MAX(h.impressions) - MIN(h.impressions)) AS impressions_change,
            (SELECT h2.position 
             FROM {$wpdb->prefix}sb2_query_keywords_history AS h2 
             WHERE h2.query_keywords_id = qk.id 
             ORDER BY h2.date DESC 
             LIMIT 1) AS current_position
        FROM {$wpdb->prefix}sb2_query_keywords AS qk
        INNER JOIN {$wpdb->prefix}sb2_query_keywords_history AS h 
            ON qk.id = h.query_keywords_id
        WHERE h.date >= DATE_SUB(NOW(), INTERVAL %d DAY)
        GROUP BY qk.query, qk.page
        HAVING position_change > 0
        ORDER BY position_change DESC",
        $days
    );
    $results = $wpdb->get_results($query, OBJECT);

    if ($results) {
        wp_cache_set($cache_key, $results, '', self::get_cache_time('most-improved-keywords'));
    }

    return $results;
}

    /**
     * handle_ajax_report_table.
     *
     * @author	Lars Koudal
     * @since	v0.0.1
     * @version	v1.0.0	Thursday, January 16th, 2025.
     * @access	public static
     * @return	void
     */
    public static function handle_ajax_report_table() {
        $overall_start = microtime(true);
        check_ajax_referer('sb_report_nonce', 'nonce');
        
             // Check user capabilities - restrict to administrators and SEO managers
             if (!current_user_can('manage_options')) {
                wp_send_json_error([
                    'message' => __('You do not have sufficient permissions to access this data.', 'seo-booster')
                ], 403);
            }
        
            
        $table_id = sanitize_text_field($_POST['table_id']);
        $result = self::get_report_data($table_id);
        
        if ($result['data'] === false) {
            wp_send_json_error(['message' => __('Invalid report type', 'seo-booster')]);
        }

        // Convert data to HTML table
        $html = self::generate_table_html($table_id, $result['data']);
        
        // Debug response size
        $response = [
            'html' => $html,
            'performance' => [
                'execution_time' => $result['execution_time'],
                'cached' => $result['cached'],
                'table_id' => $result['table_id']
            ]
        ];
        
        // $response_size = strlen(json_encode($response));

        $overall_time = (microtime(true) - $overall_start) * 1000;
        $response['overall_time'] = $overall_time;
        wp_send_json_success($response);
    }

    private static function get_report_data($table_id) {
        $start_time = microtime(true);
        
        // Generate cache key based on table_id
        $cache_key = 'sb_report_' . sanitize_key($table_id);
        
        // Try to get cached data
        $cached_data = get_transient($cache_key);
        
        // Debug size of cached data
        if ($cached_data !== false) {
            $serialized_size = strlen(serialize($cached_data));
            $json_size = strlen(json_encode($cached_data));

        }


        
        if (false !== $cached_data) {
            $end_time = microtime(true);
            $execution_time = round(($end_time - $start_time) * 1000, 2);
            
            return [
                'data' => $cached_data,
                'cached' => true,
                'execution_time' => $execution_time,
                'table_id' => $table_id
            ];
        }

        
        // If no cached data, get fresh data
        $data = false;
        switch ($table_id) {
            case 'ctr-improvement-opportunities':
                $data = CTRImprovement::get_data();
                break;
            case 'keyword-canabalisations':
            case 'keyword-cannibalization':
                $data = KeywordCannibalization::get_data();
                break;
            case 'long-tail-keywords':
                $data = LongTailKeywords::get_data();
                break;
            case 'top-pages':
                $data = TopPages::get_data();
                break;
            case 'top-performing-keywords':
                $data = TopPerformingKeywords::get_data();
                break;
            case 'declining-keywords':
                $data = DecliningKeywords::get_data();
                break;
            case '404-missing-pages':
                $data = Missing404Pages::get_data();
                break;
            case 'question-queries':
                $data = QuestionQueries::get_data();
                break;
            case 'new-keywords':
                $data = self::get_new_keywords_past_x_days();
                break;
            case 'inactive-keywords':
                $data = self::get_inactive_keywords(30);
                break;
            case 'most-improved-keywords':
                $data = self::get_most_improved_keywords(30);
                break;
            default:

                return false;
        }

        if ($data !== false) {
            $cache_time = self::get_cache_time($table_id);
            $cache_set = set_transient($cache_key, $data, $cache_time);

        }

        $end_time = microtime(true);
        $execution_time = round(($end_time - $start_time) * 1000, 2);

        return [
            'data' => $data,
            'cached' => false,
            'execution_time' => $execution_time,
            'table_id' => $table_id
        ];
    }

    /**
     * Get cache time for different report types.
     *
     * @param string $table_id The report type identifier.
     * @return int Cache time in seconds.
     */
    private static function get_cache_time($table_id) {
       // @todo - 12*1 is not correct, it should be 12*HOUR_IN_SECONDS
        $cache_times = [
            // Real-time data needs shorter cache
            '404-missing-pages' => 5 * MINUTE_IN_SECONDS,
            
            // Medium cache for frequently changing data
            'top-pages' => 12 * HOUR_IN_SECONDS,
            'top-performing-keywords' => 12 * HOUR_IN_SECONDS,
            'declining-keywords' => 12 * HOUR_IN_SECONDS,
            
            // Longer cache for more stable data
            'keyword-canabalisations' => 12 * HOUR_IN_SECONDS,
            'keyword-cannibalization' => 12 * HOUR_IN_SECONDS,
            'long-tail-keywords' => 12 * HOUR_IN_SECONDS,
            'ctr-improvement-opportunities' => 12 * HOUR_IN_SECONDS,
            'question-queries' => 12 * HOUR_IN_SECONDS,
            
            // Historical data can be cached longer
            'new-keywords' => 24 * HOUR_IN_SECONDS,
            'inactive-keywords' => 24 * HOUR_IN_SECONDS,
            'most-improved-keywords' => 24 * HOUR_IN_SECONDS,
        ];

        return $cache_times[$table_id] ?? 24 * HOUR_IN_SECONDS; // Default to 1 hour if not specified
    }

    private static function generate_table_html($table_id, $data) {
        $start_time = microtime(true);
        
        if (empty($data)) {
            return '<div class="wp-list-table-wrapper"><table class="wp-list-table widefat fixed striped"><tr><td colspan="10">' . 
                __('No data available', 'seo-booster') . '</td></tr></table></div>';
        }

        // Pre-generate headers
        $headers = self::get_table_headers($table_id);
        $table_html = '<div class="wp-list-table-wrapper"><table class="wp-list-table widefat fixed striped">';
        $table_html .= '<thead><tr>';
        foreach ($headers as $header) {
            $table_html .= '<th class="column-' . sanitize_title($header) . '">' . esc_html($header) . '</th>';
        }
        $table_html .= '</tr></thead>';

        // Generate rows
        $table_html .= '<tbody>';
        foreach ($data as $row) {
            $table_html .= '<tr>';
            foreach ($row as $key => $value) {
                $formatted_value = self::format_table_cell($key, $value, $table_id);
                $table_html .= '<td>' . $formatted_value . '</td>';
            }
            $table_html .= '</tr>';
        }
        $table_html .= '</tbody></table></div>';
        
        return $table_html;
    }

    private static function format_table_cell($key, $value, $table_id) {

        
        // Special handling for competing_pages array
        if ($key === 'competing_pages' && is_array($value)) {
            return htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8');
        }
        
        // Format numeric values
        if (is_numeric($value)) {
            if (strpos($key, 'ctr') !== false) {
                return number_format($value, 4) . '%';
            } elseif (strpos($key, 'position') !== false) {
                return number_format($value, 1);
            }
        }
        
        return esc_html($value);
    }

    private static function get_table_headers($table_id) {
        // Normalize the table ID for keyword cannibalization
        if ($table_id === 'keyword-cannibalization') {
            $table_id = 'keyword-canabalisations';
        }
        
        $headers = [
            'ctr-improvement-opportunities' => [
                __('Query', 'seo-booster'),
                __('Page', 'seo-booster'),
                __('Clicks', 'seo-booster'),
                __('Impressions', 'seo-booster'),
                __('CTR', 'seo-booster')
            ],
            'keyword-canabalisations' => [
                __('Query', 'seo-booster'),
                __('Competing Pages', 'seo-booster'),
                __('Page Count', 'seo-booster'),
                __('Total Clicks', 'seo-booster'),
                __('Total Impressions', 'seo-booster'),
                __('Avg. CTR', 'seo-booster')
            ],
            'new-keywords' => [
                __('Query', 'seo-booster'),
                __('Page', 'seo-booster'),
                __('First Seen Date', 'seo-booster')
            ],
            'inactive-keywords' => [
                __('Query', 'seo-booster'),
                __('Page', 'seo-booster'),
                __('Latest Date', 'seo-booster'),
                __('Last Known Position', 'seo-booster')
            ],
            'most-improved-keywords' => [
                __('Query', 'seo-booster'),
                __('Page', 'seo-booster'),
                __('Top Position', 'seo-booster'),
                __('Bottom Position', 'seo-booster'),
                __('Position Change', 'seo-booster'),
                __('CTR Change', 'seo-booster'),
                __('Impressions Change', 'seo-booster'),
                __('Current Position', 'seo-booster')
            ],
            '404-missing-pages' => [
                __('URL', 'seo-booster'),
                __('Visits', 'seo-booster'),
                __('First Seen', 'seo-booster'),
                __('Last Seen', 'seo-booster'),
                __('Referrer', 'seo-booster')
            ],
            'long-tail-keywords' => [
                __('Query', 'seo-booster'),
                __('Page', 'seo-booster'),
                __('Position', 'seo-booster'),
                __('Clicks', 'seo-booster'),
                __('Impressions', 'seo-booster'),
                __('CTR', 'seo-booster')
            ],
            'top-pages' => [
                __('Page', 'seo-booster'),
                __('Clicks', 'seo-booster'),
                __('Impressions', 'seo-booster'),
                __('CTR', 'seo-booster'),
                __('Average Position', 'seo-booster')
            ],
            'top-performing-keywords' => [
                __('Query', 'seo-booster'),
                __('Page', 'seo-booster'),
                __('Position', 'seo-booster'),
                __('Clicks', 'seo-booster'),
                __('Impressions', 'seo-booster'),
                __('CTR', 'seo-booster')
            ],
            'declining-keywords' => [
                __('Query', 'seo-booster'),
                __('Page', 'seo-booster'),
                __('Position Change', 'seo-booster'),
                __('Current Position', 'seo-booster'),
                __('Previous Position', 'seo-booster')
            ],
            'question-queries' => [
                __('Query', 'seo-booster'),
                __('Page', 'seo-booster'),
                __('Position', 'seo-booster'),
                __('Clicks', 'seo-booster'),
                __('Impressions', 'seo-booster'),
                __('CTR', 'seo-booster')
            ]
        ];

        return $headers[$table_id] ?? [];
    }

    /**
     * Clear all report caches.
     *
     * @return void
     */
    public static function clear_report_caches() {
        $report_types = [
            'ctr-improvement-opportunities',
            'keyword-canabalisations',
            'keyword-cannibalization',
            'long-tail-keywords',
            'top-pages',
            'top-performing-keywords',
            'declining-keywords',
            '404-missing-pages',
            'question-queries',
            'new-keywords',
            'inactive-keywords',
            'most-improved-keywords'
        ];

        foreach ($report_types as $type) {
            // Use delete_transient instead of wp_cache_delete
            wp_cache_delete( $type );
        }
    }

    /**
     * Handle AJAX request to check cache status
     */
    public static function handle_ajax_cache_status() {
        check_ajax_referer('sb_report_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have sufficient permissions to access this data.', 'seo-booster')
            ], 403);
        }
        
        $table_id = sanitize_text_field($_POST['table_id']);
        $cache_key = 'sb_report_' . sanitize_key($table_id);
        
        // Check if data exists in cache
        $cached_data = get_transient($cache_key);
        
        wp_send_json_success([
            'cached' => ($cached_data !== false),
            'table_id' => $table_id
        ]);
    }
}
Reports::init();
