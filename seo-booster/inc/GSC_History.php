<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded GSC keyword history reads for mini charts and insight scanners.
 *
 * Table totals (all-time SUM/AVG) stay on callers that need them; this class
 * caps chart payloads to real calendar days only (no weekly rollups).
 */
class GSC_History {

	const CHART_WINDOW_DAYS     = 90;
	const CHART_MAX_POINTS      = 90;
	const FULL_CHART_MAX_POINTS = 180;
	const INSIGHT_WINDOW_DAYS   = 90;

	/**
	 * Row indices to sample when downsizing a date-ordered series.
	 *
	 * @param int $count Total rows (0-based max index is count - 1).
	 * @param int $max   Max indices to return.
	 * @return int[]
	 */
	public static function compute_sample_indices( $count, $max ) {
		$count = (int) $count;
		$max   = max( 1, (int) $max );

		if ( $count <= 0 ) {
			return array();
		}

		if ( $count <= $max ) {
			return range( 0, $count - 1 );
		}

		$indices  = array();
		$last_idx = $count - 1;

		for ( $i = 0; $i < $max; $i++ ) {
			$indices[] = ( ( $max - 1 ) === $i ) ? $last_idx : (int) round( ( $i * $last_idx ) / ( $max - 1 ) );
		}

		return $indices;
	}

