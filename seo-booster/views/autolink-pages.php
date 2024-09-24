<?php

// don't load directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
	<div class="sbpheader">
		<?php 
require_once SEOBOOSTER_PLUGINPATH . 'inc/adminheader.php';
// Contains general info
?>
		<h1><?php 
esc_html_e( 'Autolink - Change keywords to links', 'seo-booster' );
?> - SEO Booster v.<?php 
echo  esc_html( SEOBOOSTER_VERSION ) ;
?></h1>
				<?php 
?>
	</div>
	<div id="sb2fof" class="clearfix clear">
		<div class="lead">
			<p><?php 
esc_html_e( 'Add keywords or phrases that should be changed in your posts to links.', 'seo-booster' );
?></p>
			<p><?php 
esc_html_e( 'The last used column shows the latest 3 pages where the keyword phrase was last replaced. ', 'seo-booster' );
?></p>
			<p><a href="<?php 
echo  esc_url( admin_url( 'admin.php?page=sb2_settings#autolinks' ) ) ;
?>"><?php 
_e( 'Direct link to Auto Link settings', 'seo-booster' );
?></a></p>
		</div>
	</div>
	<?php 
global  $wpdb ;
$deleted_dupes = $wpdb->query( "DELETE t1 FROM {$wpdb->prefix}sb2_autolink t1 INNER JOIN {$wpdb->prefix}sb2_autolink t2 WHERE t1.id > t2.id AND t1.keyword = t2.keyword;" );

if ( $deleted_dupes > 0 ) {
    ?>

		<div class="notice notice-info"><p>
			<?php 
    // translators:
    echo  esc_html( sprintf( __( '<code>%s</code> duplicate keyword links were removed automatically. ', 'seo-booster' ), number_format_i18n( $deleted_dupes ) ) ) ;
    ?>
		</p>
		<p><input type="button" class="button button-secondary" value="<?php 
    esc_html_e( 'Click to reload page', 'seo-booster' );
    ?>" onClick="window.location.reload()"></p>
	</div>
		<?php 
}

$lookupkwlimit = 1000;
$stepcount = 0;

if ( isset( $_POST['btn_reset_pages_autolink'] ) ) {
    $nonce = sanitize_text_field( $_REQUEST['_wpnonce'] );
    
    if ( !wp_verify_nonce( $nonce, 'seobooster_reset_pages_autolink' ) ) {
        die( 'Security check' );
    } else {
        $args = array(
            'post_type'      => array( 'post', 'page' ),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        );
        $posts = get_posts( $args );
        $stepcount = 0;
        foreach ( $posts as $post ) {
            $stepcount++;
            update_post_meta( $post->ID, '_sbp-autolink', 'yes' );
        }
        
        if ( $stepcount ) {
            // translators:
            self::log( sprintf( __( 'Success! Turned on automatic link on <code>%s</code> posts and pages.', 'seo-booster' ), number_format_i18n( $stepcount ) ) );
            ?>

			<div class="notice notice-success"><p>
				<?php
            echo esc_html(
                // translators:
                sprintf( __( 'Success! Turned on automatic linking on %s posts and pages.', 'seo-booster' ), number_format_i18n( $stepcount ) )
            ) ;
            ?>
			</p></div>
				<?php 
        }
    
    }

}


