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
		if ( 'title' === $field_type || 'both' === $field_type ) {
			$keys['title_key'] = '_seopress_titles_title';
		}
		if ( 'description' === $field_type || 'both' === $field_type ) {
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
				if ( '' !== $title ) {
					$raw['title'] = sanitize_text_field( $title );
				}
			}
			$desc_service = seopress_get_service( 'DescriptionMeta' );
			if ( is_object( $desc_service ) && method_exists( $desc_service, 'getValue' ) ) {
				$desc = (string) $desc_service->getValue( (int) $post_id );
				if ( '' !== $desc ) {
					$raw['description'] = sanitize_textarea_field( $desc );
				}
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Keep raw.
		}

		return $raw;
	}

	/**
	 * Resolve taxonomy title/description templates via SEOPress TitleMeta / DescriptionMeta.
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
		if ( ! function_exists( 'seopress_get_service' ) ) {
			return $raw;
		}

		try {
			$context = $this->build_seopress_term_context( $term );
			if ( empty( $context ) ) {
				return $raw;
			}

			$title_service = seopress_get_service( 'TitleMeta' );
			if ( is_object( $title_service ) && method_exists( $title_service, 'getValue' ) ) {
				$title = (string) $title_service->getValue( $context );
				if ( '' !== $title ) {
					$raw['title'] = sanitize_text_field( $title );
				}
			}

			$desc_service = seopress_get_service( 'DescriptionMeta' );
			if ( is_object( $desc_service ) && method_exists( $desc_service, 'getValue' ) ) {
				$desc = (string) $desc_service->getValue( $context );
				if ( '' !== $desc ) {
					$raw['description'] = sanitize_textarea_field( $desc );
				}
			}

			// Fallback: replace %%tags%% on raw strings when TitleMeta returned empty/raw.
			$tags = seopress_get_service( 'TagsToString' );
			if ( is_object( $tags ) && method_exists( $tags, 'replace' ) ) {
				if ( self::looks_like_seo_template( $raw['title'] ) ) {
					$replaced = (string) $tags->replace( $raw['title'], $context );
					if ( '' !== $replaced && ! self::looks_like_seo_template( $replaced ) ) {
						$raw['title'] = sanitize_text_field( $replaced );
					}
				}
				if ( self::looks_like_seo_template( $raw['description'] ) ) {
					$replaced = (string) $tags->replace( $raw['description'], $context );
					if ( '' !== $replaced && ! self::looks_like_seo_template( $replaced ) ) {
						$raw['description'] = sanitize_textarea_field( $replaced );
					}
				}
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Keep raw.
		}

		return $raw;
	}

	/**
	 * Build SEOPress context array for a taxonomy term.
	 *
	 * @param object $term Term-like object with term_id and taxonomy.
	 * @return array<string, mixed>
	 */
	private function build_seopress_term_context( $term ) {
		$context_service = seopress_get_service( 'ContextPage' );
		if ( is_object( $context_service ) && method_exists( $context_service, 'buildContextWithCurrentId' ) && method_exists( $context_service, 'getContext' ) ) {
			$built = $context_service->buildContextWithCurrentId(
				(int) $term->term_id,
				array(
					'type'     => 'term',
					'taxonomy' => $term->taxonomy,
				)
			);
			if ( is_object( $built ) && method_exists( $built, 'getContext' ) ) {
				$context = $built->getContext();
				if ( is_array( $context ) && ! empty( $context['term_id'] ) ) {
					return $context;
				}
			}
		}

		return array(
			'term_id'     => (int) $term->term_id,
			'term'        => $term,
			'is_category' => ( 'category' === $term->taxonomy ),
			'is_tag'      => ( 'post_tag' === $term->taxonomy ),
			'is_tax'      => ! in_array( $term->taxonomy, array( 'category', 'post_tag' ), true ),
			'is_archive'  => true,
		);
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
