<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! current_user_can( 'update_plugins' ) ) {
	wp_die( 'You are not allowed to update plugins on this blog.' );
}
global  $wpdb, $seobooster_fs;
$foftable = $wpdb->prefix . 'sb2_404';

?>
<div class="wrap">
	<?php
	global  $seobooster_fs;
	// todo
	$kws_google         = $wpdb->get_var( "SELECT count(*) as kws FROM {$wpdb->prefix}sb2_kw WHERE `kw` NOT LIKE '#' AND `engine` LIKE '%google%';" );
	$kws_not_google     = $wpdb->get_var( "SELECT count(*) as kws FROM {$wpdb->prefix}sb2_kw WHERE `kw` NOT LIKE '#' AND `engine` NOT LIKE '%google%' AND `engine` NOT LIKE 'Internal Search'" );
	$kws_total          = $kws_google + $kws_not_google;
	$traffic_google     = $wpdb->get_var( "SELECT sum(visits) FROM {$wpdb->prefix}sb2_kw WHERE `engine` LIKE '%google.%';" );
	$traffic_not_google = $wpdb->get_var( "SELECT sum(visits) FROM {$wpdb->prefix}sb2_kw WHERE `engine` NOT LIKE '%google.%';" );
	$traffic_total      = $traffic_google + $traffic_not_google;
	$lps_google         = $wpdb->get_var( "SELECT count(DISTINCT(lp)) FROM {$wpdb->prefix}sb2_kw WHERE `engine` LIKE '%google.%';" );
	$lps_not_google     = $wpdb->get_var( "SELECT count(DISTINCT(lp)) FROM {$wpdb->prefix}sb2_kw WHERE `engine` NOT LIKE '%google.%';" );
	$over90daysold      = $wpdb->get_var( "SELECT COUNT(*) AS cnt FROM {$wpdb->prefix}sb2_kw pm WHERE lastvisit < DATE_SUB(NOW(), INTERVAL 90 DAY)" );
	$known_keywords     = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sb2_kw WHERE `kw` <> '#' AND `engine` NOT LIKE 'Internal Search';" );
	require_once SEOBOOSTER_PLUGINPATH . 'inc/adminheader.php';
	// Contains general info
	?>
	<h1><?php esc_html_e( 'Dashboard', 'seo-booster' ); ?> - SEO Booster v. <?php echo esc_html( SEOBOOSTER_VERSION ); ?></h1>
	<?php

	global  $wpdb;
	$dbliste = array(
		$wpdb->prefix . 'sb2_autolink',
		$wpdb->prefix . 'sb2_bl',
		$wpdb->prefix . 'sb2_kwdt',
		$wpdb->prefix . 'sb2_crawl',
		$wpdb->prefix . 'sb2_urls',
		$wpdb->prefix . 'sb2_urls_meta',
		$wpdb->prefix . 'sb2_404',
		$wpdb->prefix . 'sb2_log',
		$wpdb->prefix . 'sb2_kw',
	);
	$missing = '';
	foreach ( $dbliste as $dbt ) {
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $dbt ) ) !== $dbt ) {
			// translators:
			$missing .= '<p>' . sprintf( __( 'Database table %1$s is missing.', 'seo-booster' ), '<code>' . $dbt . '</code>' ) . '</p>';
		}
	}
	$seobooster_db_version = get_option( 'SEOBOOSTER_INSTALLED_DB_VERSION', '1.0' );
	// latest update
	if ( version_compare( $seobooster_db_version, SEOBOOSTER_DB_VERSION ) < 0 ) {
		// translators:
		$missing .= '<p>' . sprintf( __( 'Database out of date %1$s vs. current %2$s', 'seo-booster' ), $seobooster_db_version, SEOBOOSTER_DB_VERSION ) . '</p>';
	}

	if ( $missing ) {
		$allowed_html = wp_kses_allowed_html( 'post' );
		?>
			<div class="notice notice-error">
				<h3>
				<?php
				esc_html_e( 'Database tables needs updating', 'seo-booster' );
				?>
	</h3>
			<?php
			echo wp_kses( $missing, $allowed_html );
		//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>

			<form id="fixdatabase" method="post">
				<input type="hidden" name="page" value="<?php echo esc_attr( sanitize_text_field( $_REQUEST['page'] ) ); ?>" />
				<input type="hidden" name="action" value="sbp_fixdatabasetables" />
				<input type="hidden" name="_wpnonce" value="
				<?php
				echo esc_attr( wp_create_nonce( 'fixdbtables' ) );
				?>
				">
			<?php
			submit_button(
				'Click here to fix',
				'primary',
				'updatedb',
				true
			);
			?>
			</form>

		</div>
			<?php
	}

	?>
	<div id="welcome-panel" class="new-welcome-panel clearfix clear">
		<div id="inner-welcome">
			<div class="welcome-panel-content">
				<div class="wp-columns">
					<div class="welcome-panel-column1">
						<img src="
						<?php
						echo esc_url( plugin_dir_url( __FILE__ ) . 'images/seoboosterlogo.png' );
						?>
						" height="35" width="150" class="seoboosterlogo" alt="SEO Booster">

						<?php
						require 'inc/search_engines.php';

						if ( $sengine ) {
							$secount = count( $sengine );
							?>
							<p class="lead">
								<?php
								// translators:
								printf( esc_html__( 'Tracking visitors from %1$s keyword sources.', 'seo-booster' ), '<span>' . esc_attr( number_format_i18n( $secount ) ) . '</span>' );
								?>
							</p>
							<?php
						}

						?>
						<p class="lead">
						<?php
						esc_html_e( 'Uncluttered view of what content brings traffic.', 'seo-booster' );
						?>
						</p>

						<?php
						$show_welcome_text = true;
						$engines_arr       = $wpdb->get_results( "SELECT engine, sum(visits) as visits from {$wpdb->prefix}sb2_kw WHERE `engine` NOT LIKE 'Internal Search' AND `engine` NOT LIKE '%google.%' GROUP BY engine ORDER BY visits DESC;" );
						$totalengines      = count( $engines_arr ) + 1;

						if ( $known_keywords > 2 ) {
							$show_welcome_text = false;

							if ( $engines_arr ) {
								echo '<h3>' . esc_html( __( 'Quick Overview', 'seo-booster' ) ) . '</h3>';
								echo '<p class="quickie">' . esc_html( sprintf( __( 'Since installation SEO BOOSTER has recorded <span>%1$s</span> keywords found from visitors and <span>%2$s</span> sources.', 'seo-booster' ), number_format_i18n( $known_keywords ), number_format_i18n( $totalengines ) ) ) . '</p>';
							}

							$latestlimit = 10;
							$latestkws   = $wpdb->get_results( $wpdb->prepare( "SELECT kw, engine, firstvisit FROM {$wpdb->prefix}sb2_kw WHERE `engine` NOT LIKE 'Internal Search' AND `kw`<>%s GROUP BY kw ORDER BY visits ASC LIMIT {$latestlimit};", array( '#' ) ) );

							if ( $latestkws ) {
								echo '<p>' . esc_html( __( 'Latest detected keywords:', 'seo-booster' ) ) . '</p>';
								echo '<div class="kwcontainer">';
								foreach ( $latestkws as $lkw ) {
									$engine = $lkw->engine;
									$engine = str_replace( 'www.', '', $engine );
									$kw     = str_replace( '  ', ' ', $lkw->kw );
									echo '<div class="kws">' . esc_html( $kw ) . '</div>';
								}
								echo '</div>';
								echo "<div class='clearfix'></div>";
							}
						}

						$totalbls = $wpdb->get_var( "SELECT count(*) FROM {$wpdb->prefix}sb2_bl;" );

						if ( $totalbls > 0 ) {
							$show_welcome_text = false;
							// translators:
							echo wp_kses( '<p class="quickie">' . sprintf( __( 'Found <span>%s</span>  external links from visitors.', 'seo-booster' ), number_format_i18n( $totalbls ), number_format_i18n( $totalengines ) ) . '</p>', wp_kses_allowed_html() );
						}

						$totalfofs = $wpdb->get_var( "SELECT count(*) FROM {$wpdb->prefix}sb2_404;" );

						if ( $totalfofs > 0 ) {
							$show_welcome_text = false;
							// translators: %s - number of 404 errors - pages and links not found
							echo esc_html( '<p class="quickie">' . sprintf( __( 'Detected <span>%s</span> not found pages and links.', 'seo-booster' ), number_format_i18n( $totalfofs ) ) . '</p>' );
						}


						if ( $show_welcome_text ) {
							?>
							<h3>
							<?php
							esc_html_e( 'Seems a bit boring here?', 'seo-booster' );
							?>
	</h3>

							<p class="lead">
							<?php
							esc_html_e( 'If you have just installed the plugin, that is to be expected.', 'seo-booster' );
							?>
	</p>

							<p>
							<?php
							esc_html_e( 'The plugin needs some time to listen to your traffic and figure out the details.', 'seo-booster' );
							?>
	</p>
							<?php
						}

						?>
					</div><!-- .welcome-panel-column1 -->
					<div class="helpbox">
						<h3><span class="dashicons dashicons-welcome-learn-more"></span>  
						<?php
						esc_html_e( 'Need help?', 'seo-booster' );
						?>
						</h3>
						<ul>
							<li><a href="https://wordpress.org/support/plugin/seo-booster/" target="_blank">
							<?php
							esc_html_e( 'WP Support Forum', 'seo-booster' );
							?>
							</a></li>
						</ul>

						<h3><span class="dashicons dashicons-welcome-widgets-menus"></span> 
						<?php
						esc_html_e( 'Plugin pages', 'seo-booster' );
						?>
						</h3>
						<ul>
							<li><a href="
							<?php
							echo esc_url( admin_url( 'admin.php?page=sb2_settings' ) );
							?>
							">
							<?php
							esc_html_e( 'The Settings', 'seo-booster' );
							?>
							</a></li>
							<li><a href="
							<?php
							echo esc_url( admin_url( 'admin.php?page=sb2_keywords' ) );
							?>
							">
							<?php
							esc_html_e( 'Keyword Details', 'seo-booster' );
							?>
							</a></li>
							<li><a href="
							<?php
							echo esc_url( admin_url( 'admin.php?page=sb2_backlinks' ) );
							?>
							">
							<?php
							esc_html_e( 'Backlink Details', 'seo-booster' );
							?>
							</a></li>
							<li><a href="
							<?php
							echo esc_url( admin_url( 'admin.php?page=sb2_crawled' ) );
							?>
							">
							<?php
							esc_html_e( 'Crawled Pages', 'seo-booster' );
							?>
							</a></li>
							<li><a href="
							<?php
							echo esc_url( admin_url( 'admin.php?page=sb2_404s' ) );
							?>
							">
							<?php
							esc_html_e( '404 Errors', 'seo-booster' );
							?>
							</a></li>
							<li><a href="
							<?php
							echo esc_url( admin_url( 'admin.php?page=sb2_forgotten' ) );
							?>
							">
							<?php
							esc_html_e( 'Forgotten Pages', 'seo-booster' );
							?>
							</a></li>
							<li><a href="
							<?php
							echo esc_url( admin_url( 'admin.php?page=sb2_log' ) );
							?>
							">
							<?php
							esc_html_e( 'The Log', 'seo-booster' );
							?>
							</a></li>
						</ul>
					</div>
				</div><!-- .wp-columns -->
			</div><!-- .welcome-panel-content -->
		</div><!--#inner-welcome-->
	</div><!-- .welcome-panel -->

	<?php
	$searchtrafficbyday_query = "SELECT daday, sum(visits) as totalvisits FROM {$wpdb->prefix}sb2_kwdt WHERE daday > DATE_SUB(NOW(), INTERVAL 120 DAY) GROUP BY daday ORDER BY daday ASC";

	$searchtrafficbyday = $wpdb->get_results( $searchtrafficbyday_query );

	if ( $searchtrafficbyday ) {
		?>
			<div id="sb2traffic" class="clearfix clear">
				<h3>
				<?php
				esc_html_e( 'Visitors from Search Engines', 'seo-booster' );
				?>
				</h3>
				<div id="searchtrafficbydaychart"></div>
				<script type="text/javascript">

					jQuery(document).ready(function() {

						google.charts.load('current', {packages: ['corechart']});
						google.charts.setOnLoadCallback(drawTrafficChart);
						function drawTrafficChart() {
							var data = google.visualization.arrayToDataTable([
								['Day','Visits'],
							<?php
							foreach ( $searchtrafficbyday as $stbd ) {
												echo "['" . esc_html( $stbd->daday ) . "', " . esc_html( $stbd->totalvisits ) . '],';
							}
							?>
								]);

							var options = {
								title: ' <?php esc_html_e( 'Visitors from Search Engines', 'seo-booster' ); ?>',
					curveType: 'function', // makes curved lines
					legend: { position: 'bottom' },
					backgroundColor : 'transparent',
					pointSize:7,
					titlePosition:'none',
					height:300,
					chartArea:{
						left:50,
						width:'100%'
					},
					series: [
					{
						color: '#36ace0',
						visibleInLegend: true
					}
					],
					lineWidth:4,
					trendlines: {
						0: {
							type: 'exponential',
							color: '#333',
							opacity: 1
						}
					}
				};
				var trafficchart = new google.visualization.LineChart(document.getElementById('searchtrafficbydaychart'));
				trafficchart.draw(data, options);
		} // function drawTrafficChart()
	});
</script>
</div><!-- #sb2traffic -->
			<?php
	}

	?>

