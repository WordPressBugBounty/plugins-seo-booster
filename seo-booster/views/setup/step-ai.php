<?php
/**
 * AI assistance step.
 *
 * @package Cleverplugins\SEOBooster
 * @var array $state
 */

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$variant     = isset( $state['ai_variant'] ) ? $state['ai_variant'] : 'preview';
$bot_on      = ! isset( $state['bot_tracking_on'] ) || ! empty( $state['bot_tracking_on'] );
$ai_bots_url = ! empty( $state['ai_bots_url'] ) ? $state['ai_bots_url'] : admin_url( 'admin.php?page=sb2_ai_bots' );
?>
<div class="sb-setup__content sb-setup__content--center" data-ai-variant="<?php echo esc_attr( $variant ); ?>">
	<h1 class="sb-setup__title"><?php esc_html_e( 'AI suggestions for your pages', 'seo-booster' ); ?></h1>

	<div class="sb-setup-ai" data-ai-panel="ready" <?php echo 'ready' === $variant ? '' : 'hidden'; ?>>
		<p class="sb-setup__lead">
			<?php esc_html_e( 'WordPress AI is ready on this site. Turn on suggestions for titles, meta, and more.', 'seo-booster' ); ?>
		</p>
	</div>

	<div class="sb-setup-ai" data-ai-panel="needs_connector" <?php echo 'needs_connector' === $variant ? '' : 'hidden'; ?>>
		<p class="sb-setup__lead">
			<?php esc_html_e( 'Connect an AI provider in WordPress to power title and meta suggestions inside SEO Booster.', 'seo-booster' ); ?>
		</p>
	</div>

	<div class="sb-setup-ai" data-ai-panel="preview" <?php echo 'preview' === $variant ? '' : 'hidden'; ?>>
		<p class="sb-setup__lead">
			<?php esc_html_e( 'On WordPress 7 with Connectors, SEO Booster can suggest titles, meta descriptions, and more, right where you edit.', 'seo-booster' ); ?>
		</p>
	</div>

	<label class="sb-setup-bot">
		<input type="checkbox" id="sb-setup-bot-tracking" value="1" <?php checked( $bot_on ); ?> />
		<span>
			<span class="sb-setup-bot__title"><?php esc_html_e( 'Track AI bots visiting your site', 'seo-booster' ); ?></span>
			<p class="sb-setup-bot__desc">
				<?php esc_html_e( 'See which AI crawlers and answer engines request your pages.', 'seo-booster' ); ?>
				<a href="<?php echo esc_url( $ai_bots_url ); ?>"><?php esc_html_e( 'AI Bots', 'seo-booster' ); ?></a>
			</p>
		</span>
	</label>

	<div class="sb-setup-ai" data-ai-panel="ready" <?php echo 'ready' === $variant ? '' : 'hidden'; ?>>
		<div class="sb-setup__actions">
			<button type="button" class="button button-primary sb-setup__btn-primary" id="sb-setup-ai-enable">
				<?php esc_html_e( 'Turn on AI suggestions', 'seo-booster' ); ?>
			</button>
			<button type="button" class="button-link sb-setup__btn-quiet" data-sb-setup-next="health">
				<?php esc_html_e( 'Continue', 'seo-booster' ); ?>
			</button>
		</div>
	</div>

	<div class="sb-setup-ai" data-ai-panel="needs_connector" <?php echo 'needs_connector' === $variant ? '' : 'hidden'; ?>>
		<div class="sb-setup__actions">
			<a href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>" class="button button-primary sb-setup__btn-primary" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Set up AI in WordPress', 'seo-booster' ); ?>
			</a>
			<button type="button" class="button-link sb-setup__btn-quiet" data-sb-setup-next="health">
				<?php esc_html_e( 'Continue', 'seo-booster' ); ?>
			</button>
		</div>
	</div>

	<div class="sb-setup-ai" data-ai-panel="preview" <?php echo 'preview' === $variant ? '' : 'hidden'; ?>>
		<div class="sb-setup__actions">
			<button type="button" class="button button-primary sb-setup__btn-primary" data-sb-setup-next="health">
				<?php esc_html_e( 'Continue', 'seo-booster' ); ?>
			</button>
		</div>
	</div>
</div>
