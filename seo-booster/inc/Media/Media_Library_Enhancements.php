<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Media_Library_Enhancements
 *
 * Handles media library enhancements: Alt text column and bulk AI generation.
 *
 * @package Cleverplugins\SEOBooster
 * @since 7.0.2
 */
class Media_Library_Enhancements {

	/**
	 * Initialize the class and set up hooks.
	 *
	 * @since 7.0.2
	 * @return void
	 */
	public static function init() {
		// Add Alt text column
		add_filter( 'manage_media_columns', array( __CLASS__, 'add_alt_text_column' ) );
		add_action( 'manage_media_custom_column', array( __CLASS__, 'display_alt_text_column' ), 10, 2 );

		// Add bulk action
		add_filter( 'bulk_actions-upload', array( __CLASS__, 'add_bulk_action' ) );
		add_filter( 'handle_bulk_actions-upload', array( __CLASS__, 'handle_bulk_action' ), 10, 3 );

		// AJAX handlers
		add_action( 'wp_ajax_sb_get_bulk_status', array( __CLASS__, 'ajax_get_bulk_status' ) );
		add_action( 'wp_ajax_sb_process_bulk_image', array( __CLASS__, 'ajax_process_bulk_image' ) );
		add_action( 'wp_ajax_sb_cancel_bulk_generation', array( __CLASS__, 'ajax_cancel_bulk_generation' ) );

		// Enqueue scripts for media library
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}


	/**
	 * Add Alt text column to media library.
	 *
	 * @since 7.0.2
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public static function add_alt_text_column( $columns ) {
		// Insert Alt text column after the title column
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( $key === 'title' ) {
				$new_columns['sb_alt_text'] = __( 'Alt Text', 'seo-booster' );
			}
		}

		// If title column doesn't exist, add at the end
		if ( ! isset( $new_columns['sb_alt_text'] ) ) {
			$new_columns['sb_alt_text'] = __( 'Alt Text', 'seo-booster' );
		}

		return $new_columns;
	}

	/**
	 * Display Alt text in the column.
	 *
	 * @since 7.0.2
	 * @param string $column_name Column name.
	 * @param int $post_id Attachment ID.
	 * @return void
	 */
	public static function display_alt_text_column( $column_name, $post_id ) {
		if ( $column_name !== 'sb_alt_text' ) {
			return;
		}

		$alt_text = get_post_meta( $post_id, '_wp_attachment_image_alt', true );

		if ( ! empty( $alt_text ) ) {
			echo '<span title="' . esc_attr( $alt_text ) . '">' . esc_html( wp_trim_words( $alt_text, 10, '...' ) ) . '</span>';
		} else {
			echo '<span style="color: #999;">—</span>';
		}
	}

	/**
	 * Add bulk action to media library.
	 *
	 * @since 7.0.2
	 * @param array $actions Existing bulk actions.
	 * @return array Modified actions.
	 */
	public static function add_bulk_action( $actions ) {
		$ai_provider = LLM_Helper::get_selected_ai_provider();
		if ( in_array( $ai_provider, array( 'WordPress', 'seobooster' ), true ) && AI_Image_Generator::model_supports_vision() ) {
			$actions['sb_generate_ai_content'] = __( 'AI Image Metadata', 'seo-booster' ) . ' (SEO Booster)';
		}

		return $actions;
	}

