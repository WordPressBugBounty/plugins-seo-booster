<?php

// don't load directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class SB_Backlinks_List_Table extends WP_List_Table
{
    public function __construct()
    {
        // Set parent defaults.
        parent::__construct( array(
            'singular' => 'url',
            'plural'   => 'urls',
            'ajax'     => false,
        ) );
    }
    
    public function single_row( $item )
    {
        global  $post ;
        $classnames = 'backlink';
        // Looking for backlinks that might be search related
        if ( strpos( $item['ref'], '?q=' ) !== false || strpos( $item['ref'], '&q=' ) !== false || strpos( $item['ref'], 'query=' ) !== false || strpos( $item['ref'], 'qs=' ) !== false || strpos( $item['ref'], 'search_term' ) !== false || strpos( $item['ref'], 'search_string' ) !== false || strpos( $item['ref'], 'SearchQuery' ) !== false || strpos( $item['ref'], '?search=' ) !== false || strpos( $item['ref'], 'search.' ) !== false || strpos( $item['ref'], '.search' ) !== false || strpos( $item['ref'], '/search/' ) !== false ) {
            $classnames .= ' sesuspect';
        }
        echo  "<tr id='backlink-" . esc_attr( $item['id'] ) . "' class='". esc_attr( $classnames ) ."'>" ;
        WP_List_Table::single_row_columns( $item );
        echo  "</tr>\n" ;
    }
    
    public function get_columns()
    {
        $columns['cb'] = '<input type="checkbox" />';
        $columns['ref'] = _x( 'Link From', 'Column label', 'seo-booster' );
        $columns['lp'] = _x( 'Landing Page', 'Column label', 'seo-booster' );
        $columns['visits'] = _x( 'Visitors', 'Column label', 'seo-booster' );
        $columns['firstvisit'] = _x( 'First Visit', 'Column label', 'seo-booster' );
        return $columns;
    }
    
    protected function get_sortable_columns()
    {
        $sortable_columns = array(
            'verified'   => array( 'verified', false ),
            'ref'        => array( 'ref', false ),
            'lp'         => array( 'lp', false ),
            'visits'     => array( 'visits', false ),
            'lastcheck'  => array( 'lastcheck', false ),
            'anchor'     => array( 'anchor', false ),
            'firstvisit' => array( 'firstvisit', false ),
        );
        return $sortable_columns;
    }
    
    protected function column_verified( $item )
    {
        if ( '1' === $item['verified'] ) {
            return '<span class="dashicons dashicons-yes"></span> ' . __( 'Verified', 'seo-booster' );
        }
        if ( '0' === $item['verified'] ) {
            return '';
        }
        if ( '-1' === $item['verified'] ) {
            return '<span class="dashicons dashicons-no"></span> ' . __( 'Not found', 'seo-booster' );
        }
    }
    
    protected function column_anchor( $item )
    {
        $returntext = $item['anchor'];
        if ( '' !== $item['anchor'] && '1' === $item['nflw'] ) {
            $returntext .= '<span class="nofollow">' . __( 'Nofollow', 'seo-booster' ) . '</span>';
        }
        return $returntext;
    }
    
    protected function column_lastcheck( $item )
    {
        if ( '0000-00-00 00:00:00' === $item['lastcheck'] ) {
            return __( 'Not checked yet', 'seo-booster' );
        }
        $return = '<span class="date">' . $item['lastcheck'] . '</span>';
        if ( $item['errorcount'] > 0 ) {
            $return .= "<span class='errorcount'>" . sprintf( _n(
                '%s failed attempt',
                '%s failed attempts',
                $item['errorcount'],
                'seo-booster'
            ), number_format_i18n( $item['errorcount'] ) ) . '</span>';
        }
        return $return;
    }
    
    protected function column_default( $item, $column_name )
    {
        global  $seobooster2 ;
        switch ( $column_name ) {
            case 'ref':
                $img = '';
                include 'engine-meta.php';
                $domain = $item['domain'];
                $domain = explode( '.', $domain );
                $number = count( $domain );
                $tld = $domain[$number - 1];
                // Check if we have a match
                
                if ( isset( $engine_meta[$tld] ) ) {
                    $datld = $tld;
                } else {
                    $secondld = $domain[$number - 2];
                    $datld = $secondld . '.' . $tld;
                }
                
                
                if ( isset( $engine_meta[$datld] ) ) {
                    $imgurl = SEOBOOSTER_PLUGINURL . '/images/flags/' . $engine_meta[$datld]['flag'];
                    $imgalt = $engine_meta[$datld]['label'];
                } else {
                    $imgurl = SEOBOOSTER_PLUGINURL . '/images/flags/Unknown.png';
                    $imgalt = __( 'Unknown', 'seo-booster' );
                }
                
                $img = '<img src="' . esc_url( $imgurl ) . '" class="flag" alt="' . esc_attr ( $imgalt ) . '">';
                $imgurl = add_query_arg( array(
                    'w' => '250',
                ), '//s.wordpress.com/mshots/v1/' . urlencode( $item['ref'] ) );
                
                $screenshot = "<img data-original='". esc_url( $imgurl ). "' src='" . esc_url( SEOBOOSTER_PLUGINURL . 'images/blplaceholder.png') ."' width='125' height='93' class='lazy mshot'>";
                return $img . '<a href="' . esc_attr( $item['ref'] ) . '" target="_blank">' . $seobooster2->truncatestring( $seobooster2->remove_http( $item['ref'] ), 35 ) . '</br>' . $screenshot . '</a>';
            case 'httpstatus':
                return esc_attr( $item[$column_name] );
            case 'lp':
                return esc_attr( $seobooster2->truncatestring( $seobooster2->remove_http( $item['lp'] ), 55 ) );
            case 'visits':
                return esc_attr( $item[$column_name] );
            case 'firstvisit':
                return esc_attr( $item[$column_name] );
            default:
                return '';
        }
    }
    
    protected function column_cb( $item )
    {
        return sprintf( '<input type="checkbox" name="%1$s[]" value="%2$s" />', $this->_args['singular'], esc_attr( $item['id'] ) );
    }
    
    protected function get_bulk_actions()
    {
        $actions = array(
            'delete' => _x( 'Delete', 'List table bulk action', 'seo-booster' ),
        );
        return $actions;
    }
    
    /**
     * process_bulk_action.
     *
     * @author	Lars Koudal
     * @since	v0.0.1
     * @version	v1.0.0	Tuesday, December 14th, 2021.
     * @access	protected
     * @return	void
     */
    protected function process_bulk_action()
    {
        
        if ( 'delete' === $this->current_action() ) {
            global  $wpdb ;
            
            if ( isset( $_GET['url'] ) ) {
                $bltable = $wpdb->prefix . 'sb2_bl';
                $count = 0;
                $urlsan = sanitize_text_field( $_GET['url'] );
                if ( is_array( $urlsan ) ) {
                    foreach ( $urlsan as $urlid ) {
                        $wpdb->delete( $bltable, array(
                            'id' => intval( $urlid ),
                        ), array( '%d' ) );
                        $count++;
                    }
                }
            }
        
        }
    
    }
    
    protected function sanitize_orderby( $orderby )
    {
        $valid_column_names = [
            'verified',
            'ref',
            'lp',
            'visits',
            'firstvisit',
            'anchor',
            'lastcheck'
        ];
        if ( in_array( $orderby, $valid_column_names, true ) ) {
            return $orderby;
        }
        return 'firstvisit';
    }
    
    protected function sanitize_order( $order )
    {
        if ( in_array( strtoupper( $order ), [ 'ASC', 'DESC' ], true ) ) {
            return $order;
        }
        return 'ASC';
    }
    
    /**
     * prepare_items.
     *
     * @author	Unknown
     * @since	v0.0.1
     * @version	v1.0.0	Tuesday, November 30th, 2021.
     * @return	void
     */
    function prepare_items()
    {
        global  $wpdb ;
        $per_page = 35;
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );
        $this->process_bulk_action();
        $paged = ( isset( $_GET['paged'] ) ? sanitize_text_field( $_GET['paged'] ) : 1 );
        $offset = $paged * $per_page - $per_page;
        $bltable = $wpdb->prefix . 'sb2_bl';
        $search = ( isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : false );
        
        if ( $search ) {
            $do_search = $wpdb->prepare(
                ' AND (lp LIKE %s OR ref LIKE %s OR anchor LIKE %s ) ',
                '%' . $wpdb->esc_like( $search ) . '%',
                '%' . $wpdb->esc_like( $search ) . '%',
                '%' . $wpdb->esc_like( $search ) . '%'
            );
        } else {
            $do_search = '';
        }
        
        $orderby = filter_input( INPUT_GET, 'orderby' );
        $orderby = ( !empty($orderby) ? esc_sql( sanitize_text_field( $orderby ) ) : 'firstvisit' );
        $orderby = $this->sanitize_orderby( $orderby );
        $order = filter_input( INPUT_GET, 'order' );
        $order = ( !empty($order) ? esc_sql( strtoupper( sanitize_text_field( $order ) ) ) : 'ASC' );
        $order = $this->sanitize_order( $order );
        $daquery = "SELECT * FROM {$wpdb->prefix}sb2_bl WHERE 1 = 1 {$do_search} ORDER BY {$orderby} {$order} LIMIT {$offset}, {$per_page};";
        $data = $wpdb->get_results( $daquery, ARRAY_A );
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var( "SELECT count(lp) FROM {$wpdb->prefix}sb2_bl WHERE 1=1 {$do_search};" );
        $this->items = $data;
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );
    }
    
    /**
     * usort_reorder.
     *
     * @author	Unknown
     * @since	v0.0.1
     * @version	v1.0.0	Tuesday, November 30th, 2021.
     * @access	protected
     * @param	mixed	$a	
     * @param	mixed	$b	
     * @return	mixed
     */
    protected function usort_reorder( $a, $b )
    {
        $orderby = ( !empty($_REQUEST['orderby']) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'firstvisit' );
        $order = ( !empty($_REQUEST['order']) ? sanitize_text_field( $_REQUEST['order'] ) : 'asc' );
        $result = strcmp( $a[$orderby], $b[$orderby] );
        return ( 'asc' === $order ? $result : -$result );
    }

}