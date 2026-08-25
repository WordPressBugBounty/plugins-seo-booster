<?php

namespace Cleverplugins\SEOBooster;

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Utils extends Seobooster2 {

	/**
	 * Performs daily maintenance routines for SEO Booster.
	 *
	 * This method cleans up the log table by removing old entries
	 * when the total number of entries exceeds 10,000.
	 *
	 * @since   v0.0.1
	 * @version v1.0.2 Tuesday, August 20th, 2024.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @return void
	 */
	public static function do_seobooster_dailymaintenance() {
		global $wpdb;
		$table_name_log = $wpdb->prefix . 'sb2_log';

		// Get current table stats
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table prefix only; aggregate count query.
		$initial_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name_log}" );
		$initial_size  = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2)
			FROM information_schema.TABLES
			WHERE table_schema = %s AND table_name = %s',
				DB_NAME,
				$table_name_log
			)
		);

		// Delete entries older than 14 days
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
		$deleted_old = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name_log} WHERE logtime < DATE_SUB(NOW(), INTERVAL %d DAY)",
				14
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// If still more than 10000 entries, delete oldest entries
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table prefix only; aggregate count query.
		$current_count  = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name_log}" );
		$deleted_excess = 0;
		if ( $current_count > 10000 ) {
			$entries_to_delete = $current_count - 5000;
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
			$deleted_excess = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table_name_log} ORDER BY logtime ASC LIMIT %d",
					$entries_to_delete
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// Get final table size
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table prefix only; aggregate count query.
		$final_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name_log}" );
		$final_size  = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2)
			FROM information_schema.TABLES
			WHERE table_schema = %s AND table_name = %s',
				DB_NAME,
				$table_name_log
			)
		);

		// Log the maintenance results
		self::log(
			sprintf(
				'Log maintenance: Initial entries: %d (%.2f MB). Deleted %d old entries and %d excess entries. Final entries: %d (%.2f MB)',
				$initial_count,
				$initial_size,
				$deleted_old,
				$deleted_excess,
				$final_count,
				$final_size
			),
			10 // Info priority
		);

		$deleted_ai_hits = AI_Bot_Tracker::cleanup_old_hits();
		if ( $deleted_ai_hits > 0 ) {
			self::log(
				sprintf(
					'AI bot hits maintenance: deleted %d rows (retention %d days, soft cap %d)',
					$deleted_ai_hits,
					AI_Bot_Tracker::get_retention_days(),
					AI_Bot_Tracker::get_max_hit_rows()
				),
				5
			);
		}

		$deleted_ai_referrals = AI_Referral_Tracker::cleanup_old_referrals();
		if ( $deleted_ai_referrals > 0 ) {
			self::log(
				sprintf( 'AI referral hits retention: deleted %d rows older than %d days', $deleted_ai_referrals, AI_Bot_Tracker::get_retention_days() ),
				5
			);
		}

		self::refresh_dashboard_tools_counts();
	}

	/**
	 * Cache dashboard Tools quick-win counts for the admin dashboard card.
	 *
	 * @return void
	 */
	public static function refresh_dashboard_tools_counts() {
		if ( ! defined( 'SEOBOOSTER_PLUGINPATH' ) ) {
			return;
		}

		require_once SEOBOOSTER_PLUGINPATH . 'inc/Tools/Tools_Image_Scanner.php';
		require_once SEOBOOSTER_PLUGINPATH . 'inc/Tools/Tools_Meta_Scanner.php';

		$images_missing = \Cleverplugins\SEOBooster\Tools\Tools_Image_Scanner::count_empty_alt();
		$posts_missing  = \Cleverplugins\SEOBooster\Tools\Tools_Meta_Scanner::count_missing_title_or_description();

		set_transient(
			'sb_dashboard_tools_counts',
			array(
				'images_missing_alt' => (int) $images_missing,
				'posts_missing_meta' => (int) $posts_missing,
				'updated'            => time(),
			),
			DAY_IN_SECONDS
		);
	}

	/**
	 * Makes an auto link via AJAX call (from individual posts)
	 *
	 * @author  Unknown
	 * @since   v0.0.1
	 * @version v1.0.0  Sunday, June 30th, 2024.
	 * @access  public static
	 * @return  void
	 */
	public static function gsc_make_auto_link() {
		if ( isset( $_POST['security'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['security'] ) );
			// Nonce.
		} else {
			$nonce = '';
		}
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'sb_gsc_nonce' ) ) {
			wp_send_json_error(
				array(
					'success' => false,
					'message' => esc_html__( 'Nonce verification failed.', 'seo-booster' ),
				)
			);
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'success' => false,
					'message' => esc_html__( 'You do not have permission to create auto links.', 'seo-booster' ),
				)
			);
		}
		global $wpdb;
		$post_id  = ( isset( $_POST['post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) : '' );
		$query_id = ( isset( $_POST['query_id'] ) ? sanitize_text_field( wp_unslash( $_POST['query_id'] ) ) : '' );
		if ( empty( $post_id ) || empty( $query_id ) ) {
			wp_send_json_error(
				array(
					'success' => false,
					'message' => esc_html__( 'Post ID or Query ID is missing.', 'seo-booster' ),
				)
			);
		}
		$keyword  = $wpdb->get_var( $wpdb->prepare( "SELECT query FROM {$wpdb->prefix}sb2_query_keywords WHERE id = %d", $query_id ) );
		$page_url = get_permalink( $post_id );
		if ( $keyword && $page_url ) {
			$wpdb->insert(
				"{$wpdb->prefix}sb2_autolink",
				array(
					'keyword' => $keyword,
					'url'     => $page_url,
				),
				array( '%s', '%s' )
			);
			$last_insert_id = $wpdb->insert_id;
			// if $last_insert_id is ok, return success
			if ( $last_insert_id ) {
				Seobooster2::flush_autolink_caches();
				wp_send_json_success(
					array(
						'success' => true,
						'message' => '<span class="label label-info">' . esc_html__( 'Linked', 'seo-booster' ) . '</span>',
					)
				);
			} else {
				$wpdb_error = $wpdb->last_error;
				wp_send_json_error(
					array(
						'success' => false,
						'message' => esc_html__( 'Link creation failed.', 'seo-booster' ) . ' ' . esc_html( $wpdb_error ),
					)
				);
			}
		}
		exit;
	}

	/**
	 * prefixsetupschedule.
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Tuesday, June 25th, 2024.
	 * @access  public static
	 * @return  void
	 */
	public static function prefixsetupschedule() {
		if ( ! wp_next_scheduled( 'seobooster_email_update' ) ) {
			wp_schedule_event( time(), 'weekly', 'seobooster_email_update' );
		}
		if ( ! wp_next_scheduled( 'seobooster_gsc_data_fetch' ) ) {
			wp_schedule_event( time(), 'daily', 'seobooster_gsc_data_fetch' );
		}
		if ( ! wp_next_scheduled( 'seobooster_dailymaintenance' ) ) {
			wp_schedule_event( time(), 'daily', 'seobooster_dailymaintenance' );
		}
		if ( ! wp_next_scheduled( 'seobooster_token_validation' ) ) {
			wp_schedule_event( time(), 'sixhours', 'seobooster_token_validation' );
		}
		if ( ! wp_next_scheduled( 'seobooster_cache_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'seobooster_cache_cleanup' );
		}
	}

	/**
	 * Sanitize a Search Console query for storage without stripping literal "%".
	 *
	 * WordPress sanitize_text_field() removes percent-encoded octets (%XX), which
	 * corrupts queries such as "cbd 4%aa" and is the wrong tool for search terms.
	 *
	 * @param string $query Raw query.
	 * @return string
	 */
	public static function sanitize_gsc_query( $query ) {
		$query = (string) $query;
		if ( function_exists( 'wp_check_invalid_utf8' ) ) {
			$query = wp_check_invalid_utf8( $query );
		}
		$query = wp_strip_all_tags( $query );
		$query = preg_replace( '/[\r\n\t]+/', ' ', $query );
		$query = trim( preg_replace( '/ +/', ' ', (string) $query ) );

		return $query;
	}

	/**
	 * Normalize a keyword for case-insensitive content matching.
	 *
	 * Preserves literal "%" (unlike sanitize_text_field) and collapses unicode spaces.
	 *
	 * @param string $keyword Keyword.
	 * @return string Lowercased keyword, or empty string.
	 */
	public static function normalize_keyword_for_match( $keyword ) {
		$keyword = self::sanitize_gsc_query( $keyword );
		$keyword = html_entity_decode( $keyword, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$keyword = preg_replace( '/[\x{00A0}\x{202F}\x{2007}\x{2009}]/u', ' ', $keyword );
		$keyword = trim( preg_replace( '/ +/', ' ', (string) $keyword ) );

		if ( '' === $keyword ) {
			return '';
		}

		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $keyword, 'UTF-8' );
		}

		return strtolower( $keyword );
	}

	/**
	 * Normalize haystack text before keyword substring / boundary matching.
	 *
	 * @param string $text Raw or HTML text.
	 * @return string
	 */
	public static function normalize_text_for_keyword_match( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/[\r\n\t\x{00A0}\x{202F}\x{2007}\x{2009}]+/u', ' ', $text );
		$text = trim( preg_replace( '/ +/', ' ', (string) $text ) );

		return $text;
	}

	/**
	 * Regex for whole-phrase keyword match using Unicode letter/number edges.
	 *
	 * Unlike \b, this still matches terms that end with punctuation such as "%".
	 *
	 * @param string $keyword           Keyword (not preg-quoted).
	 * @param bool   $case_insensitive  Case-insensitive flag.
	 * @return string
	 */
	public static function keyword_boundary_pattern( $keyword, $case_insensitive = true ) {
		$flags = $case_insensitive ? 'ui' : 'u';

		return '/(?<![\p{L}\p{N}_])(' . preg_quote( (string) $keyword, '/' ) . ')(?![\p{L}\p{N}_])/' . $flags;
	}

	/**
	 * Whether haystack contains keyword as a whole phrase (punctuation-safe).
	 *
	 * @param string $text              Haystack.
	 * @param string $keyword           Needle.
	 * @param bool   $case_insensitive  Case-insensitive flag.
	 * @return bool
	 */
	public static function text_has_keyword_bounded( $text, $keyword, $case_insensitive = true ) {
		$text    = self::normalize_text_for_keyword_match( $text );
		$keyword = $case_insensitive
			? self::normalize_keyword_for_match( $keyword )
			: self::sanitize_gsc_query( $keyword );

		if ( '' === $text || '' === $keyword ) {
			return false;
		}

		return (bool) preg_match( self::keyword_boundary_pattern( $keyword, $case_insensitive ), $text );
	}

	/**
	 * Whether haystack contains any of the keyword variations (substring, case-insensitive).
	 *
	 * Used for GSC "used in content" detection where phrase boundaries are too strict
	 * for short tokens, but percent signs and entity-decoding still matter.
	 *
	 * @param string   $text        Haystack (HTML or plain).
	 * @param string[] $variations  Already-normalized (lowercase) variations.
	 * @return bool
	 */
	public static function text_contains_keyword_variations( $text, array $variations ) {
		$text = self::normalize_text_for_keyword_match( $text );
		if ( '' === $text ) {
			return false;
		}

		$haystack = function_exists( 'mb_strtolower' )
			? mb_strtolower( $text, 'UTF-8' )
			: strtolower( $text );

		foreach ( $variations as $variation ) {
			$variation = (string) $variation;
			if ( '' === $variation ) {
				continue;
			}
			if ( false !== strpos( $haystack, $variation ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks a string against an array of keywords and returns any matches or false if no match.
	 *
	 * @author  Unknown
	 * @since   v0.0.1
	 * @version v1.0.0  Tuesday, November 30th, 2021.
	 * @version v1.0.1  Friday, June 21st, 2024.
	 * @access  public static
	 * @param   mixed   $str
	 * @param   array   $arr
	 * @return  boolean
	 */
	public static function array_in_string( $str, array $arr ) {
		$return_arr = array();
		foreach ( $arr as $arr_value ) {
			$pattern = self::keyword_boundary_pattern( $arr_value['kw'], true );
			if ( preg_match(
				$pattern,
				(string) $str,
				$matches,
				PREG_OFFSET_CAPTURE
			) ) {
				$wrdpos               = $matches[0][1];
				$orgword              = mb_substr( (string) $str, $wrdpos, mb_strlen( $arr_value['kw'] ) );
				$arr_value['orgword'] = $orgword;
				$return_arr[]         = $arr_value;
			}
		}
		if ( ! empty( $return_arr ) ) {
			return $return_arr;
		}
		return false;
	}

	/**
	 * Ordered list of plugin-owned table slugs (without $wpdb->prefix).
	 *
	 * @return string[]
	 */
	public static function get_plugin_table_slugs() {
		return array(
			'sb2_query_keywords',
			'sb2_query_keywords_history',
			'sb2_autolink',
			'sb2_404',
			'sb2_log',
			'sb2_seo_urls',
			'sb2_seo_analysis',
			'sb2_seo_issues',
			'sb2_seo_url_status',
			'sb2_llm_seo_suggestions',
			'sb2_improvements_tracking',
			'sb2_ai_bot_hits',
			'sb2_ai_referrals',
		);
	}

	/**
	 * Map of plugin table slug => full table name (with $wpdb->prefix).
	 *
	 * @return array<string, string>
	 */
	public static function get_plugin_table_names() {
		global $wpdb;

		$tables = array();
		foreach ( self::get_plugin_table_slugs() as $slug ) {
			$tables[ $slug ] = $wpdb->prefix . $slug;
		}

		return $tables;
	}

	/**
	 * Whether a plugin table exists in the database.
	 *
	 * @param string $table_name Full table name including prefix.
	 * @return bool
	 */
	public static function plugin_table_exists( $table_name ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Action Scheduler group slug for SEO Booster background jobs.
	 *
	 * @return string
	 */
	public static function get_action_scheduler_group_slug() {
		return 'seo-booster';
	}

	/**
	 * Whether a WP-Cron hook belongs to SEO Booster.
	 *
	 * @param string $hook Cron hook name.
	 * @return bool
	 */
	public static function is_plugin_cron_hook( $hook ) {
		if ( strpos( $hook, 'seobooster' ) !== false ) {
			return true;
		}

		return 0 === strpos( $hook, 'sb_gsc_' ) || 0 === strpos( $hook, 'sb_' );
	}

	/**
	 * create_database_tables.
	 *
	 * @author  Unknown
	 * @since   v0.0.1
	 * @version v1.0.0  Saturday, August 7th, 2021.
	 * @access  public static
	 * @return  void
	 */
	public static function create_database_tables() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$wpdb_collate = $wpdb->get_charset_collate();
		$table_name   = $wpdb->prefix . 'sb2_query_keywords';

		// Handle migration from varchar to text for query field
		// Check if table exists and has old schema - validate table name
		$allowed_tables = array( $wpdb->prefix . 'sb2_query_keywords' );
		$table_exists   = false;
		if ( in_array( $table_name, $allowed_tables, true ) ) {
			$table_name_escaped = esc_sql( $table_name );
			$table_exists       = $wpdb->get_var(
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$table_name_escaped
				)
			);
		}

		if ( $table_exists && in_array( $table_name, $allowed_tables, true ) ) {
			$table_name_escaped  = esc_sql( $table_name );
			$column_name_escaped = esc_sql( 'query' );
			// Check if query column is varchar (could be 191 or 1000)
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
			$column_info = $wpdb->get_row(
				$wpdb->prepare(
					"SHOW COLUMNS FROM `{$table_name_escaped}` LIKE %s",
					$column_name_escaped
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $column_info && ( strpos( $column_info->{'Type'}, 'varchar(1000)' ) !== false || strpos( $column_info->{'Type'}, 'varchar(191)' ) !== false ) ) {
				// Drop indexes first to avoid conflicts
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DDL migration; table name escaped with esc_sql().
				$wpdb->query( "ALTER TABLE `{$table_name_escaped}` DROP INDEX IF EXISTS unique_query_page" );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DDL migration; table name escaped with esc_sql().
				$wpdb->query( "ALTER TABLE `{$table_name_escaped}` DROP INDEX IF EXISTS query" );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DDL migration; table name escaped with esc_sql().
				$wpdb->query( "ALTER TABLE `{$table_name_escaped}` DROP INDEX IF EXISTS page" );

				// Change column types
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DDL migration; table name escaped with esc_sql().
				$wpdb->query( "ALTER TABLE `{$table_name_escaped}` MODIFY COLUMN query text NOT NULL" );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DDL migration; table name escaped with esc_sql().
				$wpdb->query( "ALTER TABLE `{$table_name_escaped}` MODIFY COLUMN page varchar(500) NOT NULL" );

				// Recreate indexes with proper key length
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DDL migration; table name escaped with esc_sql().
				$wpdb->query( "ALTER TABLE `{$table_name_escaped}` ADD UNIQUE KEY unique_query_page (query(191), page(191))" );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DDL migration; table name escaped with esc_sql().
				$wpdb->query( "ALTER TABLE `{$table_name_escaped}` ADD KEY query (query(191))" );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DDL migration; table name escaped with esc_sql().
				$wpdb->query( "ALTER TABLE `{$table_name_escaped}` ADD KEY page (page(191))" );
			}
		}

		$sql = "CREATE TABLE {$table_name} (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				query text NOT NULL,
				page varchar(500) NOT NULL,
				first_seen_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				latest_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				is_used_in_content tinyint(1) DEFAULT 0 NOT NULL,
				last_checked datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY unique_query_page (query(191), page(191)),
				KEY query (query(191)),
				KEY page (page(191))
				) $wpdb_collate";

		dbDelta( $sql );

		$table_name_history = $wpdb->prefix . 'sb2_query_keywords_history';
		$sql                = "CREATE TABLE {$table_name_history} (
id mediumint(9) NOT NULL AUTO_INCREMENT,
query_keywords_id mediumint(9) NOT NULL,
clicks int(11) NOT NULL,
impressions int(11) NOT NULL,
ctr float NOT NULL,
position float NOT NULL,
date DATE NOT NULL,
PRIMARY KEY  (id),
KEY query_keywords_id (query_keywords_id),
KEY date (date),
UNIQUE KEY unique_query_date (query_keywords_id, date)
) $wpdb_collate";

		dbDelta( $sql );

		$table_name = $wpdb->prefix . 'sb2_autolink';
		$sql        = "CREATE TABLE {$table_name} (
id bigint(20) NOT NULL AUTO_INCREMENT,
keyword varchar(255),
url varchar(255),
disable int(1) DEFAULT '0',
nflw int(1) DEFAULT '0',
lastseen longtext,
PRIMARY KEY  (id),
KEY keyword (keyword),
KEY url (url)) $wpdb_collate";

		dbDelta( $sql );

		$table_name = $wpdb->prefix . 'sb2_404';
		$sql        = "CREATE TABLE {$table_name} (
id bigint(20) NOT NULL AUTO_INCREMENT,
lp varchar(500) NOT NULL,
code varchar(3) DEFAULT NULL,
firstseen timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
lastseen timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
visits int(11) NOT NULL,
referer text NOT NULL,
PRIMARY KEY  (id)) $wpdb_collate";

		dbDelta( $sql );

		$table_name = $wpdb->prefix . 'sb2_log';
		$sql        = "CREATE TABLE {$table_name} (
ID bigint(20) NOT NULL AUTO_INCREMENT,
logtime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
prio tinyint(1) NOT NULL,
log varchar(2048) NOT NULL,
PRIMARY KEY  (ID),
KEY ID (ID)) $wpdb_collate";

		dbDelta( $sql );

		// SEO URLs table - Store unique URLs with metadata
		$table_name = $wpdb->prefix . 'sb2_seo_urls';
		$sql        = "CREATE TABLE {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			url varchar(1000) NOT NULL,
			url_hash varchar(64) NOT NULL,
			object_id bigint(20) DEFAULT NULL,
			object_type varchar(20) DEFAULT NULL,
			post_title varchar(255) DEFAULT NULL,
			last_analyzed timestamp NULL DEFAULT NULL,
			analysis_count int(11) DEFAULT 0,
			reachability varchar(20) DEFAULT NULL,
			http_status smallint DEFAULT NULL,
			redirect_to varchar(1000) DEFAULT NULL,
			reachability_checked_at timestamp NULL DEFAULT NULL,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY url_hash (url_hash),
			KEY url (url(191)),
			KEY object_id (object_id),
			KEY object_type (object_type),
			KEY last_analyzed (last_analyzed),
			KEY reachability (reachability),
			KEY reachability_checked_at (reachability_checked_at)
		) $wpdb_collate";

		dbDelta( $sql );

		// SEO Analysis table - Store analysis sessions
		$table_name = $wpdb->prefix . 'sb2_seo_analysis';
		$sql        = "CREATE TABLE {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			url_id bigint(20) NOT NULL,
			score tinyint(3) DEFAULT 0,
			issue_count tinyint(3) DEFAULT 0,
			status varchar(20) DEFAULT 'pending',
			improvements longtext DEFAULT NULL,
			good longtext DEFAULT NULL,
			analyzed_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY url_id (url_id),
			KEY status (status),
			KEY analyzed_at (analyzed_at),
			KEY score (score)
		) $wpdb_collate";

		dbDelta( $sql );

		// SEO Issues table - Store individual issues
		$table_name = $wpdb->prefix . 'sb2_seo_issues';
		$sql        = "CREATE TABLE {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			analysis_id bigint(20) NOT NULL,
			url_id bigint(20) NOT NULL,
			is_sitewide tinyint(1) DEFAULT 0,
			issue_key varchar(100) NOT NULL,
			message text NOT NULL,
			severity varchar(20) NOT NULL,
			user_status varchar(20) DEFAULT 'active',
			extra_data longtext DEFAULT NULL,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			status_updated_at timestamp NULL DEFAULT NULL,
			PRIMARY KEY (id),
			KEY analysis_id (analysis_id),
			KEY url_id (url_id),
			KEY is_sitewide (is_sitewide),
			KEY issue_key (issue_key),
			KEY severity (severity),
			KEY user_status (user_status),
			KEY created_at (created_at),
			KEY status_updated_at (status_updated_at)
		) $wpdb_collate";

		dbDelta( $sql );

		// Cached URL status for link/image validation.
		$table_name = $wpdb->prefix . 'sb2_seo_url_status';
		$sql        = "CREATE TABLE {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			url_hash varchar(64) NOT NULL,
			url text NOT NULL,
			kind varchar(20) NOT NULL DEFAULT 'link',
			status varchar(20) NOT NULL DEFAULT 'unknown',
			status_code smallint(5) NOT NULL DEFAULT 0,
			final_url text DEFAULT NULL,
			error_message text DEFAULT NULL,
			checked_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY url_hash_kind (url_hash, kind),
			KEY kind (kind),
			KEY checked_at (checked_at)
		) $wpdb_collate";

		dbDelta( $sql );

		// LLM SEO Suggestions table - Store AI-generated SEO suggestions
		$table_name = $wpdb->prefix . 'sb2_llm_seo_suggestions';
		$sql        = "CREATE TABLE {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			post_id bigint(20) NOT NULL,
			wp_user_id bigint(20) NOT NULL,
			language varchar(10) NOT NULL,
			condensed_text_hash varchar(64) NOT NULL,
			api_response_json longtext DEFAULT NULL,
			credits_left int(11) DEFAULT 0,
			status varchar(20) DEFAULT 'pending',
			request_id varchar(100) DEFAULT NULL,
			endpoint_version varchar(20) DEFAULT NULL,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY created_at (created_at),
			KEY status (status)
		) $wpdb_collate";

		// dbDelta() is WordPress's standard function for creating/updating database tables
		// It compares the desired table structure with the existing one and makes necessary changes
		// This is the WordPress-recommended approach for database schema management
		dbDelta( $sql );

		// Improvements Tracking table - Track daily improvements for gamification
		$table_name = $wpdb->prefix . 'sb2_improvements_tracking';
		$sql        = "CREATE TABLE {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			date date NOT NULL,
			improvements_count int(11) DEFAULT 0,
			gsc_issues_fixed int(11) DEFAULT 0,
			local_issues_fixed int(11) DEFAULT 0,
			total_issues_resolved int(11) DEFAULT 0,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY date (date),
			KEY created_at (created_at)
		) $wpdb_collate";

		dbDelta( $sql );

		// AI bot hits — aggregated crawler visit tracking.
		$table_name = $wpdb->prefix . 'sb2_ai_bot_hits';
		$sql        = "CREATE TABLE {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			bot_name varchar(100) NOT NULL,
			bot_purpose varchar(20) NOT NULL DEFAULT 'research',
			request_path varchar(500) NOT NULL,
			normalized_url varchar(500) NOT NULL DEFAULT '',
			url_hash varchar(64) NOT NULL,
			object_id bigint(20) DEFAULT NULL,
			object_type varchar(20) DEFAULT NULL,
			status_code smallint(5) NOT NULL DEFAULT 200,
			request_kind varchar(20) NOT NULL DEFAULT 'unmapped',
			hit_date date NOT NULL,
			first_seen datetime NOT NULL,
			last_seen datetime NOT NULL,
			visits int(11) NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			UNIQUE KEY unique_bot_url_day (bot_name, url_hash, hit_date),
			KEY bot_name (bot_name),
			KEY bot_purpose (bot_purpose),
			KEY url_hash (url_hash),
			KEY object_id (object_id),
			KEY request_kind (request_kind),
			KEY object_lookup (object_type, object_id),
			KEY hit_date (hit_date),
			KEY last_seen (last_seen)
		) $wpdb_collate";

		dbDelta( $sql );

		if ( AI_Bot_Tracker::table_has_request_kind_column() ) {
			AI_Bot_Tracker::backfill_request_kinds();
			AI_Bot_Tracker::backfill_redirect_kinds();
		}

		// AI referral hits — human visits from AI answer engines.
		$table_name = $wpdb->prefix . 'sb2_ai_referrals';
		$sql        = "CREATE TABLE {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			source varchar(80) NOT NULL,
			referrer_host varchar(120) NOT NULL DEFAULT '',
			landing_path varchar(500) NOT NULL,
			normalized_url varchar(500) NOT NULL DEFAULT '',
			url_hash varchar(64) NOT NULL,
			object_id bigint(20) DEFAULT NULL,
			object_type varchar(20) DEFAULT NULL,
			hit_date date NOT NULL,
			first_seen datetime NOT NULL,
			last_seen datetime NOT NULL,
			visits int(11) NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			UNIQUE KEY unique_source_url_day (source, url_hash, hit_date),
			KEY source (source),
			KEY url_hash (url_hash),
			KEY object_id (object_id),
			KEY object_lookup (object_type, object_id),
			KEY hit_date (hit_date),
			KEY last_seen (last_seen)
		) $wpdb_collate";

		dbDelta( $sql );

		// Note: dbDelta() should handle adding missing columns automatically.
		// If columns are still missing after dbDelta(), the safety checks in
		// get_sitewide_issues() and get_sitewide_stats() will trigger migration.

		self::cleanup_invalid_query_keywords();
		self::cleanup_non_html_file_url_issues();

		self::log( 'Updated database tables', 10 );
		$previous_db_version = get_option( 'SEOBOOSTER_INSTALLED_DB_VERSION', '0' );
		update_option( 'SEOBOOSTER_INSTALLED_DB_VERSION', SEOBOOSTER_DB_VERSION );
		if ( version_compare( $previous_db_version, SEOBOOSTER_DB_VERSION, '<' ) ) {
			update_option( 'seobooster_scanner_polish_notice', '1' );
			delete_transient( 'sb_seo_analysis_stats' );
		}
	}

	/**
	 * Remove per-URL possibilities stored for direct file URLs (PDF, images, archives).
	 *
	 * Earlier versions ran HTML content checks against raw file bytes from GSC URLs,
	 * producing meaningless issues. Files stay in sb2_seo_urls but carry no possibilities.
	 *
	 * @since 7.4.1
	 * @return int Number of URLs cleaned.
	 */
	public static function cleanup_non_html_file_url_issues() {
		global $wpdb;

		$urls_table   = $wpdb->prefix . 'sb2_seo_urls';
		$issues_table = $wpdb->prefix . 'sb2_seo_issues';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefixed table names only; no user input.
		$rows = $wpdb->get_results( "SELECT id, url FROM {$urls_table}" );
		if ( empty( $rows ) ) {
			return 0;
		}

		$file_url_ids = array();
		foreach ( $rows as $row ) {
			if ( \Cleverplugins\SEOBooster\Analysis\Page_Reachability::is_non_html_file_url( (string) $row->url ) ) {
				$file_url_ids[] = (int) $row->id;
			}
		}

		if ( empty( $file_url_ids ) ) {
			return 0;
		}

		$ids_sql = implode( ',', array_map( 'intval', $file_url_ids ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- IDs are integers built above.
		$wpdb->query( "DELETE FROM {$issues_table} WHERE url_id IN ({$ids_sql}) AND is_sitewide = 0" );

		delete_transient( 'sb_seo_analysis_stats' );
		self::log( sprintf( 'Removed stored possibilities for %d direct file URLs (PDF, images, etc.)', count( $file_url_ids ) ), 5 );

		return count( $file_url_ids );
	}

	/**
	 * Remove keyword rows that cannot be processed (empty query) and their history.
	 *
	 * Empty queries were previously insertable by the scheduled GSC batch path.
	 * They cannot be reconstructed and block keyword processing from completing.
	 *
	 * @since 7.4.0
	 * @return int Number of keyword rows removed.
	 */
	public static function cleanup_invalid_query_keywords() {
		global $wpdb;

		$table_keywords = $wpdb->prefix . 'sb2_query_keywords';
		$table_history  = $wpdb->prefix . 'sb2_query_keywords_history';

		$invalid_ids = $wpdb->get_col(
			"SELECT id FROM {$table_keywords} WHERE query IS NULL OR TRIM(query) = ''" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefixed table names only; no user input.
		);

		if ( empty( $invalid_ids ) ) {
			return 0;
		}

		$invalid_ids = array_map( 'absint', $invalid_ids );
		$invalid_ids = array_filter( $invalid_ids );
		if ( empty( $invalid_ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $invalid_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Placeholder count matches $invalid_ids; values prepared via splat.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_history} WHERE query_keywords_id IN ({$placeholders})", ...$invalid_ids ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Placeholder count matches $invalid_ids; values prepared via splat.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_keywords} WHERE id IN ({$placeholders})", ...$invalid_ids ) );

		$count = count( $invalid_ids );

		self::log(
			sprintf( 'Removed %d invalid GSC keyword row(s) with empty query.', $count ),
			3
		);

		return $count;
	}


	/**
	 * Returns icon in SVG format
	 * Thanks Yoast for example code.
	 *
	 * @author  Unknown
	 * @since   v0.0.1
	 * @version v1.0.0  Tuesday, November 30th, 2021.
	 * @access  public static
	 * @param   boolean $base64 Default: true
	 * @return  mixed
	 */
	public static function get_icon_svg( $base64 = true ) {
		$svg = '<svg viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:bx="https://boxy-svg.com">
			<defs>
			<symbol id="symbol-0" viewBox="0 0 100 100">
			<path d="M 63.332 70.126 L 63.332 70.126 L 63.332 70.126 C 63.332 67.186 62.292 64.896 60.212 63.256 L 60.212 63.256 L 60.212 63.256 C 58.132 61.616 54.475 59.916 49.242 58.156 L 49.242 58.156 L 49.242 58.156 C 44.015 56.403 39.739 54.706 36.412 53.066 L 36.412 53.066 L 36.412 53.066 C 25.612 47.759 20.212 40.466 20.212 31.186 L 20.212 31.186 L 20.212 31.186 C 20.212 26.566 21.555 22.489 24.242 18.956 L 24.242 18.956 L 24.242 18.956 C 26.935 15.423 30.745 12.673 35.672 10.706 L 35.672 10.706 L 35.672 10.706 C 40.599 8.739 46.135 7.756 52.282 7.756 L 52.282 7.756 L 52.282 7.756 C 58.275 7.756 63.649 8.826 68.402 10.966 L 68.402 10.966 L 68.402 10.966 C 73.155 13.106 76.852 16.149 79.492 20.096 L 79.492 20.096 L 79.492 20.096 C 82.125 24.049 83.442 28.566 83.442 33.646 L 83.442 33.646 L 63.392 33.646 L 63.392 33.646 C 63.392 30.246 62.352 27.613 60.272 25.746 L 60.272 25.746 L 60.272 25.746 C 58.192 23.873 55.375 22.936 51.822 22.936 L 51.822 22.936 L 51.822 22.936 C 48.235 22.936 45.402 23.729 43.322 25.316 L 43.322 25.316 L 43.322 25.316 C 41.235 26.896 40.192 28.909 40.192 31.356 L 40.192 31.356 L 40.192 31.356 C 40.192 33.496 41.339 35.433 43.632 37.166 L 43.632 37.166 L 43.632 37.166 C 45.925 38.906 49.955 40.703 55.722 42.556 L 55.722 42.556 L 55.722 42.556 C 61.489 44.403 66.222 46.396 69.922 48.536 L 69.922 48.536 L 69.922 48.536 C 78.935 53.729 83.442 60.889 83.442 70.016 L 83.442 70.016 L 83.442 70.016 C 83.442 77.309 80.692 83.036 75.192 87.196 L 75.192 87.196 L 75.192 87.196 C 69.692 91.363 62.152 93.446 52.572 93.446 L 52.572 93.446 L 52.572 93.446 C 45.812 93.446 39.692 92.233 34.212 89.806 L 34.212 89.806 L 34.212 89.806 C 28.732 87.379 24.609 84.056 21.842 79.836 L 21.842 79.836 L 21.842 79.836 C 19.075 75.616 17.692 70.759 17.692 65.266 L 17.692 65.266 L 37.852 65.266 L 37.852 65.266 C 37.852 69.733 39.005 73.026 41.312 75.146 L 41.312 75.146 L 41.312 75.146 C 43.625 77.259 47.379 78.316 52.572 78.316 L 52.572 78.316 L 52.572 78.316 C 55.892 78.316 58.515 77.603 60.442 76.176 L 60.442 76.176 L 60.442 76.176 C 62.369 74.743 63.332 72.726 63.332 70.126 Z" transform="matrix(1, 0, 0, 1, 0, 0)" style="fill: rgb(130, 135, 140); white-space: pre;" id="s"/>
			</symbol>
			</defs>
			<use width="100" height="100" transform="matrix(4.947808, 0, 0, 4.947808, -20.354914, -11.482257)" xlink:href="#symbol-0"/>
			<path style="paint-order: stroke; fill: rgb(130, 135, 140);" d="M 349.355 16.098 C 333.687 49.355 248.938 171.838 248.938 171.838 C 248.938 171.838 228.3 199.676 236.116 203.927 C 247.584 210.168 267.795 206.135 284.389 206.805 C 309.456 207.816 329.639 205.313 341.68 205.786 C 341.68 205.786 359.942 201.1 363.11 211.672 C 365.18 218.581 354.131 230.067 354.131 230.067 L 105.339 481.212 L 213.627 310.542 C 213.627 310.542 221.796 293.779 216.787 287.127 C 210.653 278.986 186.557 281.117 186.557 281.117 C 186.557 281.117 140.259 279.657 117.109 279.939 C 108.054 280.05 99.5 279.319 99.082 272.877 C 98.532 264.365 100.711 262.353 110.047 252.866 C 188.089 173.584 349.355 16.098 349.355 16.098 Z"/>
			</svg>';
		if ( $base64 ) {
			return 'data:image/svg+xml;base64,' . base64_encode( $svg );
			//phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
		return $svg;
	}

	/**
	 * Whether automatic link injection should skip the current request.
	 *
	 * Covers REST API, JSON, non-page requests, sitemaps, previews, and static assets.
	 *
	 * @return bool
	 */
	public static function should_skip_autolink_processing() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return true;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return true;
		}

		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return true;
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return true;
		}

		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return true;
		}

		if ( function_exists( 'is_robots' ) && is_robots() ) {
			return true;
		}

		if ( function_exists( 'is_trackback' ) && is_trackback() ) {
			return true;
		}

		if ( function_exists( 'is_search' ) && is_search() ) {
			return true;
		}

		if ( function_exists( 'is_embed' ) && is_embed() ) {
			return true;
		}

		if ( function_exists( 'is_404' ) && is_404() ) {
			return true;
		}

		if ( function_exists( 'is_preview' ) && is_preview() ) {
			return true;
		}

		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return true;
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = strtolower( wp_unslash( $_SERVER['REQUEST_URI'] ) );

			if ( false !== strpos( $request_uri, '/wp-json/' ) || preg_match( '#/wp-json$#', $request_uri ) ) {
				return true;
			}

			if ( preg_match( '#/wp-sitemap#i', $request_uri ) || preg_match( '#sitemap[^/]*\.xml#i', $request_uri ) ) {
				return true;
			}

			$skip_extensions = array( '.ico', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.css', '.js', '.webp', '.scss' );
			foreach ( $skip_extensions as $ext ) {
				if ( false !== strpos( $request_uri, $ext ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Whether automatic linking is allowed for the current front-end view.
	 *
	 * Requires a singular post/page with _sbp-autolink meta set to yes.
	 *
	 * @return bool
	 */
	public static function is_autolink_allowed_for_current_view() {
		if ( ! function_exists( 'is_singular' ) || ! is_singular() ) {
			return false;
		}

		global $post;
		if ( ! $post || ! isset( $post->ID ) ) {
			return false;
		}

		return get_post_meta( $post->ID, '_sbp-autolink', true ) === 'yes';
	}

	/**
	 * Whether a stored relative path points at the WordPress REST API.
	 *
	 * @param string $path Relative path stored in lastseen, e.g. "/wp-json/wp/v2/pages".
	 * @return bool
	 */
	public static function is_rest_api_path( $path ) {
		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}

		return (bool) preg_match( '#(^|/)wp-json(/|$)#i', $path );
	}

	/**
	 * seobooster_currenturl.
	 *
	 * @author  Unknown
	 * @since   v0.0.1
	 * @version v1.0.0  Wednesday, February 23rd, 2022.
	 * @access  public static
	 * @param   boolean $full   Default: false
	 * @return  mixed
	 */
	public static function seobooster_currenturl( $full = false ) {
		// no need to run in the admin...
		if ( is_admin() ) {
			return;
		}
		$phpdetected = add_query_arg( null, null );
		if ( ! $phpdetected ) {
			$phpdetected = ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' );
		}
		// Clean up URL if we have a value
		if ( $phpdetected ) {
			$phpdetected = remove_query_arg( array( 'gclid' ), $phpdetected );
			// removes various params from url
		}
		if ( $full ) {
			return esc_url_raw( site_url( $phpdetected ) );
		}
		return esc_url_raw( $phpdetected );
	}

	/**
	 * remove_http() - Function strips http:// or https://
	 *
	 * @author  Unknown
	 * @since   v0.0.1
	 * @version v1.0.0  Wednesday, February 23rd, 2022.
	 * @access  public static
	 * @param   string  $url    Default: ''
	 * @return  mixed
	 */
	public static function remove_http( $url = '' ) {
		if ( 'http://' === $url || 'https://' === $url ) {
			return $url;
		}
		$matches = substr( $url, 0, 7 );
		if ( 'http://' === $matches ) {
			$url = substr( $url, 7 );
		} else {
			$matches = substr( $url, 0, 8 );
			if ( 'https://' === $matches ) {
				$url = substr( $url, 8 );
			}
		}
		return $url;
	}

	/**
	 * Logs events to the plugin debug log table (sb2_log).
	 *
	 * Messages are internal diagnostics only; do not prefix with the plugin name.
	 *
	 * @author  Unknown
	 * @since   v0.0.1
	 * @version v1.0.0  Wednesday, February 23rd, 2022.
	 * @access  public static
	 * @param   mixed              $text  Log message (arrays/objects are JSON-encoded).
	 * @param   int|string         $prio  Priority. Integers: 0 Normal, 1 Debug, 2 Error, 3 Warning, 5 Info, 10 Success.
	 *                                    Legacy strings 'error', 'warning', 'info', 'debug', 'success', 'normal' are also accepted.
	 * @return  void
	 */
	public static function log( $text, $prio = 0 ) {
		if ( ! is_scalar( $text ) ) {
			$text = wp_json_encode( $text );
		}
		$text = substr( (string) $text, 0, 2048 );
		$prio = self::normalize_log_priority( $prio );

		global $wpdb;
		$table_name_log = $wpdb->prefix . 'sb2_log';
		$wpdb->insert(
			$table_name_log,
			array(
				'logtime' => current_time( 'mysql' ),
				'prio'    => $prio,
				'log'     => $text,
			),
			array( '%s', '%d', '%s' )
		);
	}

	/**
	 * Normalize log priority to an integer sb_log_table understands.
	 *
	 * @param int|string $prio Requested priority.
	 * @return int
	 */
	private static function normalize_log_priority( $prio ) {
		if ( is_string( $prio ) ) {
			$map = array(
				'normal'  => 0,
				'debug'   => 1,
				'error'   => 2,
				'warning' => 3,
				'info'    => 5,
				'success' => 10,
			);
			$key = strtolower( $prio );

			return isset( $map[ $key ] ) ? $map[ $key ] : 0;
		}

		return (int) $prio;
	}

	/**
	 * UTM source slug for outbound seoboosterpro.com links (free vs Pro build).
	 *
	 * @return string
	 */
	public static function get_web_link_utm_source() {
		$utm_source = 'seo_booster_free';
		if ( function_exists( __NAMESPACE__ . '\\seobooster_fs' ) ) {
			$fs = seobooster_fs();
			if ( is_object( $fs ) && method_exists( $fs, 'is_premium' ) && $fs->is_premium() ) {
				$utm_source = 'seo_booster_pro';
			}
		}

		return $utm_source;
	}

	/**
	 * Build a tagged seoboosterpro.com URL for admin links and marketing fallbacks.
	 *
	 * @param string $placement Where in the plugin the link is placed (utm_content).
	 * @param string $page      Path on seoboosterpro.com (e.g. docs, pricing, support).
	 * @param array  $params    Optional extra query parameters.
	 * @return string
	 */
	public static function generate_cp_web_link( $placement = '', $page = '/', $params = array() ) {
		$base_url = 'https://seoboosterpro.com';
		if ( '/' !== $page ) {
			$page = '/' . trim( $page, '/' ) . '/';
		}
		$parts = array_merge(
			array(
				'utm_source'   => self::get_web_link_utm_source(),
				'utm_medium'   => 'plugin',
				'utm_content'  => $placement,
				'utm_campaign' => 'seo_booster',
			),
			$params
		);
		$query = http_build_query( $parts, '', '&' );

		return $base_url . $page . '?' . $query;
	}

	/**
	 * Pro upgrade URL: Freemius checkout when available, else tagged pricing page.
	 *
	 * @param string $placement utm_content when falling back to seoboosterpro.com.
	 * @return string
	 */
	public static function get_pro_upgrade_url( $placement = '' ) {
		if ( function_exists( __NAMESPACE__ . '\\seobooster_fs' ) ) {
			$fs = seobooster_fs();
			if ( is_object( $fs ) && method_exists( $fs, 'get_upgrade_url' ) ) {
				$fs_upgrade = $fs->get_upgrade_url();
				if ( is_string( $fs_upgrade ) && '' !== $fs_upgrade ) {
					return $fs_upgrade;
				}
			}
		}

		return self::generate_cp_web_link( $placement, 'pricing' );
	}

	/**
	 * timerstart.
	 *
	 * @author  Unknown
	 * @since   v0.0.1
	 * @version v1.0.0  Wednesday, February 23rd, 2022.
	 * @access  public static
	 * @param   mixed   $watchname
	 * @return  void
	 */
	public static function timerstart( $watchname ) {
		set_transient( 'sb2_' . $watchname, microtime( true ), 60 * 60 * 1 );
	}

	/**
	 * timerstop.
	 *
	 * @author  Unknown
	 * @since   v0.0.1
	 * @version v1.0.0  Wednesday, February 23rd, 2022.
	 * @access  public static
	 * @param   mixed   $watchname
	 * @param   integer $digits     Default: 5
	 * @return  mixed
	 */
	public static function timerstop( $watchname, $digits = 5 ) {
		$return = round( microtime( true ) - get_transient( 'sb2_' . $watchname ), $digits );
		delete_transient( 'sb2_' . $watchname );
		return $return;
	}

	/**
	 * show_plugin_headline.
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Monday, August 26th, 2024.
	 * @access  public static
	 * @param   string  $title  Default: ''
	 * @param   boolean $return Default: false
	 * @return  void
	 */
	public static function show_plugin_headline( $title = '', $return = false ) {
		$content           = '<div class="big welcome"><span><img src="' . esc_url( SEOBOOSTER_PLUGINURL . 'images/sblogo25.png' ) . '" height="40" class="SEO Booster logo" alt="SEO Booster"></span>SEO Booster <span class="version">v. ' . esc_html( self::get_plugin_version() ) . '</span><span class="title">' . esc_html( $title ) . '</span>';
		$documentation_url = self::generate_cp_web_link( 'headline_docs', 'docs' );
		$roadmap_url       = 'https://seobooster.productlift.dev/';
		$support_url       = self::generate_cp_web_link( 'headline_support', 'support' );

		$content .= '<span class="navcont">';
		$content .= '<span class="documentation"><a href="' . esc_url( $documentation_url ) . '" target="_blank" class="documentation extlink">' . esc_html__( 'Documentation', 'seo-booster' ) . '</a></span>';

		$content .= '<span class="roadmap"><a href="' . esc_url( $roadmap_url ) . '" target="_blank" class="roadmap extlink">' . esc_html__( 'Roadmap', 'seo-booster' ) . '</a></span>';

		$content .= '<span class="support"><a href="' . esc_url( $support_url ) . '" target="_blank" class="support extlink">' . esc_html__( 'Support', 'seo-booster' ) . '</a></span>';

		$content .= '</div>';

		if ( $return ) {
			return $content;
		}

		echo wp_kses(
			$content,
			array(
				'div'  => array(
					'class' => array(),
				),
				'span' => array(
					'class' => array(),
				),
				'img'  => array(
					'src'    => array(),
					'height' => array(),
					'class'  => array(),
					'alt'    => array(),
				),
				'a'    => array(
					'href'                     => array(),
					'class'                    => array(),
					'id'                       => array(),
					'target'                   => array(),
					'rel'                      => array(),
					'data-productlift-widget'  => array(),
					'data-productlift-sidebar' => array(),
				),
			)
		);
	}

	/**
	 * Process weekly email signup AJAX request
	 *
	 * @since v0.0.1
	 * @version v1.0.0 Tuesday, September 10th, 2024.
	 * @access public static
	 * @return void
	 */
	public static function process_weekly_email_signup() {
		// Verify nonce for security
		if ( ! check_ajax_referer( 'seobooster_save_selected_site', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed', 'seo-booster' ) );
			return;
		}
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'seo-booster' ) );
			return;
		}
		// Validate email input
		$email_recipient = ( isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '' );
		if ( empty( $email_recipient ) ) {
			wp_send_json_error( __( 'No email address provided', 'seo-booster' ) );
			return;
		}
		// Process and validate email addresses
		$email_addresses = array_map( 'trim', explode( ',', $email_recipient ) );
		$valid_emails    = array_filter( $email_addresses, 'is_email' );
		// Handle case of single email address
		if ( count( $email_addresses ) === 1 && is_email( $email_addresses[0] ) ) {
			$valid_emails = $email_addresses;
		}
		if ( empty( $valid_emails ) ) {
			wp_send_json_error( __( 'No valid email addresses provided', 'seo-booster' ) );
			return;
		}
		// Prepare validated email string
		$email_recipient = implode( ',', $valid_emails );
		// Update options
		update_option( 'seobooster_weekly_email', 'on', true );
		update_option( 'seobooster_weekly_email_recipient', $email_recipient, true );
		// Send success response
		wp_send_json_success(
			sprintf(
			/* translators: %s: Email recipient(s) */
				__( 'Weekly email signup processed successfully for: %s', 'seo-booster' ),
				$email_recipient
			)
		);
	}


	/**
	 * Get top performing keywords for a given time period
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Tuesday, August 27th, 2024.
	 * @access  public static
	 * @param   integer $days  Number of days to look back
	 * @param   integer $limit Number of keywords to return
	 * @return  array   Array of top keywords with their stats
	 */
	public static function get_top_keywords( $days = 30, $limit = 10 ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT q.query as keyword, 
												SUM(h.clicks) as clicks, 
												SUM(h.impressions) as views
										 FROM {$wpdb->prefix}sb2_query_keywords q
										 JOIN {$wpdb->prefix}sb2_query_keywords_history h ON q.id = h.query_keywords_id
										 WHERE h.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
										 GROUP BY q.query
										 ORDER BY views DESC
										 LIMIT %d",
			$days,
			$limit
		);

		return $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built with prefixed tables / allowlisted ORDER BY; values prepared.
	}

	/**
	 * Fetch plugin version from plugin PHP header
	 *
	 * @author  Lars Koudal
	 * @author  Unknown
	 * @since   v0.0.1
	 * @version v1.0.0  Wednesday, January 13th, 2021.
	 * @version v1.0.1  Thursday, June 20th, 2024.
	 * @access  public static
	 * @return  mixed
	 */
	public static function get_plugin_version() {
		if ( null !== self::$version ) {
			return self::$version;
		}
		$plugin_data   = get_file_data(
			SEOBOOSTER_PLUGINPATH . 'seo-booster.php',
			array(
				'version' => 'Version',
			),
			'plugin'
		);
		self::$version = $plugin_data['version'];
		return $plugin_data['version'];
	}

	/**
	 * Count failed Action Scheduler jobs for SEO Booster hooks.
	 *
	 * @return int Zero when Action Scheduler is unavailable or no failures exist.
	 */
	public static function get_failed_background_job_count() {
		if ( ! class_exists( 'ActionScheduler_Store' ) ) {
			return 0;
		}

		global $wpdb;

		$group_slug = self::get_action_scheduler_group_slug();

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->prefix}actionscheduler_actions a
				INNER JOIN {$wpdb->prefix}actionscheduler_groups g ON a.group_id = g.group_id
				WHERE a.status = 'failed'
				AND g.slug = %s",
				$group_slug
			)
		);

		return (int) $count;
	}

	/**
	 * Check if current admin page is an SEO Booster page
	 *
	 * @since 6.1.26
	 * @return string|false The screen ID if it's an SEO Booster page, false otherwise
	 */
	public static function is_sb2_admin_page() {
		$screen = get_current_screen();

		// First verify that $screen is an object and has an 'id' property
		if ( ! is_object( $screen ) || ! isset( $screen->id ) ) {
			return false;
		}

		// Now it's safe to check the screen ID
		$admin_pages = array(
			'toplevel_page_sb2_dashboard',
			'seo-booster_page_sb2_debug',
			'seo-booster_page_sb2_log',
			'seo-booster_page_sb2_settings',
			'seo-booster_page_sb2_seo_settings',
			'seo-booster_page_sb2_gsc',
			'seo-booster_page_sb2_seo_issues',
			'seo-booster_page_sb2_404',
			'seo-booster_page_sb2_ai_bots',
			'seo-booster_page_sb2_autolink',
			'seo-booster_page_sb2_tools',
			'sb2_dashboard',
			'admin_page_seo-booster-oauth2',
			'admin_page_sb2_setup',
		);

		if ( in_array( $screen->id, $admin_pages ) ) {
			return $screen->id;
		}

		return false;
	}

	/**
	 * Check if URL is local
	 *
	 * @author  Unknown
	 * @since   v0.0.1
	 * @version v1.0.0  Sunday, November 7th, 2021.
	 * @access  public static
	 * @param   string $url Default: ''
	 * @return  mixed
	 */
	public static function is_local_url( $url = '' ) {
		$is_local_url = false;
		$url          = strtolower( trim( $url ) );
		if ( false === strpos( $url, 'http://' ) && false === strpos( $url, 'https://' ) ) {
			$url = 'http://' . $url;
		}
		$url_parts = wp_parse_url( $url );
		$host      = ( ! empty( $url_parts['host'] ) ? $url_parts['host'] : false );
		if ( ! empty( $url ) && ! empty( $host ) ) {
			if ( false !== ip2long( $host ) ) {
				if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					$is_local_url = true;
				}
			} elseif ( 'localhost' === $host ) {
				$is_local_url = true;
			}
			$tlds_to_check = array( '.dev', '.local', '.loc' );
			foreach ( $tlds_to_check as $tld ) {
				if ( false !== strpos( $host, $tld ) ) {
					$is_local_url = true;
					continue;
				}
			}
			if ( substr_count( $host, '.' ) > 1 ) {
				$subdomains_to_check = array( 'dev.', 'staging.' );
				foreach ( $subdomains_to_check as $subdomain ) {
					if ( 0 === strpos( $host, $subdomain ) ) {
						$is_local_url = true;
						continue;
					}
				}
			}
		}
		return $is_local_url;
	}

	/**
	 * Whether the current user can edit a post, attachment, or term.
	 *
	 * @param int    $object_id   Object ID.
	 * @param string $object_type post|attachment|term|taxonomy.
	 * @return bool
	 */
	public static function user_can_edit_object( $object_id, $object_type = 'post' ) {
		$object_id = absint( $object_id );
		if ( ! $object_id ) {
			return false;
		}
		if ( 'term' === $object_type || 'taxonomy' === $object_type ) {
			return current_user_can( 'edit_term', $object_id );
		}
		return current_user_can( 'edit_post', $object_id );
	}

	/**
	 * Whether a GSC property URL is in the connected site list.
	 *
	 * @param string $site_url Site URL to validate.
	 * @return bool
	 */
	public static function is_allowed_gsc_site( $site_url ) {
		$site_url = (string) $site_url;
		if ( '' === $site_url ) {
			return false;
		}
		$sites   = get_option( 'seobooster_gsc_sites', array() );
		$allowed = array();
		if ( is_array( $sites ) ) {
			foreach ( $sites as $site ) {
				if ( is_string( $site ) ) {
					$allowed[] = $site;
				} elseif ( is_array( $site ) && ! empty( $site['siteUrl'] ) ) {
					$allowed[] = $site['siteUrl'];
				} elseif ( is_object( $site ) && ! empty( $site->{'siteUrl'} ) ) {
					$allowed[] = $site->{'siteUrl'};
				}
			}
		}
		return in_array( $site_url, $allowed, true );
	}

	/**
	 * Whether a URL is safe for outbound HTTP from the plugin (SSRF guard).
	 *
	 * @param string $url  URL to check.
	 * @param array  $args {
	 *     @type bool $allow_same_host Allow URLs whose host matches home_url().
	 * }
	 * @return bool
	 */
	public static function is_safe_outbound_url( $url, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'allow_same_host' => false,
			)
		);

		if ( ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return false;
		}

		// Same-site fetches (analysis, page probe) must work on local/dev hosts.
		$site_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( ! empty( $args['allow_same_host'] ) && $site_host && $host === $site_host ) {
			return true;
		}

		$blocked_hosts = array( 'localhost', '127.0.0.1', '::1', '0.0.0.0', '169.254.169.254' );
		if ( in_array( $host, $blocked_hosts, true ) ) {
			return false;
		}

		if ( preg_match( '/\.(local|localhost|test|invalid)$/', $host ) ) {
			return false;
		}

		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Register and enqueue the shared SBModal alert/confirm assets.
	 *
	 * @return void
	 */
	public static function enqueue_modal_assets() {
		if ( ! wp_script_is( 'sb-modal', 'registered' ) ) {
			wp_register_style(
				'sb-modal',
				SEOBOOSTER_PLUGINURL . 'css/sb-modal.css',
				array(),
				filemtime( SEOBOOSTER_PLUGINPATH . 'css/sb-modal.css' )
			);
			wp_register_script(
				'sb-modal',
				SEOBOOSTER_PLUGINURL . 'js/sb-modal.js',
				array( 'jquery' ),
				filemtime( SEOBOOSTER_PLUGINPATH . 'js/sb-modal.js' ),
				true
			);
			wp_localize_script(
				'sb-modal',
				'sbModalStrings',
				array(
					'strings' => array(
						'ok'            => __( 'OK', 'seo-booster' ),
						'cancel'        => __( 'Cancel', 'seo-booster' ),
						'confirm'       => __( 'Confirm', 'seo-booster' ),
						'delete'        => __( 'Delete', 'seo-booster' ),
						'title_error'   => __( 'Error', 'seo-booster' ),
						'title_warning' => __( 'Warning', 'seo-booster' ),
						'title_success' => __( 'Success', 'seo-booster' ),
						'title_info'    => __( 'Notice', 'seo-booster' ),
					),
				)
			);
		}

		wp_enqueue_style( 'sb-modal' );
		wp_enqueue_script( 'sb-modal' );
	}
}