	/**
	 * Handle bulk action - queue jobs with Action Scheduler.
	 *
	 * @since 7.0.2
	 * @param string $redirect_to Redirect URL.
	 * @param string $action Action name.
	 * @param array $post_ids Selected post IDs.
	 * @return string Modified redirect URL.
	 */
	public static function handle_bulk_action( $redirect_to, $action, $post_ids ) {
		if ( $action !== 'sb_generate_ai_content' ) {
			return $redirect_to;
		}

		$ai_provider = LLM_Helper::get_selected_ai_provider();
		if ( ! in_array( $ai_provider, array( 'WordPress', 'seobooster' ), true ) ) {
			return add_query_arg( 'sb_bulk_error', 'no_ai_provider', $redirect_to );
		}

		// Filter to only images
		$image_ids = array_filter(
			$post_ids,
			function ( $id ) {
				return wp_attachment_is_image( $id );
			}
		);

		if ( empty( $image_ids ) ) {
			return add_query_arg( 'sb_bulk_error', 'no_images', $redirect_to );
		}

		// Create batch ID
		$batch_id = 'sb_bulk_' . get_current_user_id() . '_' . time();

		// Initialize batch status
		$batch_status = array(
			'total'          => count( $image_ids ),
			'queued'         => count( $image_ids ),
			'processed'      => 0,
			'failed'         => 0,
			'processing'     => 0,
			'user_id'        => get_current_user_id(),
			'created'        => time(),
			'attachment_ids' => array_values( array_map( 'intval', $image_ids ) ), // Store IDs for processing
		);

		// Store batch status
		set_transient( 'sb_bulk_batch_' . $batch_id, $batch_status, 3600 ); // 1 hour expiry

		// Preserve current URL parameters (pagination, search, filters)
		$current_url      = admin_url( 'upload.php' );
		$preserved_params = array();

		// Get current query parameters
		if ( isset( $_REQUEST['paged'] ) ) {
			$preserved_params['paged'] = intval( $_REQUEST['paged'] );
		}
		if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {
			$preserved_params['s'] = sanitize_text_field( $_REQUEST['s'] );
		}
		if ( isset( $_REQUEST['m'] ) && ! empty( $_REQUEST['m'] ) ) {
			$preserved_params['m'] = intval( $_REQUEST['m'] );
		}
		if ( isset( $_REQUEST['attachment-filter'] ) && ! empty( $_REQUEST['attachment-filter'] ) ) {
			$preserved_params['attachment-filter'] = sanitize_text_field( $_REQUEST['attachment-filter'] );
		}
		if ( isset( $_REQUEST['post_mime_type'] ) && ! empty( $_REQUEST['post_mime_type'] ) ) {
			$preserved_params['post_mime_type'] = sanitize_text_field( $_REQUEST['post_mime_type'] );
		}

		// Add batch status parameter
		$preserved_params['sb_bulk_status'] = $batch_id;

		// Build redirect URL with preserved parameters
		$redirect_to = add_query_arg( $preserved_params, $current_url );

		return $redirect_to;
	}

	/**
	 * AJAX handler for processing a single image in bulk generation.
	 *
	 * @since 7.0.2
	 * @return void
	 */
	public static function ajax_process_bulk_image() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$batch_id      = isset( $_POST['batch_id'] ) ? sanitize_text_field( $_POST['batch_id'] ) : '';
		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;

