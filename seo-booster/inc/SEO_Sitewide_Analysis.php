<?php

namespace Cleverplugins\SEOBooster;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SEO_Sitewide_Analysis
 *
 * Handles sitewide SEO analysis checks that can be tested from the homepage.
 *
 * @package Cleverplugins\SEOBooster
 * @since 7.0.0
 */
class SEO_Sitewide_Analysis
{
    /**
     * Analysis results.
     *
     * @var array
     */
    private $results = [];

    /**
     * Homepage URL.
     *
     * @var string
     */
    private $homepage_url;

    /**
     * Homepage content.
     *
     * @var string
     */
    private $homepage_content;

    /**
     * Homepage headers.
     *
     * @var array
     */
    private $homepage_headers;

    /**
     * Initialize the sitewide analysis.
     *
     * @since 7.0.0
     * @return void
     */
    public function __construct()
    {
        $this->homepage_url = home_url('/');
        $this->results = [
            'score' => 0,
            'issues' => [],
            'improvements' => [],
            'good' => []
        ];
    }

    /**
     * Run the complete sitewide SEO analysis.
     *
     * @since 7.0.0
     * @return array Analysis results.
     */
    public function analyze()
    {
        // Download homepage content and headers
        $this->download_homepage();

        // Run all sitewide checks
        $this->check_ssl();
        $this->check_robots_txt();
        $this->check_sitemap();
        $this->check_favicon();
        $this->check_viewport();
        $this->check_language();

        // Calculate overall score
        $this->calculate_score();

        return $this->results;
    }

    /**
     * Download homepage content and headers.
     *
     * @since 7.0.0
     * @return void
     */
    private function download_homepage()
    {
        $response = wp_remote_get($this->homepage_url, [
            'timeout' => 10,
            'sslverify' => false,
            'user-agent' => 'SEO Booster Sitewide Analysis/1.0'
        ]);

        if (is_wp_error($response)) {
            Utils::log('Failed to download homepage for sitewide analysis: ' . $response->get_error_message(), 2);
            return;
        }

        $this->homepage_content = wp_remote_retrieve_body($response);
        $this->homepage_headers = wp_remote_retrieve_headers($response)->getAll();
    }

    /**
     * Check SSL/HTTPS enforcement.
     *
     * @since 7.0.0
     * @return void
     */
    private function check_ssl()
    {
        $home_url = home_url();
        $is_https = strpos($home_url, 'https://') === 0;

        if (!$is_https) {
            $this->add_issue('ssl_missing', __('Site is not using HTTPS. SSL is important for SEO and security.', 'seo-booster'), 'error');
        } else {
            $this->add_good('ssl_enabled', __('Site is using HTTPS.', 'seo-booster'));
        }
    }

    /**
     * Check robots.txt accessibility and configuration.
     *
     * @since 7.0.0
     * @return void
     */
    private function check_robots_txt()
    {
        $robots_url = home_url('/robots.txt');
        $response = wp_remote_get($robots_url, [
            'timeout' => 5,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            $this->add_issue('robots_txt_missing', __('robots.txt file is not accessible.', 'seo-booster'), 'warning');
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $this->add_issue('robots_txt_missing', __('robots.txt file is not accessible (HTTP ' . $status_code . ').', 'seo-booster'), 'warning');
            return;
        }

        $robots_content = wp_remote_retrieve_body($response);
        
        // Check if robots.txt has content
        if (empty(trim($robots_content))) {
            $this->add_improvement('robots_txt_empty', __('robots.txt file exists but is empty. Consider adding crawl directives.', 'seo-booster'));
        } else {
            $this->add_good('robots_txt_exists', __('robots.txt file is accessible and configured.', 'seo-booster'));
        }
    }

    /**
     * Check XML sitemap existence and accessibility.
     *
     * @since 7.0.0
     * @return void
     */
    private function check_sitemap()
    {
        // Check common sitemap locations
        $sitemap_urls = [
            home_url('/sitemap.xml'),
            home_url('/sitemaps.xml'),
            home_url('/sitemap_index.xml'),
            home_url('/wp-sitemap.xml'), // WordPress default
        ];

        // Also check if Yoast or other SEO plugins generate sitemaps
        if (function_exists('get_option')) {
            $yoast_sitemap = get_option('wpseo_xml_sitemap_options');
            if (!empty($yoast_sitemap)) {
                $sitemap_urls[] = home_url('/sitemap_index.xml');
            }
        }

        $sitemap_found = false;
        foreach ($sitemap_urls as $sitemap_url) {
            $response = wp_remote_head($sitemap_url, [
                'timeout' => 5,
                'sslverify' => false
            ]);

            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                if ($status_code === 200) {
                    $sitemap_found = true;
                    $this->add_good('sitemap_exists', sprintf(__('XML sitemap found at: %s', 'seo-booster'), $sitemap_url));
                    break;
                }
            }
        }

        if (!$sitemap_found) {
            $this->add_issue('sitemap_missing', __('XML sitemap not found. Sitemaps help search engines discover and index your content.', 'seo-booster'), 'warning');
        }
    }

