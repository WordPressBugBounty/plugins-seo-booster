<?php

namespace Cleverplugins\SEOBooster\Tools;

use Cleverplugins\SEOBooster\Google_API;
use Cleverplugins\SEOBooster\GSC_History;
use Cleverplugins\SEOBooster\SEO_Issues_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared GSC data queries for Tools page scanners.
 *
 * @package Cleverplugins\SEOBooster\Tools
 */
class Tools_GSC_Helper {

	const PREVIEW_LIMIT = 50;

	/**
	 * Max keyword rows loaded for opportunity / autolink insight scans (90-day window).
	 */
	const INSIGHT_KEYWORD_LIMIT = 500;

	/**
	 * Max queries shown/sent per page group (keeps the AI prompt focused and the
	 * UI readable when one page ranks for dozens of near-duplicate queries).
	 */
	const MAX_GROUP_QUERIES = 12;

	/**
	 * Default max autolink keyword rules suggested per target page after dedupe.
	 */
	const MAX_AUTOLINK_KEYWORDS_PER_PAGE = 5;

	/**
	 * Minimum impressions for a confident focus-keyword suggestion.
	 */
	const MIN_FOCUS_IMPRESSIONS = 20;

	/**
	 * Max GSC keyword alternatives shown per page in the focus keyword tool.
	 */
	const MAX_FOCUS_KEYWORD_ALTERNATIVES = 10;

	/**
	 * Days in each comparison window (recent vs prior). Matches Gsc_Checks content freshness.
	 */
	const DECAY_WINDOW_DAYS = 30;

	/**
	 * Minimum recent impressions for a confident decay signal (same as Gsc_Checks).
	 */
	const DECAY_MIN_RECENT_IMPRESSIONS = 100;

	/**
	 * Minimum previous-period clicks so small pages are not flagged as decaying.
	 */
	const DECAY_MIN_PREVIOUS_CLICKS = 5;

	/**
	 * Decline percentage threshold (clicks for page-level Content decay tool).
	 */
	const DECAY_MIN_DECLINE_PCT = 20;

	/**
	 * Opportunity filter keys.
	 *
	 * @return string[]
	 */
	public static function get_opportunity_filter_keys() {
		return array(
			'striking_distance',
			'low_ctr',
			'high_impressions_low_clicks',
		);
	}

	/**
	 * Whether GSC data is available (connected + rows exist).
	 *
	 * @return bool
	 */
	public static function has_gsc_data() {
		$access_token = Google_API::get_access_token();
		if ( ! $access_token || is_wp_error( $access_token ) ) {
			return false;
		}

		global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table prefix only; existence check query.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sb2_query_keywords LIMIT 1" );

		return $count > 0;
	}

