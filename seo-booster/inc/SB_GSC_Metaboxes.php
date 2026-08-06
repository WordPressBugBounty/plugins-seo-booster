<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . '/Google_API.php';
require_once plugin_dir_path( __FILE__ ) . '/SB_GSC_Ajax.php';
require_once plugin_dir_path( __FILE__ ) . '/SB_GSC_Processor.php';

/**
 * Class SB_GSC_Metaboxes
 *
 * Handles the Google Search Console metaboxes for SEO Booster.
 *
 * @package Cleverplugins\SEOBooster
 * @since 0.0.1
 */
class SB_GSC_Metaboxes {

	/**
	 * Initialize the class and set up hooks.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	/**
	 * Taxonomies where Keywords are embedded in the SEO Booster metabox.
	 *
	 * @since 7.3.2
	 * @return string[]
	 */
	private static function get_seo_embedded_taxonomies() {
		return array( 'category', 'post_tag' );
	}

	public static function init() {
		// Post-type Keywords live inside SB_SEO_Metabox tabs — no separate metabox.
		add_action( 'wp_ajax_sb_gsc_reanalyze_keywords', array( __CLASS__, 'reanalyze_keywords' ) );
		add_action( 'wp_ajax_sb_gsc_load_libraries', array( __CLASS__, 'load_libraries' ) );

		// Standalone Keywords on taxonomies that do not get the SEO Booster metabox.
		$embedded_taxonomies = self::get_seo_embedded_taxonomies();
		$public_taxonomies   = get_taxonomies( array( 'public' => true ), 'names' );
		foreach ( $public_taxonomies as $taxonomy ) {
			if ( in_array( $taxonomy, $embedded_taxonomies, true ) ) {
				continue;
			}
			$priority = ( 'product_cat' === $taxonomy ) ? 9999 : 10;
			add_action( "{$taxonomy}_edit_form", array( __CLASS__, 'render_taxonomy_metabox' ), $priority, 2 );
		}
	}


	public static function render_taxonomy_metabox( $term, $taxonomy ) {
		$term_link = get_term_link( $term );

		// Ensure we have an absolute URL
		if ( ! empty( $term_link ) && strpos( $term_link, 'http' ) !== 0 ) {
			$term_link = home_url( $term_link );
		}
		self::render_metabox( $term, false );
	}

	/**
	 * Render Keywords section for embedding inside the SEO Booster metabox.
	 *
	 * @since 7.3.2
	 * @param mixed $post_or_term Post or term object.
	 * @return void
	 */
	public static function render_embedded_section( $post_or_term ) {
		self::render_metabox( $post_or_term, true );
	}

