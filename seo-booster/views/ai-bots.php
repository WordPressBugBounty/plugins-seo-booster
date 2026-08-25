<?php

namespace Cleverplugins\SEOBooster;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
$admin_page_slug = ( isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '' );
$view = ( isset( $_REQUEST['view'] ) ? sanitize_key( wp_unslash( $_REQUEST['view'] ) ) : 'content' );
$filter_days = ( isset( $_REQUEST['filter_days'] ) ? (int) $_REQUEST['filter_days'] : 30 );
if ( !in_array( $filter_days, array(7, 30, 90), true ) ) {
    $filter_days = 30;
}
$is_referrals_view = 'referrals' === $view;
$bot_tracking_on = AI_Bot_Tracker::is_tracking_enabled();
$ref_tracking_on = AI_Referral_Tracker::is_tracking_enabled();
$summary = array();
$purpose = array(
    'research' => 0,
    'citation' => 0,
);
$ratio = array(
    'content_percent' => 0,
);
$top_content = array();
$noisy_bots = array();
$ref_summary = array(
    'total_visits'      => 0,
    'unique_sources'    => 0,
    'unique_pages'      => 0,
    'top_source'        => '',
    'top_source_visits' => 0,
);
$ref_top = array();
if ( $is_referrals_view ) {
    $ref_summary = AI_Referral_Tracker::get_summary( $filter_days );
    $ref_top = AI_Referral_Tracker::get_top_landing_pages( $filter_days, 5 );
} else {
    $summary = AI_Bot_Tracker::get_summary( $filter_days );
    $purpose = ( isset( $summary['purpose_breakdown'] ) ? $summary['purpose_breakdown'] : $purpose );
    $ratio = AI_Bot_Tracker::get_content_vs_noise_ratio( $filter_days );
    $top_content = AI_Bot_Tracker::get_top_content_pages( $filter_days, 10 );
    $noisy_bots = AI_Bot_Tracker::get_bots_mostly_noise( $filter_days, 0.8, 5 );
}
$view_tabs = array(
    'content'   => __( 'Content crawled', 'seo-booster' ),
    'by_bot'    => __( 'By bot', 'seo-booster' ),
    'noise'     => __( 'Noise / unmapped', 'seo-booster' ),
    'referrals' => __( 'Referrals', 'seo-booster' ),
);
$settings_ai_url = admin_url( 'admin.php?page=sb2_settings#ai-llm' );
settings_errors( 'sb_ai_bots' );
?>
<div class="wrap sb-wrap sb-dashboard sb-ai-bots-page">
	<?php 
echo wp_kses_post( Utils::show_plugin_headline( __( 'AI Bots', 'seo-booster' ), true ) );
?>

	<?php 
if ( !$bot_tracking_on || !$ref_tracking_on ) {
    ?>
		<section class="sb-ui-cta" aria-labelledby="sb-ai-bots-tracking-title">
			<h2 class="sb-ui-cta__title" id="sb-ai-bots-tracking-title">
				<?php 
    esc_html_e( 'Tracking is turned off', 'seo-booster' );
    ?>
			</h2>
			<p class="sb-ui-cta__lead">
				<?php 
    if ( !$bot_tracking_on && !$ref_tracking_on ) {
        esc_html_e( 'AI bot crawler tracking and AI referral tracking are both off. Turn them on in Settings to collect data for this report.', 'seo-booster' );
    } elseif ( !$bot_tracking_on ) {
        esc_html_e( 'AI bot crawler tracking is turned off. Turn it on in Settings to collect crawler visits for this report.', 'seo-booster' );
    } else {
        esc_html_e( 'AI referral tracking is turned off. Turn it on in Settings to collect human visits from AI answer engines.', 'seo-booster' );
    }
    ?>
			</p>
			<div class="sb-ui-cta__actions">
				<a class="button button-primary" href="<?php 
    echo esc_url( $settings_ai_url );
    ?>">
					<?php 
    esc_html_e( 'Open AI settings', 'seo-booster' );
    ?>
				</a>
			</div>
		</section>
	<?php 
}
?>

	<div class="sb-ai-bots-toolbar">
		<form method="get" class="sb-ai-bots-days-form" id="sb-ai-bots-days-form">
			<input type="hidden" name="page" value="<?php 
