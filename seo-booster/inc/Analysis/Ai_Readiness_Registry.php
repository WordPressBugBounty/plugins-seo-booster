<?php

namespace Cleverplugins\SEOBooster\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps SEO analysis issue keys to the AI Readiness editor checklist.
 *
 * AI Readiness is a curated lens over saved possibilities — not a separate check engine.
 *
 * @since 7.2.3
 */
class Ai_Readiness_Registry {

	/**
	 * Per-post checklist items (weighted additive score for display only).
	 *
	 * @return array<int, array{key: string, label: string, points: int, pass_keys: array<int, string>, fail_keys: array<int, string>, scope?: string}>
	 */
	public static function get_post_items() {
		return array(
			array(
				'key'       => 'meta_description',
				'label'     => __( 'Meta description set', 'seo-booster' ),
				'points'    => 10,
				'pass_keys' => array( 'meta_description_set' ),
				'fail_keys' => array( 'description_missing' ),
			),
			array(
				'key'       => 'featured_image',
				'label'     => __( 'Featured image set', 'seo-booster' ),
				'points'    => 8,
				'pass_keys' => array( 'featured_image_set' ),
				'fail_keys' => array( 'featured_image_missing' ),
				'scope'     => 'post',
			),
			array(
				'key'       => 'word_count',
				'label'     => __( 'Content is 600+ words', 'seo-booster' ),
				'points'    => 8,
				'pass_keys' => array( 'content_length', 'content_long' ),
				'fail_keys' => array( 'content_too_short', 'content_below_600_words' ),
			),
			array(
				'key'       => 'headings',
				'label'     => __( 'H2 headings present', 'seo-booster' ),
				'points'    => 8,
				'pass_keys' => array( 'has_h2_headings' ),
				'fail_keys' => array( 'no_h2' ),
			),
			array(
				'key'       => 'internal_links',
				'label'     => __( 'Internal links in content', 'seo-booster' ),
				'points'    => 7,
				'pass_keys' => array( 'has_internal_links' ),
				'fail_keys' => array( 'no_internal_links' ),
			),
			array(
				'key'       => 'gsc_keyword',
				'label'     => __( 'GSC keyword used in content', 'seo-booster' ),
				'points'    => 12,
				'pass_keys' => array( 'gsc_keywords_in_content' ),
				'fail_keys' => array( 'gsc_keywords_not_in_content' ),
			),
			array(
				'key'       => 'faq_heading',
				'label'     => __( 'Question-style heading (FAQ)', 'seo-booster' ),
				'points'    => 10,
				'pass_keys' => array( 'faq_heading_present' ),
				'fail_keys' => array( 'faq_heading_missing' ),
			),
			array(
				'key'       => 'seo_score',
				'label'     => __( 'Recent SEO review score 70+', 'seo-booster' ),
				'points'    => 15,
				'pass_keys' => array(),
				'fail_keys' => array(),
				'type'      => 'seo_score_meta',
			),
		);
	}

	/**
	 * Sitewide checklist items (shown separately; not counted in per-post score).
	 *
	 * @return array<int, array{key: string, label: string, pass_keys: array<int, string>, fail_keys: array<int, string>}>
	 */
	public static function get_sitewide_items() {
		return array(
			array(
				'key'       => 'llms_txt',
				'label'     => __( 'Site: dynamic llms.txt enabled', 'seo-booster' ),
				'pass_keys' => array( 'llms_txt_served' ),
				'fail_keys' => array( 'llms_txt_disabled', 'llms_txt_unreachable', 'llms_txt_physical_shadow' ),
			),
			array(
				'key'       => 'bot_tracking',
				'label'     => __( 'Site: AI bot tracking enabled', 'seo-booster' ),
				'pass_keys' => array( 'ai_bot_tracking_enabled' ),
				'fail_keys' => array( 'ai_bot_tracking_disabled' ),
			),
			array(
				'key'       => 'robots_llms',
				'label'     => __( 'Site: robots.txt LLMS discovery line', 'seo-booster' ),
				'pass_keys' => array( 'robots_llms_present' ),
				'fail_keys' => array( 'robots_llms_missing' ),
			),
			array(
				'key'       => 'robots_entitymap',
				'label'     => __( 'Site: robots.txt EntityMap discovery line', 'seo-booster' ),
				'pass_keys' => array( 'robots_entitymap_present' ),
				'fail_keys' => array( 'robots_entitymap_missing' ),
			),
			array(
				'key'       => 'entity_map',
				'label'     => __( 'Site: Entity Map published (Pro)', 'seo-booster' ),
				'pass_keys' => array( 'entity_map_served' ),
				'fail_keys' => array( 'entity_map_disabled', 'entity_map_pro_available', 'entity_map_physical_shadow' ),
			),
		);
	}

