<?php

namespace Cleverplugins\SEOBooster\Tools;

use Cleverplugins\SEOBooster\Media\AI_Image_Generator;
use Cleverplugins\SEOBooster\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools-page-only batch processing for image metadata AI generation.
 *
 * @package Cleverplugins\SEOBooster\Tools
 */
class Tools_Image_Batch extends Tools_Batch_Base {

	/**
	 * @var int|null Attachment ID for context filter closure.
	 */
	private static $context_attachment_id = null;

	/** @inheritDoc */
	public static function get_transient_prefix() {
		return 'sb_tools_batch_';
	}

	/** @inheritDoc */
	public static function get_nonce_action() {
		return 'sb_tools_image_nonce';
	}

	/** @inheritDoc */
	public static function get_ajax_action_start() {
		return 'sb_tools_start_batch';
	}

	/** @inheritDoc */
	public static function get_ajax_action_process() {
		return 'sb_tools_process_image';
	}

	/** @inheritDoc */
	public static function get_ajax_action_status() {
		return 'sb_tools_batch_status';
	}

	/** @inheritDoc */
	public static function get_ajax_action_cancel() {
		return 'sb_tools_cancel_batch';
	}

	/** @inheritDoc */
	public static function get_item_id_post_key() {
		return 'attachment_id';
	}

	/** @inheritDoc */
	public static function get_item_ids_response_key() {
		return 'attachment_ids';
	}

	/**
	 * Default apply field map.
	 *
	 * @return array
	 */
	public static function default_apply_fields() {
		return array(
			'title'       => false,
			'alt_text'    => true,
			'caption'     => false,
			'description' => false,
		);
	}

	/**
	 * Parse apply_fields from POST.
	 *
	 * @param array|null $raw Raw input.
	 * @return array
	 */
	public static function parse_apply_fields( $raw ) {
		$defaults = self::default_apply_fields();
		if ( ! is_array( $raw ) ) {
			return $defaults;
		}
		foreach ( array_keys( $defaults ) as $key ) {
			$defaults[ $key ] = self::is_apply_field_enabled( isset( $raw[ $key ] ) ? $raw[ $key ] : false );
		}
		return $defaults;
	}

	/**
	 * Whether an apply-field value from POST/user meta is enabled.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function is_apply_field_enabled( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (int) $value === 1;
		}
		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
	}

	/** @inheritDoc */
	protected static function validate_preconditions() {
		if ( ! Tools_Image_Scanner::ai_is_available() ) {
			$message = Tools_Image_Scanner::get_ai_unavailable_message();
			if ( $message === '' ) {
				$message = __( 'AI is not configured for image generation.', 'seo-booster' );
			}
			return $message;
		}
		return '';
	}

	/** @inheritDoc */
	protected static function prepare_batch_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in Tools_Batch_Base::ajax_start_batch() before this runs.
		$process_scope = isset( $_POST['process_scope'] )
			? sanitize_text_field( wp_unslash( $_POST['process_scope'] ) )
			: 'selected';

		$filters = isset( $_POST['filters'] ) && is_array( $_POST['filters'] )
			? array_values(
				array_intersect(
					array_map( 'sanitize_text_field', wp_unslash( $_POST['filters'] ) ),
					Tools_Image_Scanner::get_filter_keys()
				)
			)
			: array();

		if ( $process_scope === 'all_matching' ) {
			if ( empty( $filters ) ) {
				wp_send_json_error( array( 'message' => __( 'Select at least one scan filter.', 'seo-booster' ) ) );
			}

			$attachment_ids = Tools_Image_Scanner::get_matching_attachment_ids( $filters );
			$attachment_ids = array_values(
				array_filter(
					$attachment_ids,
					function ( $id ) {
						return Tools_Image_Scanner::is_processable_attachment( $id );
					}
				)
			);
		} else {
			$attachment_ids = isset( $_POST['attachment_ids'] ) && is_array( $_POST['attachment_ids'] )
				? array_map( 'intval', wp_unslash( $_POST['attachment_ids'] ) )
				: array();

			$attachment_ids = array_values(
				array_filter(
					$attachment_ids,
					function ( $id ) {
						return Tools_Image_Scanner::is_processable_attachment( $id );
					}
				)
			);

			if ( ! empty( $filters ) ) {
				$attachment_ids = Tools_Image_Scanner::filter_still_matching_ids( $attachment_ids, $filters );
			}
		}

		$attachment_ids_before_readable = $attachment_ids;
		$filtered                       = Tools_Image_Scanner::filter_readable_attachment_ids( $attachment_ids );
		$attachment_ids                 = $filtered['ids'];

