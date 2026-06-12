<?php

namespace Cleverplugins\SEOBooster;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SEO_Output
 *
 * Handles SEO Booster branding output only.
 * All SEO meta output functionality has been removed to focus on enhancement features.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */
class SEO_Output
{
    /**
     * Initialize the SEO output functionality.
     *
     * @since 6.1.26
     * @return void
     */
    public static function init()
    {
        add_action('wp_head', [__CLASS__, 'output_seo_meta'], 999);
    }

    /**
     * Output SEO Booster branding comment.
     *
     * @since 6.1.26
     * @return void
     */
    public static function output_seo_meta()
    {
        // Skip on admin pages
        if (is_admin()) {
            return;
        }
        
        // Output SEO Booster comment for branding
        echo '<!-- Using SEO Booster plugin v' . Utils::get_plugin_version() . ' - https://seoboosterpro.com -->' . "\n";
    }
}