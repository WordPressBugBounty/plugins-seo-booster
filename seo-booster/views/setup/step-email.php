<?php
/**
 * Weekly email step.
 *
 * @package Cleverplugins\SEOBooster
 * @var array $state
 */

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$recipient = ! empty( $state['weekly_recipient'] ) ? $state['weekly_recipient'] : wp_get_current_user()->user_email;
?>
<div class="sb-setup__content sb-setup__content--center">
	<h1 class="sb-setup__title"><?php esc_html_e( 'Get a weekly SEO update', 'seo-booster' ); ?></h1>
	<p class="sb-setup__lead">
		<?php esc_html_e( 'A short email with Google Search clicks, new keywords, and top SEO possibilities.', 'seo-booster' ); ?>
	</p>
	<label class="sb-setup__label" for="sb-setup-email"><?php esc_html_e( 'Email address', 'seo-booster' ); ?></label>
	<input type="email" id="sb-setup-email" class="sb-setup__input" value="<?php echo esc_attr( $recipient ); ?>" autocomplete="email" />
	<div class="sb-setup__actions">
		<button type="button" class="button button-primary sb-setup__btn-primary" id="sb-setup-email-save">
			<?php esc_html_e( 'Send me weekly updates', 'seo-booster' ); ?>
		</button>
		<button type="button" class="button-link sb-setup__btn-quiet" data-sb-setup-next="autolink">
			<?php esc_html_e( 'Skip this step', 'seo-booster' ); ?>
		</button>
	</div>
</div>
