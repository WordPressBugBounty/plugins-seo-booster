<?php

namespace Cleverplugins\SEOBooster;

use function as_get_scheduled_actions;
// Include the Settings Utils class
require_once SEOBOOSTER_PLUGINPATH . 'inc/Settings_Utils.php';
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'seo-booster' ) );
}
global $wpdb, $seobooster2;
// Form processing is now handled in admin_init() method to avoid header issues
// Define variables to prevent undefined variable warnings
$seobooster_weekly_email = get_option( 'seobooster_weekly_email', '' );
$seobooster_weekly_email_recipient = get_option( 'seobooster_weekly_email_recipient', '' );
$seobooster_internal_linking = get_option( 'seobooster_internal_linking', '' );
$replace_kw_multiple = get_option( 'seobooster_replace_kw_multiple', '' );
$match_capitalization = get_option( 'seobooster_match_capitalization', '' );
$seobooster_replace_kw_limit = get_option( 'seobooster_replace_kw_limit', 1 );
$fof_monitoring = get_option( 'seobooster_fof_monitoring', 'on' );
$seobooster_delete_deactivate = get_option( 'seobooster_delete_deactivate', '' );
$seo_analysis_batch_size = get_option( 'seobooster_seo_analysis_batch_size', 1 );
$seo_possibilities_enabled = get_option( 'seobooster_seo_possibilities_enabled', 'on' );
$seo_possibilities_frequency = get_option( 'seobooster_seo_possibilities_frequency', 60 );
// Form processing is now handled by Form_Processor class in admin_init() method
echo wp_kses_post( Utils::show_plugin_headline( __( 'Settings', 'seo-booster' ), true ) );
settings_errors( 'seobooster_messages' );
?>
<div class="seo-booster-settings-page">
    <!-- Tab Navigation -->
    <div class="sb-seo-tabs">
        <nav class="nav-tab-wrapper">

            <a href="#automatic-links" class="nav-tab nav-tab-active" data-tab="automatic-links">
                <?php 
esc_html_e( 'Automatic Links', 'seo-booster' );
?>
            </a>
            <a href="#email-reports" class="nav-tab" data-tab="email-reports">
                <?php 
esc_html_e( 'Email', 'seo-booster' );
?>
            </a>
            <a href="#gsc" class="nav-tab" data-tab="gsc">
                <?php 
esc_html_e( 'Google Search Console', 'seo-booster' );
?>
            </a>
            <a href="#seo-possibilities" class="nav-tab" data-tab="seo-possibilities">
                <?php 
esc_html_e( 'SEO Possibilities', 'seo-booster' );
?>
            </a>
            <a href="#ai-llm" class="nav-tab" data-tab="ai-llm">
                <span class="dashicons dashicons-lightbulb"></span>
                <?php 
esc_html_e( 'AI/LLM', 'seo-booster' );
?>
            </a>
            <a href="#tools" class="nav-tab" data-tab="tools">
                <?php 
esc_html_e( 'Tools', 'seo-booster' );
?>
            </a>
            <a href="#stats" class="nav-tab" data-tab="stats">
                <span class="dashicons dashicons-chart-bar"></span>
                <?php 
esc_html_e( 'Stats', 'seo-booster' );
?>
            </a>
        </nav>
    </div>
    
    <form method="post" id="seobooster_settings" action="<?php 
echo esc_url( admin_url( 'admin.php?page=sb2_settings' ) );
?>">
        <?php 
wp_nonce_field( 'seobooster_save_settings' );
?>
        <input type="hidden" name="page" value="sb2_settings">

        <!-- Email & Reports Tab -->
        <div id="email-reports-tab" class="sb-tab-content">
            <table class="form-table">
                <tbody>
                    <tr valign="top">
                        <th colspan="2">
                            <h2><?php 
esc_html_e( 'Weekly Email Reports', 'seo-booster' );
?></h2>
                        </th>
                    </tr>
                    <tr valign="top">
                        <th scope="row" valign="top">
                            <?php 
esc_html_e( 'Enable/Disable', 'seo-booster' );
?>
                        </th>
                        <td>
                            <label class="sb-toggle-label">
                                <div class="sb-toggle-switch">
                                    <input type="checkbox" id="seobooster_weekly_email" name="seobooster_weekly_email" value="on" <?php 
if ( 'on' === $seobooster_weekly_email ) {
    echo " checked='checked'";
}
?> />
                                    <span class="sb-toggle-slider"></span>
                                </div>
                                <span><?php 
esc_html_e( 'Send a weekly email with new information from the past week.', 'seo-booster' );
?></span>
                            </label>
                            <?php 
if ( 'on' === $seobooster_weekly_email ) {
    $timestamp = wp_next_scheduled( 'seobooster_email_update' );
    if ( $timestamp ) {
        $timestamp = gmdate( 'Y-m-d H:i:s', $timestamp );
        $current_time = current_time( 'timestamp' );
        $time_diff = human_time_diff( $current_time, strtotime( $timestamp ) );
        // translators: %s: time difference until the next scheduled email
        echo '<small>' . esc_html__( 'Next scheduled email', 'seo-booster' ) . ': ' . esc_html( $timestamp ) . ' (' . sprintf( esc_html__( 'in %s', 'seo-booster' ), esc_html( $time_diff ) ) . ')</small>';
    }
}
?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" valign="top">
                            <?php 
esc_html_e( 'Recipient(s)', 'seo-booster' );
?>
                        </th>
                        <td>
                            <input type="text" id="seobooster_weekly_email_recipient" name="seobooster_weekly_email_recipient" value="<?php 
echo esc_attr( $seobooster_weekly_email_recipient );
?>" class="regular-text">
                            <p class="description">
                                <label for="seobooster_weekly_email_recipient">
                                    <?php 
esc_html_e( 'Email recipient. To add multiple recipients, separate each email address with a comma.', 'seo-booster' );
?>
                                </label>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" valign="top">
                            <?php 
esc_html_e( 'Send Email Now', 'seo-booster' );
?>
                        </th>
                        <td>
                            <?php 
submit_button( esc_html__( 'Send Weekly Email', 'seo-booster' ), 'secondary', 'submit_send_email' );
?>
                            <p class="description"><?php 
esc_html_e( 'Manually send the weekly email update now.', 'seo-booster' );
?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Automatic Links Tab -->
        <div id="automatic-links-tab" class="sb-tab-content sb-tab-active">
            <table class="form-table">
                <tbody>
                    <tr valign="top" id="autolinks">
                        <th colspan="2">
                            <h2><?php 
