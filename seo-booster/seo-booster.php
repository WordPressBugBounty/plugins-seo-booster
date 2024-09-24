<?php

/**
* Plugin Name: SEO Booster
* Version: 3.8.10
* Plugin URI: https://cleverplugins.com/
* Description: Automatic linking + Monitor keywords from hundreds of search engines + 404 errors tracking + backlink collecting + Crosslinking widgets and template functions + much more.
* Author: cleverplugins.com
* Author URI: https://cleverplugins.com
* Text Domain: seo-booster
* Domain Path: /languages

* This plugin uses the following 3rd party MIT licensed projects - Thank you for making other developer lives easier :-)

* Country flags Copyright (c) 2017 Go Squared Ltd. http://www.gosquared.com/ - https://github.com/gosquared/flags. MIT license.

* datatables.net library for the keywords ajax interface. MIT license.

* Jose Solorzano (https://sourceforge.net/projects/php-html/) for the Simple HTML DOM parser.

* Lazy Load plugin from Mika Tuupola - http://www.appelsiini.net/projects/lazyload. MIT license.

* Thank you Matt van Andel for the WP List Table Example class - https://github.com/Veraxus/wp-list-table-example/blob/master/list-table-example.php

* The email template is brought to you by EmailOctopus https://emailoctopus.com/ - email marketing for less, via Amazon SES. MIT License

* https://github.com/themefoundation/custom-meta-box-template/blob/master/custom-meta-box-template.php

* https://github.com/lukehaas/css-loaders

* Copyright 2008-2019 cleverplugins.com

* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*
*
* TODO - One thing I WOULD pay for - if there was a suggestion list of keywords. I mean, I'm writing a new post and I'm trying to remember 'did I use Mennonite dress or Mennonites dress?' - it would be nice if I could easily see the keywords I had already set in other posts.
*
* TODO - Moz API integration for backlinks and/or audit - https://moz.com/help/guides/moz-api/mozscape/api-reference/link-metrics og https://moz.com/help/guides/moz-api/mozscape/anatomy-of-a-mozscape-api-call
*
* TODO - SEOPress integration - analysis target
*
* TODO - Brug samme table for alle sites, men indsæt blog_id istedet i tabellen, så er det lettere at flytte data sammen i fremtiden.
*
* TODO - future allow for email customization
*
* TODO - Slet individuelle keywords fra listen - måske genskabe listen med AJAX i WP tables?
*
* TODO - Highlight / filter keywords marked as ignored i AJAX kw table
*
* TODO - Delete/remove keywords from AJAX table
*
*/
// don't load directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

if ( function_exists( 'seobooster_fs' ) ) {
    seobooster_fs()->set_basename( false, __FILE__ );
    return;
}

define( 'SEOBOOSTER_VERSION', '3.8.10' );
define( 'SEOBOOSTER_PLUGINPATH', plugin_dir_path( __FILE__ ) );
define( 'SEOBOOSTER_PLUGINURL', plugin_dir_url( __FILE__ ) );
// Last time database was updated
define( 'SEOBOOSTER_DB_VERSION', '3.7' );
define( 'SEOBOOSTER_FREEMIUS_STATE', 'seobooster_freemius_state' );
require SEOBOOSTER_PLUGINPATH . 'vendor/autoload.php';

if ( !function_exists( 'seobooster_fs' ) ) {
    // Create a helper function for easy SDK access.
    function seobooster_fs()
    {
        global  $seobooster_fs ;
        
        if ( !isset( $seobooster_fs ) ) {
            // Include Freemius SDK.
            require_once __DIR__ . '/freemius/start.php';
            // Check anonymous mode.
            $seob_freemius_state = get_site_option( SEOBOOSTER_FREEMIUS_STATE, 'anonymous' );
            $is_anonymous = 'anonymous' === $seob_freemius_state || 'skipped' === $seob_freemius_state;
            $is_premium = false;
            $is_anonymous = ( $is_premium ? false : $is_anonymous );
            $seobooster_fs = fs_dynamic_init( array(
                'id'             => '987',
                'slug'           => 'seo-booster',
                'type'           => 'plugin',
                'public_key'     => 'pk_a58b7605ac6e9e90cd7bd9458cfbc',
                'is_premium'     => false,
                'premium_suffix' => 'Premium',
                'has_addons'     => false,
                'has_paid_plans' => true,
                'trial'          => array(
                'days'               => 30,
                'is_require_payment' => true,
            ),
                'anonymous_mode' => $is_anonymous,
                'menu'           => array(
                'slug'        => 'sb2_dashboard',
                'affiliation' => false,
                'support'     => false,
                'contact'     => false,
            ),
                'is_live'        => true,
            ) );
        }
        
        return $seobooster_fs;
    }
    
    // Init Freemius.
    seobooster_fs();
    // Signal that SDK was initiated.
    do_action( 'seobooster_fs_loaded' );
}

seobooster_fs()->add_filter( 'handle_gdpr_admin_notice', '__return_true' );
seobooster_fs()->add_action( 'after_uninstall', 'seobooster_do_after_uninstall' );
// Prevent cannot redeclare
if ( !function_exists( 'seobooster_do_after_uninstall' ) ) {
    function seobooster_do_after_uninstall()
    {
        wp_clear_scheduled_hook( 'sbp_dailymaintenance' );
        wp_clear_scheduled_hook( 'sbp_hourlymaintenance' );
        wp_clear_scheduled_hook( 'sbp_checkbacklink' );
        wp_clear_scheduled_hook( 'sbp_email_update' );
        wp_clear_scheduled_hook( 'sbp_crawl_internal' );
        delete_option( 'sbp_review_notice' );
    }

}
// loads persistent admin notices
add_action( 'admin_init', array( 'PAnD', 'init' ) );

