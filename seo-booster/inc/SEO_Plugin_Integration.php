<?php

namespace Cleverplugins\SEOBooster;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SEO_Plugin_Integration
 *
 * Handles integration with other SEO plugins for AI suggestions.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */
class SEO_Plugin_Integration
{
    /**
     * Detect active SEO plugin and return field mappings.
     *
     * @since 6.1.26
     * @return array Plugin information and field mappings.
     */
    public static function get_active_seo_plugin()
    {
        $active_plugin = null;
        $field_mappings = [];

        // Check for Yoast SEO
        if (class_exists('WPSEO_Meta') || function_exists('wpseo_init')) {
            $active_plugin = 'yoast';
            $field_mappings = [
                'title' => '#yoast_wpseo_title',
                'description' => '#yoast_wpseo_metadesc',
                'focus_keyword' => '#yoast_wpseo_focuskw'
            ];
        }
        // Check for Rank Math
        elseif (class_exists('RankMath')) {
            $active_plugin = 'rankmath';
            $field_mappings = [
                'title' => '#rank_math_title',
                'description' => '#rank_math_description',
                'focus_keyword' => '#rank_math_focus_keyword'
            ];
        }
        // Check for SEOPress
        elseif (function_exists('seopress_get_service')) {
            $active_plugin = 'seopress';
            $field_mappings = [
                'title' => '#seopress_titles_title',
                'description' => '#seopress_titles_desc',
                'focus_keyword' => '#seopress_analysis_target_kw'
            ];
        }
        // Check for All in One SEO
        elseif (class_exists('AIOSEO')) {
            $active_plugin = 'aioseo';
            $field_mappings = [
                'title' => '#aioseo_title',
                'description' => '#aioseo_description',
                'focus_keyword' => '#aioseo_keywords'
            ];
        }
        // Check for The SEO Framework
        elseif (class_exists('The_SEO_Framework')) {
            $active_plugin = 'seoframework';
            $field_mappings = [
                'title' => '#tsf_title',
                'description' => '#tsf_description',
                'focus_keyword' => '#tsf_focus'
            ];
        }

        return [
            'plugin' => $active_plugin,
            'name' => $active_plugin ? self::get_plugin_name($active_plugin) : null,
            'fields' => $field_mappings
        ];
    }

    /**
     * Get human-readable plugin name.
     *
     * @since 6.1.26
     * @param string $plugin Plugin identifier.
     * @return string Plugin name.
     */
    private static function get_plugin_name($plugin)
    {
        $names = [
            'yoast' => 'Yoast SEO',
            'rankmath' => 'Rank Math',
            'seopress' => 'SEOPress',
            'aioseo' => 'All in One SEO',
            'seoframework' => 'The SEO Framework'
        ];

        return $names[$plugin] ?? 'Unknown Plugin';
    }

    /**
     * Apply AI suggestion to the appropriate field in the active SEO plugin.
     *
     * @since 6.1.26
     * @param string $field_type The type of field (title, description, focus_keyword).
     * @param string $value The value to apply.
     * @return array Result of the operation.
     */
    public static function apply_suggestion($field_type, $value)
    {
        $plugin_info = self::get_active_seo_plugin();
        
        if (!$plugin_info['plugin']) {
            return [
                'success' => false,
                'message' => __('No compatible SEO plugin detected. Please install Yoast SEO, Rank Math, SEOPress, All in One SEO, or The SEO Framework.', 'seo-booster')
            ];
        }

        $field_selector = $plugin_info['fields'][$field_type] ?? null;
        
        if (!$field_selector) {
            return [
                'success' => false,
                'message' => sprintf(__('Field type "%s" not supported for %s.', 'seo-booster'), $field_type, $plugin_info['name'])
            ];
        }

        return [
            'success' => true,
            'plugin' => $plugin_info['plugin'],
            'plugin_name' => $plugin_info['name'],
            'field_selector' => $field_selector,
            'value' => $value,
            'message' => sprintf(__('Suggestion will be applied to %s field in %s.', 'seo-booster'), $field_type, $plugin_info['name'])
        ];
    }
}
