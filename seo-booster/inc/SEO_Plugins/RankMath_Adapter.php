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
		return class_exists( 'RankMath' );
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
		$focus_keyword = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
		if ( empty( $focus_keyword ) ) {
			return array();
		}

		return $this->parse_focus_keyword_list( (string) $focus_keyword );
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
			$keys['title_key'] = 'rank_math_title';
		}
		if ( $field_type === 'description' || $field_type === 'both' ) {
			$keys['description_key'] = 'rank_math_description';
		}

		return $keys;
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
