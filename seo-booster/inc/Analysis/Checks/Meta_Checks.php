<?php

namespace Cleverplugins\SEOBooster\Analysis\Checks;

use Cleverplugins\SEOBooster\Analysis\Abstract_Checks;
use Cleverplugins\SEOBooster\Analysis\Content_Context;
use Cleverplugins\SEOBooster\Analysis\Html_Document;
use Cleverplugins\SEOBooster\Analysis\Result_Set;
use Cleverplugins\SEOBooster\Google_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Title, meta description, focus keyword, and head-tag checks.
 *
 * @since 7.1.0
 */
class Meta_Checks extends Abstract_Checks {

	/**
	 * Extra data tag for AI Readiness–related checks.
	 *
	 * @var array{ai_readiness: bool}
	 */
	private const AI_READINESS_META = array( 'ai_readiness' => true );

	/**
	 * @inheritDoc
	 */
	public function get_name() {
		return 'meta';
	}

	/**
	 * @inheritDoc
	 */
	public function run( Content_Context $context, Html_Document $document, Result_Set $results ) {
		$this->check_title( $context, $document, $results );
		$this->check_meta_description( $context, $document, $results );
		$this->check_focus_keyword( $context, $results );
		$this->check_noindex_status( $context, $results );

		if ( ! $context->has_full_page ) {
			return;
		}

		$this->check_structured_data( $document, $results );
		$this->check_open_graph( $document, $results );
		$this->check_twitter_cards( $document, $results );
		$this->check_canonical_url( $context, $document, $results );
		$this->check_robots_meta( $document, $results );
		$this->check_meta_viewport( $document, $results );
		$this->check_favicon( $document, $results );
		$this->check_language_declaration( $document, $results );
		$this->check_rel_author( $document, $results );
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Html_Document   $document Document.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function check_title( Content_Context $context, Html_Document $document, Result_Set $results ) {
		$focus_keyword = $context->seo_data['focus_keyword'] ?? '';
		$title         = '';
		$full_html     = $document->get_scope_html( Html_Document::SCOPE_FULL_PAGE );

		if ( $context->has_full_page && ! empty( $full_html ) && preg_match( '/<title[^>]*>(.*?)<\/title>/is', $full_html, $m ) ) {
			$title = trim( html_entity_decode( wp_strip_all_tags( $m[1] ) ) );
		}

		if ( empty( $title ) ) {
			$title = $context->seo_data['title'] ?? '';
		}

		if ( empty( $title ) && $context->object_type === 'post' && $context->object ) {
			$title = trim( (string) $context->object->post_title );
		}

		if ( empty( $title ) ) {
			$results->add_error( 'title_missing', __( 'No title has been set.', 'seo-booster' ) );
			return;
		}

		if ( ! empty( $focus_keyword ) && stripos( $title, $focus_keyword ) === false ) {
			$results->add_warning( 'title_no_keyword', sprintf( __( 'The focus keyword "%s" does not appear in the title.', 'seo-booster' ), $focus_keyword ) );
		} elseif ( ! empty( $focus_keyword ) ) {
			$results->add_good( 'title_has_keyword', sprintf( __( 'The focus keyword "%s" appears in the title.', 'seo-booster' ), $focus_keyword ) );
		}
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Html_Document   $document Document.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function check_meta_description( Content_Context $context, Html_Document $document, Result_Set $results ) {
		$focus_keyword = $context->seo_data['focus_keyword'] ?? '';
		$description   = $this->resolve_meta_description( $context, $document );

		if ( empty( $description ) ) {
			$results->add_error( 'description_missing', __( 'No meta description has been set.', 'seo-booster' ) );
			return;
		}

		$results->add_good(
			'meta_description_set',
			__( 'Meta description is set.', 'seo-booster' ),
			self::AI_READINESS_META
		);

		if ( ! empty( $focus_keyword ) && stripos( $description, $focus_keyword ) === false ) {
			$results->add_warning( 'description_no_keyword', sprintf( __( 'The focus keyword "%s" does not appear in the meta description.', 'seo-booster' ), $focus_keyword ) );
		} elseif ( ! empty( $focus_keyword ) ) {
			$results->add_good( 'description_has_keyword', sprintf( __( 'The focus keyword "%s" appears in the meta description.', 'seo-booster' ), $focus_keyword ) );
		}
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function check_focus_keyword( Content_Context $context, Result_Set $results ) {
		$focus_keyword = $context->seo_data['focus_keyword'] ?? '';
		if ( empty( $focus_keyword ) ) {
			$results->add_opportunity( 'keyword_missing', __( 'Consider setting a focus keyword to help with SEO targeting.', 'seo-booster' ) );
			return;
		}

		$results->add_good( 'keyword_set', __( 'A focus keyword has been set.', 'seo-booster' ) );
	}

	/**
	 * Resolve meta description from rendered HTML, SEO plugin meta, or post excerpt fallback.
	 *
	 * @param Content_Context $context Context.
	 * @param Html_Document   $document Document.
	 * @return string
	 */
	private function resolve_meta_description( Content_Context $context, Html_Document $document ) {
		$description = '';
		$full_html   = $document->get_scope_html( Html_Document::SCOPE_FULL_PAGE );

		if ( $context->has_full_page && preg_match( '/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $full_html, $m ) ) {
			$description = trim( html_entity_decode( $m[1] ) );
		}

		if ( empty( $description ) ) {
			$description = trim( (string) ( $context->seo_data['description'] ?? '' ) );
		}

		if ( empty( $description ) && $context->object_type === 'post' && $context->object && ! Google_API::identify_active_seo_plugin() ) {
			$description = trim( (string) $context->object->post_excerpt );
		}

		return $description;
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function check_noindex_status( Content_Context $context, Result_Set $results ) {
		$noindex = $context->seo_data['noindex'] ?? 0;
		if ( $noindex ) {
			$results->add_opportunity( 'noindex_set', __( 'This page is set to noindex. Search engines will not index this page.', 'seo-booster' ) );
			return;
		}

		$results->add_good( 'indexable', __( 'This page is set to be indexed by search engines.', 'seo-booster' ) );
	}

	/**
	 * @param Html_Document $document Document.
	 * @param Result_Set    $results Results.
	 * @return void
	 */
	private function check_structured_data( Html_Document $document, Result_Set $results ) {
		$html            = $document->get_scope_html( Html_Document::SCOPE_FULL_PAGE );
		$json_ld_count   = preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>/i', $html );
		$microdata_count = preg_match_all( '/\bitemscope\b/i', $html );
		$total           = $json_ld_count + $microdata_count;

		if ( 0 === $total ) {
			$results->add_opportunity( 'no_structured_data', __( 'No structured data found. Consider adding JSON-LD markup for rich results.', 'seo-booster' ) );
			return;
		}

		$results->add_good( 'has_structured_data', sprintf( __( 'Found %d structured data element(s).', 'seo-booster' ), $total ) );
	}

	/**
	 * @param Html_Document $document Document.
	 * @param Result_Set    $results Results.
	 * @return void
	 */
	private function check_open_graph( Html_Document $document, Result_Set $results ) {
		$html    = $document->get_scope_html( Html_Document::SCOPE_FULL_PAGE );
		$og_tags = array( 'og:title', 'og:description', 'og:image', 'og:url', 'og:type' );
		$found   = 0;
		$missing = array();

		foreach ( $og_tags as $tag ) {
			if ( preg_match( '/<meta[^>]*property=["\']' . preg_quote( $tag, '/' ) . '["\'][^>]*>/i', $html ) ) {
				++$found;
			} else {
				$missing[] = $tag;
			}
		}

		if ( 0 === $found ) {
			$results->add_opportunity( 'no_open_graph', __( 'No Open Graph meta tags found. Consider adding og:title, og:description, og:image, og:url, and og:type.', 'seo-booster' ) );
			return;
		}

		if ( ! empty( $missing ) ) {
			$results->add_opportunity( 'incomplete_open_graph', sprintf( __( 'Missing Open Graph tags: %s', 'seo-booster' ), implode( ', ', $missing ) ) );
			return;
		}

		$results->add_good( 'complete_open_graph', __( 'All essential Open Graph meta tags are present.', 'seo-booster' ) );
	}

	/**
	 * @param Html_Document $document Document.
	 * @param Result_Set    $results Results.
	 * @return void
	 */
	private function check_twitter_cards( Html_Document $document, Result_Set $results ) {
		$html    = $document->get_scope_html( Html_Document::SCOPE_FULL_PAGE );
		$tags    = array( 'twitter:card', 'twitter:title', 'twitter:description', 'twitter:image' );
		$found   = 0;
		$missing = array();

		foreach ( $tags as $tag ) {
			if ( preg_match( '/<meta[^>]*name=["\']' . preg_quote( $tag, '/' ) . '["\'][^>]*>/i', $html ) ) {
				++$found;
			} else {
				$missing[] = $tag;
			}
		}

		if ( 0 === $found ) {
			$results->add_opportunity( 'no_twitter_cards', __( 'No Twitter Card meta tags found. Consider adding twitter:card, twitter:title, twitter:description, and twitter:image.', 'seo-booster' ) );
			return;
		}

		if ( ! empty( $missing ) ) {
			$results->add_opportunity( 'incomplete_twitter_cards', sprintf( __( 'Missing Twitter Card tags: %s', 'seo-booster' ), implode( ', ', $missing ) ) );
			return;
		}

		$results->add_good( 'complete_twitter_cards', __( 'All essential Twitter Card meta tags are present.', 'seo-booster' ) );
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Html_Document   $document Document.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function check_canonical_url( Content_Context $context, Html_Document $document, Result_Set $results ) {
		$html = $document->get_scope_html( Html_Document::SCOPE_FULL_PAGE );
		if ( preg_match( '/<link[^>]*rel=["\']canonical["\'][^>]*>/i', $html ) ) {
			$results->add_good( 'has_canonical', __( 'Canonical URL is properly set.', 'seo-booster' ) );
			return;
		}

		$results->add_opportunity(
			'no_canonical',
			sprintf( __( 'No canonical URL found. Consider adding a canonical link tag pointing to: %s', 'seo-booster' ), $context->get_object_url() )
		);
	}

	/**
	 * @param Html_Document $document Document.
	 * @param Result_Set    $results Results.
	 * @return void
	 */
	private function check_robots_meta( Html_Document $document, Result_Set $results ) {
		$html = $document->get_scope_html( Html_Document::SCOPE_FULL_PAGE );
		if ( ! preg_match( '/<meta[^>]*name=["\']robots["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $m ) ) {
			$results->add_good( 'robots_default', __( 'No restrictive robots meta tag found (default index,follow applies).', 'seo-booster' ) );
			return;
		}

		$content = strtolower( $m[1] );
		if ( false !== strpos( $content, 'noindex' ) || false !== strpos( $content, 'none' ) ) {
			$results->add_warning( 'robots_noindex', __( 'Robots meta tag restricts indexing. Confirm this is intentional.', 'seo-booster' ) );
			return;
		}

		$results->add_good( 'has_robots_meta', __( 'Robots meta tag allows indexing.', 'seo-booster' ) );
	}

	/**
	 * @param Html_Document $document Document.
	 * @param Result_Set    $results Results.
	 * @return void
	 */
	private function check_meta_viewport( Html_Document $document, Result_Set $results ) {
		if ( $document->scope_matches( Html_Document::SCOPE_FULL_PAGE, '/<meta[^>]*name=["\']viewport["\'][^>]*>/i' ) ) {
			$results->add_good( 'viewport_ok', __( 'Meta viewport tag found. Good for mobile-friendliness.', 'seo-booster' ) );
			return;
		}

		$results->add_opportunity( 'no_viewport', __( 'Consider adding a meta viewport tag for mobile-friendliness.', 'seo-booster' ) );
	}

	/**
	 * @param Html_Document $document Document.
	 * @param Result_Set    $results Results.
	 * @return void
	 */
	private function check_favicon( Html_Document $document, Result_Set $results ) {
		if ( $document->scope_matches( Html_Document::SCOPE_FULL_PAGE, '/<link[^>]*rel=["\'](?:icon|shortcut icon)["\'][^>]*>/i' ) ) {
			$results->add_good( 'favicon_ok', __( 'Favicon found. Good for branding.', 'seo-booster' ) );
			return;
		}

		$results->add_opportunity(
			'no_favicon',
			__( 'Consider adding a favicon for better branding. (This is a sitewide improvement and can be set via the Customizer.)', 'seo-booster' )
		);
	}

	/**
	 * @param Html_Document $document Document.
	 * @param Result_Set    $results Results.
	 * @return void
	 */
	private function check_language_declaration( Html_Document $document, Result_Set $results ) {
		if ( $document->scope_matches( Html_Document::SCOPE_FULL_PAGE, '/<html[^>]*lang=["\'][^"\']+["\']/i' ) ) {
			$results->add_good( 'language_ok', __( 'Language declaration found on the html element.', 'seo-booster' ) );
			return;
		}

		$results->add_opportunity( 'no_language', __( 'Consider adding a lang attribute to the html element.', 'seo-booster' ) );
	}

	/**
	 * @param Html_Document $document Document.
	 * @param Result_Set    $results Results.
	 * @return void
	 */
	private function check_rel_author( Html_Document $document, Result_Set $results ) {
		$count = 0;

		foreach ( $document->links( Html_Document::SCOPE_FULL_PAGE ) as $node ) {
			if ( ! $node instanceof \DOMElement ) {
				continue;
			}

			$rel = Html_Document::get_attr( $node, 'rel' );
			if ( '' === $rel ) {
				continue;
			}

			foreach ( preg_split( '/\s+/', strtolower( $rel ) ) as $token ) {
				if ( 'author' === $token ) {
					++$count;
					break;
				}
			}
		}

		if ( 0 === $count ) {
			$results->add_opportunity( 'no_rel_author', __( 'No rel="author" links found. Consider adding author attribution links to improve E-E-A-T.', 'seo-booster' ) );
			return;
		}

		$results->add_good( 'has_rel_author', sprintf( __( 'Found %d rel="author" link(s).', 'seo-booster' ), $count ) );
	}
}