		if ( empty( $batch_id ) || $attachment_id === 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'seo-booster' ) ) );
		}

		$transient_key = 'sb_bulk_batch_' . $batch_id;
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
		if ( isset( $batch_status['cancelled'] ) && $batch_status['cancelled'] === true ) {
			wp_send_json_error( array( 'message' => __( 'Batch was cancelled', 'seo-booster' ) ) );
		}

		// Mark as processing
		self::update_batch_status( $batch_id, 'processing', $attachment_id );

		// Validate attachment
		$attachment = get_post( $attachment_id );
		if ( ! $attachment ) {
			self::update_batch_status( $batch_id, 'failed', $attachment_id );
			wp_send_json_error( array( 'message' => __( 'Attachment not found', 'seo-booster' ) ) );
		}

		// Check if it's an image
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			self::update_batch_status( $batch_id, 'failed', $attachment_id );
			wp_send_json_error( array( 'message' => __( 'Not an image', 'seo-booster' ) ) );
		}

		try {
			// Generate descriptions
			$generated = AI_Image_Generator::generate_descriptions( $attachment_id );

			// Automatically apply the generated content to the image
			if ( $generated && is_array( $generated ) ) {
				// Get the latest generated content
				$latest_content = get_post_meta( $attachment_id, '_sb_ai_image_latest_content', true );

				if ( $latest_content && is_array( $latest_content ) ) {
					// Apply all generated fields automatically
					$update_data = array();

					// Update title
					if ( ! empty( $latest_content['title'] ) ) {
						$update_data['post_title'] = sanitize_text_field( $latest_content['title'] );
					}

					// Update alt text
					if ( ! empty( $latest_content['alt_text'] ) ) {
						update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $latest_content['alt_text'] ) );
					}

					// Update caption (post_excerpt)
					if ( ! empty( $latest_content['caption'] ) ) {
						$update_data['post_excerpt'] = sanitize_textarea_field( $latest_content['caption'] );
					}

					// Update description (post_content)
					if ( ! empty( $latest_content['description'] ) ) {
						$update_data['post_content'] = sanitize_textarea_field( $latest_content['description'] );
					}

					// Update post data if we have changes
					if ( ! empty( $update_data ) ) {
						$update_data['ID'] = $attachment_id;
						wp_update_post( $update_data );
					}
				}
			}

			self::update_batch_status( $batch_id, 'processed', $attachment_id );

			// Get the image title for display
			$image_title = get_the_title( $attachment_id );

			wp_send_json_success(
				array(
					'message'       => __( 'Image processed and saved successfully', 'seo-booster' ),
					'attachment_id' => $attachment_id,
					'image_title'   => $image_title,
				)
			);
		} catch ( \Exception $e ) {
			Utils::log( 'Bulk image generation failed for attachment ' . $attachment_id . ': ' . $e->getMessage(), 2 );
			self::update_batch_status( $batch_id, 'failed', $attachment_id );

			wp_send_json_error(
				array(
					'message'       => $e->getMessage(),
					'attachment_id' => $attachment_id,
				)
			);
		}
	}

	/**
	 * Update batch status tracking.
	 *
	 * @since 7.0.2
	 * @param string $batch_id Batch ID.
	 * @param string $status Status to update ('processed' or 'failed').
	 * @param int $attachment_id Optional attachment ID to track.
	 * @return void
	 */
	private static function update_batch_status( $batch_id, $status, $attachment_id = 0 ) {
		$transient_key = 'sb_bulk_batch_' . $batch_id;
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

		if ( $status === 'processed' ) {
			$batch_status['processed'] = isset( $batch_status['processed'] ) ? $batch_status['processed'] + 1 : 1;
			if ( $attachment_id > 0 && ! in_array( $attachment_id, $batch_status['processed_ids'] ) ) {
				$batch_status['processed_ids'][] = $attachment_id;
				// Store the title of the last processed image
				$image_title = get_the_title( $attachment_id );
				if ( ! empty( $image_title ) ) {
					$batch_status['last_processed_title'] = $image_title;
				}
			}
			// Remove from processing
			$batch_status['processing_ids'] = array_diff( $batch_status['processing_ids'], array( $attachment_id ) );
			if ( isset( $batch_status['processing'] ) && $batch_status['processing'] > 0 ) {
				$batch_status['processing'] = $batch_status['processing'] - 1;
			}
		} elseif ( $status === 'failed' ) {
			$batch_status['failed'] = isset( $batch_status['failed'] ) ? $batch_status['failed'] + 1 : 1;
			if ( $attachment_id > 0 && ! in_array( $attachment_id, $batch_status['failed_ids'] ) ) {
				$batch_status['failed_ids'][] = $attachment_id;
			}
			// Remove from processing
			$batch_status['processing_ids'] = array_diff( $batch_status['processing_ids'], array( $attachment_id ) );
			if ( isset( $batch_status['processing'] ) && $batch_status['processing'] > 0 ) {
				$batch_status['processing'] = $batch_status['processing'] - 1;
			}
		} elseif ( $status === 'processing' ) {
			$batch_status['processing'] = isset( $batch_status['processing'] ) ? $batch_status['processing'] + 1 : 1;
			if ( $attachment_id > 0 && ! in_array( $attachment_id, $batch_status['processing_ids'] ) ) {
				$batch_status['processing_ids'][] = $attachment_id;
			}
		}

		// Update transient (extend expiry)
		set_transient( $transient_key, $batch_status, 3600 );
	}

	/**
	 * AJAX handler for getting bulk generation status.
	 *
	 * @since 7.0.2
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

		$transient_key = 'sb_bulk_batch_' . $batch_id;
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

		$completed = ( $remaining === 0 && $queued > 0 && $processing === 0 );
		$cancelled = isset( $batch_status['cancelled'] ) && $batch_status['cancelled'] === true;

		// Get next attachment ID to process if not completed
		$next_attachment_id = 0;
		if ( ! $completed && ! $cancelled && isset( $batch_status['attachment_ids'] ) && is_array( $batch_status['attachment_ids'] ) ) {
			$processed_ids  = isset( $batch_status['processed_ids'] ) ? $batch_status['processed_ids'] : array();
			$failed_ids     = isset( $batch_status['failed_ids'] ) ? $batch_status['failed_ids'] : array();
			$processing_ids = isset( $batch_status['processing_ids'] ) ? $batch_status['processing_ids'] : array();
			$completed_ids  = array_merge( $processed_ids, $failed_ids, $processing_ids );

			foreach ( $batch_status['attachment_ids'] as $id ) {
				if ( ! in_array( $id, $completed_ids ) ) {
					$next_attachment_id = $id;
					break;
				}
			}
		}

		// Clean up transient if completed (but not if cancelled - keep for UI display)
		if ( $completed && ! $cancelled ) {
			delete_transient( $transient_key );
		}

		// Get last processed image title if available
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
				'next_attachment_id'   => $next_attachment_id,
				'last_processed_title' => $last_processed_title,
			)
		);
	}

	/**
	 * AJAX handler for cancelling bulk generation.
	 *
	 * @since 7.0.2
	 * @return void
	 */
	public static function ajax_cancel_bulk_generation() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( $_POST['batch_id'] ) : '';

		if ( empty( $batch_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid batch ID', 'seo-booster' ) ) );
		}

		$transient_key = 'sb_bulk_batch_' . $batch_id;
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

		// Calculate how many images are still pending
		$queued          = isset( $batch_status['queued'] ) ? intval( $batch_status['queued'] ) : 0;
		$processed       = isset( $batch_status['processed'] ) ? intval( $batch_status['processed'] ) : 0;
		$failed          = isset( $batch_status['failed'] ) ? intval( $batch_status['failed'] ) : 0;
		$cancelled_count = max( 0, $queued - $processed - $failed );

		// Mark batch as cancelled in transient
		$batch_status['cancelled']    = true;
		$batch_status['cancelled_at'] = time();
		set_transient( $transient_key, $batch_status, 3600 );

		// Clean up transient after a short delay (to allow UI to show cancelled state)
		wp_send_json_success(
			array(
				'message'         => sprintf( __( 'Cancelled %d pending job(s)', 'seo-booster' ), $cancelled_count ),
				'cancelled_count' => $cancelled_count,
			)
		);
	}

	/**
	 * Get active batch IDs for current user.
	 *
	 * @since 7.0.2
	 * @return array Array of batch IDs.
	 */
	private static function get_active_batches() {
		global $wpdb;
		$user_id        = get_current_user_id();
		$active_batches = array();

		// Search for transients matching our pattern
		$transient_prefix = '_transient_sb_bulk_batch_';
		$transients       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE %s
             AND option_name LIKE %s",
				$transient_prefix . 'sb_bulk_' . $user_id . '_%',
				'%'
			)
		);

		foreach ( $transients as $transient ) {
			// Extract batch ID from option name
			$batch_id     = str_replace( $transient_prefix, '', $transient->option_name );
			$batch_status = get_transient( 'sb_bulk_batch_' . $batch_id );

			if ( $batch_status && is_array( $batch_status ) ) {
				// Skip if batch is cancelled
				if ( isset( $batch_status['cancelled'] ) && $batch_status['cancelled'] === true ) {
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
	 * Enqueue scripts and styles for media library.
	 *
	 * @since 7.0.2
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_scripts( $hook ) {
		// Only on media library page
		if ( $hook !== 'upload.php' ) {
			return;
		}

		// Get active batch ID from URL or find active batches
		$batch_id = isset( $_GET['sb_bulk_status'] ) ? sanitize_text_field( $_GET['sb_bulk_status'] ) : '';

		// If no batch ID in URL, check for active batches
		if ( empty( $batch_id ) ) {
			$active_batches = self::get_active_batches();
			if ( ! empty( $active_batches ) ) {
				// Use the most recent batch (last one)
				$batch_id = end( $active_batches );
			}
		}

		// Check for error parameters
		$error = isset( $_GET['sb_bulk_error'] ) ? sanitize_text_field( $_GET['sb_bulk_error'] ) : '';

		// Only enqueue script if we have a batch ID or error (no need to load if nothing is happening)
		if ( empty( $batch_id ) && empty( $error ) ) {
			// Check localStorage for batch ID (will be checked in JS)
			// Still load script but with empty batch_id so JS can check localStorage
			// But we'll optimize JS to not poll if nothing found
		}

		// Enqueue script
		Utils::enqueue_modal_assets();
		wp_enqueue_script(
			'sb-media-bulk-status',
			SEOBOOSTER_PLUGINURL . 'js/sb-media-bulk-status.js',
			array( 'jquery', 'sb-modal' ),
			filemtime( plugin_dir_path( __FILE__ ) . '../../js/sb-media-bulk-status.js' ),
			true
		);

		// Localize script
		wp_localize_script(
			'sb-media-bulk-status',
			'sbBulkStatus',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'sb_seo_metabox_nonce' ),
				'batch_id'      => $batch_id,
				'error'         => $error,
				'poll_interval' => 30000, // 30 seconds in milliseconds
				'strings'       => array(
					'processing'        => __( 'Processing...', 'seo-booster' ),
					'completed'         => __( 'Completed', 'seo-booster' ),
					'error'             => __( 'Error', 'seo-booster' ),
					'processing_status' => __( 'Processing %1$d of %2$d images...', 'seo-booster' ),
					'completed_status'  => __( 'Completed: %d processed successfully', 'seo-booster' ),
					'failed_status'     => __( '%d failed', 'seo-booster' ),
					'remaining_status'  => __( '%d remaining', 'seo-booster' ),
					'last_processed'    => __( 'Last: "%s"', 'seo-booster' ),
					'cancelled'         => __( 'Cancelled', 'seo-booster' ),
					'cancel'            => __( 'Cancel', 'seo-booster' ),
					'cancelling'        => __( 'Cancelling...', 'seo-booster' ),
					'reload'            => __( 'Reload', 'seo-booster' ),
					'no_scheduler'      => __( 'Action Scheduler is not available. Bulk generation cannot be processed.', 'seo-booster' ),
					'no_ai_provider'    => __( 'AI is not configured. Please configure WordPress (Settings → Connectors) or SEO Booster Credits in settings.', 'seo-booster' ),
					'no_images'         => __( 'No images were selected for generation.', 'seo-booster' ),
					'unknown_error'     => __( 'An error occurred while processing bulk generation.', 'seo-booster' ),
				),
			)
		);

		// Add inline CSS for banner
		$css = '
        .sb-bulk-status-banner {
            background: #0073aa;
            color: #fff;
            padding: 12px 20px;
            margin: 0 0 20px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 4px solid #005a87;
        }
        .sb-bulk-status-banner.error {
            background: #dc3232;
        }
        .sb-bulk-status-banner.success {
            background: #00a32a;
        }
        .sb-bulk-status-banner.cancelled {
            background: #dba617;
        }
        .sb-bulk-status-cancel {
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
        .sb-bulk-status-cancel:hover:not(:disabled) {
            background: rgba(255,255,255,0.3);
        }
        .sb-bulk-status-cancel:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .sb-bulk-status-reload {
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
        .sb-bulk-status-reload:hover:not(:disabled) {
            background: rgba(255,255,255,0.3);
        }
        .sb-bulk-status-reload:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .sb-bulk-status-content {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .sb-bulk-status-text {
            flex: 1;
            font-size: 14px;
            line-height: 1.5;
        }
        .sb-bulk-status-progress {
            width: 200px;
            height: 8px;
            background: rgba(255,255,255,0.3);
            border-radius: 4px;
            overflow: hidden;
        }
        .sb-bulk-status-progress-fill {
            height: 100%;
            background: #fff;
            transition: width 0.3s ease;
            border-radius: 4px;
        }
        .sb-bulk-status-close {
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
        .sb-bulk-status-close:hover {
            opacity: 1;
        }
        .sb-bulk-status-banner.hidden {
            display: none;
        }
        ';
		wp_add_inline_style( 'wp-admin', $css );
	}
}
