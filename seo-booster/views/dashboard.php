<?php

namespace Cleverplugins\SEOBooster;

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

$ai_provider = get_option('seobooster_ai_provider', 'disabled');
$wp_ai_ready = function_exists('wp_ai_client_prompt');
$show_ai_notice = false;
$ai_notice_link = admin_url('admin.php?page=sb2_settings#ai-llm');
$ai_notice_text = '';

if ($ai_provider === 'disabled') {
    $show_ai_notice = true;
    $ai_notice_text = __('Enable AI in SEO Booster Settings to get AI-generated SEO suggestions and image meta.', 'seo-booster');
} elseif ($ai_provider === 'wordpress' && !$wp_ai_ready) {
    $show_ai_notice = true;
    $ai_notice_link = admin_url('options-connectors.php');
    $ai_notice_text = __('WordPress AI is not available. Use WordPress 7 or later and configure Settings → Connectors.', 'seo-booster');
} elseif ($ai_provider === 'seobooster' && \Cleverplugins\SEOBooster\Credits_Service::is_ai_provider_available() && !\Cleverplugins\SEOBooster\Credits_Service::is_registered()) {
    $show_ai_notice = true;
    $ai_notice_text = __('Connect your SEO Booster Credits account in Settings to use AI features.', 'seo-booster');
}

if ($show_ai_notice):
?>
<div class="notice notice-info is-dismissible seobooster-notice" style="margin: 20px 0; padding: 15px; border-left: 4px solid #0073aa;">
    <h3 style="margin-top: 0; color: #0073aa;"><?php esc_html_e('Unlock AI-Powered SEO Features', 'seo-booster'); ?></h3>
    <p style="margin-bottom: 10px;"><?php echo esc_html($ai_notice_text); ?></p>
    <p style="margin-bottom: 0;">
        <a href="<?php echo esc_url($ai_notice_link); ?>" class="button button-primary">
            <?php echo $ai_provider === 'wordpress' && !$wp_ai_ready ? esc_html__('Open Settings → Connectors', 'seo-booster') : esc_html__('SEO Booster Settings', 'seo-booster'); ?>
        </a>
    </p>
