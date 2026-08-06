<?php

namespace Cleverplugins\SEOBooster\Analysis\Checks;

use Cleverplugins\SEOBooster\Analysis\Abstract_Checks;
use Cleverplugins\SEOBooster\Analysis\Content_Context;
use Cleverplugins\SEOBooster\Analysis\Html_Document;
use Cleverplugins\SEOBooster\Analysis\Result_Set;
use Cleverplugins\SEOBooster\SEO_Plugin_Registry;
use Cleverplugins\SEOBooster\SEO_Plugins\Abstract_Post_Meta_Adapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Duplicate title and meta description checks.
 *
 * @since 7.1.0
 */
class Duplicate_Checks extends Abstract_Checks {

	/**
	 * @inheritDoc
	 */
	public function get_name() {
		return 'duplicates';
	}

	/**
	 * @inheritDoc
	 */
	public function run( Content_Context $context, Html_Document $document, Result_Set $results ) {
		$this->check_duplicate_titles( $context, $results );
		$this->check_duplicate_meta_descriptions( $context, $results );
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function check_duplicate_titles( Content_Context $context, Result_Set $results ) {
		$raw = $this->get_raw_seo_fields( $context );
		$title = $raw['title'];
		if ( $title === '' || Abstract_Post_Meta_Adapter::looks_like_seo_template( $title ) ) {
			return;
		}

		global $wpdb;
		$all_duplicates = $this->collect_seo_title_duplicates( $context, $title );

		if ( $context->object_type === 'post' ) {
			$duplicate_post_titles = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_title, 'post_title' as type
             FROM {$wpdb->posts}
             WHERE post_title = %s
             AND ID != %d
             AND post_status = 'publish'",
					$title,
					$context->object_id
				)
			);
			foreach ( $duplicate_post_titles as $post ) {
				$all_duplicates[] = $post;
			}
		}

		if ( empty( $all_duplicates ) ) {
			$results->add_good( 'unique_title', __( 'This title is unique across your site.', 'seo-booster' ) );
			return;
		}

		$duplicate_links = $this->format_duplicate_links( $all_duplicates );
		$message         = sprintf( __( 'This SEO title is already used by %d other post(s) or term(s). Consider making it unique.', 'seo-booster' ), count( $all_duplicates ) );
		$message        .= '<br><small>Used by: ' . $duplicate_links . '</small>';

		$results->add_warning( 'duplicate_title', $message );
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function check_duplicate_meta_descriptions( Content_Context $context, Result_Set $results ) {
		$raw         = $this->get_raw_seo_fields( $context );
		$description = $raw['description'];
		if ( $description === '' || Abstract_Post_Meta_Adapter::looks_like_seo_template( $description ) ) {
			return;
		}

		$all_duplicates = $this->collect_seo_description_duplicates( $context, $description );

		if ( empty( $all_duplicates ) ) {
			$results->add_good( 'unique_meta_description', __( 'This meta description is unique across your site.', 'seo-booster' ) );
			return;
		}

		$duplicate_links = $this->format_duplicate_links( $all_duplicates );
		$message         = sprintf( __( 'This meta description is already used by %d other post(s) or term(s). Consider making it unique.', 'seo-booster' ), count( $all_duplicates ) );
		$message        .= '<br><small>Used by: ' . $duplicate_links . '</small>';

		$results->add_warning( 'duplicate_meta_description', $message );
	}

	/**
	 * Raw stored SEO fields for duplicate SQL (not resolved templates).
	 *
	 * @param Content_Context $context Context.
	 * @return array{title: string, description: string}
	 */
	private function get_raw_seo_fields( Content_Context $context ) {
		if ( $context->object_type === 'post' && $context->object_id > 0 ) {
			return SEO_Plugin_Registry::read_post_seo( $context->object_id );
		}
		if ( $context->object_type === 'term' && $context->object_id > 0 ) {
			return SEO_Plugin_Registry::read_term_seo( $context->object_id );
		}

		return array(
			'title'       => '',
			'description' => '',
		);
	}

	/**
	 * @param Content_Context $context Context.
	 * @param string          $title   SEO title.
	 * @return object[]
	 */
	private function collect_seo_title_duplicates( Content_Context $context, $title ) {
		$all_duplicates = array();

		if ( $context->object_type === 'post' ) {
			$duplicate_posts = SEO_Plugin_Registry::find_duplicate_posts( 'title', $title, $context->object_id );
			foreach ( $duplicate_posts as $post ) {
				$all_duplicates[] = $post;
			}
			$duplicate_terms = SEO_Plugin_Registry::find_duplicate_terms( 'title', $title, 0 );
			foreach ( $duplicate_terms as $term ) {
				$all_duplicates[] = $term;
			}
		} else {
			$duplicate_terms = SEO_Plugin_Registry::find_duplicate_terms( 'title', $title, $context->object_id );
			foreach ( $duplicate_terms as $term ) {
				$all_duplicates[] = $term;
			}
			$duplicate_posts = SEO_Plugin_Registry::find_duplicate_posts( 'title', $title, 0 );
			foreach ( $duplicate_posts as $post ) {
				$all_duplicates[] = $post;
			}
		}

		return $all_duplicates;
	}

	/**
	 * @param Content_Context $context     Context.
	 * @param string          $description Meta description.
	 * @return object[]
	 */
	private function collect_seo_description_duplicates( Content_Context $context, $description ) {
		$all_duplicates = array();

		if ( $context->object_type === 'post' ) {
			$duplicate_posts = SEO_Plugin_Registry::find_duplicate_posts( 'description', $description, $context->object_id );
			foreach ( $duplicate_posts as $post ) {
				$all_duplicates[] = $post;
			}
			$duplicate_terms = SEO_Plugin_Registry::find_duplicate_terms( 'description', $description, 0 );
			foreach ( $duplicate_terms as $term ) {
				$all_duplicates[] = $term;
			}
		} else {
			$duplicate_terms = SEO_Plugin_Registry::find_duplicate_terms( 'description', $description, $context->object_id );
			foreach ( $duplicate_terms as $term ) {
				$all_duplicates[] = $term;
			}
			$duplicate_posts = SEO_Plugin_Registry::find_duplicate_posts( 'description', $description, 0 );
			foreach ( $duplicate_posts as $post ) {
				$all_duplicates[] = $post;
			}
		}

		return $all_duplicates;
	}

	/**
	 * @param object[] $duplicates Duplicate rows.
	 * @return string
	 */
	private function format_duplicate_links( array $duplicates ) {
		$duplicate_links = array();
		foreach ( $duplicates as $duplicate ) {
			if ( isset( $duplicate->term_id ) ) {
				$taxonomy  = $duplicate->taxonomy ?? '';
				$edit_link = get_edit_term_link( (int) $duplicate->term_id, $taxonomy );
				$label     = $duplicate->name ?? __( 'Term', 'seo-booster' );
			} else {
				$edit_link = get_edit_post_link( $duplicate->ID );
				$label     = $duplicate->post_title ?? __( 'Post', 'seo-booster' );
			}

			if ( $edit_link ) {
				$duplicate_links[] = '<a href="' . esc_url( $edit_link ) . '" target="_blank">' . esc_html( $label ) . '</a>';
			}
		}

		$limited_links = array_slice( $duplicate_links, 0, 8 );
		$links_text    = implode( ', ', $limited_links );
		if ( count( $duplicate_links ) > 8 ) {
			$links_text .= ' and ' . ( count( $duplicate_links ) - 8 ) . ' more';
		}

		return $links_text;
	}
}
