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
<div class="wrap sb-wrap sb-dashboard">
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
				<input type="hidden" name="page" value="<?php 
    echo esc_attr( ( isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '' ) );
    ?>" />
				<input type="hidden" name="action" value="sbp_fixdatabasetables" />
				<input type="hidden" name="_wpnonce" value="<?php 
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
echo '<div class="sb-admin-seo-compat-wrap">';
require SEOBOOSTER_PLUGINPATH . 'inc/views/seo-plugin-compat-notice.php';
echo '</div>';
$ai_provider = \Cleverplugins\SEOBooster\LLM_Helper::get_selected_ai_provider();
$wp_ai_ready = function_exists( 'wp_ai_client_prompt' );
$show_ai_notice = false;
$ai_notice_link = admin_url( 'admin.php?page=sb2_settings#ai-llm' );
$ai_notice_text = '';
if ( 'disabled' === $ai_provider ) {
    $show_ai_notice = true;
    $ai_notice_text = __( 'Enable AI in SEO Booster Settings to get AI-generated SEO suggestions and image meta.', 'seo-booster' );
} elseif ( 'WordPress' === $ai_provider && !$wp_ai_ready ) {
    $show_ai_notice = true;
    $ai_notice_link = admin_url( 'options-connectors.php' );
    $ai_notice_text = __( 'WordPress AI is not available. Use WordPress 7 or later and configure Settings → Connectors.', 'seo-booster' );
} elseif ( 'seobooster' === $ai_provider && \Cleverplugins\SEOBooster\Credits_Service::is_ai_provider_available() && !\Cleverplugins\SEOBooster\Credits_Service::is_registered() ) {
    $show_ai_notice = true;
    $ai_notice_text = __( 'Connect your SEO Booster Credits account in Settings to use AI features.', 'seo-booster' );
}
$selected_site = get_option( 'seobooster_selected_site' );
$access_token = get_option( 'seobooster_access_token' );
$show_setup_cta = class_exists( __NAMESPACE__ . '\\Setup_Wizard' ) && Setup_Wizard::should_show_dashboard_cta( $access_token );
$wizard_resume_url = ( class_exists( __NAMESPACE__ . '\\Setup_Wizard' ) ? Setup_Wizard::get_wizard_url() : admin_url( 'admin.php?page=sb2_settings#gsc' ) );
$wizard_restart_url = ( class_exists( __NAMESPACE__ . '\\Setup_Wizard' ) ? Setup_Wizard::get_restart_url() : $wizard_resume_url );
// “Run setup wizard” always restarts from welcome; “Continue setup” resumes the saved step.
$wizard_url = ( $access_token ? $wizard_resume_url : $wizard_restart_url );
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
$dashboard_summary = sprintf(
    /* translators: 1: keyword count, 2: page count, 3: distinct day count, 4: first date, 5: last date */
    __( '%1$s keywords across %2$s pages · %3$s days with data (%4$s – %5$s).', 'seo-booster' ),
    number_format_i18n( $total_keywords ),
    number_format_i18n( $unique_pages ),
    number_format_i18n( $unique_days ),
    $first_history_date_str,
    $latest_history_date_str
);
$search_perf = Dashboard_Actions::get_search_performance_kpis( 30 );
$dashboard_kpis = ( isset( $search_perf['kpis'] ) ? $search_perf['kpis'] : array() );
require_once SEOBOOSTER_PLUGINPATH . 'inc/SEO_Issues_Manager.php';
$do_next_stats = SEO_Issues_Manager::get_analysis_stats();
$do_next_items = Dashboard_Actions::get_blocker_items( array(
    'access_token'        => $access_token,
    'selected_site'       => $selected_site,
    'unique_days'         => (int) $unique_days,
    'needs_reauth'        => (bool) get_option( 'seobooster_needs_reauth' ),
    'show_ai_notice'      => $show_ai_notice,
    'ai_notice_link'      => $ai_notice_link,
    'wizard_url'          => $wizard_url,
    'wizard_restart_url'  => $wizard_restart_url,
    'possibilities_total' => ( isset( $do_next_stats['total_issues'] ) ? (int) $do_next_stats['total_issues'] : 0 ),
    'omit_gsc_connect'    => !empty( $show_setup_cta ),
) );
?>
		<?php 
