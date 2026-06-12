<?php

namespace Cleverplugins\SEOBooster;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LLM_Content_Condenser
 *
 * Handles content condensation for LLM SEO suggestions using pure PHP.
 * Converts HTML to text and applies extractive summarization to fit token limits.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */
class LLM_Content_Condenser
{
    /**
     * Maximum tokens allowed (4,000)
     */
    const MAX_TOKENS = 4000;

    /**
     * Target tokens for condensation (3,200 - 80% of max)
     */
    const TARGET_TOKENS = 3200;

    /**
     * Pass-through threshold (1,000 tokens ~ 750 words)
     */
    const PASSTHROUGH_TOKENS = 1000;

    /**
     * Token estimation ratio (chars / 4)
     */
    const TOKEN_RATIO = 4;

    /**
     * Condense post content for LLM processing.
     *
     * @since 6.1.26
     * @param int $post_id Post ID.
     * @return string Condensed plain text content.
     * @throws \Exception If content cannot be retrieved or processed.
     */
    public function condense_post_content($post_id)
    {
        // Get full HTML content from cache
        $html_content = $this->get_cached_html_content($post_id);
        
        if (empty($html_content)) {
            throw new \Exception(__('Unable to retrieve content for condensation', 'seo-booster'));
        }

        // Convert HTML to plain text
        $plain_text = $this->html_to_text($html_content);
        
        if (empty($plain_text)) {
            throw new \Exception(__('Unable to convert content to text', 'seo-booster'));
        }

        $plain_text = $this->strip_boilerplate($plain_text);

        // Estimate tokens
        $estimated_tokens = $this->estimate_tokens($plain_text);

        // If content is short enough, pass through unchanged
        if ($estimated_tokens <= self::PASSTHROUGH_TOKENS) {
            return $plain_text;
        }

        // Apply summarization
        return $this->summarize_text($plain_text, $estimated_tokens);
    }

    /**
     * Get cached HTML content for a post.
     *
     * @since 6.1.26
     * @param int $post_id Post ID.
     * @return string HTML content or empty string if not available.
     */
    private function get_cached_html_content($post_id)
    {
        $post_url = get_permalink($post_id);
        if (!$post_url) {
            return '';
        }

        // Try to get cached content first
        $cache_response = CacheManager::fetch_and_cache_url_content($post_url, [
            'post_id' => $post_id,
            'content_type' => 'post',
            'item_id' => $post_id
        ]);

        if (!empty($cache_response['content'])) {
            return $cache_response['content'];
        }

        // Fallback to rendered post content
        $post = get_post($post_id);
        if (!$post) {
            return '';
        }

        // Apply filters to get rendered content
        $content = apply_filters('the_content', $post->post_content);
        
        // Wrap in basic HTML structure if needed
        if (strpos($content, '<html') === false) {
            $content = '<html><body>' . $content . '</body></html>';
        }

        return $content;
    }

    /**
     * Convert HTML to plain text.
     *
     * @since 6.1.26
     * @param string $html HTML content.
     * @return string Plain text content.
     */
    private function html_to_text($html)
    {
        try {
            // Use soundasleep/html2text library
            if (class_exists('\Soundasleep\Html2Text')) {
                $text = \Soundasleep\Html2Text::convert($html, [
                    'ignore_errors' => true,
                    'drop_links' => false,
                    'do_links' => 'inline'
                ]);
            } else {
                // Fallback to basic HTML stripping
                $text = wp_strip_all_tags($html);
            }
            
            // Clean up excessive whitespace and linebreaks
            $text = $this->clean_text($text);
            
            return $text;
        } catch (\Exception $e) {
            // Fallback to basic HTML stripping
            $text = wp_strip_all_tags($html);
            return $this->clean_text($text);
        }
    }

