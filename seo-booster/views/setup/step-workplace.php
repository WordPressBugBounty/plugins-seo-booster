<?php
/**
 * Where you'll work — editor, admin bar, GSC, possibilities, tools, AI bots.
 *
 * @package Cleverplugins\SEOBooster
 * @var array $state
 */

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$edit_url    = ! empty( $state['edit_post_url'] ) ? $state['edit_post_url'] : admin_url( 'edit.php' );
$gsc_url     = admin_url( 'admin.php?page=sb2_gsc' );
$issues_url  = admin_url( 'admin.php?page=sb2_seo_issues' );
$tools_url   = Tools\Tools_Page::get_page_url();
$ai_bots_url = ! empty( $state['ai_bots_url'] ) ? $state['ai_bots_url'] : admin_url( 'admin.php?page=sb2_ai_bots' );
?>
<div class="sb-setup__content">
	<h1 class="sb-setup__title"><?php esc_html_e( 'Where you’ll work', 'seo-booster' ); ?></h1>
	<p class="sb-setup__lead">
		<?php esc_html_e( 'These are the places you’ll get the most value from SEO Booster.', 'seo-booster' ); ?>
	</p>
	<div class="sb-setup-workplace">
		<article class="sb-setup-workplace__panel">
			<div class="sb-setup-illu" aria-hidden="true">
				<svg viewBox="0 0 200 120" fill="none" xmlns="http://www.w3.org/2000/svg">
					<rect x="12" y="10" width="176" height="100" rx="8" stroke="#00824c" stroke-width="2"/>
					<rect x="24" y="24" width="80" height="10" rx="2" fill="#00824c" opacity="0.35"/>
					<rect x="24" y="44" width="152" height="6" rx="2" fill="#c3c4c7"/>
					<rect x="24" y="58" width="140" height="6" rx="2" fill="#c3c4c7"/>
					<rect x="24" y="72" width="100" height="6" rx="2" fill="#c3c4c7"/>
					<rect x="120" y="24" width="56" height="48" rx="4" stroke="#0073aa" stroke-width="2"/>
					<text x="148" y="52" text-anchor="middle" font-size="10" fill="#0073aa" font-family="sans-serif">SEO</text>
				</svg>
			</div>
			<h2 class="sb-setup-workplace__title"><?php esc_html_e( 'In the editor', 'seo-booster' ); ?></h2>
			<p><?php esc_html_e( 'Open any post. The SEO Booster box shows analysis, Search Console keywords, and AI tools.', 'seo-booster' ); ?></p>
			<a class="button" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit a recent post', 'seo-booster' ); ?></a>
		</article>
		<article class="sb-setup-workplace__panel">
			<div class="sb-setup-illu" aria-hidden="true">
				<svg viewBox="0 0 200 120" fill="none" xmlns="http://www.w3.org/2000/svg">
					<rect x="8" y="8" width="184" height="18" rx="3" fill="#1d2327"/>
					<circle cx="20" cy="17" r="3" fill="#00824c"/>
					<rect x="32" y="13" width="40" height="8" rx="2" fill="#f0f0f1"/>
					<rect x="120" y="36" width="72" height="70" rx="6" stroke="#0073aa" stroke-width="2" fill="#fff"/>
					<rect x="128" y="46" width="56" height="8" rx="2" fill="#00824c" opacity="0.4"/>
					<rect x="128" y="62" width="56" height="5" rx="1" fill="#c3c4c7"/>
					<rect x="128" y="72" width="40" height="5" rx="1" fill="#c3c4c7"/>
					<rect x="128" y="82" width="48" height="5" rx="1" fill="#c3c4c7"/>
				</svg>
			</div>
			<h2 class="sb-setup-workplace__title"><?php esc_html_e( 'On the front end', 'seo-booster' ); ?></h2>
			<p><?php esc_html_e( 'While browsing your site, open SEO Booster → Page overview in the admin bar for a quick snapshot.', 'seo-booster' ); ?></p>
		</article>
		<article class="sb-setup-workplace__panel">
			<div class="sb-setup-illu" aria-hidden="true">
				<svg viewBox="0 0 200 120" fill="none" xmlns="http://www.w3.org/2000/svg">
					<rect x="16" y="18" width="168" height="84" rx="8" stroke="#00824c" stroke-width="2"/>
					<polyline points="28,78 60,58 88,66 120,40 168,50" stroke="#0073aa" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
					<circle cx="60" cy="58" r="3.5" fill="#00824c"/>
					<circle cx="120" cy="40" r="3.5" fill="#00824c"/>
					<rect x="28" y="28" width="48" height="8" rx="2" fill="#c3c4c7"/>
				</svg>
			</div>
			<h2 class="sb-setup-workplace__title"><?php esc_html_e( 'GSC Overview', 'seo-booster' ); ?></h2>
			<p><?php esc_html_e( 'Browse keyword history, trends, and which pages earn clicks and impressions from Google Search.', 'seo-booster' ); ?></p>
			<a class="button" href="<?php echo esc_url( $gsc_url ); ?>"><?php esc_html_e( 'Open GSC Overview', 'seo-booster' ); ?></a>
		</article>
		<article class="sb-setup-workplace__panel">
			<div class="sb-setup-illu" aria-hidden="true">
				<svg viewBox="0 0 200 120" fill="none" xmlns="http://www.w3.org/2000/svg">
					<rect x="24" y="20" width="152" height="22" rx="6" stroke="#0073aa" stroke-width="2"/>
					<circle cx="40" cy="31" r="5" fill="#00824c"/>
					<rect x="54" y="26" width="100" height="6" rx="2" fill="#c3c4c7"/>
					<rect x="24" y="50" width="152" height="22" rx="6" stroke="#0073aa" stroke-width="2"/>
					<circle cx="40" cy="61" r="5" fill="#00824c" opacity="0.55"/>
					<rect x="54" y="56" width="88" height="6" rx="2" fill="#c3c4c7"/>
					<rect x="24" y="80" width="152" height="22" rx="6" stroke="#0073aa" stroke-width="2"/>
					<circle cx="40" cy="91" r="5" fill="#00824c" opacity="0.3"/>
					<rect x="54" y="86" width="72" height="6" rx="2" fill="#c3c4c7"/>
				</svg>
			</div>
			<h2 class="sb-setup-workplace__title"><?php esc_html_e( 'SEO Possibilities', 'seo-booster' ); ?></h2>
			<p><?php esc_html_e( 'Review on-page improvements found by the scan: titles, meta, headings, images, and more.', 'seo-booster' ); ?></p>
			<a class="button" href="<?php echo esc_url( $issues_url ); ?>"><?php esc_html_e( 'View possibilities', 'seo-booster' ); ?></a>
		</article>
		<article class="sb-setup-workplace__panel">
			<div class="sb-setup-illu" aria-hidden="true">
				<svg viewBox="0 0 200 120" fill="none" xmlns="http://www.w3.org/2000/svg">
					<rect x="30" y="22" width="58" height="76" rx="8" stroke="#00824c" stroke-width="2"/>
					<rect x="40" y="36" width="38" height="8" rx="2" fill="#00824c" opacity="0.35"/>
					<rect x="40" y="52" width="30" height="6" rx="2" fill="#c3c4c7"/>
					<rect x="40" y="66" width="34" height="6" rx="2" fill="#c3c4c7"/>
					<rect x="112" y="22" width="58" height="76" rx="8" stroke="#0073aa" stroke-width="2"/>
					<rect x="122" y="36" width="38" height="8" rx="2" fill="#0073aa" opacity="0.35"/>
					<rect x="122" y="52" width="30" height="6" rx="2" fill="#c3c4c7"/>
					<rect x="122" y="66" width="34" height="6" rx="2" fill="#c3c4c7"/>
				</svg>
			</div>
			<h2 class="sb-setup-workplace__title"><?php esc_html_e( 'Tools', 'seo-booster' ); ?></h2>
			<p><?php esc_html_e( 'Bulk-fix missing meta, image alt text, focus keywords, and generate llms.txt for AI crawlers.', 'seo-booster' ); ?></p>
			<a class="button" href="<?php echo esc_url( $tools_url ); ?>"><?php esc_html_e( 'Open Tools', 'seo-booster' ); ?></a>
		</article>
		<article class="sb-setup-workplace__panel">
			<div class="sb-setup-illu" aria-hidden="true">
				<svg viewBox="0 0 200 120" fill="none" xmlns="http://www.w3.org/2000/svg">
					<circle cx="100" cy="52" r="28" stroke="#00824c" stroke-width="2"/>
					<circle cx="100" cy="52" r="10" fill="#0073aa" opacity="0.35"/>
					<path d="M100 24v10M100 70v10M72 52h10M118 52h10M80 32l7 7M113 67l7 7M80 72l7-7M113 37l7-7" stroke="#00824c" stroke-width="2" stroke-linecap="round"/>
					<rect x="54" y="88" width="92" height="14" rx="7" fill="#f0f0f1" stroke="#c3c4c7"/>
					<text x="100" y="98" text-anchor="middle" font-size="8" fill="#50575e" font-family="sans-serif">AI</text>
				</svg>
			</div>
			<h2 class="sb-setup-workplace__title"><?php esc_html_e( 'AI Bots', 'seo-booster' ); ?></h2>
			<p><?php esc_html_e( 'See which AI crawlers and answer engines visit your pages, and which content they request.', 'seo-booster' ); ?></p>
			<a class="button" href="<?php echo esc_url( $ai_bots_url ); ?>"><?php esc_html_e( 'Open AI Bots', 'seo-booster' ); ?></a>
		</article>
	</div>
	<div class="sb-setup__actions">
		<button type="button" class="button button-primary sb-setup__btn-primary" data-sb-setup-next="ready">
			<?php esc_html_e( 'Continue', 'seo-booster' ); ?>
		</button>
	</div>
</div>
