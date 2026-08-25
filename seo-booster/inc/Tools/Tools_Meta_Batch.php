<?php

namespace Cleverplugins\SEOBooster\Tools;

use Cleverplugins\SEOBooster\LLM_Content_Condenser;
use Cleverplugins\SEOBooster\LLM_Helper;
use Cleverplugins\SEOBooster\LLM_WP_Connector_Service;
use Cleverplugins\SEOBooster\SEO_Plugin_Registry;
use Cleverplugins\SEOBooster\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools-page batch processing for bulk SEO meta generation.
 *
 * @package Cleverplugins\SEOBooster\Tools
 */
class Tools_Meta_Batch extends Tools_Batch_Base {

	/**
	 * Initialize AJAX hooks.
	 *
	 * @return void
	 */
	public static function init() {
		parent::init();
		add_action( 'wp_ajax_sb_tools_meta_revert_batch', array( __CLASS__, 'ajax_revert_batch' ) );
		add_action( 'wp_ajax_sb_tools_meta_finalize_batch', array( __CLASS__, 'ajax_finalize_batch' ) );
	}

	/** @inheritDoc */
	public static function get_transient_prefix() {
		return 'sb_tools_meta_batch_';
	}

	/** @inheritDoc */
	public static function get_nonce_action() {
		return 'sb_tools_meta_nonce';
	}

	/** @inheritDoc */
	public static function get_ajax_action_start() {
		return 'sb_tools_meta_start_batch';
	}

	/** @inheritDoc */
	public static function get_ajax_action_process() {
		return 'sb_tools_meta_process';
	}

	/** @inheritDoc */
	public static function get_ajax_action_status() {
		return 'sb_tools_meta_batch_status';
	}

	/** @inheritDoc */
	public static function get_ajax_action_cancel() {
		return 'sb_tools_meta_cancel_batch';
	}

	/** @inheritDoc */
	public static function get_item_id_post_key() {
		return 'post_id';
	}

	/** @inheritDoc */
	public static function get_item_ids_response_key() {
		return 'post_ids';
	}

	/** @inheritDoc */
	protected static function validate_preconditions() {
		if ( ! SEO_Meta_Writer::get_target() ) {
			return SEO_Plugin_Registry::get_bulk_write_requirement_message();
		}

		if ( ! self::ai_is_available() ) {
			$message = Tools_Meta_Scanner::get_ai_unavailable_message();
			return '' !== $message ? $message : __( 'AI is not configured for meta generation.', 'seo-booster' );
		}

		return '';
	}

	/** @inheritDoc */
	protected static function prepare_batch_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in Tools_Batch_Base::ajax_start_batch() before this runs.
		$process_scope = isset( $_POST['process_scope'] )
			? sanitize_text_field( wp_unslash( $_POST['process_scope'] ) )
			: 'selected';

		$filters = isset( $_POST['filters'] ) && is_array( $_POST['filters'] )
			? array_values(
				array_intersect(
					array_map( 'sanitize_text_field', wp_unslash( $_POST['filters'] ) ),
					Tools_Meta_Scanner::get_filter_keys()
				)
			)
			: array();

		$post_types = Tools_Meta_Scanner::parse_post_types(
			isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
				? wp_unslash( $_POST['post_types'] )
				: Tools_Meta_Scanner::get_user_post_types()
		);

