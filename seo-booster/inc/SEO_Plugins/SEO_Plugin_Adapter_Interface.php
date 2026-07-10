<?php

namespace Cleverplugins\SEOBooster\SEO_Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for third-party SEO plugin adapters.
 *
 * @package Cleverplugins\SEOBooster\SEO_Plugins
 * @since 7.2.5
 */
interface SEO_Plugin_Adapter_Interface {

	/**
	 * Short slug (yoast, rankmath, aioseo, seopress, seoframework).
	 *
	 * @return string
	 */
	public function get_slug();

	/**
	 * Human-readable plugin name.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * WordPress plugin bootstrap file relative to plugins directory.
	 *
	 * @return string
	 */
	public function get_plugin_file();

	/**
	 * Whether this SEO plugin is active on the site.
	 *
	 * @return bool
	 */
	public function is_active();

	/**
	 * Whether bulk meta write is supported.
	 *
	 * @return bool
	 */
	public function supports_bulk_write();

	/**
	 * Whether focus keyword read/write is supported.
	 *
	 * @return bool
	 */
	public function supports_focus_keyword();

	/**
	 * Read SEO title and description for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{title: string, description: string}
	 */
	public function read_post_seo( $post_id );

	/**
	 * Write SEO title for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $title   Title value.
	 * @return void
	 */
	public function write_post_title( $post_id, $title );

	/**
	 * Write meta description for a post.
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $description Description value.
	 * @return void
	 */
	public function write_post_description( $post_id, $description );

	/**
	 * Read focus keywords for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public function read_focus_keywords( $post_id );

	/**
	 * Write focus keyword for a post.
	 *
	 * @param int    $post_id           Post ID.
	 * @param string $keyword           Keyword value.
	 * @param bool   $append_if_missing When true, prepend keyword if missing (multi-value plugins).
	 * @return void
	 */
	public function write_focus_keyword( $post_id, $keyword, $append_if_missing = false );

	/**
	 * Clear focus keyword for a post (revert support).
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function delete_focus_keyword( $post_id );

	/**
	 * Read SEO title and description for a term.
	 *
	 * @param int $term_id Term ID.
	 * @return array{title: string, description: string}
	 */
	public function read_term_seo( $term_id );

	/**
	 * Write SEO title for a term.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $title   Title value.
	 * @return void
	 */
	public function write_term_title( $term_id, $title );

	/**
	 * Write meta description for a term.
	 *
	 * @param int    $term_id     Term ID.
	 * @param string $description Description value.
	 * @return void
	 */
	public function write_term_description( $term_id, $description );

	/**
	 * Read focus keywords for a term.
	 *
	 * @param int $term_id Term ID.
	 * @return string[]
	 */
	public function read_focus_keywords_for_term( $term_id );

	/**
	 * Write focus keyword for a term.
	 *
	 * @param int    $term_id           Term ID.
	 * @param string $keyword           Keyword value.
	 * @param bool   $append_if_missing When true, prepend keyword if missing (multi-value plugins).
	 * @return void
	 */
	public function write_focus_keyword_for_term( $term_id, $keyword, $append_if_missing = false );

	/**
	 * Clear focus keyword for a term (revert support).
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function delete_focus_keyword_for_term( $term_id );

	/**
	 * Post meta keys for duplicate scans (postmeta-backed plugins).
	 *
	 * @param string $field_type title|description|both
	 * @return array{title_key?: string, description_key?: string}
	 */
	public function get_meta_keys( $field_type = 'both' );

	/**
	 * Term meta keys for duplicate scans (termmeta-backed plugins).
	 *
	 * @param string $field_type title|description|both
	 * @return array{title_key?: string, description_key?: string}
	 */
	public function get_term_meta_keys( $field_type = 'both' );

	/**
	 * Focus keyword post meta key, if stored in postmeta.
	 *
	 * @return string|null
	 */
	public function get_focus_keyword_meta_key();

	/**
	 * Focus keyword term meta key, if stored in termmeta.
	 *
	 * @return string|null
	 */
	public function get_focus_keyword_term_meta_key();

	/**
	 * CSS selectors for editor field apply (title, description, focus_keyword).
	 *
	 * @return array<string, string>
	 */
	public function get_editor_field_selectors();

	/**
	 * Find posts with duplicate SEO title or description.
	 *
	 * @param string $field      title|description
	 * @param string $value      Value to match.
	 * @param int    $exclude_id Post ID to exclude.
	 * @return object[] Rows with ID, post_title, type properties.
	 */
	public function find_duplicate_posts( $field, $value, $exclude_id );

	/**
	 * Find terms with duplicate SEO title or description.
	 *
	 * @param string $field      title|description
	 * @param string $value      Value to match.
	 * @param int    $exclude_id Term ID to exclude.
	 * @return object[] Rows with term_id, name, taxonomy, type properties.
	 */
	public function find_duplicate_terms( $field, $value, $exclude_id );
}
