<?php

namespace Cleverplugins\SEOBooster;

use Cleverplugins\SEOBooster\Tools\Tools_Llms_Txt;
use Cleverplugins\SEOBooster\Tools\Tools_Page;
use Cleverplugins\SEOBooster\Tools\Tools_GSC_Helper;
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Site-scoped Pro AI advisor on the dashboard (Ask about your SEO).
 *
 * @package Cleverplugins\SEOBooster
 * @since 7.4.0
 */
class AI_Site_Assistant {
    const RATE_LIMIT_SECONDS = 20;

    const TRANSIENT_PREFIX = 'sb_site_assistant_rl_';

    const HISTORY_META_KEY = 'seobooster_site_assistant_history';

    const HISTORY_LIMIT = 3;

    /**
     * Register AJAX and dashboard assets.
     *
     * @return void
     */
    public static function init() {
        add_action( 'wp_ajax_sb_site_assistant_ask', array(__CLASS__, 'ajax_ask') );
        add_action( 'wp_ajax_sb_site_assistant_history', array(__CLASS__, 'ajax_history') );
        add_action( 'admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets') );
    }

    /**
     * Whether the current install may use the Pro ask feature.
     *
     * @return bool
     */
    public static function can_use_ask() {
        $can = false;
        // seobooster_fs() lives in this namespace; function_exists( 'seobooster_fs' ) checks global only.
        if ( function_exists( __NAMESPACE__ . '\\seobooster_fs' ) ) {
        }
        return $can;
    }

    /**
     * Whether AI is configured enough to show Ask / upsell cards.
     *
     * @return bool
     */
    public static function is_ai_ready_for_card() {
        $provider = LLM_Helper::get_selected_ai_provider();
        if ( 'disabled' === $provider || '' === $provider ) {
            return false;
        }
        if ( 'WordPress' === $provider ) {
            return LLM_Helper::wp_ai_is_available();
        }
        if ( 'seobooster' === $provider ) {
            return Credits_Service::is_ai_provider_available() && Credits_Service::is_registered();
        }
        return false;
    }

    /**
     * Whether the current user may submit a dashboard Ask question now.
     *
     * @return bool
     */
    public static function can_submit_ask() {
        return self::get_ask_readiness()['ready'];
    }

    /**
     * Pro + WordPress Connectors readiness for dashboard Ask.
     *
     * @return array{ready:bool,message:string}
     */
    public static function get_ask_readiness() {
        if ( !self::can_use_ask() ) {
            return array(
                'ready'   => false,
                'message' => __( 'Ask about your SEO is available in SEO Booster Pro.', 'seo-booster' ),
            );
        }
        $provider = LLM_Helper::get_selected_ai_provider();
        if ( 'WordPress' !== $provider ) {
            return array(
                'ready'   => false,
                'message' => __( 'Ask about your SEO requires WordPress Connectors. Choose WordPress Connectors under SEO Booster → Settings → AI/LLM.', 'seo-booster' ),
            );
        }
        if ( !LLM_Helper::wp_ai_is_available() ) {
            return array(
                'ready'   => false,
                'message' => LLM_Helper::wp_ai_unavailable_message(),
            );
        }
        return array(
            'ready'   => true,
            'message' => '',
        );
    }

    /**
     * Enqueue dialog assets on the dashboard when Ask or upsell may show.
     *
     * @param string $hook Hook suffix.
     * @return void
     */
    public static function enqueue_assets( $hook ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = ( isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '' );
        if ( 'sb2_dashboard' !== $page && 'toplevel_page_sb2_dashboard' !== $hook ) {
            return;
        }
        if ( !self::can_use_ask() ) {
            return;
        }
        Utils::enqueue_modal_assets();
        $css = SEOBOOSTER_PLUGINPATH . 'css/sb-site-assistant.css';
        $js = SEOBOOSTER_PLUGINPATH . 'js/sb-site-assistant.js';
        wp_enqueue_style(
            'sb-site-assistant',
            SEOBOOSTER_PLUGINURL . 'css/sb-site-assistant.css',
            array('sb-ui', 'sb-modal'),
            ( file_exists( $css ) ? (string) filemtime( $css ) : '7.4.1' )
        );
        wp_enqueue_script(
            'sb-site-assistant',
            SEOBOOSTER_PLUGINURL . 'js/sb-site-assistant.js',
            array('jquery', 'sb-modal', 'sb-dashboard'),
            ( file_exists( $js ) ? (string) filemtime( $js ) : '7.4.1' ),
            true
        );
        $ask_readiness = self::get_ask_readiness();
        $upgrade_url = 'https://seoboosterpro.com';
        if ( function_exists( __NAMESPACE__ . '\\seobooster_fs' ) ) {
            $fs = seobooster_fs();
            if ( is_object( $fs ) && method_exists( $fs, 'get_upgrade_url' ) ) {
                $upgrade_url = $fs->get_upgrade_url();
            }
        }
        $support_url = 'https://seoboosterpro.com/support/';
        if ( function_exists( __NAMESPACE__ . '\\seobooster_fs' ) ) {
            $fs = seobooster_fs();
            if ( is_object( $fs ) && method_exists( $fs, 'contact_url' ) ) {
                $contact = $fs->contact_url();
                if ( is_string( $contact ) && '' !== $contact ) {
                    $support_url = $contact;
                }
            }
        }
        wp_localize_script( 'sb-site-assistant', 'sbSiteAssistant', array(
            'ajaxurl'         => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'sb_site_assistant' ),
            'can_ask'         => self::can_use_ask(),
            'can_submit_ask'  => !empty( $ask_readiness['ready'] ),
            'setup_message'   => ( isset( $ask_readiness['message'] ) ? (string) $ask_readiness['message'] : '' ),
            'settings_ai_url' => admin_url( 'admin.php?page=sb2_settings#ai-llm' ),
            'upgrade_url'     => $upgrade_url,
            'support_url'     => $support_url,
            'tool_urls'       => self::get_tool_urls_map(),
            'strings'         => array(
                'title'          => __( 'Ask about your SEO', 'seo-booster' ),
                'lead'           => __( 'Ask about priorities, Search Console trends, or how to use SEO Booster.', 'seo-booster' ),
                'disclaimer'     => __( 'For site results and rankings it uses data already in SEO Booster. It can also explain SEO Booster features and basic SEO ideas. It does not crawl live pages, change Google, or run tools for you. If unsure, it should say so.', 'seo-booster' ),
                'placeholder'    => __( 'Ask about priorities, keywords, or how to use a feature…', 'seo-booster' ),
                'ask'            => __( 'Ask', 'seo-booster' ),
                'asking'         => __( 'Thinking…', 'seo-booster' ),
                'askingElapsed'  => __( 'Thinking… %ss', 'seo-booster' ),
                'openTool'       => __( 'Open tool', 'seo-booster' ),
                'viewPage'       => __( 'View page', 'seo-booster' ),
                'editPage'       => __( 'Edit', 'seo-booster' ),
                'recentAnswers'  => __( 'Recent answers', 'seo-booster' ),
                'loadingAnswer'  => __( 'Loading saved answer…', 'seo-booster' ),
                'lowConfidence'  => __( 'Confidence is limited for this answer. Double-check in SEO Booster before making big changes.', 'seo-booster' ),
                'unsupported'    => __( 'That is not something SEO Booster can do yet.', 'seo-booster' ),
                'featureIdea'    => __( 'If you think we should cover this, tell support so we can improve the plugin.', 'seo-booster' ),
                'contactSupport' => __( 'Contact support', 'seo-booster' ),
                'nextSteps'      => __( 'Suggested next steps', 'seo-booster' ),
                'itemsHeading'   => __( 'Pages and items to review', 'seo-booster' ),
                'genericError'   => __( 'Something went wrong. Check the SEO Booster debug log for details.', 'seo-booster' ),
                'emptyQuestion'  => __( 'Please enter a question.', 'seo-booster' ),
                'rateLimited'    => __( 'Please wait a moment before asking again.', 'seo-booster' ),
                'chips'          => array(
                    array(
                        'intent'   => 'prioritize',
                        'question' => __( 'Which pages need attention first?', 'seo-booster' ),
                    ),
                    array(
                        'intent'   => 'traffic',
                        'question' => __( 'How is Search Console traffic doing?', 'seo-booster' ),
                    ),
                    array(
                        'intent'   => 'keywords',
                        'question' => __( 'What new keywords showed up recently?', 'seo-booster' ),
                    ),
                    array(
                        'intent'   => 'content',
                        'question' => __( 'What content should I create next?', 'seo-booster' ),
                    ),
                    array(
                        'intent'   => 'help',
                        'question' => __( 'What can SEO Booster help me with?', 'seo-booster' ),
                    )
                ),
            ),
        ) );
    }

    /**
     * AJAX: answer a site-scoped question (Pro + WordPress Connectors).
     *
     * @return void
     */
    public static function ajax_ask() {
        check_ajax_referer( 'sb_site_assistant', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied', 'seo-booster' ),
            ) );
        }
        $readiness = self::get_ask_readiness();
        if ( empty( $readiness['ready'] ) ) {
            wp_send_json_error( array(
                'message' => ( isset( $readiness['message'] ) ? (string) $readiness['message'] : '' ),
            ) );
        }
        $user_id = get_current_user_id();
        $rl_key = self::TRANSIENT_PREFIX . $user_id;
        if ( get_transient( $rl_key ) ) {
            wp_send_json_error( array(
                'message' => __( 'Please wait a moment before asking again.', 'seo-booster' ),
                'code'    => 'rate_limited',
            ) );
        }
        $question = ( isset( $_POST['question'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['question'] ) ) : '' );
        $question = LLM_Helper::ensure_utf8( $question );
        if ( '' === $question ) {
            wp_send_json_error( array(
                'message' => __( 'Please enter a question.', 'seo-booster' ),
            ) );
        }
        $chip_id = ( isset( $_POST['intent'] ) ? sanitize_key( wp_unslash( (string) $_POST['intent'] ) ) : '' );
        $intent = self::detect_intent( $question, $chip_id );
        try {
            $brief = self::build_site_brief( $intent, $question );
            $language = get_user_locale();
            if ( !is_string( $language ) || '' === $language ) {
                $language = get_locale();
            }
            $service = new LLM_WP_Connector_Service();
            $result = $service->generate_site_assistant_reply(
                $question,
                $brief,
                $language,
                $intent
            );
            set_transient( $rl_key, 1, self::RATE_LIMIT_SECONDS );
            $items = ( isset( $result['items'] ) && is_array( $result['items'] ) ? array_slice( $result['items'], 0, 10 ) : array() );
            $items = self::prepare_result_items( $items, $brief );
            $response = self::sanitize_history_response( array(
                'answer'       => $result['answer'],
                'confidence'   => $result['confidence'],
                'items'        => $items,
                'next_steps'   => ( isset( $result['next_steps'] ) ? $result['next_steps'] : array() ),
                'unsupported'  => !empty( $result['unsupported'] ),
                'feature_idea' => ( isset( $result['feature_idea'] ) ? $result['feature_idea'] : '' ),
                'intent'       => $intent,
                'question'     => $question,
            ) );
            self::save_history_entry( $user_id, $response );
            wp_send_json_success( $response );
        } catch ( \Exception $e ) {
            Utils::log( 'AI site assistant error: ' . $e->getMessage(), 2 );
            wp_send_json_error( array(
                'message' => __( 'Something went wrong. Check the SEO Booster debug log for details.', 'seo-booster' ),
            ) );
        }
    }

    /**
     * AJAX: list or load the current user's three most recent answers.
     *
     * @return void
     */
    public static function ajax_history() {
        check_ajax_referer( 'sb_site_assistant', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied', 'seo-booster' ),
            ) );
        }
        if ( !self::can_use_ask() ) {
            wp_send_json_error( array(
                'message' => __( 'Ask about your SEO is available in SEO Booster Pro.', 'seo-booster' ),
            ) );
        }
        $history = self::get_history( get_current_user_id() );
        $id = ( isset( $_POST['history_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['history_id'] ) ) : '' );
        if ( '' !== $id ) {
            foreach ( $history as $entry ) {
                if ( isset( $entry['id'], $entry['response'] ) && hash_equals( (string) $entry['id'], $id ) ) {
                    wp_send_json_success( array(
                        'response' => self::sanitize_history_response( $entry['response'] ),
                    ) );
                }
            }
            wp_send_json_error( array(
                'message' => __( 'That saved answer is no longer available.', 'seo-booster' ),
            ) );
        }
        $summaries = array();
        foreach ( $history as $entry ) {
            $created = ( isset( $entry['created'] ) ? (int) $entry['created'] : 0 );
            $summaries[] = array(
                'id'       => ( isset( $entry['id'] ) ? sanitize_text_field( (string) $entry['id'] ) : '' ),
                'question' => ( isset( $entry['response']['question'] ) ? sanitize_text_field( (string) $entry['response']['question'] ) : '' ),
                'date'     => ( $created > 0 ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created ) : '' ),
            );
        }
        wp_send_json_success( array(
            'history' => $summaries,
            'latest'  => ( !empty( $history[0]['response'] ) ? self::sanitize_history_response( $history[0]['response'] ) : null ),
        ) );
    }

    /**
     * Add safe page and edit links to AI result items.
     *
     * @param array $items Result items.
     * @param array $brief Site brief supplied to the model.
     * @return array
     */
    private static function prepare_result_items( array $items, array $brief ) {
        $allowed_urls = self::collect_brief_urls( $brief );
        $prepared = array();
        foreach ( $items as $item ) {
            if ( !is_array( $item ) ) {
                continue;
            }
            $url = ( isset( $item['url'] ) ? esc_url_raw( (string) $item['url'] ) : '' );
            if ( '' !== $url && !isset( $allowed_urls[untrailingslashit( $url )] ) ) {
                $url = '';
            }
            $tool = ( isset( $item['tool'] ) ? sanitize_key( (string) $item['tool'] ) : 'none' );
            $prepared[] = array(
                'url'      => $url,
                'edit_url' => ( '' !== $url ? self::get_edit_url_for_frontend_url( $url ) : '' ),
                'title'    => ( isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : '' ),
                'why'      => ( isset( $item['why'] ) ? sanitize_text_field( (string) $item['why'] ) : '' ),
                'tool'     => $tool,
                'tool_url' => ( 'none' !== $tool ? self::tool_url( $tool ) : '' ),
            );
        }
        return $prepared;
    }

    /**
     * Collect frontend URLs that were actually supplied to the model.
     *
     * @param array $values Site brief values.
     * @return array<string, bool>
     */
    private static function collect_brief_urls( array $values ) {
        $urls = array();
        array_walk_recursive( $values, static function ( $value, $key ) use(&$urls) {
            if ( !is_string( $value ) || !in_array( $key, array(
                'url',
                'page',
                'leader_url',
                'home_url'
            ), true ) ) {
                return;
            }
            $url = esc_url_raw( $value );
            if ( '' !== $url ) {
                $urls[untrailingslashit( $url )] = true;
            }
        } );
        return $urls;
    }

    /**
     * Resolve a frontend URL to a post or term edit screen.
     *
     * @param string $url Frontend URL.
     * @return string
     */
    private static function get_edit_url_for_frontend_url( $url ) {
        $post_id = url_to_postid( $url );
        if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
            $edit_url = get_edit_post_link( $post_id, 'raw' );
            return ( is_string( $edit_url ) ? esc_url_raw( $edit_url ) : '' );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'sb2_seo_urls';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin table and prepared URL hash.
        $object = $wpdb->get_row( $wpdb->prepare( "SELECT object_id, object_type FROM {$table} WHERE url_hash = %s LIMIT 1", hash( 'sha256', $url ) ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( !$object || empty( $object->object_id ) ) {
            return '';
        }
        $object_id = (int) $object->object_id;
        if ( 'post' === $object->object_type && current_user_can( 'edit_post', $object_id ) ) {
            $edit_url = get_edit_post_link( $object_id, 'raw' );
            return ( is_string( $edit_url ) ? esc_url_raw( $edit_url ) : '' );
        }
        if ( 'term' === $object->object_type && current_user_can( 'edit_term', $object_id ) ) {
            $term = get_term( $object_id );
            if ( $term && !is_wp_error( $term ) ) {
                $edit_url = get_edit_term_link( $object_id, $term->taxonomy );
                return ( is_string( $edit_url ) ? esc_url_raw( $edit_url ) : '' );
            }
        }
        return '';
    }

    /**
     * Store one answer and keep only the three newest for this user.
     *
     * @param int   $user_id  User ID.
     * @param array $response Safe response data.
     * @return void
     */
    private static function save_history_entry( $user_id, array $response ) {
        $history = self::get_history( $user_id );
        array_unshift( $history, array(
            'id'       => wp_generate_uuid4(),
            'created'  => time(),
            'response' => self::sanitize_history_response( $response ),
        ) );
        update_user_meta( $user_id, self::HISTORY_META_KEY, array_slice( $history, 0, self::HISTORY_LIMIT ) );
    }

    /**
     * Get validated answer history for a user.
     *
     * @param int $user_id User ID.
     * @return array
     */
    private static function get_history( $user_id ) {
        $history = get_user_meta( $user_id, self::HISTORY_META_KEY, true );
        if ( !is_array( $history ) ) {
            return array();
        }
        return array_slice( $history, 0, self::HISTORY_LIMIT );
    }

    /**
     * Sanitize response data before storage or output.
     *
     * @param array $response Response data.
     * @return array
     */
    private static function sanitize_history_response( array $response ) {
        $items = array();
        if ( !empty( $response['items'] ) && is_array( $response['items'] ) ) {
            foreach ( array_slice( $response['items'], 0, 10 ) as $item ) {
                if ( !is_array( $item ) ) {
                    continue;
                }
                $items[] = array(
                    'url'      => ( isset( $item['url'] ) ? esc_url_raw( (string) $item['url'] ) : '' ),
                    'edit_url' => ( isset( $item['edit_url'] ) ? esc_url_raw( (string) $item['edit_url'] ) : '' ),
                    'title'    => ( isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : '' ),
                    'why'      => ( isset( $item['why'] ) ? sanitize_text_field( (string) $item['why'] ) : '' ),
                    'tool'     => ( isset( $item['tool'] ) ? sanitize_key( (string) $item['tool'] ) : 'none' ),
                    'tool_url' => ( isset( $item['tool_url'] ) ? esc_url_raw( (string) $item['tool_url'] ) : '' ),
                );
            }
        }
        $next_steps = array();
        if ( !empty( $response['next_steps'] ) && is_array( $response['next_steps'] ) ) {
            foreach ( array_slice( $response['next_steps'], 0, 6 ) as $step ) {
                $next_steps[] = sanitize_text_field( (string) $step );
            }
        }
        $confidence = ( isset( $response['confidence'] ) ? sanitize_key( (string) $response['confidence'] ) : 'medium' );
        if ( !in_array( $confidence, array('high', 'medium', 'low'), true ) ) {
            $confidence = 'medium';
        }
        return array(
            'answer'       => ( isset( $response['answer'] ) ? LLM_Helper::ensure_utf8( sanitize_textarea_field( (string) $response['answer'] ) ) : '' ),
            'confidence'   => $confidence,
            'items'        => $items,
            'next_steps'   => $next_steps,
            'unsupported'  => !empty( $response['unsupported'] ),
            'feature_idea' => ( isset( $response['feature_idea'] ) ? sanitize_text_field( (string) $response['feature_idea'] ) : '' ),
            'intent'       => ( isset( $response['intent'] ) ? sanitize_key( (string) $response['intent'] ) : '' ),
            'question'     => ( isset( $response['question'] ) ? LLM_Helper::ensure_utf8( sanitize_text_field( (string) $response['question'] ) ) : '' ),
        );
    }

    /**
     * Detect intent from chip id or free-text heuristics.
     *
     * @param string $question User question.
     * @param string $chip_id  Optional chip intent id.
     * @return string
     */
    public static function detect_intent( $question, $chip_id = '' ) {
        $allowed = array(
            'prioritize',
            'traffic',
            'keywords',
            'content',
            'ai_visibility',
            'help',
            'unsupported'
        );
        $chip_id = sanitize_key( (string) $chip_id );
        if ( in_array( $chip_id, $allowed, true ) ) {
            return $chip_id;
        }
        $q = strtolower( (string) $question );
        if ( preg_match( '/\\b(recrawl|reindex|submit to google|googlebot|core web vitals|competitor|backlink|hosting)\\b/', $q ) ) {
            return 'unsupported';
        }
        if ( preg_match( '/\\b(what is|how (?:do|can) i|where (?:is|do)|how to|setup wizard|seo possibilit(?:y|ies)|entity ?map|llms\\.?txt|autolink|automatic links?|bulk meta|image metadata|focus keyword|weekly email|debug log|ai bots?|credits|connectors|what can seo booster)\\b/', $q ) ) {
            return 'help';
        }
        if ( preg_match( '/\\b(new keyword|keywords? showed|first seen|discovered)\\b/', $q ) ) {
            return 'keywords';
        }
        if ( preg_match( '/\\b(losing clicks|traffic|impressions|ctr|search console|gsc)\\b/', $q ) ) {
            return 'traffic';
        }
        if ( preg_match( '/\\b(write|content ideas?|create next|topics? to)\\b/', $q ) ) {
            return 'content';
        }
        if ( preg_match( '/\\b(ai bot|llms|entity map|ai crawl)\\b/', $q ) ) {
            return 'ai_visibility';
        }
        if ( preg_match( '/\\b(need attention|fix first|priorit|what should i fix)\\b/', $q ) ) {
            return 'prioritize';
        }
        if ( preg_match( '/\\b(explain|mean|definition|what does)\\b/', $q ) ) {
            return 'help';
        }
        return 'prioritize';
    }

    /**
     * Build compact site brief for the model.
     *
     * @param string $intent   Intent key.
     * @param string $question Optional user question for routed help knowledge.
     * @return array
     */
    public static function build_site_brief( $intent, $question = '' ) {
        $intent = sanitize_key( (string) $intent );
        $health = Setup_Wizard::get_health_counts_payload();
        $stats = SEO_Issues_Manager::get_analysis_stats();
        $kpis = Dashboard_Actions::get_search_performance_kpis( 30 );
        $kpi_out = array();
        if ( !empty( $kpis['kpis'] ) && is_array( $kpis['kpis'] ) ) {
            foreach ( $kpis['kpis'] as $key => $metric ) {
                if ( !is_array( $metric ) ) {
                    continue;
                }
                $kpi_out[$key] = array(
                    'value'      => ( isset( $metric['value'] ) ? $metric['value'] : null ),
                    'change'     => ( isset( $metric['change'] ) ? $metric['change'] : null ),
                    'percentage' => ( isset( $metric['percentage'] ) ? $metric['percentage'] : null ),
                );
            }
        }
        $is_pro = false;
        if ( function_exists( __NAMESPACE__ . '\\seobooster_fs' ) ) {
        }
        $handbook = self::get_plugin_handbook();
        if ( 'help' === $intent ) {
            $handbook = self::get_plugin_handbook( $question );
        }
        $brief = array(
            'core'      => array(
                'site_name'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
                'home_url'      => home_url( '/' ),
                'locale'        => get_locale(),
                'gsc_connected' => (bool) get_option( 'seobooster_access_token' ) && (bool) get_option( 'seobooster_selected_site' ),
                'ai_provider'   => LLM_Helper::get_selected_ai_provider(),
                'is_pro'        => $is_pro,
                'health'        => ( isset( $health['counts'] ) ? $health['counts'] : array() ),
                'possibilities' => array(
                    'total'    => ( isset( $stats['total_issues'] ) ? (int) $stats['total_issues'] : 0 ),
                    'critical' => ( isset( $stats['critical'] ) ? (int) $stats['critical'] : 0 ),
                    'high'     => ( isset( $stats['high'] ) ? (int) $stats['high'] : 0 ),
                    'medium'   => ( isset( $stats['medium'] ) ? (int) $stats['medium'] : 0 ),
                    'low'      => ( isset( $stats['low'] ) ? (int) $stats['low'] : 0 ),
                ),
                'kpis_30d'      => $kpi_out,
                'kpi_labels'    => array(
                    'current' => ( isset( $kpis['label_current'] ) ? $kpis['label_current'] : '' ),
                    'compare' => ( isset( $kpis['label_compare'] ) ? $kpis['label_compare'] : '' ),
                ),
            ),
            'handbook'  => $handbook,
            'intent'    => $intent,
            'pack'      => array(),
            'tool_urls' => self::get_tool_urls_map(),
        );
        if ( 'prioritize' === $intent ) {
            $brief['pack'] = array(
                'top_issue_types' => SEO_Issues_Manager::get_top_possibilities_for_dashboard( 5 ),
                'priority_urls'   => SEO_Issues_Manager::get_priority_urls_for_assistant( 8 ),
            );
        } elseif ( 'traffic' === $intent ) {
            $brief['pack'] = self::build_traffic_pack();
        } elseif ( 'keywords' === $intent ) {
            $brief['pack'] = self::build_keywords_pack();
        } elseif ( 'content' === $intent ) {
            $brief['pack'] = self::build_content_pack();
        } elseif ( 'ai_visibility' === $intent ) {
            $brief['pack'] = self::build_ai_visibility_pack();
        }
        return $brief;
    }

    /**
     * Curated product facts for model grounding.
     *
     * @param string $question Optional help question.
     * @return string
     */
    public static function get_plugin_handbook( $question = '' ) {
        if ( '' !== trim( (string) $question ) ) {
            return AI_Plugin_Knowledge::get_for_question( $question );
        }
        return AI_Plugin_Knowledge::get_core_summary();
    }

    /**
     * Map tool keys to admin URLs.
     *
     * @return array<string, string>
     */
    public static function get_tool_urls_map() {
        return array(
            'issues'            => admin_url( 'admin.php?page=sb2_seo_issues' ),
            'focus'             => Tools_Page::get_page_url( 'focus-keyword' ),
            'meta'              => Tools_Page::get_page_url( 'meta' ),
            'image'             => Tools_Page::get_page_url( 'image' ),
            'gsc-opportunities' => Tools_Page::get_page_url( 'gsc-opportunities' ),
            'content-decay'     => Tools_Page::get_page_url( 'content-decay' ),
            'llms'              => Tools_Page::get_page_url( 'llms' ),
            'entity-map'        => Tools_Page::get_page_url( 'entity-map' ),
            'settings-ai'       => admin_url( 'admin.php?page=sb2_settings#ai-llm' ),
            'dashboard'         => admin_url( 'admin.php?page=sb2_dashboard' ),
            'gsc'               => admin_url( 'admin.php?page=sb2_gsc' ),
            'ai-bots'           => admin_url( 'admin.php?page=sb2_ai_bots' ),
        );
    }

    /**
     * Resolve a tool key to a URL.
     *
     * @param string $tool_key Tool key.
     * @return string
     */
    public static function tool_url( $tool_key ) {
        $map = self::get_tool_urls_map();
        $key = sanitize_key( str_replace( '_', '-', (string) $tool_key ) );
        return ( isset( $map[$key] ) ? $map[$key] : '' );
    }

    /**
     * Traffic pack: decay sample pages when Pro helpers exist.
     *
     * @return array
     */
    private static function build_traffic_pack() {
        $pack = array(
            'content_decay_count'     => 0,
            'gsc_opportunities_count' => 0,
            'decay_sample'            => array(),
        );
        if ( class_exists( Tools_GSC_Helper::class ) && method_exists( Tools_GSC_Helper::class, 'has_gsc_data' ) ) {
            if ( Tools_GSC_Helper::has_gsc_data() ) {
                $pack['content_decay_count'] = (int) Tools_GSC_Helper::count_content_decay_pages();
                $pack['gsc_opportunities_count'] = (int) Tools_GSC_Helper::count_gsc_opportunity_pages();
                $scan = Tools_GSC_Helper::scan_content_decay();
                $items = ( isset( $scan['items'] ) && is_array( $scan['items'] ) ? array_slice( $scan['items'], 0, 8 ) : array() );
                foreach ( $items as $item ) {
                    $url = '';
                    if ( !empty( $item['view_url'] ) ) {
                        $url = (string) $item['view_url'];
                    } elseif ( !empty( $item['page_url'] ) ) {
                        $url = (string) $item['page_url'];
                    }
                    $pack['decay_sample'][] = array(
                        'url'             => $url,
                        'title'           => ( isset( $item['title'] ) ? (string) $item['title'] : '' ),
                        'recent_clicks'   => ( isset( $item['recent_clicks'] ) ? (int) $item['recent_clicks'] : 0 ),
                        'previous_clicks' => ( isset( $item['previous_clicks'] ) ? (int) $item['previous_clicks'] : 0 ),
                    );
                }
            }
        }
        return $pack;
    }

    /**
     * Keywords pack: new + top keywords.
     *
     * @return array
     */
    private static function build_keywords_pack() {
        global $wpdb;
        $table_k = $wpdb->prefix . 'sb2_query_keywords';
        $table_h = $wpdb->prefix . 'sb2_query_keywords_history';
        $days = 14;
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prefixed tables; prepared days.
        $new_keywords = $wpdb->get_results( $wpdb->prepare( "SELECT k.query, k.page, AVG(h.position) as avg_position, SUM(h.clicks) as total_clicks, SUM(h.impressions) as total_impressions\n\t\t\t\tFROM {$table_k} k\n\t\t\t\tJOIN {$table_h} h ON k.id = h.query_keywords_id\n\t\t\t\tWHERE k.first_seen_date >= CURDATE() - INTERVAL %d DAY\n\t\t\t\tGROUP BY k.page, k.query\n\t\t\t\tORDER BY k.first_seen_date DESC\n\t\t\t\tLIMIT 10", $days ), ARRAY_A );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( !is_array( $new_keywords ) ) {
            $new_keywords = array();
        }
        $top = Utils::get_top_keywords( 30, 10 );
        if ( !is_array( $top ) ) {
            $top = array();
        }
        return array(
            'new_keywords_days' => $days,
            'new_keywords'      => $new_keywords,
            'top_keywords'      => $top,
        );
    }

    /**
     * Content ideas pack: high impressions, low clicks.
     *
     * @return array
     */
    private static function build_content_pack() {
        global $wpdb;
        $table_k = $wpdb->prefix . 'sb2_query_keywords';
        $table_h = $wpdb->prefix . 'sb2_query_keywords_history';
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prefixed tables; read-only.
        $rows = $wpdb->get_results( "SELECT k.query, k.page,\n\t\t\t\tSUM(h.impressions) as impressions,\n\t\t\t\tSUM(h.clicks) as clicks,\n\t\t\t\tAVG(h.position) as avg_position\n\t\t\tFROM {$table_k} k\n\t\t\tJOIN {$table_h} h ON k.id = h.query_keywords_id\n\t\t\tWHERE h.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)\n\t\t\tGROUP BY k.query, k.page\n\t\t\tHAVING impressions >= 50 AND clicks <= 2 AND avg_position BETWEEN 4 AND 25\n\t\t\tORDER BY impressions DESC\n\t\t\tLIMIT 10", ARRAY_A );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( !is_array( $rows ) ) {
            $rows = array();
        }
        return array(
            'opportunity_queries' => $rows,
        );
    }

    /**
     * AI visibility pack.
     *
     * @return array
     */
    private static function build_ai_visibility_pack() {
        $llms = Tools_Llms_Txt::get_settings();
        $llms_on = !empty( $llms['enabled'] );
        $bots = array();
        if ( class_exists( AI_Bot_Tracker::class ) ) {
            $items = AI_Bot_Tracker::get_top_content_pages( 30, 8 );
            if ( is_array( $items ) ) {
                foreach ( $items as $item ) {
                    $bots[] = array(
                        'url'    => ( isset( $item['url'] ) ? (string) $item['url'] : (( isset( $item['path'] ) ? (string) $item['path'] : '' )) ),
                        'title'  => ( isset( $item['title'] ) ? (string) $item['title'] : '' ),
                        'visits' => ( isset( $item['visits'] ) ? (int) $item['visits'] : 0 ),
                    );
                }
            }
        }
        return array(
            'llms_enabled'  => $llms_on,
            'top_bot_pages' => $bots,
        );
    }

}
