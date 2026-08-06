<?php
/**
 * Automatic links step.
 *
 * @package Cleverplugins\SEOBooster
 */

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sb-setup__content">
	<h1 class="sb-setup__title"><?php esc_html_e( 'Turn on automatic links', 'seo-booster' ); ?></h1>
	<p class="sb-setup__lead">
		<?php esc_html_e( 'Link related pages automatically when readers see your keywords.', 'seo-booster' ); ?>
	</p>
	<div id="sb-setup-autolink-suggestions" class="sb-setup-suggestions" hidden>
		<p class="sb-setup__label"><?php esc_html_e( 'Suggested links from Search Console', 'seo-booster' ); ?></p>
		<ul class="sb-setup-suggestions__list" id="sb-setup-autolink-list"></ul>
	</div>
	<div class="sb-setup__actions">
		<button type="button" class="button button-primary sb-setup__btn-primary" id="sb-setup-autolink-enable">
			<?php esc_html_e( 'Turn on automatic links', 'seo-booster' ); ?>
		</button>
		<button type="button" class="button-link sb-setup__btn-quiet" data-sb-setup-next="scan">
			<?php esc_html_e( 'Skip this step', 'seo-booster' ); ?>
		</button>
	</div>
</div>
