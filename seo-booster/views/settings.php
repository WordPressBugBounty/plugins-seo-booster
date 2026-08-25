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
$ai_bot_tracking = get_option( 'seobooster_ai_bot_tracking', 'on' );
$ai_referral_tracking = get_option( 'seobooster_ai_referral_tracking', 'on' );
$ai_bot_track_mapped_only = get_option( 'seobooster_ai_bot_track_mapped_only', 'on' );
$ai_bot_retention_days = (int) get_option( 'seobooster_ai_bot_retention_days', 90 );
$seobooster_delete_deactivate = get_option( 'seobooster_delete_deactivate', '' );
$seo_analysis_batch_size = get_option( 'seobooster_seo_analysis_batch_size', 1 );
$seo_possibilities_enabled = get_option( 'seobooster_seo_possibilities_enabled', 'on' );
$seo_possibilities_frequency = get_option( 'seobooster_seo_possibilities_frequency', 60 );
// Form processing is now handled by Form_Processor class in admin_init() method
?>
<div class="wrap sb-wrap sb-dashboard sb-settings-page">
	<?php 
echo wp_kses_post( Utils::show_plugin_headline( __( 'Settings', 'seo-booster' ), true ) );
settings_errors( 'seobooster_messages' );
echo '<div class="sb-admin-seo-compat-wrap">';
require SEOBOOSTER_PLUGINPATH . 'inc/views/seo-plugin-compat-notice.php';
echo '</div>';
?>
	<section class="sb-ui-panel seo-booster-settings-page" aria-labelledby="sb-settings-panel-title">
		<h2 class="sb-ui-title screen-reader-text" id="sb-settings-panel-title"><?php 
esc_html_e( 'Settings', 'seo-booster' );
?></h2>
	<!-- Tab Navigation -->
	<div class="sb-seo-tabs">
		<nav class="nav-tab-wrapper">

			<a href="#ai-llm" class="nav-tab nav-tab-active" data-tab="ai-llm">
				<span class="dashicons dashicons-lightbulb"></span>
				<?php 
esc_html_e( 'AI/LLM', 'seo-booster' );
?>
			</a>
			<a href="#automatic-links" class="nav-tab" data-tab="automatic-links">
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
									<input type="checkbox" id="seobooster_weekly_email" name="seobooster_weekly_email" value="on" 
									<?php 
if ( 'on' === $seobooster_weekly_email ) {
    echo " checked='checked'";
}
?>
									/>
									<span class="sb-toggle-slider"></span>
								</div>
								<span><?php 
esc_html_e( 'Send a weekly email with Google Search performance and SEO opportunities.', 'seo-booster' );
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
					<tr valign="top">
						<th scope="row" valign="top">
							<?php 
esc_html_e( 'Setup Wizard', 'seo-booster' );
?>
						</th>
						<td>
							<a class="button" href="<?php 
echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sb_setup_restart' ), 'sb_setup_restart' ) );
?>">
								<?php 
esc_html_e( 'Run setup again', 'seo-booster' );
?>
							</a>
							<p class="description"><?php 
esc_html_e( 'Walk through connecting Search Console, email, automatic links, and more. Your existing data stays intact.', 'seo-booster' );
?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Automatic Links Tab -->
		<div id="automatic-links-tab" class="sb-tab-content">
			<table class="form-table sb-autolink-settings" role="presentation">
				<tbody>
					<tr valign="top">
						<th colspan="2">
							<h2><?php 
esc_html_e( 'Automatic Links', 'seo-booster' );
?></h2>
							<p class="description">
								<?php 
esc_html_e( 'Automatically turn keywords into internal links while visitors read your posts and pages. Manage which keywords to link on the Automatic Links screen.', 'seo-booster' );
?>
								<a href="<?php 
echo esc_url( admin_url( 'admin.php?page=sb2_autolink' ) );
?>"><?php 
esc_html_e( 'Manage keywords', 'seo-booster' );
?></a>
							</p>
						</th>
					</tr>
					<tr valign="top">
						<th scope="row">
							<?php 
esc_html_e( 'Automatic linking', 'seo-booster' );
?>
						</th>
						<td>
							<label class="sb-toggle-label">
								<div class="sb-toggle-switch">
									<input type="checkbox" id="seobooster_internal_linking" name="seobooster_internal_linking" value="on" <?php 
