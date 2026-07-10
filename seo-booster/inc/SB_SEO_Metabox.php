<?php

namespace Cleverplugins\SEOBooster;

use Cleverplugins\SEOBooster\Analysis\Ai_Readiness_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SB_SEO_Metabox
 *
 * Handles the SEO meta box functionality for posts and pages.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */
class SB_SEO_Metabox {

	/**
	 * Initialize the class and set up hooks.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_seo_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_sb_seo_get_keyword_suggestions', array( __CLASS__, 'get_keyword_suggestions' ) );
		// AI writing assistance (sb_seo_ai_writing_assistance) removed for 7.2 — was placeholder only.
		// Reimplement with WordPress Connectors before listing as a Pro feature again.
		add_action( 'wp_ajax_sb_seo_analysis', array( __CLASS__, 'ajax_seo_analysis' ) );
		add_action( 'wp_ajax_sb_seo_download_and_analyze', array( __CLASS__, 'ajax_download_and_analyze' ) );
		add_action( 'wp_ajax_sb_seo_get_saved_analysis', array( __CLASS__, 'ajax_get_saved_analysis' ) );
		add_action( 'wp_ajax_sb_seo_generate_wp_connector_suggestions', array( __CLASS__, 'ajax_generate_wp_connector_suggestions' ) );
		add_action( 'wp_ajax_sb_seo_generate_credits_suggestions', array( __CLASS__, 'ajax_generate_credits_suggestions' ) );
		add_action( 'wp_ajax_sb_seo_generate_product_seo', array( __CLASS__, 'ajax_generate_product_seo' ) );
		add_action( 'wp_ajax_sb_seo_generate_comprehensive_analysis', array( __CLASS__, 'ajax_generate_comprehensive_analysis' ) );
		add_action( 'wp_ajax_sb_seo_poll_credits_request', array( __CLASS__, 'ajax_poll_credits_request' ) );
		add_action( 'wp_ajax_sb_seo_save_pending_request', array( __CLASS__, 'ajax_save_pending_request' ) );
		add_action( 'wp_ajax_sb_seo_clear_pending_request', array( __CLASS__, 'ajax_clear_pending_request' ) );
		add_action( 'wp_ajax_sb_seo_get_recent_request_statuses', array( __CLASS__, 'ajax_get_recent_request_statuses' ) );
		add_action( 'wp_ajax_sb_seo_retry_request', array( __CLASS__, 'ajax_retry_request' ) );
		add_action( 'wp_ajax_sb_seo_get_llm_suggestions', array( __CLASS__, 'ajax_get_llm_suggestions' ) );
		add_action( 'wp_ajax_sb_seo_analyze_step', array( __CLASS__, 'ajax_analyze_step' ) );
		add_action( 'wp_ajax_sb_seo_toggle_analysis_exclusion', array( __CLASS__, 'ajax_toggle_analysis_exclusion' ) );
		add_action( 'wp_ajax_sb_seo_apply_seo_field', array( __CLASS__, 'ajax_apply_seo_field' ) );

		// Save exclusion preference on post save
		add_action( 'save_post', array( __CLASS__, 'save_exclusion_preference' ) );

		// Add taxonomy meta boxes
		add_action( 'category_edit_form', array( __CLASS__, 'render_taxonomy_metabox' ), 10, 2 );
		add_action( 'post_tag_edit_form', array( __CLASS__, 'render_taxonomy_metabox' ), 10, 2 );
	}


	/**
	 * Add the SEO meta box to public post types.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function add_seo_metabox() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'sb_seo_metabox',
				__( 'SEO Booster', 'seo-booster' ),
				array( __CLASS__, 'render_metabox' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render the SEO meta box for taxonomy pages.
	 *
	 * @since 6.1.26
	 * @param WP_Term $term The term object.
	 * @param string $taxonomy The taxonomy name.
	 * @return void
	 */
	public static function render_taxonomy_metabox( $term, $taxonomy ) {
		echo '<div class="sb-seo-taxonomy-metabox">';
		echo '<h3>' . esc_html__( 'SEO Booster', 'seo-booster' ) . '</h3>';
		self::render_metabox( $term );
		echo '</div>';
	}

