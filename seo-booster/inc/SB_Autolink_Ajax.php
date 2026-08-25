<?php
namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_Autolink_Ajax {
	public static function init() {
		add_action( 'wp_ajax_sb_update_keyword', array( __CLASS__, 'ajax_update_keyword' ) );
		add_action( 'wp_ajax_sb_delete_keyword', array( __CLASS__, 'ajax_delete_keyword' ) );
	}


	public static function ajax_update_keyword() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'add-keyword-nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		global $wpdb;
		$id      = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( $_POST['keyword'] ) : '';
		$url     = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';

		if ( ! $id || ( ! $keyword && ! $url ) ) {
			wp_send_json_error( 'Invalid data' );
		}

		$data   = array();
		$format = array();

		if ( $keyword ) {
			$data['keyword'] = $keyword;
			$format[]        = '%s';
		}

		if ( $url ) {
			$data['url'] = $url;
			$format[]    = '%s';
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'sb2_autolink',
			$data,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);

		if ( false !== $result ) {
			Utils::log( "Keyword or URL updated successfully for ID: $id", 0 );
			Seobooster2::flush_autolink_caches();
			wp_send_json_success();
		}
		Utils::log( "Failed to update keyword or URL for ID: $id", 2 );
		wp_send_json_error( 'Update failed' );
	}

	/**
	 * Delete a single autolink keyword via AJAX.
	 */
	public static function ajax_delete_keyword() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! $id || ! wp_verify_nonce( $nonce, 'sb_delete_kw_' . $id ) ) {
			wp_send_json_error( 'Invalid request' );
		}

		global $wpdb;
		$deleted = $wpdb->delete(
			$wpdb->prefix . 'sb2_autolink',
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( $deleted ) {
			Utils::log( "Deleted autolink keyword ID: $id", 0 );
			Seobooster2::flush_autolink_caches();
			wp_send_json_success();
		}

		Utils::log( "Failed to delete autolink keyword ID: $id", 2 );
		wp_send_json_error( 'Delete failed' );
	}
}
