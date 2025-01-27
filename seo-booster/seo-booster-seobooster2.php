<?php

namespace Cleverplugins\SEOBooster;

use Cleverplugins\SEOBooster\Utils;
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !current_user_can( 'update_plugins' ) ) {
    wp_die( esc_html__( 'You are not allowed to update plugins on this blog.', 'seo-booster' ) );
}
global $wpdb, $seobooster_fs;
?>
<div class="wrap">
	<?php 
global $wpdb;
$dbliste = array(
    $wpdb->prefix . 'sb2_autolink',
    $wpdb->prefix . 'sb2_404',
    $wpdb->prefix . 'sb2_log',
    $wpdb->prefix . 'sb2_query_keywords',
    $wpdb->prefix . 'sb2_query_keywords_history'
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
		<div class="notice notice-error seobooster-notice">
			<h3>
				<?php 
    esc_html_e( 'Database tables needs updating', 'seo-booster' );
    ?>
			</h3>
			<?php 
    echo wp_kses( $missing, $allowed_html );
    ?>

			<form id="fixdatabase" method="post">
				<input type="hidden" name="page" value="<?php 
    echo esc_attr( ( isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '' ) );
    ?>" />
				<input type="hidden" name="action" value="sbp_fixdatabasetables" />
				<input type="hidden" name="_wpnonce" value=" <?php 
    echo esc_attr( wp_create_nonce( 'fixdbtables' ) );
    ?>">
				<?php 
    submit_button(
        __( 'Click here to fix', 'seo-booster' ),
        'primary',
        'updatedb',
        true
    );
    ?>
			</form>

		</div>
	<?php 
}
echo wp_kses_post( Utils::show_plugin_headline( esc_html__( 'Dashboard', 'seo-booster' ), true ) );
?>

	<div id="welcome-panel" class="new-welcome-panel clearfix clear">
		<div id="inner-welcome">
			<div class="welcome-panel-content">
				<?php 
