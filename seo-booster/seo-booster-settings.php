<?php

namespace Cleverplugins\SEOBooster;

use function as_get_scheduled_actions;
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !current_user_can( 'update_plugins' ) ) {
    wp_die( esc_html__( 'You are not allowed to update plugins on this blog.', 'seo-booster' ) );
}
global $wpdb, $seobooster2;
if ( isset( $_POST['page'] ) && 'sb2_settings' === sanitize_text_field( wp_unslash( $_POST['page'] ) ) ) {
    if ( isset( $_REQUEST['_wpnonce'] ) ) {
        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
    } else {
        $nonce = '';
    }
    if ( !wp_verify_nonce( $nonce, 'seobooster_save_settings' ) ) {
        die( 'Security check failed.' );
    }
    if ( isset( $_POST['seobooster_internal_linking'] ) ) {
        update_option( 'seobooster_internal_linking', sanitize_text_field( wp_unslash( $_POST['seobooster_internal_linking'] ) ) );
    } else {
        delete_option( 'seobooster_internal_linking' );
    }
    if ( isset( $_POST['seobooster_replace_kw_multiple'] ) ) {
        update_option( 'seobooster_replace_kw_multiple', sanitize_text_field( wp_unslash( $_POST['seobooster_replace_kw_multiple'] ) ) );
    } else {
        delete_option( 'seobooster_replace_kw_multiple' );
    }
    if ( isset( $_POST['seobooster_replace_kw_limit'] ) ) {
        update_option( 'seobooster_replace_kw_limit', intval( $_POST['seobooster_replace_kw_limit'] ) );
    }
    if ( isset( $_POST['seobooster_delete_deactivate'] ) ) {
        update_option( 'seobooster_delete_deactivate', sanitize_key( $_POST['seobooster_delete_deactivate'] ) );
    } else {
        delete_option( 'seobooster_delete_deactivate' );
    }
    if ( isset( $_POST['seobooster_weekly_email'] ) ) {
        update_option( 'seobooster_weekly_email', sanitize_text_field( wp_unslash( $_POST['seobooster_weekly_email'] ) ) );
    } else {
        delete_option( 'seobooster_weekly_email' );
    }
    if ( isset( $_POST['seobooster_weekly_email_recipient'] ) ) {
        update_option( 'seobooster_weekly_email_recipient', sanitize_text_field( wp_unslash( $_POST['seobooster_weekly_email_recipient'] ) ) );
    } else {
        delete_option( 'seobooster_weekly_email_recipient' );
    }
    if ( isset( $_POST['seobooster_ignorelist'] ) ) {
        update_option( 'seobooster_ignorelist', sanitize_text_field( wp_unslash( $_POST['seobooster_ignorelist'] ) ) );
    }
}
if ( isset( $_POST['submit_dbupdates'] ) ) {
    $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
    if ( !wp_verify_nonce( $nonce, 'seobooster_do_actions' ) ) {
        die( 'Security check failed.' );
    }
    if ( !current_user_can( 'manage_options' ) ) {
        die( 'Security check failed.' );
    }
    self::seobooster_activate( false );
}
if ( isset( $_POST['schedule_all_pages'] ) ) {
    if ( !isset( $_POST['schedule_all_pages_nonce_field'] ) ) {
        add_settings_error(
            'seobooster_messages',
            'seobooster_message',
            __( 'Security check failed.', 'seo-booster' ),
            'error'
        );
        return;
    }
    $nonce = sanitize_text_field( wp_unslash( $_POST['schedule_all_pages_nonce_field'] ) );
    if ( wp_verify_nonce( $nonce, 'schedule_all_pages_nonce' ) ) {
        do_action( 'sb_gsc_schedule_all_pages' );
        add_settings_error(
            'seobooster_messages',
            'seobooster_message',
            __( 'All pages have been scheduled for GSC data processing.', 'seo-booster' ),
            'updated'
        );
    } else {
        add_settings_error(
            'seobooster_messages',
            'seobooster_message',
            __( 'Security check failed.', 'seo-booster' ),
            'error'
        );
    }
}
if ( isset( $_POST['submit_reset_log'] ) ) {
    $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
    if ( !wp_verify_nonce( $nonce, 'seobooster_do_actions' ) ) {
        die( 'Security check failed.' );
    }
    if ( !current_user_can( 'manage_options' ) ) {
        die( 'Security check failed.' );
    }
    global $wpdb;
    $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}sb2_log;" );
    Utils::log( __( 'Log emptied manually', 'seo-booster' ) );
}
if ( !empty( $_POST['submit_send_email'] ) ) {
    $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
    if ( !wp_verify_nonce( $nonce, 'seobooster_do_actions' ) ) {
        die( esc_html__( 'Security check failed.', 'seo-booster' ) );
    }
    if ( !current_user_can( 'manage_options' ) ) {
        die( esc_html__( 'Permission denied.', 'seo-booster' ) );
    }
    email_status::send_email_update( 7, true );
}
/* *** Empty the database *** */
if ( !empty( $_POST['submit_dbempty'] ) ) {
    $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
    if ( !wp_verify_nonce( $nonce, 'seobooster_do_actions' ) ) {
        die( esc_html__( 'Security check failed.', 'seo-booster' ) );
    }
    if ( !current_user_can( 'manage_options' ) ) {
        die( esc_html__( 'Permission denied.', 'seo-booster' ) );
    }
    // Clean all transients related to keyword analysis
    $cleaned_count = $wpdb->query( "DELETE FROM {$wpdb->options} \n\t\tWHERE option_name LIKE '_transient_sb_gsc_keyword_usage_%' \n\t\tOR option_name LIKE '_transient_timeout_sb_gsc_keyword_usage_%'" );
    Utils::log( sprintf( 
        /* translators: %s: number of cleaned transients */
        esc_html__( 'Keyword analysis transients cleaned: %s', 'seo-booster' ),
        number_format_i18n( $cleaned_count )
     ), 5 );
    // Clean all action scheduler jobs related to SEO Booster
    $cleaned_jobs = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}actionscheduler_actions \n\t\t\tWHERE hook LIKE %s", 'sb_gsc_%' ) );
    Utils::log( sprintf( 
        /* translators: %s: number of cleaned action scheduler jobs */
        esc_html__( 'SEO Booster action scheduler jobs cleaned: %s', 'seo-booster' ),
        number_format_i18n( $cleaned_jobs )
     ), 5 );
    // Clean all action scheduler logs related to SEO Booster
    $cleaned_logs = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}actionscheduler_logs \n\t\t\tWHERE action_id IN (\n\t\t\t\tSELECT action_id \n\t\t\t\tFROM {$wpdb->prefix}actionscheduler_actions \n\t\t\t\tWHERE hook LIKE %s\n\t\t\t)", 'sb_gsc_%' ) );
    Utils::log( sprintf( 
        /* translators: %s: number of cleaned action scheduler logs */
        esc_html__( 'SEO Booster action scheduler logs cleaned: %s', 'seo-booster' ),
        number_format_i18n( $cleaned_logs )
     ), 5 );
    $table_array = array($wpdb->prefix . 'sb2_query_keywords', $wpdb->prefix . 'sb2_query_keywords_history');
    foreach ( $table_array as $table ) {
        $wpdb->query( "TRUNCATE TABLE {$table}" );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
    Utils::log( esc_html__( 'Database tables emptied', 'seo-booster' ), 5 );
}
// *** Completely empty the database ***
if ( !empty( $_POST['submit_allempty'] ) ) {
    $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
    if ( !wp_verify_nonce( $nonce, 'seobooster_do_actions' ) ) {
        die( esc_html__( 'Security check failed.', 'seo-booster' ) );
    }
    if ( !current_user_can( 'manage_options' ) ) {
        die( esc_html__( 'Security check failed.', 'seo-booster' ) );
    }
    $table_array = array(
        $wpdb->prefix . 'sb2_404',
        $wpdb->prefix . 'sb2_log',
        $wpdb->prefix . 'sb2_query_keywords',
        $wpdb->prefix . 'sb2_query_keywords_history'
    );
    foreach ( $table_array as $table ) {
        $wpdb->query( "TRUNCATE TABLE {$table}" );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
    // Clean all transients related to keyword analysis
    $cleaned_count = $wpdb->query( "DELETE FROM {$wpdb->options} \n\t\tWHERE option_name LIKE '_transient_sb_gsc_keyword_usage_%' \n\t\tOR option_name LIKE '_transient_timeout_sb_gsc_keyword_usage_%'" );
    Utils::log( sprintf( 
        /* translators: %s: number of cleaned transients */
        esc_html__( 'Keyword analysis transients cleaned: %s', 'seo-booster' ),
        number_format_i18n( $cleaned_count )
     ), 5 );
    // Clean all action scheduler jobs related to SEO Booster
    $cleaned_jobs = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}actionscheduler_actions \n\t\t\tWHERE hook LIKE %s", 'sb_gsc_%' ) );
    Utils::log( sprintf( 
        /* translators: %s: number of cleaned action scheduler jobs */
        esc_html__( 'SEO Booster action scheduler jobs cleaned: %s', 'seo-booster' ),
        number_format_i18n( $cleaned_jobs )
     ), 5 );
    delete_option( 'seobooster_access_token' );
    delete_option( 'seobooster_gsc_refresh_token' );
    delete_option( 'seobooster_selected_site' );
    delete_option( 'seobooster_gsc_access_token' );
    Utils::log( esc_html__( 'Database tables emptied and options deleted.', 'seo-booster' ), 5 );
    wp_safe_redirect( admin_url( 'admin.php?page=sb2_dashboard' ) );
    exit;
}
global $seobooster_fs;
$seobooster_replace_kw_limit = get_option( 'seobooster_replace_kw_limit', 10 );
$seobooster_internal_linking = get_option( 'seobooster_internal_linking' );
$replace_kw_multiple = get_option( 'seobooster_replace_kw_multiple' );
$seobooster_weekly_email = get_option( 'seobooster_weekly_email' );
$seobooster_weekly_email_recipient = get_option( 'seobooster_weekly_email_recipient' );
$fof_monitoring = get_option( 'seobooster_fof_monitoring' );
$seobooster_delete_deactivate = get_option( 'seobooster_delete_deactivate' );
?>
<div class="wrap">
	<?php 