if ( !empty( $show_setup_cta ) ) {
    ?>
			<?php 
    $setup_cta_primary = ( $access_token ? __( 'Continue setup', 'seo-booster' ) : __( 'Run setup wizard', 'seo-booster' ) );
    $setup_cta_href = ( $access_token ? $wizard_resume_url : $wizard_restart_url );
    $setup_cta_lead = ( $access_token ? __( 'A few setup steps are still open. The wizard walks you through Search Console and the recommended plugin features.', 'seo-booster' ) : __( 'The setup wizard connects Search Console and configures the recommended features so you get the most from SEO Booster.', 'seo-booster' ) );
    ?>
	<section class="sb-ui-cta" aria-labelledby="sb-dashboard-setup-cta-title">
		<div class="sb-ui-cta__body">
			<h2 class="sb-ui-cta__title" id="sb-dashboard-setup-cta-title"><?php 
    esc_html_e( 'Set up SEO Booster for the full benefits', 'seo-booster' );
    ?></h2>
			<p class="sb-ui-cta__lead"><?php 
    echo esc_html( $setup_cta_lead );
    ?></p>
			<div class="sb-ui-cta__actions">
				<a class="button button-primary button-hero" href="<?php 
    echo esc_url( $setup_cta_href );
    ?>">
					<?php 
    echo esc_html( $setup_cta_primary );
    ?>
				</a>
			</div>
		</div>
	</section>
		<?php 
}
?>
		<?php 
$show_ask_card = false;
$show_ask_upsell = true;
$ask_readiness = array(
    'ready'   => false,
    'message' => '',
);
// seobooster_fs() is namespaced; bare function_exists( 'seobooster_fs' ) always fails.
if ( function_exists( __NAMESPACE__ . '\\seobooster_fs' ) ) {
}
$ask_upgrade_url = Utils::get_pro_upgrade_url( 'dashboard_ask_upgrade' );
?>
	<section class="sb-ui-panel" id="sb-dashboard-do-next" aria-labelledby="sb-dashboard-do-next-title">
		<h2 class="sb-ui-title" id="sb-dashboard-do-next-title"><?php 
esc_html_e( 'Here’s what to do next', 'seo-booster' );
?></h2>
		<p class="sb-ui-lead"><?php 
esc_html_e( 'Quick wins and setup items for your site. Open a tool when you’re ready. Nothing runs from here.', 'seo-booster' );
?></p>
		<div class="sb-action-cards" id="sb-dashboard-do-next-cards">
			<div class="sb-action-cards__group" id="sb-dashboard-ask">
				<?php 
if ( $show_ask_card ) {
    ?>
					<article class="sb-action-card sb-action-card--ask" data-action-id="ask_seo">
						<div class="sb-action-card__num sb-action-card__num--ok"><?php 
    esc_html_e( 'Advisor', 'seo-booster' );
    ?></div>
						<h3 class="sb-action-card__title">
							<?php 
    esc_html_e( 'Ask about your SEO', 'seo-booster' );
    ?>
							<span class="sb-action-card__badge sb-action-card__badge--beta"><?php 
    esc_html_e( 'Beta', 'seo-booster' );
    ?></span>
							<span class="sb-action-card__badge sb-action-card__badge--ai" title="<?php 
    esc_attr_e( 'Uses WordPress Connectors AI', 'seo-booster' );
    ?>"><?php 
    esc_html_e( 'AI', 'seo-booster' );
    ?></span>
						</h3>
						<a href="#ai" class="sb-action-card__link sb-site-assistant-open" id="sb-site-assistant-open">
							<?php 
    esc_html_e( 'Open', 'seo-booster' );
    ?>
						</a>
					</article>
				<?php 
} elseif ( $show_ask_upsell ) {
    ?>
					<article class="sb-action-card sb-action-card--ask" data-action-id="ask_seo_upsell">
						<div class="sb-action-card__num"><?php 
    esc_html_e( 'Advisor', 'seo-booster' );
    ?></div>
						<h3 class="sb-action-card__title">
							<?php 
    esc_html_e( 'Ask about your SEO', 'seo-booster' );
    ?>
							<span class="sb-action-card__badge sb-action-card__badge--beta"><?php 
    esc_html_e( 'Beta', 'seo-booster' );
    ?></span>
							<span class="sb-action-card__badge sb-action-card__badge--pro"><?php 
    esc_html_e( 'Pro', 'seo-booster' );
    ?></span>
							<span class="sb-action-card__badge sb-action-card__badge--ai" title="<?php 
    esc_attr_e( 'Uses WordPress Connectors AI', 'seo-booster' );
    ?>"><?php 
    esc_html_e( 'AI', 'seo-booster' );
    ?></span>
						</h3>
						<a class="sb-action-card__link" href="<?php 
    echo esc_url( $ask_upgrade_url );
    ?>" target="_blank" rel="noopener noreferrer">
							<?php 
    esc_html_e( 'Upgrade', 'seo-booster' );
    ?>
						</a>
					</article>
				<?php 
}
?>
			</div>
			<div class="sb-action-cards__group" id="sb-dashboard-blockers">
				<?php 
