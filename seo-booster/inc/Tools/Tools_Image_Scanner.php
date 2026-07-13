<?php

namespace Cleverplugins\SEOBooster\Tools;

use Cleverplugins\SEOBooster\Media\AI_Image_Generator;
use Cleverplugins\SEOBooster\Credits_Service;
use Cleverplugins\SEOBooster\LLM_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans Media Library image attachments for missing metadata.
 *
 * @package Cleverplugins\SEOBooster\Tools
 */
class Tools_Image_Scanner {

	/**
	 * Maximum rows shown in the scan results preview table.
	 */
	const PREVIEW_LIMIT = 50;

	/**
	 * Batch size when walking all image attachments for a full-library match count.
	 */
	const SCAN_BATCH_SIZE = 100;

	/**
	 * Valid scan filter keys.
	 *
	 * @return string[]
	 */
	public static function get_filter_keys() {
		return array( 'empty_alt', 'empty_title', 'empty_caption', 'empty_description' );
	}

	/**
	 * MIME types supported by AI vision (JPEG, PNG, WebP).
	 *
	 * @return string[]
	 */
	public static function get_processable_mime_types() {
		$types = array( 'image/jpeg', 'image/png', 'image/webp' );

		/**
		 * Filter processable attachment MIME types for the Tools image scanner.
		 *
		 * @since 7.0.4
		 * @param string[] $types MIME type strings.
		 */
		return apply_filters( 'sb_tools_processable_mime_types', $types );
	}

	/**
	 * Whether an attachment can be processed by AI vision in Tools.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function is_processable_attachment( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}

		$mime = get_post_mime_type( $attachment_id );

		return in_array( $mime, self::get_processable_mime_types(), true );
	}

	/**
	 * Human-readable list of supported formats for error messages.
	 *
	 * @return string
	 */
	public static function get_processable_formats_label() {
		return __( 'JPEG, PNG, WebP', 'seo-booster' );
	}

	/**
	 * Initialize AJAX hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_sb_tools_scan_images', array( __CLASS__, 'ajax_scan_images' ) );
	}

	/**
	 * AJAX: scan attachments matching filters.
	 *
	 * @return void
	 */
	public static function ajax_scan_images() {
		check_ajax_referer( 'sb_tools_image_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$filters = isset( $_POST['filters'] ) && is_array( $_POST['filters'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['filters'] ) )
			: array();

		$filters = array_values( array_intersect( $filters, self::get_filter_keys() ) );

		if ( empty( $filters ) ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one scan filter.', 'seo-booster' ) ) );
		}

		Tools_Image_Batch::save_user_scan_filters( $filters );

		wp_send_json_success( self::scan( $filters ) );
	}

	/**
	 * Run scan: find all matching attachments; return total count and preview rows.
	 *
	 * @param string[] $filters Active filter keys.
	 * @return array
	 */
	public static function scan( array $filters ) {
		$metadata_matches = self::get_matching_attachment_ids( $filters );
		$filtered         = self::filter_readable_attachment_ids( $metadata_matches );
		$matching_ids     = $filtered['ids'];
		$missing_count    = $filtered['missing_count'];
		$preview_ids      = array_slice( $matching_ids, 0, self::PREVIEW_LIMIT );

		$items = array();
		foreach ( $preview_ids as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			if ( ! self::is_processable_attachment( $attachment_id ) ) {
				continue;
			}

			$meta   = self::get_attachment_meta( $attachment_id );
			$issues = self::classify_issues( $meta, $filters );

			if ( empty( $issues ) ) {
				continue;
			}

			$thumb   = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
			$items[] = array(
				'id'        => $attachment_id,
				'title'     => $meta['title'],
				'filename'  => wp_basename( get_attached_file( $attachment_id ) ?: '' ),
				'thumb_url' => $thumb ? $thumb[0] : '',
				'issues'    => $issues,
				'edit_url'  => get_edit_post_link( $attachment_id, 'raw' ),
				'view_url'  => wp_get_attachment_url( $attachment_id ) ?: '',
				'meta'      => array(
					'alt_text'    => $meta['alt_text'],
					'caption'     => $meta['caption'],
					'description' => $meta['description'],
				),
			);
		}

		return array(
			'items'              => $items,
			'total_found'        => count( $matching_ids ),
			'missing_file_count' => $missing_count,
			'preview_limit'      => self::PREVIEW_LIMIT,
			'preview_count'      => count( $items ),
		);
	}

	/**
	 * Keep only attachments with a readable image file on disk.
	 *
	 * @since 7.2.1
	 * @param int[] $attachment_ids Candidate attachment IDs.
	 * @return array{ids: int[], missing_count: int}
	 */
	public static function filter_readable_attachment_ids( array $attachment_ids ) {
		$readable      = array();
		$missing_count = 0;

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			if ( $attachment_id <= 0 ) {
				continue;
			}

			if ( AI_Image_Generator::attachment_has_readable_file( $attachment_id ) ) {
				$readable[] = $attachment_id;
			} else {
				++$missing_count;
			}
		}

		return array(
			'ids'           => $readable,
			'missing_count' => $missing_count,
		);
	}