$selected_site = get_option( 'seobooster_selected_site' );
$access_token = get_option( 'seobooster_access_token' );
// check if authentication is set
if ( !seobooster_fs()->is_registered() && !seobooster_fs()->is_tracking_allowed() ) {
    echo '<div class="card">';
    echo '<h3>' . esc_html__( 'Welcome to SEO Booster!', 'seo-booster' ) . '</h3>';
    echo '<p>' . esc_html__( 'You\'re all set to explore the powerful features of SEO Booster. While you can navigate existing data and use free features, connecting to Google Search Console for real-time keyword data requires account validation.', 'seo-booster' ) . '</p>';
    echo '<div class="seobooster-features">';
    echo '<h4>' . esc_html__( 'Unlock with Validation:', 'seo-booster' ) . '</h4>';
    echo '<ul>';
    echo '<li>' . esc_html__( 'Real-time keyword tracking', 'seo-booster' ) . '</li>';
    echo '<li>' . esc_html__( 'Advanced performance analytics', 'seo-booster' ) . '</li>';
    echo '<li>' . esc_html__( 'Seamless Google Search Console integration', 'seo-booster' ) . '</li>';
    echo '<li>' . esc_html__( 'On-page SEO analysis', 'seo-booster' ) . '</li>';
    echo '<li>' . esc_html__( 'Basic keyword research tools', 'seo-booster' ) . '</li>';
    echo '<li>' . esc_html__( 'Site structure optimization', 'seo-booster' ) . '</li>';
    echo '</ul>';
    echo '</div>';
    echo '<p><a href="' . esc_url( seobooster_fs()->get_reconnect_url() ) . '" class="button button-primary">' . esc_html__( 'Validate Your Account to Unlock Full Potential', 'seo-booster' ) . '</a></p>';
    echo '</div>';
}
if ( !$access_token || !$selected_site || $selected_site === "" ) {
    Google_API::display_auth_status();
}
// if ($selected_site) {
$query_keywords_table = $wpdb->prefix . 'sb2_query_keywords';
$history_table = $wpdb->prefix . 'sb2_query_keywords_history';
// Try to get cached stats first
$cache_key = 'seobooster_keyword_stats';
$stats = wp_cache_get( $cache_key );
if ( false === $stats ) {
    $stats = array(
        'total_keywords'      => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT query) \n\t\t\t\t\t\t\t\t\tFROM {$wpdb->prefix}sb2_query_keywords \n\t\t\t\t\t\t\t\t\tWHERE %s = %s", '1', '1' ) ),
        'unique_pages'        => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT page) \n\t\t\t\t\t\t\t\t\tFROM {$wpdb->prefix}sb2_query_keywords \n\t\t\t\t\t\t\t\t\tWHERE %s = %s", '1', '1' ) ),
        'unique_days'         => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT date) \n\t\t\t\t\t\t\t\t\tFROM {$wpdb->prefix}sb2_query_keywords_history \n\t\t\t\t\t\t\t\t\tWHERE %s = %s", '1', '1' ) ),
        'first_history_date'  => $wpdb->get_var( $wpdb->prepare( "SELECT MIN(date) \n\t\t\t\t\t\t\t\t\tFROM {$wpdb->prefix}sb2_query_keywords_history \n\t\t\t\t\t\t\t\t\tWHERE %s = %s", '1', '1' ) ),
        'latest_history_date' => $wpdb->get_var( $wpdb->prepare( "SELECT MAX(date) \n\t\t\t\t\t\t\t\t\tFROM {$wpdb->prefix}sb2_query_keywords_history \n\t\t\t\t\t\t\t\t\tWHERE %s = %s", '1', '1' ) ),
    );
    // Cache the results for 1 hour (3600 seconds)
    wp_cache_set(
        $cache_key,
        $stats,
        '',
        3600
    );
}
$total_keywords = $stats['total_keywords'];
$unique_pages = $stats['unique_pages'];
$unique_days = $stats['unique_days'];
$first_history_date = $stats['first_history_date'];
$latest_history_date = $stats['latest_history_date'];
$first_history_date_str = ( $first_history_date ? date_i18n( get_option( 'date_format' ), strtotime( $first_history_date ) ) : __( 'N/A', 'seo-booster' ) );
$latest_history_date_str = ( $latest_history_date ? date_i18n( get_option( 'date_format' ), strtotime( $latest_history_date ) ) : __( 'N/A', 'seo-booster' ) );
echo '<h3>' . esc_html__( 'Quick Overview', 'seo-booster' ) . '</h3>';
$overview_message = sprintf(
    // translators: 1: number of days, 2: first date, 3: last date
    _n(
        'There is data from Google Search Console for <span>%1$s</span> unique day, from <span>%2$s</span> to <span>%3$s</span>.',
        'There is data from Google Search Console for <span>%1$s</span> unique days, from <span>%2$s</span> to <span>%3$s</span>.',
        $unique_days,
        'seo-booster'
    ),
    number_format_i18n( $unique_days ),
    $first_history_date_str,
    $latest_history_date_str
);
$overview_message .= '<br>' . sprintf( 
    // translators: 1: number of keywords, 2: number of pages
    __( 'A total of <span>%1$s</span> different keyword terms have been used to find <span>%2$s</span> different pages on your website.', 'seo-booster' ),
    number_format_i18n( $total_keywords ),
    number_format_i18n( $unique_pages )
 );
