<?php

namespace Cleverplugins\SEOBooster\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cached HTTP status for links and images.
 *
 * @since 7.1.0
 */
class Url_Status_Cache {

	public const KIND_LINK  = 'link';
	public const KIND_IMAGE = 'image';

	public const TTL_SECONDS = 86400;

	/**
	 * @var float|null
	 */
	private static $deadline = null;

	/**
	 * @var int
	 */
	private static $time_budget_seconds = 8;

	/**
	 * Reset per-analysis time budget.
	 *
	 * @param int $seconds Budget in seconds.
	 * @return void
	 */
	public static function reset_time_budget( $seconds = 8 ) {
		self::$time_budget_seconds = max( 1, (int) $seconds );
		self::$deadline            = microtime( true ) + self::$time_budget_seconds;
	}

	/**
	 * Whether the current analysis still has HTTP budget.
	 *
	 * @return bool
	 */
	public static function has_time_budget() {
		if ( null === self::$deadline ) {
			return true;
		}

		return microtime( true ) < self::$deadline;
	}

	/**
	 * Get cached status row.
	 *
	 * @param string $url URL.
	 * @param string $kind link|image.
	 * @return array|null
	 */
	public static function get( $url, $kind ) {
		global $wpdb;

		self::ensure_table();

		$table = $wpdb->prefix . 'sb2_seo_url_status';
		$hash  = self::hash_url( $url );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE url_hash = %s AND kind = %s AND checked_at >= DATE_SUB(NOW(), INTERVAL %d SECOND) LIMIT 1",
				$hash,
				$kind,
				self::TTL_SECONDS
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Store cached status row.
	 *
	 * @param string $url URL.
	 * @param string $kind link|image.
	 * @param array  $data Status data.
	 * @return void
	 */
	public static function set( $url, $kind, array $data ) {
		global $wpdb;

		self::ensure_table();

		$table = $wpdb->prefix . 'sb2_seo_url_status';
		$hash  = self::hash_url( $url );

		$wpdb->replace(
			$table,
			array(
				'url_hash'      => $hash,
				'url'           => $url,
				'kind'          => $kind,
				'status'        => $data['status'] ?? 'unknown',
				'status_code'   => isset( $data['status_code'] ) ? (int) $data['status_code'] : 0,
				'final_url'     => $data['final_url'] ?? '',
				'error_message' => $data['error'] ?? '',
				'checked_at'    => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Queue uncached URLs for background validation.
	 *
	 * @param string[] $urls URLs.
	 * @param string   $kind link|image.
	 * @return void
	 */
	public static function queue_background_checks( array $urls, $kind ) {
		$urls = array_values( array_unique( array_filter( $urls ) ) );
		if ( empty( $urls ) ) {
			return;
		}

		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		as_enqueue_async_action(
			'sb_seo_validate_urls',
			array(
				'urls' => array_slice( $urls, 0, 50 ),
				'kind' => $kind,
			),
			'seo-booster'
		);
	}

	/**
	 * Action Scheduler callback: validate URLs and cache results.
	 *
	 * @param array $args Args with urls and kind.
	 * @return void
	 */
	public static function process_background_checks( $args ) {
		$urls = isset( $args['urls'] ) && is_array( $args['urls'] ) ? $args['urls'] : array();
		$kind = isset( $args['kind'] ) ? (string) $args['kind'] : self::KIND_LINK;

		foreach ( $urls as $url ) {
			if ( self::KIND_IMAGE === $kind ) {
				$status = Abstract_Checks::check_image_status( $url );
			} else {
				$status = Abstract_Checks::check_link_status( $url );
			}

			self::set(
				$url,
				$kind,
				array(
					'status'      => $status['status'] ?? 'unknown',
					'status_code' => $status['status_code'] ?? 0,
					'final_url'   => $status['redirect_to'] ?? '',
					'error'       => $status['error'] ?? '',
				)
			);
		}
	}

	/**
	 * Ensure the URL status cache table exists (dbDelta via Utils).
	 *
	 * @return void
	 */
	private static function ensure_table() {
		global $wpdb;

		static $checked = false;
		if ( $checked ) {
			return;
		}
		$checked = true;

		$table = $wpdb->prefix . 'sb2_seo_url_status';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence check for lazy migration.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			\Cleverplugins\SEOBooster\Utils::create_database_tables();
		}
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	private static function hash_url( $url ) {
		return hash( 'sha256', (string) $url );
	}
}
