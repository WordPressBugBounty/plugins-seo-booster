<?php

namespace Cleverplugins\SEOBooster;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SEO_Analysis
 *
 * Handles SEO analysis for posts, pages, and taxonomies.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */
class SEO_Analysis
{
    /**
     * Analysis results.
     *
     * @var array
     */
    private $results = [];

    /**
     * Post or term object.
     *
     * @var \WP_Post|\WP_Term|null
     */
    private $object;

    /**
     * Object ID.
     *
     * @var int
     */
    private $object_id;

    /**
     * Object type (post, term).
     *
     * @var string
     */
    private $object_type;

    /**
     * SEO data for the object.
     *
     * @var array
     */
    private $seo_data;

    /**
     * Content to analyze.
     *
     * @var string
     */
    private $content;

    /**
     * Full page content (downloaded from URL).
     *
     * @var string
     */
    private $full_page_content;

    /**
     * GSC checks status tracking.
     *
     * @var array
     */
    private $gsc_checks_status = [];

    /**
     * Initialize the analysis.
     *
     * @since 6.1.26
     * @param int $object_id Object ID.
     * @param string $object_type Object type (post, term).
     * @return void
     */
    public function __construct($object_id, $object_type = 'post')
    {
        $this->object_id = (int) $object_id;
        $this->object_type = $object_type;
        $this->seo_data = [];
        $this->content = '';
        $this->full_page_content = '';

        if ($object_type === 'post') {
            $post = get_post($object_id);
            if ($post instanceof \WP_Post) {
                $this->object = $post;
                $this->content = (string) $post->post_content;
            }
            return;
        }

        if ($object_type === 'term' && $this->object_id > 0) {
            $term = get_term($this->object_id);
            if (! is_wp_error($term) && $term instanceof \WP_Term) {
                $this->object = $term;
                $this->content = isset($term->description) ? (string) $term->description : '';
            }
        }
    }

    /**
     * Set current input values for real-time analysis.
     *
     * @since 6.1.26
     * @param string $title Current title value.
     * @param string $description Current description value.
     * @param string $focus_keyword Current focus keyword value.
     * @return void
     */
    public function set_current_values($title, $description, $focus_keyword)
    {
        $this->seo_data['title'] = $title;
        $this->seo_data['description'] = $description;
        $this->seo_data['focus_keyword'] = $focus_keyword;
    }

    /**
     * Run the complete SEO analysis.
     *
     * @since 6.1.26
     * @param bool $use_full_page Whether to analyze full page content instead of just post content.
     * @return array Analysis results.
     */
    public function analyze($use_full_page = false, $force_download = false)
    {
        // Exclude private posts and WooCommerce special pages
        if ($this->object_type === 'post' && $this->object_id && SEO_Issues_Manager::should_exclude_from_analysis($this->object_id)) {
            $this->results = [
                'score' => null,
                'issues' => [],
                'improvements' => [],
                'good' => [],
                'excluded' => true
            ];
            return $this->results;
        }

        $this->results = [
            'score' => 0,
            'issues' => [],
            'improvements' => [],
            'good' => []
        ];

        // Store current input values before refreshing
        $current_title = $this->seo_data['title'] ?? '';
        $current_description = $this->seo_data['description'] ?? '';
        $current_focus_keyword = $this->seo_data['focus_keyword'] ?? '';

        // Refresh SEO data to get the latest values from database
        $this->refresh_seo_data();

        // Restore current input values (they override database values for real-time analysis)
        if (!empty($current_title)) {
            $this->seo_data['title'] = $current_title;
        }
        if (!empty($current_description)) {
            $this->seo_data['description'] = $current_description;
        }
        if (!empty($current_focus_keyword)) {
            $this->seo_data['focus_keyword'] = $current_focus_keyword;
        }

        // Handle full page content download
        if ($use_full_page) {
            if ($force_download) {
                // Force redownload even if cached content exists
                $this->download_full_page_content();
            } else {
                // Try to use existing downloaded content first, download if not available
                $has_downloaded_content = $this->load_existing_downloaded_content();
                if (!$has_downloaded_content) {
                    $this->download_full_page_content();
                }
            }
        }

        // Determine if we have full page content for analysis
        $has_full_page = !empty($this->full_page_content);

        // Run all analysis checks
        $this->check_title();
        $this->check_meta_description();
        $this->check_focus_keyword();
        $this->check_noindex_status();
        $this->check_content_length();
        $this->check_heading_structure();
        $this->check_image_alt_text();
        $this->check_internal_links();
        $this->check_external_links();
        $this->check_keyword_density();
        $this->check_readability();
        $this->check_duplicate_content();
        $this->check_duplicate_titles();
        $this->check_duplicate_meta_descriptions();
        $this->check_broken_images();
        $this->check_external_links_validation();
        $this->check_contact_info();
        
        // Link validation checks (only run if we have full page content for better accuracy)
        if ($has_full_page) {
            $this->check_broken_external_links();
            $this->check_broken_internal_links();
        }
        
        // Additional checks that benefit from full page analysis
        if ($has_full_page) {
            $this->check_structured_data();
            $this->check_open_graph();
            $this->check_twitter_cards();
            $this->check_canonical_url();
            $this->check_robots_meta();
            $this->check_page_speed_indicators();
            $this->check_rel_author();
            $this->check_meta_viewport();
            $this->check_favicon();
            $this->check_language_declaration();
            $this->check_accessibility_basics();
            $this->check_content_readability();
        }

        // Initialize GSC checks status tracking
        $this->gsc_checks_status = [];

        // Check GSC status if GSC is connected
        $this->check_gsc_status();

        // Check GSC-based SEO possibilities
        $this->check_gsc_low_ctr_good_position();
        $this->check_gsc_high_impressions_low_clicks();
        $this->check_gsc_keywords_not_in_content();
        $this->check_gsc_keyword_cannibalization();
        $this->check_gsc_longtail_opportunities();
        $this->check_gsc_content_freshness();
        $this->check_gsc_question_queries();

        // Calculate overall score
        $this->calculate_score();

        // Save the analysis results
        $this->save_analysis_results($has_full_page);

        return $this->results;
    }

    /**
     * Save analysis results to the database.
     *
     * @since 6.1.26
     * @param bool $is_full_page Whether this was a full page analysis.
     * @return void
     */
    private function save_analysis_results($is_full_page = false)
    {
        $url = $this->get_object_url();
        
        // Add metadata to results
        $this->results['metadata'] = [
            'timestamp' => time(),
            'is_full_page' => $is_full_page,
            'content_length' => strlen($this->get_analysis_content()),
            'url' => $url
        ];

        // Save to new database system
        $analysis_id = SEO_Issues_Manager::save_analysis_to_db(
            $this->object_id,
            $this->object_type,
            $url,
            $this->results
        );
        
        if (!$analysis_id) {
            Utils::log('Failed to save analysis results', 2);
            return false;
        }

        return true;
    }

    /**
     * Get saved analysis results from the database.
     *
     * @since 6.1.26
     * @return array|null Saved analysis results or null if not found.
     */
    public static function get_saved_analysis($object_id, $object_type = 'post')
    {
        return SEO_Issues_Manager::get_saved_analysis($object_id, $object_type);
    }

    /**
     * Refresh SEO data from the database.
     *
     * @since 6.1.26
     * @return void
     */
    private function refresh_seo_data()
    {
        if ($this->object_type === 'post') {
            // Get SEO data from active SEO plugin
            $seo_plugin_data = Google_API::get_seo_title_and_description($this->object_id);
            $this->seo_data = [
                'title' => $seo_plugin_data['title'] ?? '',
                'description' => $seo_plugin_data['description'] ?? '',
            ];
            
            // Get focus keyword from active SEO plugin
            $plugin_keywords = Google_API::get_focus_keywords($this->object_id);
            if (!empty($plugin_keywords)) {
                $this->seo_data['focus_keyword'] = $plugin_keywords[0]; // Use the first focus keyword
            }
        } else {
            $this->seo_data = [];
        }
    }

    /**
     * Get the URL of the current object.
     *
     * @since 6.1.26
     * @return string The object URL.
     */
    private function get_object_url()
    {
        if ($this->object_type === 'post') {
            return get_permalink($this->object_id);
        } else {
            return get_term_link($this->object_id);
        }
    }

    /**
     * Load existing downloaded content if available.
     *
     * @since 6.1.26
     * @return bool True if downloaded content was loaded, false otherwise.
     */
    private function load_existing_downloaded_content()
    {
        if ($this->object_type !== 'post') {
            return false; // Only for posts
        }

        $post_url = get_permalink($this->object_id);
        if (!$post_url) {
            return false;
        }

        // Try to get cached content
        $cache_response = CacheManager::fetch_and_cache_url_content($post_url, [
            'post_id' => $this->object_id,
            'content_type' => 'post',
            'item_id' => $this->object_id
        ]);

        if (!empty($cache_response['content'])) {
            $this->full_page_content = $cache_response['content'];
            return true;
        }

        return false;
    }

    /**
     * Download and cache full page content for analysis.
     *
     * @since 6.1.26
     * @return void
     */
    private function download_full_page_content()
    {
        if ($this->object_type !== 'post') {
            return; // Only download for posts
        }

        $post_url = get_permalink($this->object_id);
        if (!$post_url) {
            return;
        }

        // Use CacheManager to fetch and cache the page content
        $cache_response = CacheManager::fetch_and_cache_url_content($post_url, [
            'post_id' => $this->object_id,
            'content_type' => 'post',
            'item_id' => $this->object_id
        ]);

        if (!empty($cache_response['content'])) {
            $this->full_page_content = $cache_response['content'];
            
            // Store the download timestamp
            update_post_meta($this->object_id, '_sb_last_page_download', time());
        }
    }

    /**
     * Get content for analysis (either post content or full page content).
     *
     * @since 6.1.26
     * @return string Content to analyze.
     */
    private function get_analysis_content()
    {
        if (! empty($this->full_page_content)) {
            return (string) $this->full_page_content;
        }

        return (string) $this->content;
    }

