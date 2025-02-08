<?php

namespace Cleverplugins\SEOBooster;

// don't load directly
if ( !defined( 'ABSPATH' ) ) {
	exit;
}
class Utils extends Seobooster2 {
	/**
	* Performs daily maintenance routines for SEO Booster.
	*
	* This method cleans up the log table by removing old entries
	* when the total number of entries exceeds 10,000.
	*
	* @since   v0.0.1
	* @version v1.0.2 Tuesday, August 20th, 2024.
	*
	* @global wpdb $wpdb WordPress database abstraction object.
	* @return void
	*/
	public static function do_seobooster_dailymaintenance() {
		global $wpdb;
		$table_name_log = $wpdb->prefix . 'sb2_log';
		// Count the number of entries in the sb2_log table
		$entry_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name_log}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// If there are more than 10,000 entries, delete the oldest entries
		if ( $entry_count && intval( $entry_count ) > 10000 ) {
			$entries_to_delete = intval( $entry_count ) - 10000;
			$wpdb->query( $wpdb->prepare( "DELETE FROM `%s` ORDER BY logtime ASC LIMIT %d", $table_name_log, $entries_to_delete ) );
		}
	}
	
	/**
	* Adds direct links to settings and support from the plugin overview page.
	*
	* @since   v0.0.1
	* @version v1.0.1  Tuesday, August 27th, 2024.
	* @access  public static
	* @param   array  $actions     An array of plugin action links.
	* @param   string $plugin_file Path to the plugin file relative to the plugins directory.
	* @return  array  Modified array of plugin action links.
	*/
	public static function add_settings_link( $actions, $plugin_file ) {
		static $plugin;
		if ( !isset( $plugin ) ) {
			$plugin = plugin_basename( $plugin_file );
		}
		if ( $plugin === $plugin_file ) {
			$new_actions = array(
				'settings'      => sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=sb2_settings' ) ), esc_html__( 'Settings', 'seo-booster' ) ),
				'documentation' => sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( 'https://seoboosterpro.com/support/' ), esc_html__( 'Support', 'seo-booster' ) ),
			);
			$actions = array_merge( $new_actions, $actions );
		}
		return $actions;
	}
	
	/**
	* Makes an auto link via AJAX call (from individual posts)
	*
	* @author  Unknown
	* @since   v0.0.1
	* @version v1.0.0  Sunday, June 30th, 2024.
	* @access  public static
	* @return  void
	*/
	public static function gsc_make_auto_link() {
		if ( isset( $_POST['security'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['security'] ) );
			// Nonce.
		} else {
			$nonce = '';
		}
		if ( empty( $nonce ) || !wp_verify_nonce( $nonce, 'sb_gsc_nonce' ) ) {
			wp_send_json_error( array(
				'success' => false,
				'message' => esc_html__( 'Nonce verification failed.', 'seo-booster' ),
				) );
			}
			if ( !current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array(
					'success' => false,
					'message' => esc_html__( 'You do not have permission to create auto links.', 'seo-booster' ),
					) );
				}
				global $wpdb;
				$post_id = ( isset( $_POST['post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) : '' );
				$query_id = ( isset( $_POST['query_id'] ) ? sanitize_text_field( wp_unslash( $_POST['query_id'] ) ) : '' );
				if ( empty( $post_id ) || empty( $query_id ) ) {
					wp_send_json_error( array(
						'success' => false,
						'message' => esc_html__( 'Post ID or Query ID is missing.', 'seo-booster' ),
						) );
					}
					$keyword = $wpdb->get_var( $wpdb->prepare( "SELECT query FROM {$wpdb->prefix}sb2_query_keywords WHERE id = %d", $query_id ) );
					$page_url = get_permalink( $post_id );
					if ( $keyword && $page_url ) {
						$wpdb->insert( "{$wpdb->prefix}sb2_autolink", array(
							'keyword' => $keyword,
							'url'     => $page_url,
						), array('%s', '%s') );
						$last_insert_id = $wpdb->insert_id;
						// if $last_insert_id is ok, return success
						if ( $last_insert_id ) {
							wp_send_json_success( array(
								'success' => true,
								'message' => '<span class="label label-info">' . esc_html__( 'Linked', 'seo-booster' ) . '</span>',
								) );
							} else {
								$wpdb_error = $wpdb->last_error;
								wp_send_json_error( array(
									'success' => false,
									'message' => esc_html__( 'Link creation failed.', 'seo-booster' ) . ' ' . esc_html( $wpdb_error ),
									) );
								}
							}
							exit;
						}
						
						/**
						* prefixsetupschedule.
						*
						* @author  Lars Koudal
						* @since   v0.0.1
						* @version v1.0.0  Tuesday, June 25th, 2024.
						* @access  public static
						* @return  void
						*/
						public static function prefixsetupschedule() {
							if ( !wp_next_scheduled( 'seobooster_email_update' ) ) {
								wp_schedule_event( time(), 'weekly', 'seobooster_email_update' );
							}
							if ( !wp_next_scheduled( 'seobooster_gsc_data_fetch' ) ) {
								wp_schedule_event( time(), 'daily', 'seobooster_gsc_data_fetch' );
							}
							if ( !wp_next_scheduled( 'seobooster_dailymaintenance' ) ) {
								wp_schedule_event( time(), 'daily', 'seobooster_dailymaintenance' );
							}
						}
						
						/**
						* Checks a string against an array of keywords and returns any matches or false if no match.
						*
						* @author  Unknown
						* @since   v0.0.1
						* @version v1.0.0  Tuesday, November 30th, 2021.
						* @version v1.0.1  Friday, June 21st, 2024.
						* @access  public static
						* @param   mixed   $str
						* @param   array   $arr
						* @return  boolean
						*/
						public static function array_in_string( $str, array $arr ) {
							$return_arr = array();
							foreach ( $arr as $arr_value ) {
								$pattern = '/\\b' . preg_quote( $arr_value['kw'], '/' ) . '\\b/u';
								if ( preg_match(
									$pattern,
									$str,
									$matches,
									PREG_OFFSET_CAPTURE
									) ) {
										$wrdpos = $matches[0][1];
										$orgword = mb_substr( $str, $wrdpos, mb_strlen( $arr_value['kw'] ) );
										$arr_value['orgword'] = $orgword;
										$return_arr[] = $arr_value;
									}
								}
								if ( !empty( $return_arr ) ) {
									return $return_arr;
								}
								return false;
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
							public static function create_database_tables() {
								require_once ABSPATH . 'wp-admin/includes/upgrade.php';
								global $wpdb;
								$wpdb_collate = $wpdb->get_charset_collate();
								$table_name = $wpdb->prefix . 'sb2_query_keywords';

								$sql = "CREATE TABLE {$table_name} (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				query varchar(255) NOT NULL,
				page varchar(255) NOT NULL,
				first_seen_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				latest_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				is_used_in_content tinyint(1) DEFAULT 0 NOT NULL,
				last_checked datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY unique_query_page (query, page),
				KEY query (query),
				KEY page (page)
				) $wpdb_collate";

								dbDelta( $sql );

								$table_name_history = $wpdb->prefix . 'sb2_query_keywords_history';
$sql = "CREATE TABLE {$table_name_history} (
id mediumint(9) NOT NULL AUTO_INCREMENT,
query_keywords_id mediumint(9) NOT NULL,
clicks int(11) NOT NULL,
impressions int(11) NOT NULL,
ctr float NOT NULL,
position float NOT NULL,
date DATE NOT NULL,
PRIMARY KEY  (id),
KEY query_keywords_id (query_keywords_id),
KEY date (date),
UNIQUE KEY unique_query_date (query_keywords_id, date)
) $wpdb_collate";

								dbDelta( $sql );

								$table_name = $wpdb->prefix . 'sb2_autolink';
$sql = "CREATE TABLE {$table_name} (
id bigint(20) NOT NULL AUTO_INCREMENT,
keyword varchar(255),
url varchar(255),
disable int(1) DEFAULT '0',
nflw int(1) DEFAULT '0',
lastseen longtext,
PRIMARY KEY  (id),
KEY keyword (keyword),
KEY url (url)) $wpdb_collate";

								dbDelta( $sql );

								$table_name = $wpdb->prefix . 'sb2_404';
$sql = "CREATE TABLE {$table_name} (
id bigint(20) NOT NULL AUTO_INCREMENT,
lp varchar(500) NOT NULL,
firstseen timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
lastseen timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
visits int(11) NOT NULL,
referer text NOT NULL,
PRIMARY KEY  (id)) $wpdb_collate";

								dbDelta( $sql );

								$table_name = $wpdb->prefix . 'sb2_log';
