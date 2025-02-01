<?php
namespace Cleverplugins\SEOBooster;

if (!defined('ABSPATH')) {
    exit;
}

class SB_Autolink_Ajax {
    public static function init() {
        add_action('wp_ajax_sb_update_keyword', [__CLASS__, 'ajax_update_keyword']);
    }


    public static function ajax_update_keyword() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'add-keyword-nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        global $wpdb;
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';

        if (!$id || (!$keyword && !$url)) {
            wp_send_json_error('Invalid data');
        }

        $data = array();
        $format = array();

        if ($keyword) {
            $data['keyword'] = $keyword;
            $format[] = '%s';
        }

        if ($url) {
            $data['url'] = $url;
            $format[] = '%s';
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'sb2_autolink',
            $data,
            array('id' => $id),
            $format,
            array('%d')
        );

        if ($result !== false) {
            Utils::log("Keyword or URL updated successfully for ID: $id", 0);
            wp_send_json_success();
        }
        Utils::log("Failed to update keyword or URL for ID: $id", 2);
        wp_send_json_error('Update failed');
    }
} 