echo wp_kses_post( Utils::show_plugin_headline( __( 'Settings', 'seo-booster' ), true ) );
settings_errors( 'seobooster_messages' );
?>

	<form method="post" id="seobooster_settings" action="<?php 
echo esc_url( admin_url( 'admin.php?page=sb2_settings' ) );
?>">
		<table class="form-table">
			<tbody>
				<tr valign="top" id="autolinks">
					<th colspan="2">
						<h2>
							<?php 
esc_html_e( 'Automatic Links', 'seo-booster' );
?>
						</h2>
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
esc_html_e( 'Maximum Replacements', 'seo-booster' );
?>
					</th>
					<td>
						<input type="number" id="seobooster_replace_kw_limit" name="seobooster_replace_kw_limit" value="<?php 
echo esc_attr( $seobooster_replace_kw_limit );
?>" class="small-text" step="1" min="1" max="25" />
						<p class="description"><label for="seobooster_replace_kw_limit">
								<?php 
esc_html_e( 'Maximum number of links created per post. It does not include current links in the content.', 'seo-booster' );
?>
							</label></p>
					</td>
				</tr>
				<?php 
$cssclasses = '';
?>
				<tr valign="top">
					<th scope="row" valign="top" class="<?php 
echo esc_attr( $cssclasses );
?>">WooCommerce</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php 
esc_html_e( 'Enable keyword link in WooCommerce product descriptions', 'seo-booster' );
?></span></legend>
							<label for="seobooster_woocommerce">
								<?php 
