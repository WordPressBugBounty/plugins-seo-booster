<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SEO_Plugin_Integration
 *
 * Handles integration with other SEO plugins for AI suggestions.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */
class SEO_Plugin_Integration {

	/**
	 * Detect active SEO plugin and return field mappings.
	 *
	 * @since 6.1.26
	 * @return array Plugin information and field mappings.
	 */
	public static function get_active_seo_plugin() {
		return SEO_Plugin_Registry::get_active_seo_plugin_info();
	}

	/**
	 * Apply AI suggestion to the appropriate field in the active SEO plugin.
	 *
	 * @since 6.1.26
	 * @param string $field_type        The type of field (title, description, focus_keyword).
	 * @param string $value             The value to apply.
	 * @param int    $object_id         Post or term ID (0 = UI-only).
	 * @param string $object_type       post|term.
	 * @param bool   $append_focus_keyword Prepend focus keyword when supported.
	 * @return array Result of the operation.
	 */
	public static function apply_suggestion( $field_type, $value, $object_id = 0, $object_type = 'post', $append_focus_keyword = false ) {
		$plugin_info = self::get_active_seo_plugin();

		if ( ! $plugin_info['plugin'] ) {
			return array(
				'success' => false,
				'message' => SEO_Plugin_Registry::get_no_compatible_plugin_message(),
			);
		}

		$field_selector = $plugin_info['fields'][ $field_type ] ?? null;

		if ( ! $field_selector && 'focus_keyword' === $field_type && ! SEO_Plugin_Registry::supports_focus_keyword() ) {
			return array(
				'success' => false,
				'message' => SEO_Plugin_Registry::get_focus_keyword_requirement_message(),
			);
		}

		if ( ! $field_selector && 'focus_keyword' !== $field_type ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: 1: field type key, 2: SEO plugin name. */
					__( 'Field type "%1$s" not supported for %2$s.', 'seo-booster' ),
					$field_type,
					$plugin_info['name']
				),
			);
		}

		$persisted = false;
		if ( $object_id > 0 ) {
			$persisted = SEO_Plugin_Registry::write_seo_field(
				$object_id,
				$object_type,
				$field_type,
				$value,
				$append_focus_keyword
			);
		}

		return array(
			'success'        => true,
			'plugin'         => $plugin_info['plugin'],
			'plugin_name'    => $plugin_info['name'],
			'name'           => $plugin_info['name'],
			'field_selector' => $field_selector,
			'value'          => $value,
			'persisted'      => $persisted,
			'message'        => $persisted
				? sprintf(
					/* translators: 1: field type key, 2: SEO plugin name. */
					__( 'Saved %1$s to %2$s.', 'seo-booster' ),
					$field_type,
					$plugin_info['name']
				)
				: sprintf(
					/* translators: 1: field type key, 2: SEO plugin name. */
					__( 'Suggestion will be applied to %1$s field in %2$s.', 'seo-booster' ),
					$field_type,
					$plugin_info['name']
				),
		);
	}
}
