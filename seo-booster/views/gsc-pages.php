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
		<div class="sb-filter-container">
			<div class="sb-filter-row">
				<!-- Search Section -->
				<div class="sb-filter-group sb-search-group">
					<input type="hidden" name="page" value="<?php echo esc_attr(isset($_REQUEST['page']) ? sanitize_key(wp_unslash($_REQUEST['page'])) : ''); ?>" />
					<input type="search" 
						   name="s" 
						   id="search-box-id" 
						   value="<?php echo esc_attr(isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : ''); ?>" 
						   placeholder="<?php esc_attr_e('Search keywords, pages, or dates...', 'seo-booster'); ?>" 
						   class="sb-search-input" />
				</div>
				
				<!-- Filter Options -->
				<div class="sb-filter-group sb-filter-dropdown">
					<label for="filter-options">
						<?php esc_html_e('Filter:', 'seo-booster'); ?>
					</label>
					<select name="filter_options" id="filter-options">
						<option value=""><?php esc_html_e('All Keywords', 'seo-booster'); ?></option>
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
							<?php esc_html_e('High Average Position (1-10)', 'seo-booster'); ?>
						</option>
						<option value="medium_position" <?php selected(isset($_GET['filter_options']) && $_GET['filter_options'] === 'medium_position'); ?>>
							<?php esc_html_e('Medium Average Position (11-50)', 'seo-booster'); ?>
						</option>
						<option value="low_position" <?php selected(isset($_GET['filter_options']) && $_GET['filter_options'] === 'low_position'); ?>>
							<?php esc_html_e('Low Average Position (50+)', 'seo-booster'); ?>
						</option>
					</select>
				</div>
				
				<!-- Checkboxes -->
				<div class="sb-filter-group sb-checkboxes">
					<label for="exact-match" class="sb-checkbox-label">
						<input type="checkbox" name="exact_match" id="exact-match" value="1" <?php checked(isset($_GET['exact_match']), true); ?>>
						<?php esc_html_e('Exact Match', 'seo-booster'); ?>
						<span class="dashicons dashicons-editor-help" title="<?php esc_attr_e('Search for exact keyword matches only', 'seo-booster'); ?>"></span>
					</label>
					
					<label for="traffic-30-days" class="sb-checkbox-label">
						<input type="checkbox" name="traffic_30_days" id="traffic-30-days" value="1" <?php checked(isset($_GET['traffic_30_days']), true); ?>>
						<?php esc_html_e('Recent Activity', 'seo-booster'); ?>
						<span class="dashicons dashicons-editor-help" title="<?php esc_attr_e('Show only keywords with traffic in the past 30 days', 'seo-booster'); ?>"></span>
					</label>
				</div>
				
				<!-- Search Button -->
				<div class="sb-filter-group sb-search-button">
					<input type="submit" class="button" value="<?php esc_attr_e('Search', 'seo-booster'); ?>" />
				</div>
			</div>
		</div>
		<?php
		$gsc_list_table->display();
		?>
	</form>
</div>

<script>
// Tooltip positioning fix - no movement
jQuery(document).ready(function($) {
	// Create custom tooltip element
	const tooltip = $('<div class="sb-custom-tooltip"></div>').appendTo('body');
	
	// Handle tooltip hover events
	$(document).on('mouseenter', '.sb-competition-indicator[data-tooltip-html]', function(e) {
		const html = $(this).data('tooltip-html');
		
		// Calculate position to the right of the mouse cursor
		let left = e.clientX + 15;
		let top = e.clientY - 50; // Approximate center
		
		// Show tooltip with content and position
		tooltip.html(html).css({
			left: left + 'px',
			top: top + 'px',
			display: 'block'
		});
	});
	
	$(document).on('mouseleave', '.sb-competition-indicator[data-tooltip-html]', function() {
		tooltip.hide();
	});
});
</script>