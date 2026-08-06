<?php

namespace Cleverplugins\SEOBooster\SEO_Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Yoast SEO adapter.
 *
 * @package Cleverplugins\SEOBooster\SEO_Plugins
 * @since 7.2.5
 */
class Yoast_Adapter extends Abstract_Post_Meta_Adapter {

	/**
	 * @return string
	 */
	public function get_slug() {
		return 'yoast';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return 'Yoast SEO';
	}

	/**
	 * @return string
	 */
	public function get_plugin_file() {
		return 'wordpress-seo/wp-seo.php';
	}

	/**
	 * @return bool
	 */
	public function is_active() {
		return class_exists( 'WPSEO_Meta' ) || function_exists( 'wpseo_init' );
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
		$keywords = $this->normalize_focus_keyword_raw(
			get_post_meta( (int) $post_id, '_yoast_wpseo_focuskw', true )
		);

		// Yoast Premium stores additional keyphrases separately; primary stays first.
		$extra = get_post_meta( (int) $post_id, '_yoast_wpseo_focuskeywords', true );
		if ( is_string( $extra ) && $extra !== '' ) {
			$decoded = json_decode( $extra, true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $entry ) {
					if ( ! is_array( $entry ) || empty( $entry['keyword'] ) ) {
						continue;
					}
					$keyword = sanitize_text_field( (string) $entry['keyword'] );
					if ( $keyword !== '' && ! in_array( $keyword, $keywords, true ) ) {
						$keywords[] = $keyword;
					}
				}
			}
		}

