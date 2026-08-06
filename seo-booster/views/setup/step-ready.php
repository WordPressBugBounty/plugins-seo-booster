<?php
/**
 * Ready / completion step.
 *
 * @package Cleverplugins\SEOBooster
 * @var array $state
 */

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$destinations = isset( $state['destinations'] ) && is_array( $state['destinations'] ) ? $state['destinations'] : array();
?>
<div class="sb-setup__content sb-setup__content--center">
	<div class="sb-setup__success-mark" aria-hidden="true">✓</div>
	<h1 class="sb-setup__title"><?php esc_html_e( 'You’re ready', 'seo-booster' ); ?></h1>
	<p class="sb-setup__lead">
		<?php esc_html_e( 'SEO Booster is set up. Explore keyword history and trends anytime under GSC Overview.', 'seo-booster' ); ?>
	</p>
	<div class="sb-setup__actions sb-setup__actions--stack" id="sb-setup-destinations">
		<?php foreach ( $destinations as $dest ) : ?>
			<a
				href="<?php echo esc_url( $dest['url'] ); ?>"
				class="button <?php echo ! empty( $dest['primary'] ) ? 'button-primary sb-setup__btn-primary' : ''; ?>"
				data-sb-setup-complete
			>
				<?php echo esc_html( $dest['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</div>
</div>
