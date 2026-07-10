<?php

namespace Cleverplugins\SEOBooster\Tools;

use Cleverplugins\SEOBooster\SEO_Plugin_Registry;
use Cleverplugins\SEOBooster\Utils;
use function Cleverplugins\SEOBooster\seobooster_fs;
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * SEO Booster Tools admin page (tabbed hub).
 *
 * @package Cleverplugins\SEOBooster\Tools
 */
class Tools_Page {
    /**
     * Initialize hooks.
     *
     * @return void
     */
    public static function init() {
        Tools_Image_Scanner::init();
        Tools_Image_Batch::init();
        Tools_Meta_Scanner::init();
        Tools_Meta_Batch::init();
        Tools_Llms_Txt::init();
        if ( function_exists( 'Cleverplugins\\SEOBooster\\seobooster_fs' ) ) {
        }
        add_action( 'admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts') );
    }

    /**
     * Registered tools for tabs.
     *
     * @return array<string, array>
     */
    public static function get_tools() {
        $tools = array(
            'overview' => array(
                'id'      => 'overview',
                'label'   => __( 'Overview', 'seo-booster' ),
                'premium' => false,
                'render'  => array(__CLASS__, 'render_overview'),
                'enqueue' => null,
            ),
            'meta'     => array(
                'id'      => 'meta',
                'label'   => __( 'Bulk meta', 'seo-booster' ),
                'premium' => false,
                'render'  => array(__CLASS__, 'render_meta_tool'),
                'enqueue' => array(__CLASS__, 'enqueue_meta_scripts'),
            ),
            'image'    => array(
                'id'      => 'image',
                'label'   => __( 'Image metadata', 'seo-booster' ),
                'premium' => false,
                'render'  => array(__CLASS__, 'render_image_tool'),
                'enqueue' => array(__CLASS__, 'enqueue_image_scripts'),
            ),
            'llms'     => array(
                'id'      => 'llms',
                'label'   => __( 'llms.txt', 'seo-booster' ),
                'premium' => false,
                'render'  => array(Tools_Llms_Txt::class, 'render_admin'),
                'enqueue' => array(Tools_Llms_Txt::class, 'enqueue_scripts'),
            ),
        );
        $tools = array_merge( $tools, self::get_premium_tool_tabs() );
        /**
         * Filter registered Tools page tabs.
         *
         * @param array $tools Tool registry.
         */
        return apply_filters( 'sb_tools_registry', $tools );
    }

    /**
     * Premium tool tab definitions (visible to all installs; content gated at render).
     *
     * @return array<string, array>
     */
    private static function get_premium_tool_tabs() {
        return array(
            'needs-analysis'         => array(
                'id'      => 'needs-analysis',
                'label'   => __( 'Needs analysis', 'seo-booster' ),
                'premium' => true,
                'render'  => array(__CLASS__, 'render_needs_analysis_tab'),
                'enqueue' => array(__CLASS__, 'enqueue_needs_analysis_scripts'),
            ),
            'gsc-opportunities'      => array(
                'id'      => 'gsc-opportunities',
                'label'   => __( 'GSC opportunities', 'seo-booster' ),
                'premium' => true,
                'render'  => array(__CLASS__, 'render_gsc_opportunities_tab'),
                'enqueue' => array(__CLASS__, 'enqueue_gsc_opportunities_scripts'),
            ),
            'focus-keyword'          => array(
                'id'      => 'focus-keyword',
                'label'   => __( 'Focus keywords', 'seo-booster' ),
                'premium' => true,
                'render'  => array(__CLASS__, 'render_focus_keyword_tab'),
                'enqueue' => array(__CLASS__, 'enqueue_focus_keyword_scripts'),
            ),
            'autolink-opportunities' => array(
                'id'      => 'autolink-opportunities',
                'label'   => __( 'Autolink opportunities', 'seo-booster' ),
                'premium' => true,
                'render'  => array(__CLASS__, 'render_autolink_opportunities_tab'),
                'enqueue' => array(__CLASS__, 'enqueue_autolink_opportunities_scripts'),
            ),
            'entity-map'             => array(
                'id'      => 'entity-map',
                'label'   => __( 'Entity Map', 'seo-booster' ),
                'premium' => true,
                'render'  => array(__CLASS__, 'render_entity_map_tab'),
                'enqueue' => array(__CLASS__, 'enqueue_entity_map_scripts'),
            ),
        );
    }

    /**
     * Whether the current install can use premium Tools tabs.
     *
     * @return bool
     */
    public static function user_has_premium_tools() {
        if ( !function_exists( 'Cleverplugins\\SEOBooster\\seobooster_fs' ) ) {
            return false;
        }
        return seobooster_fs()->can_use_premium_code__premium_only();
    }

    /**
     * Freemius upgrade URL for premium teasers.
     *
     * @return string
     */
    private static function get_upgrade_url() {
        $upgrade_url = 'https://seoboosterpro.com';
        if ( function_exists( 'Cleverplugins\\SEOBooster\\seobooster_fs' ) ) {
            $fs = seobooster_fs();
            if ( is_object( $fs ) && method_exists( $fs, 'get_upgrade_url' ) ) {
                $upgrade_url = $fs->get_upgrade_url();
            }
        }
        return $upgrade_url;
    }

    /**
     * Render locked premium teaser for free installs.
     *
     * @param string $title       Tool title.
     * @param string $description Short description.
     * @param string $variant     Optional teaser variant (e.g. entity-map).
     * @return void
     */
    private static function render_premium_teaser( $title, $description, $variant = '' ) {
        $teaser_title = $title;
        $teaser_description = $description;
        $teaser_variant = $variant;
        $upgrade_url = self::get_upgrade_url();
        include SEOBOOSTER_PLUGINPATH . 'inc/Tools/views/premium-tool-teaser.php';
    }

    /**
     * Render a premium tool or its teaser.
     *
     * @param string   $class       Premium tool class name.
     * @param string   $title       Teaser title.
     * @param string   $description Teaser description.
     * @param callable $render      Licensed render callback.
     * @param string   $variant     Optional locked-teaser variant.
     * @return void
     */
    private static function render_premium_tool_tab(
        $class,
        $title,
        $description,
        $render,
        $variant = ''
    ) {
        $show_premium_tool = false;
        if ( self::user_has_premium_tools() ) {
            if ( class_exists( $class ) ) {
                $show_premium_tool = true;
            }
        }
        if ( $show_premium_tool ) {
            call_user_func( $render );
            return;
        }
        self::render_premium_teaser( $title, $description, $variant );
    }

    /**
     * Enqueue premium tool assets when licensed.
     *
     * @param string   $class   Premium tool class name.
     * @param callable $enqueue Licensed enqueue callback.
     * @param string   $hook    Admin page hook.
     * @return void
     */
    private static function enqueue_premium_tool_scripts( $class, $enqueue, $hook ) {
        $can_enqueue = false;
        if ( self::user_has_premium_tools() ) {
            if ( class_exists( $class ) ) {
                $can_enqueue = true;
            }
        }
        if ( !$can_enqueue ) {
            return;
        }
        call_user_func( $enqueue, $hook );
    }

    /**
     * @return void
     */
    public static function render_needs_analysis_tab() {
        self::render_premium_tool_tab(
            'Cleverplugins\\SEOBooster\\Tools\\Tools_Needs_Analysis',
            __( 'Needs analysis', 'seo-booster' ),
            __( 'Find content that was never analyzed, has stale SEO analysis, or still shows issues from your last run. Queue a bulk re-analysis using the existing SEO analysis engine.', 'seo-booster' ),
            array('Cleverplugins\\SEOBooster\\Tools\\Tools_Needs_Analysis', 'render_admin')
        );
    }

    /**
     * @return void
     */
    public static function render_gsc_opportunities_tab() {
        self::render_premium_tool_tab(
            'Cleverplugins\\SEOBooster\\Tools\\Tools_GSC_Opportunities',
            __( 'GSC opportunities', 'seo-booster' ),
            __( 'Find striking-distance keywords, low-CTR pages, and high-impression opportunities from GSC. Batch-rewrite SEO titles and meta with AI seeded by the actual query.', 'seo-booster' ),
            array('Cleverplugins\\SEOBooster\\Tools\\Tools_GSC_Opportunities', 'render_admin')
        );
    }

    /**
     * @return void
     */
    public static function render_focus_keyword_tab() {
        self::render_premium_tool_tab(
            'Cleverplugins\\SEOBooster\\Tools\\Tools_Focus_Keyword',
            __( 'Focus keywords', 'seo-booster' ),
            __( 'Suggest unique focus keywords from imported GSC data for pages missing one — no AI required. Picks the highest-impression query per URL and skips utility pages.', 'seo-booster' ),
            array('Cleverplugins\\SEOBooster\\Tools\\Tools_Focus_Keyword', 'render_admin')
        );
    }

    /**
     * @return void
     */
    public static function render_autolink_opportunities_tab() {
        self::render_premium_tool_tab(
            'Cleverplugins\\SEOBooster\\Tools\\Tools_Autolink_Opportunities',
            __( 'Autolink opportunities', 'seo-booster' ),
            __( 'Discover high-value GSC queries without autolink rules, or enable autolink on high-traffic pages where it is off.', 'seo-booster' ),
            array('Cleverplugins\\SEOBooster\\Tools\\Tools_Autolink_Opportunities', 'render_admin')
        );
    }

    /**
     * @return void
     */
    public static function render_entity_map_tab() {
        self::render_premium_tool_tab(
            'Cleverplugins\\SEOBooster\\Tools\\Tools_Entity_Map',
            __( 'Entity Map', 'seo-booster' ),
            __( 'Publish a structured Entity Map so AI systems understand your organization, key content, and relationships — beyond llms.txt.', 'seo-booster' ),
            array('Cleverplugins\\SEOBooster\\Tools\\Tools_Entity_Map', 'render_admin'),
            'entity-map'
        );
    }

    /**
     * @param string $hook Admin hook.
     * @return void
     */
    public static function enqueue_needs_analysis_scripts( $hook ) {
        self::enqueue_premium_tool_scripts( 'Cleverplugins\\SEOBooster\\Tools\\Tools_Needs_Analysis', array('Cleverplugins\\SEOBooster\\Tools\\Tools_Needs_Analysis', 'enqueue_scripts'), $hook );
    }

    /**
     * @param string $hook Admin hook.
     * @return void
     */
    public static function enqueue_gsc_opportunities_scripts( $hook ) {
        self::enqueue_premium_tool_scripts( 'Cleverplugins\\SEOBooster\\Tools\\Tools_GSC_Opportunities', array('Cleverplugins\\SEOBooster\\Tools\\Tools_GSC_Opportunities', 'enqueue_scripts'), $hook );
    }

    /**
     * @param string $hook Admin hook.
     * @return void
     */
    public static function enqueue_focus_keyword_scripts( $hook ) {
        self::enqueue_premium_tool_scripts( 'Cleverplugins\\SEOBooster\\Tools\\Tools_Focus_Keyword', array('Cleverplugins\\SEOBooster\\Tools\\Tools_Focus_Keyword', 'enqueue_scripts'), $hook );
    }

    /**
     * @param string $hook Admin hook.
     * @return void
     */
    public static function enqueue_autolink_opportunities_scripts( $hook ) {
        self::enqueue_premium_tool_scripts( 'Cleverplugins\\SEOBooster\\Tools\\Tools_Autolink_Opportunities', array('Cleverplugins\\SEOBooster\\Tools\\Tools_Autolink_Opportunities', 'enqueue_scripts'), $hook );
    }

    /**
     * @param string $hook Admin hook.
     * @return void
     */
    public static function enqueue_entity_map_scripts( $hook ) {
        self::enqueue_premium_tool_scripts( 'Cleverplugins\\SEOBooster\\Tools\\Tools_Entity_Map', array('Cleverplugins\\SEOBooster\\Tools\\Tools_Entity_Map', 'enqueue_scripts'), $hook );
    }

    /**
     * Active tab slug from query string.
     *
     * @return string
     */
    public static function get_active_tab() {
        $tab = ( isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview' );
        $tools = self::get_tools();
        if ( !isset( $tools[$tab] ) ) {
            return 'overview';
        }
        return $tab;
    }

    /**
     * Base admin URL for Tools page.
     *
     * @param string $tab Optional tab.
     * @return string
     */
    public static function get_page_url( $tab = '' ) {
        $url = admin_url( 'admin.php?page=sb2_tools' );
        if ( $tab !== '' ) {
            $url = add_query_arg( 'tab', sanitize_key( $tab ), $url );
        }
        return $url;
    }

    /**
     * Render the Tools page.
     *
     * @return void
     */
    public static function render() {
        if ( !current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'seo-booster' ) );
        }
        $tools = self::get_tools();
        $active_tab = self::get_active_tab();
        echo '<div class="wrap sb-wrap sb-tools-wrap">';
        echo wp_kses_post( Utils::show_plugin_headline( __( 'Tools', 'seo-booster' ), true ) );
        $has_premium = self::user_has_premium_tools();
        echo '<nav class="nav-tab-wrapper sb-tools-nav" aria-label="' . esc_attr__( 'Tools sections', 'seo-booster' ) . '">';
        foreach ( $tools as $tool ) {
            $url = self::get_page_url( $tool['id'] );
            $class = 'nav-tab';
            if ( $tool['id'] === $active_tab ) {
                $class .= ' nav-tab-active';
            }
            if ( !empty( $tool['premium'] ) && !$has_premium ) {
                $class .= ' proonly';
            }
            printf(
                '<a href="%s" class="%s">%s</a>',
                esc_url( $url ),
                esc_attr( $class ),
                esc_html( $tool['label'] )
            );
        }
        echo '</nav>';
        echo '<div class="sb-tools-tab-content">';
        if ( isset( $tools[$active_tab]['render'] ) && is_callable( $tools[$active_tab]['render'] ) ) {
            call_user_func( $tools[$active_tab]['render'] );
        }
        echo '</div>';
        echo '</div>';
    }

    /**
     * Overview tab.
     *
     * @return void
     */
    public static function render_overview() {
        $seo_target = SEO_Meta_Writer::get_target();
        $seo_target_label = SEO_Meta_Writer::get_target_label();
        $supported_plugins_list = SEO_Plugin_Registry::get_supported_plugins_list();
        include SEOBOOSTER_PLUGINPATH . 'inc/Tools/views/tools-overview.php';
    }

    /**
     * Image metadata tab.
     *
     * @return void
     */
    public static function render_image_tool() {
        $ai_available = Tools_Image_Scanner::ai_is_available();
        $ai_unavailable_message = Tools_Image_Scanner::get_ai_unavailable_message();
        $ai_notice_type = Tools_Image_Scanner::get_ai_notice_type();
        $apply_fields = Tools_Image_Batch::get_user_apply_fields();
        $scan_filters = Tools_Image_Batch::get_user_scan_filters();
        $settings_url = admin_url( 'admin.php?page=sb2_settings#ai-llm' );
        $connectors_url = admin_url( 'options-connectors.php' );
        include SEOBOOSTER_PLUGINPATH . 'inc/Tools/views/image-metadata-tool.php';
    }

    /**
     * Bulk meta tab.
     *
     * @return void
     */
    public static function render_meta_tool() {
        $seo_target = SEO_Meta_Writer::get_target();
        $seo_target_label = SEO_Meta_Writer::get_target_label();
        $ai_available = Tools_Meta_Scanner::ai_is_available();
        $ai_unavailable_message = Tools_Meta_Scanner::get_ai_unavailable_message();
        $ai_notice_type = Tools_Meta_Scanner::get_ai_notice_type();
        $apply_fields = Tools_Meta_Scanner::get_user_apply_fields();
        $scan_filters = Tools_Meta_Scanner::get_user_scan_filters();
        $post_types = Tools_Meta_Scanner::get_user_post_types();
        $selectable_post_types = Tools_Meta_Scanner::get_selectable_post_types();
        $has_revertable = SEO_Meta_Writer::has_revertable_batch();
        $settings_url = admin_url( 'admin.php?page=sb2_settings#ai-llm' );
        $connectors_url = admin_url( 'options-connectors.php' );
        include SEOBOOSTER_PLUGINPATH . 'inc/Tools/views/meta-bulk-tool.php';
    }

    /**
     * Enqueue assets on Tools page only.
     *
     * @param string $hook Admin page hook.
     * @return void
     */
    public static function enqueue_scripts( $hook ) {
        $tools_hook = ( function_exists( 'get_plugin_page_hookname' ) ? get_plugin_page_hookname( 'sb2_tools', 'sb2_dashboard' ) : 'seo-booster_page_sb2_tools' );
        if ( $hook !== $tools_hook && $hook !== 'seo-booster_page_sb2_tools' && $hook !== 'sb2_dashboard_page_sb2_tools' ) {
            return;
        }
        wp_enqueue_style( 'list-tables' );
        wp_enqueue_style(
            'seo-booster',
            SEOBOOSTER_PLUGINURL . 'css/seo-booster.css',
            array(),
            filemtime( SEOBOOSTER_PLUGINPATH . 'css/seo-booster.css' )
        );
        wp_enqueue_style(
            'seobooster-settings',
            SEOBOOSTER_PLUGINURL . 'css/sb-settings.css',
            array('seo-booster'),
            filemtime( SEOBOOSTER_PLUGINPATH . 'css/sb-settings.css' )
        );
        wp_enqueue_style(
            'sb-tools-shared',
            SEOBOOSTER_PLUGINURL . 'css/sb-tools-image.css',
            array('list-tables', 'seo-booster', 'seobooster-settings'),
            filemtime( SEOBOOSTER_PLUGINPATH . 'css/sb-tools-image.css' )
        );
        Utils::enqueue_modal_assets();
        wp_enqueue_script(
            'sb-tools-ui',
            SEOBOOSTER_PLUGINURL . 'js/sb-tools-ui.js',
            array('jquery', 'sb-modal'),
            filemtime( SEOBOOSTER_PLUGINPATH . 'js/sb-tools-ui.js' ),
            true
        );
        wp_localize_script( 'sb-tools-ui', 'sbToolsUi', array(
            'strings' => array(
                'edit'    => __( 'Edit', 'seo-booster' ),
                'view'    => __( 'View', 'seo-booster' ),
                'dismiss' => __( 'Dismiss', 'seo-booster' ),
                'ok'      => __( 'OK', 'seo-booster' ),
                'cancel'  => __( 'Cancel', 'seo-booster' ),
            ),
        ) );
        $tools = self::get_tools();
        $active_tab = self::get_active_tab();
        if ( isset( $tools[$active_tab]['enqueue'] ) && is_callable( $tools[$active_tab]['enqueue'] ) ) {
            call_user_func( $tools[$active_tab]['enqueue'], $hook );
        }
    }

    /**
     * Enqueue image tool assets.
     *
     * @param string $hook Admin hook.
     * @return void
     */
    public static function enqueue_image_scripts( $hook ) {
        wp_enqueue_script(
            'sb-tools-image',
            SEOBOOSTER_PLUGINURL . 'js/sb-tools-image.js',
            array('jquery', 'sb-tools-ui'),
            filemtime( SEOBOOSTER_PLUGINPATH . 'js/sb-tools-image.js' ),
            true
        );
        wp_localize_script( 'sb-tools-image', 'sbToolsImage', array(
            'ajax_url'               => admin_url( 'admin-ajax.php' ),
            'nonce'                  => wp_create_nonce( 'sb_tools_image_nonce' ),
            'ai_available'           => Tools_Image_Scanner::ai_is_available(),
            'ai_unavailable_message' => Tools_Image_Scanner::get_ai_unavailable_message(),
            'ai_notice_type'         => Tools_Image_Scanner::get_ai_notice_type(),
            'connectors_url'         => admin_url( 'options-connectors.php' ),
            'settings_url'           => admin_url( 'admin.php?page=sb2_settings#ai-llm' ),
            'apply_fields'           => Tools_Image_Batch::get_user_apply_fields(),
            'scan_filters'           => Tools_Image_Batch::get_user_scan_filters(),
            'strings'                => self::get_image_js_strings(),
        ) );
    }

    /**
     * Enqueue bulk meta tool assets.
     *
     * @param string $hook Admin hook.
     * @return void
     */
    public static function enqueue_meta_scripts( $hook ) {
        wp_enqueue_script(
            'sb-tools-meta',
            SEOBOOSTER_PLUGINURL . 'js/sb-tools-meta.js',
            array('jquery', 'sb-tools-ui'),
            filemtime( SEOBOOSTER_PLUGINPATH . 'js/sb-tools-meta.js' ),
            true
        );
        wp_localize_script( 'sb-tools-meta', 'sbToolsMeta', array(
            'ajax_url'               => admin_url( 'admin-ajax.php' ),
            'nonce'                  => wp_create_nonce( 'sb_tools_meta_nonce' ),
            'seo_target'             => SEO_Meta_Writer::get_target(),
            'seo_target_label'       => SEO_Meta_Writer::get_target_label(),
            'ai_available'           => Tools_Meta_Scanner::ai_is_available(),
            'ai_unavailable_message' => Tools_Meta_Scanner::get_ai_unavailable_message(),
            'ai_notice_type'         => Tools_Meta_Scanner::get_ai_notice_type(),
            'connectors_url'         => admin_url( 'options-connectors.php' ),
            'settings_url'           => admin_url( 'admin.php?page=sb2_settings#ai-llm' ),
            'apply_fields'           => Tools_Meta_Scanner::get_user_apply_fields(),
            'scan_filters'           => Tools_Meta_Scanner::get_user_scan_filters(),
            'post_types'             => Tools_Meta_Scanner::get_user_post_types(),
            'has_revertable'         => SEO_Meta_Writer::has_revertable_batch(),
            'strings'                => self::get_meta_js_strings(),
        ) );
    }

    /**
     * Image tool localized strings (extracted from original Tools_Page).
     *
     * @return array
     */
    private static function get_image_js_strings() {
        return array(
            'scanning'                 => __( 'Scanning…', 'seo-booster' ),
            'updating_results'         => __( 'Updating results…', 'seo-booster' ),
            'scan_refresh_failed'      => __( 'Could not refresh results. Click “Scan media library” to try again.', 'seo-booster' ),
            'scan'                     => __( 'Scan media library', 'seo-booster' ),
            'no_results'               => __( 'No matching images found. Try different scan filters.', 'seo-booster' ),
            'no_results_missing_files' => __( 'No processable images remain after skipping missing files.', 'seo-booster' ),
            'missing_files_one'        => __( '%d image was skipped because the file was not found on disk. Try regenerating thumbnails in the Media Library or re-uploading the file.', 'seo-booster' ),
            'missing_files_many'       => __( '%d images were skipped because the files were not found on disk. Try regenerating thumbnails in the Media Library or re-uploading the files.', 'seo-booster' ),
            'matching_one'             => __( '%d matching image', 'seo-booster' ),
            'matching_many'            => __( '%d matching images', 'seo-booster' ),
            'preview_sample'           => __( 'Showing first %1$d of %2$d matching images', 'seo-booster' ),
            'select_all_preview'       => __( 'Select all in preview', 'seo-booster' ),
            'select_filter'            => __( 'Select at least one scan filter.', 'seo-booster' ),
            'select_images'            => __( 'Select at least one image.', 'seo-booster' ),
            'select_apply'             => __( 'Select at least one field to apply.', 'seo-booster' ),
            'processing'               => __( 'Processing %1$d of %2$d…', 'seo-booster' ),
            'currently_processing'     => __( 'Currently processing', 'seo-booster' ),
            'last_processed'           => __( 'Last processed', 'seo-booster' ),
            'generating'               => __( 'Generating metadata with AI…', 'seo-booster' ),
            'timer_elapsed'            => __( 'Elapsed: %s s', 'seo-booster' ),
            'timer_completed'          => __( 'Completed in %s s', 'seo-booster' ),
            'timer_failed'             => __( 'Failed after %s s', 'seo-booster' ),
            'complete'                 => __( 'Done: %1$d processed, %2$d failed.', 'seo-booster' ),
            'batch_complete'           => __( 'Batch complete in %1$s — %2$d processed, %3$d failed.', 'seo-booster' ),
            'batch_cancelled'          => __( 'Cancelled after %1$s — %2$d processed, %3$d failed.', 'seo-booster' ),
            'title_processing'         => __( '%1$d/%2$d', 'seo-booster' ),
            'title_complete'           => __( 'Complete: %1$d processed, %2$d failed', 'seo-booster' ),
            'title_cancelled'          => __( 'Cancelled: %1$d processed', 'seo-booster' ),
            'cancel'                   => __( 'Cancel', 'seo-booster' ),
            'cancelling'               => __( 'Cancelling…', 'seo-booster' ),
            'error'                    => __( 'Error', 'seo-booster' ),
            'before'                   => __( 'Before', 'seo-booster' ),
            'after'                    => __( 'After', 'seo-booster' ),
            'title'                    => __( 'Title', 'seo-booster' ),
            'alt_text'                 => __( 'Alt text', 'seo-booster' ),
            'caption'                  => __( 'Caption', 'seo-booster' ),
            'description'              => __( 'Description', 'seo-booster' ),
            'empty'                    => __( '—', 'seo-booster' ),
            'ai_disabled'              => __( 'Enable AI in SEO Booster Settings to process images.', 'seo-booster' ),
            'edit'                     => __( 'Edit', 'seo-booster' ),
            'view'                     => __( 'View', 'seo-booster' ),
            'file_col'                 => _x( 'File', 'column name', 'seo-booster' ),
            'issues_col'               => __( 'Issues', 'seo-booster' ),
            'filename_label'           => __( 'File name:', 'seo-booster' ),
            'toggle_row'               => __( 'Show more details', 'seo-booster' ),
            'process_all_matching'     => __( 'Process all matching (%d)', 'seo-booster' ),
            'confirm_process_all'      => __( 'Process all %d matching images? This may take a long time and use AI credits. Keep this tab open.', 'seo-booster' ),
            'scan_formats_note'        => __( 'Scan includes JPEG, PNG, and WebP only (AI-compatible formats).', 'seo-booster' ),
            'metadata_not_saved'       => __( 'No metadata was saved for this image.', 'seo-booster' ),
            'image_source_size'        => __( 'Analyzed size: %s', 'seo-booster' ),
            'tab_warning'              => __( 'Keep this browser tab open while images are processing. Leaving or closing the tab will stop the batch.', 'seo-booster' ),
            'failed_heading'           => __( 'Failed images', 'seo-booster' ),
            'retry_failed'             => __( 'Retry failed (%d)', 'seo-booster' ),
            'failed_and_more'          => __( 'and %d more', 'seo-booster' ),
            'eta_remaining'            => __( '~%s left', 'seo-booster' ),
            'eta_under_one_min'        => __( '< 1 min', 'seo-booster' ),
        );
    }

    /**
     * Bulk meta tool localized strings.
     *
     * @return array
     */
    private static function get_meta_js_strings() {
        return array(
            'scanning'             => __( 'Scanning…', 'seo-booster' ),
            'scan'                 => __( 'Scan content', 'seo-booster' ),
            'no_results'           => __( 'No matching content found. Try different scan filters or content types.', 'seo-booster' ),
            'matching_one'         => __( '%d matching item', 'seo-booster' ),
            'matching_many'        => __( '%d matching items', 'seo-booster' ),
            'preview_sample'       => __( 'Showing first %1$d of %2$d matching items', 'seo-booster' ),
            'select_filter'        => __( 'Select at least one scan filter.', 'seo-booster' ),
            'select_post_types'    => __( 'Select at least one content type.', 'seo-booster' ),
            'select_items'         => __( 'Select at least one item.', 'seo-booster' ),
            'select_apply'         => __( 'Select at least one field to apply.', 'seo-booster' ),
            'processing'           => __( 'Processing %1$d of %2$d…', 'seo-booster' ),
            'generating'           => __( 'Generating SEO meta with AI…', 'seo-booster' ),
            'complete'             => __( 'Done: %1$d processed, %2$d failed.', 'seo-booster' ),
            'batch_complete'       => __( 'Batch complete in %1$s — %2$d processed, %3$d failed.', 'seo-booster' ),
            'batch_cancelled'      => __( 'Cancelled after %1$s — %2$d processed, %3$d failed.', 'seo-booster' ),
            'cancel'               => __( 'Cancel', 'seo-booster' ),
            'error'                => __( 'Error', 'seo-booster' ),
            'before'               => __( 'Before', 'seo-booster' ),
            'after'                => __( 'After', 'seo-booster' ),
            'seo_title'            => __( 'SEO title', 'seo-booster' ),
            'seo_description'      => __( 'Meta description', 'seo-booster' ),
            'empty'                => __( '—', 'seo-booster' ),
            'process_all_matching' => __( 'Process all matching (%d)', 'seo-booster' ),
            'confirm_process_all'  => __( 'Process all %d matching items? This may take a long time. Keep this tab open.', 'seo-booster' ),
            'tab_warning'          => __( 'Keep this browser tab open while content is processing. Leaving or closing the tab will stop the batch.', 'seo-booster' ),
            'failed_heading'       => __( 'Failed items', 'seo-booster' ),
            'retry_failed'         => __( 'Retry failed (%d)', 'seo-booster' ),
            'revert_batch'         => __( 'Revert last bulk run', 'seo-booster' ),
            'revert_confirm'       => __( 'Restore previous SEO titles and descriptions from your last bulk run?', 'seo-booster' ),
            'revert_success'       => __( 'Previous values restored.', 'seo-booster' ),
            'issues_col'           => __( 'Issues', 'seo-booster' ),
            'content_col'          => __( 'Content', 'seo-booster' ),
            'batch_cancelled'      => __( 'Cancelled after %1$s — %2$d processed, %3$d failed.', 'seo-booster' ),
            'title_complete'       => __( 'Complete: %1$d processed, %2$d failed', 'seo-booster' ),
            'title_cancelled'      => __( 'Cancelled: %1$d processed', 'seo-booster' ),
            'item_skipped'         => __( 'Skipped — fields already filled (no AI call).', 'seo-booster' ),
        );
    }

    /**
     * Enqueue shared batch preview script for Tools batch tabs.
     *
     * @return void
     */
    public static function enqueue_batch_preview_script() {
        wp_enqueue_script(
            'sb-tools-batch-preview',
            SEOBOOSTER_PLUGINURL . 'js/sb-tools-batch-preview.js',
            array('jquery'),
            filemtime( SEOBOOSTER_PLUGINPATH . 'js/sb-tools-batch-preview.js' ),
            true
        );
    }

    /**
     * GSC opportunities tool localized strings.
     *
     * @return array
     */
    public static function get_gsc_opportunities_js_strings() {
        return array(
            'scanning'             => __( 'Scanning…', 'seo-booster' ),
            'select_filter'        => __( 'Select at least one opportunity filter.', 'seo-booster' ),
            'select_items'         => __( 'Select at least one item.', 'seo-booster' ),
            'select_apply'         => __( 'Select at least one field to apply.', 'seo-booster' ),
            'matching_one'         => __( '%d matching opportunity', 'seo-booster' ),
            'matching_many'        => __( '%d matching opportunities', 'seo-booster' ),
            'processing'           => __( 'Processing %1$d of %2$d…', 'seo-booster' ),
            'batch_complete'       => __( 'Done: %1$d processed, %2$d failed.', 'seo-booster' ),
            'batch_cancelled'      => __( 'Cancelled: %1$d processed, %2$d failed.', 'seo-booster' ),
            'process_all_matching' => __( 'Process all matching (%d)', 'seo-booster' ),
            'confirm_process_all'  => __( 'Process all %d matching opportunities? This may take a long time. Keep this tab open.', 'seo-booster' ),
            'revert_confirm'       => __( 'Restore previous SEO titles and descriptions from your last bulk run?', 'seo-booster' ),
            'revert_success'       => __( 'Previous values restored.', 'seo-booster' ),
            'error'                => __( 'Error', 'seo-booster' ),
        );
    }

    /**
     * Focus keyword tool localized strings.
     *
     * @return array
     */
    public static function get_focus_keyword_js_strings() {
        return array(
            'select_post_types'    => __( 'Select at least one content type.', 'seo-booster' ),
            'select_items'         => __( 'Select at least one item.', 'seo-booster' ),
            'matching_one'         => __( '%d suggested focus keyword', 'seo-booster' ),
            'matching_many'        => __( '%d suggested focus keywords', 'seo-booster' ),
            'processing'           => __( 'Processing %1$d of %2$d…', 'seo-booster' ),
            'batch_complete'       => __( 'Done: %1$d processed, %2$d failed.', 'seo-booster' ),
            'batch_cancelled'      => __( 'Cancelled: %1$d processed, %2$d failed.', 'seo-booster' ),
            'process_all_matching' => __( 'Apply all matching (%d)', 'seo-booster' ),
            'confirm_process_all'  => __( 'Apply focus keywords to all %d matching items?', 'seo-booster' ),
            'revert_confirm'       => __( 'Restore previous focus keywords from your last bulk run?', 'seo-booster' ),
            'skipped_summary'      => __( 'Skipped: %1$d already had a keyword, %2$d utility pages, %3$d no search demand, %4$d no unique keyword available.', 'seo-booster' ),
            'choose_keyword'       => __( 'Choose focus keyword', 'seo-booster' ),
            'show_suggestions'     => __( 'Show GSC suggestions (%d)', 'seo-booster' ),
            'hide_suggestions'     => __( 'Hide GSC suggestions', 'seo-booster' ),
            'custom_keyword'       => __( 'Custom keyword — edit or pick a suggestion below.', 'seo-booster' ),
            'impressions_short'    => __( 'Impr.', 'seo-booster' ),
            'position_short'       => __( 'Pos.', 'seo-booster' ),
            'no_metrics'           => __( '—', 'seo-booster' ),
            'error'                => __( 'Error', 'seo-booster' ),
        );
    }

    /**
     * Autolink opportunities tool localized strings.
     *
     * @return array
     */
    public static function get_autolink_opportunities_js_strings() {
        return array(
            'matching_one'          => __( '%d matching item', 'seo-booster' ),
            'matching_many'         => __( '%d matching items', 'seo-booster' ),
            'select_items'          => __( 'Select at least one item.', 'seo-booster' ),
            'processing'            => __( 'Processing %1$d of %2$d…', 'seo-booster' ),
            'batch_complete'        => __( 'Done: %1$d processed, %2$d failed.', 'seo-booster' ),
            'batch_cancelled'       => __( 'Cancelled: %1$d processed, %2$d failed.', 'seo-booster' ),
            'process_all_matching'  => __( 'Apply all matching (%d)', 'seo-booster' ),
            'confirm_process_all'   => __( 'Apply all %d matching items?', 'seo-booster' ),
            'revert_confirm'        => __( 'Remove autolink rules created in your last batch?', 'seo-booster' ),
            'enable_autolink_label' => __( 'Enable autolink on this page', 'seo-booster' ),
            'group_summary'         => __( '%1$s point here • %2$d impressions • %3$d clicks', 'seo-booster' ),
            'group_keywords_one'    => __( '%d keyword', 'seo-booster' ),
            'group_keywords_many'   => __( '%d keywords', 'seo-booster' ),
            'keywords_one'          => __( '%d keyword', 'seo-booster' ),
            'keywords_many'         => __( '%d keywords', 'seo-booster' ),
            'pages_one'             => __( '%d page', 'seo-booster' ),
            'pages_many'            => __( '%d pages', 'seo-booster' ),
            'rules_one'             => __( '%d keyword rule', 'seo-booster' ),
            'rules_many'            => __( '%d keyword rules', 'seo-booster' ),
            'show_keywords'         => __( 'Show keywords', 'seo-booster' ),
            'hide_keywords'         => __( 'Hide keywords', 'seo-booster' ),
            'reduced_from'          => __( 'reduced from %d candidates', 'seo-booster' ),
            'prev_page'             => __( 'Previous', 'seo-booster' ),
            'next_page'             => __( 'Next', 'seo-booster' ),
            'page_of'               => __( 'Page %1$d of %2$d', 'seo-booster' ),
            'error'                 => __( 'Error', 'seo-booster' ),
        );
    }

}
