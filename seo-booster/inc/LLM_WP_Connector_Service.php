<?php

namespace Cleverplugins\SEOBooster;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LLM_WP_Connector_Service
 *
 * Generates SEO suggestions using WordPress 7 AI Connectors (wp_ai_client_prompt).
 * No API keys stored in plugin; credentials from Settings → Connectors.
 *
 * @package Cleverplugins\SEOBooster
 */
class LLM_WP_Connector_Service
{
    /**
     * Generate SEO suggestions for a post using WordPress AI Client.
     *
     * @param int    $post_id                 Post ID.
     * @param string $condensed_content       Condensed content from LLM_Content_Condenser.
     * @param string $language                Post language code.
     * @param string $local_analysis_summary  Optional local SEO analysis summary.
     * @return array Result with titles, descriptions, and metadata.
     * @throws \Exception If API call fails or response is invalid.
     */
    public function generate_suggestions($post_id, $condensed_content, $language, $local_analysis_summary = '')
    {
        if (!function_exists('wp_ai_client_prompt')) {
            throw new \Exception(__('WordPress AI is not available. Use WordPress 7 or later and configure Settings → Connectors.', 'seo-booster'));
        }

        if (!LLM_Helper::wp_ai_is_available()) {
            throw new \Exception(LLM_Helper::wp_ai_unavailable_message());
        }

        $prompt = $this->build_prompt($post_id, $condensed_content, $language, $local_analysis_summary);
        $prompt = apply_filters('seobooster_ai_prompt_template', $prompt, $post_id, $condensed_content, $language);

        $response_text = wp_ai_client_prompt($prompt)
            ->using_system_instruction(__('You are an SEO expert. Return only valid JSON with no surrounding text or markdown.', 'seo-booster'))
            ->generate_text();

        if (empty($response_text)) {
            throw new \Exception(__('Empty response from AI. Check Settings → Connectors.', 'seo-booster'));
        }

        $result = $this->parse_response($response_text);
        $this->store_results($post_id, $result, $language);
        Utils::log('Created SEO suggestions for post ID ' . $post_id . ' using WordPress Connectors.', 5);

        return $result;
    }

    /**
     * Build the prompt for the AI (same structure as legacy OpenAI flow).
     *
     * @param int    $post_id                 Post ID.
     * @param string $condensed_content       Condensed content.
     * @param string $language                Language code.
     * @param string $local_analysis_summary  Optional local analysis summary.
     * @return string Formatted prompt.
     */
    private function build_prompt($post_id, $condensed_content, $language, $local_analysis_summary = '')
    {
        $post = get_post($post_id);
        if (!$post) {
            throw new \Exception(__('Post not found', 'seo-booster'));
        }

        $seo_plugin_data   = Google_API::get_seo_title_and_description($post_id);
        $categories       = wp_get_post_categories($post_id, ['fields' => 'names']);
        $tags             = wp_get_post_tags($post_id, ['fields' => 'names']);
        $gsc_keywords     = $this->get_gsc_keywords($post_id);
        $focus_keywords   = $this->get_focus_keywords($post_id);
        $description      = $this->get_post_description($post);
        $language_name    = LLM_Helper::get_language_name($language);

        $focus_keyword_instructions = '';
        if (!empty($focus_keywords)) {
            $focus_keyword_list            = implode(', ', $focus_keywords);
            $focus_keyword_instructions   = sprintf(
                "\n### FOCUS KEYWORD REQUIREMENTS:\n- The following focus keyword(s) have been set: %s\n- These focus keyword(s) MUST be included in most titles and meta descriptions following best SEO guidelines\n- Place focus keywords naturally, preferably near the beginning of titles when possible\n- Maintain readability and avoid keyword stuffing\n- While most suggestions should include the focus keyword(s), some variations may omit them for creative diversity\n- Follow SEO best practices: ensure keywords flow naturally and enhance rather than detract from the user experience",
                $focus_keyword_list
            );
        }

        $prompt = sprintf(
            'Act as an SEO expert. You are an expert SEO specialist with deep knowledge of search engine optimization, content marketing, and user engagement. Your task is to analyze the provided content and generate high-quality SEO suggestions that will improve search rankings and click-through rates.

### CONTENT TO ANALYZE:
Title: %s
Description: %s
Content: %s
Categories: %s
Tags: %s
Target Keywords: %s
GSC Keywords: %s
URL: %s
Post Type: %s
Post Language: %s (locale: %s)%s

### TASK:
Generate 5 optimized SEO title suggestions and 5 meta description suggestions in %s that will:
1. Improve search engine rankings
2. Increase click-through rates from search results
3. Accurately represent the content
4. Include relevant keywords naturally
5. Appeal to the target audience
6. Try to keep the focus keyword if set in front of the title and early in the description.
### OUTPUT FORMAT:
Return your suggestions in this exact JSON format. All content must be in %s:
{
  "titles": [
    "Optimized title 1",
    "Optimized title 2",
    "Optimized title 3",
    "Optimized title 4",
    "Optimized title 5"
  ],
  "descriptions": [
    "Meta description 1",
    "Meta description 2",
    "Meta description 3",
    "Meta description 4",
    "Meta description 5"
  ],
  "keywords": [
    "primary keyword 1",
    "secondary keyword 2",
    "long-tail keyword 3",
    "semantic keyword 4",
    "LSI keyword 5"
  ]
}

### LANGUAGE REQUIREMENT (CRITICAL - MUST FOLLOW):
- The post language is: %s (locale: %s)
- ALL titles, descriptions, and keywords MUST be written in %s
- Do NOT use English or any other language - this is a multilingual site
- The language is explicitly set to %s - you MUST respect this
- If you return content in the wrong language, the suggestions will be unusable
- Write naturally in %s as a native speaker would
- This is the MOST IMPORTANT requirement - language accuracy is critical

### CRITICAL REQUIREMENTS:
- Titles must be 55-80 characters (do NOT include character count in output)
- Meta descriptions must be 150-175 characters (do NOT include character count in output)
- Make titles compelling and click-worthy while including primary keywords
- Vary the structure and opening words of titles and descriptions to avoid repetition
- Create descriptions that encourage clicks and clearly communicate value
- Use GSC keywords as inspiration for titles and descriptions
- Suggest additional relevant keywords that could drive new traffic
- Focus on user intent and search behavior
- Ensure all suggestions are unique and offer different value propositions
- Do NOT suggest URL changes (content is already published)
- Content may be condensed but contains sufficient information for analysis

### QUALITY STANDARDS:
- Each title should have a different approach (question, benefit, urgency, etc.)
- Descriptions should highlight different aspects or benefits
- Keywords should be relevant and searchable
- All suggestions must be original and not duplicate existing content
- Prioritize user experience and search engine guidelines

Return ONLY the JSON object with no additional text, explanations, or formatting.',
            $post->post_title,
            $description,
            $condensed_content,
            implode(', ', $categories),
            implode(', ', $tags),
            implode(', ', $focus_keywords),
            implode(', ', $gsc_keywords),
            get_permalink($post_id),
            $post->post_type,
            $language_name,
            $language,
            $focus_keyword_instructions,
            $language_name,
            $language_name,
            $language,
            $language_name,
            $language_name,
            $language_name,
            $language_name
        );

        if (!empty($local_analysis_summary)) {
            $prompt .= "\n### EXISTING SEO ANALYSIS (from this site):\n" . $local_analysis_summary . "\n";
        }

        return $prompt;
    }

