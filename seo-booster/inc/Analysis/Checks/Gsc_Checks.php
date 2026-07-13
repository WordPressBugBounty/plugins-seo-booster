<?php

namespace Cleverplugins\SEOBooster\Analysis\Checks;

use Cleverplugins\SEOBooster\Analysis\Abstract_Checks;
use Cleverplugins\SEOBooster\Analysis\Content_Context;
use Cleverplugins\SEOBooster\Analysis\Gsc_Inspection_Cache;
use Cleverplugins\SEOBooster\Analysis\Html_Document;
use Cleverplugins\SEOBooster\Analysis\Result_Set;
use Cleverplugins\SEOBooster\Analysis\Severity;
use Cleverplugins\SEOBooster\Google_API;
use Cleverplugins\SEOBooster\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Search Console indexing and opportunity checks.
 *
 * @since 7.1.0
 */
class Gsc_Checks extends Abstract_Checks {

	/**
	 * @inheritDoc
	 */
	public function get_name() {
		return 'gsc';
	}

	/**
	 * @inheritDoc
	 */
	public function run( Content_Context $context, Html_Document $document, Result_Set $results ) {
		$this->check_gsc_status( $context, $results );
		$this->check_gsc_low_ctr_good_position( $context, $results );
		$this->check_gsc_high_impressions_low_clicks( $context, $results );
		$this->check_gsc_keywords_not_in_content( $context, $results );
		$this->check_gsc_keyword_cannibalization( $context, $results );
		$this->check_gsc_longtail_opportunities( $context, $results );
		$this->check_gsc_content_freshness( $context, $results );
		$this->check_gsc_question_queries( $context, $results );
	}

