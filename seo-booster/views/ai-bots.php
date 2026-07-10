<?php

namespace Cleverplugins\SEOBooster;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
$page = ( isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '' );
$view = ( isset( $_REQUEST['view'] ) ? sanitize_key( wp_unslash( $_REQUEST['view'] ) ) : 'content' );
$filter_days = ( isset( $_REQUEST['filter_days'] ) ? (int) $_REQUEST['filter_days'] : 30 );
if ( !in_array( $filter_days, array(7, 30, 90), true ) ) {
    $filter_days = 30;
}
$summary = AI_Bot_Tracker::get_summary( $filter_days );
$ref_summary = AI_Referral_Tracker::get_summary( $filter_days );
$ref_sources = AI_Referral_Tracker::get_by_source( $filter_days );
$ref_top = AI_Referral_Tracker::get_top_landing_pages( $filter_days, 5 );
$purpose = ( isset( $summary['purpose_breakdown'] ) ? $summary['purpose_breakdown'] : array(
    'research' => 0,
    'citation' => 0,
) );
$ratio = AI_Bot_Tracker::get_content_vs_noise_ratio( $filter_days );
$top_content = AI_Bot_Tracker::get_top_content_pages( $filter_days, 10 );
$noisy_bots = AI_Bot_Tracker::get_bots_mostly_noise( $filter_days, 0.8, 5 );
$tabs = array(
    'content'   => __( 'Content crawled', 'seo-booster' ),
    'by_bot'    => __( 'By bot', 'seo-booster' ),
    'noise'     => __( 'Noise / unmapped', 'seo-booster' ),
    'referrals' => __( 'Referrals', 'seo-booster' ),
);
settings_errors( 'sb_ai_bots' );
?>
<div class="wrap sb-ai-bots-page">
	<?php 
echo wp_kses_post( Utils::show_plugin_headline( __( 'AI Bots', 'seo-booster' ), true ) );
?>

	<?php 
if ( !AI_Bot_Tracker::is_tracking_enabled() ) {
    ?>
		<div class="notice notice-warning">
			<p>
				<?php 
    esc_html_e( 'AI bot tracking is turned off.', 'seo-booster' );
    ?>
				<a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_settings#ai-llm' ) );
    ?>">
					<?php 
    esc_html_e( 'Turn on in Settings', 'seo-booster' );
    ?>
				</a>
			</p>
		</div>
	<?php 
}
?>

	<?php 
if ( !AI_Referral_Tracker::is_tracking_enabled() ) {
    ?>
		<div class="notice notice-warning">
			<p>
				<?php 
    esc_html_e( 'AI referral tracking is turned off.', 'seo-booster' );
    ?>
				<a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_settings#ai-llm' ) );
    ?>">
					<?php 
    esc_html_e( 'Turn on in Settings', 'seo-booster' );
    ?>
				</a>
			</p>
		</div>
	<?php 
}
?>

	<div class="sb-ai-bots-toolbar">
		<form method="get" class="sb-ai-bots-days-form">
			<input type="hidden" name="page" value="<?php 
echo esc_attr( $page );
?>" />
			<input type="hidden" name="view" value="<?php 
echo esc_attr( $view );
?>" />
			<label for="filter-days"><?php 
esc_html_e( 'Period', 'seo-booster' );
?></label>
			<select name="filter_days" id="filter-days" onchange="this.form.submit()">
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
if ( AI_Referral_Tracker::is_tracking_enabled() ) {
    ?>
		<h2 class="sb-ai-bots-section-title"><?php 
    esc_html_e( 'AI referrals', 'seo-booster' );
    ?></h2>
		<p class="description sb-ai-bots-section-intro">
			<?php 
    esc_html_e( 'Human visitors who clicked through from AI answer engines (separate from bot crawlers below).', 'seo-booster' );
    ?>
		</p>
		<div class="sb-ai-bots-summary sb-ai-referrals-summary">
			<div class="card sb-ai-bots-summary-card">
				<strong><?php 
    echo esc_html( sprintf( __( 'Referral visits (%d days)', 'seo-booster' ), $filter_days ) );
    ?></strong>
				<p class="sb-ai-bots-summary-value"><?php 
    echo esc_html( number_format_i18n( (int) $ref_summary['total_visits'] ) );
    ?></p>
			</div>
			<div class="card sb-ai-bots-summary-card">
				<strong><?php 
    esc_html_e( 'AI sources', 'seo-booster' );
    ?></strong>
				<p class="sb-ai-bots-summary-value"><?php 
    echo esc_html( number_format_i18n( (int) $ref_summary['unique_sources'] ) );
    ?></p>
			</div>
			<div class="card sb-ai-bots-summary-card">
				<strong><?php 
    esc_html_e( 'Landing pages', 'seo-booster' );
    ?></strong>
				<p class="sb-ai-bots-summary-value"><?php 
    echo esc_html( number_format_i18n( (int) $ref_summary['unique_pages'] ) );
    ?></p>
			</div>
			<div class="card sb-ai-bots-summary-card">
				<strong><?php 
    esc_html_e( 'Top source', 'seo-booster' );
    ?></strong>
				<p class="sb-ai-bots-summary-value">
					<?php 
    if ( !empty( $ref_summary['top_source'] ) ) {
        echo esc_html( $ref_summary['top_source'] );
        echo '<br /><span class="description">' . esc_html( number_format_i18n( (int) $ref_summary['top_source_visits'] ) ) . '</span>';
    } else {
        echo esc_html( '—' );
    }
    ?>
				</p>
			</div>
		</div>

		<div class="ai-traffic-container sb-ai-bots-insights sb-ai-referrals-insights">
			<div class="ai-traffic-chart-section sb-ai-bots-chart-section">
				<h3><?php 
    esc_html_e( 'Referrals over time', 'seo-booster' );
    ?></h3>
				<div class="chart-container ai-bot-breakdown-chart">
					<canvas id="sb-ai-referrals-visits-chart" height="180"></canvas>
				</div>
			</div>
			<div class="ai-traffic-table-section">
				<h3><?php 
    esc_html_e( 'Top referral landing pages', 'seo-booster' );
    ?></h3>
				<div class="ai-bot-table-container">
					<?php 
    if ( !empty( $ref_top ) ) {
        ?>
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
					<?php 
    } else {
        ?>
						<p class="description"><?php 
        esc_html_e( 'No AI referral visits in this period yet.', 'seo-booster' );
        ?></p>
					<?php 
    }
    ?>
				</div>
			</div>
		</div>
	<?php 
}
?>

	<h2 class="sb-ai-bots-section-title"><?php 