    /**
     * Check title optimization.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_title()
    {
        $focus_keyword = $this->seo_data['focus_keyword'] ?? '';

        // Prefer live HTML <title> from full page content if available
        $title = '';
        $content = $this->get_analysis_content();
        if (!empty($this->full_page_content) && !empty($content)) {
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $content, $m)) {
                $title = trim(html_entity_decode(strip_tags($m[1])));
            }
        }
        // Fallback to stored SEO data if live title missing
        if (empty($title)) {
            $title = $this->seo_data['title'] ?? '';
        }
        // Final fallback to WordPress post title
        if (empty($title) && $this->object_type === 'post' && $this->object) {
            $title = trim((string) $this->object->post_title);
        }

        if (empty($title)) {
            $this->add_issue('title_missing', __('No title has been set.', 'seo-booster'), 'error');
            return;
        }

        if (!empty($focus_keyword) && stripos($title, $focus_keyword) === false) {
            $this->add_issue('title_no_keyword', sprintf(__('The focus keyword "%s" does not appear in the title.', 'seo-booster'), $focus_keyword), 'warning');
        } elseif (!empty($focus_keyword)) {
            $this->add_good('title_has_keyword', sprintf(__('The focus keyword "%s" appears in the title.', 'seo-booster'), $focus_keyword));
        }
    }

    /**
     * Check meta description optimization.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_meta_description()
    {
        $description = '';
        $focus_keyword = $this->seo_data['focus_keyword'] ?? '';

        // Prefer live HTML <meta name="description" content="..."> from full page content if available
        $content = $this->get_analysis_content();
        if (!empty($this->full_page_content) && !empty($content)) {
            if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $content, $m)) {
                $description = trim(html_entity_decode($m[1]));
            }
        }
        // Fallback to stored SEO data if live meta description missing
        if (empty($description)) {
            $description = $this->seo_data['description'] ?? '';
        }
        // Fallback to Yoast meta if available
        if (empty($description) && $this->object_type === 'post') {
            $yoast_desc = get_post_meta($this->object_id, '_yoast_wpseo_metadesc', true);
            if (!empty($yoast_desc)) {
                $description = $yoast_desc;
            }
        }

        if (empty($description)) {
            $this->add_issue('description_missing', __('No meta description has been set.', 'seo-booster'), 'error');
            return;
        }

        if (!empty($focus_keyword) && stripos($description, $focus_keyword) === false) {
            $this->add_issue('description_no_keyword', sprintf(__('The focus keyword "%s" does not appear in the meta description.', 'seo-booster'), $focus_keyword), 'warning');
        } elseif (!empty($focus_keyword)) {
            $this->add_good('description_has_keyword', sprintf(__('The focus keyword "%s" appears in the meta description.', 'seo-booster'), $focus_keyword));
        }
    }

    /**
     * Check focus keyword optimization.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_focus_keyword()
    {
        $focus_keyword = $this->seo_data['focus_keyword'] ?? '';

        if (empty($focus_keyword)) {
            $this->add_issue('keyword_missing', __('Consider setting a focus keyword to help with SEO targeting.', 'seo-booster'), 'low');
            return;
        }

        $this->add_good('keyword_set', __('A focus keyword has been set.', 'seo-booster'));
    }

    /**
     * Check noindex status.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_noindex_status()
    {
        $noindex = $this->seo_data['noindex'] ?? 0;

        if ($noindex) {
            $this->add_improvement('noindex_set', __('This page is set to noindex. Search engines will not index this page.', 'seo-booster'));
        } else {
            $this->add_good('indexable', __('This page is set to be indexed by search engines.', 'seo-booster'));
        }
    }

    /**
     * Check content length.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_content_length()
    {
        // For posts/pages, always use WordPress's rendered output for accurate content length
        // This excludes theme elements and properly handles page builders, shortcodes, blocks, and iframes
        if ($this->object_type === 'post' && !empty($this->content) && $this->object) {
            // Use apply_filters to render content (handles shortcodes, blocks, page builders, etc.)
            $content = (string) apply_filters('the_content', $this->content);
        } else {
            // For taxonomies, use the current approach (term descriptions don't need rendering)
            $content = $this->get_analysis_content();
        }
        
        $word_count = str_word_count(strip_tags($content));
        
        if ($word_count < 300) {
            $this->add_issue('content_too_short', sprintf(__('The content is too short (%d words detected). Aim for at least 300 words.', 'seo-booster'), $word_count), 'warning');
        } elseif ($word_count > 3000) {
            $this->add_improvement('content_long', __('The content is quite long. Consider breaking it into multiple sections.', 'seo-booster'));
        } else {
            $this->add_good('content_length', __('The content has a good length.', 'seo-booster'));
        }
    }

    /**
     * Check heading structure.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_heading_structure()
    {
        $content = $this->get_analysis_content();
        
        // Extract H1 tags with their content - handle attributes and nested elements
        // Pattern matches: <h1 with any attributes>content including nested tags</h1>
        preg_match_all('/<h1\b[^>]*>(.*?)<\/h1>/is', $content, $h1_matches);
        $h1_count = 0;
        $h1_texts = [];
        
        foreach ($h1_matches[1] as $h1_content) {
            // Strip HTML tags and check if there's actual text content
            // This handles nested elements like <span> inside H1 tags
            $text_content = trim(strip_tags($h1_content));
            if (!empty($text_content)) {
                $h1_count++;
                $h1_texts[] = $text_content;
            }
        }
        
        // If no H1 found with the standard pattern, try a more permissive pattern
        // This handles edge cases where tags might be malformed or have unusual nesting
        if ($h1_count === 0) {
            // Try matching H1 tags even with potential whitespace or formatting issues
            preg_match_all('/<h1\b[^>]*>([\s\S]*?)<\/h1>/i', $content, $h1_matches_alt);
            foreach ($h1_matches_alt[1] as $h1_content) {
                $text_content = trim(strip_tags($h1_content));
                if (!empty($text_content)) {
                    $h1_count++;
                    $h1_texts[] = $text_content;
                    break; // Only need one match
                }
            }
        }
        
        $h2_count = preg_match_all('/<h2[^>]*>/i', $content);
        
        if ($h1_count === 0) {
            // If we're not analyzing full page HTML, many themes render the title outside post content.
            // In that case, treat the WordPress post title as the H1.
            if ($this->object_type === 'post' && $this->object && !empty($this->object->post_title) && empty($this->full_page_content)) {
                $this->add_good('h1_ok', sprintf(__('Good H1 heading structure. Found: "%s"', 'seo-booster'), $this->object->post_title));
            } else {
                $this->add_issue('no_h1', __('No H1 heading found. Add an H1 heading to your content.', 'seo-booster'), 'warning');
            }
        } elseif ($h1_count > 1) {
            $this->add_issue('multiple_h1', __('Multiple H1 headings found. Use only one H1 per page.', 'seo-booster'), 'warning');
        } else {
            $h1_text = !empty($h1_texts) ? $h1_texts[0] : '';
            $this->add_good('h1_ok', sprintf(__('Good H1 heading structure. Found: "%s"', 'seo-booster'), $h1_text));
        }

        if ($h2_count === 0 && str_word_count(strip_tags($content)) > 500) {
            $this->add_improvement('no_h2', __('Consider adding H2 headings to structure your content.', 'seo-booster'));
        } elseif ($h2_count > 0) {
            $this->add_good('has_h2_headings', sprintf(__('Found %d H2 heading(s) for good content structure.', 'seo-booster'), $h2_count));
        }
    }

    /**
     * Check image alt text.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_image_alt_text()
    {
        $content = $this->get_analysis_content();
        preg_match_all('/<img[^>]+>/i', $content, $images, PREG_OFFSET_CAPTURE);
        $images_without_alt = 0;
        $images_with_empty_alt = 0;
        $total_images = count($images[0]);
        $images_without_alt_list = [];
        $images_with_empty_alt_list = [];
        $max_examples = 50;

        foreach ($images[0] as $match) {
            $img = $match[0];
            $img_position = $match[1];
            
            // Filter out hidden elements
            if ($this->is_hidden_element($img)) {
                continue;
            }
            
            // Check if alt attribute exists
            if (preg_match('/alt\s*=\s*["\']([^"\']*)["\']/', $img, $matches)) {
                $alt_text = trim($matches[1]);
                // Check if alt text is empty (alt="" or alt='')
                if (empty($alt_text)) {
                    $images_with_empty_alt++;
                    
                    // Collect examples (max 50)
                    if (count($images_with_empty_alt_list) < $max_examples) {
                        // Extract URL from img tag
                        $image_url = '';
                        if (preg_match('/src=["\']([^"\']+)["\']/', $img, $url_match)) {
                            $image_url = $url_match[1];
                        }
                        
                        // Get line number and context
                        $line_info = $this->get_line_number_and_context($content, $img_position);
                        
                        $images_with_empty_alt_list[] = [
                            'html' => $img,
                            'url' => $image_url,
                            'line' => $line_info['line'],
                            'context' => $line_info['context']
                        ];
                    }
                }
            } else {
                // No alt attribute at all
                $images_without_alt++;
                
                // Collect examples (max 50)
                if (count($images_without_alt_list) < $max_examples) {
                    // Extract URL from img tag
                    $image_url = '';
                    if (preg_match('/src=["\']([^"\']+)["\']/', $img, $url_match)) {
                        $image_url = $url_match[1];
                    }
                    
                    // Get line number and context
                    $line_info = $this->get_line_number_and_context($content, $img_position);
                    
                    $images_without_alt_list[] = [
                        'html' => $img,
                        'url' => $image_url,
                        'line' => $line_info['line'],
                        'context' => $line_info['context']
                    ];
                }
            }
        }

        $total_problematic_images = $images_without_alt + $images_with_empty_alt;

        if ($total_images > 0) {
            if ($total_problematic_images === $total_images) {
                if ($images_with_empty_alt > 0 && $images_without_alt > 0) {
                    $extra_data = [];
                    if (!empty($images_without_alt_list)) {
                        $extra_data['images_without_alt'] = $images_without_alt_list;
                    }
                    if (!empty($images_with_empty_alt_list)) {
                        $extra_data['images_with_empty_alt'] = $images_with_empty_alt_list;
                    }
                    $this->add_issue('all_alt_text_problems', sprintf(__('All %d images have alt text issues: %d missing alt attributes and %d with empty alt text (alt=""). Add meaningful alt text to your images.', 'seo-booster'), $total_images, $images_without_alt, $images_with_empty_alt), 'error', !empty($extra_data) ? $extra_data : null);
                } elseif ($images_with_empty_alt > 0) {
                    $this->add_issue('all_empty_alt_text', sprintf(__('All %d images have empty alt text (alt=""). Add meaningful alt text to your images.', 'seo-booster'), $total_images), 'error', !empty($images_with_empty_alt_list) ? ['images_with_empty_alt' => $images_with_empty_alt_list] : null);
                } else {
                    $this->add_issue('no_alt_text', __('All images are missing alt text. Add alt text to your images.', 'seo-booster'), 'error', !empty($images_without_alt_list) ? ['images_without_alt' => $images_without_alt_list] : null);
                }
            } elseif ($total_problematic_images > 0) {
                if ($images_with_empty_alt > 0 && $images_without_alt > 0) {
                    $extra_data = [];
                    if (!empty($images_without_alt_list)) {
                        $extra_data['images_without_alt'] = $images_without_alt_list;
                    }
                    if (!empty($images_with_empty_alt_list)) {
                        $extra_data['images_with_empty_alt'] = $images_with_empty_alt_list;
                    }
                    $this->add_issue('mixed_alt_text_problems', sprintf(__('%d out of %d images have alt text issues: %d missing alt attributes and %d with empty alt text (alt="").', 'seo-booster'), $total_problematic_images, $total_images, $images_without_alt, $images_with_empty_alt), 'warning', !empty($extra_data) ? $extra_data : null);
                } elseif ($images_with_empty_alt > 0) {
                    $this->add_issue('some_empty_alt_text', sprintf(__('%d out of %d images have empty alt text (alt=""). Add meaningful alt text to these images.', 'seo-booster'), $images_with_empty_alt, $total_images), 'warning', !empty($images_with_empty_alt_list) ? ['images_with_empty_alt' => $images_with_empty_alt_list] : null);
                } else {
                    $this->add_issue('some_alt_text', sprintf(__('%d out of %d images are missing alt text.', 'seo-booster'), $images_without_alt, $total_images), 'warning', !empty($images_without_alt_list) ? ['images_without_alt' => $images_without_alt_list] : null);
                }
            } else {
                $this->add_good('alt_text_ok', __('All images have meaningful alt text.', 'seo-booster'));
            }
        }
    }

    /**
     * Check internal links.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_internal_links()
    {
        $home_url = home_url();
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $this->get_analysis_content(), $links);
        
        $internal_links = 0;
        foreach ($links[1] as $link) {
            if (strpos($link, $home_url) === 0 || strpos($link, '/') === 0) {
                $internal_links++;
            }
        }

        if ($internal_links === 0 && str_word_count(strip_tags($this->get_analysis_content())) > 500) {
            $this->add_improvement('no_internal_links', __('Consider adding internal links to other relevant pages.', 'seo-booster'));
        } elseif ($internal_links > 0) {
            $this->add_good('has_internal_links', sprintf(__('Found %d internal link(s).', 'seo-booster'), $internal_links));
        }
    }

    /**
     * Check external links.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_external_links()
    {
        $home_url = home_url();
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $this->get_analysis_content(), $links);
        
        $external_links = 0;
        foreach ($links[1] as $link) {
            if (strpos($link, 'http') === 0 && strpos($link, $home_url) === false) {
                $external_links++;
            }
        }

        if ($external_links > 0) {
            $this->add_good('has_external_links', sprintf(__('Found %d external link(s).', 'seo-booster'), $external_links));
        }
    }

    /**
     * Check keyword density.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_keyword_density()
    {
        $focus_keyword = $this->seo_data['focus_keyword'] ?? '';
        
        if (empty($focus_keyword)) {
            return;
        }

        $content_text = strip_tags($this->get_analysis_content());
        $word_count = str_word_count($content_text);
        $keyword_count = substr_count(strtolower($content_text), strtolower($focus_keyword));
        
        if ($word_count > 0) {
            $density = ($keyword_count / $word_count) * 100;
            
            if ($density < 0.5) {
                $this->add_issue('keyword_density_low', __('The focus keyword density is too low. Consider using it more often.', 'seo-booster'), 'warning');
            } elseif ($density > 3) {
                $this->add_issue('keyword_density_high', __('The focus keyword density is too high. Consider reducing it.', 'seo-booster'), 'warning');
            } else {
                $this->add_good('keyword_density_ok', __('The focus keyword density is good.', 'seo-booster'));
            }
        }
    }

    /**
     * Check readability.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_readability()
    {
        $content_text = strip_tags($this->get_analysis_content());
        $sentences = preg_split('/[.!?]+/', $content_text);
        $words = str_word_count($content_text);
        $syllables = $this->count_syllables($content_text);
        
        if (count($sentences) > 0 && $words > 0) {
            $avg_sentence_length = $words / count($sentences);
            $avg_syllables_per_word = $syllables / $words;
            
            if ($avg_sentence_length > 20) {
                $this->add_improvement('sentence_length', __('Consider using shorter sentences for better readability.', 'seo-booster'));
            } else {
                $this->add_good('sentence_length', __('Good sentence length for readability.', 'seo-booster'));
            }
        }
    }

    /**
     * Check for duplicate content.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_duplicate_content()
    {
        // This is a basic check - in a real implementation, you might want to check against a database
        $content_hash = md5(strip_tags($this->get_analysis_content()));
        
        // For now, just check if content is too short (which might indicate duplicate)
        if (str_word_count(strip_tags($this->get_analysis_content())) < 50) {
            $this->add_issue('content_too_short_duplicate', __('Content is very short. This might be considered duplicate content.', 'seo-booster'), 'warning');
        } else {
            $this->add_good('content_sufficient', __('Content appears to be substantial and unique.', 'seo-booster'));
        }
    }

    /**
     * Check for structured data (JSON-LD, microdata, RDFa).
     *
     * @since 6.1.26
     * @return void
     */
    private function check_structured_data()
    {
        $content = $this->get_analysis_content();
        
        // Check for JSON-LD structured data
        $json_ld_count = preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $content);
        
