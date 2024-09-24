<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! current_user_can( 'update_plugins' ) ) {
	wp_die( 'You are not allowed to update plugins on this blog.' );
}
global  $seobooster2, $wpdb;

$kwdttable = $wpdb->prefix . 'sb2_kwdt';
if ( isset( $_COOKIE['sbp_kw_length'] ) ) {
	$the_length = sanitize_key( $_COOKIE['sbp_kw_length'] );
}

if ( isset( $_COOKIE['sbp_the_showkws'] ) ) {
	$the_showkws = sanitize_key( $_COOKIE['sbp_the_showkws'] );
} else {
	$the_showkws = 'all';
}


if ( isset( $_COOKIE['sbp_kw_hideinternal'] ) ) {
	$the_hideinternal = sanitize_key( $_COOKIE['sbp_kw_hideinternal'] );
} else {
	$the_hideinternal = '';
}

?>
<div class="wrap">
		<?php
		require_once SEOBOOSTER_PLUGINPATH . 'inc/adminheader.php';
		// Contains general info
		?>
<h1><?php esc_html_e( 'Incoming keywords', 'seo-booster' ); ?> - SEO Booster v. <?php echo esc_html( SEOBOOSTER_VERSION ); ?></h1>
	<div class="ajax-loading">
	<?php
	esc_html_e( 'Loading...', 'seo-booster' );
	?>
	</div>
	<?php
	if ( isset( $_COOKIE['sbp_kw_length'] ) ) {
		$the_length = sanitize_key( $_COOKIE['sbp_kw_length'] );
	}
	if ( isset( $_COOKIE['sbp_the_showkws'] ) ) {
		$the_showkws = sanitize_key( $_COOKIE['sbp_the_showkws'] );
	}
	if ( isset( $_COOKIE['sbp_kw_hideinternal'] ) ) {
		$the_hideinternal = sanitize_key( $_COOKIE['sbp_kw_hideinternal'] );
	}
	?>

	<div id="filtering">

		<h3>
		<?php
		esc_html_e( 'Filtering options', 'seo-booster' );
		?>
		</h3>
		<input type="checkbox" name="hideinternal" id="hideinternal"
		<?php
		if ( $the_hideinternal ) {
			echo 'checked="checked"';
		}
		?>
		><label for="hideinternal">
		<?php
		esc_html_e( 'Hide internal searches', 'seo-booster' );
		?>
		</label>

		<form role="form" id="keywordsfilter">
			<label class="radio-inline"><input type="radio" name="showkws" value="all"
			<?php
			if ( 'all' === $the_showkws ) {
				echo ' checked';
			}
			?>
			>
			<?php
			esc_html_e( 'Show all', 'seo-booster' );
			?>
			</label>
			<label class="radio-inline"><input type="radio" name="showkws" value="knowns"
			<?php
			if ( 'knowns' === $the_showkws ) {
				echo ' checked';
			}
			?>
			>
			<?php
			esc_html_e( 'Show only known keywords', 'seo-booster' );
			?>
			</label>
			<label class="radio-inline"><input type="radio" name="showkws" value="unknowns"
			<?php
			if ( 'unknowns' === $the_showkws ) {
				echo ' checked';
			}
			?>
			>
			<?php
			esc_html_e( 'Show only unknown traffic', 'seo-booster' );
			?>
			</label>
		</form>
	</div><!-- #filtering -->

	<div id="datatable-target"></div>
	<table cellpadding="0" cellspacing="0" class="wp-list-table widefat" id="datatable">
		<thead>
			<tr>
				<th scope="col" role="columnheader" class="header">
				<?php
				esc_html_e( 'Keyword', 'seo-booster' );
				?>
				</th>
				<th scope="col" role="columnheader" class="header">
				<?php
				esc_html_e( 'Landing Page', 'seo-booster' );
				?>
				</th>
				<th scope="col" role="columnheader" class="header">
				<?php
				esc_html_e( 'Search Engine', 'seo-booster' );
				?>
				</th>
				<th scope="col" role="columnheader" class="manage-column header">
				<?php
				esc_html_e( 'Visits', 'seo-booster' );
				?>
				</th>
				<th scope="col" role="columnheader" class="header">
				<?php
				esc_html_e( 'First Visit', 'seo-booster' );
				?>
				</th>
				<th scope="col" role="columnheader" class="header">
				<?php
				esc_html_e( 'Latest Visit', 'seo-booster' );
				?>
				</th>
			</tr>
		</thead>
		<tbody>

		</tbody>
		<tfoot>
			<tr>
				<th scope="col">
				<?php
				esc_html_e( 'Keyword', 'seo-booster' );
				?>
				</th>
				<th scope="col">
				<?php
				esc_html_e( 'Landing Page', 'seo-booster' );
				?>
				</th>
				<th scope="col">
				<?php
				esc_html_e( 'Search Engine', 'seo-booster' );
				?>
				</th>
				<th scope="col">
				<?php
				esc_html_e( 'Visits', 'seo-booster' );
				?>
				</th>
				<th scope="col">
				<?php
				esc_html_e( 'First Visit', 'seo-booster' );
				?>
				</th>
				<th scope="col">
				<?php
				esc_html_e( 'Latest Visit', 'seo-booster' );
				?>
				</th>
			</tr>
		</tfoot>
	</table>
	<hr>
	<?php

	$engines     = $wpdb->get_results( $wpdb->prepare( "SELECT engine, COUNT(*) as cnt, SUM(visits) as visits FROM {$wpdb->prefix}sb2_kw where `ig`='0' AND `kw`<>'#' AND engine <> %s GROUP BY %s ORDER BY `visits` DESC limit 25;", 'Internal Search', 'engine' ), ARRAY_A );
	$enginecount = count( $engines );

	if ( $enginecount > 0 ) {
		// todo - fjern (?)
		$position = 0;
		?>
		<h2>
		<?php
		esc_html_e( 'Top Search Engines', 'seo-booster' );
		?>
	</h2>
		<table class="wp-list-table widefat">
			<thead>
				<tr>
					<th scope="col">
					<?php
					esc_html_e( 'Position', 'seo-booster' );
					?>
	</th>
					<th scope="col">
					<?php
					esc_html_e( 'Search Engine', 'seo-booster' );
					?>
	</th>
					<th scope="col">
					<?php
					esc_html_e( 'Different Keywords', 'seo-booster' );
					?>
	</th>
					<th scope="col">
					<?php
					esc_html_e( 'Visitors', 'seo-booster' );
					?>
	</th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $engines as $eng ) {
						++$position;
					?>
					<tr>
						<td>
							<?php
							echo esc_attr( $position );
							?>
						</td>
						<td>
						<?php
						echo esc_html( str_replace( 'www.', '', $eng['engine'] ) );
						?>
						</td><td>
						<?php
						echo esc_attr( number_format_i18n( $eng['cnt'] ) );
						?>
						</td><td>
						<?php
						echo esc_attr( number_format_i18n( $eng['visits'] ) );
						?>
						</td></tr>
					<?php
				}
				?>
			</tbody>
			<tfoot>
				<tr>
					<th scope="col">
					<?php
					esc_html_e( 'Position', 'seo-booster' );
					?>
	</th>
					<th scope="col">
					<?php
					esc_html_e( 'Search Engine', 'seo-booster' );
					?>
	</th>
					<th scope="col">
					<?php
					esc_html_e( 'Different Keywords', 'seo-booster' );
					?>
	</th>
					<th scope="col">
					<?php
					esc_html_e( 'Visitors', 'seo-booster' );
					?>
	</th>
				</tr>
			</tfoot>
		</table>
			<?php
	}

	?>
</div>
