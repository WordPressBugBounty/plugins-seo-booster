<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'seo-booster' ) );
}

global $wpdb, $seobooster2;

// exit();

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

require __DIR__ . '/inc/SB_GSC_List_Table.php';


	// Create an instance of our package class.
$gsc_list_table = new SB_GSC_List_Table();
	// Fetch, prepare, sort, and filter our data.
$gsc_list_table->prepare_items();
	// Include the view markup.
require __DIR__ . '/views/gsc-pages.php';
