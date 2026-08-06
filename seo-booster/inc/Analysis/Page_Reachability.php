<?php

namespace Cleverplugins\SEOBooster\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Probe whether an analyzed page URL is live, redirected, or unreachable.
 *
 * @since 7.4.1
 */
class Page_Reachability {

	public const STATUS_LIVE        = 'live';
	public const STATUS_REDIRECTED  = 'redirected';
	public const STATUS_UNREACHABLE = 'unreachable';

	public const ISSUE_UNREACHABLE = 'url_unreachable';
	public const ISSUE_REDIRECTED  = 'url_redirected';

	/**
	 * Issue keys that represent reachability status (not content possibilities).
	 *
	 * @return string[]
	 */
	public static function reachability_issue_keys() {
		return array( self::ISSUE_UNREACHABLE, self::ISSUE_REDIRECTED );
	}

	/**
	 * File extensions that are never HTML pages (on-page checks do not apply).
	 *
	 * @return string[]
	 */
	public static function non_html_file_extensions() {
		return array(
			'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'csv', 'txt',
			'zip', 'gz', 'tar', 'rar', '7z',
			'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp', 'tiff',
			'mp3', 'wav', 'ogg', 'm4a', 'mp4', 'mov', 'avi', 'webm', 'mkv',
			'css', 'js', 'json', 'xml',
			'woff', 'woff2', 'ttf', 'otf', 'eot',
		);
	}

	/**
	 * Whether a URL points at a direct file (PDF, image, archive, etc.) rather than an HTML page.
	 *
	 * Handles GSC-style paths with a trailing slash after the extension, e.g. /file.pdf/.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_non_html_file_url( $url ) {
		$url = is_string( $url ) ? trim( $url ) : '';
		if ( '' === $url ) {
			return false;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( '' === $path ) {
			return false;
		}

		$path      = untrailingslashit( $path );
		$extension = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( '' === $extension ) {
			return false;
		}

		return in_array( $extension, self::non_html_file_extensions(), true );
	}

	/**
	 * Classify an HTTP status code into a reachability status.
	 *
	 * @param int $status_code HTTP status code (0 = transport failure).
	 * @return string One of STATUS_LIVE, STATUS_REDIRECTED, STATUS_UNREACHABLE.
	 */
	public static function classify_http_status( $status_code ) {
		$code = (int) $status_code;

		if ( $code >= 200 && $code < 300 ) {
			return self::STATUS_LIVE;
		}

		if ( 304 === $code ) {
			return self::STATUS_LIVE;
		}

		if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
			return self::STATUS_REDIRECTED;
		}

		if ( $code >= 300 && $code < 400 ) {
			return self::STATUS_REDIRECTED;
		}

