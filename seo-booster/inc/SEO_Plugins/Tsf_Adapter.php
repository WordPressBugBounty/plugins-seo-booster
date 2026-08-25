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
		if ( 'title' === $field_type || 'both' === $field_type ) {
			$keys['title_key'] = '_genesis_title';
		}
		if ( 'description' === $field_type || 'both' === $field_type ) {
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
	 * @param int $post_id Post ID.
	 * @return array{title: string, description: string}
	 */
	public function read_post_seo_resolved( $post_id ) {
		$raw = $this->read_post_seo( $post_id );
		if ( ! function_exists( 'tsf' ) ) {
			return $raw;
		}

		try {
			$tsf = tsf();
			if ( is_object( $tsf ) && method_exists( $tsf, 'get_title' ) && method_exists( $tsf, 'get_description' ) ) {
				$title = (string) $tsf->get_title( array(), (int) $post_id );
				$desc  = (string) $tsf->get_description( array(), (int) $post_id );
				if ( '' !== $title || '' !== $desc ) {
					return array(
						'title'       => sanitize_text_field( '' !== $title ? $title : $raw['title'] ),
						'description' => sanitize_textarea_field( '' !== $desc ? $desc : $raw['description'] ),
					);
				}
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Keep raw.
		}

		return $raw;
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
	 * @param string $field_type title|description|both
	 * @return array{title_key?: string, description_key?: string}
	 */
	public function get_term_meta_keys( $field_type = 'both' ) {
		// TSF stores term SEO in autodescription-term-settings (not flat termmeta keys).
		return array();
	}

	/**
	 * @param int $term_id Term ID.
	 * @return array{title: string, description: string}
	 */
	public function read_term_seo( $term_id ) {
		$term_id = (int) $term_id;
		if ( $this->tsf_can_use_term_api() ) {
			$term_api = tsf()->data()->plugin()->term();
			$title    = '';
			$desc     = '';
			if ( method_exists( $term_api, 'get_meta_item' ) ) {
				$title = (string) ( $term_api::get_meta_item( 'doctitle', $term_id ) ?? '' );
				$desc  = (string) ( $term_api::get_meta_item( 'description', $term_id ) ?? '' );
			}

			return array(
				'title'       => sanitize_text_field( $title ),
				'description' => sanitize_textarea_field( $desc ),
			);
		}

		$settings_key = defined( 'THE_SEO_FRAMEWORK_TERM_OPTIONS' ) ? THE_SEO_FRAMEWORK_TERM_OPTIONS : 'autodescription-term-settings';
		$settings     = get_term_meta( $term_id, $settings_key, true );
		if ( is_array( $settings ) ) {
			return array(
				'title'       => sanitize_text_field( (string) ( $settings['doctitle'] ?? '' ) ),
				'description' => sanitize_textarea_field( (string) ( $settings['description'] ?? '' ) ),
			);
		}

		// Legacy Genesis-style flat termmeta (older installs / unit tests).
		return array(
			'title'       => sanitize_text_field( (string) get_term_meta( $term_id, '_genesis_title', true ) ),
			'description' => sanitize_textarea_field( (string) get_term_meta( $term_id, '_genesis_description', true ) ),
		);
	}

	/**
	 * Resolve term title/description via The SEO Framework Meta API (includes generated fallbacks).
	 *
	 * @param int $term_id Term ID.
	 * @return array{title: string, description: string}
	 */
	public function read_term_seo_resolved( $term_id ) {
		$raw  = $this->read_term_seo( $term_id );
		$term = get_term( (int) $term_id );
		if ( ! $term || is_wp_error( $term ) || ! isset( $term->term_id, $term->taxonomy ) ) {
			return $raw;
		}

		$args = array(
			'id'  => (int) $term->term_id,
			'tax' => $term->taxonomy,
		);

		try {
			$title = '';
			$desc  = '';

			if ( class_exists( '\The_SEO_Framework\Meta\Title' ) && is_callable( array( '\The_SEO_Framework\Meta\Title', 'get_title' ) ) ) {
				$title = (string) \The_SEO_Framework\Meta\Title::get_title( $args );
			}
			if ( class_exists( '\The_SEO_Framework\Meta\Description' ) && is_callable( array( '\The_SEO_Framework\Meta\Description', 'get_description' ) ) ) {
				$desc = (string) \The_SEO_Framework\Meta\Description::get_description( $args );
			}

			// Legacy facade fallback (older TSF / when Meta classes unavailable).
			if ( ( '' === $title || '' === $desc ) && function_exists( 'tsf' ) ) {
				$tsf = tsf();
				if ( is_object( $tsf ) ) {
					if ( '' === $title && method_exists( $tsf, 'get_title' ) ) {
						$title = (string) $tsf->get_title( $args );
					}
					if ( '' === $desc && method_exists( $tsf, 'get_description' ) ) {
						$desc = (string) $tsf->get_description( $args );
					}
				}
			}

			if ( '' !== $title || '' !== $desc ) {
				return array(
					'title'       => sanitize_text_field( '' !== $title ? $title : $raw['title'] ),
					'description' => sanitize_textarea_field( '' !== $desc ? $desc : $raw['description'] ),
				);
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Keep raw.
		}

		return $raw;
	}

	/**
	 * @param int    $term_id Term ID.
	 * @param string $title   Title.
	 * @return void
	 */
	public function write_term_title( $term_id, $title ) {
		if ( $this->tsf_can_use_term_api() ) {
			tsf()->data()->plugin()->term()->update_single_meta_item( 'doctitle', sanitize_text_field( $title ), (int) $term_id );
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
			tsf()->data()->plugin()->term()->update_single_meta_item( 'description', sanitize_textarea_field( $description ), (int) $term_id );
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