if ( !class_exists( 'seobooster2' ) ) {
    include_once 'inc/class-seobooster-dyn-widget.php';
    include_once 'inc/class-seobooster-keywords-widget.php';
    class Seobooster2
    {
        public  $localization_domain = 'seobooster2' ;
        /**
         * Plugin version
         *
         * @var integer
         */
        public static  $version = 0 ;
        public function __construct()
        {
            add_action( 'add_meta_boxes', array( __CLASS__, 'do_custom_meta' ) );
            // Adds helpscout permission to Freemius
            if ( function_exists( 'seobooster_fs' ) ) {
                seobooster_fs()->add_filter( 'permission_list', array( __CLASS__, 'add_freemius_permission' ) );
            }
            add_action( 'sbp_email_update', array( __CLASS__, 'send_email_update' ) );
            add_action( 'sbp_hourlymaintenance', array( __CLASS__, 'do_seobooster_maintenance' ) );
            add_action( 'admin_notices', array( __CLASS__, 'do_admin_notices' ) );
            add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
            add_action( 'init', array( __CLASS__, 'on_init' ) );
            add_action( 'plugins_loaded', array( __CLASS__, 'do_plugins_loaded' ) );
            add_action( 'wp', array( __CLASS__, 'prefixsetupschedule' ) );
            add_action( 'init', array( __CLASS__, 'plugins_loaded_register_visitor' ) );
            add_action( 'wp_ajax_seobooster_freemius_opt_in', array( __CLASS__, 'seobooster_fs_opt_in' ) );
            add_filter( 'the_content', array( __CLASS__, 'do_filter_the_content' ), 999999 );
            add_filter( 'the_excerpt', array( __CLASS__, 'do_filter_the_content' ), 999999 );
            add_action( 'template_redirect', array( __CLASS__, 'template_redirect_action' ) );
            // todo - test om vi kan bruge options her til at slå feature til eller fra.
            add_action( 'save_post', array( __CLASS__, 'do_meta_save' ) );
            add_action( 'wp_dashboard_setup', array( __CLASS__, 'add_dashboard_widget' ) );
            add_action( 'admin_menu', array( __CLASS__, 'add_pages' ) );
            add_action( 'widgets_init', array( __CLASS__, 'src_load_widgets' ) );
            add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
            // loading scripts
            add_filter( 'cron_schedules', array( __CLASS__, 'filter_cron_schedules' ) );
            //phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
            add_action( 'wp_ajax_sbp_enable_background_updates', array( __CLASS__, 'do_action_sbp_enable_background_updates' ) );
            add_action( 'wp_ajax_sbp_dismiss_review', array( __CLASS__, 'sbp_dismiss_review' ) );
            add_action( 'wp_ajax_ajax_add_keyword', array( __CLASS__, 'wp_ajax_ajax_add_keyword_callback' ) );
            // Autolink - add new link AJAX
            add_action( 'wp_ajax_fn_my_ajaxified_dataloader_ajax', array( __CLASS__, 'fn_my_ajaxified_dataloader_ajax' ) );
            // Incoming Keywords table callback function
            add_action( 'wpmu_drop_tables', array( __CLASS__, 'on_delete_blog' ) );
            // On deactivation
            // Adds links in the plugins page
            add_filter(
                'plugin_action_links',
                array( __CLASS__, 'add_settings_link' ),
                10,
                5
            );
            register_activation_hook( __FILE__, array( __CLASS__, 'seobooster_activate' ) );
            register_deactivation_hook( __FILE__, array( __CLASS__, 'seobooster_deactivate' ) );
            add_action( 'admin_footer', array( __CLASS__, 'do_action_admin_footer' ) );
        }
        
        /**
         * Enable background updates
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Friday, March 18th, 2022.
         * @access  public static
         * @return  void
         */
        public static function do_action_sbp_enable_background_updates()
        {
            $nonce = sanitize_text_field( $_POST['nonce'] );
            // Nonce.
            // Verify nonce.
            if ( empty($nonce) || !wp_verify_nonce( $nonce, 'sbp-background-updates' ) ) {
                // Nonce verification failed.
                wp_send_json_error( array(
                    'success' => false,
                    'message' => esc_html__( 'Nonce verification failed.', 'seo-booster' ),
                ) );
            }
            $auto_updates = (array) get_site_option( 'auto_update_plugins', array() );
            // Enables automatic background updates
            $auto_updates[] = 'seo-booster/seo-booster.php';
            $auto_updates[] = 'seo-booster-premium/seo-booster.php';
            $auto_updates = array_unique( $auto_updates );
            update_site_option( 'auto_update_plugins', $auto_updates );
            wp_send_json_success( array(
                'success' => true,
                'message' => esc_html__( 'Automatic background updates enabled.', 'seo-booster' ),
            ) );
        }
        
        /**
         * Ajax callback to handle freemius opt in/out.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function seobooster_fs_opt_in()
        {
            $nonce = sanitize_text_field( $_POST['opt_nonce'] );
            $choice = sanitize_text_field( $_POST['choice'] );
            
            if ( empty($nonce) || !wp_verify_nonce( $nonce, 'seobooster-freemius-opt' ) ) {
                // Nonce verification failed.
                echo  wp_json_encode( array(
                    'success' => false,
                    'message' => esc_html__( 'Nonce verification failed.', 'seo-booster' ),
                ) ) ;
                exit;
            }
            
            // Check if choice is not empty.
            
            if ( !empty($choice) ) {
                
                if ( 'yes' === $choice ) {
                    
                    if ( !is_multisite() ) {
                        seobooster_fs()->opt_in();
                        // Opt in.
                    } else {
                        // Get sites.
                        $sites = Freemius::get_sites();
                        $sites_data = array();
                        if ( !empty($sites) ) {
                            foreach ( $sites as $site ) {
                                $sites_data[] = seobooster_fs()->get_site_info( $site );
                            }
                        }
                        seobooster_fs()->opt_in(
                            false,
                            false,
                            false,
                            false,
                            false,
                            false,
                            false,
                            false,
                            $sites_data
                        );
                    }
                    
                    // Update freemius state.
                    update_site_option( SEOBOOSTER_FREEMIUS_STATE, 'in' );
                } elseif ( 'no' === $choice ) {
                    
                    if ( !is_multisite() ) {
                        seobooster_fs()->skip_connection();
                        // Opt out.
                    } else {
                        seobooster_fs()->skip_connection( null, true );
                        // Opt out for all websites.
                    }
                    
                    // Update freemius state.
                    update_site_option( SEOBOOSTER_FREEMIUS_STATE, 'skipped' );
                }
                
                echo  wp_json_encode( array(
                    'success' => true,
                    'message' => esc_html__( 'Freemius opt choice selected.', 'seo-booster' ),
                ) ) ;
            } else {
                echo  wp_json_encode( array(
                    'success' => false,
                    'message' => esc_html__( 'Freemius opt choice not found.', 'seo-booster' ),
                ) ) ;
            }
            
            exit;
        }
        
        /**
         * add_freemius_permission.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @param   mixed   $permissions
         * @return  mixed
         */
        public static function add_freemius_permission( $permissions )
        {
            $permissions['newsletter'] = array(
                'icon-class' => 'dashicons dashicons-email-alt2',
                'label'      => 'Newsletter',
                'desc'       => 'Your email is added to the user newsletter.',
                'priority'   => 17,
            );
            return $permissions;
        }
        
        /**
         * do_action_admin_footer.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function do_action_admin_footer()
        {
            $is_sb2_admin_page = self::is_sb2_admin_page();
            if ( !$is_sb2_admin_page ) {
                return;
            }
            
            if ( function_exists( 'seobooster_fs' ) ) {
                global  $seobooster_fs ;
                $helpscoutbeacon = '';
                echo  $helpscoutbeacon ;
                //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
        
        }
        
        /**
         * Returns links from the autolink database
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Monday, October 11th, 2021.
         * @access  public static
         * @return  void
         */
        public static function return_autolinks()
        {
            global  $wpdb ;
            $lookupkwlimit = 500;
            // todo - setting
            $search_replace_arr = array();
            $internalkeywords = $wpdb->get_results( $wpdb->prepare( "SELECT keyword as kw, url as lp, id FROM {$wpdb->prefix}sb2_autolink ORDER BY kw DESC LIMIT %d;", $lookupkwlimit ) );
            
            if ( $internalkeywords ) {
                $step_count = 0;
                foreach ( $internalkeywords as $kw ) {
                    // Hent landing page og check url er korrekt
                    $landingpage = $kw->lp;
                    
                    if ( filter_var( $landingpage, FILTER_VALIDATE_URL ) ) {
                        $search_replace_arr[$step_count]['kw'] = $kw->kw;
                        $search_replace_arr[$step_count]['lp'] = $kw->lp;
                        $search_replace_arr[$step_count]['id'] = $kw->id;
                        ++$step_count;
                    }
                
                }
                $search_replace_arr = array_map( 'unserialize', array_unique( array_map( 'serialize', $search_replace_arr ) ) );
                return $search_replace_arr;
            } else {
                return false;
            }
        
        }
        
        /**
         * do_admin_notices.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Saturday, August 7th, 2021.
         * @access  public static
         * @return  void
         */
        public static function do_admin_notices()
        {
            $is_sb2_admin_page = self::is_sb2_admin_page();
            $screen = get_current_screen();
            
            if ( PAnD::is_admin_notice_active( 'seo-booster-newsletter-14' ) ) {
                $current_user = wp_get_current_user();
                ?>
				<div data-dismissible="seo-booster-newsletter-14" class="updated notice notice-success is-dismissible">
				<h3>Join the SEO Booster newsletter</h3>
				<img src="
				<?php 
                echo  esc_url( plugin_dir_url( __FILE__ ) . 'images/seo-booster.png' ) ;
                ?>
				"  class="alignleft" width="100" style="margin-right:10px;" alt="cleverplugins.com">
				<p>Get tips on how to use SEO Booster, improve traffic to your website and stay up to date on the development of SEO Booster.</p>
				<form class="ml-block-form" action="https://static.mailerlite.com/webforms/submit/b8u6n2" data-code="b8u6n2"
					method="post" target="_blank">
					<table>
					<tbody>
						<tr>
						<td>
							<input type="text" class="regular-text" data-inputmask="" name="fields[name]" placeholder="Name" autocomplete="name" style="width:15em;" value="<?php 
                echo  esc_html( $current_user->display_name ) ;
                ?>" required="required">
						</td>
						<td>
							<input aria-label="email" aria-required="true" data-inputmask="" type="email" class="regular-text required email" data-inputmask="" name="fields[email]" placeholder="Email" autocomplete="email" style="width:15em;" value="<?php 
                echo  esc_html( $current_user->user_email ) ;
                ?>" required="required">
						</td>
						<td>
						<button type="submit" class="button">Subscribe</button>
						</td>
						</tr>
					</table>
					<input type="hidden" name="fields[signupsource]" value="SEO Booster Plugin 
					<?php 
                echo  esc_attr( self::get_plugin_version() ) ;
                ?>
				">
					<input type="hidden" name="ml-submit" value="1">
					<input type="hidden" name="anticsrf" value="true">
				</form>
				<p>You can unsubscribe anytime. For more details, review our <a href="https://cleverplugins.com/privacy-policy/" target="_blank">Privacy Policy</a>.</p>
				<p><small>Signup form is shown every 14 days until dismissed</small></p>
				</div>
				<?php 
            }
            
            if ( !$is_sb2_admin_page ) {
                return;
            }
            $found = false;
            // Checks if auto updates has been enabled
            
            if ( function_exists( 'wp_is_auto_update_enabled_for_type' ) ) {
                $auto_updates_enabled = wp_is_auto_update_enabled_for_type( 'plugin' );
                $hide_auto_update = false;
                $current_screen = get_current_screen();
                $found = false;
                $look_for = array( 'seo-booster/seo-booster.php', 'seo-booster-premium/seo-booster.php' );
                $auto_updates = (array) get_site_option( 'auto_update_plugins', array() );
                if ( $auto_updates ) {
                    foreach ( $auto_updates as $au ) {
                        if ( in_array( $au, $look_for ) ) {
                            $found = true;
                        }
                    }
                }
            }
            
            
            if ( !$found && $auto_updates_enabled && !$hide_auto_update && PAnD::is_admin_notice_active( 'sbp-enable-background-updates-14' ) ) {
                ?>
				<div class="secnin-notice notice notice-info is-dismissible" id="sbp-enable-background-updates" data-dismissible="sbp-enable-background-updates-14" ><h3><?php 
                esc_html_e( 'SEO Booster - Automatic background updates', 'seo-booster' );
                ?></h3>
					<p>You have automatic updates enabled, but not for SEO Booster.</p>
					<p>Recommended - Enable to install updates automatically in the background, keeping your website protection up to date.</p>
					<p>
							<a href="javascript:;" class="button button-primary" onclick="sbp_enable_background_updates(this)"><?php 
                esc_html_e( 'Enable auto-updates', 'seo-booster' );
                ?> </a> <a href="javascript:;" class="dismiss-this button button-secondary"><?php 
                esc_html_e( 'No, thank you', 'seo-booster' );
                ?></a>
					</p>
					<input type="hidden" id="sbp-enable-background-updates-nonce" value="<?php 
                echo  esc_attr( wp_create_nonce( 'sbp-background-updates' ) ) ;
                ?>" />

				</div>
				<?php 
            }
            
            // Check anonymous mode.
            if ( 'anonymous' === get_site_option( SEOBOOSTER_FREEMIUS_STATE, 'anonymous' ) ) {
                // If user manually opt-out then don't show the notice.
                if ( seobooster_fs()->is_anonymous() && seobooster_fs()->is_not_paying() && seobooster_fs()->has_api_connectivity() ) {
                    if ( !is_multisite() || is_multisite() && is_network_admin() ) {
                        
                        if ( PAnD::is_admin_notice_active( 'seobooster-improve-notice-14' ) ) {
                            ?>
					<div data-dismissible="seobooster-improve-notice-14" class="notice notice-success is-dismissible">
						<h3>
							<?php 
                            esc_html_e( 'Help SEO Booster improve!', 'seo-booster' );
                            ?>
							</h3>

						<p>
							<?php 
                            echo  esc_html__( 'Gathering non-sensitive diagnostic data about the plugin install helps us improve the plugin.', 'seo-booster' ) . ' <a href="https://cleverplugins.com/docs/install/non-sensitive-diagnostic-data/" target="_blank">' . esc_html__( 'Read more about what we collect.', 'seo-booster' ) . '</a>' ;
                            ?>
							</p>

						<p>
							<?php 
                            // translators:
                            printf( esc_html__( 'If you opt-in, some data about your usage of %1$s will be sent to Freemius.com. If you skip this, that\'s okay! %1$s will still work just fine.', 'seo-booster' ), '<b>SEO Booster</b>' );
                            ?>
					</p>
					<p>
						<a href="javascript:;" class="button button-primary" onclick="seobooster_freemius_opt_in(this)" data-opt="yes">
							<?php 
                            esc_html_e( 'Sure, opt-in', 'seo-booster' );
                            ?>
							</a>

						<a href="javascript:;" class="button dismiss-this">
							<?php 
                            esc_html_e( 'No, thank you', 'seo-booster' );
                            ?>
							</a>
					</p>
					<input type="hidden" id="seobooster-freemius-opt-nonce" value="<?php 
                            echo  esc_attr( wp_create_nonce( 'seobooster-freemius-opt' ) ) ;
                            ?>" />
				</div>
							<?php 
                        }
                    
                    }
                }
            }
            global  $seobooster_fs ;
            include_once 'inc/proonly.php';
            $review = get_option( 'sbp_review_notice' );
            $time = time();
            $load = false;
            
            if ( !$review ) {
                $review = array(
                    'time'      => $time,
                    'dismissed' => false,
                );
                $load = true;
            } else {
                // Check if it has been dismissed or not.
                if ( isset( $review['dismissed'] ) && !$review['dismissed'] && (isset( $review['time'] ) && $review['time'] <= $time) ) {
                    $load = true;
                }
            }
            
            // Hvis vi skal vise den igen
            if ( isset( $review['time'] ) ) {
                if ( $time > $review['time'] ) {
                    // Vi kan godt vise den igen
                    $load = true;
                }
            }
            if ( !$load ) {
                return;
            }
            // Update the review option now.
            update_option( 'sbp_review_notice', $review, 'no' );
            $current_user = wp_get_current_user();
            $fname = '';
            if ( !empty($current_user->user_firstname) ) {
                $fname = $current_user->user_firstname;
            }
            if ( function_exists( 'seobooster_fs' ) ) {
                
                if ( seobooster_fs()->is_registered() ) {
                    $get_user = seobooster_fs()->get_user();
                    $fname = $get_user->first;
                }
            
            }
            // @todo - use PAND
            // We have a candidate! Output a review message.
            ?>
			<div class="notice notice-info is-dismissible sbp-review-notice">
				<p>Hey, I noticed you have been using SEO Booster for a while - that’s awesome! Could you please do me a BIG favor and give it a 5-star rating on WordPress to help us spread the word?</p>
				<p>Thank you</p>
				<p><strong>Lars Koudal,<br>cleverplugins.com</strong></p>
				<p><ul>
					<li><a href="https://wordpress.org/support/plugin/seo-booster/reviews/?filter=5#new-post" class="sbp-dismiss-review-notice sbp-reviewlink button-primary" target="_blank" rel="noopener">Ok, you deserve it</a></li>
					<li><span class="dashicons dashicons-calendar"></span><a href="#" class="sbp-dismiss-review-notice" target="_blank" rel="noopener">Nope, maybe later</a></li>
					<li><span class="dashicons dashicons-smiley"></span><a href="#" class="sbp-dismiss-review-notice" target="_blank" rel="noopener">I already did</a></li>
				</ul>
			</p>
			<p><small>This notice is shown every 30 days until dismissed.</small></p>
		</div>
			<?php 
        }
        
        // do_admin_notices
        // Update dismissed notice
        public static function sbp_dismiss_review()
        {
            check_ajax_referer( 'seobooster-nonce' );
            $review = get_option( 'sbp_review_notice' );
            if ( !$review ) {
                $review = array();
            }
            $review['time'] = time() + WEEK_IN_SECONDS * 4;
            $review['dismissed'] = true;
            update_option( 'sbp_review_notice', $review );
            die;
        }
        
        /**
         * Fetch plugin version from plugin PHP header
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, January 13th, 2021.
         * @access  public static
         * @return  mixed
         */
        public static function get_plugin_version()
        {
            $plugin_data = get_file_data( __FILE__, array(
                'version' => 'Version',
            ), 'plugin' );
            self::$version = $plugin_data['version'];
            return $plugin_data['version'];
        }
        
        /**
         * wp_ajax_ajax_add_keyword_callback.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Sunday, August 1st, 2021.
         * @access  public static
         * @return  void
         */
        public static function wp_ajax_ajax_add_keyword_callback()
        {
            check_ajax_referer( 'add-keyword-nonce', 'add-keyword-nonce', true );
            global  $wpdb ;
            $keyword = sanitize_text_field( $_POST['newkeyword'] );
            $targeturl = sanitize_text_field( $_POST['targeturl'] );
            $keyword_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sb2_autolink WHERE keyword = %s", $keyword ) );
            if ( $keyword_id ) {
                wp_send_json( array(
                    'answer' => sprintf( __( 'Error - Keyword <code>%s</code> is already used.', 'seo-booster' ), esc_attr( $keyword ) ),
                    'error'  => 'kwused',
                ) );
            }
            // Check URL is valid
            if ( filter_var( sanitize_text_field( $_POST['targeturl'] ), FILTER_VALIDATE_URL ) === false ) {
                wp_send_json( array(
                    'answer' => sprintf( __( 'Error - <code>%s</code> is not a valid URL.', 'seo-booster' ), esc_attr( sanitize_text_field( $_POST['targeturl'] ) ) ),
                    'error'  => 'malurl',
                ) );
            }
            // Insert the new keyword link
            
            if ( $keyword && $targeturl ) {
                $wpdb->insert( "{$wpdb->prefix}sb2_autolink", array(
                    'keyword' => sanitize_text_field( $_POST['newkeyword'] ),
                    'url'     => sanitize_text_field( $_POST['targeturl'] ),
                ), array( '%s', '%s' ) );
                $last_insert_id = $wpdb->insert_id;
                
                if ( $last_insert_id ) {
                    $newrow = '<tr><th scope="row" class="check-column"><input type="checkbox" name="alid[]" value="' . esc_attr( $last_insert_id ) . '"></th><td class="keyword column-keyword has-row-actions column-primary" data-colname="' . __( 'Keyword', 'seo-booster' ) . '">' . esc_html( $keyword ) . '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __( 'Show more details', 'seo-booster' ) . '</span></button></td><td class="pointing column-pointing" data-colname=""><span class="dashicons dashicons-arrow-right-alt"></span></td><td class="url column-url" data-colname="' . __( 'Target URL', 'seo-booster' ) . '"><a href="' . esc_url( $targeturl ) . '" target="_blank">' . esc_url( $targeturl ) . '</a></td></tr>';
                    wp_send_json( array(
                        'newrow'  => $newrow,
                        'answer'  => sprintf( __( 'Success! <code>%1$s</code> now links to <code>%2$s</code>.', 'seo-booster' ), $keyword, $targeturl ),
                        'success' => true,
                    ) );
                }
            
            }
            
            exit;
            // always stop
        }
        
        /**
         * do_custom_meta.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, November 30th, 2021.
         * @access  public static
         * @return  void
         */
        public static function do_custom_meta()
        {
            $post_types = get_post_types( array(
                'public'   => true,
                '_builtin' => false,
            ) );
            array_push( $post_types, 'post', 'page' );
            add_meta_box(
                'sbp_meta',
                __( 'SEO Booster', 'seo-booster' ),
                array( __CLASS__, 'sbp_meta_callback' ),
                $post_types,
                'side',
                'default',
                null
            );
        }
        
        /**
         * sbp_meta_callback.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, November 30th, 2021.
         * @access  public static
         * @param   mixed   $post
         * @return  void
         */
        public static function sbp_meta_callback( $post )
        {
            wp_nonce_field( basename( __FILE__ ), 'sbp_nonce' );
            $sbp_stored_meta = get_post_meta( $post->ID, '_sbp-autolink', true );
            // first time - lets set the default value to yes, so to replace keywords to links automatically.
            if ( 'auto-draft' === $post->post_status ) {
                $sbp_stored_meta = 'yes';
            }
            
            if ( !$sbp_stored_meta ) {
                add_post_meta( $post->ID, '_sbp-autolink', 'yes' );
                $sbp_stored_meta = 'yes';
            }
            
            ?>
			<strong>
			<?php 
            esc_html_e( 'Automatic Linking', 'seo-booster' );
            ?>
			</strong>
			<p>
				<label for="sbp-autolink">
					<input type="checkbox" name="sbp-autolink" id="sbp-autolink" value="yes"
					<?php 
            if ( isset( $sbp_stored_meta ) ) {
                checked( $sbp_stored_meta, 'yes' );
            }
            ?>
					/>
					<?php 
            esc_html_e( 'Change keywords on this page to links.', 'seo-booster' );
            ?>
					</label>
					<?php 
            $seobooster_internal_linking = get_option( 'seobooster_internal_linking' );
            
            if ( !$seobooster_internal_linking ) {
                ?>
						<small>Feature is disabled. Enable in SEO Booster settings.</small>
						<?php 
            } else {
                $autolink_url = admin_url( 'admin.php?page=sb2_autolink' );
                ?>
						<small>Change keywords and links in <a href="
						<?php 
                echo  esc_url( $autolink_url ) ;
                ?>
						" target="_blank">Autolink</a></small>
						<?php 
            }
            
            ?>
				</p>
				<?php 
            $appendkeywords = get_post_meta( $post->ID, '_sbp-appendkeywords', true );
            
            if ( !$appendkeywords ) {
                add_post_meta( $post->ID, '_sbp-appendkeywords', 'yes' );
                $appendkeywords = 'yes';
            }
            
            ?>
				<strong>
				<?php 
            esc_html_e( 'Append Keywords List', 'seo-booster' );
            ?>
			</strong>
				<p>
					<label for="sbp-appendkeywords">
						<input type="checkbox" name="sbp-appendkeywords" id="sbp-appendkeywords" value="yes"
						<?php 
            if ( isset( $appendkeywords ) ) {
                checked( $appendkeywords, 'yes' );
            }
            ?>
						/>
							<?php 
            esc_html_e( 'Append the list of popular keywords to this page.', 'seo-booster' );
            ?>
						</label>
					</p>
					<?php 
        }
        
        /**
         * Saves the custom meta input
         *
         * @param  int
         * @return [type]
         */
        public static function do_meta_save( $post_id )
        {
            // Checks save status
            $is_autosave = wp_is_post_autosave( $post_id );
            $is_revision = wp_is_post_revision( $post_id );
            $is_valid_nonce = ( isset( $_POST['sbp_nonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['sbp_nonce'] ), basename( __FILE__ ) ) ? 'true' : 'false' );
            // Exits script depending on save status
            if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
                return;
            }
            // SSSS - @fix - samle i en setting
            
            if ( isset( $_POST['sbp-autolink'] ) ) {
                update_post_meta( $post_id, '_sbp-autolink', 'yes' );
            } else {
                update_post_meta( $post_id, '_sbp-autolink', 'no' );
            }
            
            // Appending keywords to this page??
            
            if ( isset( $_POST['sbp-appendkeywords'] ) ) {
                update_post_meta( $post_id, '_sbp-appendkeywords', 'yes' );
            } else {
                update_post_meta( $post_id, '_sbp-appendkeywords', 'no' );
            }
        
        }
        
        public static function prefixsetupschedule()
        {
            if ( !wp_next_scheduled( 'sbp_hourlymaintenance' ) ) {
                wp_schedule_event( time(), 'hourly', 'sbp_hourlymaintenance' );
            }
            if ( !wp_next_scheduled( 'sbp_email_update' ) ) {
                wp_schedule_event( time(), 'weekly', 'sbp_email_update' );
            }
        }
        
        /**
         * Find a word in a string
         * Ref https://stackoverflow.com/questions/4366730/how-do-i-check-if-a-string-contains-a-specific-word
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, November 30th, 2021.
         * @access  public static
         * @param   mixed   $str    String to search in
         * @param   mixed   $word   Word to look for
         * @return  mixed
         */
        public static function contains_word( $str, $word )
        {
            preg_match(
                '#\\b' . preg_quote( $word, '#' ) . '\\b#i',
                $str,
                $matches,
                PREG_OFFSET_CAPTURE
            );
            return $matches;
        }
        
        /**
         * from shkspr.mobi - https://shkspr.mobi/blog/2012/09/a-utf-8-aware-substr_replace-for-use-in-app-net/
         *
         * @var     public  stati
         */
        public static function utf8_substr_replace(
            $original,
            $replacement,
            $position,
            $length
        )
        {
            $start_string = mb_substr(
                $original,
                0,
                $position,
                'UTF-8'
            );
            $end_string = mb_substr(
                $original,
                $position + $length,
                mb_strlen( $original ),
                'UTF-8'
            );
            $out = $start_string . $replacement . $end_string;
            return $out;
        }
        
        /**
         * Checks a string against an array of keywords and returns any matches or false if no match.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, November 30th, 2021.
         * @access  public static
         * @param   mixed   $str
         * @param   array   $arr
         * @return  boolean
         */
        public static function array_in_string( $str, array $arr )
        {
            $return_arr = array();
            foreach ( $arr as $arr_value ) {
                $wrdpos = mb_stripos( $str, $arr_value['kw'] );
                
                if ( false !== $wrdpos ) {
                    $orgword = mb_substr( $str, $wrdpos, mb_strlen( $arr_value['kw'] ) );
                    $arr_value['orgword'] = $orgword;
                    $return_arr[] = $arr_value;
                }
            
            }
            if ( !empty($return_arr) ) {
                return $return_arr;
            }
            return false;
        }
        
        /**
         * do_filter_the_content.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, November 30th, 2021.
         * @access  public static
         * @param   mixed   $content
         * @param   boolean $forced     Default: false
         * @return  mixed
         */
        public static function do_filter_the_content( $content, $forced = false )
        {
            // @Todo - seperate as a different function to do a replace in future on other post types and text.
            global  $post ;
            $replacecontent = false;
            $seobooster_internal_linking = get_option( 'seobooster_internal_linking', false );
            $seobooster_replace_cat_desc = get_option( 'seobooster_replace_cat_desc', false );
            $seobooster_replace_tags = get_option( 'seobooster_replace_tags', false );
            $seobooster_wpmofo = get_option( 'seobooster_wpmofo', false );
            $seobooster_woocommerce = get_option( 'seobooster_woocommerce', false );
            if ( isset( $post ) && 'page' === $post->post_type && $seobooster_internal_linking ) {
                $replacecontent = true;
            }
            if ( isset( $post->post_type ) && 'post' === $post->post_type && $seobooster_internal_linking ) {
                $replacecontent = true;
            }
            if ( is_post_type_archive() && $seobooster_replace_cat_desc ) {
                $replacecontent = true;
            }
            if ( is_tax() && $seobooster_replace_tags ) {
                $replacecontent = true;
            }
            // Detect WPFORO plugin
            if ( function_exists( 'is_wpforo_page' ) && is_wpforo_page() && $seobooster_wpmofo ) {
                $replacecontent = true;
            }
            // WooCommerce
            if ( isset( $post->post_type ) && 'product' === $post->post_type && $seobooster_woocommerce ) {
                $replacecontent = true;
            }
            global  $wpdb ;
            
            if ( $forced || $replacecontent ) {
                $seobooster_append_keywords = get_option( 'seobooster_append_keywords' );
                $currurl = self::seobooster_currenturl();
                $thispostid = get_the_id();
                $append_on_post = get_post_meta( $thispostid, '_sbp-appendkeywords', true );
                
                if ( $seobooster_append_keywords && 'yes' === $append_on_post ) {
                    $seobooster_append_title = get_option( 'seobooster_append_title' );
                    global  $wpdb ;
                    $introtext = '<div class="sbpappendcon"><div class="sbpappendtitle">' . $seobooster_append_title . '</div>';
                    $before = '<div class="sbplist">' . $introtext . '<ul class="sbplistul">';
                    $beforeeach = '<li>';
                    $aftereach = '</li>';
                    $after = '</ul></div></div>';
                    $currurl = strtok( $currurl, '?' );
                    // Strips parameters
                    $sqlignore = self::seobooster_generateignorelist();
                    $query = "SELECT * FROM `{$wpdb->prefix}sb2_kw` WHERE {$sqlignore} `lp` = '" . $currurl . "' AND kw<>'#' and kw<>'' ORDER BY `visits` DESC limit 20;";
                    $posthits = $wpdb->get_results( $query, ARRAY_A );
                    
                    if ( $posthits ) {
                        $content .= "<style>.sbpappendcon {}.sbpappendtitle {font-weight:bold;}.sbplist {clear: both;float: left;width: 100%;margin-bottom: 10px;}.sbplistul li {float: left;list-style-type: none;}.sbplistul li:after {content: ',';margin-right: 5px;}.sbplistul li:last-of-type:after {content: '';}</style>";
                        $content .= $before;
                        foreach ( $posthits as $hits ) {
                            $content .= $beforeeach;
                            $content .= $hits['kw'];
                            $content .= $aftereach;
                        }
                        $content .= $after;
                    }
                
                }
                
                // if append_keywords
                $seobooster_internal_linking = get_option( 'seobooster_internal_linking' );
                if ( !$seobooster_internal_linking ) {
                    return $content;
                }
                // check if this page content is to be excluded - returning unmodified content
                if ( $thispostid ) {
                    $sbp_stored_meta = get_post_meta( $thispostid, '_sbp-autolink', true );
                }
                
                if ( isset( $sbp_stored_meta ) && 'yes' !== $sbp_stored_meta ) {
                    // translators:
                    self::log( sprintf( __( 'Debug: Automatic links turned off on %s - No links added. ', 'seo-booster' ), '<a href="' . $currurl . '" target="_blank">' . self::remove_http( $currurl ) . '</a>' ) );
                    return $content;
                }
                
                global  $wpdb ;
                $currenturl = self::seobooster_currenturl();
                // all-round solution
                $currenturl = strtok( $currenturl, '?' );
                // Strips parameters
                $fullcurrenturl = site_url( $currenturl );
                self::timerstart( 'autolink' );
                $lookupkwlimit = 500;
                // todo - setting
                $replace_limit = intval( get_option( 'seobooster_replace_kw_limit' ) );
                
                if ( !$replace_limit ) {
                    $replace_limit = 5;
                    // Default value 5 links per page
                }
                
                $replaced = 0;
                // internal function use
                $step_count = 0;
                // internal function use
                $search_replace_arr = array();
                //
                // ***** AUTOLINK MODULE - 3.4+
                //
                $lp = self::seobooster_currenturl();
                $replace_kw_multiple = get_option( 'seobooster_replace_kw_multiple' );
                // Removes duplicate values, not needed after v. 3.4+
                $search_replace_arr = self::return_autolinks();
                // In case there are no results found
                if ( isset( $search_replace_arr ) && !$search_replace_arr && 'on' === get_option( 'seobooster_debug_logging' ) ) {
                    self::log( __( 'Auto link turned on, but no results found.', 'seo-booster' ) . ' ' . $currenturl );
                }
                // In case it is not an array
                if ( isset( $search_replace_arr ) && !is_array( $search_replace_arr ) || isset( $search_replace_arr ) && '' === $search_replace_arr ) {
                    self::log( __( 'Auto link turned on, but no keyword found.', 'seo-booster' ) . ' ' . $currenturl );
                }
                if ( !class_exists( 'simple_html_dom' ) ) {
                    include_once 'inc/simple_html_dom.php';
                }
                $html = new simple_html_dom();
                libxml_use_internal_errors( true );
                $html->load( $content );
                $replace_count = 0;
                $total_step_count = 0;
                $replace_notes = '';
                $replaced = false;
                $step_count = 0;
                $class = '';
                // defaults to empty // todo ssss - make option to show?
                $replace_kw_arr = array();
                foreach ( $html->find( 'text' ) as $text ) {
                    // prevents links in elements we don't want.
                    ++$total_step_count;
                    if ( in_array( $text->parent->tag, array(
                        'a',
                        'h1',
                        'h2',
                        'h3',
                        'h4',
                        'h5',
                        'h6',
                        'h7',
                        'span',
                        'button'
                    ), true ) ) {
                        continue;
                    }
                    $org = strval( $text->outertext );
                    
                    if ( '' !== $org && ' ' !== $org && $replace_count <= $replace_limit ) {
                        $found_keywords = false;
                        if ( is_array( $search_replace_arr ) ) {
                            $found_keywords = self::array_in_string( $org, $search_replace_arr );
                        }
                        unset( $replacedtext_no_html );
                        if ( is_array( $found_keywords ) ) {
                            foreach ( $found_keywords as $found_kw ) {
                                // No linking to same page.
                                
                                if ( $found_kw['lp'] != $fullcurrenturl ) {
                                    $replacestring = '<a href="' . $found_kw['lp'] . '"' . $class . '>' . $found_kw['orgword'] . '</a>';
                                    $replacedtext = self::utf8_substr_replace(
                                        $org,
                                        $replacestring,
                                        mb_stripos( $org, $found_kw['kw'] ),
                                        mb_strlen( $found_kw['kw'] )
                                    );
                                    $length = mb_strlen( $found_kw['kw'] );
                                    $replacedtext_no_html = self::utf8_substr_replace(
                                        $org,
                                        str_repeat( '-', mb_strlen( $replacestring ) ),
                                        mb_stripos( $org, $found_kw['kw'] ),
                                        mb_strlen( $found_kw['kw'] )
                                    );
                                    $text->outertext = $replacedtext;
                                    $org = $text->outertext;
                                    ++$replace_count;
                                    // Look up the lastseen column
                                    $lastseen = $wpdb->get_var( $wpdb->prepare( "SELECT lastseen FROM {$wpdb->prefix}sb2_autolink WHERE id =%d", $found_kw['id'] ) );
                                    // If the result is empty, create the array. Removes PHP notice error
                                    if ( !is_array( $lastseen ) ) {
                                        $lastseen = array();
                                    }
                                    // If the current URL is not in the list already
                                    
                                    if ( !in_array( self::remove_http( $currenturl ), $lastseen, true ) ) {
                                        // Moves it to the front of the list, pushing older down and also ensures it is not the one removed
                                        array_unshift( $lastseen, self::remove_http( $currenturl ) );
                                        // Removes any more than 3 elements
                                        $lastseen = array_slice( $lastseen, 0, 3 );
                                        // Updating the keyword row
                                    }
                                    
                                    // translators:
                                    $replace_notes .= sprintf( __( '<a href="%1$s" target="_blank">%2$s</a>', 'seo-booster' ), $found_kw['lp'], $found_kw['orgword'] ) . ', ';
                                    
                                    if ( !$replace_kw_multiple ) {
                                        // Unsets orgword value to match original style
                                        $temp_lookup = $found_kw;
                                        unset( $temp_lookup['orgword'] );
                                        $key = array_search( $temp_lookup, $search_replace_arr, true );
                                        if ( false !== $key ) {
                                            unset( $search_replace_arr[$key] );
                                        }
                                    }
                                    
                                    $replace_kw_arr[] = trim( $found_kw['kw'] );
                                }
                            
                            }
                        }
                    }
                
                }
                $end = self::timerstop( 'autolink' );
                
                if ( $replace_count > 0 ) {
                    $replace_notes = rtrim( $replace_notes, ', ' );
                    // translators:
                    $logmessage = sprintf(
                        __( '<code>%1$d</code> auto link on %2$s (%3$s sec. %4$s loops)', 'seo-booster' ) . ' ' . $replace_notes,
                        $replace_count,
                        '<a href="' . $currenturl . '" target="_blank">' . $currenturl . '</a>',
                        $end,
                        number_format_i18n( $total_step_count )
                    );
                    self::log( $logmessage );
                    $content = $html->save();
                }
            
            }
            
            
            if ( isset( $html ) ) {
                $html->clear();
                unset( $html );
            }
            
            return $content;
        }
        
        /**
         * Returns icon in SVG format
         * Thanks Yoast for example code.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, November 30th, 2021.
         * @access  public static
         * @param   boolean $base64 Default: true
         * @return  mixed
         */
        public static function get_icon_svg( $base64 = true )
        {
            $svg = '<svg viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:bx="https://boxy-svg.com">
			<defs>
			<symbol id="symbol-0" viewBox="0 0 100 100">
			<path d="M 63.332 70.126 L 63.332 70.126 L 63.332 70.126 C 63.332 67.186 62.292 64.896 60.212 63.256 L 60.212 63.256 L 60.212 63.256 C 58.132 61.616 54.475 59.916 49.242 58.156 L 49.242 58.156 L 49.242 58.156 C 44.015 56.403 39.739 54.706 36.412 53.066 L 36.412 53.066 L 36.412 53.066 C 25.612 47.759 20.212 40.466 20.212 31.186 L 20.212 31.186 L 20.212 31.186 C 20.212 26.566 21.555 22.489 24.242 18.956 L 24.242 18.956 L 24.242 18.956 C 26.935 15.423 30.745 12.673 35.672 10.706 L 35.672 10.706 L 35.672 10.706 C 40.599 8.739 46.135 7.756 52.282 7.756 L 52.282 7.756 L 52.282 7.756 C 58.275 7.756 63.649 8.826 68.402 10.966 L 68.402 10.966 L 68.402 10.966 C 73.155 13.106 76.852 16.149 79.492 20.096 L 79.492 20.096 L 79.492 20.096 C 82.125 24.049 83.442 28.566 83.442 33.646 L 83.442 33.646 L 63.392 33.646 L 63.392 33.646 C 63.392 30.246 62.352 27.613 60.272 25.746 L 60.272 25.746 L 60.272 25.746 C 58.192 23.873 55.375 22.936 51.822 22.936 L 51.822 22.936 L 51.822 22.936 C 48.235 22.936 45.402 23.729 43.322 25.316 L 43.322 25.316 L 43.322 25.316 C 41.235 26.896 40.192 28.909 40.192 31.356 L 40.192 31.356 L 40.192 31.356 C 40.192 33.496 41.339 35.433 43.632 37.166 L 43.632 37.166 L 43.632 37.166 C 45.925 38.906 49.955 40.703 55.722 42.556 L 55.722 42.556 L 55.722 42.556 C 61.489 44.403 66.222 46.396 69.922 48.536 L 69.922 48.536 L 69.922 48.536 C 78.935 53.729 83.442 60.889 83.442 70.016 L 83.442 70.016 L 83.442 70.016 C 83.442 77.309 80.692 83.036 75.192 87.196 L 75.192 87.196 L 75.192 87.196 C 69.692 91.363 62.152 93.446 52.572 93.446 L 52.572 93.446 L 52.572 93.446 C 45.812 93.446 39.692 92.233 34.212 89.806 L 34.212 89.806 L 34.212 89.806 C 28.732 87.379 24.609 84.056 21.842 79.836 L 21.842 79.836 L 21.842 79.836 C 19.075 75.616 17.692 70.759 17.692 65.266 L 17.692 65.266 L 37.852 65.266 L 37.852 65.266 C 37.852 69.733 39.005 73.026 41.312 75.146 L 41.312 75.146 L 41.312 75.146 C 43.625 77.259 47.379 78.316 52.572 78.316 L 52.572 78.316 L 52.572 78.316 C 55.892 78.316 58.515 77.603 60.442 76.176 L 60.442 76.176 L 60.442 76.176 C 62.369 74.743 63.332 72.726 63.332 70.126 Z" transform="matrix(1, 0, 0, 1, 0, 0)" style="fill: rgb(130, 135, 140); white-space: pre;" id="s"/>
			</symbol>
			</defs>
			<use width="100" height="100" transform="matrix(4.947808, 0, 0, 4.947808, -20.354914, -11.482257)" xlink:href="#symbol-0"/>
			<path style="paint-order: stroke; fill: rgb(130, 135, 140);" d="M 349.355 16.098 C 333.687 49.355 248.938 171.838 248.938 171.838 C 248.938 171.838 228.3 199.676 236.116 203.927 C 247.584 210.168 267.795 206.135 284.389 206.805 C 309.456 207.816 329.639 205.313 341.68 205.786 C 341.68 205.786 359.942 201.1 363.11 211.672 C 365.18 218.581 354.131 230.067 354.131 230.067 L 105.339 481.212 L 213.627 310.542 C 213.627 310.542 221.796 293.779 216.787 287.127 C 210.653 278.986 186.557 281.117 186.557 281.117 C 186.557 281.117 140.259 279.657 117.109 279.939 C 108.054 280.05 99.5 279.319 99.082 272.877 C 98.532 264.365 100.711 262.353 110.047 252.866 C 188.089 173.584 349.355 16.098 349.355 16.098 Z"/>
			</svg>';
            
            if ( $base64 ) {
                return 'data:image/svg+xml;base64,' . base64_encode( $svg );
                //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
            }
            
            return $svg;
        }
        
        /**
         * Load language files
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Friday, February 5th, 2021.
         * @access  public static
         * @return  void
         */
        public static function do_plugins_loaded()
        {
            load_plugin_textdomain( 'seo-booster', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
            $seobooster_db_version = get_option( 'SEOBOOSTER_INSTALLED_DB_VERSION', '1.0' );
            // latest update
            
            if ( version_compare( $seobooster_db_version, SEOBOOSTER_DB_VERSION ) < 0 ) {
                global  $wpdb ;
                self::log( 'Updating database tables - PageSpeed scores reset.', 10 );
                // Resetting pagespeed tables if they exist - before updating
                $table_array = array( $wpdb->prefix . 'sb2_urls', $wpdb->prefix . 'sb2_urls_meta' );
                foreach ( $table_array as $tablename ) {
                    $wpdb->query( "TRUNCATE TABLE {$tablename}" );
                }
                self::create_database_tables();
            }
        
        }
        
        /**
         * Checks if user agent match a known crawler robot
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Friday, February 5th, 2021.
         * @access  public static
         * @param   mixed   $user_agent
         * @return  boolean
         */
        public static function robot_detect( $user_agent )
        {
            $common_browsers = 'Firefox|Chrome|Opera|MSIE';
            if ( preg_match( '/' . $common_browsers . '/i', $user_agent ) ) {
                return false;
            }
            $robots = array(
                'bayspider',
                'bbot',
                'BingBot',
                'checkbot',
                'christcrawler',
                'fastcrawler',
                'Googlebot Images',
                'Googlebot News',
                'Googlebot',
                'infospider',
                'lycos',
                'slcrawler',
                'Slurp',
                'smartspider',
                'spiderbot',
                'spiderline',
                'spiderman',
                'Baiduspider',
                'voyager',
                'vwbot',
                'MJ12bot',
                'Screaming Frog',
                'SeznamBot',
                'DuckDuckBot',
                'YandexBot',
                'MojeekBot'
            );
            $found_key = null;
            foreach ( $robots as $key => $value ) {
                
                if ( false !== stripos( $user_agent, $value ) ) {
                    $found_key = $value;
                    break;
                }
            
            }
            if ( null !== $found_key ) {
                return $found_key;
            }
            return false;
        }
        
        /**
         * Runs on action "template_redirect" - 404
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, November 30th, 2021.
         * @access  public static
         * @return  void
         */
        public static function template_redirect_action()
        {
            if ( empty($_POST) && defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || defined( 'XMLRPC_REQUEST' ) || defined( 'DOING_AUTOSAVE' ) || defined( 'REST_REQUEST' ) ) {
                return;
            }
            $fof_monitoring = get_option( 'seobooster_fof_monitoring' );
            
            if ( 'on' !== $fof_monitoring ) {
                return;
                // 404 error monitoring is turned off so ...
            }
            
            $currurl = self::seobooster_currenturl();
            
            if ( isset( $currenturl ) ) {
                $currenturl = strtok( $currenturl, '?' );
                // Strips parameters
            }
            
            // List of args to ignore in query strings on 404 pages.
            $ignore_args = array( 'wordfence_lh' );
            $ignored_parts = $ignore_args;
            // gets the other parts from settings page
            $seobooster_fof_ignore = get_option( 'seobooster_fof_ignore' );
            $seobooster_fof_ignore = explode( ',', $seobooster_fof_ignore );
            
            if ( is_array( $seobooster_fof_ignore ) ) {
                $ignored_parts = array_merge( $seobooster_fof_ignore, $ignore_args );
                $ignored_parts = array_map( 'strtolower', $ignored_parts );
            }
            
            $parts = wp_parse_url( $currurl );
            if ( isset( $parts['query'] ) ) {
                parse_str( $parts['query'], $query_parms );
            }
            // Extracting the part after the domain name to run matches against ignore 404 parts
            $query = wp_parse_url( $currurl );
            $extractedpath = '';
            if ( isset( $query['path'] ) ) {
                $extractedpath .= $query['path'];
            }
            if ( isset( $query['query'] ) ) {
                $extractedpath .= $query['query'];
            }
            $extractedpath = strtolower( $extractedpath );
            foreach ( $ignored_parts as $part ) {
                
                if ( stripos( $extractedpath, $part ) ) {
                    return;
                    // Stop this - return
                }
            
            }
            global  $wpdb ;
            
            if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
                $parsedurl = wp_parse_url( sanitize_text_field( $_SERVER['HTTP_REFERER'] ) );
                $domain = $parsedurl['host'];
            }
            
            
            if ( is_404() ) {
                
                if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
                    $referer = strtolower( sanitize_text_field( $_SERVER['HTTP_REFERER'] ) );
                } else {
                    $referer = '';
                }
                
                // Filtrer .css og .js filer fra
                $pathinfo = pathinfo( $currurl );
                
                if ( isset( $pathinfo['extension'] ) ) {
                    $extension = strtolower( $pathinfo['extension'] );
                    
                    if ( in_array( $extension, array( 'css', 'js' ), true ) ) {
                        return;
                        // filter out unwanted .js and .css
                    }
                
                }
                
                $excistingentry = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sb2_404 WHERE lp = %s", $currurl ) );
                $rightnow = gmdate( 'Y-m-d H:i:s' );
                
                if ( $excistingentry ) {
                    $lastcount = $wpdb->get_var( $wpdb->prepare( "SELECT visits FROM {$wpdb->prefix}sb2_404 WHERE id = %d", $excistingentry ) );
                    $rows_affected = $wpdb->query( $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}sb2_404 SET visits = %d, lastseen = %s WHERE id = %s",
                        $lastcount + 1,
                        $rightnow,
                        $excistingentry
                    ) );
                } else {
                    // a NEW keyword and/or tld, insert into database...
                    // prepare not needed with insert->
                    $wpdb->insert( $wpdb->prefix . 'sb2_404', array(
                        'lp'        => $currurl,
                        'firstseen' => $rightnow,
                        'lastseen'  => $rightnow,
                        'visits'    => 1,
                        'referer'   => $referer,
                    ), array(
                        '%s',
                        '%s',
                        '%s',
                        '%d',
                        '%s'
                    ) );
                    
                    if ( isset( $referer ) && '' !== $referer ) {
                        self::log( sprintf(
                            // translators:
                            __( 'New 404 - <a href="%1$s" target="_blank">%2$s</a> Referer: %3$s', 'seo-booster' ),
                            $currurl,
                            $currurl,
                            $referer
                        ), 2 );
                    } else {
                        // translators:
                        self::log( sprintf( __( 'New 404 - <a href="%1$s" target="_blank">%2$s</a>', 'seo-booster' ), $currurl, $currurl ), 2 );
                    }
                
                }
            
            }
        
        }
        
        /**
         * Returns true if $url is a local installation - Thanks EDD
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Sunday, November 7th, 2021.
         * @access  public static
         * @param   string  $url    Default: ''
         * @return  mixed
         */
        public static function is_local_url( $url = '' )
        {
            $is_local_url = false;
            $url = strtolower( trim( $url ) );
            if ( false === strpos( $url, 'http://' ) && false === strpos( $url, 'https://' ) ) {
                $url = 'http://' . $url;
            }
            $url_parts = wp_parse_url( $url );
            $host = ( !empty($url_parts['host']) ? $url_parts['host'] : false );
            
            if ( !empty($url) && !empty($host) ) {
                
                if ( false !== ip2long( $host ) ) {
                    if ( !filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                        $is_local_url = true;
                    }
                } elseif ( 'localhost' === $host ) {
                    $is_local_url = true;
                }
                
                $tlds_to_check = array( '.dev', '.local', '.loc' );
                foreach ( $tlds_to_check as $tld ) {
                    
                    if ( false !== strpos( $host, $tld ) ) {
                        $is_local_url = true;
                        continue;
                    }
                
                }
                
                if ( substr_count( $host, '.' ) > 1 ) {
                    $subdomains_to_check = array( 'dev.', 'staging.' );
                    foreach ( $subdomains_to_check as $subdomain ) {
                        
                        if ( 0 === strpos( $host, $subdomain ) ) {
                            $is_local_url = true;
                            continue;
                        }
                    
                    }
                }
            
            }
            
            return $is_local_url;
        }
        
        /**
         * When deleting a blog in multisite - returns array of tables to delete
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, November 30th, 2021.
         * @access  public static
         * @param   mixed   $tables
         * @return  mixed
         */
        public static function on_delete_blog( $tables )
        {
            global  $wpdb ;
            $tables = array();
            $tables[] = $wpdb->prefix . 'sb2_crawl';
            $tables[] = $wpdb->prefix . 'sb2_autolink';
            $tables[] = $wpdb->prefix . 'sb2_bl';
            $tables[] = $wpdb->prefix . 'sb2_log';
            $tables[] = $wpdb->prefix . 'sb2_kwdt';
            $tables[] = $wpdb->prefix . 'sb2_kw';
            $tables[] = $wpdb->prefix . 'sb2_404';
            $tables[] = $wpdb->prefix . 'sb2_urls';
            $tables[] = $wpdb->prefix . 'sb2_urls_meta';
            return $tables;
        }
        
        /**
         * Turns a relative URL to absolute URL.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, November 30th, 2021.
         * @access  public static
         * @param   mixed   $rel
         * @param   mixed   $base
         * @return  mixed
         */
        public static function rel2abs( $rel, $base )
        {
            if ( strpos( $rel, '//' ) === 0 ) {
                return 'http:' . $rel;
            }
            /* return if  already absolute URL */
            if ( '' !== wp_parse_url( $rel, PHP_URL_SCHEME ) ) {
                return $rel;
            }
            /* queries and  anchors */
            if ( '#' === $rel[0] || '?' === $rel[0] ) {
                return $base . $rel;
            }
            /* parse base URL  and convert to local variables:
            			$scheme, $host,  $path */
            $parse_url = wp_parse_url( $base );
            /* remove  non-directory element from path */
            if ( isset( $parse_url['path'] ) ) {
                $path = preg_replace( '#/[^/]*$#', '', $parse_url['path'] );
            }
            if ( '/' === $rel[0] ) {
                $path = '';
            }
            /* dirty absolute  URL */
            $abs = $parse_url['host'] . $path . $rel;
            /* replace '//' or  '/./' or '/foo/../' with '/' */
            $re = array( '#(/.?/)#', '#/(?!..)[^/]+/../#' );
            for ( $n = 1 ;  $n > 0 ;  $abs = preg_replace(
                $re,
                '/',
                $abs,
                -1,
                $n
            ) ) {
            }
            /* absolute URL is  ready! */
            return $abs;
        }
        
        /**
         * plugins_loaded_register_visitor.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, November 30th, 2021.
         * @access  public static
         * @return  void
         */
        public static function plugins_loaded_register_visitor()
        {
            // todo - se om referrer er siteurl, og om param ?s er sat eller ej - hvis det er fra site_url og der ikke er en søgning, så skal vi ikke videre, ikke bruge tid på dette.
            if ( is_admin() ) {
                // We are in admin, so no need to test or do anything
                return;
            }
            if ( empty($_POST) && defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || defined( 'XMLRPC_REQUEST' ) || defined( 'DOING_AUTOSAVE' ) || defined( 'REST_REQUEST' ) || is_admin() ) {
                return;
            }
            $currurl = self::seobooster_currenturl();
            $currurl = strtok( $currurl, '?' );
            // Strips parameters
            if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
                $referer = strtolower( $_SERVER['HTTP_REFERER'] );
            }
            if ( !$currurl ) {
                return;
            }
            // @todo - filter out urls wordfence_lh
            $ignoreres = self::ignore_current_url( $currurl );
            
            if ( $ignoreres ) {
                if ( 'on' === get_option( 'seobooster_debug_logging' ) ) {
                    // translators:
                    self::log( sprintf( __( 'Debug: Ignoring referrer <code>%1$s</code> Matches: <code>%2$s</code> ', 'seo-booster' ), $currurl, $ignoreres ) );
                }
                return;
            }
            
            if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
                $referer = strtolower( sanitize_text_field( $_SERVER['HTTP_REFERER'] ) );
            }
            
            if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
                $useragent = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] );
                if ( self::ignore_useragent( $useragent ) ) {
                    // translators:
                    self::log( sprintf( __( 'Debug: Ignoring user agent <code>%s</code> ', 'seo-booster' ), $useragent ) );
                }
            }
            
            
            if ( isset( $currurl ) && isset( $referer ) ) {
                self::checkreferrer( $currurl, $referer );
            } elseif ( 'on' === get_option( 'seobooster_debug_logging' ) ) {
                self::log( sprintf( 'Debug: Could not detect referrer from visitor.', $useragent ) );
            }
        
        }
        
        /**
         * send_email_update.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, November 30th, 2021.
         * @access  public static
         * @param   integer $days   Default: 7
         * @param   boolean $forced Default: false
         * @return  void
         */
        public static function send_email_update( $days = 7, $forced = false )
        {
            $seobooster_weekly_email = get_option( 'seobooster_weekly_email' );
            if ( 'on' !== $seobooster_weekly_email && !$forced ) {
                return;
            }
            // testing if a local site - only allow if $forced set to true
            
            if ( !$forced && self::is_local_url( site_url() ) ) {
                self::log( __( 'This is a local site, email not sent.', 'seo-booster' ) );
                return;
            }
            
            $seobooster_weekly_email_recipient = get_option( 'seobooster_weekly_email_recipient' );
            // Take default admin in case missing
            if ( !is_email( $seobooster_weekly_email_recipient ) ) {
                $seobooster_weekly_email_recipient = get_option( 'admin_email' );
            }
            
            if ( !is_int( $days ) ) {
                $days = 7;
                // default
            }
            
            global  $wpdb ;
            $tablename = $wpdb->prefix . 'sb2_kw';
            // reset
            $content = '';
            $sendme = false;
            $somethingnew = 0;
            // We assume nothing new has happened.
            $knownkws_query = "SELECT lp, firstvisit, visits, engine FROM {$tablename} WHERE firstvisit BETWEEN DATE_SUB(NOW(), INTERVAL {$days} DAY) AND NOW() GROUP BY lp order by visits DESC, firstvisit DESC limit 10;";
            $knownkws = $wpdb->get_results( $knownkws_query );
            
            if ( $knownkws ) {
                $sendme = true;
                // translators:
                $content .= '<p>' . sprintf( __( 'Top 10 pages with Search Engine traffic the past %s days.', 'seo-booster' ), $days ) . '</p>';
                $pasturl = '';
                foreach ( $knownkws as $keyword ) {
                    ++$somethingnew;
                    $kws_comma_separated = '';
                    $kwarr = array();
                    foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT kw FROM {$wpdb->prefix}sb2_kw WHERE firstvisit BETWEEN DATE_SUB(NOW(), INTERVAL {$days} DAY) AND NOW() AND lp LIKE %s AND engine<>'Internal Search' AND kw<>'#';", $keyword->lp ) ) as $key => $row ) {
                        $kwarr[] = $row->kw;
                    }
                    $visits = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(visits) FROM {$wpdb->prefix}sb2_kw WHERE firstvisit BETWEEN DATE_SUB(NOW(), INTERVAL %d DAY) AND NOW() AND lp=%s;", $days, $keyword->lp ) );
                    $kws_comma_separated = implode( ', ', $kwarr );
                    $content .= '<a href="' . $keyword->lp . '">' . self::remove_http( $keyword->lp ) . '</a>' . "\r\n";
                    // translators:
                    $content .= sprintf( _n( '%1$s visit from search engines past %2$s days', '%1$s visits from search engines past %2$s days', $visits ), $visits, $days );
                    $content .= "\r\n\r\n";
                    if ( '' !== $kws_comma_separated ) {
                        $content .= __( 'New Keywords:', 'seo-booster' ) . ' ' . $kws_comma_separated . "\r\n\r\n";
                    }
                }
                $dashboardlink = admin_url( '?page=sb2_dashboard' );
                $emailtitle = __( 'Report from SEO Booster on ', 'seo-booster' ) . ' ' . self::remove_http( site_url() );
                // todo i8n med dage
                $dashboardlinkanchor = __( 'SEO Booster Dashboard', 'seo-booster' );
                $emailintrotext = ' ';
                $my_replacements = array(
                    '%%emailintrotext%%'      => $emailintrotext,
                    '%%websitedomain%%'       => self::remove_http( site_url() ),
                    '%%dashboardlink%%'       => $dashboardlink,
                    '%%dashboardlinkanchor%%' => $dashboardlinkanchor,
                    '%%cplogourl%%'           => SEOBOOSTER_PLUGINURL . 'images/cleverpluginslogo.png',
                    '%%emailtitle%%'          => $emailtitle,
                    '%%emailcontent%%'        => nl2br( $content ),
                );
                $html = file_get_contents( SEOBOOSTER_PLUGINURL . 'inc/emailtemplate-01.html' );
                // @todo load via filesystem instead
                foreach ( $my_replacements as $needle => $replacement ) {
                    $html = str_replace( $needle, $replacement, $html );
                }
                $headers = array( 'Content-Type: text/html; charset=UTF-8' );
                $sendresult = wp_mail(
                    $seobooster_weekly_email_recipient,
                    $emailtitle,
                    $html,
                    $headers
                );
                if ( $sendresult ) {
                    // translators:
                    self::log( sprintf( __( 'Status email was sent to %1$s - result %2$s', 'seo-booster' ), $seobooster_weekly_email_recipient, esc_html( $sendresult ) ), 5 );
                }
            } else {
                // translators:
                self::log( sprintf( __( 'No results for past %s days - no email was sent.', 'seo-booster' ), $days ), 3 );
            }
        
        }
        
        /**
         * Adds a direct link to settings from plugin overview page.
         */
        public static function add_settings_link( $actions, $plugin_file )
        {
            static  $plugin ;
            if ( !isset( $plugin ) ) {
                $plugin = plugin_basename( __FILE__ );
            }
            
            if ( $plugin === $plugin_file ) {
                $settings = array(
                    'settings' => '<a href="admin.php?page=sb2_settings">' . __( 'Settings', 'seo-booster' ) . '</a>',
                );
                $documentation = array(
                    'documentation' => '<a href="https://cleverplugins.com/support/" target="_blank">' . __( 'Support', 'seo-booster' ) . '</a>',
                );
                $actions = array_merge( $settings, $actions, $documentation );
            }
            
            return $actions;
        }
        
        /**
         * Generates keyword ignore list for database queries
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Monday, October 11th, 2021.
         * @access  public static
         * @param   string  $ignorelist
         * @return  void
         */
        public static function seobooster_generateignorelist( $ignorelist = '' )
        {
            global  $wpdb ;
            
            if ( !$ignorelist ) {
                $ignorelist = get_option( 'seobooster_ignorelist' );
                $ignorelist = preg_replace( "/\r|\n/", ',', $ignorelist );
                $ignorelist = preg_replace( '/,+/', ',', $ignorelist );
            }
            
            // TODO - optimized query? perhaps put in an array instead
            $ignorearray = explode( ',', $ignorelist );
            $ignores = count( $ignorearray );
            
            if ( '' !== $ignorelist ) {
                $ignoresearchstring = '';
                $count = 0;
                foreach ( $ignorearray as $tag ) {
                    $tag = str_replace( '\'', '', $tag );
                    $tag = trim( $tag );
                    
                    if ( '' !== $tag ) {
                        if ( $count > 0 ) {
                            $ignoresearchstring .= ' OR ';
                        }
                        $like = '%' . $wpdb->esc_like( $tag ) . '%';
                        $ignoresearchstring .= $wpdb->prepare( ' (kw LIKE %s) ', $like );
                        // REMOVED esc_sc.l
                        ++$count;
                    }
                
                }
                return ' NOT (' . $ignoresearchstring . ') AND ';
            } else {
                return '';
            }
        
        }
        
        /**
         * fn_my_ajaxified_dataloader_ajax.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Sunday, November 7th, 2021.
         * @access  public static
         * @return  void
         */
        public static function fn_my_ajaxified_dataloader_ajax()
        {
            check_ajax_referer( 'seobooster-datatable-nonce' );
            global  $wpdb ;
            $kwtable = $wpdb->prefix . 'sb2_kw';
            $kwdttable = $wpdb->prefix . 'sb2_kwdt';
            include 'inc/engine-meta.php';
            $drawval = 1;
            if ( isset( $_REQUEST['draw'] ) ) {
                $drawval = sanitize_text_field( $_REQUEST['draw'] );
            }
            $a_columns = array(
                'kw',
                'lp',
                'engine',
                'visits',
                'firstvisit',
                'lastvisit'
            );
            $sindex_column = 'id';
            // Used for counting results
            $expiry = strtotime( '+1 year' );
            $path = wp_parse_url( site_url(), PHP_URL_PATH );
            $host = wp_parse_url( site_url(), PHP_URL_HOST );
            // Setting cookies to remember user preferred settings
            
            if ( isset( $_REQUEST['length'] ) ) {
                $the_length = sanitize_text_field( $_REQUEST['length'] );
                setcookie(
                    'sbp_kw_length',
                    esc_attr( $the_length ),
                    esc_attr( $expiry ),
                    esc_url( $path ),
                    esc_attr( $host ),
                    true,
                    true
                );
            }
            
            
            if ( isset( $_REQUEST['showkws'] ) ) {
                $the_showkws = sanitize_key( $_REQUEST['showkws'] );
                setcookie(
                    'sbp_the_showkws',
                    esc_attr( $the_showkws ),
                    esc_attr( $expiry ),
                    esc_url( $path ),
                    esc_attr( $host ),
                    true,
                    true
                );
            }
            
            
            if ( isset( $_REQUEST['hideinternal'] ) ) {
                $the_hideinternal = sanitize_key( $_REQUEST['hideinternal'] );
                setcookie(
                    'sbp_kw_hideinternal',
                    esc_attr( $the_hideinternal ),
                    esc_attr( $expiry ),
                    esc_url( $path ),
                    esc_attr( $host ),
                    true,
                    true
                );
            } else {
                setcookie(
                    'sbp_kw_hideinternal',
                    '',
                    time() - 3600,
                    esc_url( $path ),
                    esc_attr( $host ),
                    true,
                    true
                );
            }
            
            $s_limit = 'LIMIT 25';
            if ( isset( $_REQUEST['start'] ) && isset( $_REQUEST['start'] ) && '-1' !== $_REQUEST['length'] ) {
                $s_limit = 'LIMIT ' . intval( $_REQUEST['start'] ) . ', ' . intval( $_REQUEST['length'] );
            }
            $search_order = 'ORDER BY lastvisit DESC';
            
            if ( isset( $_REQUEST['order'] ) ) {
                $search_order = 'ORDER BY ';
                if ( '0' === $_REQUEST['order'][0]['column'] ) {
                    $search_order .= ' kw';
                }
                if ( '1' === $_REQUEST['order'][0]['column'] ) {
                    $search_order .= ' lp';
                }
                if ( '2' === $_REQUEST['order'][0]['column'] ) {
                    $search_order .= ' engine';
                }
                if ( '3' === $_REQUEST['order'][0]['column'] ) {
                    $search_order .= ' visits';
                }
                if ( '4' === $_REQUEST['order'][0]['column'] ) {
                    $search_order .= ' firstvisit';
                }
                if ( '5' === $_REQUEST['order'][0]['column'] ) {
                    $search_order .= ' lastvisit';
                }
                
                if ( 'asc' === $_REQUEST['order'][0]['dir'] ) {
                    $search_order .= ' ASC';
                } else {
                    $search_order .= ' DESC';
                }
                
                // Default
                if ( 'ORDER BY' === $search_order ) {
                    $search_order = 'ORDER BY lastvisit DESC';
                }
            }
            
            $s_where = '';
            
            if ( isset( $_REQUEST['search'] ) && '' !== $_REQUEST['search']['value'] ) {
                $s_where = 'WHERE (';
                $a_columns_count = count( $a_columns );
                for ( $i = 0 ;  $i < $a_columns_count ;  $i++ ) {
                    if ( 'kw' === $a_columns[$i] || 'lp' === $a_columns[$i] ) {
                        $s_where .= '`' . $a_columns[$i] . "` LIKE '%" . esc_sql( sanitize_key( $_REQUEST['search']['value'] ) ) . "%' OR ";
                    }
                }
                $s_where = substr_replace( $s_where, '', -3 );
                // removes the last 'OR ' ...
                $s_where .= ')';
            }
            
            $a_columns_count = count( $a_columns );
            for ( $i = 0 ;  $i < $a_columns_count ;  $i++ ) {
                
                if ( isset( $_REQUEST['bSearchable_' . $i] ) && 'true' === $_REQUEST['bSearchable_' . $i] && '' !== $_REQUEST['sSearch_' . $i] ) {
                    
                    if ( '' === $s_where ) {
                        $s_where = 'WHERE ';
                    } else {
                        $s_where .= ' AND ';
                    }
                    
                    $s_where .= '`' . $a_columns[$i] . "` LIKE '%" . esc_sql( sanitize_key( $_REQUEST['sSearch_' . $i] ) ) . "%' ";
                }
            
            }
            // Hiding internal searches
            if ( isset( $_REQUEST['hideinternal'] ) && 'on' === $_REQUEST['hideinternal'] ) {
                
                if ( !$s_where ) {
                    $s_where = " WHERE engine<>'Internal Search' ";
                } else {
                    $s_where .= " AND engine<>'Internal Search' ";
                }
            
            }
            // Show only unknown keywords
            if ( isset( $_REQUEST['showkws'] ) && 'unknowns' === sanitize_key( $_REQUEST['showkws'] ) ) {
                
                if ( !$s_where ) {
                    $s_where = " WHERE kw='#' ";
                } else {
                    $s_where .= " AND kw='#' ";
                }
            
            }
            // Show only known keywords
            if ( isset( $_REQUEST['showkws'] ) && 'knowns' === sanitize_key( $_REQUEST['showkws'] ) ) {
                
                if ( !$s_where ) {
                    $s_where = " WHERE kw<>'#' ";
                } else {
                    $s_where .= " AND kw<>'#' ";
                }
            
            }
            $squery = ' SELECT SQL_CALC_FOUND_ROWS `' . str_replace( ' , ', ' ', implode( '`, `', $a_columns ) ) . "` FROM {$wpdb->prefix}sb2_kw {$s_where} {$search_order} {$s_limit}";
            $rresult = $wpdb->get_results( $squery, ARRAY_A );
            $ifiltered_total = $wpdb->get_var( "SELECT count(*) FROM {$wpdb->prefix}sb2_kw {$s_where} " );
            $itotal = $wpdb->get_var( "SELECT count(*) FROM {$wpdb->prefix}sb2_kw;" );
            $output = array(
                'draw'            => $drawval,
                'recordsTotal'    => $itotal,
                'recordsFiltered' => $ifiltered_total,
            );
            $surl = site_url();
            $extraclasses = '';
            $row_arr = array();
            $datastring = array();
            foreach ( $rresult as $a_row ) {
                $row = array();
                $a_columns_count = count( $a_columns );
                for ( $i = 0 ;  $i < $a_columns_count ;  $i++ ) {
                    
                    if ( 'kw' === $a_columns[$i] ) {
                        $row[] = ( '0' === $a_row[$a_columns[$i]] ? '-' : $a_row[$a_columns[$i]] );
                        // keyword
                        
                        if ( '#' === $row[0] ) {
                            $row[0] = '<span class="unknown">' . __( 'Not Provided', 'seo-booster' ) . '</span>';
                            // setting unknown class
                        }
                    
                    } elseif ( 'lp' === $a_columns[$i] ) {
                        $trimmed = str_replace( $surl, '', $a_row[$a_columns[$i]] );
                        $extraclasses = '';
                        $row[] = '<a href="' . esc_url_raw( $a_row[$a_columns[$i]] ) . '"' . $extraclasses . ' target="_blank">' . $trimmed . '</a>';
                    } elseif ( 'firstvisit' === $a_columns[$i] ) {
                        $row[] = '<small>' . $a_row[$a_columns[$i]] . '</small>';
                    } elseif ( 'lastvisit' === $a_columns[$i] ) {
                        $row[] = '<small>' . $a_row[$a_columns[$i]] . '</small>';
                    } elseif ( 'engine' === $a_columns[$i] ) {
                        $engine = $a_row[$a_columns[$i]];
                        $datld = '';
                        $domain = explode( '.', $engine );
                        // create an array of the bits
                        $number = count( $domain );
                        // find out how many there are
                        $tld = $domain[$number - 1];
                        // tld is last element
                        // Check if we have a match
                        if ( isset( $engine_meta[$tld] ) ) {
                            $datld = $tld;
                        }
                        if ( !$datld ) {
                            
                            if ( isset( $domain[$number - 2] ) ) {
                                $secondld = $domain[$number - 2];
                                $datld = $secondld . '.' . $tld;
                            }
                        
                        }
                        
                        if ( isset( $engine_meta[$datld] ) ) {
                            $imgurl = trailingslashit( SEOBOOSTER_PLUGINURL ) . 'images/flags/' . $engine_meta[$datld]['flag'];
                            $imgalt = $engine_meta[$datld]['label'];
                        } else {
                            $imgurl = trailingslashit( SEOBOOSTER_PLUGINURL ) . '/images/flags/Unknown.png';
                            $imgalt = __( 'Unknown', 'seo-booster' );
                        }
                        
                        $engine_out = '<img src="' . esc_url_raw( $imgurl ) . '" class="flag" alt="' . esc_html( $imgalt ) . '">' . $engine;
                        $row[] = $engine_out;
                        // replace www. in engine name column
                    } elseif ( ' ' !== $a_columns[$i] ) {
                        $row[] = $a_row[$a_columns[$i]];
                    }
                
                }
                $row_arr[] = $row;
            }
            $output['data'] = $row_arr;
            echo  wp_json_encode( $output ) ;
            wp_die();
        }
        
        /**
         * Returns true if on an admin page
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Sunday, November 7th, 2021.
         * @access  public static
         * @return  boolean
         */
        public static function is_sb2_admin_page()
        {
            $screen = get_current_screen();
            if ( is_object( $screen ) && 'toplevel_page_sb2_dashboard' === $screen->id || 'seo-booster_page_sb2_debug' === $screen->id || 'seo-booster_page_sb2_log' === $screen->id || 'seo-booster_page_sb2_settings' === $screen->id || 'seo-booster_page_sb2_404' === $screen->id || 'seo-booster_page_sb2_crawled' === $screen->id || 'seo-booster_page_sb2_forgotten' === $screen->id || 'seo-booster_page_sb2_keywords' === $screen->id || 'seo-booster_page_sb2_audit' === $screen->id || 'seo-booster_page_sb2_backlinks' === $screen->id || 'seo-booster_page_sb2_autolink' === $screen->id || 'seo-sb2_dashboard' === $screen->id ) {
                return $screen->id;
            }
            return false;
        }
        
        /**
         * admin_enqueue_scripts.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Saturday, August 7th, 2021.
         * @version v1.0.1  Sunday, November 7th, 2021.
         * @access  public static
         * @return  void
         */
        public static function admin_enqueue_scripts()
        {
            $is_sb2_admin_page = self::is_sb2_admin_page();
            
            if ( $is_sb2_admin_page ) {
                wp_enqueue_script( 'jquery-ui-core' );
                wp_enqueue_script(
                    'googlejsapi',
                    'https://www.google.com/jsapi',
                    array(),
                    SEOBOOSTER_VERSION,
                    true
                );
                wp_enqueue_script(
                    'googlecharts',
                    'https://www.gstatic.com/charts/loader.js',
                    array(),
                    SEOBOOSTER_VERSION,
                    true
                );
                wp_enqueue_script(
                    'lazyload',
                    plugins_url( '/js/jquery.lazyload.min.js', __FILE__ ),
                    array( 'jquery' ),
                    filemtime( plugin_dir_path( __FILE__ ) . 'js/jquery.lazyload.min.js' ),
                    true
                );
                // Indlæser specifikke data om bruger til Helpscout Beacon.
                wp_register_script(
                    'seoboosterjs',
                    plugins_url( '/js/min/seo-booster-min.js', __FILE__ ),
                    array( 'jquery' ),
                    filemtime( plugin_dir_path( __FILE__ ) . 'js/min/seo-booster-min.js' ),
                    true
                );
                $current_user = wp_get_current_user();
                $usermail = $current_user->user_email;
                $username = $current_user->display_name;
                if ( function_exists( 'seobooster_fs' ) ) {
                    
                    if ( seobooster_fs()->is_registered() ) {
                        $get_user = seobooster_fs()->get_user();
                        $usermail = $get_user->email;
                        $username = $get_user->first . ' ' . $get_user->last;
                    }
                
                }
                $sbdata_array = array(
                    'ajaxurl'       => admin_url( 'admin-ajax.php' ),
                    'user_name'     => $username,
                    'email'         => $usermail,
                    'website'       => esc_url_raw( site_url() ),
                    'enablecontact' => false,
                    'nonce'         => wp_create_nonce( 'seobooster-nonce' ),
                );
                $screen = get_current_screen();
                // Do not load this on admin dashboard
                
                if ( 'dashboard' !== $screen->id ) {
                    wp_localize_script( 'seoboosterjs', 'sbbeacondata', $sbdata_array );
                    wp_enqueue_script( 'seoboosterjs' );
                    wp_enqueue_style(
                        'seoboostercss',
                        plugins_url( '/css/seo-booster-min.css', __FILE__ ),
                        array(),
                        SEOBOOSTER_VERSION
                    );
                }
            
            }
            
            $screen = get_current_screen();
            // only load datatables on keywords page
            
            if ( is_object( $screen ) && 'seo-booster_page_sb2_keywords' === $screen->id ) {
                wp_enqueue_script(
                    'dataTables',
                    plugins_url( '/js/datatables.min.js', __FILE__ ),
                    array(),
                    SEOBOOSTER_VERSION,
                    true
                );
                wp_enqueue_style(
                    'dataTables',
                    plugins_url( '/js/jquery.dataTables.css', __FILE__ ),
                    array(),
                    SEOBOOSTER_VERSION
                );
                wp_register_script(
                    'dataTable',
                    plugins_url( '/js/datatable.js', __FILE__ ),
                    array(),
                    SEOBOOSTER_VERSION,
                    true
                );
                wp_localize_script( 'dataTable', 'seobooster_datatable', array(
                    'nonce' => wp_create_nonce( 'seobooster-datatable-nonce' ),
                ) );
                wp_enqueue_script( 'dataTable' );
            }
            
            // Check to see if user has already dismissed the pointer tour
            $dismissed = array_filter( explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) ) );
            $do_tour = !in_array( 'sbp_tour_pointer', $dismissed, true );
            // If not, we are good to continue - We check if the plugin has been registered or user wants to be anon
            global  $seobooster_fs ;
            
            if ( $do_tour && ($seobooster_fs->is_registered() || $seobooster_fs->is_anonymous() || $seobooster_fs->is_pending_activation()) ) {
                wp_enqueue_style( 'wp-pointer' );
                wp_enqueue_script( 'wp-pointer' );
                add_action( 'admin_print_footer_scripts', array( __CLASS__, 'admin_print_footer_scripts' ) );
                add_action( 'admin_head', array( __CLASS__, 'admin_head' ) );
            }
        
        }
        
        /**
         * Used to add spacing between the two buttons in the pointer overlay window.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function admin_head()
        {
            ?>
		<style type="text/css" media="screen">
			#pointer-primary {
				margin: 0 5px 0 0;
			}
		</style>
			<?php 
        }
        
        /**
         * This function is used to reload the admin page.
         * $page = the admin page we are passing (index.php or options-general.php)
         * $tab = the NEXT pointer array key we want to display
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @param   mixed   $page
         * @param   mixed   $tab
         * @return  mixed
         */
        public static function get_admin_url( $page, $tab )
        {
            $url = admin_url();
            $url .= $page;
            $url = add_query_arg( 'tab', $tab, $url );
            return $url;
        }
        
        /**
         * Define footer scripts
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Monday, October 11th, 2021.
         * @access  public static
         * @return  void
         */
        public static function admin_print_footer_scripts()
        {
            // Define global variables
            //*****************************************************************************************************
            // This is our array of individual pointers.
            // -- The array key should be unique.  It is what will be used to 'advance' to the next pointer.
            // -- The 'id' should correspond to an html element id on the page.
            // -- The 'content' will be displayed inside the pointer overlay window.
            // -- The 'button2' is the text to show for the 'action' button in the pointer overlay window.
            // -- The 'function' is the method used to reload the window (or relocate to a new window).
            //    This also creates a query variable to add to the end of the url.
            //    The query variable is used to determine which pointer to display.
            //*****************************************************************************************************
            $tour = array(
                'dashboard'         => array(
                'id'       => '#welcome-panel',
                'content'  => '<h3>' . __( 'The Dashboard Page', 'seo-booster' ) . ' (1/12) </h3><p>' . __( 'SEO Booster monitors traffic from hundreds of keyword sources, detects visitors from backlinks, 404 errors and much more.', 'seo-booster' ) . '</p><p><strong>' . __( 'The Dashboard page gives you a quick overview.', 'seo-booster' ) . '</strong></p>',
                'button2'  => __( 'Next', 'seo-booster' ),
                'function' => 'document.location="' . self::get_admin_url( 'admin.php?page=sb2_keywords', 'keywords' ) . '";',
            ),
                'keywords'          => array(
                'id'       => '#datatable-target',
                'content'  => '<h3>' . __( 'Keywords Module', 'seo-booster' ) . ' (2/12) </h3><p><strong>' . __( 'What your visitors searches for on search engines to find your website', 'seo-booster' ) . '</strong></p><p>' . __( 'Keyword traffic from 400+ sources are monitored and keywords will show up here.', 'seo-booster' ) . '</p><p>' . __( 'Google and Yandex have stopped sharing information about what people search for, but there are hundreds of other search engines who still shares.', 'seo-booster' ) . '</p>',
                'button2'  => __( 'Next', 'seo-booster' ),
                'function' => 'document.location="' . self::get_admin_url( 'admin.php?page=sb2_keywords', 'keyword_filtering' ) . '"',
            ),
                'keyword_filtering' => array(
                'id'       => '#filtering',
                'content'  => '<h3>' . __( 'Filter Keywords', 'seo-booster' ) . ' (3/12) </h3><p><strong>' . __( 'All pages that receive search engine traffic are listed. Also when the keyword was not provided.', 'seo-booster' ) . '</strong></p><p>' . __( 'Use the filter settings to show only when keywords were provided.', 'seo-booster' ) . '</p><p>' . __( 'You can also filter out internal searches.', 'seo-booster' ) . '</p>',
                'button2'  => __( 'Next', 'seo-booster' ),
                'function' => 'document.location="' . self::get_admin_url( 'admin.php?page=sb2_backlinks', 'backlinks' ) . '"',
            ),
                'backlinks'         => array(
                'id'       => '#backlinkstable-target',
                'content'  => '<h3>' . __( 'Who links to you?', 'seo-booster' ) . ' (4/12) </h3><p>' . __( 'Every new visitor that comes from another website is monitored and listed here.', 'seo-booster' ) . '</p><p><strong>' . __( 'Note - Pro version also checks each backlink and removes fake links.', 'seo-booster' ) . '</strong></p>',
                'button2'  => __( 'Next', 'seo-booster' ),
                'function' => 'document.location="' . self::get_admin_url( 'admin.php?page=sb2_forgotten', 'forgotten' ) . '"',
            ),
                'forgotten'         => array(
                'id'       => '#wp_pointer-target',
                'content'  => '<h3>' . __( 'Lost Traffic', 'seo-booster' ) . ' (5/12) </h3><p>' . __( 'Discover pages that used to receive traffic from Search Engines, but no longer gets any traffic.', 'seo-booster' ) . '</p><p>' . __( 'Do not worry if you see nothing listed here. That is good.', 'seo-booster' ) . '</p>',
                'button2'  => __( 'Next', 'seo-booster' ),
                'function' => 'document.location="' . self::get_admin_url( 'admin.php?page=sb2_404', '404' ) . '"',
            ),
                '404'               => array(
                'id'       => '#404table-target',
                'content'  => '<h3>' . __( '404 Errors!', 'seo-booster' ) . ' (6/12) </h3><p>' . __( '404 Errors refers to wrong links or missing content on your website.', 'seo-booster' ) . '</p><p>' . __( 'You should fix any as soon as possible. Use this list to get started.', 'seo-booster' ) . '</p>',
                'button2'  => __( 'Next', 'seo-booster' ),
                'function' => 'document.location="' . self::get_admin_url( 'admin.php?page=sb2_404&step2', '404reset' ) . '"',
            ),
                '404reset'          => array(
                'id'       => '#reset404s',
                'content'  => '<h3>' . __( 'Reset 404 errors', 'seo-booster' ) . ' (7/12) </h3><p>' . __( 'Sometimes the list fills up very quickly and you want to get a clean overview.', 'seo-booster' ) . '</p><p>' . __( 'Use the Reset button to clean the list and start over.', 'seo-booster' ) . '</p>',
                'button2'  => __( 'Next', 'seo-booster' ),
                'function' => 'document.location="' . self::get_admin_url( 'admin.php?page=sb2_log', 'log' ) . '"',
            ),
                'log'               => array(
                'id'       => '#log-pointer-target',
                'content'  => '<h3>' . __( 'Whats going on?', 'seo-booster' ) . ' (8/12) </h3><p>' . __( 'Per default only most important events is logged.', 'seo-booster' ) . '</p><p>' . __( 'If you want more details, go to the settings page and turn on "Debug Logging".', 'seo-booster' ) . '</p>',
                'button2'  => __( 'Next', 'seo-booster' ),
                'function' => 'document.location="' . self::get_admin_url( 'admin.php?page=sb2_settings', 'filterkeywords' ) . '"',
            ),
                'filterkeywords'    => array(
                'id'       => '#seobooster_ignorelist',
                'content'  => '<h3>' . __( 'Filter out keywords', 'seo-booster' ) . ' (9/12) </h3><p>' . __( 'Sometimes you get a keyword in your system you do not want to use on your website, filter out any you do not want here.', 'seo-booster' ) . '</p>',
                'button2'  => __( 'Next', 'seo-booster' ),
                'function' => 'document.location="' . self::get_admin_url( 'admin.php?page=sb2_settings&step3', 'ignoreinternal' ) . '"',
            ),
                'ignoreinternal'    => array(
                'id'       => '#seobooster_ignore_internal_searches',
                'content'  => '<h3>' . __( 'Ignore internal searches', 'seo-booster' ) . ' (10/12) </h3><p>' . __( 'Visitors clicking on search results on your own website are also tracked. You can turn this off here if you wish only keywords from external visitors.', 'seo-booster' ) . '</p>',
                'button2'  => __( 'Next', 'seo-booster' ),
                'function' => 'document.location="' . self::get_admin_url( 'admin.php?page=sb2_settings&step4', 'autotag' ) . '"',
            ),
                'autotag'           => array(
                'id'       => '#seobooster_dynamic_tagging',
                'content'  => '<h3>' . __( 'Automatic Tagging', 'seo-booster' ) . ' (11/12) </h3><p>' . __( 'The keywords people use to find your content can be used as Tags for your posts and pages.', 'seo-booster' ) . '</p>',
                'button2'  => __( 'Next', 'seo-booster' ),
                'function' => 'document.location="' . self::get_admin_url( 'admin.php?page=sb2_settings&step5', 'emailreport' ) . '"',
            ),
                'emailreport'       => array(
                'id'       => '#seobooster_weekly_email',
                'content'  => '<h3>' . __( 'Weekly Email Reports', 'seo-booster' ) . ' (12/12) </h3><p>' . __( 'Enable this and enter your e-mail to get a weekly update from your website about new keywords and incoming links.', 'seo-booster' ) . '</p>',
                'button2'  => __( 'Next', 'seo-booster' ),
                'function' => 'document.location="' . self::get_admin_url( 'admin.php?page=sb2_dashboard', 'lastone' ) . '"',
            ),
                'lastone'           => array(
                'id'      => '#sbpmarketingbox',
                'content' => '<h3>' . __( 'End of the Guided Tour', 'seo-booster' ) . '</h3><p>' . __( 'Thank you for finishing the tour. This was just a quick tour of the most important features - there are more cool features for you to discover.', 'seo-booster' ) . '</p><p><strong>' . __( 'Need Help?', 'seo-booster' ) . '</strong></p><p><a href="https://support.cleverplugins.com/" target="_blank">' . __( 'Knowledge Base', 'seo-booster' ) . '</br>
				<a href="https://cleverplugins.com/support/" target="_blank">' . __( 'Contact Support', 'seo-booster' ) . '</a></p><p>' . __( 'Please leave a review if you like the plugin.', 'seo-booster' ) . '</p>',
            ),
            );
            /*
            
            
            CRAWLED CONTENT
            - From version 3, visits by search engine crawlers are also monitored. This way you can see which pages are visited by robots and how often.
            
            // todo - scroll in to view?
            */
            // Determine which tab is set in the query variable
            $tab = ( isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : '' );
            // Define other variables
            $function = '';
            $button2 = '';
            $options = array();
            $show_pointer = false;
            // *******************************************************************************************************
            // This will be the first pointer shown to the user.
            // If no query variable is set in the url.. then the 'tab' cannot be determined... and we start with this pointer.
            // *******************************************************************************************************
            
            if ( !array_key_exists( $tab, $tour ) ) {
                $show_pointer = true;
                $file_error = true;
                $id = '#toplevel_page_sb2_dashboard';
                // Define ID used on page html element where we want to display pointer
                $content = '<h3>SEO Booster v.' . SEOBOOSTER_VERSION . '</h3>';
                $content .= '<p><strong>' . __( 'Thank you for installing SEO Booster :-)', 'seo-booster' ) . '</strong></p>';
                $content .= '<p>' . __( 'This Quick Guided Tour will help you learn the interface in a few minutes.', 'seo-booster' ) . '</p>';
                $content .= '<p>' . __( 'Click the <em>Begin Tour</em> button to get started.', 'seo-booster' ) . '</p>';
                $content .= '<p><small>' . __( 'If you want to watch it later use the <em>Close</em> button and start the tour from the bottom of the Settings page.', 'seo-booster' ) . '</small></p>';
                $options = array(
                    'content'  => $content,
                    'position' => array(
                    'edge'  => 'bottom',
                    'align' => 'left',
                ),
                );
                $button2 = __( 'Begin Tour', 'seo-booster' );
                $function = 'document.location="' . self::get_admin_url( 'admin.php?page=sb2_dashboard', 'dashboard' ) . '";';
            } else {
                // Else if the 'tab' is set in the query variable.. then we can determine which pointer to display
                
                if ( '' !== $tab && in_array( $tab, array_keys( $tour ), true ) ) {
                    $show_pointer = true;
                    if ( isset( $tour[$tab]['id'] ) ) {
                        $id = $tour[$tab]['id'];
                    }
                    $options = array(
                        'content'  => $tour[$tab]['content'],
                        'position' => array(
                        'edge'  => 'top',
                        'align' => 'left',
                    ),
                    );
                    $button2 = false;
                    $function = '';
                    if ( isset( $tour[$tab]['button2'] ) ) {
                        $button2 = $tour[$tab]['button2'];
                    }
                    if ( isset( $tour[$tab]['function'] ) ) {
                        $function = $tour[$tab]['function'];
                    }
                }
            
            }
            
            // If we are showing a pointer... let's load the jQuery.
            if ( $show_pointer ) {
                self::make_pointer_script(
                    $id,
                    $options,
                    __( 'Close', 'seo-booster' ),
                    $button2,
                    $function
                );
            }
        }
        
        /**
         * Print footer scripts
         *
         * @var     public  stati
         */
        public static function make_pointer_script(
            $id,
            $options,
            $button1,
            $button2 = false,
            $function = ''
        )
        {
            $wp_allowed_protocols = wp_allowed_protocols();
            ?>
			<script type="text/javascript">
				(function ($) {
						// Define pointer options
						var wp_pointers_tour_opts = <?php 
            echo  wp_json_encode( $options ) ;
            ?>, setup;

						wp_pointers_tour_opts = $.extend (wp_pointers_tour_opts, {

			// Add 'Close' button
			buttons: function (event, t) {

				button = jQuery ('<a id="pointer-close" class="button-secondary">' + '<?php 
            echo  wp_kses( $button1, $wp_allowed_protocols ) ;
            ?>' + '</a>');
				button.bind ('click.pointer', function () {
					t.element.pointer ('close');
				});
				return button;
			},
			close: function () {


				$.post (ajaxurl, {
					pointer: 'sbp_tour_pointer',
					action: 'dismiss-wp-pointer'
				});
			}
		});

// This is used for our "button2" value above (advances the pointers)
setup = function () {

	jQuery('<?php 
            echo  esc_attr( $id ) ;
            ?>').pointer(wp_pointers_tour_opts).pointer('open');

			<?php 
            
            if ( $button2 ) {
                ?>

		jQuery ('#pointer-close').after ('<a id="pointer-primary" class="button-primary">' + '<?php 
                echo  wp_kses( $button2, $wp_allowed_protocols ) ;
                ?>' + '</a>');
		jQuery ('#pointer-primary').click (function () {
				<?php 
                echo  $function ;
                ?>
					// Execute button2 function
			});
		jQuery ('#pointer-close').click (function () {
$.post (ajaxurl, {
	pointer: 'sbp_tour_pointer',
	action: 'dismiss-wp-pointer'
});
})
				<?php 
            }
            
            ?>
};

if (wp_pointers_tour_opts.position && wp_pointers_tour_opts.position.defer_loading) {

	$(window).bind('load.wp-pointers', setup);
}
else {
	setup ();
}
}) (jQuery);
</script>
			<?php 
        }
        
        /**
         * verifyurl.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Saturday, August 7th, 2021.
         * @access  public static
         * @param   mixed   $inurl
         * @return  void
         */
        public static function verifyurl( $inurl )
        {
            
            if ( strtolower( esc_url_raw( $inurl ) ) === strtolower( $inurl ) ) {
                return $inurl;
            } else {
                return false;
            }
        
        }
        
        /**
         * src_load_widgets.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Saturday, August 7th, 2021.
         * @access  public static
         * @return  void
         */
        public static function src_load_widgets()
        {
            register_widget( 'seobooster_keywords_widget' );
            register_widget( 'seobooster_dyn_widget' );
        }
        
        /**
         * list_keywords() - Lists keywords for current url, filters out old keywords over 30 days
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Saturday, August 7th, 2021.
         * @access  public static
         * @param   integer $limit      Default: 10
         * @param   string  $currurl    Default: ''
         * @return  void
         */
        public static function list_keywords( $limit = 10, $currurl = '' )
        {
            
            if ( !$currurl ) {
                $currurl = self::seobooster_currenturl();
                $currurl = strtok( $currurl, '?' );
                // Strips parameters
            }
            
            if ( !$currurl ) {
                return;
            }
            global  $post, $wpdb ;
            $sqlignore = self::seobooster_generateignorelist();
            $query = $wpdb->prepare( "SELECT DISTINCT(kw) FROM {$wpdb->prefix}sb2_kw WHERE {$sqlignore} lp like '%{$currurl}' AND ig='0' AND kw<>'#'  ORDER BY visits DESC LIMIT %d;", $limit );
            $kws = $wpdb->get_results( $query, ARRAY_A );
            $kwlist = '';
            
            if ( $kws ) {
                $count = count( $kws );
                $step = 0;
                foreach ( $kws as $kw ) {
                    ++$step;
                    $kwlist .= stripslashes( trim( $kw['kw'] ) );
                    if ( $step > 0 && $step < $count ) {
                        $kwlist .= ', ';
                    }
                }
            }
            
            
            if ( $kwlist ) {
                return $kwlist;
            } else {
                // no $kwlist, return false
                return false;
            }
        
        }
        
        /**
         * on_init.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Saturday, August 7th, 2021.
         * @access  public static
         * @return  void
         */
        public static function on_init()
        {
            // Registers data for Gutenberg - https://wordpress.org/gutenberg/handbook/block-api/attributes/#meta
            register_meta( 'post', 'seo_booster_metabox', array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
            ) );
            $dyntag = get_option( 'seobooster_dynamic_tagging' );
            $dynamic_tag_assigncpts = get_option( 'seobooster_dynamic_tag_assigncpts' );
            
            if ( 'on' === $dyntag && 'on' === $dynamic_tag_assigncpts ) {
                $dyntagtaxonomy = get_option( 'seobooster_dynamic_tag_taxonomy' );
                $cpts = get_post_types( array(
                    'public'             => true,
                    'publicly_queryable' => true,
                ), 'names', 'and' );
                $cpts = array_merge( $cpts, array( 'page' ) );
                // add the cpt 'page' to the list.
                if ( $cpts ) {
                    foreach ( $cpts as $cpt ) {
                        register_taxonomy_for_object_type( $dyntagtaxonomy, $cpt );
                    }
                }
            }
            
            $review = get_option( 'sbp_review_notice' );
            
            if ( !$review ) {
                $review = array(
                    'time'      => time() + WEEK_IN_SECONDS * 2,
                    'dismissed' => false,
                );
                update_option( 'sbp_review_notice', $review, 'no' );
            }
            
            // TESTING AND FIXING DATABASE TABLES
            
            if ( isset( $_POST['page'] ) && 'sb2_dashboard' === sanitize_text_field( $_POST['page'] ) ) {
                $nonce = sanitize_text_field( $_REQUEST['_wpnonce'] );
                
                if ( !wp_verify_nonce( $nonce, 'fixdbtables' ) ) {
                    die( 'Security check' );
                } else {
                    self::create_database_tables();
                    ?>

			<div class="notice notice-success">
				<h3>
						<?php 
                    esc_html_e( 'Database tables updated', 'seo-booster' );
                    ?>
		</h3>

			</div>
					<?php 
                }
            
            }
        
        }
        
        /**
         * seobooster_activate.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Saturday, August 7th, 2021.
         * @access  public static
         * @param   mixed   $network_wide
         * @return  void
         */
        public static function seobooster_activate( $network_wide )
        {
            global  $wpdb ;
            if ( !wp_next_scheduled( 'sbp_email_update' ) ) {
                wp_schedule_event( time(), 'daily', 'sbp_email_update' );
            }
            if ( !wp_next_scheduled( 'sbp_hourlymaintenance' ) ) {
                wp_schedule_event( time(), 'hourly', 'sbp_hourlymaintenance' );
            }
            if ( !wp_next_scheduled( 'sbp_email_update' ) ) {
                wp_schedule_event( time(), 'weekly', 'sbp_email_update' );
            }
            // Multisite
            
            if ( is_multisite() ) {
                $blogs = get_sites();
                foreach ( $blogs as $keys => $blog ) {
                    // Cast $blog as an array instead of WP_Site object
                    if ( is_object( $blog ) ) {
                        $blog = (array) $blog;
                    }
                    $blog_id = $blog['blog_id'];
                    switch_to_blog( $blog_id );
                    self::create_database_tables();
                    restore_current_blog();
                    // translators:
                    self::log( sprintf( __( 'Created database tables on blog id <code>%s</code>.', 'seo-booster' ), $blog_id ) );
                }
            } else {
                self::create_database_tables();
            }
        
        }
        
        /**
         * seobooster_deactivate.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Saturday, August 7th, 2021.
         * @access  public static
         * @param   mixed   $network_wide
         * @return  void
         */
        public static function seobooster_deactivate( $network_wide )
        {
            global  $wpdb ;
            $seobooster_delete_deactivate = get_option( 'seobooster_delete_deactivate' );
            
            if ( $seobooster_delete_deactivate ) {
                $table_array = array(
                    $wpdb->prefix . 'sb2_autolink',
                    $wpdb->prefix . 'sb2_404',
                    $wpdb->prefix . 'sb2_bl',
                    $wpdb->prefix . 'sb2_kw',
                    $wpdb->prefix . 'sb2_crawl',
                    $wpdb->prefix . 'sb2_kwdt',
                    $wpdb->prefix . 'sb2_log',
                    $wpdb->prefix . 'sb2_urls',
                    $wpdb->prefix . 'sb2_urls_meta'
                );
                // Multisite
                
                if ( is_multisite() ) {
                    $blogs = get_sites();
                    foreach ( $blogs as $keys => $blog ) {
                        // Cast $blog as an array instead of WP_Site object
                        if ( is_object( $blog ) ) {
                            $blog = (array) $blog;
                        }
                        $blog_id = $blog['blog_id'];
                        switch_to_blog( $blog_id );
                        foreach ( $table_array as $tablename ) {
                            $wpdb->query( "DROP TABLE IF EXISTS {$tablename}" );
                        }
                        restore_current_blog();
                    }
                } else {
                    // This is not multisite
                    foreach ( $table_array as $tablename ) {
                        $wpdb->query( "DROP TABLE IF EXISTS {$tablename}" );
                    }
                }
                
                delete_option( 'seobooster_delete_deactivate' );
            }
        
        }
        
        /**
         * create_database_tables.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Saturday, August 7th, 2021.
         * @access  public static
         * @return  void
         */
        public static function create_database_tables()
        {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            global  $wpdb ;
            $wpdb_collate = $wpdb->collate;
            $table_name = $wpdb->prefix . 'sb2_autolink';
            $sql = "CREATE TABLE {$table_name} (id bigint(20) NOT NULL AUTO_INCREMENT,keyword varchar(255),url varchar(255),disable int(1) DEFAULT '0',nflw int(1) DEFAULT '0',lastseen longtext,PRIMARY KEY  (id),KEY keyword (keyword),KEY url (url)) COLLATE {$wpdb_collate}";
            dbDelta( $sql );
            $table_name = $wpdb->prefix . 'sb2_bl';
            $sql = "CREATE TABLE {$table_name} (id bigint(20) NOT NULL AUTO_INCREMENT,ig tinyint(1) NOT NULL,domain text NOT NULL,ref varchar(1024) NOT NULL,httpstatus varchar(3) NOT NULL,errorcount smallint(1) NOT NULL DEFAULT '0',verified smallint(1) NOT NULL DEFAULT '0',lp varchar(1024) NOT NULL,anchor text NOT NULL,href text NOT NULL,img tinyint(4) NOT NULL,visits int(11) NOT NULL,firstvisit timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,lastvisit timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',lastcheck timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',nflw tinyint(4) NOT NULL,PRIMARY KEY  (id),KEY ref (ref(255)),KEY ig (ig) ) COLLATE {$wpdb_collate}";
            dbDelta( $sql );
            $table_name = $wpdb->prefix . 'sb2_kwdt';
            $sql = "CREATE TABLE {$table_name} ( id int(11) NOT NULL AUTO_INCREMENT, refid int(11) NOT NULL, daday date NOT NULL, visits int(11) NOT NULL DEFAULT '1', avgpos int(11) NOT NULL DEFAULT '0', cdhits int(11) NOT NULL DEFAULT '0', PRIMARY KEY  (id), KEY refid (refid), KEY daday (daday) ) COLLATE {$wpdb_collate}";
            dbDelta( $sql );
            $table_name = $wpdb->prefix . 'sb2_crawl';
            $sql = "CREATE TABLE {$table_name} ( id bigint(20) NOT NULL AUTO_INCREMENT, url varchar(1024) NOT NULL, lastcrawl timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, visits int(11) NOT NULL DEFAULT 1, engine varchar(50) NOT NULL, PRIMARY KEY  (id), KEY url (url), KEY engine (engine) ) COLLATE {$wpdb_collate}";
            dbDelta( $sql );
            $table_name = $wpdb->prefix . 'sb2_urls';
            $sql = "CREATE TABLE {$table_name} ( ID bigint(20) NOT NULL AUTO_INCREMENT, urlkey varchar(32) NOT NULL, url varchar(1024) NOT NULL, absurl varchar(1024) NOT NULL, http_code varchar(3) NOT NULL, refid int(11) DEFAULT NULL, code varchar(3) NOT NULL, scraped timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY  (ID), KEY urlkey (urlkey) ) COLLATE {$wpdb_collate}";
            dbDelta( $sql );
            $table_name = $wpdb->prefix . 'sb2_urls_meta';
            $sql = "CREATE TABLE {$table_name} ( meta_id bigint(20) NOT NULL AUTO_INCREMENT, refid int(11) NOT NULL, name varchar(255) NOT NULL, value longtext NOT NULL, PRIMARY KEY  (meta_id), KEY refid (refid), KEY name (name) ) COLLATE {$wpdb_collate}";
            dbDelta( $sql );
            $table_name = $wpdb->prefix . 'sb2_404';
            $sql = "CREATE TABLE {$table_name} ( id bigint(20) NOT NULL AUTO_INCREMENT, lp varchar(500) NOT NULL, firstseen timestamp NOT NULL DEFAULT '0000-00-00 00:00:00', lastseen timestamp NOT NULL DEFAULT '0000-00-00 00:00:00', visits int(11) NOT NULL, referer text NOT NULL, PRIMARY KEY  (id) ) COLLATE {$wpdb_collate}";
            dbDelta( $sql );
            $table_name = $wpdb->prefix . 'sb2_log';
            $sql = "CREATE TABLE {$table_name} ( ID bigint(20) NOT NULL primary key AUTO_INCREMENT, logtime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, prio tinyint(1) NOT NULL, log varchar(2048) NOT NULL, KEY ID (ID) ) COLLATE {$wpdb_collate}";
            dbDelta( $sql );
            $table_name = $wpdb->prefix . 'sb2_kw';
            $sql = "CREATE TABLE {$table_name} ( id bigint(20) NOT NULL AUTO_INCREMENT, ig tinyint(4) NOT NULL, kw varchar(255) NOT NULL, term_id bigint(20) unsigned NOT NULL DEFAULT '0', lp varchar(500) NOT NULL, googletld varchar(30) NOT NULL, visits int(11) NOT NULL, firstvisit timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, lastvisit timestamp NOT NULL DEFAULT '0000-00-00 00:00:00', `engine` text NOT NULL, PRIMARY KEY  (id), KEY lp (lp), KEY id (id), KEY kw (kw) ) COLLATE {$wpdb_collate}";
            dbDelta( $sql );
            self::log( __( 'Updated database tables', 'seo-booster' ) );
            update_option( 'SEOBOOSTER_INSTALLED_DB_VERSION', SEOBOOSTER_DB_VERSION );
            // Storing DB version for later use
        }
        
        /**
         * filter_cron_schedules.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Saturday, August 7th, 2021.
         * @access  public static
         * @param   mixed   $param
         * @return  mixed
         */
        public static function filter_cron_schedules( $param )
        {
            $newschedules = array(
                '1min' => array(
                'interval' => 60,
                'display'  => 'Every minute',
            ),
                '5min' => array(
                'interval' => 300,
                'display'  => 'Every 5 minutes',
            ),
            );
            return array_merge( $param, $newschedules );
        }
        
        /**
         * admin_init.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function admin_init()
        {
            register_setting( 'seobooster', 'seobooster_replace_kw_limit' );
            register_setting( 'seobooster', 'seobooster_replace_kw_multiple' );
            register_setting( 'seobooster', 'seobooster_enable_pagespeed' );
            register_setting( 'seobooster', 'seobooster_pagespeed_api_key' );
            register_setting( 'seobooster', 'seobooster_pagespeed_url_limit' );
            register_setting( 'seobooster', 'seobooster_showsearch_queries' );
            register_setting( 'seobooster', 'seobooster_dynamic_tagging' );
            register_setting( 'seobooster', 'seobooster_dynamic_tagging_related' );
            register_setting( 'seobooster', 'seobooster_dynamic_tag_taxonomy' );
            register_setting( 'seobooster', 'seobooster_dynamic_tag_assigncpts' );
            register_setting( 'seobooster', 'seobooster_dynamic_tag_maximum' );
            register_setting( 'seobooster', 'seobooster_dynamic_tag_minlength' );
            register_setting( 'seobooster', 'seobooster_dynamic_tag_maxlength' );
            register_setting( 'seobooster', 'seobooster_weekly_email' );
            register_setting( 'seobooster', 'seobooster_weekly_email_recipient' );
            register_setting( 'seobooster', 'seobooster_fof_monitoring' );
            register_setting( 'seobooster', 'seobooster_ignorelist' );
            register_setting( 'seobooster', 'seobooster_debug_logging' );
            register_setting( 'seobooster', 'seobooster_title_boost' );
            register_setting( 'seobooster', 'seobooster_ignore_internal_searches' );
            register_setting( 'seobooster', 'seobooster_backlinks_ignore' );
            register_setting( 'seobooster', 'seobooster_fof_ignore' );
            register_setting( 'seobooster', 'seobooster_replace_cat_desc' );
            register_setting( 'seobooster', 'seobooster_replace_tags' );
            register_setting( 'seobooster', 'seobooster_wpmofo' );
            register_setting( 'seobooster', 'seobooster_woocommerce' );
        }
        
        /**
         * add_pages() -
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function add_pages()
        {
            add_menu_page(
                __( 'SEO Booster', 'seo-booster' ) . ' ' . __( 'General Settings', 'seo-booster' ),
                __( 'SEO Booster', 'seo-booster' ),
                'manage_options',
                'sb2_dashboard',
                array( __CLASS__, 'add_seobooster2_main' ),
                self::get_icon_svg()
            );
            add_submenu_page(
                'sb2_dashboard',
                __( 'Incoming Keywords', 'seo-booster' ),
                __( 'Incoming Keywords', 'seo-booster' ),
                'manage_options',
                'sb2_keywords',
                array( __CLASS__, 'add_seobooster2_kwpage' )
            );
            add_submenu_page(
                'sb2_dashboard',
                __( 'Autolink', 'seo-booster' ),
                __( 'Autolink', 'seo-booster' ),
                'manage_options',
                'sb2_autolink',
                array( __CLASS__, 'add_seobooster2_autolink' )
            );
            add_submenu_page(
                'sb2_dashboard',
                __( 'Backlinks', 'seo-booster' ),
                __( 'Backlinks', 'seo-booster' ),
                'manage_options',
                'sb2_backlinks',
                array( __CLASS__, 'add_seobooster2_blpage' )
            );
            add_submenu_page(
                'sb2_dashboard',
                __( 'Lost Traffic', 'seo-booster' ),
                __( 'Lost Traffic', 'seo-booster' ),
                'manage_options',
                'sb2_forgotten',
                array( __CLASS__, 'add_seobooster2_forgottenpage' )
            );
            add_submenu_page(
                'sb2_dashboard',
                __( '404s', 'seo-booster' ),
                __( '404 Errors', 'seo-booster' ),
                'manage_options',
                'sb2_404',
                array( __CLASS__, 'add_seobooster2_404page' )
            );
            add_submenu_page(
                'sb2_dashboard',
                __( 'Log', 'seo-booster' ),
                __( 'Log', 'seo-booster' ),
                'manage_options',
                'sb2_log',
                array( __CLASS__, 'add_seobooster2_logpage' )
            );
            add_submenu_page(
                'sb2_dashboard',
                __( 'Settings', 'seo-booster' ),
                __( 'Settings', 'seo-booster' ),
                'manage_options',
                'sb2_settings',
                array( __CLASS__, 'add_seobooster2_settings' )
            );
            global  $wp_version ;
        }
        
        /**
         * add_seobooster2_main.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function add_seobooster2_main()
        {
            include SEOBOOSTER_PLUGINPATH . 'seo-booster-seobooster2.php';
        }
        
        /**
         * add_seobooster2_settings.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function add_seobooster2_settings()
        {
            include SEOBOOSTER_PLUGINPATH . 'seo-booster-settings.php';
        }
        
        /**
         * add_seobooster2_kwpage.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function add_seobooster2_kwpage()
        {
            include SEOBOOSTER_PLUGINPATH . 'seo-booster-keywords.php';
        }
        
        /**
         * add_seobooster2_blpage.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function add_seobooster2_blpage()
        {
            include SEOBOOSTER_PLUGINPATH . 'seo-booster-backlinks.php';
        }
        
        /**
         * add_seobooster2_autolink.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function add_seobooster2_autolink()
        {
            include SEOBOOSTER_PLUGINPATH . 'seo-booster-autolink.php';
        }
        
        /**
         * add_seobooster2_forgottenpage.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function add_seobooster2_forgottenpage()
        {
            include SEOBOOSTER_PLUGINPATH . 'seo-booster-forgotten.php';
        }
        
        /**
         * add_seobooster2_404page.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function add_seobooster2_404page()
        {
            include SEOBOOSTER_PLUGINPATH . 'seo-booster-404s.php';
        }
        
        /**
         * add_seobooster2_logpage.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function add_seobooster2_logpage()
        {
            include SEOBOOSTER_PLUGINPATH . 'seo-booster-log.php';
        }
        
        /**
         * Logs events to database
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @param   mixed   $text
         * @param   integer $prio   priority, default 0. Values: 0: Normal, 2: Error, 3: Warning, 5: Info 10: Success
         * @return  void
         */
        public static function log( $text, $prio = 0 )
        {
            if ( 0 === $prio && 'on' !== get_option( 'seobooster_debug_logging' ) ) {
                // Unless full debug logging is turned on, do not debug events with 0 prio
                return;
            }
            global  $wpdb ;
            $table_name_log = $wpdb->prefix . 'sb2_log';
            $wpdb->insert( $table_name_log, array(
                'logtime' => current_time( 'mysql' ),
                'prio'    => $prio,
                'log'     => $text,
            ), array( '%s', '%d', '%s' ) );
        }
        
        /**
         * remove_http() - Function strips http:// or https://
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @param   string  $url    Default: ''
         * @return  mixed
         */
        public static function remove_http( $url = '' )
        {
            if ( 'http://' === $url || 'https://' === $url ) {
                return $url;
            }
            $matches = substr( $url, 0, 7 );
            
            if ( 'http://' === $matches ) {
                $url = substr( $url, 7 );
            } else {
                $matches = substr( $url, 0, 8 );
                if ( 'https://' === $matches ) {
                    $url = substr( $url, 8 );
                }
            }
            
            return $url;
        }
        
        /**
         * timerstart.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @param   mixed   $watchname
         * @return  void
         */
        public static function timerstart( $watchname )
        {
            set_transient( 'sb2_' . $watchname, microtime( true ), 60 * 60 * 1 );
        }
        
        /**
         * timerstop.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @param   mixed   $watchname
         * @param   integer $digits     Default: 5
         * @return  mixed
         */
        public static function timerstop( $watchname, $digits = 5 )
        {
            $return = round( microtime( true ) - get_transient( 'sb2_' . $watchname ), $digits );
            delete_transient( 'sb2_' . $watchname );
            return $return;
        }
        
        /**
         * truncatestring.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @param   mixed   $string
         * @param   mixed   $del
         * @return  void
         */
        public static function truncatestring( $string, $del )
        {
            $len = strlen( $string );
            
            if ( $len > $del ) {
                $new = substr( $string, 0, $del ) . '...';
                return $new;
            } else {
                return $string;
            }
        
        }
        
        /**
         * add_dashboard_widget.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function add_dashboard_widget()
        {
            wp_add_dashboard_widget( 'add_dashboard_widget', 'SEO Booster', array( __CLASS__, 'dashboard_widget' ) );
        }
        
        /**
         * dashboard_widget.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Sunday, December 19th, 2021.
         * @version v1.0.1  Wednesday, February 23rd, 2022.
         * @access  public static
         * @return  void
         */
        public static function dashboard_widget()
        {
            global  $wpdb ;
            ?>
			<div style="float:right;">
				<a href="https://cleverplugins.com/" target="_blank"><img src="
				<?php 
            echo  esc_url( plugin_dir_url( __FILE__ ) . 'images/cleverpluginslogo.png' ) ;
            ?>
			" height="27" width="150" alt="cleverplugins.com"></a>
			</div>
			<?php 
            $output = '';
            $latestlimit = 5;
            $latestkws = $wpdb->get_results( "SELECT kw, engine, lastvisit, firstvisit FROM {$wpdb->prefix}sb2_kw WHERE `engine` NOT LIKE 'Internal Search' AND `kw`<>'#' ORDER BY lastvisit DESC LIMIT " . esc_sql( $latestlimit ) );
            
            if ( $latestkws ) {
                $output .= '<div id="latest-backlinks" class="activity-block">';
                $output .= '<h3>' . __( 'Latest Keywords', 'seo-booster' ) . '</h3>';
                $output .= '<p class="kwcontainer">';
                foreach ( $latestkws as $lkw ) {
                    $engine = $lkw->engine;
                    $engine = str_replace( 'www.', '', $engine );
                    $kw = str_replace( '  ', ' ', $lkw->kw );
                    $output .= '<code class="kwitm">' . trim( $kw ) . '</code>, ';
                }
                $output = rtrim( $output, ', ' );
                // removing last comma
                $output .= '</p>';
                $output .= '<p><a href="admin.php?page=sb2_keywords">' . __( 'See all on the Keywords page', 'seo-booster' ) . '</a></p>';
                $output .= '</div>';
            }
            
            $latestbacklinks = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sb2_bl WHERE `ig`='0' ORDER BY id DESC LIMIT " . esc_sql( $latestlimit ) );
            $allowed_html = wp_kses_allowed_html( 'post' );
            
            if ( $latestbacklinks ) {
                $output .= '<div id="latest-backlinks" class="activity-block">';
                $output .= '<h3>' . __( 'Latest Backlinks', 'seo-booster' ) . '</h3>';
                $output .= '<ul class="blcontainer">';
                foreach ( $latestbacklinks as $lbl ) {
                    $outurl = self::remove_http( $lbl->ref );
                    $output .= '<li class="blitm">' . trim( $outurl ) . '</li>';
                }
                $output .= '</ul>';
                $output .= '<p><a href="admin.php?page=sb2_backlinks">' . __( 'See all on the Backlinks page.', 'seo-booster' ) . '</a></p>';
                $output .= '</div>';
            }
            
            echo  wp_kses( $output, $allowed_html ) ;
        }
        
        /**
         * is_search.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @param   mixed   $inurl
         * @param   boolean $returnlist Default: false
         * @return  boolean
         */
        public static function is_search( $inurl, $returnlist = false )
        {
            if ( !$inurl ) {
                return false;
            }
            include 'inc/search_engines.php';
            if ( $returnlist ) {
                return $sengine;
            }
            $url_info = wp_parse_url( $inurl );
            // parse the url
            if ( isset( $url_info['host'] ) ) {
                $rootdomain = $url_info['host'];
            }
            foreach ( $sengine as $se ) {
                $strpos = strpos( $inurl, $se['u'] );
                // First we try the classical parameter way
                
                if ( false !== $strpos ) {
                    
                    if ( isset( $se['q'] ) ) {
                        $parsed = wp_parse_url( $inurl, PHP_URL_QUERY );
                        parse_str( $parsed, $query_info );
                        $query_field_in_use = $se['q'];
                        $returnarr = array(
                            'sengine_name' => $rootdomain,
                            'Se'           => $url_info['host'],
                            'Referstring'  => $inurl,
                        );
                        
                        if ( isset( $query_info[$query_field_in_use] ) ) {
                            $returnarr['Query'] = strtolower( $query_info[$query_field_in_use] );
                        } else {
                            $returnarr['Query'] = '#';
                        }
                        
                        return $returnarr;
                    }
                    
                    // Lets try a regexp match
                    
                    if ( isset( $se['m'] ) ) {
                        $matches = array();
                        
                        if ( preg_match( $se['m'], $inurl, $matches ) ) {
                            $keyword = strtolower( $matches[1] );
                            $keyword = str_replace( '-', ' ', $keyword );
                            $returnarr = array(
                                'sengine_name' => $rootdomain,
                                'Se'           => $url_info['host'],
                                'Query'        => $keyword,
                                'Referstring'  => $inurl,
                            );
                            return $returnarr;
                        }
                    
                    }
                
                }
            
            }
            return false;
        }
        
        /**
         * ignore_useragent.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @param   mixed   $testua
         * @return  boolean
         */
        public static function ignore_useragent( $testua )
        {
            $uaignorelist = array(
                'wprocketbot',
                'Arachnophilia',
                'AITCSRobot/1.1',
                'BackDoorBot',
                'BuiltBotTough',
                'Mata Hari',
                'LinkextractorPro',
                'UptimeRobot/2.0'
            );
            foreach ( $uaignorelist as $uaig ) {
                if ( stristr( $testua, $uaig ) ) {
                    return true;
                }
            }
            return false;
        }
        
        /**
         * ignore_current_url.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @param   mixed   $currurl
         * @return  boolean
         */
        public static function ignore_current_url( $currurl )
        {
            $ignorelist = array(
                'robots.txt',
                '/wp-login.php',
                '/wp-content/cache/',
                '/wp-json/jetpack/',
                '/wp-admin/',
                '?doing_wp_cron',
                'wp-cron.php',
                'exchange_token=',
                'sessiontoken=',
                'wpsc_action=',
                'wordfence_lh=',
                '_wfsf=view',
                '_wfsf=diff',
                'wordfence_syncAttackData=',
                'wc-ajax=',
                '.css',
                '.js',
                '.json',
                '/feed/',
                'wp-cron.php',
                '/wp-json/',
                'cdn.ampproject.org',
                '/wc-api/',
                'glid=',
                'track.adform.net',
                '/order-received/',
                'essb_counter_cache=',
                'uabb-name=',
                '?add-to-cart=',
                '&add-to-cart='
            );
            $url_info = wp_parse_url( $currurl );
            // parse the url
            foreach ( $ignorelist as $ig ) {
                if ( stristr( $currurl, $ig ) ) {
                    return $ig;
                }
            }
            return false;
        }
        
        /**
         * ignorelink.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @param   mixed   $link
         * @param   boolean $returnlist Default: false
         * @return  mixed
         */
        public static function ignorelink( $link, $returnlist = false )
        {
            $ignorelist = array(
                '.netdna-ssl.com',
                '.partner-ads.',
                'captcha.gecirtnotification.',
                'app.asana.com',
                'disqus.com/embed',
                'linkedin.com/in/',
                'linkedin.com/mwlite/',
                '.getfeedback.com',
                'online.seranking.com',
                '.meetup.com',
                'auth.miniorange.com/',
                'hooks.stripe.com',
                'linkedin.com/pulse/',
                'linkedin.com/sales/accounts/',
                'linkedin.com/messaging/',
                'webmail.',
                '/wp-login.php',
                '/wp-content/cache/',
                '/wp-json/jetpack/',
                '.uptimedoctor.com',
                '.pricerunner.',
                'anonym.to',
                'googleusercontent.com',
                'mail.',
                '/webmail/',
                'pipes.yahoo.com',
                'plus.url.google.com',
                'translate.google.',
                '/wp-admin/',
                '.facebook.com',
                '.facebook.net',
                'tinyurl.com',
                'myspace.com',
                'platform.twitter.com',
                'm.yahoo.com',
                'live.ru',
                'bit.ly',
                'baidu.com',
                'stumbleupon.com',
                '.seoheap.com',
                '1.1.1.1',
                'list-manage.com',
                'list-manage1.com',
                'anonym.to',
                'portal.attnet.mcore.com',
                'linkwithin.com',
                'domains.checkparams.com',
                'whois.domaintools.com',
                'static.ak.fbcdn.net',
                'jetpack.wordpress.com',
                'search.beamrise.com',
                'htdocs/nokia/startc/',
                'landing.secretbrowserapp',
                'm.aol.com',
                'smt.telcel.com',
                'whois.domaintools.com',
                'domains.checkparams.com',
                'wsdsold.infospace.com',
                'smt.telcel.com',
                'scriptmafia.org',
                '.movistar.com',
                'fapanga.com',
                'wp-cron.php/?doing_wp_cron',
                'translate.google.',
                '.list-manage.com',
                '.pinterest.com',
                'plus.url.google.com',
                'feedly.com',
                'feeds.feedburner.com',
                '.campaign-archive2.com',
                '.campaign-archive2.com',
                '.campaign-archive1.com',
                '.campaign-archive.com',
                '.quickpay.',
                'plus.google.com',
                '.paypal.',
                '.doubleclick.net',
                't.co',
                'l.instagram.com',
                'flipboard.com',
                'googleapis.com',
                'payment.quickpay.net',
                '.netdna-cdn.com',
                '.pinterest.com',
                'outlook.live.com',
                '.office.com',
                '.proxysite.com',
                '\\/wp-admin\\/',
                '\\/cgi-bin\\/',
                'localhost\\:',
                '\\/\\/redditcom.org',
                '.monitorbacklinks.com',
                '.copyscape.com',
                '.dnsrsearch.com',
                'ht.ly',
                '.discretesearch.com',
                '.messenger.com',
                'ebaydesc.com',
                'backlinkwatch.com',
                'tpc.googlesyndication.com',
                'semrush.com',
                'mouseflow.com',
                'cashbackdeals.dk',
                'adservicemedia.dk',
                'admin.mailchimp.com',
                '.googleadservices.',
                'staff.adservice.com',
                '.pricerunner.com',
                'tradedoubler.com',
                'insights.hotjar.com',
                'track.adform.net',
                'trackcmp.net',
                'app.intercom.io',
                'app.accuranker.com',
                'wordfence_lh=',
                '_wfsf=view',
                '_wfsf=diff'
            );
            // If asked to return the list of what is ignored
            if ( $returnlist ) {
                return $ignorelist;
            }
            $url_info = wp_parse_url( $link );
            // parse the url
            foreach ( $ignorelist as $ig ) {
                if ( stristr( $link, $ig ) ) {
                    return $ig;
                }
            }
            return false;
        }
        
        /**
         * seobooster_currenturl.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @access  public static
         * @param   boolean $full   Default: false
         * @return  mixed
         */
        public static function seobooster_currenturl( $full = false )
        {
            //no need to run in the admin...
            if ( is_admin() ) {
                return;
            }
            $phpdetected = add_query_arg( null, null );
            if ( !$phpdetected ) {
                $phpdetected = $_SERVER['REQUEST_URI'];
            }
            // Clean up URL
            
            if ( isset( $phpdetected ) ) {
                $phpdetected = remove_query_arg( array( 'gclid' ), $phpdetected );
                // removes various params from url
            }
            
            if ( $full ) {
                return esc_url_raw( site_url( $phpdetected ) );
            }
            return esc_url_raw( $phpdetected );
        }
        
        /**
         * checkreferrer.
         *
         * @author  Unknown
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 23rd, 2022.
         * @version v1.0.1  Sunday, June 5th, 2022.
         * @access  public static
         * @param   mixed   $currurl    Default: null
         * @param   mixed   $referer    Default: null
         * @return  void
         */
        public static function checkreferrer( $currurl = null, $referer = null )
        {
            // If missing parsed refererer, try to find it.
            if ( !$referer ) {
                if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
                    $referer = strtolower( sanitize_text_field( $_SERVER['HTTP_REFERER'] ) );
                }
            }
            $searchref = self::is_search( $referer );
            // returns the search term if detected
            
            if ( !isset( $currurl ) ) {
                $currurl = self::seobooster_currenturl();
                $currurl = strtok( $currurl, '?' );
                // Strips parameters
            }
            
            $igres = self::ignore_current_url( $currurl );
            
            if ( $igres ) {
                self::log( sprintf( 'Visit to <code>%1$s</code> ignored, matched <code>%2$s</code> in ignorelist.', str_replace( site_url(), '', $currurl ), $igres ) );
                return;
            }
            
            $blogurl = self::remove_http( site_url() );
            if ( isset( $referer ) ) {
                $parsedurl = wp_parse_url( $referer );
            }
            if ( isset( $parsedurl['host'] ) ) {
                $domain = $parsedurl['host'];
            }
            if ( isset( $parsedurl['query'] ) ) {
                parse_str( $parsedurl['query'], $params );
            }
            // Filter out internal navigation by users - allowing internal searches
            if ( strpos( $referer, self::remove_http( site_url() ) ) !== false && !isset( $params['s'] ) ) {
                return;
            }
            // translators:
            self::log( sprintf( __( 'Debug: checkreferrer(): Visitor on  <code>%1$s</code> from <code>%2$s</code>', 'seo-booster' ), self::remove_http( $currurl ), self::remove_http( $referer ) ) );
            
            if ( isset( $parsedurl['query'] ) ) {
                parse_str( $parsedurl['query'], $params );
                // an internal search
                
                if ( isset( $params['s'] ) ) {
                    // came from internal search result so lets add the keyword etc..
                    $searchref = array();
                    $searchref['Query'] = $params['s'];
                    $searchingfor = $params['s'];
                    $searchref['sengine_name'] = 'Internal Search';
                    // Constant, dont translate.
                    self::log( 'Debug: Visitor searching on website for: "' . sanitize_text_field( $searchingfor ) . '"' );
                }
            
            }
            
            // isset
            // Reintroduced in 2.4 - Ignore links from builtin list of domain referrers to ignore
            
            if ( self::ignorelink( $referer ) ) {
                // filter backlinks and unwanted links
                self::log( "Debug: Referrer ignored '" . sanitize_text_field( $referer ) . "'" );
                return;
            }
            
            
            if ( $searchref ) {
                global  $wpdb, $wp_query ;
                $table_kw = $wpdb->prefix . 'sb2_kw';
                $gquery = sanitize_text_field( $searchref['Query'] );
                // Sanitized
                $engine = sanitize_text_field( $searchref['sengine_name'] );
                
                if ( $gquery && '#' !== $gquery ) {
                    self::log( sprintf(
                        __( "Visit from %1\$s - Searching for <code>%2\$s</code> <a href='%3\$s' target='_blank'>%4\$s</a>", 'seo-booster' ),
                        $engine,
                        $gquery,
                        $currurl,
                        str_replace( site_url(), '', $currurl )
                    ) );
                } else {
                    self::log( sprintf(
                        __( "Visit from %1\$s. <a href='%2\$s' target='_blank'>%3\$s</a> Keyword not provided.", 'seo-booster' ),
                        $engine,
                        sanitize_text_field( $currurl ),
                        self::remove_http( str_replace( site_url(), '', $currurl ) )
                    ) );
                }
                
                // sets to # indicating the keyword is unknown
                if ( '' === $gquery ) {
                    $gquery = '#';
                }
                
                if ( isset( $parsedurl['host'] ) ) {
                    $tld = $parsedurl['host'];
                    // setting tld
                }
                
                if ( 'Google' !== $engine ) {
                    $tld = '';
                }
                if ( !$tld ) {
                    $tld = '';
                }
                $excistingentry = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}sb2_kw WHERE kw = %s AND lp = %s AND engine = %s LIMIT 1;",
                    $gquery,
                    $currurl,
                    $engine
                ) );
                
                if ( $excistingentry ) {
                    $tstamp = gmdate( 'Y-m-d H:i:s' );
                    $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}sb2_kw SET visits = visits+1, lastvisit=%s WHERE id = %d LIMIT 1;", $tstamp, $excistingentry ) );
                } else {
                    $wpdb->insert( $table_kw, array(
                        'kw'        => esc_attr( $gquery ),
                        'lp'        => $currurl,
                        'engine'    => $engine,
                        'googletld' => $tld,
                        'visits'    => 1,
                        'lastvisit' => gmdate( 'Y-m-d H:i:s' ),
                    ) );
                    $lastid = $wpdb->insert_id;
                }
                
                // ********* Update daily tracking and avg. position
                $table_kwdt = $wpdb->prefix . 'sb2_kwdt';
                $today = gmdate( 'Y-m-d' );
                $refid = false;
                if ( isset( $lastid ) ) {
                    $refid = $lastid;
                }
                if ( isset( $excistingentry ) && !isset( $refid ) ) {
                    $refid = $excistingentry;
                }
                
                if ( $refid ) {
                    // the referring id in the kw table has been found...
                    $visits = $wpdb->get_var( $wpdb->prepare( "SELECT visits FROM {$wpdb->prefix}sb2_kwdt WHERE refid = %d AND daday = %s limit 1;", $refid, $today ) );
                    
                    if ( $visits ) {
                        $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}sb2_kwdt SET `visits` = `visits`+1 WHERE refid = %d AND daday = %s LIMIT 1 ;", $refid, $today ) );
                        $kwtablerefid = $refid;
                    } else {
                        // new tracking of keyword daily visits...
                        $wpdb->insert( $table_kwdt, array(
                            'refid'  => $refid,
                            'daday'  => $today,
                            'visits' => '1',
                        ), array( '%s', '%s', '%d' ) );
                        $kwtablerefid = $wpdb->insert_id;
                    }
                
                }
                
                // Parse the URL into an array
                $url_parts = wp_parse_url( $referer );
                if ( isset( $url_parts['query'] ) ) {
                    parse_str( $url_parts['query'], $path_parts );
                }
            }
            
            // Verifies its a valid URL or return
            
            if ( !self::verifyurl( $referer ) ) {
                // translators:
                self::log( sprintf( __( 'Ignored invalid URL <code>%s</code>', 'seo-booster' ), sanitize_text_field( $referer ) ) );
                return;
            }
            
            
            if ( !$searchref && isset( $referer ) && '' !== $referer && !strstr( wp_parse_url( $referer, PHP_URL_HOST ), self::remove_http( site_url() ) ) ) {
                global  $wpdb ;
                $table_bl = $wpdb->prefix . 'sb2_bl';
                $excisting = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sb2_bl WHERE ref = %s", esc_url( $referer ) ) );
                
                if ( $excisting ) {
                    $query = "UPDATE `{$table_bl}` SET `visits` = `visits`+1,`lastvisit`=NOW(), `ig`='0'  WHERE `id` ='{$excisting}' LIMIT 1 ;";
                    $success = $wpdb->query( $query );
                    // todo - change to ->insert @prepare
                } else {
                    // New backlink, mark for later research.
                    $details = wp_parse_url( $referer );
                    if ( isset( $details['host'] ) ) {
                        $wpdb->insert( $table_bl, array(
                            'domain'     => $details['host'],
                            'visits'     => 1,
                            'ref'        => $referer,
                            'lp'         => $currurl,
                            'anchor'     => '',
                            'firstvisit' => current_time( 'mysql' ),
                            'lastvisit'  => current_time( 'mysql' ),
                        ), array(
                            '%s',
                            '%d',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s'
                        ) );
                    }
                    self::log( sprintf( 'First visit from backlink %1$s on page %2$s.', '<code>' . esc_url( self::remove_http( $referer ) ) . '</code>', '<code>' . esc_url( self::remove_http( $currurl ) ) . '</code>' ) );
                }
            
            }
            
            // DYNAMIC TAGGING SECTION...
            
            if ( $searchref && '#' !== $gquery && !is_int( $gquery ) ) {
                $dynamic_tagging = get_option( 'seobooster_dynamic_tagging' );
                $dynamic_tagging_rel = get_option( 'seobooster_dynamic_tagging_related' );
                $dynamic_tag_tax = get_option( 'seobooster_dynamic_tag_taxonomy' );
                $dynamic_tag_max = get_option( 'seobooster_dynamic_tag_maximum' );
                $dynamic_tag_minlength = intval( get_option( 'seobooster_dynamic_tag_minlength' ) );
                $dynamic_tag_maxlength = intval( get_option( 'seobooster_dynamic_tag_maxlength' ) );
                $dynamic_tag_assigncpts = get_option( 'seobooster_dynamic_tag_assigncpts' );
                $termlength = strlen( $gquery );
                if ( !$dynamic_tag_minlength ) {
                    $dynamic_tag_minlength = 5;
                }
                if ( !$dynamic_tag_maxlength ) {
                    $dynamic_tag_maxlength = 15;
                }
                
                if ( $dynamic_tagging ) {
                    $postid = null;
                    global  $post ;
                    if ( $post ) {
                        $postid = $post->ID;
                    }
                    if ( !isset( $postid ) ) {
                        $postid = url_to_postid( $currurl );
                    }
                    
                    if ( !isset( $postid ) ) {
                        $page = get_page_by_path( $currurl );
                        if ( $page ) {
                            $postid = $page->ID;
                        }
                    }
                    
                    $termlength = strlen( $gquery );
                    if ( !$dynamic_tag_minlength ) {
                        $dynamic_tag_minlength = 5;
                    }
                    if ( !$dynamic_tag_maxlength ) {
                        $dynamic_tag_maxlength = 15;
                    }
                    
                    if ( $postid && $termlength > $dynamic_tag_minlength && $termlength < $dynamic_tag_maxlength ) {
                        // we figured out the post id!
                        $terms = get_the_terms( $postid, $dynamic_tag_tax );
                        
                        if ( $terms ) {
                            $termcount = count( $terms );
                            
                            if ( $termcount < $dynamic_tag_max ) {
                                // if we are below the max count...
                                $term = term_exists( $gquery, $dynamic_tag_tax );
                                
                                if ( 0 !== $term && null !== $term ) {
                                    
                                    if ( is_array( $term ) ) {
                                        $term_id = intval( $term['term_id'] );
                                    } else {
                                        $term_id = intval( $term );
                                    }
                                
                                } else {
                                    $newterm = wp_insert_term(
                                        $gquery,
                                        // the term
                                        $dynamic_tag_tax,
                                        // the taxonomy
                                        array(
                                            'description' => esc_attr( $gquery ),
                                            'slug'        => sanitize_title( $gquery ),
                                        )
                                    );
                                    $term_id = intval( $newterm['term_id'] );
                                    $term = $newterm;
                                    $posttitle = $wpdb->get_var( $wpdb->prepare( "SELECT post_title FROM {$wpdb->prefix}sb2_bl WHERE ID=%d;", $postid ) );
                                    self::log( sprintf(
                                        __( "Created a new term <a href='%1\$s'>%2\$s</a> in the <code>%3\$s</code> taxonomy. For <a href='%4\$s' target='_blank'>%5\$s</a> (ID %6\$d)", 'seo-booster' ),
                                        admin_url( "edit-tags.php?action=edit&taxonomy={$dynamic_tag_tax}&tag_ID={$term_id}" ),
                                        esc_attr( $gquery ),
                                        $dynamic_tag_tax,
                                        get_permalink( $postid ),
                                        self::remove_http( get_permalink( $postid ) ),
                                        $postid
                                    ), 10 );
                                    // todo - change to ->insert
                                    $sqlstr = "UPDATE  `{$table_kw}` SET  `term_id` =  '" . $term_id . "' WHERE  `id` ={$postid};";
                                    // associate in database with the tag..
                                    $result = $wpdb->query( $sqlstr );
                                }
                                
                                $setresult = wp_set_object_terms(
                                    $postid,
                                    $term_id,
                                    $dynamic_tag_tax,
                                    true
                                );
                                
                                if ( is_wp_error( $setresult ) ) {
                                    $error_string = $setresult->get_error_message();
                                    // translators:
                                    self::log( sprintf( __( 'Error tagging <code>%s</code>', 'seo-booster' ), $error_string ) );
                                } else {
                                    // translators:
                                    self::log( sprintf(
                                        __( "Tagged <a href='%1\$s'>%2\$s</a> with <a href='%3\$s'>%4\$s</a>", 'seo-booster' ),
                                        get_permalink( $postid ),
                                        str_replace( site_url(), '', get_permalink( $postid ) ),
                                        admin_url( "edit-tags.php?action=edit&taxonomy={$dynamic_tag_tax}&tag_ID={$term_id}" ),
                                        $gquery
                                    ), 5 );
                                }
                                
                                // Automatic tagging related posts
                                
                                if ( 'on' === $dynamic_tagging_rel ) {
                                    $query_args = array(
                                        's'                   => $gquery,
                                        'posts_per_page'      => '25',
                                        'suppress_filters'    => '1',
                                        'ignore_sticky_posts' => true,
                                        'post_status'         => array( 'publish' ),
                                    );
                                    // todo - make a limit how many to automatically tag...
                                    
                                    if ( $dynamic_tag_assigncpts ) {
                                        $query_args['post_type'] = 'any';
                                        // any is filtered and respects exclude_from_search
                                    }
                                    
                                    $relatedposts = new WP_Query( $query_args );
                                    
                                    if ( $relatedposts->have_posts() ) {
                                        $collectedlist = array();
                                        while ( $relatedposts->have_posts() ) {
                                            $relatedposts->the_post();
                                            // todo - fails with plugins_loaded
                                            $relid = $post->ID;
                                            $relterms = get_the_terms( $relid, $dynamic_tag_tax );
                                            $reltermcount = count( $relterms );
                                            
                                            if ( $reltermcount < $dynamic_tag_max ) {
                                                $setrel = wp_set_object_terms(
                                                    $relid,
                                                    $gquery,
                                                    $dynamic_tag_tax,
                                                    true
                                                );
                                                self::log( sprintf(
                                                    __( "Tagged related post <a href='%1\$s'>%2\$s</a> with <a href='%3\$s'>%4\$s</a>", 'seo-booster' ),
                                                    get_permalink( $relid ),
                                                    str_replace( site_url(), '', get_permalink( $relid ) ),
                                                    admin_url( "edit-tags.php?action=edit&taxonomy={$dynamic_tag_tax}&tag_ID={$term_id}" ),
                                                    $gquery
                                                ) );
                                            }
                                        
                                        }
                                    }
                                    
                                    wp_reset_postdata();
                                }
                            
                            }
                        
                        }
                        
                        // if $terms
                    }
                
                }
            
            }
        
        }
        
        /**
         * https://stackoverflow.com/questions/6284553/using-an-array-as-needles-in-strpos
         * Update: Improved code with stop when the first of the needles is found:
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Saturday, August 7th, 2021.
         * @access  public static
         * @param   mixed   $haystack
         * @param   mixed   $needle
         * @param   integer $offset     Default: 0
         * @return  boolean
         */
        public static function strposa( $haystack, $needle, $offset = 0 )
        {
            if ( !is_array( $needle ) ) {
                $needle = array( $needle );
            }
            foreach ( $needle as $query ) {
                if ( strpos( $haystack, $query, $offset ) !== false ) {
                    return $query;
                }
            }
            return false;
        }
        
        /**
         * do_seobooster_maintenance() - Takes no arguments - handles all database maintenance and cleanup routines.
         * Used to run daily, now runs every hour.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Saturday, August 7th, 2021.
         * @access  public static
         * @return  void
         */
        public static function do_seobooster_maintenance()
        {
            global  $wpdb ;
            $changecount = 0;
            $sb2_maintenance_urls_table_stage = get_option( 'sb2_maintenance_urls_table_stage' );
            if ( !$sb2_maintenance_urls_table_stage ) {
                $sb2_maintenance_urls_table_stage = 0;
            }
            
            if ( wp_next_scheduled( 'sbp_dailymaintenance' ) ) {
                wp_clear_scheduled_hook( 'sbp_dailymaintenance' );
                self::log( __( 'Updated daily maintenance schedule to run every hour instead.', 'seo-booster' ) );
            }
            
            // LOG - Get count of how many log entries there are.
            $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sb2_log;" );
            $maxlogentries = 3000;
            
            if ( $total > $maxlogentries * 2 ) {
                // Over twice as big as allowed, time to trim.
                $targettime = $wpdb->get_var( "SELECT `logtime` from {$wpdb->prefix}sb2_log order by `logtime` DESC limit 3000,1;" );
                // find timestamp for last insert
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sb2_log WHERE logtime < %s;", $targettime ) );
                // translators:
                self::log( sprintf( __( 'Log table has %1$s entries, trimming to %2$s.', 'seo-booster' ), number_format_i18n( $total ), number_format_i18n( 3000 ) ) );
            }
            
            // deleting old daily keyword tracking
            $oldkwdtcount = $wpdb->get_var( "SELECT count(refid) FROM {$wpdb->prefix}sb2_kwdt WHERE refid NOT IN (SELECT id FROM {$wpdb->prefix}sb2_kw );" );
            
            if ( $oldkwdtcount > 0 ) {
                $wpdb->query( "DELETE FROM {$wpdb->prefix}sb2_kwdt WHERE refid NOT IN (SELECT id FROM {$wpdb->prefix}sb2_kw) LIMIT 3000;" );
                //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                // translators: Number of old unused keyword tracking removed
                self::log( sprintf( __( 'Removed %s old daily keywords tracking data.', 'seo-booster' ), number_format_i18n( $oldkwdtcount ) ) );
            }
            
            // Begin staged processing
            $sb2_maintenance_bl_table_stage = get_option( 'sb2_maintenance_bl_table_stage' );
            if ( !$sb2_maintenance_bl_table_stage ) {
                $sb2_maintenance_bl_table_stage = 0;
            }
            $stepinterval = 5000;
            // @todo
            $site_url = site_url();
            $site_url_no_http = self::remove_http( $site_url );
            // @todo better query
            $ownrefsquery = "SELECT id,ref FROM {$wpdb->prefix}sb2_bl WHERE (ref LIKE '%{$site_url}%' OR ref LIKE '%{$site_url_no_http}%') AND id>{$sb2_maintenance_bl_table_stage} LIMIT {$stepinterval};";
            // internal backlinks being falsely reported as external backlinks
            $ownresults = $wpdb->get_results( $ownrefsquery, ARRAY_A );
            //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            if ( $ownresults ) {
                foreach ( $ownresults as $res ) {
                    $resid = $res['id'];
                    $wpdb->delete( $wpdb->prefix . 'sb2_bl', array(
                        'id' => $resid,
                    ), array( '%d' ) );
                    ++$changecount;
                    // translators:
                    self::log( sprintf( __( 'Maintenance - Removing backlink <code>%1$s</code> Step %2$s', 'seo-booster' ), $res['ref'], $stepinterval ) );
                }
            }
            // @todo better query
            $higherrorcount = "SELECT id,ref,errorcount FROM {$wpdb->prefix}sb2_bl WHERE errorcount >= 5 AND verified = -1 LIMIT {$stepinterval};";
            // internal backlinks being falsely reported as external backlinks
            $errorresults = $wpdb->get_results( $higherrorcount, ARRAY_A );
            if ( $errorresults ) {
                foreach ( $errorresults as $res ) {
                    $resid = $res['id'];
                    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sb2_bl WHERE id=%d limit 1", $resid ) );
                    ++$changecount;
                    // translators:
                    self::log( sprintf( __( 'Maintenance - Removing backlink <code>%1$s</code> after %2$d failed attempts to verify.', 'seo-booster' ), substr( self::remove_http( $res['ref'] ), 0, 55 ), intval( $res['errorcount'] ) ) );
                }
            }
            // Filter out ignored backlink sources
            $seobooster_backlinks_ignore = get_option( 'seobooster_backlinks_ignore' );
            $blignore_arr = explode( ',', $seobooster_backlinks_ignore );
            
            if ( is_array( $blignore_arr ) ) {
                $wildcard = '%';
                // We could do query in array, but this seems more accurate
                foreach ( $blignore_arr as $bli ) {
                    
                    if ( '' !== $bli ) {
                        $like = $wildcard . $wpdb->esc_like( $bli ) . $wildcard;
                        $blresults = $wpdb->get_results( $wpdb->prepare( "SELECT id,ref,lp FROM {$wpdb->prefix}sb2_bl WHERE ref LIKE %s LIMIT %d;", $like, $stepinterval ), ARRAY_A );
                        
                        if ( $blresults ) {
                            foreach ( $blresults as $res ) {
                                $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sb2_bl WHERE id = %d LIMIT 1;", $res['id'] ) );
                            }
                            self::log( sprintf( __( "Maintenance - Removing backlink <code>%1\$s</code> - Matched <code>%2\$s</code> in 'Ignore backlink sources'", 'seo-booster' ), substr( self::remove_http( $res['ref'] ), 0, 55 ), $bli ) );
                        }
                    
                    }
                
                }
            }
            
            // Filter url table from unwanted urls
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT ID,url FROM {$wpdb->prefix}sb2_urls WHERE ID > %d LIMIT 5000;", $sb2_maintenance_urls_table_stage ), ARRAY_A );
            
            if ( $results ) {
                $ignoreext = array(
                    '.css',
                    '.jpg',
                    '.png',
                    '.txt',
                    '.gif',
                    '.xml'
                );
                foreach ( $results as $res ) {
                    $deleted = false;
                    // Så vi kun checker en gang
                    $resid = $res['ID'];
                    
                    if ( self::ignore_current_url( $res['url'] ) ) {
                        ++$changecount;
                        $wpdb->delete( $wpdb->prefix . 'sb2_urls', array(
                            'ID' => $resid,
                        ), array( '%d' ) );
                        $wpdb->delete( $wpdb->prefix . 'sb2_urls_meta', array(
                            'refid' => $resid,
                        ), array( '%d' ) );
                    }
                    
                    
                    if ( !$deleted ) {
                        $fundet = self::strposa( $res['url'], $ignoreext );
                        
                        if ( $fundet ) {
                            // translators:
                            self::log( sprintf( __( 'Maintenance - Removed URL in audit table <code>%s</code> - Matched ignored fileextension.', 'seo-booster' ), $res['url'] ) );
                            ++$changecount;
                            $wpdb->delete( $wpdb->prefix . 'sb2_urls', array(
                                'ID' => $resid,
                            ), array( '%d' ) );
                            $wpdb->delete( $wpdb->prefix . 'sb2_urls_meta', array(
                                'refid' => $resid,
                            ), array( '%d' ) );
                        }
                    
                    }
                
                }
            }
            
            // Clean up urls from backlinks table
            // @todo - better query
            $query = "SELECT id,ref,lp,lastvisit,visits FROM {$wpdb->prefix}sb2_bl WHERE id > {$sb2_maintenance_bl_table_stage} LIMIT {$stepinterval};";
            $results = $wpdb->get_results( $query, ARRAY_A );
            if ( $results ) {
                foreach ( $results as $res ) {
                    $resid = $res['id'];
                    $sb2_maintenance_bl_table_stage = $resid;
                    
                    if ( !self::verifyurl( $res['ref'] ) ) {
                        ++$changecount;
                        $wpdb->delete( $wpdb->prefix . 'sb2_bl', array(
                            'id' => $resid,
                        ), array( '%d' ) );
                        // translators:
                        self::log( sprintf( __( 'Maintenance - Removing backlink <code>%s</code>', 'seo-booster' ), $res['ref'] ) );
                    }
                    
                    // Filtrer links fra vi -ikke- kan lide
                    
                    if ( self::ignorelink( $res['ref'] ) ) {
                        ++$changecount;
                        $wpdb->delete( $wpdb->prefix . 'sb2_bl', array(
                            'id' => $resid,
                        ), array( '%d' ) );
                    }
                    
                    
                    if ( self::ignore_current_url( $res['ref'] ) ) {
                        ++$changecount;
                        $wpdb->delete( $wpdb->prefix . 'sb2_bl', array(
                            'id' => $resid,
                        ), array( '%d' ) );
                    }
                    
                    $searchref = self::is_search( $res['ref'] );
                    
                    if ( $searchref ) {
                        $engine = $searchref['sengine_name'];
                        $gquery = $searchref['Query'];
                        if ( '' === $gquery ) {
                            $gquery = '#';
                        }
                        $parsedurl = wp_strip_all_tags( $res['ref'] );
                        $tld = $parsedurl['host'];
                        if ( 'Google' !== $engine ) {
                            $tld = '';
                        }
                        // translators: When maintenance script found a new
                        self::log( sprintf( __( 'Maintenance - Found a search engine visitor from <code>%s</code> - updating keyword records.', 'seo-booster' ), $searchref['sengine_name'] ) );
                        $wpdb->insert( $wpdb->prefix . 'sb2_kw', array(
                            'kw'        => $gquery,
                            'lp'        => $res['lp'],
                            'engine'    => $searchref['sengine_name'],
                            'googletld' => $tld,
                            'visits'    => $res['visits'],
                            'lastvisit' => $res['lastvisit'],
                        ), array(
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%d',
                            '%s'
                        ) );
                        $lastid = $wpdb->insert_id;
                        if ( $lastid ) {
                            $wpdb->delete( $wpdb->prefix . 'sb2_bl', array(
                                'id' => $resid,
                            ), array( '%d' ) );
                        }
                        ++$changecount;
                    } else {
                        // checking to ignore this link or even remove.
                        
                        if ( self::ignorelink( $res['ref'] ) ) {
                            ++$changecount;
                            $wpdb->delete( $wpdb->prefix . 'sb2_bl', array(
                                'id' => $resid,
                            ), array( '%d' ) );
                            // translators:
                            self::log( sprintf( __( 'Maintenance - Removing a reference to <code>%s</code>.', 'seo-booster' ), self::remove_http( $res['ref'] ) ) );
                        }
                    
                    }
                
                }
            }
            if ( $changecount > 0 ) {
                // translators:
                self::log( sprintf( __( 'Maintenance routines made %d changes to the database.', 'seo-booster' ), number_format_i18n( $changecount ) ) );
            }
            $highest_bltable = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}sb2_bl ORDER BY id DESC LIMIT 1;" );
            
            if ( $sb2_maintenance_bl_table_stage >= $highest_bltable ) {
                $sb2_maintenance_bl_table_stage = 0;
            } else {
                $sb2_maintenance_bl_table_stage + $stepinterval;
            }
            
            update_option( 'sb2_maintenance_bl_table_stage', $sb2_maintenance_bl_table_stage );
            // Setting current stage in cleaning up - Urlstable
            $sb2_maintenance_urls_table_stage = $sb2_maintenance_urls_table_stage + $stepinterval;
            $highest_urlstable = $wpdb->get_var( "SELECT ID FROM {$wpdb->prefix}sb2_urls ORDER BY ID DESC LIMIT 1;" );
            if ( $sb2_maintenance_urls_table_stage >= $highest_urlstable ) {
                $sb2_maintenance_urls_table_stage = 0;
            }
            update_option( 'sb2_maintenance_urls_table_stage', $sb2_maintenance_urls_table_stage );
        }
        
        /**
         * check_page_for_url.
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Saturday, August 7th, 2021.
         * @version v1.0.1  Tuesday, November 30th, 2021.
         * @access  public static
         * @param   mixed   $page_url
         * @param   mixed   $check_url
         * @return  mixed
         */
        public static function check_page_for_url( $page_url, $check_url )
        {
            $result = array();
            $response = wp_remote_get( $page_url, array(
                'timeout'    => 30,
                'user-agent' => esc_attr( 'Mozilla/5.0 (compatible; SEO Booster Bot v.' . SEOBOOSTER_VERSION . '; +https://cleverplugins.com)' ),
            ) );
            $http_code = wp_remote_retrieve_response_code( $response );
            $output = wp_remote_retrieve_body( $response );
            
            if ( 200 === $http_code ) {
                $dom = new DomDocument();
                libxml_use_internal_errors( true );
                $dom->loadHTML( $output );
                $urls = $dom->getElementsByTagName( 'a' );
                foreach ( $urls as $url ) {
                    
                    if ( !isset( $result['link_found'] ) ) {
                        // Ingen grund til at checke hvis vi -har- fundet linket
                        $daurl = $url->getAttribute( 'href' );
                        $pos = strpos( $daurl, $check_url );
                        
                        if ( false !== $pos ) {
                            $result['link_found'] = '1';
                            $result['href'] = $daurl;
                            $result['anchor_text'] = (string) $url->nodeValue;
                        }
                    
                    }
                
                }
            }
            
            return $result;
        }
    
    }
    // end class seobooster2
}

