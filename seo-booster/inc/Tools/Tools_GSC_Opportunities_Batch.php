<?php
/**
 * GSC opportunity bulk meta batch (Premium).
 *
 * @fs_premium_only
 *
 * @package Cleverplugins\SEOBooster\Tools
 */

namespace Cleverplugins\SEOBooster\Tools;

use Cleverplugins\SEOBooster\LLM_Content_Condenser;
use Cleverplugins\SEOBooster\LLM_Helper;
use Cleverplugins\SEOBooster\LLM_WP_Connector_Service;
use Cleverplugins\SEOBooster\SEO_Plugin_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batch AI meta fixes for GSC opportunities.
 */
class Tools_GSC_Opportunities_Batch extends Tools_Batch_Base {

	/**
	 * @return void
	 */
	public static function init() {
		parent::init();
		add_action( 'wp_ajax_sb_tools_gsc_opportunities_revert_batch', array( __CLASS__, 'ajax_revert_batch' ) );
		add_action( 'wp_ajax_sb_tools_gsc_opportunities_finalize_batch', array( __CLASS__, 'ajax_finalize_batch' ) );
	}

	/** @inheritDoc */
	public static function get_transient_prefix() {
		return 'sb_tools_gsc_opp_batch_';
	}

	/** @inheritDoc */
	public static function get_nonce_action() {
		return 'sb_tools_gsc_opportunities_nonce';
	}

	/** @inheritDoc */
	public static function get_ajax_action_start() {
		return 'sb_tools_gsc_opportunities_start_batch';
	}

	/** @inheritDoc */
	public static function get_ajax_action_process() {
		return 'sb_tools_gsc_opportunities_process';
	}

	/** @inheritDoc */
	public static function get_ajax_action_status() {
		return 'sb_tools_gsc_opportunities_batch_status';
	}

	/** @inheritDoc */
	public static function get_ajax_action_cancel() {
		return 'sb_tools_gsc_opportunities_cancel_batch';
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

		if ( ! Tools_Meta_Scanner::ai_is_available() ) {
			$message = Tools_Meta_Scanner::get_ai_unavailable_message();
			return $message !== '' ? $message : __( 'AI is not configured for meta generation.', 'seo-booster' );
		}

		return '';
	}

	/** @inheritDoc */
	protected static function prepare_batch_from_request() {
		$process_scope = isset( $_POST['process_scope'] )
			? sanitize_text_field( wp_unslash( $_POST['process_scope'] ) )
			: 'selected';

		$filters = isset( $_POST['filters'] ) && is_array( $_POST['filters'] )
			? array_values(
				array_intersect(
					array_map( 'sanitize_text_field', wp_unslash( $_POST['filters'] ) ),
					Tools_GSC_Helper::get_opportunity_filter_keys()
				)
			)
			: array();

		$apply_fields = Tools_Meta_Scanner::parse_apply_fields(
			isset( $_POST['apply_fields'] ) && is_array( $_POST['apply_fields'] )
				? wp_unslash( $_POST['apply_fields'] )
				: array(
					'title'       => true,
					'description' => true,
				)
		);

		if ( ! self::has_any_apply_field( $apply_fields ) ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one field to apply.', 'seo-booster' ) ) );
		}

		$overwrite = ! empty( $_POST['overwrite_existing'] );

		if ( empty( $filters ) ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one opportunity filter.', 'seo-booster' ) ) );
		}

		// Rebuild the grouped opportunity set server-side so the query cluster
		// for each page is authoritative (one AI rewrite per page, not per query).
		$scan       = Tools_GSC_Helper::scan_opportunities( $filters );
		$item_seeds = array();

		foreach ( $scan['all_items'] ?? array() as $item ) {
			$post_id = (int) ( $item['post_id'] ?? 0 );
			if ( $post_id <= 0 ) {
				continue;
			}

			$queries = array();
			foreach ( ( $item['queries'] ?? array() ) as $query_row ) {
				$query = trim( (string) ( $query_row['query'] ?? '' ) );
				if ( $query !== '' ) {
					$queries[] = $query;
				}
			}

			$item_seeds[ $post_id ] = array(
				'post_id'           => $post_id,
				'queries'           => $queries,
				'opportunity_types' => array_values( (array) ( $item['opportunity_types'] ?? array() ) ),
			);
		}

		if ( $process_scope === 'selected' ) {
			$selected = self::parse_selected_post_ids();
			if ( empty( $selected ) ) {
				wp_send_json_error( array( 'message' => __( 'No matching opportunities selected.', 'seo-booster' ) ) );
			}
			$item_seeds = array_intersect_key( $item_seeds, array_flip( $selected ) );
		}

		$post_ids = array_keys( $item_seeds );

		if ( empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No matching opportunities selected.', 'seo-booster' ) ) );
		}

		return array(
			'item_ids' => $post_ids,
			'config'   => array(
				'apply_fields'       => $apply_fields,
				'overwrite_existing' => $overwrite,
				'item_seeds'         => $item_seeds,
				'scan_filters'       => $filters,
				'process_scope'      => $process_scope,
			),
		);
	}

	/**
	 * Parse the selected post IDs from the request items payload.
	 *
	 * @return int[]
	 */
	private static function parse_selected_post_ids() {
		$raw_items = isset( $_POST['items'] ) && is_array( $_POST['items'] )
			? wp_unslash( $_POST['items'] )
			: array();

		$post_ids = array();
		foreach ( $raw_items as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$post_id = isset( $raw['post_id'] ) ? (int) $raw['post_id'] : 0;
			if ( $post_id > 0 ) {
				$post_ids[ $post_id ] = $post_id;
			}
		}

		return array_values( $post_ids );
	}