	/**
	 * All issue keys used for AI Readiness filtering on the Issues page.
	 *
	 * @return array<int, string>
	 */
	public static function get_issue_keys() {
		$keys = array(
			'content_below_600_words',
			'description_missing',
			'meta_description_set',
			'featured_image_missing',
			'featured_image_set',
			'faq_heading_missing',
			'faq_heading_present',
			'content_too_short',
			'content_length',
			'no_h2',
			'has_h2_headings',
			'no_internal_links',
			'has_internal_links',
			'gsc_keywords_not_in_content',
			'gsc_keywords_in_content',
			'llms_txt_disabled',
			'llms_txt_unreachable',
			'llms_txt_physical_shadow',
			'llms_txt_served',
			'robots_llms_missing',
			'robots_llms_present',
			'robots_entitymap_missing',
			'robots_entitymap_present',
			'entity_map_served',
			'entity_map_disabled',
			'entity_map_pro_available',
			'entity_map_physical_shadow',
			'ai_bot_tracking_disabled',
			'ai_bot_tracking_enabled',
		);

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Whether an issue key belongs to the AI Readiness category.
	 *
	 * @param string $issue_key Issue key.
	 * @return bool
	 */
	public static function is_ai_readiness_key( $issue_key ) {
		return in_array( $issue_key, self::get_issue_keys(), true );
	}

	/**
	 * Collect all issue keys present in an analysis payload, bucketed by outcome.
	 *
	 * @param array|null $analysis Saved or fresh analysis array.
	 * @return array{pass: array<int, string>, fail: array<int, string>, all: array<int, string>}
	 */
	public static function collect_analysis_keys( $analysis ) {
		$pass = array();
		$fail = array();

		if ( empty( $analysis ) || ! is_array( $analysis ) ) {
			return array(
				'pass' => array(),
				'fail' => array(),
				'all'  => array(),
			);
		}

		$buckets = array(
			'issues'        => 'fail',
			'opportunities' => 'fail',
			'improvements'  => 'fail',
			'good'          => 'pass',
			'not_applicable' => 'pass',
		);

		foreach ( $buckets as $bucket => $outcome ) {
			if ( empty( $analysis[ $bucket ] ) || ! is_array( $analysis[ $bucket ] ) ) {
				continue;
			}
			foreach ( $analysis[ $bucket ] as $item ) {
				if ( empty( $item['key'] ) ) {
					continue;
				}
				if ( $outcome === 'pass' ) {
					$pass[] = $item['key'];
				} else {
					$fail[] = $item['key'];
				}
			}
		}

		$pass = array_values( array_unique( $pass ) );
		$fail = array_values( array_unique( $fail ) );

		return array(
			'pass' => $pass,
			'fail' => $fail,
			'all'  => array_values( array_unique( array_merge( $pass, $fail ) ) ),
		);
	}

	/**
	 * Collect issue keys from sitewide issues rows.
	 *
	 * @param array<int, array<string, mixed>|object> $sitewide_issues Sitewide issue rows (arrays or stdClass from wpdb).
	 * @return array{pass: array<int, string>, fail: array<int, string>}
	 */
	public static function collect_sitewide_keys( $sitewide_issues ) {
		$pass = array();
		$fail = array();

		if ( empty( $sitewide_issues ) || ! is_array( $sitewide_issues ) ) {
			return array(
				'pass' => array(),
				'fail' => array(),
			);
		}

		foreach ( $sitewide_issues as $row ) {
			$row      = is_array( $row ) ? $row : (array) $row;
			$key      = $row['issue_key'] ?? ( $row['key'] ?? '' );
			$severity = $row['severity'] ?? '';

			if ( $key === '' ) {
				continue;
			}

			if ( $severity === 'good' ) {
				$pass[] = $key;
			} else {
				$fail[] = $key;
			}
		}

		return array(
			'pass' => array_values( array_unique( $pass ) ),
			'fail' => array_values( array_unique( $fail ) ),
		);
	}

	/**
	 * Evaluate whether a registry item passes given key sets.
	 *
	 * @param array<string, mixed> $item Registry item.
	 * @param array<int, string>   $pass_keys Keys that passed in analysis.
	 * @param array<int, string>   $fail_keys Keys that failed in analysis.
	 * @param array|null           $analysis Full analysis (for seo_score_meta).
	 * @return bool|null Null when indeterminate (no analysis yet).
	 */
	public static function item_passes( $item, $pass_keys, $fail_keys, $analysis = null ) {
		if ( ! empty( $item['type'] ) && $item['type'] === 'seo_score_meta' ) {
			return self::seo_score_meta_passes( $analysis );
		}

		foreach ( $item['pass_keys'] as $key ) {
			if ( in_array( $key, $pass_keys, true ) ) {
				return true;
			}
		}

		foreach ( $item['fail_keys'] as $key ) {
			if ( in_array( $key, $fail_keys, true ) ) {
				return false;
			}
		}

		return null;
	}

	/**
	 * Whether saved analysis meets the recent SEO score threshold.
	 *
	 * @param array|null $analysis Analysis payload.
	 * @return bool|null
	 */
	public static function seo_score_meta_passes( $analysis ) {
		if ( empty( $analysis ) || ! isset( $analysis['score'] ) ) {
			return null;
		}

		if ( (int) $analysis['score'] < 70 ) {
			return false;
		}

		$analyzed_at = '';
		if ( ! empty( $analysis['db_metadata']['analyzed_at'] ) ) {
			$analyzed_at = $analysis['db_metadata']['analyzed_at'];
		} elseif ( ! empty( $analysis['metadata']['timestamp'] ) ) {
			$analyzed_at = gmdate( 'Y-m-d H:i:s', (int) $analysis['metadata']['timestamp'] );
		}

		if ( $analyzed_at === '' ) {
			return null;
		}

		$cutoff = strtotime( '-30 days', current_time( 'timestamp' ) );
		return strtotime( $analyzed_at ) >= $cutoff;
	}
}
