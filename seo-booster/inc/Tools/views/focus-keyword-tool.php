<?php
/**
 * Focus keyword tool view (Premium).
 *
 * @package Cleverplugins\SEOBooster\Tools
 */

use Cleverplugins\SEOBooster\SEO_Plugin_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$seo_focus_supported = $seo_focus_supported ?? SEO_Plugin_Registry::supports_focus_keyword();
$gsc_settings_url    = admin_url( 'admin.php?page=sb2_settings#gsc' );
$ready_to_scan       = $seo_focus_supported && $has_gsc;
$docs_url            = \Cleverplugins\SEOBooster\Utils::generate_cp_web_link( 'tools_focus_keyword', '/docs/tools/pro-tools/' );

?>
<div class="sb-tools-tool sb-tools-focus-keyword">
	<div class="sb-tools-tool-header">
		<h2><?php esc_html_e( 'Focus keyword bulk setter', 'seo-booster' ); ?></h2>
		<p class="sb-tools-docs-link">
			<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Documentation', 'seo-booster' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'seo-booster' ); ?></span>
			</a>
		</p>
	</div>
	<p class="description">
		<?php esc_html_e( 'Uses imported Google Search Console data only — no AI required.', 'seo-booster' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'For each page without a focus keyword, suggests the highest-impression GSC query for that URL (minimum 20 impressions, position 1–50), skipping utility pages and keywords already used on another page.', 'seo-booster' ); ?>
	</p>

	<?php if ( ! $seo_focus_supported ) : ?>
		<div class="notice notice-warning inline">
			<p><?php echo esc_html( SEO_Plugin_Registry::get_focus_keyword_requirement_message() ); ?></p>
		</div>
	<?php else : ?>
		<div class="notice notice-success inline sb-tools-seo-plugin-detected" role="status">
			<p>
				<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<?php
				printf(
					/* translators: %s: SEO plugin name */
					esc_html__( 'Writing focus keywords to %s.', 'seo-booster' ),
					esc_html( $seo_target_label )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! $has_gsc ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php esc_html_e( 'Connect Google Search Console and import keyword data to use this tool.', 'seo-booster' ); ?>
				<a href="<?php echo esc_url( $gsc_settings_url ); ?>"><?php esc_html_e( 'Open GSC settings', 'seo-booster' ); ?></a>
			</p>
		</div>
	<?php elseif ( $ready_to_scan ) : ?>
		<div class="notice notice-success inline sb-tools-focus-ready" role="status">
			<p>
				<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'Ready to scan — GSC data only, no AI setup needed.', 'seo-booster' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<table class="form-table sb-tools-form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Content types', 'seo-booster' ); ?></th>
			<td>
				<div class="sb-toggle-group sb-tools-content-types">
					<?php foreach ( $selectable as $type ) : ?>
						<?php
						$object = get_post_type_object( $type );
						$label  = $object ? $object->labels->name : $type;
						?>
						<label class="sb-toggle-label">
							<div class="sb-toggle-switch">
								<input type="checkbox" name="post_type" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, $post_types, true ) ); ?> <?php disabled( ! $seo_focus_supported || ! $has_gsc ); ?> />
								<span class="sb-toggle-slider"></span>
							</div>
							<span class="sb-toggle-text"><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<p class="sb-tools-form-table__actions">
					<button type="button" class="button button-primary" id="sb-tools-focus-scan-btn" <?php disabled( ! $seo_focus_supported || ! $has_gsc ); ?>><?php esc_html_e( 'Scan for suggestions', 'seo-booster' ); ?></button>
					<span class="spinner sb-tools-scan-spinner"></span>
				</p>
			</td>
		</tr>
	</table>

	<div class="sb-tools-section">
		<p class="sb-tools-process-actions" id="sb-tools-focus-process-actions" hidden>
			<button type="button" class="button button-primary" id="sb-tools-focus-process-selected" disabled><?php esc_html_e( 'Apply selected', 'seo-booster' ); ?></button>
			<button type="button" class="button" id="sb-tools-focus-process-all" disabled><?php esc_html_e( 'Apply all matching (0)', 'seo-booster' ); ?></button>
		</p>
		<?php if ( $has_revertable ) : ?>
		<p class="sb-tools-revert-actions">
			<button type="button" class="button" id="sb-tools-focus-revert-batch"><?php esc_html_e( 'Revert last bulk run', 'seo-booster' ); ?></button>
		</p>
		<?php else : ?>
		<p class="sb-tools-revert-actions" id="sb-tools-focus-revert-wrap" hidden>
			<button type="button" class="button" id="sb-tools-focus-revert-batch"><?php esc_html_e( 'Revert last bulk run', 'seo-booster' ); ?></button>
		</p>
		<?php endif; ?>
	</div>

	<p id="sb-tools-focus-skipped-summary" class="description" style="display:none;"></p>

	<?php
	$prefix = 'sb-tools-focus';
	require SEOBOOSTER_PLUGINPATH . 'inc/Tools/views/partials/batch-preview.php';
	?>

	<div id="sb-tools-focus-results" class="sb-tools-results" style="display:none;">
		<h3><?php esc_html_e( 'Suggested focus keywords', 'seo-booster' ); ?></h3>
		<p class="displaying-num" id="sb-tools-focus-displaying-num"></p>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<td class="manage-column column-cb check-column"><input type="checkbox" id="sb-tools-focus-select-all" /></td>
					<th><?php esc_html_e( 'Content', 'seo-booster' ); ?></th>
					<th><?php esc_html_e( 'Focus keyword', 'seo-booster' ); ?></th>
					<th><?php esc_html_e( 'GSC metrics', 'seo-booster' ); ?></th>
				</tr>
			</thead>
			<tbody id="sb-tools-focus-results-body"></tbody>
		</table>
	</div>
</div>
