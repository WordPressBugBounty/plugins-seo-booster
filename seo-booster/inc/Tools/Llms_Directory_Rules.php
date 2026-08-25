<?php

namespace Cleverplugins\SEOBooster\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Directory include/exclude rules for llms.txt curation.
 *
 * @package Cleverplugins\SEOBooster\Tools
 */
class Llms_Directory_Rules {

	/**
	 * Normalize a directory path for rule matching.
	 *
	 * @param string $dir Directory path or URL fragment.
	 * @return string
	 */
	public static function normalize_directory( $dir ) {
		$dir         = trim( (string) $dir );
		$parsed_path = wp_parse_url( $dir, PHP_URL_PATH );
		$dir         = is_string( $parsed_path ) && '' !== $parsed_path ? $parsed_path : $dir;
		$dir         = trim( $dir, "/ \t\n\r\0\x0B" );
		if ( '' === $dir ) {
			return '/';
		}
		$dir = preg_replace( '/[^a-zA-Z0-9_\-\/\.]/', '', $dir );
		$dir = preg_replace( '#/+#', '/', $dir );

		return '/' . trim( $dir, '/' ) . '/';
	}

	/**
	 * Hard-blocked URL path prefixes (never included in llms.txt).
	 *
	 * @return string[]
	 */
	public static function get_blocked_path_prefixes() {
		$prefixes = array(
			'author/',
			'tag/',
			'category/',
			'feed/',
			'wp-admin/',
			'wp-content/',
			'wp-includes/',
			'elementor_library/',
			'wp_template/',
			'wp_template_part/',
			'wp_block/',
		);

		/**
		 * Filter hard-blocked URL path prefixes for llms.txt directory rules.
		 *
		 * @param string[] $prefixes Path prefixes without leading slash.
		 */
		return apply_filters( 'seobooster_tools_llms_blocked_path_prefixes', $prefixes );
	}