</div>
<?php endif; ?>

	<div class="sbpanel">



		<div id="inner-welcome">
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
'total_keywords'      => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT query) 
FROM {$wpdb->prefix}sb2_query_keywords 
WHERE %s = %s", '1', '1' ) ),
'unique_pages'        => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT page) 
FROM {$wpdb->prefix}sb2_query_keywords 
WHERE %s = %s", '1', '1' ) ),
'unique_days'         => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT date) 
FROM {$wpdb->prefix}sb2_query_keywords_history 
WHERE %s = %s", '1', '1' ) ),
'first_history_date'  => $wpdb->get_var( $wpdb->prepare( "SELECT MIN(date) 
FROM {$wpdb->prefix}sb2_query_keywords_history 
WHERE %s = %s", '1', '1' ) ),
'latest_history_date' => $wpdb->get_var( $wpdb->prepare( "SELECT MAX(date) 
FROM {$wpdb->prefix}sb2_query_keywords_history 
WHERE %s = %s", '1', '1' ) ),
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

$overview_message = '';

$overview_message .= '<p class="quickie">' . sprintf( 
    // translators: 1: number of keywords, 2: number of pages
    __( 'A total of <span>%1$s</span> different keyword terms have been used to find <span>%2$s</span> different pages on your website.', 'seo-booster' ). '</p>',
    number_format_i18n( $total_keywords ),
    number_format_i18n( $unique_pages )
 );


$overview_message .= '<p class="quickie">' . sprintf(
    // translators: 1: number of days, 2: first date, 3: last date
    _n(
        'There is data from Google Search Console for <span>%1$s</span> unique day, from <span>%2$s</span> to <span>%3$s</span>.',
        'There is data from Google Search Console for <span>%1$s</span> unique days, from <span>%2$s</span> to <span>%3$s</span>.',
        $unique_days,
        'seo-booster'
    ).'</p>',
    number_format_i18n( $unique_days ),
    $first_history_date_str,
    $latest_history_date_str
);

// First, get the latest date with data
$latest_date = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT MAX(date) 
        FROM {$wpdb->prefix}sb2_query_keywords_history 
        WHERE %s = %s",
        '1', '1'
    )
);

if ($latest_date) {
    $past_30_days_data = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT 
                SUM(h.impressions) AS total_impressions, 
                SUM(h.clicks) AS total_clicks, 
                AVG(h.position) AS avg_position, 
                AVG(h.ctr) AS avg_ctr
            FROM 
                {$wpdb->prefix}sb2_query_keywords_history AS h
            WHERE 
                h.date BETWEEN DATE_SUB(%s, INTERVAL 30 DAY) AND %s",
            $latest_date,
            $latest_date
        ),
        OBJECT
    );

    $previous_30_days_data = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT 
                SUM(h.impressions) AS total_impressions, 
                SUM(h.clicks) AS total_clicks, 
                AVG(h.position) AS avg_position, 
                AVG(h.ctr) AS avg_ctr
            FROM 
                {$wpdb->prefix}sb2_query_keywords_history AS h
            WHERE 
                h.date BETWEEN DATE_SUB(%s, INTERVAL 60 DAY) AND DATE_SUB(%s, INTERVAL 30 DAY)",
            $latest_date,
            $latest_date
        ),
        OBJECT
    );

    if ($past_30_days_data && $previous_30_days_data) {
        $past_30_days = $past_30_days_data[0];
        $previous_30_days = $previous_30_days_data[0];

        $impressions_change = $past_30_days->total_impressions - $previous_30_days->total_impressions;
        $clicks_change = $past_30_days->total_clicks - $previous_30_days->total_clicks;
        $position_change = $past_30_days->avg_position - $previous_30_days->avg_position;
        $ctr_change = ($past_30_days->avg_ctr - $previous_30_days->avg_ctr) * 100; // Convert to percentage

        // Calculate percentage changes
        $impressions_percentage = $previous_30_days->total_impressions > 0 ? 
            round(($impressions_change / $previous_30_days->total_impressions) * 100, 1) : 0;
        $clicks_percentage = $previous_30_days->total_clicks > 0 ? 
            round(($clicks_change / $previous_30_days->total_clicks) * 100, 1) : 0;
        $position_percentage = $previous_30_days->avg_position > 0 ? 
            round(($position_change / $previous_30_days->avg_position) * 100, 1) : 0;
        $ctr_percentage = $previous_30_days->avg_ctr > 0 ? 
            round(($ctr_change / ($previous_30_days->avg_ctr * 100)) * 100, 1) : 0;

        $impressions_message = $impressions_change >= 0 ? 'increased' : 'decreased';
        $clicks_message = $clicks_change >= 0 ? 'increased' : 'decreased';
        $position_message = $position_change <= 0 ? 'improved' : 'worsened';
        $ctr_message = $ctr_change >= 0 ? 'increased' : 'decreased';

        
        // Executive summary for impressions and clicks
        $overview_message .= '<p class="quickie">';
        $overview_message .= sprintf(
            __('In the last 30 days, your content was shown <strong>%s times</strong> in search results %s and received <strong>%s clicks</strong> %s.', 'seo-booster'),
            number_format_i18n($past_30_days->total_impressions),
            sprintf(
                '(<span class="change %s">%s%s, %s%%</span>)',
                $impressions_change >= 0 ? 'positive' : 'negative',
                $impressions_change >= 0 ? '+' : '',
                number_format_i18n($impressions_change),
                $impressions_percentage
            ),
            number_format_i18n($past_30_days->total_clicks),
            sprintf(
                '(<span class="change %s">%s%s, %s%%</span>)',
                $clicks_change >= 0 ? 'positive' : 'negative',
                $clicks_change >= 0 ? '+' : '',
                number_format_i18n($clicks_change),
                $clicks_percentage
            )
        );
        $overview_message .= '</p>';

        // Technical metrics in a compact format
        $overview_message .= '<p class="technical-metrics">';
        $overview_message .= sprintf(
            __('Technical metrics: Average Position: <strong>%1$s</strong> (%2$s%3$s, %4$s%%) • CTR: <strong>%5$s%%</strong> (%6$s%7$s%%)', 'seo-booster'),
            number_format_i18n($past_30_days->avg_position, 1),
            $position_change <= 0 ? '+' : '',  // Note: For position, negative change is good
            number_format_i18n(abs($position_change), 2),
            $position_percentage,
            number_format_i18n($past_30_days->avg_ctr * 100, 2),
            $ctr_change >= 0 ? '+' : '',
            number_format_i18n($ctr_change, 2)
        );
        $overview_message .= '</p>';
        
    } 
}

$overview_message .= '<small>'. __('The data is based on the data collected from Google Search Console. It does not represent a full picture of your website\'s traffic.', 'seo-booster') .'</small>';
 

