<?php

namespace Cleverplugins\SEOBooster;

use Cleverplugins\SEOBooster\SEO_Plugins\Aioseo_Adapter;
use Cleverplugins\SEOBooster\SEO_Plugins\RankMath_Adapter;
use Cleverplugins\SEOBooster\SEO_Plugins\SEO_Plugin_Adapter_Interface;
use Cleverplugins\SEOBooster\SEO_Plugins\Seopress_Adapter;
use Cleverplugins\SEOBooster\SEO_Plugins\Tsf_Adapter;
use Cleverplugins\SEOBooster\SEO_Plugins\Yoast_Adapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central registry for third-party SEO plugin adapters.
 *
 * @package Cleverplugins\SEOBooster
 * @since 7.2.5
 */
class SEO_Plugin_Registry {

	/** @var SEO_Plugin_Adapter_Interface[]|null */
	private static $adapters = null;

	/** @var SEO_Plugin_Adapter_Interface|null */
	private static $active_adapter = null;

	/** @var bool */
	private static $active_resolved = false;

	/**
	 * @return SEO_Plugin_Adapter_Interface[]
	 */
	public static function get_adapters() {
		if ( null === self::$adapters ) {
			self::$adapters = array(
				new Yoast_Adapter(),
				new RankMath_Adapter(),
				new Seopress_Adapter(),
				new Aioseo_Adapter(),
				new Tsf_Adapter(),
			);
		}

		return self::$adapters;
	}

	/**
	 * @return SEO_Plugin_Adapter_Interface|null
	 */
	public static function get_active_adapter() {
		if ( ! self::$active_resolved ) {
			self::$active_resolved = true;
			foreach ( self::get_adapters() as $adapter ) {
				if ( $adapter->is_active() ) {
					self::$active_adapter = $adapter;
					break;
				}
			}
		}

		return self::$active_adapter;
	}

	/**
	 * Reset cached active adapter (for tests).
	 *
	 * @return void
	 */
	public static function reset_cache() {
		self::$active_adapter  = null;
		self::$active_resolved = false;
	}

	/**
	 * Register admin hooks for SEO plugin notices.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_multi_plugin_notice' ) );
		add_action( 'wp_ajax_sb_dismiss_multi_seo_notice', array( __CLASS__, 'ajax_dismiss_multi_seo_notice' ) );
	}

	/**
	 * All active adapters in priority order.
	 *
	 * @return SEO_Plugin_Adapter_Interface[]
	 */
	public static function get_active_adapters() {
		$active = array();
		foreach ( self::get_adapters() as $adapter ) {
			if ( $adapter->is_active() ) {
				$active[] = $adapter;
			}
		}

		return $active;
	}

	/**
	 * @return bool
	 */
	public static function has_multiple_active_plugins() {
		return count( self::get_active_adapters() ) > 1;
	}

	/**
	 * @return string full|partial|analysis_only
	 */
	public static function get_integration_tier() {
		$adapter = self::get_active_adapter();
		if ( ! $adapter ) {
			return 'analysis_only';
		}

		if ( ! $adapter->supports_bulk_write() ) {
			return 'analysis_only';
		}

		if ( ! $adapter->supports_focus_keyword() ) {
			return 'partial';
		}

		return 'full';
	}

	/**
	 * Labels of all active SEO plugins.
	 *
	 * @return string[]
	 */
	public static function get_active_labels() {
		$labels = array();
		foreach ( self::get_active_adapters() as $adapter ) {
			$labels[] = $adapter->get_label();
		}

		return $labels;
	}

	/**
	 * Dismissible notice when multiple SEO plugins are active.
	 *
	 * @return void
	 */
	public static function maybe_show_multi_plugin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! self::has_multiple_active_plugins() ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( get_user_meta( $user_id, 'sb_dismissed_multi_seo_notice', true ) ) {
			return;
		}

		if ( ! self::is_plugin_admin_screen() ) {
			return;
		}

