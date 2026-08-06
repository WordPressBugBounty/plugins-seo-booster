<?php

namespace Cleverplugins\SEOBooster\Analysis;

use Cleverplugins\SEOBooster\CacheManager;
use Cleverplugins\SEOBooster\SEO_Issues_Manager;
use Cleverplugins\SEOBooster\SEO_Plugin_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared analysis context: object, SEO data, rendered and full-page content.
 *
 * @since 7.1.0
 */
class Content_Context {

	/**
	 * @var \WP_Post|\WP_Term|null
	 */
	public $object;

	/**
	 * @var int
	 */
	public $object_id;

	/**
	 * @var string
	 */
	public $object_type;

	/**
	 * @var array<string, mixed>
	 */
	public $seo_data = array();

	/**
	 * Raw post/term content.
	 *
	 * @var string
	 */
	public $raw_content = '';

	/**
	 * Rendered post content via the_content filter.
	 *
	 * @var string
	 */
	public $rendered_content = '';

	/**
	 * Downloaded full page HTML.
	 *
	 * @var string
	 */
	public $full_page_content = '';

	/**
	 * Whether full-page HTML is available.
	 *
	 * @var bool
	 */
	public $has_full_page = false;

	/**
	 * Bulk/sitewide mode: throttle live HTTP and GSC inspection.
	 *
	 * @var bool
	 */
	public $bulk_mode = false;

	/**
	 * GSC check status tracking.
	 *
	 * @var array<string, array<string, string>>
	 */
	public $gsc_checks_status = array();

	/**
	 * Page reachability status: live|redirected|unreachable.
	 *
	 * @var string
	 */
	public $reachability = '';

	/**
	 * Last probed HTTP status code for the page URL.
	 *
	 * @var int
	 */
	public $http_status = 0;

	/**
	 * Redirect Location when reachability is redirected.
	 *
	 * @var string
	 */
	public $redirect_to = '';

	/**
	 * Explicit URL for URL-only analysis (when no local post/term resolves).
	 *
	 * @var string
	 */
	private $explicit_url = '';

	/**
	 * @param int         $object_id Object ID.
	 * @param string      $object_type post|term|url.
	 * @param string|null $url Optional URL override (required for URL-only analysis).
	 */
	public function __construct( $object_id, $object_type = 'post', $url = null ) {
		$this->object_id   = (int) $object_id;
		$this->object_type = $object_type;

		if ( is_string( $url ) && $url !== '' ) {
			$this->explicit_url = $url;
		}

		if ( $object_type === 'post' ) {
			$post = get_post( $object_id );
			if ( $post instanceof \WP_Post ) {
				$this->object           = $post;
				$this->raw_content      = (string) $post->post_content;
				$this->rendered_content = (string) apply_filters( 'the_content', $this->raw_content );
			}
			return;
		}

		if ( $object_type === 'term' && $this->object_id > 0 ) {
			$term = get_term( $this->object_id );
			if ( ! is_wp_error( $term ) && $term instanceof \WP_Term ) {
				$this->object           = $term;
				$this->raw_content      = isset( $term->description ) ? (string) $term->description : '';
				$this->rendered_content = $this->raw_content;
			}
		}
	}

	/**
	 * Set live metabox input values for real-time analysis.
	 *
	 * @param string $title Title.
	 * @param string $description Description.
	 * @param string $focus_keyword Focus keyword.
	 * @return void
	 */
	public function set_current_values( $title, $description, $focus_keyword ) {
		if ( ! empty( $title ) ) {
			$this->seo_data['title'] = $title;
		}
		if ( ! empty( $description ) ) {
			$this->seo_data['description'] = $description;
		}
		if ( ! empty( $focus_keyword ) ) {
			$this->seo_data['focus_keyword'] = $focus_keyword;
		}
	}