$seobooster_weekly_email = get_option( 'seobooster_weekly_email' );
if ( isset( $_GET['gsc_updated'] ) && $_GET['gsc_updated'] == '1' && !$seobooster_weekly_email ) {
    ?>
			<div id="seobooster_email_container" class="notice notice-success is-dismissible seobooster-notice">
				<div class="innercont">
				<h2><?php 
    esc_html_e( 'Import complete!', 'seo-booster' );
    ?></h2>
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
										<input type="text" name="seobooster_email" id="seobooster_email" class="regular-text" value="<?php 
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
// Lars - commented out for now
/*
else {
	// We need to set it up
	Google_API::display_auth_status();
}
*/
if ( $selected_site && 0 < $unique_days ) {
    $gsc_link = admin_url( 'admin.php?page=sb2_gsc' );

?>
    <div class="flexrow">
        <div class="col1">
        <?php
    echo '<h2>' . esc_html__( 'Quick Overview', 'seo-booster' ) . '</h2>';


    // Auto-validate token and clear reauth flag if possible
    Google_API::auto_validate_and_clear_reauth();
    
    $need_reauth = get_option('seobooster_needs_reauth');

    if ($need_reauth) {
        echo '<div class="notice notice-error seobooster-notice"><p>';
        echo '<strong>' . esc_html__( 'Authentication Issue Detected', 'seo-booster' ) . '</strong><br>';
        echo esc_html__( 'Your Google Search Console access token appears to be invalid or expired. This can happen if:', 'seo-booster' );
        echo '<ul style="margin-left: 20px; margin-top: 5px;">';
        echo '<li>' . esc_html__( 'The token has expired (tokens typically last 1 hour)', 'seo-booster' ) . '</li>';
        echo '<li>' . esc_html__( 'Google has revoked access to your account', 'seo-booster' ) . '</li>';
        echo '<li>' . esc_html__( 'There was a network connectivity issue', 'seo-booster' ) . '</li>';
        echo '</ul>';

        $google_email = get_option('seobooster_google_email');
        if ($google_email) {
            echo '<p><strong>' . esc_html__( 'Connected Account:', 'seo-booster' ) . '</strong> ' . esc_html( $google_email ) . '</p>';
        }

        echo '</p>';
        echo '<p>';
        echo '<button type="button" id="try-validate-token" class="button button-secondary" style="margin-right: 10px;">' . esc_html__( 'Try Again', 'seo-booster' ) . '</button>';
        echo '<a href="'.esc_url( add_query_arg( array(
                'install_id' => $install_id,
                'auth_token' => $authentication_string,
                'return_to'  => $return_to,
            ), 'https://seoboosterauth.com/auth' ) ).'" target="_blank" class="button button-primary">'.esc_html__( 'Re-authenticate with Google', 'seo-booster' ).'</a>';
        echo '</p>';
        echo '<div id="validation-status" style="display:none; margin-top: 10px;"></div>';
        echo '</div>';
        
        echo '<script>';
        echo 'jQuery(document).ready(function($) {';
        echo '    $("#try-validate-token").on("click", function() {';
        echo '        var button = $(this);';
        echo '        var status = $("#validation-status");';
        echo '        button.prop("disabled", true).text("' . esc_js(__('Validating...', 'seo-booster')) . '");';
        echo '        status.html("<span style=\"color: #0073aa;\">' . esc_js(__('Checking token validity...', 'seo-booster')) . '</span>").show();';
        echo '        $.ajax({';
        echo '            url: ajaxurl,';
        echo '            type: "POST",';
        echo '            data: {';
        echo '                action: "manual_token_refresh",';
        echo '                nonce: "' . wp_create_nonce('seobooster_token_refresh') . '"';
        echo '            },';
        echo '            success: function(response) {';
        echo '                if (response.valid) {';
        echo '                    status.html("<span style=\"color: green;\">✓ ' . esc_js(__('Token is valid! Refreshing page...', 'seo-booster')) . '</span>");';
        echo '                    setTimeout(function() {';
        echo '                        location.reload();';
        echo '                    }, 2000);';
        echo '                } else {';
        echo '                    status.html("<span style=\"color: red;\">✗ " + response.message + "</span>");';
        echo '                }';
        echo '            },';
        echo '            error: function() {';
        echo '                status.html("<span style=\"color: red;\">' . esc_js(__('Error checking token', 'seo-booster')) . '</span>");';
        echo '            },';
        echo '            complete: function() {';
        echo '                button.prop("disabled", false).text("' . esc_js(__('Try Again', 'seo-booster')) . '");';
        echo '            }';
        echo '        });';
        echo '    });';
        echo '});';
        echo '</script>';
    } else {
        // Display authentication status and site selection
        $access_token = Google_API::get_access_token();
        $selected_site = get_option('seobooster_selected_site');
        $google_email = get_option('seobooster_google_email');
        
        // Check if we're on a local development domain
        $is_local = false;
        $local_domains = array('.local', '.test', '.dev', '.localhost', 'localhost', '127.0.0.1');
        foreach ($local_domains as $local_domain) {
            if (strpos(site_url(), $local_domain) !== false) {
                $is_local = true;
                break;
            }
        }
        
        // Site selection form - only show when we have access token, Google email, and sites
        if ($access_token && !$selected_site && !empty($google_email)): 
            // Try to fetch sites if not already done
            $sites = Google_API::fetch_sites();
            
            // Handle different scenarios
            if (is_wp_error($sites)): ?>
                <div class="notice notice-error seobooster-notice">
                    <p><?php echo esc_html($sites->get_error_message()); ?></p>
                    <p><a href="<?php 
                        echo esc_url(add_query_arg(array(
                            'install_id' => $install_id,
                            'auth_token' => $authentication_string,
                            'return_to'  => $return_to,
                        ), 'https://seoboosterauth.com/auth')); 
                    ?>" class="button button-primary">
                        <?php esc_html_e('Re-authenticate with Google', 'seo-booster'); ?>
                    </a></p>
                </div>
                
            <?php elseif (empty($sites)): ?>
                <div class="notice notice-warning seobooster-notice">
                    <p><?php esc_html_e('No sites found in your Google Search Console account. Make sure you have added and verified at least one site in Google Search Console.', 'seo-booster'); ?></p>
                    <p><a href="https://search.google.com/search-console/welcome" target="_blank" class="button">
                        <?php esc_html_e('Open Google Search Console', 'seo-booster'); ?>
                    </a></p>
                </div>
                
            <?php else: ?>
                <div class=" notice-info seobooster-notice">
                    <h3><?php esc_html_e('Select Google Search Console Site', 'seo-booster'); ?></h3>
                    <p class="great-connected">
                        <?php 
                        printf(
                            esc_html__('Great, you have now connected your Google account (%s).', 'seo-booster'),
                            '<strong>' . esc_html($google_email) . '</strong>'
                        ); 
                        ?>
                    </p>
                    
                    <form method="post">
                        <div id="choosecont">
                            <div class="col">
                                <?php
                                $site_url = esc_url(site_url('/')); ?>
                                <select name="seobooster_selected_site">
                                    <option value="" disabled selected><?php esc_html_e('Please select a site to continue', 'seo-booster'); ?></option>
                                    
                                    <?php
                                    // Group sites by permission level
                                    $available_sites = [];
                                    $unavailable_sites = [];
                                    
                                    foreach ($sites as $site) {
                                        if (empty($site)) {
                                            continue;
                                        }
                                        
                                        // Handle both array and string formats
                                        $site_data = [];
                                        $site_data['url'] = is_array($site) ? $site['siteUrl'] : $site;
                                        $site_data['permission'] = is_array($site) && isset($site['permissionLevel']) ? $site['permissionLevel'] : '';
                                        
                                        // Sites with siteUnverifiedUser permission don't have enough access
                                        if ($site_data['permission'] === 'siteUnverifiedUser') {
                                            $unavailable_sites[] = $site_data;
                                        } else {
                                            $available_sites[] = $site_data;
                                        }
                                    }
                                    
                                    // Sort both arrays alphabetically
                                    usort($available_sites, function($a, $b) {
                                        $a_clean = str_replace('sc-domain:', '', $a['url']);
                                        $b_clean = str_replace('sc-domain:', '', $b['url']);
                                        return strcasecmp($a_clean, $b_clean);
                                    });
                                    
                                    usort($unavailable_sites, function($a, $b) {
                                        $a_clean = str_replace('sc-domain:', '', $a['url']);
                                        $b_clean = str_replace('sc-domain:', '', $b['url']);
                                        return strcasecmp($a_clean, $b_clean);
                                    });
                                    
                                    // Display available sites first
                                    foreach ($available_sites as $site_data) {
                                        $value = $site_data['url'];
                                        $permission = $site_data['permission'];
                                        
                                        // Remove 'sc-domain:' from display label
                                        $label = str_replace('sc-domain:', '', $value);
                                        
                                        // Add domain verified text if it's a domain property
                                        if (strpos($value, 'sc-domain:') === 0) {
                                            $label .= ' ' . esc_html__('(domain verified)', 'seo-booster');
                                        }
                                        ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $site_url); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                        <?php
                                    }
                                    
                                    // Only add divider and unavailable sites if there are any
                                    if (!empty($unavailable_sites)) {
                                        // Add a divider
                                        ?>
                                        <option disabled>───────────────────</option>
                                        <option value="" disabled><?php esc_html_e('Insufficient Permissions', 'seo-booster'); ?></option>
                                        <?php
                                        
                                        // Display unavailable sites
                                        foreach ($unavailable_sites as $site_data) {
                                            $value = $site_data['url'];
                                            $permission = $site_data['permission'];
                                            
                                            // Remove 'sc-domain:' from display label
                                            $label = str_replace('sc-domain:', '', $value);
                                            
                                            // Add domain verified text if it's a domain property
                                            if (strpos($value, 'sc-domain:') === 0) {
                                                $label .= ' ' . esc_html__('(domain verified)', 'seo-booster');
                                            }
                                            
                                            // Add permission level if available
                                            if (!empty($permission)) {
                                                $label .= ' - ' . esc_html($permission);
                                            }
                                            ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $site_url); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                            <?php
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col">
                                <?php wp_nonce_field('seobooster_save_selected_site', 'seobooster_selected_site_nonce'); ?>
                                <input type="hidden" name="seobooster_selected_days" value="90">
                                <input type="submit" name="submit" value="<?php esc_attr_e('Get keyword data', 'seo-booster'); ?>" class="button button-primary" id="seobooster2selectsite">
                            </div>
                        </div>
                    </form>

                    <div id="seobooster-api-error"></div>
                    <div id="seobooster-api-status"><span class="spinner is-active"></span></div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php 
        // Show authentication button when either access token or google email is missing
        if (!$access_token || empty($google_email)): 
        ?>
                <h3>
                    <?php 
                    if (!empty($selected_site) || !empty($google_email)) {
                        esc_html_e('Reauthenticate with Google', 'seo-booster');
                    } else {
                        esc_html_e('Authenticate with Google', 'seo-booster');
                    }
                    ?>
                </h3>
                
                <?php if (!empty($google_email)): ?>
                    <p><?php echo esc_html($google_email); ?></p>
                <?php else: ?>
                    <p><?php esc_html_e('You need to connect your Google account to use this feature.', 'seo-booster'); ?></p>
                <?php endif; ?>

                <p>
                    <a href="<?php 
                        echo esc_url(add_query_arg(array(
                            'install_id' => $install_id,
                            'auth_token' => $authentication_string,
                            'return_to'  => $return_to,
                        ), 'https://seoboosterauth.com/auth'));
                    ?>" class="button button-primary button-hero">
                        <?php esc_html_e('Start Authentication', 'seo-booster'); ?>
                    </a>
                </p>
                <p><?php esc_html_e('You will be taken to Google to authorize your account.', 'seo-booster'); ?></p>
                <p><?php esc_html_e('Connect to the API to gather data from GSC via seoboosterauth.com.', 'seo-booster'); ?></p>
                
                <?php 
                // Display warning and help for local domains
                if ($is_local): 
                    
                    echo '<div class="notice seobooster-notice">';
                    echo '<p><strong>' . esc_html__('Warning: Local Development Domain Detected', 'seo-booster') . '</strong></p>';
                    echo '<p>' . esc_html__('Authentication with Google may fail because you are using a local development domain. OAuth services typically reject callbacks to non-public domains for security reasons.', 'seo-booster') . '</p>';
                    echo '<p><a href="#" class="button show-local-domain-info">' . esc_html__('Show More Information', 'seo-booster') . '</a></p>';
                    echo '<div class="local-domain-details" style="display:none;">';
                    echo Google_API::get_local_domain_info();
                    echo '</div>';
                    echo '</div>';
                    

                endif;
                ?>
        <?php endif; ?>

        <?php if ($access_token && $selected_site && !empty($google_email)):
            
            /*
            ?>
            <div class="notice notice-success seobooster-notice">
                <p>
                    <small>
                        <a href="<?php 
                            echo esc_url(add_query_arg(array(
                                'install_id' => $install_id,
                                'auth_token' => $authentication_string,
                                'return_to'  => $return_to,
                            ), 'https://seoboosterauth.com/auth'));
                        ?>" target="_blank">
                            <?php esc_html_e('Reauthenticate with Google', 'seo-booster'); ?>
                        </a>
                    </small>
                </p>
            </div>
        <?php
    */
    endif; ?>
    <?php } // End of else block for $need_reauth
    
    // Display the overview
    echo wp_kses_post( '<p class="quickie">' . $overview_message . '</p>' );
    ?></div>
        <div class="col2">
        <?php
        // Fetch the top 10 keywords used in the past 30 days
        $top_keywords = Utils::get_top_keywords(30, 7);

        if (!empty($top_keywords)) {
            echo '<h3>' . esc_html__('Top 7 Keywords (Past 30 Days)', 'seo-booster') . '</h3>';
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Keyword', 'seo-booster') . '</th>';
            echo '<th>' . esc_html__('Views', 'seo-booster') . '</th>';
            echo '<th>' . esc_html__('Clicks', 'seo-booster') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ($top_keywords as $keyword) {
                echo '<tr>';
                echo '<td>' . esc_html($keyword['keyword']) . '</td>';
                echo '<td>' . esc_html(number_format_i18n($keyword['views'])) . '</td>';
                echo '<td>' . esc_html(number_format_i18n($keyword['clicks'])) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>' . esc_html__('No keyword data available for the past 30 days.', 'seo-booster') . '</p>';
        }
        ?>
        </div>
    </div>
    </div>
    </div>
    
    <?php
    // Display SEO Possibilities section - integrated with GSC insights
    require_once SEOBOOSTER_PLUGINPATH . 'inc/SEO_Issues_Manager.php';
    $top_possibilities = SEO_Issues_Manager::get_top_possibilities_for_dashboard(5);
    $possibilities_stats = SEO_Issues_Manager::get_analysis_stats();
    
    if (!empty($top_possibilities) || $possibilities_stats['total_issues'] > 0) {
        ?>
        <div class="sbpanel">
            <div class="flexrow">
                <div class="col1" style="width: 100%;">
                    <h2><?php esc_html_e('SEO Possibilities', 'seo-booster'); ?></h2>
                    
                    <?php if ($possibilities_stats['total_issues'] > 0) { ?>
                        <p class="quickie">
                            <?php
                            printf(
                                esc_html__('We\'ve detected %1$s SEO possibilities across your site. Here are the most impactful ones to address:', 'seo-booster'),
                                '<strong>' . number_format_i18n($possibilities_stats['total_issues']) . '</strong>'
                            );
                            ?>
                        </p>
                    <?php } ?>
                    
                    <?php if (!empty($top_possibilities)) { ?>
                        <div class="sb-possibilities-list" style="margin-top: 20px;">
                            <?php
                            $severity_labels = [
                                'critical' => __('Critical', 'seo-booster'),
                                'error' => __('Error', 'seo-booster'),
                                'high' => __('High Priority', 'seo-booster'),
                                'warning' => __('Warning', 'seo-booster'),
                                'medium' => __('Medium Priority', 'seo-booster'),
                                'low' => __('Low Priority', 'seo-booster')
                            ];
                            
                            $severity_colors = [
                                'critical' => '#dc3232',
                                'error' => '#dc3232',
                                'high' => '#f56e28',
                                'warning' => '#ffb900',
                                'medium' => '#00a0d2',
                                'low' => '#8c8f94'
                            ];
                            
                            foreach ($top_possibilities as $possibility) {
                                $severity = $possibility['severity'];
                                $severity_label = isset($severity_labels[$severity]) ? $severity_labels[$severity] : ucfirst($severity);
                                $severity_color = isset($severity_colors[$severity]) ? $severity_colors[$severity] : '#8c8f94';
                                ?>
                                <div class="sb-possibility-item" style="
                                    border-left: 4px solid <?php echo esc_attr($severity_color); ?>;
                                    padding: 15px;
                                    margin-bottom: 15px;
                                    background: #fff;
                                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                                ">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                        <h3 style="margin: 0 0 5px 0; font-size: 16px; font-weight: 600;">
                                            <?php echo esc_html($possibility['message']); ?>
                                        </h3>
                                        <span style="
                                            background: <?php echo esc_attr($severity_color); ?>;
                                            color: #fff;
                                            padding: 3px 10px;
                                            border-radius: 3px;
                                            font-size: 11px;
                                            font-weight: 600;
                                            text-transform: uppercase;
                                        "><?php echo esc_html($severity_label); ?></span>
                                    </div>
                                    <p style="margin: 0; color: #646970; font-size: 14px;">
                                        <?php
                                        printf(
                                            _n(
                                                'Affects %1$s page',
                                                'Affects %1$s pages',
                                                $possibility['affected_urls'],
                                                'seo-booster'
                                            ),
                                            '<strong>' . number_format_i18n($possibility['affected_urls']) . '</strong>'
                                        );
                                        ?>
                                    </p>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                        
                        <p style="margin-top: 20px;">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sb2_seo_issues')); ?>" class="button button-primary">
                                <?php esc_html_e('View All Possibilities', 'seo-booster'); ?>
                            </a>
                        </p>
                    <?php } else { ?>
                        <p><?php esc_html_e('No specific possibilities detected yet. Run an analysis to discover SEO opportunities.', 'seo-booster'); ?></p>
                        <p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sb2_seo_issues')); ?>" class="button button-secondary">
                                <?php esc_html_e('Go to SEO Possibilities', 'seo-booster'); ?>
                            </a>
                        </p>
                    <?php } ?>
                </div>
            </div>
        </div>
        <?php
    }
    ?>
    
    <div class="sbpanel">
<div class="flexrow col3">
		<div class="helpcont">
			<div class="helpbox">
				<h3><?php 
    esc_html_e( 'Keywords Overview', 'seo-booster' );
    ?></h3>

				<p><?php 
    echo esc_html__( 'Get a combined overview of all the keywords that have been used to find your website.', 'seo-booster' );
    ?></p>
				<a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_gsc' ) );
    ?>" class="button button-secondary"><?php 
    esc_html_e( 'Go to the keyword overview page', 'seo-booster' );
    ?></a>
			</div>
			<div class="helpbox">
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
			<div class="helpbox">
				<h3><?php 
    esc_html_e( 'SEO Possibilities', 'seo-booster' );
    ?></h3>
				
				<p><?php 
    echo esc_html__( 'Discover SEO opportunities and insights to improve your website.', 'seo-booster' );
    ?></p>
				<a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_seo_issues' ) );
    ?>" class="button button-secondary"><?php 
    echo esc_html__( 'Open SEO Possibilities', 'seo-booster' );
    ?></a>
			</div>
		</div><!-- .helpcont -->
	</div><!-- .flexrow -->
    </div><!-- .sbpanel -->

	<?php 
}

// Show appropriate interface based on authentication and site selection status
if ( !$access_token ) {
    
    // Check if we're on a local development domain
    $is_local = false;
    $local_domains = array('.local', '.test', '.dev', '.localhost', 'localhost', '127.0.0.1');
    foreach ($local_domains as $local_domain) {
        if (strpos(site_url(), $local_domain) !== false) {
            $is_local = true;
            break;
        }
    }
    
    $google_email = get_option('seobooster_google_email');
    ?>
        <div class="flexrow">
            <div class="col1">
                <h3>
                        <?php 
                        if (!empty($google_email)) {
                            esc_html_e('Reauthenticate with Google', 'seo-booster');
                        } else {
                            esc_html_e('Authenticate with Google', 'seo-booster');
                        }
                        ?>
                </h3>
                
                <?php if (!empty($google_email)): ?>
                    <p><?php echo esc_html($google_email); ?></p>
                <?php else: ?>
                    <p><?php esc_html_e('You need to connect your Google account to use this feature.', 'seo-booster'); ?></p>
                <?php endif; ?>

                <p>
                    <a href="<?php 
                        echo esc_url(add_query_arg(array(
                            'install_id' => $install_id,
                            'auth_token' => $authentication_string,
                            'return_to'  => $return_to,
                        ), 'https://seoboosterauth.com/auth'));
                    ?>" class="button button-primary button-hero">
                        <?php esc_html_e('Start Authentication', 'seo-booster'); ?>
                    </a>
                </p>
                <p><?php esc_html_e('You will be taken to Google to authorize your account.', 'seo-booster'); ?></p>
                <p><?php esc_html_e('Connect to the API to gather data from GSC via seoboosterauth.com.', 'seo-booster'); ?></p>
                
                <?php 
                // Display warning and help for local domains
                if ($is_local): 
                    
                    echo '<div class="notice seobooster-notice">';
                    echo '<p><strong>' . esc_html__('Warning: Local Development Domain Detected', 'seo-booster') . '</strong></p>';
                    echo '<p>' . esc_html__('Authentication with Google may fail because you are using a local development domain. OAuth services typically reject callbacks to non-public domains for security reasons.', 'seo-booster') . '</p>';
                    echo '<p><a href="#" class="button show-local-domain-info">' . esc_html__('Show More Information', 'seo-booster') . '</a></p>';
                    echo '<div class="local-domain-details" style="display:none;">';
                    echo Google_API::get_local_domain_info();
                    echo '</div>';
                    echo '</div>';
                    

                endif;
                ?>
            </div>
        </div>
    <?php

} elseif ( $access_token && !$selected_site ) {
    
    // Check if we're on a local development domain
    $is_local = false;
    $local_domains = array('.local', '.test', '.dev', '.localhost', 'localhost', '127.0.0.1');
    foreach ($local_domains as $local_domain) {
        if (strpos(site_url(), $local_domain) !== false) {
            $is_local = true;
            break;
        }
    }
    
    $google_email = get_option('seobooster_google_email');
    
    // Site selection form - only show when we have access token, Google email, and sites
    if (!empty($google_email)): 
        // Try to fetch sites if not already done
        $sites = Google_API::fetch_sites();
        
        // Handle different scenarios
        if (is_wp_error($sites)): ?>
            <div class="notice notice-error seobooster-notice">
                <p><?php echo esc_html($sites->get_error_message()); ?></p>
                <p><a href="<?php 
                    echo esc_url(add_query_arg(array(
                        'install_id' => $install_id,
                        'auth_token' => $authentication_string,
                        'return_to'  => $return_to,
                    ), 'https://seoboosterauth.com/auth')); 
                ?>" class="button button-primary">
                    <?php esc_html_e('Re-authenticate with Google', 'seo-booster'); ?>
                </a></p>
            </div>
            
        <?php elseif (empty($sites)): ?>
            <div class="notice notice-warning seobooster-notice">
                <p><?php esc_html_e('No sites found in your Google Search Console account. Make sure you have added and verified at least one site in Google Search Console.', 'seo-booster'); ?></p>
                <p><a href="https://search.google.com/search-console/welcome" target="_blank" class="button">
                    <?php esc_html_e('Open Google Search Console', 'seo-booster'); ?>
                </a></p>
            </div>
            
        <?php else: ?>
            <div class="seobooster-notice">
                <h3><?php esc_html_e('Select Google Search Console Site', 'seo-booster'); ?></h3>
                <p class="great-connected">
                    <?php 
                    printf(
                        esc_html__('Great, you have now connected your Google account (%s).', 'seo-booster'),
                        '<strong>' . esc_html($google_email) . '</strong>'
                    ); 
                    ?>
                </p>
                
                <form method="post">
                    <div id="choosecont">
                        <div class="col">
                            <?php
                            $site_url = esc_url(site_url('/')); ?>
                            <select name="seobooster_selected_site">
                                <option value="" disabled selected><?php esc_html_e('Please select a site to continue', 'seo-booster'); ?></option>
                                
                                <?php
                                // Group sites by permission level
                                $available_sites = [];
                                $unavailable_sites = [];
                                
                                foreach ($sites as $site) {
                                    if (empty($site)) {
                                        continue;
                                    }
                                    
                                    // Handle both array and string formats
                                    $site_data = [];
                                    $site_data['url'] = is_array($site) ? $site['siteUrl'] : $site;
                                    $site_data['permission'] = is_array($site) && isset($site['permissionLevel']) ? $site['permissionLevel'] : '';
                                    
                                    // Sites with siteUnverifiedUser permission don't have enough access
                                    if ($site_data['permission'] === 'siteUnverifiedUser') {
                                        $unavailable_sites[] = $site_data;
                                    } else {
                                        $available_sites[] = $site_data;
                                    }
                                }
                                
                                // Sort both arrays alphabetically
                                usort($available_sites, function($a, $b) {
                                    $a_clean = str_replace('sc-domain:', '', $a['url']);
                                    $b_clean = str_replace('sc-domain:', '', $b['url']);
                                    return strcasecmp($a_clean, $b_clean);
                                });
                                
                                usort($unavailable_sites, function($a, $b) {
                                    $a_clean = str_replace('sc-domain:', '', $a['url']);
                                    $b_clean = str_replace('sc-domain:', '', $b['url']);
                                    return strcasecmp($a_clean, $b_clean);
                                });
                                
                                // Display available sites first
                                foreach ($available_sites as $site_data) {
                                    $value = $site_data['url'];
                                    $permission = $site_data['permission'];
                                    
                                    // Remove 'sc-domain:' from display label
                                    $label = str_replace('sc-domain:', '', $value);
                                    
                                    // Add domain verified text if it's a domain property
                                    if (strpos($value, 'sc-domain:') === 0) {
                                        $label .= ' ' . esc_html__('(domain verified)', 'seo-booster');
                                    }
                                    ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $site_url); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                    <?php
                                }
                                
                                // Only add divider and unavailable sites if there are any
                                if (!empty($unavailable_sites)) {
                                    // Add a divider
                                    ?>
                                    <option disabled>───────────────────</option>
                                    <option value="" disabled><?php esc_html_e('Insufficient Permissions', 'seo-booster'); ?></option>
                                    <?php
                                    
                                    // Display unavailable sites
                                    foreach ($unavailable_sites as $site_data) {
                                        $value = $site_data['url'];
                                        $permission = $site_data['permission'];
                                        
                                        // Remove 'sc-domain:' from display label
                                        $label = str_replace('sc-domain:', '', $value);
                                        
                                        // Add domain verified text if it's a domain property
                                        if (strpos($value, 'sc-domain:') === 0) {
                                            $label .= ' ' . esc_html__('(domain verified)', 'seo-booster');
                                        }
                                        
                                        // Add permission level if available
                                        if (!empty($permission)) {
                                            $label .= ' - ' . esc_html($permission);
                                        }
                                        ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $site_url); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                        <?php
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col">
                            <?php wp_nonce_field('seobooster_save_selected_site', 'seobooster_selected_site_nonce'); ?>
                            <input type="hidden" name="seobooster_selected_days" value="90">
                            <input type="submit" name="submit" value="<?php esc_attr_e('Get keyword data', 'seo-booster'); ?>" class="button button-primary" id="seobooster2selectsite">
                        </div>
                    </div>
                </form>

                <div id="seobooster-api-error"></div>
                <div id="seobooster-api-status"><span class="spinner is-active"></span></div>
            </div>
        <?php endif; ?>
     <?php endif; ?>
     <?php
}

if ( ! ( $selected_site && 0 < $unique_days ) ) {
	?>
	</div><!-- #inner-welcome -->
	</div><!-- .sbpanel -->
	<?php
}

if ( (!isset( $total_keywords ) || '0' === $total_keywords || 0 >= $unique_days) && seobooster_fs()->is_registered() && seobooster_fs()->is_tracking_allowed() && $access_token && $selected_site ) {
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
} elseif ( $access_token && $selected_site && ( !isset( $total_keywords ) || '0' === $total_keywords || 0 >= $unique_days ) ) {
    // Show message when authenticated and site selected but no data
    ?>
    <div class="sbpanel">
        <div class="flexrow">
            <div class="col1">
                <h3><?php esc_html_e( 'No Data Available', 'seo-booster' ); ?></h3>
                <p><?php esc_html_e( 'You are authenticated and have selected a site, but no data has been imported yet.', 'seo-booster' ); ?></p>
                <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=sb2_settings#manualupdate' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Import Data from Settings', 'seo-booster' ); ?></a></p>
            </div>
        </div>
    </div>
    <?php
}
?>


<?php 
if ( $access_token && $selected_site && 0 < $unique_days ) {
    ?>
    <div class="sbpanel">
    <div class="flexrow">
	<div id="sb2canvascont" style="height:550px;display:block;margin:0 0 20px 0;">
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
    </div>
    </div>

<?php 
}

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
</div> <!-- .wrap -->