	/**
	 * Fetch GSC keywords with 90-day aggregated stats for insight scanners.
	 *
	 * @param int|null $limit                Max rows, or null for no LIMIT.
	 * @param string[] $opportunity_filters  Opportunity type keys; when set, SQL HAVING pre-filters rows.
	 * @param bool     $require_min_traffic  When true, HAVING impressions >= 50 OR clicks >= 5.
	 * @return array<int, array>
	 */
	public static function get_insight_keyword_rows( $limit = null, array $opportunity_filters = array(), $require_min_traffic = false ) {
		global $wpdb;

		$window = (int) GSC_History::INSIGHT_WINDOW_DAYS;

		$having_parts = array();
		if ( $require_min_traffic ) {
			$having_parts[] = '(COALESCE(SUM(qkh.impressions), 0) >= 50 OR COALESCE(SUM(qkh.clicks), 0) >= 5)';
		}

		$opportunity_having = self::build_opportunity_having_clause( $opportunity_filters );
		if ( '' !== $opportunity_having ) {
			$having_parts[] = '(' . $opportunity_having . ')';
		}

		$having_sql = '';
		if ( ! empty( $having_parts ) ) {
			$having_sql = ' HAVING ' . implode( ' AND ', $having_parts );
		}

		$limit_sql = '';
		$prepare   = array( $window );
		if ( null !== $limit ) {
			$limit_sql = ' LIMIT %d';
			$prepare[] = max( 1, (int) $limit );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Prefixed tables; dynamic HAVING/LIMIT.
		$sql = "SELECT
                qk.id,
                qk.query,
                qk.page,
                qk.is_used_in_content,
                COALESCE(SUM(qkh.clicks), 0) AS clicks,
                COALESCE(SUM(qkh.impressions), 0) AS impressions,
                COALESCE(AVG(qkh.ctr), 0) AS ctr,
                COALESCE(AVG(qkh.position), 0) AS position
            FROM {$wpdb->prefix}sb2_query_keywords AS qk
            LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh
                ON qk.id = qkh.query_keywords_id
                AND qkh.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
            GROUP BY qk.id, qk.query, qk.page, qk.is_used_in_content{$having_sql}
            ORDER BY impressions DESC, clicks DESC{$limit_sql}";

		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic HAVING/LIMIT; placeholders filled via $prepare.
			$wpdb->prepare( $sql, ...$prepare ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Fetch all GSC keywords with aggregated stats (capped preview list).
	 *
	 * @return array<int, array>
	 */
	public static function get_all_keywords_with_stats() {
		return self::get_insight_keyword_rows( self::INSIGHT_KEYWORD_LIMIT );
	}

	/**
	 * Build SQL HAVING OR-clauses for opportunity type filters.
	 *
	 * @param string[] $filters Active opportunity filter keys.
	 * @return string Empty when no filters.
	 */
	private static function build_opportunity_having_clause( array $filters ) {
		$allowed = self::get_opportunity_filter_keys();
		$filters = array_values( array_intersect( $filters, $allowed ) );

		if ( empty( $filters ) ) {
			return '';
		}

		$clauses = array();

		if ( in_array( 'striking_distance', $filters, true ) ) {
			$clauses[] = '(COALESCE(AVG(qkh.position), 0) >= 4 AND COALESCE(AVG(qkh.position), 0) <= 20 AND COALESCE(SUM(qkh.impressions), 0) >= 50)';
		}

		if ( in_array( 'low_ctr', $filters, true ) ) {
			$clauses[] = '(COALESCE(AVG(qkh.position), 0) > 0 AND COALESCE(AVG(qkh.position), 0) < 10 AND COALESCE(AVG(qkh.ctr), 0) < 2.0 AND COALESCE(SUM(qkh.impressions), 0) >= 50)';
		}

		if ( in_array( 'high_impressions_low_clicks', $filters, true ) ) {
			$clauses[] = '(COALESCE(SUM(qkh.impressions), 0) > 1000 AND COALESCE(SUM(qkh.clicks), 0) < 50)';
		}

		return implode( ' OR ', $clauses );
	}

	/**
	 * Classify a keyword row into opportunity types.
	 *
	 * @param array $row Keyword row with position, ctr, impressions, clicks.
	 * @return string[]
	 */
	public static function classify_keyword_opportunities( array $row ) {
		$types       = array();
		$position    = (float) ( $row['position'] ?? 0 );
		$ctr         = (float) ( $row['ctr'] ?? 0 );
		$impressions = (int) ( $row['impressions'] ?? 0 );
		$clicks      = (int) ( $row['clicks'] ?? 0 );

		if ( $position >= 4 && $position <= 20 && $impressions >= 50 ) {
			$types[] = 'striking_distance';
		}

		if ( $position > 0 && $position < 10 && $ctr < 2.0 && $impressions >= 50 ) {
			$types[] = 'low_ctr';
		}

		if ( $impressions > 1000 && $clicks < 50 ) {
			$types[] = 'high_impressions_low_clicks';
		}

		return $types;
	}

	/**
	 * Scan GSC opportunities for Tools tab.
	 *
	 * Matching queries are grouped by their destination page so a page that
	 * ranks for many near-duplicate queries appears once, carrying the whole
	 * cluster of queries. This avoids rewriting the same page's meta once per
	 * query and lets the AI pick the best representative keyword(s) instead of
	 * stuffing every variation.
	 *
	 * @param string[] $filters Active filter keys.
	 * @param array    $args    Optional: include_all_items (bool, default false).
	 * @return array
	 */
	public static function scan_opportunities( array $filters, array $args = array() ) {
		$include_all_items = ! empty( $args['include_all_items'] );

		if ( empty( $filters ) ) {
			return array(
				'items'         => array(),
				'total_found'   => 0,
				'preview_limit' => self::PREVIEW_LIMIT,
				'preview_count' => 0,
				'all_ids'       => array(),
			);
		}

		$allowed = self::get_opportunity_filter_keys();
		$filters = array_values( array_intersect( $filters, $allowed ) );

		$groups    = array();
		$page_post = array();
		$keywords  = self::get_insight_keyword_rows( null, $filters );

		foreach ( $keywords as $row ) {
			$types = self::classify_keyword_opportunities( $row );
			$types = array_values( array_intersect( $types, $filters ) );

			if ( empty( $types ) ) {
				continue;
			}

			$page = isset( $row['page'] ) ? (string) $row['page'] : '';
			if ( '' === $page ) {
				continue;
			}

			if ( ! array_key_exists( $page, $page_post ) ) {
				$page_post[ $page ] = self::resolve_publishable_post_id( $page );
			}

			$post_id = $page_post[ $page ];
			if ( $post_id <= 0 ) {
				continue;
			}

			$query = trim( (string) $row['query'] );
			if ( '' === $query ) {
				continue;
			}
			$normalized = strtolower( $query );

			if ( ! isset( $groups[ $post_id ] ) ) {
				$post               = get_post( $post_id );
				$groups[ $post_id ] = array(
					'id'          => $post_id,
					'post_id'     => $post_id,
					'title'       => get_the_title( $post_id ),
					'post_type'   => $post ? $post->post_type : '',
					'slug'        => $post ? (string) $post->post_name : '',
					'edit_url'    => get_edit_post_link( $post_id, 'raw' ) ? get_edit_post_link( $post_id, 'raw' ) : '',
					'view_url'    => get_permalink( $post_id ) ? get_permalink( $post_id ) : $page,
					'page_url'    => $page,
					'queries'     => array(),
					'seen'        => array(),
					'types'       => array(),
					'impressions' => 0,
					'clicks'      => 0,
					'positions'   => array(),
				);
			}

			foreach ( $types as $type ) {
				if ( ! in_array( $type, $groups[ $post_id ]['types'], true ) ) {
					$groups[ $post_id ]['types'][] = $type;
				}
			}

			if ( isset( $groups[ $post_id ]['seen'][ $normalized ] ) ) {
				continue;
			}
			$groups[ $post_id ]['seen'][ $normalized ] = true;

			$impressions = (int) $row['impressions'];
			$clicks      = (int) $row['clicks'];
			$position    = round( (float) $row['position'], 1 );

			$groups[ $post_id ]['queries'][]    = array(
				'query'            => $query,
				'query_id'         => (int) $row['id'],
				'opportunity_type' => $types[0],
				'impressions'      => $impressions,
				'clicks'           => $clicks,
				'ctr'              => round( (float) $row['ctr'], 2 ),
				'position'         => $position,
			);
			$groups[ $post_id ]['impressions'] += $impressions;
			$groups[ $post_id ]['clicks']      += $clicks;
			if ( $position > 0 ) {
				$groups[ $post_id ]['positions'][] = $position;
			}
		}

		$matching = array();
		foreach ( $groups as $group ) {
			$queries = $group['queries'];
			usort(
				$queries,
				function ( $a, $b ) {
					$by_impressions = $b['impressions'] <=> $a['impressions'];
					return 0 !== $by_impressions ? $by_impressions : ( $b['clicks'] <=> $a['clicks'] );
				}
			);

			$primary       = $queries[0] ?? null;
			$best_position = ! empty( $group['positions'] ) ? min( $group['positions'] ) : 0.0;

			$matching[] = array(
				'id'                => $group['post_id'],
				'post_id'           => $group['post_id'],
				'title'             => $group['title'],
				'post_type'         => $group['post_type'],
				'slug'              => $group['slug'],
				'edit_url'          => $group['edit_url'],
				'view_url'          => $group['view_url'],
				'page_url'          => $group['page_url'],
				'opportunity_types' => $group['types'],
				'opportunity_type'  => $primary ? $primary['opportunity_type'] : ( $group['types'][0] ?? '' ),
				'query'             => $primary ? $primary['query'] : '',
				'queries'           => array_slice( $queries, 0, self::MAX_GROUP_QUERIES ),
				'query_count'       => count( $queries ),
				'impressions'       => $group['impressions'],
				'clicks'            => $group['clicks'],
				'position'          => $best_position,
			);
		}

		usort(
			$matching,
			function ( $a, $b ) {
				$by_impressions = $b['impressions'] <=> $a['impressions'];
				return 0 !== $by_impressions ? $by_impressions : ( $b['clicks'] <=> $a['clicks'] );
			}
		);

		$total_found = count( $matching );
		$preview     = array_slice( $matching, 0, self::PREVIEW_LIMIT );

		$result = array(
			'items'         => $preview,
			'total_found'   => $total_found,
			'preview_limit' => self::PREVIEW_LIMIT,
			'preview_count' => count( $preview ),
			'all_ids'       => array_values( array_unique( array_column( $matching, 'post_id' ) ) ),
		);

		if ( $include_all_items ) {
			$result['all_items'] = $matching;
		}

		return $result;
	}

	/**
	 * Resolve a GSC page URL to a published, analyzable post ID.
	 *
	 * @param string $page Page URL.
	 * @return int Post ID, or 0 when not eligible.
	 */
	private static function resolve_publishable_post_id( $page ) {
		$post_id = (int) url_to_postid( $page );
		if ( $post_id <= 0 ) {
			return 0;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return 0;
		}

		if ( SEO_Issues_Manager::should_exclude_from_analysis( $post_id ) ) {
			return 0;
		}

		return $post_id;
	}

	/**
	 * Get keywords for a page URL with aggregated stats.
	 *
	 * @param string $page_url Page URL.
	 * @return array
	 */
	public static function get_keywords_for_page( $page_url ) {
		global $wpdb;

		if ( '' === $page_url ) {
			return array();
		}

		$window = (int) GSC_History::INSIGHT_WINDOW_DAYS;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prefixed tables; INTERVAL/LIMIT are integers.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
                    qk.id,
                    qk.query,
                    qk.page,
                    COALESCE(SUM(qkh.clicks), 0) AS clicks,
                    COALESCE(SUM(qkh.impressions), 0) AS impressions,
                    COALESCE(AVG(qkh.ctr), 0) AS ctr,
                    COALESCE(AVG(qkh.position), 0) AS position
                FROM {$wpdb->prefix}sb2_query_keywords AS qk
                LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh
                    ON qk.id = qkh.query_keywords_id
                    AND qkh.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
                WHERE qk.page = %s
                GROUP BY qk.id, qk.query, qk.page
                ORDER BY impressions DESC, clicks DESC
                LIMIT 100",
				$window,
				$page_url
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Normalized map of focus keywords already assigned sitewide.
	 *
	 * @return array<string, int> normalized keyword => post_id
	 */
	public static function get_site_focus_keyword_map() {
		$key = SEO_Meta_Writer::get_focus_keyword_meta_key();
		if ( ! $key ) {
			return array();
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
				$key
			),
			ARRAY_A
		);

		$map = array();
		if ( ! is_array( $rows ) ) {
			return $map;
		}

		foreach ( $rows as $row ) {
			$post_id = (int) ( $row['post_id'] ?? 0 );
			$value   = trim( (string) ( $row['meta_value'] ?? '' ) );
			if ( $post_id <= 0 || '' === $value ) {
				continue;
			}

			$parts = explode( ',', $value );
			$first = trim( (string) ( $parts[0] ?? '' ) );
			if ( '' === $first ) {
				continue;
			}

			$normalized = self::normalize_keyword( $first );
			if ( '' !== $normalized && ! isset( $map[ $normalized ] ) ) {
				$map[ $normalized ] = $post_id;
			}
		}

		return $map;
	}

	/**
	 * @param string $keyword Keyword.
	 * @return string
	 */
	public static function normalize_keyword( $keyword ) {
		return strtolower( trim( (string) $keyword ) );
	}

	/**
	 * Default utility page slug fragments to skip for focus keywords.
	 *
	 * @return string[]
	 */
	public static function get_utility_page_slug_fragments() {
		$defaults = array(
			'privacy',
			'contact',
			'terms',
			'cookie',
			'cookies',
			'disclaimer',
			'refund',
			'returns',
			'shipping',
			'thank-you',
			'thankyou',
			'404',
			'login',
			'sitemap',
			'legal',
			'imprint',
			'impressum',
		);

		/**
		 * Filter slug/title fragments that should not receive a focus keyword.
		 *
		 * @param string[] $defaults Slug fragments.
		 */
		return apply_filters( 'seobooster_focus_keyword_skip_slugs', $defaults );
	}

	/**
	 * Whether a post should skip focus keyword assignment.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function should_skip_focus_keyword_for_post( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return true;
		}

		if ( SEO_Issues_Manager::should_exclude_from_analysis( $post_id ) ) {
			return true;
		}

		$privacy_id = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( $privacy_id > 0 && $post_id === $privacy_id ) {
			return true;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return true;
		}

		$fragments = self::get_utility_page_slug_fragments();
		$slug      = strtolower( (string) $post->post_name );
		$title     = strtolower( (string) $post->post_title );

		foreach ( $fragments as $fragment ) {
			$fragment = strtolower( trim( (string) $fragment ) );
			if ( '' === $fragment ) {
				continue;
			}
			if ( strpos( $slug, $fragment ) !== false || strpos( $title, $fragment ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Pick best unique GSC keyword for a post.
	 *
	 * @param int                $post_id       Post ID.
	 * @param array<string, int> $assigned_map  Normalized keyword => post_id.
	 * @param array<string, true> $batch_claimed Keywords claimed in current batch.
	 * @return array{keyword: string, impressions: int, position: float}|null
	 */
	public static function suggest_focus_keyword_for_post( $post_id, array $assigned_map, array $batch_claimed = array() ) {
		$candidates = self::suggest_focus_keywords_for_post( $post_id, $assigned_map, $batch_claimed, 1 );

		return ! empty( $candidates ) ? $candidates[0] : null;
	}

	/**
	 * Top unique GSC keyword candidates for a post.
	 *
	 * @param int                $post_id       Post ID.
	 * @param array<string, int> $assigned_map  Normalized keyword => post_id.
	 * @param array<string, true> $batch_claimed Keywords claimed in current batch.
	 * @param int|null           $limit         Max suggestions (default MAX_FOCUS_KEYWORD_ALTERNATIVES).
	 * @return array<int, array{keyword: string, impressions: int, position: float}>
	 */
	public static function suggest_focus_keywords_for_post( $post_id, array $assigned_map, array $batch_claimed = array(), $limit = null ) {
		$url = get_permalink( $post_id );
		if ( ! $url ) {
			return array();
		}

		$keywords = self::get_keywords_for_page( $url );
		if ( empty( $keywords ) ) {
			return array();
		}

		if ( null === $limit ) {
			$limit = self::MAX_FOCUS_KEYWORD_ALTERNATIVES;
		}
		$limit = max( 1, (int) $limit );
		$found = array();

		foreach ( $keywords as $row ) {
			if ( count( $found ) >= $limit ) {
				break;
			}

			$impressions = (int) ( $row['impressions'] ?? 0 );
			$position    = (float) ( $row['position'] ?? 0 );
			$query       = trim( (string) ( $row['query'] ?? '' ) );

			if ( '' === $query || $impressions < self::MIN_FOCUS_IMPRESSIONS ) {
				continue;
			}

			if ( $position <= 0 || $position > 50 ) {
				continue;
			}

			$normalized = self::normalize_keyword( $query );
			if ( '' === $normalized ) {
				continue;
			}

			if ( isset( $batch_claimed[ $normalized ] ) ) {
				continue;
			}

			if ( isset( $assigned_map[ $normalized ] ) && (int) $assigned_map[ $normalized ] !== $post_id ) {
				continue;
			}

			$found[] = array(
				'keyword'     => $query,
				'impressions' => $impressions,
				'position'    => round( $position, 1 ),
			);
		}

		return $found;
	}

	/**
	 * Existing autolink keywords normalized for lookup.
	 *
	 * @return array<string, true>
	 */
	public static function get_autolink_keyword_set() {
		global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table prefix only; static column select.
		$rows = $wpdb->get_col( "SELECT keyword FROM {$wpdb->prefix}sb2_autolink WHERE keyword IS NOT NULL AND keyword != ''" );
		$set  = array();

		if ( ! is_array( $rows ) ) {
			return $set;
		}

		foreach ( $rows as $keyword ) {
			$normalized = self::normalize_keyword( $keyword );
			if ( '' !== $normalized ) {
				$set[ $normalized ] = true;
			}
		}

		return $set;
	}

	/**
	 * Autolink opportunity filter keys.
	 *
	 * @return string[]
	 */
	public static function get_autolink_filter_keys() {
		return array(
			'missing_rules',
			'enable_high_traffic',
		);
	}

	/**
	 * Scan autolink opportunities.
	 *
	 * @param string   $filter     missing_rules|enable_high_traffic.
	 * @param string[] $post_types Post types for enable filter.
	 * @return array
	 */
	public static function scan_autolink_opportunities( $filter, array $post_types = array( 'post', 'page' ) ) {
		if ( 'enable_high_traffic' === $filter ) {
			return self::scan_autolink_enable_high_traffic( $post_types );
		}

		return self::scan_autolink_missing_rules();
	}

	/**
	 * Describe an autolink target: slug, URL path, and title.
	 *
	 * @param int    $post_id  Resolved post ID (0 when unknown).
	 * @param string $page_url GSC page URL.
	 * @return array{slug: string, path: string, title: string}
	 */
	public static function describe_autolink_target( $post_id, $page_url ) {
		$slug  = '';
		$title = '';
		$path  = '';

		$post_id = (int) $post_id;
		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$slug  = (string) $post->post_name;
				$title = get_the_title( $post_id );
			}
		}

		$parsed = wp_parse_url( (string) $page_url );
		if ( is_array( $parsed ) && ! empty( $parsed['path'] ) ) {
			$path = $parsed['path'];
			if ( '' === $slug ) {
				$trimmed = trim( $parsed['path'], '/' );
				if ( '' !== $trimmed ) {
					$segments = explode( '/', $trimmed );
					$slug     = (string) end( $segments );
				}
			}
		}

		return array(
			'slug'  => $slug,
			'path'  => $path,
			'title' => $title,
		);
	}

	/**
	 * Max autolink keyword rules per target page (after near-duplicate reduction).
	 *
	 * @return int
	 */
	public static function get_max_autolink_keywords_per_page() {
		$max = (int) apply_filters(
			'seobooster_autolink_max_keywords_per_page',
			self::MAX_AUTOLINK_KEYWORDS_PER_PAGE
		);

		return max( 1, $max );
	}

	/**
	 * Stopwords stripped when comparing keyword near-duplicates.
	 *
	 * @return string[]
	 */
	private static function get_keyword_stopwords() {
		return array(
			'a',
			'an',
			'and',
			'at',
			'by',
			'for',
			'from',
			'how',
			'in',
			'is',
			'of',
			'on',
			'or',
			'the',
			'to',
			'vs',
			'with',
		);
	}

	/**
	 * Significant tokens for a keyword (lowercase, no punctuation, stopwords removed).
	 *
	 * @param string $keyword Keyword.
	 * @return string[]
	 */
	public static function keyword_token_set( $keyword ) {
		$normalized = strtolower( trim( (string) $keyword ) );
		$normalized = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $normalized );
		$tokens     = preg_split( '/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY );
		$stopwords  = self::get_keyword_stopwords();
		$filtered   = array();

		if ( ! is_array( $tokens ) ) {
			return array();
		}

		foreach ( $tokens as $token ) {
			if ( '' !== $token && ! in_array( $token, $stopwords, true ) ) {
				$filtered[] = $token;
			}
		}

		sort( $filtered );

		return array_values( array_unique( $filtered ) );
	}

	/**
	 * Normalized signature for near-duplicate keyword comparison.
	 *
	 * @param string $keyword Keyword.
	 * @return string
	 */
	public static function keyword_signature( $keyword ) {
		return implode( ' ', self::keyword_token_set( $keyword ) );
	}

	/**
	 * @param string[] $subset  Smaller token set.
	 * @param string[] $superset Larger token set.
	 * @return bool
	 */
	private static function token_set_is_subset( array $subset, array $superset ) {
		if ( empty( $subset ) ) {
			return false;
		}

		foreach ( $subset as $token ) {
			if ( ! in_array( $token, $superset, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Reduce near-duplicate keywords and cap to the per-page limit.
	 *
	 * @param array<int, array> $candidates Raw keyword candidates for one page.
	 * @return array{kept: array<int, array>, total_candidates: int}
	 */
	private static function dedupe_and_cap_page_keywords( array $candidates ) {
		$total_candidates = count( $candidates );

		usort(
			$candidates,
			function ( $a, $b ) {
				return ( $b['traffic_score'] <=> $a['traffic_score'] );
			}
		);

		$kept            = array();
		$kept_signatures = array();

		foreach ( $candidates as $entry ) {
			$signature = self::keyword_signature( $entry['keyword'] ?? '' );
			if ( '' === $signature || isset( $kept_signatures[ $signature ] ) ) {
				continue;
			}

			$tokens = self::keyword_token_set( $entry['keyword'] ?? '' );

			$skip = false;
			foreach ( $kept as $index => $kept_entry ) {
				$kept_tokens = self::keyword_token_set( $kept_entry['keyword'] ?? '' );
				if ( self::token_set_is_subset( $tokens, $kept_tokens ) ) {
					$skip = true;
					break;
				}
				if ( self::token_set_is_subset( $kept_tokens, $tokens ) ) {
					unset( $kept[ $index ] );
					unset( $kept_signatures[ self::keyword_signature( $kept_entry['keyword'] ?? '' ) ] );
				}
			}

			if ( $skip ) {
				continue;
			}

			$kept_signatures[ $signature ] = true;
			$kept[]                        = $entry;
		}

		$kept = array_values( $kept );
		$max  = self::get_max_autolink_keywords_per_page();
		if ( count( $kept ) > $max ) {
			$kept = array_slice( $kept, 0, $max );
		}

		$formatted = array();
		foreach ( $kept as $entry ) {
			$formatted[] = array(
				'keyword'     => $entry['keyword'],
				'query_id'    => (int) ( $entry['query_id'] ?? 0 ),
				'target_url'  => $entry['target_url'],
				'impressions' => (int) ( $entry['impressions'] ?? 0 ),
				'clicks'      => (int) ( $entry['clicks'] ?? 0 ),
				'position'    => round( (float) ( $entry['position'] ?? 0 ), 1 ),
			);
		}

		return array(
			'kept'             => $formatted,
			'total_candidates' => $total_candidates,
		);
	}

	/**
	 * High-value GSC queries without autolink rules, grouped by target page.
	 *
	 * Returns one entry per target page with a deduped, capped keyword list.
	 *
	 * @return array<int, array>
	 */
	public static function get_autolink_missing_rules_items() {
		$existing   = self::get_autolink_keyword_set();
		$keywords   = self::get_insight_keyword_rows( null, array(), true );
		$by_keyword = array();

		foreach ( $keywords as $row ) {
			$query       = trim( (string) ( $row['query'] ?? '' ) );
			$page        = trim( (string) ( $row['page'] ?? '' ) );
			$impressions = (int) ( $row['impressions'] ?? 0 );
			$clicks      = (int) ( $row['clicks'] ?? 0 );
			$word_count  = count( array_filter( explode( ' ', $query ) ) );

			if ( '' === $query || '' === $page || strlen( $query ) < 3 ) {
				continue;
			}

			if ( $word_count < 2 && strlen( $query ) < 8 ) {
				continue;
			}

			if ( $impressions < 50 && $clicks < 5 ) {
				continue;
			}

			$normalized = self::normalize_keyword( $query );
			if ( '' === $normalized || isset( $existing[ $normalized ] ) ) {
				continue;
			}

			$traffic_score = $impressions + ( $clicks * 10 );

			if ( ! isset( $by_keyword[ $normalized ] ) || $traffic_score > $by_keyword[ $normalized ]['traffic_score'] ) {
				$post_id                   = (int) url_to_postid( $page );
				$by_keyword[ $normalized ] = array(
					'query_id'      => (int) $row['id'],
					'keyword'       => $query,
					'target_url'    => $page,
					'post_id'       => $post_id,
					'edit_url'      => $post_id > 0 ? ( get_edit_post_link( $post_id, 'raw' ) ? get_edit_post_link( $post_id, 'raw' ) : '' ) : '',
					'clicks'        => $clicks,
					'impressions'   => $impressions,
					'position'      => round( (float) ( $row['position'] ?? 0 ), 1 ),
					'traffic_score' => $traffic_score,
				);
			}
		}

		$groups = array();
		foreach ( $by_keyword as $entry ) {
			$group_key = $entry['post_id'] > 0
				? 'post:' . $entry['post_id']
				: 'url:' . $entry['target_url'];

			if ( ! isset( $groups[ $group_key ] ) ) {
				$target               = self::describe_autolink_target( $entry['post_id'], $entry['target_url'] );
				$groups[ $group_key ] = array(
					'group_key'         => $group_key,
					'post_id'           => $entry['post_id'],
					'target_url'        => $entry['target_url'],
					'target_slug'       => $target['slug'],
					'target_path'       => $target['path'],
					'post_title'        => $target['title'],
					'edit_url'          => $entry['edit_url'],
					'group_traffic'     => 0,
					'group_impressions' => 0,
					'group_clicks'      => 0,
					'raw_keywords'      => array(),
				);
			}

			$groups[ $group_key ]['group_traffic']     += $entry['traffic_score'];
			$groups[ $group_key ]['group_impressions'] += $entry['impressions'];
			$groups[ $group_key ]['group_clicks']      += $entry['clicks'];
			$groups[ $group_key ]['raw_keywords'][]     = $entry;
		}

		uasort(
			$groups,
			function ( $a, $b ) {
				return ( $b['group_traffic'] <=> $a['group_traffic'] );
			}
		);

		$page_groups = array();
		foreach ( $groups as $group ) {
			$deduped = self::dedupe_and_cap_page_keywords( $group['raw_keywords'] );
			if ( empty( $deduped['kept'] ) ) {
				continue;
			}

			$page_groups[] = array(
				'group_key'         => $group['group_key'],
				'post_id'           => $group['post_id'],
				'target_url'        => $group['target_url'],
				'target_slug'       => $group['target_slug'],
				'target_path'       => $group['target_path'],
				'post_title'        => $group['post_title'],
				'edit_url'          => $group['edit_url'],
				'group_impressions' => $group['group_impressions'],
				'group_clicks'      => $group['group_clicks'],
				'group_traffic'     => $group['group_traffic'],
				'keywords'          => $deduped['kept'],
				'kept_count'        => count( $deduped['kept'] ),
				'total_candidates'  => $deduped['total_candidates'],
			);
		}

		return $page_groups;
	}

	/**
	 * Flatten page groups into per-keyword rule rows for batch processing.
	 *
	 * @param array<int, array> $page_groups Page groups from get_autolink_missing_rules_items().
	 * @return array<int, array>
	 */
	public static function flatten_autolink_page_groups( array $page_groups ) {
		$items = array();

		foreach ( $page_groups as $group ) {
			foreach ( $group['keywords'] ?? array() as $kw ) {
				$items[] = array(
					'query_id'   => (int) ( $kw['query_id'] ?? 0 ),
					'keyword'    => $kw['keyword'] ?? '',
					'target_url' => $kw['target_url'] ?? ( $group['target_url'] ?? '' ),
					'post_id'    => (int) ( $group['post_id'] ?? 0 ),
					'group_key'  => $group['group_key'] ?? '',
				);
			}
		}

		return $items;
	}

	/**
	 * High-value GSC queries without autolink rules (page-grouped for UI).
	 *
	 * @return array
	 */
	public static function scan_autolink_missing_rules() {
		$page_groups = self::get_autolink_missing_rules_items();
		$total_pages = count( $page_groups );
		$all_ids     = array();

		foreach ( $page_groups as $group ) {
			foreach ( $group['keywords'] ?? array() as $kw ) {
				$query_id = (int) ( $kw['query_id'] ?? 0 );
				if ( $query_id > 0 ) {
					$all_ids[] = $query_id;
				}
			}
		}

		return array(
			'items'         => $page_groups,
			'total_found'   => $total_pages,
			'preview_limit' => $total_pages,
			'preview_count' => $total_pages,
			'all_ids'       => $all_ids,
			'filter'        => 'missing_rules',
		);
	}

	/**
	 * Published posts with GSC traffic but autolink disabled.
	 *
	 * @param string[] $post_types Post types.
	 * @return array
	 */
	public static function scan_autolink_enable_high_traffic( array $post_types ) {
		$matching    = self::get_autolink_enable_high_traffic_items( $post_types );
		$total_found = count( $matching );
		$preview     = array_slice( $matching, 0, self::PREVIEW_LIMIT );

		return array(
			'items'         => $preview,
			'total_found'   => $total_found,
			'preview_limit' => self::PREVIEW_LIMIT,
			'preview_count' => count( $preview ),
			'all_ids'       => array_column( $matching, 'post_id' ),
			'filter'        => 'enable_high_traffic',
		);
	}

	/**
	 * Full list of high-traffic pages with autolink off (90-day window, max 200 pages).
	 *
	 * @param string[] $post_types Post types.
	 * @return array<int, array>
	 */
	public static function get_autolink_enable_high_traffic_items( array $post_types ) {
		global $wpdb;

		$matching   = array();
		$min_clicks = 10;
		$window     = (int) GSC_History::INSIGHT_WINDOW_DAYS;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prefixed table names; HAVING uses %d.
		$page_clicks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT qk.page, COALESCE(SUM(qkh.clicks), 0) AS clicks
            FROM {$wpdb->prefix}sb2_query_keywords AS qk
            LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh
                ON qk.id = qkh.query_keywords_id
                AND qkh.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
            GROUP BY qk.page
            HAVING clicks >= %d
            ORDER BY clicks DESC
            LIMIT 200",
				$window,
				(int) $min_clicks
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $page_clicks ) ) {
			$page_clicks = array();
		}

		foreach ( $page_clicks as $row ) {
			$page = (string) ( $row['page'] ?? '' );
			if ( '' === $page ) {
				continue;
			}

			$post_id = (int) url_to_postid( $page );
			if ( $post_id <= 0 ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, $post_types, true ) ) {
				continue;
			}

			$autolink = get_post_meta( $post_id, '_sbp-autolink', true );
			if ( 'yes' === $autolink ) {
				continue;
			}

			$matching[] = array(
				'id'        => $post_id,
				'post_id'   => $post_id,
				'title'     => get_the_title( $post_id ),
				'post_type' => $post->post_type,
				'edit_url'  => get_edit_post_link( $post_id, 'raw' ) ? get_edit_post_link( $post_id, 'raw' ) : '',
				'page_url'  => $page,
				'clicks'    => (int) ( $row['clicks'] ?? 0 ),
			);
		}

		return $matching;
	}

	/**
	 * Human label for opportunity type.
	 *
	 * @param string $type Type key.
	 * @return string
	 */
	public static function get_opportunity_type_label( $type ) {
		switch ( $type ) {
			case 'striking_distance':
				return __( 'Striking distance', 'seo-booster' );
			case 'low_ctr':
				return __( 'Low CTR (good position)', 'seo-booster' );
			case 'high_impressions_low_clicks':
				return __( 'High impressions, low clicks', 'seo-booster' );
			default:
				return $type;
		}
	}

	/**
	 * Scan pages with declining GSC clicks (recent 30d vs prior 30d).
	 *
	 * Thresholds align with Analysis\Checks\Gsc_Checks::check_gsc_content_freshness()
	 * windows; this method aggregates by page and ranks by click decline.
	 *
	 * @return array{items: array, total_found: int, preview_limit: int, preview_count: int, all_ids: int[], has_history: bool}
	 */
	public static function scan_content_decay() {
		if ( ! self::decay_has_sufficient_history() ) {
			return array(
				'items'         => array(),
				'total_found'   => 0,
				'preview_limit' => self::PREVIEW_LIMIT,
				'preview_count' => 0,
				'all_ids'       => array(),
				'has_history'   => false,
			);
		}

		$matching    = self::build_decay_items_from_rows( self::query_decay_page_rows() );
		$total_found = count( $matching );
		$preview     = array_slice( $matching, 0, self::PREVIEW_LIMIT );

		return array(
			'items'         => $preview,
			'total_found'   => $total_found,
			'preview_limit' => self::PREVIEW_LIMIT,
			'preview_count' => count( $preview ),
			'all_ids'       => array_column( $matching, 'post_id' ),
			'has_history'   => true,
		);
	}

	/**
	 * Top declining pages for Ask / dashboard samples (lightweight).
	 *
	 * @param int $limit Max rows.
	 * @return array<int, array>
	 */
	public static function get_content_decay_preview_items( $limit = 8 ) {
		$limit = max( 1, (int) $limit );

		if ( ! self::decay_has_sufficient_history() ) {
			return array();
		}

		return self::build_decay_items_from_rows( self::query_decay_page_rows( $limit ) );
	}

	/**
	 * Whether history spans enough days for decay comparison.
	 *
	 * @return bool
	 */
	private static function decay_has_sufficient_history() {
		global $wpdb;

		$window    = (int) self::DECAY_WINDOW_DAYS;
		$table_qkh = $wpdb->prefix . 'sb2_query_keywords_history';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prefixed table name; read-only aggregate.
		$span_days = (int) $wpdb->get_var(
			"SELECT DATEDIFF(MAX(date), MIN(date)) FROM {$table_qkh}"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $span_days >= ( $window * 2 - 1 );
	}

	/**
	 * SQL rows for page-level content decay (optional LIMIT after sort).
	 *
	 * @param int|null $limit Max pages, or null for all matches.
	 * @return array<int, array>
	 */
	private static function query_decay_page_rows( $limit = null ) {
		global $wpdb;

		$window    = (int) self::DECAY_WINDOW_DAYS;
		$table_qk  = $wpdb->prefix . 'sb2_query_keywords';
		$table_qkh = $wpdb->prefix . 'sb2_query_keywords_history';

		$limit_sql = '';
		$span      = $window * 2;
		$min_prev  = (int) self::DECAY_MIN_PREVIOUS_CLICKS;
		$min_impr  = (int) self::DECAY_MIN_RECENT_IMPRESSIONS;
		$min_decl  = (int) self::DECAY_MIN_DECLINE_PCT;

		$sql = "SELECT
				qk.page,
				SUM(CASE WHEN qkh.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY) THEN qkh.clicks ELSE 0 END) AS recent_clicks,
				SUM(CASE WHEN qkh.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY) THEN qkh.impressions ELSE 0 END) AS recent_impressions,
				SUM(CASE WHEN qkh.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
					AND qkh.date < DATE_SUB(CURDATE(), INTERVAL %d DAY) THEN qkh.clicks ELSE 0 END) AS previous_clicks,
				SUM(CASE WHEN qkh.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
					AND qkh.date < DATE_SUB(CURDATE(), INTERVAL %d DAY) THEN qkh.impressions ELSE 0 END) AS previous_impressions
			FROM {$table_qk} AS qk
			LEFT JOIN {$table_qkh} AS qkh
				ON qk.id = qkh.query_keywords_id
				AND qkh.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
			WHERE qk.page IS NOT NULL AND qk.page != ''
			GROUP BY qk.page
			HAVING previous_clicks >= %d
				AND recent_impressions >= %d
				AND ((previous_clicks - recent_clicks) / previous_clicks) * 100 >= %d
			ORDER BY ((previous_clicks - recent_clicks) / previous_clicks) DESC, previous_clicks DESC";

		if ( null !== $limit ) {
			$limit_sql = ' LIMIT %d';
			$sql      .= $limit_sql;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Prefixed tables; dynamic LIMIT; $sql built above.
		if ( null !== $limit ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					$sql,
					$window,
					$window,
					$span,
					$window,
					$span,
					$window,
					$span,
					$min_prev,
					$min_impr,
					$min_decl,
					max( 1, (int) $limit )
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					$sql,
					$window,
					$window,
					$span,
					$window,
					$span,
					$window,
					$span,
					$min_prev,
					$min_impr,
					$min_decl
				),
				ARRAY_A
			);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Enrich decay SQL rows into publishable page items.
	 *
	 * @param array<int, array> $rows Rows from query_decay_page_rows().
	 * @return array<int, array>
	 */
	private static function build_decay_items_from_rows( array $rows ) {
		$matching = array();

		foreach ( $rows as $row ) {
			$page            = (string) ( $row['page'] ?? '' );
			$recent_clicks   = (int) ( $row['recent_clicks'] ?? 0 );
			$previous_clicks = (int) ( $row['previous_clicks'] ?? 0 );
			$recent_impr     = (int) ( $row['recent_impressions'] ?? 0 );
			$previous_impr   = (int) ( $row['previous_impressions'] ?? 0 );

			if ( '' === $page || $previous_clicks <= 0 ) {
				continue;
			}

			$decline_pct = ( ( $previous_clicks - $recent_clicks ) / $previous_clicks ) * 100;

			$post_id = self::resolve_publishable_post_id( $page );
			if ( $post_id <= 0 ) {
				continue;
			}

			$permalink = get_permalink( $post_id );
			$view_url  = $permalink ? $permalink : $page;
			$status    = class_exists( __NAMESPACE__ . '\\Tools_Needs_Analysis' )
				? Tools_Needs_Analysis::get_analysis_status( $post_id )
				: array(
					'label'       => '',
					'never'       => false,
					'stale'       => false,
					'issue_count' => 0,
				);

			$item = array(
				'post_id'              => $post_id,
				'title'                => get_the_title( $post_id ),
				'post_type'            => get_post_type( $post_id ),
				'slug'                 => get_post_field( 'post_name', $post_id ),
				'edit_url'             => get_edit_post_link( $post_id, 'raw' ) ? get_edit_post_link( $post_id, 'raw' ) : '',
				'view_url'             => $view_url,
				'page_url'             => $page,
				'recent_clicks'        => $recent_clicks,
				'previous_clicks'      => $previous_clicks,
				'recent_impressions'   => $recent_impr,
				'previous_impressions' => $previous_impr,
				'decline_pct'          => round( $decline_pct, 1 ),
				'analysis_status'      => $status['label'],
				'never'                => ! empty( $status['never'] ),
				'stale'                => ! empty( $status['stale'] ),
				'issue_count'          => (int) $status['issue_count'],
			);

			if ( $status['issue_count'] > 0 && '' !== $view_url ) {
				$item['possibilities_url'] = admin_url(
					'admin.php?page=sb2_seo_issues&s=' . rawurlencode( $view_url )
				);
			}

			$matching[] = $item;
		}

		usort(
			$matching,
			static function ( $a, $b ) {
				$by_decline = $b['decline_pct'] <=> $a['decline_pct'];
				if ( 0 !== $by_decline ) {
					return $by_decline;
				}
				return $b['previous_clicks'] <=> $a['previous_clicks'];
			}
		);

		return $matching;
	}

	/**
	 * Count publishable pages matching content-decay thresholds (for weekly email).
	 *
	 * Same windows and thresholds as scan_content_decay(); skips UI payloads.
	 *
	 * @return int
	 */
	public static function count_content_decay_pages() {
		global $wpdb;

		$window    = (int) self::DECAY_WINDOW_DAYS;
		$table_qk  = $wpdb->prefix . 'sb2_query_keywords';
		$table_qkh = $wpdb->prefix . 'sb2_query_keywords_history';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prefixed table name; read-only aggregate.
		$span_days = (int) $wpdb->get_var(
			"SELECT DATEDIFF(MAX(date), MIN(date)) FROM {$table_qkh}"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $span_days < ( $window * 2 - 1 ) ) {
			return 0;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prefixed tables; INTERVAL/HAVING use %d.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
				qk.page,
				SUM(CASE WHEN qkh.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY) THEN qkh.clicks ELSE 0 END) AS recent_clicks,
				SUM(CASE WHEN qkh.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY) THEN qkh.impressions ELSE 0 END) AS recent_impressions,
				SUM(CASE WHEN qkh.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
					AND qkh.date < DATE_SUB(CURDATE(), INTERVAL %d DAY) THEN qkh.clicks ELSE 0 END) AS previous_clicks
			FROM {$table_qk} AS qk
			LEFT JOIN {$table_qkh} AS qkh
				ON qk.id = qkh.query_keywords_id
				AND qkh.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
			WHERE qk.page IS NOT NULL AND qk.page != ''
			GROUP BY qk.page
			HAVING previous_clicks >= %d
				AND recent_impressions >= %d
				AND ((previous_clicks - recent_clicks) / previous_clicks) * 100 >= %d",
				$window,
				$window,
				$window * 2,
				$window,
				$window * 2,
				(int) self::DECAY_MIN_PREVIOUS_CLICKS,
				(int) self::DECAY_MIN_RECENT_IMPRESSIONS,
				(int) self::DECAY_MIN_DECLINE_PCT
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $rows ) ) {
			return 0;
		}

