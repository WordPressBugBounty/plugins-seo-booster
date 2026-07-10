<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'seo-booster' ) );
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

$view        = isset( $_REQUEST['view'] ) ? sanitize_key( wp_unslash( $_REQUEST['view'] ) ) : 'content';
$filter_days = isset( $_REQUEST['filter_days'] ) ? (int) $_REQUEST['filter_days'] : 30;

$bulk_action = '';
if ( isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
	$bulk_action = sanitize_key( wp_unslash( $_REQUEST['action'] ) );
} elseif ( isset( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) {
	$bulk_action = sanitize_key( wp_unslash( $_REQUEST['action2'] ) );
}

if ( 'purge_noise' === $bulk_action && check_admin_referer( 'bulk-ai_bot_hits' ) ) {
	$deleted = AI_Bot_Tracker::purge_noise_data();
	add_settings_error(
		'sb_ai_bots',
		'purge_noise',
		sprintf(
			/* translators: %d: number of deleted rows */
			__( 'Removed %d noise rows from AI bot tracking.', 'seo-booster' ),
			$deleted
		),
		'updated'
	);
}

$ai_bots_list_table = null;

if ( 'referrals' === $view ) {
	require __DIR__ . '/inc/SB_AI_Referrals_List_Table.php';
	$ai_bots_list_table = new SB_AI_Referrals_List_Table();
} else {
	require __DIR__ . '/inc/SB_AI_Bots_List_Table.php';
	$ai_bots_list_table = new SB_AI_Bots_List_Table();
	$ai_bots_list_table->set_view( $view );
}

$ai_bots_list_table->set_filter_days( $filter_days );
$ai_bots_list_table->prepare_items();

require __DIR__ . '/views/ai-bots.php';
