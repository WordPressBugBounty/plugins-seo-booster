<?php

namespace Cleverplugins\SEOBooster\Analysis\Checks;

use Cleverplugins\SEOBooster\Analysis\Abstract_Checks;
use Cleverplugins\SEOBooster\Analysis\Content_Context;
use Cleverplugins\SEOBooster\Analysis\Html_Document;
use Cleverplugins\SEOBooster\Analysis\Result_Set;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content length, headings, readability, and on-page text checks.
 *
 * @since 7.1.0
 */
class Content_Checks extends Abstract_Checks {

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
		return 'content';
	}

	/**
	 * @inheritDoc
	 */
	public function run( Content_Context $context, Html_Document $document, Result_Set $results ) {
		$scope = $this->content_scope( $context );

		$this->check_content_length( $context, $document, $results );
		$this->check_featured_image( $context, $results );
		$this->check_faq_heading( $context, $document, $results, $scope );
		$this->check_heading_structure( $context, $document, $results, $scope );
		$this->check_keyword_density( $context, $document, $results, $scope );
		$this->check_thin_content( $document, $results, $scope );
		$this->check_contact_info( $context, $document, $results, $scope );

		if ( $context->has_full_page ) {
			$this->check_readability( $document, $results, $scope, $context->get_locale() );
			$this->check_content_readability( $document, $results, $scope );
			$this->check_form_accessibility( $document, $results );
		}
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Html_Document   $document Document.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function check_content_length( Content_Context $context, Html_Document $document, Result_Set $results ) {
		$scope      = $this->content_scope( $context );
		$content    = $document->text( $scope );
		$word_count = $this->count_words( $content );
		if ( $word_count < 300 ) {
			$results->add_warning(
				'content_too_short',
				sprintf(
					/* translators: %d: detected word count */
					__( 'The content is too short (%d words detected). Aim for at least 300 words.', 'seo-booster' ),
					$word_count
				)
			);
			return;
		}

		if ( $word_count < 600 ) {
			$results->add_opportunity(
				'content_below_600_words',
				sprintf(
					/* translators: %d: detected word count */
					__( 'Content is under 600 words (%d detected). Aim higher for AI answer visibility.', 'seo-booster' ),
					$word_count
				),
				self::AI_READINESS_META
			);
			return;
		}

		if ( $word_count > 3000 ) {
			$results->add_opportunity( 'content_long', __( 'The content is quite long. Consider breaking it into multiple sections.', 'seo-booster' ) );
			return;
		}

		$results->add_good( 'content_length', __( 'The content has a good length.', 'seo-booster' ) );
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function check_featured_image( Content_Context $context, Result_Set $results ) {
		if ( 'post' !== $context->object_type || ! $context->object_id ) {
			return;
		}

		if ( $context->object && 'attachment' === $context->object->post_type ) {
			return;
		}

		if ( ! has_post_thumbnail( $context->object_id ) ) {
			$results->add_opportunity(
				'featured_image_missing',
				__( 'Featured image is missing. Set a featured image for richer previews.', 'seo-booster' ),
				self::AI_READINESS_META
			);
			return;
		}

		$results->add_good(
			'featured_image_set',
			__( 'Featured image is set.', 'seo-booster' ),
			self::AI_READINESS_META
		);
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Html_Document   $document Document.
	 * @param Result_Set      $results Results.
	 * @param string          $scope Scope.
	 * @return void
	 */
	private function check_faq_heading( Content_Context $context, Html_Document $document, Result_Set $results, $scope ) {
		$scope_html = $document->get_scope_html( $scope );
		if ( 'post' === $context->object_type && ! empty( $context->rendered_content ) && ! $context->has_full_page ) {
			$scope_html = $context->rendered_content;
		}

		$has_faq = (bool) preg_match( '/<h[23][^>]*>[^<]*\?\s*<\/h[23]>/i', $scope_html );
		if ( ! $has_faq ) {
			$results->add_opportunity(
				'faq_heading_missing',
				__( 'No question-style heading (FAQ) found. Consider adding H2/H3 headings that end with a question mark.', 'seo-booster' ),
				self::AI_READINESS_META
			);
			return;
		}

		$results->add_good(
			'faq_heading_present',
			__( 'Question-style heading (FAQ) found.', 'seo-booster' ),
			self::AI_READINESS_META
		);
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Html_Document   $document Document.
	 * @param Result_Set      $results Results.
	 * @param string          $scope Scope.
	 * @return void
	 */
	private function check_heading_structure( Content_Context $context, Html_Document $document, Result_Set $results, $scope ) {
		$h1_nodes = $document->headings( $scope, 'h1' );
		$h1_count = 0;
		$h1_texts = array();

		foreach ( $h1_nodes as $node ) {
			$text = trim( wp_strip_all_tags( $node->{'textContent'} ?? '' ) );
			if ( ! empty( $text ) ) {
				++$h1_count;
				$h1_texts[] = $text;
			}
		}

		if ( 0 === $h1_count && 'post' === $context->object_type && $context->object && ! empty( $context->object->post_title ) && ! $context->has_full_page ) {
			$results->add_good(
				'h1_ok',
				sprintf(
					/* translators: %s: H1 heading text */
					__( 'Good H1 heading structure. Found: "%s"', 'seo-booster' ),
					$context->object->post_title
				)
			);
		} elseif ( 0 === $h1_count ) {
			$results->add_warning( 'no_h1', __( 'No H1 heading found. Add an H1 heading to your content.', 'seo-booster' ) );
		} elseif ( $h1_count > 1 ) {
			$results->add_warning( 'multiple_h1', __( 'Multiple H1 headings found. Use only one H1 per page.', 'seo-booster' ) );
		} else {
			$results->add_good(
				'h1_ok',
				sprintf(
					/* translators: %s: H1 heading text */
					__( 'Good H1 heading structure. Found: "%s"', 'seo-booster' ),
					$h1_texts[0]
				)
			);
		}

		$h2_count   = count( $document->headings( $scope, 'h2' ) );
		$word_count = $this->count_words( $document->text( $scope ) );
		if ( 0 === $h2_count && $word_count > 500 ) {
			$results->add_opportunity( 'no_h2', __( 'Consider adding H2 headings to structure your content.', 'seo-booster' ) );
		} elseif ( $h2_count > 0 ) {
			$results->add_good(
				'has_h2_headings',
				sprintf(
					/* translators: %d: number of H2 headings */
					__( 'Found %d H2 heading(s) for good content structure.', 'seo-booster' ),
					$h2_count
				)
			);
		}
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Html_Document   $document Document.
	 * @param Result_Set      $results Results.
	 * @param string          $scope Scope.
	 * @return void
	 */
	private function check_keyword_density( Content_Context $context, Html_Document $document, Result_Set $results, $scope ) {
		$focus_keyword = $context->seo_data['focus_keyword'] ?? '';
		if ( empty( $focus_keyword ) ) {
			return;
		}

		$content_text  = $document->text( $scope );
		$word_count    = $this->count_words( $content_text );
		$keyword_count = $this->count_keyword_occurrences( $content_text, $focus_keyword );

		if ( $word_count <= 0 ) {
			return;
		}

		$density = ( $keyword_count / $word_count ) * 100;
		if ( $density < 0.5 ) {
			$results->add_warning( 'keyword_density_low', __( 'The focus keyword density is too low. Consider using it more often.', 'seo-booster' ) );
		} elseif ( $density > 3 ) {
			$results->add_warning( 'keyword_density_high', __( 'The focus keyword density is too high. Consider reducing it.', 'seo-booster' ) );
		} else {
			$results->add_good( 'keyword_density_ok', __( 'The focus keyword density is good.', 'seo-booster' ) );
		}
	}

	/**
	 * @param Html_Document $document Document.
	 * @param Result_Set    $results Results.
	 * @param string        $scope Scope.
	 * @return void
	 */
	private function check_thin_content( Html_Document $document, Result_Set $results, $scope ) {
		$word_count = $this->count_words( $document->text( $scope ) );
		if ( $word_count < 50 ) {
			$results->add_warning( 'content_too_short_duplicate', __( 'Content is very thin. Expand the page to avoid thin-content signals.', 'seo-booster' ) );
			return;
		}

		$results->add_good( 'content_sufficient', __( 'Content appears to be substantial.', 'seo-booster' ) );
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Html_Document   $document Document.
	 * @param Result_Set      $results Results.
	 * @param string          $scope Scope.
	 * @return void
	 */
	private function check_contact_info( Content_Context $context, Html_Document $document, Result_Set $results, $scope ) {
		if ( 'post' !== $context->object_type || ! $context->object || 'page' !== $context->object->post_type ) {
			return;
		}

		// Contact details often live in footer/site chrome, not post content alone.
		$trust_scope = $context->has_full_page ? Html_Document::SCOPE_FULL_PAGE : $scope;
		$text        = $document->text( $trust_scope );
		$found       = 0;

		if ( preg_match( '/\b[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\b/', $text ) ) {
			++$found;
		}

		if ( preg_match( '/(?:\+?\d{1,3}[\s.-]?)?(?:\(\d{2,4}\)|\d{2,4})[\s.-]?\d{3,4}[\s.-]?\d{3,4}\b/', $text ) ) {
			++$found;
		}

		if ( preg_match( '/\b\d+\s+[A-Za-z\s]+(?:Street|St|Avenue|Ave|Road|Rd|Boulevard|Blvd|Lane|Ln|Drive|Dr|Way|Place|Pl)\b/i', $text ) ) {
			++$found;
		}

		if ( 0 === $found ) {
			$results->add_opportunity( 'no_contact_info', __( 'No contact information found. Consider adding contact details to improve trustworthiness and E-E-A-T.', 'seo-booster' ) );
			return;
		}

		$results->add_good(
			'has_contact_info',
			sprintf(
				/* translators: %d: number of contact information types found */
				__( 'Found contact information (%d type(s)).', 'seo-booster' ),
				$found
			)
		);
	}

	/**
	 * @param Html_Document $document Document.
	 * @param Result_Set    $results Results.
	 * @param string        $scope Scope.
	 * @param string        $locale Locale.
	 * @return void
	 */
	private function check_readability( Html_Document $document, Result_Set $results, $scope, $locale ) {
		if ( 0 !== strpos( $locale, 'en' ) ) {
			return;
		}

		$content_text = $document->text( $scope );
		$sentences    = preg_split( '/[.!?]+/', $content_text );
		$words        = $this->count_words( $content_text );

		if ( count( $sentences ) <= 0 || $words <= 0 ) {
			return;
		}

		$avg_sentence_length = $words / count( $sentences );
		if ( $avg_sentence_length > 20 ) {
			$results->add_opportunity( 'sentence_length', __( 'Consider using shorter sentences for better readability.', 'seo-booster' ) );
			return;
		}

		$results->add_good( 'sentence_length', __( 'Good sentence length for readability.', 'seo-booster' ) );
	}

	/**
	 * @param Html_Document $document Document.
	 * @param Result_Set    $results Results.
	 * @param string        $scope Scope.
	 * @return void
	 */
	private function check_content_readability( Html_Document $document, Result_Set $results, $scope ) {
		$text_content = $document->text( $scope );
		if ( empty( $text_content ) ) {
			return;
		}

		$sentences = array_filter(
			preg_split( '/[.!?]+/', $text_content ),
			static function ( $s ) {
				return trim( $s ) !== '';
			}
		);
		$words     = $this->count_words( $text_content );
		if ( ! empty( $sentences ) ) {
			$avg = $words / count( $sentences );
			if ( $avg <= 15 ) {
				$results->add_good( 'sentence_length_ok', __( 'Good sentence length. Easy to read.', 'seo-booster' ) );
			} elseif ( $avg <= 20 ) {
				$results->add_opportunity( 'sentence_length_moderate', __( 'Consider shortening some sentences for better readability.', 'seo-booster' ) );
			} else {
				$results->add_opportunity( 'sentence_length_long', __( 'Some sentences are quite long. Consider breaking them up for better readability.', 'seo-booster' ) );
			}
		}

		$scope_html           = $document->get_scope_html( $scope );
		$long_paragraphs      = 0;
		$long_paragraphs_list = array();

		foreach ( $document->paragraphs( $scope ) as $node ) {
			if ( $this->is_hidden_node( $node ) ) {
				continue;
			}

			$p_text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $node->{'textContent'} ?? '' ) ) );
			if ( empty( $p_text ) ) {
				continue;
			}

			$word_count = $this->count_words( $p_text );
			if ( $word_count <= 150 ) {
				continue;
			}

			++$long_paragraphs;
			if ( count( $long_paragraphs_list ) < 50 ) {
				$html    = Html_Document::node_to_html( $node );
				$pos     = strpos( $scope_html, $html );
				$line    = false !== $pos ? $this->get_line_number_and_context( $scope_html, $pos ) : array(
					'line'    => 0,
					'context' => '',
				);
				$preview = mb_substr( $p_text, 0, 150 );
				if ( mb_strlen( $p_text ) > 150 ) {
					$preview .= '...';
				}
				$long_paragraphs_list[] = array(
					'preview'    => $preview,
					'word_count' => $word_count,
					'line'       => $line['line'],
					'context'    => $line['context'],
				);
			}
		}

		if ( 0 === $long_paragraphs ) {
			$results->add_good( 'paragraph_length_ok', __( 'Good paragraph length. Easy to read.', 'seo-booster' ) );
			return;
		}

		$results->add_opportunity(
			'long_paragraphs',
			sprintf(
				/* translators: %d: number of long paragraphs */
				__( '%d paragraphs are quite long. Consider breaking them up for better readability.', 'seo-booster' ),
				$long_paragraphs
			),
			array( 'long_paragraphs' => $long_paragraphs_list )
		);
	}

	/**
	 * @param Html_Document $document Document.
	 * @param Result_Set    $results Results.
	 * @return void
	 */
	private function check_form_accessibility( Html_Document $document, Result_Set $results ) {
		$missing = 0;
		foreach ( $document->inputs( Html_Document::SCOPE_PAGE_MAIN ) as $node ) {
			$html = Html_Document::node_to_html( $node );
			if ( ! preg_match( '/type=["\'](?:text|email|password|search|tel|url)["\']/', $html ) ) {
				continue;
			}
			if ( preg_match( '/aria-label=["\'][^"\']*["\']/', $html ) || preg_match( '/id=["\'][^"\']*["\']/', $html ) ) {
				continue;
			}
			++$missing;
		}

		if ( $missing > 0 ) {
			$results->add_opportunity(
				'missing_form_labels',
				sprintf(
					/* translators: %d: number of form inputs that may be missing labels */
					__( '%d form inputs may be missing labels. Consider adding labels or aria-label attributes.', 'seo-booster' ),
					$missing
				)
			);
			return;
		}

		$results->add_good( 'form_accessibility_ok', __( 'Form inputs appear to have proper labels. Good for accessibility.', 'seo-booster' ) );
	}
}
