<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEO Possibilities Admin Page
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */

require_once SEOBOOSTER_PLUGINPATH . 'inc/SEO_Issues_Manager.php';
require_once SEOBOOSTER_PLUGINPATH . 'inc/SEO_Issues_List_Table.php';
require_once SEOBOOSTER_PLUGINPATH . 'inc/SEO_Issues_Ajax.php';
require_once SEOBOOSTER_PLUGINPATH . 'inc/SEO_Sitewide_Analysis.php';

/**
 * Human-readable severity label for sitewide checklist chips.
 *
 * @param string $severity Severity slug.
 * @return string
 */
function sb_seo_issues_severity_label( $severity ) {
	$labels = array(
		'critical' => __( 'Critical', 'seo-booster' ),
		'high'     => __( 'High', 'seo-booster' ),
		'medium'   => __( 'Medium', 'seo-booster' ),
		'low'      => __( 'Low', 'seo-booster' ),
		'good'     => __( 'Good', 'seo-booster' ),
		'error'    => __( 'Critical', 'seo-booster' ),
		'warning'  => __( 'High', 'seo-booster' ),
	);

	return $labels[ $severity ] ?? ucfirst( (string) $severity );
}

/**
 * Render the SEO Possibilities admin page.
 *
 * @since 6.1.26
 * @return void
 */