esc_html_e( 'AI bot crawlers', 'seo-booster' );
?></h2>

	<div class="sb-ai-bots-summary">
		<div class="card sb-ai-bots-summary-card">
			<strong><?php 
echo esc_html( sprintf( __( 'Visits (%d days)', 'seo-booster' ), $filter_days ) );
?></strong>
			<p class="sb-ai-bots-summary-value"><?php 
echo esc_html( number_format_i18n( (int) $summary['total_visits'] ) );
?></p>
		</div>
		<div class="card sb-ai-bots-summary-card">
			<strong><?php 
esc_html_e( 'Unique bots', 'seo-booster' );
?></strong>
			<p class="sb-ai-bots-summary-value"><?php 
echo esc_html( number_format_i18n( (int) $summary['unique_bots'] ) );
?></p>
		</div>
		<div class="card sb-ai-bots-summary-card">
			<strong><?php 
esc_html_e( 'Content pages crawled', 'seo-booster' );
?></strong>
			<p class="sb-ai-bots-summary-value"><?php 
echo esc_html( number_format_i18n( (int) $summary['unique_content_pages'] ) );
?></p>
		</div>
		<div class="card sb-ai-bots-summary-card sb-ai-bots-purpose-card">
			<strong><?php 
echo esc_html( sprintf( __( 'Purpose (%d days)', 'seo-booster' ), $filter_days ) );
?></strong>
			<div class="sb-ai-bots-purpose-chart-wrap">
				<canvas id="sb-ai-bots-purpose-chart" width="120" height="120" aria-hidden="true"></canvas>
			</div>
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

	<div class="notice notice-info inline sb-ai-bots-insight">
		<p>
			<?php 
printf( 
    /* translators: 1: percent of content visits, 2: number of days */
    esc_html__( '%1$s%% of AI bot visits hit mapped content in the last %2$d days.', 'seo-booster' ),
    esc_html( number_format_i18n( $ratio['content_percent'], 1 ) ),
    (int) $filter_days
 );
?>
		</p>
	</div>

	<div class="ai-traffic-container sb-ai-bots-insights">
		<div class="ai-traffic-chart-section sb-ai-bots-chart-section">
			<h2><?php 
esc_html_e( 'Visits over time', 'seo-booster' );
?></h2>
			<div class="chart-container ai-bot-breakdown-chart">
				<canvas id="sb-ai-bots-visits-chart" height="220"></canvas>
			</div>
		</div>
		<div class="ai-traffic-table-section">
			<h2><?php 
esc_html_e( 'Top crawled content', 'seo-booster' );
?></h2>
			<div class="ai-bot-table-container">
				<?php 
if ( !empty( $top_content ) ) {
    ?>
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
				<?php 
} else {
    ?>
					<p class="description"><?php 
    esc_html_e( 'No mapped content visits in this period.', 'seo-booster' );
    ?></p>
				<?php 
}
?>
			</div>

			<?php 
if ( !empty( $noisy_bots ) ) {
    ?>
				<h3><?php 
    esc_html_e( 'Bots mostly hitting noise', 'seo-booster' );
    ?></h3>
				<div class="ai-bot-table-container">
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
					<p class="description ai-bot-note">
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
		</div>
	</div>

	<p class="description sb-ai-bots-intro">
		<?php 
if ( 'referrals' === $view ) {
    esc_html_e( 'Full log of human visits referred from AI answer engines.', 'seo-booster' );
} else {
    esc_html_e( 'See which real pages AI crawlers fetch on your site. Use Content crawled for actionable pages, By bot for crawler totals, Noise for search traps and unmapped URLs, and Referrals for human traffic from AI engines.', 'seo-booster' );
}
?>
	</p>

	<nav class="nav-tab-wrapper sb-ai-bots-tabs">
		<?php 
foreach ( $tabs as $tab_key => $tab_label ) {
    ?>
			<a href="
			<?php 
    echo esc_url( add_query_arg( array(
        'page'        => $page,
        'view'        => $tab_key,
        'filter_days' => $filter_days,
    ), admin_url( 'admin.php' ) ) );
    ?>
						" class="nav-tab <?php 
    echo ( $view === $tab_key ? 'nav-tab-active' : '' );
    ?>">
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
echo esc_attr( $page );
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
</div>
<?php 