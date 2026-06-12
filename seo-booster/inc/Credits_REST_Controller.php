<?php
namespace Cleverplugins\SEOBooster;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Credits REST Controller
 *
 * Provides a WP REST API endpoint for the Credits API server
 * to push completed results back to the plugin asynchronously.
 *
 * @package SEO_Booster
 * @since 6.2.0
 */
class Credits_REST_Controller {

    const NAMESPACE = 'seo-booster/v1';

    /**
     * Initialize the REST route.
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    /**
     * Register REST API routes.
     */
    public static function register_routes() {
        register_rest_route(self::NAMESPACE, '/callback', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_callback'),
            'permission_callback' => array(__CLASS__, 'verify_callback'),
        ));

        register_rest_route(self::NAMESPACE, '/credits/balance', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_balance'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        register_rest_route(self::NAMESPACE, '/credits/register', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'register_account'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ));

        register_rest_route(self::NAMESPACE, '/credits/sync-purchases', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'sync_purchases'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ));
    }

    /**
     * Verify the callback request comes from our API server.
     */
    public static function verify_callback(\WP_REST_Request $request) {
        $secret = $request->get_header('X-SB-Callback-Secret');
        $expected = get_option('seobooster_credits_callback_secret', '');

        if (empty($expected) || empty($secret)) {
            return false;
        }

        return hash_equals($expected, $secret);
    }

    /**
     * Handle async callback from the API server.
     */
    public static function handle_callback(\WP_REST_Request $request) {
        $body = $request->get_json_params();
        $request_id = $body['request_id'] ?? '';
        $status = $body['status'] ?? '';
        $data = $body['data'] ?? null;

        if (empty($request_id)) {
            return new \WP_REST_Response(array('error' => 'Missing request_id'), 400);
        }

        // Store the result for later retrieval
        $cache_key = 'sb_credit_result_' . $request_id;
        set_transient($cache_key, array(
            'status' => $status,
            'data'   => $data,
        ), 3600); // Cache for 1 hour

        Credits_Service::invalidate_balance_cache();

        return new \WP_REST_Response(array('received' => true), 200);
    }

    /**
     * Get credit balance via REST.
     */
    public static function get_balance(\WP_REST_Request $request) {
        $balance = Credits_Service::get_balance(true);

        return new \WP_REST_Response(array(
            'credits_remaining' => $balance,
            'is_registered'     => Credits_Service::is_registered(),
        ), 200);
    }

    /**
     * Register the site with the Credits API.
     */
    public static function register_account(\WP_REST_Request $request) {
        $email = $request->get_param('email');

        if (empty($email) || !is_email($email)) {
            return new \WP_REST_Response(array('error' => 'Valid email is required'), 400);
        }

        $callback_secret = wp_generate_password(32, false);
        update_option('seobooster_credits_callback_secret', $callback_secret);

        $result = Credits_Service::register($email, home_url());

        if ($result['success']) {
            return new \WP_REST_Response(array(
                'success'         => true,
                'credits_balance' => $result['data']['credits_balance'] ?? 0,
            ), 200);
        }

        return new \WP_REST_Response(array(
            'success' => false,
            'error'   => $result['error'],
        ), 400);
    }

    /**
     * Sync Freemius purchases for the current account (so new credits appear after checkout).
     */
    public static function sync_purchases(\WP_REST_Request $request) {
        $result = Credits_Service::sync_purchases();

        if ($result['success']) {
            return new \WP_REST_Response(array(
                'success'              => true,
                'credits_added'        => $result['credits_added'],
                'credits_balance'      => $result['credits_balance'],
                'purchases_processed'  => $result['purchases_processed'],
            ), 200);
        }

        return new \WP_REST_Response(array(
            'success' => false,
            'error'   => $result['error'],
        ), 400);
    }
}
