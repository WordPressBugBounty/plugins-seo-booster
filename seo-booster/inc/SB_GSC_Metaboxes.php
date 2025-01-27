<?php

namespace Cleverplugins\SEOBooster;

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '/Google_API.php';
require_once plugin_dir_path(__FILE__) . '/SB_GSC_Ajax.php';
require_once plugin_dir_path(__FILE__) . '/SB_GSC_Processor.php';

/**
 * Class SB_GSC_Metaboxes
 *
 * Handles the Google Search Console metaboxes for SEO Booster.
 *
 * @package Cleverplugins\SEOBooster
 * @since 0.0.1
 */
class SB_GSC_Metaboxes
{
    /**
     * Initialize the class and set up hooks.
     *
     * @since 0.0.1
     * @return void
     */
    public static function init()
    {
        add_action('add_meta_boxes', [__CLASS__, 'add_gsc_metabox']);
        add_action('wp_ajax_sb_gsc_delete_transients', [__CLASS__, 'delete_transients']);
    }

    /**
     * Add the Google Search Console metabox to public post types.
     *
     * @since 0.0.1
     * @return void
     */
    public static function add_gsc_metabox()
    {
        $post_types = get_post_types(['public' => true], 'names');
        foreach ($post_types as $post_type) {
            add_meta_box(
                'sb_gsc_keywords',
                __('SEO Booster Keyword Analysis', 'seo-booster'),
                [__CLASS__, 'render_metabox'],
                $post_type,
                'normal',
                'default'
            );
        }
    }

    /**
     * Render the Google Search Console metabox content.
     *
     * @since 0.0.1
     * @param WP_Post $post The current post object.
     * @return void
     */
    public static function render_metabox($post)
    {
        $seo_plug_name = Google_API::identify_active_seo_plugin();

        if ($seo_plug_name) {
            // show detected SEO plugin
            echo '<p>' . esc_html__('Detected SEO plugin: ', 'seo-booster') . esc_html($seo_plug_name['name']);

            $get_focus_keyword = Google_API::get_focus_keywords($post->ID);

            if ($get_focus_keyword) {
                echo ' | ' . esc_html__('Focus keyword(s)', 'seo-booster') . ': ';
                foreach ($get_focus_keyword as $focus_keyword) {
                    echo '<p><span class="label">' . esc_html($focus_keyword) . '</span> </p>';
                }
            } else {
                echo ' | ' . esc_html__('No focus keyword found.', 'seo-booster');
            }
            echo '</p>';
        }
        echo '<div id="sb-gsc-keywords-container"></div>';
    }

