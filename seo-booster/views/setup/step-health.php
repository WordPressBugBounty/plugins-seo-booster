<?php
/**
 * Site health / Tools peek step.
 *
 * @package Cleverplugins\SEOBooster
 * @var array $state
 */

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sb-setup__content">
	<h1 class="sb-setup__title"><?php esc_html_e( 'Quick look at your site', 'seo-booster' ); ?></h1>
	<p class="sb-setup__lead">
		<?php esc_html_e( 'SEO Booster can scan for these simple issues. Open a tool when you’re ready. Nothing runs from here.', 'seo-booster' ); ?>
	</p>

	<div class="sb-action-cards" id="sb-setup-health-peek">
		<div class="sb-action-cards__group" id="sb-setup-health-cards">
			<article class="sb-action-card" data-health="possibilities">
				<div class="sb-action-card__num sb-action-card__num--loading" aria-busy="true">
					<span class="sb-action-card__spinner" aria-hidden="true"></span>
					<span class="sb-action-card__loading-label"><?php esc_html_e( 'Loading…', 'seo-booster' ); ?></span>
				</div>
				<h3 class="sb-action-card__title"><?php esc_html_e( 'SEO possibilities to review', 'seo-booster' ); ?></h3>
				<a class="sb-action-card__link" href="#" data-tool-link="possibilities"><?php esc_html_e( 'Review', 'seo-booster' ); ?></a>
			</article>
			<article class="sb-action-card" data-health="missing_focus_keyword">
				<div class="sb-action-card__num sb-action-card__num--loading" aria-busy="true">
					<span class="sb-action-card__spinner" aria-hidden="true"></span>
					<span class="sb-action-card__loading-label"><?php esc_html_e( 'Loading…', 'seo-booster' ); ?></span>
				</div>
				<h3 class="sb-action-card__title"><?php esc_html_e( 'Pages without a focus keyword', 'seo-booster' ); ?></h3>
				<a class="sb-action-card__link" href="#" data-tool-link="focus"><?php esc_html_e( 'Open tool', 'seo-booster' ); ?></a>
			</article>
			<article class="sb-action-card" data-health="empty_alt">
				<div class="sb-action-card__num sb-action-card__num--loading" aria-busy="true">
					<span class="sb-action-card__spinner" aria-hidden="true"></span>
					<span class="sb-action-card__loading-label"><?php esc_html_e( 'Loading…', 'seo-booster' ); ?></span>
				</div>
				<h3 class="sb-action-card__title"><?php esc_html_e( 'Images missing alt text', 'seo-booster' ); ?></h3>
				<a class="sb-action-card__link" href="#" data-tool-link="image"><?php esc_html_e( 'Open tool', 'seo-booster' ); ?></a>
			</article>
			<article class="sb-action-card" data-health="missing_meta">
				<div class="sb-action-card__num sb-action-card__num--loading" aria-busy="true">
					<span class="sb-action-card__spinner" aria-hidden="true"></span>
					<span class="sb-action-card__loading-label"><?php esc_html_e( 'Loading…', 'seo-booster' ); ?></span>
				</div>
				<h3 class="sb-action-card__title"><?php esc_html_e( 'Pages missing title or description', 'seo-booster' ); ?></h3>
				<a class="sb-action-card__link" href="#" data-tool-link="meta"><?php esc_html_e( 'Open tool', 'seo-booster' ); ?></a>
			</article>
			<article class="sb-action-card" data-health="llms">
				<div class="sb-action-card__num sb-action-card__num--loading" aria-busy="true">
					<span class="sb-action-card__spinner" aria-hidden="true"></span>
					<span class="sb-action-card__loading-label"><?php esc_html_e( 'Loading…', 'seo-booster' ); ?></span>
				</div>
				<h3 class="sb-action-card__title"><?php esc_html_e( 'llms.txt for AI crawlers', 'seo-booster' ); ?></h3>
				<a class="sb-action-card__link" href="#" data-tool-link="llms"><?php esc_html_e( 'Open tool', 'seo-booster' ); ?></a>
			</article>
		</div>
		<div class="sb-action-cards__group" id="sb-setup-health-pro" hidden></div>
	</div>
	<div class="sb-setup__actions">
		<button type="button" class="button button-primary sb-setup__btn-primary" data-sb-setup-next="workplace">
			<?php esc_html_e( 'Continue', 'seo-booster' ); ?>
		</button>
	</div>
</div>