	/**
	 * Render the SEO meta box content.
	 *
	 * @since 6.1.26
	 * @param mixed $post_or_term The current post object or term object.
	 * @return void
	 */
	public static function render_metabox( $post_or_term ) {
		$is_term   = is_object( $post_or_term ) && isset( $post_or_term->term_id );
		$item_id   = $is_term ? $post_or_term->term_id : $post_or_term->ID;
		$item_type = $is_term ? 'term' : 'post';

		// Check if this is an attachment
		$is_attachment = ! $is_term && isset( $post_or_term->post_type ) && $post_or_term->post_type === 'attachment';

		// Get current URL for preview
		$current_url = $is_term ? get_term_link( $post_or_term ) : get_permalink( $item_id );
		if ( is_wp_error( $current_url ) ) {
			$current_url = '';
		}

		// Get site name for preview
		$site_name = get_bloginfo( 'name' );

		wp_nonce_field( 'sb_seo_metabox_nonce', 'sb_seo_metabox_nonce' );

		// Show simplified UI for attachments
		if ( $is_attachment && wp_attachment_is_image( $item_id ) ) {
			self::render_attachment_metabox( $item_id );
			return;
		}

		// Check exclusion status (only for posts, not terms)
		$is_excluded      = false;
		$is_auto_excluded = false;
		$exclusion_reason = '';
		if ( ! $is_term && $item_type === 'post' ) {
			// Check if user has manually excluded
			$user_excluded = get_post_meta( $item_id, '_sb_exclude_from_analysis', true );
			$is_excluded   = ( $user_excluded === '1' );

			// Check if auto-excluded (WooCommerce/private)
			$is_auto_excluded = \Cleverplugins\SEOBooster\SEO_Issues_Manager::is_auto_excluded( $item_id );

			// Auto-set postmeta for auto-excluded pages if not already set
			if ( $is_auto_excluded && $user_excluded !== '1' && $user_excluded !== '0' ) {
				update_post_meta( $item_id, '_sb_exclude_from_analysis', '1' );
				$is_excluded = true;
			}

			// Determine exclusion reason for display
			if ( $is_auto_excluded ) {
				$post_status = get_post_status( $item_id );
				if ( $post_status === 'private' ) {
					$exclusion_reason = __( 'This page is automatically excluded (private page)', 'seo-booster' );
				} elseif ( function_exists( 'wc_get_page_id' ) ) {
					$wc_page_types = array(
						'cart'      => __( 'Cart', 'seo-booster' ),
						'checkout'  => __( 'Checkout', 'seo-booster' ),
						'myaccount' => __( 'My Account', 'seo-booster' ),
						'shop'      => __( 'Shop', 'seo-booster' ),
					);
					foreach ( $wc_page_types as $page_type => $page_label ) {
						$wc_page_id = wc_get_page_id( $page_type );
						if ( $wc_page_id && $item_id === $wc_page_id ) {
							$exclusion_reason = sprintf( __( 'This page is automatically excluded (WooCommerce %s page)', 'seo-booster' ), $page_label );
							break;
						}
					}
				}
			}
		}
		?>
		<div id="sb-seo-metabox" class="sb-seo-metabox" data-object-id="<?php echo esc_attr( (string) $item_id ); ?>" data-object-type="<?php echo esc_attr( $item_type ); ?>">
			<!-- Exclusion Checkbox -->
			<?php if ( ! $is_term && $item_type === 'post' ) : ?>
			<div class="sb-exclusion-control" style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
				<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
					<input type="checkbox" id="sb-exclude-from-analysis" name="sb_exclude_from_analysis" value="1" <?php checked( $is_excluded, true ); ?>>
					<strong><?php esc_html_e( 'Exclude from SEO Analysis', 'seo-booster' ); ?></strong>
				</label>
				<?php if ( $exclusion_reason ) : ?>
				<p style="margin: 8px 0 0 0; font-size: 12px; color: #666; font-style: italic;">
					<?php echo esc_html( $exclusion_reason ); ?>
				</p>
				<?php endif; ?>
				<p style="margin: 8px 0 0 0; font-size: 12px; color: #666;">
					<?php esc_html_e( 'When checked, this page will not appear in SEO analysis results and will be excluded from the global SEO possibilities page.', 'seo-booster' ); ?>
				</p>
			</div>
			<?php endif; ?>
			
			<!-- Row 1: Score circle on top, centered -->
			<div class="sb-seo-score-header">
				<div class="sb-seo-score">
					<div class="sb-score-circle" id="sb-score-circle">
						<span class="sb-score-number" id="sb-score-number">-</span>
						<span class="sb-score-label"><?php esc_html_e( 'SEO Score', 'seo-booster' ); ?></span>
					</div>
					<div class="sb-score-loading" id="sb-score-loading" style="display: none;">
						<small class="sb-analysis-timestamp"></small>
					</div>
				</div>
			</div>
			
			<!-- Row 2: Full-width issues list -->
			<div class="sb-seo-issues-container">
				<div class="sb-analysis-results" id="sb-analysis-results">
					<div class="sb-loading"><?php esc_html_e( 'Loading...', 'seo-booster' ); ?></div>
				</div>
			</div>

			<?php if ( ! $is_term && $item_type === 'post' ) : ?>
				<?php AI_Readiness::render_classic_sections( $item_id ); ?>
			<?php endif; ?>
			
			<!-- SEO Analysis: local analysis + AI actions and results (single section) -->
			<div class="sb-seo-analysis-section">
				<div class="sb-seo-analysis-actions">
					<h3><?php esc_html_e( 'SEO Analysis', 'seo-booster' ); ?></h3>
					<div class="sb-seo-analysis-buttons">
					<?php if ( ! $is_term && $item_type === 'post' ) : ?>
					<button type="button" id="sb-quick-review" class="button button-secondary">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Run quick review', 'seo-booster' ); ?>
					</button>
					<?php endif; ?>
					<button type="button" id="sb-download-and-analyze" class="button button-primary">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'SEO review entire page', 'seo-booster' ); ?>
					</button>
					</div>
					<div class="sb-analysis-info">
						<?php
						$last_download = get_post_meta( $item_id, '_sb_last_page_download', true );
						if ( $last_download ) {
							echo '<small id="sb-last-download-info">';
							printf(
								__( 'Last downloaded: %s', 'seo-booster' ),
								gmdate( 'Y-m-d H:i:s', $last_download )
							);
							echo '</small><br>';
						} else {
							echo '<small id="sb-last-download-info" class="sb-hidden"></small>';
						}
						?>
						<small>
							<?php esc_html_e( 'We analyze the full page output including widgets, footer, and theme elements.', 'seo-booster' ); ?>
							<a href="<?php echo esc_url( Utils::generate_cp_web_link( 'post_metabox', 'docs/seo-analysis/seo-analysis-overview/' ) ); ?>" target="_blank">
								<?php esc_html_e( 'Learn more', 'seo-booster' ); ?>
							</a>
						</small>
					</div>
					<?php
					$ai_provider   = LLM_Helper::get_selected_ai_provider();
					$is_wc_product = ! $is_term && isset( $post_or_term->post_type ) && $post_or_term->post_type === 'product' && function_exists( 'wc_get_product' );
					if ( empty( $ai_provider ) || in_array( $ai_provider, array( 'disabled' ), true ) ) :
						?>
					<div class="sb-llm-disabled">
						<p class="sb-llm-disabled-text">
							<?php esc_html_e( 'AI features are not enabled. To use AI-powered SEO suggestions, please configure your AI provider in', 'seo-booster' ); ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=sb2_settings#ai-llm' ) ); ?>"><?php esc_html_e( 'SEO Booster Settings', 'seo-booster' ); ?></a>.
						</p>
					</div>
					<?php else : ?>
					<div class="sb-ai-buttons-row">
						<div class="sb-ai-buttons-group">
							<?php if ( $is_wc_product && $ai_provider === 'seobooster' ) : ?>
							<button type="button" id="sb-get-llm-suggestions" class="button button-primary" data-provider="<?php echo esc_attr( $ai_provider ); ?>" data-request-type="product_seo">
								<span class="dashicons dashicons-cart"></span>
								<?php esc_html_e( 'Generate Product SEO', 'seo-booster' ); ?>
							</button>
							<?php else : ?>
							<button type="button" id="sb-get-llm-suggestions" class="button button-primary" data-provider="<?php echo esc_attr( $ai_provider ); ?>" data-request-type="seo_suggestions">
								<span class="dashicons dashicons-lightbulb"></span>
								<?php esc_html_e( 'Generate AI SEO Suggestions', 'seo-booster' ); ?>
							</button>
							<?php endif; ?>
							<?php if ( $ai_provider === 'seobooster' && Credits_Service::is_registered() ) : ?>
							<button type="button" id="sb-comprehensive-analysis" class="button" data-provider="<?php echo esc_attr( $ai_provider ); ?>">
								<span class="dashicons dashicons-chart-bar"></span>
								<?php esc_html_e( 'Comprehensive SEO Analysis', 'seo-booster' ); ?>
								<span class="sb-credits-badge">(3 <?php esc_html_e( 'credits', 'seo-booster' ); ?>)</span>
							</button>
							<?php endif; ?>
						</div>
						<?php if ( $ai_provider === 'seobooster' && Credits_Service::is_registered() ) : ?>
						<span class="sb-credits-inline" id="sb-credits-inline">
							<?php
							$inline_balance = Credits_Service::get_balance();
							printf(
								/* translators: %s: number of credits */
								esc_html__( '%s credits left', 'seo-booster' ),
								'<strong id="sb-credits-inline-count">' . esc_html( number_format( $inline_balance ) ) . '</strong>'
							);
							?>
							&bull;
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=sb2_settings#ai-llm' ) ); ?>" class="sb-credits-buy-link"><?php esc_html_e( 'Buy more', 'seo-booster' ); ?></a>
						</span>
						<?php endif; ?>
						<p class="sb-ai-button-description">
							<strong><?php esc_html_e( 'What this does:', 'seo-booster' ); ?></strong>
							<?php
							if ( $is_wc_product && $ai_provider === 'seobooster' ) {
								esc_html_e( 'Uses 1 credit. AI generates optimized product titles, meta descriptions, and short descriptions tailored for WooCommerce.', 'seo-booster' );
							} elseif ( $ai_provider === 'seobooster' ) {
								esc_html_e( 'Left button: 1 credit – 5 SEO title suggestions and 5+2 meta descriptions. Right: 3 credits – full audit (issues, content gaps, internal linking, keyword opportunities, quick wins).', 'seo-booster' );
							} else {
								esc_html_e( 'The AI will analyze your content and generate 5 optimized SEO title suggestions and 5 meta description suggestions.', 'seo-booster' );
							}
							?>
						</p>
						<?php if ( $ai_provider === 'WordPress' ) : ?>
						<p class="sb-ai-connectors-tip" style="margin-top: 8px; font-size: 12px; color: #646970;">
							<?php esc_html_e( 'Configure AI at', 'seo-booster' ); ?>
							<a href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>"><?php esc_html_e( 'Settings → Connectors', 'seo-booster' ); ?></a>.
						</p>
						<?php endif; ?>
					</div>
					<?php endif; ?>
				</div>

				<?php
				if ( $ai_provider !== 'disabled' ) :
					$has_saved_suggestions   = false;
					$saved_comprehensive_ui  = null;
					if ( ! $is_term && $item_type === 'post' && $item_id ) {
						$saved_llm = LLM_Helper::get_saved_suggestions( $item_id );
						$has_saved_suggestions  = is_array( $saved_llm )
							&& ! empty( $saved_llm['titles'] )
							&& ! empty( $saved_llm['descriptions'] );
						$saved_comprehensive_ui = get_post_meta( $item_id, '_sb_comprehensive_analysis_result', true );
						if ( ! is_array( $saved_comprehensive_ui ) || empty( $saved_comprehensive_ui ) ) {
							$saved_comprehensive_ui = null;
						}
					}
					$show_results_zone  = $has_saved_suggestions || ! empty( $saved_comprehensive_ui );
					$pending_request_ui = ( ! $is_term && $item_id ) ? get_post_meta( $item_id, '_sb_pending_request_id', true ) : '';
					$show_activity_zone = ! empty( $pending_request_ui );
					?>
				<div id="sb-ai-activity-zone" class="<?php echo $show_activity_zone ? '' : 'sb-hidden'; ?>">
					<div class="sb-llm-loading sb-hidden">
						<div class="sb-llm-loading-row">
							<span class="spinner is-active"></span>
							<span class="sb-llm-status-message"><?php esc_html_e( 'Preparing request...', 'seo-booster' ); ?></span>
						</div>
						<div class="sb-llm-status-details">
							<span class="sb-llm-status-step"></span>
						</div>
						<div class="sb-llm-status-timer">
							<span class="sb-llm-timer-text"></span>
						</div>
						<div class="sb-llm-last-checked">
							<span class="sb-llm-last-checked-text"></span>
						</div>
					</div>

