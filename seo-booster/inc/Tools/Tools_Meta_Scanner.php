<?php

namespace Cleverplugins\SEOBooster\Tools;

use Cleverplugins\SEOBooster\Google_API;
use Cleverplugins\SEOBooster\LLM_Helper;
use Cleverplugins\SEOBooster\SEO_Issues_Manager;
use Cleverplugins\SEOBooster\SEO_Plugin_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans posts/pages for SEO title and description issues.
 *
 * @package Cleverplugins\SEOBooster\Tools
 */
class Tools_Meta_Scanner {

	const PREVIEW_LIMIT   = 50;
	const SCAN_BATCH_SIZE = 100;

	/**
	 * @return string[]
	 */
	public static function get_filter_keys() {
		return array(
			'missing_title',
			'missing_description',
			'duplicate_title',
			'duplicate_description',
			'missing_keyword',
		);
	}

	/**
	 * Default public post types for scanning.
	 *
	 * @return string[]
	 */
	public static function get_default_post_types() {
		return array( 'post', 'page' );
	}

	/**
	 * Selectable public post types (excludes attachment).
	 *
	 * @return string[]
	 */
	public static function get_selectable_post_types() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );
		return array_values( $types );
	}

	/**
	 * Initialize AJAX hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_sb_tools_scan_meta', array( __CLASS__, 'ajax_scan_meta' ) );
	}

	/**
	 * AJAX scan handler.
	 *
	 * @return void
	 */
	public static function ajax_scan_meta() {
		check_ajax_referer( 'sb_tools_meta_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		if ( ! SEO_Meta_Writer::get_target() ) {
			wp_send_json_error(
				array(
					'message' => SEO_Plugin_Registry::get_bulk_write_requirement_message(),
				)
			);
		}

		$filters = isset( $_POST['filters'] ) && is_array( $_POST['filters'] )
			? array_values(
				array_intersect(
					array_map( 'sanitize_text_field', wp_unslash( $_POST['filters'] ) ),
					self::get_filter_keys()
				)
			)
			: array();

		if ( empty( $filters ) ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one scan filter.', 'seo-booster' ) ) );
		}

		$post_types = self::parse_post_types(
			isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
				? wp_unslash( $_POST['post_types'] )
				: self::get_default_post_types()
		);

		if ( empty( $post_types ) ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one content type.', 'seo-booster' ) ) );
		}

		self::save_user_scan_filters( $filters );
		self::save_user_post_types( $post_types );

		wp_send_json_success( self::scan( $filters, $post_types ) );
	}

	/**
	 * @param mixed $raw Raw post types.
	 * @return string[]
	 */
	public static function parse_post_types( $raw ) {
		if ( ! is_array( $raw ) ) {
			return self::get_default_post_types();
		}

		$allowed = self::get_selectable_post_types();
		$parsed  = array_values( array_intersect( array_map( 'sanitize_key', $raw ), $allowed ) );

		return ! empty( $parsed ) ? $parsed : self::get_default_post_types();
	}

	/**
	 * Run scan.
	 *
	 * @param string[] $filters    Filter keys.
	 * @param string[] $post_types Post types.
	 * @return array
	 */
	public static function scan( array $filters, array $post_types ) {
		$all_posts     = self::collect_posts( $post_types );
		$duplicate_map = self::build_duplicate_map( $all_posts );
		$matching      = array();

		foreach ( $all_posts as $row ) {
			$issues = self::classify_issues( $row, $filters, $duplicate_map );
			if ( ! empty( $issues ) ) {
				$matching[] = array_merge( $row, array( 'issues' => $issues ) );
			}
		}

		$total_found = count( $matching );
		$preview     = array_slice( $matching, 0, self::PREVIEW_LIMIT );
		$items       = array();

		foreach ( $preview as $row ) {
			$post_id = (int) $row['id'];
			$items[] = array(
				'id'        => $post_id,
				'title'     => $row['post_title'],
				'post_type' => $row['post_type'],
				'slug'      => get_post_field( 'post_name', $post_id ),
				'edit_url'  => get_edit_post_link( $post_id, 'raw' ) ?: '',
				'view_url'  => get_permalink( $post_id ) ?: '',
				'issues'    => $row['issues'],
				'meta'      => array(
					'seo_title'       => $row['seo_title'],
					'seo_description' => $row['seo_description'],
				),
			);
		}

		return array(
			'items'         => $items,
			'total_found'   => $total_found,
			'preview_limit' => self::PREVIEW_LIMIT,
			'preview_count' => count( $items ),
			'seo_plugin'    => SEO_Meta_Writer::get_target_label(),
		);
	}

	/**
	 * Collect post rows with SEO meta for duplicate detection.
	 *
	 * @param string[] $post_types Post types.
	 * @return array<int, array>
	 */
	private static function collect_posts( array $post_types ) {
		$rows = array();

		foreach ( $post_types as $post_type ) {
			$paged = 1;
			do {
				$query = new \WP_Query(
					array(
						'post_type'              => $post_type,
						'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
						'posts_per_page'         => self::SCAN_BATCH_SIZE,
						'paged'                  => $paged,
						'orderby'                => 'modified',
						'order'                  => 'DESC',
						'fields'                 => 'ids',
						'no_found_rows'          => true,
						'update_post_meta_cache' => true,
						'update_post_term_cache' => false,
					)
				);

				foreach ( $query->posts as $post_id ) {
					$post_id = (int) $post_id;
					if ( $post_id <= 0 || SEO_Issues_Manager::should_exclude_from_analysis( $post_id ) ) {
						continue;
					}

					$post = get_post( $post_id );
					if ( ! $post ) {
						continue;
					}

					$meta = SEO_Meta_Writer::read( $post_id );

					$rows[] = array(
						'id'              => $post_id,
						'post_title'      => $post->post_title,
						'post_type'       => $post->post_type,
						'seo_title'       => $meta['title'],
						'seo_description' => $meta['description'],
						'keywords'        => self::get_target_keywords( $post_id ),
					);
				}

				$count = count( $query->posts );
				++$paged;
			} while ( $count >= self::SCAN_BATCH_SIZE );
		}

		return $rows;
	}

	/**
	 * @param array[] $rows Post rows.
	 * @return array{titles: array, descriptions: array}
	 */
	private static function build_duplicate_map( array $rows ) {
		$titles       = array();
		$descriptions = array();

		foreach ( $rows as $row ) {
			$title_key = self::normalize_for_duplicate( $row['seo_title'] );
			if ( $title_key !== '' ) {
				if ( ! isset( $titles[ $title_key ] ) ) {
					$titles[ $title_key ] = 0;
				}
				++$titles[ $title_key ];
			}

			$desc_key = self::normalize_for_duplicate( $row['seo_description'] );
			if ( $desc_key !== '' ) {
				if ( ! isset( $descriptions[ $desc_key ] ) ) {
					$descriptions[ $desc_key ] = 0;
				}
				++$descriptions[ $desc_key ];
			}
		}

		return array(
			'titles'       => $titles,
			'descriptions' => $descriptions,
		);
	}

	/**
	 * @param string $value Value.
	 * @return string
	 */
	private static function normalize_for_duplicate( $value ) {
		return strtolower( trim( (string) $value ) );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function get_target_keywords( $post_id ) {
		$keywords = Google_API::get_focus_keywords( $post_id );
		if ( ! is_array( $keywords ) ) {
			$keywords = array();
		}

		$gsc_data = get_post_meta( $post_id, '_sb_seo_keyword_data', true );
		if ( ! empty( $gsc_data ) && is_array( $gsc_data ) ) {
			$gsc_queries = array_slice( array_column( $gsc_data, 'query' ), 0, 5 );
			$keywords    = array_merge( $keywords, $gsc_queries );
		}

		$keywords = array_filter( array_map( 'trim', $keywords ) );
		return array_values( array_unique( $keywords ) );
	}

	/**
	 * @param array $row           Post row.
	 * @param array $filters       Active filters.
	 * @param array $duplicate_map Duplicate counts.
	 * @return string[]
	 */
	public static function classify_issues( array $row, array $filters, array $duplicate_map ) {
		$issues = array();

		if ( in_array( 'missing_title', $filters, true ) && trim( (string) $row['seo_title'] ) === '' ) {
			$issues[] = 'missing_title';
		}
		if ( in_array( 'missing_description', $filters, true ) && trim( (string) $row['seo_description'] ) === '' ) {
			$issues[] = 'missing_description';
		}

		$title_key = self::normalize_for_duplicate( $row['seo_title'] );
		if ( in_array( 'duplicate_title', $filters, true ) && $title_key !== ''
			&& isset( $duplicate_map['titles'][ $title_key ] ) && $duplicate_map['titles'][ $title_key ] > 1 ) {
			$issues[] = 'duplicate_title';
		}

		$desc_key = self::normalize_for_duplicate( $row['seo_description'] );
		if ( in_array( 'duplicate_description', $filters, true ) && $desc_key !== ''
			&& isset( $duplicate_map['descriptions'][ $desc_key ] ) && $duplicate_map['descriptions'][ $desc_key ] > 1 ) {
			$issues[] = 'duplicate_description';
		}

		if ( in_array( 'missing_keyword', $filters, true ) ) {
			$keywords = isset( $row['keywords'] ) ? $row['keywords'] : self::get_target_keywords( $row['id'] );
			if ( ! empty( $keywords ) && ! self::meta_contains_keyword( $row, $keywords ) ) {
				$issues[] = 'missing_keyword';
			}
		}

		return $issues;
	}

	/**
	 * @param array    $row      Post row.
	 * @param string[] $keywords Keywords.
	 * @return bool
	 */
	private static function meta_contains_keyword( array $row, array $keywords ) {
		$haystack = strtolower( trim( $row['seo_title'] . ' ' . $row['seo_description'] ) );

		foreach ( $keywords as $keyword ) {
			$keyword = strtolower( trim( (string) $keyword ) );
			if ( $keyword !== '' && strpos( $haystack, $keyword ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Matching post IDs for batch (newest first).
	 *
	 * @param string[] $filters    Filters.
	 * @param string[] $post_types Post types.
	 * @return int[]
	 */
	public static function get_matching_post_ids( array $filters, array $post_types ) {
		$all_posts     = self::collect_posts( $post_types );
		$duplicate_map = self::build_duplicate_map( $all_posts );
		$matching      = array();

		foreach ( $all_posts as $row ) {
			if ( ! empty( self::classify_issues( $row, $filters, $duplicate_map ) ) ) {
				$matching[] = (int) $row['id'];
			}
		}

		return $matching;
	}

	/**
	 * Re-check selected IDs against filters.
	 *
	 * @param int[]    $post_ids Post IDs.
	 * @param string[] $filters  Filters.
	 * @param string[] $post_types Post types (for duplicate map scope).
	 * @return int[]
	 */
	public static function filter_still_matching_ids( array $post_ids, array $filters, array $post_types ) {
		if ( empty( $filters ) ) {
			return array_values( array_map( 'intval', $post_ids ) );
		}

		$all_posts     = self::collect_posts( $post_types );
		$duplicate_map = self::build_duplicate_map( $all_posts );
		$by_id         = array();
		foreach ( $all_posts as $row ) {
			$by_id[ (int) $row['id'] ] = $row;
		}

		$matching = array();
		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 || ! isset( $by_id[ $post_id ] ) ) {
				continue;
			}
			if ( ! empty( self::classify_issues( $by_id[ $post_id ], $filters, $duplicate_map ) ) ) {
				$matching[] = $post_id;
			}
		}

		return $matching;
	}

	/**
	 * Whether text AI is available (WordPress Connectors only).
	 *
	 * @return bool
	 */
	public static function ai_is_available() {
		$ai_provider = LLM_Helper::get_selected_ai_provider();
		if ( $ai_provider !== 'WordPress' ) {
			return false;
		}

		return LLM_Helper::wp_ai_is_available();
	}

	/**
	 * @return string
	 */
	public static function get_ai_unavailable_message() {
		$ai_provider = LLM_Helper::get_selected_ai_provider();

		if ( $ai_provider === 'seobooster' ) {
			return __( 'SEO Booster Credits are not available yet. Use WordPress Connectors for bulk meta generation.', 'seo-booster' );
		}

		if ( $ai_provider === 'WordPress' ) {
			return LLM_Helper::wp_ai_unavailable_message();
		}

		return __( 'Enable AI in SEO Booster Settings (WordPress Connectors) to generate meta titles and descriptions.', 'seo-booster' )
			. LLM_Helper::ai_credits_option_hint();
	}

	/**
	 * @return string
	 */
	public static function get_ai_notice_type() {
		$ai_provider = LLM_Helper::get_selected_ai_provider();
		if ( $ai_provider === 'WordPress' ) {
			return LLM_Helper::wp_ai_is_available() ? 'ok' : 'connector';
		}
		if ( $ai_provider === 'seobooster' ) {
			return 'credits';
		}
		return 'disabled';
	}

	/**
	 * @param string $issue Issue key.
	 * @return string
	 */
	public static function issue_label( $issue ) {
		$labels = array(
			'missing_title'         => __( 'Missing SEO title', 'seo-booster' ),
			'missing_description'   => __( 'Missing meta description', 'seo-booster' ),
			'duplicate_title'       => __( 'Duplicate SEO title', 'seo-booster' ),
			'duplicate_description' => __( 'Duplicate meta description', 'seo-booster' ),
			'missing_keyword'       => __( 'Missing focus/GSC keyword in meta', 'seo-booster' ),
		);

		return $labels[ $issue ] ?? $issue;
	}

	/**
	 * @return array
	 */
	public static function default_apply_fields() {
		return array(
			'title'       => true,
			'description' => true,
		);
	}

	/**
	 * @param mixed $raw Raw input.
	 * @return array
	 */
	public static function parse_apply_fields( $raw ) {
		$defaults = self::default_apply_fields();
		if ( ! is_array( $raw ) ) {
			return $defaults;
		}
		foreach ( array_keys( $defaults ) as $key ) {
			$defaults[ $key ] = Tools_Image_Batch::is_apply_field_enabled( isset( $raw[ $key ] ) ? $raw[ $key ] : false );
		}
		return $defaults;
	}

	/**
	 * @param array $apply_fields Apply fields.
	 * @return void
	 */
	public static function save_user_apply_fields( array $apply_fields ) {
		update_user_meta( get_current_user_id(), 'sb_tools_meta_apply_fields', $apply_fields );
	}

	/**
	 * @return array
	 */
	public static function get_user_apply_fields() {
		$saved = get_user_meta( get_current_user_id(), 'sb_tools_meta_apply_fields', true );
		if ( is_array( $saved ) ) {
			return self::parse_apply_fields( $saved );
		}
		return self::default_apply_fields();
	}

	/**
	 * @param string[] $filters Filters.
	 * @return void
	 */
	public static function save_user_scan_filters( array $filters ) {
		update_user_meta( get_current_user_id(), 'sb_tools_meta_scan_filters', $filters );
	}

	/**
	 * @return string[]
	 */
	public static function get_user_scan_filters() {
		$saved = get_user_meta( get_current_user_id(), 'sb_tools_meta_scan_filters', true );
		if ( is_array( $saved ) ) {
			return array_values( array_intersect( $saved, self::get_filter_keys() ) );
		}
		return array( 'missing_title', 'missing_description' );
	}

	/**
	 * @param string[] $post_types Post types.
	 * @return void
	 */
	public static function save_user_post_types( array $post_types ) {
		update_user_meta( get_current_user_id(), 'sb_tools_meta_post_types', $post_types );
	}

	/**
	 * @return string[]
	 */
	public static function get_user_post_types() {
		$saved = get_user_meta( get_current_user_id(), 'sb_tools_meta_post_types', true );
		if ( is_array( $saved ) ) {
			return self::parse_post_types( $saved );
		}
		return self::get_default_post_types();
	}

	/**
	 * Count published posts/pages missing SEO title or description (setup wizard).
	 *
	 * Uses the same read path as scan(); capped for responsiveness.
	 *
	 * @param int $cap Max posts to inspect.
	 * @return int
	 */
	public static function count_missing_title_or_description( $cap = 500 ) {
		$cap   = max( 50, min( 2000, (int) $cap ) );
		$count = 0;

		$query = new \WP_Query(
			array(
				'post_type'              => self::get_default_post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => $cap,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $query->posts as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 || SEO_Issues_Manager::should_exclude_from_analysis( $post_id ) ) {
				continue;
			}
			$meta = SEO_Meta_Writer::read( $post_id );
			if ( trim( (string) ( $meta['title'] ?? '' ) ) === '' || trim( (string) ( $meta['description'] ?? '' ) ) === '' ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Count published posts/pages missing a focus keyword (setup wizard).
	 *
	 * @param int $cap Max posts to inspect.
	 * @return int
	 */
	public static function count_missing_focus_keyword( $cap = 500 ) {
		if ( ! SEO_Plugin_Registry::supports_focus_keyword() ) {
			return 0;
		}

		$cap   = max( 50, min( 2000, (int) $cap ) );
		$count = 0;

		$query = new \WP_Query(
			array(
				'post_type'              => self::get_default_post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => $cap,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $query->posts as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 || SEO_Issues_Manager::should_exclude_from_analysis( $post_id ) ) {
				continue;
			}
			if ( trim( SEO_Meta_Writer::read_focus_keyword( $post_id ) ) === '' ) {
				++$count;
			}
		}

		return $count;
	}
}
