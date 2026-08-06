<?php

namespace Cleverplugins\SEOBooster\SEO_Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEOPress adapter.
 *
 * @package Cleverplugins\SEOBooster\SEO_Plugins
 * @since 7.2.5
 */
class Seopress_Adapter extends Abstract_Post_Meta_Adapter {

	/**
	 * @return string
	 */
	public function get_slug() {
		return 'seopress';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return 'SEOPress';
	}

	/**
	 * @return string
	 */
	public function get_plugin_file() {
		return 'wp-seopress/seopress.php';
	}

	/**
	 * @return bool
	 */
	public function is_active() {
		return function_exists( 'seopress_get_service' ) || class_exists( 'SEOPress' );
	}

	/**
	 * @return bool
	 */
	public function supports_focus_keyword() {
		return true;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public function read_focus_keywords( $post_id ) {
		return $this->normalize_focus_keyword_raw(
			get_post_meta( (int) $post_id, '_seopress_analysis_target_kw', true )
		);
	}

	/**
	 * @return bool
	 */
	protected function uses_comma_separated_focus_keywords() {
		return true;
	}

	/**
	 * @param string $field_type title|description|both
	 * @return array{title_key?: string, description_key?: string}
	 */
	public function get_meta_keys( $field_type = 'both' ) {
		$keys = array();
		if ( $field_type === 'title' || $field_type === 'both' ) {
			$keys['title_key'] = '_seopress_titles_title';
		}
		if ( $field_type === 'description' || $field_type === 'both' ) {
			$keys['description_key'] = '_seopress_titles_desc';
		}

		return $keys;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array{title: string, description: string}
	 */
	public function read_post_seo_resolved( $post_id ) {
		$raw = $this->read_post_seo( $post_id );
		if ( ! function_exists( 'seopress_get_service' ) ) {
			return $raw;
		}

		try {
			$service = seopress_get_service( 'TitleMeta' );
			if ( is_object( $service ) && method_exists( $service, 'getValue' ) ) {
				$title = (string) $service->getValue( (int) $post_id );
				if ( $title !== '' ) {
					$raw['title'] = sanitize_text_field( $title );
				}
			}
			$desc_service = seopress_get_service( 'DescriptionMeta' );
			if ( is_object( $desc_service ) && method_exists( $desc_service, 'getValue' ) ) {
				$desc = (string) $desc_service->getValue( (int) $post_id );
				if ( $desc !== '' ) {
					$raw['description'] = sanitize_textarea_field( $desc );
				}
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Keep raw.
		}

		return $raw;
	}

	/**
	 * @return string|null
	 */
	public function get_focus_keyword_meta_key() {
		return '_seopress_analysis_target_kw';
	}

	/**
	 * @return array<string, string>
	 */
	public function get_editor_field_selectors() {
		return array(
			'title'         => '#seopress_titles_title',
			'description'   => '#seopress_titles_desc',
			'focus_keyword' => '#seopress_analysis_target_kw',
		);
	}
}