?>
								<?php 
if ( !seobooster_fs()->can_use_premium_code() ) {
    ?>
									<p class="description"><?php 
    esc_html_e( 'Upgrade to SEO Booster Pro to unlock this feature.', 'seo-booster' );
    ?></p>
								<?php 
}
?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr valign="top">
					<th colspan="2">
						<h2>
							<?php 
esc_html_e( 'Weekly Email Reports', 'seo-booster' );
?>
						</h2>
					</th>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Enable/Disable', 'seo-booster' );
?>
					</th>
					<td>
						<input type="checkbox" id="seobooster_weekly_email" name="seobooster_weekly_email" value="on" <?php 
if ( 'on' === $seobooster_weekly_email ) {
    echo " checked='checked'";
}
?> />
						<p class="description"><label for="seobooster_weekly_email">
								<?php 
esc_html_e( 'Send a weekly email with new information from the past week.', 'seo-booster' );
?>
							</label></p>
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
					<th colspan="2">
						<h2><?php 
esc_html_e( '404 Errors', 'seo-booster' );
?></h2>
					</th>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top" class="<?php 
echo esc_attr( $cssclasses );
?>">
						<p><?php 
esc_html_e( 'Enable/Disable', 'seo-booster' );
?></p>
					</th>
					<td>
						<?php 
