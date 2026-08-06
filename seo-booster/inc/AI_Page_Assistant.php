<?php

namespace Cleverplugins\SEOBooster;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Page-scoped AI assistant on the editor AI tools tab.
 *
 * Free: Ask (read-only answers + optional title/meta suggestions).
 * Pro: Apply focus keyword and create autolink actions.
 *
 * @package Cleverplugins\SEOBooster
 * @since 7.4.0
 */
class AI_Page_Assistant {
    /** Post meta key for assistant thread history. */
    public const META_KEY = '_sb_ai_assistant_thread';

    /** Max stored Q&A turns per post. */
    public const MAX_TURNS = 8;

    /**
     * Register AJAX hooks.
     *
     * @return void
     */
    public static function init() {
        add_action( 'wp_ajax_sb_seo_ai_assistant_ask', array(__CLASS__, 'ajax_ask') );
        add_action( 'wp_ajax_sb_seo_get_ai_assistant_history', array(__CLASS__, 'ajax_get_history') );
        add_action( 'wp_ajax_sb_seo_ai_assistant_apply_action', array(__CLASS__, 'ajax_apply_action') );
    }

    /**
     * Whether the post has saved assistant history (cheap meta check for localize).
     *
     * @param int $post_id Post ID.
     * @return bool
     */
    public static function has_history( $post_id ) {
        $post_id = (int) $post_id;
        if ( $post_id <= 0 ) {
            return false;
        }
        $raw = get_post_meta( $post_id, self::META_KEY, true );
        return is_array( $raw ) && !empty( $raw['turns'] ) && is_array( $raw['turns'] );
    }

    /**
     * Whether Pro apply actions are available for this install.
     *
     * @return bool
     */
    public static function can_use_pro_actions() {
        $can = false;
        if ( function_exists( __NAMESPACE__ . '\\seobooster_fs' ) ) {
        }
        return $can;
    }

