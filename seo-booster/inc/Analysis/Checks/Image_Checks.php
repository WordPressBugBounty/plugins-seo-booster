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
 * Image alt text, broken images, and dimension checks.
 *
 * @since 7.1.0
 */
class Image_Checks extends Abstract_Checks {

	/**
	 * @inheritDoc
	 */
	public function get_name() {
		return 'images';
	}

	/**
	 * @inheritDoc
	 */
	public function run( Content_Context $context, Html_Document $document, Result_Set $results ) {
		$scope      = $this->content_scope( $context );
		$scope_html = Html_Document::strip_noscript( $document->get_scope_html( $scope ) );

		$this->check_image_alt_text( $context, $document, $results, $scope, $scope_html );

		if ( $context->has_full_page ) {
			$this->check_broken_images( $context, $document, $results, $scope, $scope_html );
			$this->check_image_dimensions( $context, $document, $results, $scope, $scope_html );
		}
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Html_Document   $document Document.
	 * @param Result_Set      $results Results.
	 * @param string          $scope Scope.
	 * @param string          $scope_html Scope HTML.
	 * @return void
	 */
	private function check_image_alt_text( Content_Context $context, Html_Document $document, Result_Set $results, $scope, $scope_html ) {
		$images                     = $document->images( $scope );
		$images_without_alt         = 0;
		$images_with_empty_alt      = 0;
		$total_images               = 0;
		$images_without_alt_list    = array();
		$images_with_empty_alt_list = array();
		$seen_urls                  = array();
		$max_examples               = 50;

		foreach ( $images as $node ) {
			if ( $this->is_hidden_node( $node ) || Html_Document::is_placeholder_image( $node ) ) {
				continue;
			}

			$resolved = Html_Document::resolve_image_url( $node );
			if ( ! empty( $resolved ) ) {
				$key = md5( $resolved );
				if ( isset( $seen_urls[ $key ] ) ) {
					continue;
				}
				$seen_urls[ $key ] = true;
			}

			++$total_images;
			$alt = $this->get_alt_from_node( $node );

			if ( ! $alt['present'] ) {
				++$images_without_alt;
				if ( count( $images_without_alt_list ) < $max_examples ) {
					$images_without_alt_list[] = $this->image_example( $context, $document, $node, $scope_html );
				}
				continue;
			}

			if ( '' === $alt['value'] ) {
				++$images_with_empty_alt;
				if ( count( $images_with_empty_alt_list ) < $max_examples ) {
					$images_with_empty_alt_list[] = $this->image_example( $context, $document, $node, $scope_html );
				}
			}
		}

		$total_problematic = $images_without_alt + $images_with_empty_alt;
		if ( 0 === $total_images ) {
			return;
		}

		if ( 0 === $total_problematic ) {
			$results->add_good( 'alt_text_ok', __( 'All images have meaningful alt text.', 'seo-booster' ) );
			return;
		}

		$extra_missing = ! empty( $images_without_alt_list ) ? array( 'images_without_alt' => $images_without_alt_list ) : null;
		$extra_empty   = ! empty( $images_with_empty_alt_list ) ? array( 'images_with_empty_alt' => $images_with_empty_alt_list ) : null;

		if ( $images_without_alt > 0 ) {
			if ( $images_without_alt === $total_images ) {
				$results->add_error( 'no_alt_text', __( 'All images are missing alt text. Add alt text to your images.', 'seo-booster' ), $extra_missing );
			} else {
				$results->add_warning(
					'some_alt_text',
					sprintf( __( '%1$d out of %2$d images are missing alt text.', 'seo-booster' ), $images_without_alt, $total_images ),
					$extra_missing
				);
			}
		}

		if ( $images_with_empty_alt > 0 ) {
			if ( $images_with_empty_alt === $total_images && 0 === $images_without_alt ) {
				$results->add_opportunity(
					'all_empty_alt_text',
					sprintf( __( 'All %d images have empty alt text (alt=""). Confirm they are decorative or add meaningful alt text.', 'seo-booster' ), $total_images ),
					$extra_empty
				);
			} else {
				$results->add_opportunity(
					'some_empty_alt_text',
					sprintf( __( '%1$d out of %2$d images have empty alt text (alt=""). Confirm they are decorative or add meaningful alt text.', 'seo-booster' ), $images_with_empty_alt, $total_images ),
					$extra_empty
				);
			}
		}
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Html_Document   $document Document.
	 * @param Result_Set      $results Results.
	 * @param string          $scope Scope.
	 * @param string          $scope_html Scope HTML.
	 * @return void
	 */
	private function check_broken_images( Content_Context $context, Html_Document $document, Result_Set $results, $scope, $scope_html ) {
		Url_Status_Cache::reset_time_budget( $context->bulk_mode ? 4 : 8 );
		$images               = $document->images( $scope );
		$broken_images        = 0;
		$external_images      = 0;
		$broken_images_list   = array();
		$external_images_list = array();
		$queue                = array();
		$seen                 = array();
		$max_examples         = 50;
		$max_checks           = 25;
		$checked_count        = 0;

		foreach ( $images as $node ) {
			if ( $this->is_hidden_node( $node ) || Html_Document::is_placeholder_image( $node ) ) {
				continue;
			}

			$image_url = Html_Document::resolve_image_url( $node );
			$normalized = $this->normalize_image_url( $image_url );
			if ( false === $normalized ) {
				continue;
			}

			$key = md5( $normalized );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			$is_local = $this->is_internal_url( $normalized );

			if ( $checked_count >= $max_checks ) {
				if ( ! $is_local ) {
					++$external_images;
				}
				continue;
			}

			++$checked_count;
			$image_status = $this->resolve_url_status( $normalized, Url_Status_Cache::KIND_IMAGE, $queue );
			if ( null === $image_status ) {
				continue;
			}

			if ( 'broken' === $image_status['status'] ) {
				++$broken_images;
				if ( count( $broken_images_list ) < $max_examples ) {
					$html      = Html_Document::node_to_html( $node );
					$pos       = strpos( $scope_html, $html );
					$line_info = false !== $pos ? $this->get_line_number_and_context( $scope_html, $pos ) : array( 'line' => 0, 'context' => '' );
					$error     = $image_status['error'] ?? '';
					if ( ! empty( $image_status['status_code'] ) ) {
						$error = sprintf( __( 'HTTP %1$d: %2$s', 'seo-booster' ), $image_status['status_code'], $error );
					}
					$broken_images_list[] = array(
						'url'     => $image_url,
						'error'   => $error,
						'line'    => $line_info['line'],
						'context' => $line_info['context'],
					);
				}
			}

			if ( ! $is_local ) {
				++$external_images;
				if ( count( $external_images_list ) < $max_examples && 'broken' !== $image_status['status'] ) {
					$external_images_list[] = $this->image_example( $context, $document, $node, $scope_html );
				}
			}
		}

		if ( ! empty( $queue ) ) {
			Url_Status_Cache::queue_background_checks( $queue, Url_Status_Cache::KIND_IMAGE );
		}

		if ( $broken_images > 0 ) {
			$results->add_error(
				'broken_images',
				sprintf( __( '%d broken image(s) found. Check and fix the image URLs.', 'seo-booster' ), $broken_images ),
				array( 'broken_images' => $broken_images_list )
			);
		}

		if ( $external_images > 0 ) {
			$results->add_warning(
				'external_images',
				sprintf( __( '%d external image(s) found. Consider hosting images locally for better performance and reliability.', 'seo-booster' ), $external_images ),
				! empty( $external_images_list ) ? array( 'external_images' => $external_images_list ) : null
			);
		}

		if ( 0 === $broken_images && 0 === $external_images ) {
			$results->add_good( 'images_ok', __( 'All images appear to be working correctly.', 'seo-booster' ) );
		}
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Html_Document   $document Document.
	 * @param Result_Set      $results Results.
	 * @param string          $scope Scope.
	 * @param string          $scope_html Scope HTML.
	 * @return void
	 */
	private function check_image_dimensions( Content_Context $context, Html_Document $document, Result_Set $results, $scope, $scope_html ) {
		$images                         = $document->images( $scope );
		$images_without_dimensions        = 0;
		$images_without_dimensions_list   = array();
		$max_examples                     = 50;

		foreach ( $images as $node ) {
			if ( $this->is_hidden_node( $node ) || Html_Document::is_placeholder_image( $node ) ) {
				continue;
			}

			$html = Html_Document::node_to_html( $node );
			if ( preg_match( '/\bwidth\s*=\s*["\'][^"\']+["\']/i', $html ) && preg_match( '/\bheight\s*=\s*["\'][^"\']+["\']/i', $html ) ) {
				continue;
			}

			++$images_without_dimensions;
			if ( count( $images_without_dimensions_list ) < $max_examples ) {
				$images_without_dimensions_list[] = $this->image_example( $context, $document, $node, $scope_html );
			}
		}

		if ( $images_without_dimensions > 0 ) {
			$results->add_opportunity(
				'images_without_dimensions',
				sprintf( __( '%d image(s) without width/height attributes. Add dimensions to prevent layout shift.', 'seo-booster' ), $images_without_dimensions ),
				array( 'images_without_dimensions' => $images_without_dimensions_list )
			);
		}
	}
}