        // Check for microdata
        $microdata_count = preg_match_all('/itemscope[^>]*>/i', $content);
        
        // Check for RDFa
        $rdfa_count = preg_match_all('/vocab=["\'][^"\']*["\']/i', $content);
        
        $total_structured_data = $json_ld_count + $microdata_count + $rdfa_count;
        
        if ($total_structured_data === 0) {
            $this->add_issue('no_structured_data', __('No structured data found. Consider adding JSON-LD, microdata, or RDFa markup.', 'seo-booster'), 'warning');
        } else {
            $this->add_good('has_structured_data', sprintf(__('Found %d structured data element(s).', 'seo-booster'), $total_structured_data));
        }
    }

    /**
     * Check for Open Graph meta tags.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_open_graph()
    {
        $content = $this->get_analysis_content();
        
        $og_tags = [
            'og:title' => 'og:title',
            'og:description' => 'og:description',
            'og:image' => 'og:image',
            'og:url' => 'og:url',
            'og:type' => 'og:type'
        ];
        
        $found_tags = 0;
        $missing_tags = [];
        
        foreach ($og_tags as $tag => $name) {
            if (preg_match('/<meta[^>]*property=["\']' . preg_quote($tag, '/') . '["\'][^>]*>/i', $content)) {
                $found_tags++;
            } else {
                $missing_tags[] = $name;
            }
        }
        
        if ($found_tags === 0) {
            $this->add_issue('no_open_graph', __('No Open Graph meta tags found. Add og:title, og:description, og:image, og:url, and og:type.', 'seo-booster'), 'warning');
        } elseif (count($missing_tags) > 0) {
            $this->add_issue('incomplete_open_graph', sprintf(__('Missing Open Graph tags: %s', 'seo-booster'), implode(', ', $missing_tags)), 'warning');
        } else {
            $this->add_good('complete_open_graph', __('All essential Open Graph meta tags are present.', 'seo-booster'));
        }
    }

    /**
     * Check for Twitter Card meta tags.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_twitter_cards()
    {
        $content = $this->get_analysis_content();
        
        $twitter_tags = [
            'twitter:card' => 'twitter:card',
            'twitter:title' => 'twitter:title',
            'twitter:description' => 'twitter:description',
            'twitter:image' => 'twitter:image'
        ];
        
        $found_tags = 0;
        $missing_tags = [];
        
        foreach ($twitter_tags as $tag => $name) {
            if (preg_match('/<meta[^>]*name=["\']' . preg_quote($tag, '/') . '["\'][^>]*>/i', $content)) {
                $found_tags++;
            } else {
                $missing_tags[] = $name;
            }
        }
        
        if ($found_tags === 0) {
            $this->add_improvement('no_twitter_cards', __('No Twitter Card meta tags found. Consider adding twitter:card, twitter:title, twitter:description, and twitter:image.', 'seo-booster'));
        } elseif (count($missing_tags) > 0) {
            $this->add_improvement('incomplete_twitter_cards', sprintf(__('Missing Twitter Card tags: %s', 'seo-booster'), implode(', ', $missing_tags)));
        } else {
            $this->add_good('complete_twitter_cards', __('All essential Twitter Card meta tags are present.', 'seo-booster'));
        }
    }

    /**
     * Check for canonical URL.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_canonical_url()
    {
        $content = $this->get_analysis_content();
        $canonical_url = $this->get_object_url();
        
        if (preg_match('/<link[^>]*rel=["\']canonical["\'][^>]*>/i', $content)) {
            $this->add_good('has_canonical', __('Canonical URL is properly set.', 'seo-booster'));
        } else {
            $this->add_improvement('no_canonical', sprintf(__('No canonical URL found. Consider adding a canonical link tag pointing to: %s', 'seo-booster'), $canonical_url));
        }
    }

    /**
     * Check robots meta tags.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_robots_meta()
    {
        $content = $this->get_analysis_content();
        
        if (preg_match('/<meta[^>]*name=["\']robots["\'][^>]*>/i', $content)) {
            $this->add_good('has_robots_meta', __('Robots meta tag is present.', 'seo-booster'));
        } else {
            $this->add_improvement('no_robots_meta', __('No robots meta tag found. Consider adding one to control search engine crawling.', 'seo-booster'));
        }
    }

    /**
     * Check for page speed indicators.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_page_speed_indicators()
    {
        $content = $this->get_analysis_content();
        
        // Check for large images without optimization
        preg_match_all('/<img[^>]+>/i', $content, $images, PREG_OFFSET_CAPTURE);
        $large_images = 0;
        $images_without_dimensions_list = [];
        $max_examples = 50;
        
        foreach ($images[0] as $match) {
            $img = $match[0];
            $img_position = $match[1];
            
            // Filter out hidden elements
            if ($this->is_hidden_element($img)) {
                continue;
            }
            
            // Check for images without width/height attributes (layout shift indicator)
            if (!preg_match('/(width|height)=["\'][^"\']*["\']/', $img)) {
                $large_images++;
                
                // Collect examples (max 50)
                if (count($images_without_dimensions_list) < $max_examples) {
                    // Extract URL from img tag
                    $image_url = '';
                    if (preg_match('/src=["\']([^"\']+)["\']/', $img, $url_match)) {
                        $image_url = $url_match[1];
                    }
                    
                    // Get line number and context
                    $line_info = $this->get_line_number_and_context($content, $img_position);
                    
                    $images_without_dimensions_list[] = [
                        'html' => $img,
                        'url' => $image_url,
                        'line' => $line_info['line'],
                        'context' => $line_info['context']
                    ];
                }
            }
        }
        
        if ($large_images > 0) {
            $this->add_improvement('images_without_dimensions', sprintf(__('%d image(s) without width/height attributes. Add dimensions to prevent layout shift.', 'seo-booster'), $large_images), [
                'images_without_dimensions' => $images_without_dimensions_list
            ]);
        }
        
        // Check for inline CSS (can impact page speed)
        $inline_css_count = preg_match_all('/<style[^>]*>/i', $content);
        if ($inline_css_count > 3) {
            $this->add_issue('excessive_inline_css', sprintf(__('%d inline style blocks found. Consider moving CSS to external files.', 'seo-booster'), $inline_css_count), 'warning');
        }
        
        // Check for external resources
        preg_match_all('/<link[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $content, $external_links);
        $external_css_count = 0;
        $external_js_count = 0;
        
        foreach ($external_links[1] as $link) {
            if (strpos($link, 'http') === 0 && strpos($link, home_url()) === false) {
                if (preg_match('/\.css["\']?$/', $link)) {
                    $external_css_count++;
                } elseif (preg_match('/\.js["\']?$/', $link)) {
                    $external_js_count++;
                }
            }
        }
        
        if ($external_css_count > 5) {
            $this->add_improvement('many_external_css', sprintf(__('%d external CSS files found. Consider combining or using fewer external resources.', 'seo-booster'), $external_css_count));
        }
        
        if ($external_js_count > 5) {
            $this->add_improvement('many_external_js', sprintf(__('%d external JavaScript files found. Consider combining or using fewer external resources.', 'seo-booster'), $external_js_count));
        }
        
        // Add good practices if no issues found
        if ($large_images === 0 && $inline_css_count <= 3 && $external_css_count <= 5 && $external_js_count <= 5) {
            $this->add_good('page_speed_optimized', __('Good page speed indicators. No major performance issues detected.', 'seo-booster'));
        }
    }

    /**
     * Check for rel="author" links for E-E-A-T improvement.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_rel_author()
    {
        $content = $this->get_analysis_content();
        
        // Check for rel="author" links
        $author_links = preg_match_all('/<a[^>]*rel=["\'][^"\']*author[^"\']*["\'][^>]*>/i', $content);
        
        if ($author_links === 0) {
            $this->add_improvement('no_rel_author', __('No rel="author" links found. Consider adding author attribution links to improve E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness).', 'seo-booster'));
        } else {
            $this->add_good('has_rel_author', sprintf(__('Found %d rel="author" link(s). This helps establish content authorship and improves E-E-A-T.', 'seo-booster'), $author_links));
        }
    }

    /**
     * Check for contact information for E-E-A-T improvement.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_contact_info()
    {
        $content = $this->get_analysis_content();
        
        // Check for common contact information patterns
        $contact_patterns = [
            'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            'phone' => '/[\+]?[1-9]?[0-9]{7,15}/',
            'address' => '/\b\d+\s+[A-Za-z\s]+(?:Street|St|Avenue|Ave|Road|Rd|Boulevard|Blvd|Lane|Ln|Drive|Dr|Way|Place|Pl)\b/i'
        ];
        
        $found_contact = 0;
        foreach ($contact_patterns as $type => $pattern) {
            if (preg_match($pattern, $content)) {
                $found_contact++;
            }
        }
        
        // Only show warning for pages, not posts
        if ($this->object_type === 'post' && $this->object && $this->object->post_type === 'page') {
            if ($found_contact === 0) {
                $this->add_issue('no_contact_info', __('No contact information found. Consider adding contact details to improve trustworthiness and E-E-A-T.', 'seo-booster'), 'warning');
            } else {
                $this->add_good('has_contact_info', sprintf(__('Found contact information (%d type(s)). This improves trustworthiness and E-E-A-T.', 'seo-booster'), $found_contact));
            }
        }
    }

    /**
     * Check for duplicate titles across the site.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_duplicate_titles()
    {
        $title = $this->seo_data['title'] ?? '';
        
        if (empty($title)) {
            return;
        }

        global $wpdb;
        $all_duplicates = [];
        
        // Get SEO plugin meta key for title
        $meta_keys = Google_API::get_seo_plugin_meta_keys('title');
        $title_key = $meta_keys['title_key'] ?? '';
        
        // Check for duplicate titles in posts (SEO titles from active SEO plugin)
        if (!empty($title_key) && $this->object_type === 'post') {
            $duplicate_posts = $wpdb->get_results($wpdb->prepare(
                "SELECT p.ID, p.post_title, 'post_seo' as type
                 FROM {$wpdb->posts} p 
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                 WHERE pm.meta_key = %s 
                 AND pm.meta_value = %s 
                 AND p.ID != %d 
                 AND p.post_status = 'publish'",
                $title_key,
                $title,
                $this->object_id
            ));
            
            foreach ($duplicate_posts as $post) {
                $all_duplicates[] = $post;
            }
        }
        
        // Also check against actual post titles
        $duplicate_post_titles = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, 'post_title' as type
             FROM {$wpdb->posts} 
             WHERE post_title = %s 
             AND ID != %d 
             AND post_status = 'publish'",
            $title,
            $this->object_id
        ));
        
        foreach ($duplicate_post_titles as $post) {
            $all_duplicates[] = $post;
        }
        
        $duplicate_count = count($all_duplicates);

        if ($duplicate_count > 0) {
            $duplicate_links = [];
            
            // Process all duplicates
            foreach ($all_duplicates as $duplicate) {
                if ($duplicate->type === 'taxonomy') {
                    $edit_link = get_edit_term_link($duplicate->ID);
                    $duplicate_links[] = '<a href="' . esc_url($edit_link) . '" target="_blank">' . esc_html($duplicate->post_title) . '</a>';
                } else {
                    $edit_link = get_edit_post_link($duplicate->ID);
                    $duplicate_links[] = '<a href="' . esc_url($edit_link) . '" target="_blank">' . esc_html($duplicate->post_title) . '</a>';
                }
            }
            
            // Limit to 8 links and create message
            $limited_links = array_slice($duplicate_links, 0, 8);
            $links_text = implode(', ', $limited_links);
            
            if (count($duplicate_links) > 8) {
                $links_text .= ' and ' . (count($duplicate_links) - 8) . ' more';
            }
            
            $message = sprintf(__('This SEO title is already used by %d other post(s) or term(s). Consider making it unique.', 'seo-booster'), $duplicate_count);
            $message .= '<br><small>Used by: ' . $links_text . '</small>';
            
            $this->add_issue('duplicate_title', $message, 'warning');
        } else {
            $this->add_good('unique_title', __('This title is unique across your site.', 'seo-booster'));
        }
    }

    /**
     * Check for duplicate meta descriptions across the site.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_duplicate_meta_descriptions()
    {
        $description = $this->seo_data['description'] ?? '';
        
        if (empty($description)) {
            return;
        }

        global $wpdb;
        $all_duplicates = [];
        
        // Get SEO plugin meta key for description
        $meta_keys = Google_API::get_seo_plugin_meta_keys('description');
        $description_key = $meta_keys['description_key'] ?? '';
        
        // Check for duplicate meta descriptions in posts (from active SEO plugin)
        if (!empty($description_key) && $this->object_type === 'post') {
            $duplicate_posts = $wpdb->get_results($wpdb->prepare(
                "SELECT p.ID, p.post_title, 'post' as type
                 FROM {$wpdb->posts} p 
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                 WHERE pm.meta_key = %s 
                 AND pm.meta_value = %s 
                 AND p.ID != %d 
                 AND p.post_status = 'publish'",
                $description_key,
                $description,
                $this->object_id
            ));
            
            foreach ($duplicate_posts as $post) {
                $all_duplicates[] = $post;
            }
        }
        
        $duplicate_count = count($all_duplicates);

        if ($duplicate_count > 0) {
            $duplicate_links = [];
            
            // Process all duplicates
            foreach ($all_duplicates as $duplicate) {
                if ($duplicate->type === 'taxonomy') {
                    $edit_link = get_edit_term_link($duplicate->ID);
                    $duplicate_links[] = '<a href="' . esc_url($edit_link) . '" target="_blank">' . esc_html($duplicate->post_title) . '</a>';
                } else {
                    $edit_link = get_edit_post_link($duplicate->ID);
                    $duplicate_links[] = '<a href="' . esc_url($edit_link) . '" target="_blank">' . esc_html($duplicate->post_title) . '</a>';
                }
            }
            
            // Limit to 8 links and create message
            $limited_links = array_slice($duplicate_links, 0, 8);
            $links_text = implode(', ', $limited_links);
            
            if (count($duplicate_links) > 8) {
                $links_text .= ' and ' . (count($duplicate_links) - 8) . ' more';
            }
            
            $message = sprintf(__('This meta description is already used by %d other post(s) or term(s). Consider making it unique.', 'seo-booster'), $duplicate_count);
            $message .= '<br><small>Used by: ' . $links_text . '</small>';
            
            $this->add_issue('duplicate_meta_description', $message, 'warning');
        } else {
            $this->add_good('unique_meta_description', __('This meta description is unique across your site.', 'seo-booster'));
        }
    }

    /**
     * Normalize image URL to absolute URL.
     *
     * Converts relative URLs and protocol-relative URLs to fully qualified absolute URLs.
     *
     * @since 6.1.26
     * @param string $url Image URL (can be relative, protocol-relative, or absolute).
     * @return string|false Normalized absolute URL, or false if invalid.
     */
    private function normalize_image_url($url)
    {
        if (empty($url)) {
            return false;
        }

        // Skip data URLs
        if (strpos($url, 'data:') === 0) {
            return false;
        }

        // Already absolute URL
        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            return $url;
        }

        // Protocol-relative URL (//example.com/image.jpg)
        if (strpos($url, '//') === 0) {
            // Use the same protocol as the current site
            $protocol = is_ssl() ? 'https:' : 'http:';
            return $protocol . $url;
        }

        // Relative URL starting with /
        if (strpos($url, '/') === 0) {
            return home_url($url);
        }

        // Invalid URL format
        return false;
    }

    /**
     * Check image status via HTTP.
     *
     * Verifies if an image URL is accessible via HTTP. Uses HEAD request first,
     * with GET fallback for servers that don't support HEAD.
     *
     * @since 6.1.26
     * @param string $url Image URL to check.
     * @return array Status array with keys: 'status' (working|broken|redirected), 'status_code' (int), 'error' (string|null).
     */
    private function check_image_status($url)
    {
        if (empty($url) || strpos($url, 'http') !== 0) {
            return [
                'status' => 'broken',
                'status_code' => 0,
                'error' => __('Invalid image URL', 'seo-booster')
            ];
        }

        $args = [
            'timeout' => 10,
            'redirection' => 0, // Don't follow redirects automatically
            'user-agent' => 'SEO Booster Image Checker/1.0',
        ];

        // Try HEAD request first (lightweight)
        $response = wp_remote_head($url, $args);

        // If HEAD fails or returns error, try GET with Range header as fallback
        if (is_wp_error($response)) {
            $get_args = $args;
            $get_args['headers'] = [
                'Range' => 'bytes=0-0',
            ];
            $response = wp_remote_get($url, $get_args);
        } elseif (!is_wp_error($response)) {
            $status_code = wp_remote_retrieve_response_code($response);
            // Some servers return 405/501 for HEAD, fallback to GET
            if (in_array($status_code, [405, 501])) {
                $get_args = $args;
                $get_args['headers'] = [
                    'Range' => 'bytes=0-0',
                ];
                $response = wp_remote_get($url, $get_args);
            }
        }

        // Handle errors
        if (is_wp_error($response)) {
            return [
                'status' => 'broken',
                'status_code' => 0,
                'error' => $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $headers = wp_remote_retrieve_headers($response);

        // Check for redirects (301, 302, etc.) - consider these as working
        if (in_array($status_code, [301, 302, 303, 307, 308])) {
            $location = $headers->offsetGet('location');
            return [
                'status' => 'redirected',
                'status_code' => $status_code,
                'redirect_to' => $location,
                'error' => null
            ];
        }

        // Check for error status codes (400+)
        if ($status_code >= 400) {
            return [
                'status' => 'broken',
                'status_code' => $status_code,
                'error' => sprintf(__('HTTP %d error', 'seo-booster'), $status_code)
            ];
        }

        // Image is working (200-399)
        return [
            'status' => 'working',
            'status_code' => $status_code,
            'error' => null
        ];
    }

    /**
     * Check for broken image URLs.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_broken_images()
    {
        $content = $this->get_analysis_content();
        // Get full img tags with positions
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $images, PREG_OFFSET_CAPTURE);
        
        if (empty($images[0])) {
            return;
        }

        $broken_images = 0;
        $external_images = 0;
        $broken_images_list = [];
        $external_images_list = [];
        $max_examples = 50;
        $max_checks = 25; // Limit HTTP checks to prevent timeouts
        $checked_count = 0;
        
        $home_url = home_url();
        $home_url_http = str_replace('https://', 'http://', $home_url);
        $home_url_https = str_replace('http://', 'https://', $home_url);
        
        foreach ($images[0] as $index => $match) {
            $full_img_tag = $match[0];
            $img_position = $match[1];
            $image_url = $images[1][$index][0];
            
            // Filter out hidden elements
            if ($this->is_hidden_element($full_img_tag)) {
                continue;
            }
            
            // Normalize URL (handles relative, protocol-relative, and absolute URLs)
            $normalized_url = $this->normalize_image_url($image_url);
            if ($normalized_url === false) {
                // Skip invalid URLs (data URLs, etc.)
                continue;
            }
            
            // Check if we've reached the limit for HTTP checks
            if ($checked_count >= $max_checks) {
                // Still count external images without checking
                // Determine if it's external by checking against home_url variants
                $is_external = (
                    strpos($normalized_url, $home_url) !== 0 &&
                    strpos($normalized_url, $home_url_http) !== 0 &&
                    strpos($normalized_url, $home_url_https) !== 0
                );
                
                if ($is_external) {
                    $external_images++;
                    
                    if (count($external_images_list) < $max_examples) {
                        $line_info = $this->get_line_number_and_context($content, $img_position);
                        $external_images_list[] = [
                            'html' => $full_img_tag,
                            'url' => $image_url,
                            'line' => $line_info['line'],
                            'context' => $line_info['context']
                        ];
                    }
                }
                continue;
            }
            
            // Determine if it's a local or external image
            // Check against both http and https versions of home_url to handle protocol mismatches
            $is_local = (
                strpos($normalized_url, $home_url) === 0 ||
                strpos($normalized_url, $home_url_http) === 0 ||
                strpos($normalized_url, $home_url_https) === 0
            );
            
            // Check image status via HTTP
            $checked_count++;
            $image_status = $this->check_image_status($normalized_url);
            
            if ($image_status['status'] === 'broken') {
                // Image is broken
                $broken_images++;
                
                if (count($broken_images_list) < $max_examples) {
                    $line_info = $this->get_line_number_and_context($content, $img_position);
                    
                    $error_message = $image_status['error'];
                    if ($image_status['status_code'] > 0) {
                        $error_message = sprintf(__('HTTP %d: %s', 'seo-booster'), $image_status['status_code'], $error_message);
                    }
                    
                    $broken_images_list[] = [
                        'url' => $image_url,
                        'error' => $error_message,
                        'line' => $line_info['line'],
                        'context' => $line_info['context']
                    ];
                }
            }
            
            // Track external images (regardless of whether they're broken or not)
            if (!$is_local) {
                $external_images++;
                
                if (count($external_images_list) < $max_examples && $image_status['status'] !== 'broken') {
                    $line_info = $this->get_line_number_and_context($content, $img_position);
                    $external_images_list[] = [
                        'html' => $full_img_tag,
                        'url' => $image_url,
                        'line' => $line_info['line'],
                        'context' => $line_info['context']
                    ];
                }
            }
        }
        
        // Report broken images
        if ($broken_images > 0) {
            $this->add_issue('broken_images', sprintf(__('%d broken image(s) found. Check and fix the image URLs.', 'seo-booster'), $broken_images), 'error', [
                'broken_images' => $broken_images_list
            ]);
        }
        
        // Report external images (warning, not error)
        if ($external_images > 0) {
            $this->add_issue('external_images', sprintf(__('%d external image(s) found. Consider hosting images locally for better performance and reliability.', 'seo-booster'), $external_images), 'warning', !empty($external_images_list) ? ['external_images' => $external_images_list] : null);
        }
        
        // Report success if no issues
        if ($broken_images === 0 && $external_images === 0) {
            $this->add_good('images_ok', __('All images appear to be working correctly.', 'seo-booster'));
        }
    }

    /**
     * Check external links and warn about them.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_external_links_validation()
    {
        $content = $this->get_analysis_content();
        $home_url = home_url();
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $links);
        
        $external_links = 0;
        $suspicious_domains = 0;
        
        // List of potentially suspicious domains (can be expanded)
        $suspicious_domains_list = [
            'bit.ly', 'tinyurl.com', 'goo.gl', 't.co', 'ow.ly', 'short.link'
        ];
        
        foreach ($links[1] as $link) {
            if (strpos($link, 'http') === 0 && strpos($link, $home_url) === false) {
                $external_links++;
                
                // Check for suspicious domains
                foreach ($suspicious_domains_list as $domain) {
                    if (strpos($link, $domain) !== false) {
                        $suspicious_domains++;
                        break;
                    }
                }
            }
        }
        
        if ($external_links > 0) {
            $this->add_good('has_external_links', sprintf(__('Found %d external link(s).', 'seo-booster'), $external_links));
            
            if ($suspicious_domains > 0) {
                $this->add_issue('suspicious_links', sprintf(__('%d link(s) use URL shorteners. Consider using direct links for better SEO.', 'seo-booster'), $suspicious_domains), 'warning');
            }
        }
    }

    /**
     * Check for broken or redirected external links.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_broken_external_links()
    {
        $content = $this->get_analysis_content();
        $home_url = home_url();
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $links);
        
        $broken_links = [];
        $redirected_links = [];
        $checked_links = 0;
        $max_checks = 10; // Limit to prevent timeout
        
        foreach ($links[1] as $link) {
            if ($checked_links >= $max_checks) {
                break; // Stop checking after max_checks to prevent timeout
            }
            
            // Only check external links
            if (strpos($link, 'http') === 0 && strpos($link, $home_url) === false) {
                $checked_links++;
                $link_status = $this->check_link_status($link);
                
                if ($link_status['status'] === 'broken') {
                    $broken_links[] = [
                        'url' => $link,
                        'error' => $link_status['error']
                    ];
                } elseif ($link_status['status'] === 'redirected') {
                    $redirected_links[] = [
                        'url' => $link,
                        'redirect_to' => $link_status['redirect_to'],
                        'status_code' => $link_status['status_code']
                    ];
                }
            }
        }
        
        // Report broken links
        if (!empty($broken_links)) {
            $broken_count = count($broken_links);
            $message = sprintf(__('%d broken external link(s) found. These links return errors and should be fixed or removed.', 'seo-booster'), $broken_count);
            
            $this->add_issue('broken_external_links', $message, 'error', [
                'broken_links' => $broken_links,
                'total_checked' => $checked_links
            ]);
        }
        
        // Report redirected links
        if (!empty($redirected_links)) {
            $redirected_count = count($redirected_links);
            $message = sprintf(__('%d redirected external link(s) found. Consider updating these links to point directly to the final destination.', 'seo-booster'), $redirected_count);
            
            $this->add_issue('redirected_external_links', $message, 'warning', [
                'redirected_links' => $redirected_links,
                'total_checked' => $checked_links
            ]);
        }
        
        // Report if no issues found
        if (empty($broken_links) && empty($redirected_links) && $checked_links > 0) {
            $this->add_good('external_links_ok', sprintf(__('All %d checked external link(s) are working correctly.', 'seo-booster'), $checked_links));
        }
    }

    /**
     * Check for broken or redirected internal links.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_broken_internal_links()
    {
        $content = $this->get_analysis_content();
        $home_url = home_url();
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $links);
        
        $broken_links = [];
        $redirected_links = [];
        $checked_links = 0;
        $max_checks = 15; // Higher limit for internal links since they're faster to check
        
        foreach ($links[1] as $link) {
            if ($checked_links >= $max_checks) {
                break; // Stop checking after max_checks to prevent timeout
            }
            
            // Convert relative URLs to absolute URLs for checking
            $absolute_url = $link;
            if (strpos($link, '/') === 0) {
                $absolute_url = $home_url . $link;
            } elseif (strpos($link, 'http') !== 0) {
                continue; // Skip non-HTTP links
            }
            
            // Only check internal links
            if (strpos($absolute_url, $home_url) === 0) {
                $checked_links++;
                $link_status = $this->check_link_status($absolute_url);
                
                if ($link_status['status'] === 'broken') {
                    $broken_links[] = [
                        'url' => $link,
                        'absolute_url' => $absolute_url,
                        'error' => $link_status['error']
                    ];
                } elseif ($link_status['status'] === 'redirected') {
                    $redirected_links[] = [
                        'url' => $link,
                        'absolute_url' => $absolute_url,
                        'redirect_to' => $link_status['redirect_to'],
                        'status_code' => $link_status['status_code']
                    ];
                }
            }
        }
        
        // Report broken links
        if (!empty($broken_links)) {
            $broken_count = count($broken_links);
            $message = sprintf(__('%d broken internal link(s) found. These links return errors and should be fixed or removed.', 'seo-booster'), $broken_count);
            
            $this->add_issue('broken_internal_links', $message, 'error', [
                'broken_links' => $broken_links,
                'total_checked' => $checked_links
            ]);
        }
        
        // Report redirected links
        if (!empty($redirected_links)) {
            $redirected_count = count($redirected_links);
            $message = sprintf(__('%d redirected internal link(s) found. Consider updating these links to point directly to the final destination.', 'seo-booster'), $redirected_count);
            
            $this->add_issue('redirected_internal_links', $message, 'warning', [
                'redirected_links' => $redirected_links,
                'total_checked' => $checked_links
            ]);
        }
        
        // Report if no issues found
        if (empty($broken_links) && empty($redirected_links) && $checked_links > 0) {
            $this->add_good('internal_links_ok', sprintf(__('All %d checked internal link(s) are working correctly.', 'seo-booster'), $checked_links));
        }
    }

    /**
     * Check the status of a link (broken, redirected, or working).
     *
     * @since 6.1.26
     * @param string $url URL to check.
     * @return array Link status information.
     */
    private function check_link_status($url)
    {
        // Use WordPress HTTP API with a short timeout
        $response = wp_remote_head($url, [
            'timeout' => 10,
            'redirection' => 0, // Don't follow redirects automatically
            'user-agent' => 'SEO Booster Link Checker/1.0'
        ]);
        
        if (is_wp_error($response)) {
            return [
                'status' => 'broken',
                'error' => $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $headers = wp_remote_retrieve_headers($response);
        
        // Check for redirects
        if (in_array($status_code, [301, 302, 303, 307, 308])) {
            $location = $headers->offsetGet('location');
            return [
                'status' => 'redirected',
                'status_code' => $status_code,
                'redirect_to' => $location
            ];
        }
        
        // Check for error status codes
        if ($status_code >= 400) {
            return [
                'status' => 'broken',
                'error' => sprintf(__('HTTP %d error', 'seo-booster'), $status_code)
            ];
        }
        
        // Link is working
        return [
            'status' => 'working',
            'status_code' => $status_code
        ];
    }

    /**
     * Count syllables in text.
     *
     * @since 6.1.26
     * @param string $text Text to analyze.
     * @return int Syllable count.
     */
    private function count_syllables($text)
    {
        $words = explode(' ', strtolower($text));
        $syllables = 0;
        
        foreach ($words as $word) {
            $syllables += max(1, preg_match_all('/[aeiouy]+/', $word));
        }
        
        return $syllables;
    }

    /**
     * Check if an HTML element should be filtered (hidden/template code).
     *
     * @since 6.1.26
     * @param string $html HTML element to check.
     * @return bool True if element should be filtered, false otherwise.
     */
    private function is_hidden_element($html)
    {
        // Check for hidden attribute
        if (preg_match('/\bhidden\b/i', $html)) {
            return true;
        }
        
        // Check for display: none in style attribute
        if (preg_match('/style\s*=\s*["\'][^"\']*display\s*:\s*none[^"\']*["\']/i', $html)) {
            return true;
        }
        
        // Check for WordPress Interactivity API bindings
        if (preg_match('/data-wp-bind|data-wp-on/i', $html)) {
            return true;
        }
        
        // Check for aria-hidden="true"
        if (preg_match('/aria-hidden\s*=\s*["\']true["\']/i', $html)) {
            return true;
        }
        
        return false;
    }

    /**
     * Get line number and context for a match position in content.
     *
     * @since 6.1.26
     * @param string $content Full content string.
     * @param int $position Character position of the match.
     * @param int $context_lines Number of lines before and after to include in context.
     * @return array Array with 'line' (line number) and 'context' (context snippet).
     */
    private function get_line_number_and_context($content, $position, $context_lines = 2)
    {
        $before = substr($content, 0, $position);
        $line_number = substr_count($before, "\n") + 1;
        
        // Get context (2-3 lines before and after)
        $lines = explode("\n", $content);
        $start_line = max(0, $line_number - $context_lines - 1);
        $end_line = min(count($lines), $line_number + $context_lines);
        $context_lines_array = array_slice($lines, $start_line, $end_line - $start_line);
        $context = implode("\n", $context_lines_array);
        
        // Truncate context if too long (max 300 characters)
        if (strlen($context) > 300) {
            $context = substr($context, 0, 297) . '...';
        }
        
        return [
            'line' => $line_number,
            'context' => $context
        ];
    }

    /**
     * Add an issue to the results.
     *
     * @since 6.1.26
     * @param string $key Issue key.
     * @param string $message Issue message.
     * @param string $severity Issue severity (error, warning).
     * @param array $extra_data Additional data to store with the issue.
     * @return void
     */
    private function add_issue($key, $message, $severity = 'warning', $extra_data = null)
    {
        $issue = [
            'key' => $key,
            'message' => $message,
            'severity' => $severity
        ];
        
        if ($extra_data !== null) {
            $issue['extra_data'] = $extra_data;
        }
        
        $this->results['issues'][] = $issue;
    }

    /**
     * Add an improvement to the results.
     *
     * @since 6.1.26
     * @param string $key Improvement key.
     * @param string $message Improvement message.
     * @param array $extra_data Additional data to store with the improvement.
     * @return void
     */
    private function add_improvement($key, $message, $extra_data = null)
    {
        $improvement = [
            'key' => $key,
            'message' => $message
        ];
        
        if ($extra_data !== null) {
            $improvement['extra_data'] = $extra_data;
        }
        
        $this->results['improvements'][] = $improvement;
    }

    /**
     * Add a good practice to the results.
     *
     * @since 6.1.26
     * @param string $key Good practice key.
     * @param string $message Good practice message.
     * @return void
     */
    private function add_good($key, $message)
    {
        $this->results['good'][] = [
            'key' => $key,
            'message' => $message
        ];
    }

    /**
     * Record GSC check status.
     *
     * @since 7.0.0
     * @param string $check_name Check identifier (e.g., 'low_ctr_good_position').
     * @param string $status Status: 'data_found', 'no_data', or 'not_applicable'.
     * @param string $reason Optional reason for the status.
     * @return void
     */
    private function record_gsc_check_status($check_name, $status, $reason = '')
    {
        $this->gsc_checks_status[$check_name] = [
            'status' => $status,
            'reason' => $reason
        ];
    }


    /**
     * Calculate overall SEO score.
     *
     * @since 6.1.26
     * @return void
     */
    private function calculate_score()
    {
        $error_count = 0;
        $warning_count = 0;
        
        // Safely handle null/empty arrays
        $issues = $this->results['issues'] ?? [];
        $good = $this->results['good'] ?? [];
        $improvements = $this->results['improvements'] ?? [];
        
        foreach ($issues as $issue) {
            if ($issue['severity'] === 'error') {
                $error_count++;
            } else {
                $warning_count++;
            }
        }

        $good_count = count($good);
        $improvement_count = count($improvements);

        // Calculate score using percentage-based deductions
        // Errors: Deduct 5% per error (max 50% total)
        // Warnings: Deduct 2.5% per warning (max 30% total)
        // Improvements: Deduct 1% per improvement (max 15% total)
        // Good practices: Add 0.5% per good practice (max 10% bonus)
        
        $error_deduction = min(50, $error_count * 5);
        $warning_deduction = min(30, $warning_count * 2.5);
        $improvement_deduction = min(15, $improvement_count * 1);
        $good_bonus = min(10, $good_count * 0.5);
        
        $score = 100 - $error_deduction - $warning_deduction - $improvement_deduction + $good_bonus;
        
        $this->results['score'] = max(0, min(100, round($score)));
    }

    /**
     * Check for meta viewport tag.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_meta_viewport()
    {
        $content = $this->get_analysis_content();
        
        if (preg_match('/<meta[^>]*name=["\']viewport["\'][^>]*>/i', $content)) {
            $this->add_good('viewport_ok', __('Meta viewport tag found. Good for mobile-friendliness.', 'seo-booster'));
        } else {
            $this->add_improvement('no_viewport', __('Consider adding a meta viewport tag for mobile-friendliness.', 'seo-booster'));
        }
    }

    /**
     * Check for favicon.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_favicon()
    {
        $content = $this->get_analysis_content();
        
        if (preg_match('/<link[^>]*rel=["\'](?:icon|shortcut icon)["\'][^>]*>/i', $content)) {
            $this->add_good('favicon_ok', __('Favicon found. Good for branding.', 'seo-booster'));
        } else {
            $this->add_improvement(
                'no_favicon',
                __('Consider adding a favicon for better branding. (This is a sitewide improvement and can be set via the Customizer.)', 'seo-booster')
            );
        }
    }

    /**
     * Check for language declaration.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_language_declaration()
    {
        $content = $this->get_analysis_content();
        
        if (preg_match('/<html[^>]*lang=["\'][^"\']+["\'][^>]*>/i', $content)) {
            $this->add_good('language_ok', __('Language declaration found. Good for international SEO.', 'seo-booster'));
        } else {
            $this->add_improvement('no_language', __('Consider adding a lang attribute to the HTML tag for international SEO.', 'seo-booster'));
        }
    }

    /**
     * Check basic accessibility features.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_accessibility_basics()
    {
        $content = $this->get_analysis_content();
        
        // Check for images without alt text
        preg_match_all('/<img[^>]+>/i', $content, $images, PREG_OFFSET_CAPTURE);
        $images_without_alt = 0;
        $images_without_alt_list = [];
        $max_examples = 50;
        
        foreach ($images[0] as $match) {
            $img = $match[0];
            $img_position = $match[1];
            
            // Filter out hidden elements
            if ($this->is_hidden_element($img)) {
                continue;
            }
            
            // Check if alt attribute is missing
            if (!preg_match('/alt\s*=\s*["\'][^"\']*["\']/', $img)) {
                $images_without_alt++;
                
                // Collect examples (max 50)
                if (count($images_without_alt_list) < $max_examples) {
                    // Extract URL from img tag
                    $image_url = '';
                    if (preg_match('/src=["\']([^"\']+)["\']/', $img, $url_match)) {
                        $image_url = $url_match[1];
                    }
                    
                    // Get line number and context
                    $line_info = $this->get_line_number_and_context($content, $img_position);
                    
                    $images_without_alt_list[] = [
                        'html' => $img,
                        'url' => $image_url,
                        'line' => $line_info['line'],
                        'context' => $line_info['context']
                    ];
                }
            }
        }
        
        if ($images_without_alt === 0) {
            $this->add_good('alt_text_ok', __('All images have alt text. Good for accessibility.', 'seo-booster'));
        } else {
            $extra_data = !empty($images_without_alt_list) ? ['images_without_alt' => $images_without_alt_list] : null;
            $this->add_improvement('missing_alt_text', sprintf(__('%d images are missing alt text. Consider adding alt attributes for accessibility.', 'seo-booster'), $images_without_alt), $extra_data);
        }
        
        // Check for form labels
        preg_match_all('/<input[^>]*>/i', $content, $inputs);
        $inputs_without_labels = 0;
        
        foreach ($inputs[0] as $input) {
            if (preg_match('/type=["\'](?:text|email|password|search|tel|url)["\']/', $input) && 
                !preg_match('/aria-label=["\'][^"\']*["\']/', $input) &&
                !preg_match('/id=["\'][^"\']*["\']/', $input)) {
                $inputs_without_labels++;
            }
        }
        
        if ($inputs_without_labels > 0) {
            $this->add_improvement('missing_form_labels', sprintf(__('%d form inputs may be missing labels. Consider adding labels or aria-label attributes.', 'seo-booster'), $inputs_without_labels));
        } else {
            $this->add_good('form_accessibility_ok', __('Form inputs appear to have proper labels. Good for accessibility.', 'seo-booster'));
        }
    }

    /**
     * Check content readability indicators.
     *
     * @since 6.1.26
     * @return void
     */
    private function check_content_readability()
    {
        $content = $this->get_analysis_content();
        
        // Extract main content area (look for article, main, or largest text block)
        $main_content = $content;
        if (preg_match('/<main[^>]*>(.*?)<\/main>/is', $content, $matches)) {
            $main_content = $matches[1];
        } elseif (preg_match('/<article[^>]*>(.*?)<\/article>/is', $content, $matches)) {
            $main_content = $matches[1];
        }
        
        // Remove header, footer, sidebar elements
        $main_content = preg_replace('/<(?:header|footer|nav|aside)[^>]*>.*?<\/(?:header|footer|nav|aside)>/is', '', $main_content);
        
        // Get text content
        $text_content = strip_tags($main_content);
        $text_content = preg_replace('/\s+/', ' ', $text_content);
        $text_content = trim($text_content);
        
        if (empty($text_content)) {
            return;
        }
        
        // Count sentences and words
        $sentences = preg_split('/[.!?]+/', $text_content);
        $sentences = array_filter($sentences, function($s) { return trim($s) !== ''; });
        $sentence_count = count($sentences);
        
        $words = str_word_count($text_content);
        
        if ($sentence_count > 0) {
            $avg_sentence_length = $words / $sentence_count;
            
            if ($avg_sentence_length <= 15) {
                $this->add_good('sentence_length_ok', __('Good sentence length. Easy to read.', 'seo-booster'));
            } elseif ($avg_sentence_length <= 20) {
                $this->add_improvement('sentence_length_moderate', __('Consider shortening some sentences for better readability.', 'seo-booster'));
            } else {
                $this->add_improvement('sentence_length_long', __('Some sentences are quite long. Consider breaking them up for better readability.', 'seo-booster'));
            }
        }
        
        // Check paragraph length - work with original HTML to get positions
        // Find <p> tags in the main content
        preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $main_content, $p_matches, PREG_OFFSET_CAPTURE);
        
        $long_paragraphs = 0;
        $long_paragraphs_list = [];
        $max_examples = 50;
        
        if (!empty($p_matches[0])) {
            foreach ($p_matches[0] as $index => $match) {
                $full_p_tag = $match[0];
                $p_position = $match[1];
                $p_content = $p_matches[1][$index][0];
                
                // Filter out hidden elements
                if ($this->is_hidden_element($full_p_tag)) {
                    continue;
                }
                
                // Get text content of paragraph
                $p_text = strip_tags($p_content);
                $p_text = preg_replace('/\s+/', ' ', $p_text);
                $p_text = trim($p_text);
                
                if (empty($p_text)) {
                    continue;
                }
                
                $word_count = str_word_count($p_text);
                if ($word_count > 150) {
                    $long_paragraphs++;
                    
                    // Collect examples (max 50)
                    if (count($long_paragraphs_list) < $max_examples) {
                        // Get preview (first 150 characters)
                        $preview = mb_substr($p_text, 0, 150);
                        if (mb_strlen($p_text) > 150) {
                            $preview .= '...';
                        }
                        
                        // Get line number and context from original content
                        // Need to find position in original content, not main_content
                        $content_position = strpos($content, $full_p_tag);
                        if ($content_position !== false) {
                            $line_info = $this->get_line_number_and_context($content, $content_position);
                        } else {
                            // Fallback: use position in main_content
                            $line_info = $this->get_line_number_and_context($main_content, $p_position);
                        }
                        
                        $long_paragraphs_list[] = [
                            'preview' => $preview,
                            'word_count' => $word_count,
                            'line' => $line_info['line'],
                            'context' => $line_info['context']
                        ];
                    }
                }
            }
        }
        
        // Also check text paragraphs (double line breaks) if no <p> tags found
        if ($long_paragraphs === 0) {
            $text_paragraphs = preg_split('/\n\s*\n/', $text_content);
            $text_paragraphs = array_filter($text_paragraphs, function($p) { return trim($p) !== ''; });
            
            foreach ($text_paragraphs as $paragraph) {
                $word_count = str_word_count($paragraph);
                if ($word_count > 150) {
                    $long_paragraphs++;
                    
                    // Collect examples (max 50)
                    if (count($long_paragraphs_list) < $max_examples) {
                        // Get preview (first 150 characters)
                        $preview = mb_substr(trim($paragraph), 0, 150);
                        if (mb_strlen(trim($paragraph)) > 150) {
                            $preview .= '...';
                        }
                        
                        // Find position in original content
                        $paragraph_escaped = preg_quote($paragraph, '/');
                        if (preg_match('/' . $paragraph_escaped . '/', $content, $text_match, PREG_OFFSET_CAPTURE)) {
                            $line_info = $this->get_line_number_and_context($content, $text_match[0][1]);
                        } else {
                            $line_info = ['line' => 0, 'context' => ''];
                        }
                        
                        $long_paragraphs_list[] = [
                            'preview' => $preview,
                            'word_count' => $word_count,
                            'line' => $line_info['line'],
                            'context' => $line_info['context']
                        ];
                    }
                }
            }
        }
        
        if ($long_paragraphs === 0) {
            $this->add_good('paragraph_length_ok', __('Good paragraph length. Easy to read.', 'seo-booster'));
        } else {
            $this->add_improvement('long_paragraphs', sprintf(__('%d paragraphs are quite long. Consider breaking them up for better readability.', 'seo-booster'), $long_paragraphs), [
                'long_paragraphs' => $long_paragraphs_list
            ]);
        }
    }

    /**
     * Run analysis in steps for progressive feedback.
     *
     * @since 6.1.26
     * @param string $step The current step to execute.
     * @return array Step result with message, next step, and elapsed time.
     */
    public function analyze_step($step)
    {
        $start_time = microtime(true);
        $result = [
            'step' => $step,
            'message' => '',
            'next_step' => null,
            'elapsed' => 0
        ];
        
        // Initialize results array if not already done
        if (empty($this->results)) {
            $this->results = [
                'score' => 0,
                'issues' => [],
                'improvements' => [],
                'good' => []
            ];
        }
        
        switch ($step) {
            case 'start':
                $result['message'] = __('Starting analysis...', 'seo-booster');
                $result['next_step'] = 'download';
                break;
                
            case 'download':
                $this->download_full_page_content();
                $result['message'] = __('Downloading page content...', 'seo-booster');
                $result['next_step'] = 'headers';
                break;
                
            case 'headers':
                $this->check_title();
                $this->check_meta_description();
                $this->check_heading_structure();
                $result['message'] = __('Analyzing headers...', 'seo-booster');
                $result['next_step'] = 'links';
                break;
                
            case 'links':
                $this->check_internal_links();
                $this->check_external_links();
                $result['message'] = __('Checking links...', 'seo-booster');
                $result['next_step'] = 'content';
                break;
                
            case 'content':
                $this->check_content_length();
                $this->check_keyword_density();
                $this->check_content_readability();
                $this->check_meta_viewport();
                $this->check_favicon();
                $this->check_language_declaration();
                $this->check_accessibility_basics();
                $result['message'] = __('Analyzing content...', 'seo-booster');
                $result['next_step'] = 'cache';
                break;
                
            case 'cache':
                $this->calculate_score();
                $this->save_analysis_results(true);
                $result['message'] = __('Caching results...', 'seo-booster');
                $result['next_step'] = null; // Done
                $result['results'] = $this->results;
                break;
        }
        
        $result['elapsed'] = round(microtime(true) - $start_time, 2);
        return $result;
    }

    /**
     * Check Google Search Console status for the current URL.
     *
     * @since 6.2.0
     * @return void
     */
    private function check_gsc_status()
    {
        // Check if GSC is connected
        $access_token = Google_API::get_access_token();
        if (!$access_token || is_wp_error($access_token)) {
            $this->record_gsc_check_status('status', 'not_applicable', __('GSC not connected', 'seo-booster'));
            $this->add_good('gsc_status_skipped', __('Google Search Console: Indexing & Structured Data check skipped (GSC not connected).', 'seo-booster'));
            return; // GSC not connected, skip check
        }

        $site_url = get_option('seobooster_selected_site', '');
        if (empty($site_url)) {
            $this->record_gsc_check_status('status', 'not_applicable', __('No site URL configured', 'seo-booster'));
            $this->add_good('gsc_status_skipped', __('Google Search Console: Indexing & Structured Data check skipped (No site URL configured).', 'seo-booster'));
            return; // No site URL configured
        }

        $url = $this->get_object_url();
        if (empty($url)) {
            $this->record_gsc_check_status('status', 'not_applicable', __('No URL available', 'seo-booster'));
            $this->add_good('gsc_status_skipped', __('Google Search Console: Indexing & Structured Data check skipped (No URL available).', 'seo-booster'));
            return;
        }

        // Inspect URL via GSC API
        $inspection_data = Google_API::inspect_url($url, $site_url);

        if (is_wp_error($inspection_data)) {
            Utils::log('GSC inspection failed for URL: ' . $url . ' - ' . $inspection_data->get_error_message(), 2);
            $this->record_gsc_check_status('status', 'not_applicable', __('GSC inspection failed', 'seo-booster'));
            $this->add_good('gsc_status_skipped', __('Google Search Console: Indexing & Structured Data check skipped (Inspection failed).', 'seo-booster'));
            return;
        }

        if (empty($inspection_data) || !is_array($inspection_data)) {
            $this->record_gsc_check_status('status', 'not_applicable', __('No inspection data available', 'seo-booster'));
            $this->add_good('gsc_status_skipped', __('Google Search Console: Indexing & Structured Data check skipped (No inspection data available).', 'seo-booster'));
            return;
        }

        // Extract inspection result
        $inspection_result = isset($inspection_data['inspectionResult']) 
            ? $inspection_data['inspectionResult'] 
            : [];

        if (empty($inspection_result)) {
            $this->record_gsc_check_status('status', 'not_applicable', __('No inspection result available', 'seo-booster'));
            $this->add_good('gsc_status_skipped', __('Google Search Console: Indexing & Structured Data check skipped (No inspection result available).', 'seo-booster'));
            return;
        }

        // Get index status result
        $index_status = isset($inspection_result['indexStatusResult']) 
            ? $inspection_result['indexStatusResult'] 
            : [];

        // Get rich results result
        $rich_results = isset($inspection_result['richResultsResult']) 
            ? $inspection_result['richResultsResult'] 
            : [];

        $issues_found = false;

        // Process indexing state issues
        if (!empty($index_status)) {
            $indexing_state = isset($index_status['indexingState']) ? $index_status['indexingState'] : '';
            $coverage_state = isset($index_status['coverageState']) ? $index_status['coverageState'] : '';
            $verdict = isset($index_status['verdict']) ? $index_status['verdict'] : '';

            // Create issue for indexing state if not allowed
            if (!empty($indexing_state) && $indexing_state !== 'INDEXING_ALLOWED') {
                $severity = $this->map_gsc_severity($indexing_state, $coverage_state);
                $message = $this->translate_gsc_term($indexing_state);
                
                if (!empty($coverage_state) && $coverage_state !== 'PASS') {
                    $message .= ' - ' . $this->translate_gsc_term($coverage_state);
                }

                $issue_key = 'gsc_indexing_' . strtolower(str_replace('_', '-', $indexing_state));
                
                $this->add_issue($issue_key, $message, $severity, [
                    'source' => 'gsc',
                    'indexing_state' => $indexing_state,
                    'coverage_state' => $coverage_state,
                    'verdict' => $verdict,
                ]);
                $issues_found = true;
            }
        }

        // Process structured data issues
        if (!empty($rich_results) && isset($rich_results['detectedItems'])) {
            foreach ($rich_results['detectedItems'] as $item) {
                $rich_result_type = isset($item['richResultType']) ? sanitize_text_field($item['richResultType']) : '';
                
                if (empty($rich_result_type)) {
                    continue;
                }

                // Process items within this rich result type
                if (isset($item['items']) && is_array($item['items'])) {
                    foreach ($item['items'] as $item_data) {
                        // Process issues for this item
                        if (isset($item_data['issues']) && is_array($item_data['issues'])) {
                            foreach ($item_data['issues'] as $issue) {
                                $issue_message = isset($issue['issueMessage']) ? sanitize_text_field($issue['issueMessage']) : '';
                                $issue_severity = isset($issue['severity']) ? sanitize_text_field($issue['severity']) : '';

                                if (empty($issue_message)) {
                                    continue;
                                }

                                $severity = ($issue_severity === 'ERROR') ? 'error' : 'warning';
                                $issue_key = 'gsc_structured_' . strtolower(str_replace(' ', '-', $rich_result_type));
                                $message = sprintf(
                                    __('%s: %s', 'seo-booster'),
                                    $rich_result_type,
                                    $issue_message
                                );

                                $this->add_issue($issue_key, $message, $severity, [
                                    'source' => 'gsc',
                                    'rich_result_type' => $rich_result_type,
                                    'issue_message' => $issue_message,
                                    'severity_raw' => $issue_severity,
                                ]);
                                $issues_found = true;
                            }
                        }
                    }
                }
            }
        }

        // Record status and add to good if no issues
        if ($issues_found) {
            $this->record_gsc_check_status('status', 'data_found', '');
        } else {
            $this->record_gsc_check_status('status', 'no_data', '');
            $this->add_good('gsc_status_ok', __('Google Search Console: No indexing or structured data issues found.', 'seo-booster'));
        }
    }

    /**
     * Map GSC indexing/coverage state to SEO issue severity.
     *
     * @since 6.2.0
     * @param string $indexing_state The indexing state from GSC.
     * @param string $coverage_state The coverage state from GSC.
     * @return string Severity level (error or warning).
     */
    private function map_gsc_severity($indexing_state, $coverage_state = '')
    {
        // Critical/Error: Page blocked and coverage failed
        if ($coverage_state === 'FAIL' && in_array($indexing_state, ['BLOCKED_BY_META_TAG', 'BLOCKED_BY_ROBOTS_TXT'])) {
            return 'error';
        }

        // Error: Blocked by meta tag or robots.txt
        if (in_array($indexing_state, ['BLOCKED_BY_META_TAG', 'BLOCKED_BY_ROBOTS_TXT'])) {
            return 'error';
        }

        // Warning: Other indexing issues
        if ($indexing_state !== 'INDEXING_ALLOWED') {
            return 'warning';
        }

        // Warning: Coverage issues but indexing allowed
        if ($coverage_state === 'FAIL' || $coverage_state === 'NEUTRAL') {
            return 'warning';
        }

        return 'warning';
    }

    /**
     * Translate GSC technical terms to user-friendly language.
     *
     * @since 6.2.0
     * @param string $technical_term The technical term from GSC.
     * @return string Translated term.
     */
    private function translate_gsc_term($technical_term)
    {
        $translations = [
            'BLOCKED_BY_META_TAG' => __('Page is set to not be indexed', 'seo-booster'),
            'BLOCKED_BY_ROBOTS_TXT' => __('Page is blocked by robots.txt', 'seo-booster'),
            'NOT_FOUND' => __('Page not found (404 error)', 'seo-booster'),
            'INDEXING_ALLOWED' => __('Indexing is allowed', 'seo-booster'),
            'FAIL' => __("Google can't index this page", 'seo-booster'),
            'PASS' => __('Page is indexed', 'seo-booster'),
            'NEUTRAL' => __('Page is excluded from indexing', 'seo-booster'),
            'SUCCESSFUL' => __('Page fetch successful', 'seo-booster'),
            'BLOCKED' => __('Page fetch blocked', 'seo-booster'),
            'ALLOWED' => __('Allowed by robots.txt', 'seo-booster'),
            'DISALLOWED' => __('Disallowed by robots.txt', 'seo-booster'),
        ];

        return $translations[$technical_term] ?? $technical_term;
    }

    /**
     * Check if a query is a question-based query.
     *
     * @since 7.0.0
     * @param string $query The search query to check.
     * @return bool True if the query is a question, false otherwise.
     */
    private function is_question_query($query)
    {
        if (empty($query)) {
            return false;
        }

        // Default question words by language (multi-word phrases first for proper matching)
        $question_words = [
            // Spanish - multi-word phrases first
            'por qué',
            // English
            'what', 'how', 'why', 'when', 'where', 'who', 'which', 'whose', 'whom',
            // Spanish
            'qué', 'cómo', 'cuándo', 'dónde', 'quién', 'cuál', 'cuáles',
            // Danish
            'hvad', 'hvordan', 'hvorfor', 'hvornår', 'hvor', 'hvem', 'hvilken', 'hvilket',
            // Swedish
            'vad', 'hur', 'varför', 'när', 'var', 'vem', 'vilken', 'vilket',
            // German
            'was', 'wie', 'warum', 'wann', 'wo', 'wer', 'welcher', 'welche', 'welches',
        ];

        // Allow filtering of question words
        $question_words = apply_filters('seobooster_question_words', $question_words);

        // Normalize query to lowercase for comparison
        $query_lower = mb_strtolower(trim($query));

        // Check if query starts with any question word followed by a space (or end of string for single-word queries)
        foreach ($question_words as $word) {
            $word_lower = mb_strtolower($word);
            $word_length = mb_strlen($word_lower);
            
            // Check if query starts with the question word
            if (mb_substr($query_lower, 0, $word_length) === $word_lower) {
                // Check if followed by a space (to avoid false positives like "whatever" matching "what")
                $next_char = mb_substr($query_lower, $word_length, 1);
                if ($next_char === ' ' || $next_char === '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get GSC keywords data for the current URL.
     *
     * @since 7.0.0
     * @return array Array of keyword data with aggregated stats.
     */
    private function get_gsc_keywords_for_url()
    {
        global $wpdb;

        $url = $this->get_object_url();
        if (empty($url)) {
            return [];
        }

        // Check if GSC is connected
        $access_token = Google_API::get_access_token();
        if (!$access_token || is_wp_error($access_token)) {
            return [];
        }

        // Get keywords for this URL with aggregated stats
        $query = $wpdb->prepare(
            "SELECT 
                qk.id,
                qk.query,
                qk.page,
                qk.is_used_in_content,
                COALESCE(SUM(qkh.clicks), 0) as clicks,
                COALESCE(SUM(qkh.impressions), 0) as impressions,
                COALESCE(AVG(qkh.ctr), 0) as ctr,
                COALESCE(AVG(qkh.position), 0) as position,
                MAX(qkh.date) as latest_date
            FROM {$wpdb->prefix}sb2_query_keywords AS qk
            LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh 
                ON qk.id = qkh.query_keywords_id
            WHERE qk.page = %s
            GROUP BY qk.id, qk.query, qk.page, qk.is_used_in_content
            ORDER BY impressions DESC, clicks DESC",
            $url
        );

        $results = $wpdb->get_results($query, ARRAY_A);

        return $results ? $results : [];
    }

    /**
     * Check for keywords with low CTR despite good position.
     *
     * @since 7.0.0
     * @return void
     */
    private function check_gsc_low_ctr_good_position()
    {
        $keywords = $this->get_gsc_keywords_for_url();
        if (empty($keywords)) {
            $this->record_gsc_check_status('low_ctr_good_position', 'not_applicable', __('No keywords available', 'seo-booster'));
            $this->add_good('gsc_low_ctr_skipped', __('Google Search Console: Low CTR (Good Position) check skipped (No keywords available).', 'seo-booster'));
            return;
        }

        // Filter keywords: position < 10 and CTR < 2%
        $low_ctr_keywords = [];
        foreach ($keywords as $keyword) {
            $position = floatval($keyword['position']);
            $ctr = floatval($keyword['ctr']);
            
            if ($position > 0 && $position < 10 && $ctr < 2.0) {
                $low_ctr_keywords[] = $keyword;
            }
        }

        if (empty($low_ctr_keywords)) {
            $this->record_gsc_check_status('low_ctr_good_position', 'no_data', '');
            $this->add_good('gsc_low_ctr_ok', __('Google Search Console: No keywords with low CTR despite good position found.', 'seo-booster'));
            return;
        }

        // Aggregate stats
        $total_clicks = 0;
        $total_impressions = 0;
        $avg_position = 0;
        $avg_ctr = 0;
        $keyword_list = [];

        foreach ($low_ctr_keywords as $kw) {
            $total_clicks += intval($kw['clicks']);
            $total_impressions += intval($kw['impressions']);
            $avg_position += floatval($kw['position']);
            $avg_ctr += floatval($kw['ctr']);
            $keyword_list[] = [
                'query' => $kw['query'],
                'clicks' => intval($kw['clicks']),
                'impressions' => intval($kw['impressions']),
                'position' => floatval($kw['position']),
                'ctr' => floatval($kw['ctr']),
            ];
        }

        $count = count($low_ctr_keywords);
        $avg_position = $count > 0 ? $avg_position / $count : 0;
        $avg_ctr = $count > 0 ? $avg_ctr / $count : 0;

        $this->add_issue(
            'gsc_low_ctr_good_position',
            __('This page has keywords ranking in top 10 but with low click-through rates. Consider optimizing titles and meta descriptions.', 'seo-booster'),
            'low',
            [
                'keywords' => $keyword_list,
                'page' => $this->get_object_url(),
                'aggregated_stats' => [
                    'total_clicks' => $total_clicks,
                    'total_impressions' => $total_impressions,
                    'avg_position' => round($avg_position, 2),
                    'avg_ctr' => round($avg_ctr, 2),
                    'keyword_count' => $count,
                ],
            ]
        );
        
        $this->record_gsc_check_status('low_ctr_good_position', 'data_found', '');
    }

    /**
     * Check for keywords with high impressions but low clicks.
     *
     * @since 7.0.0
     * @return void
     */
    private function check_gsc_high_impressions_low_clicks()
    {
        $keywords = $this->get_gsc_keywords_for_url();
        if (empty($keywords)) {
            $this->record_gsc_check_status('high_impressions_low_clicks', 'not_applicable', __('No keywords available', 'seo-booster'));
            $this->add_good('gsc_high_impressions_skipped', __('Google Search Console: High Impressions, Low Clicks check skipped (No keywords available).', 'seo-booster'));
            return;
        }

        // Filter keywords: impressions > 1000 and clicks < 50
        $high_imp_low_click_keywords = [];
        foreach ($keywords as $keyword) {
            $impressions = intval($keyword['impressions']);
            $clicks = intval($keyword['clicks']);
            
            if ($impressions > 1000 && $clicks < 50) {
                $high_imp_low_click_keywords[] = $keyword;
            }
        }

        if (empty($high_imp_low_click_keywords)) {
            $this->record_gsc_check_status('high_impressions_low_clicks', 'no_data', '');
            $this->add_good('gsc_high_impressions_ok', __('Google Search Console: No keywords with high impressions but low clicks found.', 'seo-booster'));
            return;
        }

        // Sort by impressions descending and take top 20
        usort($high_imp_low_click_keywords, function($a, $b) {
            return intval($b['impressions']) - intval($a['impressions']);
        });
        $high_imp_low_click_keywords = array_slice($high_imp_low_click_keywords, 0, 20);

        // Aggregate stats
        $total_clicks = 0;
        $total_impressions = 0;
        $keyword_list = [];

        foreach ($high_imp_low_click_keywords as $kw) {
            $total_clicks += intval($kw['clicks']);
            $total_impressions += intval($kw['impressions']);
            $keyword_list[] = [
                'query' => $kw['query'],
                'clicks' => intval($kw['clicks']),
                'impressions' => intval($kw['impressions']),
                'position' => floatval($kw['position']),
                'ctr' => floatval($kw['ctr']),
            ];
        }

        $this->add_issue(
            'gsc_high_impressions_low_clicks',
            __('Keywords with high visibility but low clicks. Consider optimizing titles and meta descriptions to improve click-through rates.', 'seo-booster'),
            'low',
            [
                'keywords' => $keyword_list,
                'page' => $this->get_object_url(),
                'aggregated_stats' => [
                    'total_clicks' => $total_clicks,
                    'total_impressions' => $total_impressions,
                    'keyword_count' => count($keyword_list),
                ],
            ]
        );
        
        $this->record_gsc_check_status('high_impressions_low_clicks', 'data_found', '');
    }

    /**
     * Check for keywords not used in content.
     *
     * @since 7.0.0
     * @return void
     */
    private function check_gsc_keywords_not_in_content()
    {
        $keywords = $this->get_gsc_keywords_for_url();
        if (empty($keywords)) {
            $this->record_gsc_check_status('keywords_not_in_content', 'not_applicable', __('No keywords available', 'seo-booster'));
            $this->add_good('gsc_keywords_not_in_content_skipped', __('Google Search Console: Keywords Not in Content check skipped (No keywords available).', 'seo-booster'));
            return;
        }

        // Filter keywords: not used in content (is_used_in_content = -1 or 0)
        $unused_keywords = [];
        foreach ($keywords as $keyword) {
            $is_used = intval($keyword['is_used_in_content']);
            if ($is_used <= 0) {
                $unused_keywords[] = $keyword;
            }
        }

        if (empty($unused_keywords)) {
            $this->record_gsc_check_status('keywords_not_in_content', 'no_data', '');
            $this->add_good('gsc_keywords_in_content', __('Google Search Console: All ranking keywords are found in your content.', 'seo-booster'));
            return;
        }

        // Sort by traffic (clicks + impressions) and take top 20
        usort($unused_keywords, function($a, $b) {
            $traffic_a = intval($a['clicks']) + intval($a['impressions']);
            $traffic_b = intval($b['clicks']) + intval($b['impressions']);
            return $traffic_b - $traffic_a;
        });
        $unused_keywords = array_slice($unused_keywords, 0, 20);

        // Aggregate stats
        $total_clicks = 0;
        $total_impressions = 0;
        $keyword_list = [];

        foreach ($unused_keywords as $kw) {
            $total_clicks += intval($kw['clicks']);
            $total_impressions += intval($kw['impressions']);
            $keyword_list[] = [
                'query' => $kw['query'],
                'clicks' => intval($kw['clicks']),
                'impressions' => intval($kw['impressions']),
                'position' => floatval($kw['position']),
            ];
        }

        $this->add_issue(
            'gsc_keywords_not_in_content',
            __('These ranking keywords are not found in your content. Consider adding them naturally to improve relevance.', 'seo-booster'),
            'low',
            [
                'keywords' => $keyword_list,
                'page' => $this->get_object_url(),
                'aggregated_stats' => [
                    'total_clicks' => $total_clicks,
                    'total_impressions' => $total_impressions,
                    'keyword_count' => count($keyword_list),
                ],
            ]
        );
        
        $this->record_gsc_check_status('keywords_not_in_content', 'data_found', '');
    }

    /**
     * Check for keyword cannibalization (multiple pages ranking for same keyword).
     *
     * @since 7.0.0
     * @return void
     */
    private function check_gsc_keyword_cannibalization()
    {
        global $wpdb;

        $url = $this->get_object_url();
        if (empty($url)) {
            $this->record_gsc_check_status('keyword_cannibalization', 'not_applicable', __('No URL available', 'seo-booster'));
            $this->add_good('gsc_cannibalization_skipped', __('Google Search Console: Keyword Cannibalization check skipped (No URL available).', 'seo-booster'));
            return;
        }

        // Check if GSC is connected
        $access_token = Google_API::get_access_token();
        if (!$access_token || is_wp_error($access_token)) {
            $this->record_gsc_check_status('keyword_cannibalization', 'not_applicable', __('GSC not connected', 'seo-booster'));
            $this->add_good('gsc_cannibalization_skipped', __('Google Search Console: Keyword Cannibalization check skipped (GSC not connected).', 'seo-booster'));
            return;
        }

        // Find keywords that this page ranks for, and check if other pages also rank for them
        $query = $wpdb->prepare(
            "SELECT 
                qk.query,
                qk.page,
                COALESCE(SUM(qkh.clicks), 0) as clicks,
                COALESCE(SUM(qkh.impressions), 0) as impressions,
                COALESCE(AVG(qkh.position), 0) as position
            FROM {$wpdb->prefix}sb2_query_keywords AS qk
            LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh 
                ON qk.id = qkh.query_keywords_id
            WHERE qk.query IN (
                SELECT DISTINCT query 
                FROM {$wpdb->prefix}sb2_query_keywords 
                WHERE page = %s
            )
            GROUP BY qk.query, qk.page
            HAVING COUNT(DISTINCT qk.page) > 1
            ORDER BY impressions DESC, clicks DESC
            LIMIT 50",
            $url
        );

        $results = $wpdb->get_results($query, ARRAY_A);

        if (empty($results)) {
            return;
        }

        // Group by keyword to show competing pages
        $cannibalized_keywords = [];
        foreach ($results as $result) {
            $query_text = $result['query'];
            if (!isset($cannibalized_keywords[$query_text])) {
                $cannibalized_keywords[$query_text] = [];
            }
            $cannibalized_keywords[$query_text][] = [
                'page' => $result['page'],
                'clicks' => intval($result['clicks']),
                'impressions' => intval($result['impressions']),
                'position' => floatval($result['position']),
            ];
        }

        // Filter to only keywords where current page is involved and has competition
        $relevant_cannibalization = [];
        foreach ($cannibalized_keywords as $query_text => $pages) {
            $has_current_page = false;
            $has_other_pages = false;
            
            foreach ($pages as $page_data) {
                if ($page_data['page'] === $url) {
                    $has_current_page = true;
                } else {
                    $has_other_pages = true;
                }
            }
            
            if ($has_current_page && $has_other_pages && count($pages) > 1) {
                $relevant_cannibalization[$query_text] = $pages;
            }
        }

        if (empty($relevant_cannibalization)) {
            $this->record_gsc_check_status('keyword_cannibalization', 'no_data', '');
            $this->add_good('gsc_no_cannibalization', __('Google Search Console: No keyword cannibalization detected.', 'seo-booster'));
            return;
        }

        // Take top 10 keywords by total impressions
        $keyword_stats = [];
        foreach ($relevant_cannibalization as $query_text => $pages) {
            $total_impressions = 0;
            foreach ($pages as $page_data) {
                $total_impressions += $page_data['impressions'];
            }
            $keyword_stats[$query_text] = $total_impressions;
        }
        arsort($keyword_stats);
        $top_keywords = array_slice(array_keys($keyword_stats), 0, 10);

        $final_data = [];
        foreach ($top_keywords as $query_text) {
            $final_data[$query_text] = $relevant_cannibalization[$query_text];
        }

        $this->add_issue(
            'gsc_keyword_cannibalization',
            __('Multiple pages are competing for the same keyword. Consider consolidating or differentiating content to avoid keyword cannibalization.', 'seo-booster'),
            'low',
            [
                'cannibalized_keywords' => $final_data,
                'current_page' => $url,
            ]
        );
        
        $this->record_gsc_check_status('keyword_cannibalization', 'data_found', '');
    }

    /**
     * Check for long-tail keyword opportunities.
     *
     * @since 7.0.0
     * @return void
     */
    private function check_gsc_longtail_opportunities()
    {
        $keywords = $this->get_gsc_keywords_for_url();
        if (empty($keywords)) {
            $this->record_gsc_check_status('longtail_opportunities', 'not_applicable', __('No keywords available', 'seo-booster'));
            $this->add_good('gsc_longtail_skipped', __('Google Search Console: Long-tail Opportunities check skipped (No keywords available).', 'seo-booster'));
            return;
        }

        // Filter keywords: 4+ words, clicks > 10 OR impressions > 100, position 4-20
        $longtail_keywords = [];
        foreach ($keywords as $keyword) {
            $query = $keyword['query'];
            $word_count = count(explode(' ', trim($query)));
            $clicks = intval($keyword['clicks']);
            $impressions = intval($keyword['impressions']);
            $position = floatval($keyword['position']);
            
            if ($word_count >= 4 && 
                ($clicks > 10 || $impressions > 100) && 
                $position >= 4 && $position <= 20) {
                $longtail_keywords[] = $keyword;
            }
        }

        if (empty($longtail_keywords)) {
            $this->record_gsc_check_status('longtail_opportunities', 'no_data', '');
            // Note: We don't add to "good" for this one as it's an opportunity check, not a problem check
            return;
        }

        // Sort by impressions and take top 20
        usort($longtail_keywords, function($a, $b) {
            return intval($b['impressions']) - intval($a['impressions']);
        });
        $longtail_keywords = array_slice($longtail_keywords, 0, 20);

        // Aggregate stats
        $total_clicks = 0;
        $total_impressions = 0;
        $keyword_list = [];

        foreach ($longtail_keywords as $kw) {
            $total_clicks += intval($kw['clicks']);
            $total_impressions += intval($kw['impressions']);
            $keyword_list[] = [
                'query' => $kw['query'],
                'clicks' => intval($kw['clicks']),
                'impressions' => intval($kw['impressions']),
                'position' => floatval($kw['position']),
            ];
        }

        $this->add_issue(
            'gsc_longtail_opportunities',
            __('Long-tail keywords with potential. Consider expanding content around these topics.', 'seo-booster'),
            'low',
            [
                'keywords' => $keyword_list,
                'page' => $this->get_object_url(),
                'aggregated_stats' => [
                    'total_clicks' => $total_clicks,
                    'total_impressions' => $total_impressions,
                    'keyword_count' => count($keyword_list),
                ],
            ]
        );
        
        $this->record_gsc_check_status('longtail_opportunities', 'data_found', '');
    }

    /**
     * Check for content freshness signals (declining traffic).
     *
     * @since 7.0.0
     * @return void
     */
    private function check_gsc_content_freshness()
    {
        global $wpdb;

        $url = $this->get_object_url();
        if (empty($url)) {
            $this->record_gsc_check_status('content_freshness', 'not_applicable', __('No URL available', 'seo-booster'));
            $this->add_good('gsc_content_freshness_skipped', __('Google Search Console: Content Freshness check skipped (No URL available).', 'seo-booster'));
            return;
        }

        // Check if GSC is connected
        $access_token = Google_API::get_access_token();
        if (!$access_token || is_wp_error($access_token)) {
            $this->record_gsc_check_status('content_freshness', 'not_applicable', __('GSC not connected', 'seo-booster'));
            $this->add_good('gsc_content_freshness_skipped', __('Google Search Console: Content Freshness check skipped (GSC not connected).', 'seo-booster'));
            return;
        }

        // Get keywords with traffic data for recent 30 days vs previous 30 days
        $query = $wpdb->prepare(
            "SELECT 
                qk.query,
                SUM(CASE WHEN qkh.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN qkh.impressions ELSE 0 END) as recent_impressions,
                SUM(CASE WHEN qkh.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN qkh.clicks ELSE 0 END) as recent_clicks,
                SUM(CASE WHEN qkh.date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) 
                         AND qkh.date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN qkh.impressions ELSE 0 END) as previous_impressions,
                SUM(CASE WHEN qkh.date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) 
                         AND qkh.date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN qkh.clicks ELSE 0 END) as previous_clicks
            FROM {$wpdb->prefix}sb2_query_keywords AS qk
            LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh 
                ON qk.id = qkh.query_keywords_id
            WHERE qk.page = %s
            GROUP BY qk.query
            HAVING recent_impressions >= 100
            ORDER BY recent_impressions DESC",
            $url
        );

        $results = $wpdb->get_results($query, ARRAY_A);

        if (empty($results)) {
            $this->record_gsc_check_status('content_freshness', 'no_data', __('No traffic data available for comparison', 'seo-booster'));
            return;
        }

        // Calculate decline percentage and filter
        $declining_keywords = [];
        foreach ($results as $result) {
            $recent_impressions = intval($result['recent_impressions']);
            $previous_impressions = intval($result['previous_impressions']);
            
            if ($previous_impressions > 0) {
                $decline_percentage = (($previous_impressions - $recent_impressions) / $previous_impressions) * 100;
                
                // Only flag if decline > 20% and recent period has at least 100 impressions
                if ($decline_percentage > 20 && $recent_impressions >= 100) {
                    $declining_keywords[] = [
                        'query' => $result['query'],
                        'recent_impressions' => $recent_impressions,
                        'previous_impressions' => $previous_impressions,
                        'recent_clicks' => intval($result['recent_clicks']),
                        'previous_clicks' => intval($result['previous_clicks']),
                        'decline_percentage' => round($decline_percentage, 1),
                    ];
                }
            }
        }

        if (empty($declining_keywords)) {
            $this->record_gsc_check_status('content_freshness', 'no_data', '');
            $this->add_good('gsc_content_freshness_ok', __('Google Search Console: No declining traffic detected for keywords.', 'seo-booster'));
            return;
        }

        // Sort by decline percentage and take top 10
        usort($declining_keywords, function($a, $b) {
            return $b['decline_percentage'] - $a['decline_percentage'];
        });
        $declining_keywords = array_slice($declining_keywords, 0, 10);

        $this->add_issue(
            'gsc_content_freshness',
            __('Keywords showing declining traffic. Consider updating content to maintain relevance.', 'seo-booster'),
            'low',
            [
                'keywords' => $declining_keywords,
                'page' => $url,
                'aggregated_stats' => [
                    'keyword_count' => count($declining_keywords),
                ],
            ]
        );
        
        $this->record_gsc_check_status('content_freshness', 'data_found', '');
    }

    /**
     * Check for question-based queries.
     *
     * @since 7.0.0
     * @return void
     */
    private function check_gsc_question_queries()
    {
        $keywords = $this->get_gsc_keywords_for_url();
        if (empty($keywords)) {
            $this->record_gsc_check_status('question_queries', 'not_applicable', __('No keywords available', 'seo-booster'));
            $this->add_good('gsc_question_queries_skipped', __('Google Search Console: Question-based Queries check skipped (No keywords available).', 'seo-booster'));
            return;
        }

        // Filter question-based queries
        $question_keywords = [];
        foreach ($keywords as $keyword) {
            if ($this->is_question_query($keyword['query'])) {
                $question_keywords[] = $keyword;
            }
        }

        if (empty($question_keywords)) {
            $this->record_gsc_check_status('question_queries', 'no_data', '');
            // Note: We don't add to "good" for this one as it's an opportunity check, not a problem check
            return;
        }

        // Sort by impressions and take top 20
        usort($question_keywords, function($a, $b) {
            return intval($b['impressions']) - intval($a['impressions']);
        });
        $question_keywords = array_slice($question_keywords, 0, 20);

        // Aggregate stats
        $total_clicks = 0;
        $total_impressions = 0;
        $keyword_list = [];

        foreach ($question_keywords as $kw) {
            $total_clicks += intval($kw['clicks']);
            $total_impressions += intval($kw['impressions']);
            $keyword_list[] = [
                'query' => $kw['query'],
                'clicks' => intval($kw['clicks']),
                'impressions' => intval($kw['impressions']),
                'position' => floatval($kw['position']),
            ];
        }

        $this->add_issue(
            'gsc_question_queries',
            __('Question-based queries detected. Consider adding FAQ sections or structured answers to target featured snippets.', 'seo-booster'),
            'low',
            [
                'keywords' => $keyword_list,
                'page' => $this->get_object_url(),
                'aggregated_stats' => [
                    'total_clicks' => $total_clicks,
                    'total_impressions' => $total_impressions,
                    'keyword_count' => count($keyword_list),
                ],
            ]
        );
        
        $this->record_gsc_check_status('question_queries', 'data_found', '');
    }
}