checked( $seobooster_internal_linking, 'on' );
?> />
									<span class="sb-toggle-slider"></span>
								</div>
								<span><?php 
esc_html_e( 'Enable automatic internal links on the frontend', 'seo-booster' );
?></span>
							</label>
							<p class="description">
								<?php 
esc_html_e( 'When enabled, SEO Booster replaces configured keywords with links to the pages you chose. Each post must also have automatic linking turned on individually.', 'seo-booster' );
?>
							</p>
						</td>
					</tr>
					<tr valign="top" class="sb-settings-section-row">
						<th colspan="2">
							<h3 class="sb-settings-section-title"><?php 
esc_html_e( 'Link behavior', 'seo-booster' );
?></h3>
							<p class="description"><?php 
esc_html_e( 'Control how often keywords are linked and how closely text must match.', 'seo-booster' );
?></p>
						</th>
					</tr>
					<tr valign="top">
						<th scope="row">
							<?php 
esc_html_e( 'Repeated keywords', 'seo-booster' );
?>
						</th>
						<td>
							<label class="sb-toggle-label">
								<div class="sb-toggle-switch">
									<input type="checkbox" id="seobooster_replace_kw_multiple" name="seobooster_replace_kw_multiple" value="on" <?php 
checked( $replace_kw_multiple, 'on' );
?> />
									<span class="sb-toggle-slider"></span>
								</div>
								<span><?php 
esc_html_e( 'Link every occurrence of a keyword on the page', 'seo-booster' );
?></span>
							</label>
							<p class="description">
								<?php 
esc_html_e( 'Off (default): only the first match is linked. On: the same keyword can be linked multiple times in one page.', 'seo-booster' );
?>
							</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<?php 
esc_html_e( 'Match capitalization', 'seo-booster' );
?>
						</th>
						<td>
							<label class="sb-toggle-label">
								<div class="sb-toggle-switch">
									<input type="checkbox" id="seobooster_match_capitalization" name="seobooster_match_capitalization" value="on" <?php 
checked( $match_capitalization, 'on' );
?> />
									<span class="sb-toggle-slider"></span>
								</div>
								<span><?php 
esc_html_e( 'Only link text that matches the keyword\'s capitalization', 'seo-booster' );
?></span>
							</label>
							<p class="description">
								<?php 
esc_html_e( 'Example: keyword "WordPress" will not link the word "WordPress".', 'seo-booster' );
?>
							</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="seobooster_replace_kw_limit"><?php 
esc_html_e( 'Links per page', 'seo-booster' );
?></label>
						</th>
						<td>
							<input type="number" id="seobooster_replace_kw_limit" name="seobooster_replace_kw_limit" value="<?php 
echo esc_attr( max( 1, intval( $seobooster_replace_kw_limit ) ) );
?>" class="small-text" step="1" min="1" max="99" />
							<p class="description">
								<?php 
esc_html_e( 'Maximum new automatic links added to a single page. Links that already exist in the content are not counted.', 'seo-booster' );
?>
							</p>
						</td>
					</tr>
					<tr valign="top" class="sb-settings-section-row">
						<th colspan="2">
							<h3 class="sb-settings-section-title"><?php 
esc_html_e( 'Where not to link', 'seo-booster' );
?></h3>
							<p class="description"><?php 
esc_html_e( 'Choose HTML areas that should never receive automatic links. This keeps headings, lists, and quotes clean.', 'seo-booster' );
?></p>
						</th>
					</tr>
					<tr valign="top">
						<th scope="row">
							<?php 
