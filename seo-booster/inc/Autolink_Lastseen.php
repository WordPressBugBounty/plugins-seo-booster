<?php

namespace Cleverplugins\SEOBooster;

/**
 * Helpers for automatic-link "Last Used On" (lastseen) tracking and reconciliation.
 */
class Autolink_Lastseen {

	/**
	 * Convert a full permalink to a site-relative path stored in lastseen.
	 *
	 * @param string $full_url Full permalink URL.
	 * @return string Relative path beginning with /.
	 */
	public static function relative_path_from_full_url( $full_url ) {
		$site_url     = site_url();
		$current_path = str_replace( $site_url, '', $full_url );

		if ( substr( $current_path, 0, 1 ) !== '/' ) {
			$current_path = '/' . $current_path;
		}

		return $current_path;
	}

	/**
	 * Parse a lastseen DB value into an ordered list of relative paths.
	 *
	 * @param mixed $lastseen Raw DB value.
	 * @return array<int, string>
	 */
	public static function parse_lastseen_urls( $lastseen ) {
		if ( empty( $lastseen ) ) {
			return array();
		}

		$unserialized = maybe_unserialize( $lastseen );
		if ( is_array( $unserialized ) ) {
			return array_values( $unserialized );
		}

		if ( is_string( $unserialized ) && '' !== $unserialized ) {
			return array( $unserialized );
		}

		return array();
	}

	/**
	 * Build a lookup set of injected keyword + target URL pairs.
	 *
	 * @param array<int, array{keyword?: string, url?: string}> $injected_keywords Injected pairs from ContentProcessing.
	 * @return array<string, true>
	 */
	public static function build_injected_lookup( array $injected_keywords ) {
		$lookup = array();

		foreach ( $injected_keywords as $keyword_data ) {
			if ( empty( $keyword_data['keyword'] ) || empty( $keyword_data['url'] ) ) {
				continue;
			}

			$lookup[ self::pair_key( $keyword_data['keyword'], $keyword_data['url'] ) ] = true;
		}

		return $lookup;
	}

	/**
	 * Remove a path from a lastseen URL list.
	 *
	 * @param array<int, string> $urls      Parsed lastseen paths.
	 * @param string             $path      Relative path to remove.
	 * @return array{urls: array<int, string>, changed: bool}
	 */
	public static function remove_path_from_urls( array $urls, $path ) {
		$index = array_search( $path, $urls, true );
		if ( false === $index ) {
			return array(
				'urls'    => $urls,
				'changed' => false,
			);
		}

		unset( $urls[ $index ] );

		return array(
			'urls'    => array_values( $urls ),
			'changed' => true,
		);
	}

	/**
	 * Remove stale lastseen entries for the current page when injection did not occur.
	 *
	 * Skipped when the replace limit was reached on this request (not all keywords were evaluated).
	 *
	 * @param array<int, array{keyword?: string, url?: string}> $injected_keywords Keywords injected on this page load.
	 * @param string                                            $current_url       Full permalink of the current page.
	 * @return void
	 */
	public static function reconcile_keywords_last_usage( array $injected_keywords, $current_url ) {
		global $wpdb;

		$current_path     = self::relative_path_from_full_url( $current_url );
		$injected_lookup  = self::build_injected_lookup( $injected_keywords );
		$table_name       = $wpdb->prefix . 'sb2_autolink';
		$table_escaped    = esc_sql( $table_name );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name escaped; read for reconcile pass.
		$rows = $wpdb->get_results( "SELECT id, keyword, url, lastseen FROM `{$table_escaped}` WHERE lastseen IS NOT NULL AND lastseen != ''" );

		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			if ( isset( $injected_lookup[ self::pair_key( $row->keyword, $row->url ) ] ) ) {
				continue;
			}

			$urls = self::parse_lastseen_urls( $row->lastseen );
			if ( ! in_array( $current_path, $urls, true ) ) {
				continue;
			}

			$result = self::remove_path_from_urls( $urls, $current_path );
			if ( ! $result['changed'] ) {
				continue;
			}

			$new_lastseen = empty( $result['urls'] ) ? '' : serialize( $result['urls'] );

			$wpdb->update(
				$table_name,
				array( 'lastseen' => $new_lastseen ),
				array( 'id' => (int) $row->id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Stable lookup key for a keyword + target URL pair.
	 *
	 * @param string $keyword Autolink keyword.
	 * @param string $url     Target URL.
	 * @return string
	 */
	private static function pair_key( $keyword, $url ) {
		return $keyword . "\0" . $url;
	}
}
