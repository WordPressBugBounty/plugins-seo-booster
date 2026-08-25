<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_SEO_Analysis
 *
 * Handles bulk SEO analysis for post types with topbar progress display.
 *
 * @package Cleverplugins\SEOBooster
 * @since 7.0.3
 */
class Bulk_SEO_Analysis {

	/**
	 * Initialize the class and set up hooks.
	 *
	 * @since 7.0.3
	 * @return void
	 */
	public static function init() {
		// Add bulk actions for all public post types
		add_action( 'admin_init', array( __CLASS__, 'add_bulk_actions_for_post_types' ) );

		// AJAX handlers
		add_action( 'wp_ajax_sb_get_bulk_seo_status', array( __CLASS__, 'ajax_get_bulk_status' ) );
		add_action( 'wp_ajax_sb_process_bulk_seo_analysis', array( __CLASS__, 'ajax_process_bulk_analysis' ) );
		add_action( 'wp_ajax_sb_cancel_bulk_seo_analysis', array( __CLASS__, 'ajax_cancel_bulk_analysis' ) );

		// Enqueue scripts for post list pages
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Add bulk actions for all public post types.
	 *
	 * @since 7.0.3
	 * @return void
	 */
	public static function add_bulk_actions_for_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			// Skip attachments (handled by Media_Library_Enhancements)
			if ( 'attachment' === $post_type ) {
				continue;
			}

			// Add bulk action filter
			add_filter( "bulk_actions-edit-{$post_type}", array( __CLASS__, 'add_bulk_action' ) );

			// Add bulk action handler
			add_filter( "handle_bulk_actions-edit-{$post_type}", array( __CLASS__, 'handle_bulk_action' ), 10, 3 );
		}
	}

	/**
	 * Add bulk action to post type list.
	 *
	 * @since 7.0.3
	 * @param array $actions Existing bulk actions.
	 * @return array Modified actions.
	 */
	public static function add_bulk_action( $actions ) {
		$actions['sb_seo_analysis'] = __( 'SEO Analysis', 'seo-booster' ) . ' (SEO Booster)';
		return $actions;
	}

	/**
	 * Handle bulk action - create batch and redirect.
	 *
	 * @since 7.0.3
	 * @param string $redirect_to Redirect URL.
	 * @param string $action Action name.
	 * @param array $post_ids Selected post IDs.
	 * @return string Modified redirect URL.
	 */
	public static function handle_bulk_action( $redirect_to, $action, $post_ids ) {
		if ( 'sb_seo_analysis' !== $action ) {
			return $redirect_to;
		}

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			return add_query_arg( 'sb_bulk_seo_error', 'no_posts', $redirect_to );
		}

		// Filter out excluded posts and invalid posts
		$valid_post_ids = array();
		foreach ( $post_ids as $post_id ) {
			$post_id = intval( $post_id );
			if ( $post_id <= 0 ) {
				continue;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			if ( ! Utils::user_can_edit_object( $post_id ) ) {
				continue;
			}

			// Skip excluded posts
			if ( SEO_Issues_Manager::should_exclude_from_analysis( $post_id ) ) {
				continue;
			}

			$valid_post_ids[] = $post_id;
		}

		if ( empty( $valid_post_ids ) ) {
			return add_query_arg( 'sb_bulk_seo_error', 'no_valid_posts', $redirect_to );
		}

		// Create batch ID
		$batch_id = 'sb_seo_bulk_' . get_current_user_id() . '_' . time();

		// Initialize batch status
		$batch_status = array(
			'total'      => count( $valid_post_ids ),
			'queued'     => count( $valid_post_ids ),
			'processed'  => 0,
			'failed'     => 0,
			'processing' => 0,
			'user_id'    => get_current_user_id(),
			'created'    => time(),
			'post_ids'   => array_values( $valid_post_ids ), // Store IDs for processing
			'post_type'  => get_post_type( $valid_post_ids[0] ), // Store post type for redirect
		);

		// Store batch status
		set_transient( 'sb_seo_bulk_batch_' . $batch_id, $batch_status, 3600 ); // 1 hour expiry

		// Preserve current URL parameters (pagination, search, filters)
		$current_url      = admin_url( 'edit.php' );
		$preserved_params = array();

		// Get current query parameters
		if ( isset( $_REQUEST['post_type'] ) ) {
			$preserved_params['post_type'] = sanitize_text_field( $_REQUEST['post_type'] );
		}
		if ( isset( $_REQUEST['paged'] ) ) {
			$preserved_params['paged'] = intval( $_REQUEST['paged'] );
		}
		if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {
			$preserved_params['s'] = sanitize_text_field( $_REQUEST['s'] );
		}
		if ( isset( $_REQUEST['post_status'] ) && ! empty( $_REQUEST['post_status'] ) ) {
			$preserved_params['post_status'] = sanitize_text_field( $_REQUEST['post_status'] );
		}

		// Add batch status parameter
		$preserved_params['sb_bulk_seo_status'] = $batch_id;

		// Build redirect URL with preserved parameters
		$redirect_to = add_query_arg( $preserved_params, $current_url );

		return $redirect_to;
	}

	/**
	 * AJAX handler for processing a single post in bulk analysis.
	 *
	 * @since 7.0.3
	 * @return void
	 */
	public static function ajax_process_bulk_analysis() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( $_POST['batch_id'] ) : '';
		$post_id  = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( empty( $batch_id ) || 0 === $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'seo-booster' ) ) );
		}

		if ( ! Utils::user_can_edit_object( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$transient_key = 'sb_seo_bulk_batch_' . $batch_id;
		$batch_status  = get_transient( $transient_key );

		if ( ! $batch_status || ! is_array( $batch_status ) ) {
			wp_send_json_error( array( 'message' => __( 'Batch not found', 'seo-booster' ) ) );
		}

		// SECURITY: Verify batch belongs to current user
		$batch_user_id   = isset( $batch_status['user_id'] ) ? intval( $batch_status['user_id'] ) : 0;
		$current_user_id = get_current_user_id();

		if ( $batch_user_id !== $current_user_id ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		// Check if batch is cancelled
		if ( isset( $batch_status['cancelled'] ) && true === $batch_status['cancelled'] ) {
			wp_send_json_error( array( 'message' => __( 'Batch was cancelled', 'seo-booster' ) ) );
		}

		// Mark as processing
		self::update_batch_status( $batch_id, 'processing', $post_id );

		// Validate post
		$post = get_post( $post_id );
		if ( ! $post ) {
			self::update_batch_status( $batch_id, 'failed', $post_id );
			wp_send_json_error( array( 'message' => __( 'Post not found', 'seo-booster' ) ) );
		}

		// Check if excluded from analysis
		if ( SEO_Issues_Manager::should_exclude_from_analysis( $post_id ) ) {
			self::update_batch_status( $batch_id, 'failed', $post_id );
			wp_send_json_error( array( 'message' => __( 'Post is excluded from analysis', 'seo-booster' ) ) );
		}

		try {
			// Run SEO analysis with full page content
			$analysis = new SEO_Analysis( $post_id, 'post' );
			$analysis->set_bulk_mode( true );
			$results = $analysis->analyze( true, false );

			self::update_batch_status( $batch_id, 'processed', $post_id );

			// Get the post title for display
			$post_title = get_the_title( $post_id );

			wp_send_json_success(
				array(
					'message'      => __( 'SEO analysis completed successfully', 'seo-booster' ),
					'post_id'      => $post_id,
					'post_title'   => $post_title,
					'issues_count' => isset( $results['issues'] ) ? count( $results['issues'] ) : 0,
				)
			);
		} catch ( \Exception $e ) {
			Utils::log( sprintf( 'SEO analysis failed for post %d: %s', $post_id, $e->getMessage() ), 2 );
			self::update_batch_status( $batch_id, 'failed', $post_id );

			wp_send_json_error(
				array(
					'message' => __( 'Something went wrong. Check the SEO Booster debug log for details.', 'seo-booster' ),
					'post_id' => $post_id,
				)
			);
		}
	}

	/**
	 * Update batch status tracking.
	 *
	 * @since 7.0.3
	 * @param string $batch_id Batch ID.
	 * @param string $status Status to update ('processed' or 'failed').
	 * @param int $post_id Optional post ID to track.
	 * @return void
	 */
	private static function update_batch_status( $batch_id, $status, $post_id = 0 ) {
		$transient_key = 'sb_seo_bulk_batch_' . $batch_id;
		$batch_status  = get_transient( $transient_key );

		if ( ! $batch_status || ! is_array( $batch_status ) ) {
			return;
		}

		// Initialize tracking arrays
		if ( ! isset( $batch_status['processed_ids'] ) ) {
			$batch_status['processed_ids'] = array();
		}
		if ( ! isset( $batch_status['failed_ids'] ) ) {
			$batch_status['failed_ids'] = array();
		}
		if ( ! isset( $batch_status['processing_ids'] ) ) {
			$batch_status['processing_ids'] = array();
		}

		if ( 'processed' === $status ) {
			$batch_status['processed'] = isset( $batch_status['processed'] ) ? $batch_status['processed'] + 1 : 1;
			if ( $post_id > 0 && ! in_array( $post_id, $batch_status['processed_ids'], true ) ) {
				$batch_status['processed_ids'][] = $post_id;
				// Store the title of the last processed post
				$post_title = get_the_title( $post_id );
				if ( ! empty( $post_title ) ) {
					$batch_status['last_processed_title'] = $post_title;
				}
			}
			// Remove from processing
			$batch_status['processing_ids'] = array_diff( $batch_status['processing_ids'], array( $post_id ) );
			if ( isset( $batch_status['processing'] ) && $batch_status['processing'] > 0 ) {
				$batch_status['processing'] = $batch_status['processing'] - 1;
			}
		} elseif ( 'failed' === $status ) {
			$batch_status['failed'] = isset( $batch_status['failed'] ) ? $batch_status['failed'] + 1 : 1;
			if ( $post_id > 0 && ! in_array( $post_id, $batch_status['failed_ids'], true ) ) {
				$batch_status['failed_ids'][] = $post_id;
			}
			// Remove from processing
			$batch_status['processing_ids'] = array_diff( $batch_status['processing_ids'], array( $post_id ) );
			if ( isset( $batch_status['processing'] ) && $batch_status['processing'] > 0 ) {
				$batch_status['processing'] = $batch_status['processing'] - 1;
			}
		} elseif ( 'processing' === $status ) {
			$batch_status['processing'] = isset( $batch_status['processing'] ) ? $batch_status['processing'] + 1 : 1;
			if ( $post_id > 0 && ! in_array( $post_id, $batch_status['processing_ids'], true ) ) {
				$batch_status['processing_ids'][] = $post_id;
			}
		}

		// Update transient (extend expiry)
		set_transient( $transient_key, $batch_status, 3600 );
	}

	/**
	 * AJAX handler for getting bulk analysis status.
	 *
	 * @since 7.0.3
	 * @return void
	 */
	public static function ajax_get_bulk_status() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( $_POST['batch_id'] ) : '';

		if ( empty( $batch_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid batch ID', 'seo-booster' ) ) );
		}

		$transient_key = 'sb_seo_bulk_batch_' . $batch_id;
		$batch_status  = get_transient( $transient_key );

		if ( ! $batch_status || ! is_array( $batch_status ) ) {
			wp_send_json_error( array( 'message' => __( 'Batch not found', 'seo-booster' ) ) );
		}

		// SECURITY: Verify batch belongs to current user
		$batch_user_id   = isset( $batch_status['user_id'] ) ? intval( $batch_status['user_id'] ) : 0;
		$current_user_id = get_current_user_id();

		if ( $batch_user_id !== $current_user_id ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$total      = isset( $batch_status['total'] ) ? intval( $batch_status['total'] ) : 0;
		$queued     = isset( $batch_status['queued'] ) ? intval( $batch_status['queued'] ) : 0;
		$processed  = isset( $batch_status['processed'] ) ? intval( $batch_status['processed'] ) : 0;
		$failed     = isset( $batch_status['failed'] ) ? intval( $batch_status['failed'] ) : 0;
		$processing = isset( $batch_status['processing'] ) ? intval( $batch_status['processing'] ) : 0;

		// Calculate remaining (queued minus completed)
		$remaining = max( 0, $queued - $processed - $failed );

		$completed = ( 0 === $remaining && $queued > 0 && 0 === $processing );
		$cancelled = isset( $batch_status['cancelled'] ) && true === $batch_status['cancelled'];

		// Get next post ID to process if not completed
		$next_post_id = 0;
		if ( ! $completed && ! $cancelled && isset( $batch_status['post_ids'] ) && is_array( $batch_status['post_ids'] ) ) {
			$processed_ids  = isset( $batch_status['processed_ids'] ) ? $batch_status['processed_ids'] : array();
			$failed_ids     = isset( $batch_status['failed_ids'] ) ? $batch_status['failed_ids'] : array();
			$processing_ids = isset( $batch_status['processing_ids'] ) ? $batch_status['processing_ids'] : array();
			$completed_ids  = array_merge( $processed_ids, $failed_ids, $processing_ids );

			foreach ( $batch_status['post_ids'] as $id ) {
				if ( ! in_array( $id, $completed_ids, true ) ) {
					$next_post_id = $id;
					break;
				}
			}
		}

		// Clean up transient if completed (but not if cancelled - keep for UI display)
		if ( $completed && ! $cancelled ) {
			delete_transient( $transient_key );
		}

		// Get last processed post title if available
		$last_processed_title = '';
		if ( isset( $batch_status['last_processed_title'] ) && ! empty( $batch_status['last_processed_title'] ) ) {
			$last_processed_title = $batch_status['last_processed_title'];
		}

		wp_send_json_success(
			array(
				'batch_id'             => $batch_id,
				'total'                => $total,
				'queued'               => $queued,
				'processed'            => $processed,
				'failed'               => $failed,
				'processing'           => $processing,
				'remaining'            => $remaining,
				'completed'            => $completed,
				'cancelled'            => $cancelled,
				'next_post_id'         => $next_post_id,
				'last_processed_title' => $last_processed_title,
			)
		);
	}

	/**
	 * AJAX handler for cancelling bulk analysis.
	 *
	 * @since 7.0.3
	 * @return void
	 */
	public static function ajax_cancel_bulk_analysis() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( $_POST['batch_id'] ) : '';

		if ( empty( $batch_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid batch ID', 'seo-booster' ) ) );
		}

		$transient_key = 'sb_seo_bulk_batch_' . $batch_id;
		$batch_status  = get_transient( $transient_key );

		if ( ! $batch_status || ! is_array( $batch_status ) ) {
			wp_send_json_error( array( 'message' => __( 'Batch not found', 'seo-booster' ) ) );
		}

		// SECURITY: Verify batch belongs to current user
		$batch_user_id   = isset( $batch_status['user_id'] ) ? intval( $batch_status['user_id'] ) : 0;
		$current_user_id = get_current_user_id();

		if ( $batch_user_id !== $current_user_id ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		// Calculate how many posts are still pending
		$queued          = isset( $batch_status['queued'] ) ? intval( $batch_status['queued'] ) : 0;
		$processed       = isset( $batch_status['processed'] ) ? intval( $batch_status['processed'] ) : 0;
		$failed          = isset( $batch_status['failed'] ) ? intval( $batch_status['failed'] ) : 0;
		$cancelled_count = max( 0, $queued - $processed - $failed );

		// Mark batch as cancelled in transient
		$batch_status['cancelled']    = true;
		$batch_status['cancelled_at'] = time();
		set_transient( $transient_key, $batch_status, 3600 );

		wp_send_json_success(
			array(
				'message'         => sprintf(
					/* translators: %d: number of pending analyses cancelled */
					__( 'Cancelled %d pending analysis(es)', 'seo-booster' ),
					$cancelled_count
				),
				'cancelled_count' => $cancelled_count,
			)
		);
	}

	/**
	 * Get active batch IDs for current user.
	 *
	 * @since 7.0.3
	 * @return array Array of batch IDs.
	 */
	private static function get_active_batches() {
		global $wpdb;
		$user_id        = get_current_user_id();
		$active_batches = array();

		// Search for transients matching our pattern
		$transient_prefix = '_transient_sb_seo_bulk_batch_';
		$transients       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE %s
             AND option_name LIKE %s",
				$transient_prefix . 'sb_seo_bulk_' . $user_id . '_%',
				'%'
			)
		);

		foreach ( $transients as $transient ) {
			// Extract batch ID from option name
			$batch_id     = str_replace( $transient_prefix, '', $transient->option_name );
			$batch_status = get_transient( 'sb_seo_bulk_batch_' . $batch_id );

			if ( $batch_status && is_array( $batch_status ) ) {
				// Skip if batch is cancelled
				if ( isset( $batch_status['cancelled'] ) && true === $batch_status['cancelled'] ) {
					continue;
				}

				// Check if batch is still active (not completed)
				$queued    = isset( $batch_status['queued'] ) ? intval( $batch_status['queued'] ) : 0;
				$processed = isset( $batch_status['processed'] ) ? intval( $batch_status['processed'] ) : 0;
				$failed    = isset( $batch_status['failed'] ) ? intval( $batch_status['failed'] ) : 0;

				if ( ( $processed + $failed ) < $queued ) {
					$active_batches[] = $batch_id;
				}
			}
		}

		return $active_batches;
	}

	/**
	 * Enqueue scripts and styles for post list pages.
	 *
	 * @since 7.0.3
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_scripts( $hook ) {
		// Only on post list pages (edit.php)
		if ( 'edit.php' !== $hook ) {
			return;
		}

		// Get active batch ID from URL or find active batches
		$batch_id = isset( $_GET['sb_bulk_seo_status'] ) ? sanitize_text_field( $_GET['sb_bulk_seo_status'] ) : '';

		// If no batch ID in URL, check for active batches
		if ( empty( $batch_id ) ) {
			$active_batches = self::get_active_batches();
			if ( ! empty( $active_batches ) ) {
				// Use the most recent batch (last one)
				$batch_id = end( $active_batches );
			}
		}

		// Check for error parameters
		$error = isset( $_GET['sb_bulk_seo_error'] ) ? sanitize_text_field( $_GET['sb_bulk_seo_error'] ) : '';

		// Enqueue script (JS also checks localStorage when batch_id is empty)
		Utils::enqueue_modal_assets();
		wp_enqueue_script(
			'sb-bulk-seo-status',
			SEOBOOSTER_PLUGINURL . 'js/sb-bulk-seo-status.js',
			array( 'jquery', 'sb-modal' ),
			filemtime( plugin_dir_path( __FILE__ ) . '../js/sb-bulk-seo-status.js' ),
			true
		);

		// Localize script
		wp_localize_script(
			'sb-bulk-seo-status',
			'sbBulkSeoStatus',
			array(
				'ajax_url'              => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'sb_seo_metabox_nonce' ),
				'batch_id'              => $batch_id,
				'error'                 => $error,
				'poll_interval'         => 30000, // 30 seconds in milliseconds
				'seo_possibilities_url' => admin_url( 'admin.php?page=sb2_seo_issues' ),
				'strings'               => array(
					'processing'               => __( 'Processing...', 'seo-booster' ),
					'completed'                => __( 'Completed', 'seo-booster' ),
					'error'                    => __( 'Error', 'seo-booster' ),
					/* translators: 1: number of posts processed so far, 2: total number of posts */
					'processing_status'        => __( 'Processing %1$d of %2$d posts...', 'seo-booster' ),
					/* translators: %d: number of posts analyzed successfully */
					'completed_status'         => __( 'Completed: %d analyzed successfully', 'seo-booster' ),
					/* translators: %d: number of failed analyses */
					'failed_status'            => __( '%d failed', 'seo-booster' ),
					/* translators: %d: number of remaining analyses */
					'remaining_status'         => __( '%d remaining', 'seo-booster' ),
					/* translators: %s: title of the last processed post */
					'last_processed'           => __( 'Last: "%s"', 'seo-booster' ),
					'cancelled'                => __( 'Cancelled', 'seo-booster' ),
					'cancel'                   => __( 'Cancel', 'seo-booster' ),
					'cancelling'               => __( 'Cancelling...', 'seo-booster' ),
					'reload'                   => __( 'Reload', 'seo-booster' ),
					'no_posts'                 => __( 'No posts were selected for analysis.', 'seo-booster' ),
					'no_valid_posts'           => __( 'No valid posts were selected for analysis.', 'seo-booster' ),
					'unknown_error'            => __( 'An error occurred while processing bulk analysis.', 'seo-booster' ),
					/* translators: %1$s: link text for SEO Possibilities page */
					'completed_message'        => __( 'Click the Reload button to see updated scores, or go to %1$s to see an updated list.', 'seo-booster' ),
					'completed_message_simple' => __( 'Click the Reload button to see updated scores.', 'seo-booster' ),
					'seo_possibilities_link'   => __( 'SEO Possibilities', 'seo-booster' ),
				),
			)
		);

		// Add inline CSS for banner
		$css = '

        .sb-bulk-seo-status-banner.success:before {
    background: #00a32a;
        }
        .sb-bulk-seo-status-banner:before {
            content: "SEO Booster";
            position: absolute;
    top: -22px;
    left: 0;
    width: auto;
    height: auto;
   background: #0073aa;
    display: block;
    padding: 2px 4px;
    border-top-left-radius: 4px;
    border-top-right-radius: 4px;
        }
        .sb-bulk-seo-status-banner {
        position:relative;
            background: #0073aa;
            color: #fff;
            padding: 10px 15px;
            margin: 30px 0 20px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 15px;
         
        }
        .sb-bulk-seo-status-banner.error {
            background: #dc3232;
        }
        .sb-bulk-seo-status-banner.success {
            background: #00a32a;
        }
        .sb-bulk-seo-status-banner.cancelled {
            background: #dba617;
        }
        .sb-bulk-seo-status-cancel {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.4);
            color: #fff;
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .sb-bulk-seo-status-cancel:hover:not(:disabled) {
            background: rgba(255,255,255,0.3);
        }
        .sb-bulk-seo-status-cancel:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .sb-bulk-seo-status-reload {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.4);
            color: #fff;
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .sb-bulk-seo-status-reload:hover:not(:disabled) {
            background: rgba(255,255,255,0.3);
        }
        .sb-bulk-seo-status-reload:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .sb-bulk-seo-status-content {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .sb-bulk-seo-status-text-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .sb-bulk-seo-status-text {
            font-size: 14px;
            line-height: 1.5;
        }
        .sb-bulk-seo-status-message {
            font-size: 13px;
            line-height: 1.5;
            opacity: 0.95;
            margin-top: 4px;
        }
        .sb-bulk-seo-status-link {
            color: #fff;
            text-decoration: underline;
            font-weight: 500;
            transition: opacity 0.2s;
        }
        .sb-bulk-seo-status-link:hover {
            opacity: 0.8;
        }
        .sb-bulk-seo-status-progress {
            width: 200px;
            height: 8px;
            background: rgba(255,255,255,0.3);
            border-radius: 4px;
            overflow: hidden;
        }
        .sb-bulk-seo-status-progress-fill {
            height: 100%;
            background: #fff;
            transition: width 0.3s ease;
            border-radius: 4px;
        }
        .sb-bulk-seo-status-close {
            background: none;
            border: none;
            color: #fff;
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        .sb-bulk-seo-status-close:hover {
            opacity: 1;
        }
        .sb-bulk-seo-status-banner.hidden {
            display: none;
        }
        ';
		wp_add_inline_style( 'wp-admin', $css );
	}
}
