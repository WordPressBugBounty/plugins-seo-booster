<?php

namespace Cleverplugins\SEOBooster\Media;

use Cleverplugins\SEOBooster\Credits_Service;
use Cleverplugins\SEOBooster\LLM_Helper;
use Cleverplugins\SEOBooster\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AI_Image_Generator
 *
 * Handles AI-powered image description generation (ALT text, caption, description)
 * for WordPress media attachments using WordPress 7 Connectors (or SEO Booster Credits).
 *
 * @package Cleverplugins\SEOBooster\Media
 * @since 7.0.0
 */
class AI_Image_Generator {

	/**
	 * Initialize the class and set up hooks.
	 *
	 * @since 7.0.0
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_sb_seo_generate_image_descriptions', array( __CLASS__, 'ajax_generate_image_descriptions' ) );
		add_action( 'wp_ajax_sb_seo_restore_image_content', array( __CLASS__, 'ajax_restore_image_content' ) );
		add_action( 'wp_ajax_sb_seo_apply_image_content', array( __CLASS__, 'ajax_apply_image_content' ) );
	}

	/**
	 * AJAX handler for generating image descriptions.
	 *
	 * @since 7.0.0
	 * @return void
	 */
	public static function ajax_generate_image_descriptions() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID', 'seo-booster' ) ) );
		}

		if ( ! Utils::user_can_edit_object( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		try {
			$result = self::generate_descriptions( $attachment_id );
			wp_send_json_success( $result );
		} catch ( \Exception $e ) {
			Utils::log( 'AI image description generation failed: ' . $e->getMessage(), 2 );
			wp_send_json_error( array( 'message' => __( 'Something went wrong. Check the SEO Booster debug log for details.', 'seo-booster' ) ) );
		}
	}

	/**
	 * AJAX handler for restoring previous image content.
	 *
	 * @since 7.0.0
	 * @return void
	 */
	public static function ajax_restore_image_content() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID', 'seo-booster' ) ) );
		}

		if ( ! Utils::user_can_edit_object( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		try {
			$result = self::restore_previous_content( $attachment_id );
			wp_send_json_success( $result );
		} catch ( \Exception $e ) {
			Utils::log( 'AI image content restore failed: ' . $e->getMessage(), 2 );
			wp_send_json_error( array( 'message' => __( 'Something went wrong. Check the SEO Booster debug log for details.', 'seo-booster' ) ) );
		}
	}

	/**
	 * AJAX handler for applying generated image content.
	 *
	 * @since 7.0.0
	 * @return void
	 */
	public static function ajax_apply_image_content() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID', 'seo-booster' ) ) );
		}

		if ( ! Utils::user_can_edit_object( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		try {
			$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
			$alt_text    = isset( $_POST['alt_text'] ) ? sanitize_text_field( wp_unslash( $_POST['alt_text'] ) ) : '';
			$caption     = isset( $_POST['caption'] ) ? sanitize_textarea_field( wp_unslash( $_POST['caption'] ) ) : '';
			$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

			// Update ALT text
			if ( '' !== $alt_text ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
			}

			// Update title, caption and description
			$update_data = array( 'ID' => $attachment_id );

			if ( '' !== $title ) {
				$update_data['post_title'] = $title;
			}

			if ( '' !== $caption ) {
				$update_data['post_excerpt'] = $caption;
			}

			if ( '' !== $description ) {
				$update_data['post_content'] = $description;
			}

			if ( count( $update_data ) > 1 ) {
				wp_update_post( $update_data );
			}

			wp_send_json_success(
				array(
					'title'       => $title,
					'alt_text'    => $alt_text,
					'caption'     => $caption,
					'description' => $description,
				)
			);
		} catch ( \Exception $e ) {
			Utils::log( 'AI image content apply failed: ' . $e->getMessage(), 2 );
			wp_send_json_error( array( 'message' => __( 'Something went wrong. Check the SEO Booster debug log for details.', 'seo-booster' ) ) );
		}
	}

	/**
	 * Generate title, ALT text, caption, and description for an image.
	 *
	 * @since 7.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return array Generated content with title, alt_text, caption, and description.
	 * @throws \Exception If generation fails.
	 */
	public static function generate_descriptions( $attachment_id ) {
		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			throw new \Exception( esc_html__( 'Invalid attachment', 'seo-booster' ) );
		}

		// Check if it's an image
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			throw new \Exception( esc_html__( 'This feature is only available for images', 'seo-booster' ) );
		}

		// Check AI provider
		$ai_provider = LLM_Helper::get_selected_ai_provider();

		if ( ! in_array( $ai_provider, array( 'WordPress', 'seobooster' ), true ) ) {
			throw new \Exception( esc_html__( 'Image description generation requires an AI provider to be enabled.', 'seo-booster' ) );
		}

		// Store previous content before generating new one
		self::store_previous_content( $attachment_id );

		$image_source = self::get_image_source_for_ai( $attachment_id );
		if ( ! $image_source ) {
			throw new \Exception( esc_html__( 'Could not resolve a readable image file or publicly reachable URL. Regenerate thumbnails or ensure the image file exists on disk.', 'seo-booster' ) );
		}

		// Get context (existing metadata only; post context is not sent to vision).
		$context = self::get_attachment_context( $attachment_id );

		// Get language
		$language = self::get_language( $attachment_id );

		if ( 'seobooster' === $ai_provider ) {
			if ( ! Credits_Service::is_credits_provider_usable() ) {
				throw new \Exception( esc_html__( 'SEO Booster Credits are not available. Enable them in SEO Booster Settings or use WordPress Connectors.', 'seo-booster' ) );
			}
			if ( empty( $image_source['public_url'] ) || ! self::is_url_publicly_reachable( $image_source['public_url'] ) ) {
				throw new \Exception(
					esc_html__( 'SEO Booster Credits requires a publicly reachable image URL. Local or staging sites should use the WordPress AI provider instead. No metadata was saved.', 'seo-booster' )
				);
			}
			$generated = self::generate_via_credits( $attachment_id, $image_source, $context, $language );
		} else {
			$generated = self::call_wp_connector_vision( $image_source, $context, $language );
		}

		$generated['image_source'] = $image_source;

		// Store latest generated content
		update_post_meta(
			$attachment_id,
			'_sb_ai_image_latest_content',
			array(
				'title'       => $generated['title'],
				'alt_text'    => $generated['alt_text'],
				'caption'     => $generated['caption'],
				'description' => $generated['description'],
				'timestamp'   => time(),
			)
		);

		// Append to recent request history when using credits API (same structure as post metabox)
		if ( ! empty( $generated['request_id'] ) ) {
			$recent = get_post_meta( $attachment_id, '_sb_recent_request_ids', true );
			if ( ! is_array( $recent ) ) {
				$recent = array();
			}
			array_unshift(
				$recent,
				array(
					'id'      => $generated['request_id'],
					'type'    => 'image_analysis',
					'created' => time(),
				)
			);
			$recent = array_slice( $recent, 0, 10 );
			update_post_meta( $attachment_id, '_sb_recent_request_ids', $recent );
		}

		return $generated;
	}

	/**
	 * Get attachment context (attached post information).
	 *
	 * @since 7.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return array Context information.
	 */
	private static function get_attachment_context( $attachment_id ) {
		$context = array(
			'post_title'   => '',
			'post_excerpt' => '',
			'categories'   => array(),
			'filename'     => '',
		);

		$attached_file       = get_attached_file( $attachment_id );
		$context['filename'] = self::humanize_filename( $attached_file ? $attached_file : '' );

		$attachment = get_post( $attachment_id );
		if ( $attachment && '' !== $attachment->post_title ) {
			$context['existing_title'] = $attachment->post_title;
		}

		// Get the post this attachment is attached to
		$parent_id = wp_get_post_parent_id( $attachment_id );

		if ( $parent_id ) {
			$parent_post = get_post( $parent_id );
			if ( $parent_post ) {
				$context['post_title'] = $parent_post->post_title;

				// Get excerpt
				if ( ! empty( $parent_post->post_excerpt ) ) {
					$context['post_excerpt'] = $parent_post->post_excerpt;
				} else {
					// Generate excerpt from content
					$content                 = wp_strip_all_tags( $parent_post->post_content );
					$context['post_excerpt'] = wp_trim_words( $content, 25, '...' );
				}

				// Get categories
				$categories = wp_get_post_categories( $parent_id, array( 'fields' => 'names' ) );
				if ( ! empty( $categories ) ) {
					$context['categories'] = $categories;
				}
			}
		}

		return apply_filters( 'sb_ai_image_attachment_context', $context, $attachment_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook; renaming would break existing integrations.
	}

	/**
	 * Convert an attachment file path to a human-readable hint, or empty if generic.
	 *
	 * @since 7.2
	 * @param string $path Absolute or relative file path.
	 * @return string Humanized basename without extension, or empty string.
	 */
	private static function humanize_filename( $path ) {
		if ( '' === $path ) {
			return '';
		}

		$base = wp_basename( $path );
		$name = pathinfo( $base, PATHINFO_FILENAME );
		if ( '' === $name || false === $name ) {
			return '';
		}

		$compact = preg_replace( '/[\s_-]+/', '', $name );
		if ( '' === $compact || preg_match( '/^\d+$/', $compact ) ) {
			return '';
		}
		if ( preg_match( '/^(?:img|dsc|p\d+|photo|image|wp|screenshot|capture)[\d_-]*$/i', $compact ) ) {
			return '';
		}

		$human = preg_replace( '/[-_]+/', ' ', $name );
		$human = preg_replace( '/\s+/', ' ', trim( $human ) );

		return $human;
	}

	/**
	 * Whether the attachment has a readable image file on disk (any preferred size).
	 *
	 * Filesystem check only — no HTTP requests.
	 *
	 * @since 7.2.1
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function attachment_has_readable_file( $attachment_id ) {
		return null !== self::get_readable_local_image_source( $attachment_id );
	}

	/**
	 * Resolve the best on-disk file and public URL for AI vision analysis.
	 *
	 * Prefers medium (~300px) for cost and speed, then large, then the full
	 * original only as a last resort. Returns the first size that has a readable
	 * local file or a publicly reachable URL.
	 *
	 * @since 7.0.5
	 * @param int $attachment_id Attachment ID.
	 * @return array{file_path: string, public_url: string, size: string, mime_type: string}|null
	 */
	public static function get_image_source_for_ai( $attachment_id ) {
		$local_source = self::get_readable_local_image_source( $attachment_id );
		if ( $local_source ) {
			return $local_source;
		}

		$mime_type = get_post_mime_type( $attachment_id );
		if ( ! $mime_type ) {
			$mime_type = 'image/jpeg';
		}

		$sizes = array( 'medium', 'large', 'full' );

		foreach ( $sizes as $size ) {
			$image_data = wp_get_attachment_image_src( $attachment_id, $size );
			if ( ! $image_data || empty( $image_data[0] ) ) {
				continue;
			}

			$url    = $image_data[0];
			$width  = (int) $image_data[1];
			$height = (int) $image_data[2];

			if ( 'full' !== $size && ( $width < 150 || $height < 150 ) ) {
				continue;
			}

			if ( strpos( $url, 'http' ) !== 0 ) {
				$url = home_url( $url );
			}

			if ( self::is_url_publicly_reachable( $url ) ) {
				return array(
					'file_path'  => '',
					'public_url' => $url,
					'size'       => $size,
					'mime_type'  => $mime_type,
				);
			}
		}

		return null;
	}

	/**
	 * First readable local derivative for AI (medium, large, then full).
	 *
	 * @since 7.2.1
	 * @param int $attachment_id Attachment ID.
	 * @return array{file_path: string, public_url: string, size: string, mime_type: string}|null
	 */
	private static function get_readable_local_image_source( $attachment_id ) {
		$mime_type = get_post_mime_type( $attachment_id );
		if ( ! $mime_type ) {
			$mime_type = 'image/jpeg';
		}

		$sizes = array( 'medium', 'large', 'full' );

		foreach ( $sizes as $size ) {
			$image_data = wp_get_attachment_image_src( $attachment_id, $size );
			if ( ! $image_data || empty( $image_data[0] ) ) {
				continue;
			}

			$url    = $image_data[0];
			$width  = (int) $image_data[1];
			$height = (int) $image_data[2];

			if ( 'full' !== $size && ( $width < 150 || $height < 150 ) ) {
				continue;
			}

			if ( strpos( $url, 'http' ) !== 0 ) {
				$url = home_url( $url );
			}

			$file_path = self::get_derivative_file_path( $attachment_id, $size );

			if ( $file_path && is_readable( $file_path ) ) {
				return array(
					'file_path'  => $file_path,
					'public_url' => $url,
					'size'       => $size,
					'mime_type'  => $mime_type,
				);
			}
		}

		return null;
	}

	/**
	 * Absolute path to an attachment derivative on disk.
	 *
	 * @since 7.0.5
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size          Image size name.
	 * @return string File path or empty string.
	 */
	private static function get_derivative_file_path( $attachment_id, $size ) {
		$attached_file = get_attached_file( $attachment_id );
		if ( ! $attached_file ) {
			return '';
		}

		if ( 'full' === $size ) {
			return is_readable( $attached_file ) ? $attached_file : '';
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( empty( $metadata['sizes'][ $size ]['file'] ) ) {
			return '';
		}

		$path = path_join( dirname( $attached_file ), $metadata['sizes'][ $size ]['file'] );

		return is_readable( $path ) ? $path : '';
	}

	/**
	 * Whether a URL is reachable from the public internet (not localhost/staging-only).
	 *
	 * @since 7.0.5
	 * @param string $url Image URL.
	 * @return bool
	 */
	public static function is_url_publicly_reachable( $url ) {
		if ( empty( $url ) || strpos( $url, 'http' ) !== 0 ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host || self::is_local_only_host( $host ) ) {
			return false;
		}

		$status = self::get_image_url_http_status( $url );

		return ! is_wp_error( $status ) && 200 === (int) $status;
	}

	/**
	 * Whether a host is local-only (localhost, .local, private IP).
	 *
	 * @since 7.0.5
	 * @param string $host Hostname or IP.
	 * @return bool
	 */
	private static function is_local_only_host( $host ) {
		$host = strtolower( (string) $host );
		if ( false !== strpos( $host, ':' ) && filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$url = 'http://[' . $host . ']/';
		} else {
			$url = 'http://' . $host . '/';
		}
		return ! \Cleverplugins\SEOBooster\Utils::is_safe_outbound_url( $url );
	}

	/**
	 * Get language for attachment using WPML.
	 *
	 * @since 7.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return string Language code.
	 */
	private static function get_language( $attachment_id ) {
		return LLM_Helper::get_post_language( $attachment_id );
	}

	/**
	 * Preflight check: verify the image URL returns HTTP 200 before sending to AI.
	 *
	 * Uses HEAD first, with a small GET fallback for servers that don't support HEAD.
	 *
	 * @since 7.0.0
	 * @param string $url Image URL.
	 * @return int|\WP_Error HTTP status code or WP_Error on request failure.
	 */
	private static function get_image_url_http_status( $url ) {
		if ( empty( $url ) || strpos( $url, 'http' ) !== 0 ) {
			return new \WP_Error( 'sb_invalid_image_url', 'Invalid image URL' );
		}

		if ( ! \Cleverplugins\SEOBooster\Utils::is_safe_outbound_url( $url ) ) {
			return new \WP_Error( 'sb_blocked_image_url', 'Blocked private or local URL' );
		}

		$args = array(
			'timeout'     => 10,
			'redirection' => 0,
			'user-agent'  => 'SEO Booster Image Checker/1.0',
		);

		$response = wp_remote_head( $url, $args );
		if ( ! is_wp_error( $response ) ) {
			$status_code = wp_remote_retrieve_response_code( $response );
			if ( ! empty( $status_code ) ) {
				return (int) $status_code;
			}
		}

		// Some servers block or don't support HEAD (405/501). Try a lightweight GET as fallback.
		$get_args            = $args;
		$get_args['headers'] = array(
			'Range' => 'bytes=0-0',
		);

		$response = wp_remote_get( $url, $get_args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return (int) wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Build vision-safe context (existing metadata + soft naming hints).
	 *
	 * @since 7.0.5
	 * @param array $context Full attachment context.
	 * @return array
	 */
	private static function get_vision_context( $context ) {
		$vision = array();
		$keys   = array(
			'existing_title',
			'existing_alt_text',
			'existing_caption',
			'existing_description',
			'filename',
			'post_title',
		);

		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $context ) && '' !== $context[ $key ] ) {
				$vision[ $key ] = $context[ $key ];
			}
		}

		return $vision;
	}

	/**
	 * Build user prompt for image description generation.
	 *
	 * @since 7.0.5
	 * @param string $language Language code.
	 * @param array  $context  Vision-safe context.
	 * @return string
	 */
	private static function build_vision_user_prompt( $language, array $context ) {
		$existing_note  = '';
		$existing_parts = array();
		$hint_parts     = array();

		if ( ! empty( $context['filename'] ) ) {
			$hint_parts[] = 'filename: "' . $context['filename'] . '"';
		}
		if ( ! empty( $context['existing_title'] ) ) {
			$hint_parts[] = 'image title: "' . $context['existing_title'] . '"';
		}
		if ( ! empty( $context['post_title'] ) ) {
			$hint_parts[] = 'parent post: "' . $context['post_title'] . '"';
		}

		if ( array_key_exists( 'existing_title', $context ) && '' !== $context['existing_title'] ) {
			$existing_parts[] = 'title: "' . $context['existing_title'] . '"';
		}
		if ( array_key_exists( 'existing_alt_text', $context ) ) {
			$existing_parts[] = 'alt_text: "' . $context['existing_alt_text'] . '"';
		}
		if ( array_key_exists( 'existing_caption', $context ) ) {
			$existing_parts[] = 'caption: "' . $context['existing_caption'] . '"';
		}
		if ( array_key_exists( 'existing_description', $context ) ) {
			$existing_parts[] = 'description: "' . $context['existing_description'] . '"';
		}

		$hints_note = '';
		if ( ! empty( $hint_parts ) ) {
			$hints_note = 'Hints (use only to name or disambiguate things that are genuinely visible; ignore any hint that contradicts the image): '
				. implode( ', ', $hint_parts ) . '. ';
		}

		if ( ! empty( $existing_parts ) ) {
			$existing_note = 'Existing attachment metadata (for reference only; do not copy if it contradicts what you see in the image): '
				. implode( ', ', $existing_parts ) . '. ';
		}

		$user_prompt  = "Analyze the attached image and generate four descriptions in {$language}.\n\n";
		$user_prompt .= "Rules:\n";
		$user_prompt .= "- Describe ONLY what is visibly present in the attached image.\n";
		$user_prompt .= "- Hints below may help you name or disambiguate things that are genuinely visible. Use them ONLY for that. If a hint is not supported by the image, ignore it. Never invent details that are not visible.\n";
		$user_prompt .= "- If you cannot see or analyze the image, or you are uncertain, respond with ONLY this JSON: {\"error\":\"cannot_analyze_image\",\"message\":\"brief reason\"}\n\n";
		$user_prompt .= "When the image is visible and analyzable, return ONLY valid JSON with these keys:\n";
		$user_prompt .= "- image_visible (boolean, must be true)\n";
		$user_prompt .= "- title: concise media library title (max 60 characters)\n";
		$user_prompt .= "- alt_text: screen-reader text (max 125 characters, no period at end)\n";
		$user_prompt .= "- caption: brief caption (1-2 sentences)\n";
		$user_prompt .= "- description: detailed description (2-3 sentences)\n\n";

		if ( $hints_note ) {
			$user_prompt .= $hints_note . "\n";
		}

		if ( $existing_note ) {
			$user_prompt .= $existing_note . "\n";
		}

		$user_prompt .= 'Return ONLY JSON. No markdown or other text.';

		return $user_prompt;
	}

	/**
	 * Parse and validate AI vision JSON response.
	 *
	 * @since 7.0.5
	 * @param string $response_text Raw AI response.
	 * @return array Generated descriptions.
	 * @throws \Exception If response is invalid or analysis was refused.
	 */
	private static function parse_vision_response( $response_text ) {
		if ( empty( $response_text ) ) {
			throw new \Exception( esc_html__( 'Empty response from AI. Configure a vision-capable provider at Settings → Connectors.', 'seo-booster' ) );
		}

		if ( is_wp_error( $response_text ) ) {
			throw new \Exception( esc_html( $response_text->get_error_message() ) );
		}

		$response_text = preg_replace( '#^```(?:json)?\s*|\s*```$#', '', trim( (string) $response_text ) );
		$parsed        = json_decode( $response_text, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \Exception( esc_html__( 'Invalid JSON response from AI. No metadata was saved.', 'seo-booster' ) );
		}

		if ( ! empty( $parsed['error'] ) ) {
			$detail = ! empty( $parsed['message'] ) ? $parsed['message'] : $parsed['error'];
			throw new \Exception(
				esc_html(
					sprintf(
					/* translators: %s: error detail from AI */
						__( 'Could not analyze image: %s. No metadata was saved.', 'seo-booster' ),
						sanitize_text_field( $detail )
					)
				)
			);
		}

		if ( empty( $parsed['image_visible'] ) || true !== $parsed['image_visible'] ) {
			throw new \Exception( esc_html__( 'The AI could not verify the image content. No metadata was saved.', 'seo-booster' ) );
		}

		if ( empty( $parsed['title'] ) || empty( $parsed['alt_text'] ) || empty( $parsed['caption'] ) || empty( $parsed['description'] ) ) {
			throw new \Exception( esc_html__( 'AI response missing required fields (title, alt_text, caption, description). No metadata was saved.', 'seo-booster' ) );
		}

		return array(
			'title'       => sanitize_text_field( $parsed['title'] ),
			'alt_text'    => sanitize_text_field( $parsed['alt_text'] ),
			'caption'     => sanitize_textarea_field( $parsed['caption'] ),
			'description' => sanitize_textarea_field( $parsed['description'] ),
		);
	}

	/**
	 * Generate image descriptions using WordPress AI Connectors (WP 7).
	 * Sends the image via with_file() for true vision input.
	 *
	 * @since 7.0.0
	 * @param array  $image_source file_path, public_url, size, mime_type.
	 * @param array  $context      Attachment context (existing metadata only is used).
	 * @param string $language     Language code.
	 * @return array Generated descriptions.
	 * @throws \Exception If API call fails.
	 */
	private static function call_wp_connector_vision( array $image_source, $context, $language ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			throw new \Exception( esc_html__( 'WordPress AI is not available. Use WordPress 7 or later and configure Settings → Connectors.', 'seo-booster' ) );
		}

		if ( ! LLM_Helper::wp_ai_is_available() ) {
			throw new \Exception( esc_html( LLM_Helper::wp_ai_unavailable_message() ) );
		}

		$file_path  = $image_source['file_path'] ?? '';
		$public_url = $image_source['public_url'] ?? '';
		$mime_type  = $image_source['mime_type'] ?? 'image/jpeg';

		$vision_context = self::get_vision_context( $context );
		$user_prompt    = self::build_vision_user_prompt( $language, $vision_context );

		$system = __(
			'You write accessible image descriptions for websites. Base your descriptions on what is visible in the attached image. You may use provided hints (filename, titles) only to correctly name or disambiguate things you can actually see; never use them to invent content. If the image is missing or unreadable, return only {"error":"cannot_analyze_image","message":"..."}. Otherwise return JSON with image_visible true plus title, alt_text, caption, and description.',
			'seo-booster'
		);

		$builder = LLM_Helper::ai_prompt( $user_prompt )
			->using_system_instruction( $system );

		if ( ! empty( $file_path ) && is_readable( $file_path ) ) {
			$builder = $builder->with_file( $file_path, $mime_type );
		} elseif ( ! empty( $public_url ) && self::is_url_publicly_reachable( $public_url ) ) {
			$builder = $builder->with_file( $public_url );
		} else {
			throw new \Exception(
				esc_html__( 'Image file is not readable and URL is not publicly reachable. Cannot analyze image safely. No metadata was saved.', 'seo-booster' )
			);
		}

		$response_text = LLM_Helper::generate_ai_text( $builder, 'image-metadata' );

		return self::parse_vision_response( $response_text );
	}

	/**
	 * Store previous content before generating new AI content.
	 *
	 * @since 7.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private static function store_previous_content( $attachment_id ) {
		$current_alt         = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$attachment          = get_post( $attachment_id );
		$current_title       = $attachment ? $attachment->post_title : '';
		$current_caption     = $attachment ? $attachment->post_excerpt : '';
		$current_description = $attachment ? $attachment->post_content : '';

		// Only store if there's existing content
		if ( ! empty( $current_title ) || ! empty( $current_alt ) || ! empty( $current_caption ) || ! empty( $current_description ) ) {
			update_post_meta(
				$attachment_id,
				'_sb_ai_image_previous_content',
				array(
					'title'       => $current_title,
					'alt_text'    => $current_alt,
					'caption'     => $current_caption,
					'description' => $current_description,
					'timestamp'   => time(),
				)
			);
		}
	}

	/**
	 * Restore previous AI-generated content.
	 *
	 * @since 7.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return array Restored content.
	 * @throws \Exception If no previous content exists.
	 */
	public static function restore_previous_content( $attachment_id ) {
		$previous = get_post_meta( $attachment_id, '_sb_ai_image_previous_content', true );

		if ( empty( $previous ) || ! is_array( $previous ) ) {
			throw new \Exception( esc_html__( 'No previous content to restore', 'seo-booster' ) );
		}

		// Restore ALT text
		$alt_text = $previous['alt_text'] ?? '';
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

		// Restore title, caption and description
		$attachment = get_post( $attachment_id );
		if ( $attachment ) {
			$update_data = array(
				'ID' => $attachment_id,
			);

			if ( isset( $previous['title'] ) ) {
				$update_data['post_title'] = $previous['title'];
			}

			if ( isset( $previous['caption'] ) ) {
				$update_data['post_excerpt'] = $previous['caption'];
			}

			if ( isset( $previous['description'] ) ) {
				$update_data['post_content'] = $previous['description'];
			}

			wp_update_post( $update_data );
		}

		return array(
			'title'       => $previous['title'] ?? '',
			'alt_text'    => $alt_text,
			'caption'     => $previous['caption'] ?? '',
			'description' => $previous['description'] ?? '',
		);
	}

	/**
	 * Get latest AI-generated content for an attachment.
	 *
	 * @since 7.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return array|false Latest content or false if not found.
	 */
	public static function get_latest_content( $attachment_id ) {
		return get_post_meta( $attachment_id, '_sb_ai_image_latest_content', true );
	}

	/**
	 * Get previous content for an attachment.
	 *
	 * @since 7.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return array|false Previous content or false if not found.
	 */
	public static function get_previous_content( $attachment_id ) {
		return get_post_meta( $attachment_id, '_sb_ai_image_previous_content', true );
	}

	/**
	 * Check if the current AI setup supports vision (image description generation).
	 * For WordPress provider, returns true when wp_ai_client_prompt exists. For Credits, returns true.
	 *
	 * @since 7.0.0
	 * @param string|null $model Model name (legacy; ignored when provider is WordPress).
	 * @return bool True if model supports vision, false otherwise.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Legacy API; parameter retained for backward compatibility.
	public static function model_supports_vision( $model = null ) {
		$provider = LLM_Helper::get_selected_ai_provider();
		if ( 'WordPress' === $provider ) {
			return LLM_Helper::wp_ai_image_tools_can_process();
		}
		if ( 'seobooster' === $provider ) {
			return Credits_Service::is_credits_provider_usable();
		}
		return false;
	}

	/**
	 * Generate image descriptions via the Credits API (synchronous poll).
	 *
	 * @since 6.2.0
	 * @param int    $attachment_id Attachment ID.
	 * @param array  $image_source  file_path, public_url, size, mime_type.
	 * @param array  $context       Attachment context (vision-safe subset is sent).
	 * @param string $language      Language locale.
	 * @return array Generated descriptions.
	 */
	private static function generate_via_credits( $attachment_id, array $image_source, $context, $language ) {
		if ( ! Credits_Service::is_registered() ) {
			throw new \Exception( esc_html__( 'Credits account not connected. Go to SEO Booster Settings to connect.', 'seo-booster' ) );
		}

		$public_url = $image_source['public_url'] ?? '';
		if ( empty( $public_url ) || ! self::is_url_publicly_reachable( $public_url ) ) {
			throw new \Exception(
				esc_html__( 'SEO Booster Credits requires a publicly reachable image URL. No metadata was saved.', 'seo-booster' )
			);
		}

		$filename = basename( get_attached_file( $attachment_id ) );

		$input_data = array(
			'image_url'  => $public_url,
			'language'   => $language,
			'context'    => self::get_vision_context( $context ),
			'filename'   => $filename,
			'post_title' => $context['post_title'] ?? '',
			'image_size' => $image_source['size'] ?? '',
		);

		$result = Credits_Service::submit_request( 'image_analysis', $input_data );

		if ( ! $result['success'] ) {
			throw new \Exception( esc_html( $result['error'] ) );
		}

		$request_id = $result['request_id'];

		// Poll for completion (image analysis typically takes 5-15 seconds)
		$max_polls     = 30;
		$poll_interval = 2;

		for ( $i = 0; $i < $max_polls; $i++ ) {
			sleep( $poll_interval );

			$status = Credits_Service::get_request_status( $request_id );

			if ( ! $status['success'] ) {
				continue;
			}

			if ( 'completed' === $status['status'] && ! empty( $status['data'] ) ) {
				if ( ! empty( $status['data']['error'] ) ) {
					$detail = $status['data']['message'] ?? $status['data']['error'];
					throw new \Exception(
						esc_html(
							sprintf(
							/* translators: %s: error detail */
								__( 'Could not analyze image: %s. No metadata was saved.', 'seo-booster' ),
								sanitize_text_field( $detail )
							)
						)
					);
				}

				if ( empty( $status['data']['image_visible'] ) || true !== $status['data']['image_visible'] ) {
					throw new \Exception( esc_html__( 'The AI could not verify the image content. No metadata was saved.', 'seo-booster' ) );
				}

				if ( empty( $status['data']['title'] ) || empty( $status['data']['alt_text'] )
					|| empty( $status['data']['caption'] ) || empty( $status['data']['description'] ) ) {
					throw new \Exception( esc_html__( 'AI response missing required fields. No metadata was saved.', 'seo-booster' ) );
				}

				return array(
					'title'       => sanitize_text_field( $status['data']['title'] ?? '' ),
					'alt_text'    => sanitize_text_field( $status['data']['alt_text'] ?? '' ),
					'caption'     => sanitize_text_field( $status['data']['caption'] ?? '' ),
					'description' => sanitize_textarea_field( $status['data']['description'] ?? '' ),
					'request_id'  => $request_id,
				);
			}

			if ( 'failed' === $status['status'] ) {
				throw new \Exception(
					esc_html( __( 'Image analysis failed: ', 'seo-booster' ) . ( $status['error'] ?? __( 'Unknown error', 'seo-booster' ) ) )
				);
			}
		}

		throw new \Exception( esc_html__( 'Image analysis timed out. Credits have been refunded.', 'seo-booster' ) );
	}
}