esc_html_e( 'Excluded elements', 'seo-booster' );
?>
						</th>
						<td>
							<?php 
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
$mandatory_elements = array(
    'a'        => __( 'Existing links', 'seo-booster' ),
    'code'     => __( 'Code & preformatted blocks', 'seo-booster' ),
    'script'   => __( 'Scripts', 'seo-booster' ),
    'style'    => __( 'Style tags', 'seo-booster' ),
    'head'     => __( 'Head section', 'seo-booster' ),
    'textarea' => __( 'Form fields & controls', 'seo-booster' ),
    'svg'      => __( 'SVG graphics', 'seo-booster' ),
);
$element_groups = array(
    'headings' => array(
        'label'    => __( 'Headings', 'seo-booster' ),
        'elements' => array(
            'h1' => __( 'Heading 1 (H1)', 'seo-booster' ),
            'h2' => __( 'Heading 2 (H2)', 'seo-booster' ),
            'h3' => __( 'Heading 3 (H3)', 'seo-booster' ),
            'h4' => __( 'Heading 4 (H4)', 'seo-booster' ),
            'h5' => __( 'Heading 5 (H5)', 'seo-booster' ),
            'h6' => __( 'Heading 6 (H6)', 'seo-booster' ),
        ),
    ),
    'lists'    => array(
        'label'    => __( 'Lists', 'seo-booster' ),
        'elements' => array(
            'ul' => __( 'Bullet lists (UL)', 'seo-booster' ),
            'ol' => __( 'Numbered lists (OL)', 'seo-booster' ),
            'dl' => __( 'Definition lists (DL)', 'seo-booster' ),
        ),
    ),
    'other'    => array(
        'label'    => __( 'Other', 'seo-booster' ),
        'elements' => array(
            'blockquote' => __( 'Blockquotes', 'seo-booster' ),
        ),
    ),
);
?>
							<div class="sb-excluded-elements">
								<?php 
foreach ( $element_groups as $group ) {
    ?>
									<fieldset class="sb-excluded-elements__group">
										<legend><?php 
    echo esc_html( $group['label'] );
    ?></legend>
										<div class="sb-excluded-elements__grid">
											<?php 
    foreach ( $group['elements'] as $element => $label ) {
        ?>
												<label class="sb-excluded-elements__item">
													<input type="checkbox" name="seobooster_excluded_elements[<?php 
        echo esc_attr( $element );
        ?>]" value="1" <?php 
        checked( !empty( $excluded_elements[$element] ) );
        ?> />
													<span><?php 
        echo esc_html( $label );
        ?></span>
												</label>
											<?php 
    }
    ?>
										</div>
									</fieldset>
								<?php 
}
?>

								<div class="sb-excluded-elements__locked">
									<p class="sb-excluded-elements__locked-title">
										<span class="dashicons dashicons-lock" aria-hidden="true"></span>
										<?php 
esc_html_e( 'Always excluded', 'seo-booster' );
?>
									</p>
									<ul class="sb-excluded-elements__locked-list">
										<?php 
foreach ( $mandatory_elements as $element => $label ) {
    ?>
											<li><?php 
    echo esc_html( $label );
    ?></li>
										<?php 
}
?>
									</ul>
									<?php 
foreach ( $mandatory_elements as $element => $label ) {
    ?>
										<input type="hidden" name="seobooster_excluded_elements[<?php 
    echo esc_attr( $element );
    ?>]" value="1" />
									<?php 
}
?>
								</div>
							</div>
							<p class="description">
								<?php 
esc_html_e( 'Checked items are skipped. Uncheck an element to allow linking inside it.', 'seo-booster' );
?>
								<a href="<?php 
echo esc_url( Utils::generate_cp_web_link( 'settings_autolink_docs', 'docs/automatic-links/why-arent-my-keywords-being-linked-automatically/' ) );
?>" target="_blank" rel="noopener">
									<?php 
esc_html_e( 'Why wasn\'t my keyword linked?', 'seo-booster' );
?>
								</a>
							</p>
						</td>
					</tr>
					<tr valign="top" class="sb-settings-section-row">
						<th colspan="2">
							<h3 class="sb-settings-section-title"><?php 
esc_html_e( '404 error monitoring', 'seo-booster' );
?></h3>
							<p class="description"><?php 
esc_html_e( 'Optional Pro feature: track broken links visitors hit on your site.', 'seo-booster' );
?></p>
						</th>
					</tr>
					<tr valign="top">
						<th scope="row">
							<?php 
esc_html_e( '404 monitoring', 'seo-booster' );
?>
						</th>
						<td>
							<?php 
$show_404_upsell = true;
if ( $show_404_upsell ) {
    ?>
							<p class="description">
								<?php 
    esc_html_e( 'Upgrade to SEO Booster Pro to monitor and track 404 errors on your site.', 'seo-booster' );
    ?>
							</p>
								<?php 
}
?>
						</td>
					</tr>
					<?php 