	/**
	 * Refresh SEO data from active SEO plugin.
	 *
	 * @return void
	 */
	public function refresh_seo_data() {
		$object_type = $this->object_type;
		$object_id   = $this->object_id;

		// URL-only analysis still needs focus keywords / meta when the URL maps to a post or term.
		if ( $object_type === 'url' || ( $object_id <= 0 && $this->explicit_url !== '' ) ) {
			$resolved = SEO_Issues_Manager::resolve_object_from_url( $this->get_object_url() );
			if ( ! empty( $resolved['object_id'] ) && ! empty( $resolved['object_type'] ) ) {
				$object_id   = (int) $resolved['object_id'];
				$object_type = (string) $resolved['object_type'];
			}
		}

		if ( $object_type === 'post' && $object_id > 0 ) {
			$seo_plugin_data = SEO_Plugin_Registry::read_post_seo_resolved( $object_id );
			$this->seo_data  = array(
				'title'       => $seo_plugin_data['title'] ?? '',
				'description' => $seo_plugin_data['description'] ?? '',
			);

			$plugin_keywords = SEO_Plugin_Registry::read_focus_keywords( $object_id );
			if ( ! empty( $plugin_keywords ) ) {
				$this->seo_data['focus_keyword'] = $plugin_keywords[0];
			}

			$noindex = SEO_Plugin_Registry::read_post_noindex( $object_id );
			if ( null !== $noindex ) {
				$this->seo_data['noindex'] = $noindex ? 1 : 0;
			}

			return;
		}

		if ( $object_type === 'term' && $object_id > 0 ) {
			$seo_plugin_data = SEO_Plugin_Registry::read_term_seo_resolved( $object_id );
			$this->seo_data  = array(
				'title'       => $seo_plugin_data['title'] ?? '',
				'description' => $seo_plugin_data['description'] ?? '',
			);

			$plugin_keywords = SEO_Plugin_Registry::read_focus_keywords_for_term( $object_id );
			if ( ! empty( $plugin_keywords ) ) {
				$this->seo_data['focus_keyword'] = $plugin_keywords[0];
			}

			$noindex = SEO_Plugin_Registry::read_term_noindex( $object_id );
			if ( null !== $noindex ) {
				$this->seo_data['noindex'] = $noindex ? 1 : 0;
			}

			return;
		}

		$this->seo_data = array();
	}

	/**
	 * Get permalink, term link, or explicit URL for the object.
	 *
	 * @return string
	 */
	public function get_object_url() {
		if ( $this->explicit_url !== '' ) {
			return $this->explicit_url;
		}

		if ( $this->object_type === 'post' ) {
			return (string) get_permalink( $this->object_id );
		}

		if ( $this->object_type === 'term' ) {
			$link = get_term_link( $this->object_id );
			return is_wp_error( $link ) ? '' : (string) $link;
		}

		return '';
	}

	/**
	 * Load or download full page content.
	 *
	 * @param bool $force_download Force re-download.
	 * @return void
	 */
	public function prepare_full_page_content( $force_download = false ) {
		if ( $this->object_type !== 'post' && $this->object_type !== 'url' ) {
			return;
		}

		if ( $force_download ) {
			$this->download_full_page_content();
			return;
		}

		if ( $this->load_existing_downloaded_content() ) {
			return;
		}

		$this->download_full_page_content();
	}

	/**
	 * @return bool
	 */
	public function load_existing_downloaded_content() {
		$page_url = $this->get_object_url();
		if ( $page_url === '' ) {
			return false;
		}

		if ( $this->object_type !== 'post' && $this->object_type !== 'url' ) {
			return false;
		}

		$cache_args = array(
			'post_id'             => $this->object_id > 0 ? $this->object_id : null,
			'content_type'        => $this->object_type === 'post' ? 'post' : 'url',
			'item_id'             => $this->object_id > 0 ? $this->object_id : null,
			'require_ok_response' => true,
		);

		$cache_response = CacheManager::fetch_and_cache_url_content( $page_url, $cache_args );

		if ( ! empty( $cache_response['content'] ) && empty( $cache_response['error'] ) ) {
			$this->full_page_content = (string) $cache_response['content'];
			$this->has_full_page     = true;
			return true;
		}

		return false;
	}

	/**
	 * @return void
	 */
	public function download_full_page_content() {
		$page_url = $this->get_object_url();
		if ( $page_url === '' ) {
			return;
		}

		if ( $this->object_type !== 'post' && $this->object_type !== 'url' ) {
			return;
		}

		$cache_args = array(
			'post_id'             => $this->object_id > 0 ? $this->object_id : null,
			'content_type'        => $this->object_type === 'post' ? 'post' : 'url',
			'item_id'             => $this->object_id > 0 ? $this->object_id : null,
			'require_ok_response' => true,
		);

		$cache_response = CacheManager::fetch_and_cache_url_content( $page_url, $cache_args );

		if ( ! empty( $cache_response['content'] ) && empty( $cache_response['error'] ) ) {
			$this->full_page_content = (string) $cache_response['content'];
			$this->has_full_page     = true;
			if ( $this->object_type === 'post' && $this->object_id > 0 ) {
				update_post_meta( $this->object_id, '_sb_last_page_download', time() );
			}
		}
	}

	/**
	 * Record GSC check status for UI/debug.
	 *
	 * @param string $check_name Check name.
	 * @param string $status Status.
	 * @param string $reason Reason.
	 * @return void
	 */
	public function record_gsc_check_status( $check_name, $status, $reason = '' ) {
		$this->gsc_checks_status[ $check_name ] = array(
			'status' => $status,
			'reason' => $reason,
		);
	}

	/**
	 * Site locale for readability heuristics.
	 *
	 * @return string
	 */
	public function get_locale() {
		return (string) get_locale();
	}
}
