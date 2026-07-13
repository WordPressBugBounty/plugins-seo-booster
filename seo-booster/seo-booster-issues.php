<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEO Possibilities Admin Page
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */

// Include required classes
require_once SEOBOOSTER_PLUGINPATH . 'inc/SEO_Issues_Manager.php';
require_once SEOBOOSTER_PLUGINPATH . 'inc/SEO_Issues_List_Table.php';
require_once SEOBOOSTER_PLUGINPATH . 'inc/SEO_Issues_Ajax.php';
require_once SEOBOOSTER_PLUGINPATH . 'inc/SEO_Sitewide_Analysis.php';

/**
 * Render the SEO Possibilities admin page.
 *
 * @since 6.1.26
 * @return void
 */
function render_seo_issues_page() {
	// Create list table instance
	$list_table = new SEO_Issues_List_Table();
	$list_table->prepare_items();

	// Get current stats
	$stats = SEO_Issues_Manager::get_analysis_stats();

	// Get sitewide stats
	$sitewide_stats  = SEO_Issues_Manager::get_sitewide_stats();
	$sitewide_issues = SEO_Issues_Manager::get_sitewide_issues();

	// Auto-run sitewide analysis on first visit if it hasn't been run yet
	$sitewide_analysis_run = get_option( 'sb_sitewide_analysis_run', false );
	if ( ! $sitewide_analysis_run && empty( $sitewide_issues ) ) {
		// Run sitewide analysis automatically
		try {
			$sitewide_analysis = new SEO_Sitewide_Analysis();
			$results           = $sitewide_analysis->analyze();
			SEO_Issues_Manager::save_sitewide_analysis_to_db( $results );
			update_option( 'sb_sitewide_analysis_run', true );
			// Refresh stats after running
			$sitewide_stats  = SEO_Issues_Manager::get_sitewide_stats();
			$sitewide_issues = SEO_Issues_Manager::get_sitewide_issues();
		} catch ( \Exception $e ) {
			Utils::log( 'Auto sitewide analysis failed: ' . $e->getMessage(), 2 );
		}
	}

	?>
	<div class="wrap sb-seo-issues-page">
		<h1><?php _e( 'SEO Possibilities', 'seo-booster' ); ?></h1>
		
		<style>
		.row-actions {
			visibility: hidden;
		}
		tr:hover .row-actions {
			visibility: visible;
		}
		.check-column {
			width: 2.2em !important;
		}
		
		/* Force hide modal and show inline progress */
		#sb-progress-modal {
			display: none !important;
		}
		
		#sb-current-analysis {
			display: block;
			margin: 15px 0;
		}
		
		.sb-inline-progress {
			background: #f9f9f9;
			border: 1px solid #ddd;
			border-radius: 4px;
			padding: 15px;
			margin: 10px 0;
		}
		
		.sb-progress-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 15px;
		}
		
		.sb-progress-header strong {
			color: #0073aa;
		}
		
		.sb-progress-bar-container {
			margin: 15px 0;
		}
		
		.sb-progress-bar {
			background: #e1e1e1;
			border-radius: 3px;
			height: 20px;
			overflow: hidden;
			position: relative;
		}
		
		.sb-progress-fill {
			background: linear-gradient(90deg, #0073aa, #005177);
			height: 100%;
			transition: width 0.3s ease;
			border-radius: 3px;
		}
		
		.sb-progress-text {
			text-align: center;
			margin-top: 5px;
			font-weight: bold;
			color: #0073aa;
		}
		
		.sb-progress-stats {
			display: flex;
			flex-direction: row;
			gap: 15px;
			margin: 15px 0;
			flex-wrap: nowrap;
			align-items: center;
			justify-content: space-between;
			background: #f9f9f9;
			padding: 10px;
			border-radius: 4px;
			border: 1px solid #ddd;
		}
		
		.sb-stat-item {
			display: flex;
			flex-direction: column;
			align-items: center;
			min-width: 60px;
			text-align: center;
			flex: 1;
			padding: 5px;
			background: white;
			border-radius: 3px;
			border: 1px solid #e0e0e0;
		}
		
		.sb-stat-label {
			font-size: 12px;
			color: #666;
			margin-bottom: 5px;
		}
		
		.sb-stat-value {
			font-size: 18px;
			font-weight: bold;
			color: #0073aa;
		}
		
		.sb-current-url-display {
			margin-top: 15px;
			padding: 10px;
			background: #fff;
			border: 1px solid #e1e1e1;
			border-radius: 3px;
		}
		
		.sb-current-url {
			font-family: monospace;
			background: #f1f1f1;
			padding: 2px 4px;
			border-radius: 2px;
		}
		
		.sb-analysis-toolbar {
			background: #fff;
			padding: 15px;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			display: flex;
			gap: 10px;
			align-items: center;
		}
		
		.sb-analysis-toolbar button:disabled {
			opacity: 0.5;
			cursor: not-allowed;
		}
		.sb-seo-score {
			font-weight: bold;
			padding: 2px 6px;
			border-radius: 3px;
			font-size: 12px;
			display: inline-block;
		}
		
		.sb-seo-score-good {
			background-color: #d4edda;
			color: #155724;
		}
		
		.sb-seo-score-medium {
			background-color: #fff3cd;
			color: #856404;
		}
		
		.sb-seo-score-poor {
			background-color: #f8d7da;
			color: #721c24;
		}
		
		.sb-seo-score-null {
			color: #6c757d;
		}
		
		.column-score {
			text-align: center;
			width: 100px;
		}
		</style>

		<!-- Sitewide SEO Checks Section -->
		<div class="sb-sitewide-section" style="margin-bottom: 30px;">
			<h2><?php _e( 'Sitewide SEO Checks', 'seo-booster' ); ?></h2>
			<p class="description"><?php _e( 'These checks analyze your entire site by examining the homepage and common sitewide elements.', 'seo-booster' ); ?></p>
			
			<div class="sb-sitewide-stats" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; margin: 15px 0;">
				<h3 class="sb-collapsible-header" style="margin: 0; border-bottom: 1px solid #c3c4c7;">
					<span class="sb-collapse-icon dashicons <?php echo ! empty( $sitewide_issues ) ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2'; ?>"></span>
					<strong><?php _e( 'Sitewide Stats:', 'seo-booster' ); ?></strong>
					<?php
					printf(
						__( '%1$d total issues (%2$d errors, %3$d warnings, %4$d good practices)', 'seo-booster' ),
						$sitewide_stats['total_issues'],
						$sitewide_stats['error'],
						$sitewide_stats['warning'],
						$sitewide_stats['good']
					);
					?>
				</h3>
				<div class="sb-collapsible-content" style="<?php echo ! empty( $sitewide_issues ) ? 'display: block;' : 'display: none;'; ?> padding: 15px;">
					<div style="display: flex; gap: 20px; align-items: center; margin-bottom: 15px;">
						<button type="button" class="button button-primary" id="sb-run-sitewide-analysis">
							<span class="dashicons dashicons-update"></span>
							<?php _e( 'Run Sitewide Analysis', 'seo-booster' ); ?>
						</button>
					</div>
					
					<div id="sb-sitewide-issues-list" class="sb-sitewide-issues-list">
						<?php if ( empty( $sitewide_issues ) ) : ?>
							<p><?php _e( 'No sitewide analysis has been run yet. Click "Run Sitewide Analysis" to check your site.', 'seo-booster' ); ?></p>
						<?php else : ?>
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th style="width: 100px;"><?php _e( 'Severity', 'seo-booster' ); ?></th>
										<th><?php _e( 'Issue', 'seo-booster' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $sitewide_issues as $issue ) : ?>
										<tr>
											<td>
												<?php
												$severity_class = 'sb-severity-' . esc_attr( $issue->severity );
												$severity_label = ucfirst( $issue->severity );
												echo '<span class="' . esc_attr( $severity_class ) . '" style="padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">' . esc_html( $severity_label ) . '</span>';
												?>
											</td>
											<td><?php echo esc_html( $issue->message ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<!-- Quick Stats -->
		<div class="notice seobooster-notice notice-info">
			<p>
				<strong><?php _e( 'Quick Stats:', 'seo-booster' ); ?></strong>
				<?php
				printf(
					__( '%1$d total possibilities (%2$d critical, %3$d high, %4$d medium) • %5$d URLs analyzed', 'seo-booster' ),
					$stats['total_issues'],
					$stats['critical'],
					$stats['high'],
					$stats['medium'],
					$stats['total_analyzed']
				);
				?>
			</p>
		</div>

		<!-- Analysis Toolbar -->
		<div class="sb-analysis-toolbar" style="margin: 15px 0;">
			<button type="button" class="button button-primary" id="sb-analyze-remaining">
				<?php _e( 'Analyze Remaining', 'seo-booster' ); ?>
			</button>
			<button type="button" class="button" id="sb-stop-analysis" style="display: none;">
				<?php _e( 'Stop Analysis', 'seo-booster' ); ?>
			</button>
		</div>
		
		<!-- Current Analysis Display - Right beneath the notice, above the table -->
		<div id="sb-current-analysis" class="sb-current-analysis" style="display: none;">
			<!-- Inline progress will be inserted here by JavaScript -->
		</div>


		<!-- Possibilities by URL View -->
		<div id="sb-urls-view" class="sb-view-content">
			<form method="get" id="sb-possibilities-filter-form">
				<input type="hidden" name="page" value="sb2_seo_issues" />
				
				<?php
				$list_table->display();
				?>
			</form>
		</div>

		<!-- URL Possibilities Detail Container -->
		<div id="sb-url-possibilities-detail" style="display: none;">
			<!-- Possibilities for specific URL will be loaded here -->
		</div>

		<!-- Progress Modal (Hidden - using inline progress instead) -->
		<div id="sb-progress-modal" class="sb-issues-modal-overlay" style="display: none !important;">
			<div class="sb-issues-modal-content">
				<div class="sb-issues-modal-header">
					<h3><?php _e( 'Analyzing Content', 'seo-booster' ); ?></h3>
					<button type="button" class="sb-issues-modal-close">&times;</button>
				</div>
				
				<div class="sb-issues-modal-body">
					<div class="sb-progress-bar-container">
						<div class="sb-progress-bar">
							<div class="sb-progress-fill" id="sb-progress-fill" style="width: 0%;"></div>
						</div>
						<div class="sb-progress-text" id="sb-progress-text">0%</div>
					</div>
					
					<div class="sb-progress-stats">
						<div class="sb-stat-item">
							<span class="sb-stat-label"><?php _e( 'Analyzed:', 'seo-booster' ); ?></span>
							<span class="sb-stat-value" id="sb-analyzed-count">0</span>
						</div>
						<div class="sb-stat-item">
							<span class="sb-stat-label"><?php _e( 'Remaining:', 'seo-booster' ); ?></span>
							<span class="sb-stat-value" id="sb-remaining-count">0</span>
						</div>
						<div class="sb-stat-item">
							<span class="sb-stat-label"><?php _e( 'Possibilities Found:', 'seo-booster' ); ?></span>
							<span class="sb-stat-value" id="sb-possibilities-count">0</span>
						</div>
					</div>
					
			
				</div>
				
				<div class="sb-issues-modal-footer">
					<button type="button" class="button" id="sb-cancel-analysis"><?php _e( 'Cancel', 'seo-booster' ); ?></button>
				</div>
			</div>
		</div>
	</div>

	<?php
}