$seobooster_weekly_email = get_option( 'seobooster_weekly_email' );
if ( isset( $_GET['gsc_updated'] ) && $_GET['gsc_updated'] == '1' && !$seobooster_weekly_email ) {
    ?>
						<div id="seobooster_email_container" class="notice notice-success is-dismissible seobooster-notice">
							<div class="innercont">
						<h3><?php 
    esc_html_e( 'Import complete!', 'seo-booster' );
    ?></h3>
								<h4><?php 
    esc_html_e( 'Get your personalized weekly SEO insights!', 'seo-booster' );
    ?><span><?php 
    esc_html_e( 'Stay informed and ahead of the competition', 'seo-booster' );
    ?></span></h4>
								<div class="cont">
								<div class="col">
										<form method="post" action="" class="card">
											<?php 
    $current_user = wp_get_current_user();
    ?>
											<?php 
    wp_nonce_field( 'seobooster_save_selected_site', 'seobooster_selected_site_nonce' );
    ?>

											<div class="">
												<p><?php 
    esc_html_e( 'Confirm or change your email to receive your personalized weekly report:', 'seo-booster' );
    ?></p>
												<p>
													<input type="text" name="seobooster_email" id="seobooster_email" class="regular-text" placeholder="<?php 
    esc_attr_e( 'email@example.com', 'seo-booster' );
    ?>" value="<?php 
    echo esc_html( $current_user->user_email );
    ?>" autocomplete="off" data-1p-ignore>
												</p>
												<p>
													<input type="submit" name="submit" value="<?php 
    esc_attr_e( 'Confirm Email for Weekly Reports', 'seo-booster' );
    ?>" class="button button-primary">
												</p>
												<p class="description"><?php 
    esc_html_e( 'You can add multiple email addresses, separated by commas', 'seo-booster' );
    ?>
												</p>
												<p class="description"><?php 
    esc_html_e( 'You can modify your report preferences anytime in the plugin settings', 'seo-booster' );
    ?>
												</p>
											</div>

										</form>

									</div>
								<div class="col">
										<p><?php 
    esc_html_e( 'Receive a tailored weekly report to help you monitor performance, address issues promptly, and capitalize on new opportunities to boost your traffic!', 'seo-booster' );
    ?></p>
										<h4><?php 
    esc_html_e( 'Your weekly report includes:', 'seo-booster' );
    ?></h4>
										<ul class="sb2weeklybenefits">
											<li><?php 
    esc_html_e( 'Your top-performing keywords driving traffic', 'seo-booster' );
    ?></li>
											<li><?php 
    esc_html_e( 'Emerging keyword opportunities specific to your site', 'seo-booster' );
    ?></li>
											<li><?php 
    esc_html_e( 'Significant changes in your rankings', 'seo-booster' );
    ?></li>
											<li><?php 
    esc_html_e( 'Content areas requiring your attention', 'seo-booster' );
    ?></li>
											<li>Pro: <?php 
    esc_html_e( '404 errors - content that is not found', 'seo-booster' );
    ?></li>

										</ul>
										<p><?php 
    esc_html_e( 'Your data privacy is our priority. All information is processed locally on your server and sent to your email address from your own server — we never access your data.', 'seo-booster' );
    ?></p>
									</div>
								</div>
							
							</div><!-- .innercont -->
							<div id="sbweeklyemail_signupmessage" style="display: none;"></div>

						</div><!-- #seobooster_email_container -->
					<?php 
}
if ( 0 < $unique_days ) {
    // Display the overview
    echo wp_kses_post( '<p class="quickie">' . $overview_message . '</p>' );
}
// Lars - commented out for now
/*
else {
	// We need to set it up
	Google_API::display_auth_status();
}
*/
$css_class = ' proonly';
// if ($access_token && $selected_site && (0 < $unique_days)) {
if ( $selected_site && 0 < $unique_days ) {
    $gsc_link = admin_url( 'admin.php?page=sb2_gsc' );
    ?>

					<h3><?php 
    esc_html_e( 'What to do now?', 'seo-booster' );
    ?></h3>
					<div class="helpcont">
						<div class="helpbox">
							<h3><?php 
    esc_html_e( 'All keywords from Google Search Console', 'seo-booster' );
    ?></h3>

							<p><?php 
    echo esc_html__( 'Get a combined overview of all the keywords that have been used to find your website. Discover opportunities for improvement and inspiration for new content.', 'seo-booster' );
    ?></p>
							<a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_gsc' ) );
    ?>" class="button button-secondary"><?php 
    esc_html_e( 'Go to the keyword overview page', 'seo-booster' );
    ?></a>
						</div>
						<div class="helpbox<?php 
    echo esc_attr( $css_class );
    ?>">
							<h3><?php 
    esc_html_e( 'Keywords used to find your front page', 'seo-booster' );
    ?></h3>
							
							<p><?php 
    echo esc_html__( 'All keyword details for each page are available a few clicks away in the admin bar.', 'seo-booster' );
    ?></p>
							<a href="<?php 
    echo esc_url( home_url( '?seobooster_showdetails=1' ) );
    ?>" class="button button-secondary" target="_blank"><?php 
    echo esc_html__( 'Open the front page', 'seo-booster' );
    ?></a>
						</div>
						<div class="helpbox<?php 
    echo esc_attr( $css_class );
    ?>">
							<h3><?php 
    esc_html_e( 'Reports', 'seo-booster' );
    ?></h3>
							
							<p><?php 
    echo esc_html__( 'Dive into the data and get insights on how to improve your website. Different reports give you suggestions for improvements and practical advice.', 'seo-booster' );
    ?></p>
							<a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_reports' ) );
    ?>" class="button button-secondary"><?php 
    echo esc_html__( 'Open the report page', 'seo-booster' );
    ?></a>
						</div>
					</div><!-- .helpcont -->
				<?php 
}
if ( (!isset( $total_keywords ) || '0' === $total_keywords) && seobooster_fs()->is_registered() && seobooster_fs()->is_tracking_allowed() && $access_token && $selected_site ) {
    ?>

					<div class="helpcont">
						<div class="helpbox">
							<h3><?php 
    esc_html_e( 'No data collected yet - import data', 'seo-booster' );
    ?></h3>

							<p><?php 
    esc_html_e( 'It looks like no data has been collected yet. To get started:', 'seo-booster' );
    ?></p>
							<ol>
								<li><?php 
    esc_html_e( 'Go to the Settings page', 'seo-booster' );
    ?></li>
								<li><?php 
    esc_html_e( 'Find the "Reimport" function', 'seo-booster' );
    ?></li>
								<li><?php 
    esc_html_e( 'Use it to import your Google Search Console data', 'seo-booster' );
    ?></li>
							</ol>
							<p><?php 
    esc_html_e( 'This will populate your dashboard with valuable SEO insights.', 'seo-booster' );
    ?></p>
							<p><a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_settings#manualupdate' ) );
    ?>"><?php 
    esc_html_e( 'Go to the Settings page', 'seo-booster' );
    ?></a></p>
						</div>
					</div>
				<?php 
}
?>
			</div><!-- .welcome-panel-content -->


			<?php 
