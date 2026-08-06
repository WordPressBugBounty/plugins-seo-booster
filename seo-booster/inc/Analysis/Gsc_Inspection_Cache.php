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
	 * @return string
	 */
	private static function cache_key( $url ) {
		return 'sb_gsc_inspect_' . md5( (string) $url );
	}

	/**
	 * @param string $url Page URL.
	 * @return array|null Cached inspection payload, or null if missing.
	 */
	public static function get( $url ) {
		$cached = get_transient( self::cache_key( $url ) );
		return false === $cached ? null : $cached;
	}

	/**
	 * @param string $url Page URL.
	 * @param mixed  $data Inspection data (arrays only; never WP_Error).
	 * @return void
	 */
	public static function set( $url, $data ) {
		if ( is_wp_error( $data ) || ! is_array( $data ) ) {
			return;
		}
		set_transient( self::cache_key( $url ), $data, self::TTL_SECONDS );
	}

	/**
	 * Drop cached inspection for a URL (forced re-analyze).
	 *
	 * @param string $url Page URL.
	 * @return void
	 */
	public static function invalidate( $url ) {
		delete_transient( self::cache_key( $url ) );
	}

	/**
	 * BCP-47 language code for Inspection API messages.
	 *
	 * @return string
	 */
	public static function language_code_for_site() {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$locale = str_replace( '_', '-', (string) $locale );
		return $locale !== '' ? $locale : 'en-US';
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

		$data = Google_API::inspect_url( $url, $site_url, self::language_code_for_site() );
		if ( ! is_wp_error( $data ) && is_array( $data ) ) {
			self::set( $url, $data );
		}

		return $data;
	}
}
