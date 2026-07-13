<?php
/**
 * Content decay tool view (Premium).
 *
 * @package Cleverplugins\SEOBooster\Tools
 *
 * @var bool $has_gsc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$docs_url = \Cleverplugins\SEOBooster\Utils::generate_cp_web_link( 'tools_content_decay', '/docs/tools/pro-tools/' );

?>
<div class="sb-tools-tool sb-tools-content-decay">
	<div class="sb-tools-tool-header">
		<h2><?php esc_html_e( 'Content decay', 'seo-booster' ); ?></h2>
		<p class="sb-tools-docs-link">
			<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Documentation', 'seo-booster' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'seo-booster' ); ?></span>
			</a>
		</p>
	</div>
	<p class="description">
		<?php esc_html_e( 'Find pages whose Google Search Console clicks fell over the last 30 days compared with the previous 30 days. Open the editor, review SEO Possibilities, or queue a fresh SEO analysis.', 'seo-booster' ); ?>
	</p>

	<?php if ( ! $has_gsc ) : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( 'Connect Google Search Console and import keyword data to use this tool.', 'seo-booster' ); ?></p>
		</div>
	<?php endif; ?>

	<table class="form-table sb-tools-form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Scan', 'seo-booster' ); ?></th>
			<td>
				<p class="description">
					<?php esc_html_e( 'Compares recent 30 days of clicks to the prior 30 days. Pages need meaningful prior clicks and recent impressions (same confidence bar as on-page GSC freshness checks).', 'seo-booster' ); ?>
				</p>
				<p class="sb-tools-form-table__actions">
					<button type="button" class="button button-primary" id="sb-tools-decay-scan-btn" <?php disabled( ! $has_gsc ); ?>>
						<?php esc_html_e( 'Scan for decaying pages', 'seo-booster' ); ?>
					</button>
					<span class="spinner sb-tools-scan-spinner"></span>
				</p>
			</td>
		</tr>
	</table>

	<div class="sb-tools-section sb-tools-results" id="sb-tools-decay-results" style="display:none;">
		<h3><?php esc_html_e( 'Results', 'seo-booster' ); ?></h3>
		<p class="displaying-num" id="sb-tools-decay-displaying-num"></p>
		<p id="sb-tools-decay-queue-status" class="sb-tools-queue-status notice notice-info inline" style="display:none;" role="status"></p>
		<p>
			<button type="button" class="button button-primary" id="sb-tools-decay-queue-all" disabled>
				<?php esc_html_e( 'Queue analysis for all matching', 'seo-booster' ); ?>
			</button>
		</p>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Content', 'seo-booster' ); ?></th>
					<th><?php esc_html_e( 'Clicks (recent / prior)', 'seo-booster' ); ?></th>
					<th><?php esc_html_e( 'Decline', 'seo-booster' ); ?></th>
					<th><?php esc_html_e( 'Analysis', 'seo-booster' ); ?></th>
				</tr>
			</thead>
			<tbody id="sb-tools-decay-results-body"></tbody>
		</table>
	</div>
</div>
