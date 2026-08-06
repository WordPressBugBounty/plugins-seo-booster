<?php
/**
 * Plugin Name: AI Markdown Discovery
 * Description: AI-friendly Markdown, llms.txt, llms-full.txt, entitymap.json and entitymap.html for WordPress.
 * Version: 8.6.0.3
 * Author: Concept Interest
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

final class AI_Markdown_Discovery {
    const VERSION = '8.6.0.3';
    const QV = 'ai_markdown_discovery';
    const OPTION = 'ai_markdown_discovery_settings';
    const DIR_OPTION = 'ai_markdown_discovery_directory_rules';
    const DEFAULT_CACHE_TTL = 3600;
    const MAX_FULL_EXPORT_POSTS = 250;
    const MAX_ENTITYMAP_POSTS = 50;

    public static function init(): void {
        add_action('init', [__CLASS__, 'rewrite']);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('parse_request', [__CLASS__, 'direct'], 0);
        add_action('template_redirect', [__CLASS__, 'route'], 0);
        add_action('wp_head', [__CLASS__, 'head_links'], 1);
        add_action('send_headers', [__CLASS__, 'headers']);
        add_filter('robots_txt', [__CLASS__, 'robots'], 10, 2);

        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_init', [__CLASS__, 'maybe_repair_settings']);
        add_action('admin_post_ai_markdown_discovery_clear_cache', [__CLASS__, 'clear_cache_action']);
        add_action('admin_post_ai_markdown_discovery_save_directories', [__CLASS__, 'save_directories_action']);
        add_action('save_post', [__CLASS__, 'clear_cache']);
        add_action('deleted_post', [__CLASS__, 'clear_cache']);

        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'deactivate']);
    }

    public static function activate(): void {
        if (!get_option(self::OPTION)) {
            add_option(self::OPTION, self::default_settings());
        }
        self::rewrite();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    public static function rewrite(): void {
        add_rewrite_rule('^(.+)\.md/?$', 'index.php?pagename=$matches[1]&'.self::QV.'=md', 'top');
        add_rewrite_rule('^index\.md/?$', 'index.php?'.self::QV.'=md', 'top');
        add_rewrite_rule('^llms\.txt/?$', 'index.php?'.self::QV.'=llms', 'top');
        add_rewrite_rule('^llms-full\.txt/?$', 'index.php?'.self::QV.'=llmsfull', 'top');
        add_rewrite_rule('^entitymap\.json/?$', 'index.php?'.self::QV.'=entityjson', 'top');
        add_rewrite_rule('^entitymap\.html/?$', 'index.php?'.self::QV.'=entityhtml', 'top');
    }

    public static function query_vars(array $vars): array { $vars[] = self::QV; return $vars; }

    private static function sw(string $h, string $n): bool { return $n === '' || substr($h, 0, strlen($n)) === $n; }
    private static function ew(string $h, string $n): bool { return $n === '' || substr($h, -strlen($n)) === $n; }
    private static function has(string $h, string $n): bool { return $n === '' || strpos($h, $n) !== false; }

    private static function default_settings(): array {
        return [
            'md' => 1,
            'llms' => 1,
            'llms_full' => 1,
            'entitymap_json' => 1,
            'entitymap_html' => 1,
            'robots_llms' => 1,
            'head_links' => 1,
            'http_headers' => 1,
            'cache_ttl' => self::DEFAULT_CACHE_TTL,
            'llms_full_limit' => self::MAX_FULL_EXPORT_POSTS,
            'entitymap_limit' => 30,
            'entitymap_html_title' => '',
            'settings_version' => self::VERSION,
        ];
    }

    private static function settings(): array {
        $saved = get_option(self::OPTION, []);
        if (!is_array($saved)) $saved = [];
        return array_merge(self::default_settings(), $saved);
    }

    private static function bool_setting(string $key): bool {
        $s = self::settings();
        return !empty($s[$key]);
    }

    private static function endpoint_enabled(string $endpoint): bool {
        $s = self::settings();
        $enabled = !empty($s[$endpoint]);
        return (bool) apply_filters('ai_markdown_discovery_endpoint_enabled', $enabled, $endpoint);
    }

    private static function cache_ttl(): int {
        $s = self::settings();
        $ttl = isset($s['cache_ttl']) ? (int) $s['cache_ttl'] : self::DEFAULT_CACHE_TTL;
        $ttl = max(60, $ttl);
        return max(60, (int) apply_filters('ai_markdown_discovery_cache_ttl', $ttl));
    }

    private static function safe_limit(int $requested, int $max): int {
        if ($requested < 0) return $max;
        return max(1, min($requested, $max));
    }

    private static function cache_key(string $key): string {
        return 'amd_' . md5($key);
    }

    private static function cache_get(string $key) {
        return get_transient(self::cache_key($key));
    }

    private static function cache_set(string $key, string $value): void {
        set_transient(self::cache_key($key), $value, self::cache_ttl());
    }

    private static function cache_keys(): array {
        return [
            'llms_' . get_locale(),
            'llms_full_' . get_locale() . '_' . self::llms_full_limit(),
            'entitymap_json_' . get_locale(),
            'entitymap_html_' . get_locale(),
        ];
    }

    public static function clear_cache(): void {
        foreach (self::cache_keys() as $key) {
            delete_transient(self::cache_key($key));
        }
    }

    private static function llms_full_limit(): int {
        $s = self::settings();
        $limit = isset($s['llms_full_limit']) ? (int) $s['llms_full_limit'] : self::MAX_FULL_EXPORT_POSTS;
        return self::safe_limit((int) apply_filters('ai_markdown_discovery_llms_full_limit', $limit), self::MAX_FULL_EXPORT_POSTS);
    }

    private static function entitymap_limit(): int {
        $s = self::settings();
        $limit = isset($s['entitymap_limit']) ? (int) $s['entitymap_limit'] : 30;
        return self::safe_limit((int) apply_filters('ai_markdown_discovery_entitymap_limit', $limit), self::MAX_ENTITYMAP_POSTS);
    }

    public static function admin_menu(): void {
        add_options_page(
            'AI Markdown Discovery',
            'AI Markdown',
            'manage_options',
            'ai-markdown-discovery',
            [__CLASS__, 'settings_page']
        );
    }

    public static function register_settings(): void {
        register_setting('ai_markdown_discovery_group', self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
            'default' => self::default_settings(),
        ]);
    }

    public static function sanitize_settings($input): array {
        $input = is_array($input) ? $input : [];
        $defaults = self::default_settings();
        $out = [];

        foreach (['md','llms','llms_full','entitymap_json','entitymap_html','robots_llms','head_links','http_headers'] as $key) {
            $out[$key] = !empty($input[$key]) ? 1 : 0;
        }

        $out['cache_ttl'] = isset($input['cache_ttl']) ? max(60, min(86400, (int) $input['cache_ttl'])) : $defaults['cache_ttl'];
        $out['llms_full_limit'] = isset($input['llms_full_limit']) ? max(1, min(self::MAX_FULL_EXPORT_POSTS, (int) $input['llms_full_limit'])) : $defaults['llms_full_limit'];
        $out['entitymap_limit'] = isset($input['entitymap_limit']) ? max(1, min(self::MAX_ENTITYMAP_POSTS, (int) $input['entitymap_limit'])) : $defaults['entitymap_limit'];

        $entitymap_html_title = isset($input['entitymap_html_title'])
            ? sanitize_text_field(wp_unslash((string) $input['entitymap_html_title']))
            : '';

        if (function_exists('mb_substr')) {
            $entitymap_html_title = mb_substr($entitymap_html_title, 0, 180);
        } else {
            $entitymap_html_title = substr($entitymap_html_title, 0, 180);
        }

        $out['entitymap_html_title'] = trim($entitymap_html_title);
        $out['settings_version'] = self::VERSION;

        self::clear_cache();

        return $out;
    }

    public static function maybe_repair_settings(): void {
        if (!current_user_can('manage_options')) return;

        $s = get_option(self::OPTION, []);
        if (!is_array($s) || empty($s)) return;

        $core = ['md','llms','llms_full','entitymap_json','entitymap_html'];
        $all_off = true;
        foreach ($core as $key) {
            if (!empty($s[$key])) { $all_off = false; break; }
        }

        $version = isset($s['settings_version']) ? (string) $s['settings_version'] : '';

        if ($all_off && $version !== self::VERSION) {
            $defaults = self::default_settings();
            update_option(self::OPTION, $defaults);
        }
    }

    public static function clear_cache_action(): void {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ai_markdown_discovery_clear_cache');
        self::clear_cache();
        wp_safe_redirect(add_query_arg(['page' => 'ai-markdown-discovery', 'amd_cache' => 'cleared'], admin_url('options-general.php')));
        exit;
    }

    public static function save_directories_action(): void {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ai_markdown_discovery_save_directories');

        /*
         * v8.6 fix:
         * Directory rules are now stored in their own option and submitted as one JSON payload.
         * This avoids unreliable form-array parsing and avoids interaction with endpoint settings.
         */
        $json = isset($_POST['amd_directory_rules_json']) ? wp_unslash((string) $_POST['amd_directory_rules_json']) : '';
        $decoded = json_decode($json, true);

        $rules = [];

        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $dir = self::normalize_directory((string) ($item['dir'] ?? ''));
                $status = sanitize_key((string) ($item['status'] ?? 'auto'));

                if ($dir === '' || !in_array($status, ['auto', 'include', 'exclude'], true)) {
                    continue;
                }

                if ($status !== 'auto') {
                    $rules[$dir] = $status;
                }
            }
        }

        /*
         * Fallback for non-JS admin environments.
         */
        if (!$rules && isset($_POST['directory_dirs'], $_POST['directory_statuses']) && is_array($_POST['directory_dirs']) && is_array($_POST['directory_statuses'])) {
            $dirs = wp_unslash($_POST['directory_dirs']);
            $statuses = wp_unslash($_POST['directory_statuses']);
            $count = min(count($dirs), count($statuses));

            for ($i = 0; $i < $count; $i++) {
                $dir = self::normalize_directory((string) $dirs[$i]);
                $status = sanitize_key((string) $statuses[$i]);

                if ($dir !== '' && in_array($status, ['include', 'exclude'], true)) {
                    $rules[$dir] = $status;
                }
            }
        }

        update_option(self::DIR_OPTION, $rules, false);
        self::clear_cache();

        wp_safe_redirect(add_query_arg([
            'page' => 'ai-markdown-discovery',
            'amd_dirs' => 'saved',
            'amd_dir_count' => count($rules),
        ], admin_url('options-general.php')));
        exit;
    }

    private static function normalize_directory(string $dir): string {
        $dir = trim($dir);
        $dir = wp_parse_url($dir, PHP_URL_PATH) ?: $dir;
        $dir = trim($dir, "/ \t\n\r\0\x0B");
        if ($dir === '') return '/';
        $dir = preg_replace('/[^a-zA-Z0-9_\-\/\.]/', '', $dir);
        $dir = preg_replace('#/+#', '/', $dir);
        return '/' . trim($dir, '/') . '/';
    }

    private static function directory_rules(): array {
        $rules = get_option(self::DIR_OPTION, null);

        /*
         * Migration fallback from v8.3-v8.5, where rules lived inside the main settings option.
         */
        if (!is_array($rules)) {
            $s = self::settings();
            $rules = isset($s['directory_rules']) && is_array($s['directory_rules']) ? $s['directory_rules'] : [];

            if ($rules) {
                update_option(self::DIR_OPTION, $rules, false);
            }
        }

        $clean = [];

        foreach ($rules as $dir => $status) {
            $dir = self::normalize_directory((string) $dir);
            $status = sanitize_key((string) $status);

            if ($dir && in_array($status, ['include', 'exclude'], true)) {
                $clean[$dir] = $status;
            }
        }

        return $clean;
    }

    private static function directory_status_for_path(string $path): string {
        $path = '/' . trim($path, '/') . '/';
        $best = '';
        $status = 'auto';
        foreach (self::directory_rules() as $dir => $rule) {
            if ($dir === '/' || self::sw($path, $dir)) {
                if (strlen($dir) >= strlen($best)) { $best = $dir; $status = $rule; }
            }
        }
        return $status;
    }

    private static function discover_directories(int $limit = 1000): array {
        $posts = get_posts([
            'post_type' => array_values(array_diff(get_post_types(['public'=>true], 'names'), self::blocked_types())),
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(2000, $limit)),
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
        ]);
        $dirs = [];
        foreach ($posts as $post) {
            if (!$post instanceof WP_Post) continue;
            $url = get_permalink($post); if (!$url) continue;
            $path = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');
            $path = preg_replace('/\.(html|htm|php)$/i', '', $path);
            $parts = array_values(array_filter(explode('/', $path)));
            $dir = '/';
            if (count($parts) > 1) $dir = '/' . implode('/', array_slice($parts, 0, -1)) . '/';
            $dir = self::normalize_directory($dir);
            if (!isset($dirs[$dir])) {
                $dirs[$dir] = ['directory'=>$dir,'count'=>0,'post_types'=>[],'example_url'=>$url,'example_md'=>self::md_permalink($url),'auto_status'=>self::auto_status_for_directory($dir)];
            }
            $dirs[$dir]['count']++;
            $dirs[$dir]['post_types'][$post->post_type] = true;
        }
        foreach ($dirs as &$row) {
            $row['post_types'] = implode(', ', array_keys($row['post_types']));
            $row['rule'] = self::directory_rules()[$row['directory']] ?? 'auto';
        }
        unset($row);
        uasort($dirs, function($a,$b){ return strcmp($a['directory'], $b['directory']); });
        return $dirs;
    }

    private static function auto_status_for_directory(string $dir): string {
        $path = trim($dir, '/');
        if ($path !== '' && self::hard_blocked_path($path)) return 'exclude';
        return 'auto';
    }

    private static function endpoint_url(string $path): string {
        return esc_url(home_url('/' . ltrim($path, '/')));
    }

    public static function settings_page(): void {
        if (!current_user_can('manage_options')) return;
        $s = self::settings();
        $box = function($key, $label, $desc = '') use ($s) {
            echo '<label class="amd-toggle"><input type="checkbox" name="' . esc_attr(self::OPTION) . '[' . esc_attr($key) . ']" value="1" ' . checked(!empty($s[$key]), true, false) . '><span><strong>' . esc_html($label) . '</strong>';
            if ($desc) echo '<small>' . esc_html($desc) . '</small>';
            echo '</span></label>';
        };

        echo '<div class="wrap amd-wrap">';
        echo '<h1>AI Markdown Discovery <span style="font-size:14px;color:#646970;font-weight:400">v' . esc_html(self::VERSION) . '</span></h1>';
        echo '<p class="amd-lead">Styr AI-discovery, Markdown-versioner, llms.txt, llms-full.txt, entity maps, directory-regler og .md-preview. v8.4 retter form-fejlen fra v8.3, så endpoint-indstillinger og directory-regler gemmes separat.</p>';

        if (!empty($_GET['settings-updated'])) echo '<div class="notice notice-success is-dismissible"><p>Indstillinger gemt.</p></div>';
        if (!empty($_GET['amd_cache']) && $_GET['amd_cache'] === 'cleared') echo '<div class="notice notice-success is-dismissible"><p>Cache ryddet.</p></div>';
        if (!empty($_GET['amd_dirs']) && $_GET['amd_dirs'] === 'saved') echo '<div class="notice notice-success is-dismissible"><p>Directory-regler gemt. Valgene bør nu blive stående efter reload.</p></div>';
        if ((string) get_option('permalink_structure') === '') echo '<div class="notice notice-warning"><p>Permalinks ser ud til at være sat til standardstruktur. Pretty permalinks anbefales for .md-endpoints.</p></div>';

        echo '<style>
            .amd-wrap{max-width:1120px}.amd-lead{font-size:16px;color:#50575e;margin-bottom:22px}.amd-grid{display:grid;grid-template-columns:1.35fr .85fr;gap:22px;align-items:start}.amd-card{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:22px;box-shadow:0 1px 2px rgba(0,0,0,.04);margin-bottom:18px}.amd-card h2{margin-top:0;font-size:18px}.amd-toggle{display:flex;gap:12px;align-items:flex-start;border:1px solid #e2e4e7;border-radius:12px;padding:14px;margin:10px 0;background:#fbfbfc}.amd-toggle input{margin-top:3px;transform:scale(1.15)}.amd-toggle span{display:flex;flex-direction:column;gap:3px}.amd-toggle small{color:#646970}.amd-row{display:grid;grid-template-columns:220px 1fr;gap:14px;align-items:center;margin:14px 0}.amd-row input{max-width:160px}.amd-endpoints a{display:block;margin:8px 0;text-decoration:none}.amd-pill{display:inline-block;background:#f0f6fc;color:#0969da;border:1px solid #c8e1ff;border-radius:999px;padding:2px 8px;font-size:12px;margin-left:6px}.amd-table{width:100%;border-collapse:collapse}.amd-table th,.amd-table td{border-bottom:1px solid #e2e4e7;padding:10px;text-align:left;vertical-align:top}.amd-table th{font-weight:700;background:#f6f7f7}.amd-status-include{color:#008a20;font-weight:700}.amd-status-exclude{color:#b32d2e;font-weight:700}.amd-mini{font-size:12px;color:#646970}@media(max-width:900px){.amd-grid{grid-template-columns:1fr}.amd-row{grid-template-columns:1fr}.amd-table{font-size:12px}}
        </style>';

        echo '<div class="amd-grid"><div>';

        echo '<form method="post" action="options.php">';
        settings_fields('ai_markdown_discovery_group');

        echo '<div class="amd-card"><h2>Endpoints</h2>';
        $box('md', 'Markdown URLs (*.md)', 'Genererer Markdown-versioner af publiceret indhold.');
        $box('llms', 'llms.txt', 'AI-discovery entrypoint.');
        $box('llms_full', 'llms-full.txt', 'Fuld Markdown-eksport med hård maksimumgrænse.');
        $box('entitymap_json', 'entitymap.json', 'Maskinlæsbar entitetsfil.');
        $box('entitymap_html', 'entitymap.html', 'Menneskelæsbar entity map-side.');
        echo '</div>';

        echo '<div class="amd-card"><h2>Discovery-signaler</h2>';
        $box('robots_llms', 'LLMS i robots.txt', 'Tilføjer LLMS: /llms.txt til WordPress’ virtuelle robots.txt.');
        $box('head_links', 'HTML link tags', 'Tilføjer alternate til sidens Markdown-version og describedby til entity maps på forsiden.');
        $box('http_headers', 'HTTP Link headers', 'Sender discovery-signaler som HTTP Link headers.');
        echo '</div>';

        echo '<div class="amd-card"><h2>Entity map HTML</h2>';
        echo '<div class="amd-row"><label for="amd_entitymap_html_title"><strong>Title tag</strong><br><small>Gælder kun &lt;title&gt; på entitymap.html. Tomt felt bruger standardtitlen: EntityMap · sitets navn.</small></label><input id="amd_entitymap_html_title" type="text" maxlength="180" name="' . esc_attr(self::OPTION) . '[entitymap_html_title]" value="' . esc_attr((string) ($s['entitymap_html_title'] ?? '')) . '" placeholder="EntityMap · ' . esc_attr(self::site_name()) . '" style="max-width:520px;width:100%"></div>';
        echo '</div>';

        echo '<div class="amd-card"><h2>Performance og grænser</h2>';
        echo '<div class="amd-row"><label for="amd_cache_ttl"><strong>Cache TTL</strong><br><small>Sekunder. Min. 60, maks. 86400.</small></label><input id="amd_cache_ttl" type="number" min="60" max="86400" name="' . esc_attr(self::OPTION) . '[cache_ttl]" value="' . esc_attr((string) $s['cache_ttl']) . '"></div>';
        echo '<div class="amd-row"><label for="amd_full_limit"><strong>llms-full limit</strong><br><small>Maks. ' . esc_html((string) self::MAX_FULL_EXPORT_POSTS) . '.</small></label><input id="amd_full_limit" type="number" min="1" max="' . esc_attr((string) self::MAX_FULL_EXPORT_POSTS) . '" name="' . esc_attr(self::OPTION) . '[llms_full_limit]" value="' . esc_attr((string) $s['llms_full_limit']) . '"></div>';
        echo '<div class="amd-row"><label for="amd_entity_limit"><strong>Entitymap limit</strong><br><small>Maks. ' . esc_html((string) self::MAX_ENTITYMAP_POSTS) . '.</small></label><input id="amd_entity_limit" type="number" min="1" max="' . esc_attr((string) self::MAX_ENTITYMAP_POSTS) . '" name="' . esc_attr(self::OPTION) . '[entitymap_limit]" value="' . esc_attr((string) $s['entitymap_limit']) . '"></div>';
        echo '</div>';

        submit_button('Gem endpoint-indstillinger');
        echo '</form>';

        $directories = self::discover_directories();
        echo '<div class="amd-card"><h2>Directory scanner og .md-preview</h2>';
        echo '<p>Pluginet scanner publicerede WordPress-URL’er via databasen og udleder directories. Det crawler ikke frontend. Vælg <strong>Auto</strong>, <strong>Medtag</strong> eller <strong>Udeluk</strong>.</p>';
        echo '<p class="amd-mini">Sitemap kan bruges som ekstern kontrol, men databasen er primær, fordi den matcher WordPress’ faktiske posts, pages og CPTs.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="amd-directory-form">';
        echo '<input type="hidden" name="action" value="ai_markdown_discovery_save_directories">';
        echo '<input type="hidden" name="amd_directory_rules_json" id="amd-directory-rules-json" value="">';
        wp_nonce_field('ai_markdown_discovery_save_directories');
        echo '<table class="amd-table"><thead><tr><th>Directory</th><th>Antal</th><th>Post types</th><th>Eksempel</th><th>Status</th></tr></thead><tbody>';
        foreach ($directories as $row) {
            $dir = $row['directory']; $rule = $row['rule']; $effective = $rule !== 'auto' ? $rule : $row['auto_status'];
            echo '<tr><td><code>' . esc_html($dir) . '</code><br><span class="amd-mini">Effektiv: <span class="amd-status-' . esc_attr($effective) . '">' . esc_html($effective) . '</span></span></td>';
            echo '<td>' . esc_html((string) $row['count']) . '</td>';
            echo '<td><code>' . esc_html($row['post_types']) . '</code></td>';
            echo '<td><a href="' . esc_url($row['example_url']) . '" target="_blank">HTML</a> · <a href="' . esc_url($row['example_md']) . '" target="_blank">.md preview</a></td>';
            echo '<td><input type="hidden" name="directory_dirs[]" value="' . esc_attr($dir) . '"><select class="amd-directory-select" data-directory="' . esc_attr($dir) . '" name="directory_statuses[]"><option value="auto"' . selected($rule, 'auto', false) . '>Auto</option><option value="include"' . selected($rule, 'include', false) . '>Medtag</option><option value="exclude"' . selected($rule, 'exclude', false) . '>Udeluk</option></select></td></tr>';
        }
        if (!$directories) echo '<tr><td colspan="5">Ingen publicerede URL’er fundet.</td></tr>';
        echo '</tbody></table><p><button type="submit" class="button button-primary">Gem directory-regler</button></p>';
        echo '<script>
        (function(){
            var form = document.getElementById("amd-directory-form");
            if (!form) return;
            form.addEventListener("submit", function(){
                var rows = [];
                var selects = form.querySelectorAll(".amd-directory-select");
                for (var i = 0; i < selects.length; i++) {
                    rows.push({
                        dir: selects[i].getAttribute("data-directory") || "",
                        status: selects[i].value || "auto"
                    });
                }
                var target = document.getElementById("amd-directory-rules-json");
                if (target) target.value = JSON.stringify(rows);
            });
        })();
        </script>';
        echo '</form></div>';

        echo '</div><div>';

        echo '<div class="amd-card"><h2>Systemstatus</h2>';
        echo '<p><strong>Plugin-version:</strong> ' . esc_html(self::VERSION) . '</p>';
        echo '<p><strong>PHP-version:</strong> ' . esc_html(PHP_VERSION) . '</p>';
        echo '<p><strong>Permalink-struktur:</strong> <code>' . esc_html((string) get_option('permalink_structure')) . '</code></p>';
        echo '<p><strong>Cache TTL:</strong> ' . esc_html((string) self::cache_ttl()) . ' sekunder</p>';
        echo '<p><strong>llms-full limit:</strong> ' . esc_html((string) self::llms_full_limit()) . '</p>';
        echo '<p><strong>Entitymap limit:</strong> ' . esc_html((string) self::entitymap_limit()) . '</p></div>';

        echo '<div class="amd-card amd-endpoints"><h2>Test endpoints</h2>';
        echo '<a href="' . self::endpoint_url('llms.txt') . '" target="_blank">/llms.txt <span class="amd-pill">text</span></a>';
        echo '<a href="' . self::endpoint_url('llms-full.txt') . '" target="_blank">/llms-full.txt <span class="amd-pill">markdown</span></a>';
        echo '<a href="' . self::endpoint_url('entitymap.json') . '" target="_blank">/entitymap.json <span class="amd-pill">json</span></a>';
        echo '<a href="' . self::endpoint_url('entitymap.html') . '" target="_blank">/entitymap.html <span class="amd-pill">html</span></a>';
        $sample = self::sample_markdown_post();
        if ($sample instanceof WP_Post) echo '<a href="' . esc_url(self::md_permalink(get_permalink($sample))) . '" target="_blank">Eksempel på .md <span class="amd-pill">markdown</span></a>';
        echo '</div>';

        echo '<div class="amd-card"><h2>Cache</h2><p>Ryd cache efter større indholdsændringer, eller hvis du ændrer vigtige entitymap-filtre.</p><p><a class="button button-secondary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=ai_markdown_discovery_clear_cache'), 'ai_markdown_discovery_clear_cache')) . '">Ryd plugin-cache</a></p></div>';
        echo '<div class="amd-card"><h2>Robots-status</h2><p><strong>.md, llms.txt og llms-full.txt:</strong><br><code>X-Robots-Tag: noindex, follow</code></p><p><strong>entitymap.html og entitymap.json:</strong><br>Indekserbare offentlige ressourcer.</p></div>';

        echo '</div></div></div>';
    }

    public static function robots(string $out, bool $public): string {
        if (!self::bool_setting('robots_llms') || !self::endpoint_enabled('llms')) return $out;
        return rtrim($out) . "\n\nLLMS: " . home_url('/llms.txt') . "\n";
    }

    public static function head_links(): void {
        if (!self::bool_setting('head_links')) return;
        $md = self::current_md_url();
        if ($md && self::endpoint_enabled('md')) echo "\n" . '<link rel="alternate" type="text/markdown" href="' . esc_url($md) . '">' . "\n";
        if (is_front_page()) {
            if (self::endpoint_enabled('entitymap_json')) echo '<link rel="describedby" type="application/json" href="' . esc_url(home_url('/entitymap.json')) . '">' . "\n";
            if (self::endpoint_enabled('entitymap_html')) echo '<link rel="describedby" type="text/html" href="' . esc_url(home_url('/entitymap.html')) . '">' . "\n";
        }
    }

    public static function headers(): void {
        if (headers_sent() || !self::bool_setting('http_headers')) return;
        $md = self::current_md_url();
        if ($md && self::endpoint_enabled('md')) header('Link: <' . esc_url_raw($md) . '>; rel="alternate"; type="text/markdown"', false);
        if (is_front_page()) {
            if (self::endpoint_enabled('entitymap_json')) header('Link: <' . esc_url_raw(home_url('/entitymap.json')) . '>; rel="describedby"; type="application/json"', false);
            if (self::endpoint_enabled('entitymap_html')) header('Link: <' . esc_url_raw(home_url('/entitymap.html')) . '>; rel="describedby"; type="text/html"', false);
        }
    }

    private static function current_md_url(): string {
        if (is_admin() || is_feed() || is_robots() || is_search() || is_404()) return '';

        // The actual site front page always uses the dedicated /index.md endpoint.
        // When the posts index is the front page, both is_front_page() and
        // is_home() are true, so this branch still returns /index.md.
        if (is_front_page()) {
            return self::homepage_markdown_available() ? home_url('/index.md') : '';
        }

        // A separate posts page is not the site root. Point to that page's
        // own Markdown URL instead of incorrectly pointing to /index.md.
        if (is_home()) {
            $posts_page_id = (int) get_option('page_for_posts');
            $posts_page = $posts_page_id > 0 ? get_post($posts_page_id) : null;
            if (!$posts_page instanceof WP_Post || !self::allowed($posts_page)) return '';
            $posts_page_url = get_permalink($posts_page);
            return $posts_page_url ? self::md_permalink($posts_page_url) : '';
        }

        if (!is_singular()) return '';
        global $post;
        if (!$post instanceof WP_Post || !self::allowed($post)) return '';
        $url = get_permalink($post);
        return $url ? self::md_permalink($url) : '';
    }

    private static function homepage_post(): ?WP_Post {
        $front_id = (int) get_option('page_on_front');
        if ($front_id < 1) return null;
        $front = get_post($front_id);
        return ($front instanceof WP_Post && self::allowed($front)) ? $front : null;
    }

    private static function homepage_markdown_available(): bool {
        $front_id = (int) get_option('page_on_front');
        if ($front_id < 1) return true; // WordPress posts homepage.
        return self::homepage_post() instanceof WP_Post;
    }

    private static function homepage_markdown(): string {
        $front = self::homepage_post();
        if ($front instanceof WP_Post) return self::markdown($front);

        $site = self::site_name();
        $description = self::site_desc();
        $limit = (int) apply_filters('ai_markdown_discovery_homepage_limit', 20);
        $limit = max(1, min(50, $limit));
        $latest = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ]);

        $out = ['# ' . $site, ''];
        if ($description !== '') array_push($out, '> ' . $description, '');
        array_push(
            $out,
            '## Metadata',
            '',
            '- URL: ' . home_url('/'),
            '- Markdown URL: ' . home_url('/index.md'),
            '- Type: Homepage',
            '',
            '## Latest content',
            ''
        );

        $added = 0;
        foreach ($latest as $item) {
            if (!$item instanceof WP_Post || !self::allowed($item)) continue;
            $permalink = get_permalink($item);
            if (!$permalink) continue;
            $relative_path = trim((string) wp_parse_url($permalink, PHP_URL_PATH), '/');
            $home_path = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
            if ($home_path !== '' && self::sw($relative_path, $home_path . '/')) {
                $relative_path = substr($relative_path, strlen($home_path) + 1);
            }
            $relative_path = preg_replace('/\.(html|htm|php)$/i', '', $relative_path);
            if (self::blocked_path($relative_path)) continue;
            $out[] = '- [' . self::link_text(get_the_title($item)) . '](' . self::md_permalink($permalink) . ')';
            $added++;
        }

        if ($added === 0) $out[] = 'No published posts are currently available.';
        return trim(implode("\n", $out)) . "\n";
    }

    public static function direct(WP $wp): void {
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $path = trim((string) wp_parse_url($uri, PHP_URL_PATH), '/');
        $home_path = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
        if ($home_path !== '' && self::sw($path, $home_path . '/')) $path = substr($path, strlen($home_path) + 1);
        $path = strtolower(trim($path, '/'));

        if ($path === 'llms.txt') { if (!self::endpoint_enabled('llms')) self::gone('Endpoint disabled.'); self::llms(); }
        if ($path === 'llms-full.txt') { if (!self::endpoint_enabled('llms_full')) self::gone('Endpoint disabled.'); self::llms_full(); }
        if ($path === 'entitymap.json') { if (!self::endpoint_enabled('entitymap_json')) self::gone('Endpoint disabled.'); self::entity_json(); }
        if ($path === 'entitymap.html') { if (!self::endpoint_enabled('entitymap_html')) self::gone('Endpoint disabled.'); self::entity_html(); }
        if (self::ew($path, '.md') && self::blocked_path(preg_replace('/\.md$/i', '', $path))) self::gone('This Markdown endpoint has been removed.');
        if (self::ew($path, '.md') && !self::endpoint_enabled('md')) self::gone('Endpoint disabled.');
        if ($path === 'index.md') {
            if (!self::homepage_markdown_available()) self::not_found('Homepage Markdown is not available.');
            self::md_response(self::homepage_markdown());
        }
        if (self::ew($path, '.md')) {
            $p = self::resolve_path($path);
            $clean_path = preg_replace('/\.md$/i', '', $path);
            if (!$p || !self::allowed_for_path($p, $clean_path)) self::not_found('Markdown page not found.');
            self::md_response(self::markdown($p));
        }
    }

    public static function route(): void {
        $mode = get_query_var(self::QV);
        if (!$mode) return;
        if ($mode === 'llms') { if (!self::endpoint_enabled('llms')) self::gone('Endpoint disabled.'); self::llms(); }
        if ($mode === 'llmsfull') { if (!self::endpoint_enabled('llms_full')) self::gone('Endpoint disabled.'); self::llms_full(); }
        if ($mode === 'entityjson') { if (!self::endpoint_enabled('entitymap_json')) self::gone('Endpoint disabled.'); self::entity_json(); }
        if ($mode === 'entityhtml') { if (!self::endpoint_enabled('entitymap_html')) self::gone('Endpoint disabled.'); self::entity_html(); }
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $request_path = trim((string) wp_parse_url($uri, PHP_URL_PATH), '/');
        $home_path = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
        if ($home_path !== '' && self::sw($request_path, $home_path . '/')) {
            $request_path = substr($request_path, strlen($home_path) + 1);
        }
        $clean_path = preg_replace('/\.md$/i', '', strtolower(trim($request_path, '/')));

        if ($mode === 'md' && $clean_path === 'index') {
            if (!self::homepage_markdown_available()) self::not_found('Homepage Markdown is not available.');
            self::md_response(self::homepage_markdown());
        }

        $p = self::resolve_current();
        if (!$p || !self::allowed_for_path($p, $clean_path)) self::not_found('Markdown page not found.');
        self::md_response(self::markdown($p));
    }

    private static function md_response(string $body): void {
        status_header(200); nocache_headers();
        header('Content-Type: text/markdown; charset=' . get_option('blog_charset'));
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, follow');
        echo $body; exit;
    }

    private static function llms(): void {
        $key = 'llms_' . get_locale();
        $cached = self::cache_get($key);
        status_header(200); nocache_headers();
        header('Content-Type: text/plain; charset=' . get_option('blog_charset'));
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, follow');
        if (is_string($cached)) { echo $cached; exit; }

        ob_start();
        echo '# ' . self::site_name() . "\n\n";
        $desc = self::site_desc();
        if ($desc) echo $desc . "\n\n";
        echo "## Structured knowledge\n\n";
        if (self::endpoint_enabled('entitymap_json')) echo '- [Entity map JSON](' . home_url('/entitymap.json') . ")\n";
        if (self::endpoint_enabled('entitymap_html')) echo '- [Entity map HTML](' . home_url('/entitymap.html') . ")\n";
        echo "\n## Important pages\n\n";
        foreach (self::posts(50) as $p) echo '- [' . self::link_text(get_the_title($p)) . '](' . self::md_permalink(get_permalink($p)) . ")\n";
        if (self::endpoint_enabled('llms_full')) {
            echo "\n## Full export\n\n";
            echo '- [Full site markdown](' . home_url('/llms-full.txt') . ")\n";
        }
        $body = (string) ob_get_clean();
        self::cache_set($key, $body);
        echo $body; exit;
    }

    private static function llms_full(): void {
        $limit = self::llms_full_limit();
        $key = 'llms_full_' . get_locale() . '_' . $limit;
        $cached = self::cache_get($key);
        status_header(200); nocache_headers();
        header('Content-Type: text/markdown; charset=' . get_option('blog_charset'));
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, follow');
        if (is_string($cached)) { echo $cached; exit; }

        ob_start();
        echo '# ' . self::site_name() . "\n\n";
        echo "> Full Markdown export generated from public WordPress content.\n\n";
        if (self::endpoint_enabled('entitymap_json')) echo '- Entity map JSON: ' . home_url('/entitymap.json') . "\n";
        if (self::endpoint_enabled('entitymap_html')) echo '- Entity map HTML: ' . home_url('/entitymap.html') . "\n";
        echo '- Export limit: ' . $limit . " posts/pages\n\n";
        foreach (self::posts($limit) as $p) echo "\n\n---\n\n" . self::markdown($p) . "\n";
        $body = (string) ob_get_clean();
        self::cache_set($key, $body);
        echo $body; exit;
    }

    private static function entity_json(): void {
        $key = 'entitymap_json_' . get_locale();
        $cached = self::cache_get($key);
        status_header(200); nocache_headers();
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        header('X-Content-Type-Options: nosniff');
        if (is_string($cached)) { echo $cached; exit; }
        $body = (string) wp_json_encode(self::entitymap(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        self::cache_set($key, $body);
        echo $body; exit;
    }

    private static function entitymap_html_title(string $site_name): string {
        $settings = self::settings();
        $custom_title = isset($settings['entitymap_html_title'])
            ? trim((string) $settings['entitymap_html_title'])
            : '';

        if ($custom_title !== '') {
            return $custom_title;
        }

        return 'EntityMap · ' . $site_name;
    }

    private static function entity_html(): void {
        $key = 'entitymap_html_' . get_locale();
        $cached = self::cache_get($key);
        status_header(200); nocache_headers();
        header('Content-Type: text/html; charset=' . get_option('blog_charset'));
        header('X-Content-Type-Options: nosniff');
        if (is_string($cached)) { echo $cached; exit; }

        $m = self::entitymap();
        $site_name = (string) $m['publisher']['name'];
        $site = esc_html($site_name);
        $title_tag = esc_html(self::entitymap_html_title($site_name));
        $json = esc_url(home_url('/entitymap.json'));
        ob_start();
        echo '<!doctype html><html lang="' . esc_attr(get_bloginfo('language') ?: 'da') . '"><head><meta charset="' . esc_attr(get_option('blog_charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $title_tag . '</title><link rel="alternate" type="application/json" href="' . $json . '"><style>body{margin:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,&quot;Segoe UI&quot;,sans-serif;line-height:1.6;background:#f7f8f5;color:#17211b}main{max-width:980px;margin:0 auto;padding:48px 20px 72px}.entity{background:#fff;border:1px solid #dfe5dc;border-radius:16px;padding:26px;margin:18px 0}h1{font-size:clamp(2rem,4vw,3.2rem);line-height:1.1;margin:0 0 14px}.type,.meta,.intro,blockquote{color:#5f6b63}blockquote{margin:8px 0 14px;padding:10px 14px;border-left:4px solid #dfe5dc;background:#fafbf8}code{background:#eef4ed;padding:2px 6px;border-radius:6px}a{color:#245b3c}</style></head><body><main>';
        echo '<header><h1>EntityMap · ' . $site . '</h1><p class="intro">Struktureret oversigt over centrale entiteter, relationer og kilder på sitet. Maskinlæsbar version: <a href="' . $json . '">entitymap.json</a>.</p><p class="meta">Genereret: ' . esc_html($m['generated']) . ' · Status: ' . esc_html($m['verificationStatus']) . ' · ' . count($m['entities']) . ' entiteter</p></header>';
        foreach ($m['entities'] as $e) {
            echo '<section class="entity"><h2>' . esc_html($e['name']) . '</h2><p class="type">' . esc_html($e['@type']) . '</p><p>' . esc_html($e['description'] ?? '') . '</p>';
            if (!empty($e['hasChunks'])) {
                echo '<h3>Kilder</h3><ul>';
                foreach ($e['hasChunks'] as $c) echo '<li><a href="' . esc_url($c['sourceUrl']) . '">' . esc_html($c['pageTitle']) . '</a><blockquote>' . esc_html($c['text']) . '</blockquote></li>';
                echo '</ul>';
            }
            if (!empty($e['relations'])) {
                echo '<h3>Relationer</h3><ul>';
                foreach ($e['relations'] as $r) echo '<li><code>' . esc_html($r['predicate']) . '</code> → ' . esc_html($r['targetName']) . '</li>';
                echo '</ul>';
            }
            echo '</section>';
        }
        echo '<footer><p>Kanonisk maskinlæsbar kilde: <a href="' . $json . '">entitymap.json</a>.</p></footer></main></body></html>';
        $body = (string) ob_get_clean();
        self::cache_set($key, $body);
        echo $body; exit;
    }

    private static function entitymap(): array {
        $site = self::site_name(); $home = home_url('/'); $desc = self::site_desc();
        $org = [
            'entityId' => 'site_organization',
            '@type' => 'Organization',
            'name' => $site,
            'description' => $desc ?: 'Primary organization represented by this website.',
            'url' => $home,
            'relations' => [],
            'hasChunks' => [[
                'chunkId' => 'chunk_organization_home',
                'text' => $desc ?: $site . ' is the primary organization represented by this website.',
                'sourceUrl' => $home,
                'pageTitle' => $site,
                'publisher' => $site,
                'retrieved' => gmdate('c'),
                'relevanceScore' => 0.95,
                'contentType' => 'definition'
            ]]
        ];
        $entities = []; $i = 1;
        foreach (self::posts(self::entitymap_limit()) as $p) {
            $id = 'content_' . $p->ID; $title = self::clean(get_the_title($p)); $pdesc = self::meta_desc($p); $url = get_permalink($p);
            $org['relations'][] = ['predicate' => 'PUBLISHES', 'targetName' => $title, 'targetId' => $id];
            $entities[] = [
                'entityId' => $id,
                '@type' => self::entity_type($p),
                'name' => $title,
                'description' => $pdesc,
                'url' => $url,
                'relations' => [['predicate' => 'PUBLISHED_BY', 'targetName' => $site, 'targetId' => 'site_organization']],
                'hasChunks' => [[
                    'chunkId' => 'chunk_' . $p->ID,
                    'text' => $pdesc ?: wp_trim_words(wp_strip_all_tags($p->post_content), 35),
                    'sourceUrl' => $url,
                    'pageTitle' => $title,
                    'publisher' => $site,
                    'retrieved' => gmdate('c'),
                    'relevanceScore' => max(0.50, 0.95 - ($i * 0.01)),
                    'contentType' => 'definition'
                ]]
            ];
            $i++;
        }
        array_unshift($entities, $org);
        return apply_filters('ai_markdown_discovery_entitymap', [
            'version' => '1.0',
            'schema' => 'https://entitymap.org/spec/v1.0',
            'publisher' => ['name' => $site, 'url' => $home],
            'generated' => gmdate('c'),
            'verificationStatus' => 'self-declared',
            'entities' => $entities
        ]);
    }

    private static function entity_type(WP_Post $p): string {
        if ($p->post_type === 'page') return 'WebPage';
        if (in_array($p->post_type, ['case','cases'], true)) return 'CreativeWork';
        if (in_array($p->post_type, ['service','services'], true)) return 'Service';
        if (in_array($p->post_type, ['ordbog','glossary','dictionary','concept','concepts'], true)) return 'DefinedTerm';
        if ($p->post_type === 'product') return 'Product';
        if ($p->post_type === 'course') return 'Course';
        return 'Article';
    }

    private static function sample_markdown_post(): ?WP_Post {
        $posts = self::posts(1);

        if (!empty($posts[0]) && $posts[0] instanceof WP_Post) {
            return $posts[0];
        }

        return null;
    }

    private static function posts(int $limit): array {
        $limit = self::safe_limit($limit, self::MAX_FULL_EXPORT_POSTS);
        return get_posts(['post_type' => self::allowed_types(), 'post_status' => 'publish', 'posts_per_page' => $limit, 'orderby' => 'modified', 'order' => 'DESC', 'no_found_rows' => true]);
    }

    private static function resolve_current(): ?WP_Post {
        global $post; if ($post instanceof WP_Post) return $post;
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        return self::resolve_path(trim((string) wp_parse_url($uri, PHP_URL_PATH), '/'));
    }

    private static function resolve_path(string $path): ?WP_Post {
        $path = trim($path, '/');
        $path = preg_replace('/\.md\/?$/i', '', $path);
        $path = preg_replace('/\.(html|htm|php)$/i', '', $path);
        $path = preg_replace('/^index$/i', '', $path);
        $path = trim($path, '/');
        if (self::blocked_path($path)) return null;
        if ($path === '') { $front = (int) get_option('page_on_front'); return $front ? get_post($front) : null; }

        $found = get_page_by_path($path, OBJECT, self::allowed_types());
        if ($found instanceof WP_Post) return $found;

        $segments = array_values(array_filter(explode('/', $path)));
        $slug = sanitize_title(end($segments));
        if (!$slug) return null;

        $q = new WP_Query(['name' => $slug, 'post_type' => self::allowed_types(), 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true]);
        if (!empty($q->posts[0]) && $q->posts[0] instanceof WP_Post) return $q->posts[0];

        $prefixes = (array) apply_filters('ai_markdown_discovery_editorial_prefixes', ['ordbog/','glossary/','dictionary/','cases/','case/','viden/','blog/','brancher/','services/','specialer/','ydelser/','behandlinger/','klinikker/','om-os/','en/']);
        $editorial = false;
        foreach ($prefixes as $pre) { $pre = strtolower(trim((string) $pre, '/')) . '/'; if (self::sw($path, $pre)) { $editorial = true; break; } }
        if (!$editorial && self::directory_status_for_path($path) !== 'include') return null;

        global $wpdb;
        $types = array_values(array_diff(get_post_types(['public' => true], 'names'), self::blocked_types()));
        if (!$types) return null;
        $ph = implode(',', array_fill(0, count($types), '%s'));
        $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_status = 'publish' AND post_type IN ($ph) ORDER BY post_date DESC LIMIT 1";
        $id = (int) $wpdb->get_var($wpdb->prepare($sql, array_merge([$slug], $types)));
        return $id ? get_post($id) : null;
    }

    private static function allowed_types(): array {
        $types = ['post','page','case','cases','ordbog','glossary','dictionary','concept','concepts','service','services','branche','brancher','podcast','course','product'];
        $types = apply_filters('ai_markdown_discovery_allowed_post_types', $types);
        $types = array_values(array_unique(array_filter(array_map('sanitize_key', (array) $types))));
        return array_values(array_diff($types, self::blocked_types()));
    }

    private static function blocked_types(): array {
        return apply_filters('ai_markdown_discovery_blocked_post_types', ['attachment','astra-advanced-hook','elementor_library','wp_template','wp_template_part','wp_block','acf-field-group','acf-field','fl-builder-template','et_pb_layout','nav_menu_item','revision','custom_css','customize_changeset','oembed_cache','user_request']);
    }

    private static function allowed(WP_Post $p): bool { return $p->post_status === 'publish' && in_array($p->post_type, self::allowed_types(), true); }

    private static function allowed_for_path(WP_Post $p, string $path): bool {
        if ($p->post_status !== 'publish') return false;
        if (self::allowed($p)) return true;
        return self::directory_status_for_path($path) === 'include' && !in_array($p->post_type, self::blocked_types(), true);
    }

    private static function hard_blocked_path(string $path): bool {
        $path = strtolower(trim($path, '/'));
        if (in_array($path, ['xmlrpc.php'], true)) return true;
        $prefixes = apply_filters('ai_markdown_discovery_blocked_path_prefixes', ['author/','tag/','category/','page/','feed/','wp-admin/','wp-content/','wp-includes/','elementor_library/','astra-advanced-hook/','wp_template/','wp_template_part/','wp_block/','acf-field/','acf-field-group/','fl-builder-template/','et_pb_layout/','blocks/','templates/']);
        foreach ($prefixes as $pre) { $pre = strtolower(trim((string) $pre, '/')) . '/'; if (self::sw($path, $pre)) return true; }
        return false;
    }

    private static function blocked_path(string $path): bool {
        $path = strtolower(trim($path, '/'));
        if (self::hard_blocked_path($path)) return true;
        return self::directory_status_for_path($path) === 'exclude';
    }

    private static function md_permalink(string $url): string {
        $url = trim($url);
        if ($url === '') return '';

        if (self::ew(strtolower($url), '.md')) return $url;

        $url_host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $home_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        $url_path = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');
        $home_path = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');

        /*
         * v8.6.0.2: The homepage is routed through /index.md.
         * Appending .md directly to a bare domain would incorrectly create
         * a domain ending in .md instead of the site's /index.md endpoint.
         */
        if ($url_host !== '' && $url_host === $home_host && $url_path === $home_path) {
            return home_url('/index.md');
        }

        $url = untrailingslashit($url);
        $url = preg_replace('/\.(html|htm|php)$/i', '', $url);

        return $url . '.md';
    }

    private static function markdown(WP_Post $p): string {
        setup_postdata($p);
        $title = self::clean(get_the_title($p));
        $desc = self::meta_desc($p);
        $canonical = get_permalink($p);
        $content = self::rewrite_links(apply_filters('the_content', $p->post_content));
        $body = self::html_to_md($content);
        $faq = self::faq($p);
        wp_reset_postdata();

        $out = ['# ' . $title, ''];
        if ($desc) array_push($out, '> ' . $desc, '');
        array_push($out, '## Metadata', '', '- URL: ' . $canonical, '- Markdown URL: ' . self::md_permalink($canonical), '- Published: ' . get_the_date('c', $p), '- Modified: ' . get_the_modified_date('c', $p), '', '## Content', '', trim($body), '');
        if ($faq) {
            array_push($out, '## FAQ', '');
            foreach ($faq as $item) array_push($out, '### ' . self::clean($item['question']), '', trim(self::html_to_md($item['answer'])), '');
        }
        return trim(implode("\n", $out)) . "\n";
    }

    private static function rewrite_links(string $html): string {
        $home = home_url();
        return preg_replace_callback('#href=("|\')(.*?)\1#i', function($m) use ($home) {
            $q = $m[1]; $url = $m[2];
            if (self::sw($url, $home) && !self::has($url, '.md') && !self::has($url, '#') && !preg_match('/\.(jpg|jpeg|png|gif|webp|svg|pdf|zip|doc|docx|xls|xlsx|ppt|pptx)$/i', $url)) $url = self::md_permalink($url);
            return 'href=' . $q . esc_url_raw($url) . $q;
        }, $html);
    }

    private static function meta_desc(WP_Post $p): string {
        foreach (['_yoast_wpseo_metadesc','rank_math_description','_seopress_titles_desc','_aioseo_description'] as $key) {
            $v = get_post_meta($p->ID, $key, true);
            if (is_string($v) && trim($v) !== '') return self::clean(do_shortcode($v));
        }
        if (has_excerpt($p)) return self::clean(get_the_excerpt($p));
        return self::clean(wp_trim_words(wp_strip_all_tags($p->post_content), 35));
    }

    private static function faq(WP_Post $p): array {
        $faq = [];
        if (!has_blocks($p->post_content)) return [];
        foreach (self::faq_blocks(parse_blocks($p->post_content)) as $item) {
            $q = self::clean((string) ($item['question'] ?? '')); $a = trim((string) ($item['answer'] ?? ''));
            if ($q && $a) $faq[md5(mb_strtolower($q))] = ['question' => $q, 'answer' => $a];
        }
        return array_values($faq);
    }

    private static function faq_blocks(array $blocks): array {
        $out = [];
        foreach ($blocks as $b) {
            $name = $b['blockName'] ?? '';
            if ($name === 'yoast/faq-block' && !empty($b['attrs']['questions'])) foreach ($b['attrs']['questions'] as $q) $out[] = ['question' => $q['jsonQuestion'] ?? '', 'answer' => $q['jsonAnswer'] ?? ''];
            if (in_array($name, ['rank-math/faq-block','rank-math/faq'], true) && !empty($b['attrs']['questions'])) foreach ((array) $b['attrs']['questions'] as $q) $out[] = ['question' => $q['title'] ?? '', 'answer' => $q['content'] ?? ''];
            if (!empty($b['innerBlocks'])) $out = array_merge($out, self::faq_blocks($b['innerBlocks']));
        }
        return $out;
    }

    private static function html_to_md(string $html): string {
        $html = do_shortcode($html);
        $html = preg_replace('#<(script|style|iframe|noscript)[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        $html = str_replace(["\r\n","\r"], "\n", $html);

        if (class_exists('\League\HTMLToMarkdown\HtmlConverter')) {
            $c = new \League\HTMLToMarkdown\HtmlConverter(['strip_tags' => true, 'remove_nodes' => 'script style iframe noscript', 'hard_break' => true]);
            return trim($c->convert($html));
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', get_bloginfo('charset'));
        $wrapped = '<!DOCTYPE html><html><body>' . mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8') . '</body></html>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $body = $dom->getElementsByTagName('body')->item(0);
        $md = self::node_md($body);
        libxml_clear_errors();
        $md = html_entity_decode($md, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $md = preg_replace("/[ \t]+\n/", "\n", $md);
        $md = preg_replace("/\n{3,}/", "\n\n", $md);
        return trim($md);
    }

    private static function node_md(?DOMNode $node): string {
        if (!$node) return '';
        if ($node->nodeType === XML_TEXT_NODE) return preg_replace('/\s+/', ' ', $node->nodeValue ?? '');
        if ($node->nodeType !== XML_ELEMENT_NODE && $node->hasChildNodes()) { $o = ''; foreach($node->childNodes as $c) $o .= self::node_md($c); return $o; }

        $tag = strtolower($node->nodeName); $content = '';
        foreach ($node->childNodes as $c) $content .= self::node_md($c);
        $content = trim($content);

        switch ($tag) {
            case 'h1': return "\n# " . $content . "\n\n";
            case 'h2': return "\n## " . $content . "\n\n";
            case 'h3': return "\n### " . $content . "\n\n";
            case 'h4': return "\n#### " . $content . "\n\n";
            case 'h5': return "\n##### " . $content . "\n\n";
            case 'h6': return "\n###### " . $content . "\n\n";
            case 'p': return $content !== '' ? "\n" . $content . "\n\n" : '';
            case 'br': return "  \n";
            case 'strong': case 'b': return $content !== '' ? '**' . $content . '**' : '';
            case 'em': case 'i': return $content !== '' ? '*' . $content . '*' : '';
            case 'a': $href = $node instanceof DOMElement ? $node->getAttribute('href') : ''; return ($href && $content) ? '[' . $content . '](' . esc_url_raw($href) . ')' : $content;
            case 'ul': return "\n" . self::list_md($node, false) . "\n";
            case 'ol': return "\n" . self::list_md($node, true) . "\n";
            case 'blockquote': $lines = array_filter(array_map('trim', explode("\n", $content))); return "\n" . implode("\n", array_map(function($l){return '> ' . $l;}, $lines)) . "\n\n";
            case 'code': return '`' . trim($content) . '`';
            case 'pre': return "\n```\n" . trim($node->textContent ?? '') . "\n```\n\n";
            case 'img': $alt = $node instanceof DOMElement ? $node->getAttribute('alt') : ''; $src = $node instanceof DOMElement ? $node->getAttribute('src') : ''; return $src ? '![' . $alt . '](' . esc_url_raw($src) . ')' : '';
            case 'table': return "\n" . self::table_md($node) . "\n\n";
            case 'div': case 'section': case 'article': case 'main': return $content !== '' ? "\n" . $content . "\n" : '';
            default: return $content;
        }
    }

    private static function list_md(DOMNode $node, bool $ordered): string {
        $out = []; $i = 1;
        foreach ($node->childNodes as $c) {
            if (strtolower($c->nodeName) !== 'li') continue;
            $text = preg_replace('/\n+/', "\n  ", trim(self::node_md($c)));
            $out[] = ($ordered ? $i . '. ' : '- ') . $text; $i++;
        }
        return implode("\n", $out) . "\n";
    }

    private static function table_md(DOMNode $node): string {
        $rows = [];
        foreach ($node->getElementsByTagName('tr') as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $cell) if (in_array(strtolower($cell->nodeName), ['td','th'], true)) $cells[] = trim(preg_replace('/\s+/', ' ', $cell->textContent ?? ''));
            if ($cells) $rows[] = $cells;
        }
        if (!$rows) return '';
        $head = array_shift($rows); $out = ['| ' . implode(' | ', $head) . ' |', '| ' . implode(' | ', array_fill(0, count($head), '---')) . ' |'];
        foreach ($rows as $row) { $row = array_pad($row, count($head), ''); $out[] = '| ' . implode(' | ', array_slice($row, 0, count($head))) . ' |'; }
        return implode("\n", $out);
    }

    private static function clean(string $text): string {
        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset'));
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private static function link_text(string $text): string { return str_replace([']','['], ['\]','\['], self::clean($text)); }
    private static function site_name(): string { $n = self::clean((string) get_bloginfo('name')); return $n ?: (string) parse_url(home_url('/'), PHP_URL_HOST); }
    private static function site_desc(): string { return self::clean((string) get_bloginfo('description')); }

    private static function gone(string $msg): void { status_header(410); header('Content-Type: text/markdown; charset=' . get_option('blog_charset')); header('X-Robots-Tag: noindex, nofollow'); echo "# 410 Gone\n\n" . $msg . "\n"; exit; }
    private static function not_found(string $msg): void { status_header(404); header('Content-Type: text/markdown; charset=' . get_option('blog_charset')); header('X-Robots-Tag: noindex, follow'); echo "# 404\n\n" . $msg . "\n"; exit; }
}

AI_Markdown_Discovery::init();
