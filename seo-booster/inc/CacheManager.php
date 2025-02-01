<?php

namespace Cleverplugins\SEOBooster;

/**
 * Class CacheManager
 * Manages caching operations for SEO Booster plugin.
 *
 * @package Cleverplugins\SEOBooster
 */
class CacheManager {

    /** @var string $cache_dir The directory where cache files are stored. */
    private static $cache_dir;

    /** @var int $default_cache_duration Default cache duration in seconds. */
    private static $default_cache_duration = 86400; // 24 hours in seconds

    /** @var \WP_Filesystem_Base $filesystem WordPress filesystem object. */
    private static $filesystem;

    /**
     * Initializes the CacheManager.
     *
     * @return void
     */
    public static function init() {
        // Set the cache directory
        $cache_dir = apply_filters('seo_booster_cache_dir', WP_CONTENT_DIR . '/cache/seo-booster/');
        self::$cache_dir = wp_normalize_path(trailingslashit($cache_dir));
        
        // Initialize the filesystem
        self::initialize_filesystem();

        // Ensure the cache and seo-booster directories exist
        self::initialize_cache_directory();

        // Register cron event if not already scheduled
        if (!wp_next_scheduled('seobooster_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'seobooster_cache_cleanup');
        }
        add_action('seobooster_cache_cleanup', array(__CLASS__, 'cleanup_old_cache_files'));
    }

    /**
     * Initializes the WordPress filesystem.
     *
     * @return bool True if filesystem is initialized successfully, false otherwise.
     */
    private static function initialize_filesystem() {
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
            if (!WP_Filesystem()) {
                Utils::log(__("Failed to initialize WP_Filesystem", 'seo-booster'), 2);
                return false;
            }
        }
        self::$filesystem = $wp_filesystem;
        return true;
    }

    /**
     * Initializes the cache directory structure.
     *
     * @return bool True if directory structure is created successfully, false otherwise.
     */
    private static function initialize_cache_directory() {
        $cache_base_dir = WP_CONTENT_DIR . '/cache/';
        if (!self::$filesystem->is_dir($cache_base_dir)) {
            if (!self::$filesystem->mkdir($cache_base_dir, FS_CHMOD_DIR)) {
                Utils::log(__("Failed to create base cache directory", 'seo-booster'), 2);
                return false;
            }
            if (!self::$filesystem->put_contents($cache_base_dir . 'index.php', '<?php // Silence is golden', FS_CHMOD_FILE)) {
                Utils::log(__("Failed to create index.php in base cache directory", 'seo-booster'), 2);
            }
        }
        if (!self::$filesystem->is_dir(self::$cache_dir)) {
            if (!self::$filesystem->mkdir(self::$cache_dir, FS_CHMOD_DIR)) {
                Utils::log(__("Failed to create seo-booster cache directory", 'seo-booster'), 2);
                return false;
            }
            if (!self::$filesystem->put_contents(self::$cache_dir . 'index.php', '<?php // Silence is golden', FS_CHMOD_FILE)) {
                Utils::log(__("Failed to create index.php in seo-booster cache directory", 'seo-booster'), 2);
            }
        }
        return true;
    }

    /**
     * Fetches and caches URL content.
     *
     * @param string $url The URL to fetch and cache.
     * @param array $args Additional arguments for the request.
     * @return array An array containing the fetched content and cache status.
     */
    public static function fetch_and_cache_url_content(string $url, array $args = []): array
    {
        $post_id = isset($args['post_id']) ? $args['post_id'] : false;

        if (!wp_http_validate_url($url)) {
            // translators: %s: URL that failed validation
            Utils::log(sprintf(__("Invalid URL: %s", 'seo-booster'), $url), 2);
            return array('error' => __('Invalid URL', 'seo-booster'), 'cached' => false);
        }
        // Start the timer
        Utils::timerstart('fetch_url');

        // Generate a cache key
        $post_modified_time = isset($args['post_id']) ? get_post_modified_time('U', false, $args['post_id']) : time();
        $cache_key = self::generate_cache_key($url, $post_modified_time);
        $cache_file = self::$cache_dir . $cache_key;

        // Check if cache exists and is valid
        if (self::$filesystem->exists($cache_file)) {
            $post_id = url_to_postid($url);
            $post_modified_time = get_post_modified_time('U', false, $post_id);

            if (self::check_cache_validity($cache_file, $post_modified_time)) {
                // Get and decompress cached content
                $cached_content = self::$filesystem->get_contents($cache_file);
                $decoded_content = base64_decode($cached_content);
                
                // Try to decompress if it's compressed
                $decompressed_content = @gzdecode($decoded_content);
                if ($decompressed_content !== false) {
                    $decoded_content = $decompressed_content;
                }

                $time_taken = Utils::timerstop('fetch_url', 5);
                
                return array(
                    'content' => $decoded_content,
                    'cached'  => true,
                    'time'    => filemtime($cache_file),
                    'duration' => $time_taken,
                );
            }
        }

        $is_local_site = self::is_local_environment();

        $sslverify = apply_filters('seo_booster_ssl_verify', !$is_local_site);

        // Fetch the content from the URL
        $response = wp_remote_get($url, array(
            'sslverify' => $sslverify,
            'timeout' => 30,
            'user-agent' => 'SEO Booster/1.0',
            'headers' => array(
                'Accept-Encoding' => 'gzip, deflate',
            ),
            'decompress' => true,
        ));
        
        if (is_wp_error($response)) {
            $parsed_url = wp_parse_url($url);
            $stripped_url = isset($parsed_url['path']) ? $parsed_url['path'] : '';
            // translators: 1: URL that failed to fetch, 2: error message
            Utils::log(sprintf(__('Failed to fetch URL: %1$s. Error: %2$s', 'seo-booster'), $stripped_url, $response->get_error_message()), 2);
            return array(
                'error' => $response->get_error_message(),
                'cached' => false,
                'duration' => Utils::timerstop('fetch_url', 5),
            );
        }

        $content = wp_remote_retrieve_body($response);
        self::create_cache_file($cache_file, $content);

        $time_taken = Utils::timerstop('fetch_url', 5);
        $parsed_url = wp_parse_url($url);
        $stripped_url = isset($parsed_url['path']) ? $parsed_url['path'] : '';

        return array(
            'content' => $content,
            'cached'  => false,
            'size'    => strlen($content),
            'time'    => time(),
            'duration' => $time_taken,
        );
    }