foreach ( $do_next_items as $item ) {
    ?>
					<article class="sb-action-card<?php 
    echo ( 'blocker' === $item['kind'] ? ' sb-action-card--blocker' : '' );
    ?>" data-action-id="<?php 
    echo esc_attr( $item['id'] );
    ?>">
						<div class="sb-action-card__num<?php 
    echo ( !empty( $item['warn'] ) ? ' sb-action-card__num--warn' : '' );
    ?>"><?php 
    echo esc_html( $item['num'] );
    ?></div>
						<h3 class="sb-action-card__title"><?php 
    echo esc_html( $item['title'] );
    ?></h3>
						<a
							class="sb-action-card__link<?php 
    echo ( !empty( $item['oauth_destination'] ) ? ' sb-oauth-start' : '' );
    ?>"
							href="<?php 
    echo ( !empty( $item['oauth_destination'] ) ? '#' : esc_url( $item['url'] ) );
    ?>"
							<?php 
    if ( !empty( $item['oauth_destination'] ) ) {
        ?>
								data-sb-oauth-destination="<?php 
        echo esc_attr( $item['oauth_destination'] );
        ?>"
							<?php 
    }
    ?>
						><?php 
    echo esc_html( $item['cta'] );
    ?></a>
					</article>
				<?php 
}
?>
			</div>
			<div class="sb-action-cards__group" id="sb-dashboard-health-cards">
				<article class="sb-action-card" data-health="missing_focus_keyword">
					<div class="sb-action-card__num sb-action-card__num--loading" aria-busy="true">
						<span class="sb-action-card__spinner" aria-hidden="true"></span>
						<span class="sb-action-card__loading-label"><?php 
esc_html_e( 'Loading…', 'seo-booster' );
?></span>
					</div>
					<h3 class="sb-action-card__title"><?php 
esc_html_e( 'Pages without a focus keyword', 'seo-booster' );
?></h3>
					<a class="sb-action-card__link" href="#" data-tool-link="focus"><?php 
esc_html_e( 'Open tool', 'seo-booster' );
?></a>
				</article>
				<article class="sb-action-card" data-health="empty_alt">
					<div class="sb-action-card__num sb-action-card__num--loading" aria-busy="true">
						<span class="sb-action-card__spinner" aria-hidden="true"></span>
						<span class="sb-action-card__loading-label"><?php 
esc_html_e( 'Loading…', 'seo-booster' );
?></span>
					</div>
					<h3 class="sb-action-card__title"><?php 
esc_html_e( 'Images missing alt text', 'seo-booster' );
?></h3>
					<a class="sb-action-card__link" href="#" data-tool-link="image"><?php 
esc_html_e( 'Open tool', 'seo-booster' );
?></a>
				</article>
				<article class="sb-action-card" data-health="missing_meta">
					<div class="sb-action-card__num sb-action-card__num--loading" aria-busy="true">
						<span class="sb-action-card__spinner" aria-hidden="true"></span>
						<span class="sb-action-card__loading-label"><?php 
esc_html_e( 'Loading…', 'seo-booster' );
?></span>
					</div>
					<h3 class="sb-action-card__title"><?php 
