<?php
/**
 * Search Console step.
 *
 * @package Cleverplugins\SEOBooster
 * @var array $state
 */

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$phase    = isset( $state['gsc_phase'] ) ? $state['gsc_phase'] : 'connect';
$sites    = isset( $state['sites'] ) && is_array( $state['sites'] ) ? $state['sites'] : array();
$email    = isset( $state['google_email'] ) ? $state['google_email'] : '';
$selected = isset( $state['selected_site'] ) ? $state['selected_site'] : '';
?>
<div class="sb-setup__content">
	<h1 class="sb-setup__title"><?php esc_html_e( 'Connect Google Search Console', 'seo-booster' ); ?></h1>
	<p class="sb-setup__lead">
		<?php esc_html_e( 'See the search terms that already bring people to your site.', 'seo-booster' ); ?>
	</p>

	<div class="sb-setup-gsc" data-gsc-phase="<?php echo esc_attr( $phase ); ?>">
		<div class="sb-setup-gsc__phase" data-gsc-panel="connect" <?php echo in_array( $phase, array( 'site', 'import', 'done' ), true ) ? 'hidden' : ''; ?>>
			<?php if ( $email ) : ?>
				<p class="sb-setup__soft"><?php echo esc_html( $email ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $state['is_local'] ) ) : ?>
				<p class="sb-setup__tip"><?php esc_html_e( 'You’re on a local site. Google sign-in may need a public URL.', 'seo-booster' ); ?></p>
			<?php endif; ?>
			<div class="sb-setup__actions">
				<a href="#" class="button button-primary sb-setup__btn-primary" id="sb-setup-gsc-connect">
					<?php esc_html_e( 'Connect Google', 'seo-booster' ); ?>
				</a>
				<button type="button" class="button-link sb-setup__btn-quiet" data-sb-setup-next="email">
					<?php esc_html_e( 'Skip this step', 'seo-booster' ); ?>
				</button>
			</div>
		</div>

		<div class="sb-setup-gsc__phase" data-gsc-panel="site" <?php echo 'site' === $phase ? '' : 'hidden'; ?>>
			<label class="sb-setup__label" for="sb-setup-gsc-site"><?php esc_html_e( 'Choose your site', 'seo-booster' ); ?></label>
			<select id="sb-setup-gsc-site" class="sb-setup__select" name="seobooster_selected_site">
				<option value=""><?php esc_html_e( 'Select a property…', 'seo-booster' ); ?></option>
				<?php foreach ( $sites as $site_url ) : ?>
					<option value="<?php echo esc_attr( $site_url ); ?>" <?php selected( $selected, $site_url ); ?>>
						<?php echo esc_html( $site_url ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<label class="sb-setup__label" for="sb-setup-gsc-days"><?php esc_html_e( 'How much history?', 'seo-booster' ); ?></label>
			<select id="sb-setup-gsc-days" class="sb-setup__select">
				<option value="28"><?php esc_html_e( 'Last 28 days', 'seo-booster' ); ?></option>
				<option value="90" selected><?php esc_html_e( 'Last 90 days', 'seo-booster' ); ?></option>
				<option value="180"><?php esc_html_e( 'Last 180 days', 'seo-booster' ); ?></option>
			</select>
			<div class="sb-setup__actions">
				<button type="button" class="button button-primary sb-setup__btn-primary" id="sb-setup-gsc-import">
					<?php esc_html_e( 'Import keyword data', 'seo-booster' ); ?>
				</button>
				<button type="button" class="button-link sb-setup__btn-quiet" data-sb-setup-next="email">
					<?php esc_html_e( 'Skip this step', 'seo-booster' ); ?>
				</button>
			</div>
		</div>

		<div class="sb-setup-gsc__phase" data-gsc-panel="import" <?php echo in_array( $phase, array( 'import', 'done' ), true ) ? '' : 'hidden'; ?>>
			<div class="sb-setup__progress-bar" aria-hidden="true"><span class="sb-setup__progress-fill" id="sb-setup-gsc-bar"></span></div>
			<div class="sb-setup__status" id="sb-setup-gsc-status"></div>
			<p class="sb-setup__soft" id="sb-setup-gsc-hint">
				<?php esc_html_e( 'Import keeps running while you continue. You don’t need to wait here.', 'seo-booster' ); ?>
			</p>
			<div class="sb-setup__actions">
				<button type="button" class="button button-primary sb-setup__btn-primary" id="sb-setup-gsc-continue" data-sb-setup-next="email">
					<?php esc_html_e( 'Continue', 'seo-booster' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
