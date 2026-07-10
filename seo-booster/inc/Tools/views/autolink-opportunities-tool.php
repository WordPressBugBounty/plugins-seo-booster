<?php
/**
 * Autolink opportunities tool view (Premium).
 *
 * @package Cleverplugins\SEOBooster\Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cleverplugins\SEOBooster\Tools\Tools_Meta_Scanner;

$post_types_default = Tools_Meta_Scanner::get_default_post_types();
$docs_url           = \Cleverplugins\SEOBooster\Utils::generate_cp_web_link( 'tools_autolink_opportunities', '/docs/tools/pro-tools/' );

?>
<div class="sb-tools-tool sb-tools-autolink-opportunities">
	<div class="sb-tools-tool-header">
		<h2><?php esc_html_e( 'Autolink opportunities', 'seo-booster' ); ?></h2>
		<p class="sb-tools-docs-link">
			<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Documentation', 'seo-booster' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'seo-booster' ); ?></span>
			</a>
		</p>
	</div>
	<p class="description">
		<?php esc_html_e( 'Find high-value GSC queries without autolink rules, or enable autolink on high-traffic pages where it is turned off.', 'seo-booster' ); ?>
	</p>

	<?php if ( ! $autolink_on ) : ?>
		<div class="sb-tools-ai-warning sb-tools-ai-warning-config" role="alert">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<div class="sb-tools-ai-warning__content">
				<p class="sb-tools-ai-warning__text"><?php esc_html_e( 'Automatic internal linking is disabled. Turn it on before you can scan or apply autolink opportunities.', 'seo-booster' ); ?></p>
				<p class="sb-tools-ai-warning__actions">
					<a href="<?php echo esc_url( $settings_autolink_url ); ?>"><?php esc_html_e( 'Enable in Settings', 'seo-booster' ); ?></a>
				</p>
			</div>
		</div>
	<?php endif; ?>

	<table class="form-table sb-tools-form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Scan mode', 'seo-booster' ); ?></th>
			<td>
				<fieldset class="sb-tools-filters sb-tools-radio-group">
					<label><input type="radio" name="autolink_opp_filter" value="missing_rules" <?php checked( $scan_filter, 'missing_rules' ); ?> <?php disabled( ! $autolink_on ); ?> /> <?php esc_html_e( 'Missing autolink rules (GSC queries)', 'seo-booster' ); ?></label><br />
					<label><input type="radio" name="autolink_opp_filter" value="enable_high_traffic" <?php checked( $scan_filter, 'enable_high_traffic' ); ?> <?php disabled( ! $autolink_on ); ?> /> <?php esc_html_e( 'Enable autolink on high-traffic pages', 'seo-booster' ); ?></label>
				</fieldset>
			</td>
		</tr>
		<tr id="sb-tools-autolink-post-types-wrap" <?php echo $scan_filter !== 'enable_high_traffic' ? 'style="display:none;"' : ''; ?>>
			<th scope="row"><?php esc_html_e( 'Content types', 'seo-booster' ); ?></th>
			<td>
				<div class="sb-toggle-group sb-tools-content-types">
					<?php foreach ( $post_types as $type ) : ?>
						<?php $object = get_post_type_object( $type ); ?>
						<label class="sb-toggle-label">
							<div class="sb-toggle-switch">
								<input type="checkbox" name="autolink_post_type" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, $post_types_default, true ) ); ?> <?php disabled( ! $autolink_on ); ?> />
								<span class="sb-toggle-slider"></span>
							</div>
							<span class="sb-toggle-text"><?php echo esc_html( $object ? $object->labels->name : $type ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Scan', 'seo-booster' ); ?></th>
			<td>
				<p class="sb-tools-form-table__actions">
					<button type="button" class="button button-primary" id="sb-tools-autolink-scan-btn" <?php disabled( ! $autolink_on ); ?>><?php esc_html_e( 'Scan', 'seo-booster' ); ?></button>
					<span class="spinner sb-tools-scan-spinner"></span>
				</p>
				<p class="description"><?php esc_html_e( 'Revert removes autolink rules created in your last batch. Enable-autolink actions are not reverted.', 'seo-booster' ); ?></p>
			</td>
		</tr>
	</table>

	<div class="sb-tools-section">
		<p class="sb-tools-process-actions" id="sb-tools-autolink-process-actions" hidden>
			<button type="button" class="button button-primary" id="sb-tools-autolink-process-selected" disabled><?php esc_html_e( 'Apply selected', 'seo-booster' ); ?></button>
			<button type="button" class="button" id="sb-tools-autolink-process-all" disabled><?php esc_html_e( 'Apply all matching (0)', 'seo-booster' ); ?></button>
		</p>
		<?php if ( $has_revertable ) : ?>
		<p class="sb-tools-revert-actions">
			<button type="button" class="button" id="sb-tools-autolink-revert-batch"><?php esc_html_e( 'Revert last rule batch', 'seo-booster' ); ?></button>
		</p>
		<?php else : ?>
		<p class="sb-tools-revert-actions" id="sb-tools-autolink-revert-wrap" hidden>
			<button type="button" class="button" id="sb-tools-autolink-revert-batch"><?php esc_html_e( 'Revert last rule batch', 'seo-booster' ); ?></button>
		</p>
		<?php endif; ?>
	</div>

	<?php
	$prefix = 'sb-tools-autolink';
	require SEOBOOSTER_PLUGINPATH . 'inc/Tools/views/partials/batch-preview.php';
	?>

	<div id="sb-tools-autolink-results" class="sb-tools-results" style="display:none;">
		<h3><?php esc_html_e( 'Results', 'seo-booster' ); ?></h3>
		<p class="displaying-num" id="sb-tools-autolink-displaying-num"></p>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<td class="manage-column column-cb check-column"><input type="checkbox" id="sb-tools-autolink-select-all" /></td>
					<th><?php esc_html_e( 'Page', 'seo-booster' ); ?></th>
					<th><?php esc_html_e( 'Keywords to add', 'seo-booster' ); ?></th>
					<th><?php esc_html_e( 'Metrics', 'seo-booster' ); ?></th>
				</tr>
			</thead>
			<tbody id="sb-tools-autolink-results-body"></tbody>
		</table>
		<div id="sb-tools-autolink-pagination" class="sb-tools-pagination" hidden></div>
	</div>
</div>