echo esc_attr( $admin_page_slug );
?>" />
			<input type="hidden" name="view" value="<?php 
echo esc_attr( $view );
?>" />
			<label for="filter-days"><?php 
esc_html_e( 'Period', 'seo-booster' );
?></label>
			<select name="filter_days" id="filter-days">
				<option value="7" <?php 
selected( $filter_days, 7 );
?>><?php 
esc_html_e( 'Last 7 days', 'seo-booster' );
?></option>
				<option value="30" <?php 
selected( $filter_days, 30 );
?>><?php 
esc_html_e( 'Last 30 days', 'seo-booster' );
?></option>
				<option value="90" <?php 
selected( $filter_days, 90 );
?>><?php 
esc_html_e( 'Last 90 days', 'seo-booster' );
?></option>
			</select>
		</form>
	</div>

	<?php 
if ( $is_referrals_view ) {
    ?>
		<?php 
    if ( $ref_tracking_on ) {
        ?>
			<section class="sb-ui-panel" aria-labelledby="sb-ai-bots-ref-overview-title">
				<h2 class="sb-ui-title" id="sb-ai-bots-ref-overview-title"><?php 
        esc_html_e( 'AI referrals overview', 'seo-booster' );
        ?></h2>
				<p class="sb-ui-lead">
					<?php 
        esc_html_e( 'Human visitors who clicked through from AI answer engines (separate from bot crawlers).', 'seo-booster' );
        ?>
				</p>
				<div class="sb-action-cards">
					<article class="sb-action-card">
						<div class="sb-action-card__num"><?php 
        echo esc_html( number_format_i18n( (int) $ref_summary['total_visits'] ) );
        ?></div>
						<h3 class="sb-action-card__title">
							<?php 
        echo esc_html( sprintf( 
            /* translators: %d: number of days */
            __( 'Referral visits (%d days)', 'seo-booster' ),
            $filter_days
         ) );
        ?>
						</h3>
					</article>
					<article class="sb-action-card">
						<div class="sb-action-card__num"><?php 
        echo esc_html( number_format_i18n( (int) $ref_summary['unique_sources'] ) );
        ?></div>
						<h3 class="sb-action-card__title"><?php 
        esc_html_e( 'AI sources', 'seo-booster' );
        ?></h3>
					</article>
					<article class="sb-action-card">
						<div class="sb-action-card__num"><?php 
        echo esc_html( number_format_i18n( (int) $ref_summary['unique_pages'] ) );
        ?></div>
						<h3 class="sb-action-card__title"><?php 
        esc_html_e( 'Landing pages', 'seo-booster' );
        ?></h3>
					</article>
					<article class="sb-action-card">
						<div class="sb-action-card__num sb-ai-bots-top-source">
							<?php 
        if ( !empty( $ref_summary['top_source'] ) ) {
            echo esc_html( $ref_summary['top_source'] );
        } else {
            esc_html_e( 'None', 'seo-booster' );
        }
        ?>
						</div>
						<h3 class="sb-action-card__title">
							<?php 
        if ( !empty( $ref_summary['top_source'] ) ) {
            echo esc_html( sprintf( 
                /* translators: %s: visit count */
                __( 'Top source (%s visits)', 'seo-booster' ),
                number_format_i18n( (int) $ref_summary['top_source_visits'] )
             ) );
        } else {
            esc_html_e( 'Top source', 'seo-booster' );
        }
        ?>
						</h3>
					</article>
				</div>
			</section>

			<div class="sb-ui-grid sb-ui-grid--2">
				<section class="sb-ui-panel" aria-labelledby="sb-ai-bots-ref-chart-title">
					<h2 class="sb-ui-title" id="sb-ai-bots-ref-chart-title"><?php 
        esc_html_e( 'Referrals over time', 'seo-booster' );
        ?></h2>
					<div class="sb-ai-bots-chart-wrap">
						<canvas id="sb-ai-referrals-visits-chart" height="180"></canvas>
					</div>
				</section>
				<section class="sb-ui-panel" aria-labelledby="sb-ai-bots-ref-top-title">
					<h2 class="sb-ui-title" id="sb-ai-bots-ref-top-title"><?php 
        esc_html_e( 'Top referral landing pages', 'seo-booster' );
        ?></h2>
					<?php 
        if ( !empty( $ref_top ) ) {
            ?>
						<div class="sb-ai-bots-table-wrap">
							<table class="widefat striped">
								<thead>
									<tr>
										<th><?php 
            esc_html_e( 'Page', 'seo-booster' );
            ?></th>
										<th><?php 
            esc_html_e( 'Visits', 'seo-booster' );
            ?></th>
										<th><?php 
            esc_html_e( 'Sources', 'seo-booster' );
            ?></th>
									</tr>
								</thead>
								<tbody>
									<?php 
            foreach ( $ref_top as $row ) {
                ?>
										<?php 
                $label = AI_Bot_Tracker::resolve_object_label( (int) $row['object_id'], $row['object_type'] );
                ?>
										<tr>
											<td>
												<?php 
                if ( !empty( $label['view_url'] ) && !empty( $label['title'] ) ) {
                    ?>
													<a href="<?php 
                    echo esc_url( $label['view_url'] );
                    ?>" target="_blank" rel="noopener noreferrer"><?php 
                    echo esc_html( $label['title'] );
                    ?></a>
												<?php 
                } elseif ( !empty( $row['normalized_url'] ) ) {
                    ?>
													<a href="<?php 
                    echo esc_url( $row['normalized_url'] );
                    ?>" target="_blank" rel="noopener noreferrer"><?php 
                    echo esc_html( AI_Bot_Tracker::truncate_display( $row['normalized_url'], 60 ) );
                    ?></a>
												<?php 
                } else {
                    ?>
													<?php 
                    echo esc_html( AI_Bot_Tracker::truncate_display( $row['landing_path'], 60 ) );
                    ?>
												<?php 
                }
                ?>
											</td>
											<td><?php 
                echo esc_html( number_format_i18n( (int) $row['visits'] ) );
                ?></td>
											<td><?php 
                echo esc_html( ( isset( $row['sources'] ) ? AI_Bot_Tracker::truncate_display( $row['sources'], 40 ) : '' ) );
                ?></td>
										</tr>
									<?php 
            }
            ?>
								</tbody>
							</table>
						</div>
					<?php 
        } else {
            ?>
						<p class="sb-ui-empty"><?php 
            esc_html_e( 'No AI referral visits in this period yet.', 'seo-booster' );
            ?></p>
					<?php 
        }
        ?>
				</section>
			</div>
		<?php 
    }
    ?>
	<?php 
} else {
    ?>
		<section class="sb-ui-panel" aria-labelledby="sb-ai-bots-overview-title">
			<h2 class="sb-ui-title" id="sb-ai-bots-overview-title"><?php 
    esc_html_e( 'AI bot crawlers overview', 'seo-booster' );
    ?></h2>
			<p class="sb-ui-lead">
				<?php 
    esc_html_e( 'Which AI crawlers fetch your pages, how often, and whether they hit real content or noise.', 'seo-booster' );
    ?>
			</p>
			<div class="sb-action-cards">
				<article class="sb-action-card">
					<div class="sb-action-card__num"><?php 
    echo esc_html( number_format_i18n( (int) ($summary['total_visits'] ?? 0) ) );
    ?></div>
					<h3 class="sb-action-card__title">
						<?php 
    echo esc_html( sprintf( 
        /* translators: %d: number of days */
        __( 'Visits (%d days)', 'seo-booster' ),
        $filter_days
     ) );
    ?>
					</h3>
				</article>
				<article class="sb-action-card">
					<div class="sb-action-card__num"><?php 
    echo esc_html( number_format_i18n( (int) ($summary['unique_bots'] ?? 0) ) );
    ?></div>
					<h3 class="sb-action-card__title"><?php 
    esc_html_e( 'Unique bots', 'seo-booster' );
    ?></h3>
				</article>
				<article class="sb-action-card">
					<div class="sb-action-card__num"><?php 
    echo esc_html( number_format_i18n( (int) ($summary['unique_content_pages'] ?? 0) ) );
    ?></div>
					<h3 class="sb-action-card__title"><?php 
    esc_html_e( 'Content pages crawled', 'seo-booster' );
    ?></h3>
				</article>
			</div>

			<div class="sb-ai-bots-purpose">
				<div class="sb-ai-bots-purpose-chart-wrap">
					<canvas id="sb-ai-bots-purpose-chart" width="120" height="120" aria-hidden="true"></canvas>
				</div>
				<div class="sb-ai-bots-purpose-legend">
					<p class="sb-ai-bots-purpose-legend__title">
						<?php 
    echo esc_html( sprintf( 
        /* translators: %d: number of days */
        __( 'Purpose (%d days)', 'seo-booster' ),
        $filter_days
     ) );
    ?>
					</p>
					<p>
						<?php 
    printf( 
        /* translators: %s: visit count */
        esc_html__( 'Research / training: %s', 'seo-booster' ),
        esc_html( number_format_i18n( (int) $purpose['research'] ) )
     );
    ?>
					</p>
					<p>
						<?php 
    printf( 
        /* translators: %s: visit count */
        esc_html__( 'Citation / answer engine: %s', 'seo-booster' ),
        esc_html( number_format_i18n( (int) $purpose['citation'] ) )
     );
    ?>
					</p>
				</div>
			</div>

			<p class="sb-ui-tip">
				<?php 
    printf( 
        /* translators: 1: percent of content visits, 2: number of days */
        esc_html__( '%1$s%% of AI bot visits hit mapped content in the last %2$d days.', 'seo-booster' ),
        esc_html( number_format_i18n( $ratio['content_percent'], 1 ) ),
        (int) $filter_days
     );
    ?>
			</p>
		</section>

		<div class="sb-ui-grid sb-ui-grid--2">
			<section class="sb-ui-panel" aria-labelledby="sb-ai-bots-visits-chart-title">
				<h2 class="sb-ui-title" id="sb-ai-bots-visits-chart-title"><?php 
    esc_html_e( 'Visits over time', 'seo-booster' );
    ?></h2>
				<div class="sb-ai-bots-chart-wrap sb-ai-bots-chart-wrap--tall">
					<canvas id="sb-ai-bots-visits-chart" height="220"></canvas>
				</div>
			</section>
			<section class="sb-ui-panel" aria-labelledby="sb-ai-bots-top-content-title">
				<h2 class="sb-ui-title" id="sb-ai-bots-top-content-title"><?php 
    esc_html_e( 'Top crawled content', 'seo-booster' );
    ?></h2>
				<?php 
    if ( !empty( $top_content ) ) {
        ?>
					<div class="sb-ai-bots-table-wrap">
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php 
        esc_html_e( 'Page', 'seo-booster' );
        ?></th>
									<th><?php 
        esc_html_e( 'Visits', 'seo-booster' );
        ?></th>
									<th><?php 
        esc_html_e( 'Bots', 'seo-booster' );
        ?></th>
								</tr>
							</thead>
							<tbody>
								<?php 
        foreach ( $top_content as $row ) {
            ?>
									<?php 
            $label = AI_Bot_Tracker::resolve_object_label( (int) $row['object_id'], $row['object_type'] );
            ?>
									<tr>
										<td>
											<?php 
            if ( !empty( $label['view_url'] ) ) {
                ?>
												<a href="<?php 
                echo esc_url( $label['view_url'] );
                ?>" target="_blank" rel="noopener noreferrer"><?php 
                echo esc_html( $label['title'] );
                ?></a>
											<?php 
            } else {
                ?>
												<?php 
                echo esc_html( $label['title'] );
                ?>
											<?php 
            }
            ?>
										</td>
										<td><?php 
            echo esc_html( number_format_i18n( (int) $row['visits'] ) );
            ?></td>
										<td><?php 
            echo esc_html( ( isset( $row['bot_names'] ) ? AI_Bot_Tracker::truncate_display( $row['bot_names'], 40 ) : '' ) );
            ?></td>
									</tr>
								<?php 
        }
        ?>
							</tbody>
						</table>
					</div>
				<?php 
    } else {
        ?>
					<p class="sb-ui-empty"><?php 
        esc_html_e( 'No mapped content visits in this period.', 'seo-booster' );
        ?></p>
				<?php 
    }
    ?>

				<?php 
    if ( !empty( $noisy_bots ) ) {
        ?>
					<h3 class="sb-ai-bots-subheading"><?php 
        esc_html_e( 'Bots mostly hitting noise', 'seo-booster' );
        ?></h3>
					<div class="sb-ai-bots-table-wrap">
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php 
        esc_html_e( 'Bot', 'seo-booster' );
        ?></th>
									<th><?php 
        esc_html_e( 'Noise %', 'seo-booster' );
        ?></th>
								</tr>
							</thead>
							<tbody>
								<?php 
        foreach ( $noisy_bots as $bot_row ) {
            ?>
									<tr>
										<td><?php 
            echo esc_html( $bot_row['bot_name'] );
            ?></td>
										<td><?php 
            echo esc_html( number_format_i18n( (float) $bot_row['noise_ratio'], 1 ) );
            ?>%</td>
									</tr>
								<?php 
        }
        ?>
							</tbody>
						</table>
					</div>
					<?php 
        $show_blocking_upsell = true;
        if ( $show_blocking_upsell ) {
            ?>
						<p class="sb-ui-tip sb-ai-bots-upsell">
							<?php 
            esc_html_e( 'Upgrade to Pro to block noisy AI bots at the server.', 'seo-booster' );
            ?>
						</p>
					<?php 
        }
        ?>
				<?php 
    }
    ?>
			</section>
		</div>
	<?php 
}
?>

	<section class="sb-ui-panel sb-ai-bots-results" aria-labelledby="sb-ai-bots-results-title">
		<h2 class="sb-ui-title" id="sb-ai-bots-results-title">
			<?php 