		return $keywords;
	}

	/**
	 * @param int $term_id Term ID.
	 * @return array{title: string, description: string}
	 */
	public function read_term_seo( $term_id ) {
		if ( ! $this->yoast_taxonomy_meta_available() ) {
			return array(
				'title'       => '',
				'description' => '',
			);
		}

		$taxonomy = $this->get_term_taxonomy( $term_id );
		if ( ! $taxonomy ) {
			return array(
				'title'       => '',
				'description' => '',
			);
		}

		return array(
			'title'       => sanitize_text_field( (string) \WPSEO_Taxonomy_Meta::get_term_meta( $term_id, $taxonomy, 'title' ) ),
			'description' => sanitize_textarea_field( (string) \WPSEO_Taxonomy_Meta::get_term_meta( $term_id, $taxonomy, 'desc' ) ),
		);
	}

	/**
	 * @param int    $term_id Term ID.
	 * @param string $title   Title.
	 * @return void
	 */
	public function write_term_title( $term_id, $title ) {
		$this->write_yoast_term_field( $term_id, 'wpseo_title', sanitize_text_field( $title ) );
	}

	/**
	 * @param int    $term_id     Term ID.
	 * @param string $description Description.
	 * @return void
	 */
	public function write_term_description( $term_id, $description ) {
		$this->write_yoast_term_field( $term_id, 'wpseo_desc', sanitize_textarea_field( $description ) );
	}

	/**
	 * @param int $term_id Term ID.
	 * @return string[]
	 */
	public function read_focus_keywords_for_term( $term_id ) {
		if ( ! $this->yoast_taxonomy_meta_available() ) {
			return array();
		}

		$taxonomy = $this->get_term_taxonomy( $term_id );
		if ( ! $taxonomy ) {
			return array();
		}

		$focus = \WPSEO_Taxonomy_Meta::get_term_meta( $term_id, $taxonomy, 'focuskw' );
		if ( empty( $focus ) ) {
			return array();
		}

		return array( sanitize_text_field( (string) $focus ) );
	}

	/**
	 * @param int    $term_id           Term ID.
	 * @param string $keyword           Keyword.
	 * @param bool   $append_if_missing Unused for Yoast (single keyword).
	 * @return void
	 */
	public function write_focus_keyword_for_term( $term_id, $keyword, $append_if_missing = false ) {
		$this->write_yoast_term_field( $term_id, 'wpseo_focuskw', sanitize_text_field( $keyword ) );
	}

	/**
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function delete_focus_keyword_for_term( $term_id ) {
		$this->write_yoast_term_field( $term_id, 'wpseo_focuskw', '' );
	}

	/**
	 * Yoast stores taxonomy SEO in wp_options, not termmeta.
	 *
	 * @param string $field_type title|description|both
	 * @return array{}
	 */
	public function get_term_meta_keys( $field_type = 'both' ) {
		return array();
	}

	/**
	 * @param string $field      title|description
	 * @param string $value      Value.
	 * @param int    $exclude_id Exclude term ID.
	 * @return object[]
	 */
	public function find_duplicate_terms( $field, $value, $exclude_id ) {
		if ( $value === '' || ! in_array( $field, array( 'title', 'description' ), true ) ) {
			return array();
		}

		$key      = $field === 'title' ? 'wpseo_title' : 'wpseo_desc';
		$all_meta = get_option( 'wpseo_taxonomy_meta', array() );
		if ( ! is_array( $all_meta ) || empty( $all_meta ) ) {
			return array();
		}

		$duplicates = array();
		foreach ( $all_meta as $taxonomy => $terms ) {
			if ( ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term_id => $meta ) {
				$term_id = (int) $term_id;
				if ( $term_id === (int) $exclude_id || ! is_array( $meta ) ) {
					continue;
				}
				if ( ( $meta[ $key ] ?? '' ) !== $value ) {
					continue;
				}
				$term = get_term( $term_id, $taxonomy );
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}
				$duplicates[] = (object) array(
					'term_id'  => $term_id,
					'name'     => $term->name,
					'taxonomy' => $taxonomy,
					'type'     => 'term_seo',
				);
			}
		}

		return $duplicates;
	}

	/**
	 * @param string $field_type title|description|both
	 * @return array{title_key?: string, description_key?: string}
	 */
	public function get_meta_keys( $field_type = 'both' ) {
		$keys = array();
		if ( $field_type === 'title' || $field_type === 'both' ) {
			$keys['title_key'] = '_yoast_wpseo_title';
		}
		if ( $field_type === 'description' || $field_type === 'both' ) {
			$keys['description_key'] = '_yoast_wpseo_metadesc';
		}

		return $keys;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array{title: string, description: string}
	 */
	public function read_post_seo_resolved( $post_id ) {
		$post_id = (int) $post_id;
		if ( function_exists( 'YoastSEO' ) ) {
			try {
				$meta = YoastSEO()->meta->for_post( $post_id );
				if ( is_object( $meta ) ) {
					$title = isset( $meta->title ) ? (string) $meta->title : '';
					$desc  = isset( $meta->description ) ? (string) $meta->description : '';
					if ( $title !== '' || $desc !== '' ) {
						return array(
							'title'       => sanitize_text_field( $title ),
							'description' => sanitize_textarea_field( $desc ),
						);
					}
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Fall through to raw.
			}
		}

		return $this->read_post_seo( $post_id );
	}

	/**
	 * @param int $term_id Term ID.
	 * @return array{title: string, description: string}
	 */
	public function read_term_seo_resolved( $term_id ) {
		$term_id = (int) $term_id;
		if ( function_exists( 'YoastSEO' ) ) {
			try {
				$meta = YoastSEO()->meta->for_term( $term_id );
				if ( is_object( $meta ) ) {
					$title = isset( $meta->title ) ? (string) $meta->title : '';
					$desc  = isset( $meta->description ) ? (string) $meta->description : '';
					if ( $title !== '' || $desc !== '' ) {
						return array(
							'title'       => sanitize_text_field( $title ),
							'description' => sanitize_textarea_field( $desc ),
						);
					}
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Fall through to raw.
			}
		}

		return $this->read_term_seo( $term_id );
	}

	/**
	 * @return string|null
	 */
	public function get_focus_keyword_meta_key() {
		return '_yoast_wpseo_focuskw';
	}

	/**
	 * @return array<string, string>
	 */
	public function get_editor_field_selectors() {
		return array(
			'title'         => '#yoast_wpseo_title',
			'description'   => '#yoast_wpseo_metadesc',
			'focus_keyword' => '#yoast_wpseo_focuskw',
		);
	}

	/**
	 * @param int    $term_id Term ID.
	 * @param string $key     Yoast internal key.
	 * @param string $value   Value.
	 * @return void
	 */
	private function write_yoast_term_field( $term_id, $key, $value ) {
		if ( ! $this->yoast_taxonomy_meta_available() ) {
			return;
		}

		$taxonomy = $this->get_term_taxonomy( $term_id );
		if ( ! $taxonomy ) {
			return;
		}

		$existing = \WPSEO_Taxonomy_Meta::get_term_meta( $term_id, $taxonomy );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$existing[ $key ] = $value;
		\WPSEO_Taxonomy_Meta::set_values( $term_id, $taxonomy, $existing );
	}

	/**
	 * @param int $term_id Term ID.
	 * @return string
	 */
	private function get_term_taxonomy( $term_id ) {
		$term = get_term( (int) $term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}

		return (string) $term->taxonomy;
	}

	/**
	 * @return bool
	 */
	private function yoast_taxonomy_meta_available() {
		return class_exists( 'WPSEO_Taxonomy_Meta' );
	}
}