$sql = "CREATE TABLE {$table_name} (
ID bigint(20) NOT NULL AUTO_INCREMENT,
logtime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
prio tinyint(1) NOT NULL,
log varchar(2048) NOT NULL,
PRIMARY KEY  (ID),
KEY ID (ID)) $wpdb_collate";

								dbDelta( $sql );

								Utils::log( __( 'Updated database tables', 'seo-booster' ) );
								update_option( 'SEOBOOSTER_INSTALLED_DB_VERSION', SEOBOOSTER_DB_VERSION );
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
							public static function get_icon_svg( $base64 = true ) {
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
							* seobooster_currenturl.
							*
							* @author  Unknown
							* @since   v0.0.1
							* @version v1.0.0  Wednesday, February 23rd, 2022.
							* @access  public static
							* @param   boolean $full   Default: false
							* @return  mixed
							*/
							public static function seobooster_currenturl( $full = false ) {
								// no need to run in the admin...
								if ( is_admin() ) {
									return;
								}
								$phpdetected = add_query_arg( null, null );
								if ( !$phpdetected ) {
									$phpdetected = ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' );
								}
								// Clean up URL if we have a value
								if ( $phpdetected ) {
									$phpdetected = remove_query_arg( array('gclid'), $phpdetected );
									// removes various params from url
								}
								if ( $full ) {
									return esc_url_raw( site_url( $phpdetected ) );
								}
								return esc_url_raw( $phpdetected );
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
							public static function remove_http( $url = '' ) {
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
							* Logs events to database
							*
							* @author  Unknown
							* @since   v0.0.1
							* @version v1.0.0  Wednesday, February 23rd, 2022.
							* @access  public static
							* @param   mixed   $text
							* @param   integer $prio   priority, default 0. Values: 
							* 0: Normal 
							* 1: Debug
							* 2: Error
							* 3: Warning
							* 5: Info
							* 10: Success
							* @return  void
							*/
							public static function log( $text, $prio = 0 ) {
								// Trim text to 2048 characters
								$text = substr( $text, 0, 2048 );
								global $wpdb;
								$table_name_log = $wpdb->prefix . 'sb2_log';
								$wpdb->insert( $table_name_log, array(
									'logtime' => current_time( 'mysql' ),
									'prio'    => $prio,
									'log'     => $text,
								), array('%s', '%d', '%s') );
							}
							
							/**
							* Helper function to generate tagged links
							*
							* @author  Lars Koudal
							* @since   v0.0.1
							* @version v1.0.0  Tuesday, June 25th, 2024.
							* @access  public static
							* @param   string  $placement  Where in the plugin the link is placed
							* @param   string  $page       Which page on cleverplugins website the link points to
							* @param   mixed   $params     Any additional parameters to add to the link
							* @return  mixed
							*/
							public static function generate_cp_web_link( $placement = '', $page = '/', $params = array() ) {
								$base_url = 'https://seoboosterpro.com';
								if ( '/' !== $page ) {
									$page = '/' . trim( $page, '/' ) . '/';
								}
								$utm_source = 'seo_booster_free';
								$parts = array_merge( array(
									'utm_source'   => esc_attr( $utm_source ),
									'utm_medium'   => 'plugin',
									'utm_content'  => esc_attr( $placement ),
									'utm_campaign' => esc_attr( 'seo_booster_v' . self::get_plugin_version() ),
								), $params );
								$out = $base_url . $page . '?' . http_build_query( $parts, '', '&amp;' );
								return $out;
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
							public static function timerstart( $watchname ) {
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
							public static function timerstop( $watchname, $digits = 5 ) {
								$return = round( microtime( true ) - get_transient( 'sb2_' . $watchname ), $digits );
								delete_transient( 'sb2_' . $watchname );
								return $return;
							}
							
							/**
							* show_plugin_headline.
							*
							* @author	Lars Koudal
							* @since	v0.0.1
							* @version	v1.0.0	Monday, August 26th, 2024.
							* @access	public static
							* @param	string 	$title 	Default: ''
							* @param	boolean	$return	Default: false
							* @return	void
							*/
							public static function show_plugin_headline( $title = '', $return = false ) {
								$content = '<div class="big welcome"><span><img src="' . esc_url( SEOBOOSTER_PLUGINURL . 'images/sblogo25.png' ) . '" height="40" class="SEO Booster logo" alt="SEO Booster"></span>SEO Booster <span class="version">v. ' . esc_html( Seobooster2::get_plugin_version() ) . '</span><span class="title">' . esc_html( $title ) . '</span>';
								$documentation_url = Utils::generate_cp_web_link( 'admin', 'docs' );
								$content .= '<span class="navcont">';
								$content .= '<span class="documentation"><a href="' . esc_url( $documentation_url ) . '" target="_blank" class="documentation">' . esc_html__( 'Documentation', 'seo-booster' ) . '</a></span>';
								$content .= '</div>';
								if ( $return ) {
									return $content;
								}
								echo wp_kses( $content, array(
									'div'  => array(
										'class' => array(),
									),
									'span' => array(
										'class' => array(),
									),
									'img'  => array(
										'src'    => array(),
										'height' => array(),
										'class'  => array(),
										'alt'    => array(),
									),
									'a'    => array(
										'href'                     => array(),
										'class'                    => array(),
										'id'                       => array(),
										'target'                   => array(),
										'rel'                      => array(),
										'data-productlift-widget'  => array(),
										'data-productlift-sidebar' => array(),
									),
									) );
								}
								
								/**
								* Process weekly email signup AJAX request
								*
								* @since v0.0.1
								* @version v1.0.0 Tuesday, September 10th, 2024.
								* @access public static
								* @return void
								*/
								public static function process_weekly_email_signup() {
									// Verify nonce for security
									if ( !check_ajax_referer( 'seobooster_save_selected_site', 'nonce', false ) ) {
										wp_send_json_error( __( 'Security check failed', 'seo-booster' ) );
										return;
									}
									// Check user capabilities
									if ( !current_user_can( 'manage_options' ) ) {
										wp_send_json_error( __( 'Insufficient permissions', 'seo-booster' ) );
										return;
									}
									// Validate email input
									$email_recipient = ( isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '' );
									if ( empty( $email_recipient ) ) {
										wp_send_json_error( __( 'No email address provided', 'seo-booster' ) );
										return;
									}
									// Process and validate email addresses
									$email_addresses = array_map( 'trim', explode( ',', $email_recipient ) );
									$valid_emails = array_filter( $email_addresses, 'is_email' );
									// Handle case of single email address
									if ( count( $email_addresses ) === 1 && is_email( $email_addresses[0] ) ) {
										$valid_emails = $email_addresses;
									}
									if ( empty( $valid_emails ) ) {
										wp_send_json_error( __( 'No valid email addresses provided', 'seo-booster' ) );
										return;
									}
									// Prepare validated email string
									$email_recipient = implode( ',', $valid_emails );
									// Update options
									update_option( 'seobooster_weekly_email', 'on', true );
									update_option( 'seobooster_weekly_email_recipient', $email_recipient, true );
									// Send success response
									wp_send_json_success( sprintf( 
										/* translators: %s: Email recipient(s) */
										__( 'Weekly email signup processed successfully for: %s', 'seo-booster' ),
										$email_recipient
										) );
									}
									
								}
								