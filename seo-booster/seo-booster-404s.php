<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'update_plugins' ) ) {
	wp_die( 'You are not allowed to update plugins on this blog' );
}

global $wpdb, $seobooster2;


if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

require __DIR__ . '/inc/class-404-pages-list-table.php';


	// Create an instance of our package class.
$fof_list_table = new SB_FOF_List_Table();
	// Fetch, prepare, sort, and filter our data.
$fof_list_table->prepare_items();
	// Include the view markup.
require __DIR__ . '/views/404-pages.php';
