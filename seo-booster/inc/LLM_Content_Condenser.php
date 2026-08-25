<?php

namespace Cleverplugins\SEOBooster;

use Cleverplugins\SEOBooster\Analysis\Content_Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_Content_Condenser
 *
 * Handles content condensation for LLM SEO suggestions using pure PHP.
 * Converts HTML to text and applies extractive summarization to fit token limits.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */
class LLM_Content_Condenser {

	/** Profile: metabox / full SEO analysis (full rendered page). */
	const PROFILE_FULL = 'full';

	/** Profile: bulk meta tool (editor content only, smaller token budget). */
	const PROFILE_BULK = 'bulk';

	/**
	 * Maximum tokens allowed (4,000) — full profile.
	 */
	const MAX_TOKENS = 4000;

	/**
	 * Target tokens for condensation (3,200 - 80% of max) — full profile.
	 */
	const TARGET_TOKENS = 3200;

	/**
	 * Pass-through threshold (1,000 tokens ~ 750 words) — full profile.
	 */
	const PASSTHROUGH_TOKENS = 1000;

	/** Bulk meta token limits. */
	const BULK_MAX_TOKENS = 800;

	const BULK_TARGET_TOKENS = 640;

	const BULK_PASSTHROUGH_TOKENS = 400;

	/**
	 * Token estimation ratio (chars / 4)
	 */
	const TOKEN_RATIO = 4;

	/**
	 * Condense post content for LLM processing.
	 *
	 * @since 6.1.26
	 * @param int   $post_id Post ID.
	 * @param array $options Optional: profile (full|bulk).
	 * @return string Condensed plain text content.
	 * @throws \Exception If content cannot be retrieved or processed.
	 */
	public function condense_post_content( $post_id, array $options = array() ) {
		$profile = isset( $options['profile'] ) ? (string) $options['profile'] : self::PROFILE_FULL;
		$limits  = $this->get_profile_limits( $profile );

		if ( self::PROFILE_BULK === $profile ) {
			$html_content = $this->get_editor_html_content( $post_id );
		} else {
			$html_content = $this->get_cached_html_content( $post_id );
		}

		if ( empty( $html_content ) ) {
			if ( self::PROFILE_BULK === $profile ) {
				return $this->get_bulk_fallback_text( $post_id );
			}
			throw new \Exception( esc_html__( 'Unable to retrieve content for condensation', 'seo-booster' ) );
		}

		$plain_text = $this->html_to_text( $html_content );

		if ( empty( $plain_text ) ) {
			if ( self::PROFILE_BULK === $profile ) {
				return $this->get_bulk_fallback_text( $post_id );
			}
			throw new \Exception( esc_html__( 'Unable to convert content to text', 'seo-booster' ) );
		}

		if ( self::PROFILE_BULK !== $profile ) {
			$plain_text = $this->strip_boilerplate( $plain_text );
		}

		$estimated_tokens = $this->estimate_tokens( $plain_text );

		if ( $estimated_tokens <= $limits['passthrough'] ) {
			$result = $plain_text;
		} else {
			$result = $this->summarize_text( $plain_text, $estimated_tokens, $limits );
		}

		$result = LLM_Helper::ensure_utf8( $result );

		if ( self::PROFILE_BULK === $profile ) {
			/**
			 * Filters condensed editor content for bulk meta AI prompts.
			 *
			 * @since 7.2.3
			 *
			 * @param string $result  Condensed plain text.
			 * @param int    $post_id Post ID.
			 */
			return LLM_Helper::ensure_utf8( (string) apply_filters( 'seobooster_bulk_meta_condensed_content', $result, $post_id ) );
		}

		return $result;
	}

	/**
	 * Token limits for a condensation profile.
	 *
	 * @param string $profile full|bulk.
	 * @return array{max: int, target: int, passthrough: int}
	 */
	private function get_profile_limits( $profile ) {
		if ( self::PROFILE_BULK === $profile ) {
			return array(
				'max'         => self::BULK_MAX_TOKENS,
				'target'      => self::BULK_TARGET_TOKENS,
				'passthrough' => self::BULK_PASSTHROUGH_TOKENS,
			);
		}

		return array(
			'max'         => self::MAX_TOKENS,
			'target'      => self::TARGET_TOKENS,
			'passthrough' => self::PASSTHROUGH_TOKENS,
		);
	}

	/**
	 * Rendered editor content for bulk meta (no full-page HTTP fetch).
	 *
	 * @param int $post_id Post ID.
	 * @return string HTML fragment or empty string.
	 */
	private function get_editor_html_content( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		return (string) Content_Context::render_post_content( $post->post_content );
	}

