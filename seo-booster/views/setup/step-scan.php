<?php
/**
 * Possibilities scan step.
 *
 * @package Cleverplugins\SEOBooster
 * @var array $state
 */

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$count = isset( $state['publishable_count'] ) ? (int) $state['publishable_count'] : 0;
?>
<div class="sb-setup__content sb-setup__content--center">
	<h1 class="sb-setup__title"><?php esc_html_e( 'Find improvements on your pages', 'seo-booster' ); ?></h1>
	<p class="sb-setup__lead">
		<?php
		printf(
			/* translators: %s: number of pages */
			esc_html__( 'We found %s public pages. Scanning for SEO improvements starts automatically.', 'seo-booster' ),
			'<strong id="sb-setup-scan-total">' . esc_html( number_format_i18n( $count ) ) . '</strong>'
		);
		?>
	</p>
	<div class="sb-setup__progress-bar" aria-hidden="true"><span class="sb-setup__progress-fill" id="sb-setup-scan-bar"></span></div>
	<div class="sb-setup__status" id="sb-setup-scan-status"><?php esc_html_e( 'Starting scan…', 'seo-booster' ); ?></div>
	<p class="sb-setup__soft" id="sb-setup-scan-hint"><?php esc_html_e( 'This keeps going in the background. You can continue.', 'seo-booster' ); ?></p>
	<div class="sb-setup__actions">
		<button type="button" class="button button-primary sb-setup__btn-primary" id="sb-setup-scan-continue" data-sb-setup-next="ai">
			<?php esc_html_e( 'Continue', 'seo-booster' ); ?>
		</button>
	</div>
</div>
