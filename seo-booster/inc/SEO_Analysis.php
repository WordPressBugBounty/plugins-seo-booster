<?php

namespace Cleverplugins\SEOBooster;

use Cleverplugins\SEOBooster\Analysis\Check_Registry;
use Cleverplugins\SEOBooster\Analysis\Checks\Content_Checks;
use Cleverplugins\SEOBooster\Analysis\Checks\Duplicate_Checks;
use Cleverplugins\SEOBooster\Analysis\Checks\Gsc_Checks;
use Cleverplugins\SEOBooster\Analysis\Checks\Image_Checks;
use Cleverplugins\SEOBooster\Analysis\Checks\Link_Checks;
use Cleverplugins\SEOBooster\Analysis\Checks\Meta_Checks;
use Cleverplugins\SEOBooster\Analysis\Content_Context;
use Cleverplugins\SEOBooster\Analysis\Gsc_Inspection_Cache;
use Cleverplugins\SEOBooster\Analysis\Html_Document;
use Cleverplugins\SEOBooster\Analysis\Page_Reachability;
use Cleverplugins\SEOBooster\Analysis\Result_Set;
use Cleverplugins\SEOBooster\Analysis\Url_Status_Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates SEO analysis for posts, pages, and taxonomies.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */
class SEO_Analysis {

	/**
	 * @var Content_Context
	 */
	private $context;

	/**
	 * @var Result_Set
	 */
	private $results;

	/**
	 * @var Check_Registry
	 */
	private $registry;

	/**
	 * @var Html_Document|null
	 */
	private $document;

	/**
	 * Initialize the analysis.
	 *
	 * @since 6.1.26
	 * @param int         $object_id Object ID.
	 * @param string      $object_type Object type (post, term, url).
	 * @param string|null $url Optional URL override for URL-only analysis.
	 */
	public function __construct( $object_id, $object_type = 'post', $url = null ) {
		$this->context  = new Content_Context( $object_id, $object_type, $url );
		$this->results  = new Result_Set();
		$this->registry = self::build_registry();
	}

	/**
	 * Set current input values for real-time analysis.
	 *
	 * @since 6.1.26
	 * @param string $title Current title value.
	 * @param string $description Current description value.
	 * @param string $focus_keyword Current focus keyword value.
	 * @return void
	 */
	public function set_current_values( $title, $description, $focus_keyword ) {
		$this->context->set_current_values( $title, $description, $focus_keyword );
	}

	/**
	 * Mark analysis as running in bulk mode (throttles live HTTP/GSC calls).
	 *
	 * @since 7.1.0
	 * @param bool $bulk_mode Bulk mode flag.
	 * @return void
	 */
	public function set_bulk_mode( $bulk_mode = true ) {
		$this->context->bulk_mode = (bool) $bulk_mode;
	}

	/**
	 * Run the complete SEO analysis.
	 *
	 * @since 6.1.26
	 * @param bool $use_full_page Whether to analyze full page content.
	 * @param bool $force_download Force re-download of full page HTML.
	 * @return array Analysis results.
	 */
	public function analyze( $use_full_page = false, $force_download = false ) {
		if ( 'post' === $this->context->object_type && $this->context->object_id && SEO_Issues_Manager::should_exclude_from_analysis( $this->context->object_id ) ) {
			return array(
				'score'        => null,
				'issues'       => array(),
				'improvements' => array(),
				'good'         => array(),
				'excluded'     => true,
			);
		}

		$current_title         = $this->context->seo_data['title'] ?? '';
		$current_description   = $this->context->seo_data['description'] ?? '';
		$current_focus_keyword = $this->context->seo_data['focus_keyword'] ?? '';

		$this->context->refresh_seo_data();
		$this->context->set_current_values( $current_title, $current_description, $current_focus_keyword );

		$this->results = new Result_Set();
		Url_Status_Cache::reset_time_budget( $this->context->bulk_mode ? 4 : 8 );

		$should_gate = $use_full_page || 'url' === $this->context->object_type;

		// Direct files (PDF, images, archives) are not HTML pages: record an empty
		// analysis so they leave the Possibilities queue without fake content audits.
		if ( $should_gate && $this->is_non_html_file_target() ) {
			$this->results->calculate_score();
			$this->save_analysis_results( false );
			return $this->results->to_array();
		}

		if ( $should_gate && $this->apply_reachability_gate() ) {
			$this->results->calculate_score();
			$this->save_analysis_results( false );
			return $this->results->to_array();
		}

		if ( $use_full_page ) {
			$this->context->prepare_full_page_content( $force_download );
		}

		if ( $force_download ) {
			$inspect_url = $this->context->get_object_url();
			if ( '' !== $inspect_url ) {
				Gsc_Inspection_Cache::invalidate( $inspect_url );
			}
		}

		$document = $this->build_document();
		$this->registry->run_all( $this->context, $document, $this->results );

		$this->context->reachability = Page_Reachability::STATUS_LIVE;
		$this->context->http_status  = 200;
		$this->context->redirect_to  = '';

		$this->results->calculate_score();
		$this->save_analysis_results( $this->context->has_full_page );

		return $this->results->to_array();
	}