	/**
	 * @param Content_Context $context Context.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_gsc_keywords_for_url( Content_Context $context ) {
		global $wpdb;

		$url = $context->get_object_url();
		if ( empty( $url ) ) {
			return array();
		}

		$access_token = Google_API::get_access_token();
		if ( ! $access_token || is_wp_error( $access_token ) ) {
			return array();
		}

		$query = $wpdb->prepare(
			"SELECT
                qk.id,
                qk.query,
                qk.page,
                qk.is_used_in_content,
                COALESCE(SUM(qkh.clicks), 0) as clicks,
                COALESCE(SUM(qkh.impressions), 0) as impressions,
                COALESCE(AVG(qkh.ctr), 0) as ctr,
                COALESCE(AVG(qkh.position), 0) as position,
                MAX(qkh.date) as latest_date
            FROM {$wpdb->prefix}sb2_query_keywords AS qk
            LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh
                ON qk.id = qkh.query_keywords_id
            WHERE qk.page = %s
            GROUP BY qk.id, qk.query, qk.page, qk.is_used_in_content
            ORDER BY impressions DESC, clicks DESC",
			$url
		);

		$results = $wpdb->get_results( $query, ARRAY_A );
		return $results ? $results : array();
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function check_gsc_status( Content_Context $context, Result_Set $results ) {
		$access_token = Google_API::get_access_token();
		if ( ! $access_token || is_wp_error( $access_token ) ) {
			$context->record_gsc_check_status( 'status', 'not_applicable', __( 'GSC not connected', 'seo-booster' ) );
			$results->add_not_applicable( 'gsc_status_skipped_not_connected', __( 'Google Search Console: Indexing & Structured Data check skipped (GSC not connected).', 'seo-booster' ) );
			return;
		}

		$site_url = get_option( 'seobooster_selected_site', '' );
		if ( empty( $site_url ) ) {
			$context->record_gsc_check_status( 'status', 'not_applicable', __( 'No site URL configured', 'seo-booster' ) );
			$results->add_not_applicable( 'gsc_status_skipped_no_site', __( 'Google Search Console: Indexing & Structured Data check skipped (No site URL configured).', 'seo-booster' ) );
			return;
		}

		$url = $context->get_object_url();
		if ( empty( $url ) ) {
			$context->record_gsc_check_status( 'status', 'not_applicable', __( 'No URL available', 'seo-booster' ) );
			$results->add_not_applicable( 'gsc_status_skipped_no_url', __( 'Google Search Console: Indexing & Structured Data check skipped (No URL available).', 'seo-booster' ) );
			return;
		}

		$inspection_data = Gsc_Inspection_Cache::inspect( $url, $context );
		if ( null === $inspection_data ) {
			$context->record_gsc_check_status( 'status', 'not_applicable', __( 'Inspection skipped in bulk mode (no cache)', 'seo-booster' ) );
			$results->add_not_applicable( 'gsc_status_skipped_bulk_deferred', __( 'Google Search Console: Indexing check deferred (bulk mode, no cached inspection).', 'seo-booster' ) );
			return;
		}

		if ( is_wp_error( $inspection_data ) ) {
			Utils::log( 'GSC inspection failed for URL: ' . $url . ' - ' . $inspection_data->get_error_message(), 2 );
			$context->record_gsc_check_status( 'status', 'not_applicable', __( 'GSC inspection failed', 'seo-booster' ) );
			$results->add_not_applicable( 'gsc_status_skipped_inspection_failed', __( 'Google Search Console: Indexing & Structured Data check skipped (Inspection failed).', 'seo-booster' ) );
			return;
		}

		if ( empty( $inspection_data ) || ! is_array( $inspection_data ) ) {
			$context->record_gsc_check_status( 'status', 'not_applicable', __( 'No inspection data available', 'seo-booster' ) );
			$results->add_not_applicable( 'gsc_status_skipped_no_data', __( 'Google Search Console: Indexing & Structured Data check skipped (No inspection data available).', 'seo-booster' ) );
			return;
		}

		$inspection_result = $inspection_data['inspectionResult'] ?? array();
		if ( empty( $inspection_result ) ) {
			$context->record_gsc_check_status( 'status', 'not_applicable', __( 'No inspection result available', 'seo-booster' ) );
			$results->add_not_applicable( 'gsc_status_skipped_no_result', __( 'Google Search Console: Indexing & Structured Data check skipped (No inspection result available).', 'seo-booster' ) );
			return;
		}

		$index_status = $inspection_result['indexStatusResult'] ?? array();
		$rich_results = $inspection_result['richResultsResult'] ?? array();
		$issues_found = false;

		if ( ! empty( $index_status ) ) {
			$indexing_state = $index_status['indexingState'] ?? '';
			$coverage_state = $index_status['coverageState'] ?? '';
			$verdict        = $index_status['verdict'] ?? '';

			if ( ! empty( $indexing_state ) && 'INDEXING_ALLOWED' !== $indexing_state ) {
				$severity = $this->map_gsc_severity( $indexing_state, $coverage_state );
				$message  = $this->translate_gsc_term( $indexing_state );
				if ( ! empty( $coverage_state ) && 'PASS' !== $coverage_state ) {
					$message .= ' - ' . $this->translate_gsc_term( $coverage_state );
				}

				$issue_key = 'gsc_indexing_' . strtolower( str_replace( '_', '-', $indexing_state ) );
				if ( Severity::ERROR === $severity ) {
					$results->add_error(
						$issue_key,
						$message,
						array(
							'source'         => 'gsc',
							'indexing_state' => $indexing_state,
							'coverage_state' => $coverage_state,
							'verdict'        => $verdict,
						)
					);
				} else {
					$results->add_warning(
						$issue_key,
						$message,
						array(
							'source'         => 'gsc',
							'indexing_state' => $indexing_state,
							'coverage_state' => $coverage_state,
							'verdict'        => $verdict,
						)
					);
				}
				$issues_found = true;
			}
		}

		if ( ! empty( $rich_results['detectedItems'] ) && is_array( $rich_results['detectedItems'] ) ) {
			foreach ( $rich_results['detectedItems'] as $item ) {
				$rich_result_type = isset( $item['richResultType'] ) ? sanitize_text_field( $item['richResultType'] ) : '';
				if ( empty( $rich_result_type ) || empty( $item['items'] ) || ! is_array( $item['items'] ) ) {
					continue;
				}

				foreach ( $item['items'] as $item_data ) {
					if ( empty( $item_data['issues'] ) || ! is_array( $item_data['issues'] ) ) {
						continue;
					}

					foreach ( $item_data['issues'] as $issue ) {
						$issue_message  = isset( $issue['issueMessage'] ) ? sanitize_text_field( $issue['issueMessage'] ) : '';
						$issue_severity = isset( $issue['severity'] ) ? sanitize_text_field( $issue['severity'] ) : '';
						if ( empty( $issue_message ) ) {
							continue;
						}

						$issue_key = 'gsc_structured_' . strtolower( str_replace( ' ', '-', $rich_result_type ) );
						$message   = sprintf( __( '%1$s: %2$s', 'seo-booster' ), $rich_result_type, $issue_message );
						$extra     = array(
							'source'           => 'gsc',
							'rich_result_type' => $rich_result_type,
							'issue_message'    => $issue_message,
							'severity_raw'     => $issue_severity,
						);

						if ( 'ERROR' === $issue_severity ) {
							$results->add_error( $issue_key, $message, $extra );
						} else {
							$results->add_warning( $issue_key, $message, $extra );
						}
						$issues_found = true;
					}
				}
			}
		}

		if ( $issues_found ) {
			$context->record_gsc_check_status( 'status', 'data_found', '' );
			return;
		}

		$context->record_gsc_check_status( 'status', 'no_data', '' );
		$results->add_good( 'gsc_status_ok', __( 'Google Search Console: No indexing or structured data issues found.', 'seo-booster' ) );
	}

	/**
	 * @param string $indexing_state Indexing state.
	 * @param string $coverage_state Coverage state.
	 * @return string
	 */
	private function map_gsc_severity( $indexing_state, $coverage_state = '' ) {
		if ( in_array( $indexing_state, array( 'BLOCKED_BY_META_TAG', 'BLOCKED_BY_ROBOTS_TXT' ), true ) ) {
			return Severity::ERROR;
		}

		if ( 'INDEXING_ALLOWED' !== $indexing_state ) {
			return Severity::WARNING;
		}

		return Severity::WARNING;
	}

