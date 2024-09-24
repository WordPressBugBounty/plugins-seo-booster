<?php

// don't load directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
	<h1>SEO Booster v.<?php 
echo  esc_attr( SEOBOOSTER_VERSION ) ;
?> Crawled Pages</h1>

	<div id="sb2fof" class="clearfix clear">
		<div class="lead">
			<p>See which of your pages have been crawled and by which crawlers.</p>
		</div>
	</div>
			<?php 
?>
	<form id="urls-filter" method="get">
		<input type="hidden" name="page" value="<?php 
echo esc_attr( sanitize_text_field( $_REQUEST['page'] ) );
?>" />

		<?php 
$crawled_pages_list_table->search_box( 'Search', 'search-box-id' );
$crawled_pages_list_table->display();
?>
	</form>

	<form id="resetcrawls" method="get">
		<input type="hidden" name="page" value="<?php 
echo esc_attr( sanitize_text_field( $_REQUEST['page'] ) );
?>" />
		<input type="hidden" name="action" value="deleteall" />
		<?php 
submit_button( 'Reset Crawl data', 'secondary' );
?>
	</form>

</div>