if ( isset( $_POST['focuskw_convert'] ) ) {
    $nonce = sanitize_text_field( $_REQUEST['_wpnonce'] );
    
    if ( !wp_verify_nonce( $nonce, 'seobooster_convert_focuskw_autolink' ) ) {
        die( 'Security check' );
    } else {
        $fokuskw_query = "SELECT {$wpdb->prefix}posts.ID as ID, {$wpdb->prefix}postmeta.meta_value as kw, {$wpdb->prefix}postmeta.meta_key as dakey FROM {$wpdb->prefix}posts, {$wpdb->prefix}postmeta WHERE {$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id AND (( {$wpdb->prefix}postmeta.meta_key = '_yoast_wpseo_focuskw' ) OR ( {$wpdb->prefix}postmeta.meta_key = '_yoast_wpseo_focuskeywords' ) ) AND {$wpdb->prefix}postmeta.meta_value <> '' AND {$wpdb->prefix}postmeta.meta_value <> '[]' AND {$wpdb->prefix}posts.post_status = 'publish GROUP BY kw 'LIMIT {$lookupkwlimit};";
        // TODO
        $kwresults = $wpdb->get_results( $fokuskw_query );
        $stepcount = 0;
        
        if ( $kwresults ) {
            $yoastkwlist = array();
            foreach ( $kwresults as $kw ) {
                
                if ( '_yoast_wpseo_focuskw' === $kw->dakey ) {
                    $foundid = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sb2_autolink WHERE `keyword` = %s limit 1;", $kw->kw ) );
                    
                    if ( !$foundid ) {
                        $yoastkwlist[$stepcount]['kw'] = $kw->kw;
                        $yoastkwlist[$stepcount]['lp'] = get_permalink( $kw->ID );
                    }
                
                }
                
                // Process premium version with multiple keywords
                
                if ( '_yoast_wpseo_focuskeywords' === $kw->dakey ) {
                    $focuskeywords = json_decode( $kw->kw );
                    if ( is_array( $focuskeywords ) ) {
                        foreach ( $focuskeywords as $focuskw ) {
                            $foundid = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sb2_autolink WHERE `keyword` = %s", $focuskw->keyword ) );
                            
                            if ( ! $foundid ) {
                                $yoastkwlist[$stepcount]['kw'] = $focuskw->keyword;
                                $yoastkwlist[$stepcount]['lp'] = get_permalink( $kw->ID );
                            }
                        
                        }
                    }
                }
                
                $stepcount++;
            }
        }
        
        // end lookup from postmeta
        // Reading data from taxonomies, tags, categories etc.
        $wpseo_taxonomy_meta = get_option( 'wpseo_taxonomy_meta' );
        if ( $wpseo_taxonomy_meta ) {
            if ( is_array( $wpseo_taxonomy_meta ) ) {
                foreach ( $wpseo_taxonomy_meta as $tax => $element ) {
                    foreach ( $element as $daid => $data ) {
                        
                        if ( isset( $data['wpseo_focuskw'] ) ) {
                            $permalink_kw = get_term_link( $daid, $tax );
                            
                            if ( !is_wp_error( $permalink_kw ) ) {
                                // Checking for and removing duplicates
                                $foundid = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sb2_autolink WHERE `keyword` = %s limit 1;", $data['wpseo_focuskw'] ) );
                                
                                if ( !$foundid ) {
                                    $yoastkwlist[$stepcount]['kw'] = $data['wpseo_focuskw'];
                                    $yoastkwlist[$stepcount]['lp'] = $permalink_kw;
                                    $stepcount++;
                                }
                            
                            }
                        
                        }
                    
                    }
                }
            }
        }
        // fix duplicate keywords in combined list - just to be sure
        if ( isset( $yoastkwlist ) ) {
            $yoastkwlist = array_reverse( array_values( array_column( array_reverse( $yoastkwlist ), null, 'kw' ) ) );
        }
        
        if ( is_array( $yoastkwlist ) ) {
            echo  '<div class="notices notice-info">' ;
            foreach ( $yoastkwlist as $kw ) {
                $wpdb->insert( "{$wpdb->prefix}sb2_autolink", array(
                    'keyword' => $kw['kw'],
                    'url'     => $kw['lp'],
                ), array( '%s', '%s' ) );
                $last_insert_id = $wpdb->insert_id;
                
                if ( $last_insert_id ) {
                    echo  '<p>' ;
                    echo  esc_html(
                        // translators:
                        sprintf( __( 'Added <code>%1$s</code> - links to <code>%2$s</code>.', 'seo-booster' ), $kw['kw'], $kw['lp'] )
                    ) ;
                    echo  '</p>' ;
                    // translators:
                    self::log( sprintf( __( 'Added <code>%1$s</code> - links to <code>%2$s</code>.', 'seo-booster' ), $kw['kw'], $kw['lp'] ) );
                }
            
            }
            echo  '<p><input type="button" class="button button-secondary" value="' . __( 'Click to reload page', 'seo-booster' ) . '" onClick="window.location.reload()"></p>' ;
            echo  '</div>' ;
        }
    
    }

}

$stepcount = 0;
$internal_linking = get_option( 'seobooster_internal_linking' );

if ( !$internal_linking ) {
    ?>
	<div class="notices notice-info">
		<p>
			<?php 
    esc_html_e( 'Automatic Linking is turned off! Turn on the option in the settings page.', 'seo-booster' );
    ?>
		</p>
	</div>
		<?php 
}

?>



<div id="backlinkstable-target"></div>
<form id="urls-filter" method="get">
	<input type="hidden" name="page" value="<?php 
echo esc_attr( sanitize_text_field( $_REQUEST['page'] ) );
?>" />
	<?php 

