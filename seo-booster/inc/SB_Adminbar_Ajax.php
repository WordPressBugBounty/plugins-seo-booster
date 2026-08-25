<?php
/**
 * Admin bar Page overview AJAX (read-only snapshot).
 *
 * @package SEOBooster
 */

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight read-only page snapshot for the admin bar WinBox panel.
 *
 * @since 7.4.0
 */
class SB_Adminbar_Ajax {

	const NONCE_ACTION = 'sb_adminbar_nonce';

	/** Max issues/opportunities returned. */
	const LIST_LIMIT = 12;

	/** Max compact keyword rows. */
	const KEYWORD_LIMIT = 10;

	/** Max message length in lists. */
	const MESSAGE_MAX = 200;

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_sb_adminbar_page_snapshot', array( __CLASS__, 'ajax_page_snapshot' ) );
	}

	/**
	 * Return a read-only page snapshot for the admin bar panel.
	 *
	 * @return void
	 */
	public static function ajax_page_snapshot() {
		check_ajax_referer( self::NONCE_ACTION, 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to view this overview.', 'seo-booster' ) ),
				403
			);
		}

		$resolved = self::resolve_context();
		if ( is_wp_error( $resolved ) ) {
			wp_send_json_error( array( 'message' => $resolved->get_error_message() ), 400 );
		}

		$object_id   = (int) $resolved['object_id'];
		$object_type = $resolved['object_type'];
		$page_url    = $resolved['page_url'];
		$edit_url    = $resolved['edit_url'];

		$seo_title = '';
		$seo_desc  = '';
		if ( $object_id > 0 ) {
			if ( 'post' === $object_type ) {
				$meta = SEO_Plugin_Registry::read_post_seo_resolved( $object_id );
			} elseif ( 'term' === $object_type ) {
				$meta = SEO_Plugin_Registry::read_term_seo_resolved( $object_id );
			} else {
				$meta = array();
			}
			$seo_title = isset( $meta['title'] ) ? (string) $meta['title'] : '';
			$seo_desc  = isset( $meta['description'] ) ? (string) $meta['description'] : '';
		}

		$score           = null;
		$analyzed_at     = '';
		$content_changed = false;
		$issues          = array();
		$opportunities   = array();
		$has_analysis    = false;

		if ( $object_id > 0 ) {
			$saved = SEO_Analysis::get_saved_analysis( $object_id, $object_type );
			if ( is_array( $saved ) && isset( $saved['score'] ) && null !== $saved['score'] ) {
				$has_analysis    = true;
				$score           = (int) $saved['score'];
				$content_changed = ! empty( $saved['content_changed'] );
				if ( ! empty( $saved['db_metadata']['analyzed_at'] ) ) {
					$analyzed_at = (string) $saved['db_metadata']['analyzed_at'];
				} elseif ( ! empty( $saved['metadata']['timestamp'] ) ) {
					$analyzed_at = gmdate( 'Y-m-d H:i:s', (int) $saved['metadata']['timestamp'] );
				}
				$issues        = self::slim_items( $saved['issues'] ?? array() );
				$opportunities = self::slim_items( $saved['opportunities'] ?? ( $saved['improvements'] ?? array() ) );
			}
		}

		$ai_titles = array();
		$ai_descs  = array();
		$has_ai    = false;
		if ( 'post' === $object_type && $object_id > 0 ) {
			$suggestions = LLM_Helper::get_saved_suggestions( $object_id );
			if ( is_array( $suggestions ) ) {
				$ai_titles = self::string_list( $suggestions['titles'] ?? array(), 7 );
				$ai_descs  = self::string_list( $suggestions['descriptions'] ?? array(), 7 );
				$has_ai    = ! empty( $ai_titles ) || ! empty( $ai_descs );
			}
		}

		$keywords      = array();
		$keyword_total = 0;
		if ( '' !== $page_url ) {
			$keyword_data  = self::get_compact_keywords( $page_url );
			$keywords      = $keyword_data['keywords'];
			$keyword_total = $keyword_data['total'];
		}

		wp_send_json_success(
			array(
				'object_id'       => $object_id,
				'object_type'     => $object_type,
				'page_url'        => $page_url,
				'edit_url'        => $edit_url,
				'score'           => $score,
				'analyzed_at'     => $analyzed_at,
				'content_changed' => $content_changed,
				'seo_title'       => $seo_title,
				'seo_description' => $seo_desc,
				'issues'          => $issues,
				'opportunities'   => $opportunities,
				'ai_suggestions'  => array(
					'titles'       => $ai_titles,
					'descriptions' => $ai_descs,
				),
				'keywords'        => $keywords,
				'keyword_total'   => $keyword_total,
				'empty'           => array(
					'analysis'    => ! $has_analysis,
					'suggestions' => ! $has_ai,
					'keywords'    => 0 === $keyword_total,
				),
			)
		);
	}

	/**
	 * Resolve object id/type/url from POST (prefer item_id).
	 *
	 * @return array{object_id: int, object_type: string, page_url: string, edit_url: string}|\WP_Error
	 */
	private static function resolve_context() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in SB_Adminbar_Ajax::ajax_page_snapshot() before this runs.
		$object_id   = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$object_type = isset( $_POST['content_type'] ) ? sanitize_key( wp_unslash( $_POST['content_type'] ) ) : '';
		if ( 'taxonomy' === $object_type ) {
			$object_type = 'term';
		}
		if ( ! in_array( $object_type, array( 'post', 'term' ), true ) ) {
			$object_type = '';
		}

		$page_url = '';
		if ( isset( $_POST['public_url'] ) ) {
			$page_url = self::sanitize_same_site_url( wp_unslash( $_POST['public_url'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( $object_id > 0 && 'post' === $object_type ) {
			$post = get_post( $object_id );
			if ( ! $post ) {
				return new \WP_Error( 'invalid_post', __( 'Post not found.', 'seo-booster' ) );
			}
			$permalink = get_permalink( $object_id );
			return array(
				'object_id'   => $object_id,
				'object_type' => 'post',
				'page_url'    => is_string( $permalink ) ? $permalink : $page_url,
				'edit_url'    => (string) get_edit_post_link( $object_id, 'raw' ),
			);
		}

		if ( $object_id > 0 && 'term' === $object_type ) {
			$term = get_term( $object_id );
			if ( ! $term || is_wp_error( $term ) ) {
				return new \WP_Error( 'invalid_term', __( 'Term not found.', 'seo-booster' ) );
			}
			$term_link = get_term_link( $term );
			$edit_url  = '';
			if ( ! empty( $term->taxonomy ) ) {
				$edit_url = (string) get_edit_term_link( $object_id, $term->taxonomy );
			}
			return array(
				'object_id'   => $object_id,
				'object_type' => 'term',
				'page_url'    => ( ! is_wp_error( $term_link ) && is_string( $term_link ) ) ? $term_link : $page_url,
				'edit_url'    => $edit_url,
			);
		}

		// URL fallback when item_id was not localized (e.g. some builder contexts).
		if ( '' === $page_url ) {
			return new \WP_Error( 'missing_context', __( 'Could not determine the current page.', 'seo-booster' ) );
		}

		$post_id = url_to_postid( $page_url );
		if ( $post_id > 0 ) {
			return array(
				'object_id'   => $post_id,
				'object_type' => 'post',
				'page_url'    => get_permalink( $post_id ) ? get_permalink( $post_id ) : $page_url,
				'edit_url'    => (string) get_edit_post_link( $post_id, 'raw' ),
			);
		}

		return array(
			'object_id'   => 0,
			'object_type' => 'post',
			'page_url'    => $page_url,
			'edit_url'    => '',
		);
	}

	/**
	 * Sanitize a URL and require same host as the site.
	 *
	 * @param string $raw Raw URL.
	 * @return string Empty string if invalid.
	 */
	private static function sanitize_same_site_url( $raw ) {
		$url = esc_url_raw( $raw );
		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return '';
		}

		$home = wp_parse_url( home_url( '/' ) );
		if ( empty( $home['host'] ) ) {
			return '';
		}

		if ( strtolower( $parts['host'] ) !== strtolower( $home['host'] ) ) {
			return '';
		}

		$strip_keys = array(
			'fl_builder',
			'fl_builder_preview',
			'fl_builder_ui_iframe',
			'elementor-preview',
			'elementor_library',
			'preview',
			'preview_id',
			'preview_nonce',
			'seobooster_showdetails',
			'seobooster_showlinks',
			'seobooster_showgsc',
		);

		$path   = isset( $parts['path'] ) ? $parts['path'] : '/';
		$scheme = ! empty( $parts['scheme'] ) ? $parts['scheme'] : ( ! empty( $home['scheme'] ) ? $home['scheme'] : 'https' );
		$clean  = $scheme . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$clean .= ':' . $parts['port'];
		}
		$clean .= $path;

		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query_params );
			foreach ( $strip_keys as $key ) {
				unset( $query_params[ $key ] );
			}
			if ( ! empty( $query_params ) ) {
				$clean .= '?' . http_build_query( $query_params );
			}
		}

		return $clean;
	}

	/**
	 * Slim issue/opportunity rows for the panel.
	 *
	 * @param array $items Raw items.
	 * @return array<int, array{key: string, message: string, severity: string}>
	 */
	private static function slim_items( $items ) {
		if ( ! is_array( $items ) ) {
			return array();
		}

		$out = array();
		foreach ( array_slice( $items, 0, self::LIST_LIMIT ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$message = isset( $item['message'] ) ? wp_strip_all_tags( (string) $item['message'] ) : '';
			if ( function_exists( 'mb_strlen' ) && mb_strlen( $message ) > self::MESSAGE_MAX ) {
				$message = mb_substr( $message, 0, self::MESSAGE_MAX - 1 ) . '…';
			} elseif ( strlen( $message ) > self::MESSAGE_MAX ) {
				$message = substr( $message, 0, self::MESSAGE_MAX - 1 ) . '…';
			}
			$out[] = array(
				'key'      => isset( $item['key'] ) ? sanitize_key( (string) $item['key'] ) : '',
				'message'  => $message,
				'severity' => isset( $item['severity'] ) ? sanitize_key( (string) $item['severity'] ) : '',
			);
		}
		return $out;
	}

	/**
	 * Normalize a list of strings.
	 *
	 * @param mixed $list  Input.
	 * @param int   $limit Max items.
	 * @return array<int, string>
	 */
	private static function string_list( $list, $limit = 7 ) {
		if ( ! is_array( $list ) ) {
			return array();
		}
		$out = array();
		foreach ( array_slice( $list, 0, $limit ) as $value ) {
			$text = sanitize_text_field( (string) $value );
			if ( '' !== $text ) {
				$out[] = $text;
			}
		}
		return $out;
	}

	/**
	 * Compact GSC keyword aggregates for a page URL (no history).
	 *
	 * @param string $page_url Permalink.
	 * @return array{keywords: array<int, array>, total: int}
	 */
	private static function get_compact_keywords( $page_url ) {
		global $wpdb;

		$table = $wpdb->prefix . 'sb2_query_keywords';
		$hist  = $wpdb->prefix . 'sb2_query_keywords_history';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefixed table; value uses %s placeholder.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE page = %s",
				$page_url
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $total < 1 ) {
			return array(
				'keywords' => array(),
				'total'    => 0,
			);
		}

		$limit = self::KEYWORD_LIMIT;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefixed tables; values use placeholders.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT k.query, k.is_used_in_content,
					COALESCE(SUM(h.clicks), 0) AS clicks,
					COALESCE(SUM(h.impressions), 0) AS impressions,
					AVG(h.ctr) AS average_ctr,
					AVG(h.position) AS average_position
				FROM {$table} AS k
				LEFT JOIN {$hist} AS h ON k.id = h.query_keywords_id
				WHERE k.page = %s
				GROUP BY k.id, k.query, k.is_used_in_content
				ORDER BY impressions DESC, clicks DESC
				LIMIT %d",
				$page_url,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$keywords = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$used = isset( $row['is_used_in_content'] ) ? (string) $row['is_used_in_content'] : '0';
				if ( '1' === $used ) {
					$used_label = __( 'Used', 'seo-booster' );
				} elseif ( '-1' === $used ) {
					$used_label = __( 'Not used', 'seo-booster' );
				} else {
					$used_label = __( 'Not analyzed', 'seo-booster' );
				}

				$ctr = isset( $row['average_ctr'] ) ? (float) $row['average_ctr'] : 0.0;
				$pos = isset( $row['average_position'] ) ? (float) $row['average_position'] : 0.0;

				$keywords[] = array(
					'query'       => (string) ( $row['query'] ?? '' ),
					'clicks'      => (int) ( $row['clicks'] ?? 0 ),
					'impressions' => (int) ( $row['impressions'] ?? 0 ),
					'ctr'         => number_format( $ctr, 2 ) . '%',
					'position'    => number_format( $pos, 1 ),
					'used'        => $used_label,
				);
			}
		}

		return array(
			'keywords' => $keywords,
			'total'    => $total,
		);
	}
}
