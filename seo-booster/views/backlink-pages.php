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
esc_html_e( 'Backlinks', 'seo-booster' );
?> - SEO Booster v.<?php 
echo  esc_html( SEOBOOSTER_VERSION ) ;
?></h1>
<?php 
?>
</div>
<div id="sb2fof" class="clearfix clear">
<p class="lead"><?php 
esc_html_e( 'Who links to you - These backlinks have been detected via visits from other websites that links to you.', 'seo-booster' );
?></p>
<p><?php 
esc_html_e( 'These visits can be faked, you need to verify each link exists. The PRO version does this for you.', 'seo-booster' );
?></p>
</div>


<div id="backlinkstable-target"></div>
<form id="urls-filter" method="get">
<input type="hidden" name="page" value="<?php 
echo esc_attr( sanitize_text_field( $_REQUEST['page'] ) ) ;
?>" />
<?php 
$backlink_pages_list_table->search_box( __( 'Search' ), 'seo-booster' );
$backlink_pages_list_table->display();
?>
<div>
<p><?php 
esc_html_e( 'Note - Backlinks you delete from this list will reappear next time someone visits again.', 'seo-booster' );
?></p>
<p><?php esc_html_e( 'You can filter out specific domains in the settings.', 'seo-booster' ); ?></p>
</div>
</form>



<h3>Export Backlinks</h3>	
<form id="exportbacklinks" method="get">
<input type="hidden" name="page" value="<?php echo esc_attr( sanitize_text_field( $_REQUEST['page'] ) ); ?>" />
<input type="hidden" name="action" value="sbp_backlinks_export_csv" />
<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'sbp-nonce' ) ); ?>" />
<?php submit_button( 'Export to .csv', 'secondary' ); ?>
</form>
<div class="about__section has-2-columns">
<div class="column">
<h3>Top 10 most linked to pages</h3>
<p>Shows the most linked to URLs on your website.</p>
</br>
<table class="wp-list-table widefat fixed striped table-view-list urls">
<thead><tr><th>Landing Page</th><th>Incoming Links</th></tr></thead>
<tbody>
<?php 
$bls = $wpdb->get_results( "SELECT lp, COUNT(lp) as CNT FROM {$wpdb->prefix}sb2_bl GROUP by lp ORDER BY `CNT` DESC LIMIT 10;" );

if ( $bls ) {
    foreach ( $bls as $bl ) {
        ?><tr><td><a href="<?php echo esc_url( site_url( $bl->lp ) ); ?>" target="_blank" rel="noopener"><?php echo esc_attr( $bl->lp ); ?></a></td><td><?php 
        echo esc_html( number_format_i18n( $bl->CNT ) ); ?></td></tr>
		<?php 
    }
} else {
    // no backlinks detected
    ?>
	<tr><td colspan="2">No backlinks detected yet, please check again later.</td></tr>
	<?php 
}

?>
</tbody>

</table>

</div>
<div class="column">
<h3>Top 10 Referring Domains</h3>
<p class="lead>">The websites that links the most to you.</p>
</br>
<table class="wp-list-table widefat fixed striped table-view-list urls">
<thead><tr><th>Domain</th><th>Detected links</th></tr></thead>
<tbody>
<?php 
$bls = $wpdb->get_results( "SELECT domain, COUNT(domain) as CNT FROM {$wpdb->prefix}sb2_bl GROUP by domain ORDER BY `CNT` DESC LIMIT 10;" );

if ( $bls ) {
    foreach ( $bls as $bl ) {
        ?>
		<tr><td><?php 
        echo  esc_attr( $bl->domain ) ;
        ?></td><td><?php 
        echo  number_format_i18n( $bl->CNT ) ;
        ?></td></tr>
		<?php 
    }
} else {
    // no backlinks detected
    ?>
	<tr><td colspan="2">No backlinks detected yet, please check again later.</td></tr>
	<?php 
}

?>
</tbody>

</table>

</div>
</div>
</div>
