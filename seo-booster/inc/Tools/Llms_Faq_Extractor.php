<?php

namespace Cleverplugins\SEOBooster\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extract FAQ blocks from post content for llms.txt export.
 *
 * @package Cleverplugins\SEOBooster\Tools
 */
class Llms_Faq_Extractor {

	/**
	 * Collect FAQ items from posts.
	 *
	 * @param \WP_Post[] $posts     Posts to scan.
	 * @param int        $max_items Maximum FAQ pairs.
	 * @return array<int, array{question: string, answer: string, source_title: string, source_url: string}>
	 */
	public static function collect_from_posts( array $posts, $max_items = 10 ) {
		$max_items = max( 1, min( 50, (int) $max_items ) );
		$items     = array();

		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post || ! has_blocks( $post->post_content ) ) {
				continue;
			}

			$blocks = parse_blocks( $post->post_content );
			$faqs   = self::extract_from_blocks( $blocks );
			$url    = get_permalink( $post );
			$title  = get_the_title( $post );

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
	 * Extract FAQ pairs from parsed blocks (recursive).
	 *
	 * @param array $blocks Parsed block list.
	 * @return array<int, array{question: string, answer: string}>
	 */
	public static function extract_from_blocks( array $blocks ) {
		$out = array();

		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';

			if ( $name === 'yoast/faq-block' && ! empty( $block['attrs']['questions'] ) ) {
				foreach ( (array) $block['attrs']['questions'] as $question ) {
					$out[] = array(
						'question' => self::clean_text( (string) ( $question['jsonQuestion'] ?? '' ) ),
						'answer'   => self::clean_text( (string) ( $question['jsonAnswer'] ?? '' ), true ),
					);
				}
			}

			if ( in_array( $name, array( 'rank-math/faq-block', 'rank-math/faq' ), true ) && ! empty( $block['attrs']['questions'] ) ) {
				foreach ( (array) $block['attrs']['questions'] as $question ) {
					$out[] = array(
						'question' => self::clean_text( (string) ( $question['title'] ?? '' ) ),
						'answer'   => self::clean_text( (string) ( $question['content'] ?? '' ), true ),
					);
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$out = array_merge( $out, self::extract_from_blocks( $block['innerBlocks'] ) );
			}
		}

		$filtered = array();
		foreach ( $out as $item ) {
			if ( $item['question'] !== '' && $item['answer'] !== '' ) {
				$filtered[] = $item;
			}
		}

		return $filtered;
	}

	/**
	 * Strip tags and normalize whitespace.
	 *
	 * @param string $text      Raw text or HTML.
	 * @param bool   $allow_html Keep basic text from HTML.
	 * @return string
	 */
	private static function clean_text( $text, $allow_html = false ) {
		$text = (string) $text;
		if ( $allow_html ) {
			$text = wp_strip_all_tags( $text );
		} else {
			$text = wp_strip_all_tags( $text );
		}
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/', ' ', $text );

		return trim( $text );
	}
}
