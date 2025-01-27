<?php

namespace Cleverplugins\SEOBooster;

// don't load directly
if (!defined('ABSPATH')) {
	exit;
}

// Ensure the list table class is loaded and initialized
if (!class_exists('Cleverplugins\SEOBooster\SB_GSC_List_Table')) {
	require_once plugin_dir_path(__FILE__) . 'class-gsc-list-table.php'; // Adjust the path as needed
}

// Initialize the list table
$gsc_list_table = new SB_GSC_List_Table();

// Prepare the items for display
$gsc_list_table->prepare_items();

?>
<div class="wrap">

	<?php
	// Display the plugin headline
	echo wp_kses_post(Utils::show_plugin_headline('Google Search Console Overview', true));
	?>

	<div id="sb2fof" class="clearfix clear"></div>

	<!-- The form for searching and filtering results -->
	<form id="urls-filter" method="get" style="margin-bottom: 15px;">
		<div class="tablenav top" style="display: flex; align-items: center;">
			<!-- Filter and Search Options -->
			<div class="actions" style="flex-grow: 1; display: flex; align-items: center;">
				<!-- Filter Options -->
				<label for="filter-options" style="margin-right: 10px;">
					<?php esc_html_e('Filter by:', 'seo-booster'); ?>
				</label>
				<select name="filter_options" id="filter-options" style="margin-right: 15px;">
					<option value=""><?php esc_html_e('Select a filter', 'seo-booster'); ?></option>
					<option value="new_keywords" <?php selected(isset($_GET['filter_options']) && $_GET['filter_options'] === 'new_keywords'); ?>>
						<?php esc_html_e('New Keywords (Past 30 Days)', 'seo-booster'); ?>
					</option>
					<option value="not_seen" <?php selected(isset($_GET['filter_options']) && $_GET['filter_options'] === 'not_seen'); ?>>
						<?php esc_html_e('Keywords Not Seen (Over 30 Days)', 'seo-booster'); ?>
					</option>
					<option value="keywords_used" <?php selected(isset($_GET['filter_options']) && $_GET['filter_options'] === 'keywords_used'); ?>>
						<?php esc_html_e('Keywords Found in Content', 'seo-booster'); ?>
					</option>
					<option value="keywords_unused" <?php selected(isset($_GET['filter_options']) && $_GET['filter_options'] === 'keywords_unused'); ?>>
						<?php esc_html_e('Keywords Not Used in Content', 'seo-booster'); ?>
					</option>
					<option value="high_position" <?php selected(isset($_GET['filter_options']) && $_GET['filter_options'] === 'high_position'); ?>>
						<?php esc_html_e('High Position (1-10)', 'seo-booster'); ?>
					</option>
					<option value="medium_position" <?php selected(isset($_GET['filter_options']) && $_GET['filter_options'] === 'medium_position'); ?>>
						<?php esc_html_e('Medium Position (11-50)', 'seo-booster'); ?>
					</option>
					<option value="low_position" <?php selected(isset($_GET['filter_options']) && $_GET['filter_options'] === 'low_position'); ?>>
						<?php esc_html_e('Low Position (50+)', 'seo-booster'); ?>
					</option>
				</select>

				<!-- Search Box -->
				<input type="hidden" name="page" value="<?php echo esc_attr(isset($_REQUEST['page']) ? sanitize_key(wp_unslash($_REQUEST['page'])) : ''); ?>" />
				<?php
				// Add the search box for the list table
				$gsc_list_table->search_box(__('Search', 'seo-booster'), 'search-box-id');
				?>
				<!-- Exact Match Checkbox -->
				<div class="exact-match-checkbox" style="margin-left: 15px;">
					<label for="exact-match">
						<input type="checkbox" name="exact_match" id="exact-match" value="1" <?php checked(isset($_GET['exact_match']), true); ?>>
						<?php esc_html_e('Exact Match', 'seo-booster'); ?>
					</label>
				</div>
			</div>
		</div>
		<?php
		$gsc_list_table->display();
		?>
	</form>
</div>
<script type="text/javascript">
	document.getElementById('filter-options').addEventListener('change', function() {
		this.form.submit();
	});
</script>