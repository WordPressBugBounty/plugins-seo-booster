<?php

namespace Cleverplugins\SEOBooster;

use Cleverplugins\SEOBooster\Credits_Service;
use Cleverplugins\SEOBooster\Utils;
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'seo-booster' ) );
}
global $wpdb, $seobooster_fs;
?>
<div class="wrap sb-wrap">
	<?php 
global $wpdb;
$dbliste = array_values( Utils::get_plugin_table_names() );
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
				<input type="hidden" name="page" value="
				<?php 
    echo esc_attr( ( isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '' ) );
    ?>
	" />
				<input type="hidden" name="action" value="sbp_fixdatabasetables" />
				<input type="hidden" name="_wpnonce" value=" 
				<?php 
    echo esc_attr( wp_create_nonce( 'fixdbtables' ) );
    ?>
	">
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
echo '<div class="sb-admin-seo-compat-wrap">';
require SEOBOOSTER_PLUGINPATH . 'inc/views/seo-plugin-compat-notice.php';
echo '</div>';
$ai_provider = \Cleverplugins\SEOBooster\LLM_Helper::get_selected_ai_provider();
$wp_ai_ready = function_exists( 'wp_ai_client_prompt' );
$show_ai_notice = false;
$ai_notice_link = admin_url( 'admin.php?page=sb2_settings#ai-llm' );
$ai_notice_text = '';
if ( $ai_provider === 'disabled' ) {
    $show_ai_notice = true;
    $ai_notice_text = __( 'Enable AI in SEO Booster Settings to get AI-generated SEO suggestions and image meta.', 'seo-booster' );
} elseif ( $ai_provider === 'WordPress' && !$wp_ai_ready ) {
    $show_ai_notice = true;
    $ai_notice_link = admin_url( 'options-connectors.php' );
    $ai_notice_text = __( 'WordPress AI is not available. Use WordPress 7 or later and configure Settings → Connectors.', 'seo-booster' );
} elseif ( $ai_provider === 'seobooster' && \Cleverplugins\SEOBooster\Credits_Service::is_ai_provider_available() && !\Cleverplugins\SEOBooster\Credits_Service::is_registered() ) {
    $show_ai_notice = true;
    $ai_notice_text = __( 'Connect your SEO Booster Credits account in Settings to use AI features.', 'seo-booster' );
}
if ( $show_ai_notice ) {
    ?>
<div class="notice notice-info is-dismissible seobooster-notice" style="margin: 20px 0; padding: 15px; border-left: 4px solid #0073aa;">
	<h3 style="margin-top: 0; color: #0073aa;"><?php 
    esc_html_e( 'Unlock AI-Powered SEO Features', 'seo-booster' );
    ?></h3>
	<p style="margin-bottom: 10px;"><?php 
    echo esc_html( $ai_notice_text );
    ?></p>
	<p style="margin-bottom: 0;">
		<a href="<?php 
    echo esc_url( $ai_notice_link );
    ?>" class="button button-primary">
			<?php 
    echo ( $ai_provider === 'WordPress' && !$wp_ai_ready ? esc_html__( 'Open Settings → Connectors', 'seo-booster' ) : esc_html__( 'SEO Booster Settings', 'seo-booster' ) );
    ?>
		</a>
	</p>
</div>
<?php 
}
?>

		<?php 
