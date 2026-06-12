<?php

namespace Cleverplugins\SEOBooster;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LLM_Helper
 *
 * Helper utilities for LLM SEO functionality.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */
class LLM_Helper
{
    /**
     * Get post language with Polylang/WPML support.
     *
     * @since 6.1.26
     * @param int $post_id Post ID.
     * @return string Language code in WordPress locale format.
     */
    public static function get_post_language($post_id)
    {
        $detected_locale = null;
        $detection_method = '';

        // Try Polylang first (if available)
        if (function_exists('pll_get_post_language')) {
            // Try to get locale directly if Polylang supports it
            $polylang_locale = pll_get_post_language($post_id, 'locale');
            if ($polylang_locale) {
                $detected_locale = $polylang_locale;
                $detection_method = 'Polylang (locale)';
            } else {
                // Fallback: get language code and convert
                $polylang_code = pll_get_post_language($post_id);
                if ($polylang_code) {
                    $detected_locale = self::convert_language_code_to_locale($polylang_code);
                    $detection_method = 'Polylang (code)';
                }
            }
        }

        // Fallback to Polylang current language
        if (!$detected_locale && function_exists('pll_current_language')) {
            $polylang_current = pll_current_language('locale');
            if ($polylang_current) {
                $detected_locale = $polylang_current;
                $detection_method = 'Polylang (current locale)';
            } else {
                // Try with slug if locale not available
                $polylang_slug = pll_current_language();
                if ($polylang_slug) {
                    $detected_locale = self::convert_language_code_to_locale($polylang_slug);
                    $detection_method = 'Polylang (current code)';
                }
            }
        }

        // Try WPML post language details (modern WPML hook)
        if (!$detected_locale && has_filter('wpml_post_language_details')) {
            $lang_details = apply_filters('wpml_post_language_details', null, $post_id);
            if ($lang_details) {
                // Handle both object and array formats
                $locale = is_object($lang_details) ? ($lang_details->locale ?? null) : ($lang_details['locale'] ?? null);
                if ($locale) {
                    $detected_locale = $locale;
                    $detection_method = 'WPML (post locale)';
                } else {
                    // If locale not available, try language_code
                    $language_code = is_object($lang_details) ? ($lang_details->language_code ?? null) : ($lang_details['language_code'] ?? null);
                    if ($language_code) {
                        $detected_locale = self::convert_language_code_to_locale($language_code);
                        $detection_method = 'WPML (post code)';
                    }
                }
            }
        }

        // Fallback to WPML current site language (modern WPML hook)
        if (!$detected_locale && has_filter('wpml_current_language')) {
            $current_lang = apply_filters('wpml_current_language', null);
            if ($current_lang) {
                $detected_locale = self::convert_language_code_to_locale($current_lang);
                $detection_method = 'WPML (current)';
            }
        }

        // Fallback to WordPress site locale
        if (!$detected_locale) {
            $detected_locale = get_locale();
            $detection_method = 'WordPress (get_locale)';
        }

        // Normalize the locale
        $final_locale = self::normalize_locale($detected_locale);

        return $final_locale;
    }

    /**
     * Convert WPML 2-letter language code to WordPress locale format.
     *
     * WordPress doesn't provide built-in functionality to convert language codes to locales,
     * so we use a mapping as fallback when wpml_post_language_details only returns language_code.
     *
     * @since 6.1.26
     * @param string $language_code 2-letter language code (e.g., 'en', 'da', 'fr').
     * @return string WordPress locale format (e.g., 'en_US', 'da_DK').
     */
    private static function convert_language_code_to_locale($language_code)
    {
        // Mapping for common languages (WordPress doesn't provide this conversion)
        $code_to_locale = [
            'en' => 'en_US',
            'da' => 'da_DK',
            'sv' => 'sv_SE',
            'no' => 'no_NO',
            'de' => 'de_DE',
            'fr' => 'fr_FR',
            'es' => 'es_ES',
            'it' => 'it_IT',
            'pt' => 'pt_PT',
            'nl' => 'nl_NL',
            'pl' => 'pl_PL',
            'ru' => 'ru_RU',
        ];
        
        return $code_to_locale[$language_code] ?? 'en_US';
    }

    /**
     * Normalize locale format.
     *
     * @since 6.1.26
     * @param string $locale WordPress locale.
     * @return string Normalized locale.
     */
    private static function normalize_locale($locale)
    {
        // Normalize common variants
        $normalizations = [
            'en_GB' => 'en_US', // Normalize British English to US English
            'da' => 'da_DK',    // Normalize 2-letter code to full locale
        ];
        
        return $normalizations[$locale] ?? $locale;
    }

