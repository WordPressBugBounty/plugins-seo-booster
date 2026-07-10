<?php

namespace Cleverplugins\SEOBooster;

use Cleverplugins\SEOBooster\Analysis\Ai_Readiness_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editor view layer: AI Readiness checklist derived from SEO analysis + sitewide checks.
 *
 * The weighted AI Readiness score is a curated display lens — not a second SEO score engine.
 *
 * @since 7.2.3
 */
class AI_Readiness {

	const NONCE_ACTION = 'sb_ai_readiness_nonce';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_sb_ai_readiness_score', array( __CLASS__, 'ajax_editor_panel' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor_assets' ) );
	}

	/**
	 * Grade label and color for a score.
	 *
	 * @param int $score Score 0–100.
	 * @return array{label: string, color: string}
	 */
	public static function grade( $score ) {
		$score = (int) $score;

		if ( $score >= 80 ) {
			return array(
				'label' => __( 'Excellent', 'seo-booster' ),
				'color' => '#00a32a',
			);
		}

		if ( $score >= 60 ) {
			return array(
				'label' => __( 'Good', 'seo-booster' ),
				'color' => '#dba617',
			);
		}

		return array(
			'label' => __( 'Needs work', 'seo-booster' ),
			'color' => '#d63638',
		);
	}

	/**
	 * Build unified editor panel payload from analysis + sitewide data.
	 *
	 * @param array|null $analysis Per-post analysis array.
	 * @param array<int, array<string, mixed>> $sitewide_issues Sitewide issue rows.
	 * @return array<string, mixed>
	 */
	public static function build_view_from_analysis( $analysis, $sitewide_issues = array() ) {
		$key_sets   = Ai_Readiness_Registry::collect_analysis_keys( $analysis );
		$site_keys  = Ai_Readiness_Registry::collect_sitewide_keys( $sitewide_issues );
		$post_items = Ai_Readiness_Registry::get_post_items();

		$score   = 0;
		$max     = 0;
		$details = array();

		foreach ( $post_items as $item ) {
			$max += (int) $item['points'];
			$pass  = Ai_Readiness_Registry::item_passes( $item, $key_sets['pass'], $key_sets['fail'], $analysis );

			if ( $pass === true ) {
				$score += (int) $item['points'];
			}

			$details[] = array(
				'key'    => $item['key'],
				'label'  => $item['label'],
				'pass'   => $pass === true,
				'unknown'=> $pass === null,
				'points' => (int) $item['points'],
				'scope'  => 'post',
			);
		}

		$sitewide_details = array();
		foreach ( Ai_Readiness_Registry::get_sitewide_items() as $item ) {
			$pass = Ai_Readiness_Registry::item_passes( $item, $site_keys['pass'], $site_keys['fail'], null );
			$sitewide_details[] = array(
				'key'     => $item['key'],
				'label'   => $item['label'],
				'pass'    => $pass === true,
				'unknown' => $pass === null,
				'scope'   => 'sitewide',
			);
		}

		$readiness_score = min( $max, $score );
		$seo_score       = isset( $analysis['score'] ) ? (int) $analysis['score'] : null;

		return array(
			'score'              => $readiness_score,
			'max'                => $max,
			'grade'              => self::grade( $max > 0 ? (int) round( ( $readiness_score / $max ) * 100 ) : 0 ),
			'details'            => $details,
			'sitewide'           => $sitewide_details,
			'seo_score'          => $seo_score,
			'seo_score_grade'    => $seo_score !== null ? self::grade( $seo_score ) : null,
			'top_possibilities'  => self::get_top_possibilities( $analysis ),
			'analyzed_at'        => self::get_analyzed_at( $analysis ),
			'has_analysis'       => ! empty( $analysis ) && ( $seo_score !== null || ! empty( $key_sets['all'] ) ),
			'issues_url'         => admin_url( 'admin.php?page=sb2_seo_issues' ),
			'tools_url'          => admin_url( 'admin.php?page=sb2_tools' ),
			'ai_bots_url'        => admin_url( 'admin.php?page=sb2_ai_bots' ),
			'settings_url'       => admin_url( 'admin.php?page=sb2_settings' ),
		);
	}

	/**
	 * Load or refresh editor panel data for a post.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $refresh Run quick local analysis before building view.
	 * @return array<string, mixed>
	 */
	public static function get_post_view( $post_id, $refresh = false ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return self::build_view_from_analysis( null, SEO_Issues_Manager::get_sitewide_issues() );
		}

		$analysis = SEO_Issues_Manager::get_saved_analysis( $post_id, 'post' );

		if ( $refresh || empty( $analysis ) ) {
			if ( ! SEO_Issues_Manager::should_exclude_from_analysis( $post_id ) ) {
				$runner   = new SEO_Analysis( $post_id, 'post' );
				$analysis = $runner->analyze( false );
				if ( ! empty( $analysis ) ) {
					$saved = SEO_Issues_Manager::get_saved_analysis( $post_id, 'post' );
					if ( ! empty( $saved ) ) {
						$analysis = $saved;
					}
				}
			}
		}

		return self::build_view_from_analysis( $analysis, SEO_Issues_Manager::get_sitewide_issues() );
	}

	/**
	 * Backward-compatible alias for get_post_view().
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function score_post( $post_id ) {
		return self::get_post_view( $post_id, false );
	}

	/**
	 * Top actionable possibilities for the editor panel.
	 *
	 * @param array|null $analysis Analysis payload.
	 * @return array<int, array{key: string, message: string, severity: string}>
	 */
	private static function get_top_possibilities( $analysis ) {
		if ( empty( $analysis ) || ! is_array( $analysis ) ) {
			return array();
		}

		$items = array();
		foreach ( array( 'issues', 'opportunities', 'improvements' ) as $bucket ) {
			if ( empty( $analysis[ $bucket ] ) || ! is_array( $analysis[ $bucket ] ) ) {
				continue;
			}
			foreach ( $analysis[ $bucket ] as $item ) {
				if ( empty( $item['key'] ) || empty( $item['message'] ) ) {
					continue;
				}
				$items[] = array(
					'key'      => $item['key'],
					'message'  => $item['message'],
					'severity' => isset( $item['severity'] ) ? $item['severity'] : $bucket,
				);
			}
		}

		return array_slice( $items, 0, 5 );
	}

	/**
	 * @param array|null $analysis Analysis payload.
	 * @return string
	 */
	private static function get_analyzed_at( $analysis ) {
		if ( empty( $analysis ) ) {
			return '';
		}

		if ( ! empty( $analysis['db_metadata']['analyzed_at'] ) ) {
			return (string) $analysis['db_metadata']['analyzed_at'];
		}

		if ( ! empty( $analysis['metadata']['timestamp'] ) ) {
			return gmdate( 'Y-m-d H:i:s', (int) $analysis['metadata']['timestamp'] );
		}

		return '';
	}

	/**
	 * AJAX: return editor panel payload for a post.
	 *
	 * @return void
	 */
	public static function ajax_editor_panel() {
		check_ajax_referer( self::NONCE_ACTION, 'security' );

		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$refresh = ! empty( $_POST['refresh'] );

		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'seo-booster' ) ), 403 );
		}

		wp_send_json_success( self::get_post_view( $post_id, (bool) $refresh ) );
	}

	/**
	 * Render AI Readiness + site checks for the classic main metabox.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function render_classic_sections( $post_id ) {
		$data  = self::get_post_view( (int) $post_id );
		$grade = isset( $data['grade'] ) ? $data['grade'] : self::grade( 0 );
		?>
		<div id="sb-ai-readiness-section" class="sb-metabox-readiness-sections">
			<details class="sb-metabox-collapsible" open>
				<summary class="sb-metabox-collapsible-summary">
					<span class="sb-metabox-collapsible-title"><?php esc_html_e( 'AI Readiness', 'seo-booster' ); ?></span>
					<span class="sb-metabox-collapsible-meta sb-ai-readiness-summary-meta" style="color: <?php echo esc_attr( $grade['color'] ); ?>;">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: score points, 2: max points, 3: grade label */
								__( '%1$d / %2$d · %3$s', 'seo-booster' ),
								(int) ( $data['score'] ?? 0 ),
								(int) ( $data['max'] ?? 0 ),
								$grade['label']
							)
						);
						?>
					</span>
				</summary>
				<div class="sb-metabox-collapsible-body">
					<ul class="sb-ai-readiness-checklist" id="sb-ai-readiness-checklist">
						<?php self::render_checklist_items( $data['details'] ?? array() ); ?>
					</ul>
				</div>
			</details>

			<details class="sb-metabox-collapsible">
				<summary class="sb-metabox-collapsible-summary">
					<span class="sb-metabox-collapsible-title"><?php esc_html_e( 'Site checks', 'seo-booster' ); ?></span>
				</summary>
				<div class="sb-metabox-collapsible-body">
					<ul class="sb-ai-readiness-checklist" id="sb-ai-readiness-sitewide">
						<?php self::render_checklist_items( $data['sitewide'] ?? array(), false ); ?>
					</ul>
					<?php if ( ! empty( $data['issues_url'] ) ) : ?>
						<p class="description">
							<a href="<?php echo esc_url( $data['issues_url'] ); ?>"><?php esc_html_e( 'View all on SEO Possibilities', 'seo-booster' ); ?></a>
						</p>
					<?php endif; ?>
				</div>
			</details>
		</div>
		<?php
	}

	/**
	 * Output checklist rows for classic or JS refresh.
	 *
	 * @param array<int, array<string, mixed>> $items Checklist items.
	 * @param bool                             $show_points Show point values.
	 * @return void
	 */
	public static function render_checklist_items( $items, $show_points = true ) {
		if ( empty( $items ) ) {
			echo '<li class="is-unknown"><span class="sb-ai-readiness-status">—</span><span class="sb-ai-readiness-label">' . esc_html__( 'Not analyzed yet.', 'seo-booster' ) . '</span></li>';
			return;
		}

		foreach ( $items as $item ) {
			$key     = isset( $item['key'] ) ? $item['key'] : '';
			$label   = isset( $item['label'] ) ? $item['label'] : '';
			$pass    = ! empty( $item['pass'] );
			$unknown = ! empty( $item['unknown'] );
			$class   = $pass ? 'is-pass' : ( $unknown ? 'is-unknown' : 'is-fail' );
			$symbol  = $pass ? '✓' : ( $unknown ? '—' : '○' );
			?>
			<li class="<?php echo esc_attr( $class ); ?>" data-key="<?php echo esc_attr( $key ); ?>">
				<span class="sb-ai-readiness-status"><?php echo esc_html( $symbol ); ?></span>
				<span class="sb-ai-readiness-label"><?php echo esc_html( $label ); ?></span>
				<?php if ( $show_points && ! empty( $item['points'] ) ) : ?>
					<span class="sb-ai-readiness-points">+<?php echo esc_html( (string) (int) $item['points'] ); ?></span>
				<?php endif; ?>
			</li>
			<?php
		}
	}

	/**
	 * Localized strings for editor assets.
	 *
	 * @return array<string, string>
	 */
	private static function get_editor_strings() {
		return array(
			'title'              => __( 'SEO Booster', 'seo-booster' ),
			'seoScore'           => __( 'SEO Score', 'seo-booster' ),
			'aiReadiness'        => __( 'AI Readiness', 'seo-booster' ),
			'siteChecks'         => __( 'Site checks', 'seo-booster' ),
			'topPossibilities'   => __( 'Top possibilities', 'seo-booster' ),
			'quickLinks'         => __( 'Quick links', 'seo-booster' ),
			'refresh'            => __( 'Run quick review', 'seo-booster' ),
			'loading'            => __( 'Loading…', 'seo-booster' ),
			'error'              => __( 'Could not refresh.', 'seo-booster' ),
			'pass'               => __( 'Pass', 'seo-booster' ),
			'fail'               => __( 'Missing', 'seo-booster' ),
			'unknown'            => __( 'Not analyzed', 'seo-booster' ),
			'noAnalysis'         => __( 'Save draft and run a quick review to see SEO score and possibilities.', 'seo-booster' ),
			'viewAllIssues'      => __( 'View all on SEO Possibilities', 'seo-booster' ),
			'tools'              => __( 'Tools', 'seo-booster' ),
			'aiBots'             => __( 'AI Bots', 'seo-booster' ),
			'settings'           => __( 'Settings', 'seo-booster' ),
		);
	}

	/**
	 * Enqueue block editor sidebar assets.
	 *
	 * @return void
	 */
	public static function enqueue_block_editor_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->base !== 'post' ) {
			return;
		}

		wp_enqueue_style(
			'sb-editor-panel',
			SEOBOOSTER_PLUGINURL . 'css/sb-editor-panel.css',
			array(),
			Utils::get_plugin_version()
		);

		wp_enqueue_script(
			'sb-editor-panel',
			SEOBOOSTER_PLUGINURL . 'js/sb-editor-panel.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ),
			filemtime( SEOBOOSTER_PLUGINPATH . 'js/sb-editor-panel.js' ),
			true
		);

		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$view    = $post_id > 0 ? self::get_post_view( $post_id ) : array();

		wp_localize_script(
			'sb-editor-panel',
			'sbEditorPanelData',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( self::NONCE_ACTION ),
				'view'             => $view,
				'aiReadinessKeys'  => Ai_Readiness_Registry::get_issue_keys(),
				'strings'          => self::get_editor_strings(),
			)
		);
	}
}