if ( $access_token && $selected_site && 0 < $unique_days ) {
    ?>
				<div id="sb2canvascont" style="height:550px;max-height:750px;display:block;margin:0 auto 20px auto;">
					<canvas id="seobooster-gsc-chart"><?php 
    esc_html_e( 'Chart', 'seo-booster' );
    ?></canvas>
					<div id="loading-indicator">
						<div id="spinner"></div>
					</div>
				</div>
				<p><?php 
    esc_html_e( 'The chart is based on the data collected from Google Search Console. It does not represent a full picture of your website\'s traffic.', 'seo-booster' );
    ?></p>

			<?php 
}
?>

		</div><!--#inner-welcome-->
	</div><!-- .welcome-panel -->
	<?php 
if ( $selected_site ) {
    $timestamp_output = '';
    $timestamp = wp_next_scheduled( 'seobooster_gsc_data_fetch' );
    // display the timestamp
    if ( $timestamp ) {
        $timestamp = gmdate( 'Y-m-d H:i:s', $timestamp );
        $current_time = current_time( 'timestamp' );
        $time_diff = human_time_diff( $current_time, strtotime( $timestamp ) );
        // translators: %1$s: timestamp, %2$s: time difference
        $timestamp_output = ' - <small>' . __( 'Next scheduled update:', 'seo-booster' ) . ' ' . $timestamp . ' (' . sprintf( __( 'in %s', 'seo-booster' ), $time_diff ) . ')</small>';
    }
    // translators: %s: Google Search Console site URL
    echo '<p>' . sprintf( esc_html__( 'You are connected to the GSC site %s', 'seo-booster' ), '<strong>' . esc_html( $selected_site ) . '</strong>' ) . wp_kses_post( $timestamp_output ) . '</p>';
}
?>
</div> <!-- .wrap --><?php 