    /**
     * AJAX: ask a question about the current post.
     *
     * @return void
     */
    public static function ajax_ask() {
        check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );
        $post_id = ( isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0 );
        if ( $post_id <= 0 || !Utils::user_can_edit_object( $post_id ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied', 'seo-booster' ),
            ) );
        }
        $provider = LLM_Helper::get_selected_ai_provider();
        if ( 'WordPress' !== $provider ) {
            wp_send_json_error( array(
                'message' => __( 'Ask about this page requires WordPress Connectors. Choose WordPress Connectors under SEO Booster → Settings → AI/LLM.', 'seo-booster' ),
            ) );
        }
        if ( !LLM_Helper::wp_ai_is_available() ) {
            wp_send_json_error( array(
                'message' => LLM_Helper::wp_ai_unavailable_message(),
            ) );
        }
        $question = ( isset( $_POST['question'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['question'] ) ) : '' );
        $question = LLM_Helper::ensure_utf8( $question );
        if ( '' === $question ) {
            wp_send_json_error( array(
                'message' => __( 'Please enter a question.', 'seo-booster' ),
            ) );
        }
        $post = get_post( $post_id );
        if ( !$post ) {
            wp_send_json_error( array(
                'message' => __( 'Post not found', 'seo-booster' ),
            ) );
        }
        try {
            $condenser = new LLM_Content_Condenser();
            $condensed = $condenser->condense_post_content( $post_id );
            $language = LLM_Helper::get_post_language( $post_id );
            $summary = SB_SEO_Metabox::build_local_analysis_summary( $post_id );
            $service = new LLM_WP_Connector_Service();
            $result = $service->generate_assistant_reply(
                $post_id,
                $question,
                $condensed,
                $language,
                $summary
            );
            $turn = array(
                'q'            => $question,
                'answer'       => $result['answer'],
                'actions'      => ( isset( $result['actions'] ) ? $result['actions'] : array() ),
                'titles'       => ( isset( $result['titles'] ) ? $result['titles'] : array() ),
                'descriptions' => ( isset( $result['descriptions'] ) ? $result['descriptions'] : array() ),
                'ts'           => time(),
            );
            self::append_turn( $post_id, $turn, $language );
            wp_send_json_success( array(
                'answer'              => $turn['answer'],
                'actions'             => $turn['actions'],
                'titles'              => $turn['titles'],
                'descriptions'        => $turn['descriptions'],
                'language'            => $language,
                'question'            => $question,
                'can_use_pro_actions' => self::can_use_pro_actions(),
                'timestamp'           => $turn['ts'],
            ) );
        } catch ( \Exception $e ) {
            Utils::log( 'AI page assistant error: ' . $e->getMessage(), 2 );
            wp_send_json_error( array(
                'message' => __( 'Something went wrong. Check the SEO Booster debug log for details.', 'seo-booster' ),
            ) );
        }
    }

    /**
     * AJAX: load saved assistant history (lazy, on AI tab open).
     *
     * @return void
     */
    public static function ajax_get_history() {
        check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );
        $post_id = ( isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0 );
        if ( $post_id <= 0 || !Utils::user_can_edit_object( $post_id ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied', 'seo-booster' ),
            ) );
        }
        $thread = self::get_thread( $post_id );
        $turns = ( isset( $thread['turns'] ) && is_array( $thread['turns'] ) ? $thread['turns'] : array() );
        $last = ( !empty( $turns ) ? end( $turns ) : null );
        wp_send_json_success( array(
            'has_history'         => !empty( $turns ),
            'language'            => ( isset( $thread['language'] ) ? (string) $thread['language'] : '' ),
            'updated'             => ( isset( $thread['updated'] ) ? (int) $thread['updated'] : 0 ),
            'last_turn'           => $last,
            'turn_count'          => count( $turns ),
            'can_use_pro_actions' => self::can_use_pro_actions(),
        ) );
    }

    /**
     * AJAX: apply a Pro action (focus keyword or autolink).
     *
     * @return void
     */
    public static function ajax_apply_action() {
        check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );
        $post_id = ( isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0 );
        if ( $post_id <= 0 || !Utils::user_can_edit_object( $post_id ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied', 'seo-booster' ),
            ) );
        }
        $applied = false;
        if ( function_exists( __NAMESPACE__ . '\\seobooster_fs' ) ) {
        }
        if ( !$applied ) {
            wp_send_json_error( array(
                'message' => __( 'Applying this action requires SEO Booster Pro.', 'seo-booster' ),
                'pro'     => true,
            ) );
        }
    }

    /**
     * Run a Pro apply action and send JSON (called only inside premium gate).
     *
     * @param int $post_id Post ID.
     * @return bool True if handled (success or typed error already sent).
     */
    private static function execute_pro_action( $post_id ) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in AI_Page_Assistant::ajax_apply_action() before this runs.
        $type = ( isset( $_POST['action_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['action_type'] ) ) : '' );
        $raw = ( isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : array() );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if ( is_string( $raw ) ) {
            $decoded = json_decode( $raw, true );
            $raw = ( is_array( $decoded ) ? $decoded : array() );
        }
        if ( !is_array( $raw ) ) {
            $raw = array();
        }
        if ( 'set_focus_keyword' === $type ) {
            $keyword = ( isset( $raw['keyword'] ) ? LLM_Helper::ensure_utf8( sanitize_text_field( (string) $raw['keyword'] ) ) : '' );
            if ( '' === $keyword ) {
                wp_send_json_error( array(
                    'message' => __( 'Missing focus keyword.', 'seo-booster' ),
                ) );
                return true;
            }
            $result = SEO_Plugin_Integration::apply_suggestion(
                'focus_keyword',
                $keyword,
                $post_id,
                'post',
                false
            );
            if ( empty( $result['success'] ) ) {
                wp_send_json_error( array(
                    'message' => ( isset( $result['message'] ) ? $result['message'] : __( 'Could not set focus keyword.', 'seo-booster' ) ),
                ) );
                return true;
            }
            wp_send_json_success( array(
                'message' => sprintf( 
                    /* translators: %s: focus keyword */
                    __( 'Focus keyword set to “%s”.', 'seo-booster' ),
                    $keyword
                 ),
                'type'    => $type,
                'keyword' => $keyword,
            ) );
            return true;
        }
        if ( 'create_autolink' === $type ) {
            // Autolink rules apply site-wide, so require admin capability, not just edit_post.
            if ( !current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Permission denied', 'seo-booster' ),
                ) );
                return true;
            }
            $keyword = ( isset( $raw['keyword'] ) ? LLM_Helper::ensure_utf8( sanitize_text_field( (string) $raw['keyword'] ) ) : '' );
            $target_url = ( isset( $raw['target_url'] ) ? esc_url_raw( (string) $raw['target_url'] ) : '' );
            if ( '' === $target_url ) {
                $permalink = get_permalink( $post_id );
                $target_url = ( $permalink ? $permalink : '' );
            }
            if ( '' === $keyword || '' === $target_url ) {
                wp_send_json_error( array(
                    'message' => __( 'Missing keyword or target URL for autolink.', 'seo-booster' ),
                ) );
                return true;
            }
            $created = self::create_autolink_rule( $keyword, $target_url );
            if ( is_wp_error( $created ) ) {
                wp_send_json_error( array(
                    'message' => $created->get_error_message(),
                ) );
                return true;
            }
            wp_send_json_success( array(
                'message' => sprintf( 
                    /* translators: %s: keyword phrase */
                    __( 'Autolink created for “%s”.', 'seo-booster' ),
                    $keyword
                 ),
                'type'    => $type,
                'rule_id' => $created,
            ) );
            return true;
        }
        wp_send_json_error( array(
            'message' => __( 'Unknown action type.', 'seo-booster' ),
        ) );
        return true;
    }

    /**
     * Insert an autolink rule if the keyword is free.
     *
     * @param string $keyword    Keyword phrase.
     * @param string $target_url Destination URL.
     * @return int|\WP_Error Rule ID or error.
     */
    private static function create_autolink_rule( $keyword, $target_url ) {
        global $wpdb;
        $keyword = LLM_Helper::ensure_utf8( $keyword );
        $target_url = LLM_Helper::ensure_utf8( $target_url );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Duplicate check before insert.
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sb2_autolink WHERE LOWER(keyword) = LOWER(%s) LIMIT 1", $keyword ) );
        if ( $existing ) {
            return new \WP_Error('exists', __( 'An autolink rule for this keyword already exists.', 'seo-booster' ));
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional write.
        $inserted = $wpdb->insert( "{$wpdb->prefix}sb2_autolink", array(
            'keyword' => $keyword,
            'url'     => $target_url,
        ), array('%s', '%s') );
        if ( !$inserted ) {
            return new \WP_Error('insert_failed', __( 'Could not create autolink rule.', 'seo-booster' ));
        }
        $rule_id = (int) $wpdb->insert_id;
        if ( class_exists( Seobooster2::class ) ) {
            Seobooster2::flush_autolink_caches();
        }
        return $rule_id;
    }

    /**
     * Read and normalize thread meta.
     *
     * @param int $post_id Post ID.
     * @return array{updated: int, language: string, turns: array}
     */
    private static function get_thread( $post_id ) {
        $raw = get_post_meta( (int) $post_id, self::META_KEY, true );
        if ( !is_array( $raw ) ) {
            return array(
                'updated'  => 0,
                'language' => '',
                'turns'    => array(),
            );
        }
        $turns = ( isset( $raw['turns'] ) && is_array( $raw['turns'] ) ? $raw['turns'] : array() );
        $clean = array();
        foreach ( $turns as $turn ) {
            if ( !is_array( $turn ) ) {
                continue;
            }
            $clean[] = array(
                'q'            => ( isset( $turn['q'] ) ? LLM_Helper::ensure_utf8( (string) $turn['q'] ) : '' ),
                'answer'       => ( isset( $turn['answer'] ) ? LLM_Helper::ensure_utf8( (string) $turn['answer'] ) : '' ),
                'actions'      => ( isset( $turn['actions'] ) && is_array( $turn['actions'] ) ? $turn['actions'] : array() ),
                'titles'       => ( isset( $turn['titles'] ) && is_array( $turn['titles'] ) ? $turn['titles'] : array() ),
                'descriptions' => ( isset( $turn['descriptions'] ) && is_array( $turn['descriptions'] ) ? $turn['descriptions'] : array() ),
                'ts'           => ( isset( $turn['ts'] ) ? (int) $turn['ts'] : 0 ),
            );
        }
        return array(
            'updated'  => ( isset( $raw['updated'] ) ? (int) $raw['updated'] : 0 ),
            'language' => ( isset( $raw['language'] ) ? (string) $raw['language'] : '' ),
            'turns'    => $clean,
        );
    }

    /**
     * Append a turn and cap history length.
     *
     * @param int    $post_id  Post ID.
     * @param array  $turn     Turn data.
     * @param string $language Locale.
     * @return void
     */
    private static function append_turn( $post_id, array $turn, $language ) {
        $thread = self::get_thread( $post_id );
        $thread['turns'][] = $turn;
        if ( count( $thread['turns'] ) > self::MAX_TURNS ) {
            $thread['turns'] = array_slice( $thread['turns'], -1 * self::MAX_TURNS );
        }
        $thread['updated'] = time();
        $thread['language'] = (string) $language;
        update_post_meta( (int) $post_id, self::META_KEY, $thread );
    }

}
