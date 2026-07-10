<?php

namespace Cleverplugins\SEOBooster\Analysis;

use Cleverplugins\SEOBooster\Google_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cached GSC URL Inspection API responses.
 *
 * @since 7.1.0
 */
class Gsc_Inspection_Cache {

	public const TTL_SECONDS = 86400;

	/**
	 * @param string $url Page URL.
	 * @return array|\WP_Error|null Cached inspection payload, WP_Error, or null if missing.
	 */
	public static function get( $url ) {
		$key = 'sb_gsc_inspect_' . md5( $url );
		$cached = get_transient( $key );
		return false === $cached ? null : $cached;
	}

	/**
	 * @param string $url Page URL.
	 * @param mixed  $data Inspection data or WP_Error marker.
	 * @return void
	 */
	public static function set( $url, $data ) {
		$key = 'sb_gsc_inspect_' . md5( $url );
		set_transient( $key, $data, self::TTL_SECONDS );
	}

	/**
	 * Inspect URL with cache and bulk throttling.
	 *
	 * @param string          $url Page URL.
	 * @param Content_Context $context Context.
	 * @return array|\WP_Error|null
	 */
	public static function inspect( $url, Content_Context $context ) {
		if ( $context->bulk_mode ) {
			$cached = self::get( $url );
			if ( null !== $cached ) {
				return $cached;
			}
			return null;
		}

		$cached = self::get( $url );
		if ( null !== $cached ) {
			return $cached;
		}

		$site_url = get_option( 'seobooster_selected_site', '' );
		if ( empty( $site_url ) ) {
			return null;
		}

		$data = Google_API::inspect_url( $url, $site_url );
		self::set( $url, $data );
		return $data;
	}
}
