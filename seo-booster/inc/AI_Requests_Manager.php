<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AI_Requests_Manager
 *
 * Handles AI endpoint requests and responses for SEO analysis.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */
class AI_Requests_Manager {

	/**
	 * Create a new AI request record for SEO recommendations.
	 *
	 * @since 6.1.26
	 * @param int $url_id URL ID from sb2_seo_urls table.
	 * @param int|null $object_id Post or term ID.
	 * @param string|null $object_type 'post', 'term', or null.
	 * @param string $request_type Type of AI request (e.g., 'seo_recommendations', 'content_optimization').
	 * @param array $request_data Data to send to AI endpoint (URL, content, current SEO data).
	 * @return int|false Request ID on success, false on failure.
	 */
	public static function create_request( $url_id, $object_id, $object_type, $request_type, $request_data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'sb2_ai_requests';

		$result = $wpdb->insert(
			$table_name,
			array(
				'url_id'       => $url_id,
				'object_id'    => $object_id,
				'object_type'  => $object_type,
				'request_type' => $request_type,
				'request_data' => wp_json_encode( $request_data ),
				'status'       => 'pending',
			)
		);

		if ( $result === false ) {
			Utils::log( 'Failed to create AI request record', 2 );
			return false;
		}

		$request_id = $wpdb->insert_id;
		Utils::log( "Created AI request #{$request_id} for URL ID {$url_id}", 3 );

		return $request_id;
	}

	/**
	 * Update AI request with response data.
	 *
	 * @since 6.1.26
	 * @param int $request_id Request ID to update.
	 * @param array $response_data Response from AI endpoint.
	 * @param string $status Request status ('completed', 'failed').
	 * @param string|null $error_message Error message if failed.
	 * @return bool True on success, false on failure.
	 */
	public static function update_request( $request_id, $response_data, $status = 'completed', $error_message = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'sb2_ai_requests';

		$update_data = array(
			'response_data' => wp_json_encode( $response_data ),
			'status'        => $status,
			'processed_at'  => current_time( 'mysql' ),
		);

		if ( $error_message ) {
			$update_data['error_message'] = $error_message;
		}

		$result = $wpdb->update( $table_name, $update_data, array( 'id' => $request_id ) );

		if ( $result === false ) {
			Utils::log( "Failed to update AI request #{$request_id}", 2 );
			return false;
		}

		Utils::log( "Updated AI request #{$request_id} with status: {$status}", 3 );
		return true;
	}

