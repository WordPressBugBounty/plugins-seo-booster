<?php
/**
 * Setup wizard shell.
 *
 * @package Cleverplugins\SEOBooster
 * @var array $state Wizard state from Setup_Wizard::build_state().
 */

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$state       = isset( $state ) && is_array( $state ) ? $state : Setup_Wizard::build_state();
$step_ids    = Setup_Wizard::get_step_ids();
$current     = isset( $state['step'] ) ? $state['step'] : 'welcome';
$logo_url    = SEOBOOSTER_PLUGINURL . 'images/sblogo25.png';
$is_ready    = ( 'ready' === $current );
$can_go_back = ! $is_ready && 'welcome' !== $current;
?>
<div class="sb-setup" id="sb-setup" data-step="<?php echo esc_attr( $current ); ?>">
	<header class="sb-setup__header">
		<div class="sb-setup__brand">
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'SEO Booster', 'seo-booster' ); ?>" height="36" width="36" />
			<span class="sb-setup__brand-name"><?php esc_html_e( 'SEO Booster', 'seo-booster' ); ?></span>
		</div>
		<div class="sb-setup__ambient" id="sb-setup-ambient" hidden></div>
		<div class="sb-setup__header-right">
			<nav class="sb-setup__progress" aria-label="<?php esc_attr_e( 'Setup progress', 'seo-booster' ); ?>">
				<?php
				foreach ( $step_ids as $index => $step_id ) :
					$is_current = ( $step_id === $current );
					$is_done    = ! empty( $state['completions'][ $step_id ] ) || array_search( $step_id, $step_ids, true ) < array_search( $current, $step_ids, true );
					$classes    = 'sb-setup__dot';
					if ( $is_current ) {
						$classes .= ' is-current';
					}
					if ( $is_done && ! $is_current ) {
						$classes .= ' is-done';
					}
					?>
					<span class="<?php echo esc_attr( $classes ); ?>" data-step="<?php echo esc_attr( $step_id ); ?>" title="<?php echo esc_attr( $step_id ); ?>"></span>
				<?php endforeach; ?>
			</nav>
			<button
				type="button"
				class="sb-setup__close"
				id="sb-setup-close"
				aria-label="<?php echo $is_ready ? esc_attr__( 'Close', 'seo-booster' ) : esc_attr__( 'Exit setup', 'seo-booster' ); ?>"
				<?php echo $is_ready ? 'data-sb-setup-finish' : 'data-sb-setup-dismiss'; ?>
			>
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
	</header>

	<main class="sb-setup__main">
		<div class="sb-setup__panel" id="sb-setup-panel">
			<?php
			foreach ( $step_ids as $step_id ) {
				$active = ( $step_id === $current ) ? ' is-active' : '';
				echo '<section class="sb-setup__step' . esc_attr( $active ) . '" data-step-panel="' . esc_attr( $step_id ) . '" ' . ( $step_id === $current ? '' : 'hidden' ) . '>';
				$partial = SEOBOOSTER_PLUGINPATH . 'views/setup/step-' . $step_id . '.php';
				if ( file_exists( $partial ) ) {
					include $partial;
				}
				echo '</section>';
			}
			?>
		</div>
		<div class="sb-setup__footer" id="sb-setup-footer" <?php echo $is_ready ? 'hidden' : ''; ?>>
			<button
				type="button"
				class="button-link sb-setup__back"
				id="sb-setup-back"
				data-sb-setup-back
				<?php echo $can_go_back ? '' : 'hidden'; ?>
			>
				<?php esc_html_e( 'Back', 'seo-booster' ); ?>
			</button>
			<button type="button" class="button-link sb-setup__skip" data-sb-setup-dismiss>
				<?php esc_html_e( 'Exit setup', 'seo-booster' ); ?>
			</button>
		</div>
	</main>
</div>
