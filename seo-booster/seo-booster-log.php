<?php

namespace Cleverplugins\SEOBooster;

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap">
	<?php
	echo wp_kses_post(Utils::show_plugin_headline(__('Debug Log', 'seo-booster'), true));
	?>
	<div class="innercont">
		<div class="clearfix" style="width: 100%; display:flex;">
			<div class="search-box alignleft">
				<label class="screen-reader-text" for="search-input"><?php esc_html_e('Search Pages', 'seo-booster'); ?>:</label>
				<input type="search" id="search-input" name="s" value="">
				<input type="submit" id="search-submit" class="button" value="<?php esc_attr_e('Search Logs', 'seo-booster'); ?>">
			</div>
			<div class="alignright" style="margin-left:auto;">
				<button id="refresh-button" class="button button-small button-secondary alignright"><?php esc_html_e('Refresh', 'seo-booster'); ?></button>
			</div>
		</div>
		<div id="pagination-controls"></div>
		<div id="seobooster_tabulator" class="seoboosterlogpage"></div>
		<div id="results-count" style="margin-bottom: 10px;"></div>
	</div>
</div>