if ( !seobooster_fs()->can_use_premium_code() ) {
    ?>
							<p class="description"><?php 
    esc_html_e( 'Upgrade to SEO Booster Pro to unlock this feature.', 'seo-booster' );
    ?></p>
						<?php 
}
?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top" class="<?php 
echo esc_attr( $cssclasses );
?>"><?php 
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
				<tr valign="top">
					<th colspan="2">
						<hr>
					</th>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Delete data on deactivate', 'seo-booster' );
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
				<tr>
					<td colspan="2">
						<input type="hidden" name="page" value="sb2_settings">
						<?php 
submit_button();
?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php 
wp_nonce_field( 'seobooster_save_settings' );
?>
	</form>
	<hr>
	<h3>
		<?php 
esc_html_e( 'Tools', 'seo-booster' );
?>
	</h3>
	<form method="post" action="<?php 
echo esc_url( admin_url( 'admin.php?page=sb2_settings' ) );
?>">
		<?php 
wp_nonce_field( 'seobooster_do_actions' );
?>
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
?>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Reset Log', 'seo-booster' );
?>
					</th>
					<td>
						<?php 
submit_button( __( 'Reset the log', 'seo-booster' ), 'secondary', 'submit_reset_log' );
?>
						<label class="description" for="submit">
							<?php 
esc_html_e( 'Resets all the entries in the log.', 'seo-booster' );
?>
						</label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'Send email now', 'seo-booster' );
?>
					</th>
					<td>
						<?php 
submit_button( esc_html__( 'Send status email', 'seo-booster' ), 'secondary', 'submit_send_email' );
?>
						<p><?php 
esc_html_e( 'It can be hard to wait for good things. Once a week is not often enough? Send the weekly report email now.', 'seo-booster' );
?></p>
						<label class="description" for="submit">
							<?php 
esc_html_e( 'Remember to save any changes to email recipients first, before clicking send.', 'seo-booster' );
?>
						</label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'GSC selected site', 'seo-booster' );
?>
					</th>
					<td>
						<?php 
$selected_site = get_option( 'seobooster_selected_site' );
if ( $selected_site ) {
    // translators: %s: selected site
    echo '<p>' . sprintf( esc_html__( 'Selected site: %s', 'seo-booster' ), '<strong>' . esc_html( $selected_site ) . '</strong>' ) . '</p>';
    $google_email = get_option( 'seobooster_google_email' );
    // translators: %s: Google email address
    echo '<p>' . sprintf( esc_html__( 'Google Email: %s', 'seo-booster' ), '<strong>' . esc_html( $google_email ) . '</strong>' ) . '</p>';
}
$install_id = seobooster_fs()->get_site()->id;
$site_private_key = seobooster_fs()->get_site()->secret_key;
$nonce = gmdate( 'Y-m-d' );
$return_to = admin_url( 'admin.php?page=sb2_dashboard&auth=1' );
$pk_hash = hash( 'sha512', $site_private_key . '|' . $nonce );
$authentication_string = base64_encode( $pk_hash . '|' . $nonce );
if ( isset( $install_id, $site_private_key, $authentication_string ) ) {
    ?>
							<p><?php 
    esc_html_e( 'Reauthenticate or change account here', 'seo-booster' );
    ?>: <a href="<?php 
    echo esc_url( add_query_arg( array(
        'install_id' => $install_id,
        'auth_token' => $authentication_string,
        'return_to'  => $return_to,
    ), 'https://seoboosterauth.com/auth' ) );
    ?>" target="_blank" class=""><?php 
    esc_html_e( 'Authenticate with Google', 'seo-booster' );
    ?></a></p>
						<?php 
}
if ( !$selected_site ) {
    ?>
							<p class="description" style="color: #d63638;">
								<?php 
    esc_html_e( 'No site selected.', 'seo-booster' );
    ?>
							</p>
						<?php 
}
?>
						<p><?php 
esc_html_e( 'The selected site used for Google Search Console data.', 'seo-booster' );
?>
						</p>
						<p>⚠️ <?php 
esc_html_e( 'This will allow you to select a different site to import data from.', 'seo-booster' );
?></p>
						<ul>
							<li>🗑️ <?php 
esc_html_e( 'Erases all keyword data and the history of keyword positions and clicks.', 'seo-booster' );
?></li>
							<li>🗑️ <?php 
esc_html_e( 'GSC options - Authentication details are not affected, but you need to select a site again.', 'seo-booster' );
?></li>
						</ul>
						<?php 
submit_button( __( 'Change selected site', 'seo-booster' ), 'secondary', 'submit_gsc_change_site' );
?>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row" valign="top">
						<a name="manualupdate"></a>
						<?php 
