<?php

namespace Cleverplugins\SEOBooster\SEO_Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base adapter for SEO plugins that store title/description in post/term meta.
 *
 * @package Cleverplugins\SEOBooster\SEO_Plugins
 * @since 7.2.5
 */
abstract class Abstract_Post_Meta_Adapter implements SEO_Plugin_Adapter_Interface {

	/**
	 * @return bool
	 */
	public function supports_bulk_write() {
		return true;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array{title: string, description: string}
	 */
	public function read_post_seo( $post_id ) {
		$keys = $this->get_meta_keys( 'both' );

		return array(
			'title'       => sanitize_text_field( (string) get_post_meta( $post_id, $keys['title_key'] ?? '', true ) ),
			'description' => sanitize_textarea_field( (string) get_post_meta( $post_id, $keys['description_key'] ?? '', true ) ),
		);
	}

	/**
	 * Default: same as raw. Adapters override when the SEO plugin can resolve templates.
	 *
	 * @param int $post_id Post ID.
	 * @return array{title: string, description: string}
	 */
	public function read_post_seo_resolved( $post_id ) {
		return $this->read_post_seo( $post_id );
	}

	/**
	 * @param int $term_id Term ID.
	 * @return array{title: string, description: string}
	 */
	public function read_term_seo( $term_id ) {
		$keys = $this->get_term_meta_keys( 'both' );

		return array(
			'title'       => sanitize_text_field( (string) get_term_meta( $term_id, $keys['title_key'] ?? '', true ) ),
			'description' => sanitize_textarea_field( (string) get_term_meta( $term_id, $keys['description_key'] ?? '', true ) ),
		);
	}

	/**
	 * Default: same as raw. Adapters override when the SEO plugin can resolve templates.
	 *
	 * @param int $term_id Term ID.
	 * @return array{title: string, description: string}
	 */
	public function read_term_seo_resolved( $term_id ) {
		return $this->read_term_seo( $term_id );
	}

	/**
	 * Whether a stored SEO string looks like an unresolved template.
	 *
	 * @param string $value Raw value.
	 * @return bool
	 */
	public static function looks_like_seo_template( $value ) {
		$value = (string) $value;
		if ( $value === '' ) {
			return false;
		}

		if ( preg_match( '/%[a-z0-9_-]+%/i', $value ) ) {
			return true;
		}
		if ( preg_match( '/%%[a-z0-9_-]+%%/i', $value ) ) {
			return true;
		}
		if ( preg_match( '/#[a-z0-9_]+/i', $value ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $title   Title.
	 * @return void
	 */
	public function write_post_title( $post_id, $title ) {
		$keys = $this->get_meta_keys( 'title' );
		if ( ! empty( $keys['title_key'] ) ) {
			update_post_meta( $post_id, $keys['title_key'], sanitize_text_field( $title ) );
		}
	}

	/**
	 * @param int    $post_id     Post ID.
	 * @param string $description Description.
	 * @return void
	 */
	public function write_post_description( $post_id, $description ) {
		$keys = $this->get_meta_keys( 'description' );
		if ( ! empty( $keys['description_key'] ) ) {
			update_post_meta( $post_id, $keys['description_key'], sanitize_textarea_field( $description ) );
		}
	}

	/**
	 * @param int    $term_id Term ID.
	 * @param string $title   Title.
	 * @return void
	 */
	public function write_term_title( $term_id, $title ) {
		$keys = $this->get_term_meta_keys( 'title' );
		if ( ! empty( $keys['title_key'] ) ) {
			update_term_meta( $term_id, $keys['title_key'], sanitize_text_field( $title ) );
		}
	}

	/**
	 * @param int    $term_id     Term ID.
	 * @param string $description Description.
	 * @return void
	 */
	public function write_term_description( $term_id, $description ) {
		$keys = $this->get_term_meta_keys( 'description' );
		if ( ! empty( $keys['description_key'] ) ) {
			update_term_meta( $term_id, $keys['description_key'], sanitize_textarea_field( $description ) );
		}
	}

	/**
	 * @param int    $post_id           Post ID.
	 * @param string $keyword           Keyword.
	 * @param bool   $append_if_missing Append for multi-value plugins.
	 * @return void
	 */
	public function write_focus_keyword( $post_id, $keyword, $append_if_missing = false ) {
		$key = $this->get_focus_keyword_meta_key();
		if ( ! $key ) {
			return;
		}

		$value = $this->resolve_focus_keyword_value( (string) get_post_meta( $post_id, $key, true ), $keyword, $append_if_missing );
		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * @param int    $term_id           Term ID.
	 * @param string $keyword           Keyword.
	 * @param bool   $append_if_missing Append for multi-value plugins.
	 * @return void
	 */
	public function write_focus_keyword_for_term( $term_id, $keyword, $append_if_missing = false ) {
		$key = $this->get_focus_keyword_term_meta_key();
		if ( ! $key ) {
			return;
		}

		$value = $this->resolve_focus_keyword_value( (string) get_term_meta( $term_id, $key, true ), $keyword, $append_if_missing );
		update_term_meta( $term_id, $key, $value );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function delete_focus_keyword( $post_id ) {
		$key = $this->get_focus_keyword_meta_key();
		if ( $key ) {
			delete_post_meta( $post_id, $key );
		}
	}

	/**
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function delete_focus_keyword_for_term( $term_id ) {
		$key = $this->get_focus_keyword_term_meta_key();
		if ( $key ) {
			delete_term_meta( $term_id, $key );
		}
	}

	/**
	 * @param int $term_id Term ID.
	 * @return string[]
	 */
	public function read_focus_keywords_for_term( $term_id ) {
		$key = $this->get_focus_keyword_term_meta_key();
		if ( ! $key ) {
			return array();
		}

		return $this->normalize_focus_keyword_raw( get_term_meta( $term_id, $key, true ) );
	}

	/**
	 * @param string $field_type title|description|both
	 * @return array{title_key?: string, description_key?: string}
	 */
	public function get_term_meta_keys( $field_type = 'both' ) {
		return $this->get_meta_keys( $field_type );
	}

	/**
	 * @return string|null
	 */
	public function get_focus_keyword_term_meta_key() {
		return $this->get_focus_keyword_meta_key();
	}

	/**
	 * @param string $field      title|description
	 * @param string $value      Value.
	 * @param int    $exclude_id Exclude post ID.
	 * @return object[]
	 */
	public function find_duplicate_posts( $field, $value, $exclude_id ) {
		global $wpdb;

		$meta_keys = $this->get_meta_keys( $field );
		$meta_key  = $field === 'title' ? ( $meta_keys['title_key'] ?? '' ) : ( $meta_keys['description_key'] ?? '' );

		if ( $meta_key === '' || $value === '' ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, 'post_seo' AS type
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				AND pm.meta_value = %s
				AND p.ID != %d
				AND p.post_status = 'publish'",
				$meta_key,
				$value,
				$exclude_id
			)
		);
	}

	/**
	 * @param string $field      title|description
	 * @param string $value      Value.
	 * @param int    $exclude_id Exclude term ID.
	 * @return object[]
	 */
	public function find_duplicate_terms( $field, $value, $exclude_id ) {
		global $wpdb;

		$meta_keys = $this->get_term_meta_keys( $field );
		$meta_key  = $field === 'title' ? ( $meta_keys['title_key'] ?? '' ) : ( $meta_keys['description_key'] ?? '' );

		if ( $meta_key === '' || $value === '' ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, tt.taxonomy, 'term_seo' AS type
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				INNER JOIN {$wpdb->termmeta} tm ON t.term_id = tm.term_id
				WHERE tm.meta_key = %s
				AND tm.meta_value = %s
				AND t.term_id != %d",
				$meta_key,
				$value,
				$exclude_id
			)
		);
	}

	/**
	 * @param string $existing          Existing stored value.
	 * @param string $keyword           New keyword.
	 * @param bool   $append_if_missing Whether to prepend when missing from list.
	 * @return string
	 */
	protected function resolve_focus_keyword_value( $existing, $keyword, $append_if_missing ) {
		$keyword = sanitize_text_field( $keyword );
		if ( ! $append_if_missing || ! $this->uses_comma_separated_focus_keywords() ) {
			return $keyword;
		}

		return $this->merge_comma_focus_keywords( $existing, $keyword );
	}

	/**
	 * @return bool
	 */
	protected function uses_comma_separated_focus_keywords() {
		return false;
	}

	/**
	 * @param string $existing   Comma-separated keywords.
	 * @param string $new_primary Primary keyword to prepend.
	 * @return string
	 */
	protected function merge_comma_focus_keywords( $existing, $new_primary ) {
		$new_primary = trim( $new_primary );
		$keywords    = $this->parse_focus_keyword_list( $existing );

		if ( $new_primary === '' ) {
			return implode( ', ', $keywords );
		}

		if ( in_array( $new_primary, $keywords, true ) ) {
			return implode( ', ', $keywords );
		}

		array_unshift( $keywords, $new_primary );

		return implode( ', ', array_values( array_unique( $keywords ) ) );
	}

	/**
	 * Normalize raw focus-keyword storage into a string list.
	 *
	 * Handles comma-separated strings, arrays, and JSON tag payloads used by some editors.
	 *
	 * @param mixed $raw Raw focus keyword field.
	 * @return string[]
	 */
	protected function normalize_focus_keyword_raw( $raw ) {
		if ( null === $raw || false === $raw || '' === $raw ) {
			return array();
		}

		if ( is_array( $raw ) ) {
			$keywords = array();
			foreach ( $raw as $entry ) {
				if ( is_string( $entry ) || is_numeric( $entry ) ) {
					$keywords[] = sanitize_text_field( trim( (string) $entry ) );
					continue;
				}
				if ( is_array( $entry ) ) {
					if ( isset( $entry['value'] ) ) {
						$keywords[] = sanitize_text_field( trim( (string) $entry['value'] ) );
					} elseif ( isset( $entry['id'] ) ) {
						$keywords[] = sanitize_text_field( trim( (string) $entry['id'] ) );
					}
				}
			}

			return array_values( array_filter( $keywords ) );
		}

		if ( ! is_string( $raw ) && ! is_numeric( $raw ) ) {
			return array();
		}

		return $this->parse_focus_keyword_list( (string) $raw );
	}

	/**
	 * @param string $raw Raw focus keyword field.
	 * @return string[]
	 */
	protected function parse_focus_keyword_list( $raw ) {
		$raw = trim( (string) $raw );
		if ( $raw === '' ) {
			return array();
		}

		// Rank Math classic editor sometimes posts JSON tag objects before sanitize runs.
		if ( isset( $raw[0] ) && ( $raw[0] === '[' || $raw[0] === '{' ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return $this->normalize_focus_keyword_raw( $decoded );
			}
		}

		$keywords = preg_split( '/\s*,\s*/', $raw );

		return array_values(
			array_filter(
				array_map(
					static function ( $keyword ) {
						return sanitize_text_field( trim( (string) $keyword ) );
					},
					is_array( $keywords ) ? $keywords : array()
				)
			)
		);
	}
}