		$active_label = self::get_active_label();
		$all_labels   = implode( ', ', self::get_active_labels() );
		?>
		<div class="notice notice-warning is-dismissible sb-multi-seo-notice" data-notice="multi-seo">
			<p>
				<?php
				printf(
					/* translators: 1: comma-separated plugin names, 2: active plugin name used for writes */
					esc_html__( 'Multiple SEO plugins are active (%1$s). SEO Booster writes to %2$s based on priority order. Deactivate unused plugins to avoid confusion.', 'seo-booster' ),
					esc_html( $all_labels ),
					'<strong>' . esc_html( $active_label ) . '</strong>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * @return bool
	 */
	private static function is_plugin_admin_screen() {
		if ( ! is_admin() ) {
			return false;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( strpos( $page, 'sb2_' ) === 0 ) {
			return true;
		}

		global $pagenow;
		return in_array( $pagenow, array( 'post.php', 'post-new.php', 'term.php', 'edit-tags.php' ), true );
	}

	/**
	 * Persist dismiss flag for multi-plugin notice.
	 *
	 * @return void
	 */
	public static function ajax_dismiss_multi_seo_notice() {
		check_ajax_referer( 'sb_seo_compat_notice', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		update_user_meta( get_current_user_id(), 'sb_dismissed_multi_seo_notice', '1' );
		wp_send_json_success();
	}

	/**
	 * @return string|null
	 */
	public static function get_active_slug() {
		$adapter = self::get_active_adapter();

		return $adapter ? $adapter->get_slug() : null;
	}

	/**
	 * @return string
	 */
	public static function get_active_label() {
		$adapter = self::get_active_adapter();

		return $adapter ? $adapter->get_label() : '';
	}

	/**
	 * @return bool
	 */
	public static function supports_bulk_write() {
		$adapter = self::get_active_adapter();

		return $adapter ? $adapter->supports_bulk_write() : false;
	}

	/**
	 * @return bool
	 */
	public static function supports_focus_keyword() {
		$adapter = self::get_active_adapter();

		return $adapter ? $adapter->supports_focus_keyword() : false;
	}

	/**
	 * @return string[]
	 */
	public static function get_supported_labels() {
		$labels = array();
		foreach ( self::get_adapters() as $adapter ) {
			$labels[] = $adapter->get_label();
		}

		return $labels;
	}

	/**
	 * Human-readable list of supported SEO plugins.
	 *
	 * @return string
	 */
	public static function get_supported_plugins_list() {
		return self::format_plugin_list( self::get_supported_labels() );
	}

	/**
	 * @return string
	 */
	public static function get_focus_keyword_requirement_message() {
		if ( self::get_active_adapter() && ! self::supports_focus_keyword() ) {
			return sprintf(
				/* translators: %s: active SEO plugin name */
				__( '%s does not support focus keyword bulk updates through SEO Booster.', 'seo-booster' ),
				self::get_active_label()
			);
		}

		return self::get_bulk_write_requirement_message();
	}

	/**
	 * @return string
	 */
	public static function get_bulk_write_requirement_message() {
		return sprintf(
			/* translators: %s: comma-separated list of supported SEO plugin names */
			__( 'Install %s to use this tool.', 'seo-booster' ),
			self::get_supported_plugins_list()
		);
	}

	/**
	 * @return string
	 */
	public static function get_no_compatible_plugin_message() {
		return sprintf(
			/* translators: %s: comma-separated list of supported SEO plugin names */
			__( 'No compatible SEO plugin detected. Please install %s.', 'seo-booster' ),
			self::get_supported_plugins_list()
		);
	}

	/**
	 * Legacy shape for SEO_Plugin_Integration and AJAX.
	 *
	 * @return array{plugin: string|null, name: string|null, fields: array<string, string>}
	 */
	public static function get_active_seo_plugin_info() {
		$adapter = self::get_active_adapter();
		if ( ! $adapter ) {
			return array(
				'plugin' => null,
				'name'   => null,
				'fields' => array(),
			);
		}

		return array(
			'plugin' => $adapter->get_slug(),
			'name'   => $adapter->get_label(),
			'fields' => $adapter->get_editor_field_selectors(),
		);
	}

	/**
	 * Legacy shape for Google_API::identify_active_seo_plugin().
	 *
	 * @return array{name: string, file: string}|null
	 */
	public static function identify_active_seo_plugin() {
		$adapter = self::get_active_adapter();
		if ( ! $adapter ) {
			return null;
		}

		return array(
			'name' => $adapter->get_label(),
			'file' => $adapter->get_plugin_file(),
		);
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array{title: string, description: string}
	 */
	public static function read_post_seo( $post_id ) {
		$adapter = self::get_active_adapter();
		if ( ! $adapter ) {
			return array(
				'title'       => '',
				'description' => '',
			);
		}

		return $adapter->read_post_seo( $post_id );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function read_focus_keywords( $post_id ) {
		$adapter = self::get_active_adapter();
		if ( ! $adapter ) {
			return array();
		}

		return $adapter->read_focus_keywords( $post_id );
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $title   Title.
	 * @return void
	 */
	public static function write_post_title( $post_id, $title ) {
		$adapter = self::get_active_adapter();
		if ( $adapter ) {
			$adapter->write_post_title( $post_id, $title );
		}
	}

	/**
	 * @param int    $post_id     Post ID.
	 * @param string $description Description.
	 * @return void
	 */
	public static function write_post_description( $post_id, $description ) {
		$adapter = self::get_active_adapter();
		if ( $adapter ) {
			$adapter->write_post_description( $post_id, $description );
		}
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $keyword Keyword.
	 * @return void
	 */
	public static function write_focus_keyword( $post_id, $keyword ) {
		$adapter = self::get_active_adapter();
		if ( $adapter && $adapter->supports_focus_keyword() ) {
			$adapter->write_focus_keyword( $post_id, $keyword );
		}
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $keyword Keyword (empty clears).
	 * @return void
	 */
	public static function restore_focus_keyword( $post_id, $keyword ) {
		$adapter = self::get_active_adapter();
		if ( ! $adapter || ! $adapter->supports_focus_keyword() ) {
			return;
		}

		if ( trim( (string) $keyword ) === '' ) {
			$adapter->delete_focus_keyword( $post_id );
			return;
		}

		$adapter->write_focus_keyword( $post_id, $keyword );
	}

	/**
	 * @param string $field_type title|description|both
	 * @return array{title_key?: string, description_key?: string}
	 */
	public static function get_meta_keys( $field_type = 'both' ) {
		$adapter = self::get_active_adapter();
		if ( ! $adapter ) {
			return array();
		}

		return $adapter->get_meta_keys( $field_type );
	}

	/**
	 * @return string|null
	 */
	public static function get_focus_keyword_meta_key() {
		$adapter = self::get_active_adapter();
		if ( ! $adapter || ! $adapter->supports_focus_keyword() ) {
			return null;
		}

		return $adapter->get_focus_keyword_meta_key();
	}

	/**
	 * @param string $field      title|description
	 * @param string $value      Value.
	 * @param int    $exclude_id Exclude post ID.
	 * @return object[]
	 */
	public static function find_duplicate_posts( $field, $value, $exclude_id ) {
		$adapter = self::get_active_adapter();
		if ( ! $adapter ) {
			return array();
		}

		return $adapter->find_duplicate_posts( $field, $value, $exclude_id );
	}

	/**
	 * @param int $term_id Term ID.
	 * @return array{title: string, description: string}
	 */
	public static function read_term_seo( $term_id ) {
		$adapter = self::get_active_adapter();
		if ( ! $adapter ) {
			return array(
				'title'       => '',
				'description' => '',
			);
		}

		return $adapter->read_term_seo( $term_id );
	}

	/**
	 * @param int $term_id Term ID.
	 * @return string[]
	 */
	public static function read_focus_keywords_for_term( $term_id ) {
		$adapter = self::get_active_adapter();
		if ( ! $adapter ) {
			return array();
		}

		return $adapter->read_focus_keywords_for_term( $term_id );
	}

	/**
	 * @param int    $term_id Term ID.
	 * @param string $title   Title.
	 * @return void
	 */
	public static function write_term_title( $term_id, $title ) {
		$adapter = self::get_active_adapter();
		if ( $adapter ) {
			$adapter->write_term_title( $term_id, $title );
		}
	}

	/**
	 * @param int    $term_id     Term ID.
	 * @param string $description Description.
	 * @return void
	 */
	public static function write_term_description( $term_id, $description ) {
		$adapter = self::get_active_adapter();
		if ( $adapter ) {
			$adapter->write_term_description( $term_id, $description );
		}
	}

	/**
	 * @param int    $term_id           Term ID.
	 * @param string $keyword           Keyword.
	 * @param bool   $append_if_missing Append when missing.
	 * @return void
	 */
	public static function write_focus_keyword_for_term( $term_id, $keyword, $append_if_missing = false ) {
		$adapter = self::get_active_adapter();
		if ( $adapter && $adapter->supports_focus_keyword() ) {
			$adapter->write_focus_keyword_for_term( $term_id, $keyword, $append_if_missing );
		}
	}

	/**
	 * @param int    $object_id   Object ID.
	 * @param string $object_type post|term.
	 * @param string $field       title|description|focus_keyword.
	 * @param string $value       Value.
	 * @param bool   $append_focus_keyword Append focus keyword if missing (editor Use).
	 * @return bool
	 */
	public static function write_seo_field( $object_id, $object_type, $field, $value, $append_focus_keyword = false ) {
		$adapter = self::get_active_adapter();
		if ( ! $adapter || ! $object_id ) {
			return false;
		}

		if ( $object_type === 'term' ) {
			if ( $field === 'title' ) {
				$adapter->write_term_title( $object_id, $value );
				return true;
			}
			if ( $field === 'description' ) {
				$adapter->write_term_description( $object_id, $value );
				return true;
			}
			if ( $field === 'focus_keyword' && $adapter->supports_focus_keyword() ) {
				$adapter->write_focus_keyword_for_term( $object_id, $value, $append_focus_keyword );
				return true;
			}

			return false;
		}

		if ( $field === 'title' ) {
			$adapter->write_post_title( $object_id, $value );
			return true;
		}
		if ( $field === 'description' ) {
			$adapter->write_post_description( $object_id, $value );
			return true;
		}
		if ( $field === 'focus_keyword' && $adapter->supports_focus_keyword() ) {
			$adapter->write_focus_keyword( $object_id, $value, $append_focus_keyword );
			return true;
		}

		return false;
	}

	/**
	 * @param string $field      title|description
	 * @param string $value      Value.
	 * @param int    $exclude_id Exclude term ID.
	 * @return object[]
	 */
	public static function find_duplicate_terms( $field, $value, $exclude_id ) {
		$adapter = self::get_active_adapter();
		if ( ! $adapter ) {
			return array();
		}

		return $adapter->find_duplicate_terms( $field, $value, $exclude_id );
	}

	/**
	 * @param string[] $labels Plugin labels.
	 * @return string
	 */
	private static function format_plugin_list( array $labels ) {
		$labels = array_values( array_filter( $labels ) );
		$count  = count( $labels );

		if ( $count === 0 ) {
			return '';
		}

		if ( $count === 1 ) {
			return $labels[0];
		}

		if ( $count === 2 ) {
			return $labels[0] . ' ' . __( 'or', 'seo-booster' ) . ' ' . $labels[1];
		}

		$last = array_pop( $labels );

		return implode( ', ', $labels ) . ', ' . __( 'or', 'seo-booster' ) . ' ' . $last;
	}
}
