<?php

namespace Cleverplugins\SEOBooster\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parsed HTML document with scoped XPath queries.
 *
 * @since 7.1.0
 */
class Html_Document {

	public const SCOPE_CONTENT   = 'content';
	public const SCOPE_PAGE_MAIN = 'page_main';
	public const SCOPE_FULL_PAGE = 'full_page';

	/**
	 * @var \DOMDocument|null
	 */
	private $document;

	/**
	 * @var \DOMXPath|null
	 */
	private $xpath;

	/**
	 * @var array<string, string>
	 */
	private $scope_html = array();

	/**
	 * @param string $html Raw HTML.
	 */
	public function __construct( $html ) {
		if ( empty( $html ) ) {
			return;
		}

		$this->document = new \DOMDocument();
		libxml_use_internal_errors( true );
		$this->document->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		$this->xpath = new \DOMXPath( $this->document );

		$this->scope_html[ self::SCOPE_FULL_PAGE ] = $html;
	}

	/**
	 * Register scoped HTML fragments.
	 *
	 * @param string $scope Scope key.
	 * @param string $html HTML fragment.
	 * @return void
	 */
	public function set_scope_html( $scope, $html ) {
		$this->scope_html[ $scope ] = (string) $html;
	}

	/**
	 * Build page_main scope from full page by stripping chrome and noscript.
	 *
	 * Prefer non-empty &lt;main&gt;, then best non-empty &lt;article&gt; (prefer one with H1),
	 * else chrome-stripped &lt;body&gt;. Empty landmarks are never preferred over body text.
	 *
	 * @param string $full_page_html Full page HTML.
	 * @return string
	 */
	public static function build_page_main_html( $full_page_html ) {
		if ( empty( $full_page_html ) ) {
			return '';
		}

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $full_page_html, LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();

		$xpath = new \DOMXPath( $dom );
		self::remove_chrome_nodes( $xpath );

		$mains = self::collect_nonempty_elements( $xpath, '//main' );
		if ( ! empty( $mains ) ) {
			return self::inner_html( $mains[0]['node'] );
		}

		$articles = self::collect_nonempty_elements( $xpath, '//article' );
		if ( ! empty( $articles ) ) {
			usort(
				$articles,
				static function ( $a, $b ) {
					if ( $a['has_h1'] !== $b['has_h1'] ) {
						return $a['has_h1'] ? -1 : 1;
					}
					return $b['length'] <=> $a['length'];
				}
			);
			return self::inner_html( $articles[0]['node'] );
		}

		$body_list = $dom->getElementsByTagName( 'body' );
		if ( $body_list->length > 0 ) {
			$body = $body_list->item( 0 );
			self::remove_widget_regions( $xpath, $body );
			return self::inner_html( $body );
		}

		return self::strip_chrome_regex( $full_page_html );
	}

	/**
	 * UTF-8-aware word count for analysis text.
	 *
	 * @param string $text Plain text.
	 * @return int
	 */
	public static function count_words( $text ) {
		$text = trim( wp_strip_all_tags( (string) $text ) );
		if ( $text === '' ) {
			return 0;
		}

		$count = preg_match_all( '/\p{L}[\p{L}\p{Mn}\p{Pd}\'\x{2019}]*/u', $text );
		return is_int( $count ) ? $count : 0;
	}

