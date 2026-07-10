<?php

namespace Cleverplugins\SEOBooster\SEO_Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The SEO Framework v5+ adapter.
 *
 * @package Cleverplugins\SEOBooster\SEO_Plugins
 * @since 7.2.5
 */
class Tsf_Adapter extends Abstract_Post_Meta_Adapter {

	/**
	 * @return string
	 */
	public function get_slug() {
		return 'seoframework';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return 'The SEO Framework';
	}

	/**
	 * @return string
	 */
	public function get_plugin_file() {
		return 'autodescription/autodescription.php';
	}

	/**
	 * @return bool
	 */
	public function is_active() {
		return function_exists( 'tsf' ) || class_exists( 'The_SEO_Framework' );
	}

	/**
	 * @return bool
	 */
	public function supports_focus_keyword() {
		return false;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public function read_focus_keywords( $post_id ) {
		return array();
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $keyword Keyword.
	 * @return void
	 */
	public function write_focus_keyword( $post_id, $keyword, $append_if_missing = false ) {
		// TSF has no native focus keyword field.
	}

	/**
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function delete_focus_keyword( $post_id ) {
		// TSF has no native focus keyword field.
	}

	/**
	 * @param string $field_type title|description|both
	 * @return array{title_key?: string, description_key?: string}
	 */
	public function get_meta_keys( $field_type = 'both' ) {
		$keys = array();
		if ( $field_type === 'title' || $field_type === 'both' ) {
			$keys['title_key'] = '_genesis_title';
		}
		if ( $field_type === 'description' || $field_type === 'both' ) {
			$keys['description_key'] = '_genesis_description';
		}

		return $keys;
	}

	/**
	 * @return string|null
	 */
	public function get_focus_keyword_meta_key() {
		return null;
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $title   Title.
	 * @return void
	 */
	public function write_post_title( $post_id, $title ) {
		if ( $this->tsf_can_use_post_api() ) {
			tsf()->data()->plugin()->post()->update_single_meta_item( '_genesis_title', sanitize_text_field( $title ), $post_id );
			return;
		}

		parent::write_post_title( $post_id, $title );
	}

	/**
	 * @param int    $post_id     Post ID.
	 * @param string $description Description.
	 * @return void
	 */
	public function write_post_description( $post_id, $description ) {
		if ( $this->tsf_can_use_post_api() ) {
			tsf()->data()->plugin()->post()->update_single_meta_item( '_genesis_description', sanitize_textarea_field( $description ), $post_id );
			return;
		}

		parent::write_post_description( $post_id, $description );
	}

	/**
	 * @param int    $term_id Term ID.
	 * @param string $title   Title.
	 * @return void
	 */
	public function write_term_title( $term_id, $title ) {
		if ( $this->tsf_can_use_term_api() ) {
			tsf()->data()->plugin()->term()->update_single_meta_item( '_genesis_title', sanitize_text_field( $title ), $term_id );
			return;
		}

		parent::write_term_title( $term_id, $title );
	}

	/**
	 * @param int    $term_id     Term ID.
	 * @param string $description Description.
	 * @return void
	 */
	public function write_term_description( $term_id, $description ) {
		if ( $this->tsf_can_use_term_api() ) {
			tsf()->data()->plugin()->term()->update_single_meta_item( '_genesis_description', sanitize_textarea_field( $description ), $term_id );
			return;
		}

		parent::write_term_description( $term_id, $description );
	}

	/**
	 * @param int    $term_id           Term ID.
	 * @param string $keyword           Keyword.
	 * @param bool   $append_if_missing Unused.
	 * @return void
	 */
	public function write_focus_keyword_for_term( $term_id, $keyword, $append_if_missing = false ) {
		// TSF has no native focus keyword field.
	}

	/**
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function delete_focus_keyword_for_term( $term_id ) {
		// TSF has no native focus keyword field.
	}

	/**
	 * @return array<string, string>
	 */
	public function get_editor_field_selectors() {
		return array(
			'title'       => '#autodescription-title',
			'description' => '#autodescription-description',
		);
	}

	/**
	 * @return bool
	 */
	private function tsf_can_use_post_api() {
		if ( ! function_exists( 'tsf' ) ) {
			return false;
		}

		$tsf = tsf();
		if ( ! is_object( $tsf ) || ! method_exists( $tsf, 'data' ) ) {
			return false;
		}

		$data = $tsf->data();
		if ( ! is_object( $data ) || ! method_exists( $data, 'plugin' ) ) {
			return false;
		}

		$plugin = $data->plugin();
		if ( ! is_object( $plugin ) || ! method_exists( $plugin, 'post' ) ) {
			return false;
		}

		$post_api = $plugin->post();

		return is_object( $post_api ) && method_exists( $post_api, 'update_single_meta_item' );
	}

	/**
	 * @return bool
	 */
	private function tsf_can_use_term_api() {
		if ( ! function_exists( 'tsf' ) ) {
			return false;
		}

		$tsf = tsf();
		if ( ! is_object( $tsf ) || ! method_exists( $tsf, 'data' ) ) {
			return false;
		}

		$data = $tsf->data();
		if ( ! is_object( $data ) || ! method_exists( $data, 'plugin' ) ) {
			return false;
		}

		$plugin = $data->plugin();
		if ( ! is_object( $plugin ) || ! method_exists( $plugin, 'term' ) ) {
			return false;
		}

		$term_api = $plugin->term();

		return is_object( $term_api ) && method_exists( $term_api, 'update_single_meta_item' );
	}
}