		$count     = 0;
		$page_post = array();

		foreach ( $rows as $row ) {
			$page            = (string) ( $row['page'] ?? '' );
			$recent_clicks   = (int) ( $row['recent_clicks'] ?? 0 );
			$previous_clicks = (int) ( $row['previous_clicks'] ?? 0 );

			if ( '' === $page || $previous_clicks <= 0 ) {
				continue;
			}

			$decline_pct = ( ( $previous_clicks - $recent_clicks ) / $previous_clicks ) * 100;

			if ( ! array_key_exists( $page, $page_post ) ) {
				$page_post[ $page ] = self::resolve_publishable_post_id( $page );
			}

			if ( $page_post[ $page ] > 0 ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Count unique publishable pages with any GSC opportunity type (for weekly email).
	 *
	 * Uses the same classification as scan_opportunities() with all filters active.
	 *
	 * @return int
	 */
	public static function count_gsc_opportunity_pages() {
		$filters   = self::get_opportunity_filter_keys();
		$keywords  = self::get_insight_keyword_rows( null, $filters );
		$page_post = array();
		$post_ids  = array();

		foreach ( $keywords as $row ) {
			$types = self::classify_keyword_opportunities( $row );
			$types = array_values( array_intersect( $types, $filters ) );

			if ( empty( $types ) ) {
				continue;
			}

			$page = isset( $row['page'] ) ? (string) $row['page'] : '';
			if ( '' === $page ) {
				continue;
			}

			if ( ! array_key_exists( $page, $page_post ) ) {
				$page_post[ $page ] = self::resolve_publishable_post_id( $page );
			}

			$post_id = $page_post[ $page ];
			if ( $post_id <= 0 ) {
				continue;
			}

			$post_ids[ $post_id ] = true;
		}

		return count( $post_ids );
	}
}
