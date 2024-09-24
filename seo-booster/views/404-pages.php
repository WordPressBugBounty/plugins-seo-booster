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
_e( '404 Errors', 'seo-booster' );
?> - SEO Booster v.<?php 
echo  SEOBOOSTER_VERSION ;
?></h1>
</div><!-- .sbpheader -->

<div id="sb2fof" class="clearfix clear">
			<?php 
?>
</div>
<?php 
$fof_monitoring = get_option( 'seobooster_fof_monitoring' );

if ( !$fof_monitoring ) {
    ?>
	<div class="notice notice-warning"><p><?php 
    _e( '404 Error monitoring is turned off.', 'seo-booster' );
    ?> <a href="<?php 
    echo  admin_url( 'admin.php?page=sb2_settings' ) ;
    ?>"><?php 
    _e( 'Turn on in the settings', 'seo-booster' );
    ?></a></p></div>
<div id="404table-target"></div>
	<?php 
}

?>
<form id="urls-filter" method="get">
	<input type="hidden" name="page" value="<?php 
echo  esc_attr( sanitize_text_field( $_REQUEST['page'] ) );
?>" />
	<?php 
$fof_list_table->search_box( __( 'Search', 'seo-booster' ), 'search-box-id' );
?>
	<?php 
$fof_list_table->display();
?>
</form>

<form id="reset404s" method="get">
	<input type="hidden" name="page" value="<?php 
echo esc_attr( sanitize_text_field( $_REQUEST['page'] ) );
?>" />
	<input type="hidden" name="action" value="deleteall" />
	<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'sbp-nonce' ) ); ?>" />
	<?php 
submit_button( __( 'Reset 404 Errors', 'seo-booster' ), 'secondary' );


?>
</form>

<form id="export404s" method="get" class="profeature">
	<input type="hidden" name="page" value="<?php echo esc_attr( sanitize_text_field( $_REQUEST['page'] ));?>" />
	<input type="hidden" name="action" value="sbp_404_export_csv" />
	<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'sbp-nonce' ) ); ?>" />
	<?php 
submit_button( __( 'Export to .csv', 'seo-booster' ), 'secondary' );
?>
</form>
<?php 
// List of args to ignore in query strings on 404 pages.
$ignored_parts = $ignore_args = array( 'wordfence_lh' );
// Gets the other parts from settings page
$seobooster_fof_ignore = get_option( 'seobooster_fof_ignore' );
$seobooster_fof_ignore = explode( ',', $seobooster_fof_ignore );

if ( is_array( $seobooster_fof_ignore ) ) {
    ?>
	<p><?php 
    _e( 'You are ignoring URLs that match:', 'seo-booster' );
    ?></p>
	<?php 
    $step = 0;
    $ipcount = count( $seobooster_fof_ignore );
    foreach ( $seobooster_fof_ignore as $ip ) {
        echo  '<code>' . esc_attr( $ip ) . '</code>' ;
        $step++;
        if ( $step < $ipcount ) {
            echo  ', ';
        }
    }
}

?>
</div>
