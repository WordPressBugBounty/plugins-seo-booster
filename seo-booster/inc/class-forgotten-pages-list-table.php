<?php

// don't load directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class SB_Forgotten_List_Table extends WP_List_Table
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
    
    public function get_columns()
    {
        $columns = array(
            'lp'        => _x( 'Landing Page', 'Column label', 'seo-booster' ),
            'lastvisit' => _x( 'Latest SE Visitor', 'Column label', 'seo-booster' ),
            'kws'       => _x( 'Keywords', 'Column label', 'seo-booster' ),
        );
        return $columns;
    }
    
    protected function get_bulk_actions()
    {
        $actions = array(
            'delete' => _x( 'Delete', 'List table bulk action', 'seo-booster' ),
        );
        return $actions;
    }
    
    protected function process_bulk_action()
    {
        // check if user has permission
        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( 'delete' === $this->current_action() ) {
            global  $wpdb ;
            
            if ( isset( $_GET['url'] ) ) {
                $bltable = $wpdb->prefix . 'sb2_bl';
                $count = 0;
                $urlsan = sanitize_text_field( $_GET['url'] );
                if ( is_array( $urlsan ) ) {
                    foreach ( $urlsan as $urlid ) {
                        $wpdb->query( $wpdb->prepare( "DELETE FROM {$bltable} WHERE id=%d limit 1;" ), intval( $urlid) );
                        $count++;
                    }
                }
            }
        
        }
    
    }
    
    protected function get_sortable_columns()
    {
        $sortable_columns = array(
            'lp'        => array( 'lp', false ),
            'lastvisit' => array( 'lastvisit', false ),
        );
        return $sortable_columns;
    }
    
    protected function column_default( $item, $column_name )
    {
        switch ( $column_name ) {
            case 'lastvisit':
                return $item[$column_name];
            case 'kws':
                return $item[$column_name];
            default:
                return print_r( $item, true );
                // Show the whole array for troubleshooting purposes.
        }
    }
    
    /**
     * column_lp.
     *
     * @author	Unknown
     * @since	v0.0.1
     * @version	v1.0.0	Tuesday, November 30th, 2021.
     * @access	protected
     * @param	mixed	$item	
     * @return	mixed
     */
    protected function column_lp( $item ) {
        return sprintf(
            '<a href="%1$s" target="_blank">%2$s</a> <span style="color:silver;">(Visitors:%3$s)</span>',
            esc_url( site_url( $item['lp'] ) ),
            $item['lp'],
            $item['visits']
        );
    }
    
    protected function sanitize_orderby( $orderby )
    {
        $valid_column_names = [ 'lastvisit', 'lp' ];
        if ( in_array( $orderby, $valid_column_names, true ) ) {
            return $orderby;
        }
        return 'lp';
    }
    
    protected function sanitize_order( $order )
    {
        if ( in_array( strtoupper( $order ), [ 'ASC', 'DESC' ], true ) ) {
            return $order;
        }
        return 'ASC';
    }
    
    function prepare_items()
    {
        global  $wpdb ;
        $per_page = 15;
        // todo - screen settings?
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );
        $this->process_bulk_action();
        $kwtable = $wpdb->prefix . 'sb2_kw';
        $paged = ( get_query_var( 'page' ) ? get_query_var( 'page' ) : 1 );
        $offset = $paged * $per_page - $per_page;
        // TODO - optional range - 30, 60, 90 days?
        /*
         */
        $orderby = filter_input( INPUT_GET, 'orderby' );
        $orderby = ( !empty($orderby) ? esc_sql( sanitize_text_field( $orderby ) ) : 'lp' );
        $orderby = $this->sanitize_orderby( $orderby );
        $order = filter_input( INPUT_GET, 'order' );
        $order = ( !empty($order) ? esc_sql( strtoupper( sanitize_text_field( $order ) ) ) : 'ASC' );
        $order = $this->sanitize_order( $order );

        $daquery = "SELECT id,kw,lp,visits,firstvisit,lastvisit,min(firstvisit) FROM {$kwtable} WHERE engine<>'Internal Search' AND visits>1 AND kw<>'#' AND ( (lastvisit < DATE_SUB(NOW(), INTERVAL 30 DAY) ) or (firstvisit < DATE_SUB(NOW(), INTERVAL 30 DAY) ) ) GROUP BY lp ORDER BY {$orderby} {$order} LIMIT {$offset}, {$per_page};";
        // @todo - lav query der henter data på sideniveua, dvs. man kunne have fået besøg af "blue widget" før, men efterfølgende er der blot "#" - det skal der tages højde for.
        $data = $wpdb->get_results( $daquery, ARRAY_A );
        $newarr = array();
        // Parse each
        foreach ( $data as $dat ) {
            $newdat = $dat;
            $lp = $dat['lp'];
            $lastvisit = $newdat['lastvisit'];
            if ( '0000-00-00 00:00:00' == $lastvisit ) {
                $lastvisit = $newdat['firstvisit'];
            }
            $newdat['firstvisit'] = "<span class='ago'>" . human_time_diff( strtotime( $dat['firstvisit'] ) ) . ' ' . __( 'ago', 'seo-booster' ) . "</span><span class='date'>" . $dat['firstvisit'] . '</span>';
            $newdat['lastvisit'] = "<div class='visitdata'><span class='ago'>" . human_time_diff( strtotime( $lastvisit ) ) . ' ' . __( 'ago', 'seo-booster' ) . "</span><span class='date'>" . $lastvisit . '</span></div>';
            $newdat['lastvisit'] .= "<div class='visitdata first'>" . __( 'First visit:', 'seo-booster' ) . " <span class='ago'>" . human_time_diff( strtotime( $dat['firstvisit'] ) ) . ' ' . __( 'ago', 'seo-booster' ) . "</span><span class='date'>" . $dat['firstvisit'] . '</span>';
            $kwlist = $wpdb->get_results( "SELECT kw FROM {$kwtable} WHERE lp='{$lp}' AND kw<>'#' ORDER by visits DESC limit 25;", ARRAY_A );
            $kwstring = '';
            if ( $kwlist ) {
                foreach ( $kwlist as $kw ) {
                    $kwstring .= '<span>' . $kw['kw'] . '</span>';
                }
            }
            $newdat['kws'] = wp_kses( $kwstring, wp_allowed_protocols() );
            $newarr[] = $newdat;
        }
        $data = $newarr;
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var( "SELECT count(*) FROM {$kwtable} WHERE engine<>'Internal Search' AND visits>1 AND kw<>'#' AND lastvisit < DATE_SUB(NOW(), INTERVAL 30 DAY);" );
        $data = array_slice( $data, ($current_page - 1) * $per_page, $per_page );
        $this->items = $data;
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );
    }

}