    /**
     * Parse AI response text into titles, descriptions, keywords.
     *
     * @param string $response_text Raw response from generate_text().
     * @return array Parsed result.
     * @throws \Exception If response format is invalid.
     */
    private function parse_response($response_text)
    {
        // Strip possible markdown code blocks.
        $response_text = preg_replace('#^```(?:json)?\s*|\s*```$#', '', trim($response_text));
        $result_data   = json_decode($response_text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Utils::log('WP Connector AI: Invalid JSON in response - ' . substr($response_text, 0, 500), 2);
            throw new \Exception(__('Unable to parse AI response content', 'seo-booster'));
        }

        $titles       = isset($result_data['titles']) && is_array($result_data['titles']) ? $result_data['titles'] : [];
        $descriptions = isset($result_data['descriptions']) && is_array($result_data['descriptions']) ? $result_data['descriptions'] : [];
        $keywords     = isset($result_data['keywords']) && is_array($result_data['keywords']) ? $result_data['keywords'] : [];

        if (empty($titles) || empty($descriptions)) {
            throw new \Exception(__('Invalid response: missing titles or descriptions', 'seo-booster'));
        }

        return [
            'titles'       => $titles,
            'descriptions' => $descriptions,
            'keywords'     => $keywords,
            'status'       => 'completed',
        ];
    }

    /**
     * Store results in post meta.
     *
     * @param int    $post_id  Post ID.
     * @param array  $result   Parsed result.
     * @param string $language Language code.
     */
    private function store_results($post_id, $result, $language)
    {
        $compact_result = [
            'titles'       => $result['titles'],
            'descriptions' => $result['descriptions'],
            'status'       => $result['status'],
            'language'     => $language,
            'timestamp'    => time(),
            'provider'     => 'wordpress',
        ];
        update_post_meta($post_id, '_llm_seo_last_result', $compact_result);
    }

    private function get_gsc_keywords($post_id)
    {
        global $wpdb;
        $permalink = get_permalink($post_id);
        if (!$permalink) {
            return [];
        }
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT k.query FROM {$wpdb->prefix}sb2_query_keywords AS k
             WHERE k.page = %s
             AND LENGTH(k.query) >= 3
             ORDER BY LENGTH(k.query) DESC
             LIMIT 20",
            $permalink
        ), ARRAY_A);
        return array_column($results, 'query');
    }

    private function get_focus_keywords($post_id)
    {
        $plugin_keywords = Google_API::get_focus_keywords($post_id);
        return is_array($plugin_keywords) ? $plugin_keywords : [];
    }

    private function get_post_description($post)
    {
        if (!empty($post->post_excerpt)) {
            return $post->post_excerpt;
        }
        $content = wp_strip_all_tags($post->post_content);
        return wp_trim_words($content, 25, '...');
    }
}
