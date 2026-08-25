<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page utility functions
 *
 * This class contains helper functions for the settings page,
 * including form rendering, validation, and data processing.
 */
class Settings_Utils {



	/**
	 * Render separator options for title templates
	 *
	 * @param string $current_separator Currently selected separator
	 * @return string HTML output
	 */
	public static function render_separator_options( $current_separator = '|' ) {
		$separators = array( '-', '–', '—', ':', '·', '•', '*', '⋆', '|', '~', '«', '»', '>', '<' );

		$html = '<div class="sb-seo-separator-options">';

		foreach ( $separators as $sep ) {
			$checked = ( $sep === $current_separator ) ? 'checked=""' : '';
			$html   .= '<label class="sb-seo-separator-option">';
			$html   .= '<input type="radio" name="seobooster_global_title_separator" value="' . esc_attr( $sep ) . '" ' . $checked . '>';
			$html   .= '<span class="sb-seo-separator-preview">' . esc_html( $sep ) . '</span>';
			$html   .= '</label>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render image upload container
	 *
	 * @param string $field_name The field name for the image
	 * @param string $current_value Current image ID or URL
	 * @param string $description Description text for the field
	 * @return string HTML output
	 */
	public static function render_image_upload_container( $field_name, $current_value = '', $description = '' ) {
		$html  = '<div class="sb-seo-image-upload">';
		$html .= '<input type="hidden" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $current_value ) . '">';

		if ( $current_value ) {
			$image_url = wp_get_attachment_url( $current_value );
			if ( $image_url ) {
				$html .= '<div class="sb-seo-image-preview" style="margin-bottom: 10px;">';
				$html .= '<img src="' . esc_url( $image_url ) . '" style="max-width: 200px; height: auto;">';
				$html .= '</div>';
			}
		} else {
			$html .= '<div class="sb-seo-image-preview" style="margin-bottom: 10px;">';
			$html .= '<div class="sb-no-image" style="width: 200px; height: 120px; border: 2px dashed #ccc; display: flex; align-items: center; justify-content: center; color: #666;">';
			$html .= 'No image selected';
			$html .= '</div>';
			$html .= '</div>';
		}

		$html .= '<button type="button" class="button sb-upload-image-btn" data-target="' . esc_attr( $field_name ) . '">';
		$html .= 'Select Image';
		$html .= '</button>';

		if ( $current_value ) {
			$html .= '<button type="button" class="button sb-remove-image-btn" data-target="' . esc_attr( $field_name ) . '" style="margin-left: 5px;">';
			$html .= 'Remove Image';
			$html .= '</button>';
		}

		if ( $description ) {
			$html .= '<p class="description">' . esc_html( $description ) . '</p>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render field with variable inserter button
	 *
	 * @param string $field_name The field name
	 * @param string $field_type The field type (title, description, etc.)
	 * @param string $current_value Current field value
	 * @param string $placeholder Placeholder text
	 * @param string $field_type_input Input type (text, textarea)
	 * @param int $rows Number of rows for textarea
	 * @return string HTML output
	 */
	public static function render_field_with_inserter( $field_name, $field_type, $current_value = '', $placeholder = '', $field_type_input = 'text', $rows = 3 ) {
		$html = '<div class="sb-seo-field-with-inserter">';

		if ( 'textarea' === $field_type_input ) {
			$html .= '<textarea id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '" rows="' . esc_attr( $rows ) . '" cols="50" class="large-text" placeholder="' . esc_attr( $placeholder ) . '">' . esc_textarea( $current_value ) . '</textarea>';
		} else {
			$html .= '<input type="text" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $current_value ) . '" class="regular-text" placeholder="' . esc_attr( $placeholder ) . '">';
		}

		$html .= '<button type="button" class="button sb-variable-inserter-btn" data-target="' . esc_attr( $field_name ) . '" data-type="' . esc_attr( $field_type ) . '">';
		$html .= 'Insert Variable';
		$html .= '</button>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render content type settings
	 *
	 * @param string $type Content type (post, page, etc.)
	 * @param object $post_type Post type object
	 * @param array $noindex_types Array of noindex post types
	 * @return string HTML output
	 */
	public static function render_content_type_settings( $type, $post_type, $noindex_types ) {
		$is_noindex = in_array( $type, $noindex_types );

		$html  = '<div class="sb-content-type-item">';
		$html .= '<div class="sb-content-type-header" data-target="post-type-' . esc_attr( $type ) . '">';
		$html .= '<div class="sb-content-type-info">';
		$html .= '<h4>' . esc_html( $post_type->label ) . '</h4>';
		$html .= '<span class="sb-content-type-status ' . ( $is_noindex ? 'noindex' : 'index' ) . '">';
		$html .= $is_noindex ? 'No-index' : 'Index';
		$html .= '</span>';
		$html .= '</div>';
		$html .= '<span class="sb-content-type-toggle dashicons dashicons-arrow-down-alt2"></span>';
		$html .= '</div>';

		$html .= '<div class="sb-content-type-content" id="post-type-' . esc_attr( $type ) . '">';
		$html .= '<table class="form-table">';
		$html .= '<tbody>';

		// Search Engine Visibility
		$html .= '<tr valign="top">';
		$html .= '<th scope="row">Search Engine Visibility</th>';
		$html .= '<td>';
		$html .= '<label>';
		$html .= '<input type="checkbox" name="seobooster_seo_default_noindex_post_types[]" value="' . esc_attr( $type ) . '"' . ( $is_noindex ? ' checked' : '' ) . '>';
		$html .= 'No-index this post type by default';
		$html .= '</label>';
		$html .= '<p class="description">When enabled, posts of this type will not be indexed by search engines.</p>';
		$html .= '</td>';
		$html .= '</tr>';

		// Title Template
		$html          .= '<tr valign="top">';
		$html          .= '<th scope="row">Title Template</th>';
		$html          .= '<td>';
		$title_template = get_option( 'seobooster_ct_' . $type . '_title_template', '' );
		$html          .= self::render_field_with_inserter( 'seobooster_ct_' . $type . '_title_template', 'title', $title_template, '{title} {separator} {site_name}' );
		$html          .= '<p class="description">Available variables: {title}, {site_name}, {separator}, {description}, {excerpt}, {excerpt_only}</p>';
		$html          .= '</td>';
		$html          .= '</tr>';

		// Description Template
		$html                .= '<tr valign="top">';
		$html                .= '<th scope="row">Description Template</th>';
		$html                .= '<td>';
		$description_template = get_option( 'seobooster_ct_' . $type . '_description_template', '' );
		$html                .= self::render_field_with_inserter( 'seobooster_ct_' . $type . '_description_template', 'description', $description_template, 'Enter default meta description...', 'textarea' );
		$html                .= '<p class="description">Available variables: {title}, {site_name}, {description}, {excerpt}, {excerpt_only}</p>';
		$html                .= '</td>';
		$html                .= '</tr>';

		$html .= '</tbody>';
		$html .= '</table>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render taxonomy settings
	 *
	 * @param string $type Taxonomy type (category, post_tag, etc.)
	 * @param object $taxonomy Taxonomy object
	 * @param array $noindex_taxonomies Array of noindex taxonomies
	 * @return string HTML output
	 */
	public static function render_taxonomy_settings( $type, $taxonomy, $noindex_taxonomies ) {
		$is_noindex = in_array( $type, $noindex_taxonomies );

		$html  = '<div class="sb-content-type-item">';
		$html .= '<div class="sb-content-type-header" data-target="taxonomy-' . esc_attr( $type ) . '">';
		$html .= '<div class="sb-content-type-info">';
		$html .= '<h4>' . esc_html( $taxonomy->label ) . '</h4>';
		$html .= '<span class="sb-content-type-status ' . ( $is_noindex ? 'noindex' : 'index' ) . '">';
		$html .= $is_noindex ? 'No-index' : 'Index';
		$html .= '</span>';
		$html .= '</div>';
		$html .= '<span class="sb-content-type-toggle dashicons dashicons-arrow-down-alt2"></span>';
		$html .= '</div>';

		$html .= '<div class="sb-content-type-content" id="taxonomy-' . esc_attr( $type ) . '">';
		$html .= '<table class="form-table">';
		$html .= '<tbody>';

		// Search Engine Visibility
		$html .= '<tr valign="top">';
		$html .= '<th scope="row">Search Engine Visibility</th>';
		$html .= '<td>';
		$html .= '<label>';
		$html .= '<input type="checkbox" name="seobooster_seo_default_noindex_taxonomies[]" value="' . esc_attr( $type ) . '"' . ( $is_noindex ? ' checked' : '' ) . '>';
		$html .= 'No-index this taxonomy by default';
		$html .= '</label>';
		$html .= '<p class="description">When enabled, terms of this taxonomy will not be indexed by search engines.</p>';
		$html .= '</td>';
		$html .= '</tr>';

		// Title Template
		$html          .= '<tr valign="top">';
		$html          .= '<th scope="row">Title Template</th>';
		$html          .= '<td>';
		$title_template = get_option( 'seobooster_tax_' . $type . '_title_template', '' );
		$html          .= self::render_field_with_inserter( 'seobooster_tax_' . $type . '_title_template', 'title', $title_template, '{title} {separator} {site_name}' );
		$html          .= '<p class="description">Available variables: {title}, {site_name}, {separator}, {description}, {excerpt}, {excerpt_only}</p>';
		$html          .= '</td>';
		$html          .= '</tr>';

		// Description Template
		$html                .= '<tr valign="top">';
		$html                .= '<th scope="row">Description Template</th>';
		$html                .= '<td>';
		$description_template = get_option( 'seobooster_tax_' . $type . '_description_template', '' );
		$html                .= self::render_field_with_inserter( 'seobooster_tax_' . $type . '_description_template', 'description', $description_template, 'Enter default meta description...', 'textarea' );
		$html                .= '<p class="description">Available variables: {title}, {site_name}, {description}, {excerpt}, {excerpt_only}</p>';
		$html                .= '</td>';
		$html                .= '</tr>';

		$html .= '</tbody>';
		$html .= '</table>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render toggle switch
	 *
	 * @param string $field_name The field name
	 * @param string $label The label text
	 * @param bool $checked Whether the toggle is checked
	 * @param string $description Description text
	 * @return string HTML output
	 */
	public static function render_toggle_switch( $field_name, $label, $checked = false, $description = '' ) {
		$html  = '<label class="sb-toggle-label">';
		$html .= '<div class="sb-toggle-switch">';
		$html .= '<input type="checkbox" name="' . esc_attr( $field_name ) . '" value="1"' . ( $checked ? ' checked' : '' ) . '>';
		$html .= '<span class="sb-toggle-slider"></span>';
		$html .= '</div>';
		$html .= '<span class="sb-toggle-text">' . esc_html( $label ) . '</span>';
		$html .= '</label>';

		if ( $description ) {
			$html .= '<p class="description">' . esc_html( $description ) . '</p>';
		}

		return $html;
	}
}
