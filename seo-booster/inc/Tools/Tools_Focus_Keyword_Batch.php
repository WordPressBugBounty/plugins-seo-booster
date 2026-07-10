<?php
/**
 * Focus keyword bulk batch (Premium).
 *
 * @fs_premium_only
 *
 * @package Cleverplugins\SEOBooster\Tools
 */

namespace Cleverplugins\SEOBooster\Tools;

use Cleverplugins\SEOBooster\SEO_Plugin_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batch write focus keywords with uniqueness enforcement.
 */
class Tools_Focus_Keyword_Batch extends Tools_Batch_Base {

	/**
	 * Keywords claimed during the current batch run (normalized => post_id).
	 *
	 * @var array<string, int>
	 */
	private static $batch_claimed = array();

	/**
	 * @return void
	 */
	public static function init() {
		parent::init();
		add_action( 'wp_ajax_sb_tools_focus_keyword_revert_batch', array( __CLASS__, 'ajax_revert_batch' ) );
		add_action( 'wp_ajax_sb_tools_focus_keyword_finalize_batch', array( __CLASS__, 'ajax_finalize_batch' ) );
	}

	/** @inheritDoc */
	public static function get_transient_prefix() {
		return 'sb_tools_focuskw_batch_';
	}

	/** @inheritDoc */
	public static function get_nonce_action() {
		return 'sb_tools_focus_keyword_nonce';
	}

	/** @inheritDoc */
	public static function get_ajax_action_start() {
		return 'sb_tools_focus_keyword_start_batch';
	}

	/** @inheritDoc */
	public static function get_ajax_action_process() {
		return 'sb_tools_focus_keyword_process';
	}

	/** @inheritDoc */
	public static function get_ajax_action_status() {
		return 'sb_tools_focus_keyword_batch_status';
	}

	/** @inheritDoc */
	public static function get_ajax_action_cancel() {
		return 'sb_tools_focus_keyword_cancel_batch';
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
		if ( ! SEO_Plugin_Registry::supports_focus_keyword() ) {
			return SEO_Plugin_Registry::get_focus_keyword_requirement_message();
		}

		return '';
	}

	/** @inheritDoc */
	protected static function on_batch_start( array $item_ids, array $config ) {
		self::$batch_claimed = array();
	}

	/** @inheritDoc */
	protected static function prepare_batch_from_request() {
		$process_scope = isset( $_POST['process_scope'] )
			? sanitize_text_field( wp_unslash( $_POST['process_scope'] ) )
			: 'selected';

		$post_types    = Tools_Focus_Keyword::get_user_post_types();
		$item_keywords = array();

		if ( $process_scope === 'all_matching' ) {
			$scan = Tools_Focus_Keyword::scan( $post_types );
			foreach ( $scan['all_items'] ?? array() as $item ) {
				$post_id = (int) ( $item['post_id'] ?? 0 );
				$keyword = trim( (string) ( $item['suggested_keyword'] ?? '' ) );
				if ( $post_id > 0 && $keyword !== '' ) {
					$item_keywords[ $post_id ] = $keyword;
				}
			}
		} else {
			$raw_items = isset( $_POST['items'] ) && is_array( $_POST['items'] )
				? wp_unslash( $_POST['items'] )
				: array();

			foreach ( $raw_items as $raw ) {
				if ( ! is_array( $raw ) ) {
					continue;
				}
				$post_id = isset( $raw['post_id'] ) ? (int) $raw['post_id'] : 0;
				$keyword = isset( $raw['suggested_keyword'] ) ? sanitize_text_field( (string) $raw['suggested_keyword'] ) : '';
				if ( $post_id > 0 && $keyword !== '' ) {
					$item_keywords[ $post_id ] = $keyword;
				}
			}
		}

		$post_ids = array_keys( $item_keywords );

		if ( empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No matching items selected.', 'seo-booster' ) ) );
		}

		return array(
			'item_ids' => $post_ids,
			'config'   => array(
				'item_keywords' => $item_keywords,
				'assigned_map'  => Tools_GSC_Helper::get_site_focus_keyword_map(),
				'batch_claimed' => array(),
				'process_scope' => $process_scope,
				'post_types'    => $post_types,
			),
		);
	}