	/**
	 * Keep first, last, and evenly spaced real daily rows (no averaging).
	 *
	 * @param array<int, array<string, mixed>> $rows Ordered by date ascending.
	 * @param int                                $max  Max points to return.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sample_real_days( array $rows, $max ) {
		$max   = max( 1, (int) $max );
		$count = count( $rows );

		if ( 0 === $count ) {
			return array();
		}

		if ( $count <= $max ) {
			return $rows;
		}

		$sampled = array();
		foreach ( self::compute_sample_indices( $count, $max ) as $idx ) {
			$sampled[] = $rows[ $idx ];
		}

		return $sampled;
	}

	/**
	 * Sparkline series for one or more keywords (Overview Trends + editor metabox).
	 *
	 * @param int[] $keyword_ids Keyword row IDs from sb2_query_keywords.
	 * @return array<int, array<int, array<string, mixed>>> keyword_id => formatted points.
	 */
	public static function get_chart_series( array $keyword_ids ) {
		global $wpdb;

		$keyword_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $keyword_ids ),
					static function ( $id ) {
						return $id > 0;
					}
				)
			)
		);

		if ( empty( $keyword_ids ) ) {
			return array();
		}

		$table   = $wpdb->prefix . 'sb2_query_keywords_history';
		$window  = (int) self::CHART_WINDOW_DAYS;
		$max_pts = (int) self::CHART_MAX_POINTS;

		$meta_by_id = self::get_keyword_span_meta( $keyword_ids, $table );

		$placeholders = implode( ',', array_fill( 0, count( $keyword_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- IN() placeholders; prefixed table.
		$recent_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT query_keywords_id, date, clicks, impressions, position
				FROM {$table}
				WHERE query_keywords_id IN ({$placeholders})
				AND date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
				ORDER BY query_keywords_id, date ASC",
				...array_merge( $keyword_ids, array( $window ) )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		if ( ! is_array( $recent_rows ) ) {
			$recent_rows = array();
		}

		$grouped_recent = array();
		foreach ( $recent_rows as $row ) {
			$kid = (int) ( $row['query_keywords_id'] ?? 0 );
			if ( $kid > 0 ) {
				$grouped_recent[ $kid ][] = $row;
			}
		}

		$needs_fallback = array();
		foreach ( $keyword_ids as $kid ) {
			if ( ! isset( $grouped_recent[ $kid ] ) || empty( $grouped_recent[ $kid ] ) ) {
				$needs_fallback[] = $kid;
			}
		}

		$grouped_fallback = self::fetch_stale_keyword_fallback_rows( $needs_fallback, $table, $max_pts );

		$cutoff_date = gmdate( 'Y-m-d', strtotime( '-' . $window . ' days' ) );
		$result      = array();

		foreach ( $keyword_ids as $kid ) {
			$is_historical = false;
			$display_rows  = array();

			if ( isset( $grouped_recent[ $kid ] ) && ! empty( $grouped_recent[ $kid ] ) ) {
				$display_rows = $grouped_recent[ $kid ];
			} elseif ( isset( $grouped_fallback[ $kid ] ) ) {
				$display_rows  = $grouped_fallback[ $kid ];
				$is_historical = true;
			}

			if ( empty( $display_rows ) ) {
				$result[ $kid ] = array();
				continue;
			}

			$display_rows = self::sample_real_days( $display_rows, $max_pts );

			$span           = isset( $meta_by_id[ $kid ] ) ? $meta_by_id[ $kid ] : array();
			$result[ $kid ] = self::format_series_with_meta( $display_rows, $span, $is_historical, $cutoff_date );
		}

		return $result;
	}

	/**
	 * Expanded chart series (explicit "Show full history" only).
	 *
	 * @param int $keyword_id Keyword row ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_full_chart_series( $keyword_id ) {
		global $wpdb;

		$keyword_id = (int) $keyword_id;
		if ( $keyword_id <= 0 ) {
			return array();
		}

		$table   = $wpdb->prefix . 'sb2_query_keywords_history';
		$max_pts = (int) self::FULL_CHART_MAX_POINTS;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prefixed table; prepared id.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE query_keywords_id = %d",
				$keyword_id
			)
		);

		if ( $total <= 0 ) {
			return array();
		}

		if ( $total <= $max_pts ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT date, clicks, impressions, position
					FROM {$table}
					WHERE query_keywords_id = %d
					ORDER BY date ASC",
					$keyword_id
				),
				ARRAY_A
			);
		} else {
			$offsets = self::compute_sample_indices( $total, $max_pts );
			$rows    = self::fetch_rows_at_offsets( $keyword_id, $offsets, $table );
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array();
		}

		$meta_by_id = self::get_keyword_span_meta( array( $keyword_id ), $table );
		$span       = isset( $meta_by_id[ $keyword_id ] ) ? $meta_by_id[ $keyword_id ] : array();
		$cutoff     = gmdate( 'Y-m-d', strtotime( '-' . self::CHART_WINDOW_DAYS . ' days' ) );

		$has_recent = false;
		foreach ( $rows as $row ) {
			if ( isset( $row['date'] ) && $row['date'] >= $cutoff ) {
				$has_recent = true;
				break;
			}
		}

		return self::format_series_with_meta( $rows, $span, ! $has_recent, $cutoff );
	}

	/**
	 * Fetch history rows at specific 0-based offsets (one UNION ALL round trip).
	 *
	 * @param int    $keyword_id Keyword row ID.
	 * @param int[]  $offsets    Ordered offsets into date ASC series.
	 * @param string $table      History table name.
	 * @return array<int, array<string, mixed>>
	 */
	private static function fetch_rows_at_offsets( $keyword_id, array $offsets, $table ) {
		global $wpdb;

		$keyword_id = (int) $keyword_id;
		if ( $keyword_id <= 0 || empty( $offsets ) ) {
			return array();
		}

		$offsets = array_values(
			array_unique(
				array_map( 'intval', $offsets )
			)
		);

		$parts   = array();
		$prepare = array();

		foreach ( $offsets as $offset ) {
			$parts[]   = "(SELECT date, clicks, impressions, position FROM {$table} WHERE query_keywords_id = %d ORDER BY date ASC LIMIT 1 OFFSET %d)";
			$prepare[] = $keyword_id;
			$prepare[] = max( 0, $offset );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- UNION subqueries; dynamic count.
		$sql  = implode( ' UNION ALL ', $parts );
		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, ...$prepare ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Last stored window per keyword when no recent (90d) history exists.
	 *
	 * @param int[]  $keyword_ids Stale keyword IDs.
	 * @param string $table       History table name.
	 * @param int    $max_pts     Max days per keyword.
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	private static function fetch_stale_keyword_fallback_rows( array $keyword_ids, $table, $max_pts ) {
		global $wpdb;

		$keyword_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $keyword_ids ),
					static function ( $id ) {
						return $id > 0;
					}
				)
			)
		);

		if ( empty( $keyword_ids ) ) {
			return array();
		}

		$window       = (int) self::CHART_WINDOW_DAYS;
		$placeholders = implode( ',', array_fill( 0, count( $keyword_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- IN() placeholders; prefixed table.
		$fallback_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT h.query_keywords_id, h.date, h.clicks, h.impressions, h.position
				FROM {$table} AS h
				INNER JOIN (
					SELECT query_keywords_id, MAX(date) AS max_date
					FROM {$table}
					WHERE query_keywords_id IN ({$placeholders})
					GROUP BY query_keywords_id
				) AS m ON h.query_keywords_id = m.query_keywords_id
				WHERE h.date >= DATE_SUB(m.max_date, INTERVAL %d DAY)
				ORDER BY h.query_keywords_id, h.date ASC",
				...array_merge( $keyword_ids, array( $window ) )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		if ( ! is_array( $fallback_rows ) || empty( $fallback_rows ) ) {
			return array();
		}

		$grouped = array();
		foreach ( $fallback_rows as $row ) {
			$kid = (int) ( $row['query_keywords_id'] ?? 0 );
			if ( $kid > 0 ) {
				$grouped[ $kid ][] = $row;
			}
		}

		foreach ( $grouped as $kid => $rows ) {
			if ( count( $rows ) > $max_pts ) {
				$grouped[ $kid ] = array_slice( $rows, -$max_pts );
			}
		}

		return $grouped;
	}

	/**
	 * MIN/MAX/COUNT per keyword for chart metadata.
	 *
	 * @param int[]  $keyword_ids IDs.
	 * @param string $table       History table name.
	 * @return array<int, array{min_date: string, max_date: string, total: int}>
	 */
	private static function get_keyword_span_meta( array $keyword_ids, $table ) {
		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $keyword_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- IN() placeholders; prefixed table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT query_keywords_id,
					MIN(date) AS min_date,
					MAX(date) AS max_date,
					COUNT(*) AS total
				FROM {$table}
				WHERE query_keywords_id IN ({$placeholders})
				GROUP BY query_keywords_id",
				...$keyword_ids
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$out = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}

		foreach ( $rows as $row ) {
			$kid = (int) ( $row['query_keywords_id'] ?? 0 );
			if ( $kid <= 0 ) {
				continue;
			}
			$out[ $kid ] = array(
				'min_date' => (string) ( $row['min_date'] ?? '' ),
				'max_date' => (string) ( $row['max_date'] ?? '' ),
				'total'    => (int) ( $row['total'] ?? 0 ),
			);
		}

		return $out;
	}

	/**
	 * Attach UI metadata expected by Overview and metabox chart renderers.
	 *
	 * @param array<int, array<string, mixed>> $rows           Daily rows (date ascending).
	 * @param array<string, mixed>              $span           min_date, max_date, total.
	 * @param bool                              $is_historical  Fallback series (no recent window data).
	 * @param string                            $cutoff_date    Y-m-d window start.
	 * @return array<int, array<string, mixed>>
	 */
	private static function format_series_with_meta( array $rows, array $span, $is_historical, $cutoff_date ) {
		if ( empty( $rows ) ) {
			return array();
		}

		$first_date = (string) ( $span['min_date'] ?? $rows[0]['date'] );
		$last_date  = (string) ( $span['max_date'] ?? $rows[ count( $rows ) - 1 ]['date'] );
		$total_rows = (int) ( $span['total'] ?? count( $rows ) );

		$last_visit_date       = $last_date;
		$days_since_last_visit = ( strtotime( 'today' ) - strtotime( $last_visit_date ) ) / ( 24 * 60 * 60 );
		$total_days            = ( strtotime( $last_date ) - strtotime( $first_date ) ) / ( 24 * 60 * 60 );

		$has_historical_data = $total_rows > self::CHART_MAX_POINTS
			|| ( '' !== $first_date && $first_date < $cutoff_date );

		$formatted = array();
		foreach ( $rows as $row ) {
			$entry                          = self::format_point( $row );
			$entry['last_visit_date']       = $last_visit_date;
			$entry['days_since_last_visit'] = $days_since_last_visit;
			$entry['is_historical']         = $is_historical;
			$entry['has_historical_data']   = $has_historical_data;
			$entry['total_days']            = $total_days;
			$entry['first_date']            = $first_date;
			$entry['last_date']             = $last_date;
			$formatted[]                    = $entry;
		}

		return $formatted;
	}

	/**
	 * @param array<string, mixed>|object $row History row.
	 * @return array<string, mixed>
	 */
	private static function format_point( $row ) {
		if ( is_object( $row ) ) {
			$row = (array) $row;
		}

		return array(
			'date'        => (string) ( $row['date'] ?? '' ),
			'position'    => (float) ( $row['position'] ?? 0 ),
			'clicks'      => (int) ( $row['clicks'] ?? 0 ),
			'impressions' => (int) ( $row['impressions'] ?? 0 ),
		);
	}
}
