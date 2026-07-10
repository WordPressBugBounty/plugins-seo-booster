<?php

namespace Cleverplugins\SEOBooster\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared transient-backed batch processing for Tools page bulk actions.
 *
 * @package Cleverplugins\SEOBooster\Tools
 */
abstract class Tools_Batch_Base {

	const TRANSIENT_TTL = 3600;

	/**
	 * Register AJAX handlers for this batch tool.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_' . static::get_ajax_action_start(), array( static::class, 'ajax_start_batch' ) );
		add_action( 'wp_ajax_' . static::get_ajax_action_process(), array( static::class, 'ajax_process_item' ) );
		add_action( 'wp_ajax_' . static::get_ajax_action_status(), array( static::class, 'ajax_batch_status' ) );
		add_action( 'wp_ajax_' . static::get_ajax_action_cancel(), array( static::class, 'ajax_cancel_batch' ) );
	}

	/**
	 * Transient key prefix (must end with underscore).
	 *
	 * @return string
	 */
	abstract public static function get_transient_prefix();

	/**
	 * Nonce action string.
	 *
	 * @return string
	 */
	abstract public static function get_nonce_action();

	/**
	 * @return string
	 */
	abstract public static function get_ajax_action_start();

	/**
	 * @return string
	 */
	abstract public static function get_ajax_action_process();

	/**
	 * @return string
	 */
	abstract public static function get_ajax_action_status();

	/**
	 * @return string
	 */
	abstract public static function get_ajax_action_cancel();

	/**
	 * POST key for item ID during process (e.g. attachment_id, post_id).
	 *
	 * @return string
	 */
	abstract public static function get_item_id_post_key();

	/**
	 * Response key for item ID list on start (e.g. attachment_ids, post_ids).
	 *
	 * @return string
	 */
	abstract public static function get_item_ids_response_key();

	/**
	 * Validate preconditions; return error message or empty string if OK.
	 *
	 * @return string
	 */
	abstract protected static function validate_preconditions();

	/**
	 * Build item IDs and batch config from POST. Calls wp_send_json_error on failure.
	 *
	 * @return array{item_ids: int[], config: array}
	 */
	abstract protected static function prepare_batch_from_request();

	/**
	 * Process one item.
	 *
	 * @param int   $item_id Item ID.
	 * @param array $config  Batch config from transient.
	 * @return array Preview payload for success response.
	 * @throws \Exception On failure.
	 */
	abstract public static function process_single( $item_id, array $config );

	/**
	 * Extra fields for failed item list entries.
	 *
	 * @param int    $item_id       Item ID.
	 * @param string $error_message Error message.
	 * @return array
	 */
	abstract protected static function get_failed_item_meta( $item_id, $error_message );

	/**
	 * Extra keys merged into start-batch success response.
	 *
	 * @param array $config Batch config.
	 * @return array
	 */
	protected static function get_start_batch_extra_response( array $config ) {
		return array();
	}