	/**
	 * Get pending AI requests.
	 *
	 * @since 6.1.26
	 * @param int $limit Maximum number of requests to return.
	 * @return array Array of pending request objects.
	 */
	public static function get_pending_requests( $limit = 10 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'sb2_ai_requests';
		$urls_table = $wpdb->prefix . 'sb2_seo_urls';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, u.url, u.post_title 
             FROM {$table_name} r 
             JOIN {$urls_table} u ON r.url_id = u.id 
             WHERE r.status = 'pending' 
             ORDER BY r.created_at ASC 
             LIMIT %d",
				$limit
			)
		);

		return $results ?: array();
	}

	/**
	 * Get AI recommendations for a specific URL.
	 *
	 * @since 6.1.26
	 * @param int $url_id URL ID from sb2_seo_urls table.
	 * @param string $request_type Type of recommendations to get.
	 * @return array Array of recommendation objects.
	 */
	public static function get_url_recommendations( $url_id, $request_type = 'seo_recommendations' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'sb2_ai_requests';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} 
             WHERE url_id = %d 
             AND request_type = %s 
             AND status = 'completed' 
             ORDER BY processed_at DESC",
				$url_id,
				$request_type
			)
		);

		$recommendations = array();
		foreach ( $results as $result ) {
			$response_data = json_decode( $result->response_data, true );
			if ( $response_data && isset( $response_data['recommendations'] ) ) {
				$recommendations[] = array(
					'id'              => $result->id,
					'recommendations' => $response_data['recommendations'],
					'processed_at'    => $result->processed_at,
					'request_data'    => json_decode( $result->request_data, true ),
				);
			}
		}

		return $recommendations;
	}

	/**
	 * Get AI request by ID.
	 *
	 * @since 6.1.26
	 * @param int $request_id Request ID.
	 * @return object|null Request object or null if not found.
	 */
	public static function get_request( $request_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'sb2_ai_requests';
		$urls_table = $wpdb->prefix . 'sb2_seo_urls';

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT r.*, u.url, u.post_title 
             FROM {$table_name} r 
             JOIN {$urls_table} u ON r.url_id = u.id 
             WHERE r.id = %d",
				$request_id
			)
		);

		return $result;
	}

	/**
	 * Get AI requests with filters.
	 *
	 * @since 6.1.26
	 * @param array $filters Filter options.
	 * @return array Array of request objects.
	 */
	public static function get_requests( $filters = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'sb2_ai_requests';
		$urls_table = $wpdb->prefix . 'sb2_seo_urls';

		$where_conditions = array( '1=1' );
		$where_values     = array();

		// Status filter
		if ( ! empty( $filters['status'] ) ) {
			$where_conditions[] = 'r.status = %s';
			$where_values[]     = $filters['status'];
		}

		// Request type filter
		if ( ! empty( $filters['request_type'] ) ) {
			$where_conditions[] = 'r.request_type = %s';
			$where_values[]     = $filters['request_type'];
		}

		// Date range filter
		if ( ! empty( $filters['date_from'] ) ) {
			$where_conditions[] = 'r.created_at >= %s';
			$where_values[]     = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_conditions[] = 'r.created_at <= %s';
			$where_values[]     = $filters['date_to'];
		}

		// Search filter
		if ( ! empty( $filters['search'] ) ) {
			$where_conditions[] = '(u.url LIKE %s OR u.post_title LIKE %s OR r.request_type LIKE %s)';
			$search_term        = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where_values[]     = $search_term;
			$where_values[]     = $search_term;
			$where_values[]     = $search_term;
		}

		$where_clause = implode( ' AND ', $where_conditions );

		$sql = "SELECT r.*, u.url, u.post_title
                FROM {$table_name} r 
                JOIN {$urls_table} u ON r.url_id = u.id 
                WHERE {$where_clause} 
                ORDER BY r.created_at DESC";

		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, $where_values );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get AI request statistics.
	 *
	 * @since 6.1.26
	 * @return array Statistics array.
	 */
	public static function get_stats() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'sb2_ai_requests';

		$stats = array(
			'total_requests' => 0,
			'pending'        => 0,
			'completed'      => 0,
			'failed'         => 0,
			'by_type'        => array(),
		);

		// Get total counts by status
		$status_counts = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$table_name} GROUP BY status"
		);

		foreach ( $status_counts as $row ) {
			$stats[ $row->status ]    = intval( $row->count );
			$stats['total_requests'] += intval( $row->count );
		}

		// Get counts by request type
		$type_counts = $wpdb->get_results(
			"SELECT request_type, COUNT(*) as count FROM {$table_name} GROUP BY request_type"
		);

		foreach ( $type_counts as $row ) {
			$stats['by_type'][ $row->request_type ] = intval( $row->count );
		}

		return $stats;
	}

	/**
	 * Clean up old AI requests.
	 *
	 * @since 6.1.26
	 * @param int $days_old Number of days old to consider for cleanup.
	 * @return int Number of records deleted.
	 */
	public static function cleanup_old_requests( $days_old = 30 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'sb2_ai_requests';

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY) 
             AND status IN ('completed', 'failed')",
				$days_old
			)
		);

		if ( $deleted > 0 ) {
			Utils::log( "Cleaned up {$deleted} old AI requests", 3 );
		}

		return $deleted;
	}

	/**
	 * Send request to AI endpoint.
	 *
	 * @since 6.1.26
	 * @param int $request_id Request ID to process.
	 * @return bool True on success, false on failure.
	 */
	public static function process_request( $request_id ) {
		$request = self::get_request( $request_id );

		if ( ! $request ) {
			Utils::log( "AI request #{$request_id} not found", 2 );
			return false;
		}

		if ( $request->status !== 'pending' ) {
			Utils::log( "AI request #{$request_id} is not pending", 2 );
			return false;
		}

		$request_data = json_decode( $request->request_data, true );

		if ( ! $request_data ) {
			self::update_request( $request_id, array(), 'failed', 'Invalid request data' );
			return false;
		}

		// Make HTTP request to AI endpoint
		$ai_endpoint_url = get_option( 'seobooster_ai_endpoint_url', '' );

		if ( empty( $ai_endpoint_url ) ) {
			self::update_request( $request_id, array(), 'failed', 'AI endpoint URL not configured' );
			return false;
		}

		// Example implementation (replace with your actual AI endpoint logic)
		$response = wp_remote_post(
			$ai_endpoint_url,
			array(
				'body'    => wp_json_encode( $request_data ),
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . get_option( 'seobooster_ai_api_key', '' ),
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			self::update_request( $request_id, array(), 'failed', $error_message );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code !== 200 ) {
			self::update_request( $request_id, array(), 'failed', "HTTP {$response_code}: {$response_body}" );
			return false;
		}

		$response_data = json_decode( $response_body, true );

		if ( ! $response_data ) {
			self::update_request( $request_id, array(), 'failed', 'Invalid response from AI endpoint' );
			return false;
		}

		// Update request with successful response
		self::update_request( $request_id, $response_data, 'completed' );

		Utils::log( "Successfully processed AI request #{$request_id}", 3 );
		return true;
	}

	/**
	 * Create SEO recommendations request for a URL.
	 *
	 * @since 6.1.26
	 * @param string $url The URL to analyze.
	 * @param int|null $object_id Post or term ID.
	 * @param string|null $object_type 'post', 'term', or null.
	 * @param array $seo_data Current SEO analysis data.
	 * @param string $content Page content to analyze.
	 * @return int|false Request ID on success, false on failure.
	 */
	public static function create_seo_recommendations_request( $url, $object_id, $object_type, $seo_data, $content ) {
		// First, get or create URL record
		global $wpdb;
		$urls_table = $wpdb->prefix . 'sb2_seo_urls';
		$url_hash   = hash( 'sha256', $url );

		$url_record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$urls_table} WHERE url_hash = %s",
				$url_hash
			)
		);

		if ( ! $url_record ) {
			// Create URL record if it doesn't exist
			$post_title = null;
			if ( $object_id && $object_type === 'post' ) {
				$post       = get_post( $object_id );
				$post_title = $post ? $post->post_title : null;
			} elseif ( $object_id && $object_type === 'term' ) {
				$term       = get_term( $object_id );
				$post_title = $term && ! is_wp_error( $term ) ? $term->name : null;
			}

			$wpdb->insert(
				$urls_table,
				array(
					'url'         => $url,
					'url_hash'    => $url_hash,
					'object_id'   => $object_id,
					'object_type' => $object_type,
					'post_title'  => $post_title,
					'created_at'  => current_time( 'mysql' ),
				)
			);

			$url_id = $wpdb->insert_id;
		} else {
			$url_id = $url_record->id;
		}

		// Prepare request data for AI endpoint
		$request_data = array(
			'url'          => $url,
			'content'      => $content,
			'seo_analysis' => $seo_data,
			'object_id'    => $object_id,
			'object_type'  => $object_type,
			'timestamp'    => current_time( 'mysql' ),
		);

		// Create AI request
		return self::create_request( $url_id, $object_id, $object_type, 'seo_recommendations', $request_data );
	}
}