		return self::STATUS_UNREACHABLE;
	}

	/**
	 * Probe a page URL without following redirects.
	 *
	 * @param string $url Page URL.
	 * @param bool   $use_cache Whether to use Url_Status_Cache.
	 * @return array{status:string,status_code:int,redirect_to:string,error:string}
	 */
	public static function probe( $url, $use_cache = true ) {
		$url = is_string( $url ) ? trim( $url ) : '';

		if ( '' === $url ) {
			return self::result( self::STATUS_UNREACHABLE, 0, '', __( 'Empty URL', 'seo-booster' ) );
		}

		/**
		 * Filter the page reachability probe result (for tests and overrides).
		 *
		 * Returning a non-null array short-circuits the HTTP probe.
		 *
		 * @param array|null $override Override result or null.
		 * @param string     $url      URL being probed.
		 */
		$override = apply_filters( 'seo_booster_page_reachability_probe', null, $url );
		if ( is_array( $override ) && isset( $override['status'] ) ) {
			return self::normalize_result( $override );
		}

		if ( ! \Cleverplugins\SEOBooster\Utils::is_safe_outbound_url( $url, array( 'allow_same_host' => true ) ) ) {
			return self::result( self::STATUS_UNREACHABLE, 0, '', __( 'Invalid or blocked URL', 'seo-booster' ) );
		}

		if ( $use_cache ) {
			$cached = Url_Status_Cache::get( $url, Url_Status_Cache::KIND_PAGE );
			if ( is_array( $cached ) ) {
				$mapped = self::map_cache_row( $cached );
				if ( null !== $mapped ) {
					return $mapped;
				}
			}
		}

		$probe = self::http_probe( $url );

		if ( $use_cache ) {
			Url_Status_Cache::set(
				$url,
				Url_Status_Cache::KIND_PAGE,
				array(
					'status'      => $probe['status'],
					'status_code' => $probe['status_code'],
					'final_url'   => $probe['redirect_to'],
					'error'       => $probe['error'],
				)
			);
		}

		return $probe;
	}

	/**
	 * Build human-readable issue payload for a non-live probe result.
	 *
	 * @param array $probe Probe result from probe().
	 * @return array{key:string,message:string,extra_data:array}|null Null when live.
	 */
	public static function issue_for_probe( array $probe ) {
		$probe  = self::normalize_result( $probe );
		$status = $probe['status'];

		if ( self::STATUS_LIVE === $status ) {
			return null;
		}

		$code = (int) $probe['status_code'];

		if ( self::STATUS_REDIRECTED === $status ) {
			$redirect_to = (string) $probe['redirect_to'];
			if ( $code > 0 && $redirect_to !== '' ) {
				$message = sprintf(
					/* translators: 1: HTTP status code, 2: redirect destination URL */
					__( 'This URL redirects (HTTP %1$d) to: %2$s. On-page possibilities are not shown for redirected URLs.', 'seo-booster' ),
					$code,
					$redirect_to
				);
			} elseif ( $code > 0 ) {
				$message = sprintf(
					/* translators: %d: HTTP status code */
					__( 'This URL redirects (HTTP %d). On-page possibilities are not shown for redirected URLs.', 'seo-booster' ),
					$code
				);
			} else {
				$message = __( 'This URL redirects. On-page possibilities are not shown for redirected URLs.', 'seo-booster' );
			}

			return array(
				'key'        => self::ISSUE_REDIRECTED,
				'message'    => $message,
				'extra_data' => array(
					'status_code'  => $code,
					'redirect_to'  => $redirect_to,
					'reachability' => self::STATUS_REDIRECTED,
				),
			);
		}

		if ( $code > 0 ) {
			$message = sprintf(
				/* translators: %d: HTTP status code */
				__( 'URL not found or unavailable (HTTP %d).', 'seo-booster' ),
				$code
			);
		} elseif ( ! empty( $probe['error'] ) ) {
			$message = sprintf(
				/* translators: %s: error message */
				__( 'URL not found or unavailable (%s).', 'seo-booster' ),
				$probe['error']
			);
		} else {
			$message = __( 'URL not found or unavailable.', 'seo-booster' );
		}

		return array(
			'key'        => self::ISSUE_UNREACHABLE,
			'message'    => $message,
			'extra_data' => array(
				'status_code'  => $code,
				'redirect_to'  => '',
				'reachability' => self::STATUS_UNREACHABLE,
				'error'        => (string) $probe['error'],
			),
		);
	}

	/**
	 * Perform HEAD (then GET fallback) with redirects disabled.
	 *
	 * @param string $url URL.
	 * @return array{status:string,status_code:int,redirect_to:string,error:string}
	 */
	private static function http_probe( $url ) {
		$args = array(
			'timeout'     => 8,
			'redirection' => 0,
			'user-agent'  => 'SEO Booster Page Reachability/1.0',
			'sslverify'   => apply_filters( 'seo_booster_ssl_verify', ! self::is_local_site() ),
		);

		$response = wp_remote_head( $url, $args );

		$use_get = false;
		if ( is_wp_error( $response ) ) {
			$use_get = true;
		} else {
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( in_array( $code, array( 405, 501 ), true ) || 0 === $code ) {
				$use_get = true;
			}
		}

		if ( $use_get ) {
			$get_args            = $args;
			$get_args['headers'] = array( 'Range' => 'bytes=0-0' );
			$response            = wp_remote_get( $url, $get_args );
		}

		if ( is_wp_error( $response ) ) {
			return self::result( self::STATUS_UNREACHABLE, 0, '', $response->get_error_message() );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$headers     = wp_remote_retrieve_headers( $response );
		$location    = '';
		if ( is_object( $headers ) && isset( $headers['location'] ) ) {
			$location = (string) $headers['location'];
		} elseif ( is_array( $headers ) && isset( $headers['location'] ) ) {
			$location = (string) $headers['location'];
		}

		$status = self::classify_http_status( $status_code );

		return self::result(
			$status,
			$status_code,
			self::STATUS_REDIRECTED === $status ? $location : '',
			''
		);
	}

	/**
	 * @param array $row Cache row.
	 * @return array|null
	 */
	private static function map_cache_row( array $row ) {
		$raw_status = isset( $row['status'] ) ? (string) $row['status'] : '';
		$code       = isset( $row['status_code'] ) ? (int) $row['status_code'] : 0;

		if ( in_array( $raw_status, array( self::STATUS_LIVE, self::STATUS_REDIRECTED, self::STATUS_UNREACHABLE ), true ) ) {
			return self::result(
				$raw_status,
				$code,
				isset( $row['final_url'] ) ? (string) $row['final_url'] : '',
				isset( $row['error_message'] ) ? (string) $row['error_message'] : ''
			);
		}

		// Legacy link-checker statuses stored under page kind.
		if ( 'working' === $raw_status ) {
			return self::result( self::STATUS_LIVE, $code, '', '' );
		}
		if ( 'redirected' === $raw_status ) {
			return self::result(
				self::STATUS_REDIRECTED,
				$code,
				isset( $row['final_url'] ) ? (string) $row['final_url'] : '',
				''
			);
		}
		if ( 'broken' === $raw_status ) {
			return self::result(
				self::STATUS_UNREACHABLE,
				$code,
				'',
				isset( $row['error_message'] ) ? (string) $row['error_message'] : ''
			);
		}

		return null;
	}

	/**
	 * @param array $data Raw result.
	 * @return array{status:string,status_code:int,redirect_to:string,error:string}
	 */
	private static function normalize_result( array $data ) {
		$status = isset( $data['status'] ) ? (string) $data['status'] : self::STATUS_UNREACHABLE;
		if ( ! in_array( $status, array( self::STATUS_LIVE, self::STATUS_REDIRECTED, self::STATUS_UNREACHABLE ), true ) ) {
			$status = self::STATUS_UNREACHABLE;
		}

		return self::result(
			$status,
			isset( $data['status_code'] ) ? (int) $data['status_code'] : 0,
			isset( $data['redirect_to'] ) ? (string) $data['redirect_to'] : '',
			isset( $data['error'] ) ? (string) $data['error'] : ''
		);
	}

	/**
	 * @param string $status Status.
	 * @param int    $status_code Code.
	 * @param string $redirect_to Redirect location.
	 * @param string $error Error message.
	 * @return array{status:string,status_code:int,redirect_to:string,error:string}
	 */
	private static function result( $status, $status_code, $redirect_to, $error ) {
		return array(
			'status'      => $status,
			'status_code' => (int) $status_code,
			'redirect_to' => (string) $redirect_to,
			'error'       => (string) $error,
		);
	}

	/**
	 * @return bool
	 */
	private static function is_local_site() {
		$site_url = site_url();
		foreach ( array( 'localhost', '127.0.0.1', '.local', '.test' ) as $local ) {
			if ( stripos( $site_url, $local ) !== false ) {
				return true;
			}
		}
		return false;
	}
}