	/**
	 * Minimal text when bulk editor content is empty.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_bulk_fallback_text( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$parts = array( trim( $post->post_title ) );
		if ( ! empty( $post->post_excerpt ) ) {
			$parts[] = trim( $post->post_excerpt );
		}

		return LLM_Helper::ensure_utf8( trim( implode( "\n\n", array_filter( $parts ) ) ) );
	}

	/**
	 * Get cached HTML content for a post.
	 *
	 * @since 6.1.26
	 * @param int $post_id Post ID.
	 * @return string HTML content or empty string if not available.
	 */
	private function get_cached_html_content( $post_id ) {
		$post_url = get_permalink( $post_id );
		if ( ! $post_url ) {
			return '';
		}

		// Try to get cached content first
		$cache_response = CacheManager::fetch_and_cache_url_content(
			$post_url,
			array(
				'post_id'      => $post_id,
				'content_type' => 'post',
				'item_id'      => $post_id,
			)
		);

		if ( ! empty( $cache_response['content'] ) ) {
			return $cache_response['content'];
		}

		// Fallback to rendered post content
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		// Apply filters to get rendered content
		$content = Content_Context::render_post_content( $post->post_content );

		// Wrap in basic HTML structure if needed
		if ( false === strpos( $content, '<html' ) ) {
			$content = '<html><body>' . $content . '</body></html>';
		}

		return $content;
	}

	/**
	 * Convert HTML to plain text.
	 *
	 * @since 6.1.26
	 * @param string $html HTML content.
	 * @return string Plain text content.
	 */
	private function html_to_text( $html ) {
		try {
			// Use soundasleep/html2text library
			if ( class_exists( '\Soundasleep\Html2Text' ) ) {
				$text = \Soundasleep\Html2Text::convert(
					$html,
					array(
						'ignore_errors' => true,
						'drop_links'    => false,
						'do_links'      => 'inline',
					)
				);
			} else {
				// Fallback to basic HTML stripping
				$text = wp_strip_all_tags( $html );
			}

			// Clean up excessive whitespace and linebreaks
			$text = $this->clean_text( $text );

			return $text;
		} catch ( \Exception $e ) {
			// Fallback to basic HTML stripping
			$text = wp_strip_all_tags( $html );
			return $this->clean_text( $text );
		}
	}

	/**
	 * Cleans up text by removing excessive whitespace and linebreaks.
	 * Decodes HTML entities and normalizes newlines to spaces so payloads stay compact.
	 *
	 * @param string $text Raw text.
	 * @return string Cleaned text.
	 */
	private function clean_text( $text ) {
		if ( '' === $text ) {
			return '';
		}
		// Decode HTML entities (e.g. &#8211; -> –)
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		// Treat all newlines as space, then collapse runs of spaces to one
		$text = str_replace( array( "\r\n", "\n", "\r" ), ' ', $text );
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		$text = trim( $text );
		return LLM_Helper::ensure_utf8( $text );
	}

	/**
	 * Strip common theme/comment/footer boilerplate so it is not sent to the API.
	 *
	 * @param string $text Plain text (after html_to_text).
	 * @return string Text with boilerplate phrases removed.
	 */
	private function strip_boilerplate( $text ) {
		$phrases = array(
			'Leave a Reply',
			'Cancel reply',
			'Your email address will not be published',
			'Required fields are marked',
			'Comment *',
			'Name *',
			'Email *',
			'Website',
			'Save my name, email, and website in this browser',
			'Designed with WordPress',
			'Your cart',
			'Items: 0',
			'Products in cart',
			'Previous:',
			'Next:',
			'Comments',
			'Privacy Policy',
			'Terms and Conditions',
			'Contact Us',
			'Facebook',
			'Instagram',
			'Twitter/X',
			'Team',
			'History',
			'Careers',
		);
		foreach ( $phrases as $phrase ) {
			$esc  = preg_quote( $phrase, '/' );
			$text = preg_replace( '/\s*' . $esc . '\s*/ui', ' ', $text );
		}
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		return trim( $text );
	}

	/**
	 * Estimate token count from text.
	 *
	 * @since 6.1.26
	 * @param string $text Text content.
	 * @return int Estimated token count.
	 */
	private function estimate_tokens( $text ) {
		return ceil( strlen( $text ) / self::TOKEN_RATIO );
	}

	/**
	 * Summarize text to fit within token limits.
	 *
	 * @since 6.1.26
	 * @param string $text           Original text.
	 * @param int    $current_tokens Current token count.
	 * @param array  $limits         max, target, passthrough token limits.
	 * @return string Summarized text.
	 */
	private function summarize_text( $text, $current_tokens, array $limits ) {
		$target_length = $limits['target'] * self::TOKEN_RATIO;
		$summarized    = $this->extractive_summarize( $text, $target_length );

		$new_tokens = $this->estimate_tokens( $summarized );

		if ( $new_tokens <= $limits['max'] ) {
			return $summarized;
		}

		$max_length = $limits['max'] * self::TOKEN_RATIO;
		$summarized = $this->extractive_summarize( $summarized, $max_length );

		$final_tokens = $this->estimate_tokens( $summarized );

		if ( $final_tokens > $limits['max'] ) {
			$summarized = $this->drop_lowest_ranked_sentences( $summarized, $limits['max'] );
		}

		return $summarized;
	}

