<?php

namespace Cleverplugins\SEOBooster;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class SB_AI_Bots_List_Table extends \WP_List_Table {
    /** @var string */
    private $view = 'content';

    /** @var int */
    private $filter_days = 30;

    public function __construct() {
        parent::__construct( array(
            'singular' => 'ai_bot_hit',
            'plural'   => 'ai_bot_hits',
            'ajax'     => false,
        ) );
    }

    /**
     * Set active report view.
     *
     * @param string $view View key.
     * @return void
     */
    public function set_view( $view ) {
        $allowed = array('content', 'by_bot', 'noise');
        $this->view = ( in_array( $view, $allowed, true ) ? $view : 'content' );
    }

    /**
     * Set reporting window in days.
     *
     * @param int $days Days.
     * @return void
     */
    public function set_filter_days( $days ) {
        $allowed = array(7, 30, 90);
        $this->filter_days = ( in_array( (int) $days, $allowed, true ) ? (int) $days : 30 );
    }

    public function no_items() {
        if ( 'noise' === $this->view ) {
            esc_html_e( 'No unmapped or trap traffic recorded for this period.', 'seo-booster' );
            return;
        }
        if ( 'by_bot' === $this->view ) {
            esc_html_e( 'No AI bot visits recorded for this period.', 'seo-booster' );
            return;
        }
        esc_html_e( 'No mapped content visits recorded for this period.', 'seo-booster' );
    }

    public function get_columns() {
        if ( 'by_bot' === $this->view ) {
            return array(
                'bot_name'     => _x( 'Bot', 'Column label', 'seo-booster' ),
                'bot_purpose'  => _x( 'Purpose', 'Column label', 'seo-booster' ),
                'visits'       => _x( 'Visits', 'Column label', 'seo-booster' ),
                'content_page' => _x( 'Top content page', 'Column label', 'seo-booster' ),
                'noise_ratio'  => _x( 'Noise', 'Column label', 'seo-booster' ),
                'last_seen'    => _x( 'Last seen', 'Column label', 'seo-booster' ),
            );
        }
        if ( 'noise' === $this->view ) {
            return array(
                'request_path' => _x( 'Request path', 'Column label', 'seo-booster' ),
                'bot_name'     => _x( 'Bot', 'Column label', 'seo-booster' ),
                'visits'       => _x( 'Visits', 'Column label', 'seo-booster' ),
                'status_code'  => _x( 'Status', 'Column label', 'seo-booster' ),
                'last_seen'    => _x( 'Last seen', 'Column label', 'seo-booster' ),
            );
        }
        return array(
            'page'            => _x( 'Page', 'Column label', 'seo-booster' ),
            'object_type'     => _x( 'Type', 'Column label', 'seo-booster' ),
            'visits'          => _x( 'Visits', 'Column label', 'seo-booster' ),
            'redirect_visits' => _x( 'Redirects', 'Column label', 'seo-booster' ),
            'bots'            => _x( 'Bots', 'Column label', 'seo-booster' ),
            'research_visits' => _x( 'Research', 'Column label', 'seo-booster' ),
            'citation_visits' => _x( 'Citation', 'Column label', 'seo-booster' ),
            'status_code'     => _x( 'Status', 'Column label', 'seo-booster' ),
            'last_seen'       => _x( 'Last seen', 'Column label', 'seo-booster' ),
        );
    }

    protected function get_sortable_columns() {
        if ( 'by_bot' === $this->view ) {
            return array(
                'bot_name'  => array('bot_name', false),
                'visits'    => array('visits', true),
                'last_seen' => array('last_seen', true),
            );
        }
        if ( 'noise' === $this->view ) {
            return array(
                'request_path' => array('request_path', false),
                'bot_name'     => array('bot_name', false),
                'visits'       => array('visits', true),
                'last_seen'    => array('last_seen', true),
            );
        }
        return array(
            'visits'      => array('visits', true),
            'last_seen'   => array('last_seen', true),
            'object_type' => array('object_type', false),
        );
    }

    public function get_bulk_actions() {
        if ( 'noise' === $this->view ) {
            return array(
                'purge_noise' => __( 'Purge all noise data', 'seo-booster' ),
            );
        }
        return array();
    }