esc_html_e( 'Pages missing title or description', 'seo-booster' );
?></h3>
					<a class="sb-action-card__link" href="#" data-tool-link="meta"><?php 
esc_html_e( 'Open tool', 'seo-booster' );
?></a>
				</article>
				<article class="sb-action-card" data-health="llms">
					<div class="sb-action-card__num sb-action-card__num--loading" aria-busy="true">
						<span class="sb-action-card__spinner" aria-hidden="true"></span>
						<span class="sb-action-card__loading-label"><?php 
esc_html_e( 'Loading…', 'seo-booster' );
?></span>
					</div>
					<h3 class="sb-action-card__title"><?php 
esc_html_e( 'llms.txt for AI crawlers', 'seo-booster' );
?></h3>
					<a class="sb-action-card__link" href="#" data-tool-link="llms"><?php 
esc_html_e( 'Open tool', 'seo-booster' );
?></a>
				</article>
			</div>
			<div class="sb-action-cards__group" id="sb-dashboard-health-pro" hidden></div>
		</div>
		<p class="sb-ui-empty" id="sb-dashboard-do-next-empty" hidden><?php 
esc_html_e( 'You’re in good shape. No quick wins waiting right now.', 'seo-booster' );
?></p>
	</section>
		<?php 
if ( $show_ask_card ) {
    ?>
	<section class="sb-ui-panel sb-site-assistant" id="ai" aria-labelledby="sb-site-assistant-title">
		<h2 class="sb-ui-title sb-site-assistant__heading" id="sb-site-assistant-title">
			<span class="sb-site-assistant__title">
				<?php 
    esc_html_e( 'Ask about your SEO', 'seo-booster' );
    ?>
				<span class="sb-action-card__badge sb-action-card__badge--beta"><?php 
    esc_html_e( 'Beta', 'seo-booster' );
    ?></span>
				<span class="sb-action-card__badge sb-action-card__badge--ai" title="<?php 
    esc_attr_e( 'Uses WordPress Connectors AI', 'seo-booster' );
    ?>"><?php 
    esc_html_e( 'AI', 'seo-booster' );
    ?></span>
			</span>
		</h2>
		<div class="sb-site-assistant__body" id="sb-site-assistant-body">
			<p class="sb-site-assistant__lead"><?php 
    esc_html_e( 'Ask about priorities, Search Console trends, or how to use SEO Booster.', 'seo-booster' );
    ?></p>
			<p class="sb-site-assistant__disclaimer"><?php 
    esc_html_e( 'For site results and rankings it uses data already in SEO Booster. It can also explain SEO Booster features and basic SEO ideas. It does not crawl live pages, change Google, or run tools for you. If unsure, it should say so.', 'seo-booster' );
    ?></p>
			<?php 
    if ( empty( $ask_readiness['ready'] ) && !empty( $ask_readiness['message'] ) ) {
        ?>
			<p class="sb-site-assistant__note" id="sb-site-assistant-setup-notice"><?php 
        echo esc_html( (string) $ask_readiness['message'] );
        ?></p>
			<?php 
    }
    ?>
			<div class="sb-site-assistant__chips" id="sb-site-assistant-chips" role="group" aria-label="<?php 
    esc_attr_e( 'Suggested questions', 'seo-booster' );
    ?>"></div>
			<form class="sb-site-assistant__form" id="sb-site-assistant-form">
				<label for="sb-site-assistant-input" class="screen-reader-text"><?php 
    esc_html_e( 'Your question', 'seo-booster' );
    ?></label>
				<input type="text" id="sb-site-assistant-input" class="sb-site-assistant__input" maxlength="500" autocomplete="off" <?php 
    echo ( empty( $ask_readiness['ready'] ) ? 'disabled' : '' );
    ?> />
				<button type="submit" class="button button-primary" id="sb-site-assistant-submit" <?php 
    echo ( empty( $ask_readiness['ready'] ) ? 'disabled' : '' );
    ?>><?php 
    esc_html_e( 'Ask', 'seo-booster' );
    ?></button>
			</form>
			<div class="sb-site-assistant__history" id="sb-site-assistant-history" hidden>
				<h3 class="sb-site-assistant__subheading"><?php 
    esc_html_e( 'Recent answers', 'seo-booster' );
    ?></h3>
				<div class="sb-site-assistant__history-items" id="sb-site-assistant-history-items"></div>
			</div>
			<div class="sb-site-assistant__loading" id="sb-site-assistant-loading" hidden>
				<span class="sb-action-card__spinner" aria-hidden="true"></span>
				<span id="sb-site-assistant-loading-text"></span>
			</div>
			<div class="sb-site-assistant__result" id="sb-site-assistant-result" hidden aria-live="polite"></div>
		</div>
	</section>
		<?php 
}
?>
		<?php 