					<?php if ( $ai_provider === 'seobooster' ) : ?>
					<div id="sb-recent-requests-wrap" class="sb-recent-requests-wrap sb-hidden">
						<h4 class="sb-recent-requests-title"><?php esc_html_e( 'Recent requests', 'seo-booster' ); ?></h4>
						<div id="sb-recent-requests-list"><span class="sb-recent-requests-placeholder"><?php esc_html_e( 'Loading…', 'seo-booster' ); ?></span></div>
					</div>
					<?php endif; ?>

					<div id="sb-ai-progress" class="sb-ai-progress sb-hidden">
						<span class="sb-progress-message"></span>
						<span class="sb-progress-timer"></span>
					</div>
				</div>

				<div id="sb-ai-results-zone" class="sb-ai-suggestions-area<?php echo $show_results_zone ? '' : ' sb-hidden'; ?>">
					<div id="sb-llm-suggestions-display"></div>
					<div id="sb-comprehensive-analysis-display"></div>
					<div id="sb-outline-prompt-modal" class="sb-outline-modal sb-hidden">
						<!-- Prompt textarea for user input -->
					</div>
					<div id="sb-outline-display"></div>
					<div id="sb-outline-status" class="sb-outline-status sb-hidden">
						<span class="spinner is-active"></span>
						<span class="sb-outline-status-text"></span>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</div>

