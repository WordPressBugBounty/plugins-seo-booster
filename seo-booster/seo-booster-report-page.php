<?php

namespace Cleverplugins\SEOBooster;

use Cleverplugins\SEOBooster\Reports\TopPerformingKeywords;
use Cleverplugins\SEOBooster\Reports\DecliningKeywords;
use Cleverplugins\SEOBooster\Reports\TopPages;
use Cleverplugins\SEOBooster\Reports\LongTailKeywords;
use Cleverplugins\SEOBooster\Reports\KeywordCannibalization;
use Cleverplugins\SEOBooster\Reports\CTRImprovement;
use Cleverplugins\SEOBooster\Reports\QuestionQueries;
use Cleverplugins\SEOBooster\Reports\Missing404Pages;
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !current_user_can( 'update_plugins' ) ) {
    wp_die( 'You are not allowed to update plugins on this blog' );
}
echo wp_kses_post( Utils::show_plugin_headline( __( 'Reports', 'seo-booster' ), true ) );
if ( !seobooster_fs()->can_use_premium_code() ) {
    echo '<div class="wrap">';
    echo '<h2 class="proonly">' . esc_html__( 'Upgrade to SEO Booster Pro', 'seo-booster' ) . '</h2>';
    echo '<div class="notice notice-info is-dismissible">';
    echo '<p>' . esc_html__( 'Unlock powerful SEO reports and take your website to the next level!', 'seo-booster' ) . '</p>';
    echo '</div>';
    echo '<div class="card">';
    echo '<h3>' . esc_html__( 'Benefits of SEO Booster Pro Reports:', 'seo-booster' ) . '</h3>';
    echo '<ul class="ul-disc">';
    echo '<li>' . esc_html__( 'In-depth keyword analysis and optimization suggestions', 'seo-booster' ) . '</li>';
    echo '<li>' . esc_html__( 'Identify and fix content cannibalization issues', 'seo-booster' ) . '</li>';
    echo '<li>' . esc_html__( 'Track your most improved and declining keywords', 'seo-booster' ) . '</li>';
    echo '<li>' . esc_html__( 'Discover long-tail keyword opportunities', 'seo-booster' ) . '</li>';
    echo '<li>' . esc_html__( 'Improve click-through rates with actionable insights', 'seo-booster' ) . '</li>';
    echo '<li>' . esc_html__( 'Monitor 404 errors', 'seo-booster' ) . '</li>';
    echo '</ul>';
    echo '<p class="submit">';
    echo '<a href="' . esc_url( seobooster_fs()->get_upgrade_url() ) . '" class="button button-primary">' . esc_html__( 'Upgrade Now', 'seo-booster' ) . '</a>';
    echo '</p>';
    echo '</div>';
    echo '</div>';
}