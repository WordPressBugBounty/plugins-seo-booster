<?php

namespace Cleverplugins\SEOBooster\Tools;

use Cleverplugins\SEOBooster\AI_Bot_Tracker;
use Cleverplugins\SEOBooster\LLM_Helper;
use Cleverplugins\SEOBooster\SEO_Plugin_Registry;
use Cleverplugins\SEOBooster\Utils;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use function Cleverplugins\SEOBooster\seobooster_fs;
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * llms.txt generator — virtual endpoint (WordPress rewrite), not a physical file.
 *
 * @package Cleverplugins\SEOBooster\Tools
 */
class Tools_Llms_Txt {
    const OPTION_KEY = 'sb_tools_llms_txt_settings';

    const QUERY_VAR = 'sb_llms_txt';

    const CACHE_KEY_PREFIX = 'sb_tools_llms_txt_';

    const DEFAULT_CACHE_TTL = 43200;

    /**
     * Initialize hooks.
     *
     * @return void
     */
    public static function init() {
        add_action( 'init', array(__CLASS__, 'register_rewrite') );
        add_action( 'parse_request', array(__CLASS__, 'parse_request_serve'), 0 );
        add_action( 'template_redirect', array(__CLASS__, 'maybe_serve_file') );
        add_filter(
            'robots_txt',
            array(__CLASS__, 'filter_robots_txt'),
            10,
            2
        );
        add_action( 'wp_head', array(__CLASS__, 'output_head_links'), 1 );
        add_action( 'send_headers', array(__CLASS__, 'output_http_link_headers') );
        add_action( 'wp_ajax_sb_tools_save_llms_settings', array(__CLASS__, 'ajax_save_settings') );
        add_action( 'wp_ajax_sb_tools_preview_llms', array(__CLASS__, 'ajax_preview') );
        add_action( 'wp_ajax_sb_tools_download_llms', array(__CLASS__, 'ajax_download') );
        add_action( 'wp_ajax_sb_tools_llms_ai_suggest', array(__CLASS__, 'ajax_ai_suggest') );
        add_action( 'save_post', array(__CLASS__, 'maybe_invalidate_cache') );
    }

    /**
     * Post types excluded from llms.txt curation UI.
     *
     * @return string[]
     */
    public static function get_excluded_post_types() {
        $excluded = array(
            'attachment',
            'revision',
            'nav_menu_item',
            'custom_css',
            'customize_changeset',
            'oembed_cache',
            'user_request',
            'wp_block',
            'wp_navigation',
            'wp_global_styles',
            'elementor_library',
            'e-landing-page',
            'elementor_snippet',
            'elementor_font',
            'elementor_icons',
            'elementor_hfc',
            'jet-engine'
        );
        /**
         * Filter post types excluded from llms.txt content selection.
         *
         * @param string[] $excluded Post type slugs.
         */
        return apply_filters( 'sb_tools_llms_excluded_post_types', $excluded );
    }

    /**
     * Public post types suitable for llms.txt curation.
     *
     * @return \WP_Post_Type[]
     */
    public static function get_selectable_post_types() {
        $excluded = self::get_excluded_post_types();
        $types = get_post_types( array(
            'public' => true,
        ), 'objects' );
        foreach ( $excluded as $slug ) {
            unset($types[$slug]);
        }
        return $types;
    }