    protected function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }
        $filter_bot = ( isset( $_REQUEST['filter_bot'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_bot'] ) ) : '' );
        $filter_purpose = ( isset( $_REQUEST['filter_purpose'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_purpose'] ) ) : '' );
        $filter_object_type = ( isset( $_REQUEST['filter_object_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_object_type'] ) ) : '' );
        $filter_min_visits = ( isset( $_REQUEST['filter_min_visits'] ) ? (int) $_REQUEST['filter_min_visits'] : 1 );
        $crawlers = AI_Bot_Tracker::get_crawlers();
        echo '<div class="alignleft actions">';
        if ( 'content' === $this->view ) {
            echo '<label class="screen-reader-text" for="filter-object-type">' . esc_html__( 'Filter by type', 'seo-booster' ) . '</label>';
            echo '<select name="filter_object_type" id="filter-object-type">';
            echo '<option value="">' . esc_html__( 'All types', 'seo-booster' ) . '</option>';
            foreach ( array(
                'post',
                'term',
                'archive',
                'home'
            ) as $type_key ) {
                echo '<option value="' . esc_attr( $type_key ) . '"' . selected( $filter_object_type, $type_key, false ) . '>' . esc_html( AI_Bot_Tracker::get_object_type_label( $type_key ) ) . '</option>';
            }
            echo '</select>';
            echo '<label class="screen-reader-text" for="filter-min-visits">' . esc_html__( 'Minimum visits', 'seo-booster' ) . '</label>';
            echo '<select name="filter_min_visits" id="filter-min-visits">';
            foreach ( array(1, 5, 10) as $min ) {
                /* translators: %d: minimum visit count filter. */
                echo '<option value="' . esc_attr( $min ) . '"' . selected( $filter_min_visits, $min, false ) . '>' . esc_html( sprintf( __( 'Min %d visits', 'seo-booster' ), $min ) ) . '</option>';
            }
            echo '</select>';
        }
        if ( 'by_bot' !== $this->view ) {
            echo '<label class="screen-reader-text" for="filter-bot">' . esc_html__( 'Filter by bot', 'seo-booster' ) . '</label>';
            echo '<select name="filter_bot" id="filter-bot">';
            echo '<option value="">' . esc_html__( 'All bots', 'seo-booster' ) . '</option>';
            foreach ( array_keys( $crawlers ) as $bot_name ) {
                echo '<option value="' . esc_attr( $bot_name ) . '"' . selected( $filter_bot, $bot_name, false ) . '>' . esc_html( $bot_name ) . '</option>';
            }
            echo '</select>';
        }
        echo '<label class="screen-reader-text" for="filter-purpose">' . esc_html__( 'Filter by purpose', 'seo-booster' ) . '</label>';
        echo '<select name="filter_purpose" id="filter-purpose">';
        echo '<option value="">' . esc_html__( 'All purposes', 'seo-booster' ) . '</option>';
        echo '<option value="research"' . selected( $filter_purpose, 'research', false ) . '>' . esc_html__( 'Research / training', 'seo-booster' ) . '</option>';
        echo '<option value="citation"' . selected( $filter_purpose, 'citation', false ) . '>' . esc_html__( 'Citation / answer engine', 'seo-booster' ) . '</option>';
        echo '</select>';
        submit_button(
            __( 'Filter', 'seo-booster' ),
            'secondary',
            'filter_action',
            false
        );
        echo '</div>';
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'visits':
            case 'redirect_visits':
            case 'research_visits':
            case 'citation_visits':
                return number_format_i18n( (int) $item[$column_name] );
            case 'last_seen':
                return esc_html( $item[$column_name] );
            case 'bot_purpose':
                return esc_html( AI_Bot_Tracker::get_purpose_label( $item[$column_name] ) );
            case 'object_type':
                return esc_html( AI_Bot_Tracker::get_object_type_label( $item[$column_name] ) );
            case 'status_code':
                return self::format_status_badge( (int) $item[$column_name] );
            default:
                return ( isset( $item[$column_name] ) && is_scalar( $item[$column_name] ) ? esc_html( $item[$column_name] ) : '' );
        }
    }

    protected function column_page( $item ) {
        $object_id = ( isset( $item['object_id'] ) ? (int) $item['object_id'] : 0 );
        $object_type = ( isset( $item['object_type'] ) ? $item['object_type'] : '' );
        $label = AI_Bot_Tracker::resolve_object_label( $object_id, $object_type );
        $title = esc_html( $label['title'] );
        $view_url = ( !empty( $item['normalized_url'] ) ? esc_url( $item['normalized_url'] ) : '' );
        if ( empty( $view_url ) && !empty( $label['view_url'] ) ) {
            $view_url = esc_url( $label['view_url'] );
        }
        $edit_url = ( !empty( $label['edit_url'] ) ? esc_url( $label['edit_url'] ) : '' );
        $links = array();
        if ( $edit_url ) {
            $links[] = '<a href="' . $edit_url . '">' . esc_html__( 'Edit', 'seo-booster' ) . '</a>';
        }
        if ( $view_url ) {
            $links[] = '<a href="' . $view_url . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View', 'seo-booster' ) . '</a>';
        }
        $badges = self::build_integration_badges( $object_id, $object_type, $view_url );
        $html = '<strong>';
        if ( $view_url ) {
            $html .= '<a href="' . $view_url . '" target="_blank" rel="noopener noreferrer">' . $title . '</a>';
        } else {
            $html .= $title;
        }
        $html .= '</strong>';
        if ( $badges ) {
            $html .= '<div class="sb-ai-bots-badges">' . $badges . '</div>';
        }
        if ( !empty( $links ) ) {
            $html .= '<div class="row-actions">' . implode( ' | ', $links ) . '</div>';
        }
        $html .= '<button type="button" class="button-link sb-ai-bots-expand" data-object-id="' . esc_attr( $object_id ) . '" data-object-type="' . esc_attr( $object_type ) . '">';
        $html .= '<span class="dashicons dashicons-arrow-down-alt2"></span> ' . esc_html__( 'Bot breakdown', 'seo-booster' );
        $html .= '</button>';
        $html .= '<div class="sb-ai-bots-breakdown sb-hidden" id="sb-ai-breakdown-' . esc_attr( $object_type . '-' . $object_id ) . '"></div>';
        return $html;
    }

    protected function column_bots( $item ) {
        $bots = ( isset( $item['bot_names'] ) ? $item['bot_names'] : '' );
        if ( empty( $bots ) ) {
            return '&mdash;';
        }
        $parts = array_map( 'trim', explode( ',', $bots ) );
        if ( count( $parts ) <= 3 ) {
            return esc_html( $bots );
        }
        return esc_html( sprintf( 
            /* translators: 1: bot count, 2: first bots */
            __( '%1$d bots (%2$s…)', 'seo-booster' ),
            count( $parts ),
            implode( ', ', array_slice( $parts, 0, 2 ) )
         ) );
    }

    protected function column_content_page( $item ) {
        $top = AI_Bot_Tracker::get_top_content_for_bot( ( isset( $item['bot_name'] ) ? $item['bot_name'] : '' ), ( isset( $item['bot_purpose'] ) ? $item['bot_purpose'] : '' ), $this->filter_days );
        if ( empty( $top ) ) {
            return '&mdash;';
        }
        $label = AI_Bot_Tracker::resolve_object_label( (int) $top['object_id'], $top['object_type'] );
        $title = esc_html( $label['title'] );
        $url = ( !empty( $label['view_url'] ) ? esc_url( $label['view_url'] ) : '' );
        if ( $url ) {
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $title . '</a>';
        }
        return $title;
    }

    protected function column_noise_ratio( $item ) {
        $visits = ( isset( $item['visits'] ) ? (int) $item['visits'] : 0 );
        $noise = ( isset( $item['noise_visits'] ) ? (int) $item['noise_visits'] : 0 );
        if ( $visits <= 0 ) {
            return '&mdash;';
        }
        $percent = round( $noise / $visits * 100, 1 );
        $text = sprintf( '%s%%', number_format_i18n( $percent, 1 ) );
        $can_show_block_link = false;
        $html = esc_html( $text );
        if ( $percent >= 80 && $can_show_block_link ) {
            $settings_url = add_query_arg( array(
                'highlight_bot' => rawurlencode( $item['bot_name'] ),
            ), admin_url( 'admin.php?page=sb2_settings#ai-llm' ) );
            $html .= '<div class="row-actions"><a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Block bot', 'seo-booster' ) . '</a></div>';
        }
        return $html;
    }

    protected function column_request_path( $item ) {
        $path = ( isset( $item['request_path'] ) ? $item['request_path'] : '' );
        $url = ( isset( $item['normalized_url'] ) && !empty( $item['normalized_url'] ) ? $item['normalized_url'] : home_url( $path ) );
        $display = AI_Bot_Tracker::truncate_display( $path, 60 );
        return sprintf(
            '<a href="%1$s" target="_blank" rel="noopener noreferrer" title="%3$s">%2$s</a>',
            esc_url( $url ),
            esc_html( $display ),
            esc_attr( $path )
        );
    }

    protected function column_bot_name( $item ) {
        $name = ( isset( $item['bot_name'] ) ? $item['bot_name'] : '' );
        $url = add_query_arg( array(
            'page'        => 'sb2_ai_bots',
            'view'        => 'content',
            'filter_bot'  => $name,
            'filter_days' => $this->filter_days,
        ), admin_url( 'admin.php' ) );
        return '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
    }

    /**
     * Format HTTP status as badge.
     *
     * @param int $status_code Status code.
     * @return string
     */
    private static function format_status_badge( $status_code ) {
        if ( $status_code <= 0 ) {
            return '&mdash;';
        }
        $class = 'sb-status-ok';
        if ( $status_code >= 400 ) {
            $class = 'sb-status-error';
        } elseif ( $status_code >= 300 ) {
            $class = 'sb-status-warn';
        }
        return '<span class="sb-ai-bots-status ' . esc_attr( $class ) . '">' . esc_html( (string) $status_code ) . '</span>';
    }

    /**
     * Build GSC and SEO issue badges for a content row.
     *
     * @param int    $object_id   Object ID.
     * @param string $object_type Object type.
     * @param string $view_url    Public URL.
     * @return string
     */
    private static function build_integration_badges( $object_id, $object_type, $view_url ) {
        $badges = array();
        $issue_count = AI_Bot_Tracker::get_issue_count_for_object( $object_id, $object_type );
        if ( $issue_count > 0 ) {
            $issues_url = admin_url( 'admin.php?page=sb2_seo_issues' );
            $badges[] = '<a class="sb-ai-bots-badge" href="' . esc_url( $issues_url ) . '">' . esc_html( sprintf( 
                /* translators: %d: issue count */
                _n(
                    '%d SEO possibility',
                    '%d SEO possibilities',
                    $issue_count,
                    'seo-booster'
                ),
                $issue_count
             ) ) . '</a>';
        }
        $gsc_count = AI_Bot_Tracker::get_gsc_keyword_count( $view_url );
        if ( $gsc_count > 0 ) {
            $gsc_url = admin_url( 'admin.php?page=sb2_gsc' );
            $badges[] = '<a class="sb-ai-bots-badge" href="' . esc_url( $gsc_url ) . '">' . esc_html( sprintf( 
                /* translators: %d: keyword count */
                _n(
                    '%d GSC keyword',
                    '%d GSC keywords',
                    $gsc_count,
                    'seo-booster'
                ),
                $gsc_count
             ) ) . '</a>';
        }
        return implode( ' ', $badges );
    }

    protected function sanitize_orderby( $orderby ) {
        $valid = array(
            'bot_name',
            'bot_purpose',
            'request_path',
            'normalized_url',
            'visits',
            'last_seen',
            'object_type'
        );
        if ( in_array( $orderby, $valid, true ) ) {
            return $orderby;
        }
        return ( 'content' === $this->view ? 'visits' : 'last_seen' );
    }

    protected function sanitize_order( $order ) {
        if ( in_array( strtoupper( $order ), array('ASC', 'DESC'), true ) ) {
            return $order;
        }
        return 'DESC';
    }

    public function prepare_items() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- List table pagination, search, and sort reads only.
        $per_page = 50;
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $paged = ( isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1 );
        $offset = $paged * $per_page - $per_page;
        $search = ( isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '' );
        $filter_bot = ( isset( $_REQUEST['filter_bot'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_bot'] ) ) : '' );
        $filter_purpose = ( isset( $_REQUEST['filter_purpose'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_purpose'] ) ) : '' );
        $filter_object_type = ( isset( $_REQUEST['filter_object_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_object_type'] ) ) : '' );
        $filter_min_visits = ( isset( $_REQUEST['filter_min_visits'] ) ? (int) $_REQUEST['filter_min_visits'] : 1 );
        $orderby = filter_input( INPUT_GET, 'orderby' );
        $orderby = ( !empty( $orderby ) ? sanitize_text_field( $orderby ) : (( 'content' === $this->view ? 'visits' : 'last_seen' )) );
        $orderby = $this->sanitize_orderby( $orderby );
        $order = filter_input( INPUT_GET, 'order' );
        $order = ( !empty( $order ) ? $this->sanitize_order( sanitize_text_field( $order ) ) : 'DESC' );
        $args = array(
            'days'               => $this->filter_days,
            'offset'             => $offset,
            'per_page'           => $per_page,
            'orderby'            => $orderby,
            'order'              => $order,
            'search'             => $search,
            'filter_bot'         => $filter_bot,
            'filter_purpose'     => $filter_purpose,
            'filter_object_type' => $filter_object_type,
            'min_visits'         => max( 1, $filter_min_visits ),
        );
        if ( 'by_bot' === $this->view ) {
            $result = AI_Bot_Tracker::get_bot_hits( $args );
        } elseif ( 'noise' === $this->view ) {
            $result = AI_Bot_Tracker::get_noise_hits( $args );
        } else {
            $result = AI_Bot_Tracker::get_content_hits( $args );
        }
        $this->items = ( isset( $result['items'] ) ? $result['items'] : array() );
        $total_items = ( isset( $result['total'] ) ? (int) $result['total'] : 0 );
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
    }

}
