<?php

namespace Cleverplugins\SEOBooster;

use Cleverplugins\SEOBooster\Tools\Tools_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard “Do next” actions — blockers + health peek wiring.
 *
 * @package Cleverplugins\SEOBooster
 * @since 7.4.0
 */
class Dashboard_Actions {

	const MAX_CARDS = 6;

	/**
	 * Register dashboard assets.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue shared UI + dashboard Do next script on sb2_dashboard.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'sb2_dashboard' !== $page && 'toplevel_page_sb2_dashboard' !== $hook ) {
			return;
		}

		$ui_css = SEOBOOSTER_PLUGINPATH . 'css/sb-ui.css';
		$js     = SEOBOOSTER_PLUGINPATH . 'js/sb-dashboard.js';

		Utils::enqueue_modal_assets();

		wp_enqueue_style(
			'sb-ui',
			SEOBOOSTER_PLUGINURL . 'css/sb-ui.css',
			array(),
			file_exists( $ui_css ) ? (string) filemtime( $ui_css ) : '7.4.0'
		);

		wp_enqueue_script(
			'sb-dashboard',
			SEOBOOSTER_PLUGINURL . 'js/sb-dashboard.js',
			array( 'jquery', 'sb-modal' ),
			file_exists( $js ) ? (string) filemtime( $js ) : '7.4.0',
			true
		);

		wp_localize_script( 'sb-dashboard', 'sbDashboard', self::get_js_data() );
	}

	/**
	 * Localized data for sb-dashboard.js.
	 *
	 * @return array
	 */
	public static function get_js_data() {
		$has_premium = false;
		if ( function_exists( __NAMESPACE__ . '\\seobooster_fs' ) ) {
			$fs = seobooster_fs();
			if ( $fs && is_object( $fs ) && method_exists( $fs, 'can_use_premium_code' ) && $fs->can_use_premium_code() ) {
				$has_premium = true;
			}
		}

		return array(
			'ajaxurl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'sb_setup_nonce' ),
			'oauth_nonce' => wp_create_nonce( 'seobooster_oauth_prepare' ),
			'maxCards'    => self::MAX_CARDS,
			'has_premium' => $has_premium,
			'tools_meta'  => Tools_Page::get_page_url( 'meta' ),
			'tools_image' => Tools_Page::get_page_url( 'image' ),
			'tools_llms'  => Tools_Page::get_page_url( 'llms' ),
			'tools_focus' => Tools_Page::get_page_url( 'focus-keyword' ),
			'tools_decay' => Tools_Page::get_page_url( 'content-decay' ),
			'tools_opps'  => Tools_Page::get_page_url( 'gsc-opportunities' ),
			'issues_url'  => admin_url( 'admin.php?page=sb2_seo_issues' ),
			'strings'     => array(
				'allSet'            => __( 'All set', 'seo-booster' ),
				'openTool'          => __( 'Open tool', 'seo-booster' ),
				'llmsLive'          => __( 'Live', 'seo-booster' ),
				'llmsOff'           => __( 'Not enabled yet', 'seo-booster' ),
				'pagesLosingClicks' => __( 'Pages losing clicks', 'seo-booster' ),
				'gscOpportunities'  => __( 'GSC opportunities', 'seo-booster' ),
				'goodShape'         => __( 'You’re in good shape. No quick wins waiting right now.', 'seo-booster' ),
				'missingFocus'      => __( 'Pages without a focus keyword', 'seo-booster' ),
				'emptyAlt'          => __( 'Images missing alt text', 'seo-booster' ),
				'missingMeta'       => __( 'Pages missing title or description', 'seo-booster' ),
				'llmsTitle'         => __( 'llms.txt for AI crawlers', 'seo-booster' ),
				'connectError'      => __( 'Could not start Google authentication. Please try again.', 'seo-booster' ),
			),
		);
	}