esc_html_e( 'Manual Update', 'seo-booster' );
?>
					</th>
					<td>
						<?php 
wp_nonce_field( 'manual_update_nonce', 'manual_update_nonce_field' );
$days_options = array(7, 14);
$selected_days = ( isset( $_POST['reimport_days'] ) ? intval( $_POST['reimport_days'] ) : 7 );
?>
						<div class="">
							<select name="reimport_days" id="reimport_days" class="postform">
								<?php 
foreach ( $days_options as $days ) {
    ?>
									<option value="<?php 
    echo esc_attr( $days );
    ?>" <?php 
    selected( $selected_days, $days );
    ?>>
										<?php 
    // translators: %d: number of days
    echo sprintf( esc_html__( 'Last %d days', 'seo-booster' ), esc_attr( $days ) );
    ?>
									</option>
								<?php 
}
?>
							</select>
							<?php 
submit_button(
    esc_html__( 'Reimport', 'seo-booster' ),
    'secondary',
    'manual_update',
    false,
    ( $selected_site ? [] : [
        'disabled' => 'disabled',
    ] )
);
if ( !$selected_site ) {
    ?>
								<p class="description" style="color: #d63638;">
									<?php 
    esc_html_e( 'Reimport button is disabled because no site is selected. Please select a site first.', 'seo-booster' );
    ?>
								</p>
							<?php 
}
?>
						</div>
						<p><?php 
esc_html_e( 'New data will be added, no overwriting existing data.', 'seo-booster' );
?></p>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row" valign="top">
						<?php 
esc_html_e( 'RESET KEYWORDS AND ALL HISTORY', 'seo-booster' );
?>
					</th>
					<td>
						<ul>
							<li>🗑️ <?php 
esc_html_e( 'Removes all keyword data along with the history of keyword positions and clicks.', 'seo-booster' );
?></li>
							<li>🗑️ <?php 
esc_html_e( 'Resets the debug log.', 'seo-booster' );
?></li>
							<li class="<?php 
echo esc_attr( $cssclasses );
?>">🗑️ <?php 
esc_html_e( 'Clears all 404 error records.', 'seo-booster' );
?></li>
							<li class="<?php 
echo esc_attr( $cssclasses );
?>">🗑️ <?php 
esc_html_e( 'Deletes cached pages used for keyword research.', 'seo-booster' );
?></li>
							<li class="<?php 
echo esc_attr( $cssclasses );
?>">🗑️ <?php 
esc_html_e( 'Purges all keyword analysis results for a fresh start.', 'seo-booster' );
?></li>
							<li class="<?php 
echo esc_attr( $cssclasses );
?>">🗑️ <?php 
esc_html_e( 'Cancels and removes all scheduled Action Scheduler jobs related to SEO Booster.', 'seo-booster' );
?></li>
						</ul>
						✅ <?php 
esc_html_e( 'Automatic links remain unaffected.', 'seo-booster' );
?>
						<?php 
esc_html_e( "GSC options like authentication and the selected site won't be touched.", 'seo-booster' );
?>

						<p class="description" for="submit">⚠️ <?php 
esc_html_e( "Warning: This action makes permanent changes to your database. Proceed carefully—there's no undo!", 'seo-booster' );
?></p>
						<?php 
submit_button( esc_html__( 'Reset keyword data', 'seo-booster' ), 'secondary', 'submit_dbempty' );
?>
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
							<li class="<?php 
echo esc_attr( $cssclasses );
?>">🗑️ <?php 
esc_html_e( 'Clears all 404 error records.', 'seo-booster' );
?></li>
							<li class="<?php 
echo esc_attr( $cssclasses );
?>">🗑️ <?php 
esc_html_e( 'Deletes cached content pages used for keyword research.', 'seo-booster' );
?></li>
							<li class="<?php 
echo esc_attr( $cssclasses );
?>">🗑️ <?php 
esc_html_e( 'Purges all keyword analysis results for a fresh start.', 'seo-booster' );
?></li>
							<li class="<?php 
echo esc_attr( $cssclasses );
?>">🗑️ <?php 
esc_html_e( 'Cancels and removes all scheduled Action Scheduler jobs related to SEO Booster.', 'seo-booster' );
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
				<?php 
?>
			</tbody>
		</table>
	</form>

	<h3><?php 
esc_html_e( 'Database Stats', 'seo-booster' );
?></h3>
	<?php 
\Cleverplugins\SEOBooster\Google_API::display_data_size();
?>
</div><?php 