    /**
     * Register rewrite for /llms.txt.
     *
     * @return void
     */
    public static function register_rewrite() {
        add_rewrite_rule( '^llms\\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
        add_rewrite_tag( '%' . self::QUERY_VAR . '%', '1' );
        $settings = self::get_settings();
        if ( !empty( $settings['enabled'] ) && !get_option( 'sb_tools_llms_rewrite_flushed' ) ) {
            flush_rewrite_rules( false );
            update_option( 'sb_tools_llms_rewrite_flushed', 1, false );
        }
    }

    /**
     * Default settings.
     *
     * @return array
     */
    public static function default_settings() {
        return array(
            'enabled'         => false,
            'intro'           => '',
            'post_types'      => array('post', 'page'),
            'max_links'       => 20,
            'robots_llms'     => true,
            'head_links'      => true,
            'http_headers'    => true,
            'cache_ttl'       => self::DEFAULT_CACHE_TTL,
            'directory_rules' => array(),
            'include_faq'     => false,
            'faq_max_items'   => 10,
            'pinned_post_ids' => array(),
        );
    }

    /**
     * @return array
     */
    public static function get_settings() {
        $saved = get_option( self::OPTION_KEY, array() );
        if ( !is_array( $saved ) ) {
            $saved = array();
        }
        $settings = wp_parse_args( $saved, self::default_settings() );
        $settings['directory_rules'] = Llms_Directory_Rules::sanitize_rules( $settings['directory_rules'] ?? array() );
        $settings['pinned_post_ids'] = self::parse_pinned_post_ids( $settings['pinned_post_ids'] ?? array() );
        $settings['cache_ttl'] = max( 60, min( 86400, (int) $settings['cache_ttl'] ) );
        $settings['faq_max_items'] = max( 1, min( 50, (int) $settings['faq_max_items'] ) );
        return $settings;
    }

    /**
     * Whether dynamic llms.txt discovery features should run.
     *
     * @return bool
     */
    public static function is_discovery_active() {
        $settings = self::get_settings();
        return !empty( $settings['enabled'] ) && !self::physical_file_may_shadow_virtual();
    }

    /**
     * Physical llms.txt on disk (if any).
     *
     * @return array{exists: bool, path: string, readable: bool, size: int, preview: string}
     */
    public static function get_physical_file_status() {
        $path = trailingslashit( ABSPATH ) . 'llms.txt';
        $exists = file_exists( $path ) && is_file( $path );
        $readable = $exists && is_readable( $path );
        $preview = '';
        if ( $readable ) {
            $raw = file_get_contents( $path );
            if ( is_string( $raw ) ) {
                $preview = wp_trim_words( $raw, 40, '…' );
            }
        }
        return array(
            'exists'   => $exists,
            'path'     => $path,
            'readable' => $readable,
            'size'     => ( $exists ? (int) filesize( $path ) : 0 ),
            'preview'  => $preview,
        );
    }

    /**
     * Whether a physical file may take precedence over virtual serving.
     *
     * @return bool
     */
    public static function physical_file_may_shadow_virtual() {
        $status = self::get_physical_file_status();
        return !empty( $status['exists'] );
    }

    /**
     * Render admin tab.
     *
     * @return void
     */
    public static function render_admin() {
        $settings = self::get_settings();
        $preview = self::build_file_content( $settings );
        $file_url = home_url( '/llms.txt' );
        $post_types = self::get_selectable_post_types();
        $physical_status = self::get_physical_file_status();
        $physical_shadows = self::physical_file_may_shadow_virtual();
        $directories = Llms_Directory_Rules::discover_directories( $settings );
        $bot_gaps = self::get_bot_crawl_gaps( 30, 20, $settings );
        $ai_available = Tools_Meta_Scanner::ai_is_available();
        $ai_message = Tools_Meta_Scanner::get_ai_unavailable_message();
        $show_markdown = false;
        if ( function_exists( 'Cleverplugins\\SEOBooster\\seobooster_fs' ) ) {
        }
        include SEOBOOSTER_PLUGINPATH . 'inc/Tools/views/llms-txt-tool.php';
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Admin hook.
     * @return void
     */
    public static function enqueue_scripts( $hook ) {
        wp_enqueue_script(
            'sb-tools-llms',
            SEOBOOSTER_PLUGINURL . 'js/sb-tools-llms.js',
            array('jquery'),
            filemtime( SEOBOOSTER_PLUGINPATH . 'js/sb-tools-llms.js' ),
            true
        );
        wp_localize_script( 'sb-tools-llms', 'sbToolsLlms', array(
            'ajax_url'               => admin_url( 'admin-ajax.php' ),
            'nonce'                  => wp_create_nonce( 'sb_tools_llms_nonce' ),
            'ai_available'           => Tools_Meta_Scanner::ai_is_available(),
            'ai_unavailable_message' => Tools_Meta_Scanner::get_ai_unavailable_message(),
            'strings'                => array(
                'saved'           => __( 'Settings saved.', 'seo-booster' ),
                'error'           => __( 'Could not save settings.', 'seo-booster' ),
                'ai_generating'   => __( 'Generating suggestions…', 'seo-booster' ),
                'ai_error'        => __( 'Could not generate AI suggestions.', 'seo-booster' ),
                'ai_applied'      => __( 'AI suggestions applied. Save settings to publish.', 'seo-booster' ),
                'pinned'          => __( 'Pinned to llms.txt.', 'seo-booster' ),
                'unpinned'        => __( 'Unpinned.', 'seo-booster' ),
                'pin'             => __( 'Pin', 'seo-booster' ),
                'unpin'           => __( 'Unpin', 'seo-booster' ),
                'suggested_intro' => __( 'Suggested intro', 'seo-booster' ),
                'suggested_pages' => __( 'Suggested pages', 'seo-booster' ),
                'apply_selected'  => __( 'Apply selected', 'seo-booster' ),
                'cache_cleared'   => __( 'Markdown cache cleared.', 'seo-booster' ),
            ),
        ) );
    }

    /**
     * @param mixed $raw Raw post types.
     * @return string[]
     */
    public static function parse_post_types( $raw ) {
        if ( !is_array( $raw ) ) {
            return array('post', 'page');
        }
        $allowed = array_keys( self::get_selectable_post_types() );
        $parsed = array_values( array_intersect( array_map( 'sanitize_key', $raw ), $allowed ) );
        return ( !empty( $parsed ) ? $parsed : array('post', 'page') );
    }

    /**
     * @param mixed $raw Raw pinned post IDs.
     * @return int[]
     */
    public static function parse_pinned_post_ids( $raw ) {
        if ( !is_array( $raw ) ) {
            return array();
        }
        $ids = array_map( 'absint', $raw );
        $ids = array_values( array_filter( array_unique( $ids ) ) );
        return $ids;
    }

    /**
     * Parse settings from POST payload.
     *
     * @return array
     */
    private static function parse_settings_from_request() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in Tools_Llms_Txt::ajax_save_settings() before this runs.
        $enabled = !empty( $_POST['enabled'] );
        $intro = ( isset( $_POST['intro'] ) ? sanitize_textarea_field( wp_unslash( $_POST['intro'] ) ) : '' );
        $max = ( isset( $_POST['max_links'] ) ? max( 1, min( 100, (int) $_POST['max_links'] ) ) : 20 );
        $post_types = self::parse_post_types( ( isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) ? wp_unslash( $_POST['post_types'] ) : array('post', 'page') ) );
        $directory_rules = array();
        if ( isset( $_POST['directory_rules'] ) && is_array( $_POST['directory_rules'] ) ) {
            $directory_rules = Llms_Directory_Rules::sanitize_rules( wp_unslash( $_POST['directory_rules'] ) );
        }
        $pinned_post_ids = self::parse_pinned_post_ids( ( isset( $_POST['pinned_post_ids'] ) && is_array( $_POST['pinned_post_ids'] ) ? wp_unslash( $_POST['pinned_post_ids'] ) : array() ) );
        return array(
            'enabled'         => $enabled,
            'intro'           => $intro,
            'post_types'      => $post_types,
            'max_links'       => $max,
            'robots_llms'     => !empty( $_POST['robots_llms'] ),
            'head_links'      => !empty( $_POST['head_links'] ),
            'http_headers'    => !empty( $_POST['http_headers'] ),
            'cache_ttl'       => ( isset( $_POST['cache_ttl'] ) ? max( 60, min( 86400, (int) $_POST['cache_ttl'] ) ) : self::DEFAULT_CACHE_TTL ),
            'directory_rules' => $directory_rules,
            'include_faq'     => !empty( $_POST['include_faq'] ),
            'faq_max_items'   => ( isset( $_POST['faq_max_items'] ) ? max( 1, min( 50, (int) $_POST['faq_max_items'] ) ) : 10 ),
            'pinned_post_ids' => $pinned_post_ids,
        );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    /**
     * AJAX: save settings.
     *
     * @return void
     */
    public static function ajax_save_settings() {
        check_ajax_referer( 'sb_tools_llms_nonce', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied', 'seo-booster' ),
            ) );
        }
        $settings = self::parse_settings_from_request();
        update_option( self::OPTION_KEY, $settings, false );
        self::invalidate_cache();
        delete_option( 'sb_tools_llms_rewrite_flushed' );
        flush_rewrite_rules( false );
        update_option( 'sb_tools_llms_rewrite_flushed', 1, false );
        if ( function_exists( 'Cleverplugins\\SEOBooster\\seobooster_fs' ) ) {
        }
        wp_send_json_success( array(
            'message'          => __( 'Settings saved.', 'seo-booster' ),
            'preview'          => self::build_file_content( $settings, true ),
            'physical_shadows' => self::physical_file_may_shadow_virtual(),
            'bot_gaps'         => self::format_bot_gaps_for_ui( self::get_bot_crawl_gaps( 30, 20, $settings ) ),
        ) );
    }

    /**
     * AJAX: preview content.
     *
     * @return void
     */
    public static function ajax_preview() {
        check_ajax_referer( 'sb_tools_llms_nonce', 'nonce' );
        if ( !current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied', 'seo-booster' ),
            ) );
        }
        wp_send_json_success( array(
            'preview' => self::build_file_content( self::get_settings(), true ),
        ) );
    }

    /**
     * AJAX: download generated content as a file (no front-end serving required).
     *
     * @return void
     */
    public static function ajax_download() {
        check_ajax_referer( 'sb_tools_llms_nonce', 'nonce' );
        if ( !current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Permission denied', 'seo-booster' ) );
        }
        $content = self::build_file_content( self::get_settings(), true );
        nocache_headers();
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="llms.txt"' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain download body.
        echo $content;
        exit;
    }

    /**
     * AJAX: AI intro and page suggestions.
     *
     * @return void
     */
    public static function ajax_ai_suggest() {
        check_ajax_referer( 'sb_tools_llms_nonce', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied', 'seo-booster' ),
            ) );
        }
        if ( !Tools_Meta_Scanner::ai_is_available() ) {
            wp_send_json_error( array(
                'message' => Tools_Meta_Scanner::get_ai_unavailable_message(),
            ) );
        }
        $settings = self::get_settings();
        try {
            $result = self::generate_ai_suggestions( $settings );
        } catch ( \Exception $e ) {
            Utils::log( 'llms.txt AI suggestions failed: ' . $e->getMessage(), 2 );
            wp_send_json_error( array(
                'message' => __( 'Something went wrong. Check the SEO Booster debug log for details.', 'seo-booster' ),
            ) );
            return;
        }
        wp_send_json_success( $result );
    }

    /**
     * Generate AI intro and page suggestions.
     *
     * @param array $settings Settings.
     * @return array{intro: string, suggestions: array<int, array{post_id: int, title: string, reason: string, score: float}>}
     * @throws \Exception When AI fails.
     */
    public static function generate_ai_suggestions( array $settings ) {
        if ( !function_exists( 'wp_ai_client_prompt' ) || !LLM_Helper::wp_ai_is_available() ) {
            throw new \Exception(esc_html( Tools_Meta_Scanner::get_ai_unavailable_message() ));
        }
        $candidates = self::get_post_curation_scores( $settings, 30 );
        $gaps = self::get_bot_crawl_gaps( 30, 10, $settings );
        $site_name = get_bloginfo( 'name' );
        $tagline = get_bloginfo( 'description' );
        $intro = trim( (string) ($settings['intro'] ?? '') );
        $candidate_lines = array();
        foreach ( array_slice(
            $candidates,
            0,
            30,
            true
        ) as $post_id => $row ) {
            $candidate_lines[] = sprintf(
                '- ID %d: %s (score %.2f, GSC clicks %d, bot visits %d)',
                $post_id,
                $row['title'],
                $row['score'],
                $row['gsc_clicks'],
                $row['bot_visits']
            );
        }
        $gap_lines = array();
        foreach ( $gaps as $gap ) {
            $gap_lines[] = sprintf(
                '- ID %d: %s (bot visits %d, not currently in llms.txt)',
                $gap['post_id'],
                $gap['title'],
                $gap['bot_visits']
            );
        }
        $prompt = sprintf(
            "You are an SEO and AI discovery expert. Suggest an optimized llms.txt introduction and a ranked list of pages for AI crawlers.\n\nSite: %s\nTagline: %s\nCurrent intro: %s\nMax links allowed: %d\n\nTop candidate pages (GSC + bot traffic):\n%s\n\nBot crawl gaps (high bot traffic, missing from current llms.txt selection):\n%s\n\nReturn ONLY valid JSON:\n{\n  \"intro\": \"1-3 sentence site intro for llms.txt blockquote\",\n  \"suggestions\": [\n    {\"post_id\": 123, \"reason\": \"short reason\"}\n  ]\n}\n\nInclude up to %d suggestions ordered by importance. Use only post IDs from the lists above.",
            $site_name,
            $tagline,
            ( $intro !== '' ? $intro : '(empty)' ),
            (int) ($settings['max_links'] ?? 20),
            implode( "\n", $candidate_lines ),
            ( !empty( $gap_lines ) ? implode( "\n", $gap_lines ) : '(none)' ),
            min( 20, (int) ($settings['max_links'] ?? 20) )
        );
        $builder = LLM_Helper::ai_prompt( $prompt )->using_system_instruction( __( 'Return only valid JSON with intro and suggestions. No markdown.', 'seo-booster' ) );
        if ( class_exists( RequestOptions::class ) ) {
            $builder = $builder->using_request_options( RequestOptions::fromArray( array(
                RequestOptions::KEY_TIMEOUT => 60.0,
            ) ) );
        }
        $response = LLM_Helper::generate_ai_text( $builder, 'llms-txt' );
        if ( is_wp_error( $response ) ) {
            throw new \Exception(esc_html( $response->get_error_message() ));
        }
        $response = preg_replace( '#^```(?:json)?\\s*|\\s*```$#', '', trim( (string) $response ) );
        $data = json_decode( $response, true );
        if ( json_last_error() !== JSON_ERROR_NONE || !is_array( $data ) ) {
            throw new \Exception(esc_html__( 'Unable to parse AI response.', 'seo-booster' ));
        }
        $intro_out = trim( (string) ($data['intro'] ?? '') );
        $suggestions_out = array();
        $allowed_ids = array_keys( $candidates );
        foreach ( $gaps as $gap ) {
            $allowed_ids[] = (int) $gap['post_id'];
        }
        $allowed_ids = array_values( array_unique( array_filter( array_map( 'absint', $allowed_ids ) ) ) );
        if ( !empty( $data['suggestions'] ) && is_array( $data['suggestions'] ) ) {
            foreach ( $data['suggestions'] as $item ) {
                $post_id = absint( $item['post_id'] ?? 0 );
                if ( $post_id <= 0 || !in_array( $post_id, $allowed_ids, true ) ) {
                    continue;
                }
                $post = get_post( $post_id );
                if ( !$post ) {
                    continue;
                }
                $suggestions_out[] = array(
                    'post_id' => $post_id,
                    'title'   => get_the_title( $post ),
                    'reason'  => sanitize_text_field( (string) ($item['reason'] ?? '') ),
                    'score'   => $candidates[$post_id]['score'] ?? 0,
                );
            }
        }
        return array(
            'intro'       => $intro_out,
            'suggestions' => $suggestions_out,
        );
    }

    /**
     * Invalidate cached llms.txt when content changes.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public static function maybe_invalidate_cache( $post_id ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        $settings = self::get_settings();
        if ( empty( $settings['enabled'] ) ) {
            return;
        }
        $post_type = get_post_type( $post_id );
        if ( $post_type && in_array( $post_type, (array) $settings['post_types'], true ) ) {
            self::invalidate_cache();
        }
    }

    /**
     * @return void
     */
    public static function invalidate_cache() {
        delete_transient( self::get_cache_key( self::get_settings() ) );
    }

    /**
     * Build transient cache key for current settings.
     *
     * @param array $settings Settings.
     * @return string
     */
    public static function get_cache_key( array $settings ) {
        $parts = array(
            get_locale(),
            (int) ($settings['max_links'] ?? 20),
            implode( ',', (array) ($settings['post_types'] ?? array()) ),
            md5( wp_json_encode( $settings['directory_rules'] ?? array() ) ),
            ( !empty( $settings['include_faq'] ) ? '1' : '0' ),
            (int) ($settings['faq_max_items'] ?? 10),
            implode( ',', self::parse_pinned_post_ids( $settings['pinned_post_ids'] ?? array() ) ),
            md5( trim( (string) ($settings['intro'] ?? '') ) )
        );
        return self::CACHE_KEY_PREFIX . md5( implode( '|', $parts ) );
    }

    /**
     * Parse-request fallback for /llms.txt.
     *
     * @param \WP $wp WordPress environment.
     * @return void
     */
    public static function parse_request_serve( $wp ) {
        unset($wp);
        if ( !self::is_llms_request() ) {
            return;
        }
        self::serve_response();
    }

    /**
     * Serve llms.txt when enabled (virtual file — no disk write).
     *
     * @return void
     */
    public static function maybe_serve_file() {
        if ( !self::is_llms_request() ) {
            return;
        }
        self::serve_response();
    }

    /**
     * Whether the current request targets /llms.txt.
     *
     * @return bool
     */
    private static function is_llms_request() {
        $settings = self::get_settings();
        if ( empty( $settings['enabled'] ) || self::physical_file_may_shadow_virtual() ) {
            return false;
        }
        if ( (int) get_query_var( self::QUERY_VAR ) === 1 ) {
            return true;
        }
        if ( !isset( $_SERVER['REQUEST_URI'] ) ) {
            return false;
        }
        $path = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
        if ( !is_string( $path ) ) {
            return false;
        }
        $path = untrailingslashit( $path );
        if ( $path === 'llms.txt' ) {
            $path = '/llms.txt';
        }
        $home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
        $home_path = ( is_string( $home_path ) ? untrailingslashit( $home_path ) : '' );
        if ( $home_path !== '' && strpos( $path, $home_path . '/llms.txt' ) === 0 ) {
            return true;
        }
        return $path === '/llms.txt';
    }

    /**
     * Output llms.txt response and exit.
     *
     * @return void
     */
    private static function serve_response() {
        $settings = self::get_settings();
        $content = self::get_cached_content( $settings );
        status_header( 200 );
        nocache_headers();
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Robots-Tag: noindex, follow' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain llms.txt body.
        echo $content;
        exit;
    }

    /**
     * Append LLMS discovery line to robots.txt.
     *
     * @param string $output Robots.txt output.
     * @param bool   $public Whether site is public.
     * @return string
     */
    public static function filter_robots_txt( $output, $public ) {
        if ( !$public || !self::is_discovery_active() ) {
            return $output;
        }
        $settings = self::get_settings();
        if ( empty( $settings['robots_llms'] ) ) {
            return $output;
        }
        $llms_url = home_url( '/llms.txt' );
        if ( stripos( $output, 'LLMS:' ) !== false ) {
            return $output;
        }
        return rtrim( (string) $output ) . "\n\nLLMS: {$llms_url}\n";
    }

    /**
     * Output discovery link tags on the front page.
     *
     * @return void
     */
    public static function output_head_links() {
        if ( !self::is_discovery_active() || is_admin() ) {
            return;
        }
        $settings = self::get_settings();
        if ( empty( $settings['head_links'] ) || !is_front_page() && !is_home() ) {
            return;
        }
        $url = esc_url( home_url( '/llms.txt' ) );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $url escaped with esc_url() above.
        echo "\n" . '<link rel="alternate" type="text/plain" href="' . $url . '">' . "\n";
    }

    /**
     * Output HTTP Link discovery headers on the front page.
     *
     * @return void
     */
    public static function output_http_link_headers() {
        if ( headers_sent() || !self::is_discovery_active() || is_admin() ) {
            return;
        }
        $settings = self::get_settings();
        if ( empty( $settings['http_headers'] ) || !is_front_page() && !is_home() ) {
            return;
        }
        header( 'Link: <' . esc_url_raw( home_url( '/llms.txt' ) ) . '>; rel="alternate"; type="text/plain"', false );
    }

    /**
     * @param array $settings Settings.
     * @return string
     */
    private static function get_cached_content( array $settings ) {
        $key = self::get_cache_key( $settings );
        $cached = get_transient( $key );
        if ( is_string( $cached ) && $cached !== '' ) {
            return $cached;
        }
        $content = self::build_file_content( $settings, true );
        $ttl = max( 60, min( 86400, (int) ($settings['cache_ttl'] ?? self::DEFAULT_CACHE_TTL) ) );
        set_transient( $key, $content, $ttl );
        return $content;
    }

    /**
     * Resolve post description for llms.txt line.
     *
     * @param \WP_Post $post Post.
     * @return string
     */
    public static function get_post_description( $post ) {
        $seo = SEO_Plugin_Registry::read_post_seo( $post->ID );
        $desc = trim( (string) ($seo['description'] ?? '') );
        if ( $desc === '' && !empty( $post->post_excerpt ) ) {
            $desc = trim( wp_strip_all_tags( $post->post_excerpt ) );
        }
        if ( $desc === '' && !empty( $post->post_content ) ) {
            $desc = wp_trim_words( wp_strip_all_tags( $post->post_content ), 20, '…' );
        }
        if ( $desc === '' ) {
            $desc = get_the_title( $post );
        }
        return $desc;
    }

    /**
     * Build llms.txt markdown content.
     *
     * @param array $settings Settings.
     * @param bool  $fresh    Skip cache read when building.
     * @return string
     */
    public static function build_file_content( array $settings, $fresh = false ) {
        unset($fresh);
        $site_name = get_bloginfo( 'name' );
        $site_url = home_url( '/' );
        $intro = trim( (string) ($settings['intro'] ?? '') );
        if ( $intro === '' ) {
            $intro = sprintf( 
                /* translators: %s: site name */
                __( 'Curated content from %s for AI assistants and answer engines.', 'seo-booster' ),
                $site_name
             );
        }
        $lines = array();
        $lines[] = '# ' . $site_name;
        $lines[] = '';
        $lines[] = '> ' . $intro;
        $lines[] = '';
        $lines[] = '## ' . __( 'Important pages', 'seo-booster' );
        $lines[] = '';
        $posts = self::get_curated_posts( $settings );
        foreach ( $posts as $post ) {
            $url = get_permalink( $post );
            $title = get_the_title( $post );
            $desc = self::get_post_description( $post );
            $lines[] = '- [' . $title . '](' . $url . '): ' . $desc;
        }
        if ( !empty( $settings['include_faq'] ) ) {
            $faqs = Llms_Faq_Extractor::collect_from_posts( $posts, (int) ($settings['faq_max_items'] ?? 10) );
            if ( !empty( $faqs ) ) {
                $lines[] = '';
                $lines[] = '## ' . __( 'Frequently asked questions', 'seo-booster' );
                $lines[] = '';
                foreach ( $faqs as $faq ) {
                    $lines[] = '### ' . $faq['question'];
                    $lines[] = '';
                    $lines[] = $faq['answer'];
                    $lines[] = '';
                    if ( !empty( $faq['source_url'] ) ) {
                        $lines[] = sprintf( 
                            /* translators: 1: page title, 2: URL */
                            __( 'Source: [%1$s](%2$s)', 'seo-booster' ),
                            $faq['source_title'],
                            $faq['source_url']
                         );
                        $lines[] = '';
                    }
                }
            }
        }
        $lines[] = '';
        $lines[] = '## ' . __( 'Site', 'seo-booster' );
        $lines[] = '';
        $lines[] = '- [' . __( 'Home', 'seo-booster' ) . '](' . $site_url . ')';
        $entity_section = '';
        if ( function_exists( 'Cleverplugins\\SEOBooster\\seobooster_fs' ) ) {
        }
        if ( $entity_section !== '' ) {
            $lines[] = rtrim( $entity_section );
        }
        $full_section = '';
        if ( function_exists( 'Cleverplugins\\SEOBooster\\seobooster_fs' ) ) {
        }
        if ( $full_section !== '' ) {
            $lines[] = rtrim( $full_section );
        }
        return implode( "\n", $lines ) . "\n";
    }

    /**
     * Curate posts for llms.txt (GSC clicks when available, else recent).
     *
     * @param array $settings Settings.
     * @return \WP_Post[]
     */
    public static function get_curated_posts( array $settings ) {
        $post_types = ( isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ? self::parse_post_types( $settings['post_types'] ) : array('post', 'page') );
        $max_links = ( isset( $settings['max_links'] ) ? (int) $settings['max_links'] : 20 );
        // llms.txt UI caps at 100; Pro llms-full.txt may request up to 250.
        $max_links = max( 1, min( 250, $max_links ) );
        $rules = Llms_Directory_Rules::sanitize_rules( $settings['directory_rules'] ?? array() );
        $pinned_ids = self::parse_pinned_post_ids( $settings['pinned_post_ids'] ?? array() );
        $pinned = array();
        foreach ( $pinned_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( $post && $post->post_status === 'publish' && in_array( $post->post_type, $post_types, true ) ) {
                if ( Llms_Directory_Rules::is_post_allowed( $post, $rules ) ) {
                    $pinned[$post_id] = $post;
                }
            }
        }
        $scored = self::get_post_curation_scores( $settings, max( $max_links * 3, 60 ) );
        $posts = array();
        foreach ( $pinned as $post ) {
            $posts[$post->ID] = $post;
        }
        foreach ( $scored as $post_id => $row ) {
            if ( count( $posts ) >= $max_links ) {
                break;
            }
            if ( isset( $posts[$post_id] ) ) {
                continue;
            }
            $post = get_post( $post_id );
            if ( $post && $post->post_status === 'publish' && in_array( $post->post_type, $post_types, true ) ) {
                if ( Llms_Directory_Rules::is_post_allowed( $post, $rules ) ) {
                    $posts[$post_id] = $post;
                }
            }
        }
        if ( empty( $posts ) ) {
            $query = new \WP_Query(array(
                'post_type'      => $post_types,
                'post_status'    => 'publish',
                'posts_per_page' => $max_links,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            ));
            $posts = Llms_Directory_Rules::filter_posts( $query->posts, $rules );
            return array_slice( $posts, 0, $max_links );
        }
        return array_slice( array_values( $posts ), 0, $max_links );
    }

    /**
     * GSC click totals keyed by post ID.
     *
     * @param array $settings Settings.
     * @return array<int, int>
     */
    public static function get_gsc_clicks_by_post( array $settings ) {
        global $wpdb;
        $post_types = self::parse_post_types( $settings['post_types'] ?? array('post', 'page') );
        $keywords_table = $wpdb->prefix . 'sb2_query_keywords';
        $history_table = $wpdb->prefix . 'sb2_query_keywords_history';
        $keywords_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $keywords_table ) ) === $keywords_table;
        $history_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $history_table ) ) === $history_table;
        if ( !$keywords_exists || !$history_exists ) {
            return array();
        }
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
        $urls_clicks = $wpdb->get_results( "SELECT k.page, SUM(h.clicks) AS total_clicks\n\t\t\tFROM {$keywords_table} AS k\n\t\t\tINNER JOIN {$history_table} AS h ON k.id = h.query_keywords_id\n\t\t\tGROUP BY k.page\n\t\t\tORDER BY total_clicks DESC\n\t\t\tLIMIT 500", ARRAY_A );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $clicks = array();
        if ( empty( $urls_clicks ) ) {
            return $clicks;
        }
        foreach ( $urls_clicks as $row ) {
            $post_id = url_to_postid( $row['page'] );
            if ( $post_id <= 0 ) {
                continue;
            }
            $post = get_post( $post_id );
            if ( !$post || $post->post_status !== 'publish' || !in_array( $post->post_type, $post_types, true ) ) {
                continue;
            }
            $clicks[$post_id] = (int) $row['total_clicks'];
        }
        return $clicks;
    }

    /**
     * Bot visit totals keyed by post ID.
     *
     * @param int $days Days window.
     * @return array<int, int>
     */
    public static function get_bot_visits_by_post( $days = 30 ) {
        $items = AI_Bot_Tracker::get_top_content_pages( $days, 200 );
        $visits = array();
        foreach ( $items as $row ) {
            if ( ($row['object_type'] ?? '') !== 'post' ) {
                continue;
            }
            $post_id = absint( $row['object_id'] ?? 0 );
            if ( $post_id <= 0 ) {
                continue;
            }
            $visits[$post_id] = (int) ($row['visits'] ?? 0);
        }
        return $visits;
    }

    /**
     * Merge GSC and bot signals into curation scores.
     *
     * @param array $settings Settings.
     * @param int   $limit    Max rows.
     * @return array<int, array{post_id: int, title: string, score: float, gsc_clicks: int, bot_visits: int}>
     */
    public static function get_post_curation_scores( array $settings, $limit = 30 ) {
        $gsc_clicks = self::get_gsc_clicks_by_post( $settings );
        $bot_visits = self::get_bot_visits_by_post( 30 );
        $post_ids = array_unique( array_merge( array_keys( $gsc_clicks ), array_keys( $bot_visits ) ) );
        $weights = apply_filters( 'sb_tools_llms_curation_weights', array(
            'gsc'  => 0.6,
            'bots' => 0.4,
        ) );
        $gsc_weight = (float) ($weights['gsc'] ?? 0.6);
        $bot_weight = (float) ($weights['bots'] ?? 0.4);
        $max_gsc = ( !empty( $gsc_clicks ) ? max( $gsc_clicks ) : 0 );
        $max_bots = ( !empty( $bot_visits ) ? max( $bot_visits ) : 0 );
        $rules = Llms_Directory_Rules::sanitize_rules( $settings['directory_rules'] ?? array() );
        $scores = array();
        foreach ( $post_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( !$post || $post->post_status !== 'publish' ) {
                continue;
            }
            if ( !Llms_Directory_Rules::is_post_allowed( $post, $rules ) ) {
                continue;
            }
            $gsc = (int) ($gsc_clicks[$post_id] ?? 0);
            $bot = (int) ($bot_visits[$post_id] ?? 0);
            $gsc_norm = ( $max_gsc > 0 ? $gsc / $max_gsc : 0 );
            $bot_norm = ( $max_bots > 0 ? $bot / $max_bots : 0 );
            $score = $gsc_norm * $gsc_weight + $bot_norm * $bot_weight;
            if ( $score <= 0 && $gsc === 0 && $bot === 0 ) {
                continue;
            }
            $scores[$post_id] = array(
                'post_id'    => $post_id,
                'title'      => get_the_title( $post ),
                'score'      => round( $score, 4 ),
                'gsc_clicks' => $gsc,
                'bot_visits' => $bot,
            );
        }
        uasort( $scores, static function ( $a, $b ) {
            if ( $a['score'] === $b['score'] ) {
                return $b['gsc_clicks'] <=> $a['gsc_clicks'];
            }
            return $b['score'] <=> $a['score'];
        } );
        return array_slice(
            $scores,
            0,
            max( 1, (int) $limit ),
            true
        );
    }

    /**
     * Posts with high bot traffic not in current llms.txt selection.
     *
     * @param int   $days     Days window.
     * @param int   $limit    Max rows.
     * @param array $settings Settings.
     * @return array<int, array{post_id: int, title: string, bot_visits: int, gsc_clicks: int, url: string}>
     */
    public static function get_bot_crawl_gaps( $days = 30, $limit = 20, array $settings = array() ) {
        if ( empty( $settings ) ) {
            $settings = self::get_settings();
        }
        $curated_ids = array();
        foreach ( self::get_curated_posts( $settings ) as $post ) {
            $curated_ids[$post->ID] = true;
        }
        $gsc_clicks = self::get_gsc_clicks_by_post( $settings );
        $gaps = array();
        $items = AI_Bot_Tracker::get_top_content_pages( $days, 100 );
        foreach ( $items as $row ) {
            if ( ($row['object_type'] ?? '') !== 'post' ) {
                continue;
            }
            $post_id = absint( $row['object_id'] ?? 0 );
            if ( $post_id <= 0 || isset( $curated_ids[$post_id] ) ) {
                continue;
            }
            $post = get_post( $post_id );
            if ( !$post || $post->post_status !== 'publish' ) {
                continue;
            }
            $rules = Llms_Directory_Rules::sanitize_rules( $settings['directory_rules'] ?? array() );
            if ( !Llms_Directory_Rules::is_post_allowed( $post, $rules ) ) {
                continue;
            }
            $gaps[] = array(
                'post_id'    => $post_id,
                'title'      => get_the_title( $post ),
                'bot_visits' => (int) ($row['visits'] ?? 0),
                'gsc_clicks' => (int) ($gsc_clicks[$post_id] ?? 0),
                'url'        => get_permalink( $post ),
            );
            if ( count( $gaps ) >= $limit ) {
                break;
            }
        }
        return $gaps;
    }

    /**
     * Format bot gaps for admin JSON responses.
     *
     * @param array $gaps Gap rows.
     * @return array<int, array<string, mixed>>
     */
    public static function format_bot_gaps_for_ui( array $gaps ) {
        $out = array();
        foreach ( $gaps as $gap ) {
            $out[] = array(
                'post_id'    => (int) $gap['post_id'],
                'title'      => (string) $gap['title'],
                'bot_visits' => (int) $gap['bot_visits'],
                'gsc_clicks' => (int) $gap['gsc_clicks'],
                'url'        => (string) $gap['url'],
            );
        }
        return $out;
    }

}