    /**
     * Get language name from locale code.
     *
     * @since 6.1.26
     * @param string $locale WordPress locale code (e.g., 'da_DK', 'en_US').
     * @return string Language name (e.g., 'Danish', 'English').
     */
    public static function get_language_name($locale)
    {
        $language_names = [
            'da_DK' => 'Danish',
            'en_US' => 'English',
            'sv_SE' => 'Swedish',
            'no_NO' => 'Norwegian',
            'de_DE' => 'German',
            'fr_FR' => 'French',
            'es_ES' => 'Spanish',
            'it_IT' => 'Italian',
            'pt_PT' => 'Portuguese',
            'nl_NL' => 'Dutch',
            'pl_PL' => 'Polish',
            'ru_RU' => 'Russian',
        ];

        // Return language name if found, otherwise return locale code
        return $language_names[$locale] ?? $locale;
    }

    /**
     * Get post description (excerpt or generated).
     *
     * @since 6.1.26
     * @param \WP_Post $post Post object.
     * @return string Post description.
     */
    public static function get_post_description($post)
    {
        if (!empty($post->post_excerpt)) {
            return $post->post_excerpt;
        }

        // Generate from content
        $content = wp_strip_all_tags($post->post_content);
        return wp_trim_words($content, 25, '...');
    }

    /**
     * Get GSC keywords for a post.
     *
     * @since 6.1.26
     * @param int $post_id Post ID.
     * @return array Array of GSC keywords.
     */
    public static function get_gsc_keywords($post_id)
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

    /**
     * Get focus keywords for a post.
     *
     * @since 6.1.26
     * @param int $post_id Post ID.
     * @return array Array of focus keywords.
     */
    public static function get_focus_keywords($post_id)
    {
        $plugin_keywords = Google_API::get_focus_keywords($post_id);
        
        if (empty($plugin_keywords)) {
            return [];
        }

        return $plugin_keywords;
    }

    /**
     * Get saved suggestions for a post.
     *
     * @since 6.1.26
     * @param int $post_id Post ID.
     * @return array|false Saved suggestions or false if not found.
     */
    public static function get_saved_suggestions($post_id)
    {
        $saved = get_post_meta($post_id, '_llm_seo_last_result', true);
        
        if (empty($saved) || !is_array($saved)) {
            return false;
        }

        return $saved;
    }

    /**
     * Whether the WordPress AI Client environment is ready (WP 7+).
     *
     * @since 7.0.4
     * @return bool
     */
    public static function wp_ai_environment_ready()
    {
        if (!function_exists('wp_ai_client_prompt')) {
            return false;
        }

        if (function_exists('wp_supports_ai') && !wp_supports_ai()) {
            return false;
        }

        return true;
    }