if ( !$show_404_upsell ) {
    ?>
					<tr valign="top">
						<th scope="row">
							<?php 
    esc_html_e( 'Ignored URLs', 'seo-booster' );
    ?>
						</th>
						<td>
							<p class="description">
								<?php 
    esc_html_e( 'Common system URLs (feeds, admin, login, etc.) are ignored automatically and will not be logged as 404 errors.', 'seo-booster' );
    ?>
								<a href="<?php 
    echo esc_url( Utils::generate_cp_web_link( 'settings_404_docs', 'docs/404-errors/customizing-the-list-of-ignored-urls/' ) );
    ?>" target="_blank" rel="noopener">
									<?php 
    esc_html_e( 'Customize the ignore list', 'seo-booster' );
    ?>
								</a>
							</p>
						</td>
					</tr>
					<?php 
}
?>
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
        // translators: %s: Google account email address.
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
								<button type="button" class="button sb-oauth-start" data-sb-oauth-destination="settings">
									<?php 
esc_html_e( 'Re-authenticate with Google', 'seo-booster' );
?>
								</button>
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
								<input type="checkbox" id="seobooster_seo_possibilities_enabled" name="seobooster_seo_possibilities_enabled" value="on" 
								<?php 
if ( 'on' === $seo_possibilities_enabled ) {
    echo " checked='checked'";
}
?>
								/>
								<span class="sb-toggle-slider"></span>
							</div>
							<span><?php 
esc_html_e( 'Enable automatic discovery and analysis of URLs from imported Google Search Console data.', 'seo-booster' );
?></span>
						</label>
						<?php 
if ( 'on' === $seo_possibilities_enabled && function_exists( 'as_next_scheduled_action' ) ) {
    $next_scheduled = as_next_scheduled_action( 'sb_seo_possibilities_auto_scan', array(), 'seo-booster' );
    if ( $next_scheduled ) {
        $next_time = gmdate( 'Y-m-d H:i:s', $next_scheduled );
        $current_time = current_time( 'timestamp' );
        $time_diff = human_time_diff( $current_time, $next_scheduled );
        // translators: %s: time difference until the next scheduled scan.
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
	<div id="ai-llm-tab" class="sb-tab-content sb-tab-active">
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
$ai_provider = \Cleverplugins\SEOBooster\LLM_Helper::get_selected_ai_provider();
$credits_ai_available = \Cleverplugins\SEOBooster\Credits_Service::is_ai_provider_available();
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
								<input type="radio" name="seobooster_ai_provider" value="WordPress" <?php 
checked( $ai_provider, 'WordPress' );
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
echo ( 'WordPress' !== $ai_provider ? 'display: none;' : '' );
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
    echo ( 'seobooster' !== $ai_provider ? 'display: none;' : '' );
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
        echo ( 'seobooster' !== $ai_provider ? 'display: none;' : '' );
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
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'AI bot tracking', 'seo-booster' );
?>
					</th>
					<td>
						<label class="sb-toggle-label">
							<div class="sb-toggle-switch">
								<input type="checkbox" id="seobooster_ai_bot_tracking" name="seobooster_ai_bot_tracking" value="on" <?php 
checked( $ai_bot_tracking, 'on' );
?> />
								<span class="sb-toggle-slider"></span>
							</div>
							<span><?php 
esc_html_e( 'Track visits from known AI crawlers and answer engines', 'seo-booster' );
?></span>
						</label>
						<p class="description">
							<?php 
esc_html_e( 'Records which AI bots visit your pages and classifies activity as research/training or citation/answer-engine use. View reports under SEO Booster → AI Bots.', 'seo-booster' );
?>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'AI referral tracking', 'seo-booster' );
?>
					</th>
					<td>
						<label class="sb-toggle-label">
							<div class="sb-toggle-switch">
								<input type="checkbox" id="seobooster_ai_referral_tracking" name="seobooster_ai_referral_tracking" value="on" <?php 
checked( $ai_referral_tracking, 'on' );
?> />
								<span class="sb-toggle-slider"></span>
							</div>
							<span><?php 
esc_html_e( 'Track visitors arriving from ChatGPT, Perplexity, Claude, Gemini, and Copilot', 'seo-booster' );
?></span>
						</label>
						<p class="description">
							<?php 
