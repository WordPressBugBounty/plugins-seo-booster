<?php

namespace Cleverplugins\SEOBooster;

if (!defined('ABSPATH')) {
    exit;
}

class SB_GSC_Processor
{
    public static function init()
    {
        add_action('sb_gsc_process_keywords_batch', [__CLASS__, 'process_keywords_batch'], 10, 3);
        add_action('sb_gsc_schedule_all_pages', [__CLASS__, 'schedule_keyword_processing_for_all_pages']);
        add_action('sb_gsc_analyze_post_keywords', [__CLASS__, 'analyze_post_keywords']);
        
    }
    /**
     * Process a batch of keywords for a given URL.
     *
     * @param string $post_url The URL of the post to process.
     * @param array $keywords Array of keywords to process.
     * @param int $batch_index The batch index for processing.
     */
    public static function process_keywords_batch($post_url, $keywords, $batch_index)
    {
        global $wpdb;
        $start_time = microtime(true);
        $post_id = url_to_postid($post_url);
        $cache_response = CacheManager::fetch_and_cache_url_content($post_url, ['post_id' => $post_id]);
        $post_content = $cache_response['content'] ?? '';
        $current_time = current_time('mysql');
        $keyword_count = count($keywords);
        foreach ($keywords as $keyword) {
            $keyword_id = $keyword['id'];
            $keyword_query = $keyword['query'];

            $occurrences = Google_API::check_keyword_occurrences__premium_only($post_content, $keyword_query, $post_id);
            $is_used = !empty($occurrences);

            $wpdb->update(
                $wpdb->prefix . 'sb2_query_keywords',
                [
                    'is_used_in_content' => $is_used ? 1 : 0,
                    'last_checked' => $current_time,
                ],
                ['id' => $keyword_id],
                ['%d', '%s'],
                ['%d']
            );

            $transient_key = 'sb_gsc_keyword_usage_' . $post_id . '_' . $keyword_id;
            set_transient($transient_key, $is_used ? '1' : '0', DAY_IN_SECONDS);
        }

        $parsed_url = wp_parse_url($post_url);
        $stripped_url = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $time_taken = round(microtime(true) - $start_time, 4);
        // translators: %1$s is the batch index, %2$s is the URL path, %3$s is the number of keywords
        Utils::log(sprintf(__('Analyzed keyword usage for URL: %1$s in %2$s seconds (%3$s keywords)', 'seo-booster'), $stripped_url, $time_taken, $keyword_count));
    }

    /**
     * Schedule keyword processing for all pages.
     *
     * @return void
     */
    public static function schedule_keyword_processing_for_all_pages()
    {
        global $wpdb;
        $unique_pages = $wpdb->get_col(
            "SELECT DISTINCT page 
            FROM {$wpdb->prefix}sb2_query_keywords"
        );

        foreach ($unique_pages as $page_url) {
            $keywords = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, query 
                    FROM {$wpdb->prefix}sb2_query_keywords 
                    WHERE page = %s",
                    $page_url
                ), 
                ARRAY_A
            );

            // Split keywords into batches
            $keyword_batches = array_chunk($keywords, 50);

            foreach ($keyword_batches as $batch_index => $keyword_batch) {
                $has_pending_action = as_has_scheduled_action(
                    'sb_gsc_process_keywords_batch',
                    ['post_url' => $page_url, 'keywords' => $keyword_batch, 'batch_index' => $batch_index],
                    'sb_gsc_keyword_processing'
                );

                if (!$has_pending_action) {
                    as_schedule_single_action(
                        time() + ($batch_index * 10), // Stagger the batches 
                        'sb_gsc_process_keywords_batch',
                        ['post_url' => $page_url, 'keywords' => $keyword_batch, 'batch_index' => $batch_index],
                        'sb_gsc_keyword_processing'
                    );
                }
            }
        }
        Utils::log(__('Scheduled keyword processing for all pages', 'seo-booster'));
    }

    public static function analyze_post_keywords($post_id) {
        $post_url = get_permalink($post_id);
        if (!$post_url) {
            Utils::log(__('Failed to get URL for post ID: ', 'seo-booster') . $post_id);
            return;
        }

        global $wpdb;
        $query_keywords_table = $wpdb->prefix . 'sb2_query_keywords';

        $keywords = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, query 
                FROM {$wpdb->prefix}sb2_query_keywords 
                WHERE page = %s",
                $post_url
            ),
            ARRAY_A
        );

        $keyword_batches = array_chunk($keywords, 100);

        foreach ($keyword_batches as $batch_index => $keyword_batch) {
            self::process_keywords_batch($post_url, $keyword_batch, $batch_index);
        }

        Utils::log(__('Completed keyword analysis for post ID: ', 'seo-booster') . $post_id);
    }
}

SB_GSC_Processor::init();