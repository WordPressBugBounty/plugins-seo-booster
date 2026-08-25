<?php

namespace Cleverplugins\SEOBooster;

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure the list table class is loaded and initialized
if ( ! class_exists( 'Cleverplugins\SEOBooster\SB_GSC_List_Table' ) ) {
	require_once SEOBOOSTER_PLUGINPATH . 'inc/SB_GSC_List_Table.php';
}

// Initialize the list table
$gsc_list_table = new SB_GSC_List_Table();

// Prepare the items for display
$gsc_list_table->prepare_items();

?>
<div class="wrap sb-wrap sb-dashboard sb-gsc-page">

	<?php
	echo wp_kses_post( Utils::show_plugin_headline( 'Google Search Console Overview', true ) );
	?>

	<section class="sb-ui-panel sb-gsc-results" aria-labelledby="sb-gsc-results-title">
		<h2 class="sb-ui-title" id="sb-gsc-results-title"><?php esc_html_e( 'Keywords and pages', 'seo-booster' ); ?></h2>
		<p class="sb-ui-lead">
			<?php esc_html_e( 'Search and filter keywords imported from Google Search Console.', 'seo-booster' ); ?>
		</p>

	<!-- The form for searching and filtering results -->
	<form id="urls-filter" method="get">
		<div class="sb-filter-container">
			<div class="sb-filter-row">
				<!-- Search Section -->
				<div class="sb-filter-group sb-search-group">
					<input type="hidden" name="page" value="<?php echo esc_attr( isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '' ); ?>" />
					<input type="hidden" name="sb_gsc_filtered" value="1" />
					<input type="search" 
							name="s" 
							id="search-box-id" 
							value="<?php echo esc_attr( isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '' ); ?>" 
							placeholder="<?php esc_attr_e( 'Search keywords, pages, or dates...', 'seo-booster' ); ?>" 
							class="sb-search-input" />
				</div>
				
				<!-- Filter Options -->
				<div class="sb-filter-group sb-filter-dropdown">
					<label for="filter-options">
						<?php esc_html_e( 'Filter:', 'seo-booster' ); ?>
					</label>
					<select name="filter_options" id="filter-options">
						<option value=""><?php esc_html_e( 'All Keywords', 'seo-booster' ); ?></option>
						<option value="new_keywords" <?php selected( isset( $_GET['filter_options'] ) && 'new_keywords' === $_GET['filter_options'] ); ?>>
							<?php esc_html_e( 'New Keywords (Past 30 Days)', 'seo-booster' ); ?>
						</option>
						<option value="not_seen" <?php selected( isset( $_GET['filter_options'] ) && 'not_seen' === $_GET['filter_options'] ); ?>>
							<?php esc_html_e( 'Keywords Not Seen (Over 30 Days)', 'seo-booster' ); ?>
						</option>
						<option value="keywords_used" <?php selected( isset( $_GET['filter_options'] ) && 'keywords_used' === $_GET['filter_options'] ); ?>>
							<?php esc_html_e( 'Keywords Found in Content', 'seo-booster' ); ?>
						</option>
						<option value="keywords_unused" <?php selected( isset( $_GET['filter_options'] ) && 'keywords_unused' === $_GET['filter_options'] ); ?>>
							<?php esc_html_e( 'Keywords Not Used in Content', 'seo-booster' ); ?>
						</option>
						<option value="high_position" <?php selected( isset( $_GET['filter_options'] ) && 'high_position' === $_GET['filter_options'] ); ?>>
							<?php esc_html_e( 'High Average Position (1-10)', 'seo-booster' ); ?>
						</option>
						<option value="medium_position" <?php selected( isset( $_GET['filter_options'] ) && 'medium_position' === $_GET['filter_options'] ); ?>>
							<?php esc_html_e( 'Medium Average Position (11-50)', 'seo-booster' ); ?>
						</option>
						<option value="low_position" <?php selected( isset( $_GET['filter_options'] ) && 'low_position' === $_GET['filter_options'] ); ?>>
							<?php esc_html_e( 'Low Average Position (50+)', 'seo-booster' ); ?>
						</option>
					</select>
				</div>
				
				<!-- Checkboxes -->
				<div class="sb-filter-group sb-checkboxes">
					<label for="exact-match" class="sb-checkbox-label">
						<input type="checkbox" name="exact_match" id="exact-match" value="1" <?php checked( isset( $_GET['exact_match'] ), true ); ?>>
						<?php esc_html_e( 'Exact Match', 'seo-booster' ); ?>
						<span class="dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Search for exact keyword matches only', 'seo-booster' ); ?>"></span>
					</label>
				</div>
				
				<!-- Search Button -->
				<div class="sb-filter-group sb-search-button">
					<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'seo-booster' ); ?>" />
				</div>
			</div>
		</div>
		<div class="sb-gsc-table-wrap">
		<?php
		$gsc_list_table->display();
		?>
		</div>
	</form>
	</section>
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