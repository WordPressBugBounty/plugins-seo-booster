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
?>
		<h1><?php 
esc_html_e( 'Lost Traffic', 'seo-booster' );
?> - SEO Booster v.<?php 
echo  esc_attr( SEOBOOSTER_VERSION ) ;
?></h1>
		<?php 
?>
	</div>
	<div id="sb2forgotten" class="clearfix clear">
		<div class="lead">

			<p class="stat headline"><?php 
esc_html_e( 'Pages that have not received search engine traffic for a while.', 'seo-booster' );
?></p>
		</div>
	</div>

	<div id="wp_pointer-target"></div>
	<form id="urls-filter" method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( sanitize_text_field( $_REQUEST['page'] ) );?>" />
		<?php
$test_list_table->display();
?>
	</form>

</div>
