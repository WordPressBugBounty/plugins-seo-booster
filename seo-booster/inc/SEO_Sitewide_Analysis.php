<?php

namespace Cleverplugins\SEOBooster;

use Cleverplugins\SEOBooster\Tools\Tools_Llms_Txt;
use function Cleverplugins\SEOBooster\seobooster_fs;
if ( !defined( 'ABSPATH' ) ) {
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
class SEO_Sitewide_Analysis {
    /**
     * Analysis results.
     *
     * @var array
     */
    private $results = array();

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
    public function __construct() {
        $this->homepage_url = home_url( '/' );
        $this->results = array(
            'score'        => 0,
            'issues'       => array(),
            'improvements' => array(),
            'good'         => array(),
        );
    }

    /**
     * Run the complete sitewide SEO analysis.
     *
     * @since 7.0.0
     * @return array Analysis results.
     */
    public function analyze() {
        // Download homepage content and headers
        $this->download_homepage();
        // Run all sitewide checks
        $this->check_ssl();
        $this->check_robots_txt();
        $this->check_sitemap();
        $this->check_favicon();
        $this->check_viewport();
        $this->check_language();
        $this->check_llms_txt();
        $this->check_entity_map();
        $this->check_ai_bot_tracking();
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
    private function download_homepage() {
        $response = wp_remote_get( $this->homepage_url, array(
            'timeout'    => 10,
            'sslverify'  => false,
            'user-agent' => 'SEO Booster Sitewide Analysis/1.0',
        ) );
        if ( is_wp_error( $response ) ) {
            Utils::log( 'Failed to download homepage for sitewide analysis: ' . $response->get_error_message(), 2 );
            return;
        }
        $this->homepage_content = wp_remote_retrieve_body( $response );
        $this->homepage_headers = wp_remote_retrieve_headers( $response )->getAll();
    }

    /**
     * Check SSL/HTTPS enforcement.
     *
     * @since 7.0.0
     * @return void
     */
    private function check_ssl() {
        $home_url = home_url();
        $is_https = strpos( $home_url, 'https://' ) === 0;
        if ( !$is_https ) {
            $this->add_issue( 'ssl_missing', __( 'Site is not using HTTPS. SSL is important for SEO and security.', 'seo-booster' ), 'error' );
        } else {
            $this->add_good( 'ssl_enabled', __( 'Site is using HTTPS.', 'seo-booster' ) );
        }
    }

    /**
     * Check robots.txt accessibility and configuration.
     *
     * @since 7.0.0
     * @return void
     */
    private function check_robots_txt() {
        $robots_url = home_url( '/robots.txt' );
        $response = wp_remote_get( $robots_url, array(
            'timeout'   => 5,
            'sslverify' => false,
        ) );
        if ( is_wp_error( $response ) ) {
            $this->add_issue( 'robots_txt_missing', __( 'robots.txt file is not accessible.', 'seo-booster' ), 'warning' );
            return;
        }
        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            $this->add_issue( 'robots_txt_missing', __( 'robots.txt file is not accessible (HTTP ' . $status_code . ').', 'seo-booster' ), 'warning' );
            return;
        }
        $robots_content = wp_remote_retrieve_body( $response );
        // Check if robots.txt has content
        if ( empty( trim( $robots_content ) ) ) {
            $this->add_improvement( 'robots_txt_empty', __( 'robots.txt file exists but is empty. Consider adding crawl directives.', 'seo-booster' ) );
        } else {
            $this->add_good( 'robots_txt_exists', __( 'robots.txt file is accessible and configured.', 'seo-booster' ) );
        }
        $llms_settings = Tools_Llms_Txt::get_settings();
        $ai_meta = array(
            'ai_readiness' => true,
        );
        if ( !empty( $llms_settings['enabled'] ) && !empty( $llms_settings['robots_llms'] ) && !Tools_Llms_Txt::physical_file_may_shadow_virtual() ) {
            if ( stripos( $robots_content, 'LLMS:' ) !== false ) {
                $this->add_good( 'robots_llms_present', __( 'robots.txt includes an LLMS discovery line for llms.txt.', 'seo-booster' ), $ai_meta );
            } else {
                $this->add_issue(
                    'robots_llms_missing',
                    __( 'robots.txt is missing the LLMS discovery line for dynamic llms.txt.', 'seo-booster' ),
                    'warning',
                    $ai_meta
                );
            }
        }
        if ( function_exists( 'Cleverplugins\\SEOBooster\\seobooster_fs' ) ) {
        }
    }

    /**
     * Check XML sitemap existence and accessibility.
     *
     * @since 7.0.0
     * @return void
     */
    private function check_sitemap() {
        // Check common sitemap locations
        $sitemap_urls = array(
            home_url( '/sitemap.xml' ),
            home_url( '/sitemaps.xml' ),
            home_url( '/sitemap_index.xml' ),
            home_url( '/wp-sitemap.xml' )
        );
        // Also check if Yoast or other SEO plugins generate sitemaps
        if ( function_exists( 'get_option' ) ) {
            $yoast_sitemap = get_option( 'wpseo_xml_sitemap_options' );
            if ( !empty( $yoast_sitemap ) ) {
                $sitemap_urls[] = home_url( '/sitemap_index.xml' );
            }
        }
        $sitemap_found = false;
        foreach ( $sitemap_urls as $sitemap_url ) {
            $response = wp_remote_head( $sitemap_url, array(
                'timeout'   => 5,
                'sslverify' => false,
            ) );
            if ( !is_wp_error( $response ) ) {
                $status_code = wp_remote_retrieve_response_code( $response );
                if ( $status_code === 200 ) {
                    $sitemap_found = true;
                    $this->add_good( 'sitemap_exists', sprintf( __( 'XML sitemap found at: %s', 'seo-booster' ), $sitemap_url ) );
                    break;
                }
            }
        }
        if ( !$sitemap_found ) {
            $this->add_issue( 'sitemap_missing', __( 'XML sitemap not found. Sitemaps help search engines discover and index your content.', 'seo-booster' ), 'warning' );
        }
    }

    /**
     * Check favicon presence.
     *
     * @since 7.0.0
     * @return void
     */
    private function check_favicon() {
        if ( empty( $this->homepage_content ) ) {
            return;
        }
        // Check for favicon in various formats and locations
        $favicon_patterns = array('/<link[^>]*rel=["\'](?:shortcut )?icon["\'][^>]*>/i', '/<link[^>]*rel=["\']apple-touch-icon["\'][^>]*>/i');
        $favicon_found = false;
        foreach ( $favicon_patterns as $pattern ) {
            if ( preg_match( $pattern, $this->homepage_content ) ) {
                $favicon_found = true;
                break;
            }
        }
        // Also check common favicon locations
        if ( !$favicon_found ) {
            $favicon_urls = array(home_url( '/favicon.ico' ), home_url( '/favicon.png' ), home_url( '/apple-touch-icon.png' ));
            foreach ( $favicon_urls as $favicon_url ) {
                $response = wp_remote_head( $favicon_url, array(
                    'timeout'   => 3,
                    'sslverify' => false,
                ) );
                if ( !is_wp_error( $response ) ) {
                    $status_code = wp_remote_retrieve_response_code( $response );
                    if ( $status_code === 200 ) {
                        $favicon_found = true;
                        break;
                    }
                }
            }
        }
        if ( $favicon_found ) {
            $this->add_good( 'favicon_exists', __( 'Favicon is present.', 'seo-booster' ) );
        } else {
            $this->add_improvement( 'favicon_missing', __( 'Favicon is missing. Add a favicon to improve brand recognition.', 'seo-booster' ) );
        }
    }

    /**
     * Check mobile viewport meta tag (sitewide check).
     *
     * @since 7.0.0
     * @return void
     */
    private function check_viewport() {
        if ( empty( $this->homepage_content ) ) {
            return;
        }
        // Check for viewport meta tag
        if ( preg_match( '/<meta[^>]*name=["\']viewport["\'][^>]*>/i', $this->homepage_content ) ) {
            $this->add_good( 'viewport_exists', __( 'Mobile viewport meta tag is present.', 'seo-booster' ) );
        } else {
            $this->add_issue( 'viewport_missing', __( 'Mobile viewport meta tag is missing. This is essential for mobile SEO.', 'seo-booster' ), 'error' );
        }
    }

    /**
     * Check language declaration (sitewide check).
     *
     * @since 7.0.0
     * @return void
     */
    private function check_language() {
        if ( empty( $this->homepage_content ) ) {
            return;
        }
        // Check for lang attribute on html tag
        if ( preg_match( '/<html[^>]*lang=["\']([^"\']+)["\']/i', $this->homepage_content, $matches ) ) {
            $lang = $matches[1];
            $this->add_good( 'language_declared', sprintf( __( 'Language is declared: %s', 'seo-booster' ), $lang ) );
        } else {
            $this->add_improvement( 'language_missing', __( 'HTML lang attribute is missing. Declaring the language helps search engines understand your content.', 'seo-booster' ) );
        }
    }

    /**
     * Check dynamic llms.txt availability (AI readiness).
     *
     * @since 7.2.3
     * @return void
     */
    private function check_llms_txt() {
        $ai_meta = array(
            'ai_readiness' => true,
        );
        $settings = Tools_Llms_Txt::get_settings();
        if ( empty( $settings['enabled'] ) ) {
            $this->add_issue(
                'llms_txt_disabled',
                __( 'Dynamic llms.txt is disabled. Enable it under SEO Booster → Tools to help AI crawlers discover your content.', 'seo-booster' ),
                'medium',
                $ai_meta
            );
            return;
        }
        if ( Tools_Llms_Txt::physical_file_may_shadow_virtual() ) {
            $this->add_improvement( 'llms_txt_physical_shadow', __( 'A physical llms.txt file may override the dynamic llms.txt route.', 'seo-booster' ), $ai_meta );
            return;
        }
        $response = wp_remote_get( home_url( '/llms.txt' ), array(
            'timeout'   => 5,
            'sslverify' => false,
        ) );
        if ( is_wp_error( $response ) ) {
            $this->add_issue(
                'llms_txt_unreachable',
                __( 'Dynamic llms.txt could not be fetched.', 'seo-booster' ),
                'warning',
                $ai_meta
            );
            return;
        }
        $status_code = wp_remote_retrieve_response_code( $response );
        $body = trim( (string) wp_remote_retrieve_body( $response ) );
        if ( $status_code !== 200 || $body === '' ) {
            $this->add_issue(
                'llms_txt_unreachable',
                sprintf( __( 'Dynamic llms.txt is not reachable (HTTP %d).', 'seo-booster' ), (int) $status_code ),
                'warning',
                $ai_meta
            );
            return;
        }
        $this->add_good( 'llms_txt_served', __( 'Dynamic llms.txt is enabled and reachable.', 'seo-booster' ), $ai_meta );
    }

    /**
     * Check Entity Map publish state (AI readiness / Pro marketing).
     *
     * @since 7.3.0
     * @return void
     */
    private function check_entity_map() {
        $ai_meta = array(
            'ai_readiness' => true,
        );
        $llms_settings = Tools_Llms_Txt::get_settings();
        $has_premium = false;
        if ( function_exists( 'Cleverplugins\\SEOBooster\\seobooster_fs' ) ) {
        }
        if ( !$has_premium ) {
            if ( !empty( $llms_settings['enabled'] ) ) {
                $this->add_improvement( 'entity_map_pro_available', __( 'Structured Entity Map is available with SEO Booster Pro. Go beyond llms.txt with machine-readable organization and content relationships.', 'seo-booster' ), $ai_meta );
            }
            return;
        }
        if ( !class_exists( '\\Cleverplugins\\SEOBooster\\Tools\\Tools_Entity_Map' ) ) {
            return;
        }
        $entity_class = '\\Cleverplugins\\SEOBooster\\Tools\\Tools_Entity_Map';
        $settings = $entity_class::get_settings();
        if ( $entity_class::physical_file_may_shadow_virtual() ) {
            $this->add_improvement( 'entity_map_physical_shadow', __( 'A physical entitymap.json or entitymap.html file may override dynamic Entity Map endpoints.', 'seo-booster' ), $ai_meta );
            return;
        }
        if ( $entity_class::is_served() ) {
            $this->add_good( 'entity_map_served', __( 'Entity Map is enabled and published.', 'seo-booster' ), $ai_meta );
            return;
        }
        if ( !empty( $llms_settings['enabled'] ) && empty( $settings['enabled'] ) ) {
            $this->add_issue(
                'entity_map_disabled',
                __( 'llms.txt is enabled but Entity Map is not published. Enable it under SEO Booster → Tools → Entity Map.', 'seo-booster' ),
                'warning',
                $ai_meta
            );
        }
    }

    /**
     * Check AI bot tracking setting (AI readiness).
     *
     * @since 7.2.3
     * @return void
     */
    private function check_ai_bot_tracking() {
        $ai_meta = array(
            'ai_readiness' => true,
        );
        if ( !AI_Bot_Tracker::is_tracking_enabled() ) {
            $this->add_issue(
                'ai_bot_tracking_disabled',
                __( 'AI bot tracking is disabled. Enable it under SEO Booster → Settings to monitor AI crawler visits.', 'seo-booster' ),
                'warning',
                $ai_meta
            );
            return;
        }
        $this->add_good( 'ai_bot_tracking_enabled', __( 'AI bot tracking is enabled.', 'seo-booster' ), $ai_meta );
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
    private function add_issue(
        $key,
        $message,
        $severity = 'warning',
        $extra_data = null
    ) {
        $issue = array(
            'key'      => $key,
            'message'  => $message,
            'severity' => $severity,
        );
        if ( $extra_data !== null ) {
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
     * @param array|null $extra_data Additional data.
     * @return void
     */
    private function add_improvement( $key, $message, $extra_data = null ) {
        $item = array(
            'key'     => $key,
            'message' => $message,
        );
        if ( $extra_data !== null ) {
            $item['extra_data'] = $extra_data;
        }
        $this->results['improvements'][] = $item;
    }

    /**
     * Add a good practice to the results.
     *
     * @since 7.0.0
     * @param string $key Good practice key.
     * @param string $message Good practice message.
     * @param array|null $extra_data Additional data.
     * @return void
     */
    private function add_good( $key, $message, $extra_data = null ) {
        $item = array(
            'key'     => $key,
            'message' => $message,
        );
        if ( $extra_data !== null ) {
            $item['extra_data'] = $extra_data;
        }
        $this->results['good'][] = $item;
    }

    /**
     * Calculate overall SEO score.
     *
     * @since 7.0.0
     * @return void
     */
    private function calculate_score() {
        $error_count = 0;
        $warning_count = 0;
        $issues = $this->results['issues'] ?? array();
        $good = $this->results['good'] ?? array();
        $improvements = $this->results['improvements'] ?? array();
        foreach ( $issues as $issue ) {
            if ( $issue['severity'] === 'error' ) {
                ++$error_count;
            } else {
                ++$warning_count;
            }
        }
        $good_count = count( $good );
        $improvement_count = count( $improvements );
        // Calculate score: Start with 100, subtract points for issues
        // Errors: -15 points each, Warnings: -8 points each
        // Improvements: -3 points each
        // Good practices: +2 points each
        $score = 100 - $error_count * 15 - $warning_count * 8 - $improvement_count * 3 + $good_count * 2;
        $this->results['score'] = max( 0, min( 100, $score ) );
    }

}
