<?php

namespace Cleverplugins\SEOBooster;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Tracks visits from known AI/LLM crawlers and answer-engine fetchers.
 */
class AI_Bot_Tracker {
    const OPTION_TRACKING = 'seobooster_ai_bot_tracking';

    const OPTION_BLOCKING = 'seobooster_ai_bot_blocking';

    const OPTION_BLOCKED_BOTS = 'seobooster_ai_bot_blocked_bots';

    const OPTION_BLOCKED_PURPOSES = 'seobooster_ai_bot_blocked_purposes';

    const OPTION_TRACK_MAPPED_ONLY = 'seobooster_ai_bot_track_mapped_only';

    const OPTION_RETENTION_DAYS = 'seobooster_ai_bot_retention_days';

    const BUCKET_PATH_SEARCH = '/__sb_ai_bot_search_trap/';

    const BUCKET_PATH_JUNK = '/__sb_ai_bot_junk_trap/';

    const BUCKET_PATH_UNMAPPED = '/__sb_ai_bot_unmapped/';

    /** @var array|null Cached crawler definitions from ai-crawlers.json. */
    private static $crawlers = null;

    /** @var array|null Pending hit queued during template_redirect. */
    private static $pending_hit = null;

    /** @var bool Whether shutdown finalize hook is registered. */
    private static $shutdown_registered = false;

    /** @var bool Whether WordPress issued a redirect during this bot request. */
    private static $request_redirected = false;

    /** @var int HTTP status code from wp_redirect, when set. */
    private static $redirect_status = 0;

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function init() {
        if ( is_admin() ) {
            return;
        }
        add_action( 'template_redirect', array(__CLASS__, 'handle_request'), 5 );
        add_filter(
            'wp_redirect',
            array(__CLASS__, 'mark_request_redirected'),
            1,
            2
        );
    }

    /**
     * Whether AI bot tracking is enabled.
     *
     * @return bool
     */
    public static function is_tracking_enabled() {
        return get_option( self::OPTION_TRACKING, 'on' ) !== 'off';
    }

    /**
     * Whether only mapped WordPress content should be recorded.
     *
     * @return bool
     */
    public static function is_track_mapped_only() {
        return get_option( self::OPTION_TRACK_MAPPED_ONLY, 'on' ) === 'on';
    }

    /**
     * Retention window in days for hit rows.
     *
     * @return int
     */
    public static function get_retention_days() {
        $days = (int) get_option( self::OPTION_RETENTION_DAYS, 90 );
        if ( $days < 7 ) {
            return 7;
        }
        if ( $days > 365 ) {
            return 365;
        }
        return $days;
    }

    /**
     * Handle a frontend request: optionally block (premium) and queue hit recording.
     *
     * @return void
     */
    public static function handle_request() {
        if ( !self::is_tracking_enabled() ) {
            return;
        }
        if ( self::should_skip_request() ) {
            return;
        }
        $match = self::match_user_agent();
        if ( empty( $match ) ) {
            return;
        }
        $bot_name = $match['bot_name'];
        $bot_purpose = $match['bot_purpose'];
        self::queue_pending_hit( $bot_name, $bot_purpose );
        if ( !self::$shutdown_registered ) {
            add_action( 'shutdown', array(__CLASS__, 'finalize_pending_hit'), 999 );
            self::$shutdown_registered = true;
        }
    }

    /**
     * Finalize and record a queued hit after the response status is known.
     *
     * @return void
     */
    public static function finalize_pending_hit() {
        if ( empty( self::$pending_hit ) || !did_action( 'wp' ) ) {
            return;
        }
        $pending = self::$pending_hit;
        self::$pending_hit = null;
        $request_redirected = self::$request_redirected;
        $redirect_status = self::$redirect_status;
        self::$request_redirected = false;
        self::$redirect_status = 0;
        $request_path = self::get_request_path();
        if ( empty( $request_path ) ) {
            return;
        }
        $normalized = self::get_normalized_url_data();
        $canonical = self::canonicalize_request( ( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : $request_path ) );
        $mapped_kind = self::resolve_request_kind( $canonical, (int) $normalized['object_id'] );
        $status_code = (int) http_response_code();
        if ( $request_redirected && $redirect_status >= 300 ) {
            $status_code = $redirect_status;
        }
        if ( $status_code < 100 ) {
            $status_code = 200;
        }
        if ( $status_code === 404 || function_exists( 'is_404' ) && is_404() ) {
            return;
        }
        $is_redirect = $request_redirected || $status_code >= 300 && $status_code < 400;
        if ( $mapped_kind === 'content' && $is_redirect ) {
            $request_kind = 'redirect';
        } else {
            $request_kind = $mapped_kind;
        }
        if ( self::is_track_mapped_only() && self::is_noise_kind( $request_kind ) ) {
            return;
        }
        $url_for_hash = $normalized['url'];
        $display_path = $request_path;
        if ( $request_kind === 'content' || $request_kind === 'redirect' ) {
            $url_for_hash = $normalized['url'];
            $display_path = $request_path;
        } else {
            $url_for_hash = self::get_bucket_url( $request_kind );
            $display_path = $canonical['canonical_path'];
        }
        $url_for_hash = untrailingslashit( strtolower( esc_url_raw( $url_for_hash ) ) );
        $url_hash = hash( 'sha256', $url_for_hash );
        $normalized_url = substr( $url_for_hash, 0, 500 );
        self::write_hit(
            $pending['bot_name'],
            $pending['bot_purpose'],
            $display_path,
            $normalized_url,
            $url_hash,
            (int) $normalized['object_id'],
            $normalized['object_type'],
            $request_kind,
            $status_code
        );
    }

    /**
     * Flag bot requests that WordPress redirects before the response completes.
     *
     * @param string $location Redirect target URL.
     * @param int    $status   Redirect HTTP status code.
     * @return string
     */
    public static function mark_request_redirected( $location, $status ) {
        if ( !empty( self::$pending_hit ) ) {
            self::$request_redirected = true;
            self::$redirect_status = (int) $status;
        }
        return $location;
    }

    /**
     * Queue bot metadata for shutdown recording.
     *
     * @param string $bot_name    Bot identifier.
     * @param string $bot_purpose Purpose key.
     * @return void
     */
    private static function queue_pending_hit( $bot_name, $bot_purpose ) {
        self::$pending_hit = array(
            'bot_name'    => $bot_name,
            'bot_purpose' => $bot_purpose,
        );
    }