esc_html_e( 'Automatic Links', 'seo-booster' );
?></h2>
                        </th>
                    </tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Enable/Disable', 'seo-booster' );
?>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span>
									<?php 
esc_html_e( 'Change keywords in text to links to relevant pages on your site.', 'seo-booster' );
?>
								</span></legend>
							<label for="seobooster_internal_linking">
								<input type="checkbox" id="seobooster_internal_linking" name="seobooster_internal_linking" value="on" <?php 
if ( $seobooster_internal_linking ) {
    echo " checked='checked'";
}
?> />
								<p class="description">
									<?php 
esc_html_e( 'Change keywords in text to links to relevant pages on your site.', 'seo-booster' );
?>
								</p>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Repeat Keywords', 'seo-booster' );
?>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span>
									<?php 
esc_html_e( 'If the same word is used multiple times, only the first occurence will be replaced by a link.', 'seo-booster' );
?>
								</span></legend>
							<label for="seobooster_replace_kw_multiple">
								<input type="checkbox" id="seobooster_replace_kw_multiple" name="seobooster_replace_kw_multiple" value="on" <?php 
if ( $replace_kw_multiple ) {
    echo " checked='checked'";
}
?> />
								<p class="description">
									<?php 
esc_html_e( 'This will allow the same keyword and URL to be used repeatedly. Usually, if the same word is used multiple times, only the first occurence will be replaced by a link.', 'seo-booster' );
?>
								</p>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Match Capitalization', 'seo-booster' );
?>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span>
									<?php 
esc_html_e( 'Only inject links matching keyword capitalization.', 'seo-booster' );
?>
								</span></legend>
							<label for="seobooster_match_capitalization">
								<input type="checkbox" id="seobooster_match_capitalization" name="seobooster_match_capitalization" value="on" <?php 
if ( $match_capitalization ) {
    echo " checked='checked'";
}
?> />
								<p class="description">
									<?php 
esc_html_e( 'Only inject links matching keyword capitalization.', 'seo-booster' );
?>
								</p>
							</label>
						</fieldset>
					</td>
				</tr>
				











				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Maximum Replacements', 'seo-booster' );
?>
					</th>
					<td>
						<input type="number" id="seobooster_replace_kw_limit" name="seobooster_replace_kw_limit" value="<?php 
echo esc_attr( max( 1, intval( $seobooster_replace_kw_limit ) ) );
?>" class="small-text" step="1" min="1" max="99" />
						<p class="description"><label for="seobooster_replace_kw_limit">
								<?php 
esc_html_e( 'Maximum number of links created per post. It does not include current links in the content.', 'seo-booster' );
?>
							</label></p>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Excluded Elements', 'seo-booster' );
?>
					</th>
					<td>
						<?php 
// Get saved excluded elements or set defaults
$excluded_elements = get_option( 'seobooster_excluded_elements', array(
    'h1'         => 1,
    'h2'         => 1,
    'h3'         => 1,
    'h4'         => 1,
    'h5'         => 1,
    'h6'         => 1,
    'ul'         => 1,
    'ol'         => 1,
    'dl'         => 1,
    'blockquote' => 1,
) );
// Elements that cannot be deselected
$mandatory_elements = array(
    'a',
    'script',
    'style',
    'head'
);
// All available elements
$all_elements = array(
    'h1'         => __( 'Heading 1 (H1)', 'seo-booster' ),
    'h2'         => __( 'Heading 2 (H2)', 'seo-booster' ),
    'h3'         => __( 'Heading 3 (H3)', 'seo-booster' ),
    'h4'         => __( 'Heading 4 (H4)', 'seo-booster' ),
    'h5'         => __( 'Heading 5 (H5)', 'seo-booster' ),
    'h6'         => __( 'Heading 6 (H6)', 'seo-booster' ),
    'ul'         => __( 'Unordered Lists (UL)', 'seo-booster' ),
    'ol'         => __( 'Ordered Lists (OL)', 'seo-booster' ),
    'dl'         => __( 'Definition Lists (DL)', 'seo-booster' ),
    'blockquote' => __( 'Blockquotes', 'seo-booster' ),
);
echo '<div class="excluded-elements-container" style="max-width: 600px;">';
// First display optional elements
foreach ( $all_elements as $element => $label ) {
    $checked = ( isset( $excluded_elements[$element] ) ? 'checked="checked"' : '' );
    echo '<label style="display: inline-block; margin-right: 15px; margin-bottom: 10px; min-width: 150px;">';
    echo '<input type="checkbox" name="seobooster_excluded_elements[' . esc_attr( $element ) . ']" value="1" ' . $checked . '> ';
    echo esc_html( $label );
    echo '</label>';
}
echo '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">';
echo '<strong>' . esc_html__( 'Always excluded (cannot be changed):', 'seo-booster' ) . '</strong><br>';
// Then display mandatory elements
foreach ( $mandatory_elements as $element ) {
    echo '<label style="display: inline-block; margin-right: 15px; margin-bottom: 10px; min-width: 150px; color: #666;">';
    echo '<input type="checkbox" checked="checked" disabled="disabled"> ';
    switch ( $element ) {
        case 'a':
            echo esc_html__( 'Links (A)', 'seo-booster' );
            break;
        case 'script':
            echo esc_html__( 'Scripts', 'seo-booster' );
            break;
        case 'style':
            echo esc_html__( 'Style tags', 'seo-booster' );
            break;
        case 'head':
            echo esc_html__( 'Head', 'seo-booster' );
            break;
        default:
            echo esc_html( ucfirst( $element ) );
    }
    echo '</label>';
    // Add hidden field to ensure these values are submitted
    echo '<input type="hidden" name="seobooster_excluded_elements[' . esc_attr( $element ) . ']" value="1">';
}
echo '</div>';
echo '</div>';
?>
						
						<p class="description">
							<?php 
esc_html_e( 'Select which HTML elements should be excluded from automatic linking. Text inside these elements will not receive automatic links.', 'seo-booster' );
?>
							<a href="<?php 
echo esc_url( Utils::generate_cp_web_link( 'admin', 'docs/automatic-links/why-arent-my-keywords-being-linked-automatically/' ) );
?>" target="_blank">
								<?php 
esc_html_e( 'Learn more about excluded elements', 'seo-booster' );
?>
							</a>
						</p>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row" valign="top">
						<p><?php 
esc_html_e( 'Enable/Disable', 'seo-booster' );
?></p>
					</th>
					<td>
						<?php 
