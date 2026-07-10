<?php
/**
 * Premium tool upsell teaser (free installs).
 *
 * @package Cleverplugins\SEOBooster\Tools
 *
 * @var string      $teaser_title
 * @var string      $teaser_description
 * @var string      $upgrade_url
 * @var string|null $teaser_variant Optional: 'entity-map' for richer Entity Map upsell.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$teaser_variant = isset( $teaser_variant ) ? (string) $teaser_variant : '';

?>
<div class="sb-tools-tool sb-tools-premium-teaser<?php echo $teaser_variant !== '' ? ' sb-tools-premium-teaser--' . esc_attr( $teaser_variant ) : ''; ?>">
	<div class="sb-card sb-card--locked">
		<h2><?php echo esc_html( $teaser_title ); ?></h2>
		<p class="description"><?php echo esc_html( $teaser_description ); ?></p>

		<?php if ( $teaser_variant === 'entity-map' ) : ?>
			<ul class="sb-tools-premium-teaser__list">
				<li><?php esc_html_e( 'Serve /entitymap.json for machines and /entitymap.html for humans', 'seo-booster' ); ?></li>
				<li><?php esc_html_e( 'Build from GSC clicks and AI bot traffic — then edit names, relations, and source chunks', 'seo-booster' ); ?></li>
				<li><?php esc_html_e( 'Optional AI draft via WordPress Connectors; lock entities you want to keep', 'seo-booster' ); ?></li>
				<li><?php esc_html_e( 'Link Entity Map from llms.txt under Structured knowledge when published', 'seo-booster' ); ?></li>
			</ul>
			<p class="description">
				<?php esc_html_e( 'llms.txt tells AI where to start. Entity Map tells AI who you are.', 'seo-booster' ); ?>
			</p>
			<p class="description">
				<a href="<?php echo esc_url( \Cleverplugins\SEOBooster\Utils::generate_cp_web_link( 'tools_entity_map_teaser', '/docs/tools/entity-map/' ) ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Read the Entity Map documentation', 'seo-booster' ); ?>
					<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'seo-booster' ); ?></span>
				</a>
			</p>
		<?php endif; ?>

		<p class="sb-card__footer">
			<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-secondary" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Upgrade to SEO Booster Pro', 'seo-booster' ); ?>
			</a>
		</p>
	</div>
</div>
