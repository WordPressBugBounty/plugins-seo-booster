<?php
/**
 * Shared batch processing preview panel for Tools tabs.
 *
 * @package Cleverplugins\SEOBooster\Tools
 *
 * @var string $prefix DOM id prefix (e.g. sb-tools-gsc-opp).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $prefix ) ) {
	return;
}

?>
<div id="<?php echo esc_attr( $prefix ); ?>-preview" class="sb-tools-preview" style="display:none;" aria-live="polite">
	<div class="sb-tools-preview-inner">
		<div class="sb-tools-preview-header">
			<span class="sb-tools-preview-header-status">
				<span id="<?php echo esc_attr( $prefix ); ?>-preview-progress"></span>
			</span>
			<span class="sb-tools-preview-header-actions">
				<button type="button" class="button" id="<?php echo esc_attr( $prefix ); ?>-cancel-batch" hidden><?php esc_html_e( 'Cancel', 'seo-booster' ); ?></button>
			</span>
		</div>

		<p id="<?php echo esc_attr( $prefix ); ?>-tab-warning" class="notice notice-warning inline sb-tools-tab-warning" hidden><?php esc_html_e( 'Keep this browser tab open while processing. Leaving or closing the tab will stop the batch.', 'seo-booster' ); ?></p>

		<section id="<?php echo esc_attr( $prefix ); ?>-preview-completed" class="sb-tools-preview-card sb-tools-preview-card--completed" style="display:none;">
			<h3 class="sb-tools-preview-card-title"><?php esc_html_e( 'Last processed', 'seo-booster' ); ?></h3>
			<div class="sb-tools-preview-meta">
				<h4 id="<?php echo esc_attr( $prefix ); ?>-preview-title"></h4>
				<div id="<?php echo esc_attr( $prefix ); ?>-preview-body" class="sb-tools-preview-text-body"></div>
				<p class="sb-tools-preview-error" id="<?php echo esc_attr( $prefix ); ?>-preview-error" role="alert" style="display:none;"></p>
			</div>
		</section>

		<section id="<?php echo esc_attr( $prefix ); ?>-preview-failed" class="sb-tools-preview-card sb-tools-preview-failed" hidden>
			<h3 class="sb-tools-preview-card-title"><?php esc_html_e( 'Failed items', 'seo-booster' ); ?></h3>
			<ul id="<?php echo esc_attr( $prefix ); ?>-preview-failed-list" class="sb-tools-preview-failed-list"></ul>
		</section>
	</div>
</div>

<div id="<?php echo esc_attr( $prefix ); ?>-complete" class="sb-tools-complete notice" style="display:none;" role="status"></div>