$show_404_upsell = true;
if ( $show_404_upsell ) {
    ?>
							<p class="description"><?php 
    esc_html_e( 'Upgrade to SEO Booster Pro to unlock this feature.', 'seo-booster' );
    ?></p>
							<?php 
}
?>
					</td>
				</tr>
				<?php 
if ( !$show_404_upsell ) {
    ?>
				<tr valign="top">
					<th scope="row" valign="top"><?php 
    esc_html_e( 'Ignore links', 'seo-booster' );
    ?> </th>
					<td>
						<p><?php 
    esc_html_e( 'SEO Booster ignores a range of common URLs.', 'seo-booster' );
    ?></p>
						<?php 
    ?>
						<p><?php 
    esc_html_e( 'Read more here on how to manipulate the ignore list', 'seo-booster' );
    ?> <a href="<?php 
    echo esc_url( Seobooster2::gen_web_link( 'documentation_link', '/docs/404-errors/customizing-the-list-of-ignored-urls/' ) );
    ?>" target="_blank" rel="noopener"><?php 
    esc_html_e( 'Customizing the List of Ignored URLs', 'seo-booster' );
    ?></a></p>
					</td>
				</tr>
				<?php 
}
?>
				<tr valign="top">
					<th colspan="2">
						<hr>
					</th>
				</tr>
			</tbody>
		</table>
	</div>
	
	<!-- Google Search Console Tab -->
	<div id="gsc-tab" class="sb-tab-content">
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th colspan="2">
						<h2><?php 
esc_html_e( 'Google Search Console Integration', 'seo-booster' );
?></h2>
					</th>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'GSC Connection Status', 'seo-booster' );
?>
					</th>
					<td>
						<?php 
$access_token = get_option( 'seobooster_access_token' );
$selected_site = get_option( 'seobooster_selected_site' );
if ( $access_token && $selected_site ) {
    echo '<span style="color: green;">✓ ' . esc_html__( 'Connected', 'seo-booster' ) . '</span>';
} else {
    echo '<span style="color: red;">✗ ' . esc_html__( 'Not Connected', 'seo-booster' ) . '</span>';
}
?>
						<p class="description">
							<?php 
esc_html_e( 'Connect to Google Search Console to import keyword data.', 'seo-booster' );
?>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Selected GSC Site', 'seo-booster' );
?>
					</th>
					<td>
						<?php 
$selected_site = get_option( 'seobooster_selected_site' );
if ( $selected_site ) {
    echo '<strong>' . esc_html( $selected_site ) . '</strong>';
    $google_email = get_option( 'seobooster_google_email' );
    if ( $google_email ) {
        echo '<br><small>' . sprintf( esc_html__( 'Google Email: %s', 'seo-booster' ), esc_html( $google_email ) ) . '</small>';
    }
} else {
    echo '<em>' . esc_html__( 'No site selected', 'seo-booster' ) . '</em>';
}
?>
						<p class="description">
							<?php 
esc_html_e( 'The Google Search Console site currently selected for data import.', 'seo-booster' );
?>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Import Actions', 'seo-booster' );
?>
					</th>
					<td>
						<div class="sb-gsc-import-actions">
							<div class="sb-gsc-time-range">
								<label for="gsc-time-range"><?php 
esc_html_e( 'Time Range:', 'seo-booster' );
?></label>
								<select id="gsc-time-range" name="gsc_time_range">
									<option value="7"><?php 
esc_html_e( 'Last 7 days', 'seo-booster' );
?></option>
									<option value="30" selected><?php 
esc_html_e( 'Last 30 days', 'seo-booster' );
?></option>
									<option value="90"><?php 
esc_html_e( 'Last 90 days', 'seo-booster' );
?></option>
								</select>
							</div>
							<div class="sb-gsc-buttons">
								<button type="button" id="manual_update_ajax" class="button button-primary">
									<?php 
esc_html_e( 'Import GSC Data', 'seo-booster' );
?>
								</button>
								<button type="button" id="change_gsc_site" class="button">
									<?php 
esc_html_e( 'Change GSC Site', 'seo-booster' );
?>
								</button>
								<?php 
// Generate authentication parameters for re-authentication
$install_id = '';
$site_private_key = '';
// Try to get from Freemius first
if ( function_exists( 'seobooster_fs' ) && seobooster_fs()->is_registered() ) {
    try {
        $install_id = seobooster_fs()->get_site()->id;
        $site_private_key = seobooster_fs()->get_site()->secret_key;
    } catch ( \Exception $e ) {
        // Fall through to alternative methods
    }
}
// Fallback to alternative ID if Freemius not available
if ( empty( $install_id ) ) {
    $alt_install_id = get_option( 'seobooster_alt_install_id' );
    if ( empty( $alt_install_id ) ) {
        $salt = 'seobooster_' . substr( wp_generate_password( 8, false, false ), 0, 8 );
        $site_url = site_url();
        $alt_install_id = 'ALT_' . substr( md5( $site_url . $salt ), 0, 16 );
        update_option( 'seobooster_alt_install_id', $alt_install_id, false );
    }
    $install_id = $alt_install_id;
}
// Fallback to alternative key if Freemius not available
if ( empty( $site_private_key ) ) {
    $alt_site_key = get_option( 'seobooster_alt_site_key' );
    if ( empty( $alt_site_key ) ) {
        $alt_site_key = wp_generate_password( 32, true, true );
        update_option( 'seobooster_alt_site_key', $alt_site_key, false );
    }
    $site_private_key = $alt_site_key;
}
// Generate authentication string
if ( !empty( $install_id ) && !empty( $site_private_key ) ) {
    $nonce = gmdate( 'Y-m-d' );
    $return_to = admin_url( 'admin.php?page=sb2_settings#gsc' );
    $pk_hash = hash( 'sha512', $site_private_key . '|' . $nonce );
    $authentication_string = base64_encode( $pk_hash . '|' . $nonce );
    $auth_url = add_query_arg( array(
        'install_id' => $install_id,
        'auth_token' => $authentication_string,
        'return_to'  => $return_to,
    ), 'https://seoboosterauth.com/auth' );
    ?>
									<a href="<?php 
    echo esc_url( $auth_url );
    ?>" target="_blank" class="button">
										<?php 
    esc_html_e( 'Re-authenticate with Google', 'seo-booster' );
    ?>
									</a>
									<?php 
}
?>
							</div>
						</div>
						<div id="settings-import-status" style="margin-top: 10px;"></div>
						<div id="settings-import-progress" style="margin-top: 10px;"></div>
						<div id="settings-import-error" style="margin-top: 10px; color: red;"></div>
						<p class="description">
							<?php 
