<?php
/**
 * Focus keyword bulk setter (Premium).
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
 * Focus keyword suggestions from imported GSC data (no AI).
 */
class Tools_Focus_Keyword {

	const PREVIEW_LIMIT = 50;

	/**
	 * Initialize AJAX hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_sb_tools_scan_focus_keyword', array( __CLASS__, 'ajax_scan' ) );
	}

	/**
	 * Render admin tab.
	 *
	 * @return void
	 */
	public static function render_admin() {
		$seo_target          = SEO_Meta_Writer::get_target();
		$seo_target_label    = SEO_Meta_Writer::get_target_label();
		$seo_focus_supported = SEO_Plugin_Registry::supports_focus_keyword();
		$has_gsc             = Tools_GSC_Helper::has_gsc_data();
		$has_revertable      = SEO_Meta_Writer::has_revertable_focus_keyword_batch();
		$post_types          = self::get_user_post_types();
		$selectable          = Tools_Meta_Scanner::get_selectable_post_types();

		include SEOBOOSTER_PLUGINPATH . 'inc/Tools/views/focus-keyword-tool.php';
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
			'sb-tools-focus-keyword',
			SEOBOOSTER_PLUGINURL . 'js/sb-tools-focus-keyword.js',
			array( 'jquery', 'sb-tools-batch-preview', 'sb-tools-ui' ),
			filemtime( SEOBOOSTER_PLUGINPATH . 'js/sb-tools-focus-keyword.js' ),
			true
		);

		wp_localize_script(
			'sb-tools-focus-keyword',
			'sbToolsFocusKeyword',
			array(
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'sb_tools_focus_keyword_nonce' ),
				'seo_target'       => SEO_Plugin_Registry::supports_focus_keyword() ? SEO_Meta_Writer::get_target() : null,
				'seo_target_label' => SEO_Meta_Writer::get_target_label(),
				'has_gsc'          => Tools_GSC_Helper::has_gsc_data(),
				'has_revertable'   => SEO_Meta_Writer::has_revertable_focus_keyword_batch(),
				'post_types'       => self::get_user_post_types(),
				'strings'          => Tools_Page::get_focus_keyword_js_strings(),
			)
		);
	}

	/**
	 * AJAX scan handler.
	 *
	 * @return void
	 */
	public static function ajax_scan() {
		check_ajax_referer( 'sb_tools_focus_keyword_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		if ( ! SEO_Plugin_Registry::supports_focus_keyword() ) {
			wp_send_json_error( array( 'message' => SEO_Plugin_Registry::get_focus_keyword_requirement_message() ) );
		}

		if ( ! Tools_GSC_Helper::has_gsc_data() ) {
			wp_send_json_error( array( 'message' => __( 'Connect Google Search Console and import keyword data first.', 'seo-booster' ) ) );
		}

		$post_types = Tools_Meta_Scanner::parse_post_types(
			isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
				? wp_unslash( $_POST['post_types'] )
				: self::get_user_post_types()
		);

		self::save_user_post_types( $post_types );

		wp_send_json_success( self::scan( $post_types ) );
	}

	/**
	 * Run focus keyword scan.
	 *
	 * @param string[] $post_types Post types.
	 * @return array
	 */
	public static function scan( array $post_types ) {
		$assigned_map = Tools_GSC_Helper::get_site_focus_keyword_map();
		$suggested    = array();
		$skipped      = array(
			'has_keyword' => 0,
			'utility'     => 0,
			'no_demand'   => 0,
			'no_unique'   => 0,
		);

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
						'update_post_meta_cache' => false,
					)
				);

				foreach ( $query->posts as $post_id ) {
					$post_id = (int) $post_id;
					if ( $post_id <= 0 ) {
						continue;
					}

					$current = SEO_Meta_Writer::read_focus_keyword( $post_id );
					if ( trim( $current ) !== '' ) {
						++$skipped['has_keyword'];
						continue;
					}

					if ( Tools_GSC_Helper::should_skip_focus_keyword_for_post( $post_id ) ) {
						++$skipped['utility'];
						continue;
					}

					$suggestion = Tools_GSC_Helper::suggest_focus_keywords_for_post( $post_id, $assigned_map );
					if ( empty( $suggestion ) ) {
						$keywords = Tools_GSC_Helper::get_keywords_for_page( get_permalink( $post_id ) ?: '' );
						if ( ! empty( $keywords ) ) {
							++$skipped['no_unique'];
						} else {
							++$skipped['no_demand'];
						}
						continue;
					}

					$primary                     = $suggestion[0];
					$normalized                  = Tools_GSC_Helper::normalize_keyword( $primary['keyword'] );
					$assigned_map[ $normalized ] = $post_id;

					$suggested[] = array(
						'id'                => $post_id,
						'post_id'           => $post_id,
						'title'             => get_the_title( $post_id ),
						'post_type'         => get_post_type( $post_id ),
						'slug'              => get_post_field( 'post_name', $post_id ),
						'edit_url'          => get_edit_post_link( $post_id, 'raw' ) ?: '',
						'view_url'          => get_permalink( $post_id ) ?: '',
						'suggested_keyword' => $primary['keyword'],
						'alternatives'      => $suggestion,
						'impressions'       => (int) $primary['impressions'],
						'position'          => round( (float) $primary['position'], 1 ),
						'status'            => 'suggested',
					);
				}

				$count = count( $query->posts );
				++$paged;
			} while ( $count >= 100 );
		}

		usort(
			$suggested,
			function ( $a, $b ) {
				return ( $b['impressions'] <=> $a['impressions'] );
			}
		);

		$total_found = count( $suggested );
		$preview     = array_slice( $suggested, 0, self::PREVIEW_LIMIT );

		return array(
			'items'         => $preview,
			'total_found'   => $total_found,
			'preview_limit' => self::PREVIEW_LIMIT,
			'preview_count' => count( $preview ),
			'all_ids'       => array_column( $suggested, 'post_id' ),
			'all_items'     => $suggested,
			'skipped'       => $skipped,
		);
	}

	/**
	 * @return string[]
	 */
	public static function get_user_post_types() {
		$saved = get_user_meta( get_current_user_id(), 'sb_tools_focus_keyword_post_types', true );
		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return Tools_Meta_Scanner::get_default_post_types();
		}

		return Tools_Meta_Scanner::parse_post_types( $saved );
	}

	/**
	 * @param string[] $post_types Post types.
	 * @return void
	 */
	public static function save_user_post_types( array $post_types ) {
		update_user_meta( get_current_user_id(), 'sb_tools_focus_keyword_post_types', $post_types );
	}
}