	/**
	 * Cheap check: whether any GSC keyword rows exist for a page URL.
	 *
	 * Uses a short-lived transient so edit screens stay fast while still
	 * reflecting fairly fresh import data (GSC updates at least daily).
	 *
	 * @since 7.3.5
	 * @param string $public_url Absolute page URL.
	 * @return bool|null True when keywords exist, false when not, null when URL is empty.
	 */
	public static function page_has_keyword_data( $public_url ) {
		$public_url = esc_url_raw( (string) $public_url );
		if ( '' === $public_url ) {
			return null;
		}

		$cache_key = 'sb_gsc_kw_has_' . md5( $public_url );
		$cached    = get_transient( $cache_key );
		if ( '1' === $cached || '0' === $cached ) {
			return ( '1' === $cached );
		}

		global $wpdb;

		$url_candidates = array_values(
			array_unique(
				array_filter(
					array(
						$public_url,
						untrailingslashit( $public_url ),
						trailingslashit( $public_url ),
					)
				)
			)
		);

		if ( empty( $url_candidates ) ) {
			return null;
		}

		$placeholders = implode( ',', array_fill( 0, count( $url_candidates ), '%s' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- IN() placeholders from array_fill; table from prefix; values prepared.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}sb2_query_keywords WHERE page IN ( {$placeholders} ) LIMIT 1",
				...$url_candidates
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$has_data = null !== $found && false !== $found && '' !== (string) $found;
		set_transient( $cache_key, $has_data ? '1' : '0', 6 * HOUR_IN_SECONDS );

		return $has_data;
	}

	/**
	 * Render the Google Search Console keyword analysis content.
	 *
	 * @since 0.0.1
	 * @param mixed $post_or_term The current post object, term object, or array of rows.
	 * @param bool  $embedded     When true, omit the standalone heading (used inside SEO tabs).
	 * @return void
	 */
	public static function render_metabox( $post_or_term, $embedded = false ) {

		$seo_plug_name = Google_API::identify_active_seo_plugin();

		// Determine if this is a post or term
		$content_type = 'post';
		$public_url   = '';
		$item_id      = 0;

		if ( is_object( $post_or_term ) ) {
			if ( isset( $post_or_term->ID ) ) {
				// This is a post object
				$content_type = 'post';
				$item_id      = $post_or_term->ID;
				$public_url   = get_permalink( $item_id );
			} elseif ( isset( $post_or_term->term_id ) ) {
				// This is a term object
				$content_type = 'term';
				$item_id      = $post_or_term->term_id;
				$public_url   = get_term_link( $post_or_term );
			}
		}

		// Ensure we have an absolute URL
		if ( ! empty( $public_url ) && strpos( $public_url, 'http' ) !== 0 ) {
			$public_url = home_url( $public_url );
		}

		$wrapper_class = $embedded ? 'sb-gsc-embedded' : '';
		$output        = '<div id="sbtablecont"' . ( $wrapper_class ? ' class="' . esc_attr( $wrapper_class ) . '"' : '' ) . '>';
		$output       .= '<input type="hidden" id="sb-gsc-public-url" value="' . esc_attr( $public_url ) . '">';
		$output       .= '<input type="hidden" id="sb-gsc-content-type" value="' . esc_attr( $content_type ) . '">';
		$output       .= '<input type="hidden" id="sb-gsc-item-id" value="' . esc_attr( $item_id ) . '">';

		if ( $seo_plug_name && ! $embedded ) {
			$output .= '<p class="sb-gsc-plugin-status">' . esc_html__( 'Detected SEO plugin: ', 'seo-booster' ) . esc_html( $seo_plug_name['name'] );

			if ( $content_type === 'post' && $item_id > 0 ) {
				$get_focus_keyword = Google_API::get_focus_keywords( $item_id );

				if ( $get_focus_keyword ) {
					$output .= ' | ' . esc_html__( 'Focus keyword(s)', 'seo-booster' ) . ': ';
					foreach ( $get_focus_keyword as $focus_keyword ) {
						$output .= '<span class="label">' . esc_html( $focus_keyword ) . '</span> ';
					}
				} else {
					$output .= ' | ' . esc_html__( 'No focus keyword found.', 'seo-booster' );
				}
			}
			$output .= '</p>';
		}

		if ( ! $embedded ) {
			$output .= '<h3>' . esc_html__( 'SEO Booster Keyword Analysis', 'seo-booster' ) . '</h3>';
		}

		$keyword_presence = self::page_has_keyword_data( $public_url );

		// Add the "click to load data" button instead of immediately showing the container
		$output .= '<div id="sb-gsc-load-button-container" class="sb-gsc-load-button-wrapper">';
		if ( true === $keyword_presence ) {
			$output .= '<p class="sb-gsc-presence sb-gsc-presence--has"><span class="sb-gsc-presence-dot" aria-hidden="true"></span> ' . esc_html__( 'Keyword data is available for this URL.', 'seo-booster' ) . '</p>';
		} elseif ( false === $keyword_presence ) {
			$output .= '<p class="sb-gsc-presence sb-gsc-presence--empty"><span class="sb-gsc-presence-dot" aria-hidden="true"></span> ' . esc_html__( 'No keyword data found for this URL yet.', 'seo-booster' ) . '</p>';
		}
		$output .= '<button type="button" id="sb-gsc-load-data-btn" class="button button-primary">';
		$output .= '<span class="dashicons dashicons-chart-line"></span> ';
		$output .= esc_html__( 'Load Keyword Analysis Data', 'seo-booster' );
		$output .= '</button>';
		$output .= '<p class="description">' . esc_html__( 'Click to load the keyword analysis data and charts. This will load additional resources.', 'seo-booster' ) . '</p>';
		$output .= '</div>';

		$output .= '<div id="sb-gsc-keywords-container" style="display: none;"></div>';
		$output .= '<div class="sb-chart-legend" style="display: none;">
            <div class="sb-legend-item">
                <div class="sb-legend-color" style="background-color: rgba(24, 119, 242, 0.8);"></div>
                <span>' . esc_html__( 'Impressions', 'seo-booster' ) . '</span>
            </div>
            <div class="sb-legend-item">
                <div class="sb-legend-color" style="background-color: rgba(45, 196, 78, 0.8);"></div>
                <span>' . esc_html__( 'Clicks', 'seo-booster' ) . '</span>
            </div>
            <div class="sb-legend-item">
                <div class="sb-legend-color" style="background-color: rgba(242, 120, 24, 0.8);"></div>
                <span>' . esc_html__( 'Position', 'seo-booster' ) . '</span>
            </div>
        </div>';
		$output .= '<div class="sb-analysis-lastupdate"></div>';
		$output .= '</div>';
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with escaped pieces above.
	}

	/**
	 * Enqueue scripts and styles for the metabox.
	 *
	 * @since 0.0.1
	 * @param string $hook The current admin page.
	 * @return void
	 */
	public static function enqueue_scripts( $hook ) {
		// Only enqueue on post edit pages, taxonomy edit pages, and other relevant admin pages
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook && 'term.php' !== $hook ) {
			return;
		}

		$js_file_path  = SEOBOOSTER_PLUGINPATH . 'js/sb-gsc-metabox.js';
		$css_file_path = SEOBOOSTER_PLUGINPATH . 'css/sb-gsc-metabox.css';

		$js_version  = file_exists( $js_file_path ) ? filemtime( $js_file_path ) : Utils::get_plugin_version();
		$css_version = file_exists( $css_file_path ) ? filemtime( $css_file_path ) : Utils::get_plugin_version();

		Utils::enqueue_modal_assets();

		wp_enqueue_script( 'sb-gsc-metabox', SEOBOOSTER_PLUGINURL . 'js/sb-gsc-metabox.js', array( 'jquery', 'sb-modal' ), $js_version, true );
		wp_enqueue_style( 'sb-gsc-metabox', SEOBOOSTER_PLUGINURL . 'css/sb-gsc-metabox.css', array( 'dashicons' ), $css_version );

		// Note: Tabulator and uPlot libraries are now loaded dynamically via AJAX when user clicks "Load Data" button

		$public_url = Utils::seobooster_currenturl( true );

		// Get the post ID or term ID
		$item_id      = get_the_ID();
		$content_type = 'post';

		// Check if we're on a taxonomy page
		if ( 'term.php' === $hook ) {
			$content_type = 'term';
			$term_id      = isset( $_GET['tag_ID'] ) ? absint( $_GET['tag_ID'] ) : 0;
			if ( $term_id ) {
				$item_id    = $term_id;
				$public_url = get_term_link( $term_id );
			}
		}
		// if we are on a edit post (any kind) set the public_url to the permalink of the post
		if ( 'post' === $content_type ) {
			$public_url = get_permalink( $item_id );
		}

		wp_localize_script(
			'sb-gsc-metabox',
			'sb_gsc_metabox_data',
			array(
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'item_id'      => $item_id,
				'content_type' => $content_type,
				'public_url'   => $public_url,
				'security'     => wp_create_nonce( 'sb_gsc_nonce' ),
				'strings'      => array(
					'hoverToSeeChart'          => __( 'Hover to see the chart' ),
					'analyzing'                => __( 'Loading ...', 'seo-booster' ),
					'reanalyze'                => __( 'Reanalyze', 'seo-booster' ),
					'scriptLoaded'             => __( 'GSC Metabox script loaded', 'seo-booster' ),
					'searchPlaceholder'        => __( 'Search...', 'seo-booster' ),
					'allKeywords'              => __( 'All Keywords', 'seo-booster' ),
					'keywordsNoClicks'         => __( 'Keywords with no clicks', 'seo-booster' ),
					'keywordsNotUsedInContent' => __( 'Keywords not used in content', 'seo-booster' ),
					'keywordsUsedInContent'    => __( 'Keywords used in content', 'seo-booster' ),
					'resetFilters'             => __( 'Reset Filters', 'seo-booster' ),
					'query'                    => __( 'Query', 'seo-booster' ),
					'firstSeen'                => __( 'First Seen', 'seo-booster' ),
					'lastSeen'                 => __( 'Last Seen', 'seo-booster' ),
					'showingKeywords'          => __( 'Showing', 'seo-booster' ),
					'seenInContent'            => __( 'Seen in content?', 'seo-booster' ),
					'clicks'                   => __( 'Clicks', 'seo-booster' ),
					'impressions'              => __( 'Impressions', 'seo-booster' ),
					'ctr'                      => __( 'CTR', 'seo-booster' ),
					'position'                 => __( 'Position', 'seo-booster' ),
					'autolink'                 => __( 'Autolink', 'seo-booster' ),
					'hideColumn'               => __( 'Hide Column', 'seo-booster' ),
					'showAllColumns'           => __( 'Show All Columns', 'seo-booster' ),
					'time'                     => __( 'Time', 'seo-booster' ),
					'totalKeywords'            => __( 'Total Keywords', 'seo-booster' ),
					'noKeywordsFound'          => __( 'No keywords found', 'seo-booster' ),
					'noKeywordsExplanation'    => __( 'This page doesn\'t have any keywords with search data yet. This could be because:', 'seo-booster' ),
					'noKeywordsReason1'        => __( 'The page is new and hasn\'t been indexed yet', 'seo-booster' ),
					'noKeywordsReason2'        => __( 'The page doesn\'t rank for any keywords in Google Search Console data', 'seo-booster' ),
					'checkUrl'                 => __( 'Check URL', 'seo-booster' ),
					'currentUrl'               => __( 'Current URL', 'seo-booster' ),
					'urlCheckExplanation'      => __( 'Verify this URL matches what\'s in Google Search Console. If different, update the URL in your content.', 'seo-booster' ),
					'error'                    => __( 'Error', 'seo-booster' ),
					'copySelected'             => __( 'Copy Selected', 'seo-booster' ),
					'copiedToClipboard'        => __( 'Copied to clipboard', 'seo-booster' ),
					'showHideColumns'          => __( 'Show/Hide Columns', 'seo-booster' ),
					'refresh'                  => __( 'Refresh', 'seo-booster' ),
					'refreshKeywords'          => __( 'Refresh Keywords', 'seo-booster' ),
					'copyAll'                  => __( 'Copy All', 'seo-booster' ),
					'refreshKeywordAnalysis'   => __( 'Refresh Keyword Analysis', 'seo-booster' ),
					'errorDeletingTransients'  => __( 'Error deleting transients', 'seo-booster' ),
					'noDataAvailable'          => __( 'No chart data available', 'seo-booster' ),
					'insufficientData'         => __( 'Not enough data points', 'seo-booster' ),
					'trends'                   => __( 'Trends', 'seo-booster' ),
					'clicksImpressions'        => __( 'Clicks/Impressions trend', 'seo-booster' ),
					'analysisResetMessage'     => __( 'Analysis reset and running - please allow up to 1-2 minutes on busy sites and/or if lots of keywords to process. You can use the refresh button to update the results', 'seo-booster' ),
					'trendsLegend'             => __( 'Trend chart legend', 'seo-booster' ),
					'analyzing_text'           => esc_js( __( 'Analyzing...', 'seo-booster' ) ),
					'refresh_text'             => esc_js( __( 'Refresh Analysis', 'seo-booster' ) ),
					'loadData'                 => __( 'Load Keyword Analysis Data', 'seo-booster' ),
					'loadingData'              => __( 'Loading data...', 'seo-booster' ),
					'keywordDataAvailable'     => __( 'Keyword data available', 'seo-booster' ),
					'noKeywordDataYet'         => __( 'No keyword data yet', 'seo-booster' ),
				),
			)
		);
	}

