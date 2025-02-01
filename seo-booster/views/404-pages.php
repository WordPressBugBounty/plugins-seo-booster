<?php

namespace Cleverplugins\SEOBooster;

// don't load directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// Verify nonce
$nonce_action = '404_pages_nonce';
wp_nonce_field( $nonce_action, '_wpnonce' );
// Safely get and sanitize the page parameter
$page = ( isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '' );
?>
<div class="wrap">
    <?php 
echo wp_kses_post( Utils::show_plugin_headline( '404 errors', true ) );
?>

    <div id="sb2fof" class="clearfix clear">
    </div>
    <?php 
if ( !seobooster_fs()->can_use_premium_code() ) {
    ?>
        <div class="wrap">
            <h2 class="proonly"><?php 
    esc_html_e( 'Upgrade to SEO Booster Pro', 'seo-booster' );
    ?></h2>
            <div class="card">
                <h3><?php 
    esc_html_e( 'Unlock powerful SEO features:', 'seo-booster' );
    ?></h3>
                <ul class="ul-disc">
                    <li><?php 
    esc_html_e( 'Monitor and analyze 404 errors', 'seo-booster' );
    ?></li>
                    <li><?php 
    esc_html_e( 'Improve user experience and SEO performance', 'seo-booster' );
    ?></li>
                    <li><?php 
    esc_html_e( 'Advanced reporting and insights', 'seo-booster' );
    ?></li>
                    <li><?php 
    esc_html_e( 'Comprehensive keyword analysis', 'seo-booster' );
    ?></li>
                </ul>
                <p><?php 
    esc_html_e( 'Take your website to the next level with SEO Booster Pro!', 'seo-booster' );
    ?></p>
                <p>
                    <a href="<?php 
    echo esc_url( seobooster_fs()->get_upgrade_url() );
    ?>" class="button button-primary">
                        <?php 
    esc_html_e( 'Upgrade Now', 'seo-booster' );
    ?>
                    </a>
                </p>
            </div>
        </div>
    <?php 
}
?>
</div><?php 