esc_html_e( 'Import keyword data from Google Search Console for the selected time range.', 'seo-booster' );
?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	
	<!-- SEO Possibilities Tab -->
	<div id="seo-possibilities-tab" class="sb-tab-content">
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th colspan="2">
						<h2><?php 
esc_html_e( 'SEO Possibilities Auto-Scan', 'seo-booster' );
?></h2>
						<p class="description"><?php 
esc_html_e( 'Automatically discover and analyze URLs from imported Google Search Console data. Only analyzes URLs that haven\'t been analyzed yet.', 'seo-booster' );
?></p>
					</th>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Enable/Disable', 'seo-booster' );
?>
					</th>
					<td>
						<label class="sb-toggle-label">
							<div class="sb-toggle-switch">
								<input type="checkbox" id="seobooster_seo_possibilities_enabled" name="seobooster_seo_possibilities_enabled" value="on" <?php 
if ( 'on' === $seo_possibilities_enabled ) {
    echo " checked='checked'";
}
?> />
								<span class="sb-toggle-slider"></span>
							</div>
							<span><?php 
esc_html_e( 'Enable automatic discovery and analysis of URLs from imported Google Search Console data.', 'seo-booster' );
?></span>
						</label>
						<?php 
if ( 'on' === $seo_possibilities_enabled && function_exists( 'as_next_scheduled_action' ) ) {
    $next_scheduled = as_next_scheduled_action( 'sb_seo_possibilities_auto_scan', [], 'seo-booster' );
    if ( $next_scheduled ) {
        $next_time = gmdate( 'Y-m-d H:i:s', $next_scheduled );
        $current_time = current_time( 'timestamp' );
        $time_diff = human_time_diff( $current_time, $next_scheduled );
        echo '<br><small>' . esc_html__( 'Next scheduled scan', 'seo-booster' ) . ': ' . esc_html( $next_time ) . ' (' . sprintf( esc_html__( 'in %s', 'seo-booster' ), esc_html( $time_diff ) ) . ')</small>';
    }
}
?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Scan Frequency', 'seo-booster' );
?>
					</th>
					<td>
						<select name="seobooster_seo_possibilities_frequency" id="seobooster_seo_possibilities_frequency">
							<option value="60" <?php 
selected( $seo_possibilities_frequency, 60 );
?>><?php 
esc_html_e( 'Every minute', 'seo-booster' );
?></option>
							<option value="300" <?php 
selected( $seo_possibilities_frequency, 300 );
?>><?php 
esc_html_e( 'Every 5 minutes', 'seo-booster' );
?></option>
							<option value="900" <?php 
selected( $seo_possibilities_frequency, 900 );
?>><?php 
esc_html_e( 'Every 15 minutes', 'seo-booster' );
?></option>
							<option value="1800" <?php 
selected( $seo_possibilities_frequency, 1800 );
?>><?php 
esc_html_e( 'Every 30 minutes', 'seo-booster' );
?></option>
							<option value="3600" <?php 
selected( $seo_possibilities_frequency, 3600 );
?>><?php 
esc_html_e( 'Hourly', 'seo-booster' );
?></option>
						</select>
						<p class="description">
							<?php 
esc_html_e( 'How often the system checks for URLs from Google Search Console data that need analysis. Scans only run if no other analysis is currently in progress. Analyses run sequentially (one at a time) to avoid server overload.', 'seo-booster' );
?>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Batch Size', 'seo-booster' );
?>
					</th>
					<td>
						<select name="seobooster_seo_analysis_batch_size" id="seobooster_seo_analysis_batch_size">
							<option value="1" <?php 
selected( $seo_analysis_batch_size, 1 );
?>><?php 
esc_html_e( '1 URL per batch', 'seo-booster' );
?></option>
							<option value="2" <?php 
selected( $seo_analysis_batch_size, 2 );
?>><?php 
esc_html_e( '2 URLs per batch', 'seo-booster' );
?></option>
							<option value="3" <?php 
selected( $seo_analysis_batch_size, 3 );
?>><?php 
esc_html_e( '3 URLs per batch', 'seo-booster' );
?></option>
							<option value="4" <?php 
selected( $seo_analysis_batch_size, 4 );
?>><?php 
esc_html_e( '4 URLs per batch', 'seo-booster' );
?></option>
							<option value="5" <?php 
selected( $seo_analysis_batch_size, 5 );
?>><?php 
esc_html_e( '5 URLs per batch', 'seo-booster' );
?></option>
						</select>
						<p class="description">
							<?php 
esc_html_e( 'Number of URLs to analyze per batch. Lower values reduce server load. Default: 1', 'seo-booster' );
?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	
	<!-- AI/LLM Tab -->
	<div id="ai-llm-tab" class="sb-tab-content">
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th colspan="2">
						<h2><?php 
esc_html_e( 'AI/LLM Settings', 'seo-booster' );
?></h2>
						<p class="description"><?php 
esc_html_e( 'Configure AI-powered SEO suggestions for your content.', 'seo-booster' );
?></p>
					</th>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'AI Provider', 'seo-booster' );
?>
					</th>
					<td>
                        <?php 
$ai_provider = get_option( 'seobooster_ai_provider', 'disabled' );
$credits_ai_available = \Cleverplugins\SEOBooster\Credits_Service::is_ai_provider_available();
if ( !$credits_ai_available && $ai_provider === 'seobooster' ) {
    $ai_provider = 'disabled';
}
?>
                        <fieldset class="seobooster-ai-provider-fieldset">
                            <label>
                                <input type="radio" name="seobooster_ai_provider" value="disabled" <?php 
checked( $ai_provider, 'disabled' );
?>>
                                <?php 
esc_html_e( 'Disabled', 'seo-booster' );
?>
                            </label><br>
                            <label>
                                <input type="radio" name="seobooster_ai_provider" value="wordpress" <?php 
checked( $ai_provider, 'wordpress' );
?>>
                                <?php 
esc_html_e( 'WordPress (Connectors)', 'seo-booster' );
?>
                            </label><br>
                            <label class="seobooster-credits-label seobooster-credits-label--disabled">
                                <input type="radio" name="seobooster_ai_provider" value="seobooster" disabled="disabled" />
                                <span class="seobooster-credits-label-text"><?php 
esc_html_e( 'SEO Booster Credits (Coming soon)', 'seo-booster' );
?></span>
                            </label>
                            <p class="description seobooster-credits-upsell">
                                <?php 
