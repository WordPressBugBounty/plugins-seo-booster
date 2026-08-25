<?php

namespace Cleverplugins\SEOBooster\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extract FAQ blocks from post content for llms.txt and Markdown exports.
 *
 * @package Cleverplugins\SEOBooster\Tools
 */
class Llms_Faq_Extractor {

	/**
	 * Collect FAQ items from posts.
	 *
	 * @param \WP_Post[] $posts     Posts to scan.
	 * @param int        $max_items Maximum FAQ pairs.
	 * @return array<int, array{question: string, answer: string, answer_html?: string, source_title: string, source_url: string}>
	 */
	public static function collect_from_posts( array $posts, $max_items = 10 ) {
		$max_items = max( 1, min( 50, (int) $max_items ) );
		$items     = array();

		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$faqs  = self::extract_from_post( $post );
			$url   = get_permalink( $post );
			$title = get_the_title( $post );

			foreach ( $faqs as $faq ) {
				$key = md5( mb_strtolower( $faq['question'] ) );
				if ( isset( $items[ $key ] ) ) {
					continue;
				}

				$items[ $key ] = array(
					'question'     => $faq['question'],
					'answer'       => $faq['answer'],
					'source_title' => $title,
					'source_url'   => is_string( $url ) ? $url : '',
				);

				if ( count( $items ) >= $max_items ) {
					break 2;
				}
			}
		}

		return array_values( $items );
	}

	/**
	 * Extract FAQ pairs from a single post.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<int, array{question: string, answer: string, answer_html: string}>
	 */
	public static function extract_from_post( $post ) {
		if ( ! $post instanceof \WP_Post || ! has_blocks( $post->post_content ) ) {
			return array();
		}

		$blocks = parse_blocks( $post->post_content );
		return self::extract_from_blocks( $blocks );
	}

	/**
	 * Extract FAQ pairs from parsed blocks (recursive).
	 *
	 * @param array $blocks Parsed block list.
	 * @return array<int, array{question: string, answer: string, answer_html: string}>
	 */
	public static function extract_from_blocks( array $blocks ) {
		$out = array();

		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';

			if ( 'yoast/faq-block' === $name && ! empty( $block['attrs']['questions'] ) ) {
				foreach ( (array) $block['attrs']['questions'] as $question ) {
					$answer_html = (string) ( $question['jsonAnswer'] ?? '' );
					$out[]       = array(
						'question'    => self::clean_text( (string) ( $question['jsonQuestion'] ?? '' ) ),
						'answer'      => self::clean_text( $answer_html, true ),
						'answer_html' => $answer_html,
					);
				}
			}

			if ( in_array( $name, array( 'rank-math/faq-block', 'rank-math/faq' ), true ) && ! empty( $block['attrs']['questions'] ) ) {
				foreach ( (array) $block['attrs']['questions'] as $question ) {
					$answer_html = (string) ( $question['content'] ?? '' );
					$out[]       = array(
						'question'    => self::clean_text( (string) ( $question['title'] ?? '' ) ),
						'answer'      => self::clean_text( $answer_html, true ),
						'answer_html' => $answer_html,
					);
				}
			}

			// All in One SEO FAQ block.
			if ( in_array( $name, array( 'aioseo/faq', 'aioseo/faq-block' ), true ) ) {
				$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
				if ( ! empty( $attrs['questions'] ) && is_array( $attrs['questions'] ) ) {
					foreach ( $attrs['questions'] as $question ) {
						$q           = (string) ( $question['question'] ?? $question['title'] ?? '' );
						$answer_html = (string) ( $question['answer'] ?? $question['content'] ?? '' );
						$out[]       = array(
							'question'    => self::clean_text( $q ),
							'answer'      => self::clean_text( $answer_html, true ),
							'answer_html' => $answer_html,
						);
					}
				} elseif ( ! empty( $attrs['question'] ) || ! empty( $attrs['answer'] ) ) {
					$answer_html = (string) ( $attrs['answer'] ?? '' );
					$out[]       = array(
						'question'    => self::clean_text( (string) ( $attrs['question'] ?? '' ) ),
						'answer'      => self::clean_text( $answer_html, true ),
						'answer_html' => $answer_html,
					);
				}
			}

			// SEOPress FAQ block.
			if ( in_array( $name, array( 'seopress/faq-block', 'seopress/faq' ), true ) && ! empty( $block['attrs']['faqs'] ) ) {
				foreach ( (array) $block['attrs']['faqs'] as $question ) {
					$answer_html = (string) ( $question['answer'] ?? $question['content'] ?? '' );
					$out[]       = array(
						'question'    => self::clean_text( (string) ( $question['question'] ?? $question['title'] ?? '' ) ),
						'answer'      => self::clean_text( $answer_html, true ),
						'answer_html' => $answer_html,
					);
				}
			}

			// The SEO Framework FAQ (custom/block variants when present).
			if ( in_array( $name, array( 'tsf/faq', 'the-seo-framework/faq' ), true ) && ! empty( $block['attrs']['questions'] ) ) {
				foreach ( (array) $block['attrs']['questions'] as $question ) {
					$answer_html = (string) ( $question['answer'] ?? $question['content'] ?? '' );
					$out[]       = array(
						'question'    => self::clean_text( (string) ( $question['question'] ?? $question['title'] ?? '' ) ),
						'answer'      => self::clean_text( $answer_html, true ),
						'answer_html' => $answer_html,
					);
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$out = array_merge( $out, self::extract_from_blocks( $block['innerBlocks'] ) );
			}
		}

		$filtered = array();
		foreach ( $out as $item ) {
			if ( '' !== $item['question'] && '' !== $item['answer'] ) {
				$filtered[] = $item;
			}
		}

		return $filtered;
	}

	/**
	 * Strip tags and normalize whitespace.
	 *
	 * @param string $text       Raw text or HTML.
	 * @param bool   $allow_html Keep basic text from HTML.
	 * @return string
	 */
	private static function clean_text( $text, $allow_html = false ) {
		$text = (string) $text;
		unset( $allow_html );
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/', ' ', $text );

		return trim( $text );
	}
}