	/** @inheritDoc */
	public static function process_single( $item_id, array $config ) {
		$post_id = (int) $item_id;
		$seeds   = isset( $config['item_seeds'] ) && is_array( $config['item_seeds'] ) ? $config['item_seeds'] : array();
		$seed    = isset( $seeds[ $post_id ] ) && is_array( $seeds[ $post_id ] ) ? $seeds[ $post_id ] : null;

		if ( ! $seed ) {
			throw new \Exception( __( 'Opportunity data not found for this item.', 'seo-booster' ) );
		}

		$queries = isset( $seed['queries'] ) && is_array( $seed['queries'] )
			? array_values( array_filter( array_map( 'trim', $seed['queries'] ) ) )
			: array();

		if ( $post_id <= 0 || empty( $queries ) ) {
			throw new \Exception( __( 'Invalid opportunity row.', 'seo-booster' ) );
		}

		$primary_query = (string) $queries[0];

		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \Exception( __( 'Post not found', 'seo-booster' ) );
		}

		$apply_fields = isset( $config['apply_fields'] ) && is_array( $config['apply_fields'] )
			? $config['apply_fields']
			: Tools_Meta_Scanner::default_apply_fields();
		$overwrite    = ! empty( $config['overwrite_existing'] );

		$before   = SEO_Meta_Writer::read( $post_id );
		$writable = SEO_Meta_Writer::get_writable_fields( $post_id, $apply_fields, $overwrite );

		if ( empty( $writable ) ) {
			return array(
				'post_id'           => $post_id,
				'post_title'        => $post->post_title,
				'post_type'         => $post->post_type,
				'edit_url'          => get_edit_post_link( $post_id, 'raw' ) ?: '',
				'query'             => $primary_query,
				'queries'           => $queries,
				'opportunity_types' => array_values( (array) ( $seed['opportunity_types'] ?? array() ) ),
				'skipped'           => true,
				'message'           => __( 'Skipped — selected fields already have values. Enable overwrite to update.', 'seo-booster' ),
				'before'            => $before,
				'after'             => $before,
			);
		}

		SEO_Meta_Writer::backup( $post_id );

		$language  = LLM_Helper::get_post_language( $post_id );
		$condenser = new LLM_Content_Condenser();
		$condensed = $condenser->condense_post_content( $post_id, array( 'profile' => LLM_Content_Condenser::PROFILE_BULK ) );

		$service   = new LLM_WP_Connector_Service();
		$generated = $service->generate_suggestions(
			$post_id,
			$condensed,
			$language,
			'',
			array(
				'variant'          => LLM_WP_Connector_Service::VARIANT_BULK,
				'fields_needed'    => array_keys( $writable ),
				'gsc_limit'        => 10,
				'focus_seed_query' => $primary_query,
				'seed_queries'     => $queries,
			)
		);

		$picked = self::pick_best_suggestion( $generated, $writable );
		$after  = SEO_Meta_Writer::write( $post_id, $picked, $apply_fields, $overwrite );

		return array(
			'post_id'           => $post_id,
			'post_title'        => $post->post_title,
			'post_type'         => $post->post_type,
			'edit_url'          => get_edit_post_link( $post_id, 'raw' ) ?: '',
			'query'             => $primary_query,
			'queries'           => $queries,
			'opportunity_types' => array_values( (array) ( $seed['opportunity_types'] ?? array() ) ),
			'before'            => $before,
			'after'             => $after,
			'generated'         => $picked,
		);
	}

	/** @inheritDoc */
	protected static function get_failed_item_meta( $item_id, $error_message ) {
		return array(
			'post_id' => (int) $item_id,
			'message' => $error_message,
		);
	}

	/**
	 * AJAX: revert last bulk meta batch from GSC opportunities.
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
	 * AJAX: remember processed posts for revert.
	 *
	 * @return void
	 */
	public static function ajax_finalize_batch() {
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

		$processed_ids = isset( $batch['processed_ids'] ) && is_array( $batch['processed_ids'] )
			? array_map( 'intval', $batch['processed_ids'] )
			: array();

		$post_ids = array();
		foreach ( $processed_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id > 0 && get_post_meta( $post_id, SEO_Meta_Writer::BACKUP_META_KEY, true ) ) {
				$post_ids[ $post_id ] = $post_id;
			}
		}

		if ( empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No processed items to remember for revert.', 'seo-booster' ) ) );
		}

		SEO_Meta_Writer::remember_last_batch( array_values( $post_ids ) );

		wp_send_json_success(
			array(
				'message' => __( 'Last bulk run saved for revert.', 'seo-booster' ),
				'count'   => count( $post_ids ),
			)
		);
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
	 * @param array $writable_fields Writable field keys.
	 * @return array
	 */
	private static function pick_best_suggestion( array $generated, array $writable_fields ) {
		$picked = array();

		if ( ! empty( $writable_fields['title'] ) && ! empty( $generated['titles'] ) && is_array( $generated['titles'] ) ) {
			foreach ( $generated['titles'] as $title ) {
				$title = trim( (string) $title );
				if ( $title !== '' ) {
					$picked['title'] = SEO_Meta_Writer::truncate_title( $title );
					break;
				}
			}
		}

		if ( ! empty( $writable_fields['description'] ) && ! empty( $generated['descriptions'] ) && is_array( $generated['descriptions'] ) ) {
			foreach ( $generated['descriptions'] as $description ) {
				$description = trim( (string) $description );
				if ( $description !== '' ) {
					$picked['description'] = SEO_Meta_Writer::truncate_description( $description );
					break;
				}
			}
		}

		if ( empty( $picked ) ) {
			throw new \Exception( __( 'AI did not return usable title or description suggestions.', 'seo-booster' ) );
		}

		return $picked;
	}
}
