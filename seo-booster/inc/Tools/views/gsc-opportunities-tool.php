<?php
/**
 * GSC opportunities tool view (Premium).
 *
 * @package Cleverplugins\SEOBooster\Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$docs_url = \Cleverplugins\SEOBooster\Utils::generate_cp_web_link( 'tools_gsc_opportunities', '/docs/tools/pro-tools/' );

?>
<div class="sb-tools-tool sb-tools-gsc-opportunities">
	<div class="sb-tools-tool-header">
		<h2><?php esc_html_e( 'GSC opportunities', 'seo-booster' ); ?></h2>
		<p class="sb-tools-docs-link">
			<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Documentation', 'seo-booster' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'seo-booster' ); ?></span>
			</a>
		</p>
	</div>
	<p class="description">
		<?php esc_html_e( 'Find pages with striking-distance keywords, low CTR despite good rankings, or high impressions but few clicks. Batch-rewrite SEO titles and meta descriptions with AI seeded by the actual GSC query.', 'seo-booster' ); ?>
	</p>

	<?php if ( ! $has_gsc ) : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( 'Connect Google Search Console and import keyword data to use this tool.', 'seo-booster' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $seo_target ) : ?>
		<div class="notice notice-warning inline">
			<p><?php echo esc_html( \Cleverplugins\SEOBooster\SEO_Plugin_Registry::get_bulk_write_requirement_message() ); ?></p>
		</div>
	<?php else : ?>
		<div class="notice notice-success inline sb-tools-seo-plugin-detected" role="status">
			<p>
				<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<?php
				printf(
					/* translators: %s: SEO plugin name */
					esc_html__( 'Writing to %s.', 'seo-booster' ),
					esc_html( $seo_target_label )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $seo_target && ! $ai_available ) : ?>
		<div class="notice notice-warning inline sb-tools-ai-notice">
			<?php if ( ! empty( $ai_unavailable_message ) ) : ?>
			<p><?php echo esc_html( $ai_unavailable_message ); ?></p>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'You can still scan opportunities without AI.', 'seo-booster' ); ?></p>
		</div>
	<?php endif; ?>

	<table class="form-table sb-tools-form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Opportunity filters', 'seo-booster' ); ?></th>
			<td>
				<div class="sb-toggle-group">
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="gsc_opp_filter" value="striking_distance" <?php checked( in_array( 'striking_distance', $scan_filters, true ) ); ?> <?php disabled( ! $has_gsc ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Striking distance (positions 4–20)', 'seo-booster' ); ?></span>
					</label>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="gsc_opp_filter" value="low_ctr" <?php checked( in_array( 'low_ctr', $scan_filters, true ) ); ?> <?php disabled( ! $has_gsc ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Low CTR (top 10, CTR under 2%)', 'seo-booster' ); ?></span>
					</label>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="gsc_opp_filter" value="high_impressions_low_clicks" <?php checked( in_array( 'high_impressions_low_clicks', $scan_filters, true ) ); ?> <?php disabled( ! $has_gsc ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'High impressions, low clicks', 'seo-booster' ); ?></span>
					</label>
				</div>
				<p class="description"><?php esc_html_e( 'Combine any filters, then scan to preview matching pages.', 'seo-booster' ); ?></p>
				<p class="sb-tools-form-table__actions">
					<button type="button" class="button button-primary" id="sb-tools-gsc-opp-scan-btn" <?php disabled( ! $has_gsc ); ?>><?php esc_html_e( 'Scan opportunities', 'seo-booster' ); ?></button>
					<span class="spinner sb-tools-scan-spinner"></span>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Apply fields', 'seo-booster' ); ?></th>
			<td id="sb-tools-gsc-opp-apply-section">
				<p class="description"><?php esc_html_e( 'AI rewrites meta per page cluster; only checked fields are saved to your SEO plugin.', 'seo-booster' ); ?></p>
				<div class="sb-toggle-group">
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="gsc_opp_apply_field" value="title" checked <?php disabled( ! $seo_target || ! $ai_available ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'SEO title', 'seo-booster' ); ?></span>
					</label>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="gsc_opp_apply_field" value="description" checked <?php disabled( ! $seo_target || ! $ai_available ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Meta description', 'seo-booster' ); ?></span>
					</label>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" id="sb-tools-gsc-opp-overwrite" <?php disabled( ! $seo_target || ! $ai_available ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Overwrite existing values (default: fill empty fields only)', 'seo-booster' ); ?></span>
					</label>
				</div>
				<p class="sb-tools-process-actions" id="sb-tools-gsc-opp-process-actions" hidden>
					<button type="button" class="button button-primary" id="sb-tools-gsc-opp-process-selected" disabled><?php esc_html_e( 'Process selected', 'seo-booster' ); ?></button>
					<button type="button" class="button" id="sb-tools-gsc-opp-process-all" disabled><?php esc_html_e( 'Process all matching (0)', 'seo-booster' ); ?></button>
				</p>
				<?php if ( $has_revertable ) : ?>
				<p class="sb-tools-revert-actions">
					<button type="button" class="button" id="sb-tools-gsc-opp-revert-batch"><?php esc_html_e( 'Revert last bulk run', 'seo-booster' ); ?></button>
				</p>
				<?php else : ?>
				<p class="sb-tools-revert-actions" id="sb-tools-gsc-opp-revert-wrap" hidden>
					<button type="button" class="button" id="sb-tools-gsc-opp-revert-batch"><?php esc_html_e( 'Revert last bulk run', 'seo-booster' ); ?></button>
				</p>
				<?php endif; ?>
			</td>
		</tr>
	</table>

	<?php
	$prefix = 'sb-tools-gsc-opp';
	require SEOBOOSTER_PLUGINPATH . 'inc/Tools/views/partials/batch-preview.php';
	?>

	<div id="sb-tools-gsc-opp-results" class="sb-tools-results" style="display:none;">
		<h3><?php esc_html_e( 'Results', 'seo-booster' ); ?></h3>
		<p class="description"><?php esc_html_e( 'One row per page. Pages that rank for several similar queries are grouped so AI rewrites each page once using the whole cluster.', 'seo-booster' ); ?></p>
		<p class="displaying-num" id="sb-tools-gsc-opp-displaying-num"></p>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<td class="manage-column column-cb check-column"><input type="checkbox" id="sb-tools-gsc-opp-select-all" /></td>
					<th><?php esc_html_e( 'Page', 'seo-booster' ); ?></th>
					<th><?php esc_html_e( 'Opportunity', 'seo-booster' ); ?></th>
					<th><?php esc_html_e( 'GSC queries', 'seo-booster' ); ?></th>
					<th><?php esc_html_e( 'Metrics', 'seo-booster' ); ?></th>
				</tr>
			</thead>
			<tbody id="sb-tools-gsc-opp-results-body"></tbody>
		</table>
	</div>
</div>
