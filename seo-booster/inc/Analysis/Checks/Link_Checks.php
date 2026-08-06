<?php

namespace Cleverplugins\SEOBooster\Analysis\Checks;

use Cleverplugins\SEOBooster\Analysis\Abstract_Checks;
use Cleverplugins\SEOBooster\Analysis\Content_Context;
use Cleverplugins\SEOBooster\Analysis\Html_Document;
use Cleverplugins\SEOBooster\Analysis\Result_Set;
use Cleverplugins\SEOBooster\Analysis\Url_Status_Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Internal/external link counting and validation.
 *
 * @since 7.1.0
 */
class Link_Checks extends Abstract_Checks {

	/**
	 * @inheritDoc
	 */
	public function get_name() {
		return 'links';
	}

	/**
	 * @inheritDoc
	 */
	public function run( Content_Context $context, Html_Document $document, Result_Set $results ) {
		$scope = $this->content_scope( $context );
		$this->check_internal_links( $document, $results, $scope );
		$this->check_external_links( $document, $results, $scope );

		if ( $context->has_full_page ) {
			$this->check_external_links_validation( $document, $results, $scope );
			$this->check_broken_links( $context, $document, $results, $scope );
		}
	}

	/**
	 * @param Html_Document $document Document.
	 * @param Result_Set    $results Results.
	 * @param string        $scope Scope.
	 * @return void
	 */
	private function check_internal_links( Html_Document $document, Result_Set $results, $scope ) {
		$internal = 0;
		foreach ( $document->links( $scope ) as $node ) {
			$href = Html_Document::get_attr( $node, 'href' );
			$url  = $this->normalize_link_url( $href );
			if ( false === $url && strpos( $href, '/' ) === 0 ) {
				++$internal;
				continue;
			}
			if ( false !== $url && $this->is_internal_url( $url ) ) {
				++$internal;
			}
		}

		$word_count = $this->count_words( $document->text( $scope ) );
		if ( 0 === $internal && $word_count > 500 ) {
			$results->add_opportunity( 'no_internal_links', __( 'Consider adding internal links to other relevant pages.', 'seo-booster' ) );
			return;
		}

		if ( $internal > 0 ) {
			$results->add_good( 'has_internal_links', sprintf( __( 'Found %d internal link(s).', 'seo-booster' ), $internal ) );
		}
	}

	/**
	 * @param Html_Document $document Document.
	 * @param Result_Set    $results Results.
	 * @param string        $scope Scope.
	 * @return void
	 */
	private function check_external_links( Html_Document $document, Result_Set $results, $scope ) {
		$external = 0;
		foreach ( $document->links( $scope ) as $node ) {
			$url = $this->normalize_link_url( Html_Document::get_attr( $node, 'href' ) );
			if ( false !== $url && ! $this->is_internal_url( $url ) ) {
				++$external;
			}
		}

		if ( $external > 0 ) {
			$results->add_good( 'has_external_links', sprintf( __( 'Found %d external link(s).', 'seo-booster' ), $external ) );
		}
	}

