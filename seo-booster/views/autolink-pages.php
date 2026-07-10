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
?>
<div class="wrap">
	<?php
	echo wp_kses_post( Utils::show_plugin_headline( esc_html__( 'Automatic keywords to links', 'seo-booster' ), true ) );
	?>

	<div class="add_kw_container">
		<div id="sb2_autolink_add" class="">
			<h2>
			<?php
				esc_html_e( 'Add new link', 'seo-booster' );
			?>
				</h2>

			<p>
			<?php
				esc_html_e( 'Enter keyword and which URL the keyword should link to. Works with internal and external links.', 'seo-booster' );
			?>
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
		</div>

		<div id="add_kw_help">
			<strong><?php esc_html_e( 'How Links Are Placed', 'seo-booster' ); ?></strong>
			<p><?php esc_html_e( 'Our system scans your content and automatically adds links where the keyword appears. However, to ensure readability and SEO best practices, links will not be placed in:', 'seo-booster' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Headings:', 'seo-booster' ); ?> <code>&lt;h1&gt;</code>, <code>&lt;h2&gt;</code>, <code>&lt;h3&gt;</code>, <code>&lt;h4&gt;</code>, <code>&lt;strong&gt;</code>, <?php esc_html_e( 'etc.', 'seo-booster' ); ?></li>
				<li><?php esc_html_e( 'Lists:', 'seo-booster' ); ?> <code>&lt;ul&gt;</code>, <code>&lt;ol&gt;</code>, <code>&lt;li&gt;</code></li>
				<li><?php esc_html_e( 'Existing links and code blocks:', 'seo-booster' ); ?> <code>&lt;a&gt;</code>, <code>&lt;code&gt;</code>, <code>&lt;pre&gt;</code></li>
			</ul>
		</div>


	</div><!-- .add_kw_container -->





	<div id="sb2fof" class="clearfix clear">
		<div>
			<p>
			<?php
				esc_html_e( 'Add keywords or phrases that should be changed in your posts to links.', 'seo-booster' );
			?>
				</p>

		</div>
	</div>
	<?php
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

	$internal_linking = get_option( 'seobooster_internal_linking' );

	if ( ! $internal_linking ) {
		?>
		<div class="notices notice-info">
			<p>
				<?php
				esc_html_e( 'Automatic Linking is turned off! Turn on the option in the settings page.', 'seo-booster' );
				?>
			</p>
		</div>
		<?php
	}

	?>


	<form id="urls-filter" method="get">
		<?php
		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';
		?>
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
</div>
