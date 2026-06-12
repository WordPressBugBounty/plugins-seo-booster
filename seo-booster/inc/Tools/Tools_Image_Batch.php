<?php

namespace Cleverplugins\SEOBooster\Tools;

use Cleverplugins\SEOBooster\AI_Image_Generator;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tools-page-only batch processing for image metadata AI generation.
 *
 * @package Cleverplugins\SEOBooster\Tools
 */
class Tools_Image_Batch
{
    const TRANSIENT_PREFIX = 'sb_tools_batch_';
    const TRANSIENT_TTL    = 3600;

    /**
     * @var int|null Attachment ID for context filter closure.
     */
    private static $context_attachment_id = null;

    /**
     * Initialize AJAX hooks.
     *
     * @return void
     */
    public static function init()
    {
        add_action('wp_ajax_sb_tools_start_batch', [__CLASS__, 'ajax_start_batch']);
        add_action('wp_ajax_sb_tools_process_image', [__CLASS__, 'ajax_process_image']);
        add_action('wp_ajax_sb_tools_batch_status', [__CLASS__, 'ajax_batch_status']);
        add_action('wp_ajax_sb_tools_cancel_batch', [__CLASS__, 'ajax_cancel_batch']);
    }

    /**
     * Default apply field map.
     *
     * @return array
     */
    public static function default_apply_fields()
    {
        return [
            'title'       => false,
            'alt_text'    => true,
            'caption'     => false,
            'description' => false,
        ];
    }

    /**
     * Parse apply_fields from POST.
     *
     * @param array|null $raw Raw input.
     * @return array
     */
    public static function parse_apply_fields($raw)
    {
        $defaults = self::default_apply_fields();
        if (!is_array($raw)) {
            return $defaults;
        }
        foreach (array_keys($defaults) as $key) {
            $defaults[$key] = self::is_apply_field_enabled(isset($raw[$key]) ? $raw[$key] : false);
        }
        return $defaults;
    }