esc_html_e( 'Pay-as-you-go AI without your own API key. Not available yet.', 'seo-booster' );
?>
                            </p>
                        </fieldset>
                        <p class="description"><?php 
esc_html_e( 'Choose how to generate AI-powered SEO suggestions.', 'seo-booster' );
?></p>
                    </td>
                </tr>
                <tr valign="top" id="wordpress-connectors-settings" style="<?php 
echo ( $ai_provider !== 'wordpress' ? 'display: none;' : '' );
?>">
                    <th scope="row" valign="top">
                        <?php 
esc_html_e( 'Configure AI', 'seo-booster' );
?>
                    </th>
                    <td>
                        <p class="description" style="margin: 0;">
                            <?php 
esc_html_e( 'Configure AI providers (OpenAI, Claude, Gemini, etc.) at', 'seo-booster' );
?>
                            <a href="<?php 
echo esc_url( admin_url( 'options-connectors.php' ) );
?>"><?php 
esc_html_e( 'Settings → Connectors', 'seo-booster' );
?></a>.
                        </p>
                    </td>
                </tr>
				<!-- SEO Booster Credits Settings -->
				<?php 
if ( $credits_ai_available ) {
    ?>
				<?php 
    $is_registered = \Cleverplugins\SEOBooster\Credits_Service::is_registered();
    ?>
				<tr valign="top" id="seobooster-credits-register" style="<?php 
    echo ( $ai_provider !== 'seobooster' ? 'display: none;' : '' );
    ?>">
					<th scope="row" valign="top">
						<?php 
    esc_html_e( 'Account', 'seo-booster' );
    ?>
					</th>
					<td>
						<?php 
    if ( $is_registered ) {
        ?>
							<div class="sb-credits-account-status" style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
								<span class="dashicons dashicons-yes-alt" style="color:#46b450; font-size:20px;"></span>
								<span style="font-weight:600;"><?php 
        esc_html_e( 'Connected', 'seo-booster' );
        ?></span>
							</div>
						<?php 
    } else {
        ?>
							<div style="margin-bottom:10px;">
								<input type="email" id="sb-credits-register-email" class="regular-text"
									   placeholder="<?php 
        esc_attr_e( 'Your email address', 'seo-booster' );
        ?>"
									   value="<?php 
        echo esc_attr( get_option( 'admin_email' ) );
        ?>">
								<button type="button" id="sb-credits-register-btn" class="button button-primary">
									<?php 
        esc_html_e( 'Connect Account', 'seo-booster' );
        ?>
								</button>
								<span class="spinner" id="sb-credits-register-spinner" style="float:none;"></span>
							</div>
							<p class="description"><?php 
        esc_html_e( 'Register with the Credits API to start using AI credits.', 'seo-booster' );
        ?></p>
						<?php 
    }
    ?>
					</td>
				</tr>
				<?php 
    if ( $is_registered ) {
        ?>
				<tr valign="top" id="seobooster-credits-balance-row" style="<?php 
        echo ( $ai_provider !== 'seobooster' ? 'display: none;' : '' );
        ?>">
					<th scope="row" valign="top">
						<?php 
        esc_html_e( 'Credit Balance', 'seo-booster' );
        ?>
					</th>
					<td>
						<?php 
        $balance = \Cleverplugins\SEOBooster\Credits_Service::get_balance();
        ?>
						<div class="sb-credits-balance-display" style="display:flex; align-items:center; gap:15px; margin-bottom:15px;">
							<span class="sb-credits-balance-number" style="font-size:28px; font-weight:700; color:#1d2327;" id="sb-credits-balance-value">
								<?php 
        echo esc_html( number_format( $balance ) );
        ?>
							</span>
							<span style="color:#646970;"><?php 
        esc_html_e( 'credits remaining', 'seo-booster' );
        ?></span>
							<button type="button" id="sb-credits-refresh-btn" class="button button-small" title="<?php 
        esc_attr_e( 'Refresh', 'seo-booster' );
        ?>">
								<span class="dashicons dashicons-update" style="margin-top:3px;"></span>
							</button>
							<button type="button" id="sb-credits-sync-purchases-btn" class="button button-secondary button-small">
								<?php 
        esc_html_e( 'Refresh credits from purchase', 'seo-booster' );
        ?>
							</button>
						</div>
						<p class="description" style="margin-top:0;"><?php 
        esc_html_e( 'Just bought credits? Click "Refresh credits from purchase" to sync your balance.', 'seo-booster' );
        ?></p>
						<div style="background:#f9f9f9; border:1px solid #c3c4c7; border-radius:4px; padding:15px;">
							<h4 style="margin-top:0; margin-bottom:10px;"><?php 
        esc_html_e( 'Purchase Credits', 'seo-booster' );
        ?></h4>
							<p style="margin-bottom:10px; color:#646970; font-size:13px;">
								<?php 
        esc_html_e( '1 credit = 1 SEO text suggestion, 3 credits = 1 image analysis. One-time purchase, no subscription.', 'seo-booster' );
        ?>
							</p>
							<div class="sb-credits-packs" style="display:flex; flex-wrap:wrap; gap:8px;">
								<?php 
        $packs = \Cleverplugins\SEOBooster\Credits_Service::get_packs();
        foreach ( $packs as $pack ) {
            $checkout_url = ( !empty( $pack['checkout_url'] ) ? $pack['checkout_url'] : \Cleverplugins\SEOBooster\Credits_Service::get_checkout_url( $pack['pricing_id'] ?? null ) );
            ?>
								<a href="<?php 
            echo esc_url( $checkout_url );
            ?>" target="_blank" class="button" style="min-width:120px; text-align:center;">
									<?php 
            echo esc_html( $pack['credits'] );
            ?> <?php 
            esc_html_e( 'credits', 'seo-booster' );
            ?> &mdash; <?php 
            echo esc_html( $pack['price'] );
            ?>
								</a>
								<?php 
        }
        ?>
							</div>
						</div>
					</td>
				</tr>
				<?php 
    }
    ?>
				<?php 
}
?>
			</tbody>
		</table>
	</div>
	
	<!-- Tools & Maintenance Tab -->
	<div id="tools-tab" class="sb-tab-content">
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Database Update', 'seo-booster' );
?>
					</th>
					<td>
						<?php 
submit_button( __( 'Run Update Database', 'seo-booster' ), 'secondary', 'submit_dbupdates' );
?>
						<p><?php 
esc_html_e( 'If you need to manually run the database updates. No need to use unless directed by support.', 'seo-booster' );
?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Restart Keyword Scanning', 'seo-booster' );
?>
					</th>
					<td>
						<?php 