if ( $is_referrals_view ) {
    esc_html_e( 'Referral log', 'seo-booster' );
} else {
    esc_html_e( 'Crawler log', 'seo-booster' );
}
?>
		</h2>
		<p class="sb-ui-lead">
			<?php 
if ( $is_referrals_view ) {
    esc_html_e( 'Full log of human visits referred from AI answer engines.', 'seo-booster' );
} else {
    esc_html_e( 'See which real pages AI crawlers fetch on your site. Use Content crawled for actionable pages, By bot for crawler totals, Noise for search traps and unmapped URLs, and Referrals for human traffic from AI engines.', 'seo-booster' );
}
?>
		</p>

		<nav class="nav-tab-wrapper sb-ai-bots-tabs">
			<?php 
foreach ( $view_tabs as $tab_key => $tab_label ) {
    ?>
				<a
					href="
					<?php 
    echo esc_url( add_query_arg( array(
        'page'        => $admin_page_slug,
        'view'        => $tab_key,
        'filter_days' => $filter_days,
    ), admin_url( 'admin.php' ) ) );
    ?>
							"
					class="nav-tab <?php 
    echo ( $view === $tab_key ? 'nav-tab-active' : '' );
    ?>"
				>
					<?php 
    echo esc_html( $tab_label );
    ?>
				</a>
			<?php 
}
?>
		</nav>

		<form id="ai-bots-filter" method="get">
			<input type="hidden" name="page" value="<?php 
echo esc_attr( $admin_page_slug );
?>" />
			<input type="hidden" name="view" value="<?php 
echo esc_attr( $view );
?>" />
			<input type="hidden" name="filter_days" value="<?php 
echo esc_attr( (string) $filter_days );
?>" />
			<?php 
$ai_bots_list_table->search_box( __( 'Search', 'seo-booster' ), 'ai-bots-search' );
?>
			<?php 
$ai_bots_list_table->display();
?>
		</form>
	</section>
</div>
<?php 