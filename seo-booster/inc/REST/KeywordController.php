<?php
namespace Cleverplugins\SEOBooster\REST;

class KeywordController extends \WP_REST_Controller {
    public function register_routes() {
        register_rest_route('seo-booster/v1', '/keywords', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_keywords'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_keyword'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);
    }

    public function check_permission() {
        return current_user_can('manage_options');
    }
}