	/**
	 * Attachment IDs matching filters (newest first).
	 *
	 * @param string[] $filters Active filter keys.
	 * @return int[]
	 */
	public static function get_matching_attachment_ids( array $filters ) {
		$matching = array();
		$paged    = 1;

		do {
			$query = new \WP_Query(
				array(
					'post_type'              => 'attachment',
					'post_status'            => 'inherit',
					'post_mime_type'         => self::get_processable_mime_types(),
					'posts_per_page'         => self::SCAN_BATCH_SIZE,
					'paged'                  => $paged,
					'orderby'                => 'date',
					'order'                  => 'DESC',
					'fields'                 => 'ids',
					'update_post_meta_cache' => true,
					'update_post_term_cache' => false,
				)
			);

			foreach ( $query->posts as $attachment_id ) {
				$attachment_id = (int) $attachment_id;
				if ( ! self::is_processable_attachment( $attachment_id ) ) {
					continue;
				}

				$meta = self::get_attachment_meta( $attachment_id );
				if ( ! empty( self::classify_issues( $meta, $filters ) ) ) {
					$matching[] = $attachment_id;
				}
			}

			$posts_count = count( $query->posts );
			++$paged;
		} while ( $posts_count >= self::SCAN_BATCH_SIZE );

		return $matching;
	}

	/**
	 * Read attachment metadata fields.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	public static function get_attachment_meta( $attachment_id ) {
		$attachment = get_post( $attachment_id );

		return array(
			'title'       => $attachment ? $attachment->post_title : '',
			'alt_text'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'caption'     => $attachment ? $attachment->post_excerpt : '',
			'description' => $attachment ? $attachment->post_content : '',
		);
	}

	/**
	 * Return issue labels matching selected filters.
	 *
	 * @param array $meta    Attachment meta.
	 * @param array $filters Active filters.
	 * @return string[]
	 */
	public static function classify_issues( array $meta, array $filters ) {
		$issues = array();

		if ( in_array( 'empty_alt', $filters, true ) && '' === trim( $meta['alt_text'] ) ) {
			$issues[] = 'empty_alt';
		}
		if ( in_array( 'empty_title', $filters, true ) && '' === trim( $meta['title'] ) ) {
			$issues[] = 'empty_title';
		}
		if ( in_array( 'empty_caption', $filters, true ) && '' === trim( $meta['caption'] ) ) {
			$issues[] = 'empty_caption';
		}
		if ( in_array( 'empty_description', $filters, true ) && '' === trim( $meta['description'] ) ) {
			$issues[] = 'empty_description';
		}

		return $issues;
	}

	/**
	 * Keep only attachment IDs that still match the active scan filters (live meta check).
	 *
	 * @since 7.0.5
	 * @param int[]    $attachment_ids Candidate IDs.
	 * @param string[] $filters        Active filter keys.
	 * @return int[]
	 */
	public static function filter_still_matching_ids( array $attachment_ids, array $filters ) {
		if ( empty( $filters ) ) {
			return array_values( array_map( 'intval', $attachment_ids ) );
		}

		$matching = array();
		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			if ( $attachment_id <= 0 || ! self::is_processable_attachment( $attachment_id ) ) {
				continue;
			}

			$meta = self::get_attachment_meta( $attachment_id );
			if ( ! empty( self::classify_issues( $meta, $filters ) )
				&& AI_Image_Generator::attachment_has_readable_file( $attachment_id ) ) {
				$matching[] = $attachment_id;
			}
		}

		return $matching;
	}

	/**
	 * Whether AI image generation is available.
	 *
	 * @return bool
	 */
	public static function ai_is_available() {
		$ai_provider = LLM_Helper::get_selected_ai_provider();

		if ( $ai_provider === 'WordPress' ) {
			return AI_Image_Generator::model_supports_vision();
		}

		if ( $ai_provider === 'seobooster' ) {
			return Credits_Service::is_credits_provider_usable() && AI_Image_Generator::model_supports_vision();
		}

		return false;
	}

	/**
	 * Explain why AI processing is unavailable (for admin notices and JS).
	 *
	 * @return string
	 */
	public static function get_ai_unavailable_message() {
		$ai_provider = LLM_Helper::get_selected_ai_provider();

		if ( $ai_provider === 'WordPress' ) {
			return LLM_Helper::wp_ai_image_metadata_unavailable_message();
		}

		if ( $ai_provider === 'seobooster' ) {
			if ( ! Credits_Service::is_credits_provider_usable() ) {
				return __( 'SEO Booster Credits are not available yet.', 'seo-booster' );
			}
			if ( ! AI_Image_Generator::model_supports_vision() ) {
				return __( 'SEO Booster Credits are not available for image analysis. Connect your credits account in SEO Booster Settings.', 'seo-booster' );
			}
			return '';
		}

		return __( 'Enable AI in SEO Booster Settings (WordPress with a vision connector) to process images.', 'seo-booster' )
			. LLM_Helper::ai_credits_option_hint();
	}

	/**
	 * Notice type slug for styling or JS (deepseek_only, text_only_only, etc.).
	 *
	 * @return string
	 */
	public static function get_ai_notice_type() {
		$ai_provider = LLM_Helper::get_selected_ai_provider();

		if ( $ai_provider === 'WordPress' ) {
			$context = LLM_Helper::wp_ai_image_metadata_notice_context();
			return $context['type'];
		}

		if ( $ai_provider === 'seobooster' ) {
			return AI_Image_Generator::model_supports_vision() ? 'ok' : 'credits';
		}

		return 'disabled';
	}

	/**
	 * Human-readable issue label.
	 *
	 * @param string $issue Issue key.
	 * @return string
	 */
	public static function issue_label( $issue ) {
		$labels = array(
			'empty_alt'         => __( 'Empty alt text', 'seo-booster' ),
			'empty_title'       => __( 'Empty title', 'seo-booster' ),
			'empty_caption'     => __( 'Empty caption', 'seo-booster' ),
			'empty_description' => __( 'Empty description', 'seo-booster' ),
		);

		return $labels[ $issue ] ?? $issue;
	}
}
