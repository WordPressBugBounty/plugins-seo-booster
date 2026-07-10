<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX handlers for the AI Bots admin report.
 */
class SB_AI_Bots_Ajax {

	/**
	 * Register AJAX actions.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_sb_ai_bots_chart_data', array( __CLASS__, 'ajax_chart_data' ) );
		add_action( 'wp_ajax_sb_ai_bots_object_breakdown', array( __CLASS__, 'ajax_object_breakdown' ) );
	}

	/**
	 * Chart data for visits over time and purpose breakdown.
	 *
	 * @return void
	 */
	public static function ajax_chart_data() {
		check_ajax_referer( 'sb_ai_bots_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'seo-booster' ) ), 403 );
		}

		$days = isset( $_POST['days'] ) ? (int) $_POST['days'] : 30;
		if ( ! in_array( $days, array( 7, 30, 90 ), true ) ) {
			$days = 30;
		}

		$daily   = AI_Bot_Tracker::get_visits_by_day( $days );
		$purpose = AI_Bot_Tracker::get_purpose_breakdown( $days );

		$labels         = array();
		$content_series = array();
		$noise_series   = array();

		foreach ( $daily as $row ) {
			$labels[]         = isset( $row['hit_date'] ) ? $row['hit_date'] : '';
			$content_series[] = isset( $row['content_visits'] ) ? (int) $row['content_visits'] : 0;
			$noise_series[]   = isset( $row['noise_visits'] ) ? (int) $row['noise_visits'] : 0;
		}

		$ref_daily   = AI_Referral_Tracker::get_visits_by_day( $days );
		$ref_labels  = array();
		$ref_series  = array();
		foreach ( $ref_daily as $row ) {
			$ref_labels[] = isset( $row['hit_date'] ) ? $row['hit_date'] : '';
			$ref_series[] = isset( $row['visits'] ) ? (int) $row['visits'] : 0;
		}

		wp_send_json_success(
			array(
				'daily'   => array(
					'labels'  => $labels,
					'content' => $content_series,
					'noise'   => $noise_series,
				),
				'referrals' => array(
					'labels' => $ref_labels,
					'visits' => $ref_series,
				),
				'purpose' => array(
					'research' => isset( $purpose['research'] ) ? (int) $purpose['research'] : 0,
					'citation' => isset( $purpose['citation'] ) ? (int) $purpose['citation'] : 0,
				),
			)
		);
	}

	/**
	 * Per-bot breakdown for a content object row.
	 *
	 * @return void
	 */
	public static function ajax_object_breakdown() {
		check_ajax_referer( 'sb_ai_bots_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'seo-booster' ) ), 403 );
		}

		$object_id   = isset( $_POST['object_id'] ) ? (int) $_POST['object_id'] : 0;
		$object_type = isset( $_POST['object_type'] ) ? sanitize_key( wp_unslash( $_POST['object_type'] ) ) : '';
		$days        = isset( $_POST['days'] ) ? (int) $_POST['days'] : 30;

		if ( $object_id <= 0 || empty( $object_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid object.', 'seo-booster' ) ) );
		}

		$rows  = AI_Bot_Tracker::get_hits_for_object( $object_id, $object_type, $days );
		$html  = '<table class="widefat striped"><thead><tr>';
		$html .= '<th>' . esc_html__( 'Bot', 'seo-booster' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Purpose', 'seo-booster' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Content visits', 'seo-booster' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Redirect visits', 'seo-booster' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Status', 'seo-booster' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Last seen', 'seo-booster' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		if ( empty( $rows ) ) {
			$html .= '<tr><td colspan="6">' . esc_html__( 'No visits found.', 'seo-booster' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$content_visits  = isset( $row['content_visits'] ) ? (int) $row['content_visits'] : (int) ( $row['visits'] ?? 0 );
				$redirect_visits = isset( $row['redirect_visits'] ) ? (int) $row['redirect_visits'] : 0;
				$status_code     = isset( $row['status_code'] ) ? (int) $row['status_code'] : 0;
				$status_label    = $status_code > 0 ? esc_html( (string) $status_code ) : '&mdash;';

				$html .= '<tr>';
				$html .= '<td>' . esc_html( $row['bot_name'] ) . '</td>';
				$html .= '<td>' . esc_html( AI_Bot_Tracker::get_purpose_label( $row['bot_purpose'] ) ) . '</td>';
				$html .= '<td>' . esc_html( number_format_i18n( $content_visits ) ) . '</td>';
				$html .= '<td>' . esc_html( number_format_i18n( $redirect_visits ) ) . '</td>';
				$html .= '<td>' . $status_label . '</td>';
				$html .= '<td>' . esc_html( $row['last_seen'] ) . '</td>';
				$html .= '</tr>';
			}
		}

		$html .= '</tbody></table>';

		wp_send_json_success( array( 'html' => $html ) );
	}
}
