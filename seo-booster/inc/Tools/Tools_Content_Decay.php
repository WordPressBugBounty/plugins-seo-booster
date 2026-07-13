<?php
/**
 * Content decay scanner (Premium).
 *
 * @fs_premium_only
 *
 * @package Cleverplugins\SEOBooster\Tools
 */

namespace Cleverplugins\SEOBooster\Tools;

use Cleverplugins\SEOBooster\SEO_Issues_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists pages with declining GSC clicks (recent 30d vs prior 30d).
 */
class Tools_Content_Decay {

	/**
	 * Initialize AJAX hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_sb_tools_scan_content_decay', array( __CLASS__, 'ajax_scan' ) );
		add_action( 'wp_ajax_sb_tools_queue_content_decay', array( __CLASS__, 'ajax_queue_analysis' ) );
	}

	/**
	 * Render admin tab.
	 *
	 * @return void
	 */
	public static function render_admin() {
		$has_gsc = Tools_GSC_Helper::has_gsc_data();
		include SEOBOOSTER_PLUGINPATH . 'inc/Tools/views/content-decay-tool.php';
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Admin hook.
	 * @return void
	 */
	public static function enqueue_scripts( $hook ) {
		wp_enqueue_script(
			'sb-tools-content-decay',
			SEOBOOSTER_PLUGINURL . 'js/sb-tools-content-decay.js',
			array( 'jquery', 'sb-tools-ui' ),
			filemtime( SEOBOOSTER_PLUGINPATH . 'js/sb-tools-content-decay.js' ),
			true
		);

		wp_localize_script(
			'sb-tools-content-decay',
			'sbToolsContentDecay',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'sb_tools_content_decay_nonce' ),
				'has_gsc'  => Tools_GSC_Helper::has_gsc_data(),
				'strings'  => array(
					'scanning'           => __( 'Scanning…', 'seo-booster' ),
					'no_results'         => __( 'No declining pages found.', 'seo-booster' ),
					'need_gsc'           => __( 'Connect Google Search Console and import keyword data first.', 'seo-booster' ),
					'need_history'       => __( 'Need at least about 60 days of Search Console history to compare periods. Import more GSC data and try again.', 'seo-booster' ),
					'matching'           => __( '%d declining pages', 'seo-booster' ),
					'queue_confirm'      => __( 'Queue SEO analysis for all matching pages?', 'seo-booster' ),
					'queue_title'        => __( 'Queue SEO analysis', 'seo-booster' ),
					'queued'             => __( 'SEO analysis queued.', 'seo-booster' ),
					'queued_title'       => __( 'Analysis queued', 'seo-booster' ),
					'queue_status'       => __( '%d items queued for SEO analysis.', 'seo-booster' ),
					'view_possibilities' => __( 'View in SEO Possibilities', 'seo-booster' ),
					'error'              => __( 'Error', 'seo-booster' ),
				),
			)
		);
	}

	/**
	 * AJAX scan handler.
	 *
	 * @return void
	 */
	public static function ajax_scan() {
		check_ajax_referer( 'sb_tools_content_decay_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		if ( ! Tools_GSC_Helper::has_gsc_data() ) {
			wp_send_json_error( array( 'message' => __( 'Connect Google Search Console and import keyword data first.', 'seo-booster' ) ) );
		}

		$result = Tools_GSC_Helper::scan_content_decay();
		if ( empty( $result['has_history'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Need at least about 60 days of Search Console history to compare periods. Import more GSC data and try again.', 'seo-booster' ),
				)
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: queue bulk SEO analysis for matching posts (same transient pattern as Needs analysis).
	 *
	 * @return void
	 */
	public static function ajax_queue_analysis() {
		check_ajax_referer( 'sb_tools_content_decay_nonce', 'nonce' );

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
			'source'         => 'tools_content_decay',
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
}