<div id="sb2dashboard" class="clearfix clear">
	<div>
		<?php
		$totalkeywords      = $wpdb->get_var( "SELECT count(*) FROM {$wpdb->prefix}sb2_kw;" );
		$totalunknown       = $wpdb->get_var( "SELECT count(*) FROM {$wpdb->prefix}sb2_kw WHERE kw IN ('#','');" );
		$totalknown         = $wpdb->get_var( "SELECT count(*) FROM {$wpdb->prefix}sb2_kw WHERE kw NOT IN ('#','');" );
		$totalvisits        = $wpdb->get_var( "SELECT SUM(visits) FROM {$wpdb->prefix}sb2_kw;" );
		$totalknownvisits   = $wpdb->get_var( "SELECT SUM(visits) FROM {$wpdb->prefix}sb2_kw WHERE kw<>'#' AND kw<>'';" );
		$totalunknownvisits = $wpdb->get_var( "SELECT SUM(visits) FROM {$wpdb->prefix}sb2_kw WHERE kw='#' OR kw='';" );
		$topkeywords        = $wpdb->get_results( "SELECT kw,lp,visits FROM {$wpdb->prefix}sb2_kw WHERE ig='0' AND kw<>'Internal Search' AND kw<>'#' AND kw<>'' GROUP BY kw ORDER BY visits DESC limit 10;", ARRAY_A );

		if ( $topkeywords ) {
			?>
			<h2>
			<?php
			esc_html_e( 'Top Keywords', 'seo-booster' );
			?>
	</h2>

			<table class="wp-list-table widefat">
				<thead>
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
						esc_html_e( 'Visits', 'seo-booster' );
						?>
	</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $topkeywords as $topkw ) {
						echo '<tr><td>' . esc_html( $topkw['kw'] ) . "</td><td><a href='" . esc_html( $topkw['lp'] ) . "'>" . esc_html( $topkw['lp'] ) . '</a></td><td>' . esc_html( number_format_i18n( $topkw['visits'] ) ) . '</td></tr>';
					}
					?>
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
						esc_html_e( 'Visits', 'seo-booster' );
						?>
	</th>
					</tr>
				</tfoot>
			</table>
					<?php
		}

		$query   = "SELECT engine, COUNT(*) as cnt, SUM(visits) as visits FROM {$wpdb->prefix}sb2_kw WHERE `ig`='0' AND engine<>'Internal Search' GROUP BY `engine` ORDER BY `visits` DESC LIMIT 35;";
		$engines = $wpdb->get_results( $query, ARRAY_A );

		if ( $engines ) {
			?>
			<h2>
			<?php
			esc_html_e( 'Top Search Engines', 'seo-booster' );
			?>
	</h2>
			<div id="searchengineschart"></div>
			<script type="text/javascript">
				jQuery(document).ready(function() {
					google.charts.load('current', {'packages':['corechart','line']});

					google.charts.setOnLoadCallback(drawPieChart);

					function drawPieChart() {
						var data = google.visualization.arrayToDataTable([
							['
							<?php
							esc_html_e( 'Day', 'seo-booster' );
							?>
	', '
			<?php
			esc_html_e( 'Visitors', 'seo-booster' );
			?>
	'],
							<?php
							foreach ( $engines as $eng ) {
										echo "['" . esc_html( $eng['engine'] ) . "', " . esc_html( $eng['cnt'] ) . '],';
							}
							?>
							]);
						var options = {
							backgroundColor : 'transparent',
							title: '
							<?php
							esc_html_e( 'Top Search Engines', 'seo-booster' );
							?>
	',
							height: 330,
							legend: {
								position: 'right',
								textStyle: {
									fontSize: 16
								}
							},
							pieHole: 0.2,
							chartArea: {
								'top': 10,
								'width':'100%',
								'left':0,
								'height': 310,
								'backgroundColor': {
									'fill': 'transparent',
									'opacity': 50
								},
								'width':'100%'
							}
						};
						var chart = new google.visualization.PieChart(document.getElementById('searchengineschart'));
						chart.draw(data, options);
					}
				});
			</script>
					<?php
		}

		?>
		<h2>
		<?php
		esc_html_e( 'Backlink Stats', 'seo-booster' );
		?>
		</h2>
		<p>
		<?php
		esc_html_e( 'Details of the backlinks recorded.', 'seo-booster' );
		?>
		</p>
		<?php
		$totalbls               = $wpdb->get_var( "SELECT count(*) FROM {$wpdb->prefix}sb2_bl;" );
		$totalblsignore         = $wpdb->get_var( "SELECT count(*) FROM {$wpdb->prefix}sb2_bl WHERE ig='1';" );
		$totalblsverified       = $wpdb->get_var( "SELECT count(*) FROM {$wpdb->prefix}sb2_bl WHERE verified='1' AND ig<>'1';" );
		$totalblsvisits         = $wpdb->get_var( "SELECT SUM(visits) FROM {$wpdb->prefix}sb2_bl;" );
		$totalblsignorevisits   = $wpdb->get_var( "SELECT SUM(visits) FROM {$wpdb->prefix}sb2_bl WHERE ig='1';" );
		$totalblsverifiedvisits = $wpdb->get_var( "SELECT SUM(visits) FROM {$wpdb->prefix}sb2_bl WHERE verified='1' AND ig<>'1';" );
		?>
		<table class="wp-list-table widefat">
			<tr><td>
			<?php
			esc_html_e( 'Verified', 'seo-booster' );
			?>
			</td><td>
<?php
echo esc_html( number_format_i18n( $totalblsverified ) );
?>
</td><td>(
<?php
echo esc_html( number_format_i18n( $totalblsverifiedvisits ) ) . ' ' . esc_html__( 'Visits', 'seo-booster' );
?>
)</td></tr>
			<tr><td>
			<?php
			esc_html_e( 'Ignored', 'seo-booster' );
			?>
			</td><td>
<?php
echo esc_html( number_format_i18n( $totalblsignore ) );
?>
</td><td>(
<?php
echo esc_html( number_format_i18n( $totalblsignorevisits ) ) . ' ' . esc_html__( 'Visits', 'seo-booster' );
?>
)</td></tr>
			<tr><td>
			<?php
			esc_html_e( 'Total', 'seo-booster' );
			?>
			</td><td>
<?php
echo esc_html( number_format_i18n( $totalbls ) );
?>
</td><td>(
<?php
echo esc_html( number_format_i18n( $totalblsvisits ) ) . ' ' . esc_html__( 'Visits', 'seo-booster' );
?>
)</td></tr>

		</table>
		<p>
		<?php
		esc_html_e( 'Verified means SEO Booster has verified the link, and if possible collected anchor text and other details. Ignored links are backlinks that do not have a link back or are filtered for other reasons.', 'seo-booster' );
		?>
		</p>
		<?php
		$samplelinks = $wpdb->get_results( "SELECT domain, (SELECT COUNT(*) FROM {$wpdb->prefix}sb2_bl O WHERE O.domain = M.domain ) AS totallinks, (SELECT SUM(D.visits) FROM {$wpdb->prefix}sb2_bl D WHERE D.domain = M.domain ) AS totalvisits FROM {$wpdb->prefix}sb2_bl M where ig='0' AND domain<>'' group by domain order by totalvisits desc limit 15;", ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $samplelinks ) {
			?>

			<h2>
			<?php
			esc_html_e( 'Top Linking Domains', 'seo-booster' );
			?>
	</h2>

			<table class="wp-list-table widefat">
				<thead>
					<tr>
						<th scope="col">
						<?php
						esc_html_e( 'Domain', 'seo-booster' );
						?>
	</th>
						<th scope="col">
						<?php
						esc_html_e( 'Total Links', 'seo-booster' );
						?>
	</th>
						<th scope="col">
						<?php
						esc_html_e( 'Total Visits', 'seo-booster' );
						?>
	</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $samplelinks as $sample ) {
						echo '<tr><td>' . esc_html( $sample['domain'] ) . '</td><td>' . esc_html( $sample['totallinks'] ) . '</td><td>' . esc_html( $sample['totalvisits'] ) . '</td></tr>';
					}
					?>
				</tbody>
				<tfoot>
					<tr>
						<th scope="col">
						<?php
						esc_html_e( 'Domain', 'seo-booster' );
						?>
	</th>
						<th scope="col">
						<?php
						esc_html_e( 'Total Links', 'seo-booster' );
						?>
	</th>
						<th scope="col">
						<?php
						esc_html_e( 'Total Visits', 'seo-booster' );
						?>
	</th>
					</tr>
				</tfoot>

			</table>
					<?php
		}

		?>
	</div>
</div>
</div> <!-- .wrap -->
