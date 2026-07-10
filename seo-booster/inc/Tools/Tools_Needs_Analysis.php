<?php
/**
 * Needs analysis overview tool (Premium).
 *
 * @fs_premium_only
 *
 * @package Cleverplugins\SEOBooster\Tools
 */

namespace Cleverplugins\SEOBooster\Tools;

use Cleverplugins\SEOBooster\SEO_Analysis;
use Cleverplugins\SEOBooster\SEO_Issues_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Surfaces never-analyzed or stale content and existing analysis issues.
 */
class Tools_Needs_Analysis {

	const STALE_DAYS    = 90;
	const PREVIEW_LIMIT = 50;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_sb_tools_scan_needs_analysis', array( __CLASS__, 'ajax_scan' ) );
		add_action( 'wp_ajax_sb_tools_queue_needs_analysis', array( __CLASS__, 'ajax_queue_analysis' ) );
	}

	/**
	 * Render admin tab.
	 *
	 * @return void
	 */
	public static function render_admin() {
		include SEOBOOSTER_PLUGINPATH . 'inc/Tools/views/needs-analysis-tool.php';
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Admin hook.
	 * @return void
	 */
	public static function enqueue_scripts( $hook ) {
		wp_enqueue_script(
			'sb-tools-needs-analysis',
			SEOBOOSTER_PLUGINURL . 'js/sb-tools-needs-analysis.js',
			array( 'jquery', 'sb-tools-ui' ),
			filemtime( SEOBOOSTER_PLUGINPATH . 'js/sb-tools-needs-analysis.js' ),
			true
		);

		wp_localize_script(
			'sb-tools-needs-analysis',
			'sbToolsNeedsAnalysis',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'sb_tools_needs_analysis_nonce' ),
				'strings'  => array(
					'scanning'      => __( 'Scanning…', 'seo-booster' ),
					'no_results'    => __( 'No matching content found.', 'seo-booster' ),
					'queue_confirm' => __( 'Queue SEO analysis for all matching items?', 'seo-booster' ),
					'queue_title'   => __( 'Queue SEO analysis', 'seo-booster' ),
					'queued'        => __( 'SEO analysis queued.', 'seo-booster' ),
					'queued_title'  => __( 'Analysis queued', 'seo-booster' ),
					'queue_status'  => __( '%d items queued for SEO analysis.', 'seo-booster' ),
					'error'         => __( 'Error', 'seo-booster' ),
				),
			)
		);
	}

	/**
	 * AJAX: scan for needs-analysis items.
	 *
	 * @return void
	 */
	public static function ajax_scan() {
		check_ajax_referer( 'sb_tools_needs_analysis_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$filter     = isset( $_POST['filter'] ) ? sanitize_key( wp_unslash( $_POST['filter'] ) ) : 'never_analyzed';
		$post_types = Tools_Meta_Scanner::parse_post_types(
			isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
				? wp_unslash( $_POST['post_types'] )
				: Tools_Meta_Scanner::get_default_post_types()
		);

		wp_send_json_success( self::scan( $filter, $post_types ) );
	}

	/**
	 * AJAX: queue bulk SEO analysis for matching posts.
	 *
	 * @return void
	 */
	public static function ajax_queue_analysis() {
		check_ajax_referer( 'sb_tools_needs_analysis_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$post_ids = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] )
			? array_map( 'intval', wp_unslash( $_POST['post_ids'] ) )
			: array();

		$post_ids = array_values(
			array_filter(
				$post_ids,
				function ( $id ) {
					return $id > 0 && get_post( $id ) && ! SEO_Issues_Manager::should_exclude_from_analysis( $id );
				}
			)
		);

		if ( empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No valid posts selected.', 'seo-booster' ) ) );
		}

		$batch_id     = 'sb_seo_bulk_' . get_current_user_id() . '_' . time();
		$batch_status = array(
			'total'          => count( $post_ids ),
			'queued'         => count( $post_ids ),
			'processed'      => 0,
			'failed'         => 0,
			'processing'     => 0,
			'user_id'        => get_current_user_id(),
			'created'        => time(),
			'post_ids'       => array_values( $post_ids ),
			'post_type'      => get_post_type( $post_ids[0] ),
			'processed_ids'  => array(),
			'failed_ids'     => array(),
			'processing_ids' => array(),
			'cancelled'      => false,
			'source'         => 'tools_needs_analysis',
		);

		set_transient( 'sb_seo_bulk_batch_' . $batch_id, $batch_status, 3600 );

		wp_send_json_success(
			array(
				'batch_id' => $batch_id,
				'total'    => count( $post_ids ),
				'message'  => __( 'SEO analysis has been queued. Your content will be analyzed automatically in the background — you can safely leave this page.', 'seo-booster' ),
			)
		);
	}

	/**
	 * Scan posts.
	 *
	 * @param string   $filter     never_analyzed|stale|has_issues.
	 * @param string[] $post_types Post types.
	 * @return array
	 */
	public static function scan( $filter, array $post_types ) {
		$matching = array();
		$stale_ts = time() - ( self::STALE_DAYS * DAY_IN_SECONDS );

		foreach ( $post_types as $post_type ) {
			$paged = 1;
			do {
				$query = new \WP_Query(
					array(
						'post_type'              => $post_type,
						'post_status'            => 'publish',
						'posts_per_page'         => 100,
						'paged'                  => $paged,
						'fields'                 => 'ids',
						'no_found_rows'          => true,
						'update_post_meta_cache' => true,
					)
				);

				foreach ( $query->posts as $post_id ) {
					$post_id = (int) $post_id;
					if ( $post_id <= 0 || SEO_Issues_Manager::should_exclude_from_analysis( $post_id ) ) {
						continue;
					}

					$status = self::get_analysis_status( $post_id );
					if ( ! self::matches_filter( $status, $filter ) ) {
						continue;
					}

					$matching[] = array(
						'id'          => $post_id,
						'title'       => get_the_title( $post_id ),
						'post_type'   => get_post_type( $post_id ),
						'slug'        => get_post_field( 'post_name', $post_id ),
						'edit_url'    => get_edit_post_link( $post_id, 'raw' ) ?: '',
						'view_url'    => get_permalink( $post_id ) ?: '',
						'status'      => $status['label'],
						'issues'      => $status['issue_summary'],
						'issue_count' => $status['issue_count'],
					);
				}

				$count = count( $query->posts );
				++$paged;
			} while ( $count >= 100 );
		}

		$total_found = count( $matching );
		$preview     = array_slice( $matching, 0, self::PREVIEW_LIMIT );

		return array(
			'items'         => $preview,
			'total_found'   => $total_found,
			'preview_limit' => self::PREVIEW_LIMIT,
			'preview_count' => count( $preview ),
			'all_ids'       => array_column( $matching, 'id' ),
		);
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private static function get_analysis_status( $post_id ) {
		$comprehensive = get_post_meta( $post_id, '_sb_comprehensive_analysis_result', true );
		$last_download = (int) get_post_meta( $post_id, '_sb_last_page_download', true );
		$saved         = SEO_Analysis::get_saved_analysis( $post_id, 'post' );

		$never = empty( $comprehensive ) && empty( $saved );
		$stale = ! $never && $last_download > 0 && $last_download < ( time() - ( self::STALE_DAYS * DAY_IN_SECONDS ) );

		$issues = array();
		if ( $saved && ! empty( $saved['issues'] ) && is_array( $saved['issues'] ) ) {
			foreach ( $saved['issues'] as $issue ) {
				if ( ! empty( $issue['message'] ) ) {
					$issues[] = $issue['message'];
				}
			}
		}

		$label = __( 'Analyzed', 'seo-booster' );
		if ( $never ) {
			$label = __( 'Never analyzed', 'seo-booster' );
		} elseif ( $stale ) {
			$label = __( 'Stale analysis', 'seo-booster' );
		} elseif ( ! empty( $issues ) ) {
			$label = __( 'Has open issues', 'seo-booster' );
		}

		return array(
			'never'         => $never,
			'stale'         => $stale,
			'issue_count'   => count( $issues ),
			'issue_summary' => implode( '; ', array_slice( $issues, 0, 3 ) ),
			'label'         => $label,
		);
	}

	/**
	 * @param array  $status Analysis status.
	 * @param string $filter Filter key.
	 * @return bool
	 */
	private static function matches_filter( array $status, $filter ) {
		switch ( $filter ) {
			case 'never_analyzed':
				return ! empty( $status['never'] );
			case 'stale':
				return ! empty( $status['stale'] );
			case 'has_issues':
				return ( $status['issue_count'] ?? 0 ) > 0;
			default:
				return ! empty( $status['never'] ) || ! empty( $status['stale'] );
		}
	}
}