$seobooster_weekly_email = get_option( 'seobooster_weekly_email' );
if ( isset( $_GET['gsc_updated'] ) && '1' === $_GET['gsc_updated'] && !$seobooster_weekly_email ) {
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
    $dashboard_user = wp_get_current_user();
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
										<input type="text" name="seobooster_email" id="seobooster_email" class="regular-text" value="<?php 
    echo esc_attr( $dashboard_user->user_email );
    ?>" autocomplete="off" data-1p-ignore>
									</p>
									<p>
										<input type="submit" name="submit" value="<?php 
    esc_attr_e( 'Confirm Email for Weekly Reports', 'seo-booster' );
    ?>" class="button button-primary">
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
    esc_html_e( 'Week-over-week Google Search clicks and impressions', 'seo-booster' );
    ?>
	</li>
								<li>
								<?php 
    esc_html_e( 'Top SEO possibilities to fix', 'seo-booster' );
    ?>
	</li>
								<li>
								<?php 
    esc_html_e( 'New keyword discoveries from Search Console', 'seo-booster' );
    ?>
	</li>
								<li>Pro:
								<?php 
    esc_html_e( 'Pages losing clicks and GSC opportunity counts', 'seo-booster' );
    ?>
	</li>

							</ul>
							<p>
							<?php 
    esc_html_e( 'Your data privacy is our priority. All information is processed locally on your server and sent to your email address from your own server. We never access your data.', 'seo-booster' );
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
        echo '<button type="button" class="button button-primary sb-oauth-start" data-sb-oauth-destination="dashboard">' . esc_html__( 'Re-authenticate with Google', 'seo-booster' ) . '</button>';
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
    $possibilities_stats = ( isset( $do_next_stats ) ? $do_next_stats : SEO_Issues_Manager::get_analysis_stats() );
    $top_keywords = Utils::get_top_keywords( 30, 7 );
    $autolink_rules = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sb2_autolink" );
    $ai_bot_summary = AI_Bot_Tracker::get_summary( 30 );
    $ai_bot_purpose = ( isset( $ai_bot_summary['purpose_breakdown'] ) ? $ai_bot_summary['purpose_breakdown'] : array(
        'research' => 0,
        'citation' => 0,
    ) );
    $ai_bot_ratio = AI_Bot_Tracker::get_content_vs_noise_ratio( 30 );
    $ai_bot_top_content = AI_Bot_Tracker::get_top_content_pages( 30, 3 );
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

	<section class="sb-ui-panel sb-dashboard-kpis" aria-labelledby="sb-dashboard-search-title">
		<h2 class="sb-ui-title" id="sb-dashboard-search-title"><?php 
    esc_html_e( 'Search performance', 'seo-booster' );
    ?></h2>
			<?php 
    if ( !empty( $search_perf['label_current'] ) ) {
        ?>
			<p class="sb-kpi-period">
				<?php 
        echo esc_html( $search_perf['label_current'] );
        ?>
				<?php 
        if ( !empty( $search_perf['label_compare'] ) ) {
            ?>
					<span class="sb-kpi-period__sep" aria-hidden="true">·</span>
					<span class="sb-kpi-period__compare"><?php 
            echo esc_html( $search_perf['label_compare'] );
            ?></span>
				<?php 
        }
        ?>
			</p>
		<?php 
    }
    ?>
			<?php 
    if ( !empty( $dashboard_kpis ) ) {
        ?>
			<div class="sb-kpi-row">
				<?php 
        foreach ( $dashboard_kpis as $kpi_key => $kpi ) {
            ?>
					<?php 
            $suffix = ( isset( $kpi['suffix'] ) ? $kpi['suffix'] : '' );
            $has_kpi_compare = !empty( $kpi['has_comparison'] ) && null !== $kpi['change'];
            ?>
					<div class="sb-kpi">
						<span class="sb-kpi__label"><?php 
            echo esc_html( $kpi_labels[$kpi_key] );
            ?></span>
						<div class="sb-kpi__value"><?php 
            echo esc_html( number_format_i18n( $kpi['value'], $kpi['decimals'] ) . $suffix );
            ?></div>
						<div class="sb-kpi__delta">
							<?php 
            if ( $has_kpi_compare ) {
                ?>
								<?php 
                $is_positive = ( !empty( $kpi['invert'] ) ? $kpi['change'] <= 0 : $kpi['change'] >= 0 );
                $delta_class = ( 0.0 === (float) $kpi['change'] ? 'neutral' : (( $is_positive ? 'positive' : 'negative' )) );
                $sign = ( $kpi['change'] > 0 ? '+' : '' );
                $display_change = ( !empty( $kpi['invert'] ) ? abs( $kpi['change'] ) : $kpi['change'] );
                $pct_text = ( null !== $kpi['percentage'] ? ' (' . (( $kpi['percentage'] > 0 ? '+' : '' )) . number_format_i18n( $kpi['percentage'], 1 ) . '%)' : '' );
                ?>
								<span class="change <?php 
                echo esc_attr( $delta_class );
                ?>">
									<?php 
                echo esc_html( $sign . number_format_i18n( $display_change, $kpi['decimals'] ) . $suffix . $pct_text );
                ?>
								</span>
								<span class="sb-kpi__delta-note"><?php 
                esc_html_e( 'vs prior period', 'seo-booster' );
                ?></span>
							<?php 
            } else {
                ?>
								<span class="change neutral"><?php 
                esc_html_e( 'No prior period yet', 'seo-booster' );
                ?></span>
							<?php 
            }
            ?>
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
		<div class="sb-dashboard-chart">
			<div id="sb2canvascont" style="height:320px;display:block;margin:16px 0 0;width:100%;">
				<canvas id="seobooster-gsc-chart"><?php 
    esc_html_e( 'Chart', 'seo-booster' );
    ?></canvas>
				<div id="loading-indicator">
					<div id="spinner"></div>
				</div>
			</div>
		</div>
	</section>

	<div class="sb-ui-grid sb-ui-grid--2">
		<section class="sb-ui-panel" aria-labelledby="sb-dashboard-keywords-title">
			<h2 class="sb-ui-title" id="sb-dashboard-keywords-title"><?php 
    esc_html_e( 'Top 7 Keywords (Past 30 Days)', 'seo-booster' );
    ?></h2>
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
				<p class="sb-card__meta"><?php 
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
		</section>
		<section class="sb-ui-panel" aria-labelledby="sb-dashboard-possibilities-title">
			<h2 class="sb-ui-title" id="sb-dashboard-possibilities-title"><?php 
    esc_html_e( 'SEO Possibilities', 'seo-booster' );
    ?></h2>
			<?php 
    if ( $possibilities_stats['total_issues'] > 0 ) {
        ?>
				<p class="sb-card__meta">
					<?php 
        printf( 
            /* translators: %s: number of SEO possibilities detected. */
            esc_html__( '%1$s possibilities detected. Top items to address:', 'seo-booster' ),
            esc_html( number_format_i18n( $possibilities_stats['total_issues'] ) )
         );
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
								<h3 class="sb-possibility-item__title"><?php 
            echo esc_html( $possibility['message'] );
            ?></h3>
								<span class="sb-severity-chip sb-severity-chip--<?php 
            echo esc_attr( $severity_class );
            ?>"><?php 
            echo esc_html( $severity_label );
            ?></span>
							</div>
							<p class="sb-possibility-item__meta">
								<?php 
            printf( 
                /* translators: %s: number of pages affected by this SEO possibility. */
                esc_html( _n(
                    'Affects %1$s page',
                    'Affects %1$s pages',
                    $possibility['affected_urls'],
                    'seo-booster'
                ) ),
                esc_html( number_format_i18n( $possibility['affected_urls'] ) )
             );
            ?>
								·
								<a href="<?php 
            echo esc_url( admin_url( 'admin.php?page=sb2_seo_issues' ) );
            ?>"><?php 
            esc_html_e( 'Review', 'seo-booster' );
            ?></a>
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
		</section>
	</div>

	<div class="sb-ui-grid sb-ui-grid--2">
		<section class="sb-ui-panel" aria-labelledby="sb-dashboard-autolink-title">
			<h2 class="sb-ui-title" id="sb-dashboard-autolink-title"><?php 
    esc_html_e( 'Automatic Links', 'seo-booster' );
    ?></h2>
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
		</section>

		<section class="sb-ui-panel" aria-labelledby="sb-dashboard-ai-bots-title">
			<h2 class="sb-ui-title" id="sb-dashboard-ai-bots-title"><?php 
    esc_html_e( 'AI Bots', 'seo-booster' );
    ?></h2>
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
            /* translators: 1: number of pages crawled, 2: mapped content percentage, 3: research bot visits, 4: citation bot visits. */
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
		</section>

			<?php 
    if ( $show_credits_card ) {
        ?>
			<section class="sb-ui-panel" aria-labelledby="sb-dashboard-credits-title">
				<h2 class="sb-ui-title" id="sb-dashboard-credits-title"><?php 
        esc_html_e( 'AI Credits', 'seo-booster' );
        ?></h2>
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
			</section>
		<?php 
    }
    ?>
	</div>

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
    esc_html_e( 'Front page overview', 'seo-booster' );
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
        $pro_upgrade_url = Utils::get_pro_upgrade_url( 'dashboard_pro_card' );
        ?>
		<section class="sb-ui-panel sb-dashboard-pro sb-card--locked" aria-labelledby="sb-dashboard-pro-title">
			<h2 class="sb-ui-title" id="sb-dashboard-pro-title"><?php 
        echo esc_html( $pro_feature['title'] );
        ?></h2>
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
		</section>
				<?php 
    }
    $timestamp_output = '';
    $timestamp = wp_next_scheduled( 'seobooster_gsc_data_fetch' );
    if ( $timestamp ) {
        $timestamp = gmdate( 'Y-m-d H:i:s', $timestamp );
        $current_time = current_time( 'timestamp' );
        $time_diff = human_time_diff( $current_time, strtotime( $timestamp ) );
        $timestamp_output = ' <small>' . esc_html__( 'Next scheduled update:', 'seo-booster' ) . ' ' . esc_html( $timestamp ) . ' (' . sprintf( 
            /* translators: %s: human-readable time until the next scheduled GSC update. */
            esc_html__( 'in %s', 'seo-booster' ),
            esc_html( $time_diff )
         ) . ')</small>';
    }
    ?>
	<p class="sb-dashboard-status">
				<?php 
    echo wp_kses_post( sprintf( 
        /* translators: %s: connected Google Search Console site URL. */
        esc_html__( 'Connected to GSC site %s', 'seo-booster' ),
        '<strong>' . esc_html( $selected_site ) . '</strong>'
     ) . $timestamp_output );
    ?>
	</p>

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
	<section class="sb-ui-panel" aria-labelledby="sb-dashboard-no-data-title">
		<h2 class="sb-ui-title" id="sb-dashboard-no-data-title"><?php 
    esc_html_e( 'No Data Available', 'seo-booster' );
    ?></h2>
		<p><?php 
    esc_html_e( 'You are authenticated and have selected a site, but no data has been imported yet.', 'seo-booster' );
    ?></p>
		<p><a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_settings#manualupdate' ) );
    ?>" class="button button-primary"><?php 
    esc_html_e( 'Import Data from Settings', 'seo-booster' );
    ?></a></p>
	</section>
			<?php 
}
if ( empty( $show_setup_cta ) && class_exists( __NAMESPACE__ . '\\Setup_Wizard' ) && current_user_can( 'manage_options' ) ) {
    ?>
	<p class="description sb-dashboard-setup-link sb-dashboard-setup-link--footer">
		<a href="<?php 
    echo esc_url( Setup_Wizard::get_restart_url() );
    ?>">
			<?php 
    esc_html_e( 'Run setup wizard', 'seo-booster' );
    ?>
		</a>
	</p>
			<?php 
}
?>

</div> <!-- .wrap --><?php 