	/**
	 * @param string $technical_term Term.
	 * @return string
	 */
	private function translate_gsc_term( $technical_term ) {
		$translations = array(
			'BLOCKED_BY_META_TAG'   => __( 'Page is set to not be indexed', 'seo-booster' ),
			'BLOCKED_BY_ROBOTS_TXT' => __( 'Page is blocked by robots.txt', 'seo-booster' ),
			'NOT_FOUND'             => __( 'Page not found (404 error)', 'seo-booster' ),
			'INDEXING_ALLOWED'      => __( 'Indexing is allowed', 'seo-booster' ),
			'FAIL'                  => __( "Google can't index this page", 'seo-booster' ),
			'PASS'                  => __( 'Page is indexed', 'seo-booster' ),
			'NEUTRAL'               => __( 'Page is excluded from indexing', 'seo-booster' ),
		);

		return $translations[ $technical_term ] ?? $technical_term;
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function check_gsc_low_ctr_good_position( Content_Context $context, Result_Set $results ) {
		$keywords = $this->get_gsc_keywords_for_url( $context );
		if ( empty( $keywords ) ) {
			$context->record_gsc_check_status( 'low_ctr_good_position', 'not_applicable', __( 'No keywords available', 'seo-booster' ) );
			$results->add_not_applicable( 'gsc_low_ctr_skipped', __( 'Google Search Console: Low CTR (Good Position) check skipped (No keywords available).', 'seo-booster' ) );
			return;
		}

		$low_ctr_keywords = array();
		foreach ( $keywords as $keyword ) {
			$position = floatval( $keyword['position'] );
			$ctr      = floatval( $keyword['ctr'] );
			if ( $position > 0 && $position < 10 && $ctr < 2.0 ) {
				$low_ctr_keywords[] = $keyword;
			}
		}

		if ( empty( $low_ctr_keywords ) ) {
			$context->record_gsc_check_status( 'low_ctr_good_position', 'no_data', '' );
			$results->add_good( 'gsc_low_ctr_ok', __( 'Google Search Console: No keywords with low CTR despite good position found.', 'seo-booster' ) );
			return;
		}

		$keyword_list = array();
		foreach ( $low_ctr_keywords as $kw ) {
			$keyword_list[] = array(
				'query'       => $kw['query'],
				'clicks'      => intval( $kw['clicks'] ),
				'impressions' => intval( $kw['impressions'] ),
				'position'    => floatval( $kw['position'] ),
				'ctr'         => floatval( $kw['ctr'] ),
			);
		}

		$results->add_opportunity(
			'gsc_low_ctr_good_position',
			__( 'This page has keywords ranking in top 10 but with low click-through rates. Consider optimizing titles and meta descriptions.', 'seo-booster' ),
			array(
				'keywords' => $keyword_list,
				'page'     => $context->get_object_url(),
			)
		);
		$context->record_gsc_check_status( 'low_ctr_good_position', 'data_found', '' );
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function check_gsc_high_impressions_low_clicks( Content_Context $context, Result_Set $results ) {
		$keywords = $this->get_gsc_keywords_for_url( $context );
		if ( empty( $keywords ) ) {
			$context->record_gsc_check_status( 'high_impressions_low_clicks', 'not_applicable', __( 'No keywords available', 'seo-booster' ) );
			$results->add_not_applicable( 'gsc_high_impressions_skipped', __( 'Google Search Console: High Impressions, Low Clicks check skipped (No keywords available).', 'seo-booster' ) );
			return;
		}

		$matches = array();
		foreach ( $keywords as $keyword ) {
			if ( intval( $keyword['impressions'] ) > 1000 && intval( $keyword['clicks'] ) < 50 ) {
				$matches[] = $keyword;
			}
		}

		if ( empty( $matches ) ) {
			$context->record_gsc_check_status( 'high_impressions_low_clicks', 'no_data', '' );
			$results->add_good( 'gsc_high_impressions_ok', __( 'Google Search Console: No keywords with high impressions but low clicks found.', 'seo-booster' ) );
			return;
		}

		usort(
			$matches,
			static function ( $a, $b ) {
				return intval( $b['impressions'] ) - intval( $a['impressions'] );
			}
		);
		$matches      = array_slice( $matches, 0, 20 );
		$keyword_list = array();
		foreach ( $matches as $kw ) {
			$keyword_list[] = array(
				'query'       => $kw['query'],
				'clicks'      => intval( $kw['clicks'] ),
				'impressions' => intval( $kw['impressions'] ),
				'position'    => floatval( $kw['position'] ),
				'ctr'         => floatval( $kw['ctr'] ),
			);
		}

		$results->add_opportunity(
			'gsc_high_impressions_low_clicks',
			__( 'Keywords with high visibility but low clicks. Consider optimizing titles and meta descriptions to improve click-through rates.', 'seo-booster' ),
			array(
				'keywords' => $keyword_list,
				'page'     => $context->get_object_url(),
			)
		);
		$context->record_gsc_check_status( 'high_impressions_low_clicks', 'data_found', '' );
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function check_gsc_keywords_not_in_content( Content_Context $context, Result_Set $results ) {
		$keywords = $this->get_gsc_keywords_for_url( $context );
		if ( empty( $keywords ) ) {
			$context->record_gsc_check_status( 'keywords_not_in_content', 'not_applicable', __( 'No keywords available', 'seo-booster' ) );
			$results->add_not_applicable( 'gsc_keywords_not_in_content_skipped', __( 'Google Search Console: Keywords Not in Content check skipped (No keywords available).', 'seo-booster' ) );
			return;
		}

		$unused = array();
		foreach ( $keywords as $keyword ) {
			if ( intval( $keyword['is_used_in_content'] ) <= 0 ) {
				$unused[] = $keyword;
			}
		}

		if ( empty( $unused ) ) {
			$context->record_gsc_check_status( 'keywords_not_in_content', 'no_data', '' );
			$results->add_good( 'gsc_keywords_in_content', __( 'Google Search Console: All ranking keywords are found in your content.', 'seo-booster' ) );
			return;
		}

		usort(
			$unused,
			static function ( $a, $b ) {
				return ( intval( $b['clicks'] ) + intval( $b['impressions'] ) ) - ( intval( $a['clicks'] ) + intval( $a['impressions'] ) );
			}
		);
		$unused       = array_slice( $unused, 0, 20 );
		$keyword_list = array();
		foreach ( $unused as $kw ) {
			$keyword_list[] = array(
				'query'       => $kw['query'],
				'clicks'      => intval( $kw['clicks'] ),
				'impressions' => intval( $kw['impressions'] ),
				'position'    => floatval( $kw['position'] ),
			);
		}

		$results->add_opportunity(
			'gsc_keywords_not_in_content',
			__( 'These ranking keywords are not found in your content. Consider adding them naturally to improve relevance.', 'seo-booster' ),
			array(
				'keywords' => $keyword_list,
				'page'     => $context->get_object_url(),
			)
		);
		$context->record_gsc_check_status( 'keywords_not_in_content', 'data_found', '' );
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function check_gsc_keyword_cannibalization( Content_Context $context, Result_Set $results ) {
		global $wpdb;

		$url = $context->get_object_url();
		if ( empty( $url ) ) {
			$context->record_gsc_check_status( 'keyword_cannibalization', 'not_applicable', __( 'No URL available', 'seo-booster' ) );
			$results->add_not_applicable( 'gsc_cannibalization_skipped_no_url', __( 'Google Search Console: Keyword Cannibalization check skipped (No URL available).', 'seo-booster' ) );
			return;
		}

		$access_token = Google_API::get_access_token();
		if ( ! $access_token || is_wp_error( $access_token ) ) {
			$context->record_gsc_check_status( 'keyword_cannibalization', 'not_applicable', __( 'GSC not connected', 'seo-booster' ) );
			$results->add_not_applicable( 'gsc_cannibalization_skipped_not_connected', __( 'Google Search Console: Keyword Cannibalization check skipped (GSC not connected).', 'seo-booster' ) );
			return;
		}

		$query = $wpdb->prepare(
			"SELECT
                qk.query,
                qk.page,
                COALESCE(SUM(qkh.clicks), 0) as clicks,
                COALESCE(SUM(qkh.impressions), 0) as impressions,
                COALESCE(AVG(qkh.position), 0) as position
            FROM {$wpdb->prefix}sb2_query_keywords AS qk
            LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh
                ON qk.id = qkh.query_keywords_id
            WHERE qk.query IN (
                SELECT DISTINCT query
                FROM {$wpdb->prefix}sb2_query_keywords
                WHERE page = %s
            )
            GROUP BY qk.query, qk.page
            HAVING COUNT(DISTINCT qk.page) > 1
            ORDER BY impressions DESC, clicks DESC
            LIMIT 50",
			$url
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );
		if ( empty( $rows ) ) {
			$context->record_gsc_check_status( 'keyword_cannibalization', 'no_data', '' );
			$results->add_good( 'gsc_no_cannibalization', __( 'Google Search Console: No keyword cannibalization detected.', 'seo-booster' ) );
			return;
		}

		$cannibalized = array();
		foreach ( $rows as $row ) {
			$query_text = $row['query'];
			if ( ! isset( $cannibalized[ $query_text ] ) ) {
				$cannibalized[ $query_text ] = array();
			}
			$cannibalized[ $query_text ][] = array(
				'page'        => $row['page'],
				'clicks'      => intval( $row['clicks'] ),
				'impressions' => intval( $row['impressions'] ),
				'position'    => floatval( $row['position'] ),
			);
		}

		$relevant = array();
		foreach ( $cannibalized as $query_text => $pages ) {
			$has_current = false;
			$has_other   = false;
			foreach ( $pages as $page_data ) {
				if ( $page_data['page'] === $url ) {
					$has_current = true;
				} else {
					$has_other = true;
				}
			}
			if ( $has_current && $has_other && count( $pages ) > 1 ) {
				$relevant[ $query_text ] = $pages;
			}
		}

		if ( empty( $relevant ) ) {
			$context->record_gsc_check_status( 'keyword_cannibalization', 'no_data', '' );
			$results->add_good( 'gsc_no_cannibalization', __( 'Google Search Console: No keyword cannibalization detected.', 'seo-booster' ) );
			return;
		}

		$keyword_stats = array();
		foreach ( $relevant as $query_text => $pages ) {
			$total = 0;
			foreach ( $pages as $page_data ) {
				$total += $page_data['impressions'];
			}
			$keyword_stats[ $query_text ] = $total;
		}
		arsort( $keyword_stats );
		$top   = array_slice( array_keys( $keyword_stats ), 0, 10 );
		$final = array();
		foreach ( $top as $query_text ) {
			$final[ $query_text ] = $relevant[ $query_text ];
		}

		$results->add_opportunity(
			'gsc_keyword_cannibalization',
			__( 'Multiple pages are competing for the same keyword. Consider consolidating or differentiating content to avoid keyword cannibalization.', 'seo-booster' ),
			array(
				'cannibalized_keywords' => $final,
				'current_page'          => $url,
			)
		);
		$context->record_gsc_check_status( 'keyword_cannibalization', 'data_found', '' );
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function check_gsc_longtail_opportunities( Content_Context $context, Result_Set $results ) {
		$keywords = $this->get_gsc_keywords_for_url( $context );
		if ( empty( $keywords ) ) {
			$context->record_gsc_check_status( 'longtail_opportunities', 'not_applicable', __( 'No keywords available', 'seo-booster' ) );
			$results->add_not_applicable( 'gsc_longtail_skipped', __( 'Google Search Console: Long-tail Opportunities check skipped (No keywords available).', 'seo-booster' ) );
			return;
		}

		$longtail = array();
		foreach ( $keywords as $keyword ) {
			$word_count  = count( explode( ' ', trim( $keyword['query'] ) ) );
			$clicks      = intval( $keyword['clicks'] );
			$impressions = intval( $keyword['impressions'] );
			$position    = floatval( $keyword['position'] );
			if ( $word_count >= 4 && ( $clicks > 10 || $impressions > 100 ) && $position >= 4 && $position <= 20 ) {
				$longtail[] = $keyword;
			}
		}

		if ( empty( $longtail ) ) {
			$context->record_gsc_check_status( 'longtail_opportunities', 'no_data', '' );
			return;
		}

		usort(
			$longtail,
			static function ( $a, $b ) {
				return intval( $b['impressions'] ) - intval( $a['impressions'] );
			}
		);
		$longtail     = array_slice( $longtail, 0, 20 );
		$keyword_list = array();
		foreach ( $longtail as $kw ) {
			$keyword_list[] = array(
				'query'       => $kw['query'],
				'clicks'      => intval( $kw['clicks'] ),
				'impressions' => intval( $kw['impressions'] ),
				'position'    => floatval( $kw['position'] ),
			);
		}

		$results->add_opportunity(
			'gsc_longtail_opportunities',
			__( 'Long-tail keywords with potential. Consider expanding content around these topics.', 'seo-booster' ),
			array(
				'keywords' => $keyword_list,
				'page'     => $context->get_object_url(),
			)
		);
		$context->record_gsc_check_status( 'longtail_opportunities', 'data_found', '' );
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function check_gsc_content_freshness( Content_Context $context, Result_Set $results ) {
		global $wpdb;

		$url = $context->get_object_url();
		if ( empty( $url ) ) {
			$context->record_gsc_check_status( 'content_freshness', 'not_applicable', __( 'No URL available', 'seo-booster' ) );
			$results->add_not_applicable( 'gsc_content_freshness_skipped_no_url', __( 'Google Search Console: Content Freshness check skipped (No URL available).', 'seo-booster' ) );
			return;
		}

		$access_token = Google_API::get_access_token();
		if ( ! $access_token || is_wp_error( $access_token ) ) {
			$context->record_gsc_check_status( 'content_freshness', 'not_applicable', __( 'GSC not connected', 'seo-booster' ) );
			$results->add_not_applicable( 'gsc_content_freshness_skipped_not_connected', __( 'Google Search Console: Content Freshness check skipped (GSC not connected).', 'seo-booster' ) );
			return;
		}

		$query = $wpdb->prepare(
			"SELECT
                qk.query,
                SUM(CASE WHEN qkh.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN qkh.impressions ELSE 0 END) as recent_impressions,
                SUM(CASE WHEN qkh.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN qkh.clicks ELSE 0 END) as recent_clicks,
                SUM(CASE WHEN qkh.date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                         AND qkh.date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN qkh.impressions ELSE 0 END) as previous_impressions,
                SUM(CASE WHEN qkh.date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                         AND qkh.date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN qkh.clicks ELSE 0 END) as previous_clicks
            FROM {$wpdb->prefix}sb2_query_keywords AS qk
            LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh
                ON qk.id = qkh.query_keywords_id
            WHERE qk.page = %s
            GROUP BY qk.query
            HAVING recent_impressions >= 100
            ORDER BY recent_impressions DESC",
			$url
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );
		if ( empty( $rows ) ) {
			$context->record_gsc_check_status( 'content_freshness', 'no_data', __( 'No traffic data available for comparison', 'seo-booster' ) );
			return;
		}

		$declining = array();
		foreach ( $rows as $row ) {
			$recent   = intval( $row['recent_impressions'] );
			$previous = intval( $row['previous_impressions'] );
			if ( $previous <= 0 ) {
				continue;
			}
			$decline = ( ( $previous - $recent ) / $previous ) * 100;
			if ( $decline > 20 && $recent >= 100 ) {
				$declining[] = array(
					'query'                => $row['query'],
					'recent_impressions'   => $recent,
					'previous_impressions' => $previous,
					'recent_clicks'        => intval( $row['recent_clicks'] ),
					'previous_clicks'      => intval( $row['previous_clicks'] ),
					'decline_percentage'   => round( $decline, 1 ),
				);
			}
		}

		if ( empty( $declining ) ) {
			$context->record_gsc_check_status( 'content_freshness', 'no_data', '' );
			$results->add_good( 'gsc_content_freshness_ok', __( 'Google Search Console: No declining traffic detected for keywords.', 'seo-booster' ) );
			return;
		}

		usort(
			$declining,
			static function ( $a, $b ) {
				return $b['decline_percentage'] <=> $a['decline_percentage'];
			}
		);
		$declining = array_slice( $declining, 0, 10 );

		$results->add_opportunity(
			'gsc_content_freshness',
			__( 'Keywords showing declining traffic. Consider updating content to maintain relevance.', 'seo-booster' ),
			array(
				'keywords' => $declining,
				'page'     => $url,
			)
		);
		$context->record_gsc_check_status( 'content_freshness', 'data_found', '' );
	}

	/**
	 * @param Content_Context $context Context.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function check_gsc_question_queries( Content_Context $context, Result_Set $results ) {
		$keywords = $this->get_gsc_keywords_for_url( $context );
		if ( empty( $keywords ) ) {
			$context->record_gsc_check_status( 'question_queries', 'not_applicable', __( 'No keywords available', 'seo-booster' ) );
			$results->add_not_applicable( 'gsc_question_queries_skipped', __( 'Google Search Console: Question-based Queries check skipped (No keywords available).', 'seo-booster' ) );
			return;
		}

		$question_keywords = array();
		foreach ( $keywords as $keyword ) {
			if ( $this->is_question_query( $keyword['query'] ) ) {
				$question_keywords[] = $keyword;
			}
		}

		if ( empty( $question_keywords ) ) {
			$context->record_gsc_check_status( 'question_queries', 'no_data', '' );
			return;
		}

		usort(
			$question_keywords,
			static function ( $a, $b ) {
				return intval( $b['impressions'] ) - intval( $a['impressions'] );
			}
		);
		$question_keywords = array_slice( $question_keywords, 0, 20 );
		$keyword_list      = array();
		foreach ( $question_keywords as $kw ) {
			$keyword_list[] = array(
				'query'       => $kw['query'],
				'clicks'      => intval( $kw['clicks'] ),
				'impressions' => intval( $kw['impressions'] ),
				'position'    => floatval( $kw['position'] ),
			);
		}

		$results->add_opportunity(
			'gsc_question_queries',
			__( 'Question-based queries detected. Consider adding FAQ sections or structured answers to target featured snippets.', 'seo-booster' ),
			array(
				'keywords' => $keyword_list,
				'page'     => $context->get_object_url(),
			)
		);
		$context->record_gsc_check_status( 'question_queries', 'data_found', '' );
	}
}
