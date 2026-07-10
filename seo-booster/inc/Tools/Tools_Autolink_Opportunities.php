<?php
/**
 * Autolink opportunity finder (Premium).
 *
 * @fs_premium_only
 *
 * @package Cleverplugins\SEOBooster\Tools
 */

namespace Cleverplugins\SEOBooster\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Surfaces GSC queries without autolink rules and high-traffic pages with autolink off.
 */
class Tools_Autolink_Opportunities {

	/**
	 * Initialize AJAX hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_sb_tools_scan_autolink_opportunities', array( __CLASS__, 'ajax_scan' ) );
	}

	/**
	 * Render admin tab.
	 *
	 * @return void
	 */
	public static function render_admin() {
		$has_gsc               = Tools_GSC_Helper::has_gsc_data();
		$has_revertable        = self::has_revertable_batch();
		$autolink_on           = self::is_internal_linking_enabled();
		$post_types            = Tools_Meta_Scanner::get_selectable_post_types();
		$scan_filter           = self::get_user_scan_filter();
		$settings_autolink_url = admin_url( 'admin.php?page=sb2_settings#automatic-links' );

		include SEOBOOSTER_PLUGINPATH . 'inc/Tools/views/autolink-opportunities-tool.php';
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
			'sb-tools-autolink-opportunities',
			SEOBOOSTER_PLUGINURL . 'js/sb-tools-autolink-opportunities.js',
			array( 'jquery', 'sb-tools-batch-preview', 'sb-tools-ui' ),
			filemtime( SEOBOOSTER_PLUGINPATH . 'js/sb-tools-autolink-opportunities.js' ),
			true
		);

		wp_localize_script(
			'sb-tools-autolink-opportunities',
			'sbToolsAutolinkOpportunities',
			array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'sb_tools_autolink_opportunities_nonce' ),
				'has_gsc'        => Tools_GSC_Helper::has_gsc_data(),
				'has_revertable' => self::has_revertable_batch(),
				'autolink_on'    => self::is_internal_linking_enabled(),
				'scan_filter'    => self::get_user_scan_filter(),
				'strings'        => Tools_Page::get_autolink_opportunities_js_strings(),
			)
		);
	}

	/**
	 * AJAX scan handler.
	 *
	 * @return void
	 */
	public static function ajax_scan() {
		check_ajax_referer( 'sb_tools_autolink_opportunities_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		if ( ! self::is_internal_linking_enabled() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Enable automatic internal linking in SEO Booster Settings first.', 'seo-booster' ),
				)
			);
		}

		$filter = isset( $_POST['filter'] ) ? sanitize_key( wp_unslash( $_POST['filter'] ) ) : 'missing_rules';
		if ( ! in_array( $filter, Tools_GSC_Helper::get_autolink_filter_keys(), true ) ) {
			$filter = 'missing_rules';
		}

		if ( $filter === 'missing_rules' && ! Tools_GSC_Helper::has_gsc_data() ) {
			wp_send_json_error( array( 'message' => __( 'Connect Google Search Console and import keyword data first.', 'seo-booster' ) ) );
		}

		$post_types = Tools_Meta_Scanner::parse_post_types(
			isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
				? wp_unslash( $_POST['post_types'] )
				: Tools_Meta_Scanner::get_default_post_types()
		);

		self::save_user_scan_filter( $filter );

		wp_send_json_success( Tools_GSC_Helper::scan_autolink_opportunities( $filter, $post_types ) );
	}

	/**
	 * @return string
	 */
	public static function get_user_scan_filter() {
		$saved = get_user_meta( get_current_user_id(), 'sb_tools_autolink_opportunities_filter', true );
		if ( ! is_string( $saved ) || ! in_array( $saved, Tools_GSC_Helper::get_autolink_filter_keys(), true ) ) {
			return 'missing_rules';
		}

		return $saved;
	}

	/**
	 * @param string $filter Filter key.
	 * @return void
	 */
	public static function save_user_scan_filter( $filter ) {
		if ( in_array( $filter, Tools_GSC_Helper::get_autolink_filter_keys(), true ) ) {
			update_user_meta( get_current_user_id(), 'sb_tools_autolink_opportunities_filter', $filter );
		}
	}

	/**
	 * Whether automatic internal linking is enabled in settings.
	 *
	 * @return bool
	 */
	public static function is_internal_linking_enabled() {
		return get_option( 'seobooster_internal_linking', '' ) === 'on';
	}

	/**
	 * @return bool
	 */
	public static function has_revertable_batch() {
		$ids = get_user_meta( get_current_user_id(), 'sb_tools_autolink_last_batch_ids', true );
		return is_array( $ids ) && ! empty( $ids );
	}
}