    /**
     * Whether at least one AI provider is configured in Settings → Connectors.
     *
     * @since 7.0.4
     * @return bool
     */
    public static function wp_ai_has_configured_provider()
    {
        if (!class_exists('\WordPress\AiClient\AiClient')) {
            return false;
        }

        try {
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            if (!method_exists($registry, 'getRegisteredProviderIds') || !method_exists($registry, 'isProviderConfigured')) {
                return false;
            }

            foreach ($registry->getRegisteredProviderIds() as $provider_id) {
                if ($registry->isProviderConfigured($provider_id)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            // Fall through to connector option check.
        }

        if (function_exists('wp_get_connectors')) {
            foreach (wp_get_connectors() as $connector_data) {
                if (($connector_data['type'] ?? '') !== 'ai_provider') {
                    continue;
                }
                $setting_name = $connector_data['authentication']['setting_name'] ?? '';
                if ($setting_name && get_option($setting_name, '')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the current setup supports text generation (official WP 7 feature detection).
     *
     * @since 7.0.4
     * @return bool
     */
    public static function wp_ai_supports_text_generation()
    {
        if (!self::wp_ai_environment_ready()) {
            return false;
        }

        if (!self::wp_ai_has_configured_provider()) {
            return false;
        }

        $builder = wp_ai_client_prompt('test');

        return (bool) $builder->is_supported_for_text_generation();
    }

    /**
     * Whether WordPress AI UI (Tools, metabox) should be enabled.
     *
     * Uses environment + configured connector only. Stricter support checks run at generation time.
     *
     * @since 7.0.4
     * @return bool
     */
    public static function wp_ai_ui_ready()
    {
        return self::wp_ai_environment_ready() && self::wp_ai_has_configured_provider();
    }

    /**
     * Whether WordPress AI can be used for text generation (all official checks pass).
     *
     * @since 7.0.4
     * @return bool
     */
    public static function wp_ai_is_available()
    {
        return self::wp_ai_supports_text_generation();
    }

    /**
     * User-facing reason when WordPress AI is not available.
     *
     * @since 7.0.4
     * @return string
     */
    public static function wp_ai_unavailable_message()
    {
        if (!function_exists('wp_ai_client_prompt')) {
            return __(
                'WordPress AI is not available. Use WordPress 7 or later.',
                'seo-booster'
            );
        }

        if (function_exists('wp_supports_ai') && !wp_supports_ai()) {
            return __(
                'AI features are disabled in this environment. On local sites, check that AI is not turned off (for example WP_AI_SUPPORT in wp-config.php).',
                'seo-booster'
            );
        }

        if (!self::wp_ai_has_configured_provider()) {
            return __(
                'No AI connector is configured. Go to Settings → Connectors and connect a provider.',
                'seo-booster'
            );
        }

        if (!self::wp_ai_supports_text_generation()) {
            return __(
                'No configured AI model supports text generation for this request. Check Settings → Connectors.',
                'seo-booster'
            );
        }

        return __(
            'WordPress AI is not available. Check Settings → Connectors.',
            'seo-booster'
        );
    }

    /**
     * Provider IDs that only support text (no image/vision input via WordPress AI Client).
     *
     * @since 7.0.4
     * @return string[]
     */
    public static function wp_ai_text_only_provider_ids()
    {
        $ids = ['deepseek'];

        /**
         * Filter text-only AI provider IDs for image metadata notices.
         *
         * @since 7.0.4
         * @param string[] $ids Provider IDs.
         */
        return apply_filters('sb_ai_text_only_provider_ids', $ids);
    }

    /**
     * Provider IDs suitable for image metadata (vision or multimodal chat).
     *
     * @since 7.0.4
     * @return string[]
     */
    public static function wp_ai_vision_capable_provider_ids()
    {
        $ids = ['openai', 'google', 'anthropic'];

        /**
         * Filter vision-capable AI provider IDs for image metadata.
         *
         * @since 7.0.4
         * @param string[] $ids Provider IDs.
         */
        return apply_filters('sb_ai_vision_capable_provider_ids', $ids);
    }

    /**
     * Configured AI provider IDs (registry + connector options).
     *
     * @since 7.0.4
     * @return string[]
     */
    public static function wp_ai_get_configured_provider_ids()
    {
        $configured = [];

        if (class_exists('\WordPress\AiClient\AiClient')) {
            try {
                $registry = \WordPress\AiClient\AiClient::defaultRegistry();
                if (method_exists($registry, 'getRegisteredProviderIds') && method_exists($registry, 'isProviderConfigured')) {
                    foreach ($registry->getRegisteredProviderIds() as $provider_id) {
                        if ($registry->isProviderConfigured($provider_id)) {
                            $configured[] = $provider_id;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Fall through.
            }
        }

        if (function_exists('wp_get_connectors')) {
            foreach (wp_get_connectors() as $connector_id => $connector_data) {
                if (($connector_data['type'] ?? '') !== 'ai_provider') {
                    continue;
                }
                $setting_name = $connector_data['authentication']['setting_name'] ?? '';
                if ($setting_name && get_option($setting_name, '') && !in_array($connector_id, $configured, true)) {
                    $configured[] = $connector_id;
                }
            }
        }

        return array_values(array_unique($configured));
    }

    /**
     * Whether a configured vision-capable connector exists (e.g. OpenAI).
     *
     * @since 7.0.4
     * @return bool
     */
    public static function wp_ai_has_vision_capable_connector_configured()
    {
        $vision_ids = self::wp_ai_vision_capable_provider_ids();
        foreach (self::wp_ai_get_configured_provider_ids() as $provider_id) {
            if (in_array($provider_id, $vision_ids, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether only text-only connectors are configured (e.g. DeepSeek alone).
     *
     * @since 7.0.4
     * @return bool
     */
    public static function wp_ai_only_text_only_connectors_configured()
    {
        $configured = self::wp_ai_get_configured_provider_ids();
        if (empty($configured)) {
            return false;
        }

        $text_only = self::wp_ai_text_only_provider_ids();
        foreach ($configured as $provider_id) {
            if (!in_array($provider_id, $text_only, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Human-readable label for a connector ID.
     *
     * @since 7.0.4
     * @param string $provider_id Provider ID.
     * @return string
     */
    public static function wp_ai_provider_label($provider_id)
    {
        if (function_exists('wp_get_connectors')) {
            $connectors = wp_get_connectors();
            if (isset($connectors[$provider_id]['name']) && $connectors[$provider_id]['name']) {
                return $connectors[$provider_id]['name'];
            }
        }

        return ucwords(str_replace(['-', '_'], ' ', $provider_id));
    }

    /**
     * Whether image metadata tools can run with the WordPress AI provider.
     *
     * Requires a vision-capable connector and a model that supports text generation.
     *
     * @since 7.0.4
     * @return bool
     */
    public static function wp_ai_image_tools_can_process()
    {
        if (!self::wp_ai_environment_ready() || !self::wp_ai_has_configured_provider()) {
            return false;
        }

        if (!self::wp_ai_has_vision_capable_connector_configured()) {
            return false;
        }

        return self::wp_ai_supports_text_generation();
    }

    /**
     * Optional user-facing hint pointing to SEO Booster Credits (when released).
     *
     * @since 7.0.5
     * @return string Empty when credits provider is not publicly available.
     */
    public static function ai_credits_option_hint()
    {
        if (!Credits_Service::is_ai_provider_available()) {
            return '';
        }

        return __(' or use SEO Booster Credits in SEO Booster Settings.', 'seo-booster');
    }

    /**
     * Notice context for the Tools image metadata UI.
     *
     * @since 7.0.4
     * @return array{message: string, type: string}
     */
    public static function wp_ai_image_metadata_notice_context()
    {
        if (!function_exists('wp_ai_client_prompt')) {
            return [
                'message' => self::wp_ai_unavailable_message(),
                'type'    => 'unavailable',
            ];
        }

        if (function_exists('wp_supports_ai') && !wp_supports_ai()) {
            return [
                'message' => self::wp_ai_unavailable_message(),
                'type'    => 'environment',
            ];
        }

        if (!self::wp_ai_has_configured_provider()) {
            return [
                'message' => __(
                    'No AI connector is configured. Connect a vision-capable provider (such as OpenAI) under Settings → Connectors.',
                    'seo-booster'
                ) . self::ai_credits_option_hint(),
                'type'    => 'no_connector',
            ];
        }

        if (self::wp_ai_only_text_only_connectors_configured()) {
            $configured = self::wp_ai_get_configured_provider_ids();
            $labels = array_map([__CLASS__, 'wp_ai_provider_label'], $configured);
            $connector_list = implode(', ', $labels);

            if (count($configured) === 1 && in_array('deepseek', $configured, true)) {
                return [
                    'message' => __(
                        'DeepSeek is a text-only connector: it cannot analyze image pixels. SEO Booster needs a vision-capable model to write alt text from the actual image (not just the file URL). Connect OpenAI under Settings → Connectors.',
                        'seo-booster'
                    ) . self::ai_credits_option_hint(),
                    'type'    => 'deepseek_only',
                ];
            }

            return [
                'message' => sprintf(
                    /* translators: %s: comma-separated connector names */
                    __(
                        '%s only supports text generation and cannot analyze images for alt text. Connect a vision-capable provider (such as OpenAI) under Settings → Connectors.',
                        'seo-booster'
                    ) . self::ai_credits_option_hint(),
                    $connector_list
                ),
                'type' => 'text_only_only',
            ];
        }

        if (!self::wp_ai_has_vision_capable_connector_configured()) {
            return [
                'message' => __(
                    'No vision-capable connector is configured. Image metadata needs a provider that can interpret images (such as OpenAI), not text-only APIs. Add one under Settings → Connectors.',
                    'seo-booster'
                ) . self::ai_credits_option_hint(),
                'type'    => 'no_vision_connector',
            ];
        }

        if (!self::wp_ai_supports_text_generation()) {
            return [
                'message' => __(
                    'A vision connector is connected, but WordPress could not find a model for text generation (check your API key and that this site can reach the provider). Open Settings → Connectors to verify the connection.',
                    'seo-booster'
                ),
                'type'    => 'support_check_failed',
            ];
        }

        return [
            'message' => '',
            'type'    => 'ok',
        ];
    }

    /**
     * User-facing notice when image metadata batch cannot run (WordPress provider).
     *
     * @since 7.0.4
     * @return string
     */
    public static function wp_ai_image_metadata_unavailable_message()
    {
        $context = self::wp_ai_image_metadata_notice_context();
        return $context['message'];
    }
}

