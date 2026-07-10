<?php
/**
 * Autolink opportunity batch (Premium).
 *
 * @fs_premium_only
 *
 * @package Cleverplugins\SEOBooster\Tools
 */

namespace Cleverplugins\SEOBooster\Tools;

use Cleverplugins\SEOBooster\Seobooster2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batch create autolink rules or enable autolink on posts.
 */
class Tools_Autolink_Opportunities_Batch extends Tools_Batch_Base {

	/**
	 * Rule IDs created during current batch (for revert).
	 *
	 * @var int[]
	 */
	private static $created_rule_ids = array();

	/**
	 * @return void
	 */
	public static function init() {
		parent::init();
		add_action( 'wp_ajax_sb_tools_autolink_opportunities_revert_batch', array( __CLASS__, 'ajax_revert_batch' ) );
		add_action( 'wp_ajax_sb_tools_autolink_opportunities_finalize_batch', array( __CLASS__, 'ajax_finalize_batch' ) );
	}

	/** @inheritDoc */
	public static function get_transient_prefix() {
		return 'sb_tools_autolink_opp_batch_';
	}

	/** @inheritDoc */
	public static function get_nonce_action() {
		return 'sb_tools_autolink_opportunities_nonce';
	}

	/** @inheritDoc */
	public static function get_ajax_action_start() {
		return 'sb_tools_autolink_opportunities_start_batch';
	}

	/** @inheritDoc */
	public static function get_ajax_action_process() {
		return 'sb_tools_autolink_opportunities_process';
	}

	/** @inheritDoc */
	public static function get_ajax_action_status() {
		return 'sb_tools_autolink_opportunities_batch_status';
	}

	/** @inheritDoc */
	public static function get_ajax_action_cancel() {
		return 'sb_tools_autolink_opportunities_cancel_batch';
	}

	/** @inheritDoc */
	public static function get_item_id_post_key() {
		return 'item_id';
	}

	/** @inheritDoc */
	public static function get_item_ids_response_key() {
		return 'item_ids';
	}

	/** @inheritDoc */
	protected static function validate_preconditions() {
		if ( ! get_option( 'seobooster_internal_linking', false ) ) {
			return __( 'Enable automatic internal linking in SEO Booster Settings first.', 'seo-booster' );
		}

		return '';
	}

	/** @inheritDoc */
	protected static function on_batch_start( array $item_ids, array $config ) {
		self::$created_rule_ids = array();
	}

