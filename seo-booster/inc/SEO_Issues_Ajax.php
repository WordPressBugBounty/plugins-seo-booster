<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SEO_Issues_Ajax
 *
 * Handles AJAX requests for the SEO Possibilities page.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */
class SEO_Issues_Ajax {

	/**
	 * Initialize AJAX handlers.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_sb_analyze_direct', array( __CLASS__, 'ajax_analyze_direct' ) );
		add_action( 'wp_ajax_sb_get_analysis_progress', array( __CLASS__, 'ajax_get_analysis_progress' ) );
		add_action( 'wp_ajax_sb_search_possibilities', array( __CLASS__, 'ajax_search_possibilities' ) );
		add_action( 'wp_ajax_sb_stop_analysis', array( __CLASS__, 'ajax_stop_analysis' ) );
		add_action( 'wp_ajax_sb_get_current_analysis', array( __CLASS__, 'ajax_get_current_analysis' ) );
		add_action( 'wp_ajax_sb_get_url_issues', array( __CLASS__, 'ajax_get_url_possibilities' ) );
		add_action( 'wp_ajax_sb_run_sitewide_analysis', array( __CLASS__, 'ajax_run_sitewide_analysis' ) );
	}


	/**
	 * AJAX handler for direct analysis (immediate execution).
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function ajax_analyze_direct() {
		Utils::log( 'AJAX analyze_direct called', 5 );

		check_ajax_referer( 'sb_seo_issues_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			Utils::log( 'Permission denied for analyze_direct', 2 );
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$batch_size = get_option( 'seobooster_seo_analysis_batch_size', 1 ); // Use configurable batch size

		// Get current stats BEFORE processing
		$stats_before = SEO_Issues_Manager::get_analysis_stats();

		$urls                  = SEO_Issues_Manager::get_publishable_urls();
		$urls_to_analyze_count = count( $urls );

		Utils::log( 'Found ' . $urls_to_analyze_count . ' URLs to analyze', 5 );

		if ( empty( $urls ) ) {
			Utils::log( 'No URLs to analyze - all content analyzed', 5 );
			wp_send_json_success(
				array(
					'message'             => __( 'All content has been analyzed!', 'seo-booster' ),
					'completed'           => true,
					'remaining'           => 0,
					'stats'               => $stats_before,
					'analyzed'            => $stats_before['total_analyzed'],
					'pending'             => $stats_before['total_pending'],
					'progress_percentage' => 100,
				)
			);
		}

		// Calculate total URLs: already analyzed + URLs that need to be analyzed
		$total_urls = $stats_before['total_analyzed'] + $urls_to_analyze_count;

		// Take first batch and execute immediately
		$batch         = array_slice( $urls, 0, $batch_size );
		$processed     = 0;
		$current_url   = '';
		$current_title = '';

		Utils::log( 'Processing batch of ' . count( $batch ) . ' URLs', 5 );

		foreach ( $batch as $url_data ) {
			try {
				$current_url   = $url_data['url'];
				$current_title = $url_data['title'] ?? basename( parse_url( $current_url, PHP_URL_PATH ) );

				Utils::log( 'Starting analysis for URL: ' . $current_url, 5 );

				// Execute analysis directly instead of scheduling
				seobooster2::process_seo_analysis_for_url( $url_data );
				++$processed;

				Utils::log( 'Direct SEO analysis completed for URL: ' . $current_url, 5 );
			} catch ( \Exception $e ) {
				Utils::log( 'Direct SEO analysis failed for URL: ' . $current_url . ' - ' . $e->getMessage(), 2 );
			}
		}

		// Recalculate remaining URLs after processing
		$remaining_urls = SEO_Issues_Manager::get_publishable_urls();
		$remaining      = count( $remaining_urls );

		// Get current stats for the response AFTER processing
		$stats = SEO_Issues_Manager::get_analysis_stats();

		// Calculate progress percentage: analyzed / total (clamped to 0-100)
		// Total = analyzed before + URLs that needed analysis
		if ( $total_urls > 0 ) {
			$progress_percentage = round( ( $stats['total_analyzed'] / $total_urls ) * 100 );
			// Clamp to 0-100 to prevent display issues
			$progress_percentage = max( 0, min( 100, $progress_percentage ) );
		} else {
			$progress_percentage = 100;
		}

		Utils::log( 'Analysis batch complete. Processed: ' . $processed . ', Remaining: ' . $remaining, 5 );

		wp_send_json_success(
			array(
				'message'             => sprintf( __( 'Directly analyzed %1$d URLs. %2$d remaining.', 'seo-booster' ), $processed, $remaining ),
				'processed'           => $processed,
				'remaining'           => $remaining,
				'completed'           => $remaining <= 0,
				'stats'               => $stats,
				'analyzed'            => $stats['total_analyzed'],
				'pending'             => $stats['total_pending'],
				'progress_percentage' => $progress_percentage,
				'current_url'         => $current_url,
				'current_title'       => $current_title,
			)
		);
	}

	/**
	 * AJAX handler for getting analysis progress.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function ajax_get_analysis_progress() {
		check_ajax_referer( 'sb_seo_issues_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$stats      = SEO_Issues_Manager::get_analysis_stats();
		$urls       = SEO_Issues_Manager::get_publishable_urls();
		$total_urls = count( $urls );

		// Calculate progress percentage based on actual analyzed vs total
		$progress_percentage = 0;
		if ( $total_urls > 0 ) {
			$progress_percentage = round( ( $stats['total_analyzed'] / $total_urls ) * 100 );
		} elseif ( $stats['total_analyzed'] > 0 ) {
			$progress_percentage = 100; // If no pending URLs but we have analyzed some, we're done
		}

		// Debug: Log the stats to see what's happening
		Utils::log( 'Analysis progress stats: ' . wp_json_encode( $stats ), 5 );
		Utils::log( 'Total URLs: ' . $total_urls . ', Progress: ' . $progress_percentage . '%', 5 );

		// Get current analysis status
		$current_analysis = null;
		$pending_count    = 0;

		if ( function_exists( 'as_has_scheduled_action' ) ) {
			global $wpdb;

			// Validate table name
			$actions_table  = $wpdb->prefix . 'actionscheduler_actions';
			$allowed_tables = array( $wpdb->prefix . 'actionscheduler_actions' );

			if ( in_array( $actions_table, $allowed_tables, true ) ) {
				// Get currently running analysis (if any)
				$running_actions = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$actions_table} 
                         WHERE hook = %s 
                         AND status = %s 
                         ORDER BY scheduled_date_gmt ASC 
                         LIMIT 1",
						'sb_analyze_seo_for_url',
						'in-progress'
					)
				);

				if ( ! empty( $running_actions ) ) {
					$action = $running_actions[0];
					$args   = maybe_unserialize( $action->args );
					if ( is_array( $args ) && isset( $args[0] ) ) {
						$args = $args[0];
					}

					$current_analysis = array(
						'url'         => isset( $args['url'] ) ? esc_url( $args['url'] ) : '',
						'object_id'   => isset( $args['object_id'] ) ? intval( $args['object_id'] ) : null,
						'object_type' => isset( $args['object_type'] ) ? sanitize_text_field( $args['object_type'] ) : null,
						'started_at'  => $action->scheduled_date_gmt,
					);
				}

				// Get pending count
				$pending_count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$actions_table} 
                         WHERE hook = %s 
                         AND status = %s",
						'sb_analyze_seo_for_url',
						'pending'
					)
				);
			} else {
				$pending_count = 0;
			}
		}

		// Get recent analysis activity
		global $wpdb;
		$analysis_table = $wpdb->prefix . 'sb2_seo_analysis';
		$urls_table     = $wpdb->prefix . 'sb2_seo_urls';

		// Validate table names
		$allowed_tables = array(
			$wpdb->prefix . 'sb2_seo_analysis',
			$wpdb->prefix . 'sb2_seo_urls',
		);

		if ( in_array( $analysis_table, $allowed_tables, true ) && in_array( $urls_table, $allowed_tables, true ) ) {
			$recent_analysis = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT u.url, u.post_title, a.analyzed_at 
                     FROM {$analysis_table} a
                     JOIN {$urls_table} u ON a.url_id = u.id
                     WHERE a.status = %s 
                     ORDER BY a.analyzed_at DESC 
                     LIMIT 5",
					'analyzed'
				)
			);
		} else {
			$recent_analysis = array();
		}

		$recent_items = array();
		foreach ( $recent_analysis as $item ) {
			$title          = ! empty( $item->post_title ) ? esc_html( $item->post_title ) : esc_html( basename( $item->url ) );
			$recent_items[] = array(
				'title' => $title,
				'url'   => esc_url( $item->url ),
				'time'  => esc_html( human_time_diff( strtotime( $item->analyzed_at ), current_time( 'timestamp' ) ) . ' ago' ),
			);
		}

		wp_send_json_success(
			array(
				'stats'               => $stats,
				'total_urls'          => $total_urls,
				'analyzed'            => $stats['total_analyzed'],
				'pending'             => $stats['total_pending'],
				'recent_analysis'     => $recent_items,
				'progress_percentage' => $progress_percentage,
				'current_analysis'    => $current_analysis,
				'pending_count'       => (int) $pending_count,
				'is_running'          => ! empty( $current_analysis ),
			)
		);
	}


	/**
	 * AJAX handler for searching issues.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function ajax_search_possibilities() {
		check_ajax_referer( 'sb_seo_issues_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$search_term = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
		$severity    = isset( $_POST['severity'] ) ? sanitize_text_field( $_POST['severity'] ) : '';
		$status      = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';

		$filters = array_filter(
			array(
				'search'   => $search_term,
				'severity' => $severity,
				'status'   => $status,
			)
		);

		$issues = SEO_Issues_Manager::get_issues( $filters );

		// Format results for display
		$formatted_issues = array();
		foreach ( $issues as $issue ) {
			$formatted_issues[] = array(
				'id'       => intval( $issue->id ),
				'message'  => esc_html( $issue->message ),
				'url'      => esc_url( $issue->url ),
				'title'    => ! empty( $issue->post_title ) ? esc_html( $issue->post_title ) : esc_html( basename( $issue->url ) ),
				'severity' => esc_html( $issue->severity ),
				'status'   => esc_html( $issue->user_status ),
			);
		}

		wp_send_json_success(
			array(
				'issues'        => $formatted_issues,
				'possibilities' => $formatted_issues,
				'count'         => count( $formatted_issues ),
			)
		);
	}

	/**
	 * AJAX handler for stopping analysis.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function ajax_stop_analysis() {
		check_ajax_referer( 'sb_seo_issues_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		// Cancel all pending SEO analysis actions
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			$cancelled = as_unschedule_all_actions( 'sb_analyze_seo_for_url', array(), 'seo-booster' );

			wp_send_json_success(
				array(
					'message'   => sprintf( __( 'Analysis stopped. %d pending actions cancelled.', 'seo-booster' ), $cancelled ),
					'cancelled' => $cancelled,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Action Scheduler not available', 'seo-booster' ) ) );
		}
	}

	/**
	 * AJAX handler for getting current analysis status.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function ajax_get_current_analysis() {
		check_ajax_referer( 'sb_seo_issues_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$current_analysis = null;
		$pending_count    = 0;

		if ( function_exists( 'as_has_scheduled_action' ) ) {
			global $wpdb;

			// Validate table name
			$actions_table  = $wpdb->prefix . 'actionscheduler_actions';
			$allowed_tables = array( $wpdb->prefix . 'actionscheduler_actions' );

			if ( in_array( $actions_table, $allowed_tables, true ) ) {
				// Get currently running analysis (if any)
				$running_actions = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$actions_table} 
                         WHERE hook = %s 
                         AND status = %s 
                         ORDER BY scheduled_date_gmt ASC 
                         LIMIT 1",
						'sb_analyze_seo_for_url',
						'in-progress'
					)
				);

				if ( ! empty( $running_actions ) ) {
					$action = $running_actions[0];
					$args   = maybe_unserialize( $action->args );
					if ( is_array( $args ) && isset( $args[0] ) ) {
						$args = $args[0];
					}

					$current_analysis = array(
						'url'         => isset( $args['url'] ) ? esc_url( $args['url'] ) : '',
						'object_id'   => isset( $args['object_id'] ) ? intval( $args['object_id'] ) : null,
						'object_type' => isset( $args['object_type'] ) ? sanitize_text_field( $args['object_type'] ) : null,
						'started_at'  => $action->scheduled_date_gmt,
					);
				}

				// Get pending count
				$pending_count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$actions_table} 
                         WHERE hook = %s 
                         AND status = %s",
						'sb_analyze_seo_for_url',
						'pending'
					)
				);
			} else {
				$pending_count = 0;
			}
		}

		wp_send_json_success(
			array(
				'current_analysis' => $current_analysis,
				'pending_count'    => (int) $pending_count,
				'is_running'       => ! empty( $current_analysis ),
			)
		);
	}

	/**
	 * AJAX handler for getting issues for a specific URL.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function ajax_get_url_possibilities() {
		check_ajax_referer( 'sb_seo_issues_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$url_id = isset( $_POST['url_id'] ) ? intval( $_POST['url_id'] ) : 0;

		if ( ! $url_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid URL ID', 'seo-booster' ) ) );
		}

		$buckets = SEO_Issues_Manager::get_issues_for_url( $url_id );

		wp_send_json_success(
			array(
				'issues'        => $buckets['issues'],
				'opportunities' => $buckets['opportunities'],
				'possibilities' => array_merge( $buckets['issues'], $buckets['opportunities'] ),
			)
		);
	}

	/**
	 * AJAX handler for running sitewide SEO analysis.
	 *
	 * @since 7.0.0
	 * @return void
	 */
	public static function ajax_run_sitewide_analysis() {
		check_ajax_referer( 'sb_seo_issues_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		try {
			// Run sitewide analysis
			$sitewide_analysis = new \Cleverplugins\SEOBooster\SEO_Sitewide_Analysis();
			$results           = $sitewide_analysis->analyze();

			// Save results to database
			$analysis_id = SEO_Issues_Manager::save_sitewide_analysis_to_db( $results );

			if ( $analysis_id === false ) {
				wp_send_json_error( array( 'message' => __( 'Failed to save sitewide analysis results', 'seo-booster' ) ) );
			}

			// Get updated stats
			$stats = SEO_Issues_Manager::get_sitewide_stats();

			wp_send_json_success(
				array(
					'message'     => __( 'Sitewide SEO analysis completed successfully', 'seo-booster' ),
					'results'     => $results,
					'stats'       => $stats,
					'analysis_id' => $analysis_id,
				)
			);
		} catch ( \Exception $e ) {
			Utils::log( 'Sitewide analysis failed: ' . $e->getMessage(), 2 );
			wp_send_json_error( array( 'message' => __( 'Sitewide analysis failed: ', 'seo-booster' ) . $e->getMessage() ) );
		}
	}
}

SEO_Issues_Ajax::init();