esc_html_e( 'Records human referral traffic separately from AI bot crawlers. View reports under SEO Booster → AI Bots.', 'seo-booster' );
?>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Track mapped content only', 'seo-booster' );
?>
					</th>
					<td>
						<label class="sb-toggle-label">
							<div class="sb-toggle-switch">
								<input type="checkbox" id="seobooster_ai_bot_track_mapped_only" name="seobooster_ai_bot_track_mapped_only" value="on" <?php 
checked( $ai_bot_track_mapped_only, 'on' );
?> />
								<span class="sb-toggle-slider"></span>
							</div>
							<span><?php 
esc_html_e( 'Skip search traps and unmapped URLs', 'seo-booster' );
?></span>
						</label>
						<p class="description">
							<?php 
esc_html_e( 'When enabled, only visits that resolve to posts, terms, archives, or the homepage are stored. Recommended to keep AI bot reports focused on real content.', 'seo-booster' );
?>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<label for="seobooster_ai_bot_retention_days"><?php 
esc_html_e( 'AI bot data retention', 'seo-booster' );
?></label>
					</th>
					<td>
						<input type="number" id="seobooster_ai_bot_retention_days" name="seobooster_ai_bot_retention_days" value="<?php 
echo esc_attr( $ai_bot_retention_days );
?>" min="7" max="365" step="1" class="small-text" />
						<span><?php 
esc_html_e( 'days', 'seo-booster' );
?></span>
						<p class="description">
							<?php 
esc_html_e( 'Hit rows older than this are removed during daily maintenance. 30–60 days is usually enough for most sites.', 'seo-booster' );
?>
							<a href="<?php 
echo esc_url( admin_url( 'admin.php?page=sb2_settings#stats' ) );
?>">
								<?php 
esc_html_e( 'See database usage on the Stats tab', 'seo-booster' );
?>
							</a>
						</p>
					</td>
				</tr>
				<?php 
