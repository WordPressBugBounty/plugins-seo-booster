<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks human visits referred from AI answer engines (ChatGPT, Perplexity, etc.).
 */
class AI_Referral_Tracker {

	const OPTION_TRACKING = 'seobooster_ai_referral_tracking';

	/** @var array|null Pending referral queued during template_redirect. */
	private static $pending_referral = null;

	/** @var bool Whether shutdown finalize hook is registered. */
	private static $shutdown_registered = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( is_admin() ) {
			return;
		}

		add_action( 'template_redirect', array( __CLASS__, 'handle_request' ), 6 );
	}

	/**
	 * Whether AI referral tracking is enabled.
	 *
	 * @return bool
	 */
	public static function is_tracking_enabled() {
		return get_option( self::OPTION_TRACKING, 'on' ) === 'on';
	}

	/**
	 * Known AI referrer host => display label map.
	 *
	 * @return array<string, string>
	 */
	public static function get_referral_sources() {
		$sources = array(
			'chatgpt.com'           => 'ChatGPT',
			'chat.openai.com'       => 'ChatGPT',
			'perplexity.ai'         => 'Perplexity',
			'claude.ai'             => 'Claude',
			'gemini.google.com'     => 'Gemini',
			'copilot.microsoft.com' => 'Copilot',
		);

		/**
		 * Filter AI referral source host => label map.
		 *
		 * @param array<string, string> $sources Host fragment => label.
		 */
		return apply_filters( 'seobooster_ai_referral_sources', $sources );
	}

	/**
	 * Handle a frontend request and queue referral recording.
	 *
	 * @return void
	 */
	public static function handle_request() {
		if ( ! self::is_tracking_enabled() ) {
			return;
		}

		if ( AI_Bot_Tracker::should_skip_tracking_request() ) {
			return;
		}

		if ( AI_Bot_Tracker::is_ai_bot_user_agent() ) {
			return;
		}

		$matched = self::match_referrer();
		if ( empty( $matched ) ) {
			return;
		}

		self::$pending_referral = $matched;

		if ( ! self::$shutdown_registered ) {
			add_action( 'shutdown', array( __CLASS__, 'finalize_pending_referral' ), 999 );
			self::$shutdown_registered = true;
		}
	}

	/**
	 * Finalize and persist a queued referral after the response is known.
	 *
	 * @return void
	 */
	public static function finalize_pending_referral() {
		if ( empty( self::$pending_referral ) || ! did_action( 'wp' ) ) {
			return;
		}

		$pending                = self::$pending_referral;
		self::$pending_referral = null;

		$status_code = (int) http_response_code();
		if ( $status_code < 100 ) {
			$status_code = 200;
		}

		if ( $status_code === 404 || ( function_exists( 'is_404' ) && is_404() ) ) {
			return;
		}

		$request_path = self::get_request_path();
		if ( $request_path === '' ) {
			return;
		}

		$normalized = AI_Bot_Tracker::get_current_request_object();
		$object_id  = (int) $normalized['object_id'];

		if ( AI_Bot_Tracker::is_track_mapped_only() && $object_id <= 0 ) {
			return;
		}

		$url_for_hash   = ! empty( $normalized['url'] ) ? $normalized['url'] : untrailingslashit( strtolower( esc_url_raw( home_url( $request_path ) ) ) );
		$url_for_hash   = substr( $url_for_hash, 0, 500 );
		$url_hash       = hash( 'sha256', $url_for_hash );
		$normalized_url = $url_for_hash;

		self::write_referral(
			$pending['source'],
			$pending['referrer_host'],
			$request_path,
			$normalized_url,
			$url_hash,
			$object_id,
			$normalized['object_type']
		);
	}

	/**
	 * Match HTTP referer against known AI sources.
	 *
	 * @return array{source: string, referrer_host: string}|array
	 */
	private static function match_referrer() {
		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			return array();
		}

		$referer = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
		$host    = wp_parse_url( $referer, PHP_URL_HOST );
		if ( ! is_string( $host ) || $host === '' ) {
			return array();
		}

		$host = strtolower( $host );

		foreach ( self::get_referral_sources() as $fragment => $label ) {
			if ( $host === $fragment ) {
				return array(
					'source'        => $label,
					'referrer_host' => substr( $host, 0, 120 ),
				);
			}
			$suffix = '.' . $fragment;
			if ( strlen( $host ) > strlen( $suffix ) && substr( $host, -strlen( $suffix ) ) === $suffix ) {
				return array(
					'source'        => $label,
					'referrer_host' => substr( $host, 0, 120 ),
				);
			}
		}

		return array();
	}

	/**
	 * Raw request path from the current request.
	 *
	 * @return string
	 */
	private static function get_request_path() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		$path = wp_unslash( $_SERVER['REQUEST_URI'] );
		$path = strtok( $path, '?' );
		$path = '/' . ltrim( $path, '/' );

		return substr( sanitize_text_field( $path ), 0, 500 );
	}

	/**
	 * Write an aggregated referral row.
	 *
	 * @param string $source         Display source label.
	 * @param string $referrer_host  Referrer host.
	 * @param string $landing_path   Landing path.
	 * @param string $normalized_url Normalized landing URL.
	 * @param string $url_hash       URL hash.
	 * @param int    $object_id      Mapped object ID.
	 * @param string $object_type    Mapped object type.
	 * @return void
	 */
	private static function write_referral( $source, $referrer_host, $landing_path, $normalized_url, $url_hash, $object_id, $object_type ) {
		global $wpdb;

		$hit_date = current_time( 'Y-m-d' );
		$table    = $wpdb->prefix . 'sb2_ai_referrals';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table}
				(source, referrer_host, landing_path, normalized_url, url_hash, object_id, object_type, hit_date, first_seen, last_seen, visits)
				VALUES (%s, %s, %s, %s, %s, %d, %s, %s, NOW(), NOW(), 1)
				ON DUPLICATE KEY UPDATE
				visits = visits + 1,
				last_seen = NOW(),
				normalized_url = IF(normalized_url = '' OR normalized_url IS NULL, %s, normalized_url),
				landing_path = IF(landing_path = '' OR landing_path IS NULL, %s, landing_path),
				object_id = IF(object_id IS NULL OR object_id = 0, %d, object_id),
				object_type = IF(object_type IS NULL OR object_type = '', %s, object_type)",
				$source,
				$referrer_host,
				$landing_path,
				$normalized_url,
				$url_hash,
				$object_id,
				$object_type,
				$hit_date,
				$normalized_url,
				$landing_path,
				$object_id,
				$object_type
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * SQL date threshold for reporting windows.
	 *
	 * @param int $days Number of days.
	 * @return string
	 */
	public static function get_since_date( $days ) {
		return AI_Bot_Tracker::get_since_date( $days );
	}

	/**
	 * Summary stats for the reporting window.
	 *
	 * @param int $days Days to include.
	 * @return array{total_visits: int, unique_sources: int, unique_pages: int, top_source: string, top_source_visits: int}
	 */
	public static function get_summary( $days = 30 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'sb2_ai_referrals';
		$since = self::get_since_date( $days );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COALESCE(SUM(visits), 0) AS total_visits,
					COUNT(DISTINCT source) AS unique_sources,
					COUNT(DISTINCT url_hash) AS unique_pages
				FROM {$table}
				WHERE hit_date >= %s",
				$since
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
		$top = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT source, SUM(visits) AS visits
				FROM {$table}
				WHERE hit_date >= %s
				GROUP BY source
				ORDER BY visits DESC
				LIMIT 1",
				$since
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'total_visits'      => isset( $row['total_visits'] ) ? (int) $row['total_visits'] : 0,
			'unique_sources'    => isset( $row['unique_sources'] ) ? (int) $row['unique_sources'] : 0,
			'unique_pages'      => isset( $row['unique_pages'] ) ? (int) $row['unique_pages'] : 0,
			'top_source'        => isset( $top['source'] ) ? (string) $top['source'] : '',
			'top_source_visits' => isset( $top['visits'] ) ? (int) $top['visits'] : 0,
		);
	}

	/**
	 * Daily referral visit totals for charting.
	 *
	 * @param int $days Days to include.
	 * @return array<int, array{hit_date: string, visits: int}>
	 */
	public static function get_visits_by_day( $days = 30 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'sb2_ai_referrals';
		$since = self::get_since_date( $days );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT hit_date, SUM(visits) AS visits
				FROM {$table}
				WHERE hit_date >= %s
				GROUP BY hit_date
				ORDER BY hit_date ASC",
				$since
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Visits grouped by source label.
	 *
	 * @param int $days Days to include.
	 * @return array<int, array{source: string, visits: int, last_seen: string}>
	 */
	public static function get_by_source( $days = 30 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'sb2_ai_referrals';
		$since = self::get_since_date( $days );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT source, SUM(visits) AS visits, MAX(last_seen) AS last_seen
				FROM {$table}
				WHERE hit_date >= %s
				GROUP BY source
				ORDER BY visits DESC",
				$since
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Top landing pages by AI referral visits.
	 *
	 * @param int $days  Days to include.
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_top_landing_pages( $days = 30, $limit = 10 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'sb2_ai_referrals';
		$since = self::get_since_date( $days );
		$limit = max( 1, min( 50, (int) $limit ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT object_id, object_type, normalized_url, landing_path,
					SUM(visits) AS visits,
					COUNT(DISTINCT source) AS source_count,
					GROUP_CONCAT(DISTINCT source ORDER BY source SEPARATOR ', ') AS sources,
					MAX(last_seen) AS last_seen
				FROM {$table}
				WHERE hit_date >= %s
				GROUP BY url_hash, object_id, object_type, normalized_url, landing_path
				ORDER BY visits DESC
				LIMIT %d",
				$since,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Aggregated referral rows for the admin list table.
	 *
	 * @param int   $days   Days to include.
	 * @param array $args   Optional search and pagination args.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public static function get_referral_log( $days = 30, $args = array() ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'sb2_ai_referrals';
		$since  = self::get_since_date( $days );
		$search = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
		$limit  = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 20;

		$where  = 'WHERE hit_date >= %s';
		$params = array( $since );

		if ( $search !== '' ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where   .= ' AND (source LIKE %s OR landing_path LIKE %s OR normalized_url LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$count_sql = "SELECT COUNT(*) FROM (
			SELECT source, url_hash
			FROM {$table}
			{$where}
			GROUP BY source, url_hash
		) grouped";
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built with prefixed tables / allowlisted ORDER BY; values prepared.

		$sql = "SELECT source, object_id, object_type, normalized_url, landing_path,
			SUM(visits) AS visits, MAX(last_seen) AS last_seen
			FROM {$table}
			{$where}
			GROUP BY source, url_hash, object_id, object_type, normalized_url, landing_path
			ORDER BY visits DESC, last_seen DESC
			LIMIT %d OFFSET %d";

		$query_params   = $params;
		$query_params[] = $limit;
		$query_params[] = $offset;

		$items = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built with prefixed tables / allowlisted ORDER BY; values prepared.

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Remove referral rows older than the retention window.
	 *
	 * @return int Rows deleted.
	 */
	public static function cleanup_old_referrals() {
		global $wpdb;

		$table = $wpdb->prefix . 'sb2_ai_referrals';
		if ( ! Utils::plugin_table_exists( $table ) ) {
			return 0;
		}

		$days = AI_Bot_Tracker::get_retention_days();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE hit_date < DATE_SUB(CURDATE(), INTERVAL %d DAY)",
				$days
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $wpdb->rows_affected;
	}
}