submit_button( __( 'Restart Keyword Scanning', 'seo-booster' ), 'secondary', 'schedule_all_pages' );
?>
						<p><?php 
esc_html_e( 'Manually restart the keyword scanning process for all pages.', 'seo-booster' );
?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Reset the log', 'seo-booster' );
?>
					</th>
					<td>
						<?php 
submit_button( __( 'Reset the log', 'seo-booster' ), 'secondary', 'submit_reset_log' );
?>
						<p><?php 
esc_html_e( 'Clear the debug log.', 'seo-booster' );
?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Reset keyword data', 'seo-booster' );
?>
					</th>
					<td>
						<?php 
submit_button( esc_html__( 'Reset keyword data', 'seo-booster' ), 'secondary', 'submit_dbempty' );
?>
						<p><?php 
esc_html_e( 'Clear all keyword data and reset the database.', 'seo-booster' );
?></p>
					</td>
				</tr>
				

				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Delete Data on Deactivate', 'seo-booster' );
?>
					</th>
					<td>
						<input type="checkbox" id="seobooster_delete_deactivate" name="seobooster_delete_deactivate" value="on" <?php 
if ( 'on' === $seobooster_delete_deactivate ) {
    echo " checked='checked'";
}
?> />
						<p class="description"><label for="seobooster_delete_deactivate">
							<?php 
esc_html_e( 'Turn this on to delete all data when deactivating the plugin. This cannot be undone.', 'seo-booster' );
?>
						</label></p>
						<p class="description">
							<?php 
esc_html_e( 'WordPress Multisite users: Careful! Turning this on and deactivating the plugin deletes ALL SEO Booster database tables on ALL sites.', 'seo-booster' );
?>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Clear Database and Reset Options', 'seo-booster' );
?>
					</th>
					<td>
						<ul>
							<li>🗑️ <?php 
esc_html_e( 'Removes all keyword data and the history of keyword positions and clicks.', 'seo-booster' );
?></li>
							<li>🗑️ <?php 
esc_html_e( 'Resets the debug log.', 'seo-booster' );
?></li>
							<li>🗑️ <?php 
esc_html_e( 'Clears all 404 error records.', 'seo-booster' );
?></li>
							<li>🗑️ <?php 
esc_html_e( 'Deletes cached content pages used for keyword research.', 'seo-booster' );
?></li>
							<li>🗑️ <?php 
esc_html_e( 'Purges all keyword analysis results for a fresh start.', 'seo-booster' );
?></li>
							<li>🗑️ <?php 
esc_html_e( 'Cancels and removes all scheduled Action Scheduler jobs related to SEO Booster.', 'seo-booster' );
?></li>
							<li>🗑️ <?php 
esc_html_e( 'Clears all AI bot visits tracking data.', 'seo-booster' );
?></li>
							<li><strong>🗑️ <?php 
esc_html_e( 'Removes all GSC options, including authentication, keyword data, and selected site.', 'seo-booster' );
?></strong></li>
						</ul>
						<?php 
esc_html_e( "Keywords to links you've created will remain intact.", 'seo-booster' );
?>
						<?php 
submit_button( esc_html__( 'Clear All Data and Options', 'seo-booster' ), 'secondary', 'submit_allempty' );
?>
						<p class="description" for="submit">⚠️ <?php 
esc_html_e( "Warning: This will permanently change your database. Proceed with care—there's no going back!", 'seo-booster' );
?></p>
					</td>
				</tr>
			</tbody>
		</table>
		
	</div>
	
	<!-- Stats Tab -->
	<div id="stats-tab" class="sb-tab-content">
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th colspan="2">
						<h2><?php 
esc_html_e( 'Database Statistics', 'seo-booster' );
?></h2>
					</th>
				</tr>
				<tr valign="top">
					<td colspan="2">
						<?php 
\Cleverplugins\SEOBooster\Google_API::display_data_size();
?>
					</td>
				</tr>
				<tr valign="top">
					<th colspan="2">
						<h2><?php 
esc_html_e( 'Cron Jobs Status', 'seo-booster' );
?></h2>
					</th>
				</tr>
				<tr valign="top">
					<td colspan="2">
						<?php 
