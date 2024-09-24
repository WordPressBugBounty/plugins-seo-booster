<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'update_plugins' ) ) {
	wp_die( 'You are not allowed to update plugins on this blog.' );
}

global $seobooster2, $wpdb;

$bltable = $wpdb->prefix . 'sb2_bl';



global $wpdb, $seobooster2;


if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

require __DIR__ . '/inc/class-sb-backlink-pages-list-table.php';


$backlink_pages_list_table = new SB_Backlinks_List_Table();

$backlink_pages_list_table->prepare_items();

require __DIR__ . '/views/backlink-pages.php';