    /**
     * Check favicon presence.
     *
     * @since 7.0.0
     * @return void
     */
    private function check_favicon()
    {
        if (empty($this->homepage_content)) {
            return;
        }

        // Check for favicon in various formats and locations
        $favicon_patterns = [
            '/<link[^>]*rel=["\'](?:shortcut )?icon["\'][^>]*>/i',
            '/<link[^>]*rel=["\']apple-touch-icon["\'][^>]*>/i',
        ];

        $favicon_found = false;
        foreach ($favicon_patterns as $pattern) {
            if (preg_match($pattern, $this->homepage_content)) {
                $favicon_found = true;
                break;
            }
        }

        // Also check common favicon locations
        if (!$favicon_found) {
            $favicon_urls = [
                home_url('/favicon.ico'),
                home_url('/favicon.png'),
                home_url('/apple-touch-icon.png'),
            ];

            foreach ($favicon_urls as $favicon_url) {
                $response = wp_remote_head($favicon_url, [
                    'timeout' => 3,
                    'sslverify' => false
                ]);

                if (!is_wp_error($response)) {
                    $status_code = wp_remote_retrieve_response_code($response);
                    if ($status_code === 200) {
                        $favicon_found = true;
                        break;
                    }
                }
            }
        }

        if ($favicon_found) {
            $this->add_good('favicon_exists', __('Favicon is present.', 'seo-booster'));
        } else {
            $this->add_improvement('favicon_missing', __('Favicon is missing. Add a favicon to improve brand recognition.', 'seo-booster'));
        }
    }

    /**
     * Check mobile viewport meta tag (sitewide check).
     *
     * @since 7.0.0
     * @return void
     */
    private function check_viewport()
    {
        if (empty($this->homepage_content)) {
            return;
        }

        // Check for viewport meta tag
        if (preg_match('/<meta[^>]*name=["\']viewport["\'][^>]*>/i', $this->homepage_content)) {
            $this->add_good('viewport_exists', __('Mobile viewport meta tag is present.', 'seo-booster'));
        } else {
            $this->add_issue('viewport_missing', __('Mobile viewport meta tag is missing. This is essential for mobile SEO.', 'seo-booster'), 'error');
        }
    }

    /**
     * Check language declaration (sitewide check).
     *
     * @since 7.0.0
     * @return void
     */
    private function check_language()
    {
        if (empty($this->homepage_content)) {
            return;
        }

        // Check for lang attribute on html tag
        if (preg_match('/<html[^>]*lang=["\']([^"\']+)["\']/i', $this->homepage_content, $matches)) {
            $lang = $matches[1];
            $this->add_good('language_declared', sprintf(__('Language is declared: %s', 'seo-booster'), $lang));
        } else {
            $this->add_improvement('language_missing', __('HTML lang attribute is missing. Declaring the language helps search engines understand your content.', 'seo-booster'));
        }
    }

    /**
     * Add an issue to the results.
     *
     * @since 7.0.0
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
     * @since 7.0.0
     * @param string $key Improvement key.
     * @param string $message Improvement message.
     * @return void
     */
    private function add_improvement($key, $message)
    {
        $this->results['improvements'][] = [
            'key' => $key,
            'message' => $message
        ];
    }

    /**
     * Add a good practice to the results.
     *
     * @since 7.0.0
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
     * Calculate overall SEO score.
     *
     * @since 7.0.0
     * @return void
     */
    private function calculate_score()
    {
        $error_count = 0;
        $warning_count = 0;
        
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

        // Calculate score: Start with 100, subtract points for issues
        // Errors: -15 points each, Warnings: -8 points each
        // Improvements: -3 points each
        // Good practices: +2 points each
        $score = 100 - ($error_count * 15) - ($warning_count * 8) - ($improvement_count * 3) + ($good_count * 2);
        
        $this->results['score'] = max(0, min(100, $score));
    }
}