	/**
	 * Server-known blocker / resume cards (before AJAX health fill).
	 *
	 * @param array $context {
	 *     @type string $access_token
	 *     @type string $selected_site
	 *     @type int    $unique_days
	 *     @type bool   $needs_reauth
	 *     @type string $ai_provider
	 *     @type bool   $show_ai_notice
	 *     @type string $ai_notice_link
	 *     @type string $ai_notice_text
	 *     @type string $wizard_url
	 *     @type bool   $omit_gsc_connect
	 *     @type int    $possibilities_total
	 * }
	 * @return array[]
	 */
	public static function get_blocker_items( array $context ) {
		$items = array();

		$access_token       = isset( $context['access_token'] ) ? $context['access_token'] : '';
		$selected_site      = isset( $context['selected_site'] ) ? $context['selected_site'] : '';
		$unique_days        = isset( $context['unique_days'] ) ? (int) $context['unique_days'] : 0;
		$needs_reauth       = ! empty( $context['needs_reauth'] );
		$omit_gsc_connect   = ! empty( $context['omit_gsc_connect'] );
		$wizard_url         = isset( $context['wizard_url'] ) ? $context['wizard_url'] : admin_url( 'admin.php?page=sb2_setup' );
		$wizard_restart_url = isset( $context['wizard_restart_url'] ) ? $context['wizard_restart_url'] : $wizard_url;

		if ( $needs_reauth && $access_token ) {
			$items[] = array(
				'id'                => 'gsc_reauth',
				'kind'              => 'blocker',
				'num'               => __( 'Action', 'seo-booster' ),
				'title'             => __( 'Reconnect Google Search Console', 'seo-booster' ),
				'url'               => '#',
				'cta'               => __( 'Re-authenticate', 'seo-booster' ),
				'warn'              => true,
				'oauth_destination' => 'dashboard',
			);
		} elseif ( ! $access_token && ! $omit_gsc_connect ) {
			$items[] = array(
				'id'    => 'gsc_connect',
				'kind'  => 'blocker',
				'num'   => __( 'Start', 'seo-booster' ),
				'title' => __( 'Set up SEO Booster for the full benefits', 'seo-booster' ),
				'url'   => $wizard_restart_url,
				'cta'   => __( 'Run setup wizard', 'seo-booster' ),
				'warn'  => false,
			);
		} elseif ( $access_token && ! $selected_site && ! $omit_gsc_connect ) {
			$items[] = array(
				'id'    => 'gsc_site',
				'kind'  => 'blocker',
				'num'   => __( 'Next', 'seo-booster' ),
				'title' => __( 'Finish setup to select your Search Console property', 'seo-booster' ),
				'url'   => $wizard_url,
				'cta'   => __( 'Continue setup', 'seo-booster' ),
				'warn'  => false,
			);
		} elseif ( $selected_site && $unique_days <= 0 ) {
			$items[] = array(
				'id'    => 'gsc_import',
				'kind'  => 'blocker',
				'num'   => __( 'Next', 'seo-booster' ),
				'title' => __( 'Import Search Console data', 'seo-booster' ),
				'url'   => admin_url( 'admin.php?page=sb2_settings#gsc' ),
				'cta'   => __( 'Open Settings', 'seo-booster' ),
				'warn'  => false,
			);
		}

		if ( ! empty( $context['show_ai_notice'] ) ) {
			$items[] = array(
				'id'    => 'ai_setup',
				'kind'  => 'blocker',
				'num'   => __( 'AI', 'seo-booster' ),
				'title' => __( 'Enable AI for SEO suggestions and image meta', 'seo-booster' ),
				'url'   => isset( $context['ai_notice_link'] ) ? $context['ai_notice_link'] : admin_url( 'admin.php?page=sb2_settings#ai-llm' ),
				'cta'   => __( 'Open settings', 'seo-booster' ),
				'warn'  => false,
			);
		}

		$possibilities = isset( $context['possibilities_total'] ) ? (int) $context['possibilities_total'] : 0;
		if ( $possibilities > 0 ) {
			$items[] = array(
				'id'    => 'possibilities',
				'kind'  => 'static',
				'num'   => number_format_i18n( $possibilities ),
				'title' => __( 'SEO possibilities to review', 'seo-booster' ),
				'url'   => admin_url( 'admin.php?page=sb2_seo_issues' ),
				'cta'   => __( 'Review', 'seo-booster' ),
				'warn'  => false,
			);
		}

		return array_slice( $items, 0, self::MAX_CARDS );
	}