    /**
     * Checks if the current environment is a local development environment.
     *
     * @return bool True if local environment, false otherwise.
     */
    private static function is_local_environment() {
        // Add your local development domains and TLDs here
        $local_domains = array('localhost', '127.0.0.1', '.local', '.test');

        // Check the site URL
        $site_url = site_url();

        foreach ($local_domains as $local_domain) {
            if (stripos($site_url, $local_domain) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the cache file is still valid.
     *
     * @param string $file_path Path to the cache file.
     * @param int $post_modified_time Timestamp of when the post was last modified.
     * @return bool True if cache is valid, false otherwise.
     */
    private static function check_cache_validity($file_path, $post_modified_time) {
        if (!file_exists($file_path)) {
            return false;
        }
        $file_modified_time = filemtime($file_path);
        $cache_duration = apply_filters('seo_booster_cache_duration', self::$default_cache_duration);
        if ($file_modified_time >= $post_modified_time && (time() - $file_modified_time) <= $cache_duration) {
            return true;
        }
        return false;
    }

    /**
     * Creates a cache file with the given content.
     *
     * @param string $file_path Path where the cache file should be created.
     * @param string $content Content to be cached.
     * @return void
     */
    private static function create_cache_file($file_path, $content) {
        // Delete any existing versions of this cache file
        $pattern = self::$cache_dir . hash('sha256', wp_parse_url($file_path, PHP_URL_PATH)) . '_*.txt';
        array_map('unlink', glob($pattern));

        // Compress the content using gzip
        $compressed_content = gzencode($content, 9);
        if ($compressed_content === false) {
            // If compression fails, store uncompressed
            $compressed_content = $content;
        }

        $encoded_content = base64_encode($compressed_content);
        $result = self::$filesystem->put_contents($file_path, $encoded_content, FS_CHMOD_FILE);
        
        if ($result === false) {
            Utils::log(sprintf(__("Failed to create cache file: %s", 'seo-booster'), $file_path), 2);
        }
    }

    /**
     * Generates a cache key for a given URL.
     *
     * @param string $url The URL to generate a cache key for.
     * @param int $post_modified_time Timestamp of when the post was last modified.
     * @return string The generated cache key.
     */
    private static function generate_cache_key($url, $post_modified_time) {
        $cache_key = hash('sha256', $url ) .'_'. $post_modified_time . '.txt';
        return $cache_key;
    }

    /**
     * Cleans up cache files based on expiration time or forces deletion of all files.
     *
     * @param bool $force_delete Whether to delete all files regardless of expiration. Default false.
     * @return void
     */
    public static function cleanup_old_cache_files($force_delete = false) {
        $files = self::$filesystem->dirlist(self::$cache_dir);
        if (!$files) {
            return;
        }

        $cache_duration = apply_filters('seo_booster_cache_duration', self::$default_cache_duration);
        $expiration_time = time() - $cache_duration;
        $deleted_count = 0;
        $deleted_size = 0;

        foreach ($files as $file) {
            if (pathinfo($file['name'], PATHINFO_EXTENSION) === 'txt') {
                $file_path = self::$cache_dir . $file['name'];
                
                // Delete if forced or file is expired
                if ($force_delete || filemtime($file_path) < $expiration_time) {
                    $file_size = filesize($file_path);
                    if (self::$filesystem->delete($file_path)) {
                        $deleted_count++;
                        $deleted_size += $file_size;
                    }
                }
            }
        }

        if ($deleted_count > 0) {
            $message = $force_delete 
                ? /* translators: 1: Number of files deleted, 2: Size in MB */
                  __('Cache cleanup: Deleted all %1$s files (total size: %2$s MB)', 'seo-booster')
                : /* translators: 1: Number of files deleted, 2: Size in MB */
                  __('Cache cleanup: Deleted %1$s expired files (total size: %2$s MB)', 'seo-booster');

            Utils::log(
                sprintf(
                    $message,
                    number_format_i18n($deleted_count),
                    number_format_i18n($deleted_size / (1024 * 1024), 2)
                ),
                5
            );
        }
    }

    /**
     * Cleanup on plugin deactivation.
     *
     * @return void
     */
    public static function cleanup_on_deactivate() {
        wp_clear_scheduled_hook('seobooster_cache_cleanup');
        self::cleanup_old_cache_files(true);
    }
}

CacheManager::init();
