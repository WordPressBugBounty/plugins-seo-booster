<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_GSC_Processor {

	public static function init() {
		add_action( 'sb_gsc_process_url_keywords', array( __CLASS__, 'process_url_keywords' ), 10, 1 );
		add_action( 'sb_gsc_schedule_all_pages', array( __CLASS__, 'schedule_keyword_processing_for_all_pages' ) );
		add_action( 'sb_gsc_analyze_post_keywords', array( __CLASS__, 'analyze_post_keywords' ) );
	}

	/**
	 * Process keywords for a given URL, handling batches at a time.
	 * If more keywords exist, schedules itself again for the same URL.
	 *
	 * @param string $post_url The URL of the post to process.
	 */
	public static function process_url_keywords( $post_url ) {
		global $wpdb;

		// Extract parameters from the URL if they exist
		$params = array();
		if ( strpos( $post_url, '?' ) !== false ) {
			$url_parts = explode( '?', $post_url, 2 );
			$post_url  = $url_parts[0];
			parse_str( $url_parts[1], $params );
		}

		// Get content type and item ID from parameters if they exist
		$content_type = isset( $params['content_type'] ) ? sanitize_text_field( $params['content_type'] ) : 'post';
		$item_id      = isset( $params['item_id'] ) ? absint( $params['item_id'] ) : 0;

		// Try to get post ID from URL if not provided
		$post_id = isset( $params['post_id'] ) ? absint( $params['post_id'] ) : url_to_postid( $post_url );

		// If we have an item ID but no post ID, use item ID as post ID
		if ( ! $post_id && $item_id ) {
			$post_id = $item_id;
		}

		try {
			// Get keywords for this URL
			$keywords = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}sb2_query_keywords WHERE page = %s",
					$post_url
				),
				ARRAY_A
			);

			if ( empty( $keywords ) ) {
				Utils::log(
					sprintf(
						'Completed processing all keywords for URL: %s',
						$post_url
					)
				);
				return;
			}

			$cache_response = CacheManager::fetch_and_cache_url_content(
				$post_url,
				array(
					'post_id'      => $post_id,
					'content_type' => $content_type,
					'item_id'      => $item_id,
				)
			);

			if ( empty( $cache_response['content'] ) ) {
				throw new \Exception( 'Empty content returned from CacheManager for URL: ' . $post_url );
			}

			$post_content = $cache_response['content'];

			foreach ( $keywords as $keyword ) {
				if ( empty( $keyword['id'] ) || empty( $keyword['query'] ) ) {
					Utils::log( 'Invalid keyword data: ' . wp_json_encode( $keyword ), 2 );
					continue;
				}

				try {
					$occurrences = Google_API::check_keyword_occurrences( $post_content, $keyword['query'], $post_id );
				} catch ( \Exception $e ) {
					Utils::log( 'Error checking keyword occurrences: ' . $e->getMessage(), 2 );
					throw $e;
				}

				$update_result = $wpdb->update(
					$wpdb->prefix . 'sb2_query_keywords',
					array(
						'is_used_in_content' => ! empty( $occurrences ) ? 1 : -1,
						'last_checked'       => current_time( 'mysql' ),
					),
					array( 'id' => $keyword['id'] ),
					array( '%d', '%s' ),
					array( '%d' )
				);

				if ( $update_result === false ) {
					Utils::log( 'Database update failed for keyword ID: ' . $keyword['id'] . ' - Error: ' . $wpdb->last_error, 2 );
				}
			}

			// Check if there are more keywords that need processing
			$remaining_keywords = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}sb2_query_keywords 
                WHERE page = %s 
                AND (last_checked IS NULL OR last_checked < DATE_SUB(NOW(), INTERVAL 1 DAY))",
					$post_url
				)
			);

			if ( $remaining_keywords > 0 ) {
				as_schedule_single_action(
					time() + 5, // Schedule 5 seconds from now
					'sb_gsc_process_url_keywords',
					array( 'post_url' => $post_url ),
					'seo-booster'
				);
			} else {
				// All keywords processed, now trigger SEO analysis if needed
				self::trigger_seo_analysis_for_url( $post_url );
			}

			return true;

		} catch ( \Exception $e ) {
			Utils::log( 'Error in process_url_keywords: ' . $e->getMessage(), 2 );
			throw $e;
		}
	}

	/**
	 * Schedule keyword processing for all pages.
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Wednesday, May 7th, 2025.
	 * @access  public static
	 * @return  void
	 */
	public static function schedule_keyword_processing_for_all_pages() {
		global $wpdb;

		// Get unique pages that have keywords
		$unique_pages = $wpdb->get_col(
			"SELECT DISTINCT page 
            FROM {$wpdb->prefix}sb2_query_keywords"
		);

		// Clear all keyword statuses in one query
		$wpdb->query(
			"UPDATE {$wpdb->prefix}sb2_query_keywords 
            SET is_used_in_content = NULL, 
                last_checked = NULL"
		);

		// Delete all existing scheduled actions for our hook, except those currently being processed
		$sql = "DELETE FROM {$wpdb->prefix}actionscheduler_actions 
            WHERE (hook = 'sb_gsc_process_url_keywords' OR hook = 'sb_gsc_process_keywords_batch')
            AND status != 'in-progress'";
		$wpdb->query( $sql );

		// Schedule new actions for each URL
		foreach ( $unique_pages as $page_url ) {
			as_schedule_single_action(
				time(),
				'sb_gsc_process_url_keywords',
				array( 'post_url' => $page_url ),
				'seo-booster'
			);
		}
		Utils::log( 'Scheduled keyword processing for all pages', 5 );
	}

	public static function analyze_post_keywords( $post_id ) {
		$post_url = get_permalink( $post_id );
		if ( ! $post_url ) {
			Utils::log( 'Failed to get URL for post ID: ' . $post_id, 2 );
			return;
		}

		global $wpdb;

		// Clear keyword statuses for this URL in one query
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}sb2_query_keywords 
            SET is_used_in_content = NULL, 
                last_checked = NULL 
            WHERE page = %s",
				$post_url
			)
		);

		// Clear scheduled actions for this URL in one query
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->actionscheduler_actions} 
            WHERE hook = 'sb_gsc_process_url_keywords' 
            AND args LIKE %s",
				'%' . $wpdb->esc_like( '"post_url":"' . $post_url . '"' ) . '%'
			)
		);

		// Schedule the initial processing
		as_schedule_single_action(
			time(),
			'sb_gsc_process_url_keywords',
			array( 'post_url' => $post_url ),
			'seo-booster'
		);

		Utils::log( 'Scheduled keyword analysis for post ID: ' . $post_id, 5 );
	}

	/**
	 * Trigger SEO analysis for a URL if needed.
	 *
	 * @since 6.1.26
	 * @param string $url The URL to analyze.
	 * @return void
	 */
	private static function trigger_seo_analysis_for_url( $url ) {
		global $wpdb;

		$analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
		$urls_table     = $wpdb->prefix . 'sb2_seo_urls';

		// Check if analysis already exists and is recent (within 24 hours)
		$existing_analysis = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.id, a.analyzed_at FROM {$analysis_table} a
             JOIN {$urls_table} u ON a.url_id = u.id
             WHERE u.url = %s AND a.status = 'analyzed' 
             AND a.analyzed_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
             ORDER BY a.analyzed_at DESC LIMIT 1",
				$url
			)
		);

		if ( $existing_analysis ) {
			// Analysis is recent, no need to re-analyze
			return;
		}

		// Try to find object_id and object_type from URL
		$object_id   = null;
		$object_type = null;

		// Check if it's a post URL
		$post_id = url_to_postid( $url );
		if ( $post_id ) {
			$object_id   = $post_id;
			$object_type = 'post';
		} else {
			// Check if it's a term URL
			$term = get_term_by( 'slug', basename( $url ) );
			if ( $term && ! is_wp_error( $term ) ) {
				$object_id   = $term->term_id;
				$object_type = 'term';
			}
		}

		// Schedule SEO analysis
		as_schedule_single_action(
			time() + 10, // Schedule 10 seconds from now
			'sb_analyze_seo_for_url',
			array(
				'url'         => $url,
				'object_id'   => $object_id,
				'object_type' => $object_type,
			),
			'seo-booster'
		);
	}
}

SB_GSC_Processor::init();
