<?php
namespace Cleverplugins\SEOBooster;
/**
 * SEO AI Configuration
 * 
 * Configuration settings for the AI optimization system
 * 
 * @package SEO_Booster
 * @since 6.1.26
 */

if (!defined('ABSPATH')) {
    exit;
}

class SEO_AI_Config {
    
    /**
     * Configuration options
     */
    private static $config = array(
        // Cache settings
        'license_cache_duration' => 3600, // 1 hour
        'content_cache_duration' => 86400, // 24 hours
        'request_timeout' => 30, // 30 minutes
        
        // Credit system
        'credits_per_request' => 1,
        'max_retries' => 30,
        'retry_interval' => 2000, // 2 seconds
        
        // AI settings
        'ai_model' => 'gpt-5-mini',
        'ai_temperature' => 0.7,
        'max_content_length' => 2000,
        
        // Freemius settings
        'freemius_product_id' => '24720',
        'freemius_api_url' => 'https://api.freemius.com/v1/developers/',

        // Credits API
        'credits_api_url' => 'https://api.seoboosterpro.com',
        
        // UI settings
        'show_optimization_ui' => true,
        'auto_apply_suggestions' => false,
        'show_seo_score' => true,
        'show_recommendations' => true,
        
        // Debug settings
        'debug_mode' => false,
        'log_requests' => false,
        'log_responses' => false
    );
    
    /**
     * Get configuration value
     */
    public static function get($key, $default = null) {
        // Check WordPress options first
        $wp_value = get_option('seo_ai_' . $key);
        if ($wp_value !== false) {
            return $wp_value;
        }
        
        // Return default config value
        return isset(self::$config[$key]) ? self::$config[$key] : $default;
    }
    
    /**
     * Set configuration value
     */
    public static function set($key, $value) {
        return update_option('seo_ai_' . $key, $value);
    }
    
    /**
     * Get all configuration
     */
    public static function get_all() {
        $config = array();
        foreach (self::$config as $key => $default) {
            $config[$key] = self::get($key, $default);
        }
        return $config;
    }
    
    /**
     * Reset configuration to defaults
     */
    public static function reset() {
        foreach (self::$config as $key => $value) {
            delete_option('seo_ai_' . $key);
        }
    }
    
    /**
     * Validate configuration
     */
    public static function validate() {
        $errors = array();
        
        // Validate numeric values
        $numeric_fields = array(
            'license_cache_duration',
            'content_cache_duration',
            'request_timeout',
            'credits_per_request',
            'max_retries',
            'retry_interval',
            'ai_temperature',
            'max_content_length'
        );
        
        foreach ($numeric_fields as $field) {
            $value = self::get($field);
            if (!is_numeric($value) || $value < 0) {
                $errors[] = "$field must be a positive number";
            }
        }
        
        // Validate temperature range
        $temperature = self::get('ai_temperature');
        if ($temperature < 0 || $temperature > 2) {
            $errors[] = 'ai_temperature must be between 0 and 2';
        }
        
        return $errors;
    }
    
    /**
     * Get configuration for JavaScript
     */
    public static function get_js_config() {
        return array(
            'max_retries' => self::get('max_retries'),
            'retry_interval' => self::get('retry_interval'),
            'show_seo_score' => self::get('show_seo_score'),
            'show_recommendations' => self::get('show_recommendations'),
            'debug_mode' => self::get('debug_mode')
        );
    }
    
    /**
     * Update configuration from form data
     */
    public static function update_from_form($data) {
        $updated = array();
        
        foreach (self::$config as $key => $default) {
            if (isset($data[$key])) {
                $value = $data[$key];
                
                // Sanitize based on type
                if (is_numeric($default)) {
                    $value = floatval($value);
                } elseif (is_bool($default)) {
                    $value = (bool) $value;
                } else {
                    $value = sanitize_text_field($value);
                }
                
                self::set($key, $value);
                $updated[] = $key;
            }
        }
        
        return $updated;
    }
    
    /**
     * Get configuration form fields
     */
    public static function get_form_fields() {
        return array(
            'freemius_product_id' => array(
                'type' => 'text',
                'label' => 'Freemius Product ID',
                'description' => 'Your Freemius product ID for license validation',
                'required' => true
            ),
            'credits_per_request' => array(
                'type' => 'number',
                'label' => 'Credits Per Request',
                'description' => 'Number of credits consumed per optimization request',
                'min' => 1,
                'max' => 100
            ),
            'license_cache_duration' => array(
                'type' => 'number',
                'label' => 'License Cache Duration (seconds)',
                'description' => 'How long to cache license validation results',
                'min' => 300,
                'max' => 86400
            ),
            'content_cache_duration' => array(
                'type' => 'number',
                'label' => 'Content Cache Duration (seconds)',
                'description' => 'How long to cache optimization results',
                'min' => 3600,
                'max' => 604800
            ),
            'ai_model' => array(
                'type' => 'select',
                'label' => 'AI Model',
                'description' => 'AI model (when using a provider that supports model selection)',
                'options' => array(
                    'gpt-5-mini' => 'GPT-5 Mini (Recommended)',
                    'gpt-5' => 'GPT-5',
                    'gpt-4.1-mini' => 'GPT-4.1 Mini',
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo'
                )
            ),
            'ai_temperature' => array(
                'type' => 'number',
                'label' => 'AI Temperature',
                'description' => 'Creativity level for AI responses (0-2)',
                'min' => 0,
                'max' => 2,
                'step' => 0.1
            ),
            'show_optimization_ui' => array(
                'type' => 'checkbox',
                'label' => 'Show Optimization UI',
                'description' => 'Display AI optimization interface on post edit screens'
            ),
            'show_seo_score' => array(
                'type' => 'checkbox',
                'label' => 'Show SEO Score',
                'description' => 'Display SEO score in optimization results'
            ),
            'show_recommendations' => array(
                'type' => 'checkbox',
                'label' => 'Show Recommendations',
                'description' => 'Display improvement recommendations'
            ),
            'debug_mode' => array(
                'type' => 'checkbox',
                'label' => 'Debug Mode',
                'description' => 'Enable debug logging and verbose output'
            )
        );
    }
}