// Get all cron jobs
$cron_jobs = _get_cron_array();
$seobooster_crons = array();
// Filter for SEO Booster cron jobs
foreach ( $cron_jobs as $timestamp => $cron ) {
    foreach ( $cron as $hook => $events ) {
        if ( strpos( $hook, 'seobooster' ) !== false ) {
            foreach ( $events as $key => $event ) {
                $seobooster_crons[] = array(
                    'hook'      => $hook,
                    'timestamp' => $timestamp,
                    'args'      => $event['args'],
                    'schedule'  => $event['schedule'],
                );
            }
        }
    }
}
if ( !empty( $seobooster_crons ) ) {
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th class="manage-column">' . esc_html__( 'Hook', 'seo-booster' ) . '</th><th class="manage-column">' . esc_html__( 'Next Execution', 'seo-booster' ) . '</th><th class="manage-column">' . esc_html__( 'Schedule', 'seo-booster' ) . '</th><th class="manage-column">' . esc_html__( 'Time Until Next', 'seo-booster' ) . '</th></tr></thead>';
    echo '<tbody>';
    foreach ( $seobooster_crons as $cron ) {
        $next_execution = gmdate( 'Y-m-d H:i:s', $cron['timestamp'] );
        $time_until = human_time_diff( time(), $cron['timestamp'] );
        $schedule = ( $cron['schedule'] ? $cron['schedule'] : esc_html__( 'Once', 'seo-booster' ) );
        echo '<tr>';
        echo '<td class="column-hook"><code>' . esc_html( $cron['hook'] ) . '</code></td>';
        echo '<td class="column-next-execution">' . esc_html( $next_execution ) . '</td>';
        echo '<td class="column-schedule">' . esc_html( $schedule ) . '</td>';
        echo '<td class="column-time-until">' . esc_html( $time_until ) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<p class="description">' . esc_html__( 'No SEO Booster cron jobs found.', 'seo-booster' ) . '</p>';
}
?>
					</td>
				</tr>
				<tr valign="top">
					<th colspan="2">
						<h2><?php 
esc_html_e( 'Scheduled Actions Statistics', 'seo-booster' );
?></h2>
					</th>
				</tr>
				<tr valign="top">
					<td colspan="2">
						<?php 
// Check if Action Scheduler is available
if ( class_exists( 'ActionScheduler_Store' ) ) {
    global $wpdb;
    // Get stats for SEO Booster actions by querying the database directly
    $seobooster_actions = array(
        'pending'     => 0,
        'in-progress' => 0,
        'complete'    => 0,
        'failed'      => 0,
        'canceled'    => 0,
    );
    // SEO Booster hooks to check
    $seobooster_hooks = array(
        'sb_gsc_process_url_keywords',
        'sb_gsc_schedule_all_pages',
        'sb_gsc_analyze_post_keywords',
        'sb_gsc_process_keywords_batch'
    );
    // Query for SEO Booster related hooks
    $seobooster_hook_patterns = ['sb_gsc_%', 'seobooster_%', '%seo_booster%'];
    $hook_placeholders = implode( ' OR hook LIKE ', array_fill( 0, count( $seobooster_hook_patterns ), '%s' ) );
    $group_results = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) as count \n\t\t\t\t\t\t\t\tFROM {$wpdb->prefix}actionscheduler_actions \n\t\t\t\t\t\t\t\tWHERE hook LIKE {$hook_placeholders} \n\t\t\t\t\t\t\t\tGROUP BY status", $seobooster_hook_patterns ) );
    foreach ( $group_results as $result ) {
        if ( isset( $seobooster_actions[$result->status] ) ) {
            $seobooster_actions[$result->status] += intval( $result->count );
        }
    }
    // Query for specific SEO Booster hooks
    $hook_placeholders = implode( ',', array_fill( 0, count( $seobooster_hooks ), '%s' ) );
    $hook_results = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) as count \n\t\t\t\t\t\t\t\tFROM {$wpdb->prefix}actionscheduler_actions \n\t\t\t\t\t\t\t\tWHERE hook IN ({$hook_placeholders}) \n\t\t\t\t\t\t\t\tGROUP BY status", $seobooster_hooks ) );
    foreach ( $hook_results as $result ) {
        if ( isset( $seobooster_actions[$result->status] ) ) {
            $seobooster_actions[$result->status] += intval( $result->count );
        }
    }
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th class="manage-column">' . esc_html__( 'Status', 'seo-booster' ) . '</th><th class="manage-column">' . esc_html__( 'Count', 'seo-booster' ) . '</th></tr></thead>';
    echo '<tbody>';
    foreach ( $seobooster_actions as $status => $count ) {
        $status_label = ucfirst( str_replace( '-', ' ', $status ) );
        $color = '';
        $class = '';
        switch ( $status ) {
            case 'pending':
                $color = '#f0ad4e';
                $class = 'status-warning';
                break;
            case 'in-progress':
                $color = '#5bc0de';
                $class = 'status-info';
                break;
            case 'complete':
                $color = '#5cb85c';
                $class = 'status-success';
                break;
            case 'failed':
                $color = '#d9534f';
                $class = 'status-error';
                break;
            case 'canceled':
                $color = '#777';
                $class = 'status-neutral';
                break;
        }
        echo '<tr>';
        echo '<td class="column-status"><span class="status-' . esc_attr( $status ) . ' ' . esc_attr( $class ) . '">' . esc_html( $status_label ) . '</span></td>';
        echo '<td class="column-count">' . esc_html( $count ) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<p>' . esc_html__( 'Action Scheduler is not available.', 'seo-booster' ) . '</p>';
}
?>
					</td>
				</tr>
				
				<!-- Cache Management Section -->
				<tr valign="top">
					<th colspan="2">
						<h2><?php 
esc_html_e( 'Cache Management', 'seo-booster' );
?></h2>
						<p class="description"><?php 
esc_html_e( 'Manage the SEO Booster cache files used for full page analysis.', 'seo-booster' );
?></p>
					</th>
				</tr>
				<tr valign="top">
					<td colspan="2">
						<?php 
// Get cache statistics
$cache_dir = WP_CONTENT_DIR . '/cache/seo-booster/';
$cache_stats = array(
    'files'  => 0,
    'size'   => 0,
    'oldest' => null,
    'newest' => null,
);
if ( is_dir( $cache_dir ) ) {
    // Only count .txt files (cache files)
    $files = glob( $cache_dir . '*.txt' );
    $cache_stats['files'] = count( $files );
    foreach ( $files as $file ) {
        if ( is_file( $file ) ) {
            $file_size = filesize( $file );
            $file_time = filemtime( $file );
            $cache_stats['size'] += $file_size;
            if ( $cache_stats['oldest'] === null || $file_time < $cache_stats['oldest'] ) {
                $cache_stats['oldest'] = $file_time;
            }
            if ( $cache_stats['newest'] === null || $file_time > $cache_stats['newest'] ) {
                $cache_stats['newest'] = $file_time;
            }
        }
    }
}
?>
						
						<div class="sb-cache-stats">
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th class="manage-column"><?php 
esc_html_e( 'Cache Information', 'seo-booster' );
?></th>
										<th class="manage-column"><?php 
esc_html_e( 'Value', 'seo-booster' );
?></th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td><strong><?php 
esc_html_e( 'Cache Directory', 'seo-booster' );
?></strong></td>
										<td><code><?php 
echo esc_html( $cache_dir );
?></code></td>
									</tr>
									<tr>
										<td><strong><?php 
esc_html_e( 'Total Files', 'seo-booster' );
?></strong></td>
										<td><?php 
echo esc_html( $cache_stats['files'] );
?></td>
									</tr>
									<tr>
										<td><strong><?php 
esc_html_e( 'Total Size', 'seo-booster' );
?></strong></td>
										<td><?php 
echo esc_html( size_format( $cache_stats['size'] ) );
?></td>
									</tr>
									<?php 
if ( $cache_stats['oldest'] ) {
    ?>
									<tr>
										<td><strong><?php 
    esc_html_e( 'Oldest File', 'seo-booster' );
    ?></strong></td>
										<td><?php 
    echo esc_html( date( 'Y-m-d H:i:s', $cache_stats['oldest'] ) );
    ?> 
											<small>(<?php 
    echo esc_html( human_time_diff( $cache_stats['oldest'] ) );
    ?> <?php 
    esc_html_e( 'ago', 'seo-booster' );
    ?>)</small>
										</td>
									</tr>
									<?php 
}
?>
									<?php 
if ( $cache_stats['newest'] ) {
    ?>
									<tr>
										<td><strong><?php 
    esc_html_e( 'Newest File', 'seo-booster' );
    ?></strong></td>
										<td><?php 
    echo esc_html( date( 'Y-m-d H:i:s', $cache_stats['newest'] ) );
    ?> 
											<small>(<?php 
    echo esc_html( human_time_diff( $cache_stats['newest'] ) );
    ?> <?php 
    esc_html_e( 'ago', 'seo-booster' );
    ?>)</small>
										</td>
									</tr>
									<?php 
}
?>
									<tr>
										<td><strong><?php 