$selected_site = get_option( 'seobooster_selected_site' );
$access_token = get_option( 'seobooster_access_token' );
$oauth_auth_params = Google_API::get_oauth_auth_params( admin_url( 'admin.php?page=sb2_dashboard&auth=1' ) );
$install_id = $oauth_auth_params['install_id'];
$authentication_string = $oauth_auth_params['auth_token'];
$return_to = $oauth_auth_params['return_to'];
/*
// check if authentication is set
if ( !seobooster_fs()->is_registered() ) {
echo '<div class="card">';
echo '<h3>' . esc_html__( 'Connect with Google Search Console', 'seo-booster' ) . '</h3>';
echo '<p>' . esc_html__( 'To access valuable keyword data from Google Search Console, you\'ll need to create a free SEO Booster account. This quick registration enables secure authentication with Google\'s API.', 'seo-booster' ) . '</p>';
echo '<p>' . esc_html__( 'In the meantime, you can explore our Automatic Link feature to start improving your SEO:', 'seo-booster' ) . '</p>';
echo '<p>';
echo '<a href="' . esc_url( seobooster_fs()->get_reconnect_url() ) . '" class="button button-primary">' . esc_html__( 'Create or Reconnect Account', 'seo-booster' ) . '</a> ';
echo '<a href="' . esc_url( admin_url( 'admin.php?page=sb2_autolink' ) ) . '" class="button button-secondary">' . esc_html__( 'Explore Automatic Links', 'seo-booster' ) . '</a>';
echo '</p>';
echo '</div>';
}
*/
// Authentication status is now handled in the .col1 section
$query_keywords_table = $wpdb->prefix . 'sb2_query_keywords';
$history_table = $wpdb->prefix . 'sb2_query_keywords_history';
// Try to get cached stats first
$cache_key = 'seobooster_keyword_stats';
$stats = wp_cache_get( $cache_key );
if ( false === $stats ) {
    $stats = array(
        'total_keywords'      => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT query) \nFROM {$wpdb->prefix}sb2_query_keywords \nWHERE %s = %s", '1', '1' ) ),
        'unique_pages'        => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT page) \nFROM {$wpdb->prefix}sb2_query_keywords \nWHERE %s = %s", '1', '1' ) ),
        'unique_days'         => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT date) \nFROM {$wpdb->prefix}sb2_query_keywords_history \nWHERE %s = %s", '1', '1' ) ),
        'first_history_date'  => $wpdb->get_var( $wpdb->prepare( "SELECT MIN(date) \nFROM {$wpdb->prefix}sb2_query_keywords_history \nWHERE %s = %s", '1', '1' ) ),
        'latest_history_date' => $wpdb->get_var( $wpdb->prepare( "SELECT MAX(date) \nFROM {$wpdb->prefix}sb2_query_keywords_history \nWHERE %s = %s", '1', '1' ) ),
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
$dashboard_kpis = array();
$dashboard_summary = sprintf(
    /* translators: 1: keyword count, 2: page count, 3: day count, 4: first date, 5: last date */
    __( '%1$s keywords across %2$s pages · %3$s days of GSC data (%4$s – %5$s).', 'seo-booster' ),
    number_format_i18n( $total_keywords ),
    number_format_i18n( $unique_pages ),
    number_format_i18n( $unique_days ),
    $first_history_date_str,
    $latest_history_date_str
);
// First, get the latest date with data
$latest_date = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(date)\n\t\tFROM {$wpdb->prefix}sb2_query_keywords_history\n\t\tWHERE %s = %s", '1', '1' ) );
if ( $latest_date ) {
    $past_30_days_data = $wpdb->get_results( $wpdb->prepare( "SELECT\n\t\t\t\tSUM(h.impressions) AS total_impressions,\n\t\t\t\tSUM(h.clicks) AS total_clicks,\n\t\t\t\tAVG(h.position) AS avg_position,\n\t\t\t\tAVG(h.ctr) AS avg_ctr\n\t\t\tFROM\n\t\t\t\t{$wpdb->prefix}sb2_query_keywords_history AS h\n\t\t\tWHERE\n\t\t\t\th.date BETWEEN DATE_SUB(%s, INTERVAL 30 DAY) AND %s", $latest_date, $latest_date ), OBJECT );
    $previous_30_days_data = $wpdb->get_results( $wpdb->prepare( "SELECT\n\t\t\t\tSUM(h.impressions) AS total_impressions,\n\t\t\t\tSUM(h.clicks) AS total_clicks,\n\t\t\t\tAVG(h.position) AS avg_position,\n\t\t\t\tAVG(h.ctr) AS avg_ctr\n\t\t\tFROM\n\t\t\t\t{$wpdb->prefix}sb2_query_keywords_history AS h\n\t\t\tWHERE\n\t\t\t\th.date BETWEEN DATE_SUB(%s, INTERVAL 60 DAY) AND DATE_SUB(%s, INTERVAL 30 DAY)", $latest_date, $latest_date ), OBJECT );
    if ( $past_30_days_data && $previous_30_days_data ) {
        $past_30_days = $past_30_days_data[0];
        $previous_30_days = $previous_30_days_data[0];
        $impressions_change = $past_30_days->total_impressions - $previous_30_days->total_impressions;
        $clicks_change = $past_30_days->total_clicks - $previous_30_days->total_clicks;
        $position_change = $past_30_days->avg_position - $previous_30_days->avg_position;
        $ctr_change = ($past_30_days->avg_ctr - $previous_30_days->avg_ctr) * 100;
        $impressions_percentage = ( $previous_30_days->total_impressions > 0 ? round( $impressions_change / $previous_30_days->total_impressions * 100, 1 ) : 0 );
        $clicks_percentage = ( $previous_30_days->total_clicks > 0 ? round( $clicks_change / $previous_30_days->total_clicks * 100, 1 ) : 0 );
        $position_percentage = ( $previous_30_days->avg_position > 0 ? round( $position_change / $previous_30_days->avg_position * 100, 1 ) : 0 );
        $ctr_percentage = ( $previous_30_days->avg_ctr > 0 ? round( $ctr_change / ($previous_30_days->avg_ctr * 100) * 100, 1 ) : 0 );
        $dashboard_kpis = array(
            'clicks'      => array(
                'value'      => (float) $past_30_days->total_clicks,
                'change'     => (float) $clicks_change,
                'percentage' => (float) $clicks_percentage,
                'invert'     => false,
                'decimals'   => 0,
            ),
            'impressions' => array(
                'value'      => (float) $past_30_days->total_impressions,
                'change'     => (float) $impressions_change,
                'percentage' => (float) $impressions_percentage,
                'invert'     => false,
                'decimals'   => 0,
            ),
            'position'    => array(
                'value'      => (float) $past_30_days->avg_position,
                'change'     => (float) $position_change,
                'percentage' => (float) $position_percentage,
                'invert'     => true,
                'decimals'   => 1,
            ),
            'ctr'         => array(
                'value'      => (float) ($past_30_days->avg_ctr * 100),
                'change'     => (float) $ctr_change,
                'percentage' => (float) $ctr_percentage,
                'invert'     => false,
                'decimals'   => 2,
                'suffix'     => '%',
            ),
        );
    }
}
$seobooster_weekly_email = get_option( 'seobooster_weekly_email' );
if ( isset( $_GET['gsc_updated'] ) && $_GET['gsc_updated'] == '1' && !$seobooster_weekly_email ) {
    ?>
			<div id="seobooster_email_container" class="notice notice-success is-dismissible seobooster-notice">
				<div class="innercont">
				<h2>
				<?php 
    esc_html_e( 'Import complete!', 'seo-booster' );
    ?>
	</h2>
					<h4>
					<?php 
    esc_html_e( 'Get your personalized weekly SEO insights!', 'seo-booster' );
    ?>
	<span>
			<?php 
    esc_html_e( 'Stay informed and ahead of the competition', 'seo-booster' );
    ?>
	</span></h4>
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
									<p>
									<?php 
    esc_html_e( 'Confirm or change your email to receive your personalized weekly report:', 'seo-booster' );
    ?>
	</p>
									<p>
										<input type="text" name="seobooster_email" id="seobooster_email" class="regular-text" value="
										<?php 
    echo esc_html( $current_user->user_email );
    ?>
	" autocomplete="off" data-1p-ignore>
									</p>
									<p>
										<input type="submit" name="submit" value="
										<?php 
    esc_attr_e( 'Confirm Email for Weekly Reports', 'seo-booster' );
    ?>
	" class="button button-primary">
									</p>
									<p class="description">
									<?php 
    esc_html_e( 'You can add multiple email addresses, separated by commas', 'seo-booster' );
    ?>
									</p>
									<p class="description">
									<?php 
    esc_html_e( 'You can modify your report preferences anytime in the plugin settings', 'seo-booster' );
    ?>
									</p>
								</div>

							</form>

						</div>
					<div class="col">
							<p>
							<?php 
    esc_html_e( 'Receive a tailored weekly report to help you monitor performance, address issues promptly, and capitalize on new opportunities to boost your traffic!', 'seo-booster' );
    ?>
	</p>
							<h4>
							<?php 
    esc_html_e( 'Your weekly report includes:', 'seo-booster' );
    ?>
	</h4>
							<ul class="sb2weeklybenefits">
								<li>
								<?php 
    esc_html_e( 'Your top-performing keywords driving traffic', 'seo-booster' );
    ?>
	</li>
								<li>
								<?php 
    esc_html_e( 'Emerging keyword opportunities specific to your site', 'seo-booster' );
    ?>
	</li>
								<li>
								<?php 
    esc_html_e( 'Significant changes in your rankings', 'seo-booster' );
    ?>
	</li>
								<li>
								<?php 
    esc_html_e( 'Content areas requiring your attention', 'seo-booster' );
    ?>
	</li>
								<li>Pro: 
								<?php 
    esc_html_e( '404 errors - content that is not found', 'seo-booster' );
    ?>
	</li>

							</ul>
							<p>
							<?php 
    esc_html_e( 'Your data privacy is our priority. All information is processed locally on your server and sent to your email address from your own server — we never access your data.', 'seo-booster' );
    ?>
	</p>
						</div>
					</div>
				
				</div><!-- .innercont -->
				<div id="sbweeklyemail_signupmessage" style="display: none;"></div>

			</div><!-- #seobooster_email_container -->
				<?php 
}
// Lars - commented out for now
/*
else {
// We need to set it up
Google_API::display_auth_status();
}
*/
if ( $selected_site && 0 < $unique_days ) {
    $gsc_link = admin_url( 'admin.php?page=sb2_gsc' );
    Google_API::auto_validate_and_clear_reauth();
    $need_reauth = get_option( 'seobooster_needs_reauth' );
    if ( $need_reauth ) {
        echo '<div class="notice notice-error seobooster-notice"><p>';
        echo '<strong>' . esc_html__( 'Authentication Issue Detected', 'seo-booster' ) . '</strong><br>';
        echo esc_html__( 'Your Google Search Console access token appears to be invalid or expired. This can happen if:', 'seo-booster' );
        echo '<ul style="margin-left: 20px; margin-top: 5px;">';
        echo '<li>' . esc_html__( 'The token has expired (tokens typically last 1 hour)', 'seo-booster' ) . '</li>';
        echo '<li>' . esc_html__( 'Google has revoked access to your account', 'seo-booster' ) . '</li>';
        echo '<li>' . esc_html__( 'There was a network connectivity issue', 'seo-booster' ) . '</li>';
        echo '</ul>';
        $google_email = get_option( 'seobooster_google_email' );
        if ( $google_email ) {
            echo '<p><strong>' . esc_html__( 'Connected Account:', 'seo-booster' ) . '</strong> ' . esc_html( $google_email ) . '</p>';
        }
        echo '</p>';
        echo '<p>';
        echo '<button type="button" id="try-validate-token" class="button button-secondary" style="margin-right: 10px;">' . esc_html__( 'Try Again', 'seo-booster' ) . '</button>';
        echo '<a href="' . esc_url( add_query_arg( array(
            'install_id' => $install_id,
            'auth_token' => $authentication_string,
            'return_to'  => $return_to,
        ), 'https://seoboosterauth.com/auth' ) ) . '" target="_blank" class="button button-primary">' . esc_html__( 'Re-authenticate with Google', 'seo-booster' ) . '</a>';
        echo '</p>';
        echo '<div id="validation-status" style="display:none; margin-top: 10px;"></div>';
        echo '</div>';
        echo '<script>';
        echo 'jQuery(document).ready(function($) {';
        echo '    $("#try-validate-token").on("click", function() {';
        echo '        var button = $(this);';
        echo '        var status = $("#validation-status");';
        echo '        button.prop("disabled", true).text("' . esc_js( __( 'Validating...', 'seo-booster' ) ) . '");';
        echo '        status.html("<span style=\\"color: #0073aa;\\">' . esc_js( __( 'Checking token validity...', 'seo-booster' ) ) . '</span>").show();';
        echo '        $.ajax({';
        echo '            url: ajaxurl,';
        echo '            type: "POST",';
        echo '            data: {';
        echo '                action: "manual_token_refresh",';
        echo '                nonce: "' . esc_js( wp_create_nonce( 'seobooster_token_refresh' ) ) . '"';
        echo '            },';
        echo '            success: function(response) {';
        echo '                if (response.valid) {';
        echo '                    status.html("<span style=\\"color: green;\\">✓ ' . esc_js( __( 'Token is valid! Refreshing page...', 'seo-booster' ) ) . '</span>");';
        echo '                    setTimeout(function() {';
        echo '                        location.reload();';
        echo '                    }, 2000);';
        echo '                } else {';
        echo '                    status.html("<span style=\\"color: red;\\">✗ " + response.message + "</span>");';
        echo '                }';
        echo '            },';
        echo '            error: function() {';
        echo '                status.html("<span style=\\"color: red;\\">' . esc_js( __( 'Error checking token', 'seo-booster' ) ) . '</span>");';
        echo '            },';
        echo '            complete: function() {';
        echo '                button.prop("disabled", false).text("' . esc_js( __( 'Try Again', 'seo-booster' ) ) . '");';
        echo '            }';
        echo '        });';
        echo '    });';
        echo '});';
        echo '</script>';
    }
    $kpi_labels = array(
        'clicks'      => __( 'Clicks', 'seo-booster' ),
        'impressions' => __( 'Impressions', 'seo-booster' ),
        'position'    => __( 'Avg. Position', 'seo-booster' ),
        'ctr'         => __( 'CTR', 'seo-booster' ),
    );
    require_once SEOBOOSTER_PLUGINPATH . 'inc/SEO_Issues_Manager.php';
    $top_possibilities = SEO_Issues_Manager::get_top_possibilities_for_dashboard( 5 );
    $possibilities_stats = SEO_Issues_Manager::get_analysis_stats();
    $top_keywords = Utils::get_top_keywords( 30, 7 );
    $autolink_rules = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sb2_autolink" );
    $ai_bot_summary = AI_Bot_Tracker::get_summary( 30 );
    $ai_bot_purpose = ( isset( $ai_bot_summary['purpose_breakdown'] ) ? $ai_bot_summary['purpose_breakdown'] : array(
        'research' => 0,
        'citation' => 0,
    ) );
    $ai_bot_ratio = AI_Bot_Tracker::get_content_vs_noise_ratio( 30 );
    $ai_bot_top_content = AI_Bot_Tracker::get_top_content_pages( 30, 3 );
    $tools_counts = get_transient( 'sb_dashboard_tools_counts' );
    $show_credits_card = 'seobooster' === $ai_provider && Credits_Service::is_registered();
    $credits_balance = ( $show_credits_card ? Credits_Service::get_balance() : 0 );
    $severity_labels = array(
        'critical' => __( 'Critical', 'seo-booster' ),
        'error'    => __( 'Error', 'seo-booster' ),
        'high'     => __( 'High Priority', 'seo-booster' ),
        'warning'  => __( 'Warning', 'seo-booster' ),
        'medium'   => __( 'Medium Priority', 'seo-booster' ),
        'low'      => __( 'Low Priority', 'seo-booster' ),
    );
    ?>

	<div class="sbpanel sb-dashboard-kpis">
		<h2><?php 
    esc_html_e( 'Search performance', 'seo-booster' );
    ?></h2>
			<?php 
    if ( !empty( $dashboard_kpis ) ) {
        ?>
			<div class="sb-kpi-row">
				<?php 
        foreach ( $dashboard_kpis as $kpi_key => $kpi ) {
            ?>
					<?php 
            $is_positive = ( !empty( $kpi['invert'] ) ? $kpi['change'] <= 0 : $kpi['change'] >= 0 );
            $delta_class = ( $is_positive ? 'positive' : 'negative' );
            $sign = ( $kpi['change'] >= 0 ? '+' : '' );
            $display_change = ( !empty( $kpi['invert'] ) ? abs( $kpi['change'] ) : $kpi['change'] );
            $suffix = ( isset( $kpi['suffix'] ) ? $kpi['suffix'] : '' );
            ?>
					<div class="sb-card sb-kpi">
						<span class="sb-kpi__label"><?php 
            echo esc_html( $kpi_labels[$kpi_key] );
            ?></span>
						<div class="sb-kpi__value"><?php 
            echo esc_html( number_format_i18n( $kpi['value'], $kpi['decimals'] ) . $suffix );
            ?></div>
						<div class="sb-kpi__delta">
							<span class="change <?php 
            echo esc_attr( $delta_class );
            ?>">
								<?php 
            echo esc_html( $sign . number_format_i18n( $display_change, $kpi['decimals'] ) . $suffix );
            echo ' (' . esc_html( number_format_i18n( $kpi['percentage'], 1 ) ) . '%)';
            ?>
							</span>
							<span class="screen-reader-text"><?php 
            esc_html_e( 'vs previous 30 days', 'seo-booster' );
            ?></span>
						</div>
					</div>
				<?php 
        }
        ?>
			</div>
		<?php 
    }
    ?>
		<p class="sb-dashboard-summary"><?php 
    echo esc_html( $dashboard_summary );
    ?></p>
		<p class="description"><?php 
    esc_html_e( 'Based on Google Search Console data. Does not represent full site traffic.', 'seo-booster' );
    ?></p>
	</div>

	<div class="sbpanel sb-dashboard-chart">
		<div class="flexrow">
			<div id="sb2canvascont" style="height:550px;display:block;margin:0 0 20px 0;width:100%;">
				<canvas id="seobooster-gsc-chart"><?php 
    esc_html_e( 'Chart', 'seo-booster' );
    ?></canvas>
				<div id="loading-indicator">
					<div id="spinner"></div>
				</div>
			</div>
		</div>
	</div>

	<div class="sbpanel sb-dashboard-working">
		<div class="flexrow">
			<div class="col1 sb-card">
				<h3><?php 
    esc_html_e( 'Top 7 Keywords (Past 30 Days)', 'seo-booster' );
    ?></h3>
						<?php 
    if ( !empty( $top_keywords ) ) {
        ?>
					<table class="widefat fixed striped">
						<thead>
							<tr>
								<th><?php 
        esc_html_e( 'Keyword', 'seo-booster' );
        ?></th>
								<th><?php 
        esc_html_e( 'Views', 'seo-booster' );
        ?></th>
								<th><?php 
        esc_html_e( 'Clicks', 'seo-booster' );
        ?></th>
							</tr>
						</thead>
						<tbody>
							<?php 
        foreach ( $top_keywords as $keyword ) {
            ?>
								<tr>
									<td><?php 
            echo esc_html( $keyword['keyword'] );
            ?></td>
									<td><?php 
            echo esc_html( number_format_i18n( $keyword['views'] ) );
            ?></td>
									<td><?php 
            echo esc_html( number_format_i18n( $keyword['clicks'] ) );
            ?></td>
								</tr>
							<?php 
        }
        ?>
						</tbody>
					</table>
				<?php 
    } else {
        ?>
					<p><?php 
        esc_html_e( 'No keyword data available for the past 30 days.', 'seo-booster' );
        ?></p>
				<?php 
    }
    ?>
				<p class="sb-card__footer">
					<a href="<?php 
    echo esc_url( $gsc_link );
    ?>" class="button button-secondary"><?php 
    esc_html_e( 'GSC Overview', 'seo-booster' );
    ?></a>
				</p>
			</div>
			<div class="col2 sb-card">
				<h3><?php 
    esc_html_e( 'SEO Possibilities', 'seo-booster' );
    ?></h3>
						<?php 
    if ( $possibilities_stats['total_issues'] > 0 ) {
        ?>
					<p class="sb-card__meta">
							<?php 
        printf( esc_html__( '%1$s possibilities detected. Top items to address:', 'seo-booster' ), esc_html( number_format_i18n( $possibilities_stats['total_issues'] ) ) );
        ?>
					</p>
				<?php 
    }
    ?>
						<?php 
    if ( !empty( $top_possibilities ) ) {
        ?>
					<div class="sb-possibilities-list">
							<?php 
        foreach ( array_slice( $top_possibilities, 0, 3 ) as $possibility ) {
            ?>
								<?php 
            $severity = $possibility['severity'];
            $severity_label = ( isset( $severity_labels[$severity] ) ? $severity_labels[$severity] : ucfirst( $severity ) );
            $severity_class = ( isset( $severity_labels[$severity] ) ? $severity : 'low' );
            ?>
							<div class="sb-possibility-item sb-possibility-item--<?php 
            echo esc_attr( $severity_class );
            ?>">
								<div class="sb-possibility-item__header">
									<h4 class="sb-possibility-item__title"><?php 
            echo esc_html( $possibility['message'] );
            ?></h4>
									<span class="sb-severity-chip sb-severity-chip--<?php 
            echo esc_attr( $severity_class );
            ?>"><?php 
            echo esc_html( $severity_label );
            ?></span>
								</div>
								<p class="sb-possibility-item__meta">
									<?php 
            printf( esc_html( _n(
                'Affects %1$s page',
                'Affects %1$s pages',
                $possibility['affected_urls'],
                'seo-booster'
            ) ), esc_html( number_format_i18n( $possibility['affected_urls'] ) ) );
            ?>
								</p>
							</div>
						<?php 
        }
        ?>
					</div>
				<?php 
    } else {
        ?>
					<p class="sb-card__meta"><?php 
        esc_html_e( 'No specific possibilities detected yet. Run an analysis to discover SEO opportunities.', 'seo-booster' );
        ?></p>
				<?php 
    }
    ?>
				<p class="sb-card__footer">
					<a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_seo_issues' ) );
    ?>" class="button button-primary"><?php 
    esc_html_e( 'View All Possibilities', 'seo-booster' );
    ?></a>
				</p>
			</div>
		</div>
	</div>

	<div class="sbpanel sb-dashboard-secondary">
		<div class="sb-card-grid">
			<div class="sb-card">
				<h3><?php 
    esc_html_e( 'Automatic Links', 'seo-booster' );
    ?></h3>
				<p class="sb-card__metric"><?php 
    echo esc_html( number_format_i18n( $autolink_rules ) );
    ?></p>
				<p class="sb-card__meta"><?php 
    esc_html_e( 'Active autolink rules defined.', 'seo-booster' );
    ?></p>
				<p class="sb-card__footer">
					<a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_autolink' ) );
    ?>" class="button button-secondary"><?php 
    esc_html_e( 'Manage links', 'seo-booster' );
    ?></a>
				</p>
			</div>

			<div class="sb-card">
				<h3><?php 
    esc_html_e( 'AI Bots', 'seo-booster' );
    ?></h3>
						<?php 
    if ( !AI_Bot_Tracker::is_tracking_enabled() ) {
        ?>
					<p class="sb-card__meta">
							<?php 
        esc_html_e( 'AI bot tracking is disabled.', 'seo-booster' );
        ?>
						<a href="<?php 
        echo esc_url( admin_url( 'admin.php?page=sb2_settings#ai-llm' ) );
        ?>"><?php 
        esc_html_e( 'Enable in Settings', 'seo-booster' );
        ?></a>
					</p>
				<?php 
    } else {
        ?>
					<p class="sb-card__metric"><?php 
        echo esc_html( number_format_i18n( (int) $ai_bot_summary['total_visits'] ) );
        ?></p>
					<p class="sb-card__meta">
						<?php 
        printf(
            esc_html__( '%1$s pages crawled · %2$s%% mapped content · %3$s research · %4$s citation', 'seo-booster' ),
            esc_html( number_format_i18n( (int) $ai_bot_summary['unique_content_pages'] ) ),
            esc_html( number_format_i18n( (float) $ai_bot_ratio['content_percent'], 1 ) ),
            esc_html( number_format_i18n( (int) $ai_bot_purpose['research'] ) ),
            esc_html( number_format_i18n( (int) $ai_bot_purpose['citation'] ) )
        );
        ?>
					</p>
					<?php 
        if ( !empty( $ai_bot_top_content ) ) {
            ?>
						<ul class="sb-card-list">
							<?php 
            foreach ( $ai_bot_top_content as $ai_row ) {
                ?>
								<?php 
                $ai_label = AI_Bot_Tracker::resolve_object_label( (int) $ai_row['object_id'], $ai_row['object_type'] );
                ?>
								<li><?php 
                echo esc_html( $ai_label['title'] );
                ?> <em>(<?php 
                echo esc_html( number_format_i18n( (int) $ai_row['visits'] ) );
                ?>)</em></li>
							<?php 
            }
            ?>
						</ul>
					<?php 
        }
        ?>
				<?php 
    }
    ?>
				<p class="sb-card__footer">
					<a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_ai_bots&view=content' ) );
    ?>" class="button button-secondary"><?php 
    esc_html_e( 'View AI Bots report', 'seo-booster' );
    ?></a>
				</p>
			</div>

			<div class="sb-card">
				<h3><?php 
    esc_html_e( 'Tools quick wins', 'seo-booster' );
    ?></h3>
						<?php 
    if ( is_array( $tools_counts ) ) {
        ?>
					<p class="sb-card__meta">
							<?php 
        printf( esc_html__( '%1$s images missing alt text · %2$s posts missing SEO meta', 'seo-booster' ), esc_html( number_format_i18n( (int) $tools_counts['images_missing_alt'] ) ), esc_html( number_format_i18n( (int) $tools_counts['posts_missing_meta'] ) ) );
        ?>
					</p>
				<?php 
    } else {
        ?>
					<p class="sb-card__meta"><?php 
        esc_html_e( 'Scan for missing image alt text and SEO titles or descriptions across your site.', 'seo-booster' );
        ?></p>
				<?php 
    }
    ?>
				<p class="sb-card__footer">
					<a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_tools' ) );
    ?>" class="button button-secondary"><?php 
    esc_html_e( 'Open Tools', 'seo-booster' );
    ?></a>
				</p>
			</div>

					<?php 
    if ( $show_credits_card ) {
        ?>
				<div class="sb-card">
					<h3><?php 
        esc_html_e( 'AI Credits', 'seo-booster' );
        ?></h3>
					<p class="sb-card__metric"><?php 
        echo esc_html( number_format_i18n( (int) $credits_balance ) );
        ?></p>
					<p class="sb-card__meta"><?php 
        esc_html_e( 'Remaining SEO Booster Credits balance.', 'seo-booster' );
        ?></p>
					<p class="sb-card__footer">
						<a href="<?php 
        echo esc_url( admin_url( 'admin.php?page=sb2_settings#ai-llm' ) );
        ?>" class="button button-secondary"><?php 
        esc_html_e( 'AI settings', 'seo-booster' );
        ?></a>
					</p>
				</div>
			<?php 
    }
    ?>
		</div>
	</div>

	<div class="sbpanel sb-dashboard-quicklinks-wrap">
		<nav class="sb-dashboard-quicklinks" aria-label="<?php 
    esc_attr_e( 'Dashboard quick links', 'seo-booster' );
    ?>">
			<a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_gsc' ) );
    ?>"><?php 
    esc_html_e( 'GSC Overview', 'seo-booster' );
    ?></a>
			<span class="sb-dashboard-quicklinks__sep" aria-hidden="true">·</span>
			<a href="<?php 
    echo esc_url( home_url( '?seobooster_showdetails=1' ) );
    ?>" target="_blank" rel="noopener noreferrer"><?php 
    esc_html_e( 'Front page keywords', 'seo-booster' );
    ?></a>
			<span class="sb-dashboard-quicklinks__sep" aria-hidden="true">·</span>
			<a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_seo_issues' ) );
    ?>"><?php 
    esc_html_e( 'SEO Possibilities', 'seo-booster' );
    ?></a>
			<span class="sb-dashboard-quicklinks__sep" aria-hidden="true">·</span>
			<a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_autolink' ) );
    ?>"><?php 
    esc_html_e( 'Automatic Links', 'seo-booster' );
    ?></a>
			<span class="sb-dashboard-quicklinks__sep" aria-hidden="true">·</span>
			<a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_tools' ) );
    ?>"><?php 
    esc_html_e( 'Tools', 'seo-booster' );
    ?></a>
		</nav>
	</div>

			<?php 
    $show_pro_card = true;
    if ( $show_pro_card ) {
        $pro_features = array(array(
            'title'       => __( '404 & Redirect Monitoring', 'seo-booster' ),
            'description' => __( 'Track broken URLs and redirects with a searchable admin report.', 'seo-booster' ),
        ), array(
            'title'       => __( 'Autolink Column', 'seo-booster' ),
            'description' => __( 'Enable or disable automatic linking per post from the posts list and Quick Edit.', 'seo-booster' ),
        ));
        $pro_feature = $pro_features[wp_rand( 0, count( $pro_features ) - 1 )];
        $pro_upgrade_url = 'https://seoboosterpro.com';
        if ( function_exists( 'seobooster_fs' ) && method_exists( seobooster_fs(), 'get_upgrade_url' ) ) {
            $pro_upgrade_url = seobooster_fs()->get_upgrade_url();
        }
        ?>
		<div class="sbpanel sb-dashboard-pro">
			<div class="sb-card sb-card--locked">
				<h3><?php 
        echo esc_html( $pro_feature['title'] );
        ?></h3>
				<p class="sb-card__meta"><?php 
        echo esc_html( $pro_feature['description'] );
        ?></p>
				<p class="sb-card__footer">
					<a href="<?php 
        echo esc_url( $pro_upgrade_url );
        ?>" class="button button-secondary" target="_blank" rel="noopener noreferrer"><?php 
        esc_html_e( 'Upgrade to SEO Booster Pro', 'seo-booster' );
        ?></a>
				</p>
			</div>
		</div>
				<?php 
    }
    $timestamp_output = '';
    $timestamp = wp_next_scheduled( 'seobooster_gsc_data_fetch' );
    if ( $timestamp ) {
        $timestamp = gmdate( 'Y-m-d H:i:s', $timestamp );
        $current_time = current_time( 'timestamp' );
        $time_diff = human_time_diff( $current_time, strtotime( $timestamp ) );
        $timestamp_output = ' — <small>' . esc_html__( 'Next scheduled update:', 'seo-booster' ) . ' ' . esc_html( $timestamp ) . ' (' . sprintf( esc_html__( 'in %s', 'seo-booster' ), esc_html( $time_diff ) ) . ')</small>';
    }
    ?>
	<p class="sb-dashboard-status">
				<?php 
    echo wp_kses_post( sprintf( esc_html__( 'Connected to GSC site %s', 'seo-booster' ), '<strong>' . esc_html( $selected_site ) . '</strong>' ) . $timestamp_output );
    ?>
	</p>

			<?php 
}
if ( !($selected_site && 0 < $unique_days) ) {
    ?>
	<div class="sbpanel">
		<div id="inner-welcome">
			<?php 
}
// Show appropriate interface based on authentication and site selection status
if ( !$access_token ) {
    // Check if we're on a local development domain
    $is_local = false;
    $local_domains = array(
        '.local',
        '.test',
        '.dev',
        '.localhost',
        'localhost',
        '127.0.0.1'
    );
    foreach ( $local_domains as $local_domain ) {
        if ( strpos( site_url(), $local_domain ) !== false ) {
            $is_local = true;
            break;
        }
    }
    $google_email = get_option( 'seobooster_google_email' );
    ?>
		<div class="flexrow">
			<div class="col1">
				<h3>
						<?php 
    if ( !empty( $google_email ) ) {
        esc_html_e( 'Reauthenticate with Google', 'seo-booster' );
    } else {
        esc_html_e( 'Authenticate with Google', 'seo-booster' );
    }
    ?>
				</h3>
				
				<?php 
    if ( !empty( $google_email ) ) {
        ?>
					<p><?php 
        echo esc_html( $google_email );
        ?></p>
				<?php 
    } else {
        ?>
					<p><?php 
        esc_html_e( 'You need to connect your Google account to use this feature.', 'seo-booster' );
        ?></p>
				<?php 
    }
    ?>

				<p>
					<a href="
					<?php 
    echo esc_url( add_query_arg( array(
        'install_id' => $install_id,
        'auth_token' => $authentication_string,
        'return_to'  => $return_to,
    ), 'https://seoboosterauth.com/auth' ) );
    ?>
					" class="button button-primary button-hero">
								<?php 
    esc_html_e( 'Start Authentication', 'seo-booster' );
    ?>
					</a>
				</p>
				<p><?php 
    esc_html_e( 'You will be taken to Google to authorize your account.', 'seo-booster' );
    ?></p>
				<p><?php 
    esc_html_e( 'Connect to the API to gather data from GSC via seoboosterauth.com.', 'seo-booster' );
    ?></p>
				
						<?php 
    // Display warning and help for local domains
    if ( $is_local ) {
        echo '<div class="notice seobooster-notice">';
        echo '<p><strong>' . esc_html__( 'Warning: Local Development Domain Detected', 'seo-booster' ) . '</strong></p>';
        echo '<p>' . esc_html__( 'Authentication with Google may fail because you are using a local development domain. OAuth services typically reject callbacks to non-public domains for security reasons.', 'seo-booster' ) . '</p>';
        echo '<p><a href="#" class="button show-local-domain-info">' . esc_html__( 'Show More Information', 'seo-booster' ) . '</a></p>';
        echo '<div class="local-domain-details" style="display:none;">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_local_domain_info() returns pre-escaped HTML.
        echo Google_API::get_local_domain_info();
        echo '</div>';
        echo '</div>';
    }
    ?>
			</div>
		</div>
			<?php 
} elseif ( $access_token && !$selected_site ) {
    // Check if we're on a local development domain
    $is_local = false;
    $local_domains = array(
        '.local',
        '.test',
        '.dev',
        '.localhost',
        'localhost',
        '127.0.0.1'
    );
    foreach ( $local_domains as $local_domain ) {
        if ( strpos( site_url(), $local_domain ) !== false ) {
            $is_local = true;
            break;
        }
    }
    $google_email = get_option( 'seobooster_google_email' );
    // Site selection form - only show when we have access token, Google email, and sites
    if ( !empty( $google_email ) ) {
        // Try to fetch sites if not already done
        $sites = Google_API::fetch_sites();
        // Handle different scenarios
        if ( is_wp_error( $sites ) ) {
            ?>
			<div class="notice notice-error seobooster-notice">
				<p><?php 
            echo esc_html( $sites->get_error_message() );
            ?></p>
				<p><a href="
					<?php 
            echo esc_url( add_query_arg( array(
                'install_id' => $install_id,
                'auth_token' => $authentication_string,
                'return_to'  => $return_to,
            ), 'https://seoboosterauth.com/auth' ) );
            ?>
				" class="button button-primary">
							<?php 
            esc_html_e( 'Re-authenticate with Google', 'seo-booster' );
            ?>
				</a></p>
			</div>
			
				<?php 
        } elseif ( empty( $sites ) ) {
            ?>
			<div class="notice notice-warning seobooster-notice">
				<p><?php 
            esc_html_e( 'No sites found in your Google Search Console account. Make sure you have added and verified at least one site in Google Search Console.', 'seo-booster' );
            ?></p>
				<p><a href="https://search.google.com/search-console/welcome" target="_blank" class="button">
					<?php 
            esc_html_e( 'Open Google Search Console', 'seo-booster' );
            ?>
				</a></p>
			</div>
			
		<?php 
        } else {
            ?>
			<div class="seobooster-notice">
				<h3><?php 
            esc_html_e( 'Select Google Search Console Site', 'seo-booster' );
            ?></h3>
				<p class="great-connected">
					<?php 
            printf( esc_html__( 'Great, you have now connected your Google account (%s).', 'seo-booster' ), '<strong>' . esc_html( $google_email ) . '</strong>' );
            ?>
				</p>
				
				<form method="post">
					<div id="choosecont">
						<div class="col">
							<?php 
            $site_url = esc_url( site_url( '/' ) );
            ?>
							<select name="seobooster_selected_site">
								<option value="" disabled selected><?php 
            esc_html_e( 'Please select a site to continue', 'seo-booster' );
            ?></option>
								
								<?php 
            // Group sites by permission level
            $available_sites = array();
            $unavailable_sites = array();
            foreach ( $sites as $site ) {
                if ( empty( $site ) ) {
                    continue;
                }
                // Handle both array and string formats
                $site_data = array();
                $site_data['url'] = ( is_array( $site ) ? $site['siteUrl'] : $site );
                $site_data['permission'] = ( is_array( $site ) && isset( $site['permissionLevel'] ) ? $site['permissionLevel'] : '' );
                // Sites with siteUnverifiedUser permission don't have enough access
                if ( $site_data['permission'] === 'siteUnverifiedUser' ) {
                    $unavailable_sites[] = $site_data;
                } else {
                    $available_sites[] = $site_data;
                }
            }
            // Sort both arrays alphabetically
            usort( $available_sites, function ( $a, $b ) {
                $a_clean = str_replace( 'sc-domain:', '', $a['url'] );
                $b_clean = str_replace( 'sc-domain:', '', $b['url'] );
                return strcasecmp( $a_clean, $b_clean );
            } );
            usort( $unavailable_sites, function ( $a, $b ) {
                $a_clean = str_replace( 'sc-domain:', '', $a['url'] );
                $b_clean = str_replace( 'sc-domain:', '', $b['url'] );
                return strcasecmp( $a_clean, $b_clean );
            } );
            // Display available sites first
            foreach ( $available_sites as $site_data ) {
                $value = $site_data['url'];
                $permission = $site_data['permission'];
                // Remove 'sc-domain:' from display label
                $label = str_replace( 'sc-domain:', '', $value );
                // Add domain verified text if it's a domain property
                if ( strpos( $value, 'sc-domain:' ) === 0 ) {
                    $label .= ' ' . esc_html__( '(domain verified)', 'seo-booster' );
                }
                ?>
									<option value="<?php 
                echo esc_attr( $value );
                ?>" <?php 
                selected( $value, $site_url );
                ?>>
										<?php 
                echo esc_html( $label );
                ?>
									</option>
									<?php 
            }
            // Only add divider and unavailable sites if there are any
            if ( !empty( $unavailable_sites ) ) {
                // Add a divider
                ?>
									<option disabled>───────────────────</option>
									<option value="" disabled><?php 
                esc_html_e( 'Insufficient Permissions', 'seo-booster' );
                ?></option>
									<?php 
                // Display unavailable sites
                foreach ( $unavailable_sites as $site_data ) {
                    $value = $site_data['url'];
                    $permission = $site_data['permission'];
                    // Remove 'sc-domain:' from display label
                    $label = str_replace( 'sc-domain:', '', $value );
                    // Add domain verified text if it's a domain property
                    if ( strpos( $value, 'sc-domain:' ) === 0 ) {
                        $label .= ' ' . esc_html__( '(domain verified)', 'seo-booster' );
                    }
                    // Add permission level if available
                    if ( !empty( $permission ) ) {
                        $label .= ' - ' . esc_html( $permission );
                    }
                    ?>
										<option value="<?php 
                    echo esc_attr( $value );
                    ?>" <?php 
                    selected( $value, $site_url );
                    ?>>
											<?php 
                    echo esc_html( $label );
                    ?>
										</option>
										<?php 
                }
            }
            ?>
							</select>
						</div>

						<div class="col">
							<?php 
            wp_nonce_field( 'seobooster_save_selected_site', 'seobooster_selected_site_nonce' );
            ?>
							<input type="hidden" name="seobooster_selected_days" value="90">
							<input type="submit" name="submit" value="<?php 
            esc_attr_e( 'Get keyword data', 'seo-booster' );
            ?>" class="button button-primary" id="seobooster2selectsite">
						</div>
					</div>
				</form>

				<div id="seobooster-api-error"></div>
				<div id="seobooster-api-status"><span class="spinner is-active"></span></div>
			</div>
		<?php 
        }
        ?>
			<?php 
    }
    ?>
			<?php 
}
if ( !($selected_site && 0 < $unique_days) ) {
    ?>
	</div><!-- #inner-welcome -->
	</div><!-- .sbpanel -->
			<?php 
}
if ( (!isset( $total_keywords ) || '0' === $total_keywords || 0 >= $unique_days) && seobooster_fs()->is_registered() && seobooster_fs()->is_tracking_allowed() && $access_token && $selected_site ) {
    ?>

		<div class="helpcont">
			<div class="helpbox">
				<h3>
				<?php 
    esc_html_e( 'No data collected yet - import data', 'seo-booster' );
    ?>
	</h3>

				<p>
				<?php 
    esc_html_e( 'It looks like no data has been collected yet. To get started:', 'seo-booster' );
    ?>
	</p>
				<ol>
					<li>
					<?php 
    esc_html_e( 'Go to the Settings page', 'seo-booster' );
    ?>
	</li>
					<li>
					<?php 
    esc_html_e( 'Find the "Reimport" function', 'seo-booster' );
    ?>
	</li>
					<li>
					<?php 
    esc_html_e( 'Use it to import your Google Search Console data', 'seo-booster' );
    ?>
	</li>
				</ol>
				<p>
				<?php 
    esc_html_e( 'This will populate your dashboard with valuable SEO insights.', 'seo-booster' );
    ?>
	</p>
				<p><a href="
				<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_settings#manualupdate' ) );
    ?>
	">
			<?php 
    esc_html_e( 'Go to the Settings page', 'seo-booster' );
    ?>
	</a></p>
			</div>
		</div>
			<?php 
} elseif ( $access_token && $selected_site && (!isset( $total_keywords ) || '0' === $total_keywords || 0 >= $unique_days) ) {
    // Show message when authenticated and site selected but no data
    ?>
	<div class="sbpanel">
		<div class="flexrow">
			<div class="col1">
				<h3><?php 
    esc_html_e( 'No Data Available', 'seo-booster' );
    ?></h3>
				<p><?php 
    esc_html_e( 'You are authenticated and have selected a site, but no data has been imported yet.', 'seo-booster' );
    ?></p>
				<p><a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_settings#manualupdate' ) );
    ?>" class="button button-primary"><?php 
    esc_html_e( 'Import Data from Settings', 'seo-booster' );
    ?></a></p>
			</div>
		</div>
	</div>
			<?php 
}
?>

</div> <!-- .wrap --><?php 