    /**
     * Whether an apply-field value from POST/user meta is enabled.
     *
     * jQuery sends unchecked fields as the string "false", which !empty() treats as true.
     *
     * @param mixed $value Raw value.
     * @return bool
     */
    public static function is_apply_field_enabled($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * AJAX: start a new batch.
     *
     * @return void
     */
    public static function ajax_start_batch()
    {
        check_ajax_referer('sb_tools_image_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied', 'seo-booster')]);
        }

        if (!Tools_Image_Scanner::ai_is_available()) {
            $message = Tools_Image_Scanner::get_ai_unavailable_message();
            if ($message === '') {
                $message = __('AI is not configured for image generation.', 'seo-booster');
            }
            wp_send_json_error(['message' => $message]);
        }

        $process_scope = isset($_POST['process_scope'])
            ? sanitize_text_field(wp_unslash($_POST['process_scope']))
            : 'selected';

        $filters = isset($_POST['filters']) && is_array($_POST['filters'])
            ? array_values(array_intersect(
                array_map('sanitize_text_field', wp_unslash($_POST['filters'])),
                Tools_Image_Scanner::get_filter_keys()
            ))
            : [];

        if ($process_scope === 'all_matching') {
            if (empty($filters)) {
                wp_send_json_error(['message' => __('Select at least one scan filter.', 'seo-booster')]);
            }

            $attachment_ids = Tools_Image_Scanner::get_matching_attachment_ids($filters);
            $attachment_ids = array_values(array_filter($attachment_ids, function ($id) {
                return Tools_Image_Scanner::is_processable_attachment($id);
            }));
        } else {
            $attachment_ids = isset($_POST['attachment_ids']) && is_array($_POST['attachment_ids'])
                ? array_map('intval', wp_unslash($_POST['attachment_ids']))
                : [];

            $attachment_ids = array_values(array_filter($attachment_ids, function ($id) {
                return Tools_Image_Scanner::is_processable_attachment($id);
            }));

            if (!empty($filters)) {
                $attachment_ids = Tools_Image_Scanner::filter_still_matching_ids($attachment_ids, $filters);
            }
        }

        if (empty($attachment_ids)) {
            wp_send_json_error(['message' => __('No processable images selected. Supported formats: JPEG, PNG, WebP.', 'seo-booster')]);
        }

        $apply_fields = self::parse_apply_fields(
            isset($_POST['apply_fields']) && is_array($_POST['apply_fields'])
                ? wp_unslash($_POST['apply_fields'])
                : null
        );

        if (!self::has_any_apply_field($apply_fields)) {
            wp_send_json_error(['message' => __('Select at least one field to apply.', 'seo-booster')]);
        }

        self::save_user_defaults($apply_fields);

        $batch_id = 'sb_tools_' . get_current_user_id() . '_' . time();

        $batch_status = [
            'total'           => count($attachment_ids),
            'queued'          => count($attachment_ids),
            'processed'       => 0,
            'failed'          => 0,
            'processing'      => 0,
            'user_id'         => get_current_user_id(),
            'created'         => time(),
            'attachment_ids'  => $attachment_ids,
            'apply_fields'    => $apply_fields,
            'scan_filters'    => $filters,
            'process_scope'   => $process_scope,
            'processed_ids'   => [],
            'failed_ids'      => [],
            'failed_items'    => [],
            'processing_ids'  => [],
            'cancelled'       => false,
        ];

        set_transient(self::TRANSIENT_PREFIX . $batch_id, $batch_status, self::TRANSIENT_TTL);

        wp_send_json_success([
            'batch_id'        => $batch_id,
            'total'           => $batch_status['total'],
            'attachment_ids'  => $attachment_ids,
            'apply_fields'    => $apply_fields,
        ]);
    }

    /**
     * AJAX: process one image.
     *
     * @return void
     */
    public static function ajax_process_image()
    {
        check_ajax_referer('sb_tools_image_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied', 'seo-booster')]);
        }

        $batch_id = isset($_POST['batch_id']) ? sanitize_text_field(wp_unslash($_POST['batch_id'])) : '';
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;

        if (empty($batch_id) || $attachment_id <= 0) {
            wp_send_json_error(['message' => __('Invalid parameters', 'seo-booster')]);
        }

        $batch = self::get_batch($batch_id);
        if (!$batch) {
            wp_send_json_error(['message' => __('Batch not found', 'seo-booster')]);
        }

        if (!empty($batch['cancelled'])) {
            wp_send_json_error(['message' => __('Batch was cancelled', 'seo-booster')]);
        }

        if (!in_array($attachment_id, $batch['attachment_ids'], true)) {
            wp_send_json_error(['message' => __('Image not in batch', 'seo-booster')]);
        }

        self::update_batch_status($batch_id, 'processing', $attachment_id);

        try {
            $payload = self::process_image($attachment_id, $batch['apply_fields']);
            self::update_batch_status($batch_id, 'processed', $attachment_id);

            $batch = self::get_batch($batch_id);
            $payload['progress'] = self::progress_from_batch($batch);

            wp_send_json_success($payload);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if ($message === '') {
                $message = __('Image processing failed.', 'seo-booster');
            }

            self::update_batch_status($batch_id, 'failed', $attachment_id, $message);
            $batch = self::get_batch($batch_id);

            $thumb = wp_get_attachment_image_src($attachment_id, 'thumbnail');
            wp_send_json_error([
                'message'       => $message,
                'attachment_id' => $attachment_id,
                'image_title'   => get_the_title($attachment_id),
                'thumb_url'     => $thumb ? $thumb[0] : '',
                'progress'      => self::progress_from_batch($batch),
            ]);
        }
    }

    /**
     * Process single attachment: generate AI content and apply selected fields.
     *
     * @param int   $attachment_id Attachment ID.
     * @param array $apply_fields  Field flags.
     * @return array Preview payload.
     * @throws \Exception On failure.
     */
    public static function process_image($attachment_id, array $apply_fields)
    {
        if (!Tools_Image_Scanner::is_processable_attachment($attachment_id)) {
            $mime = get_post_mime_type($attachment_id);
            $mime_label = $mime ? $mime : __('unknown', 'seo-booster');
            throw new \Exception(
                sprintf(
                    /* translators: 1: MIME type, 2: supported formats list */
                    __('This file type (%1$s) is not supported. Supported formats: %2$s.', 'seo-booster'),
                    $mime_label,
                    Tools_Image_Scanner::get_processable_formats_label()
                )
            );
        }

        $before = Tools_Image_Scanner::get_attachment_meta($attachment_id);

        add_filter('sb_ai_image_attachment_context', [__CLASS__, 'filter_attachment_context'], 10, 2);
        self::$context_attachment_id = $attachment_id;

        try {
            $generated = AI_Image_Generator::generate_descriptions($attachment_id);
        } finally {
            remove_filter('sb_ai_image_attachment_context', [__CLASS__, 'filter_attachment_context'], 10);
            self::$context_attachment_id = null;
        }

        $content = [
            'title'       => $generated['title'] ?? '',
            'alt_text'    => $generated['alt_text'] ?? '',
            'caption'     => $generated['caption'] ?? '',
            'description' => $generated['description'] ?? '',
        ];

        $after = self::apply_content($attachment_id, $content, $apply_fields);

        $image_source = AI_Image_Generator::get_image_source_for_ai($attachment_id);
        $thumb_url    = '';
        $image_size   = '';

        if (!empty($generated['image_source']['public_url'])) {
            $thumb_url  = $generated['image_source']['public_url'];
            $image_size = $generated['image_source']['size'] ?? '';
        } elseif ($image_source && !empty($image_source['public_url'])) {
            $thumb_url  = $image_source['public_url'];
            $image_size = $image_source['size'] ?? '';
        } else {
            $thumb = wp_get_attachment_image_src($attachment_id, 'thumbnail');
            $thumb_url = $thumb ? $thumb[0] : '';
        }

        return [
            'attachment_id'    => $attachment_id,
            'image_title'      => get_the_title($attachment_id),
            'thumb_url'        => $thumb_url,
            'image_source_url' => $thumb_url,
            'image_size'       => $image_size,
            'before'           => $before,
            'after'            => $after,
            'apply_fields'     => $apply_fields,
            'generated'        => $content,
        ];
    }

    /**
     * Merge existing attachment meta into AI context (Tools batch only).
     *
     * @param array $context       Context array.
     * @param int   $attachment_id Attachment ID.
     * @return array
     */
    public static function filter_attachment_context($context, $attachment_id)
    {
        if (self::$context_attachment_id !== (int) $attachment_id) {
            return $context;
        }

        $meta = Tools_Image_Scanner::get_attachment_meta($attachment_id);

        $context['existing_title']       = $meta['title'];
        $context['existing_alt_text']    = $meta['alt_text'];
        $context['existing_caption']     = $meta['caption'];
        $context['existing_description'] = $meta['description'];

        return $context;
    }

    /**
     * Apply generated content for enabled fields only.
     *
     * @param int   $attachment_id Attachment ID.
     * @param array $content       Generated content.
     * @param array $apply_fields  Field flags.
     * @return array Values written (after).
     */
    public static function apply_content($attachment_id, array $content, array $apply_fields)
    {
        $after = [];

        if (!empty($apply_fields['alt_text']) && isset($content['alt_text']) && $content['alt_text'] !== '') {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($content['alt_text']));
            $after['alt_text'] = sanitize_text_field($content['alt_text']);
        }

        $update_data = ['ID' => $attachment_id];

        if (!empty($apply_fields['title']) && !empty($content['title'])) {
            $update_data['post_title'] = sanitize_text_field($content['title']);
            $after['title'] = $update_data['post_title'];
        }

        if (!empty($apply_fields['caption']) && isset($content['caption']) && $content['caption'] !== '') {
            $update_data['post_excerpt'] = sanitize_textarea_field($content['caption']);
            $after['caption'] = $update_data['post_excerpt'];
        }

        if (!empty($apply_fields['description']) && isset($content['description']) && $content['description'] !== '') {
            $update_data['post_content'] = sanitize_textarea_field($content['description']);
            $after['description'] = $update_data['post_content'];
        }

        if (count($update_data) > 1) {
            wp_update_post($update_data);
        }

        return $after;
    }

    /**
     * AJAX: batch status.
     *
     * @return void
     */
    public static function ajax_batch_status()
    {
        check_ajax_referer('sb_tools_image_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied', 'seo-booster')]);
        }

        $batch_id = isset($_POST['batch_id']) ? sanitize_text_field(wp_unslash($_POST['batch_id'])) : '';
        if (empty($batch_id)) {
            wp_send_json_error(['message' => __('Invalid batch ID', 'seo-booster')]);
        }

        $batch = self::get_batch($batch_id);
        if (!$batch) {
            wp_send_json_error(['message' => __('Batch not found', 'seo-booster')]);
        }

        wp_send_json_success(self::status_response($batch_id, $batch));
    }

    /**
     * AJAX: cancel batch.
     *
     * @return void
     */
    public static function ajax_cancel_batch()
    {
        check_ajax_referer('sb_tools_image_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied', 'seo-booster')]);
        }

        $batch_id = isset($_POST['batch_id']) ? sanitize_text_field(wp_unslash($_POST['batch_id'])) : '';
        if (empty($batch_id)) {
            wp_send_json_error(['message' => __('Invalid batch ID', 'seo-booster')]);
        }

        $batch = self::get_batch($batch_id);
        if (!$batch) {
            wp_send_json_error(['message' => __('Batch not found', 'seo-booster')]);
        }

        $batch['cancelled'] = true;
        $batch['cancelled_at'] = time();
        set_transient(self::TRANSIENT_PREFIX . $batch_id, $batch, self::TRANSIENT_TTL);

        wp_send_json_success(['message' => __('Batch cancelled', 'seo-booster')]);
    }

    /**
     * Get batch transient with user check.
     *
     * @param string $batch_id Batch ID.
     * @return array|false
     */
    private static function get_batch($batch_id)
    {
        $batch = get_transient(self::TRANSIENT_PREFIX . $batch_id);
        if (!$batch || !is_array($batch)) {
            return false;
        }

        $batch_user_id = isset($batch['user_id']) ? (int) $batch['user_id'] : 0;
        if ($batch_user_id !== get_current_user_id()) {
            return false;
        }

        return $batch;
    }

    /**
     * Update batch counters.
     *
     * @param string $batch_id      Batch ID.
     * @param string $status         processed|failed|processing.
     * @param int    $attachment_id  Attachment ID.
     * @param string $error_message  Failure message when status is failed.
     * @return void
     */
    private static function update_batch_status($batch_id, $status, $attachment_id = 0, $error_message = '')
    {
        $batch = self::get_batch($batch_id);
        if (!$batch) {
            return;
        }

        if (!isset($batch['processed_ids'])) {
            $batch['processed_ids'] = [];
        }
        if (!isset($batch['failed_ids'])) {
            $batch['failed_ids'] = [];
        }
        if (!isset($batch['failed_items'])) {
            $batch['failed_items'] = [];
        }
        if (!isset($batch['processing_ids'])) {
            $batch['processing_ids'] = [];
        }

        if ($status === 'processed') {
            $batch['processed'] = isset($batch['processed']) ? $batch['processed'] + 1 : 1;
            if ($attachment_id > 0 && !in_array($attachment_id, $batch['processed_ids'], true)) {
                $batch['processed_ids'][] = $attachment_id;
            }
            $batch['processing_ids'] = array_values(array_diff($batch['processing_ids'], [$attachment_id]));
            if (!empty($batch['processing'])) {
                $batch['processing']--;
            }
        } elseif ($status === 'failed') {
            $batch['failed'] = isset($batch['failed']) ? $batch['failed'] + 1 : 1;
            if ($attachment_id > 0 && !in_array($attachment_id, $batch['failed_ids'], true)) {
                $batch['failed_ids'][] = $attachment_id;
            }
            if ($attachment_id > 0) {
                $batch['failed_items'] = array_values(array_filter(
                    $batch['failed_items'],
                    function ($item) use ($attachment_id) {
                        return !is_array($item) || (int) ($item['attachment_id'] ?? 0) !== $attachment_id;
                    }
                ));
                $thumb = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                $batch['failed_items'][] = [
                    'attachment_id' => $attachment_id,
                    'message'       => $error_message !== '' ? $error_message : __('Image processing failed.', 'seo-booster'),
                    'image_title'   => get_the_title($attachment_id),
                    'thumb_url'     => $thumb ? $thumb[0] : '',
                    'edit_url'      => get_edit_post_link($attachment_id, 'raw') ?: '',
                ];
            }
            $batch['processing_ids'] = array_values(array_diff($batch['processing_ids'], [$attachment_id]));
            if (!empty($batch['processing'])) {
                $batch['processing']--;
            }
        } elseif ($status === 'processing') {
            $batch['processing'] = isset($batch['processing']) ? $batch['processing'] + 1 : 1;
            if ($attachment_id > 0 && !in_array($attachment_id, $batch['processing_ids'], true)) {
                $batch['processing_ids'][] = $attachment_id;
            }
        }

        set_transient(self::TRANSIENT_PREFIX . $batch_id, $batch, self::TRANSIENT_TTL);
    }

    /**
     * Build status API response.
     *
     * @param string $batch_id Batch ID.
     * @param array  $batch    Batch data.
     * @return array
     */
    private static function status_response($batch_id, array $batch)
    {
        $total = (int) ($batch['total'] ?? 0);
        $processed = (int) ($batch['processed'] ?? 0);
        $failed = (int) ($batch['failed'] ?? 0);
        $queued = (int) ($batch['queued'] ?? 0);
        $processing = (int) ($batch['processing'] ?? 0);
        $remaining = max(0, $queued - $processed - $failed);
        $cancelled = !empty($batch['cancelled']);
        $completed = !$cancelled && $remaining === 0 && $queued > 0 && $processing === 0;

        $next_attachment_id = 0;
        if (!$completed && !$cancelled && !empty($batch['attachment_ids'])) {
            $done = array_merge(
                $batch['processed_ids'] ?? [],
                $batch['failed_ids'] ?? [],
                $batch['processing_ids'] ?? []
            );
            foreach ($batch['attachment_ids'] as $id) {
                if (!in_array($id, $done, true)) {
                    $next_attachment_id = (int) $id;
                    break;
                }
            }
        }

        $failed_items = isset($batch['failed_items']) && is_array($batch['failed_items'])
            ? $batch['failed_items']
            : [];

        return [
            'batch_id'             => $batch_id,
            'total'                => $total,
            'processed'            => $processed,
            'failed'               => $failed,
            'failed_items'         => array_slice($failed_items, 0, 50),
            'failed_ids'           => array_slice($batch['failed_ids'] ?? [], 0, 50),
            'remaining'            => $remaining,
            'completed'            => $completed,
            'cancelled'            => $cancelled,
            'next_attachment_id'   => $next_attachment_id,
        ];
    }

    /**
     * Progress slice for process response.
     *
     * @param array|false $batch Batch data.
     * @return array
     */
    private static function progress_from_batch($batch)
    {
        if (!$batch) {
            return ['processed' => 0, 'total' => 0, 'failed' => 0];
        }
        return [
            'processed' => (int) ($batch['processed'] ?? 0),
            'total'     => (int) ($batch['total'] ?? 0),
            'failed'    => (int) ($batch['failed'] ?? 0),
        ];
    }

    /**
     * @param array $apply_fields Apply fields.
     * @return bool
     */
    private static function has_any_apply_field(array $apply_fields)
    {
        foreach ($apply_fields as $enabled) {
            if ($enabled) {
                return true;
            }
        }
        return false;
    }

    /**
     * Save user default apply fields.
     *
     * @param array $apply_fields Apply fields.
     * @return void
     */
    private static function save_user_defaults(array $apply_fields)
    {
        update_user_meta(get_current_user_id(), 'sb_tools_image_apply_fields', $apply_fields);
    }

    /**
     * Load saved apply fields for current user.
     *
     * @return array
     */
    public static function get_user_apply_fields()
    {
        $saved = get_user_meta(get_current_user_id(), 'sb_tools_image_apply_fields', true);
        if (is_array($saved)) {
            return self::parse_apply_fields($saved);
        }
        return self::default_apply_fields();
    }

    /**
     * Load saved scan filters for current user.
     *
     * @return string[]
     */
    public static function get_user_scan_filters()
    {
        $saved = get_user_meta(get_current_user_id(), 'sb_tools_image_scan_filters', true);
        if (is_array($saved)) {
            return array_values(array_intersect($saved, Tools_Image_Scanner::get_filter_keys()));
        }
        return ['empty_alt'];
    }

    /**
     * Save scan filters to user meta.
     *
     * @param string[] $filters Filters.
     * @return void
     */
    public static function save_user_scan_filters(array $filters)
    {
        update_user_meta(get_current_user_id(), 'sb_tools_image_scan_filters', $filters);
    }
}
