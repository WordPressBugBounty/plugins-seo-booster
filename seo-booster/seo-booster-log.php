<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap sb-wrap sb-dashboard sb-log-page">
	<?php
	echo wp_kses_post( Utils::show_plugin_headline( __( 'Debug Log', 'seo-booster' ), true ) );
	?>
	<section class="sb-ui-panel sb-log-results" aria-labelledby="sb-log-results-title">
		<h2 class="sb-ui-title" id="sb-log-results-title"><?php esc_html_e( 'Log entries', 'seo-booster' ); ?></h2>
		<p class="sb-ui-lead">
			<?php esc_html_e( 'Internal plugin events for troubleshooting. Search or refresh to reload the latest entries.', 'seo-booster' ); ?>
		</p>

		<div class="sb-log-toolbar">
			<div class="sb-log-toolbar__search search-box">
				<label class="screen-reader-text" for="search-input"><?php esc_html_e( 'Search Logs', 'seo-booster' ); ?></label>
				<input type="search" id="search-input" name="s" value="">
				<input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Search Logs', 'seo-booster' ); ?>">
			</div>
			<div class="sb-log-toolbar__actions">
				<button type="button" id="refresh-button" class="button button-secondary"><?php esc_html_e( 'Refresh', 'seo-booster' ); ?></button>
			</div>
		</div>
		<div id="pagination-controls" class="sb-log-pagination"></div>
		<div class="sb-log-table-wrap">
			<div id="seobooster_tabulator" class="seoboosterlogpage"></div>
		</div>
		<div id="results-count" class="sb-log-results-count"></div>
	</section>
</div>
