<?php
/**
 * Tools overview tab.
 *
 * @package Cleverplugins\SEOBooster\Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cleverplugins\SEOBooster\Setup_Wizard;
use Cleverplugins\SEOBooster\Tools\Tools_Page;

$tools       = Tools_Page::get_tools();
$has_premium = Tools_Page::user_has_premium_tools();
unset( $tools['overview'] );

?>
<div class="sb-tools-overview">
	<?php require SEOBOOSTER_PLUGINPATH . 'inc/Tools/views/seo-plugin-compat-notice.php'; ?>

	<p class="description">
		<?php esc_html_e( 'Bulk tools help you fix SEO issues across many posts, pages, or media items at once. Choose a tool below.', 'seo-booster' ); ?>
	</p>

	<div class="sb-tools-overview-grid">
		<?php foreach ( $tools as $tool ) : ?>
			<?php
			$is_locked  = ! empty( $tool['premium'] ) && ! $has_premium;
			$card_class = 'sb-tools-overview-card';
			if ( $is_locked ) {
				$card_class .= ' sb-card sb-card--locked';
			}
			$requires_ai = ! empty( $tool['requires_ai'] );
			$ai_optional = ! empty( $tool['ai_optional'] );
			?>
			<div class="<?php echo esc_attr( $card_class ); ?>">
				<h2>
					<?php echo esc_html( $tool['label'] ); ?>
					<?php if ( $requires_ai ) : ?>
						<span class="sb-tools-ai-badge" title="<?php esc_attr_e( 'Requires WordPress Connectors AI', 'seo-booster' ); ?>"><?php esc_html_e( 'AI', 'seo-booster' ); ?></span>
					<?php elseif ( $ai_optional ) : ?>
						<span class="sb-tools-ai-badge sb-tools-ai-badge--optional" title="<?php esc_attr_e( 'Optional AI features available with WordPress Connectors', 'seo-booster' ); ?>"><?php esc_html_e( 'AI optional', 'seo-booster' ); ?></span>
					<?php endif; ?>
				</h2>
				<p class="sb-tools-overview-card__desc">
					<?php
					switch ( $tool['id'] ) {
						case 'meta':
							esc_html_e( 'Scan posts and pages for missing, duplicate, or weak SEO titles and meta descriptions. Generate improvements with AI (WordPress Connectors) and apply only the fields you choose, with one-click revert.', 'seo-booster' );
							break;
						case 'image':
							esc_html_e( 'Find Media Library images missing alt text, title, caption, or description. Batch-generate metadata with AI vision and preview before/after values.', 'seo-booster' );
							break;
						case 'llms':
							esc_html_e( 'Publish an llms.txt file at your site root to help AI crawlers discover your most important content.', 'seo-booster' );
							break;
						case 'needs-analysis':
							esc_html_e( 'See content that was never analyzed or has stale SEO analysis, review existing issue data, and run a bulk re-analysis.', 'seo-booster' );
							break;
						case 'content-decay':
							esc_html_e( 'Find pages whose Search Console clicks fell recently versus the prior period, then open the editor or re-analyze for SEO Possibilities.', 'seo-booster' );
							break;
						case 'gsc-opportunities':
							esc_html_e( 'Find striking-distance keywords, low-CTR pages, and high-impression opportunities from GSC. Batch-rewrite SEO titles and meta with AI seeded by the actual query.', 'seo-booster' );
							break;
						case 'focus-keyword':
							esc_html_e( 'Suggest unique focus keywords from imported GSC data for pages missing one: no AI required. Picks the highest-impression query per URL and skips utility pages.', 'seo-booster' );
							break;
						case 'autolink-opportunities':
							esc_html_e( 'Discover high-value GSC queries without autolink rules, or enable autolink on high-traffic pages where it is off.', 'seo-booster' );
							break;
						case 'entity-map':
							esc_html_e( 'Publish /entitymap.json and /entitymap.html so AI systems understand your organization, key content, and relationships, curated from GSC and bot traffic, with optional AI drafts (Pro).', 'seo-booster' );
							break;
						default:
							esc_html_e( 'Open this tool for bulk SEO actions.', 'seo-booster' );
							break;
					}
					?>
				</p>
				<p class="sb-tools-overview-card__footer">
					<?php if ( $is_locked ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( Tools_Page::get_page_url( $tool['id'] ) ); ?>">
							<?php esc_html_e( 'Learn more', 'seo-booster' ); ?>
						</a>
					<?php else : ?>
						<a class="button button-primary" href="<?php echo esc_url( Tools_Page::get_page_url( $tool['id'] ) ); ?>">
							<?php esc_html_e( 'Open tool', 'seo-booster' ); ?>
						</a>
					<?php endif; ?>
				</p>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( class_exists( Setup_Wizard::class ) && current_user_can( 'manage_options' ) ) : ?>
		<p class="description" style="margin-top: 24px;">
			<?php esc_html_e( 'New here?', 'seo-booster' ); ?>
			<a href="<?php echo esc_url( Setup_Wizard::get_restart_url() ); ?>">
				<?php esc_html_e( 'Run the setup wizard', 'seo-booster' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
