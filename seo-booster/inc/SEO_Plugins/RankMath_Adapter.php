<?php

namespace Cleverplugins\SEOBooster\SEO_Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rank Math SEO adapter.
 *
 * @package Cleverplugins\SEOBooster\SEO_Plugins
 * @since 7.2.5
 */
class RankMath_Adapter extends Abstract_Post_Meta_Adapter {

	/**
	 * @return string
	 */
	public function get_slug() {
		return 'rankmath';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return 'Rank Math';
	}

	/**
	 * @return string
	 */
	public function get_plugin_file() {
		return 'seo-by-rank-math/rank-math.php';
	}

	/**
	 * @return bool
	 */
	public function is_active() {
		return class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) || function_exists( 'rank_math' );
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
		$raw = null;

		// Prefer Rank Math's helper when available (handles non-string edge cases).
		if ( class_exists( '\RankMath\Helper' ) && is_callable( array( '\RankMath\Helper', 'get_post_meta' ) ) ) {
			$raw = \RankMath\Helper::get_post_meta( 'focus_keyword', (int) $post_id, '' );
		}

		if ( null === $raw || false === $raw || '' === $raw ) {
			$raw = get_post_meta( (int) $post_id, 'rank_math_focus_keyword', true );
		}

		return $this->normalize_focus_keyword_raw( $raw );
	}

	/**
	 * @param int $term_id Term ID.
	 * @return string[]
	 */
	public function read_focus_keywords_for_term( $term_id ) {
		$raw = null;

		if ( class_exists( '\RankMath\Helper' ) && is_callable( array( '\RankMath\Helper', 'get_term_meta' ) ) ) {
			$term = get_term( (int) $term_id );
			if ( $term && ! is_wp_error( $term ) ) {
				$raw = \RankMath\Helper::get_term_meta( 'focus_keyword', $term, $term->taxonomy, '' );
			}
		}

		if ( null === $raw || false === $raw || '' === $raw ) {
			$raw = get_term_meta( (int) $term_id, 'rank_math_focus_keyword', true );
		}

		return $this->normalize_focus_keyword_raw( $raw );
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
		if ( 'title' === $field_type || 'both' === $field_type ) {
			$keys['title_key'] = 'rank_math_title';
		}
		if ( 'description' === $field_type || 'both' === $field_type ) {
			$keys['description_key'] = 'rank_math_description';
		}

		return $keys;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array{title: string, description: string}
	 */
	public function read_post_seo_resolved( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return $this->read_post_seo( $post_id );
		}

		if ( ! class_exists( '\RankMath\Helper' ) || ! is_callable( array( '\RankMath\Helper', 'replace_seo_fields' ) ) ) {
			return $this->read_post_seo( $post_id );
		}

		$title = (string) \RankMath\Helper::replace_seo_fields( '%seo_title%', $post );
		$desc  = (string) \RankMath\Helper::replace_seo_fields( '%seo_description%', $post );

		return array(
			'title'       => sanitize_text_field( $title ),
			'description' => sanitize_textarea_field( $desc ),
		);
	}

	/**
	 * @param int $term_id Term ID.
	 * @return array{title: string, description: string}
	 */
	public function read_term_seo_resolved( $term_id ) {
		$term = get_term( (int) $term_id );
		if ( ! $term || is_wp_error( $term ) || ! ( $term instanceof \WP_Term ) ) {
			return $this->read_term_seo( $term_id );
		}

		$raw = $this->read_term_seo( $term_id );
		if ( ! class_exists( '\RankMath\Helper' ) || ! is_callable( array( '\RankMath\Helper', 'replace_vars' ) ) ) {
			return $raw;
		}

		$title_tpl = $raw['title'];
		$desc_tpl  = $raw['description'];

		if ( '' === $title_tpl && is_callable( array( '\RankMath\Helper', 'get_settings' ) ) ) {
			$title_tpl = (string) \RankMath\Helper::get_settings( 'titles.tax_' . $term->taxonomy . '_title', '' );
		}
		if ( '' === $desc_tpl && is_callable( array( '\RankMath\Helper', 'get_settings' ) ) ) {
			$desc_tpl = (string) \RankMath\Helper::get_settings( 'titles.tax_' . $term->taxonomy . '_description', '' );
		}

		return array(
			'title'       => sanitize_text_field( (string) \RankMath\Helper::replace_vars( $title_tpl, $term ) ),
			'description' => sanitize_textarea_field( (string) \RankMath\Helper::replace_vars( $desc_tpl, $term ) ),
		);
	}

	/**
	 * @return string|null
	 */
	public function get_focus_keyword_meta_key() {
		return 'rank_math_focus_keyword';
	}

	/**
	 * @return array<string, string>
	 */
	public function get_editor_field_selectors() {
		return array(
			'title'         => '#rank_math_title',
			'description'   => '#rank_math_description',
			'focus_keyword' => '#rank_math_focus_keyword',
		);
	}
}