	/**
	 * Whether a URL path is hard-blocked.
	 *
	 * @param string $path URL path without domain.
	 * @return bool
	 */
	public static function is_hard_blocked_path( $path ) {
		$path = strtolower( trim( (string) $path, '/' ) );
		if ( 'xmlrpc.php' === $path ) {
			return true;
		}

		foreach ( self::get_blocked_path_prefixes() as $prefix ) {
			$prefix = strtolower( trim( (string) $prefix, '/' ) ) . '/';
			if ( '/' !== $prefix && 0 === strpos( $path . '/', $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sanitize directory rules from settings.
	 *
	 * @param mixed $raw Raw rules array.
	 * @return array<string, string>
	 */
	public static function sanitize_rules( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();
		foreach ( $raw as $dir => $status ) {
			$dir    = self::normalize_directory( (string) $dir );
			$status = sanitize_key( (string) $status );
			if ( $dir && in_array( $status, array( 'include', 'exclude', 'auto' ), true ) ) {
				$clean[ $dir ] = $status;
			}
		}

		return $clean;
	}

	/**
	 * Effective directory rule for a URL path (longest prefix match).
	 *
	 * @param string           $path  URL path.
	 * @param array<string,string> $rules Directory rules.
	 * @return string auto|include|exclude
	 */
	public static function status_for_path( $path, array $rules ) {
		$path   = '/' . trim( (string) $path, '/' ) . '/';
		$best   = '';
		$status = 'auto';

		foreach ( $rules as $dir => $rule ) {
			$dir = self::normalize_directory( (string) $dir );
			if ( '/' === $dir || 0 === strpos( $path, $dir ) ) {
				if ( strlen( $dir ) >= strlen( $best ) ) {
					$best   = $dir;
					$status = $rule;
				}
			}
		}

		return $status;
	}

	/**
	 * Auto status when no explicit rule is set.
	 *
	 * @param string $dir Normalized directory.
	 * @return string auto|exclude
	 */
	public static function auto_status_for_directory( $dir ) {
		$path = trim( self::normalize_directory( $dir ), '/' );
		if ( '' !== $path && self::is_hard_blocked_path( $path ) ) {
			return 'exclude';
		}

		return 'auto';
	}

	/**
	 * Whether a post URL is allowed by directory rules.
	 *
	 * @param \WP_Post           $post  Post object.
	 * @param array<string,string> $rules Directory rules.
	 * @return bool
	 */
	public static function is_post_allowed( $post, array $rules ) {
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$url = get_permalink( $post );
		if ( ! $url ) {
			return false;
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return true;
		}

		$path = trim( $path, '/' );
		if ( self::is_hard_blocked_path( $path ) ) {
			return false;
		}

		$rule = self::status_for_path( $path, $rules );
		if ( 'exclude' === $rule ) {
			return false;
		}
		if ( 'include' === $rule ) {
			return true;
		}

		return self::auto_status_for_directory( self::directory_from_path( $path ) ) !== 'exclude';
	}

	/**
	 * Filter posts by directory rules.
	 *
	 * @param \WP_Post[]         $posts Post list.
	 * @param array<string,string> $rules Directory rules.
	 * @return \WP_Post[]
	 */
	public static function filter_posts( array $posts, array $rules ) {
		if ( empty( $rules ) ) {
			$filtered = array();
			foreach ( $posts as $post ) {
				if ( self::is_post_allowed( $post, array() ) ) {
					$filtered[] = $post;
				}
			}
			return $filtered;
		}

		$filtered = array();
		foreach ( $posts as $post ) {
			if ( self::is_post_allowed( $post, $rules ) ) {
				$filtered[] = $post;
			}
		}

		return $filtered;
	}

	/**
	 * Derive directory prefix from a URL path.
	 *
	 * @param string $path Trimmed URL path.
	 * @return string
	 */
	public static function directory_from_path( $path ) {
		$path  = trim( (string) $path, '/' );
		$path  = preg_replace( '/\.(html|htm|php)$/i', '', $path );
		$parts = array_values( array_filter( explode( '/', $path ) ) );
		if ( count( $parts ) <= 1 ) {
			return '/';
		}

		return self::normalize_directory( '/' . implode( '/', array_slice( $parts, 0, -1 ) ) . '/' );
	}

	/**
	 * Discover directories from published posts.
	 *
	 * @param array $settings llms.txt settings (post_types).
	 * @param int   $limit    Max posts to scan.
	 * @return array<int, array{directory: string, count: int, post_types: string, example_url: string, rule: string, auto_status: string}>
	 */
	public static function discover_directories( array $settings, $limit = 1000 ) {
		$post_types = isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
			? Tools_Llms_Txt::parse_post_types( $settings['post_types'] )
			: array( 'post', 'page' );
		$rules      = isset( $settings['directory_rules'] ) && is_array( $settings['directory_rules'] )
			? self::sanitize_rules( $settings['directory_rules'] )
			: array();

		$posts = get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, min( 2000, (int) $limit ) ),
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$dirs = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$url = get_permalink( $post );
			if ( ! $url ) {
				continue;
			}

			$path = wp_parse_url( $url, PHP_URL_PATH );
			if ( ! is_string( $path ) ) {
				continue;
			}

			$dir = self::directory_from_path( trim( $path, '/' ) );
			if ( ! isset( $dirs[ $dir ] ) ) {
				$example_md = '';
				if ( class_exists( Tools_Markdown::class ) && Tools_Markdown::is_md_enabled() ) {
					$example_md = Tools_Markdown::md_permalink( $url );
				}
				$dirs[ $dir ] = array(
					'directory'   => $dir,
					'count'       => 0,
					'post_types'  => array(),
					'example_url' => $url,
					'example_md'  => $example_md,
					'auto_status' => self::auto_status_for_directory( $dir ),
					'rule'        => $rules[ $dir ] ?? 'auto',
				);
			}

			++$dirs[ $dir ]['count'];
			$dirs[ $dir ]['post_types'][ $post->post_type ] = true;
		}

		foreach ( $dirs as &$row ) {
			$row['post_types'] = implode( ', ', array_keys( $row['post_types'] ) );
		}
		unset( $row );

		uasort(
			$dirs,
			static function ( $a, $b ) {
				return strcmp( $a['directory'], $b['directory'] );
			}
		);

		return array_values( $dirs );
	}
}