function render_seo_issues_page() {
	$has_triage = SEO_Issues_Manager::user_can_triage_possibilities();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter/view args.
	$current_severity   = isset( $_GET['severity'] ) ? sanitize_text_field( wp_unslash( $_GET['severity'] ) ) : '';
	$current_issue_type = isset( $_GET['issue_type'] ) ? sanitize_text_field( wp_unslash( $_GET['issue_type'] ) ) : '';
	$current_view       = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : ( $has_triage ? 'type' : 'url' );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	// Free: discovery URL list only. Ignore triage query args.
	if ( ! $has_triage ) {
		$current_view       = 'url';
		$current_issue_type = '';
	}

	if ( ! in_array( $current_view, array( 'type', 'url' ), true ) ) {
		$current_view = $has_triage ? 'type' : 'url';
	}
	// Opening a specific issue type implies the By URL worklist (Pro).
	if ( $has_triage && '' !== $current_issue_type ) {
		$current_view = 'url';
	}

	$list_table = new SEO_Issues_List_Table();
	if ( 'url' === $current_view || ! $has_triage ) {
		$list_table->prepare_items();
	}

	$stats                   = SEO_Issues_Manager::get_analysis_stats();
	$sitewide_stats          = SEO_Issues_Manager::get_sitewide_stats();
	$sitewide_issues         = SEO_Issues_Manager::get_sitewide_issues();
	$urls_with_possibilities = SEO_Issues_Manager::get_urls_with_issues_count();
	$sitewide_analysis_run   = (bool) get_option( 'sb_sitewide_analysis_run', false ) || ! empty( $sitewide_issues );

	$type_filters = array();
	if ( '' !== $current_severity ) {
		$type_filters['severity'] = $current_severity;
	}

	$issue_types_all     = array();
	$issue_types_preview = array();
	$has_more_types      = false;
	$top_urls            = array();
	$active_type_label   = $current_issue_type;
	$do_next             = null;

	if ( $has_triage ) {
		if ( 'type' === $current_view ) {
			$issue_types_all     = SEO_Issues_Manager::get_issue_types_summary( 0, $type_filters );
			$issue_types_preview = array_slice( $issue_types_all, 0, 12 );
			$has_more_types      = count( $issue_types_all ) > 12;
		}

		if ( 'url' === $current_view ) {
			$url_filters = array_filter(
				array(
					'severity'   => $current_severity,
					'issue_type' => $current_issue_type,
					'status'     => 'active',
				)
			);
			$top_urls    = SEO_Issues_Manager::get_urls_with_issues( $url_filters, 10, 0, 'severity_traffic', 'DESC' );

			if ( '' !== $current_issue_type ) {
				$active_type_label = SEO_Issues_Manager::get_issue_type_message( $current_issue_type );
				if ( '' === $active_type_label ) {
					$active_type_label = $current_issue_type;
				}
			}
		}

		$do_next_source = ( 'type' === $current_view ) ? $issue_types_preview : SEO_Issues_Manager::get_issue_types_summary( 12, $type_filters );
		if ( ! empty( $do_next_source ) ) {
			foreach ( $do_next_source as $type_row ) {
				if ( in_array( $type_row['severity'], array( 'critical', 'high', 'error', 'warning' ), true ) ) {
					$do_next = $type_row;
					break;
				}
			}
			if ( ! $do_next ) {
				$do_next = $do_next_source[0];
			}
		}
	}

	$base_args = array();
	if ( '' !== $current_severity ) {
		$base_args['severity'] = $current_severity;
	}

	$sitewide_critical = (int) ( $sitewide_stats['critical'] ?? 0 );
	$sitewide_high     = (int) ( $sitewide_stats['high'] ?? 0 );
	$sitewide_medium   = (int) ( $sitewide_stats['medium'] ?? 0 );
	$sitewide_good     = (int) ( $sitewide_stats['good'] ?? 0 );
	$sitewide_open     = $sitewide_critical + $sitewide_high + $sitewide_medium;

	$last_run = '';
	if ( ! empty( $sitewide_issues[0]->analyzed_at ) ) {
		$last_run = $sitewide_issues[0]->analyzed_at;
	}

	$filter_clear_url = SEO_Issues_Manager::get_possibilities_page_url( array( 'view' => $current_view ) );
	$critical_url     = SEO_Issues_Manager::get_possibilities_page_url(
		array_merge(
			$base_args,
			array(
				'view'     => $current_view,
				'severity' => 'critical',
			)
		)
	);
	$high_url         = SEO_Issues_Manager::get_possibilities_page_url(
		array(
			'view'     => $current_view,
			'severity' => 'high',
		)
	);
	$urls_filter_url  = SEO_Issues_Manager::get_possibilities_page_url( array( 'view' => 'url' ) );
	$total_filter_url = $filter_clear_url;

	$upgrade_url = Utils::get_pro_upgrade_url( 'possibilities_pro_strip' );

	?>
	<div class="wrap sb-wrap sb-dashboard sb-seo-issues-page">
		<?php echo wp_kses_post( Utils::show_plugin_headline( __( 'SEO Possibilities', 'seo-booster' ), true ) ); ?>

		<?php if ( ! $sitewide_analysis_run ) : ?>
			<section class="sb-ui-cta" id="sb-sitewide-first-run" aria-labelledby="sb-possibilities-intro">
				<p class="sb-ui-cta__lead" id="sb-possibilities-intro">
					<?php esc_html_e( 'Review on-page and sitewide opportunities to improve how your content shows up in search and AI answers.', 'seo-booster' ); ?>
				</p>
				<div class="sb-ui-cta__actions">
					<button type="button" class="button button-primary" id="sb-run-sitewide-analysis">
						<?php esc_html_e( 'Run sitewide checks', 'seo-booster' ); ?>
					</button>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $has_triage && $do_next ) : ?>
			<?php
			$do_next_urls_url = SEO_Issues_Manager::get_possibilities_page_url(
				array(
					'view'       => 'url',
					'issue_type' => $do_next['issue_key'],
					'severity'   => in_array( $do_next['severity'], array( 'critical', 'high' ), true ) ? $do_next['severity'] : '',
				)
			);
			$do_next_tool     = ! empty( $do_next['tool'] ) ? $do_next['tool'] : null;
			$do_next_cta_url  = $do_next_tool ? $do_next_tool['url'] : $do_next_urls_url;
			$do_next_cta      = $do_next_tool ? $do_next_tool['label'] : __( 'Show these URLs', 'seo-booster' );
			?>
			<section class="sb-ui-panel" id="sb-possibilities-do-next" aria-labelledby="sb-possibilities-do-next-title">
				<h2 class="sb-ui-title" id="sb-possibilities-do-next-title"><?php esc_html_e( 'Do next', 'seo-booster' ); ?></h2>
				<p class="sb-ui-lead">
					<?php esc_html_e( 'Fix the highest-impact problems first. Use By type for bulk Tools, or open a URL to edit.', 'seo-booster' ); ?>
				</p>
				<div class="sb-action-cards">
					<article class="sb-action-card sb-action-card--blocker" data-action-id="do-next-type">
						<div class="sb-action-card__num<?php echo in_array( $do_next['severity'], array( 'critical', 'error' ), true ) ? ' sb-action-card__num--warn' : ''; ?>">
							<?php echo esc_html( number_format_i18n( (int) $do_next['affected_urls'] ) ); ?>
						</div>
						<h3 class="sb-action-card__title">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: number of pages, 2: possibility message */
									__( 'Start with %1$d pages: %2$s', 'seo-booster' ),
									(int) $do_next['affected_urls'],
									$do_next['message']
								)
							);
							?>
						</h3>
						<a class="sb-action-card__link" href="<?php echo esc_url( $do_next_cta_url ); ?>">
							<?php echo esc_html( $do_next_cta ); ?>
						</a>
						<?php if ( $do_next_tool ) : ?>
							<a class="sb-action-card__link sb-action-card__link--secondary" href="<?php echo esc_url( $do_next_urls_url ); ?>">
								<?php esc_html_e( 'Show these URLs', 'seo-booster' ); ?>
							</a>
						<?php endif; ?>
					</article>
				</div>
			</section>
		<?php endif; ?>

		<section class="sb-ui-panel" aria-labelledby="sb-possibilities-stats-title">
			<h2 class="sb-ui-title" id="sb-possibilities-stats-title"><?php esc_html_e( 'Overview', 'seo-booster' ); ?></h2>
			<p class="sb-ui-lead">
				<?php
				if ( $has_triage ) {
					esc_html_e( 'Click a severity to filter the worklists below.', 'seo-booster' );
				} else {
					esc_html_e( 'Click a severity to filter the URL list below.', 'seo-booster' );
				}
				?>
			</p>
			<div class="sb-action-cards" id="sb-possibilities-stat-cards">
				<a class="sb-action-card sb-action-card--filter<?php echo '' === $current_severity ? ' is-active' : ''; ?> sb-action-card--secondary" href="<?php echo esc_url( $total_filter_url ); ?>">
					<div class="sb-action-card__num" data-stat="total_issues"><?php echo esc_html( number_format_i18n( (int) ( $stats['total_issues'] ?? 0 ) ) ); ?></div>
					<h3 class="sb-action-card__title"><?php esc_html_e( 'Total possibilities', 'seo-booster' ); ?></h3>
				</a>
				<a class="sb-action-card sb-action-card--filter<?php echo 'critical' === $current_severity ? ' is-active' : ''; ?>" href="<?php echo esc_url( $critical_url ); ?>">
					<div class="sb-action-card__num<?php echo ! empty( $stats['critical'] ) ? ' sb-action-card__num--warn' : ''; ?>" data-stat="critical"><?php echo esc_html( number_format_i18n( (int) ( $stats['critical'] ?? 0 ) ) ); ?></div>
					<h3 class="sb-action-card__title"><?php esc_html_e( 'Critical', 'seo-booster' ); ?></h3>
				</a>
				<a class="sb-action-card sb-action-card--filter<?php echo 'high' === $current_severity ? ' is-active' : ''; ?>" href="<?php echo esc_url( $high_url ); ?>">
					<div class="sb-action-card__num" data-stat="high"><?php echo esc_html( number_format_i18n( (int) ( $stats['high'] ?? 0 ) ) ); ?></div>
					<h3 class="sb-action-card__title"><?php esc_html_e( 'High', 'seo-booster' ); ?></h3>
				</a>
				<a class="sb-action-card sb-action-card--filter<?php echo 'url' === $current_view && '' === $current_severity && '' === $current_issue_type ? ' is-active' : ''; ?>" href="<?php echo esc_url( $urls_filter_url ); ?>">
					<div class="sb-action-card__num" data-stat="urls_count"><?php echo esc_html( number_format_i18n( (int) $urls_with_possibilities ) ); ?></div>
					<h3 class="sb-action-card__title"><?php esc_html_e( 'URLs with possibilities', 'seo-booster' ); ?></h3>
				</a>
			</div>
			<?php if ( '' !== $current_severity || '' !== $current_issue_type ) : ?>
				<p class="sb-filter-clear">
					<a href="<?php echo esc_url( SEO_Issues_Manager::get_possibilities_page_url( array( 'view' => $has_triage ? 'type' : 'url' ) ) ); ?>">
						<?php esc_html_e( 'Clear filters', 'seo-booster' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</section>

		<?php if ( ! $has_triage ) : ?>
			<section class="sb-ui-panel sb-possibilities-pro-strip" aria-labelledby="sb-possibilities-pro-title">
				<h2 class="sb-ui-title" id="sb-possibilities-pro-title">
					<?php esc_html_e( 'Triage faster with Pro', 'seo-booster' ); ?>
					<span class="sb-action-card__badge sb-action-card__badge--pro"><?php esc_html_e( 'Pro', 'seo-booster' ); ?></span>
				</h2>
				<p class="sb-ui-lead"><?php esc_html_e( 'Free lists every finding. Pro turns Possibilities into a work queue.', 'seo-booster' ); ?></p>
				<ul class="sb-possibilities-pro-strip__list">
					<li><?php esc_html_e( 'Do next: start with the highest-impact problem type', 'seo-booster' ); ?></li>
					<li><?php esc_html_e( 'By type worklist for bulk Tools workflows', 'seo-booster' ); ?></li>
					<li><?php esc_html_e( 'Top URLs prioritized by severity and Search Console traffic', 'seo-booster' ); ?></li>
					<li><?php esc_html_e( 'Mark as done and Ignore to clear the queue', 'seo-booster' ); ?></li>
				</ul>
				<p class="sb-possibilities-pro-strip__cta">
					<a class="button button-primary" href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Upgrade to Pro', 'seo-booster' ); ?>
					</a>
				</p>
			</section>
		<?php endif; ?>

		<section class="sb-ui-panel sb-results-panel" aria-labelledby="sb-results-title">
			<?php if ( $has_triage ) : ?>
			<div class="sb-view-toggle" role="tablist" aria-label="<?php esc_attr_e( 'Possibilities views', 'seo-booster' ); ?>">
				<a
					role="tab"
					class="sb-view-toggle__btn<?php echo 'type' === $current_view ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( SEO_Issues_Manager::get_possibilities_page_url( array_merge( $base_args, array( 'view' => 'type' ) ) ) ); ?>"
					aria-selected="<?php echo 'type' === $current_view ? 'true' : 'false'; ?>"
				>
					<?php esc_html_e( 'By type', 'seo-booster' ); ?>
				</a>
				<a
					role="tab"
					class="sb-view-toggle__btn<?php echo 'url' === $current_view ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( SEO_Issues_Manager::get_possibilities_page_url( array_merge( $base_args, array( 'view' => 'url' ) ) ) ); ?>"
					aria-selected="<?php echo 'url' === $current_view ? 'true' : 'false'; ?>"
				>
					<?php esc_html_e( 'By URL', 'seo-booster' ); ?>
				</a>
			</div>
			<?php endif; ?>

			<?php if ( $has_triage && 'type' === $current_view ) : ?>
				<h2 class="sb-ui-title" id="sb-results-title"><?php esc_html_e( 'Possibilities by type', 'seo-booster' ); ?></h2>
				<p class="sb-ui-lead"><?php esc_html_e( 'Work through the most common problems first. Open a Tools workflow when available, or show the affected URLs.', 'seo-booster' ); ?></p>

				<div id="sb-types-view" class="sb-view-content">
					<?php if ( empty( $issue_types_preview ) ) : ?>
						<p class="sb-ui-empty"><?php esc_html_e( 'No active possibilities in this filter.', 'seo-booster' ); ?></p>
					<?php else : ?>
						<ul class="sb-type-list" id="sb-type-list">
							<?php foreach ( $issue_types_preview as $type_row ) : ?>
								<?php
								$type_urls_url = SEO_Issues_Manager::get_possibilities_page_url(
									array(
										'view'       => 'url',
										'issue_type' => $type_row['issue_key'],
										'severity'   => $current_severity,
									)
								);
								?>
								<li class="sb-type-item sb-type-item--<?php echo esc_attr( $type_row['severity'] ); ?>" data-issue-key="<?php echo esc_attr( $type_row['issue_key'] ); ?>">
									<span class="sb-severity-badge sb-severity-<?php echo esc_attr( $type_row['severity'] ); ?>">
										<?php echo esc_html( sb_seo_issues_severity_label( $type_row['severity'] ) ); ?>
									</span>
									<div class="sb-type-item__body">
										<strong class="sb-type-item__message"><?php echo esc_html( $type_row['message'] ); ?></strong>
										<span class="sb-type-item__meta">
											<?php
											printf(
												/* translators: %d: number of URLs */
												esc_html( _n( '%d URL', '%d URLs', (int) $type_row['affected_urls'], 'seo-booster' ) ),
												(int) $type_row['affected_urls']
											);
											?>
										</span>
									</div>
									<div class="sb-type-item__actions">
										<?php if ( ! empty( $type_row['tool'] ) ) : ?>
											<a class="button button-primary button-small" href="<?php echo esc_url( $type_row['tool']['url'] ); ?>">
												<?php echo esc_html( $type_row['tool']['label'] ); ?>
											</a>
										<?php endif; ?>
										<a class="button button-small" href="<?php echo esc_url( $type_urls_url ); ?>">
											<?php esc_html_e( 'Show URLs', 'seo-booster' ); ?>
										</a>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
						<?php if ( $has_more_types ) : ?>
							<details class="sb-type-more">
								<summary><?php esc_html_e( 'Show all types', 'seo-booster' ); ?></summary>
								<ul class="sb-type-list">
									<?php foreach ( array_slice( $issue_types_all, 12 ) as $type_row ) : ?>
										<?php
										$type_urls_url = SEO_Issues_Manager::get_possibilities_page_url(
											array(
												'view'     => 'url',
												'issue_type' => $type_row['issue_key'],
												'severity' => $current_severity,
											)
										);
										?>
										<li class="sb-type-item sb-type-item--<?php echo esc_attr( $type_row['severity'] ); ?>">
											<span class="sb-severity-badge sb-severity-<?php echo esc_attr( $type_row['severity'] ); ?>">
												<?php echo esc_html( sb_seo_issues_severity_label( $type_row['severity'] ) ); ?>
											</span>
											<div class="sb-type-item__body">
												<strong class="sb-type-item__message"><?php echo esc_html( $type_row['message'] ); ?></strong>
												<span class="sb-type-item__meta">
													<?php
													printf(
														/* translators: %d: number of URLs */
														esc_html( _n( '%d URL', '%d URLs', (int) $type_row['affected_urls'], 'seo-booster' ) ),
														(int) $type_row['affected_urls']
													);
													?>
												</span>
											</div>
											<div class="sb-type-item__actions">
												<?php if ( ! empty( $type_row['tool'] ) ) : ?>
													<a class="button button-primary button-small" href="<?php echo esc_url( $type_row['tool']['url'] ); ?>">
														<?php echo esc_html( $type_row['tool']['label'] ); ?>
													</a>
												<?php endif; ?>
												<a class="button button-small" href="<?php echo esc_url( $type_urls_url ); ?>">
													<?php esc_html_e( 'Show URLs', 'seo-booster' ); ?>
												</a>
											</div>
										</li>
									<?php endforeach; ?>
								</ul>
							</details>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php elseif ( $has_triage ) : ?>
				<h2 class="sb-ui-title" id="sb-results-title"><?php esc_html_e( 'Top 10 to fix this week', 'seo-booster' ); ?></h2>
				<p class="sb-ui-lead"><?php esc_html_e( 'Prioritized by severity and Search Console traffic when available. Expand a URL to act on each finding.', 'seo-booster' ); ?></p>

				<?php if ( '' !== $current_issue_type ) : ?>
					<p class="sb-active-type-filter">
						<?php
						printf(
							/* translators: %s: possibility message */
							esc_html__( 'Filtered to: %s', 'seo-booster' ),
							esc_html( $active_type_label )
						);
						?>
						<?php esc_html_e( 'Each row still lists every open possibility on that URL.', 'seo-booster' ); ?>
					</p>
				<?php endif; ?>

				<div id="sb-top-urls" class="sb-top-urls">
					<?php if ( empty( $top_urls ) ) : ?>
						<p class="sb-ui-empty"><?php esc_html_e( 'No active possibilities in this filter.', 'seo-booster' ); ?></p>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped sb-top-urls-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'URL', 'seo-booster' ); ?></th>
									<th scope="col"><?php esc_html_e( 'SEO Score', 'seo-booster' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Possibilities', 'seo-booster' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Actions', 'seo-booster' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $top_urls as $item ) : ?>
									<?php
									echo '<tr class="sb-url-row" data-url-id="' . esc_attr( (string) $item->id ) . '">';
									echo '<td>' . $list_table->column_url( $item ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Column methods return escaped HTML.
									echo '<td>' . $list_table->column_score( $item ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo '<td>' . $list_table->column_issues( $item ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo '<td>' . $list_table->column_actions( $item ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo '</tr>';
									?>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

				<details class="sb-all-urls" id="sb-all-urls"<?php echo '' !== $current_issue_type ? ' open' : ''; ?>>
					<summary><?php esc_html_e( 'Show all URLs', 'seo-booster' ); ?></summary>
					<div id="sb-urls-view" class="sb-view-content sb-issues-table-container">
						<form method="get" id="sb-possibilities-filter-form">
							<input type="hidden" name="page" value="sb2_seo_issues" />
							<input type="hidden" name="view" value="url" />
							<?php if ( '' !== $current_severity ) : ?>
								<input type="hidden" name="severity" value="<?php echo esc_attr( $current_severity ); ?>" />
							<?php endif; ?>
							<?php if ( '' !== $current_issue_type ) : ?>
								<input type="hidden" name="issue_type" value="<?php echo esc_attr( $current_issue_type ); ?>" />
							<?php endif; ?>
							<?php $list_table->display(); ?>
						</form>
					</div>
				</details>
			<?php else : ?>
				<h2 class="sb-ui-title" id="sb-results-title"><?php esc_html_e( 'URLs with possibilities', 'seo-booster' ); ?></h2>
				<p class="sb-ui-lead"><?php esc_html_e( 'Expand a URL to review findings, open the editor, or jump to free Tools. Upgrade to Pro for Do next, By type, traffic priority, and Mark as done.', 'seo-booster' ); ?></p>

				<div id="sb-urls-view" class="sb-view-content sb-issues-table-container">
					<form method="get" id="sb-possibilities-filter-form">
						<input type="hidden" name="page" value="sb2_seo_issues" />
						<input type="hidden" name="view" value="url" />
						<?php if ( '' !== $current_severity ) : ?>
							<input type="hidden" name="severity" value="<?php echo esc_attr( $current_severity ); ?>" />
						<?php endif; ?>
						<?php $list_table->display(); ?>
					</form>
				</div>
			<?php endif; ?>
		</section>

		<section class="sb-ui-panel sb-sitewide-panel" aria-labelledby="sb-sitewide-title">
			<div class="sb-sitewide-panel__header">
				<div>
					<h2 class="sb-ui-title" id="sb-sitewide-title"><?php esc_html_e( 'Sitewide possibilities', 'seo-booster' ); ?></h2>
					<p class="sb-ui-lead sb-sitewide-panel__lead">
						<?php esc_html_e( 'Checks that apply to the whole site: SSL, robots, sitemap, favicon, language, and more.', 'seo-booster' ); ?>
					</p>
				</div>
				<button type="button" class="button" id="sb-run-sitewide-analysis-secondary">
					<?php echo empty( $sitewide_issues ) ? esc_html__( 'Run sitewide checks', 'seo-booster' ) : esc_html__( 'Re-run', 'seo-booster' ); ?>
				</button>
			</div>

			<div class="sb-sitewide-meta" id="sb-sitewide-meta">
				<span class="sb-sitewide-chips" id="sb-sitewide-chips">
					<span class="sb-sitewide-chip sb-sitewide-chip--open" data-chip="open">
						<?php
						printf(
							/* translators: %d: number of open sitewide possibilities */
							esc_html( _n( '%d open', '%d open', $sitewide_open, 'seo-booster' ) ),
							(int) $sitewide_open
						);
						?>
					</span>
					<span class="sb-sitewide-chip sb-sitewide-chip--critical" data-chip="critical">
						<?php
						printf(
							/* translators: %d: critical count */
							esc_html__( '%d critical', 'seo-booster' ),
							(int) $sitewide_critical
						);
						?>
					</span>
					<span class="sb-sitewide-chip sb-sitewide-chip--high" data-chip="high">
						<?php
						printf(
							/* translators: %d: high count */
							esc_html__( '%d high', 'seo-booster' ),
							(int) $sitewide_high
						);
						?>
					</span>
					<span class="sb-sitewide-chip sb-sitewide-chip--medium" data-chip="medium">
						<?php
						printf(
							/* translators: %d: medium count */
							esc_html__( '%d medium', 'seo-booster' ),
							(int) $sitewide_medium
						);
						?>
					</span>
					<span class="sb-sitewide-chip sb-sitewide-chip--good" data-chip="good">
						<?php
						printf(
							/* translators: %d: good practices count */
							esc_html__( '%d good', 'seo-booster' ),
							(int) $sitewide_good
						);
						?>
					</span>
				</span>
				<span class="sb-sitewide-last-run" id="sb-sitewide-last-run">
					<?php if ( $last_run ) : ?>
						<?php
						printf(
							/* translators: %s: human-readable date/time */
							esc_html__( 'Last run: %s', 'seo-booster' ),
							esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_run ) )
						);
						?>
					<?php else : ?>
						<?php esc_html_e( 'Not run yet', 'seo-booster' ); ?>
					<?php endif; ?>
				</span>
			</div>

			<div id="sb-sitewide-checklist" class="sb-sitewide-checklist">
				<?php if ( empty( $sitewide_issues ) ) : ?>
					<p class="sb-ui-empty" id="sb-sitewide-empty">
						<?php esc_html_e( 'No sitewide checks yet. Run sitewide checks to see possibilities for your whole site.', 'seo-booster' ); ?>
					</p>
				<?php else : ?>
					<ul class="sb-sitewide-list">
						<?php foreach ( $sitewide_issues as $issue ) : ?>
							<li class="sb-sitewide-item sb-sitewide-item--<?php echo esc_attr( $issue->severity ); ?>">
								<span class="sb-severity-badge sb-severity-<?php echo esc_attr( $issue->severity ); ?>">
									<?php echo esc_html( sb_seo_issues_severity_label( $issue->severity ) ); ?>
								</span>
								<span class="sb-sitewide-item__message"><?php echo esc_html( $issue->message ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</section>
	</div>
	<?php
}
