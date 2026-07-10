<?php

namespace Cleverplugins\SEOBooster\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared helpers for analysis check groups.
 *
 * @since 7.1.0
 */
abstract class Abstract_Checks implements Check_Interface {

	/**
	 * @inheritDoc
	 */
	abstract public function get_name();

	/**
	 * @inheritDoc
	 */
	abstract public function run( Content_Context $context, Html_Document $document, Result_Set $results );

	/**
	 * Whether a DOM img/link element should be ignored.
	 *
	 * @param \DOMNode $node Node.
	 * @return bool
	 */
	protected function is_hidden_node( \DOMNode $node ) {
		return $this->is_hidden_html( Html_Document::node_to_html( $node ) );
	}

	/**
	 * Whether an HTML snippet represents a hidden element.
	 *
	 * @param string $html HTML snippet.
	 * @return bool
	 */
	protected function is_hidden_html( $html ) {
		if ( preg_match( '/\bhidden\b/i', $html ) ) {
			return true;
		}

		if ( preg_match( '/style\s*=\s*["\'][^"\']*display\s*:\s*none[^"\']*["\']/i', $html ) ) {
			return true;
		}

		if ( preg_match( '/data-wp-bind|data-wp-on/i', $html ) ) {
			return true;
		}

		if ( preg_match( '/aria-hidden\s*=\s*["\']true["\']/i', $html ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get alt attribute from img node (boundary-safe).
	 *
	 * @param \DOMNode $node Img node.
	 * @return array{present: bool, value: string}
	 */
	protected function get_alt_from_node( \DOMNode $node ) {
		if ( ! $node instanceof \DOMElement ) {
			return array(
				'present' => false,
				'value'   => '',
			);
		}

		if ( ! $node->hasAttribute( 'alt' ) ) {
			return array(
				'present' => false,
				'value'   => '',
			);
		}

		return array(
			'present' => true,
			'value'   => trim( (string) $node->getAttribute( 'alt' ) ),
		);
	}

	/**
	 * Build example payload for an image node.
	 *
	 * @param Content_Context $context Context.
	 * @param Html_Document   $document Document.
	 * @param \DOMNode        $node Node.
	 * @param string          $scope_html Scope HTML for line numbers.
	 * @return array<string, mixed>
	 */
	protected function image_example( Content_Context $context, Html_Document $document, \DOMNode $node, $scope_html ) {
		$html = Html_Document::node_to_html( $node );
		$url  = Html_Document::resolve_image_url( $node );
		$pos  = strpos( $scope_html, $html );

		$line_info = array(
			'line'    => 0,
			'context' => '',
		);

		if ( false !== $pos ) {
			$line_info = $this->get_line_number_and_context( $scope_html, $pos );
		}

		return array(
			'html'    => $html,
			'url'     => $url,
			'line'    => $line_info['line'],
			'context' => $line_info['context'],
		);
	}

	/**
	 * @param string $content Content.
	 * @param int    $position Position.
	 * @param int    $context_lines Context lines.
	 * @return array{line: int, context: string}
	 */
	protected function get_line_number_and_context( $content, $position, $context_lines = 2 ) {
		$before      = substr( $content, 0, $position );
		$line_number = substr_count( $before, "\n" ) + 1;
		$lines       = explode( "\n", $content );
		$start_line  = max( 0, $line_number - $context_lines - 1 );
		$end_line    = min( count( $lines ), $line_number + $context_lines );
		$context     = implode( "\n", array_slice( $lines, $start_line, $end_line - $start_line ) );

		if ( strlen( $context ) > 300 ) {
			$context = substr( $context, 0, 297 ) . '...';
		}

		return array(
			'line'    => $line_number,
			'context' => $context,
		);
	}

	/**
	 * @param string $url URL.
	 * @return string|false
	 */
	protected function normalize_image_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		if ( strpos( $url, 'data:' ) === 0 ) {
			return false;
		}

		if ( strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 ) {
			return $url;
		}

		if ( strpos( $url, '//' ) === 0 ) {
			$protocol = is_ssl() ? 'https:' : 'http:';
			return $protocol . $url;
		}

		if ( strpos( $url, '/' ) === 0 ) {
			return home_url( $url );
		}

		return false;
	}

	/**
	 * @param string $href Href value.
	 * @return string|false
	 */
	protected function normalize_link_url( $href ) {
		if ( empty( $href ) ) {
			return false;
		}

		if ( strpos( $href, '#' ) === 0 || strpos( $href, 'mailto:' ) === 0 || strpos( $href, 'tel:' ) === 0 || strpos( $href, 'javascript:' ) === 0 ) {
			return false;
		}

		if ( strpos( $href, 'http://' ) === 0 || strpos( $href, 'https://' ) === 0 ) {
			return $href;
		}

		if ( strpos( $href, '//' ) === 0 ) {
			$protocol = is_ssl() ? 'https:' : 'http:';
			return $protocol . $href;
		}

		if ( strpos( $href, '/' ) === 0 ) {
			return home_url( $href );
		}

		return false;
	}

	/**
	 * @param string $url Absolute URL.
	 * @return bool
	 */
	protected function is_internal_url( $url ) {
		$home      = home_url();
		$home_http = str_replace( 'https://', 'http://', $home );
		$home_https = str_replace( 'http://', 'https://', $home );

		return (
			strpos( $url, $home ) === 0 ||
			strpos( $url, $home_http ) === 0 ||
			strpos( $url, $home_https ) === 0
		);
	}

	/**
	 * @param string $url Image URL.
	 * @return array<string, mixed>
	 */
	public static function check_image_status( $url ) {
		if ( empty( $url ) || strpos( $url, 'http' ) !== 0 ) {
			return array(
				'status'      => 'broken',
				'status_code' => 0,
				'error'       => 'Invalid URL',
			);
		}

		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 3,
				'user-agent'  => 'SEO Booster Image Checker/1.0',
			)
		);

		if ( is_wp_error( $response ) ) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => 8,
					'redirection' => 3,
					'range'       => 'bytes=0-0',
					'user-agent'  => 'SEO Booster Image Checker/1.0',
				)
			);
		}

		if ( is_wp_error( $response ) ) {
			return array(
				'status'      => 'broken',
				'status_code' => 0,
				'error'       => $response->get_error_message(),
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $status_code >= 400 ) {
			return array(
				'status'      => 'broken',
				'status_code' => $status_code,
				'error'       => sprintf( 'HTTP %d error', $status_code ),
			);
		}

		return array(
			'status'      => 'working',
			'status_code' => $status_code,
		);
	}

	/**
	 * @param string $url Link URL.
	 * @return array<string, mixed>
	 */
	public static function check_link_status( $url ) {
		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'user-agent'  => 'SEO Booster Link Checker/1.0',
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 'broken',
				'error'  => $response->get_error_message(),
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$headers     = wp_remote_retrieve_headers( $response );

		if ( in_array( $status_code, array( 301, 302, 303, 307, 308 ), true ) ) {
			return array(
				'status'      => 'redirected',
				'status_code' => $status_code,
				'redirect_to' => $headers['location'] ?? '',
			);
		}

		if ( $status_code >= 400 ) {
			return array(
				'status' => 'broken',
				'error'  => sprintf( 'HTTP %d error', $status_code ),
			);
		}

		return array(
			'status'      => 'working',
			'status_code' => $status_code,
		);
	}

	/**
	 * Resolve cached or live URL status within time budget.
	 *
	 * @param string $url URL.
	 * @param string $kind link|image.
	 * @param array  $queue Background queue collector.
	 * @return array<string, mixed>|null
	 */
	protected function resolve_url_status( $url, $kind, array &$queue ) {
		$cached = Url_Status_Cache::get( $url, $kind );
		if ( $cached ) {
			return array(
				'status'      => $cached['status'],
				'status_code' => (int) $cached['status_code'],
				'redirect_to' => $cached['final_url'],
				'error'       => $cached['error_message'],
			);
		}

		if ( ! Url_Status_Cache::has_time_budget() ) {
			$queue[] = $url;
			return null;
		}

		$status = Url_Status_Cache::KIND_IMAGE === $kind ? self::check_image_status( $url ) : self::check_link_status( $url );
		Url_Status_Cache::set(
			$url,
			$kind,
			array(
				'status'      => $status['status'] ?? 'unknown',
				'status_code' => $status['status_code'] ?? 0,
				'final_url'   => $status['redirect_to'] ?? '',
				'error'       => $status['error'] ?? '',
			)
		);

		return $status;
	}

	/**
	 * @param string $text Text.
	 * @return int
	 */
	protected function count_syllables( $text ) {
		$words     = explode( ' ', strtolower( $text ) );
		$syllables = 0;

		foreach ( $words as $word ) {
			$syllables += max( 1, preg_match_all( '/[aeiouy]+/', $word ) );
		}

		return $syllables;
	}

	/**
	 * @param string $query Query.
	 * @return bool
	 */
	protected function is_question_query( $query ) {
		if ( empty( $query ) ) {
			return false;
		}

		$question_words = array(
			'por qué', 'what', 'how', 'why', 'when', 'where', 'who', 'which', 'whose', 'whom',
			'qué', 'cómo', 'cuándo', 'dónde', 'quién', 'cuál', 'cuáles',
			'hvad', 'hvordan', 'hvorfor', 'hvornår', 'hvor', 'hvem', 'hvilken', 'hvilket',
			'vad', 'hur', 'varför', 'när', 'var', 'vem', 'vilken', 'vilket',
			'was', 'wie', 'warum', 'wann', 'wo', 'wer', 'welcher', 'welche', 'welches',
		);

		$question_words = apply_filters( 'seobooster_question_words', $question_words );
		$query_lower    = mb_strtolower( trim( $query ) );

		foreach ( $question_words as $word ) {
			$word_lower  = mb_strtolower( $word );
			$word_length = mb_strlen( $word_lower );

			if ( mb_substr( $query_lower, 0, $word_length ) === $word_lower ) {
				$next_char = mb_substr( $query_lower, $word_length, 1 );
				if ( $next_char === ' ' || $next_char === '' ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Count keyword occurrences with word boundaries.
	 *
	 * @param string $text Text.
	 * @param string $keyword Keyword.
	 * @return int
	 */
	protected function count_keyword_occurrences( $text, $keyword ) {
		if ( empty( $keyword ) ) {
			return 0;
		}

		$pattern = '/\b' . preg_quote( $keyword, '/' ) . '\b/iu';
		return preg_match_all( $pattern, $text );
	}

	/**
	 * Choose DOM scope for content checks.
	 *
	 * @param Content_Context $context Context.
	 * @return string Html_Document scope constant.
	 */
	protected function content_scope( Content_Context $context ) {
		if ( $context->has_full_page ) {
			return Html_Document::SCOPE_PAGE_MAIN;
		}

		return Html_Document::SCOPE_CONTENT;
	}
}