	/**
	 * AJAX: start batch.
	 *
	 * @return void
	 */
	public static function ajax_start_batch() {
		check_ajax_referer( static::get_nonce_action(), 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$precondition_error = static::validate_preconditions();
		if ( $precondition_error !== '' ) {
			wp_send_json_error( array( 'message' => $precondition_error ) );
		}

		$prepared = static::prepare_batch_from_request();
		$item_ids = $prepared['item_ids'];
		$config   = $prepared['config'];

		if ( empty( $item_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No items selected for processing.', 'seo-booster' ) ) );
		}

		static::on_batch_start( $item_ids, $config );

		$batch_id = static::get_transient_prefix() . get_current_user_id() . '_' . time();

		$batch_status = array(
			'total'          => count( $item_ids ),
			'queued'         => count( $item_ids ),
			'processed'      => 0,
			'failed'         => 0,
			'processing'     => 0,
			'user_id'        => get_current_user_id(),
			'created'        => time(),
			'item_ids'       => $item_ids,
			'config'         => $config,
			'processed_ids'  => array(),
			'failed_ids'     => array(),
			'failed_items'   => array(),
			'processing_ids' => array(),
			'cancelled'      => false,
		);

		set_transient( static::get_transient_prefix() . $batch_id, $batch_status, static::TRANSIENT_TTL );

		$response = array(
			'batch_id'                          => $batch_id,
			'total'                             => $batch_status['total'],
			static::get_item_ids_response_key() => $item_ids,
		);

		wp_send_json_success( array_merge( $response, static::get_start_batch_extra_response( $config ) ) );
	}

	/**
	 * Hook after batch validated, before transient saved.
	 *
	 * @param int[] $item_ids Item IDs.
	 * @param array $config   Config.
	 * @return void
	 */
	protected static function on_batch_start( array $item_ids, array $config ) {
		// Subclasses may persist user defaults.
	}

	/**
	 * Hook after a single item is processed successfully.
	 *
	 * @param string $batch_id Batch ID.
	 * @param int    $item_id  Item ID.
	 * @param array  $payload  Success payload from process_single.
	 * @param array  $config   Batch config.
	 * @return void
	 */
	protected static function on_item_processed( $batch_id, $item_id, array $payload, array $config ) {
		// Subclasses may persist per-item data (e.g. created rule IDs).
	}

	/**
	 * AJAX: process one item.
	 *
	 * @return void
	 */
	public static function ajax_process_item() {
		check_ajax_referer( static::get_nonce_action(), 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
		$item_key = static::get_item_id_post_key();
		$item_id  = isset( $_POST[ $item_key ] ) ? intval( $_POST[ $item_key ] ) : 0;

		if ( $batch_id === '' || $item_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'seo-booster' ) ) );
		}

		$batch = static::get_batch( $batch_id );
		if ( ! $batch ) {
			wp_send_json_error( array( 'message' => __( 'Batch not found', 'seo-booster' ) ) );
		}

		if ( ! empty( $batch['cancelled'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Batch was cancelled', 'seo-booster' ) ) );
		}

		if ( ! in_array( $item_id, $batch['item_ids'], true ) ) {
			wp_send_json_error( array( 'message' => __( 'Item not in batch', 'seo-booster' ) ) );
		}

		static::update_batch_status( $batch_id, 'processing', $item_id );

		try {
			$config  = isset( $batch['config'] ) && is_array( $batch['config'] ) ? $batch['config'] : array();
			$payload = static::process_single( $item_id, $config );
			static::update_batch_status( $batch_id, 'processed', $item_id );
			static::on_item_processed( $batch_id, $item_id, $payload, $config );

			$batch               = static::get_batch( $batch_id );
			$payload['progress'] = static::progress_from_batch( $batch );

			wp_send_json_success( $payload );
		} catch ( \Throwable $e ) {
			$message = $e->getMessage();
			if ( $message === '' ) {
				$message = __( 'Processing failed.', 'seo-booster' );
			}

			static::update_batch_status( $batch_id, 'failed', $item_id, $message );
			$batch = static::get_batch( $batch_id );

			wp_send_json_error(
				array_merge(
					static::get_failed_item_meta( $item_id, $message ),
					array(
						'message'  => $message,
						'progress' => static::progress_from_batch( $batch ),
					)
				)
			);
		}
	}

	/**
	 * AJAX: batch status.
	 *
	 * @return void
	 */
	public static function ajax_batch_status() {
		check_ajax_referer( static::get_nonce_action(), 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
		if ( $batch_id === '' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid batch ID', 'seo-booster' ) ) );
		}

		$batch = static::get_batch( $batch_id );
		if ( ! $batch ) {
			wp_send_json_error( array( 'message' => __( 'Batch not found', 'seo-booster' ) ) );
		}

		wp_send_json_success( static::status_response( $batch_id, $batch ) );
	}

	/**
	 * AJAX: cancel batch.
	 *
	 * @return void
	 */
	public static function ajax_cancel_batch() {
		check_ajax_referer( static::get_nonce_action(), 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
		if ( $batch_id === '' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid batch ID', 'seo-booster' ) ) );
		}

		$batch = static::get_batch( $batch_id );
		if ( ! $batch ) {
			wp_send_json_error( array( 'message' => __( 'Batch not found', 'seo-booster' ) ) );
		}

		$batch['cancelled']    = true;
		$batch['cancelled_at'] = time();
		set_transient( static::get_transient_prefix() . $batch_id, $batch, static::TRANSIENT_TTL );

		wp_send_json_success( array( 'message' => __( 'Batch cancelled', 'seo-booster' ) ) );
	}

	/**
	 * @param string $batch_id Batch ID.
	 * @return array|false
	 */
	protected static function get_batch( $batch_id ) {
		$batch = get_transient( static::get_transient_prefix() . $batch_id );
		if ( ! $batch || ! is_array( $batch ) ) {
			return false;
		}

		$batch_user_id = isset( $batch['user_id'] ) ? (int) $batch['user_id'] : 0;
		if ( $batch_user_id !== get_current_user_id() ) {
			return false;
		}

		return $batch;
	}

	/**
	 * @param string $batch_id      Batch ID.
	 * @param string $status        processed|failed|processing.
	 * @param int    $item_id       Item ID.
	 * @param string $error_message Failure message.
	 * @return void
	 */
	protected static function update_batch_status( $batch_id, $status, $item_id = 0, $error_message = '' ) {
		$batch = static::get_batch( $batch_id );
		if ( ! $batch ) {
			return;
		}

		if ( ! isset( $batch['processed_ids'] ) ) {
			$batch['processed_ids'] = array();
		}
		if ( ! isset( $batch['failed_ids'] ) ) {
			$batch['failed_ids'] = array();
		}
		if ( ! isset( $batch['failed_items'] ) ) {
			$batch['failed_items'] = array();
		}
		if ( ! isset( $batch['processing_ids'] ) ) {
			$batch['processing_ids'] = array();
		}

		if ( $status === 'processed' ) {
			$batch['processed'] = isset( $batch['processed'] ) ? $batch['processed'] + 1 : 1;
			if ( $item_id > 0 && ! in_array( $item_id, $batch['processed_ids'], true ) ) {
				$batch['processed_ids'][] = $item_id;
			}
			$batch['processing_ids'] = array_values( array_diff( $batch['processing_ids'], array( $item_id ) ) );
			if ( ! empty( $batch['processing'] ) ) {
				--$batch['processing'];
			}
		} elseif ( $status === 'failed' ) {
			$batch['failed'] = isset( $batch['failed'] ) ? $batch['failed'] + 1 : 1;
			if ( $item_id > 0 && ! in_array( $item_id, $batch['failed_ids'], true ) ) {
				$batch['failed_ids'][] = $item_id;
			}
			if ( $item_id > 0 ) {
				$batch['failed_items']   = array_values(
					array_filter(
						$batch['failed_items'],
						function ( $item ) use ( $item_id ) {
							return ! is_array( $item ) || (int) ( $item['item_id'] ?? 0 ) !== $item_id;
						}
					)
				);
				$batch['failed_items'][] = array_merge(
					array(
						'item_id' => $item_id,
						'message' => $error_message !== '' ? $error_message : __( 'Processing failed.', 'seo-booster' ),
					),
					static::get_failed_item_meta( $item_id, $error_message )
				);
			}
			$batch['processing_ids'] = array_values( array_diff( $batch['processing_ids'], array( $item_id ) ) );
			if ( ! empty( $batch['processing'] ) ) {
				--$batch['processing'];
			}
		} elseif ( $status === 'processing' ) {
			$batch['processing'] = isset( $batch['processing'] ) ? $batch['processing'] + 1 : 1;
			if ( $item_id > 0 && ! in_array( $item_id, $batch['processing_ids'], true ) ) {
				$batch['processing_ids'][] = $item_id;
			}
		}

		set_transient( static::get_transient_prefix() . $batch_id, $batch, static::TRANSIENT_TTL );
	}

	/**
	 * @param string $batch_id Batch ID.
	 * @param array  $batch    Batch data.
	 * @return array
	 */
	protected static function status_response( $batch_id, array $batch ) {
		$total      = (int) ( $batch['total'] ?? 0 );
		$processed  = (int) ( $batch['processed'] ?? 0 );
		$failed     = (int) ( $batch['failed'] ?? 0 );
		$queued     = (int) ( $batch['queued'] ?? 0 );
		$processing = (int) ( $batch['processing'] ?? 0 );
		$remaining  = max( 0, $queued - $processed - $failed );
		$cancelled  = ! empty( $batch['cancelled'] );
		$completed  = ! $cancelled && $remaining === 0 && $queued > 0 && $processing === 0;

		$next_item_id = 0;
		if ( ! $completed && ! $cancelled && ! empty( $batch['item_ids'] ) ) {
			$done = array_merge(
				$batch['processed_ids'] ?? array(),
				$batch['failed_ids'] ?? array(),
				$batch['processing_ids'] ?? array()
			);
			foreach ( $batch['item_ids'] as $id ) {
				if ( ! in_array( $id, $done, true ) ) {
					$next_item_id = (int) $id;
					break;
				}
			}
		}

		$failed_items = isset( $batch['failed_items'] ) && is_array( $batch['failed_items'] )
			? $batch['failed_items']
			: array();

		$item_key = static::get_item_id_post_key();

		return array(
			'batch_id'          => $batch_id,
			'total'             => $total,
			'processed'         => $processed,
			'failed'            => $failed,
			'failed_items'      => array_slice( $failed_items, 0, 50 ),
			'failed_ids'        => array_slice( $batch['failed_ids'] ?? array(), 0, 50 ),
			'remaining'         => $remaining,
			'completed'         => $completed,
			'cancelled'         => $cancelled,
			'next_item_id'      => $next_item_id,
			'next_' . $item_key => $next_item_id,
		);
	}

	/**
	 * @param array|false $batch Batch data.
	 * @return array
	 */
	protected static function progress_from_batch( $batch ) {
		if ( ! $batch ) {
			return array(
				'processed' => 0,
				'total'     => 0,
				'failed'    => 0,
			);
		}

		return array(
			'processed' => (int) ( $batch['processed'] ?? 0 ),
			'total'     => (int) ( $batch['total'] ?? 0 ),
			'failed'    => (int) ( $batch['failed'] ?? 0 ),
		);
	}
}