$show_ai_bot_blocking_upsell = true;
if ( $show_ai_bot_blocking_upsell ) {
    ?>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
    esc_html_e( 'Block AI bots', 'seo-booster' );
    ?>
					</th>
					<td>
						<p class="description">
							<?php 
    esc_html_e( 'Upgrade to SEO Booster Pro to block AI crawlers at the server when detected (HTTP 403), without relying on robots.txt.', 'seo-booster' );
    ?>
						</p>
					</td>
				</tr>
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
esc_html_e( 'Reset SEO Booster Debug Log', 'seo-booster' );
?>
					</th>
					<td>
						<?php 
submit_button( __( 'Reset SEO Booster Debug Log', 'seo-booster' ), 'secondary', 'submit_reset_log' );
?>
						<p><?php 
esc_html_e( 'Clears SEO Booster → Debug Log (the sb2_log database table). Does not clear WordPress wp-content/debug.log.', 'seo-booster' );
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
						<label class="sb-toggle-label">
							<div class="sb-toggle-switch">
								<input type="checkbox" id="seobooster_delete_deactivate" name="seobooster_delete_deactivate" value="on" <?php 
checked( $seobooster_delete_deactivate, 'on' );
?> />
								<span class="sb-toggle-slider"></span>
							</div>
							<span><?php 
esc_html_e( 'Delete all data when deactivating the plugin. This cannot be undone.', 'seo-booster' );
?></span>
						</label>
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
esc_html_e( 'Resets the SEO Booster Debug Log (sb2_log table).', 'seo-booster' );
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
							<li>🗑️ <?php 
esc_html_e( 'Resets the Setup Wizard so you can walk through first-run setup again.', 'seo-booster' );
?></li>
						</ul>
						<?php 
esc_html_e( "Keywords to links you've created will remain intact.", 'seo-booster' );
?>
						<?php 
submit_button( esc_html__( 'Clear All Data and Options', 'seo-booster' ), 'secondary', 'submit_allempty' );
?>
						<p class="description" for="submit">⚠️ <?php 
esc_html_e( "Warning: This will permanently change your database. Proceed with care. There's no going back!", 'seo-booster' );
?></p>
					</td>
				</tr>
			</tbody>
		</table>
		
	</div>
	
	<!-- Stats Tab -->
	<div id="stats-tab" class="sb-tab-content">
		<div class="sb-stats-section">
			<h2><?php 
esc_html_e( 'Database Statistics', 'seo-booster' );
?></h2>
			<div id="sb-db-stats-container" data-loaded="0">
				<p class="description"><?php 
esc_html_e( 'Loading database statistics…', 'seo-booster' );
?></p>
			</div>
		</div>

		<div class="sb-stats-section">
			<h2><?php 
esc_html_e( 'Cron Jobs Status', 'seo-booster' );
?></h2>
			<?php 
$cron_jobs = _get_cron_array();
$seobooster_crons = array();
foreach ( $cron_jobs as $timestamp => $cron ) {
    foreach ( $cron as $hook => $events ) {
        if ( Utils::is_plugin_cron_hook( $hook ) ) {
            foreach ( $events as $event ) {
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
usort( $seobooster_crons, function ( $a, $b ) {
    return $a['timestamp'] <=> $b['timestamp'];
} );
if ( !empty( $seobooster_crons ) ) {
    ?>
				<div class="sb-stats-table-wrap">
					<table class="wp-list-table widefat fixed striped table-view-list sb-stats-table">
						<thead>
							<tr>
								<th scope="col" class="manage-column column-primary column-hook"><?php 
    esc_html_e( 'Hook', 'seo-booster' );
    ?></th>
								<th scope="col" class="manage-column column-next-execution"><?php 
    esc_html_e( 'Next Execution', 'seo-booster' );
    ?></th>
								<th scope="col" class="manage-column column-schedule"><?php 
    esc_html_e( 'Schedule', 'seo-booster' );
    ?></th>
								<th scope="col" class="manage-column column-time-until"><?php 
    esc_html_e( 'Time Until Next', 'seo-booster' );
    ?></th>
							</tr>
						</thead>
						<tbody>
							<?php 
    foreach ( $seobooster_crons as $cron ) {
        $next_execution = gmdate( 'Y-m-d H:i:s', $cron['timestamp'] );
        $time_until = human_time_diff( time(), $cron['timestamp'] );
        $schedule = ( $cron['schedule'] ? $cron['schedule'] : esc_html__( 'Once', 'seo-booster' ) );
        ?>
								<tr>
									<td class="column-hook column-primary"><code><?php 
        echo esc_html( $cron['hook'] );
        ?></code></td>
									<td class="column-next-execution"><?php 
        echo esc_html( $next_execution );
        ?></td>
									<td class="column-schedule"><?php 
        echo esc_html( $schedule );
        ?></td>
									<td class="column-time-until"><?php 
        echo esc_html( $time_until );
        ?></td>
								</tr>
								<?php 
    }
    ?>
						</tbody>
					</table>
				</div>
				<?php 
} else {
    echo '<p class="description">' . esc_html__( 'No SEO Booster cron jobs found.', 'seo-booster' ) . '</p>';
}
?>
		</div>

		<div class="sb-stats-section">
			<h2><?php 
esc_html_e( 'Scheduled Actions Statistics', 'seo-booster' );
?></h2>
			<?php 
if ( class_exists( 'ActionScheduler_Store' ) ) {
    global $wpdb;
    $seobooster_actions = array(
        'pending'     => 0,
        'in-progress' => 0,
        'complete'    => 0,
        'failed'      => 0,
        'canceled'    => 0,
    );
    $group_slug = Utils::get_action_scheduler_group_slug();
    $group_results = $wpdb->get_results( $wpdb->prepare( "SELECT a.status, COUNT(*) as count\n\t\t\t\t\t\tFROM {$wpdb->prefix}actionscheduler_actions a\n\t\t\t\t\t\tINNER JOIN {$wpdb->prefix}actionscheduler_groups g ON a.group_id = g.group_id\n\t\t\t\t\t\tWHERE g.slug = %s\n\t\t\t\t\t\tGROUP BY a.status", $group_slug ) );
    foreach ( $group_results as $result ) {
        if ( isset( $seobooster_actions[$result->status] ) ) {
            $seobooster_actions[$result->status] += intval( $result->count );
        }
    }
    $hook_results = $wpdb->get_results( $wpdb->prepare( "SELECT a.hook, a.status, COUNT(*) as count\n\t\t\t\t\t\tFROM {$wpdb->prefix}actionscheduler_actions a\n\t\t\t\t\t\tINNER JOIN {$wpdb->prefix}actionscheduler_groups g ON a.group_id = g.group_id\n\t\t\t\t\t\tWHERE g.slug = %s\n\t\t\t\t\t\tGROUP BY a.hook, a.status\n\t\t\t\t\t\tORDER BY a.hook ASC, a.status ASC", $group_slug ) );
    $hook_breakdown = array();
    foreach ( $hook_results as $result ) {
        if ( !isset( $hook_breakdown[$result->hook] ) ) {
            $hook_breakdown[$result->hook] = array(
                'pending'     => 0,
                'in-progress' => 0,
                'complete'    => 0,
                'failed'      => 0,
                'canceled'    => 0,
            );
        }
        if ( isset( $hook_breakdown[$result->hook][$result->status] ) ) {
            $hook_breakdown[$result->hook][$result->status] = intval( $result->count );
        }
    }
    ?>
				<div class="sb-stats-table-wrap">
					<table class="wp-list-table widefat fixed striped table-view-list sb-stats-table">
						<thead>
							<tr>
								<th scope="col" class="manage-column column-primary column-status"><?php 
    esc_html_e( 'Status', 'seo-booster' );
    ?></th>
								<th scope="col" class="manage-column column-count"><?php 
    esc_html_e( 'Count', 'seo-booster' );
    ?></th>
							</tr>
						</thead>
						<tbody>
							<?php 
    foreach ( $seobooster_actions as $action_status => $count ) {
        $status_label = ucfirst( str_replace( '-', ' ', $action_status ) );
        $badge_class = 'status-neutral';
        switch ( $action_status ) {
            case 'pending':
                $badge_class = 'status-warning';
                break;
            case 'in-progress':
                $badge_class = 'status-info';
                break;
            case 'complete':
                $badge_class = 'status-success';
                break;
            case 'failed':
                $badge_class = 'status-error';
                break;
        }
        ?>
								<tr>
									<td class="column-status column-primary">
										<span class="status-<?php 
        echo esc_attr( $action_status );
        ?> <?php 
        echo esc_attr( $badge_class );
        ?>"><?php 
        echo esc_html( $status_label );
        ?></span>
									</td>
									<td class="column-count"><?php 
        echo esc_html( $count );
        ?></td>
								</tr>
								<?php 
    }
    ?>
						</tbody>
					</table>
				</div>
				<?php 
    if ( !empty( $hook_breakdown ) ) {
        ?>
				<div class="sb-stats-table-wrap sb-stats-hook-breakdown">
					<table class="wp-list-table widefat fixed striped table-view-list sb-stats-table">
						<thead>
							<tr>
								<th scope="col" class="manage-column column-primary column-hook"><?php 
        esc_html_e( 'Hook', 'seo-booster' );
        ?></th>
								<th scope="col" class="manage-column column-pending"><?php 
        esc_html_e( 'Pending', 'seo-booster' );
        ?></th>
								<th scope="col" class="manage-column column-in-progress"><?php 
        esc_html_e( 'In progress', 'seo-booster' );
        ?></th>
								<th scope="col" class="manage-column column-complete"><?php 
        esc_html_e( 'Complete', 'seo-booster' );
        ?></th>
								<th scope="col" class="manage-column column-failed"><?php 
        esc_html_e( 'Failed', 'seo-booster' );
        ?></th>
								<th scope="col" class="manage-column column-canceled"><?php 
        esc_html_e( 'Canceled', 'seo-booster' );
        ?></th>
							</tr>
						</thead>
						<tbody>
							<?php 
        foreach ( $hook_breakdown as $hook => $counts ) {
            ?>
							<tr>
								<td class="column-hook column-primary"><code><?php 
            echo esc_html( $hook );
            ?></code></td>
								<td class="column-pending"><?php 
            echo esc_html( $counts['pending'] );
            ?></td>
								<td class="column-in-progress"><?php 
            echo esc_html( $counts['in-progress'] );
            ?></td>
								<td class="column-complete"><?php 
            echo esc_html( $counts['complete'] );
            ?></td>
								<td class="column-failed"><?php 
            echo esc_html( $counts['failed'] );
            ?></td>
								<td class="column-canceled"><?php 
            echo esc_html( $counts['canceled'] );
            ?></td>
							</tr>
							<?php 
        }
        ?>
						</tbody>
					</table>
				</div>
				<?php 
    }
    ?>
				<?php 
} else {
    echo '<p class="description">' . esc_html__( 'Action Scheduler is not available.', 'seo-booster' ) . '</p>';
}
?>
		</div>

		<div class="sb-stats-section">
			<h2><?php 
esc_html_e( 'Cache Management', 'seo-booster' );
?></h2>
			<p class="description"><?php 
esc_html_e( 'Manage the SEO Booster cache files used for full page analysis.', 'seo-booster' );
?></p>
			<?php 
$cache_dir = WP_CONTENT_DIR . '/cache/seo-booster/';
$cache_stats = array(
    'files'  => 0,
    'size'   => 0,
    'oldest' => null,
    'newest' => null,
);
if ( is_dir( $cache_dir ) ) {
    $files = glob( $cache_dir . '*.txt' );
    $cache_stats['files'] = ( is_array( $files ) ? count( $files ) : 0 );
    if ( is_array( $files ) ) {
        foreach ( $files as $file ) {
            if ( is_file( $file ) ) {
                $file_size = filesize( $file );
                $file_time = filemtime( $file );
                $cache_stats['size'] += $file_size;
                if ( null === $cache_stats['oldest'] || $file_time < $cache_stats['oldest'] ) {
                    $cache_stats['oldest'] = $file_time;
                }
                if ( null === $cache_stats['newest'] || $file_time > $cache_stats['newest'] ) {
                    $cache_stats['newest'] = $file_time;
                }
            }
        }
    }
}
?>
			<div class="sb-stats-table-wrap sb-cache-stats">
				<table class="wp-list-table widefat fixed striped table-view-list sb-stats-table">
					<thead>
						<tr>
							<th scope="col" class="manage-column column-primary column-label"><?php 
esc_html_e( 'Cache Information', 'seo-booster' );
?></th>
							<th scope="col" class="manage-column column-value"><?php 
esc_html_e( 'Value', 'seo-booster' );
?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td class="column-label column-primary"><strong><?php 
esc_html_e( 'Cache Directory', 'seo-booster' );
?></strong></td>
							<td class="column-value"><code><?php 
echo esc_html( $cache_dir );
?></code></td>
						</tr>
						<tr>
							<td class="column-label column-primary"><strong><?php 
esc_html_e( 'Total Files', 'seo-booster' );
?></strong></td>
							<td class="column-value"><?php 
echo esc_html( $cache_stats['files'] );
?></td>
						</tr>
						<tr>
							<td class="column-label column-primary"><strong><?php 
esc_html_e( 'Total Size', 'seo-booster' );
?></strong></td>
							<td class="column-value"><?php 
echo esc_html( size_format( $cache_stats['size'] ) );
?></td>
						</tr>
						<?php 
if ( $cache_stats['oldest'] ) {
    ?>
						<tr>
							<td class="column-label column-primary"><strong><?php 
    esc_html_e( 'Oldest File', 'seo-booster' );
    ?></strong></td>
							<td class="column-value">
								<?php 
    echo esc_html( gmdate( 'Y-m-d H:i:s', $cache_stats['oldest'] ) );
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
							<td class="column-label column-primary"><strong><?php 
    esc_html_e( 'Newest File', 'seo-booster' );
    ?></strong></td>
							<td class="column-value">
								<?php 
    echo esc_html( gmdate( 'Y-m-d H:i:s', $cache_stats['newest'] ) );
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
							<td class="column-label column-primary"><strong><?php 
esc_html_e( 'Cache Status', 'seo-booster' );
?></strong></td>
							<td class="column-value column-status">
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
			</div>

			<?php 
if ( $cache_stats['files'] > 0 ) {
    ?>
			<div class="sb-cache-actions">
				<button type="submit" name="clear_seo_cache" value="1" class="button button-secondary" onclick="return confirm('<?php 
    echo esc_js( __( 'Are you sure you want to clear all cache files? This action cannot be undone.', 'seo-booster' ) );
    ?>');">
					<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					<?php 
    esc_html_e( 'Clear All Cache', 'seo-booster' );
    ?>
				</button>
				<p class="description">
					<?php 
    esc_html_e( 'Clearing the cache will remove all stored page content. New analysis will re-download pages as needed.', 'seo-booster' );
    ?>
				</p>
			</div>
			<?php 
}
?>
		</div>
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
	</section>
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