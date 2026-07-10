<?php
/**
 * Bulk meta title/description tool view.
 *
 * @package Cleverplugins\SEOBooster\Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$docs_url = \Cleverplugins\SEOBooster\Utils::generate_cp_web_link( 'tools_bulk_meta', '/docs/tools/bulk-meta/' );

?>
<div class="sb-tools-tool sb-tools-meta-bulk">
	<div class="sb-tools-tool-header">
		<h2><?php esc_html_e( 'Bulk meta title & description', 'seo-booster' ); ?></h2>
		<p class="sb-tools-docs-link">
			<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Documentation', 'seo-booster' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'seo-booster' ); ?></span>
			</a>
		</p>
	</div>
	<p class="description">
		<?php esc_html_e( 'Scan posts and pages for SEO title and meta description issues. Generate suggestions with AI (seeded with your GSC keywords and content) and apply only the fields you choose.', 'seo-booster' ); ?>
	</p>

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
					esc_html__( 'Writing to %s — generated titles and descriptions will be saved to this plugin’s SEO fields.', 'seo-booster' ),
					esc_html( $seo_target_label )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $seo_target && ! $ai_available ) : ?>
		<div class="notice notice-warning inline sb-tools-ai-notice sb-tools-ai-notice-<?php echo esc_attr( $ai_notice_type ); ?>">
			<?php if ( ! empty( $ai_unavailable_message ) ) : ?>
			<p><?php echo esc_html( $ai_unavailable_message ); ?></p>
			<?php endif; ?>
			<?php if ( in_array( $ai_notice_type, array( 'connector', 'disabled' ), true ) ) : ?>
			<p>
				<a href="<?php echo esc_url( $connectors_url ); ?>"><?php esc_html_e( 'Settings → Connectors', 'seo-booster' ); ?></a>
				<span aria-hidden="true"> · </span>
				<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'SEO Booster Settings', 'seo-booster' ); ?></a>
			</p>
			<?php elseif ( $ai_notice_type === 'credits' ) : ?>
			<p>
				<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'SEO Booster Settings', 'seo-booster' ); ?></a>
			</p>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'You can still scan content for issues without AI.', 'seo-booster' ); ?></p>
		</div>
	<?php endif; ?>

	<table class="form-table sb-tools-form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Content types', 'seo-booster' ); ?></th>
			<td>
				<div class="sb-toggle-group sb-tools-content-types">
					<?php foreach ( $selectable_post_types as $type ) : ?>
						<?php
						$object = get_post_type_object( $type );
						$label  = $object ? $object->labels->name : $type;
						?>
						<label class="sb-toggle-label">
							<div class="sb-toggle-switch">
								<input type="checkbox" name="post_type" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, $post_types, true ) ); ?> <?php disabled( ! $seo_target ); ?> />
								<span class="sb-toggle-slider"></span>
							</div>
							<span class="sb-toggle-text"><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Scan filters', 'seo-booster' ); ?></th>
			<td>
				<div class="sb-toggle-group">
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="scan_filter" value="missing_title" <?php checked( in_array( 'missing_title', $scan_filters, true ) ); ?> <?php disabled( ! $seo_target ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Missing SEO title', 'seo-booster' ); ?></span>
					</label>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="scan_filter" value="missing_description" <?php checked( in_array( 'missing_description', $scan_filters, true ) ); ?> <?php disabled( ! $seo_target ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Missing meta description', 'seo-booster' ); ?></span>
					</label>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="scan_filter" value="duplicate_title" <?php checked( in_array( 'duplicate_title', $scan_filters, true ) ); ?> <?php disabled( ! $seo_target ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Duplicate SEO title', 'seo-booster' ); ?></span>
					</label>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="scan_filter" value="duplicate_description" <?php checked( in_array( 'duplicate_description', $scan_filters, true ) ); ?> <?php disabled( ! $seo_target ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Duplicate meta description', 'seo-booster' ); ?></span>
					</label>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="scan_filter" value="missing_keyword" <?php checked( in_array( 'missing_keyword', $scan_filters, true ) ); ?> <?php disabled( ! $seo_target ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Missing focus/GSC keyword in meta', 'seo-booster' ); ?></span>
					</label>
				</div>
				<p class="sb-tools-form-table__actions">
					<button type="button" class="button button-primary" id="sb-tools-meta-scan-btn" <?php disabled( ! $seo_target ); ?>><?php esc_html_e( 'Scan content', 'seo-booster' ); ?></button>
					<span class="spinner sb-tools-scan-spinner"></span>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Apply fields', 'seo-booster' ); ?></th>
			<td id="sb-tools-meta-apply-section">
				<p class="description"><?php esc_html_e( 'AI generates title and description suggestions; only checked fields are saved to your SEO plugin.', 'seo-booster' ); ?></p>
				<div class="sb-toggle-group">
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="apply_field" value="title" <?php checked( ! empty( $apply_fields['title'] ) ); ?> <?php disabled( ! $seo_target || ! $ai_available ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'SEO title', 'seo-booster' ); ?></span>
					</label>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="apply_field" value="description" <?php checked( ! empty( $apply_fields['description'] ) ); ?> <?php disabled( ! $seo_target || ! $ai_available ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Meta description', 'seo-booster' ); ?></span>
					</label>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" id="sb-tools-meta-overwrite" <?php disabled( ! $seo_target || ! $ai_available ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Overwrite existing values (default: fill empty fields only)', 'seo-booster' ); ?></span>
					</label>
				</div>
				<p class="sb-tools-process-actions" id="sb-tools-meta-process-actions" hidden>
					<button type="button" class="button button-primary" id="sb-tools-meta-process-selected" disabled><?php esc_html_e( 'Process selected', 'seo-booster' ); ?></button>
					<button type="button" class="button" id="sb-tools-meta-process-all-matching" disabled><?php esc_html_e( 'Process all matching (0)', 'seo-booster' ); ?></button>
				</p>
				<?php if ( $has_revertable ) : ?>
				<p class="sb-tools-revert-actions">
					<button type="button" class="button" id="sb-tools-meta-revert-batch"><?php esc_html_e( 'Revert last bulk run', 'seo-booster' ); ?></button>
				</p>
				<?php else : ?>
				<p class="sb-tools-revert-actions" id="sb-tools-meta-revert-wrap" hidden>
					<button type="button" class="button" id="sb-tools-meta-revert-batch"><?php esc_html_e( 'Revert last bulk run', 'seo-booster' ); ?></button>
				</p>
				<?php endif; ?>
			</td>
		</tr>
	</table>

	<div id="sb-tools-meta-preview" class="sb-tools-preview" style="display:none;" aria-live="polite">
		<div class="sb-tools-preview-inner">
			<div class="sb-tools-preview-header">
				<span id="sb-tools-meta-preview-progress"></span>
				<button type="button" class="button" id="sb-tools-meta-cancel-batch" hidden><?php esc_html_e( 'Cancel', 'seo-booster' ); ?></button>
			</div>
			<p id="sb-tools-meta-tab-warning" class="notice notice-warning inline sb-tools-tab-warning" hidden><?php esc_html_e( 'Keep this browser tab open while content is processing. Leaving or closing the tab will stop the batch.', 'seo-booster' ); ?></p>
			<section id="sb-tools-meta-preview-completed" class="sb-tools-preview-card sb-tools-preview-card--completed" style="display:none;">
				<h3 class="sb-tools-preview-card-title"><?php esc_html_e( 'Last processed', 'seo-booster' ); ?></h3>
				<div class="sb-tools-preview-meta">
					<h4 id="sb-tools-meta-preview-title"></h4>
					<div class="sb-tools-preview-columns">
						<div class="sb-tools-preview-col sb-tools-preview-before">
							<h5><?php esc_html_e( 'Before', 'seo-booster' ); ?></h5>
							<dl id="sb-tools-meta-preview-before-list"></dl>
						</div>
						<div class="sb-tools-preview-col sb-tools-preview-after">
							<h5><?php esc_html_e( 'After', 'seo-booster' ); ?></h5>
							<dl id="sb-tools-meta-preview-after-list"></dl>
						</div>
					</div>
					<p class="sb-tools-preview-error" id="sb-tools-meta-preview-error" role="alert" style="display:none;"></p>
				</div>
			</section>
			<section id="sb-tools-meta-preview-failed" class="sb-tools-preview-card sb-tools-preview-failed" hidden>
				<h3 class="sb-tools-preview-card-title"><?php esc_html_e( 'Failed items', 'seo-booster' ); ?></h3>
				<ul id="sb-tools-meta-preview-failed-list" class="sb-tools-preview-failed-list"></ul>
				<p><button type="button" class="button button-primary" id="sb-tools-meta-retry-failed" hidden><?php esc_html_e( 'Retry failed (0)', 'seo-booster' ); ?></button></p>
			</section>
		</div>
	</div>

	<div class="sb-tools-section sb-tools-results" id="sb-tools-meta-results" style="display:none;">
		<h3><?php esc_html_e( 'Results', 'seo-booster' ); ?></h3>
		<div class="tablenav top"><div class="tablenav-pages"><span class="displaying-num" id="sb-tools-meta-displaying-num"></span></div></div>
		<table class="wp-list-table widefat fixed striped table-view-list sb-tools-results-table">
			<thead>
				<tr>
					<td class="manage-column column-cb check-column"><input type="checkbox" id="sb-tools-meta-select-all-header" /></td>
					<th scope="col" class="manage-column column-title column-primary"><?php esc_html_e( 'Content', 'seo-booster' ); ?></th>
					<th scope="col" class="manage-column column-issues"><?php esc_html_e( 'Issues', 'seo-booster' ); ?></th>
				</tr>
			</thead>
			<tbody id="sb-tools-meta-results-body"></tbody>
		</table>
	</div>

	<div id="sb-tools-meta-complete" class="sb-tools-complete notice notice-success" style="display:none;"></div>
</div>