	/** @inheritDoc */
	protected static function prepare_batch_from_request() {
		$process_scope = isset( $_POST['process_scope'] )
			? sanitize_text_field( wp_unslash( $_POST['process_scope'] ) )
			: 'selected';

		$filter = isset( $_POST['filter'] ) ? sanitize_key( wp_unslash( $_POST['filter'] ) ) : 'missing_rules';
		if ( ! in_array( $filter, Tools_GSC_Helper::get_autolink_filter_keys(), true ) ) {
			$filter = 'missing_rules';
		}

		$post_types = Tools_Meta_Scanner::parse_post_types(
			isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
				? wp_unslash( $_POST['post_types'] )
				: Tools_Meta_Scanner::get_default_post_types()
		);

		$item_meta = array();
		$item_ids  = array();

		if ( $process_scope === 'all_matching' ) {
			$scan = self::get_full_scan( $filter, $post_types );
			foreach ( $scan as $item ) {
				self::add_item_from_scan_row( $item, $filter, $item_ids, $item_meta );
			}
		} else {
			$raw_items = isset( $_POST['items'] ) && is_array( $_POST['items'] )
				? wp_unslash( $_POST['items'] )
				: array();

			foreach ( $raw_items as $raw ) {
				if ( ! is_array( $raw ) ) {
					continue;
				}
				self::add_item_from_scan_row( $raw, $filter, $item_ids, $item_meta );
			}
		}

		if ( empty( $item_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No matching items selected.', 'seo-booster' ) ) );
		}

		return array(
			'item_ids' => $item_ids,
			'config'   => array(
				'item_meta'        => $item_meta,
				'filter'           => $filter,
				'process_scope'    => $process_scope,
				'post_types'       => $post_types,
				'created_rule_ids' => array(),
			),
		);
	}

	/**
	 * Full scan rows (not preview-limited).
	 *
	 * @param string   $filter     Filter.
	 * @param string[] $post_types Post types.
	 * @return array
	 */
	private static function get_full_scan( $filter, array $post_types ) {
		if ( $filter === 'missing_rules' ) {
			$page_groups = Tools_GSC_Helper::get_autolink_missing_rules_items();
			return Tools_GSC_Helper::flatten_autolink_page_groups( $page_groups );
		}

		// enable_high_traffic - re-query all matching posts.
		global $wpdb;
		$matching   = array();
		$min_clicks = 10;

		$page_clicks = $wpdb->get_results(
			"SELECT qk.page, COALESCE(SUM(qkh.clicks), 0) AS clicks
            FROM {$wpdb->prefix}sb2_query_keywords AS qk
            LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh
                ON qk.id = qkh.query_keywords_id
            GROUP BY qk.page
            HAVING clicks >= {$min_clicks}
            ORDER BY clicks DESC",
			ARRAY_A
		);

		if ( ! is_array( $page_clicks ) ) {
			return array();
		}

		foreach ( $page_clicks as $row ) {
			$page = (string) ( $row['page'] ?? '' );
			if ( $page === '' ) {
				continue;
			}
			$post_id = (int) url_to_postid( $page );
			if ( $post_id <= 0 ) {
				continue;
			}
			$post = get_post( $post_id );
			if ( ! $post || $post->post_status !== 'publish' || ! in_array( $post->post_type, $post_types, true ) ) {
				continue;
			}
			if ( get_post_meta( $post_id, '_sbp-autolink', true ) === 'yes' ) {
				continue;
			}
			$matching[] = array(
				'post_id'    => $post_id,
				'post_title' => get_the_title( $post_id ),
				'page_url'   => $page,
				'clicks'     => (int) ( $row['clicks'] ?? 0 ),
			);
		}

		return $matching;
	}

	/**
	 * @param array    $item       Scan row.
	 * @param string   $filter     Filter.
	 * @param int[]    $item_ids   Item IDs (by ref).
	 * @param array    $item_meta  Meta (by ref).
	 * @return void
	 */
	private static function add_item_from_scan_row( array $item, $filter, array &$item_ids, array &$item_meta ) {
		if ( $filter === 'enable_high_traffic' ) {
			$post_id = (int) ( $item['post_id'] ?? $item['id'] ?? 0 );
			if ( $post_id <= 0 ) {
				return;
			}
			if ( ! in_array( $post_id, $item_ids, true ) ) {
				$item_ids[]            = $post_id;
				$item_meta[ $post_id ] = array(
					'mode'    => 'enable_autolink',
					'post_id' => $post_id,
				);
			}
			return;
		}

		if ( ! empty( $item['keywords'] ) && is_array( $item['keywords'] ) ) {
			foreach ( $item['keywords'] as $kw ) {
				if ( ! is_array( $kw ) ) {
					continue;
				}
				self::add_item_from_scan_row(
					array_merge(
						$kw,
						array(
							'target_url' => $kw['target_url'] ?? ( $item['target_url'] ?? '' ),
							'post_id'    => $item['post_id'] ?? 0,
						)
					),
					$filter,
					$item_ids,
					$item_meta
				);
			}
			return;
		}

		$query_id = (int) ( $item['query_id'] ?? $item['id'] ?? 0 );
		if ( $query_id <= 0 ) {
			return;
		}
		if ( in_array( $query_id, $item_ids, true ) ) {
			return;
		}

		$item_ids[]             = $query_id;
		$item_meta[ $query_id ] = array(
			'mode'       => 'create_rule',
			'query_id'   => $query_id,
			'keyword'    => sanitize_text_field( (string) ( $item['keyword'] ?? '' ) ),
			'target_url' => esc_url_raw( (string) ( $item['target_url'] ?? '' ) ),
		);
	}

	/** @inheritDoc */
	public static function process_single( $item_id, array $config ) {
		$item_id = (int) $item_id;
		$meta    = isset( $config['item_meta'][ $item_id ] ) && is_array( $config['item_meta'][ $item_id ] )
			? $config['item_meta'][ $item_id ]
			: null;

		if ( ! $meta ) {
			throw new \Exception( __( 'Item data not found.', 'seo-booster' ) );
		}

		if ( ( $meta['mode'] ?? '' ) === 'enable_autolink' ) {
			$post_id = (int) ( $meta['post_id'] ?? $item_id );
			$post    = get_post( $post_id );
			if ( ! $post ) {
				throw new \Exception( __( 'Post not found', 'seo-booster' ) );
			}

			$before = get_post_meta( $post_id, '_sbp-autolink', true );
			update_post_meta( $post_id, '_sbp-autolink', 'yes' );

			return array(
				'item_id'    => $item_id,
				'post_id'    => $post_id,
				'post_title' => $post->post_title,
				'edit_url'   => get_edit_post_link( $post_id, 'raw' ) ?: '',
				'before'     => $before === 'yes' ? 'yes' : 'no',
				'after'      => 'yes',
				'action'     => 'enable_autolink',
			);
		}

		$keyword    = trim( (string) ( $meta['keyword'] ?? '' ) );
		$target_url = trim( (string) ( $meta['target_url'] ?? '' ) );

		if ( $keyword === '' || $target_url === '' ) {
			throw new \Exception( __( 'Invalid autolink rule data.', 'seo-booster' ) );
		}

		global $wpdb;

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}sb2_autolink WHERE LOWER(keyword) = %s LIMIT 1",
				Tools_GSC_Helper::normalize_keyword( $keyword )
			)
		);

		if ( $existing ) {
			throw new \Exception( __( 'An autolink rule for this keyword already exists.', 'seo-booster' ) );
		}

		$inserted = $wpdb->insert(
			"{$wpdb->prefix}sb2_autolink",
			array(
				'keyword' => $keyword,
				'url'     => $target_url,
			),
			array( '%s', '%s' )
		);

		if ( ! $inserted ) {
			throw new \Exception( __( 'Could not create autolink rule.', 'seo-booster' ) );
		}

		$rule_id = (int) $wpdb->insert_id;

		return array(
			'item_id'    => $item_id,
			'rule_id'    => $rule_id,
			'keyword'    => $keyword,
			'target_url' => $target_url,
			'action'     => 'create_rule',
		);
	}

	/** @inheritDoc */
	protected static function on_item_processed( $batch_id, $item_id, array $payload, array $config ) {
		if ( ( $payload['action'] ?? '' ) !== 'create_rule' || empty( $payload['rule_id'] ) ) {
			return;
		}

		$batch = static::get_batch( $batch_id );
		if ( ! $batch ) {
			return;
		}

		if ( ! isset( $batch['config']['created_rule_ids'] ) || ! is_array( $batch['config']['created_rule_ids'] ) ) {
			$batch['config']['created_rule_ids'] = array();
		}

		$rule_id = (int) $payload['rule_id'];
		if ( $rule_id > 0 && ! in_array( $rule_id, $batch['config']['created_rule_ids'], true ) ) {
			$batch['config']['created_rule_ids'][] = $rule_id;
			set_transient( static::get_transient_prefix() . $batch_id, $batch, static::TRANSIENT_TTL );
		}

		self::$created_rule_ids[] = $rule_id;
	}

	/** @inheritDoc */
	protected static function get_failed_item_meta( $item_id, $error_message ) {
		return array(
			'item_id' => (int) $item_id,
			'message' => $error_message,
		);
	}

	/**
	 * AJAX: revert last autolink batch (delete created rules).
	 *
	 * @return void
	 */
	public static function ajax_revert_batch() {
		check_ajax_referer( static::get_nonce_action(), 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$rule_ids = get_user_meta( get_current_user_id(), 'sb_tools_autolink_last_batch_ids', true );
		if ( ! is_array( $rule_ids ) || empty( $rule_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No autolink batch to revert.', 'seo-booster' ) ) );
		}

		global $wpdb;
		$deleted = 0;

		foreach ( $rule_ids as $rule_id ) {
			$rule_id = (int) $rule_id;
			if ( $rule_id <= 0 ) {
				continue;
			}
			$result = $wpdb->delete( "{$wpdb->prefix}sb2_autolink", array( 'id' => $rule_id ), array( '%d' ) );
			if ( $result ) {
				++$deleted;
			}
		}

		delete_user_meta( get_current_user_id(), 'sb_tools_autolink_last_batch_ids' );
		delete_user_meta( get_current_user_id(), 'sb_tools_autolink_last_batch_at' );

		if ( $deleted > 0 ) {
			Seobooster2::flush_autolink_caches();
		}

		wp_send_json_success(
			array(
				'message' => sprintf( __( 'Removed %d autolink rule(s).', 'seo-booster' ), $deleted ),
				'deleted' => $deleted,
			)
		);
	}

	/**
	 * AJAX: remember created rule IDs for revert.
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

		$rule_ids = self::$created_rule_ids;
		if ( empty( $rule_ids ) ) {
			$rule_ids = isset( $batch['config']['created_rule_ids'] ) && is_array( $batch['config']['created_rule_ids'] )
				? array_map( 'intval', $batch['config']['created_rule_ids'] )
				: array();
		}

		$rule_ids = array_values( array_filter( $rule_ids ) );

		if ( empty( $rule_ids ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'Batch complete. Enable-autolink actions cannot be reverted from this tool.', 'seo-booster' ),
					'count'   => 0,
				)
			);
		}

		update_user_meta( get_current_user_id(), 'sb_tools_autolink_last_batch_ids', $rule_ids );
		update_user_meta( get_current_user_id(), 'sb_tools_autolink_last_batch_at', time() );

		Seobooster2::flush_autolink_caches();

		wp_send_json_success(
			array(
				'message' => __( 'Last bulk run saved for revert.', 'seo-booster' ),
				'count'   => count( $rule_ids ),
			)
		);
	}
}