esc_html_e( 'Cache Status', 'seo-booster' );
?></strong></td>
										<td>
											<?php 
if ( $cache_stats['files'] > 0 ) {
    ?>
												<span class="status-success"><?php 
    esc_html_e( 'Active', 'seo-booster' );
    ?></span>
											<?php 
} else {
    ?>
												<span class="status-neutral"><?php 
    esc_html_e( 'Empty', 'seo-booster' );
    ?></span>
											<?php 
}
?>
										</td>
									</tr>
								</tbody>
							</table>
							
							<?php 
if ( $cache_stats['files'] > 0 ) {
    ?>
							<div class="sb-cache-actions" style="margin-top: 15px;">
								<button type="submit" name="clear_seo_cache" value="1" class="button button-secondary" onclick="return confirm('<?php 
    esc_attr_e( 'Are you sure you want to clear all cache files? This action cannot be undone.', 'seo-booster' );
    ?>');">
									<span class="dashicons dashicons-trash"></span>
									<?php 
    esc_html_e( 'Clear All Cache', 'seo-booster' );
    ?>
								</button>
								
								<p class="description" style="margin-top: 10px;">
									<?php 
    esc_html_e( 'Clearing the cache will remove all stored page content. New analysis will re-download pages as needed.', 'seo-booster' );
    ?>
								</p>
							</div>
							<?php 
}
?>
						</div>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	
	
	<!-- Save Changes Button -->
	<div class="sb-seo-save-section">
		<table class="form-table">
			<tbody>
				<tr>
					<td colspan="2">
						<?php 
submit_button(
    __( 'Save Changes', 'seo-booster' ),
    'primary',
    'submit',
    false,
    array(
        'style' => 'font-size: 14px; padding: 8px 20px;',
    )
);
?>
						<p class="description">
							<?php 
esc_html_e( 'Save all settings across all tabs.', 'seo-booster' );
?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	</form>
</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Error and success message functions
    function showError(message) {
        hideMessages();
        var errorDiv = $('<div class="sb-error-message" style="background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb; border-radius: 4px; display: flex; align-items: center;"><span class="dashicons dashicons-warning" style="margin-right: 8px;"></span>' + message + '<button type="button" class="sb-close-message" style="margin-left: auto; background: none; border: none; color: #721c24; cursor: pointer; font-size: 16px;">&times;</button></div>');
        $('.sb-llm-settings').prepend(errorDiv);
        
        // Auto-hide after 10 seconds
        setTimeout(function() {
            errorDiv.fadeOut(300, function() {
                $(this).remove();
            });
        }, 10000);
    }
    
    function showSuccess(message) {
        hideMessages();
        var successDiv = $('<div class="sb-success-message" style="background: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border: 1px solid #c3e6cb; border-radius: 4px; display: flex; align-items: center;"><span class="dashicons dashicons-yes-alt" style="margin-right: 8px;"></span>' + message + '<button type="button" class="sb-close-message" style="margin-left: auto; background: none; border: none; color: #155724; cursor: pointer; font-size: 16px;">&times;</button></div>');
        $('.sb-llm-settings').prepend(successDiv);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            successDiv.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    function hideMessages() {
        $('.sb-error-message, .sb-success-message').remove();
    }
    
    // Close message handler
    $(document).on('click', '.sb-close-message', function() {
        $(this).parent().fadeOut(300, function() {
            $(this).remove();
        });
    });

    // LLM Settings functionality
    var llmSettings = {
        ajaxUrl: '<?php 
echo esc_url( admin_url( 'admin-ajax.php' ) );
?>',
        nonce: '<?php 
echo esc_js( wp_create_nonce( 'sb_seo_metabox_nonce' ) );
?>',
        restUrl: '<?php 
echo esc_url( rest_url( 'seo-booster/v1/' ) );
?>',
        restNonce: '<?php 
echo esc_js( wp_create_nonce( 'wp_rest' ) );
?>'
    };

    // Credits Registration
    $('#sb-credits-register-btn').on('click', function() {
        var $btn = $(this);
        var $spinner = $('#sb-credits-register-spinner');
        var email = $('#sb-credits-register-email').val();

        if (!email) {
            showError('Please enter your email address.');
            return;
        }

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.ajax({
            url: llmSettings.restUrl + 'credits/register',
            method: 'POST',
            headers: { 'X-WP-Nonce': llmSettings.restNonce },
            contentType: 'application/json',
            data: JSON.stringify({ email: email }),
            success: function(data) {
                if (data.success) {
                    showSuccess('Connected successfully! Reload the page to see your credit balance.');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showError(data.error || 'Registration failed.');
                }
            },
            error: function(xhr) {
                var msg = 'Registration failed.';
                if (xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                showError(msg);
            },
            complete: function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });

    // Refresh balance
    $('#sb-credits-refresh-btn').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').css('animation', 'spin 1s linear infinite');

        $.ajax({
            url: llmSettings.restUrl + 'credits/balance',
            method: 'GET',
            headers: { 'X-WP-Nonce': llmSettings.restNonce },
            success: function(data) {
                if (data.credits_remaining !== undefined) {
                    $('#sb-credits-balance-value').text(Number(data.credits_remaining).toLocaleString());
                }
            },
            complete: function() {
                $btn.prop('disabled', false).find('.dashicons').css('animation', '');
            }
        });
    });

    // Sync purchases (after checkout so new credits appear)
    $('#sb-credits-sync-purchases-btn').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: llmSettings.restUrl + 'credits/sync-purchases',
            method: 'POST',
            headers: { 'X-WP-Nonce': llmSettings.restNonce, 'Content-Type': 'application/json' },
            contentType: 'application/json',
            data: JSON.stringify({}),
            success: function(data) {
                if (data.credits_balance !== undefined) {
                    $('#sb-credits-balance-value').text(Number(data.credits_balance).toLocaleString());
                }
                if (data.credits_added > 0) {
                    showSuccess(data.credits_added + ' ' + (data.credits_added === 1 ? 'credit' : 'credits') + ' added. Balance updated.');
                } else if (data.purchases_processed === 0) {
                    showSuccess('No new purchases to sync. Balance refreshed.');
                } else {
                    showSuccess('Balance refreshed.');
                }
            },
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Sync failed.';
                showError(msg);
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
});
</script>

<?php 