	/**
	 * Load heavy libraries (Tabulator and uPlot) dynamically via AJAX.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public static function load_libraries() {
		check_ajax_referer( 'sb_gsc_nonce', 'security' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action', 'seo-booster' ) ) );
		}

		// Return the script and style URLs directly
		$scripts = array(
			'tabulator' => SEOBOOSTER_PLUGINURL . 'js/tabulator/dist/js/tabulator.min.js',
			'uPlot'     => SEOBOOSTER_PLUGINURL . 'js/uPlot/uPlot.iife.min.js',
		);

		$styles = array(
			'tabulator' => SEOBOOSTER_PLUGINURL . 'js/tabulator/dist/css/tabulator.min.css',
			'uPlot'     => SEOBOOSTER_PLUGINURL . 'js/uPlot/uPlot.min.css',
		);

		$response_data = array(
			'scripts' => $scripts,
			'styles'  => $styles,
			'message' => __( 'Libraries loaded successfully', 'seo-booster' ),
		);

		wp_send_json_success( $response_data );
	}

	/**
	 * Reanalyze keywords for a post, term, or other content type.
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Wednesday, October 16th, 2024.
	 * @access  public static
	 * @return  void
	 */
	public static function reanalyze_keywords() {
		check_ajax_referer( 'sb_gsc_nonce', 'security' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action', 'seo-booster' ) ) );
		}

		$item_id      = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$content_type = isset( $_POST['content_type'] ) ? sanitize_text_field( $_POST['content_type'] ) : 'post';
		$public_url   = isset( $_POST['public_url'] ) ? esc_url_raw( $_POST['public_url'] ) : '';

		if ( ! $item_id && empty( $public_url ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid ID or URL', 'seo-booster' ) ) );
		}

		if ( $item_id > 0 ) {
			$cap_type = ( 'term' === $content_type || 'taxonomy' === $content_type ) ? 'term' : 'post';
			if ( ! Utils::user_can_edit_object( $item_id, $cap_type ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action', 'seo-booster' ) ) );
			}
		}

		global $wpdb;

		// Clear transients for this item if we have an ID
		if ( $item_id > 0 ) {
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					$wpdb->esc_like( '_transient_sb_gsc_keyword_usage_' . $item_id . '_' ) . '%',
					$wpdb->esc_like( '_transient_timeout_sb_gsc_keyword_usage_' . $item_id . '_' ) . '%'
				)
			);

			if ( $deleted === false ) {
				wp_send_json_error( array( 'message' => __( 'Failed to delete transients', 'seo-booster' ) ) );
			}
		} else {
			$deleted = 0;
		}

		// If we don't have a public URL yet, try to get it based on the content type and ID
		if ( empty( $public_url ) && $item_id > 0 ) {
			if ( $content_type === 'term' ) {
				$term = get_term( $item_id );
				if ( $term && ! is_wp_error( $term ) ) {
					$public_url = get_term_link( $term );
				}
			} else {
				$public_url = get_permalink( $item_id );
			}
		}

		// Ensure we have an absolute URL
		if ( ! empty( $public_url ) && strpos( $public_url, 'http' ) !== 0 ) {
			$public_url = home_url( $public_url );
		}

		if ( empty( $public_url ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to get URL for the item', 'seo-booster' ) ) );
		}

		// Schedule and run keyword processing for this URL
		$scheduled_jobs = self::schedule_and_run_keyword_processing_for_url( $public_url, $item_id, $content_type );

		wp_send_json_success(
			array(
				'message'        => sprintf(
					__( 'Successfully scheduled %2$d jobs for %3$s. Reloading in 4 seconds...', 'seo-booster' ),
					$deleted,
					$scheduled_jobs,
					$content_type === 'term' ? __( 'term', 'seo-booster' ) : __( 'post', 'seo-booster' )
				),
				'scheduled_jobs' => $scheduled_jobs,
				'item_id'        => $item_id,
			)
		);
	}

	/**
	 * Ensures that Action Scheduler is loaded and available for use.
	 *
	 * @since v0.0.1
	 * @return bool True if Action Scheduler is loaded, false otherwise.
	 */
	private static function ensure_action_scheduler_loaded() {
		// First check if Action Scheduler functions are already available
		if ( function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_has_scheduled_action' ) ) {
			return true;
		}

		// Try to load Action Scheduler if WooCommerce is active
		if ( class_exists( 'WooCommerce' ) ) {
			// WooCommerce includes Action Scheduler
			return true;
		}

		// If not available through WooCommerce, check if we have our own copy
		$action_scheduler_file = SEOBOOSTER_PLUGINPATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
		if ( file_exists( $action_scheduler_file ) ) {
			include_once $action_scheduler_file;
			return true;
		}

		// Look for Action Scheduler in standard WordPress plugin directory
		$action_scheduler_plugin = WP_PLUGIN_DIR . '/action-scheduler/action-scheduler.php';
		if ( file_exists( $action_scheduler_plugin ) ) {
			include_once $action_scheduler_plugin;
			return true;
		}

		return false;
	}

	/**
	 * Schedule and run keyword processing for a URL.
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Wednesday, October 16th, 2024.
	 * @access  private static
	 * @param   string  $public_url The absolute URL to process.
	 * @param   int $item_id    The ID of the post or term (optional).
	 * @param   string  $content_type   The type of content (post, term, etc.).
	 * @return  int The number of scheduled jobs.
	 */
	private static function schedule_and_run_keyword_processing_for_url( $public_url, $item_id = 0, $content_type = 'post' ) {
		global $wpdb;

		if ( empty( $public_url ) ) {
			Utils::log( 'Empty URL provided for keyword processing', 2 );
			return 0;
		}

		// Ensure we have an absolute URL
		if ( strpos( $public_url, 'http' ) !== 0 ) {
			$public_url = home_url( $public_url );
		}

		// Clear keyword statuses for this URL in one query
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}sb2_query_keywords 
            SET is_used_in_content = NULL, 
                last_checked = NULL 
            WHERE page = %s",
				$public_url
			)
		);

		// Ensure Action Scheduler is loaded
		if ( ! self::ensure_action_scheduler_loaded() ) {
			Utils::log( 'Action Scheduler not available - could not schedule keyword processing for: ' . $public_url, 2 );
			return 0;
		}

		// Clear all scheduled actions for this URL in one query
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->actionscheduler_actions} 
            WHERE hook = 'sb_gsc_process_url_keywords' 
            AND args LIKE %s",
				'%' . $wpdb->esc_like( '"post_url":"' . $public_url . '"' ) . '%'
			)
		);

		// Clear the cache for this item's keywords if we have an ID
		if ( $item_id > 0 ) {
			wp_cache_delete( 'sb_gsc_keywords_' . $item_id, 'sb_gsc' );
		}

		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			$action_scheduler_file = SEOBOOSTER_PLUGINPATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
			if ( file_exists( $action_scheduler_file ) ) {
				include_once $action_scheduler_file;
			}
		}

		// Schedule the initial processing
		$action_id = as_enqueue_async_action(
			'sb_gsc_process_url_keywords',
			array(
				'post_url'     => $public_url,
				'content_type' => $content_type,
				'item_id'      => $item_id,
			),
			'seo-booster'
		);

		if ( ! $action_id ) {
			Utils::log( 'Failed to schedule keyword processing for URL: ' . $public_url, 2 );
			return 0;
		}

		// Attempt to run the queue immediately
		self::maybe_run_queue();

		return 1;
	}

	/**
	 * maybe_run_queue.
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Wednesday, October 16th, 2024.
	 * @access  private static
	 * @return  void
	 */
	private static function maybe_run_queue() {
		// Ensure Action Scheduler is loaded before checking for scheduled actions
		if ( self::ensure_action_scheduler_loaded() && function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( 'action_scheduler_run_queue' ) ) {
			as_enqueue_async_action( 'action_scheduler_run_queue', array(), 'action-scheduler' );
		}
	}
}

SB_GSC_Metaboxes::init();
