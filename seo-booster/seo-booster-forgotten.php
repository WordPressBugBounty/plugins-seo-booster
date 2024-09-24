<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'update_plugins' ) ) {
	wp_die( 'You are not allowed to update plugins on this blog.' );
}

global $wpdb, $seobooster2;
$kwtable = $wpdb->prefix . 'sb2_kw';




if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

require __DIR__ . '/inc/class-forgotten-pages-list-table.php';


	// Create an instance of our package class.
$test_list_table = new SB_Forgotten_List_Table();
	// Fetch, prepare, sort, and filter our data.
$test_list_table->prepare_items();
	// Include the view markup.
require __DIR__ . '/views/forgotten-pages.php';
