<?php
/**
 * Image metadata tool view (Tools page).
 *
 * @package Cleverplugins\SEOBooster\Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$docs_url = \Cleverplugins\SEOBooster\Utils::generate_cp_web_link( 'tools_image_metadata', '/docs/tools/image-metadata/' );

?>
<div class="sb-tools-tool sb-tools-image-metadata">
	<div class="sb-tools-tool-header">
		<h2><?php esc_html_e( 'Image metadata', 'seo-booster' ); ?></h2>
		<p class="sb-tools-docs-link">
			<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Documentation', 'seo-booster' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'seo-booster' ); ?></span>
			</a>
		</p>
	</div>
	<p class="description">
		<?php esc_html_e( 'Scan Media Library attachments for missing title, alt text, caption, or description. Generate metadata with AI and apply only the fields you choose.', 'seo-booster' ); ?>
	</p>
	<?php if ( $ai_available ) : ?>
	<p class="description sb-tools-vision-note">
		<?php esc_html_e( 'Each image is sent to the AI as the actual file for analysis. If the AI cannot verify the image, processing stops and no metadata is saved.', 'seo-booster' ); ?>
	</p>
	<?php endif; ?>

	<?php if ( ! $ai_available ) : ?>
		<div class="notice notice-warning inline sb-tools-ai-notice sb-tools-ai-notice-<?php echo esc_attr( $ai_notice_type ); ?>">
			<?php if ( ! empty( $ai_unavailable_message ) ) : ?>
			<p><?php echo esc_html( $ai_unavailable_message ); ?></p>
			<?php endif; ?>
			<?php if ( in_array( $ai_notice_type, array( 'deepseek_only', 'text_only_only', 'no_vision_connector', 'no_connector' ), true ) ) : ?>
			<p>
				<a href="<?php echo esc_url( $connectors_url ); ?>"><?php esc_html_e( 'Settings → Connectors', 'seo-booster' ); ?></a>
				<?php if ( \Cleverplugins\SEOBooster\Credits_Service::is_ai_provider_available() ) : ?>
				<span aria-hidden="true"> · </span>
				<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'SEO Booster Settings (Credits)', 'seo-booster' ); ?></a>
				<?php endif; ?>
			</p>
			<?php elseif ( 'support_check_failed' === $ai_notice_type ) : ?>
			<p>
				<a href="<?php echo esc_url( $connectors_url ); ?>"><?php esc_html_e( 'Settings → Connectors', 'seo-booster' ); ?></a>
			</p>
			<?php elseif ( 'disabled' === $ai_notice_type ) : ?>
			<p>
				<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'SEO Booster Settings', 'seo-booster' ); ?></a>
			</p>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'You can still scan the Media Library for missing metadata.', 'seo-booster' ); ?></p>
		</div>
	<?php endif; ?>

	<table class="form-table sb-tools-form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Scan filters', 'seo-booster' ); ?></th>
			<td>
				<div class="sb-toggle-group">
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="scan_filter" value="empty_alt" <?php checked( in_array( 'empty_alt', $scan_filters, true ) ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Empty alt text', 'seo-booster' ); ?></span>
					</label>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="scan_filter" value="empty_title" <?php checked( in_array( 'empty_title', $scan_filters, true ) ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Empty title', 'seo-booster' ); ?></span>
					</label>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="scan_filter" value="empty_caption" <?php checked( in_array( 'empty_caption', $scan_filters, true ) ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Empty caption', 'seo-booster' ); ?></span>
					</label>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="scan_filter" value="empty_description" <?php checked( in_array( 'empty_description', $scan_filters, true ) ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Empty description', 'seo-booster' ); ?></span>
					</label>
				</div>
				<p class="description sb-tools-scan-formats-note"><?php esc_html_e( 'Scan includes JPEG, PNG, and WebP only (AI-compatible formats).', 'seo-booster' ); ?></p>
				<p class="sb-tools-form-table__actions">
					<button type="button" class="button button-primary" id="sb-tools-scan-btn"><?php esc_html_e( 'Scan media library', 'seo-booster' ); ?></button>
					<span class="spinner sb-tools-scan-spinner"></span>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Apply fields', 'seo-booster' ); ?></th>
			<td id="sb-tools-apply-section">
				<p class="description"><?php esc_html_e( 'AI still generates all fields; only checked fields are saved to the attachment.', 'seo-booster' ); ?></p>
				<?php if ( ! $ai_available ) : ?>
				<div class="sb-tools-ai-warning sb-tools-ai-warning-<?php echo esc_attr( $ai_notice_type ); ?>" role="status">
					<span class="dashicons dashicons-warning" aria-hidden="true"></span>
					<div class="sb-tools-ai-warning__content">
						<?php if ( ! empty( $ai_unavailable_message ) ) : ?>
						<p class="sb-tools-ai-warning__text"><?php echo esc_html( $ai_unavailable_message ); ?></p>
						<?php endif; ?>
						<?php if ( in_array( $ai_notice_type, array( 'deepseek_only', 'text_only_only', 'no_vision_connector', 'no_connector' ), true ) ) : ?>
						<p class="sb-tools-ai-warning__actions">
							<a href="<?php echo esc_url( $connectors_url ); ?>"><?php esc_html_e( 'Settings → Connectors', 'seo-booster' ); ?></a>
							<?php if ( \Cleverplugins\SEOBooster\Credits_Service::is_ai_provider_available() ) : ?>
							<span aria-hidden="true"> · </span>
							<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'SEO Booster Settings (Credits)', 'seo-booster' ); ?></a>
							<?php endif; ?>
						</p>
						<?php elseif ( 'support_check_failed' === $ai_notice_type ) : ?>
						<p class="sb-tools-ai-warning__actions">
							<a href="<?php echo esc_url( $connectors_url ); ?>"><?php esc_html_e( 'Settings → Connectors', 'seo-booster' ); ?></a>
						</p>
						<?php elseif ( 'disabled' === $ai_notice_type ) : ?>
						<p class="sb-tools-ai-warning__actions">
							<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'SEO Booster Settings', 'seo-booster' ); ?></a>
						</p>
						<?php endif; ?>
					</div>
				</div>
				<?php else : ?>
				<p class="description sb-tools-process-hint"><?php esc_html_e( 'Run a scan, optionally select images in the preview table, then process selected or all matching results.', 'seo-booster' ); ?></p>
				<?php endif; ?>
				<div class="sb-toggle-group">
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="apply_field" value="title" <?php checked( ! empty( $apply_fields['title'] ) ); ?> <?php disabled( ! $ai_available ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Title', 'seo-booster' ); ?></span>
					</label>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="apply_field" value="alt_text" <?php checked( ! empty( $apply_fields['alt_text'] ) ); ?> <?php disabled( ! $ai_available ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Alt text', 'seo-booster' ); ?></span>
					</label>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="apply_field" value="caption" <?php checked( ! empty( $apply_fields['caption'] ) ); ?> <?php disabled( ! $ai_available ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Caption', 'seo-booster' ); ?></span>
					</label>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="apply_field" value="description" <?php checked( ! empty( $apply_fields['description'] ) ); ?> <?php disabled( ! $ai_available ); ?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php esc_html_e( 'Description', 'seo-booster' ); ?></span>
					</label>
				</div>
				<p class="sb-tools-process-actions" id="sb-tools-process-actions" hidden>
					<button type="button" class="button button-primary" id="sb-tools-process-selected" disabled><?php esc_html_e( 'Process selected', 'seo-booster' ); ?></button>
					<button type="button" class="button" id="sb-tools-process-all-matching" disabled><?php esc_html_e( 'Process all matching (0)', 'seo-booster' ); ?></button>
				</p>
			</td>
		</tr>
	</table>

	<div id="sb-tools-preview" class="sb-tools-preview" style="display:none;" aria-live="polite">
		<div class="sb-tools-preview-inner">
			<div class="sb-tools-preview-header">
				<span class="sb-tools-preview-header-status">
					<span id="sb-tools-preview-progress"></span>
					<span id="sb-tools-preview-eta" class="sb-tools-preview-eta" hidden></span>
				</span>
				<span class="sb-tools-preview-header-actions">
					<button type="button" class="button" id="sb-tools-cancel-batch-preview" hidden><?php esc_html_e( 'Cancel', 'seo-booster' ); ?></button>
					<button type="button" class="button-link sb-tools-preview-skip" id="sb-tools-preview-skip" style="display:none;"><?php esc_html_e( 'Skip', 'seo-booster' ); ?></button>
				</span>
			</div>

			<p id="sb-tools-tab-warning" class="notice notice-warning inline sb-tools-tab-warning" hidden><?php esc_html_e( 'Keep this browser tab open while images are processing. Leaving or closing the tab will stop the batch.', 'seo-booster' ); ?></p>

			<section id="sb-tools-preview-processing" class="sb-tools-preview-card sb-tools-preview-card--processing">
				<h3 class="sb-tools-preview-card-title"><?php esc_html_e( 'Currently processing', 'seo-booster' ); ?></h3>
				<div class="sb-tools-preview-body">
					<div class="sb-tools-preview-thumb-wrap">
						<img id="sb-tools-preview-processing-img" src="" alt="" />
						<span class="spinner sb-tools-preview-spinner is-active" aria-hidden="true"></span>
					</div>
					<div class="sb-tools-preview-meta">
						<h4 id="sb-tools-preview-processing-filename"></h4>
						<p class="sb-tools-preview-status">
							<span class="sb-tools-preview-waiting"><?php esc_html_e( 'Generating metadata with AI…', 'seo-booster' ); ?></span>
							<span class="sb-tools-preview-timer" id="sb-tools-preview-processing-timer" aria-live="off"></span>
						</p>
						<div class="sb-tools-preview-col sb-tools-preview-before">
							<h5><?php esc_html_e( 'Before', 'seo-booster' ); ?></h5>
							<dl id="sb-tools-preview-processing-before-list"></dl>
						</div>
					</div>
				</div>
			</section>

			<section id="sb-tools-preview-completed" class="sb-tools-preview-card sb-tools-preview-card--completed" style="display:none;">
				<h3 class="sb-tools-preview-card-title"><?php esc_html_e( 'Last processed', 'seo-booster' ); ?></h3>
				<div class="sb-tools-preview-body">
					<div class="sb-tools-preview-thumb-wrap">
						<img id="sb-tools-preview-completed-img" src="" alt="" />
					</div>
					<div class="sb-tools-preview-meta">
						<h4 id="sb-tools-preview-completed-filename"></h4>
						<p class="sb-tools-preview-timer sb-tools-preview-timer--completed" id="sb-tools-preview-completed-timer"></p>
						<div class="sb-tools-preview-columns">
							<div class="sb-tools-preview-col sb-tools-preview-before">
								<h5><?php esc_html_e( 'Before', 'seo-booster' ); ?></h5>
								<dl id="sb-tools-preview-completed-before-list"></dl>
							</div>
							<div class="sb-tools-preview-col sb-tools-preview-after">
								<h5><?php esc_html_e( 'After', 'seo-booster' ); ?></h5>
								<dl id="sb-tools-preview-completed-after-list"></dl>
							</div>
						</div>
						<p class="sb-tools-preview-error" id="sb-tools-preview-completed-error" role="alert" style="display:none;"></p>
					</div>
				</div>
			</section>

			<section id="sb-tools-preview-failed" class="sb-tools-preview-card sb-tools-preview-failed" hidden>
				<h3 class="sb-tools-preview-card-title"><?php esc_html_e( 'Failed images', 'seo-booster' ); ?></h3>
				<ul id="sb-tools-preview-failed-list" class="sb-tools-preview-failed-list"></ul>
				<p class="sb-tools-preview-failed-actions">
					<button type="button" class="button button-primary" id="sb-tools-retry-failed" hidden><?php esc_html_e( 'Retry failed (0)', 'seo-booster' ); ?></button>
				</p>
			</section>
		</div>
	</div>

	<div class="sb-tools-section sb-tools-results" id="sb-tools-results" style="display:none;">
		<h3><?php esc_html_e( 'Results', 'seo-booster' ); ?></h3>

		<div id="sb-tools-missing-files-notice" class="notice notice-warning inline sb-tools-missing-files-notice" hidden role="status"></div>

		<div class="tablenav top">
			<div class="tablenav-pages">
				<span class="displaying-num" id="sb-tools-displaying-num"></span>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped table-view-list media sb-tools-results-table">
			<thead>
				<tr>
					<td id="cb" class="manage-column column-cb check-column">
						<label class="screen-reader-text" for="sb-tools-select-all-header"><?php esc_html_e( 'Select all', 'seo-booster' ); ?></label>
						<input type="checkbox" id="sb-tools-select-all-header" />
					</td>
					<th scope="col" class="manage-column column-title column-primary"><?php esc_html_e( 'File', 'seo-booster' ); ?></th>
					<th scope="col" class="manage-column column-issues"><?php esc_html_e( 'Issues', 'seo-booster' ); ?></th>
				</tr>
			</thead>
			<tbody id="sb-tools-results-body"></tbody>
			<tfoot>
				<tr>
					<td class="manage-column column-cb check-column">
						<label class="screen-reader-text" for="sb-tools-select-all-footer"><?php esc_html_e( 'Select all', 'seo-booster' ); ?></label>
						<input type="checkbox" id="sb-tools-select-all-footer" />
					</td>
					<th scope="col" class="manage-column column-title column-primary"><?php esc_html_e( 'File', 'seo-booster' ); ?></th>
					<th scope="col" class="manage-column column-issues"><?php esc_html_e( 'Issues', 'seo-booster' ); ?></th>
				</tr>
			</tfoot>
		</table>
	</div>

	<div id="sb-tools-complete" class="sb-tools-complete notice notice-success" style="display:none;"></div>
</div>
