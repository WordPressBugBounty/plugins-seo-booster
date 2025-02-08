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



if (seobooster_fs()->can_use_premium_code()) {
	if (seobooster_fs()->can_use_premium_code()) {
        Utils::timerstart('dashboard_report_total_generation_time');

		$data = [];
		$report_meta = [];

		// @todo: change cache times below
		// 1 hour = 3600 seconds
		// 24 hours = 86400 seconds
		// 1 day = 86400 seconds
		// 30 days = 2592000 seconds
		$reports = [
			'question-queries' => ['function' => [QuestionQueries::class, 'get_data'], 'cache_time' => 3600], 
			'keyword-cannibalization' => ['function' => [KeywordCannibalization::class, 'get_data'], 'cache_time' => 3600], 
			'new_keywords' => ['function' => [Reports::class, 'get_new_keywords_past_x_days'], 'cache_time' => 3600], 
			'inactive_keywords' => ['function' => [Reports::class, 'get_inactive_keywords'], 'cache_time' => 3600, 'args' => [3600]], 
			'most_improved_keywords' => ['function' => [Reports::class, 'get_most_improved_keywords'], 'cache_time' => 3600, 'args' => [3600]], 
			'top_performing_keywords' => ['function' => [TopPerformingKeywords::class, 'get_data'], 'cache_time' => 3600], 
			'declining_keywords' => ['function' => [DecliningKeywords::class, 'get_data'], 'cache_time' => 3600], 
			'top_pages' => ['function' => [TopPages::class, 'get_data'], 'cache_time' => 3600], 
			'long_tail_keywords' => ['function' => [LongTailKeywords::class, 'get_data'], 'cache_time' => 3600], 
			'missing_404_pages' => ['function' => [Missing404Pages::class, 'get_data'], 'cache_time' => 3600], 
			'ctr_improvement_opportunities' => ['function' => [CTRImprovement::class, 'get_data'], 'cache_time' => 3600], 
		];

		foreach ($reports as $key => $report) {
			$cache_key = 'seobooster_report_' . $key;
			// wp_cache_delete($cache_key); // Force refresh for debugging
			$cached_data = wp_cache_get($cache_key);
			
			if (false === $cached_data) {
				try {
					$class =  $report['function'][0];
					$method = $report['function'][1];
					// Check if class exists
					if (!class_exists($class)) {
						throw new \Exception(
							sprintf(
								/* translators: %s: class name */
								esc_html__('Report class %s not found.', 'seo-booster'),
								esc_html($report['function'][0])
							)
						);
					}

					// Check if method exists
					if (!method_exists($class, $method)) {
						throw new \Exception(
							sprintf(
								/* translators: 1: method name, 2: class name */
								esc_html__('Report method %1$s not found in class %2$s.', 'seo-booster'),
								esc_html($method),
								esc_html($report['function'][0])
							)
						);
					}

					// Start timer for this report
					Utils::timerstart("report_generation_$key");

					// Execute the report method
					$args = isset($report['args']) ? $report['args'] : [];
					$report_data = call_user_func_array([$class, $method], $args);
					$generation_time = Utils::timerstop("report_generation_$key");
					Utils::log(
						sprintf(
							/* translators: 1: report key, 2: time in seconds */
							esc_html__('Report %1$s generated in %2$s seconds', 'seo-booster'),
							esc_html($key),
							esc_html($generation_time)
						),
						5
					);

					$cached_data = [
						'data' => $report_data,
						'meta' => [
							'generation_time' => $generation_time,
							'generated_at' => time(),
						],
					];

					wp_cache_set($cache_key, $cached_data, '', $report['cache_time']);
				} catch (\Exception $e) {
					Utils::log(
						sprintf(
							/* translators: 1: report key, 2: error message */
							esc_html__('Error generating report %1$s: %2$s', 'seo-booster'),
							esc_html($key),
							esc_html($e->getMessage())
						),
						2
					);

					$cached_data = [
						'data' => null,
						'meta' => [
							'error' => $e->getMessage(),
							'generation_time' => 0,
							'generated_at' => time(),
						],
					];
				}
			}

			$data[$key] = $cached_data['data'];
			$report_meta[$key] = $cached_data['meta'];
			$report_meta[$key]['cache_time'] = $report['cache_time'];
			$report_meta[$key]['next_update'] = $report_meta[$key]['generated_at'] + $report['cache_time'];
		}

		$dashboard_report_total_generation_time = Utils::timerstop('dashboard_report_total_generation_time');

		Utils::log('Total time spent generating dashboard report: ' . $dashboard_report_total_generation_time . ' seconds');

		// Add report meta to the data array
		$data['report_meta'] = $report_meta;
		echo wp_kses_post(Reports::render_dashboard_report($data));
	}
}





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