	/**
	 * @param Html_Document $document Document.
	 * @param Result_Set    $results Results.
	 * @param string        $scope Scope.
	 * @return void
	 */
	private function check_external_links_validation( Html_Document $document, Result_Set $results, $scope ) {
		$suspicious_domains = array( 'bit.ly', 'tinyurl.com', 'goo.gl', 't.co', 'ow.ly', 'short.link' );
		$suspicious         = 0;

		foreach ( $document->links( $scope ) as $node ) {
			$url = $this->normalize_link_url( Html_Document::get_attr( $node, 'href' ) );
			if ( false === $url || $this->is_internal_url( $url ) ) {
				continue;
			}
			foreach ( $suspicious_domains as $domain ) {
				if ( false !== strpos( $url, $domain ) ) {
					++$suspicious;
					break;
				}
			}
		}

		if ( $suspicious > 0 ) {
			$results->add_warning( 'suspicious_links', sprintf( __( '%d link(s) use URL shorteners. Consider using direct links for better SEO.', 'seo-booster' ), $suspicious ) );
		}
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Html_Document   $document Document.
	 * @param Result_Set      $results Results.
	 * @param string          $scope Scope.
	 * @return void
	 */
	private function check_broken_links( Content_Context $context, Html_Document $document, Result_Set $results, $scope ) {
		Url_Status_Cache::reset_time_budget( $context->bulk_mode ? 4 : 8 );
		$queue = array();

		$this->check_broken_external_links( $document, $results, $scope, $queue );
		$this->check_broken_internal_links( $document, $results, $scope, $queue );

		if ( ! empty( $queue ) ) {
			Url_Status_Cache::queue_background_checks( $queue, Url_Status_Cache::KIND_LINK );
		}
	}

	/**
	 * @param Html_Document $document Document.
	 * @param Result_Set    $results Results.
	 * @param string        $scope Scope.
	 * @param array         $queue Queue collector.
	 * @return void
	 */
	private function check_broken_external_links( Html_Document $document, Result_Set $results, $scope, array &$queue ) {
		$broken     = array();
		$redirected = array();
		$checked    = 0;
		$seen       = array();

		foreach ( $document->links( $scope ) as $node ) {
			if ( $checked >= 10 ) {
				break;
			}

			$url = $this->normalize_link_url( Html_Document::get_attr( $node, 'href' ) );
			if ( false === $url || $this->is_internal_url( $url ) ) {
				continue;
			}

			$key = md5( $url );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			++$checked;

			$status = $this->resolve_url_status( $url, Url_Status_Cache::KIND_LINK, $queue );
			if ( null === $status ) {
				continue;
			}

			if ( 'broken' === $status['status'] ) {
				$broken[] = array(
					'url'         => $url,
					'error'       => $status['error'] ?? '',
					'status_code' => (int) ( $status['status_code'] ?? 404 ),
				);
			} elseif ( 'redirected' === $status['status'] ) {
				$redirected[] = array(
					'url'         => $url,
					'redirect_to' => $status['redirect_to'] ?? '',
					'status_code' => $status['status_code'] ?? 0,
				);
			}
		}

		if ( ! empty( $broken ) ) {
			$results->add_error(
				'broken_external_links',
				sprintf( __( '%d broken external link(s) found. These links return a 404 and should be fixed or removed.', 'seo-booster' ), count( $broken ) ),
				array(
					'broken_links'  => $broken,
					'total_checked' => $checked,
				)
			);
		}

		if ( ! empty( $redirected ) ) {
			$results->add_warning(
				'redirected_external_links',
				sprintf( __( '%d redirected external link(s) found. Consider updating these links to point directly to the final destination.', 'seo-booster' ), count( $redirected ) ),
				array(
					'redirected_links' => $redirected,
					'total_checked'    => $checked,
				)
			);
		}

		if ( empty( $broken ) && empty( $redirected ) && $checked > 0 ) {
			$results->add_good( 'external_links_ok', sprintf( __( 'All %d checked external link(s) are working correctly.', 'seo-booster' ), $checked ) );
		}
	}

	/**
	 * @param Html_Document $document Document.
	 * @param Result_Set    $results Results.
	 * @param string        $scope Scope.
	 * @param array         $queue Queue collector.
	 * @return void
	 */
	private function check_broken_internal_links( Html_Document $document, Result_Set $results, $scope, array &$queue ) {
		$broken     = array();
		$redirected = array();
		$checked    = 0;
		$seen       = array();

		foreach ( $document->links( $scope ) as $node ) {
			if ( $checked >= 15 ) {
				break;
			}

			$href = Html_Document::get_attr( $node, 'href' );
			$url  = $this->normalize_link_url( $href );
			if ( false === $url ) {
				continue;
			}
			if ( ! $this->is_internal_url( $url ) ) {
				continue;
			}

			$key = md5( $url );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			++$checked;

			$status = $this->resolve_url_status( $url, Url_Status_Cache::KIND_LINK, $queue );
			if ( null === $status ) {
				continue;
			}

			if ( 'broken' === $status['status'] ) {
				$broken[] = array(
					'url'          => $href,
					'absolute_url' => $url,
					'error'        => $status['error'] ?? '',
					'status_code'  => (int) ( $status['status_code'] ?? 404 ),
				);
			} elseif ( 'redirected' === $status['status'] ) {
				$redirected[] = array(
					'url'          => $href,
					'absolute_url' => $url,
					'redirect_to'  => $status['redirect_to'] ?? '',
					'status_code'  => $status['status_code'] ?? 0,
				);
			}
		}

		if ( ! empty( $broken ) ) {
			$results->add_error(
				'broken_internal_links',
				sprintf( __( '%d broken internal link(s) found. These links return a 404 and should be fixed or removed.', 'seo-booster' ), count( $broken ) ),
				array(
					'broken_links'  => $broken,
					'total_checked' => $checked,
				)
			);
		}

		if ( ! empty( $redirected ) ) {
			$results->add_warning(
				'redirected_internal_links',
				sprintf( __( '%d redirected internal link(s) found. Consider updating these links to point directly to the final destination.', 'seo-booster' ), count( $redirected ) ),
				array(
					'redirected_links' => $redirected,
					'total_checked'    => $checked,
				)
			);
		}

		if ( empty( $broken ) && empty( $redirected ) && $checked > 0 ) {
			$results->add_good( 'internal_links_ok', sprintf( __( 'All %d checked internal link(s) are working correctly.', 'seo-booster' ), $checked ) );
		}
	}
}