    /**
     * Whether the current request should be skipped for tracking.
     *
     * @return bool
     */
    private static function should_skip_request() {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return true;
        }
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
            return true;
        }
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return true;
        }
        if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
            return true;
        }
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
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
        if ( function_exists( 'is_embed' ) && is_embed() ) {
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
            $skip_extensions = array(
                '.ico',
                '.png',
                '.jpg',
                '.jpeg',
                '.gif',
                '.svg',
                '.css',
                '.js',
                '.webp',
                '.scss',
                '.woff',
                '.woff2',
                '.ttf',
                '.map'
            );
            foreach ( $skip_extensions as $ext ) {
                if ( false !== strpos( $request_uri, $ext ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Whether the current request user agent matches a known AI crawler.
     *
     * @return bool
     */
    public static function is_ai_bot_user_agent() {
        return !empty( self::match_user_agent() );
    }

    /**
     * Whether the current frontend request should be skipped for hit/referral tracking.
     *
     * @return bool
     */
    public static function should_skip_tracking_request() {
        return self::should_skip_request();
    }

    /**
     * Resolve normalized URL and object metadata for the current request.
     *
     * @return array{url: string, url_hash: string, object_id: int, object_type: string}
     */
    public static function get_current_request_object() {
        if ( !did_action( 'wp' ) ) {
            return array(
                'url'         => '',
                'url_hash'    => '',
                'object_id'   => 0,
                'object_type' => '',
            );
        }
        return self::get_normalized_url_data();
    }

    /**
     * Match the current user agent against known AI crawlers.
     *
     * @return array{bot_name: string, bot_purpose: string}|array
     */
    private static function match_user_agent() {
        if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return array();
        }
        $user_agent = wp_unslash( $_SERVER['HTTP_USER_AGENT'] );
        $crawlers = self::get_crawlers();
        foreach ( $crawlers as $bot_name => $meta ) {
            if ( stripos( $user_agent, $bot_name ) !== false ) {
                return array(
                    'bot_name'    => $bot_name,
                    'bot_purpose' => self::purpose_from_type( ( isset( $meta['type'] ) ? $meta['type'] : '' ) ),
                );
            }
        }
        return array();
    }

    /**
     * Map crawler JSON type to a purpose label.
     *
     * @param string $type Crawler type from ai-crawlers.json.
     * @return string
     */
    public static function purpose_from_type( $type ) {
        if ( in_array( $type, array('fetcher', 'indexer'), true ) ) {
            return 'citation';
        }
        return 'research';
    }

    /**
     * Human-readable purpose label.
     *
     * @param string $purpose Internal purpose key.
     * @return string
     */
    public static function get_purpose_label( $purpose ) {
        if ( $purpose === 'citation' ) {
            return __( 'Citation / answer engine', 'seo-booster' );
        }
        return __( 'Research / training', 'seo-booster' );
    }

    /**
     * Human-readable object type label.
     *
     * @param string $object_type Internal object type key.
     * @return string
     */
    public static function get_object_type_label( $object_type ) {
        switch ( $object_type ) {
            case 'post':
                return __( 'Post', 'seo-booster' );
            case 'term':
                return __( 'Term', 'seo-booster' );
            case 'archive':
                return __( 'Archive', 'seo-booster' );
            case 'home':
                return __( 'Home', 'seo-booster' );
            default:
                return __( 'Unknown', 'seo-booster' );
        }
    }

    /**
     * Load crawler definitions from JSON.
     *
     * @return array
     */
    public static function get_crawlers() {
        if ( null !== self::$crawlers ) {
            return self::$crawlers;
        }
        $path = SEOBOOSTER_PLUGINPATH . 'inc/ai-crawlers.json';
        if ( !file_exists( $path ) ) {
            self::$crawlers = array();
            return self::$crawlers;
        }
        $json = file_get_contents( $path );
        $data = json_decode( $json, true );
        self::$crawlers = ( is_array( $data ) ? $data : array() );
        return self::$crawlers;
    }

    /**
     * Canonicalize a request URI for hashing and classification.
     *
     * @param string $request_uri Raw request URI.
     * @return array{canonical_path: string, canonical_url: string, request_kind: string}
     */
    public static function canonicalize_request( $request_uri ) {
        $request_uri = (string) $request_uri;
        $path = strtok( $request_uri, '?' );
        $path = '/' . ltrim( (string) $path, '/' );
        $path = substr( sanitize_text_field( $path ), 0, 500 );
        $lower_path = strtolower( $path );
        $lower_uri = strtolower( $request_uri );
        $request_kind = 'unmapped';
        if ( self::path_looks_like_search_trap( $lower_path, $lower_uri ) ) {
            $request_kind = 'search';
        } elseif ( self::path_looks_like_junk( $lower_path ) ) {
            $request_kind = 'junk';
        }
        $canonical_url = untrailingslashit( strtolower( esc_url_raw( home_url( $path ) ) ) );
        return array(
            'canonical_path' => $path,
            'canonical_url'  => substr( $canonical_url, 0, 500 ),
            'request_kind'   => $request_kind,
        );
    }

    /**
     * Resolve final request kind using object mapping.
     *
     * @param array $canonical   Canonical request data.
     * @param int   $object_id   Mapped object ID.
     * @return string
     */
    public static function resolve_request_kind( $canonical, $object_id ) {
        if ( $object_id > 0 ) {
            return 'content';
        }
        if ( isset( $canonical['request_kind'] ) && in_array( $canonical['request_kind'], array('search', 'junk'), true ) ) {
            return $canonical['request_kind'];
        }
        return 'unmapped';
    }

    /**
     * Whether a request kind is considered noise.
     *
     * @param string $request_kind Request kind key.
     * @return bool
     */
    public static function is_noise_kind( $request_kind ) {
        return in_array( $request_kind, array('search', 'junk', 'unmapped'), true );
    }

    /**
     * Bucket URL used for hashing trap/unmapped traffic.
     *
     * @param string $request_kind Request kind key.
     * @return string
     */
    public static function get_bucket_url( $request_kind ) {
        switch ( $request_kind ) {
            case 'search':
                $path = self::BUCKET_PATH_SEARCH;
                break;
            case 'junk':
                $path = self::BUCKET_PATH_JUNK;
                break;
            default:
                $path = self::BUCKET_PATH_UNMAPPED;
                break;
        }
        return untrailingslashit( strtolower( esc_url_raw( home_url( $path ) ) ) );
    }

    /**
     * Detect search trap style paths.
     *
     * @param string $lower_path Lowercase path.
     * @param string $lower_uri  Lowercase full URI.
     * @return bool
     */
    private static function path_looks_like_search_trap( $lower_path, $lower_uri ) {
        if ( function_exists( 'is_search' ) && is_search() ) {
            return true;
        }
        if ( false !== strpos( $lower_path, '/search' ) ) {
            return true;
        }
        if ( false !== strpos( $lower_uri, '?s=' ) || false !== strpos( $lower_uri, '&s=' ) ) {
            return true;
        }
        return false;
    }

    /**
     * Detect junk / spider trap paths.
     *
     * @param string $lower_path Lowercase path.
     * @return bool
     */
    private static function path_looks_like_junk( $lower_path ) {
        if ( strlen( $lower_path ) > 120 ) {
            return true;
        }
        if ( preg_match( '/(?:page\\d+){4,}/', $lower_path ) ) {
            return true;
        }
        return false;
    }

    /**
     * Write an aggregated hit row.
     *
     * @param string $bot_name       Bot identifier.
     * @param string $bot_purpose    Purpose classification.
     * @param string $request_path   Display path.
     * @param string $normalized_url Normalized URL.
     * @param string $url_hash       URL hash.
     * @param int    $object_id      Object ID.
     * @param string $object_type    Object type.
     * @param string $request_kind   Request kind.
     * @param int    $status_code    HTTP status.
     * @return void
     */
    private static function write_hit(
        $bot_name,
        $bot_purpose,
        $request_path,
        $normalized_url,
        $url_hash,
        $object_id,
        $object_type,
        $request_kind,
        $status_code
    ) {
        global $wpdb;
        $hit_date = current_time( 'Y-m-d' );
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $has_kind = self::table_has_request_kind_column();
        if ( $has_kind ) {
            $wpdb->query( $wpdb->prepare(
                "INSERT INTO {$table}\n\t\t\t\t\t(bot_name, bot_purpose, request_path, normalized_url, url_hash, object_id, object_type, status_code, request_kind, hit_date, first_seen, last_seen, visits)\n\t\t\t\t\tVALUES (%s, %s, %s, %s, %s, %d, %s, %d, %s, %s, NOW(), NOW(), 1)\n\t\t\t\t\tON DUPLICATE KEY UPDATE\n\t\t\t\t\tvisits = visits + 1,\n\t\t\t\t\tlast_seen = NOW(),\n\t\t\t\t\tstatus_code = %d,\n\t\t\t\t\trequest_kind = %s,\n\t\t\t\t\tnormalized_url = IF(normalized_url = '' OR normalized_url IS NULL, %s, normalized_url),\n\t\t\t\t\tobject_id = IF(object_id IS NULL OR object_id = 0, %d, object_id),\n\t\t\t\t\tobject_type = IF(object_type IS NULL OR object_type = '', %s, object_type)",
                $bot_name,
                $bot_purpose,
                $request_path,
                $normalized_url,
                $url_hash,
                $object_id,
                $object_type,
                $status_code,
                $request_kind,
                $hit_date,
                $status_code,
                $request_kind,
                $normalized_url,
                $object_id,
                $object_type
            ) );
            return;
        }
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$table}\n\t\t\t\t(bot_name, bot_purpose, request_path, normalized_url, url_hash, object_id, object_type, status_code, hit_date, first_seen, last_seen, visits)\n\t\t\t\tVALUES (%s, %s, %s, %s, %s, %d, %s, %d, %s, NOW(), NOW(), 1)\n\t\t\t\tON DUPLICATE KEY UPDATE\n\t\t\t\tvisits = visits + 1,\n\t\t\t\tlast_seen = NOW(),\n\t\t\t\tstatus_code = %d,\n\t\t\t\tnormalized_url = IF(normalized_url = '' OR normalized_url IS NULL, %s, normalized_url),\n\t\t\t\tobject_id = IF(object_id IS NULL OR object_id = 0, %d, object_id),\n\t\t\t\tobject_type = IF(object_type IS NULL OR object_type = '', %s, object_type)",
            $bot_name,
            $bot_purpose,
            $request_path,
            $normalized_url,
            $url_hash,
            $object_id,
            $object_type,
            $status_code,
            $hit_date,
            $status_code,
            $normalized_url,
            $object_id,
            $object_type
        ) );
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
     * Resolve normalized URL and object metadata for the current request.
     *
     * @return array{url: string, url_hash: string, object_id: int, object_type: string}
     */
    private static function get_normalized_url_data() {
        $object_id = 0;
        $object_type = '';
        $url = '';
        $queried = get_queried_object();
        if ( $queried instanceof \WP_Post ) {
            $object_id = (int) $queried->ID;
            $object_type = 'post';
            $url = get_permalink( $queried );
        } elseif ( $queried instanceof \WP_Term ) {
            $object_id = (int) $queried->term_id;
            $object_type = 'term';
            $url = get_term_link( $queried );
        } elseif ( function_exists( 'is_post_type_archive' ) && is_post_type_archive() ) {
            $post_type = get_query_var( 'post_type' );
            if ( is_array( $post_type ) ) {
                $post_type = reset( $post_type );
            }
            if ( $post_type ) {
                $object_type = 'archive';
                $url = get_post_type_archive_link( $post_type );
            }
        } elseif ( is_home() && !is_front_page() ) {
            $object_type = 'archive';
            $url = get_permalink( get_option( 'page_for_posts' ) );
        } elseif ( is_front_page() ) {
            $object_type = 'home';
            $url = home_url( '/' );
        }
        if ( empty( $url ) || is_wp_error( $url ) ) {
            $url = home_url( self::get_request_path() );
        }
        $url = untrailingslashit( strtolower( esc_url_raw( $url ) ) );
        return array(
            'url'         => substr( $url, 0, 500 ),
            'url_hash'    => hash( 'sha256', $url ),
            'object_id'   => $object_id,
            'object_type' => $object_type,
        );
    }

    /**
     * Whether premium blocking is enabled and this bot should be denied.
     *
     * @param string $bot_name    Bot identifier.
     * @param string $bot_purpose Purpose classification.
     * @return bool
     */
    private static function should_block_bot( $bot_name, $bot_purpose ) {
        if ( get_option( self::OPTION_BLOCKING, '' ) !== 'on' ) {
            return false;
        }
        $blocked_purposes = get_option( self::OPTION_BLOCKED_PURPOSES, array() );
        if ( is_array( $blocked_purposes ) && in_array( $bot_purpose, $blocked_purposes, true ) ) {
            return true;
        }
        $blocked_bots = get_option( self::OPTION_BLOCKED_BOTS, array() );
        if ( is_array( $blocked_bots ) && in_array( $bot_name, $blocked_bots, true ) ) {
            return true;
        }
        return false;
    }

    /**
     * SQL date threshold for reporting windows.
     *
     * @param int $days Number of days.
     * @return string
     */
    public static function get_since_date( $days ) {
        return gmdate( 'Y-m-d', strtotime( '-' . absint( $days ) . ' days' ) );
    }

    /**
     * Truncate a string for table display.
     *
     * @param string $text   Text to truncate.
     * @param int    $length Max length.
     * @return string
     */
    public static function truncate_display( $text, $length = 60 ) {
        $text = (string) $text;
        if ( strlen( $text ) <= $length ) {
            return $text;
        }
        $half = (int) floor( ($length - 3) / 2 );
        return substr( $text, 0, $half ) . '...' . substr( $text, -$half );
    }

    /**
     * Resolve display metadata for a tracked object.
     *
     * @param int    $object_id   Object ID.
     * @param string $object_type Object type.
     * @return array{title: string, view_url: string, edit_url: string}
     */
    public static function resolve_object_label( $object_id, $object_type ) {
        $title = '';
        $view_url = '';
        $edit_url = '';
        if ( $object_id <= 0 ) {
            return array(
                'title'    => '',
                'view_url' => '',
                'edit_url' => '',
            );
        }
        if ( $object_type === 'post' ) {
            $title = get_the_title( $object_id );
            $view_url = get_permalink( $object_id );
            $edit_url = get_edit_post_link( $object_id, 'raw' );
        } elseif ( $object_type === 'term' ) {
            $term = get_term( $object_id );
            if ( $term && !is_wp_error( $term ) ) {
                $title = $term->name;
                $term_link = get_term_link( $term );
                $view_url = ( is_wp_error( $term_link ) ? '' : $term_link );
                $edit_url = get_edit_term_link( $object_id, $term->taxonomy, 'raw' );
            }
        } elseif ( $object_type === 'home' ) {
            $title = __( 'Homepage', 'seo-booster' );
            $view_url = home_url( '/' );
        } elseif ( $object_type === 'archive' ) {
            $title = __( 'Archive', 'seo-booster' );
            if ( is_numeric( $object_id ) && $object_id > 0 ) {
                $view_url = get_permalink( $object_id );
            }
        }
        if ( empty( $title ) ) {
            $title = sprintf( __( 'Object #%d', 'seo-booster' ), $object_id );
        }
        return array(
            'title'    => $title,
            'view_url' => ( is_string( $view_url ) ? $view_url : '' ),
            'edit_url' => ( is_string( $edit_url ) ? $edit_url : '' ),
        );
    }

    /**
     * Summary stats for dashboard and reports.
     *
     * @param int $days Number of days to include.
     * @return array
     */
    public static function get_summary( $days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $since = self::get_since_date( $days );
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT\n\t\t\t\t\tCOALESCE(SUM(visits), 0) AS total_visits,\n\t\t\t\t\tCOUNT(DISTINCT bot_name) AS unique_bots,\n\t\t\t\t\tCOUNT(DISTINCT url_hash) AS unique_urls,\n\t\t\t\t\tCOUNT(DISTINCT CASE WHEN object_id > 0 THEN CONCAT(object_type, ':', object_id) ELSE NULL END) AS unique_content_pages,\n\t\t\t\t\tCOUNT(DISTINCT CASE WHEN object_id IS NULL OR object_id = 0 THEN url_hash ELSE NULL END) AS unique_noise_urls\n\t\t\t\tFROM {$table}\n\t\t\t\tWHERE hit_date >= %s", $since ), ARRAY_A );
        if ( !is_array( $row ) ) {
            $row = array(
                'total_visits'         => 0,
                'unique_bots'          => 0,
                'unique_urls'          => 0,
                'unique_content_pages' => 0,
                'unique_noise_urls'    => 0,
            );
        }
        $row['purpose_breakdown'] = self::get_purpose_breakdown( $days );
        return $row;
    }

    /**
     * Count distinct mapped content objects crawled.
     *
     * @param int $days Days to include.
     * @return int
     */
    public static function get_unique_content_count( $days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $since = self::get_since_date( $days );
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT CONCAT(object_type, ':', object_id))\n\t\t\t\tFROM {$table}\n\t\t\t\tWHERE hit_date >= %s AND object_id > 0", $since ) );
    }

    /**
     * Content vs noise visit ratio.
     *
     * @param int $days Days to include.
     * @return array{content_visits: int, noise_visits: int, content_percent: float}
     */
    public static function get_content_vs_noise_ratio( $days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $since = self::get_since_date( $days );
        $has_kind = self::table_has_request_kind_column();
        if ( $has_kind ) {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT\n\t\t\t\t\t\tCOALESCE(SUM(CASE WHEN request_kind = 'content' THEN visits ELSE 0 END), 0) AS content_visits,\n\t\t\t\t\t\tCOALESCE(SUM(CASE WHEN request_kind IN ('search', 'junk', 'unmapped', 'redirect') OR (object_id IS NULL OR object_id = 0) THEN visits ELSE 0 END), 0) AS noise_visits\n\t\t\t\t\tFROM {$table}\n\t\t\t\t\tWHERE hit_date >= %s", $since ), ARRAY_A );
        } else {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT\n\t\t\t\t\t\tCOALESCE(SUM(CASE WHEN object_id > 0 THEN visits ELSE 0 END), 0) AS content_visits,\n\t\t\t\t\t\tCOALESCE(SUM(CASE WHEN object_id IS NULL OR object_id = 0 THEN visits ELSE 0 END), 0) AS noise_visits\n\t\t\t\t\tFROM {$table}\n\t\t\t\t\tWHERE hit_date >= %s", $since ), ARRAY_A );
        }
        $content_visits = ( isset( $row['content_visits'] ) ? (int) $row['content_visits'] : 0 );
        $noise_visits = ( isset( $row['noise_visits'] ) ? (int) $row['noise_visits'] : 0 );
        $total = $content_visits + $noise_visits;
        $percent = ( $total > 0 ? round( $content_visits / $total * 100, 1 ) : 0.0 );
        return array(
            'content_visits'  => $content_visits,
            'noise_visits'    => $noise_visits,
            'content_percent' => $percent,
        );
    }

    /**
     * Grouped content hits for list table.
     *
     * @param array $args Query arguments.
     * @return array{items: array, total: int}
     */
    public static function get_content_hits( $args = array() ) {
        global $wpdb;
        $defaults = array(
            'days'               => 30,
            'offset'             => 0,
            'per_page'           => 50,
            'orderby'            => 'visits',
            'order'              => 'DESC',
            'search'             => '',
            'filter_bot'         => '',
            'filter_purpose'     => '',
            'filter_object_type' => '',
            'min_visits'         => 1,
        );
        $args = wp_parse_args( $args, $defaults );
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $since = self::get_since_date( $args['days'] );
        $has_kind = self::table_has_request_kind_column();
        $where = array('hit_date >= %s', 'object_id > 0');
        $prepare = array($since);
        if ( $has_kind ) {
            $where[] = "request_kind IN ('content', 'redirect')";
        }
        if ( !empty( $args['filter_bot'] ) ) {
            $where[] = 'bot_name = %s';
            $prepare[] = $args['filter_bot'];
        }
        if ( in_array( $args['filter_purpose'], array('research', 'citation'), true ) ) {
            $where[] = 'bot_purpose = %s';
            $prepare[] = $args['filter_purpose'];
        }
        if ( !empty( $args['filter_object_type'] ) ) {
            $where[] = 'object_type = %s';
            $prepare[] = $args['filter_object_type'];
        }
        if ( !empty( $args['search'] ) ) {
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = '(normalized_url LIKE %s OR request_path LIKE %s OR bot_name LIKE %s)';
            $prepare[] = $like;
            $prepare[] = $like;
            $prepare[] = $like;
        }
        $where_sql = implode( ' AND ', $where );
        if ( $has_kind ) {
            $content_visits_expr = "SUM(CASE WHEN request_kind = 'content' THEN visits ELSE 0 END)";
            $redirect_visits_expr = "SUM(CASE WHEN request_kind = 'redirect' THEN visits ELSE 0 END)";
            $having_sql = "({$content_visits_expr} >= %d OR {$redirect_visits_expr} >= %d)";
            $count_sql = "SELECT COUNT(*) FROM (\n\t\t\t\tSELECT object_type, object_id\n\t\t\t\tFROM {$table}\n\t\t\t\tWHERE {$where_sql}\n\t\t\t\tGROUP BY object_type, object_id\n\t\t\t\tHAVING {$having_sql}\n\t\t\t) grouped";
            $count_prepare = array_merge( $prepare, array((int) $args['min_visits'], (int) $args['min_visits']) );
            $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $count_prepare ) );
            $allowed_orderby = array(
                'visits'      => $content_visits_expr,
                'last_seen'   => 'MAX(last_seen)',
                'object_type' => 'object_type',
            );
            $orderby_key = ( isset( $allowed_orderby[$args['orderby']] ) ? $args['orderby'] : 'visits' );
            $order_sql = $allowed_orderby[$orderby_key];
            $order = ( strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC' );
            $query_sql = "SELECT\n\t\t\t\tobject_type,\n\t\t\t\tobject_id,\n\t\t\t\t{$content_visits_expr} AS visits,\n\t\t\t\t{$redirect_visits_expr} AS redirect_visits,\n\t\t\t\tMAX(last_seen) AS last_seen,\n\t\t\t\tMAX(normalized_url) AS normalized_url,\n\t\t\t\tSUM(CASE WHEN request_kind = 'content' AND bot_purpose = 'research' THEN visits ELSE 0 END) AS research_visits,\n\t\t\t\tSUM(CASE WHEN request_kind = 'content' AND bot_purpose = 'citation' THEN visits ELSE 0 END) AS citation_visits,\n\t\t\t\tCAST(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN request_kind = 'content' THEN status_code END ORDER BY last_seen DESC SEPARATOR ','), ',', 1) AS UNSIGNED) AS status_code,\n\t\t\t\tGROUP_CONCAT(DISTINCT bot_name ORDER BY bot_name SEPARATOR ', ') AS bot_names\n\t\t\t\tFROM {$table}\n\t\t\t\tWHERE {$where_sql}\n\t\t\t\tGROUP BY object_type, object_id\n\t\t\t\tHAVING {$having_sql}\n\t\t\t\tORDER BY {$order_sql} {$order}\n\t\t\t\tLIMIT %d, %d";
            $query_prepare = array_merge( $prepare, array(
                (int) $args['min_visits'],
                (int) $args['min_visits'],
                (int) $args['offset'],
                (int) $args['per_page']
            ) );
            $items = $wpdb->get_results( $wpdb->prepare( $query_sql, $query_prepare ), ARRAY_A );
            return array(
                'items' => ( is_array( $items ) ? $items : array() ),
                'total' => $total,
            );
        }
        $count_sql = "SELECT COUNT(*) FROM (\n\t\t\tSELECT object_type, object_id\n\t\t\tFROM {$table}\n\t\t\tWHERE {$where_sql}\n\t\t\tGROUP BY object_type, object_id\n\t\t\tHAVING SUM(visits) >= %d\n\t\t) grouped";
        $count_prepare = array_merge( $prepare, array((int) $args['min_visits']) );
        $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $count_prepare ) );
        $allowed_orderby = array(
            'visits'      => 'SUM(visits)',
            'last_seen'   => 'MAX(last_seen)',
            'object_type' => 'object_type',
        );
        $orderby_key = ( isset( $allowed_orderby[$args['orderby']] ) ? $args['orderby'] : 'visits' );
        $order_sql = $allowed_orderby[$orderby_key];
        $order = ( strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC' );
        $query_sql = "SELECT\n\t\t\tobject_type,\n\t\t\tobject_id,\n\t\t\tSUM(visits) AS visits,\n\t\t\t0 AS redirect_visits,\n\t\t\tMAX(last_seen) AS last_seen,\n\t\t\tMAX(normalized_url) AS normalized_url,\n\t\t\tSUM(CASE WHEN bot_purpose = 'research' THEN visits ELSE 0 END) AS research_visits,\n\t\t\tSUM(CASE WHEN bot_purpose = 'citation' THEN visits ELSE 0 END) AS citation_visits,\n\t\t\tMAX(status_code) AS status_code,\n\t\t\tGROUP_CONCAT(DISTINCT bot_name ORDER BY bot_name SEPARATOR ', ') AS bot_names\n\t\t\tFROM {$table}\n\t\t\tWHERE {$where_sql}\n\t\t\tGROUP BY object_type, object_id\n\t\t\tHAVING SUM(visits) >= %d\n\t\t\tORDER BY {$order_sql} {$order}\n\t\t\tLIMIT %d, %d";
        $query_prepare = array_merge( $prepare, array((int) $args['min_visits'], (int) $args['offset'], (int) $args['per_page']) );
        $items = $wpdb->get_results( $wpdb->prepare( $query_sql, $query_prepare ), ARRAY_A );
        return array(
            'items' => ( is_array( $items ) ? $items : array() ),
            'total' => $total,
        );
    }

    /**
     * Grouped bot hits for list table.
     *
     * @param array $args Query arguments.
     * @return array{items: array, total: int}
     */
    public static function get_bot_hits( $args = array() ) {
        global $wpdb;
        $defaults = array(
            'days'           => 30,
            'offset'         => 0,
            'per_page'       => 50,
            'orderby'        => 'visits',
            'order'          => 'DESC',
            'search'         => '',
            'filter_purpose' => '',
        );
        $args = wp_parse_args( $args, $defaults );
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $since = self::get_since_date( $args['days'] );
        $where = array('hit_date >= %s');
        $prepare = array($since);
        if ( !empty( $args['search'] ) ) {
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = 'bot_name LIKE %s';
            $prepare[] = $like;
        }
        if ( in_array( $args['filter_purpose'], array('research', 'citation'), true ) ) {
            $where[] = 'bot_purpose = %s';
            $prepare[] = $args['filter_purpose'];
        }
        $where_sql = implode( ' AND ', $where );
        $count_sql = "SELECT COUNT(*) FROM (\n\t\t\tSELECT bot_name, bot_purpose FROM {$table} WHERE {$where_sql} GROUP BY bot_name, bot_purpose\n\t\t) grouped";
        $total = ( empty( $prepare ) ? (int) $wpdb->get_var( $count_sql ) : (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $prepare ) ) );
        $allowed_orderby = array(
            'visits'    => 'SUM(visits)',
            'last_seen' => 'MAX(last_seen)',
            'bot_name'  => 'bot_name',
        );
        $orderby_key = ( isset( $allowed_orderby[$args['orderby']] ) ? $args['orderby'] : 'visits' );
        $order_sql = $allowed_orderby[$orderby_key];
        $order = ( strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC' );
        $query_sql = "SELECT\n\t\t\tbot_name,\n\t\t\tbot_purpose,\n\t\t\tSUM(visits) AS visits,\n\t\t\tMAX(last_seen) AS last_seen,\n\t\t\tSUM(CASE WHEN object_id > 0 THEN visits ELSE 0 END) AS content_visits,\n\t\t\tSUM(CASE WHEN object_id IS NULL OR object_id = 0 THEN visits ELSE 0 END) AS noise_visits\n\t\t\tFROM {$table}\n\t\t\tWHERE {$where_sql}\n\t\t\tGROUP BY bot_name, bot_purpose\n\t\t\tORDER BY {$order_sql} {$order}\n\t\t\tLIMIT %d, %d";
        $query_prepare = array_merge( $prepare, array((int) $args['offset'], (int) $args['per_page']) );
        $items = $wpdb->get_results( $wpdb->prepare( $query_sql, $query_prepare ), ARRAY_A );
        return array(
            'items' => ( is_array( $items ) ? $items : array() ),
            'total' => $total,
        );
    }

    /**
     * Noise / unmapped hits for list table.
     *
     * @param array $args Query arguments.
     * @return array{items: array, total: int}
     */
    public static function get_noise_hits( $args = array() ) {
        global $wpdb;
        $defaults = array(
            'days'           => 30,
            'offset'         => 0,
            'per_page'       => 50,
            'orderby'        => 'visits',
            'order'          => 'DESC',
            'search'         => '',
            'filter_bot'     => '',
            'filter_purpose' => '',
        );
        $args = wp_parse_args( $args, $defaults );
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $since = self::get_since_date( $args['days'] );
        $has_kind = self::table_has_request_kind_column();
        if ( $has_kind ) {
            $where = array('hit_date >= %s', '(object_id IS NULL OR object_id = 0 OR request_kind IN (\'search\', \'junk\', \'unmapped\'))');
        } else {
            $where = array('hit_date >= %s', '(object_id IS NULL OR object_id = 0)');
        }
        $prepare = array($since);
        if ( !empty( $args['filter_bot'] ) ) {
            $where[] = 'bot_name = %s';
            $prepare[] = $args['filter_bot'];
        }
        if ( in_array( $args['filter_purpose'], array('research', 'citation'), true ) ) {
            $where[] = 'bot_purpose = %s';
            $prepare[] = $args['filter_purpose'];
        }
        if ( !empty( $args['search'] ) ) {
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = '(request_path LIKE %s OR normalized_url LIKE %s OR bot_name LIKE %s)';
            $prepare[] = $like;
            $prepare[] = $like;
            $prepare[] = $like;
        }
        $where_sql = implode( ' AND ', $where );
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total = ( empty( $prepare ) ? (int) $wpdb->get_var( $count_sql ) : (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $prepare ) ) );
        $allowed_orderby = array(
            'visits'       => 'visits',
            'last_seen'    => 'last_seen',
            'request_path' => 'request_path',
            'bot_name'     => 'bot_name',
        );
        $orderby_key = ( isset( $allowed_orderby[$args['orderby']] ) ? $args['orderby'] : 'visits' );
        $order_sql = $allowed_orderby[$orderby_key];
        $order = ( strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC' );
        $query_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$order_sql} {$order} LIMIT %d, %d";
        $query_prepare = array_merge( $prepare, array((int) $args['offset'], (int) $args['per_page']) );
        $items = $wpdb->get_results( $wpdb->prepare( $query_sql, $query_prepare ), ARRAY_A );
        return array(
            'items' => ( is_array( $items ) ? $items : array() ),
            'total' => $total,
        );
    }

    /**
     * Per-bot visit totals and latest seen (for settings block list).
     *
     * Single indexed aggregation — intended for on-demand AJAX, not page render.
     *
     * @param int|null $days Days to include; defaults to retention window.
     * @return array<string, array{visits: int, last_seen: string}>
     */
    public static function get_bot_visit_stats( $days = null ) {
        global $wpdb;
        if ( null === $days ) {
            $days = self::get_retention_days();
        }
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $since = self::get_since_date( (int) $days );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT bot_name, SUM(visits) AS visits, MAX(last_seen) AS last_seen\n\t\t\t\tFROM {$table}\n\t\t\t\tWHERE hit_date >= %s\n\t\t\t\tGROUP BY bot_name", $since ), ARRAY_A );
        $stats = array();
        if ( !is_array( $rows ) ) {
            return $stats;
        }
        foreach ( $rows as $row ) {
            $name = ( isset( $row['bot_name'] ) ? (string) $row['bot_name'] : '' );
            if ( '' === $name ) {
                continue;
            }
            $stats[$name] = array(
                'visits'    => ( isset( $row['visits'] ) ? (int) $row['visits'] : 0 ),
                'last_seen' => ( isset( $row['last_seen'] ) ? (string) $row['last_seen'] : '' ),
            );
        }
        return $stats;
    }

    /**
     * Top bots by visit count.
     *
     * @param int $days  Days to include.
     * @param int $limit Max rows.
     * @return array
     */
    public static function get_top_bots( $days = 30, $limit = 10 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $since = self::get_since_date( $days );
        return $wpdb->get_results( $wpdb->prepare( "SELECT bot_name, bot_purpose, SUM(visits) AS visits\n\t\t\t\tFROM {$table}\n\t\t\t\tWHERE hit_date >= %s\n\t\t\t\tGROUP BY bot_name, bot_purpose\n\t\t\t\tORDER BY visits DESC\n\t\t\t\tLIMIT %d", $since, absint( $limit ) ), ARRAY_A );
    }

    /**
     * Top crawled content pages.
     *
     * @param int $days  Days to include.
     * @param int $limit Max rows.
     * @return array
     */
    public static function get_top_content_pages( $days = 30, $limit = 10 ) {
        $result = self::get_content_hits( array(
            'days'     => $days,
            'offset'   => 0,
            'per_page' => absint( $limit ),
            'orderby'  => 'visits',
            'order'    => 'DESC',
        ) );
        return ( isset( $result['items'] ) ? $result['items'] : array() );
    }

    /**
     * Bots where most visits are noise.
     *
     * @param int   $days      Days to include.
     * @param float $threshold Noise ratio threshold (0-1).
     * @param int   $limit     Max rows.
     * @return array
     */
    public static function get_bots_mostly_noise( $days = 30, $threshold = 0.8, $limit = 10 ) {
        $result = self::get_bot_hits( array(
            'days'     => $days,
            'offset'   => 0,
            'per_page' => 100,
            'orderby'  => 'visits',
            'order'    => 'DESC',
        ) );
        $items = ( isset( $result['items'] ) ? $result['items'] : array() );
        $noisy = array();
        foreach ( $items as $row ) {
            $visits = (int) $row['visits'];
            $noise = (int) $row['noise_visits'];
            if ( $visits <= 0 ) {
                continue;
            }
            $ratio = $noise / $visits;
            if ( $ratio >= $threshold ) {
                $row['noise_ratio'] = round( $ratio * 100, 1 );
                $noisy[] = $row;
            }
        }
        return array_slice( $noisy, 0, absint( $limit ) );
    }

    /**
     * Visit breakdown by purpose.
     *
     * @param int $days Days to include.
     * @return array
     */
    public static function get_purpose_breakdown( $days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $since = self::get_since_date( $days );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT bot_purpose, SUM(visits) AS visits\n\t\t\t\tFROM {$table}\n\t\t\t\tWHERE hit_date >= %s\n\t\t\t\tGROUP BY bot_purpose", $since ), ARRAY_A );
        $breakdown = array(
            'research' => 0,
            'citation' => 0,
        );
        foreach ( $rows as $row ) {
            $key = ( isset( $row['bot_purpose'] ) ? $row['bot_purpose'] : 'research' );
            if ( isset( $breakdown[$key] ) ) {
                $breakdown[$key] = (int) $row['visits'];
            }
        }
        return $breakdown;
    }

    /**
     * Daily visit totals for charts.
     *
     * @param int $days Days to include.
     * @return array
     */
    public static function get_visits_by_day( $days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $since = self::get_since_date( $days );
        $has_kind = self::table_has_request_kind_column();
        if ( $has_kind ) {
            $sql = "SELECT hit_date,\n\t\t\t\tSUM(CASE WHEN request_kind = 'content' THEN visits ELSE 0 END) AS content_visits,\n\t\t\t\tSUM(CASE WHEN request_kind IN ('search', 'junk', 'unmapped', 'redirect') OR (object_id IS NULL OR object_id = 0) THEN visits ELSE 0 END) AS noise_visits,\n\t\t\t\tSUM(visits) AS total_visits\n\t\t\t\tFROM {$table}\n\t\t\t\tWHERE hit_date >= %s\n\t\t\t\tGROUP BY hit_date\n\t\t\t\tORDER BY hit_date ASC";
        } else {
            $sql = "SELECT hit_date,\n\t\t\t\tSUM(CASE WHEN object_id > 0 THEN visits ELSE 0 END) AS content_visits,\n\t\t\t\tSUM(CASE WHEN object_id IS NULL OR object_id = 0 THEN visits ELSE 0 END) AS noise_visits,\n\t\t\t\tSUM(visits) AS total_visits\n\t\t\t\tFROM {$table}\n\t\t\t\tWHERE hit_date >= %s\n\t\t\t\tGROUP BY hit_date\n\t\t\t\tORDER BY hit_date ASC";
        }
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $since ), ARRAY_A );
        return ( is_array( $rows ) ? $rows : array() );
    }

    /**
     * Hits for a normalized URL hash.
     *
     * @param string $url Full URL.
     * @param int    $days Days to include.
     * @return array
     */
    public static function get_hits_for_url( $url, $days = 30 ) {
        global $wpdb;
        $url_hash = hash( 'sha256', untrailingslashit( strtolower( esc_url_raw( $url ) ) ) );
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $since = self::get_since_date( $days );
        return $wpdb->get_results( $wpdb->prepare( "SELECT bot_name, bot_purpose, SUM(visits) AS visits, MAX(last_seen) AS last_seen\n\t\t\t\tFROM {$table}\n\t\t\t\tWHERE url_hash = %s AND hit_date >= %s\n\t\t\t\tGROUP BY bot_name, bot_purpose\n\t\t\t\tORDER BY visits DESC", $url_hash, $since ), ARRAY_A );
    }

    /**
     * Per-bot breakdown for a content object.
     *
     * @param int    $object_id   Object ID.
     * @param string $object_type Object type.
     * @param int    $days        Days to include.
     * @return array
     */
    public static function get_hits_for_object( $object_id, $object_type, $days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $since = self::get_since_date( $days );
        $has_kind = self::table_has_request_kind_column();
        if ( $has_kind ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT\n\t\t\t\t\t\tbot_name,\n\t\t\t\t\t\tbot_purpose,\n\t\t\t\t\t\tSUM(CASE WHEN request_kind = 'content' THEN visits ELSE 0 END) AS content_visits,\n\t\t\t\t\t\tSUM(CASE WHEN request_kind = 'redirect' THEN visits ELSE 0 END) AS redirect_visits,\n\t\t\t\t\t\tMAX(last_seen) AS last_seen,\n\t\t\t\t\t\tCAST(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN request_kind = 'content' THEN status_code END ORDER BY last_seen DESC SEPARATOR ','), ',', 1) AS UNSIGNED) AS status_code\n\t\t\t\t\tFROM {$table}\n\t\t\t\t\tWHERE object_id = %d AND object_type = %s AND hit_date >= %s\n\t\t\t\t\tAND request_kind IN ('content', 'redirect')\n\t\t\t\t\tGROUP BY bot_name, bot_purpose\n\t\t\t\t\tHAVING (content_visits + redirect_visits) > 0\n\t\t\t\t\tORDER BY (content_visits + redirect_visits) DESC",
                $object_id,
                $object_type,
                $since
            ), ARRAY_A );
        }
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT bot_name, bot_purpose, SUM(visits) AS visits, MAX(last_seen) AS last_seen\n\t\t\t\tFROM {$table}\n\t\t\t\tWHERE object_id = %d AND object_type = %s AND hit_date >= %s\n\t\t\t\tGROUP BY bot_name, bot_purpose\n\t\t\t\tORDER BY visits DESC",
            $object_id,
            $object_type,
            $since
        ), ARRAY_A );
    }

    /**
     * Top content page for a bot.
     *
     * @param string $bot_name Bot name.
     * @param string $bot_purpose Bot purpose.
     * @param int    $days Days.
     * @return array|null
     */
    public static function get_top_content_for_bot( $bot_name, $bot_purpose, $days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $since = self::get_since_date( $days );
        $has_kind = self::table_has_request_kind_column();
        $kind_sql = ( $has_kind ? " AND request_kind = 'content'" : '' );
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT object_type, object_id, SUM(visits) AS visits, MAX(normalized_url) AS normalized_url\n\t\t\t\tFROM {$table}\n\t\t\t\tWHERE bot_name = %s AND bot_purpose = %s AND hit_date >= %s AND object_id > 0{$kind_sql}\n\t\t\t\tGROUP BY object_type, object_id\n\t\t\t\tORDER BY SUM(visits) DESC\n\t\t\t\tLIMIT 1",
            $bot_name,
            $bot_purpose,
            $since
        ), ARRAY_A );
        return ( is_array( $row ) ? $row : null );
    }

    /**
     * Count open SEO issues for an object.
     *
     * @param int    $object_id   Object ID.
     * @param string $object_type Object type.
     * @return int
     */
    public static function get_issue_count_for_object( $object_id, $object_type ) {
        global $wpdb;
        if ( $object_id <= 0 || empty( $object_type ) ) {
            return 0;
        }
        $issues_table = $wpdb->prefix . 'sb2_seo_issues';
        $urls_table = $wpdb->prefix . 'sb2_seo_urls';
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*)\n\t\t\t\tFROM {$issues_table} AS i\n\t\t\t\tINNER JOIN {$urls_table} AS u ON i.url_id = u.id\n\t\t\t\tWHERE u.object_id = %d AND u.object_type = %s AND i.user_status = 'active'", $object_id, $object_type ) );
    }

    /**
     * GSC keyword count for a post URL.
     *
     * @param string $view_url Public URL.
     * @return int
     */
    public static function get_gsc_keyword_count( $view_url ) {
        if ( empty( $view_url ) || !class_exists( Tools\Tools_GSC_Helper::class ) ) {
            return 0;
        }
        $keywords = Tools\Tools_GSC_Helper::get_keywords_for_page( $view_url );
        return ( is_array( $keywords ) ? count( $keywords ) : 0 );
    }

    /**
     * Recent aggregated hits for dashboard activity feed.
     *
     * @param int $limit Max rows.
     * @return array
     */
    public static function get_recent_activity( $limit = 10 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        return $wpdb->get_results( $wpdb->prepare( "SELECT bot_name, bot_purpose, request_path, normalized_url, visits, last_seen, object_id, object_type\n\t\t\t\tFROM {$table}\n\t\t\t\tWHERE object_id > 0\n\t\t\t\tORDER BY last_seen DESC\n\t\t\t\tLIMIT %d", absint( $limit ) ), ARRAY_A );
    }

    /**
     * Whether the hits table has request_kind column.
     *
     * @return bool
     */
    public static function table_has_request_kind_column() {
        global $wpdb;
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $column = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'request_kind' ) );
        return !empty( $column );
    }

    /**
     * Backfill request_kind for legacy rows.
     *
     * @return int Rows updated.
     */
    public static function backfill_request_kinds() {
        global $wpdb;
        if ( !self::table_has_request_kind_column() ) {
            return 0;
        }
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $updated = 0;
        $wpdb->query( "UPDATE {$table} SET request_kind = 'content' WHERE object_id > 0 AND (request_kind = '' OR request_kind = 'unmapped')" );
        $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET request_kind = 'search'\n\t\t\t\tWHERE (object_id IS NULL OR object_id = 0)\n\t\t\t\tAND (request_path LIKE %s OR request_path LIKE %s)", '%/search%', '%page2page%' ) );
        $wpdb->query( "UPDATE {$table} SET request_kind = 'junk'\n\t\t\tWHERE (object_id IS NULL OR object_id = 0)\n\t\t\tAND request_kind NOT IN ('search', 'content')\n\t\t\tAND (CHAR_LENGTH(request_path) > 120 OR request_path REGEXP '(page[0-9]+){4,}')" );
        $wpdb->query( "UPDATE {$table} SET request_kind = 'unmapped'\n\t\t\tWHERE (object_id IS NULL OR object_id = 0)\n\t\t\tAND (request_kind = '' OR request_kind IS NULL)" );
        $updated = (int) $wpdb->get_var( 'SELECT ROW_COUNT()' );
        return $updated;
    }

    /**
     * Reclassify legacy content rows that stored redirect responses as content crawls.
     *
     * @return int Rows updated.
     */
    public static function backfill_redirect_kinds() {
        global $wpdb;
        if ( !self::table_has_request_kind_column() ) {
            return 0;
        }
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $wpdb->query( "UPDATE {$table}\n\t\t\tSET request_kind = 'redirect'\n\t\t\tWHERE request_kind = 'content'\n\t\t\tAND status_code >= 300\n\t\t\tAND status_code < 400" );
        return (int) $wpdb->rows_affected;
    }

    /**
     * Delete noise rows from the hits table.
     *
     * @return int Rows deleted.
     */
    public static function purge_noise_data() {
        global $wpdb;
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $has_kind = self::table_has_request_kind_column();
        $deleted = 0;
        if ( $has_kind ) {
            $wpdb->query( "DELETE FROM {$table}\n\t\t\t\tWHERE object_id IS NULL OR object_id = 0 OR request_kind IN ('search', 'junk', 'unmapped')" );
        } else {
            $wpdb->query( "DELETE FROM {$table} WHERE object_id IS NULL OR object_id = 0" );
        }
        $deleted = (int) $wpdb->rows_affected;
        return $deleted;
    }

    /**
     * Remove hit rows older than the retention window.
     *
     * @return int Rows deleted.
     */
    public static function cleanup_old_hits() {
        global $wpdb;
        $table = $wpdb->prefix . 'sb2_ai_bot_hits';
        $days = self::get_retention_days();
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE hit_date < DATE_SUB(CURDATE(), INTERVAL %d DAY)", $days ) );
        return (int) $wpdb->rows_affected;
    }

}