		$attachment_ids = array_values(
			array_filter(
				$attachment_ids,
				function ( $id ) {
					return Utils::user_can_edit_object( (int) $id );
				}
			)
		);

		if ( empty( $attachment_ids ) ) {
			if ( ! empty( $attachment_ids_before_readable ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'No images with readable files were found. Regenerate thumbnails or re-upload missing files.', 'seo-booster' ),
					)
				);
			}
			wp_send_json_error( array( 'message' => __( 'No processable images selected. Supported formats: JPEG, PNG, WebP.', 'seo-booster' ) ) );
		}

		$apply_fields = self::parse_apply_fields(
			isset( $_POST['apply_fields'] ) && is_array( $_POST['apply_fields'] )
				? wp_unslash( $_POST['apply_fields'] )
				: null
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! self::has_any_apply_field( $apply_fields ) ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one field to apply.', 'seo-booster' ) ) );
		}

		return array(
			'item_ids' => $attachment_ids,
			'config'   => array(
				'apply_fields'  => $apply_fields,
				'scan_filters'  => $filters,
				'process_scope' => $process_scope,
			),
		);
	}

	/** @inheritDoc */
	protected static function on_batch_start( array $item_ids, array $config ) {
		if ( ! empty( $config['apply_fields'] ) && is_array( $config['apply_fields'] ) ) {
			self::save_user_defaults( $config['apply_fields'] );
		}
	}

	/** @inheritDoc */
	protected static function get_start_batch_extra_response( array $config ) {
		return array(
			'apply_fields' => $config['apply_fields'] ?? self::default_apply_fields(),
		);
	}

	/** @inheritDoc */
	public static function process_single( $item_id, array $config ) {
		$attachment_id = (int) $item_id;
		$apply_fields  = isset( $config['apply_fields'] ) && is_array( $config['apply_fields'] )
			? $config['apply_fields']
			: self::default_apply_fields();

		return self::process_image( $attachment_id, $apply_fields );
	}

	/** @inheritDoc */
	protected static function get_failed_item_meta( $item_id, $error_message ) {
		$thumb = wp_get_attachment_image_src( $item_id, 'thumbnail' );
		return array(
			'attachment_id' => (int) $item_id,
			'image_title'   => get_the_title( $item_id ),
			'thumb_url'     => $thumb ? $thumb[0] : '',
			'edit_url'      => get_edit_post_link( $item_id, 'raw' ) ?: '',
		);
	}

	/**
	 * Process single attachment: generate AI content and apply selected fields.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $apply_fields  Field flags.
	 * @return array Preview payload.
	 * @throws \Exception On failure.
	 */
	public static function process_image( $attachment_id, array $apply_fields ) {
		if ( ! Tools_Image_Scanner::is_processable_attachment( $attachment_id ) ) {
			$mime       = get_post_mime_type( $attachment_id );
			$mime_label = $mime ? $mime : __( 'unknown', 'seo-booster' );
			throw new \Exception(
				esc_html( sprintf(
					/* translators: 1: MIME type, 2: supported formats list */
					__( 'This file type (%1$s) is not supported. Supported formats: %2$s.', 'seo-booster' ),
					$mime_label,
					Tools_Image_Scanner::get_processable_formats_label()
				) )
			);
		}

		$before = Tools_Image_Scanner::get_attachment_meta( $attachment_id );

		if ( ! AI_Image_Generator::attachment_has_readable_file( $attachment_id ) ) {
			throw new \Exception(
				esc_html__( 'Image file was not found on disk. Regenerate thumbnails or re-upload the file.', 'seo-booster' )
			);
		}

		add_filter( 'sb_ai_image_attachment_context', array( __CLASS__, 'filter_attachment_context' ), 10, 2 );
		self::$context_attachment_id = $attachment_id;

		try {
			$generated = AI_Image_Generator::generate_descriptions( $attachment_id );
		} finally {
			remove_filter( 'sb_ai_image_attachment_context', array( __CLASS__, 'filter_attachment_context' ), 10 );
			self::$context_attachment_id = null;
		}

		$content = array(
			'title'       => $generated['title'] ?? '',
			'alt_text'    => $generated['alt_text'] ?? '',
			'caption'     => $generated['caption'] ?? '',
			'description' => $generated['description'] ?? '',
		);

		$after = self::apply_content( $attachment_id, $content, $apply_fields );

		$image_source = AI_Image_Generator::get_image_source_for_ai( $attachment_id );
		$thumb_url    = '';
		$image_size   = '';

		if ( ! empty( $generated['image_source']['public_url'] ) ) {
			$thumb_url  = $generated['image_source']['public_url'];
			$image_size = $generated['image_source']['size'] ?? '';
		} elseif ( $image_source && ! empty( $image_source['public_url'] ) ) {
			$thumb_url  = $image_source['public_url'];
			$image_size = $image_source['size'] ?? '';
		} else {
			$thumb     = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
			$thumb_url = $thumb ? $thumb[0] : '';
		}

		return array(
			'attachment_id'    => $attachment_id,
			'image_title'      => get_the_title( $attachment_id ),
			'thumb_url'        => $thumb_url,
			'image_source_url' => $thumb_url,
			'image_size'       => $image_size,
			'before'           => $before,
			'after'            => $after,
			'apply_fields'     => $apply_fields,
			'generated'        => $content,
		);
	}

	/**
	 * Merge existing attachment meta into AI context (Tools batch only).
	 *
	 * @param array $context       Context array.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	public static function filter_attachment_context( $context, $attachment_id ) {
		if ( self::$context_attachment_id !== (int) $attachment_id ) {
			return $context;
		}

		$meta = Tools_Image_Scanner::get_attachment_meta( $attachment_id );

		$context['existing_title']       = $meta['title'];
		$context['existing_alt_text']    = $meta['alt_text'];
		$context['existing_caption']     = $meta['caption'];
		$context['existing_description'] = $meta['description'];

		return $context;
	}

	/**
	 * Apply generated content for enabled fields only.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $content       Generated content.
	 * @param array $apply_fields  Field flags.
	 * @return array Values written (after).
	 */
	public static function apply_content( $attachment_id, array $content, array $apply_fields ) {
		$after = array();

		if ( ! empty( $apply_fields['alt_text'] ) && isset( $content['alt_text'] ) && $content['alt_text'] !== '' ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $content['alt_text'] ) );
			$after['alt_text'] = sanitize_text_field( $content['alt_text'] );
		}

		$update_data = array( 'ID' => $attachment_id );

		if ( ! empty( $apply_fields['title'] ) && ! empty( $content['title'] ) ) {
			$update_data['post_title'] = sanitize_text_field( $content['title'] );
			$after['title']            = $update_data['post_title'];
		}

		if ( ! empty( $apply_fields['caption'] ) && isset( $content['caption'] ) && $content['caption'] !== '' ) {
			$update_data['post_excerpt'] = sanitize_textarea_field( $content['caption'] );
			$after['caption']            = $update_data['post_excerpt'];
		}

		if ( ! empty( $apply_fields['description'] ) && isset( $content['description'] ) && $content['description'] !== '' ) {
			$update_data['post_content'] = sanitize_textarea_field( $content['description'] );
			$after['description']        = $update_data['post_content'];
		}

		if ( count( $update_data ) > 1 ) {
			wp_update_post( $update_data );
		}

		return $after;
	}

	/**
	 * @param array $apply_fields Apply fields.
	 * @return bool
	 */
	private static function has_any_apply_field( array $apply_fields ) {
		foreach ( $apply_fields as $enabled ) {
			if ( $enabled ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Save user default apply fields.
	 *
	 * @param array $apply_fields Apply fields.
	 * @return void
	 */
	private static function save_user_defaults( array $apply_fields ) {
		update_user_meta( get_current_user_id(), 'sb_tools_image_apply_fields', $apply_fields );
	}

	/**
	 * Load saved apply fields for current user.
	 *
	 * @return array
	 */
	public static function get_user_apply_fields() {
		$saved = get_user_meta( get_current_user_id(), 'sb_tools_image_apply_fields', true );
		if ( is_array( $saved ) ) {
			return self::parse_apply_fields( $saved );
		}
		return self::default_apply_fields();
	}

	/**
	 * Load saved scan filters for current user.
	 *
	 * @return string[]
	 */
	public static function get_user_scan_filters() {
		$saved = get_user_meta( get_current_user_id(), 'sb_tools_image_scan_filters', true );
		if ( is_array( $saved ) ) {
			return array_values( array_intersect( $saved, Tools_Image_Scanner::get_filter_keys() ) );
		}
		return array( 'empty_alt' );
	}

	/**
	 * Save scan filters to user meta.
	 *
	 * @param string[] $filters Filters.
	 * @return void
	 */
	public static function save_user_scan_filters( array $filters ) {
		update_user_meta( get_current_user_id(), 'sb_tools_image_scan_filters', $filters );
	}
}
