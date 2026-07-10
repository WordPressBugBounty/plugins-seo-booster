<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Columns
 *
 * Handles SEO admin columns for posts and terms.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */
class Admin_Columns {

	/**
	 * Initialize the admin columns functionality.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'add_seo_columns' ) );
		add_action( 'manage_posts_custom_column', array( __CLASS__, 'display_seo_column_content' ), 10, 2 );
		add_action( 'manage_pages_custom_column', array( __CLASS__, 'display_seo_column_content' ), 10, 2 );
		add_action( 'manage_edit-category_columns', array( __CLASS__, 'add_taxonomy_columns' ) );
		add_action( 'manage_category_custom_column', array( __CLASS__, 'display_taxonomy_column_content' ), 10, 3 );
		add_action( 'manage_edit-post_tag_columns', array( __CLASS__, 'add_taxonomy_columns' ) );
		add_action( 'manage_post_tag_custom_column', array( __CLASS__, 'display_taxonomy_column_content' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_styles' ) );
	}

	/**
	 * Add SEO columns to post types.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function add_seo_columns() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		foreach ( $post_types as $post_type ) {
			if ( $post_type->name === 'attachment' ) {
				continue;
			}

			add_filter( "manage_{$post_type->name}_posts_columns", array( __CLASS__, 'add_post_columns' ) );
		}
	}

	/**
	 * Add columns to post list table.
	 *
	 * @since 6.1.26
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public static function add_post_columns( $columns ) {
		$seo_columns = array(
			'seo_status' => __( 'SEO Status', 'seo-booster' ) . ' (SEO Booster)',
		);

		// Insert SEO column before the date column
		$date_index = array_search( 'date', array_keys( $columns ) );
		if ( $date_index !== false ) {
			$columns = array_merge(
				array_slice( $columns, 0, $date_index ),
				$seo_columns,
				array_slice( $columns, $date_index )
			);
		} else {
			$columns = array_merge( $columns, $seo_columns );
		}

		return $columns;
	}

	/**
	 * Add columns to taxonomy list table.
	 *
	 * @since 6.1.26
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public static function add_taxonomy_columns( $columns ) {
		$seo_columns = array(
			'seo_status' => __( 'SEO Status', 'seo-booster' ) . ' (SEO Booster)',
		);

		return array_merge( $columns, $seo_columns );
	}

	/**
	 * Display SEO column content for posts.
	 *
	 * @since 6.1.26
	 * @param string $column_name Column name.
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function display_seo_column_content( $column_name, $post_id ) {
		if ( $column_name !== 'seo_status' ) {
			return;
		}

		// Exclude private posts and WooCommerce special pages
		if ( \Cleverplugins\SEOBooster\SEO_Issues_Manager::should_exclude_from_analysis( $post_id ) ) {
			echo '<span class="sb-seo-default">— EXCLUDED</span>';
			return;
		}

		// Get SEO analysis score if available
		$analysis_results = \Cleverplugins\SEOBooster\SEO_Analysis::get_saved_analysis( $post_id, 'post' );
		$seo_score        = null;

		// Check if score exists and is not null (score can be 0, which is valid)
		if ( $analysis_results && array_key_exists( 'score', $analysis_results ) && $analysis_results['score'] !== null ) {
			$seo_score = (int) $analysis_results['score'];
		}

		$status_indicators = array();

		// Add SEO analysis score if available
		if ( $seo_score !== null ) {
			$score_class = 'sb-seo-score';
			if ( $seo_score >= 80 ) {
				$score_class .= ' sb-seo-score-good';
			} elseif ( $seo_score >= 60 ) {
				$score_class .= ' sb-seo-score-medium';
			} else {
				$score_class .= ' sb-seo-score-poor';
			}

			$status_indicators[] = '<span class="' . esc_attr( $score_class ) . '" title="' . esc_attr__( 'SEO Analysis Score', 'seo-booster' ) . '">📊 ' . esc_html( $seo_score ) . '%</span>';
		}

		if ( empty( $status_indicators ) ) {
			echo '<span class="sb-seo-default">—</span>';
		} else {
			echo implode( ' ', $status_indicators );
		}
	}

	/**
	 * Display SEO column content for taxonomies.
	 *
	 * @since 6.1.26
	 * @param string $content Column content.
	 * @param string $column_name Column name.
	 * @param int $term_id Term ID.
	 * @return string Modified content.
	 */
	public static function display_taxonomy_column_content( $content, $column_name, $term_id ) {
		if ( $column_name !== 'seo_status' ) {
			return $content;
		}

		// Get SEO analysis score if available
		$analysis_results = \Cleverplugins\SEOBooster\SEO_Analysis::get_saved_analysis( $term_id, 'term' );
		$seo_score        = null;
		// Check if score exists and is not null (score can be 0, which is valid)
		if ( $analysis_results && array_key_exists( 'score', $analysis_results ) && $analysis_results['score'] !== null ) {
			$seo_score = (int) $analysis_results['score'];
		}

		$status_indicators = array();

		// Add noindex indicator
		if ( ! empty( $seo_data['noindex'] ) ) {
			$status_indicators[] = '<span class="sb-seo-noindex" title="' . esc_attr__( 'Noindexed', 'seo-booster' ) . '">🚫</span>';
		}

		// Add SEO analysis score if available
		if ( $seo_score !== null ) {
			$score_class = 'sb-seo-score';
			if ( $seo_score >= 80 ) {
				$score_class .= ' sb-seo-score-good';
			} elseif ( $seo_score >= 60 ) {
				$score_class .= ' sb-seo-score-medium';
			} else {
				$score_class .= ' sb-seo-score-poor';
			}

			$status_indicators[] = '<span class="' . esc_attr( $score_class ) . '" title="' . esc_attr__( 'SEO Analysis Score', 'seo-booster' ) . '">📊 ' . esc_html( $seo_score ) . '%</span>';
		}

		if ( empty( $status_indicators ) ) {
			return '<span class="sb-seo-default">—</span>';
		} else {
			return implode( ' ', $status_indicators );
		}
	}

	/**
	 * Enqueue admin styles for SEO columns.
	 *
	 * @since 6.1.26
	 * @return void
	 */
	public static function enqueue_admin_styles() {
		// Only enqueue on post list pages
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, array( 'edit', 'edit-tags' ) ) ) {
			return;
		}

		// Add inline CSS for admin columns
		$css = '
        .sb-seo-status-column {
            text-align: center;
        }
        
        .sb-seo-status-column span {
            display: inline-block;
            margin: 0 2px;
            font-size: 16px;
            line-height: 1;
        }
        
        
        .sb-seo-score {
            font-weight: bold;
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 12px;
        }
        
        .sb-seo-score-good {
            background-color: #d4edda;
            color: #155724;
        }
        
        .sb-seo-score-medium {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .sb-seo-score-poor {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .sb-seo-default {
            color: #6c757d;
        }
        ';

		wp_add_inline_style( 'wp-admin', $css );
	}
}