if ( isset( $_REQUEST['order'] ) ) {
    ?>
		<input type="hidden" name="order" value="<?php 
    echo esc_attr( sanitize_text_field( $_REQUEST['order'] ) );
    ?>" />
	<?php 
}

?>
	<?php 

if ( isset( $_REQUEST['orderby'] ) ) {
    ?>
		<input type="hidden" name="orderby" value="<?php 
    echo esc_attr( sanitize_text_field( $_REQUEST['orderby'] ) ) ;
    ?>" />
	<?php 
}

?>
	<?php 
$autolink_list_table->search_box( __( 'Search' ), 'seo-booster' );
$autolink_list_table->display();
?>
</form>

<div id="sb2_autolink_add">

	<h2><?php 
esc_html_e( 'Add new link', 'seo-booster' );
?></h2>

	<p><?php 
esc_html_e( 'Enter keyword and which URL the keyword should link til. Works with internal and external links.', 'seo-booster' );
?></p>

	<form method="get" id="sb2_autolink_add_form">
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><label for="newkeyword"><?php 
esc_html_e( 'Keyword', 'seo-booster' );
?></label></th>
					<td><input name="newkeyword" type="text" id="newkeyword" placeholder="<?php 
esc_html_e( 'Enter keyword', 'seo-booster' );
?>" value="" class="regular-text" required>
						<p class="description"><?php 
esc_html_e( 'Enter the phrase you want to automatically link to a URL.', 'seo-booster' );
?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="targeturl"><?php 
esc_html_e( 'Target URL', 'seo-booster' );
?></label></th>
					<td><input name="targeturl" type="url" id="targeturl" placeholder="https://" value="" class="regular-text code" required>
						<p class="description"><?php 
esc_html_e( 'Enter the URL you would like to link to.', 'seo-booster' );
?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"></th>
					<td>
						<div>
							<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php 
esc_html_e( 'Add Keyword', 'seo-booster' );
?>">
							<div id="addkwresponse"></div>
						</div>
						<div class="kwaddspinner" style="display:none;">
							<div class="bounce1"></div>
							<div class="bounce2"></div>
							<div class="bounce3"></div>
						</div>
					</td>
				</tr>
			</tbody>
		</table>
		<input name="action" type="hidden" value="ajax_add_keyword"/>
		<?php 
wp_nonce_field( 'add-keyword-nonce', '_ajax_sb2_add_keyword_nonce' );
?>
	</form>
</div>
<?php 

if ( isset( $yoastkwlist ) ) {
    $yoastkwlistcount = count( $yoastkwlist );
} else {
    $yoastkwlistcount = 0;
}


if ( $yoastkwlistcount > 0 ) {
    ?>
	<div class="notices notice-info">
		<h3>Import Focus Keywords from Yoast SEO</h3>
		<h4><?php
    // translators:
    printf( _n( '%s keyword found', '%s keywords found', $yoastkwlistcount, 'seo-booster' ), intval( $yoastkwlistcount ) );
    ?></h4>

	<p class="lead">
			<?php 
    esc_html_e( 'Import Focus Keyword from Yoast SEO plugin and turn in to links on other pages.', 'seo-booster' );
    ?>
		</p>
		<ul>
			<?php 
    foreach ( $yoastkwlist as $sa ) {
        echo  '<li>' . esc_html( $sa['kw'] ) . ' <span class="dashicons dashicons-arrow-right-alt"></span> ' . esc_url( $sa['lp'] ) . '</li>' ;
    }
    ?>
		</ul>
		<p><?php 
    esc_html_e( 'Duplicates will be ignored.', 'seo-booster' );
    ?></p>
		<form method="post" id="migratedb_form">
			<?php 
    submit_button( __( 'Convert focus keywords', 'seo-booster' ), 'primary', 'focuskw_convert' );
    wp_nonce_field( 'seobooster_convert_focuskw_autolink' );
    ?>
		</form>
	</div>
	<?php 
}

?>

<div class="notices notice-info">
	<h3><?php 
esc_html_e( 'Turn on automatic linking everywhere', 'seo-booster' );
?></h3>
	<p class="description"><?php 
esc_html_e( 'Clicking this button turns on automatic linking on all post and pages on your website. You can still edit individual pages to turn them off again.', 'seo-booster' );
?></p>
	<form method="post" id="reset_pages_autolink">
		<?php 
submit_button( __( 'Turn on autolink on all published posts and pages.', 'seo-booster' ), 'secondary', 'btn_reset_pages_autolink' );
wp_nonce_field( 'seobooster_reset_pages_autolink' );
?>
	</form>
</div>
</div>