	/**
	 * Search performance KPIs: last N days vs the prior N days (non-overlapping).
	 *
	 * @param int $period_days Window length (default 30).
	 * @return array{
	 *   kpis: array,
	 *   has_comparison: bool,
	 *   period_days: int,
	 *   current_start: string,
	 *   current_end: string,
	 *   previous_start: string,
	 *   previous_end: string,
	 *   label_current: string,
	 *   label_compare: string
	 * }
	 */
	public static function get_search_performance_kpis( $period_days = 30 ) {
		global $wpdb;

		$period_days = max( 7, min( 90, (int) $period_days ) );
		$empty       = array(
			'kpis'           => array(),
			'has_comparison' => false,
			'period_days'    => $period_days,
			'current_start'  => '',
			'current_end'    => '',
			'previous_start' => '',
			'previous_end'   => '',
			'label_current'  => '',
			'label_compare'  => '',
		);

		$table = $wpdb->prefix . 'sb2_query_keywords_history';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefixed table; read-only.
		$latest_date = $wpdb->get_var( "SELECT MAX(date) FROM {$table}" );
		if ( ! $latest_date ) {
			return $empty;
		}

		$current_end   = gmdate( 'Y-m-d', strtotime( $latest_date . ' UTC' ) );
		$current_start = gmdate( 'Y-m-d', strtotime( $current_end . ' -' . ( $period_days - 1 ) . ' days UTC' ) );

		// Prefer the calendar window immediately before current; if GSC history has a gap
		// (common after partial imports), fall back to the nearest earlier window with data.
		$previous_end   = gmdate( 'Y-m-d', strtotime( $current_start . ' -1 day UTC' ) );
		$previous_start = gmdate( 'Y-m-d', strtotime( $previous_end . ' -' . ( $period_days - 1 ) . ' days UTC' ) );
		$used_fallback  = false;

		$current  = self::aggregate_history_window( $current_start, $current_end );
		$previous = self::aggregate_history_window( $previous_start, $previous_end );

		if ( empty( $previous ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefixed table; prepared date.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
			$prior_latest = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MAX(date) FROM {$table} WHERE date < %s",
					$current_start
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $prior_latest ) {
				$previous_end   = gmdate( 'Y-m-d', strtotime( $prior_latest . ' UTC' ) );
				$previous_start = gmdate( 'Y-m-d', strtotime( $previous_end . ' -' . ( $period_days - 1 ) . ' days UTC' ) );
				$previous       = self::aggregate_history_window( $previous_start, $previous_end );
				$used_fallback  = ! empty( $previous );
			}
		}

		if ( empty( $current ) || ( (float) $current['impressions'] <= 0 && (float) $current['clicks'] <= 0 ) ) {
			return $empty;
		}

		$has_comparison = ! empty( $previous )
			&& ( (float) $previous['impressions'] > 0 || (float) $previous['clicks'] > 0 );

		$kpis = array(
			'clicks'      => self::build_kpi_metric(
				(float) $current['clicks'],
				$has_comparison ? (float) $previous['clicks'] : null,
				false,
				0
			),
			'impressions' => self::build_kpi_metric(
				(float) $current['impressions'],
				$has_comparison ? (float) $previous['impressions'] : null,
				false,
				0
			),
			'position'    => self::build_kpi_metric(
				(float) $current['position'],
				$has_comparison ? (float) $previous['position'] : null,
				true,
				1
			),
			'ctr'         => self::build_kpi_metric(
				(float) $current['ctr'] * 100,
				$has_comparison ? (float) $previous['ctr'] * 100 : null,
				false,
				2,
				'%'
			),
		);

		$date_format = get_option( 'date_format' );

		if ( $has_comparison ) {
			$label_compare = $used_fallback
				? sprintf(
					/* translators: 1: number of days, 2: start date, 3: end date */
					__( 'Compared to previous available %1$d days (%2$s – %3$s)', 'seo-booster' ),
					$period_days,
					date_i18n( $date_format, strtotime( $previous_start ) ),
					date_i18n( $date_format, strtotime( $previous_end ) )
				)
				: sprintf(
					/* translators: 1: number of days, 2: start date, 3: end date */
					__( 'Compared to prior %1$d days (%2$s – %3$s)', 'seo-booster' ),
					$period_days,
					date_i18n( $date_format, strtotime( $previous_start ) ),
					date_i18n( $date_format, strtotime( $previous_end ) )
				);
		} else {
			$label_compare = __( 'No earlier period with Search Console data to compare yet.', 'seo-booster' );
		}

		return array(
			'kpis'           => $kpis,
			'has_comparison' => $has_comparison,
			'period_days'    => $period_days,
			'current_start'  => $current_start,
			'current_end'    => $current_end,
			'previous_start' => $previous_start,
			'previous_end'   => $previous_end,
			'label_current'  => sprintf(
				/* translators: 1: number of days, 2: start date, 3: end date */
				__( 'Last %1$d days (%2$s – %3$s)', 'seo-booster' ),
				$period_days,
				date_i18n( $date_format, strtotime( $current_start ) ),
				date_i18n( $date_format, strtotime( $current_end ) )
			),
			'label_compare'  => $label_compare,
		);
	}