	/**
	 * Get saved analysis results from the database.
	 *
	 * @since 6.1.26
	 * @param int    $object_id Object ID.
	 * @param string $object_type Object type.
	 * @return array|null
	 */
	public static function get_saved_analysis( $object_id, $object_type = 'post' ) {
		return SEO_Issues_Manager::get_saved_analysis( $object_id, $object_type );
	}

	/**
	 * Build shared check registry.
	 *
	 * @return Check_Registry
	 */
	private static function build_registry() {
		$registry = new Check_Registry();
		$registry->register( new Meta_Checks() );
		$registry->register( new Content_Checks() );
		$registry->register( new Link_Checks() );
		$registry->register( new Image_Checks() );
		$registry->register( new Duplicate_Checks() );
		$registry->register( new Gsc_Checks() );
		return $registry;
	}

	/**
	 * Build Html_Document with content and page scopes.
	 *
	 * @return Html_Document
	 */
	private function build_document() {
		if ( $this->document instanceof Html_Document ) {
			return $this->document;
		}

		$content_html = Html_Document::strip_noscript( $this->context->rendered_content );
		$full_html    = $this->context->has_full_page ? $this->context->full_page_content : '';

		$document = new Html_Document( $full_html ? $full_html : $content_html );
		$document->set_scope_html( Html_Document::SCOPE_CONTENT, $content_html );

		if ( $full_html ) {
			$document->set_scope_html( Html_Document::SCOPE_FULL_PAGE, $full_html );
			$page_main = Html_Document::build_page_main_html( $full_html );
			// One shared scope for content/links/images: never keep an empty page_main
			// when rendered post content is available.
			if ( Html_Document::count_words( $page_main ) < 1 && '' !== $content_html ) {
				$page_main = $content_html;
			}
			$document->set_scope_html( Html_Document::SCOPE_PAGE_MAIN, $page_main );
		} else {
			$document->set_scope_html( Html_Document::SCOPE_PAGE_MAIN, $content_html );
		}

		$this->document = $document;
		return $document;
	}

	/**
	 * Whether the analyzed URL is a direct file (PDF, image, archive) rather than an HTML page.
	 *
	 * @since 7.4.1
	 * @return bool
	 */
	private function is_non_html_file_target() {
		// Attachment posts are already excluded elsewhere; this covers URL-only analysis
		// of raw file paths coming from Search Console history.
		if ( 'post' === $this->context->object_type && $this->context->object_id ) {
			return false;
		}

		return Page_Reachability::is_non_html_file_url( $this->context->get_object_url() );
	}

	/**
	 * Probe the target URL and short-circuit with a single reachability issue when not live.
	 *
	 * @since 7.4.1
	 * @return bool True when analysis should stop (unreachable or redirected).
	 */
	private function apply_reachability_gate() {
		$page_url = $this->context->get_object_url();
		if ( '' === $page_url ) {
			$probe = array(
				'status'      => Page_Reachability::STATUS_UNREACHABLE,
				'status_code' => 0,
				'redirect_to' => '',
				'error'       => __( 'Empty URL', 'seo-booster' ),
			);
		} else {
			$probe = Page_Reachability::probe( $page_url, true );
		}

		$this->context->reachability = $probe['status'];
		$this->context->http_status  = (int) $probe['status_code'];
		$this->context->redirect_to  = (string) $probe['redirect_to'];

		$issue = Page_Reachability::issue_for_probe( $probe );
		if ( null === $issue ) {
			return false;
		}

		$this->results = new Result_Set();
		$this->results->add_error( $issue['key'], $issue['message'], $issue['extra_data'] );
		return true;
	}

	/**
	 * Save analysis results to the database.
	 *
	 * @since 6.1.26
	 * @param bool $is_full_page Whether this was a full page analysis.
	 * @return bool
	 */
	private function save_analysis_results( $is_full_page = false ) {
		$payload = $this->results->to_array();

		$scope_html          = $this->context->has_full_page ? $this->context->full_page_content : $this->context->rendered_content;
		$payload['metadata'] = array(
			'timestamp'      => time(),
			'is_full_page'   => $is_full_page,
			'content_length' => strlen( $scope_html ),
			'url'            => $this->context->get_object_url(),
		);

		if ( ! empty( $this->context->gsc_checks_status ) ) {
			$payload['gsc_checks_status'] = $this->context->gsc_checks_status;
		}

		$url         = $this->context->get_object_url();
		$analysis_id = SEO_Issues_Manager::save_analysis_to_db(
			$this->context->object_id,
			$this->context->object_type,
			$url,
			$payload
		);

		if ( false === $analysis_id ) {
			// Excluded posts return false from the save helper without being a hard failure.
			if ( 'post' === $this->context->object_type && $this->context->object_id && SEO_Issues_Manager::should_exclude_from_analysis( $this->context->object_id ) ) {
				return false;
			}
			throw new \RuntimeException( 'Could not save analysis results' );
		}

		return true;
	}
}