		<input type="hidden" id="sb-seo-item-id" value="<?php echo esc_attr( $item_id ); ?>">
		<input type="hidden" id="sb-seo-item-type" value="<?php echo esc_attr( $item_type ); ?>">
		<input type="hidden" id="sb-seo-current-url" value="<?php echo esc_attr( $current_url ); ?>">
		<input type="hidden" id="sb-seo-site-name" value="<?php echo esc_attr( $site_name ); ?>">
		<?php
	}

	/**
	 * Render simplified metabox for image attachments.
	 *
	 * @since 7.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function render_attachment_metabox( $attachment_id ) {
		$ai_provider          = LLM_Helper::get_selected_ai_provider();
		$latest_content       = AI_Image_Generator::get_latest_content( $attachment_id );
		$previous_content     = AI_Image_Generator::get_previous_content( $attachment_id );
		$has_previous         = ! empty( $previous_content );
		$attachment           = get_post( $attachment_id );
		$original_title       = $attachment ? $attachment->post_title : '';
		$original_alt         = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$original_caption     = $attachment ? $attachment->post_excerpt : '';
		$original_description = $attachment ? $attachment->post_content : '';
		$has_original         = ( '' !== $original_title || '' !== $original_alt || '' !== $original_caption || '' !== $original_description );
		$recent_request_ids   = get_post_meta( $attachment_id, '_sb_recent_request_ids', true );
		if ( ! is_array( $recent_request_ids ) ) {
			$recent_request_ids = array();
		}
		?>
		<div id="sb-seo-metabox" class="sb-seo-metabox sb-seo-attachment-metabox">
			<div class="sb-seo-ai-section">
				<h3><?php esc_html_e( 'AI Image Description Generator', 'seo-booster' ); ?></h3>
				
				<?php if ( $ai_provider === 'disabled' ) : ?>
				<div class="sb-llm-disabled">
					<p style="margin: 0 0 10px 0; color: #646970;">
						<?php esc_html_e( 'AI features are not enabled. To generate image descriptions, configure your AI provider in', 'seo-booster' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=sb2_settings#ai-llm' ) ); ?>"><?php esc_html_e( 'SEO Booster Settings', 'seo-booster' ); ?></a>.
					</p>
					<p style="margin: 0; color: #646970; font-size: 13px;">
						<?php esc_html_e( 'Use WordPress (Settings → Connectors) with a vision-capable provider, or SEO Booster Credits.', 'seo-booster' ); ?>
					</p>
				</div>
				<?php else : ?>
					<?php
					$supports_vision = ( $ai_provider === 'WordPress' && function_exists( 'wp_ai_client_prompt' ) ) || ( $ai_provider === 'seobooster' );
					?>
				<div class="sb-image-ai-generator">
					<p><?php esc_html_e( 'Generate title, ALT text, caption, and description for this image using AI. The AI will analyze the image and any related post content to create accurate descriptions.', 'seo-booster' ); ?></p>
					
					<button type="button" id="sb-generate-image-descriptions" class="button button-primary" <?php echo ! $supports_vision ? 'disabled' : ''; ?>>
						<span class="dashicons dashicons-camera"></span>
						<?php esc_html_e( 'Generate Title, ALT, Caption & Description', 'seo-booster' ); ?>
					</button>
					
					<div id="sb-image-generation-status" style="display: none; margin-top: 15px;">
						<span class="spinner is-active" style="float: none; display: inline-block; margin-right: 8px;"></span>
						<span class="sb-status-message"></span>
					</div>

					<?php if ( $has_original ) : ?>
					<div class="sb-image-original-content" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #c3c4c7; border-radius: 4px;">
						<h4 style="margin-top: 0; margin-bottom: 12px; font-size: 13px; font-weight: 600; color: #50575e;"><?php esc_html_e( 'Original content (before AI)', 'seo-booster' ); ?></h4>
						<div class="sb-original-content-fields" style="font-size: 13px;">
							<?php if ( '' !== $original_title ) : ?>
							<div class="sb-content-item" style="margin-bottom: 10px;">
								<strong><?php esc_html_e( 'Title:', 'seo-booster' ); ?></strong>
								<div style="margin-top: 4px; color: #2c3338;"><?php echo esc_html( $original_title ); ?></div>
							</div>
							<?php endif; ?>
							<?php if ( '' !== $original_alt ) : ?>
							<div class="sb-content-item" style="margin-bottom: 10px;">
								<strong><?php esc_html_e( 'ALT Text:', 'seo-booster' ); ?></strong>
								<div style="margin-top: 4px; color: #2c3338;"><?php echo esc_html( $original_alt ); ?></div>
							</div>
							<?php endif; ?>
							<?php if ( '' !== $original_caption ) : ?>
							<div class="sb-content-item" style="margin-bottom: 10px;">
								<strong><?php esc_html_e( 'Caption:', 'seo-booster' ); ?></strong>
								<div style="margin-top: 4px; color: #2c3338;"><?php echo esc_html( $original_caption ); ?></div>
							</div>
							<?php endif; ?>
							<?php if ( '' !== $original_description ) : ?>
							<div class="sb-content-item" style="margin-bottom: 10px;">
								<strong><?php esc_html_e( 'Description:', 'seo-booster' ); ?></strong>
								<div style="margin-top: 4px; color: #2c3338;"><?php echo esc_html( $original_description ); ?></div>
							</div>
							<?php endif; ?>
						</div>
					</div>
					<?php endif; ?>

					<?php if ( $ai_provider === 'seobooster' ) : ?>
					<div id="sb-recent-requests-wrap" class="sb-recent-requests-wrap sb-attachment-recent-requests sb-hidden">
						<h4 class="sb-recent-requests-title"><?php esc_html_e( 'Recent requests', 'seo-booster' ); ?></h4>
						<div id="sb-recent-requests-list"><span class="sb-recent-requests-placeholder"><?php esc_html_e( 'Loading…', 'seo-booster' ); ?></span></div>
					</div>
					<?php endif; ?>
					
					<?php if ( $latest_content ) : ?>
					<div class="sb-previously-generated" style="margin-top: 20px; padding: 15px; background: #f0f6fc; border: 1px solid #2271b1; border-radius: 4px;">
						<h4 style="margin-top: 0; margin-bottom: 15px; font-size: 14px; font-weight: 600;">
							<?php esc_html_e( 'AI generated content', 'seo-booster' ); ?>
							<?php
							$timestamp = isset( $latest_content['timestamp'] ) ? $latest_content['timestamp'] : 0;
							if ( $timestamp ) {
								echo '<small style="font-weight: normal; color: #666; margin-left: 10px;">(';
								echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) );
								echo ')</small>';
							}
							?>
						</h4>
						<div class="sb-previously-generated-content">
							<?php if ( ! empty( $latest_content['title'] ) ) : ?>
							<div class="sb-content-item" style="margin-bottom: 12px;">
								<strong><?php esc_html_e( 'Title:', 'seo-booster' ); ?></strong>
								<div style="margin-top: 5px; color: #333;"><?php echo esc_html( $latest_content['title'] ); ?></div>
							</div>
							<?php endif; ?>
							<?php if ( ! empty( $latest_content['alt_text'] ) ) : ?>
							<div class="sb-content-item" style="margin-bottom: 12px;">
								<strong><?php esc_html_e( 'ALT Text:', 'seo-booster' ); ?></strong>
								<div style="margin-top: 5px; color: #333;"><?php echo esc_html( $latest_content['alt_text'] ); ?></div>
							</div>
							<?php endif; ?>
							<?php if ( ! empty( $latest_content['caption'] ) ) : ?>
							<div class="sb-content-item" style="margin-bottom: 12px;">
								<strong><?php esc_html_e( 'Caption:', 'seo-booster' ); ?></strong>
								<div style="margin-top: 5px; color: #333;"><?php echo esc_html( $latest_content['caption'] ); ?></div>
							</div>
							<?php endif; ?>
							<?php if ( ! empty( $latest_content['description'] ) ) : ?>
							<div class="sb-content-item" style="margin-bottom: 12px;">
								<strong><?php esc_html_e( 'Description:', 'seo-booster' ); ?></strong>
								<div style="margin-top: 5px; color: #333;"><?php echo esc_html( $latest_content['description'] ); ?></div>
							</div>
							<?php endif; ?>
						</div>
					</div>
					<?php endif; ?>
					
					<div id="sb-image-generation-results" style="display: none; margin-top: 15px;">
						<div class="sb-generated-content">
							<h4><?php esc_html_e( 'Generated Content', 'seo-booster' ); ?></h4>
							<div class="sb-generated-content-fields">
								<div class="sb-content-item">
									<label style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
										<input type="checkbox" class="sb-field-checkbox" data-field="title" checked>
										<strong><?php esc_html_e( 'Title:', 'seo-booster' ); ?></strong>
									</label>
									<input type="text" class="sb-generated-input" id="sb-generated-title" data-field="title" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
								</div>
								<div class="sb-content-item" style="margin-top: 15px;">
									<label style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
										<input type="checkbox" class="sb-field-checkbox" data-field="alt_text" checked>
										<strong><?php esc_html_e( 'ALT Text:', 'seo-booster' ); ?></strong>
									</label>
									<input type="text" class="sb-generated-input" id="sb-generated-alt" data-field="alt_text" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
								</div>
								<div class="sb-content-item" style="margin-top: 15px;">
									<label style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
										<input type="checkbox" class="sb-field-checkbox" data-field="caption" checked>
										<strong><?php esc_html_e( 'Caption:', 'seo-booster' ); ?></strong>
									</label>
									<textarea class="sb-generated-input" id="sb-generated-caption" data-field="caption" rows="2" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>
								</div>
								<div class="sb-content-item" style="margin-top: 15px;">
									<label style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
										<input type="checkbox" class="sb-field-checkbox" data-field="description" checked>
										<strong><?php esc_html_e( 'Description:', 'seo-booster' ); ?></strong>
									</label>
									<textarea class="sb-generated-input" id="sb-generated-description" data-field="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>
								</div>
							</div>
							<div class="sb-content-actions" style="margin-top: 15px;">
								<button type="button" id="sb-apply-image-content" class="button button-primary">
									<?php esc_html_e( 'Apply to Image', 'seo-booster' ); ?>
								</button>
								<?php if ( $has_previous ) : ?>
								<button type="button" id="sb-restore-image-content" class="button">
									<?php esc_html_e( 'Restore Previous', 'seo-booster' ); ?>
								</button>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</div>
		
		<input type="hidden" id="sb-seo-item-id" value="<?php echo esc_attr( $attachment_id ); ?>">
		<input type="hidden" id="sb-seo-item-type" value="post">
		<input type="hidden" id="sb-attachment-id" value="<?php echo esc_attr( $attachment_id ); ?>">
		<?php
	}


	/**
	 * Enqueue scripts and styles for the meta box.
	 *
	 * @since 6.1.26
	 * @param string $hook The current admin page.
	 * @return void
	 */
	public static function enqueue_scripts( $hook ) {
		// Only enqueue on post edit pages, term edit pages, and SEO settings page
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'term.php', 'edit-tags.php', 'seo-booster_page_sb2_seo_settings' ) ) ) {
			return;
		}

		Utils::enqueue_modal_assets();

		wp_enqueue_script(
			'sb-seo-examples-display',
			SEOBOOSTER_PLUGINURL . 'js/sb-seo-examples-display.js',
			array( 'jquery' ),
			filemtime( SEOBOOSTER_PLUGINPATH . 'js/sb-seo-examples-display.js' ),
			true
		);

		wp_enqueue_script(
			'sb-seo-metabox',
			SEOBOOSTER_PLUGINURL . 'js/sb-seo-metabox.js',
			array( 'jquery', 'sb-seo-examples-display', 'sb-modal' ),
			filemtime( SEOBOOSTER_PLUGINPATH . 'js/sb-seo-metabox.js' ),
			true
		);

		wp_enqueue_style(
			'sb-seo-metabox',
			SEOBOOSTER_PLUGINURL . 'css/sb-seo-metabox.css',
			array(),
			filemtime( SEOBOOSTER_PLUGINPATH . 'css/sb-seo-metabox.css' )
		);

		$credits_data = array();
		$provider     = LLM_Helper::get_selected_ai_provider();
		if ( $provider === 'seobooster' && Credits_Service::is_registered() ) {
			$credits_data = array(
				'balance'       => Credits_Service::get_balance(),
				'is_registered' => true,
				'buy_url'       => admin_url( 'admin.php?page=sb2_settings#ai-llm' ),
			);
		}

		$post_id = 0;
		if ( ! empty( $_GET['post'] ) ) {
			$post_id = (int) $_GET['post'];
		} elseif ( function_exists( 'get_the_ID' ) ) {
			$post_id = (int) get_the_ID();
		}
		$pending_request_id   = $post_id ? get_post_meta( $post_id, '_sb_pending_request_id', true ) : '';
		$pending_request_type = $post_id && $pending_request_id ? get_post_meta( $post_id, '_sb_pending_request_type', true ) : '';
		if ( ! is_string( $pending_request_type ) || $pending_request_type === '' ) {
			$pending_request_type = 'seo_suggestions';
		}
		$recent_request_ids = $post_id ? get_post_meta( $post_id, '_sb_recent_request_ids', true ) : array();
		if ( ! is_array( $recent_request_ids ) ) {
			$recent_request_ids = array();
		}
		$saved_comprehensive = $post_id ? get_post_meta( $post_id, '_sb_comprehensive_analysis_result', true ) : array();
		if ( ! is_array( $saved_comprehensive ) || empty( $saved_comprehensive ) ) {
			$saved_comprehensive = null;
		}
		$saved_llm_meta        = $post_id ? LLM_Helper::get_saved_suggestions( $post_id ) : false;
		$has_saved_suggestions = is_array( $saved_llm_meta )
			&& ! empty( $saved_llm_meta['titles'] )
			&& ! empty( $saved_llm_meta['descriptions'] );
		$has_recent_request_ids = ! empty( $recent_request_ids );

		wp_localize_script(
			'sb-seo-metabox',
			'sb_seo_metabox',
			array(
				'ajax_url'                     => admin_url( 'admin-ajax.php' ),
				'nonce'                        => wp_create_nonce( 'sb_seo_metabox_nonce' ),
				'ai_provider'                  => $provider,
				'credits'                      => $credits_data,
				'post_id'                      => $post_id,
				'ai_readiness_keys'            => Ai_Readiness_Registry::get_issue_keys(),
				'readiness_nonce'              => wp_create_nonce( AI_Readiness::NONCE_ACTION ),
				'readiness_action'             => 'sb_ai_readiness_score',
				'pending_request_id'           => $pending_request_id ?: '',
				'pending_request_type'         => $pending_request_type,
				'recent_request_ids'           => $recent_request_ids,
				'saved_comprehensive_analysis' => $saved_comprehensive,
				'has_saved_suggestions'        => $has_saved_suggestions,
				'has_recent_request_ids'       => $has_recent_request_ids,
				'strings'                      => array(
					'error'                     => __( 'Error loading suggestions', 'seo-booster' ),
					'no_suggestions'            => __( 'No keyword traffic from GSC found for this URL', 'seo-booster' ),
					'import_success'            => __( 'Successfully imported SEO data', 'seo-booster' ),
					'import_error'              => __( 'Error importing SEO data', 'seo-booster' ),
					'loading'                   => __( 'Loading...', 'seo-booster' ),
					'analyzing'                 => __( 'Analyzing...', 'seo-booster' ),
					'analysis_failed'           => __( 'Analysis failed', 'seo-booster' ),
					'downloading'               => __( 'Downloading...', 'seo-booster' ),
					'downloading_and_analyzing' => __( 'Downloading and analyzing full page...', 'seo-booster' ),
					'download_failed'           => __( 'Download and analysis failed', 'seo-booster' ),
					'last_downloaded'           => __( 'Last downloaded', 'seo-booster' ),
					'issues'                    => __( 'Possibilities', 'seo-booster' ),
					'improvements'              => __( 'Improvements', 'seo-booster' ),
					'opportunities'             => __( 'Opportunities', 'seo-booster' ),
					'good'                      => __( 'Good', 'seo-booster' ),
					'not_applicable'            => __( 'Not applicable', 'seo-booster' ),
					'no_analysis_results'       => __( 'No analysis results available', 'seo-booster' ),
					'analysis_complete'         => __( 'Analysis complete! No possibilities found.', 'seo-booster' ),
					'not_analyzed_title'        => __( 'Not Analyzed Yet', 'seo-booster' ),
					'not_analyzed_message'      => __( 'This page has not been analyzed yet. Click the button below to download and analyze the full page.', 'seo-booster' ),
					'recent_requests'           => __( 'Recent requests', 'seo-booster' ),
					'retry'                     => __( 'Retry', 'seo-booster' ),
					'request_status_queued'     => __( 'Queued', 'seo-booster' ),
					'request_status_processing' => __( 'Processing', 'seo-booster' ),
					'request_status_completed'  => __( 'Completed', 'seo-booster' ),
					'request_status_failed'     => __( 'Failed', 'seo-booster' ),
					'request_type_seo'          => __( 'SEO suggestions', 'seo-booster' ),
					'request_type_product'      => __( 'Product SEO', 'seo-booster' ),
					'request_type_analysis'     => __( 'Full analysis', 'seo-booster' ),
					'request_type_image'        => __( 'Image descriptions', 'seo-booster' ),
					'no_recent_requests'        => __( 'No recent requests for this page.', 'seo-booster' ),
					'quick_review'              => __( 'Run quick review', 'seo-booster' ),
					'quick_review_running'      => __( 'Running quick review…', 'seo-booster' ),
					'readiness_not_analyzed'    => __( 'Not analyzed yet.', 'seo-booster' ),
				),
			)
		);
	}

	/**
	 * Get keyword suggestions for the current URL.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function get_keyword_suggestions() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$current_url = isset( $_POST['current_url'] ) ? esc_url_raw( $_POST['current_url'] ) : '';

		global $wpdb;

		// Get keywords for this URL from GSC data
		$query = $wpdb->prepare(
			"SELECT DISTINCT qk.query, 
                    COALESCE(SUM(qkh.clicks), 0) as clicks,
                    COALESCE(SUM(qkh.impressions), 0) as impressions,
                    COALESCE(AVG(qkh.position), 0) as position
             FROM {$wpdb->prefix}sb2_query_keywords qk
             LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history qkh ON qk.id = qkh.query_keywords_id
             WHERE qk.page LIKE %s
             GROUP BY qk.query
             ORDER BY clicks DESC, impressions DESC
             LIMIT 10",
			'%' . $wpdb->esc_like( $current_url ) . '%'
		);

		$keywords = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $keywords ) ) {
			wp_send_json_success(
				array(
					'keywords' => array(),
					'message'  => __( 'No keyword data available for this URL', 'seo-booster' ),
				)
			);
		}

		wp_send_json_success( array( 'keywords' => $keywords ) );
	}


	/**
	 * Detect installed SEO plugins.
	 *
	 * @since 6.1.26
	 * @return array Array of detected SEO plugins.
	 */
	public static function detect_seo_plugins() {
		$detected = array();
		foreach ( SEO_Plugin_Registry::get_adapters() as $adapter ) {
			if ( $adapter->is_active() ) {
				$detected[ $adapter->get_slug() ] = array(
					'name' => $adapter->get_label(),
				);
			}
		}

		return $detected;
	}

	/**
	 * AJAX handler for SEO analysis.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function ajax_seo_analysis() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$object_id   = isset( $_POST['object_id'] ) ? intval( $_POST['object_id'] ) : 0;
		$object_type = isset( $_POST['object_type'] ) ? sanitize_text_field( $_POST['object_type'] ) : 'post';

		// Get current input values
		$current_title         = isset( $_POST['current_title'] ) ? sanitize_text_field( $_POST['current_title'] ) : '';
		$current_description   = isset( $_POST['current_description'] ) ? sanitize_textarea_field( $_POST['current_description'] ) : '';
		$current_focus_keyword = isset( $_POST['current_focus_keyword'] ) ? sanitize_text_field( $_POST['current_focus_keyword'] ) : '';

		if ( ! $object_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid object ID', 'seo-booster' ) ) );
		}

		try {
			$analysis = new \Cleverplugins\SEOBooster\SEO_Analysis( $object_id, $object_type );
			$analysis->set_current_values( $current_title, $current_description, $current_focus_keyword );

			// Use full page content to ensure H1 tags and other theme-generated elements are detected
			$results = $analysis->analyze( true );

			wp_send_json_success( $results );
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => __( 'Analysis failed', 'seo-booster' ) ) );
		}
	}

	/**
	 * AJAX handler for downloading and analyzing full page content.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function ajax_download_and_analyze() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$object_id      = isset( $_POST['object_id'] ) ? intval( $_POST['object_id'] ) : 0;
		$object_type    = isset( $_POST['object_type'] ) ? sanitize_text_field( $_POST['object_type'] ) : 'post';
		$force_download = isset( $_POST['force_download'] ) ? (bool) $_POST['force_download'] : false;

		if ( ! $object_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid object ID', 'seo-booster' ) ) );
		}

		// Check if excluded from analysis
		if ( $object_type === 'post' && \Cleverplugins\SEOBooster\SEO_Issues_Manager::should_exclude_from_analysis( $object_id ) ) {
			wp_send_json_error(
				array(
					'message'  => __( 'This page is excluded from SEO analysis', 'seo-booster' ),
					'excluded' => true,
				)
			);
		}

		try {
			// Run analysis with full page content, forcing download if requested
			$analysis = new \Cleverplugins\SEOBooster\SEO_Analysis( $object_id, $object_type );
			$results  = $analysis->analyze( true, $force_download ); // true = use full page content, force_download = force redownload

			// Get the last download timestamp
			$last_download            = get_post_meta( $object_id, '_sb_last_page_download', true );
			$results['last_download'] = $last_download ? gmdate( 'Y-m-d H:i:s', $last_download ) : null;

			wp_send_json_success( $results );
		} catch ( \Exception $e ) {
			Utils::log( 'SEO Analysis Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 2 );
			wp_send_json_error( array( 'message' => __( 'Download and analysis failed: ' . $e->getMessage(), 'seo-booster' ) ) );
		}
	}

	/**
	 * AJAX handler for getting saved analysis results.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function ajax_get_saved_analysis() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$object_id   = isset( $_POST['object_id'] ) ? intval( $_POST['object_id'] ) : 0;
		$object_type = isset( $_POST['object_type'] ) ? sanitize_text_field( $_POST['object_type'] ) : 'post';

		// Get current input values
		$current_title         = isset( $_POST['current_title'] ) ? sanitize_text_field( $_POST['current_title'] ) : '';
		$current_description   = isset( $_POST['current_description'] ) ? sanitize_textarea_field( $_POST['current_description'] ) : '';
		$current_focus_keyword = isset( $_POST['current_focus_keyword'] ) ? sanitize_text_field( $_POST['current_focus_keyword'] ) : '';

		if ( ! $object_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid object ID', 'seo-booster' ) ) );
		}

		// Check if excluded from analysis
		if ( $object_type === 'post' && \Cleverplugins\SEOBooster\SEO_Issues_Manager::should_exclude_from_analysis( $object_id ) ) {
			wp_send_json_success(
				array(
					'score'        => null,
					'issues'       => array(),
					'improvements' => array(),
					'good'         => array(),
					'excluded'     => true,
					'message'      => __( 'This page is excluded from SEO analysis', 'seo-booster' ),
				)
			);
			return;
		}

		try {
			// Check if there's a pending analysis (content has changed)
			$has_pending = \Cleverplugins\SEOBooster\SEO_Issues_Manager::has_pending_analysis( $object_id, $object_type );

			// Get saved analysis results - return if score exists, regardless of analysis type
			$saved_analysis = \Cleverplugins\SEOBooster\SEO_Analysis::get_saved_analysis( $object_id, $object_type );

			// Check if we have a valid saved analysis with a score
			// Score can be 0 (valid score), so we check for null explicitly
			$has_score = $saved_analysis && isset( $saved_analysis['score'] ) && $saved_analysis['score'] !== null;

			if ( $has_score ) {
				// Use saved analysis results if score exists (from any analysis type)
				$results = $saved_analysis;
				// Ensure content_changed flag is set if pending
				if ( $has_pending ) {
					$results['content_changed'] = true;
				}

				// Check if GSC checks are present in saved analysis
				$has_gsc_checks = false;
				if ( ! empty( $results['issues'] ) ) {
					foreach ( $results['issues'] as $issue ) {
						if ( isset( $issue['key'] ) && strpos( $issue['key'], 'gsc_' ) === 0 ) {
							$has_gsc_checks = true;
							break;
						}
					}
				}
				if ( ! $has_gsc_checks && ! empty( $results['improvements'] ) ) {
					foreach ( $results['improvements'] as $improvement ) {
						if ( isset( $improvement['key'] ) && strpos( $improvement['key'], 'gsc_' ) === 0 ) {
							$has_gsc_checks = true;
							break;
						}
					}
				}
				if ( ! $has_gsc_checks && ! empty( $results['good'] ) ) {
					foreach ( $results['good'] as $good ) {
						if ( isset( $good['key'] ) && strpos( $good['key'], 'gsc_' ) === 0 ) {
							$has_gsc_checks = true;
							break;
						}
					}
				}

				// If GSC is connected but no GSC checks found, mark as needing update
				$gsc_connected = false;
				if ( class_exists( '\Cleverplugins\SEOBooster\Google_API' ) ) {
					$access_token  = \Cleverplugins\SEOBooster\Google_API::get_access_token();
					$gsc_connected = ! empty( $access_token ) && ! is_wp_error( $access_token );
				}

				if ( $gsc_connected && ! $has_gsc_checks ) {
					$results['needs_refresh']  = true;
					$results['refresh_reason'] = __( 'Analysis is missing Google Search Console checks. Please re-run analysis to see all checks.', 'seo-booster' );
				}

				// Add last download info if available (for full page analysis)
				if ( $object_type === 'post' ) {
					$last_download = get_post_meta( $object_id, '_sb_last_page_download', true );
					if ( ! empty( $last_download ) ) {
						$results['last_download'] = gmdate( 'Y-m-d H:i:s', $last_download );
					}
				}

				wp_send_json_success( $results );
			} else {
				// No saved analysis with score found
				// Return response without score/issues so JavaScript shows "not analyzed yet" message
				wp_send_json_success(
					array(
						'score'        => null,
						'issues'       => array(),
						'improvements' => array(),
						'good'         => array(),
					)
				);
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => __( 'Failed to get analysis', 'seo-booster' ) ) );
		}
	}

	/**
	 * Build a text summary of saved local SEO analysis for inclusion in AI requests.
	 *
	 * @since 7.1.0
	 * @param int $post_id Post ID.
	 * @return string Summary string or empty if no saved analysis.
	 */
	private static function build_local_analysis_summary( $post_id ) {
		$saved = SEO_Analysis::get_saved_analysis( $post_id, 'post' );
		if ( ! $saved || ! isset( $saved['score'] ) ) {
			return '';
		}
		$lines   = array();
		$lines[] = 'Score: ' . (int) $saved['score'] . '/100';
		if ( ! empty( $saved['issues'] ) && is_array( $saved['issues'] ) ) {
			$messages = array_map(
				function ( $i ) {
					$m = isset( $i['message'] ) ? $i['message'] : '';
					$s = isset( $i['severity'] ) ? ' [' . $i['severity'] . ']' : '';
					return '- ' . $m . $s;
				},
				$saved['issues']
			);
			$lines[]  = 'Issues:';
			$lines[]  = implode( "\n", array_slice( $messages, 0, 15 ) );
		}
		if ( ! empty( $saved['improvements'] ) && is_array( $saved['improvements'] ) ) {
			$messages = array_map(
				function ( $i ) {
					return '- ' . ( isset( $i['message'] ) ? $i['message'] : '' );
				},
				array_slice( $saved['improvements'], 0, 10 )
			);
			$lines[]  = 'Improvements:';
			$lines[]  = implode( "\n", $messages );
		}
		if ( ! empty( $saved['good'] ) && is_array( $saved['good'] ) ) {
			$messages = array_map(
				function ( $i ) {
					return '- ' . ( isset( $i['message'] ) ? $i['message'] : '' );
				},
				array_slice( $saved['good'], 0, 5 )
			);
			$lines[]  = 'Good:';
			$lines[]  = implode( "\n", $messages );
		}
		return implode( "\n", $lines );
	}

	/**
	 * AJAX handler for generating SEO suggestions via WordPress Connectors (WP 7).
	 *
	 * @return void
	 */
	public static function ajax_generate_wp_connector_suggestions() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		if ( ! LLM_Helper::wp_ai_is_available() ) {
			wp_send_json_error( array( 'message' => LLM_Helper::wp_ai_unavailable_message() ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'seo-booster' ) ) );
		}

		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			wp_send_json_error( array( 'message' => __( 'WordPress AI is not available. Use WordPress 7 or later and configure Settings → Connectors.', 'seo-booster' ) ) );
		}

		try {
			$language               = LLM_Helper::get_post_language( $post_id );
			$condenser              = new LLM_Content_Condenser();
			$condensed              = $condenser->condense_post_content( $post_id );
			$local_analysis_summary = self::build_local_analysis_summary( $post_id );

			$service = new LLM_WP_Connector_Service();
			$result  = $service->generate_suggestions( $post_id, $condensed, $language, $local_analysis_summary );

			wp_send_json_success(
				array(
					'titles'       => $result['titles'],
					'descriptions' => $result['descriptions'],
					'language'     => $language,
					'provider'     => 'wordpress',
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX handler for generating suggestions via SEO Booster Credits API.
	 *
	 * Submits request to the credits API and returns a request_id for polling.
	 *
	 * @since 6.2.0
	 * @return void
	 */
	public static function ajax_generate_credits_suggestions() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'seo-booster' ) ) );
		}

		if ( ! Credits_Service::is_registered() ) {
			wp_send_json_error( array( 'message' => __( 'Credits account not connected. Please go to Settings to connect.', 'seo-booster' ) ) );
		}

		try {
			$post     = get_post( $post_id );
			$language = LLM_Helper::get_post_language( $post_id );

			$condenser = new LLM_Content_Condenser();
			$condensed = $condenser->condense_post_content( $post_id );

			// Gather post data for the API
			$categories = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );
			$tags       = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );

			$focus_keywords = Google_API::get_focus_keywords( $post_id );

			$gsc_keywords = array();
			$gsc_data     = get_post_meta( $post_id, '_sb_seo_keyword_data', true );
			if ( ! empty( $gsc_data ) && is_array( $gsc_data ) ) {
				$gsc_keywords = array_slice( array_column( $gsc_data, 'query' ), 0, 10 );
			}

			$seo_meta         = SEO_Plugin_Registry::read_post_seo( $post_id );
			$meta_description = $seo_meta['description'] ?? '';

			$input_data = array(
				'post_title'             => $post->post_title,
				'post_description'       => $meta_description,
				'condensed_content'      => $condensed,
				'categories'             => is_array( $categories ) ? $categories : array(),
				'tags'                   => is_array( $tags ) ? $tags : array(),
				'focus_keywords'         => is_array( $focus_keywords ) ? $focus_keywords : array(),
				'gsc_keywords'           => $gsc_keywords,
				'permalink'              => get_permalink( $post_id ),
				'post_type'              => $post->post_type,
				'language'               => $language,
				'local_analysis_summary' => self::build_local_analysis_summary( $post_id ),
			);

			$callback_url = rest_url( 'seo-booster/v1/callback' );

			$result = Credits_Service::submit_request( 'seo_suggestions', $input_data, $callback_url );

			if ( $result['success'] ) {
				wp_send_json_success(
					array(
						'request_id'        => $result['request_id'],
						'credits_used'      => $result['credits_used'],
						'credits_remaining' => $result['credits_remaining'],
						'provider'          => 'seobooster',
						'post_id'           => $post_id,
					)
				);
			} else {
				$error_data = array( 'message' => $result['error'] );
				if ( ! empty( $result['insufficient'] ) ) {
					$error_data['insufficient_credits'] = true;
					$error_data['credits_remaining']    = $result['credits_remaining'];
					$error_data['credits_needed']       = $result['credits_needed'];
					$error_data['buy_url']              = admin_url( 'admin.php?page=sb2_settings#ai-llm' );
				}
				wp_send_json_error( $error_data );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX handler for generating product SEO via credits.
	 *
	 * @since 7.1.0
	 * @return void
	 */
	public static function ajax_generate_product_seo() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'seo-booster' ) ) );
		}

		if ( ! Credits_Service::is_registered() ) {
			wp_send_json_error( array( 'message' => __( 'Credits account not connected.', 'seo-booster' ) ) );
		}

		try {
			$post     = get_post( $post_id );
			$language = LLM_Helper::get_post_language( $post_id );

			$condenser = new LLM_Content_Condenser();
			$condensed = $condenser->condense_post_content( $post_id );

			$categories     = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );
			$tags           = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
			$focus_keywords = Google_API::get_focus_keywords( $post_id );

			$gsc_keywords = array();
			$gsc_data     = get_post_meta( $post_id, '_sb_seo_keyword_data', true );
			if ( ! empty( $gsc_data ) && is_array( $gsc_data ) ) {
				$gsc_keywords = array_slice( array_column( $gsc_data, 'query' ), 0, 10 );
			}

			$seo_meta         = SEO_Plugin_Registry::read_post_seo( $post_id );
			$meta_description = $seo_meta['description'] ?? '';

			// WooCommerce-specific data
			$price             = '';
			$sku               = '';
			$attributes        = '';
			$stock_status      = '';
			$short_description = $post->post_excerpt;

			if ( function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $post_id );
				if ( $product ) {
					$price        = $product->get_price() ? html_entity_decode( strip_tags( wc_price( $product->get_price() ) ) ) : '';
					$sku          = $product->get_sku();
					$stock_status = $product->get_stock_status();

					$attrs        = $product->get_attributes();
					$attr_strings = array();
					foreach ( $attrs as $attr ) {
						if ( is_a( $attr, 'WC_Product_Attribute' ) ) {
							$name           = wc_attribute_label( $attr->get_name() );
							$values         = $attr->is_taxonomy()
								? implode( ', ', wc_get_product_terms( $post_id, $attr->get_name(), array( 'fields' => 'names' ) ) )
								: implode( ', ', $attr->get_options() );
							$attr_strings[] = "$name: $values";
						}
					}
					$attributes = implode( '; ', $attr_strings );

					// WC product categories
					$product_cats = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'names' ) );
					if ( ! is_wp_error( $product_cats ) && ! empty( $product_cats ) ) {
						$categories = $product_cats;
					}

					$product_tags = wp_get_post_terms( $post_id, 'product_tag', array( 'fields' => 'names' ) );
					if ( ! is_wp_error( $product_tags ) && ! empty( $product_tags ) ) {
						$tags = $product_tags;
					}
				}
			}

			$input_data = array(
				'post_title'        => $post->post_title,
				'post_description'  => $meta_description,
				'short_description' => $short_description,
				'condensed_content' => $condensed,
				'categories'        => is_array( $categories ) ? $categories : array(),
				'tags'              => is_array( $tags ) ? $tags : array(),
				'focus_keywords'    => is_array( $focus_keywords ) ? $focus_keywords : array(),
				'gsc_keywords'      => $gsc_keywords,
				'permalink'         => get_permalink( $post_id ),
				'post_type'         => 'product',
				'language'          => $language,
				'price'             => $price,
				'sku'               => $sku,
				'attributes'        => $attributes,
				'stock_status'      => $stock_status,
			);

			$callback_url = rest_url( 'seo-booster/v1/callback' );
			$result       = Credits_Service::submit_request( 'product_seo', $input_data, $callback_url );

			if ( $result['success'] ) {
				wp_send_json_success(
					array(
						'request_id'        => $result['request_id'],
						'credits_used'      => $result['credits_used'],
						'credits_remaining' => $result['credits_remaining'],
						'provider'          => 'seobooster',
						'post_id'           => $post_id,
					)
				);
			} else {
				$error_data = array( 'message' => $result['error'] );
				if ( ! empty( $result['insufficient'] ) ) {
					$error_data['insufficient_credits'] = true;
					$error_data['credits_remaining']    = $result['credits_remaining'];
					$error_data['credits_needed']       = $result['credits_needed'];
					$error_data['buy_url']              = admin_url( 'admin.php?page=sb2_settings#ai-llm' );
				}
				wp_send_json_error( $error_data );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX handler for comprehensive SEO analysis via credits.
	 *
	 * @since 7.1.0
	 * @return void
	 */
	public static function ajax_generate_comprehensive_analysis() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'seo-booster' ) ) );
		}

		if ( ! Credits_Service::is_registered() ) {
			wp_send_json_error( array( 'message' => __( 'Credits account not connected.', 'seo-booster' ) ) );
		}

		try {
			$post     = get_post( $post_id );
			$language = LLM_Helper::get_post_language( $post_id );

			$condenser = new LLM_Content_Condenser();
			$condensed = $condenser->condense_post_content( $post_id );

			$categories     = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );
			$tags           = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
			$focus_keywords = Google_API::get_focus_keywords( $post_id );

			$gsc_keywords = array();
			$gsc_data     = get_post_meta( $post_id, '_sb_seo_keyword_data', true );
			if ( ! empty( $gsc_data ) && is_array( $gsc_data ) ) {
				$gsc_keywords = array_slice( array_column( $gsc_data, 'query' ), 0, 15 );
			}

			$seo_meta         = SEO_Plugin_Registry::read_post_seo( $post_id );
			$meta_description = $seo_meta['description'] ?? '';

			// Gather some site pages for internal linking suggestions
			$site_pages  = array();
			$pages_query = get_posts(
				array(
					'post_type'      => array( 'post', 'page', 'product' ),
					'post_status'    => 'publish',
					'posts_per_page' => 20,
					'exclude'        => array( $post_id ),
					'orderby'        => 'date',
					'order'          => 'DESC',
					'fields'         => 'ids',
				)
			);
			foreach ( $pages_query as $pid ) {
				$site_pages[] = get_the_title( $pid ) . ' (' . get_permalink( $pid ) . ')';
			}

			$input_data = array(
				'post_title'             => $post->post_title,
				'post_description'       => $meta_description,
				'condensed_content'      => $condensed,
				'categories'             => is_array( $categories ) ? $categories : array(),
				'tags'                   => is_array( $tags ) ? $tags : array(),
				'focus_keywords'         => is_array( $focus_keywords ) ? $focus_keywords : array(),
				'gsc_keywords'           => $gsc_keywords,
				'permalink'              => get_permalink( $post_id ),
				'post_type'              => $post->post_type,
				'language'               => $language,
				'site_pages'             => implode( "\n", $site_pages ),
				'local_analysis_summary' => self::build_local_analysis_summary( $post_id ),
			);

			$callback_url = rest_url( 'seo-booster/v1/callback' );
			$result       = Credits_Service::submit_request( 'comprehensive_seo_analysis', $input_data, $callback_url );

			if ( $result['success'] ) {
				wp_send_json_success(
					array(
						'request_id'        => $result['request_id'],
						'credits_used'      => $result['credits_used'],
						'credits_remaining' => $result['credits_remaining'],
						'provider'          => 'seobooster',
						'post_id'           => $post_id,
						'request_type'      => 'comprehensive_seo_analysis',
					)
				);
			} else {
				$error_data = array( 'message' => $result['error'] );
				if ( ! empty( $result['insufficient'] ) ) {
					$error_data['insufficient_credits'] = true;
					$error_data['credits_remaining']    = $result['credits_remaining'];
					$error_data['credits_needed']       = $result['credits_needed'];
					$error_data['buy_url']              = admin_url( 'admin.php?page=sb2_settings#ai-llm' );
				}
				wp_send_json_error( $error_data );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX handler for polling credits request status.
	 *
	 * @since 6.2.0
	 * @return void
	 */
	public static function ajax_poll_credits_request() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$request_id = isset( $_POST['request_id'] ) ? sanitize_text_field( wp_unslash( $_POST['request_id'] ) ) : '';
		$post_id    = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( empty( $request_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing request ID', 'seo-booster' ) ) );
		}

		$result = Credits_Service::get_request_status( $request_id );

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}

		$response = array(
			'status'            => $result['status'],
			'credits_remaining' => $result['credits_remaining'],
		);

		if ( $result['status'] === 'completed' && ! empty( $result['data'] ) ) {
			$response['data'] = $result['data'];

			// Save the results to post meta for later retrieval
			if ( $post_id && ! empty( $result['data'] ) ) {
				$data = $result['data'];
				if ( isset( $data['overall_score'] ) || isset( $data['priority_issues'] ) ) {
					update_post_meta( $post_id, '_sb_comprehensive_analysis_result', $data );
				} else {
					$compact_result = array(
						'titles'       => $data['titles'] ?? null,
						'descriptions' => $data['descriptions'] ?? null,
						'status'       => 'success',
						'language'     => $data['language'] ?? '',
						'timestamp'    => time(),
						'provider'     => 'seobooster',
					);
					update_post_meta( $post_id, '_llm_seo_last_result', $compact_result );
				}
			}
		}

		if ( $result['status'] === 'failed' ) {
			$response['error'] = $result['error'];
		}

		wp_send_json_success( $response );
	}

	/**
	 * Save pending credits request ID for this post (so user can "Check status" after reload).
	 *
	 * @since 6.2.0
	 */
	public static function ajax_save_pending_request() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}
		$post_id      = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$request_id   = isset( $_POST['request_id'] ) ? sanitize_text_field( wp_unslash( $_POST['request_id'] ) ) : '';
		$request_type = isset( $_POST['request_type'] ) ? sanitize_text_field( wp_unslash( $_POST['request_type'] ) ) : 'seo_suggestions';
		if ( ! $post_id || ! $request_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post or request ID', 'seo-booster' ) ) );
		}
		update_post_meta( $post_id, '_sb_pending_request_id', $request_id );
		update_post_meta( $post_id, '_sb_pending_request_type', $request_type );

		// Append to recent request history (cap at 10).
		$recent = get_post_meta( $post_id, '_sb_recent_request_ids', true );
		if ( ! is_array( $recent ) ) {
			$recent = array();
		}
		array_unshift(
			$recent,
			array(
				'id'      => $request_id,
				'type'    => $request_type,
				'created' => time(),
			)
		);
		$recent = array_slice( $recent, 0, 10 );
		update_post_meta( $post_id, '_sb_recent_request_ids', $recent );

		wp_send_json_success();
	}

	/**
	 * Clear pending credits request for this post (when completed or failed).
	 *
	 * @since 6.2.0
	 */
	public static function ajax_clear_pending_request() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'seo-booster' ) ) );
		}
		delete_post_meta( $post_id, '_sb_pending_request_id' );
		delete_post_meta( $post_id, '_sb_pending_request_type' );
		wp_send_json_success();
	}

	/**
	 * AJAX handler: return status for each request in this post's recent request history.
	 *
	 * @since 6.2.0
	 */
	public static function ajax_get_recent_request_statuses() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'seo-booster' ) ) );
		}
		$recent = get_post_meta( $post_id, '_sb_recent_request_ids', true );
		if ( ! is_array( $recent ) || empty( $recent ) ) {
			wp_send_json_success( array( 'statuses' => array() ) );
		}
		$statuses = array();
		foreach ( $recent as $entry ) {
			$id      = isset( $entry['id'] ) ? $entry['id'] : '';
			$type    = isset( $entry['type'] ) ? $entry['type'] : 'seo_suggestions';
			$created = isset( $entry['created'] ) ? (int) $entry['created'] : 0;
			if ( ! $id ) {
				continue;
			}
			$result     = Credits_Service::get_request_status( $id );
			$statuses[] = array(
				'id'      => $id,
				'type'    => $type,
				'created' => $created,
				'status'  => $result['success'] ? $result['status'] : 'unknown',
				'error'   => $result['success'] ? null : ( $result['error'] ?? null ),
			);
		}
		wp_send_json_success( array( 'statuses' => $statuses ) );
	}

	/**
	 * AJAX handler: retry a request (re-queue) via Credits API.
	 *
	 * @since 6.2.0
	 */
	public static function ajax_retry_request() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}
		$request_id = isset( $_POST['request_id'] ) ? sanitize_text_field( wp_unslash( $_POST['request_id'] ) ) : '';
		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request ID', 'seo-booster' ) ) );
		}
		$result = Credits_Service::retry_request( $request_id );
		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'enqueued'   => true,
					'request_id' => $request_id,
				)
			);
		}
		wp_send_json_error( array( 'message' => $result['error'] ) );
	}

	/**
	 * AJAX handler for getting saved LLM suggestions.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function ajax_get_llm_suggestions() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'seo-booster' ) ) );
		}

		$saved = LLM_Helper::get_saved_suggestions( $post_id );

		$response = $saved ?: array(
			'titles'       => null,
			'descriptions' => null,
		);

		$provider = LLM_Helper::get_selected_ai_provider();
		if ( $provider === 'seobooster' && Credits_Service::is_registered() ) {
			$response['credits_remaining'] = Credits_Service::get_balance();
		}

		wp_send_json_success( $response );
	}



	/**
	 * AJAX handler for step-by-step analysis.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function ajax_analyze_step() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$step    = isset( $_POST['step'] ) ? sanitize_key( $_POST['step'] ) : 'start';

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'seo-booster' ) ) );
		}

		$analyzer = new SEO_Analysis( $post_id );
		$result   = $analyzer->analyze_step( $step );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for toggling analysis exclusion.
	 *
	 * @since 7.0.0
	 * @return void
	 */
	public static function ajax_toggle_analysis_exclusion() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$exclude = isset( $_POST['exclude'] ) ? (bool) $_POST['exclude'] : false;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'seo-booster' ) ) );
		}

		// Update postmeta
		if ( $exclude ) {
			update_post_meta( $post_id, '_sb_exclude_from_analysis', '1' );
		} else {
			update_post_meta( $post_id, '_sb_exclude_from_analysis', '0' );
		}

		// Check if auto-excluded
		$is_auto_excluded = \Cleverplugins\SEOBooster\SEO_Issues_Manager::is_auto_excluded( $post_id );
		$exclusion_reason = '';
		if ( $is_auto_excluded ) {
			$post_status = get_post_status( $post_id );
			if ( $post_status === 'private' ) {
				$exclusion_reason = __( 'This page is automatically excluded (private page)', 'seo-booster' );
			} elseif ( function_exists( 'wc_get_page_id' ) ) {
				$wc_page_types = array(
					'cart'      => __( 'Cart', 'seo-booster' ),
					'checkout'  => __( 'Checkout', 'seo-booster' ),
					'myaccount' => __( 'My Account', 'seo-booster' ),
					'shop'      => __( 'Shop', 'seo-booster' ),
				);
				foreach ( $wc_page_types as $page_type => $page_label ) {
					$wc_page_id = wc_get_page_id( $page_type );
					if ( $wc_page_id && $post_id === $wc_page_id ) {
						$exclusion_reason = sprintf( __( 'This page is automatically excluded (WooCommerce %s page)', 'seo-booster' ), $page_label );
						break;
					}
				}
			}
		}

		wp_send_json_success(
			array(
				'excluded'         => $exclude,
				'is_auto_excluded' => $is_auto_excluded,
				'exclusion_reason' => $exclusion_reason,
			)
		);
	}

	/**
	 * Persist an AI suggestion to the active SEO plugin (server-side).
	 *
	 * @since 7.2.5
	 * @return void
	 */
	public static function ajax_apply_seo_field() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		$object_id   = isset( $_POST['object_id'] ) ? intval( $_POST['object_id'] ) : 0;
		$object_type = isset( $_POST['object_type'] ) ? sanitize_text_field( wp_unslash( $_POST['object_type'] ) ) : 'post';
		$field       = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : '';
		$value       = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';

		if ( ! $object_id || ! in_array( $object_type, array( 'post', 'term' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid object.', 'seo-booster' ) ) );
		}

		if ( ! in_array( $field, array( 'title', 'description', 'focus_keyword' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid field.', 'seo-booster' ) ) );
		}

		if ( $object_type === 'post' ) {
			if ( ! current_user_can( 'edit_post', $object_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
			}
		} elseif ( ! current_user_can( 'edit_term', $object_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		if ( $field === 'description' ) {
			$value = sanitize_textarea_field( $value );
		} else {
			$value = sanitize_text_field( $value );
		}

		$result = SEO_Plugin_Integration::apply_suggestion(
			$field,
			$value,
			$object_id,
			$object_type,
			$field === 'focus_keyword'
		);

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => $result['message'] ?? __( 'Could not save to SEO plugin.', 'seo-booster' ) ) );
		}

		if ( empty( $result['persisted'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not save to SEO plugin.', 'seo-booster' ) ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Save exclusion preference on post save.
	 *
	 * @since 7.0.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function save_exclusion_preference( $post_id ) {
		// Check autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check nonce
		if ( ! isset( $_POST['sb_seo_metabox_nonce'] ) || ! wp_verify_nonce( $_POST['sb_seo_metabox_nonce'], 'sb_seo_metabox_nonce' ) ) {
			return;
		}

		// Save exclusion preference
		if ( isset( $_POST['sb_exclude_from_analysis'] ) ) {
			update_post_meta( $post_id, '_sb_exclude_from_analysis', '1' );
		} else {
			// Only set to '0' if not auto-excluded (user explicitly unchecked)
			if ( ! \Cleverplugins\SEOBooster\SEO_Issues_Manager::is_auto_excluded( $post_id ) ) {
				update_post_meta( $post_id, '_sb_exclude_from_analysis', '0' );
			}
		}
	}
}

SB_SEO_Metabox::init();
