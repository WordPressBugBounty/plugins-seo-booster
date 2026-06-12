<?php

namespace Cleverplugins\SEOBooster\Tools;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SEO Booster Tools admin page.
 *
 * @package Cleverplugins\SEOBooster\Tools
 */
class Tools_Page
{
    /**
     * Initialize hooks.
     *
     * @return void
     */
    public static function init()
    {
        Tools_Image_Scanner::init();
        Tools_Image_Batch::init();
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    /**
     * Render the Tools page.
     *
     * @return void
     */
    public static function render()
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'seo-booster'));
        }

        $ai_available = Tools_Image_Scanner::ai_is_available();
        $ai_unavailable_message = Tools_Image_Scanner::get_ai_unavailable_message();
        $ai_notice_type = Tools_Image_Scanner::get_ai_notice_type();
        $apply_fields = Tools_Image_Batch::get_user_apply_fields();
        $scan_filters   = Tools_Image_Batch::get_user_scan_filters();
        $settings_url   = admin_url('admin.php?page=sb2_settings#ai-llm');
        $connectors_url = admin_url('options-connectors.php');

        include SEOBOOSTER_PLUGINPATH . 'inc/Tools/views/image-metadata-tool.php';
    }

    /**
     * Enqueue assets on Tools page only.
     *
     * @param string $hook Admin page hook.
     * @return void
     */
    public static function enqueue_scripts($hook)
    {
        $tools_hook = function_exists('get_plugin_page_hookname')
            ? get_plugin_page_hookname('sb2_tools', 'sb2_dashboard')
            : 'seo-booster_page_sb2_tools';

        if ($hook !== $tools_hook && $hook !== 'seo-booster_page_sb2_tools' && $hook !== 'sb2_dashboard_page_sb2_tools') {
            return;
        }

        wp_enqueue_style('list-tables');

        wp_enqueue_style(
            'sb-tools-image',
            SEOBOOSTER_PLUGINURL . 'css/sb-tools-image.css',
            ['list-tables'],
            filemtime(SEOBOOSTER_PLUGINPATH . 'css/sb-tools-image.css')
        );

        wp_enqueue_script(
            'sb-tools-image',
            SEOBOOSTER_PLUGINURL . 'js/sb-tools-image.js',
            ['jquery'],
            filemtime(SEOBOOSTER_PLUGINPATH . 'js/sb-tools-image.js'),
            true
        );

        wp_localize_script('sb-tools-image', 'sbToolsImage', [
            'ajax_url'      => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('sb_tools_image_nonce'),
            'ai_available'           => Tools_Image_Scanner::ai_is_available(),
            'ai_unavailable_message' => Tools_Image_Scanner::get_ai_unavailable_message(),
            'ai_notice_type'         => Tools_Image_Scanner::get_ai_notice_type(),
            'connectors_url'         => admin_url('options-connectors.php'),
            'settings_url'           => admin_url('admin.php?page=sb2_settings#ai-llm'),
            'apply_fields'  => Tools_Image_Batch::get_user_apply_fields(),
            'scan_filters'  => Tools_Image_Batch::get_user_scan_filters(),
            'strings'       => [
                'scanning'           => __('Scanning…', 'seo-booster'),
                'updating_results'   => __('Updating results…', 'seo-booster'),
                'scan_refresh_failed' => __('Could not refresh results. Click “Scan media library” to try again.', 'seo-booster'),
                'scan'               => __('Scan media library', 'seo-booster'),
                'no_results'         => __('No matching images found. Try different scan filters.', 'seo-booster'),
                'matching_one'       => __('%d matching image', 'seo-booster'),
                'matching_many'      => __('%d matching images', 'seo-booster'),
                'preview_sample'     => __('Showing first %1$d of %2$d matching images', 'seo-booster'),
                'select_all_preview'   => __('Select all in preview', 'seo-booster'),
                'select_filter'      => __('Select at least one scan filter.', 'seo-booster'),
                'select_images'      => __('Select at least one image.', 'seo-booster'),
                'select_apply'       => __('Select at least one field to apply.', 'seo-booster'),
                'processing'         => __('Processing %1$d of %2$d…', 'seo-booster'),
                'currently_processing' => __('Currently processing', 'seo-booster'),
                'last_processed'     => __('Last processed', 'seo-booster'),
                'generating'         => __('Generating metadata with AI…', 'seo-booster'),
                'timer_elapsed'      => __('Elapsed: %s s', 'seo-booster'),
                'timer_completed'    => __('Completed in %s s', 'seo-booster'),
                'timer_failed'       => __('Failed after %s s', 'seo-booster'),
                'complete'           => __('Done: %1$d processed, %2$d failed.', 'seo-booster'),
                'batch_complete'     => __('Batch complete in %1$s — %2$d processed, %3$d failed.', 'seo-booster'),
                'batch_cancelled'    => __('Cancelled after %1$s — %2$d processed, %3$d failed.', 'seo-booster'),
                'title_processing'   => __('%1$d/%2$d', 'seo-booster'),
                'title_complete'     => __('Complete: %1$d processed, %2$d failed', 'seo-booster'),
                'title_cancelled'    => __('Cancelled: %1$d processed', 'seo-booster'),
                'cancel'             => __('Cancel', 'seo-booster'),
                'cancelling'         => __('Cancelling…', 'seo-booster'),
                'error'              => __('Error', 'seo-booster'),
                'before'             => __('Before', 'seo-booster'),
                'after'              => __('After', 'seo-booster'),
                'title'              => __('Title', 'seo-booster'),
                'alt_text'           => __('Alt text', 'seo-booster'),
                'caption'            => __('Caption', 'seo-booster'),
                'description'        => __('Description', 'seo-booster'),
                'empty'              => __('—', 'seo-booster'),
                'ai_disabled'        => __('Enable AI in SEO Booster Settings to process images.', 'seo-booster'),
                'edit'               => __('Edit', 'seo-booster'),
                'view'               => __('View', 'seo-booster'),
                'file_col'           => _x('File', 'column name', 'seo-booster'),
                'issues_col'         => __('Issues', 'seo-booster'),
                'filename_label'     => __('File name:', 'seo-booster'),
                'toggle_row'         => __('Show more details', 'seo-booster'),
                'process_all_matching' => __('Process all matching (%d)', 'seo-booster'),
                'confirm_process_all' => __('Process all %d matching images? This may take a long time and use AI credits. Keep this tab open.', 'seo-booster'),
                'scan_formats_note'  => __('Scan includes JPEG, PNG, and WebP only (AI-compatible formats).', 'seo-booster'),
                'metadata_not_saved' => __('No metadata was saved for this image.', 'seo-booster'),
                'image_source_size'  => __('Analyzed size: %s', 'seo-booster'),
                'tab_warning'        => __('Keep this browser tab open while images are processing. Leaving or closing the tab will stop the batch.', 'seo-booster'),
                'failed_heading'     => __('Failed images', 'seo-booster'),
                'retry_failed'       => __('Retry failed (%d)', 'seo-booster'),
                'failed_and_more'    => __('and %d more', 'seo-booster'),
                'eta_remaining'      => __('~%s left', 'seo-booster'),
                'eta_under_one_min'  => __('< 1 min', 'seo-booster'),
            ],
        ]);
    }
}