    /**
     * Cleans up text by removing excessive whitespace and linebreaks.
     * Decodes HTML entities and normalizes newlines to spaces so payloads stay compact.
     *
     * @param string $text Raw text.
     * @return string Cleaned text.
     */
    private function clean_text($text)
    {
        if ($text === '') {
            return '';
        }
        // Decode HTML entities (e.g. &#8211; -> –)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Treat all newlines as space, then collapse runs of spaces to one
        $text = str_replace(["\r\n", "\n", "\r"], ' ', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = trim($text);
        return $text;
    }

    /**
     * Strip common theme/comment/footer boilerplate so it is not sent to the API.
     *
     * @param string $text Plain text (after html_to_text).
     * @return string Text with boilerplate phrases removed.
     */
    private function strip_boilerplate($text)
    {
        $phrases = [
            'Leave a Reply',
            'Cancel reply',
            'Your email address will not be published',
            'Required fields are marked',
            'Comment *',
            'Name *',
            'Email *',
            'Website',
            'Save my name, email, and website in this browser',
            'Designed with WordPress',
            'Your cart',
            'Items: 0',
            'Products in cart',
            'Previous:',
            'Next:',
            'Comments',
            'Privacy Policy',
            'Terms and Conditions',
            'Contact Us',
            'Facebook',
            'Instagram',
            'Twitter/X',
            'Team',
            'History',
            'Careers',
        ];
        foreach ($phrases as $phrase) {
            $esc = preg_quote($phrase, '/');
            $text = preg_replace('/\s*' . $esc . '\s*/ui', ' ', $text);
        }
        $text = preg_replace('/[ \t]+/', ' ', $text);
        return trim($text);
    }

    /**
     * Estimate token count from text.
     *
     * @since 6.1.26
     * @param string $text Text content.
     * @return int Estimated token count.
     */
    private function estimate_tokens($text)
    {
        return ceil(strlen($text) / self::TOKEN_RATIO);
    }

    /**
     * Summarize text to fit within token limits.
     *
     * @since 6.1.26
     * @param string $text Original text.
     * @param int $current_tokens Current token count.
     * @return string Summarized text.
     */
    private function summarize_text($text, $current_tokens)
    {
        // First pass: target 3,200 tokens
        $target_length = self::TARGET_TOKENS * self::TOKEN_RATIO;
        $summarized = $this->extractive_summarize($text, $target_length);
        
        // Check if we're still over the limit
        $new_tokens = $this->estimate_tokens($summarized);
        
        if ($new_tokens <= self::MAX_TOKENS) {
            return $summarized;
        }

        // Second pass: more aggressive summarization
        $max_length = self::MAX_TOKENS * self::TOKEN_RATIO;
        $summarized = $this->extractive_summarize($summarized, $max_length);
        
        // Final check and sentence dropping if needed
        $final_tokens = $this->estimate_tokens($summarized);
        
        if ($final_tokens > self::MAX_TOKENS) {
            $summarized = $this->drop_lowest_ranked_sentences($summarized);
        }

        return $summarized;
    }

    /**
     * Simple extractive summarization using sentence ranking.
     *
     * @since 6.1.26
     * @param string $text Original text.
     * @param int $target_length Target character length.
     * @return string Summarized text.
     */
    private function extractive_summarize($text, $target_length)
    {
        // Split into sentences
        $sentences = $this->split_into_sentences($text);
        
        if (count($sentences) <= 1) {
            return $text;
        }

        // Calculate sentence scores (simple word frequency + position)
        $scores = $this->calculate_sentence_scores($sentences);
        
        // Sort sentences by score (highest first)
        arsort($scores);
        
        // Select sentences until we reach target length
        $selected_sentences = [];
        $current_length = 0;
        
        foreach ($scores as $index => $score) {
            $sentence = $sentences[$index];
            $sentence_length = strlen($sentence);
            
            if ($current_length + $sentence_length <= $target_length) {
                $selected_sentences[] = $index;
                $current_length += $sentence_length;
            }
        }
        
        // Sort selected sentences by original order
        sort($selected_sentences);
        
        // Reconstruct text
        $result = [];
        foreach ($selected_sentences as $index) {
            $result[] = $sentences[$index];
        }
        
        return implode(' ', $result);
    }

    /**
     * Split text into sentences.
     *
     * @since 6.1.26
     * @param string $text Text to split.
     * @return array Array of sentences.
     */
    private function split_into_sentences($text)
    {
        // Simple sentence splitting - can be improved for better accuracy
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Filter out very short sentences
        return array_filter($sentences, function($sentence) {
            return strlen(trim($sentence)) > 10;
        });
    }

    /**
     * Calculate sentence scores for ranking.
     *
     * @since 6.1.26
     * @param array $sentences Array of sentences.
     * @return array Array of scores indexed by sentence position.
     */
    private function calculate_sentence_scores($sentences)
    {
        $scores = [];
        $word_frequencies = $this->calculate_word_frequencies($sentences);
        
        foreach ($sentences as $index => $sentence) {
            $score = 0;
            
            // Word frequency score
            $words = $this->extract_words($sentence);
            foreach ($words as $word) {
                $score += $word_frequencies[$word] ?? 0;
            }
            
            // Position bonus (first and last sentences get higher scores)
            if ($index === 0 || $index === count($sentences) - 1) {
                $score *= 1.5;
            }
            
            // Length penalty (very long sentences get lower scores)
            $length = strlen($sentence);
            if ($length > 200) {
                $score *= 0.8;
            }
            
            $scores[$index] = $score;
        }
        
        return $scores;
    }

    /**
     * Calculate word frequencies across all sentences.
     *
     * @since 6.1.26
     * @param array $sentences Array of sentences.
     * @return array Word frequency array.
     */
    private function calculate_word_frequencies($sentences)
    {
        $frequencies = [];
        $all_words = [];
        
        // Collect all words
        foreach ($sentences as $sentence) {
            $words = $this->extract_words($sentence);
            $all_words = array_merge($all_words, $words);
        }
        
        // Count frequencies
        foreach ($all_words as $word) {
            $frequencies[$word] = ($frequencies[$word] ?? 0) + 1;
        }
        
        return $frequencies;
    }

    /**
     * Extract words from a sentence.
     *
     * @since 6.1.26
     * @param string $sentence Sentence text.
     * @return array Array of words.
     */
    private function extract_words($sentence)
    {
        // Convert to lowercase and extract words
        $words = preg_split('/\s+/', strtolower($sentence));
        
        // Filter out short words and common stop words
        $stop_words = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should'];
        
        return array_filter($words, function($word) use ($stop_words) {
            return strlen($word) > 2 && !in_array($word, $stop_words);
        });
    }

    /**
     * Drop lowest ranked sentences to fit within token limit.
     *
     * @since 6.1.26
     * @param string $text Text to trim.
     * @return string Trimmed text.
     */
    private function drop_lowest_ranked_sentences($text)
    {
        $sentences = $this->split_into_sentences($text);
        $scores = $this->calculate_sentence_scores($sentences);
        
        // Sort by score (lowest first)
        asort($scores);
        
        // Remove lowest scored sentences until we fit
        $max_length = self::MAX_TOKENS * self::TOKEN_RATIO;
        $current_length = strlen($text);
        
        foreach ($scores as $index => $score) {
            if ($current_length <= $max_length) {
                break;
            }
            
            $sentence_length = strlen($sentences[$index]);
            if ($current_length - $sentence_length >= $max_length * 0.8) { // Keep at least 80% of target
                unset($sentences[$index]);
                $current_length -= $sentence_length;
            }
        }
        
        return implode(' ', $sentences);
    }
}