    /**
     * Enqueue scripts and styles for the metabox.
     *
     * @since 0.0.1
     * @param string $hook The current admin page.
     * @return void
     */
    public static function enqueue_scripts($hook)
    {
        // Only enqueue on post edit pages
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        $js_file_path = SEOBOOSTER_PLUGINPATH . 'js/min/sb-gsc-metabox-min.js';
        $css_file_path = SEOBOOSTER_PLUGINPATH . 'css/min/sb-gsc-metabox-min.css';

        $js_version = file_exists($js_file_path) ? filemtime($js_file_path) : Seobooster2::get_plugin_version();
        $css_version = file_exists($css_file_path) ? filemtime($css_file_path) : Seobooster2::get_plugin_version();

        wp_enqueue_script('sb-gsc-metabox', SEOBOOSTER_PLUGINURL . 'js/min/sb-gsc-metabox-min.js', array('jquery'), $js_version, true);
        wp_enqueue_style('sb-gsc-metabox', SEOBOOSTER_PLUGINURL . 'css/min/sb-gsc-metabox-min.css', array(), $css_version);

        // Tabulator
        wp_enqueue_style('tabulator', SEOBOOSTER_PLUGINURL . '/js/tabulator/dist/css/tabulator.min.css');
        wp_enqueue_script('tabulator', SEOBOOSTER_PLUGINURL . '/js/tabulator/dist/js/tabulator.min.js', ['jquery'], Seobooster2::get_plugin_version(), true);


        // get the permalink of the post
        $post_url = get_permalink(get_the_ID());

        wp_localize_script('sb-gsc-metabox', 'sb_gsc_metabox_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_id' => get_the_ID(),
            'post_url' => $post_url,
            'security' => wp_create_nonce('sb_gsc_nonce'),
            'analyzing_text' => esc_js(__('Analyzing...', 'seo-booster')),
            'refresh_text' => esc_js(__('Refresh Analysis', 'seo-booster')),
            'strings' => array(
                'analyzing' => __('Analyzing...', 'seo-booster'),
                'scriptLoaded' => __('GSC Metabox script loaded', 'seo-booster'),
                'searchPlaceholder' => __('Search...', 'seo-booster'),
                'allKeywords' => __('All Keywords', 'seo-booster'),
                'keywordsNoClicks' => __('Keywords with no clicks', 'seo-booster'),
                'keywordsNotUsedInContent' => __('Keywords not used in content', 'seo-booster'),
                'keywordsUsedInContent' => __('Keywords used in content', 'seo-booster'),
                'resetFilters' => __('Reset Filters', 'seo-booster'),
                'query' => __('Query', 'seo-booster'),
                'firstSeen' => __('First Seen', 'seo-booster'),
                'lastSeen' => __('Last Seen', 'seo-booster'),
                'showingKeywords' => __('Showing', 'seo-booster'),
                'seenInContent' => __('Seen in content?', 'seo-booster'),
                'clicks' => __('Clicks', 'seo-booster'),
                'impressions' => __('Impressions', 'seo-booster'),
                'ctr' => __('CTR', 'seo-booster'),
                'position' => __('Position', 'seo-booster'),
                'autolink' => __('Autolink', 'seo-booster'),
                'hideColumn' => __('Hide Column', 'seo-booster'),
                'showAllColumns' => __('Show All Columns', 'seo-booster'),
                'time' => __('Time', 'seo-booster'),
                'totalKeywords' => __('Total Keywords', 'seo-booster'),
                'noKeywordsFound' => __('No keywords found.', 'seo-booster'),
                'error' => __('Error', 'seo-booster'),
                'copySelected' => __('Copy Selected', 'seo-booster'),
                'copiedToClipboard' => __('Copied to clipboard', 'seo-booster'),
                'showHideColumns' => __('Show/Hide Columns', 'seo-booster'),
                'refreshKeywords' => __('Refresh Keywords', 'seo-booster'),
                'copyAll' => __('Copy All', 'seo-booster'),
                'refreshKeywordAnalysis' => __('Refresh Keyword Analysis', 'seo-booster'),
                'errorDeletingTransients' => __('Error deleting transients', 'seo-booster'),
            ),
        ));
        
    }

    /**
     * delete_transients.
     *
     * @author	Lars Koudal
     * @since	v0.0.1
     * @version	v1.0.0	Wednesday, October 16th, 2024.
     * @access	public static
     * @return	void
     */
    public static function delete_transients() {
        check_ajax_referer('sb_gsc_nonce', 'security');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'seo-booster')]);
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID', 'seo-booster')]);
        }

        global $wpdb;
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_sb_gsc_keyword_usage_' . $post_id . '_') . '%',
            $wpdb->esc_like('_transient_timeout_sb_gsc_keyword_usage_' . $post_id . '_') . '%'
        ));

        if ($deleted === false) {
            wp_send_json_error(['message' => __('Failed to delete transients', 'seo-booster')]);
        }

        // Schedule and run keyword processing for this post
        $scheduled_jobs = self::schedule_and_run_keyword_processing_for_page($post_id);

        wp_send_json_success([
        // translators: 1: number of deleted transients, 2: number of scheduled jobs, 3: post ID
            'message' => sprintf(__('Successfully deleted %1$d transients and scheduled %2$d jobs for post ID %3$d', 'seo-booster'), $deleted, $scheduled_jobs, $post_id),
            'deleted_count' => $deleted,
            'scheduled_jobs' => $scheduled_jobs,
            'post_id' => $post_id
        ]);
    }

    /**
     * schedule_and_run_keyword_processing_for_page.
     *
     * @author	Lars Koudal
     * @since	v0.0.1
     * @version	v1.0.0	Wednesday, October 16th, 2024.
     * @access	private static
     * @param	mixed	$post_id	
     * @return	mixed
     */
    private static function schedule_and_run_keyword_processing_for_page($post_id) {
        global $wpdb;
        $query_keywords_table = $wpdb->prefix . 'sb2_query_keywords';
        $post_url = get_permalink($post_id);

        $table_keywords = esc_sql($query_keywords_table);
        
        $keywords = wp_cache_get('sb_gsc_keywords_' . $post_id, 'sb_gsc');
        if (false === $keywords) {
            $keywords = $wpdb->get_results($wpdb->prepare("SELECT id, query FROM {$table_keywords} WHERE page = %s", $post_url), ARRAY_A);
            wp_cache_set('sb_gsc_keywords_' . $post_id, $keywords, 'sb_gsc', 3600); // Cache for 1 hour
        }

        // Split keywords into batches of 100 (adjust this number as needed)
        $keyword_batches = array_chunk($keywords, 10);
        $scheduled_jobs = 0;

        foreach ($keyword_batches as $batch_index => $keyword_batch) {
            // Schedule the action to run immediately
            as_enqueue_async_action(
                'sb_gsc_process_keywords_batch',
                ['post_url' => $post_url, 'keywords' => $keyword_batch, 'batch_index' => $batch_index],
                'sb_gsc_keyword_processing'
            );
            $scheduled_jobs++;
        }

        // Attempt to run the queue immediately
        self::maybe_run_queue();

        return $scheduled_jobs;
    }

    /**
     * maybe_run_queue.
     *
     * @author	Lars Koudal
     * @since	v0.0.1
     * @version	v1.0.0	Wednesday, October 16th, 2024.
     * @access	private static
     * @return	void
     */
    private static function maybe_run_queue() {
        if (function_exists('as_has_scheduled_action') && !as_has_scheduled_action('action_scheduler_run_queue')) {
            as_enqueue_async_action('action_scheduler_run_queue', [], 'action-scheduler');
        }
    }
}

SB_GSC_Metaboxes::init();
