<?php

namespace Cleverplugins\SEOBooster;

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Verify user capabilities
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'seo-booster' ) );
}

global $wpdb;

if ( ! get_option( 'seobooster_autolink_dedupe_done' ) ) {
	$duplicate_ids = $wpdb->get_col(
		"SELECT t1.id FROM {$wpdb->prefix}sb2_autolink t1 INNER JOIN {$wpdb->prefix}sb2_autolink t2 ON t1.id > t2.id AND t1.keyword = t2.keyword"
	);

	if ( ! empty( $duplicate_ids ) ) {
		$deleted_dupes = $wpdb->delete(
			$wpdb->prefix . 'sb2_autolink',
			array(
				'id' => $duplicate_ids,
			),
			array( '%d' )
		);
		if ( $deleted_dupes ) {
			Seobooster2::flush_autolink_caches();
		}
	}

	update_option( 'seobooster_autolink_dedupe_done', 1, false );
}

$internal_linking     = get_option( 'seobooster_internal_linking' );
$settings_autolink_url = admin_url( 'admin.php?page=sb2_settings#automatic-links' );
$page                  = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';
?>
<div class="wrap sb-wrap sb-dashboard sb-autolink-page">
	<?php echo wp_kses_post( Utils::show_plugin_headline( esc_html__( 'Automatic keywords to links', 'seo-booster' ), true ) ); ?>

	<?php if ( ! $internal_linking ) : ?>
		<section class="sb-ui-cta" aria-labelledby="sb-autolink-disabled-title">
			<h2 class="sb-ui-cta__title" id="sb-autolink-disabled-title">
				<?php esc_html_e( 'Automatic linking is turned off', 'seo-booster' ); ?>
			</h2>
			<p class="sb-ui-cta__lead">
				<?php esc_html_e( 'Your keyword rules are saved, but links are not applied on the front end until you turn automatic linking back on.', 'seo-booster' ); ?>
			</p>
			<div class="sb-ui-cta__actions">
				<a class="button button-primary" href="<?php echo esc_url( $settings_autolink_url ); ?>">
					<?php esc_html_e( 'Open Automatic Links settings', 'seo-booster' ); ?>
				</a>
			</div>
		</section>
	<?php endif; ?>

	<div class="sb-ui-grid sb-ui-grid--2">
		<section class="sb-ui-panel" id="sb2_autolink_add" aria-labelledby="sb-autolink-add-title">
			<h2 class="sb-ui-title" id="sb-autolink-add-title"><?php esc_html_e( 'Add new link', 'seo-booster' ); ?></h2>
			<p class="sb-ui-lead">
				<?php esc_html_e( 'Enter a keyword and the URL it should link to. Works with internal and external links.', 'seo-booster' ); ?>
			</p>

			<form method="post" id="sb2_autolink_add_form" class="sb-autolink-add-form">
				<div class="sb-autolink-field">
					<label for="newkeyword">
						<?php esc_html_e( 'Keyword', 'seo-booster' ); ?>
					</label>
					<input name="newkeyword" type="text" id="newkeyword" placeholder="<?php esc_attr_e( 'Enter keyword', 'seo-booster' ); ?>" value="" required>
				</div>

				<div class="sb-autolink-field">
					<label for="targeturl">
						<?php esc_html_e( 'Target URL', 'seo-booster' ); ?>
					</label>
					<input name="targeturl" type="url" id="targeturl" placeholder="https://" value="" required>
				</div>

				<div class="sb-autolink-submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Add Keyword', 'seo-booster' ); ?>">
				</div>

				<input name="action" type="hidden" value="ajax_add_keyword" />
				<?php wp_nonce_field( 'add-keyword-nonce', '_ajax_sb2_add_keyword_nonce' ); ?>
			</form>

			<div id="addkwresponse" class="sb-autolink-response" aria-live="polite"></div>
			<div class="kwaddspinner sb-autolink-spinner" style="display:none;">
				<div class="bounce1"></div>
				<div class="bounce2"></div>
				<div class="bounce3"></div>
			</div>
		</section>

		<section class="sb-ui-panel" id="add_kw_help" aria-labelledby="sb-autolink-help-title">
			<?php
			$excluded_elements = get_option(
				'seobooster_excluded_elements',
				array(
					'h1'         => 1,
					'h2'         => 1,
					'h3'         => 1,
					'h4'         => 1,
					'h5'         => 1,
					'h6'         => 1,
					'ul'         => 1,
					'ol'         => 1,
					'dl'         => 1,
					'blockquote' => 1,
				)
			);

			// Keep labels and groups in sync with Settings → Automatic Links → Where not to link.
			$mandatory_elements = array(
				'a'        => __( 'Existing links', 'seo-booster' ),
				'code'     => __( 'Code & preformatted blocks', 'seo-booster' ),
				'script'   => __( 'Scripts', 'seo-booster' ),
				'style'    => __( 'Style tags', 'seo-booster' ),
				'head'     => __( 'Head section', 'seo-booster' ),
				'textarea' => __( 'Form fields & controls', 'seo-booster' ),
				'svg'      => __( 'SVG graphics', 'seo-booster' ),
			);

			$element_groups = array(
				'headings' => array(
					'label'    => __( 'Headings', 'seo-booster' ),
					'elements' => array(
						'h1' => __( 'Heading 1 (H1)', 'seo-booster' ),
						'h2' => __( 'Heading 2 (H2)', 'seo-booster' ),
						'h3' => __( 'Heading 3 (H3)', 'seo-booster' ),
						'h4' => __( 'Heading 4 (H4)', 'seo-booster' ),
						'h5' => __( 'Heading 5 (H5)', 'seo-booster' ),
						'h6' => __( 'Heading 6 (H6)', 'seo-booster' ),
					),
				),
				'lists'    => array(
					'label'    => __( 'Lists', 'seo-booster' ),
					'elements' => array(
						'ul' => __( 'Bullet lists (UL)', 'seo-booster' ),
						'ol' => __( 'Numbered lists (OL)', 'seo-booster' ),
						'dl' => __( 'Definition lists (DL)', 'seo-booster' ),
					),
				),
				'other'    => array(
					'label'    => __( 'Other', 'seo-booster' ),
					'elements' => array(
						'blockquote' => __( 'Blockquotes', 'seo-booster' ),
					),
				),
			);

			$active_excluded = array();
			foreach ( $element_groups as $group ) {
				$group_items = array();
				foreach ( $group['elements'] as $element => $label ) {
					if ( ! empty( $excluded_elements[ $element ] ) ) {
						$group_items[] = $label;
					}
				}
				if ( ! empty( $group_items ) ) {
					$active_excluded[] = array(
						'label' => $group['label'],
						'items' => $group_items,
					);
				}
			}
			?>
			<h2 class="sb-ui-title" id="sb-autolink-help-title"><?php esc_html_e( 'How links are placed', 'seo-booster' ); ?></h2>
			<p class="sb-ui-lead">
				<?php esc_html_e( 'The system scans your content and adds links where the keyword appears. These areas are skipped, matching Settings → Automatic Links → Where not to link.', 'seo-booster' ); ?>
			</p>

			<?php if ( ! empty( $active_excluded ) ) : ?>
				<p class="sb-autolink-help-subtitle"><?php esc_html_e( 'Currently excluded', 'seo-booster' ); ?></p>
				<ul class="sb-autolink-help-list">
					<?php foreach ( $active_excluded as $group ) : ?>
						<li>
							<strong><?php echo esc_html( $group['label'] ); ?>:</strong>
							<?php echo esc_html( implode( ', ', $group['items'] ) ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="sb-ui-empty"><?php esc_html_e( 'No optional HTML areas are excluded right now. Linking can appear in headings, lists, and blockquotes.', 'seo-booster' ); ?></p>
			<?php endif; ?>

			<p class="sb-autolink-help-subtitle"><?php esc_html_e( 'Always excluded', 'seo-booster' ); ?></p>
			<ul class="sb-autolink-help-list">
				<?php foreach ( $mandatory_elements as $label ) : ?>
					<li><?php echo esc_html( $label ); ?></li>
				<?php endforeach; ?>
			</ul>

			<p class="sb-autolink-help-footer">
				<a href="<?php echo esc_url( $settings_autolink_url ); ?>">
					<?php esc_html_e( 'Change excluded elements in Settings', 'seo-booster' ); ?>
				</a>
			</p>
		</section>
	</div>

	<section class="sb-ui-panel sb-autolink-results" aria-labelledby="sb-autolink-results-title">
		<h2 class="sb-ui-title" id="sb-autolink-results-title"><?php esc_html_e( 'Link rules', 'seo-booster' ); ?></h2>
		<p class="sb-ui-lead">
			<?php esc_html_e( 'Add keywords or phrases that should be changed into links. Double-click a keyword or URL cell to edit it.', 'seo-booster' ); ?>
		</p>

		<form id="urls-filter" method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>" />
			<?php
			if ( isset( $_REQUEST['order'] ) ) {
				?>
				<input type="hidden" name="order" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ); ?>" />
				<?php
			}

			if ( isset( $_REQUEST['orderby'] ) ) {
				?>
				<input type="hidden" name="orderby" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) ); ?>" />
				<?php
			}
			$autolink_list_table->search_box( __( 'Search', 'seo-booster' ), 'search_id' );
			$autolink_list_table->display();
			?>
		</form>
	</section>
</div>