	/**
	 * Remove nav/footer/aside/script/style/template/noscript nodes.
	 *
	 * @param \DOMXPath $xpath XPath.
	 * @return void
	 */
	private static function remove_chrome_nodes( \DOMXPath $xpath ) {
		$nodes = $xpath->query( '//nav|//footer|//aside|//script|//style|//template|//noscript' );
		if ( ! $nodes ) {
			return;
		}

		$to_remove = array();
		foreach ( $nodes as $node ) {
			$to_remove[] = $node;
		}
		foreach ( $to_remove as $node ) {
			if ( $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}
	}

	/**
	 * Strip common widget/comment regions under a body subtree.
	 *
	 * @param \DOMXPath  $xpath XPath.
	 * @param \DOMNode   $body Body node.
	 * @return void
	 */
	private static function remove_widget_regions( \DOMXPath $xpath, \DOMNode $body ) {
		$query = './/*[@id="comments" or @id="respond" or @id="sidebar" or @id="secondary"'
			. ' or contains(concat(" ", normalize-space(@class), " "), " widget-area ")'
			. ' or contains(concat(" ", normalize-space(@class), " "), " sidebar ")]';
		$nodes = $xpath->query( $query, $body );
		if ( ! $nodes ) {
			return;
		}

		$to_remove = array();
		foreach ( $nodes as $node ) {
			$to_remove[] = $node;
		}
		foreach ( $to_remove as $node ) {
			if ( $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}
	}

	/**
	 * Collect non-empty elements for an XPath query.
	 *
	 * @param \DOMXPath $xpath XPath.
	 * @param string    $query Query.
	 * @return array<int, array{node: \DOMElement, length: int, has_h1: bool}>
	 */
	private static function collect_nonempty_elements( \DOMXPath $xpath, $query ) {
		$nodes = $xpath->query( $query );
		if ( ! $nodes ) {
			return array();
		}

		$out = array();
		foreach ( $nodes as $node ) {
			if ( ! $node instanceof \DOMElement ) {
				continue;
			}
			$text = trim( preg_replace( '/\s+/', ' ', $node->textContent ?? '' ) );
			if ( $text === '' ) {
				continue;
			}
			$h1 = $xpath->query( './/h1', $node );
			$out[] = array(
				'node'   => $node,
				'length' => strlen( $text ),
				'has_h1' => ( $h1 && $h1->length > 0 ),
			);
		}

		return $out;
	}

	/**
	 * Inner HTML of a DOM element.
	 *
	 * @param \DOMNode $node Node.
	 * @return string
	 */
	private static function inner_html( \DOMNode $node ) {
		if ( ! $node->ownerDocument instanceof \DOMDocument ) {
			return '';
		}

		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $node->ownerDocument->saveHTML( $child );
		}

		return $html;
	}

	/**
	 * Regex fallback when DOM body is missing.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function strip_chrome_regex( $html ) {
		$html = preg_replace( '/<noscript\b[^>]*>[\s\S]*?<\/noscript>/i', '', $html );
		$html = preg_replace( '/<(nav|footer|aside|script|style|template)\b[^>]*>[\s\S]*?<\/\1>/i', '', $html );
		return (string) $html;
	}

	/**
	 * Strip noscript blocks from HTML string.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function strip_noscript( $html ) {
		return (string) preg_replace( '/<noscript\b[^>]*>[\s\S]*?<\/noscript>/i', '', $html );
	}

	/**
	 * Query img nodes in a scope.
	 *
	 * @param string $scope Scope key.
	 * @return \DOMNodeList|\DOMNode[]
	 */
	public function images( $scope = self::SCOPE_CONTENT ) {
		return $this->query_nodes( $scope, './/img' );
	}

	/**
	 * Query anchor nodes in a scope.
	 *
	 * @param string $scope Scope key.
	 * @return \DOMNodeList|\DOMNode[]
	 */
	public function links( $scope = self::SCOPE_CONTENT ) {
		return $this->query_nodes( $scope, './/a[@href]' );
	}

	/**
	 * Query heading nodes in a scope.
	 *
	 * @param string $scope Scope key.
	 * @param string $tag h1|h2|etc.
	 * @return \DOMNodeList|\DOMNode[]
	 */
	public function headings( $scope, $tag ) {
		$tag = preg_replace( '/[^a-z0-9]/i', '', $tag );
		return $this->query_nodes( $scope, './/' . $tag );
	}

	/**
	 * Query paragraph nodes in a scope.
	 *
	 * @param string $scope Scope key.
	 * @return \DOMNodeList|\DOMNode[]
	 */
	public function paragraphs( $scope = self::SCOPE_PAGE_MAIN ) {
		return $this->query_nodes( $scope, './/p' );
	}

	/**
	 * Query input nodes in a scope.
	 *
	 * @param string $scope Scope key.
	 * @return \DOMNodeList|\DOMNode[]
	 */
	public function inputs( $scope = self::SCOPE_FULL_PAGE ) {
		return $this->query_nodes( $scope, './/input' );
	}

	/**
	 * Get plain text for a scope.
	 *
	 * @param string $scope Scope key.
	 * @return string
	 */
	public function text( $scope = self::SCOPE_CONTENT ) {
		$html = $this->get_scope_html( $scope );
		return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) );
	}

	/**
	 * Whether scope HTML contains a pattern.
	 *
	 * @param string $scope Scope key.
	 * @param string $pattern Regex pattern (with delimiters).
	 * @return bool
	 */
	public function scope_matches( $scope, $pattern ) {
		return (bool) preg_match( $pattern, $this->get_scope_html( $scope ) );
	}

	/**
	 * Get scope HTML string.
	 *
	 * @param string $scope Scope key.
	 * @return string
	 */
	public function get_scope_html( $scope ) {
		return $this->scope_html[ $scope ] ?? '';
	}

	/**
	 * Serialize a DOM node to HTML.
	 *
	 * @param \DOMNode $node Node.
	 * @return string
	 */
	public static function node_to_html( \DOMNode $node ) {
		if ( ! $node->ownerDocument instanceof \DOMDocument ) {
			return '';
		}

		return (string) $node->ownerDocument->saveHTML( $node );
	}

	/**
	 * Get attribute from DOM node.
	 *
	 * @param \DOMNode $node Node.
	 * @param string   $name Attribute name.
	 * @return string
	 */
	public static function get_attr( \DOMNode $node, $name ) {
		if ( ! $node instanceof \DOMElement ) {
			return '';
		}

		if ( ! $node->hasAttribute( $name ) ) {
			return '';
		}

		return trim( (string) $node->getAttribute( $name ) );
	}

	/**
	 * Resolve effective image URL from img element (lazy loaders, srcset).
	 *
	 * @param \DOMNode $node Img node.
	 * @return string
	 */
	public static function resolve_image_url( \DOMNode $node ) {
		if ( ! $node instanceof \DOMElement ) {
			return '';
		}

		$candidates = array(
			self::get_attr( $node, 'data-src' ),
			self::get_attr( $node, 'data-lazy-src' ),
			self::get_attr( $node, 'data-orig-src' ),
			self::get_attr( $node, 'data-original' ),
			self::get_attr( $node, 'src' ),
		);

		foreach ( $candidates as $url ) {
			if ( empty( $url ) ) {
				continue;
			}
			if ( strpos( $url, 'data:' ) === 0 ) {
				continue;
			}
			return $url;
		}

		$srcset = self::get_attr( $node, 'data-srcset' );
		if ( empty( $srcset ) ) {
			$srcset = self::get_attr( $node, 'srcset' );
		}

		if ( ! empty( $srcset ) ) {
			$first = trim( explode( ',', $srcset )[0] );
			$parts = preg_split( '/\s+/', $first );
			if ( ! empty( $parts[0] ) && strpos( $parts[0], 'data:' ) !== 0 ) {
				return $parts[0];
			}
		}

		return '';
	}

	/**
	 * Whether an image is a lazy/data placeholder without a real source.
	 *
	 * @param \DOMNode $node Img node.
	 * @return bool
	 */
	public static function is_placeholder_image( \DOMNode $node ) {
		$resolved = self::resolve_image_url( $node );
		if ( ! empty( $resolved ) ) {
			return false;
		}

		$src = self::get_attr( $node, 'src' );
		return strpos( $src, 'data:' ) === 0 || $src === '';
	}

	/**
	 * @param string $scope Scope.
	 * @param string $query XPath query relative to fragment root.
	 * @return \DOMNodeList|\DOMNode[]
	 */
	private function query_nodes( $scope, $query ) {
		$html = $this->get_scope_html( $scope );
		if ( empty( $html ) ) {
			return array();
		}

		$fragment = new \DOMDocument();
		libxml_use_internal_errors( true );
		$fragment->loadHTML( '<?xml encoding="UTF-8"><div id="sb-scope-root">' . $html . '</div>', LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();

		$xpath = new \DOMXPath( $fragment );
		$nodes = $xpath->query( str_replace( './/', '//', $query ) );

		return $nodes ? $nodes : array();
	}
}