		if ( 'all_matching' === $process_scope ) {
			if ( empty( $filters ) ) {
				wp_send_json_error( array( 'message' => __( 'Select at least one scan filter.', 'seo-booster' ) ) );
			}
			$post_ids = Tools_Meta_Scanner::get_matching_post_ids( $filters, $post_types );
		} else {
			$post_ids = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] )
				? array_map( 'intval', wp_unslash( $_POST['post_ids'] ) )
				: array();

			$post_ids = array_values(
				array_filter(
					$post_ids,
					function ( $id ) {
						return $id > 0 && get_post( $id );
					}
				)
			);

			if ( ! empty( $filters ) ) {
				$post_ids = Tools_Meta_Scanner::filter_still_matching_ids( $post_ids, $filters, $post_types );
			}
		}

		if ( empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No matching content selected.', 'seo-booster' ) ) );
		}

		$post_ids = array_values(
			array_filter(
				$post_ids,
				function ( $id ) {
					return Utils::user_can_edit_object( (int) $id );
				}
			)
		);

		if ( empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No matching content selected.', 'seo-booster' ) ) );
		}

		$apply_fields = Tools_Meta_Scanner::parse_apply_fields(
			isset( $_POST['apply_fields'] ) && is_array( $_POST['apply_fields'] )
				? wp_unslash( $_POST['apply_fields'] )
				: null
		);

		if ( ! self::has_any_apply_field( $apply_fields ) ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one field to apply.', 'seo-booster' ) ) );
		}

		$overwrite = ! empty( $_POST['overwrite_existing'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return array(
			'item_ids' => $post_ids,
			'config'   => array(
				'apply_fields'       => $apply_fields,
				'overwrite_existing' => $overwrite,
				'scan_filters'       => $filters,
				'post_types'         => $post_types,
				'process_scope'      => $process_scope,
			),
		);
	}

	/** @inheritDoc */
	protected static function on_batch_start( array $item_ids, array $config ) {
		if ( ! empty( $config['apply_fields'] ) && is_array( $config['apply_fields'] ) ) {
			Tools_Meta_Scanner::save_user_apply_fields( $config['apply_fields'] );
		}
	}

	/** @inheritDoc */
	protected static function get_start_batch_extra_response( array $config ) {
		return array(
			'apply_fields' => $config['apply_fields'] ?? Tools_Meta_Scanner::default_apply_fields(),
		);
	}

	/** @inheritDoc */
	public static function process_single( $item_id, array $config ) {
		$post_id = (int) $item_id;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			throw new \Exception( esc_html__( 'Post not found', 'seo-booster' ) );
		}

		$apply_fields = isset( $config['apply_fields'] ) && is_array( $config['apply_fields'] )
			? $config['apply_fields']
			: Tools_Meta_Scanner::default_apply_fields();
		$overwrite    = ! empty( $config['overwrite_existing'] );

		$before   = SEO_Meta_Writer::read( $post_id );
		$writable = SEO_Meta_Writer::get_writable_fields( $post_id, $apply_fields, $overwrite );

		if ( empty( $writable ) ) {
			return array(
				'post_id'      => $post_id,
				'post_title'   => $post->post_title,
				'post_type'    => $post->post_type,
				'edit_url'     => get_edit_post_link( $post_id, 'raw' ) ? get_edit_post_link( $post_id, 'raw' ) : '',
				'skipped'      => true,
				'message'      => __( 'Skipped: selected fields already have values. Enable overwrite to update.', 'seo-booster' ),
				'before'       => $before,
				'after'        => $before,
				'apply_fields' => $apply_fields,
			);
		}

		SEO_Meta_Writer::backup( $post_id );

		$language  = LLM_Helper::get_post_language( $post_id );
		$condenser = new LLM_Content_Condenser();
		$condensed = $condenser->condense_post_content( $post_id, array( 'profile' => LLM_Content_Condenser::PROFILE_BULK ) );

		$writable_fields = array_keys( $writable );

		$service   = new LLM_WP_Connector_Service();
		$generated = $service->generate_suggestions(
			$post_id,
			$condensed,
			$language,
			'',
			array(
				'variant'       => LLM_WP_Connector_Service::VARIANT_BULK,
				'source'        => 'tools-meta',
				'fields_needed' => $writable_fields,
				'gsc_limit'     => 10,
			)
		);

		$picked = self::pick_best_suggestion( $generated, $writable );
		$after  = SEO_Meta_Writer::write( $post_id, $picked, $apply_fields, $overwrite );

		return array(
			'post_id'      => $post_id,
			'post_title'   => $post->post_title,
			'post_type'    => $post->post_type,
			'edit_url'     => get_edit_post_link( $post_id, 'raw' ) ? get_edit_post_link( $post_id, 'raw' ) : '',
			'before'       => $before,
			'after'        => $after,
			'generated'    => $picked,
			'apply_fields' => $apply_fields,
		);
	}

	/** @inheritDoc */
	protected static function get_failed_item_meta( $item_id, $error_message ) {
		$post = get_post( $item_id );
		return array(
			'post_id'    => (int) $item_id,
			'post_title' => $post ? $post->post_title : '',
			'edit_url'   => get_edit_post_link( $item_id, 'raw' ) ? get_edit_post_link( $item_id, 'raw' ) : '',
		);
	}

	/**
	 * AJAX: revert last bulk meta batch.
	 *
	 * @return void
	 */
	public static function ajax_revert_batch() {
		check_ajax_referer( static::get_nonce_action(), 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		if ( ! SEO_Meta_Writer::has_revertable_batch() ) {
			wp_send_json_error( array( 'message' => __( 'No bulk meta batch to revert.', 'seo-booster' ) ) );
		}

		$result = SEO_Meta_Writer::restore_last_batch();

		wp_send_json_success(
			array(
				'message'  => sprintf(
					/* translators: 1: restored count, 2: skipped count */
					__( 'Reverted %1$d item(s). Skipped %2$d.', 'seo-booster' ),
					(int) $result['restored'],
					(int) $result['skipped']
				),
				'restored' => (int) $result['restored'],
				'skipped'  => (int) $result['skipped'],
			)
		);
	}

	/**
	 * AJAX: store processed IDs from a completed batch for one-click revert.
	 *
	 * @return void
	 */
	public static function ajax_finalize_batch() {
		check_ajax_referer( static::get_nonce_action(), 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
		if ( '' === $batch_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid batch ID', 'seo-booster' ) ) );
		}

		$batch = static::get_batch( $batch_id );
		if ( ! $batch ) {
			wp_send_json_error( array( 'message' => __( 'Batch not found', 'seo-booster' ) ) );
		}

		$processed_ids = isset( $batch['processed_ids'] ) && is_array( $batch['processed_ids'] )
			? array_map( 'intval', $batch['processed_ids'] )
			: array();

		$processed_ids = array_values(
			array_filter(
				$processed_ids,
				function ( $post_id ) {
					return $post_id > 0 && get_post_meta( $post_id, SEO_Meta_Writer::BACKUP_META_KEY, true );
				}
			)
		);

		if ( empty( $processed_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No processed items to remember for revert.', 'seo-booster' ) ) );
		}

		SEO_Meta_Writer::remember_last_batch( $processed_ids );

		wp_send_json_success(
			array(
				'message' => __( 'Last bulk run saved for revert.', 'seo-booster' ),
				'count'   => count( $processed_ids ),
			)
		);
	}

	/**
	 * @return bool
	 */
	private static function ai_is_available() {
		return Tools_Meta_Scanner::ai_is_available();
	}

	/**
	 * @param array $apply_fields Apply fields.
	 * @return bool
	 */
	private static function has_any_apply_field( array $apply_fields ) {
		foreach ( $apply_fields as $enabled ) {
			if ( $enabled ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array $generated      AI response.
	 * @param array $writable_fields title/description keys that can be written.
	 * @return array{title?: string, description?: string}
	 */
	private static function pick_best_suggestion( array $generated, array $writable_fields ) {
		$picked = array();

		if ( ! empty( $writable_fields['title'] ) && ! empty( $generated['titles'] ) && is_array( $generated['titles'] ) ) {
			foreach ( $generated['titles'] as $title ) {
				$title = trim( (string) $title );
				if ( '' !== $title ) {
					$picked['title'] = SEO_Meta_Writer::truncate_title( $title );
					break;
				}
			}
		}

		if ( ! empty( $writable_fields['description'] ) && ! empty( $generated['descriptions'] ) && is_array( $generated['descriptions'] ) ) {
			foreach ( $generated['descriptions'] as $description ) {
				$description = trim( (string) $description );
				if ( '' !== $description ) {
					$picked['description'] = SEO_Meta_Writer::truncate_description( $description );
					break;
				}
			}
		}

		if ( empty( $picked ) ) {
			throw new \Exception( esc_html__( 'AI did not return usable title or description suggestions.', 'seo-booster' ) );
		}

		return $picked;
	}
}
