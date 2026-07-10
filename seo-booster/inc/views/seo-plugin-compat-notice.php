<?php
/**
 * SEO plugin compatibility notice (shared across admin screens).
 *
 * @package Cleverplugins\SEOBooster
 */

use Cleverplugins\SEOBooster\SEO_Plugin_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tier               = $seo_tier ?? SEO_Plugin_Registry::get_integration_tier();
$seo_target         = $seo_target ?? SEO_Plugin_Registry::supports_bulk_write();
$seo_target_label   = $seo_target_label ?? SEO_Plugin_Registry::get_active_label();
$multi_plugin       = SEO_Plugin_Registry::has_multiple_active_plugins();
$active_labels      = SEO_Plugin_Registry::get_active_labels();
$notice_class       = 'notice-success';
$compat_notice_class = 'sb-tools-seo-compat notice inline';

if ( $tier === 'analysis_only' || ! $seo_target ) {
	$notice_class = 'notice-warning';
}

if ( $tier === 'partial' ) {
	$notice_class = 'notice-info';
}

?>
<div class="<?php echo esc_attr( $compat_notice_class . ' ' . $notice_class ); ?>" role="status">
	<?php if ( $multi_plugin ) : ?>
		<p>
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<?php
			printf(
				/* translators: 1: comma-separated plugin names, 2: active plugin name */
				esc_html__( 'Multiple SEO plugins detected (%1$s). SEO Booster writes to %2$s (priority order).', 'seo-booster' ),
				esc_html( implode( ', ', $active_labels ) ),
				'<strong>' . esc_html( $seo_target_label ) . '</strong>'
			);
			?>
		</p>
	<?php endif; ?>

	<?php if ( $tier === 'full' && $seo_target ) : ?>
		<p>
			<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
			<?php
			printf(
				/* translators: %s: SEO plugin name */
				esc_html__( 'Writing SEO titles, descriptions, and focus keywords to %s.', 'seo-booster' ),
				'<strong>' . esc_html( $seo_target_label ) . '</strong>'
			);
			?>
		</p>
	<?php elseif ( $tier === 'partial' && $seo_target ) : ?>
		<p>
			<span class="dashicons dashicons-info" aria-hidden="true"></span>
			<?php
			printf(
				/* translators: %s: SEO plugin name */
				esc_html__( 'Writing SEO titles and descriptions to %s.', 'seo-booster' ),
				'<strong>' . esc_html( $seo_target_label ) . '</strong>'
			);
			echo ' ';
			esc_html_e( 'Focus keyword tools are not available with this SEO plugin.', 'seo-booster' );
			?>
		</p>
	<?php else : ?>
		<p><?php echo esc_html( SEO_Plugin_Registry::get_bulk_write_requirement_message() ); ?></p>
		<p><small><?php esc_html_e( 'SEO analysis still works using page content and excerpts when no SEO plugin is active.', 'seo-booster' ); ?></small></p>
	<?php endif; ?>
</div>