	/**
	 * Simple extractive summarization using sentence ranking.
	 *
	 * @since 6.1.26
	 * @param string $text Original text.
	 * @param int $target_length Target character length.
	 * @return string Summarized text.
	 */
	private function extractive_summarize( $text, $target_length ) {
		// Split into sentences
		$sentences = $this->split_into_sentences( $text );

		if ( count( $sentences ) <= 1 ) {
			return $text;
		}

		// Calculate sentence scores (simple word frequency + position)
		$scores = $this->calculate_sentence_scores( $sentences );

		// Sort sentences by score (highest first)
		arsort( $scores );

		// Select sentences until we reach target length
		$selected_sentences = array();
		$current_length     = 0;

		foreach ( $scores as $index => $score ) {
			$sentence        = $sentences[ $index ];
			$sentence_length = strlen( $sentence );

			if ( $current_length + $sentence_length <= $target_length ) {
				$selected_sentences[] = $index;
				$current_length      += $sentence_length;
			}
		}

		// Sort selected sentences by original order
		sort( $selected_sentences );

		// Reconstruct text
		$result = array();
		foreach ( $selected_sentences as $index ) {
			$result[] = $sentences[ $index ];
		}

		return implode( ' ', $result );
	}

	/**
	 * Split text into sentences.
	 *
	 * @since 6.1.26
	 * @param string $text Text to split.
	 * @return array Array of sentences.
	 */
	private function split_into_sentences( $text ) {
		// Simple sentence splitting - can be improved for better accuracy
		$sentences = preg_split( '/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );

		// Filter out very short sentences
		return array_filter(
			$sentences,
			function ( $sentence ) {
				return strlen( trim( $sentence ) ) > 10;
			}
		);
	}

	/**
	 * Calculate sentence scores for ranking.
	 *
	 * @since 6.1.26
	 * @param array $sentences Array of sentences.
	 * @return array Array of scores indexed by sentence position.
	 */
	private function calculate_sentence_scores( $sentences ) {
		$scores           = array();
		$word_frequencies = $this->calculate_word_frequencies( $sentences );

		foreach ( $sentences as $index => $sentence ) {
			$score = 0;

			// Word frequency score
			$words = $this->extract_words( $sentence );
			foreach ( $words as $word ) {
				$score += $word_frequencies[ $word ] ?? 0;
			}

			// Position bonus (first and last sentences get higher scores)
			if ( 0 === $index || ( count( $sentences ) - 1 ) === $index ) {
				$score *= 1.5;
			}

			// Length penalty (very long sentences get lower scores)
			$length = strlen( $sentence );
			if ( $length > 200 ) {
				$score *= 0.8;
			}

			$scores[ $index ] = $score;
		}

		return $scores;
	}

	/**
	 * Calculate word frequencies across all sentences.
	 *
	 * @since 6.1.26
	 * @param array $sentences Array of sentences.
	 * @return array Word frequency array.
	 */
	private function calculate_word_frequencies( $sentences ) {
		$frequencies = array();
		$all_words   = array();

		// Collect all words
		foreach ( $sentences as $sentence ) {
			$words     = $this->extract_words( $sentence );
			$all_words = array_merge( $all_words, $words );
		}

		// Count frequencies
		foreach ( $all_words as $word ) {
			$frequencies[ $word ] = ( $frequencies[ $word ] ?? 0 ) + 1;
		}

		return $frequencies;
	}

	/**
	 * Extract words from a sentence.
	 *
	 * @since 6.1.26
	 * @param string $sentence Sentence text.
	 * @return array Array of words.
	 */
	private function extract_words( $sentence ) {
		$lower = function_exists( 'mb_strtolower' )
			? mb_strtolower( $sentence, 'UTF-8' )
			: strtolower( $sentence );
		$words = preg_split( '/\s+/u', $lower );

		// Filter out short words and common stop words
		$stop_words = array( 'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should' );

		return array_filter(
			$words,
			function ( $word ) use ( $stop_words ) {
				$len = function_exists( 'mb_strlen' ) ? mb_strlen( $word, 'UTF-8' ) : strlen( $word );
				return $len > 2 && ! in_array( $word, $stop_words, true );
			}
		);
	}

	/**
	 * Drop lowest ranked sentences to fit within token limit.
	 *
	 * @since 6.1.26
	 * @param string $text Text to trim.
	 * @return string Trimmed text.
	 */
	private function drop_lowest_ranked_sentences( $text, $max_tokens = self::MAX_TOKENS ) {
		$sentences = $this->split_into_sentences( $text );
		$scores    = $this->calculate_sentence_scores( $sentences );

		// Sort by score (lowest first)
		asort( $scores );

		// Remove lowest scored sentences until we fit
		$max_length     = $max_tokens * self::TOKEN_RATIO;
		$current_length = strlen( $text );

		foreach ( $scores as $index => $score ) {
			if ( $current_length <= $max_length ) {
				break;
			}

			$sentence_length = strlen( $sentences[ $index ] );
			if ( $current_length - $sentence_length >= $max_length * 0.8 ) { // Keep at least 80% of target
				unset( $sentences[ $index ] );
				$current_length -= $sentence_length;
			}
		}

		return implode( ' ', $sentences );
	}
}
