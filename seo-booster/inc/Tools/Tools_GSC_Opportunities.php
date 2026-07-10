<?php
/**
 * GSC opportunity scanner (Premium).
 *
 * @fs_premium_only
 *
 * @package Cleverplugins\SEOBooster\Tools
 */

namespace Cleverplugins\SEOBooster\Tools;

use Cleverplugins\SEOBooster\LLM_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans GSC tables for bulk-fix opportunities.
 */
class Tools_GSC_Opportunities {

	/**
	 * Initialize AJAX hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_sb_tools_scan_gsc_opportunities', array( __CLASS__, 'ajax_scan' ) );
	}

	/**
	 * Render admin tab.
	 *
	 * @return void
	 */
	public static function render_admin() {
		$seo_target             = SEO_Meta_Writer::get_target();
		$seo_target_label       = SEO_Meta_Writer::get_target_label();
		$ai_available           = self::ai_is_available();
		$ai_unavailable_message = self::get_ai_unavailable_message();
		$ai_notice_type         = self::get_ai_notice_type();
		$has_gsc                = Tools_GSC_Helper::has_gsc_data();
		$has_revertable         = SEO_Meta_Writer::has_revertable_batch();
		$scan_filters           = self::get_user_scan_filters();
		$settings_url           = admin_url( 'admin.php?page=sb2_settings#ai-llm' );
		$connectors_url         = admin_url( 'options-connectors.php' );

		include SEOBOOSTER_PLUGINPATH . 'inc/Tools/views/gsc-opportunities-tool.php';
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Admin hook.
	 * @return void
	 */
	public static function enqueue_scripts( $hook ) {
		Tools_Page::enqueue_batch_preview_script();

		wp_enqueue_script(
			'sb-tools-gsc-opportunities',
			SEOBOOSTER_PLUGINURL . 'js/sb-tools-gsc-opportunities.js',
			array( 'jquery', 'sb-tools-batch-preview', 'sb-tools-ui' ),
			filemtime( SEOBOOSTER_PLUGINPATH . 'js/sb-tools-gsc-opportunities.js' ),
			true
		);

		wp_localize_script(
			'sb-tools-gsc-opportunities',
			'sbToolsGscOpportunities',
			array(
				'ajax_url'               => admin_url( 'admin-ajax.php' ),
				'nonce'                  => wp_create_nonce( 'sb_tools_gsc_opportunities_nonce' ),
				'seo_target'             => SEO_Meta_Writer::get_target(),
				'seo_target_label'       => SEO_Meta_Writer::get_target_label(),
				'ai_available'           => self::ai_is_available(),
				'ai_unavailable_message' => self::get_ai_unavailable_message(),
				'ai_notice_type'         => self::get_ai_notice_type(),
				'has_gsc'                => Tools_GSC_Helper::has_gsc_data(),
				'has_revertable'         => SEO_Meta_Writer::has_revertable_batch(),
				'connectors_url'         => admin_url( 'options-connectors.php' ),
				'settings_url'           => admin_url( 'admin.php?page=sb2_settings#ai-llm' ),
				'scan_filters'           => self::get_user_scan_filters(),
				'strings'                => Tools_Page::get_gsc_opportunities_js_strings(),
			)
		);
	}

	/**
	 * AJAX scan handler.
	 *
	 * @return void
	 */
	public static function ajax_scan() {
		check_ajax_referer( 'sb_tools_gsc_opportunities_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		if ( ! Tools_GSC_Helper::has_gsc_data() ) {
			wp_send_json_error( array( 'message' => __( 'Connect Google Search Console and import keyword data first.', 'seo-booster' ) ) );
		}

		$filters = isset( $_POST['filters'] ) && is_array( $_POST['filters'] )
			? array_values(
				array_intersect(
					array_map( 'sanitize_text_field', wp_unslash( $_POST['filters'] ) ),
					Tools_GSC_Helper::get_opportunity_filter_keys()
				)
			)
			: array();

		if ( empty( $filters ) ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one opportunity filter.', 'seo-booster' ) ) );
		}

		self::save_user_scan_filters( $filters );

		wp_send_json_success( Tools_GSC_Helper::scan_opportunities( $filters ) );
	}

	/**
	 * @return bool
	 */
	public static function ai_is_available() {
		return Tools_Meta_Scanner::ai_is_available();
	}

	/**
	 * @return string
	 */
	public static function get_ai_unavailable_message() {
		return Tools_Meta_Scanner::get_ai_unavailable_message();
	}

	/**
	 * @return string
	 */
	public static function get_ai_notice_type() {
		return Tools_Meta_Scanner::get_ai_notice_type();
	}

	/**
	 * @return string[]
	 */
	public static function get_user_scan_filters() {
		$saved = get_user_meta( get_current_user_id(), 'sb_tools_gsc_opportunities_filters', true );
		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return array( 'low_ctr', 'high_impressions_low_clicks' );
		}

		return array_values( array_intersect( $saved, Tools_GSC_Helper::get_opportunity_filter_keys() ) );
	}

	/**
	 * @param string[] $filters Filters.
	 * @return void
	 */
	public static function save_user_scan_filters( array $filters ) {
		update_user_meta(
			get_current_user_id(),
			'sb_tools_gsc_opportunities_filters',
			array_values( array_intersect( $filters, Tools_GSC_Helper::get_opportunity_filter_keys() ) )
		);
	}
}
