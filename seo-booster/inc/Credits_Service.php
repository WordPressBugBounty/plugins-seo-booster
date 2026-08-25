<?php
namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Credits Service
 *
 * Communicates with the SEO Booster Credits API server.
 * Handles registration, credit balance, request submission, and polling.
 *
 * @package SEO_Booster
 * @since 6.2.0
 */
class Credits_Service {

	private static $balance_cache_key = 'seobooster_credits_balance';
	private static $balance_cache_ttl = 300; // 5 minutes

	/** Credits API base URL (hardcoded). */
	private static function get_api_url() {
		return 'https://api.seoboosterpro.com';
	}

	/**
	 * Get the stored API token for the current site.
	 */
	public static function get_api_token() {
		$token = get_option( 'seobooster_credits_api_token', '' );
		return $token;
	}

	/**
	 * Make an authenticated API request.
	 */
	private static function api_request( $method, $endpoint, $body = null ) {
		$url   = self::get_api_url() . '/api/v1' . $endpoint;
		$token = self::get_api_token();

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $token,
			),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		return array(
			'success'     => $status_code >= 200 && $status_code < 300,
			'status_code' => $status_code,
			'data'        => $data,
		);
	}

	/**
	 * Register or re-register with the Credits API.
	 * Returns the API token on success.
	 */
	public static function register( $email, $site_url = null ) {
		if ( empty( $site_url ) ) {
			$site_url = home_url();
		}

		$url      = self::get_api_url() . '/api/v1/auth/register';
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'email'    => $email,
						'site_url' => $site_url,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		$status = wp_remote_retrieve_response_code( $response );

		if ( $status >= 200 && $status < 300 && ! empty( $data['api_token'] ) ) {
			update_option( 'seobooster_credits_api_token', $data['api_token'], false );
			update_option( 'seobooster_credits_user_id', $data['user_id'], false );
			self::set_cached_balance( $data['credits_balance'] ?? 0 );

			return array(
				'success' => true,
				'data'    => $data,
			);
		}

		return array(
			'success' => false,
			'error'   => $data['error'] ?? __( 'Registration failed', 'seo-booster' ),
		);
	}

	/**
	 * Check if the site is registered with the Credits API.
	 */
	public static function is_registered() {
		return ! empty( self::get_api_token() );
	}

	/**
	 * Get credit balance (cached).
	 */
	public static function get_balance( $force_refresh = false ) {
		if ( ! self::is_registered() ) {
			return 0;
		}

		if ( ! $force_refresh ) {
			$cached = get_transient( self::$balance_cache_key );
			if ( false !== $cached ) {
				return (int) $cached;
			}
		}

		$result = self::api_request( 'GET', '/credits/balance' );

		if ( $result['success'] && isset( $result['data']['credits_remaining'] ) ) {
			$balance = (int) $result['data']['credits_remaining'];
			self::set_cached_balance( $balance );
			return $balance;
		}

		// On error, return last known balance if any
		$last_known = get_option( 'seobooster_credits_last_balance', 0 );
		return (int) $last_known;
	}

	/**
	 * Cache the credit balance.
	 */
	private static function set_cached_balance( $balance ) {
		set_transient( self::$balance_cache_key, $balance, self::$balance_cache_ttl );
		update_option( 'seobooster_credits_last_balance', $balance );
	}

	/**
	 * Invalidate the balance cache.
	 */
	public static function invalidate_balance_cache() {
		delete_transient( self::$balance_cache_key );
	}

	/**
	 * Sync Freemius purchases for the current account (user token).
	 * Call after checkout so new credits appear without waiting for webhook.
	 *
	 * @return array{success: bool, credits_added?: int, credits_balance?: int, purchases_processed?: int, error?: string}
	 */
	public static function sync_purchases() {
		if ( ! self::is_registered() ) {
			return array(
				'success' => false,
				'error'   => __( 'Not registered with Credits API.', 'seo-booster' ),
			);
		}

		$result = self::api_request( 'POST', '/credits/sync-purchases', (object) array() );

		if ( $result['success'] && isset( $result['data']['credits_balance'] ) ) {
			self::set_cached_balance( $result['data']['credits_balance'] );
			return array(
				'success'             => true,
				'credits_added'       => (int) ( $result['data']['credits_added'] ?? 0 ),
				'credits_balance'     => (int) $result['data']['credits_balance'],
				'purchases_processed' => (int) ( $result['data']['purchases_processed'] ?? 0 ),
			);
		}

		return array(
			'success' => false,
			'error'   => isset( $result['data']['error'] ) ? $result['data']['error'] : __( 'Sync failed.', 'seo-booster' ),
		);
	}

	/**
	 * Get available credit packs.
	 */
	public static function get_packs() {
		$cached = get_transient( 'seobooster_credits_packs' );
		if ( false !== $cached ) {
			return $cached;
		}

		$result = self::api_request( 'GET', '/credits/packs' );
		if ( $result['success'] && ! empty( $result['data']['packs'] ) ) {
			$packs = $result['data']['packs'];
			set_transient( 'seobooster_credits_packs', $packs, 3600 );
			return $packs;
		}

		// Fallback packs when API is unreachable
		return self::get_fallback_packs();
	}

	/**
	 * Static fallback pack definitions.
	 */
	private static function get_fallback_packs() {
		return array(
			array(
				'credits' => 100,
				'price'   => '$14.99',
			),
			array(
				'credits' => 250,
				'price'   => '$29.99',
			),
			array(
				'credits' => 500,
				'price'   => '$49.99',
			),
			array(
				'credits' => 1000,
				'price'   => '$89.99',
			),
			array(
				'credits' => 2500,
				'price'   => '$179.99',
			),
		);
	}

	/**
	 * Get a checkout URL for purchasing credits.
	 */
	public static function get_checkout_url( $pricing_id = null ) {
		if ( $pricing_id ) {
			$result = self::api_request( 'GET', '/credits/checkout-url?pricing_id=' . rawurlencode( $pricing_id ) );
			if ( $result['success'] && ! empty( $result['data']['checkout_url'] ) ) {
				return $result['data']['checkout_url'];
			}
		}

		// Fallback: direct Freemius checkout URL
		$base = 'https://checkout.freemius.com/product/24720/plan/41034/';
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$base .= '?sandbox=true';
		}
		return $base;
	}

	/**
	 * Submit an AI request via the credits system.
	 *
	 * @param string $type         'seo_suggestions' or 'image_analysis'
	 * @param array  $input_data   The data to process
	 * @param string $callback_url Optional callback URL for async delivery
	 * @return array
	 */
	public static function submit_request( $type, $input_data, $callback_url = null ) {
		$body = array_merge(
			array( 'type' => $type ),
			$input_data
		);

		if ( $callback_url ) {
			$body['callback_url'] = $callback_url;
		}

		LLM_Helper::log_ai_request( 'seo-booster-credits', $type );
		$result = self::api_request( 'POST', '/requests', $body );

		if ( $result['success'] && ! empty( $result['data']['request_id'] ) ) {
			self::invalidate_balance_cache();

			if ( isset( $result['data']['credits_remaining'] ) ) {
				self::set_cached_balance( $result['data']['credits_remaining'] );
			}

			return array(
				'success'           => true,
				'request_id'        => $result['data']['request_id'],
				'credits_used'      => $result['data']['credits_used'] ?? 0,
				'credits_remaining' => $result['data']['credits_remaining'] ?? 0,
			);
		}

		// Insufficient credits
		if ( isset( $result['status_code'] ) && 402 === $result['status_code'] ) {
			return array(
				'success'           => false,
				'error'             => __( 'Insufficient credits. Please purchase more credits to continue.', 'seo-booster' ),
				'credits_remaining' => $result['data']['credits_remaining'] ?? 0,
				'credits_needed'    => $result['data']['credits_needed'] ?? 0,
				'insufficient'      => true,
			);
		}

		return array(
			'success' => false,
			'error'   => $result['data']['error'] ?? __( 'Failed to submit request', 'seo-booster' ),
		);
	}

	/**
	 * Poll for request status/results.
	 *
	 * @param string $request_id UUID
	 * @return array
	 */
	public static function get_request_status( $request_id ) {
		$request_id = (string) $request_id;
		if ( ! preg_match( '/^[A-Za-z0-9_-]{1,128}$/', $request_id ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid request ID', 'seo-booster' ),
			);
		}

		$cache_key = 'sb_credit_result_' . $request_id;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && ! empty( $cached['status'] ) ) {
			$status = (string) $cached['status'];
			if ( in_array( $status, Credits_REST_Controller::ALLOWED_STATUSES, true ) ) {
				return array(
					'success'           => true,
					'status'            => $status,
					'data'              => isset( $cached['data'] ) && is_array( $cached['data'] ) ? $cached['data'] : null,
					'error'             => null,
					'credits_remaining' => null,
				);
			}
		}

		$result = self::api_request( 'GET', '/requests/' . $request_id );

		if ( $result['success'] ) {
			$data = $result['data'];

			if ( isset( $data['credits_remaining'] ) ) {
				self::set_cached_balance( $data['credits_remaining'] );
			}

			return array(
				'success'           => true,
				'status'            => $data['status'],
				'data'              => $data['data'] ?? null,
				'error'             => $data['error'] ?? null,
				'credits_remaining' => $data['credits_remaining'] ?? null,
			);
		}

		return array(
			'success' => false,
			'error'   => $result['data']['error'] ?? __( 'Failed to check request status', 'seo-booster' ),
		);
	}

	/**
	 * Retry a queued or failed request (user-scoped; uses site API token).
	 *
	 * @param string $request_id UUID of the request.
	 * @return array{success: bool, error?: string, enqueued?: bool, request_id?: string}
	 */
	public static function retry_request( $request_id ) {
		$result = self::api_request( 'POST', '/requests/' . $request_id . '/retry', (object) array() );

		if ( $result['success'] && ! empty( $result['data']['enqueued'] ) ) {
			return array(
				'success'    => true,
				'enqueued'   => true,
				'request_id' => $request_id,
			);
		}

		return array(
			'success' => false,
			'error'   => $result['data']['error'] ?? __( 'Retry failed', 'seo-booster' ),
		);
	}

	/**
	 * Whether the SEO Booster Credits AI provider may be selected in settings.
	 *
	 * Disabled by default until public release. Enable via:
	 * add_filter( 'seobooster_credits_ai_provider_available', '__return_true' );
	 *
	 * @since 7.0.5
	 * @return bool
	 */
	public static function is_ai_provider_available() {
		return (bool) apply_filters( 'seobooster_credits_ai_provider_available', false );
	}

	/**
	 * Whether the credits provider is selected and allowed to run.
	 *
	 * @since 7.2
	 * @return bool
	 */
	public static function is_credits_provider_usable() {
		if ( ! self::is_ai_provider_available() ) {
			return false;
		}

		return LLM_Helper::get_selected_ai_provider() === 'seobooster';
	}
}