	/** @inheritDoc */
	public static function process_single( $item_id, array $config ) {
		$post_id = (int) $item_id;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			throw new \Exception( __( 'Post not found', 'seo-booster' ) );
		}

		$item_keywords = isset( $config['item_keywords'] ) && is_array( $config['item_keywords'] )
			? $config['item_keywords']
			: array();
		$keyword       = trim( (string) ( $item_keywords[ $post_id ] ?? '' ) );

		if ( $keyword === '' ) {
			throw new \Exception( __( 'No suggested keyword for this item.', 'seo-booster' ) );
		}

		if ( trim( SEO_Meta_Writer::read_focus_keyword( $post_id ) ) !== '' ) {
			return array(
				'post_id'    => $post_id,
				'post_title' => $post->post_title,
				'edit_url'   => get_edit_post_link( $post_id, 'raw' ) ?: '',
				'skipped'    => true,
				'message'    => __( 'Skipped — focus keyword already set.', 'seo-booster' ),
				'before'     => SEO_Meta_Writer::read_focus_keyword( $post_id ),
				'after'      => SEO_Meta_Writer::read_focus_keyword( $post_id ),
			);
		}

		$normalized = Tools_GSC_Helper::normalize_keyword( $keyword );
		$assigned   = Tools_GSC_Helper::get_site_focus_keyword_map();

		if ( isset( self::$batch_claimed[ $normalized ] ) ) {
			throw new \Exception( __( 'Keyword already assigned in this batch.', 'seo-booster' ) );
		}

		if ( isset( $assigned[ $normalized ] ) && (int) $assigned[ $normalized ] !== $post_id ) {
			throw new \Exception( __( 'Keyword already used on another page.', 'seo-booster' ) );
		}

		$before = SEO_Meta_Writer::read_focus_keyword( $post_id );
		SEO_Meta_Writer::backup_focus_keyword( $post_id );
		$after = SEO_Meta_Writer::write_focus_keyword( $post_id, $keyword, false );

		self::$batch_claimed[ $normalized ] = $post_id;

		return array(
			'post_id'    => $post_id,
			'post_title' => $post->post_title,
			'edit_url'   => get_edit_post_link( $post_id, 'raw' ) ?: '',
			'before'     => $before,
			'after'      => $after,
			'keyword'    => $after,
		);
	}

	/** @inheritDoc */
	protected static function get_failed_item_meta( $item_id, $error_message ) {
		$post = get_post( $item_id );
		return array(
			'post_id'    => (int) $item_id,
			'post_title' => $post ? $post->post_title : '',
			'edit_url'   => get_edit_post_link( $item_id, 'raw' ) ?: '',
		);
	}

	/**
	 * AJAX: revert last focus keyword batch.
	 *
	 * @return void
	 */
	public static function ajax_revert_batch() {
		check_ajax_referer( static::get_nonce_action(), 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		if ( ! SEO_Meta_Writer::has_revertable_focus_keyword_batch() ) {
			wp_send_json_error( array( 'message' => __( 'No focus keyword batch to revert.', 'seo-booster' ) ) );
		}

		$result = SEO_Meta_Writer::restore_focus_keyword_last_batch();

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

		$processed_ids = array_values(
			array_filter(
				$processed_ids,
				function ( $post_id ) {
					return $post_id > 0 && get_post_meta( $post_id, SEO_Meta_Writer::FOCUSKW_BACKUP_META_KEY, true );
				}
			)
		);

		if ( empty( $processed_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No processed items to remember for revert.', 'seo-booster' ) ) );
		}

		SEO_Meta_Writer::remember_focus_keyword_last_batch( $processed_ids );

		wp_send_json_success(
			array(
				'message' => __( 'Last bulk run saved for revert.', 'seo-booster' ),
				'count'   => count( $processed_ids ),
			)
		);
	}
}