	/**
	 * Aggregate site-level GSC history for a closed date window.
	 *
	 * Daily totals first (sum of keyword rows per day), then window totals.
	 * Position is impression-weighted; CTR is clicks / impressions.
	 *
	 * @param string $start_date Y-m-d.
	 * @param string $end_date   Y-m-d.
	 * @return array{clicks: float, impressions: float, position: float, ctr: float}|null
	 */
	private static function aggregate_history_window( $start_date, $end_date ) {
		global $wpdb;

		$table = $wpdb->prefix . 'sb2_query_keywords_history';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefixed table; prepared dates.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM(day_clicks) AS total_clicks,
					SUM(day_impressions) AS total_impressions,
					SUM(day_position_weight) / NULLIF(SUM(day_impressions), 0) AS avg_position,
					SUM(day_clicks) / NULLIF(SUM(day_impressions), 0) AS avg_ctr
				FROM (
					SELECT
						h.date,
						SUM(h.clicks) AS day_clicks,
						SUM(h.impressions) AS day_impressions,
						SUM(h.position * h.impressions) AS day_position_weight
					FROM {$table} AS h
					WHERE h.date BETWEEN %s AND %s
					GROUP BY h.date
				) AS daily",
				$start_date,
				$end_date
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $row ) ) {
			return null;
		}

		$clicks      = (float) ( $row['total_clicks'] ?? 0 );
		$impressions = (float) ( $row['total_impressions'] ?? 0 );
		$position    = (float) ( $row['avg_position'] ?? 0 );
		$ctr         = (float) ( $row['avg_ctr'] ?? 0 );

		if ( $clicks <= 0 && $impressions <= 0 ) {
			return null;
		}

		return array(
			'clicks'      => $clicks,
			'impressions' => $impressions,
			'position'    => $position,
			'ctr'         => $ctr,
		);
	}

	/**
	 * Build one KPI metric with optional prior-period delta.
	 *
	 * @param float      $current  Current period value.
	 * @param float|null $previous Previous period value, or null if unavailable.
	 * @param bool       $invert   True when lower is better (position).
	 * @param int        $decimals Display decimals.
	 * @param string     $suffix   Optional suffix (e.g. %).
	 * @return array
	 */
	private static function build_kpi_metric( $current, $previous, $invert, $decimals, $suffix = '' ) {
		$metric = array(
			'value'          => $current,
			'change'         => null,
			'percentage'     => null,
			'has_comparison' => null !== $previous,
			'invert'         => $invert,
			'decimals'       => $decimals,
		);

		if ( '' !== $suffix ) {
			$metric['suffix'] = $suffix;
		}

		if ( null === $previous ) {
			return $metric;
		}

		$change = $current - $previous;
		$pct    = ( abs( $previous ) > 0.00001 )
			? round( ( $change / $previous ) * 100, 1 )
			: null;

		$metric['change']     = $change;
		$metric['percentage'] = $pct;

		return $metric;
	}
}
