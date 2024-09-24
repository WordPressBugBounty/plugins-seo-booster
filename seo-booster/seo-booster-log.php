<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
				<div class="sbpheader">

		<?php
		require_once SEOBOOSTER_PLUGINPATH . 'inc/adminheader.php';
		// Contains general info
		$version = SEOBOOSTER_VERSION;
		?>
<h1><?php esc_html_e( 'The log', 'seo-booster' ); ?> - SEO Booster v.<?php echo esc_attr( $version ); ?></h1>
		</div>
	<div class="innercont">
		<?php
		global  $wpdb, $seobooster2;
		$loglimit = 2000;
		$logtable = $wpdb->prefix . 'sb2_log';
		if ( ! empty( $_POST ) && check_admin_referer( 'reset_log', 'seobooster2_nonce' ) ) {

			if ( isset( $_POST['sb2_log_action'] ) && 'resetlog' === sanitize_text_field( $_POST['sb2_log_action'] ) ) {
				$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}sb2_log;" );
				$seobooster2::log( __( 'Log emptied manually', 'seo-booster' ) );
			}
		}
		?>
		<div id="log-pointer-target"></div>
		<?php
		$logs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sb2_log ORDER BY `logtime` DESC LIMIT %d;", $loglimit ), ARRAY_A );

		if ( $logs ) {
			$time = time();

			?>

			<table class="wp-list-table widefat logtable">
				<thead>
					<tr>
						<th scope="shortcol" class="shortcol">
						<?php
						esc_html_e( 'Time ago', 'seo-booster' );
						?>
	</th>
						<th scope="col">
						<?php
						esc_html_e( 'Event', 'seo-booster' );
						?>
	</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $logs as $log ) {
						$extraclass = '';
						if ( '0' === $log['prio'] ) {
							$extraclass = ' muted';
						}
						if ( '2' === $log['prio'] ) {
							$extraclass = ' error';
						}
						if ( '3' === $log['prio'] ) {
							$extraclass = ' warning';
						}
						if ( '5' === $log['prio'] ) {
							$extraclass = ' info';
						}
						if ( '10' === $log['prio'] ) {
							$extraclass = ' success';
						}

						echo "<tr><td class='shortcol prio-" . esc_attr( $log['prio'] ) . esc_attr( $extraclass ) . " '>" . esc_html( human_time_diff( strtotime( $log['logtime'] ), $time ) ) . "</td><td class='prio-" . esc_attr( $log['prio'] ) . esc_attr( $extraclass ) . "'>" . wp_kses( $log['log'], wp_kses_allowed_html() ) . '</td></tr>';
					}
					?>

				</tbody>
				<tfoot>
					<tr>
						<th scope="shortcol" class="shortcol">
						<?php
						esc_html_e( 'Time ago', 'seo-booster' );
						?>
	</th>
						<th scope="col">
						<?php
						esc_html_e( 'Event', 'seo-booster' );
						?>
	</th>
					</tr>
				</tfoot>
			</table>
			<form method="post">
				<input type="hidden" name="sb2_log_action" value="resetlog">
				<?php
				wp_nonce_field( 'reset_log', 'seobooster2_nonce' );
				submit_button( __( 'Reset Log', 'seo-booster' ), 'secondary' );
				?>
			</form>


			<?php
		} else {
			?>
	<p class="text-info">
			<?php
			esc_html_e( 'No activity logged yet.', 'seo-booster' );
			?>
	</p>
			<?php
		}

		?>

</div><!-- .innercont -->
</div>
