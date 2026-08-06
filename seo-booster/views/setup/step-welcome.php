<?php
/**
 * Welcome step.
 *
 * @package Cleverplugins\SEOBooster
 * @var array $state
 */

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$seo_label = ! empty( $state['seo_plugin_label'] ) ? $state['seo_plugin_label'] : '';
?>
<div class="sb-setup__content sb-setup__content--center">
	<img class="sb-setup__hero-logo" src="<?php echo esc_url( SEOBOOSTER_PLUGINURL . 'images/sblogo25.png' ); ?>" alt="<?php esc_attr_e( 'SEO Booster', 'seo-booster' ); ?>" width="72" height="72" />
	<h1 class="sb-setup__title"><?php esc_html_e( 'Get SEO Booster ready in a few minutes', 'seo-booster' ); ?></h1>
	<p class="sb-setup__lead">
		<?php esc_html_e( 'Connect Search Console, turn on helpful automations, and start finding improvements on your site.', 'seo-booster' ); ?>
	</p>
	<?php if ( $seo_label ) : ?>
		<p class="sb-setup__soft">
			<?php
			printf(
				/* translators: %s: detected SEO plugin name */
				esc_html__( 'Detected active SEO plugin: %s.', 'seo-booster' ),
				esc_html( $seo_label )
			);
			?>
		</p>
	<?php endif; ?>
	<div class="sb-setup__actions">
		<button type="button" class="button button-primary sb-setup__btn-primary" data-sb-setup-next="gsc">
			<?php esc_html_e( 'Get started', 'seo-booster' ); ?>
		</button>
	</div>
</div>