// Ends the if class exists logic
if ( !function_exists( 'seoboosterpro_boostlist' ) ) {
    /**
     * seoboosterpro_boostlist.
     *
     * @author  Unknown
     * @since   v0.0.1
     * @version v1.0.0  Saturday, August 7th, 2021.
     * @param   string  $before     Default: '<ul>'
     * @param   string  $after      Default: '</ul>'
     * @param   string  $beforeeach Default: '<li>'
     * @param   string  $aftereach  Default: '</li>'
     * @param   integer $limit      Default: 10
     * @return  void
     */
    function seoboosterpro_boostlist(
        $before = '<ul>',
        $after = '</ul>',
        $beforeeach = '<li>',
        $aftereach = '</li>',
        $limit = 10
    )
    {
        global  $wpdb, $seobooster2 ;
        $currurl = $seobooster2->seobooster_currenturl();
        $currurl = strtok( $currurl, '?' );
        // Strips parameters
        $kwtable = $wpdb->prefix . 'sb2_kw';
        $sqlignore = '';
        $sqlignore = $seobooster2->seobooster_generateignorelist();
        $prepared = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sb2_kw WHERE {$sqlignore} `lp` = %s AND kw<>'#' and kw<>'' ORDER BY visits DESC LIMIT %d;", $currurl, $limit );
        $posthits = $wpdb->get_results( $prepared, ARRAY_A );
        $allowed_html = wp_kses_allowed_html( 'post' );
        
        if ( $posthits ) {
            echo  wp_kses( $before, $allowed_html ) ;
            foreach ( $posthits as $hits ) {
                echo  wp_kses( $beforeeach, $allowed_html ) ;
                echo  wp_kses( $hits['kw'], $allowed_html ) ;
                echo  wp_kses( $aftereach, $allowed_html ) ;
            }
            echo  wp_kses( $after, $allowed_html ) ;
        }
    
    }

}
// Alias for the old function, seoboosterpro_boostlist()
if ( !function_exists( 'seobooster_kwlist' ) ) {
    /**
     * seobooster_kwlist.
     *
     * @author  Unknown
     * @since   v0.0.1
     * @version v1.0.0  Saturday, August 7th, 2021.
     * @param   string  $before     Default: '<ul>'
     * @param   string  $after      Default: '</ul>'
     * @param   string  $beforeeach Default: '<li>'
     * @param   string  $aftereach  Default: '</li>'
     * @param   integer $limit      Default: 10
     * @return  void
     */
    function seobooster_kwlist(
        $before = '<ul>',
        $after = '</ul>',
        $beforeeach = '<li>',
        $aftereach = '</li>',
        $limit = 10
    )
    {
        if ( function_exists( 'seoboosterpro_boostlist' ) ) {
            // if the old function exists, reuse it.
            seoboosterpro_boostlist(
                $before,
                $after,
                $beforeeach,
                $aftereach,
                $limit
            );
        }
    }

}
global  $seobooster2 ;
if ( class_exists( 'seobooster2' ) && !$seobooster2 ) {
    $seobooster2 = new seobooster2();
}