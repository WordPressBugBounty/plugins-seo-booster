<?php
/**
 * Needs analysis tool view (Premium).
 *
 * @package Cleverplugins\SEOBooster\Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cleverplugins\SEOBooster\Tools\Tools_Meta_Scanner;

$post_types = Tools_Meta_Scanner::get_selectable_post_types();
$docs_url   = \Cleverplugins\SEOBooster\Utils::generate_cp_web_link( 'tools_needs_analysis', '/docs/tools/pro-tools/' );

?>
<div class="sb-tools-tool sb-tools-needs-analysis">
	<div class="sb-tools-tool-header">
		<h2><?php esc_html_e( 'Needs analysis', 'seo-booster' ); ?></h2>
		<p class="sb-tools-docs-link">
			<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Documentation', 'seo-booster' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'seo-booster' ); ?></span>
			</a>
		</p>
	</div>
	<p class="description">
		<?php esc_html_e( 'Find content that was never analyzed, has stale SEO analysis, or still shows issues from your last run. Queue a bulk re-analysis using the existing SEO analysis engine.', 'seo-booster' ); ?>
	</p>

	<table class="form-table sb-tools-form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Scan filter', 'seo-booster' ); ?></th>
			<td>
				<fieldset class="sb-tools-filters sb-tools-radio-group">
					<label><input type="radio" name="needs_filter" value="never_analyzed" checked /> <?php esc_html_e( 'Never analyzed', 'seo-booster' ); ?></label><br />
					<label><input type="radio" name="needs_filter" value="stale" /> <?php esc_html_e( 'Stale analysis (90+ days)', 'seo-booster' ); ?></label><br />
					<label><input type="radio" name="needs_filter" value="has_issues" /> <?php esc_html_e( 'Has open issues from last analysis', 'seo-booster' ); ?></label>
				</fieldset>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Content types', 'seo-booster' ); ?></th>
			<td>
				<div class="sb-toggle-group sb-tools-content-types">
					<?php foreach ( $post_types as $type ) : ?>
						<?php $object = get_post_type_object( $type ); ?>
						<label class="sb-toggle-label">
							<div class="sb-toggle-switch">
								<input type="checkbox" name="needs_post_type" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, Tools_Meta_Scanner::get_default_post_types(), true ) ); ?> />
								<span class="sb-toggle-slider"></span>
							</div>
							<span class="sb-toggle-text"><?php echo esc_html( $object ? $object->labels->name : $type ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<p class="sb-tools-form-table__actions">
					<button type="button" class="button button-primary" id="sb-tools-needs-scan-btn"><?php esc_html_e( 'Scan', 'seo-booster' ); ?></button>
					<span class="spinner sb-tools-scan-spinner"></span>
				</p>
			</td>
		</tr>
	</table>

	<div class="sb-tools-section sb-tools-results" id="sb-tools-needs-results" style="display:none;">
		<h3><?php esc_html_e( 'Results', 'seo-booster' ); ?></h3>
		<p class="displaying-num" id="sb-tools-needs-displaying-num"></p>
		<p id="sb-tools-needs-queue-status" class="sb-tools-queue-status notice notice-info inline" style="display:none;" role="status"></p>
		<p>
			<button type="button" class="button button-primary" id="sb-tools-needs-queue-all" disabled><?php esc_html_e( 'Queue analysis for all matching', 'seo-booster' ); ?></button>
		</p>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Content', 'seo-booster' ); ?></th>
					<th><?php esc_html_e( 'Status', 'seo-booster' ); ?></th>
					<th><?php esc_html_e( 'Issues', 'seo-booster' ); ?></th>
				</tr>
			</thead>
			<tbody id="sb-tools-needs-results-body"></tbody>
		</table>
	</div>
</div>
