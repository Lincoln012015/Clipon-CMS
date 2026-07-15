<?php
require_once __DIR__ . '/JsonStorage.php';
require_once __DIR__ . '/AnalyticsBotFilter.php';

class Settings {
    private static $settings = null;

    /** @var array<string,bool> */
    private static $iso639_1 = [
        'aa' => true, 'ab' => true, 'ae' => true, 'af' => true, 'ak' => true, 'am' => true, 'an' => true, 'ar' => true,
        'as' => true, 'av' => true, 'ay' => true, 'az' => true, 'ba' => true, 'be' => true, 'bg' => true, 'bh' => true,
        'bi' => true, 'bm' => true, 'bn' => true, 'bo' => true, 'br' => true, 'bs' => true, 'ca' => true, 'ce' => true,
        'ch' => true, 'co' => true, 'cr' => true, 'cs' => true, 'cu' => true, 'cv' => true, 'cy' => true, 'da' => true,
        'de' => true, 'dv' => true, 'dz' => true, 'ee' => true, 'el' => true, 'en' => true, 'eo' => true, 'es' => true,
        'et' => true, 'eu' => true, 'fa' => true, 'ff' => true, 'fi' => true, 'fj' => true, 'fo' => true, 'fr' => true,
        'fy' => true, 'ga' => true, 'gd' => true, 'gl' => true, 'gn' => true, 'gu' => true, 'gv' => true, 'ha' => true,
        'he' => true, 'hi' => true, 'ho' => true, 'hr' => true, 'ht' => true, 'hu' => true, 'hy' => true, 'hz' => true,
        'ia' => true, 'id' => true, 'ie' => true, 'ig' => true, 'ii' => true, 'ik' => true, 'io' => true, 'is' => true,
        'it' => true, 'iu' => true, 'ja' => true, 'jv' => true, 'ka' => true, 'kg' => true, 'ki' => true, 'kj' => true,
        'kk' => true, 'kl' => true, 'km' => true, 'kn' => true, 'ko' => true, 'kr' => true, 'ks' => true, 'ku' => true,
        'kv' => true, 'kw' => true, 'ky' => true, 'la' => true, 'lb' => true, 'lg' => true, 'li' => true, 'ln' => true,
        'lo' => true, 'lt' => true, 'lu' => true, 'lv' => true, 'mg' => true, 'mh' => true, 'mi' => true, 'mk' => true,
        'ml' => true, 'mn' => true, 'mr' => true, 'ms' => true, 'mt' => true, 'my' => true, 'na' => true, 'nb' => true,
        'nd' => true, 'ne' => true, 'ng' => true, 'nl' => true, 'nn' => true, 'no' => true, 'nr' => true, 'nv' => true,
        'ny' => true, 'oc' => true, 'oj' => true, 'om' => true, 'or' => true, 'os' => true, 'pa' => true, 'pi' => true,
        'pl' => true, 'ps' => true, 'pt' => true, 'qu' => true, 'rm' => true, 'rn' => true, 'ro' => true, 'ru' => true,
        'rw' => true, 'sa' => true, 'sc' => true, 'sd' => true, 'se' => true, 'sg' => true, 'si' => true, 'sk' => true,
        'sl' => true, 'sm' => true, 'sn' => true, 'so' => true, 'sq' => true, 'sr' => true, 'ss' => true, 'st' => true,
        'su' => true, 'sv' => true, 'sw' => true, 'ta' => true, 'te' => true, 'tg' => true, 'th' => true, 'ti' => true,
        'tk' => true, 'tl' => true, 'tn' => true, 'to' => true, 'tr' => true, 'ts' => true, 'tt' => true, 'tw' => true,
        'ty' => true, 'ug' => true, 'uk' => true, 'ur' => true, 'uz' => true, 've' => true, 'vi' => true, 'vo' => true,
        'wa' => true, 'wo' => true, 'xh' => true, 'yi' => true, 'yo' => true, 'za' => true, 'zh' => true, 'zu' => true,
    ];

    private static function getDefaultConversionTypes(): array {
        return [
            ['key' => 'conversion', 'enabled' => true],
            ['key' => 'lead', 'enabled' => true],
            ['key' => 'registration', 'enabled' => true],
            ['key' => 'purchase', 'enabled' => true],
            ['key' => 'add_to_cart', 'enabled' => true],
            ['key' => 'begin_checkout', 'enabled' => true],
            ['key' => 'subscribe', 'enabled' => true],
            ['key' => 'contact', 'enabled' => true],
            ['key' => 'sign_up', 'enabled' => true],
        ];
    }

    public static function sanitizeConversionTypes($types): array {
        $defaults = self::getDefaultConversionTypes();
        $defaultMap = [];
        foreach ($defaults as $item) {
            $defaultMap[$item['key']] = $item;
        }

        if (!is_array($types)) return $defaults;
        $normalized = [];
        $customCount = 0;
        foreach ($types as $item) {
            if (!is_array($item)) continue;
            $key = strtolower(trim((string)($item['key'] ?? '')));
            $key = trim((string)(preg_replace('/[^a-z0-9_-]+/', '_', $key) ?? ''), '_-');
            $key = substr($key, 0, 48);
            if ($key === '') continue;

            if (isset($normalized[$key])) {
                continue;
            }

            if (isset($defaultMap[$key])) {
                $normalized[$key] = ['key' => $key, 'enabled' => !empty($item['enabled'])];
                continue;
            }
            if ($customCount >= 40) continue;

            $label = trim(strip_tags((string)($item['label'] ?? '')));
            $label = mb_substr($label !== '' ? $label : ucfirst(str_replace('_', ' ', $key)), 0, 80);
            $normalized[$key] = [
                'key' => $key,
                'label' => $label,
                'enabled' => !empty($item['enabled']),
                'custom' => true,
            ];
            $customCount++;
        }
        if (empty($normalized)) $normalized['conversion'] = $defaultMap['conversion'];
        return array_values($normalized);
    }

    private static function getDefaultLanguages(): array {
        return [
            ['code' => 'uk', 'name' => 'Українська', 'enabled' => true],
            ['code' => 'en', 'name' => 'English', 'enabled' => true],
        ];
    }

    public static function getDefaultCookieBannerSettings(): array {
        return [
            'analytics_mode' => 'privacy_basic',
            'cookie_banner_enabled' => false,
            'cookie_banner_title' => '',
            'cookie_banner_text' => '',
            'cookie_accept_text' => '',
            'cookie_reject_text' => '',
            'cookie_policy_url' => '',
            'cookie_banner_position' => 'bottom_bar',
            'cookie_banner_theme' => 'auto',
            'cookie_banner_colors' => [
                'background' => '#ffffff',
                'text' => '#111827',
                'muted' => '#4b5563',
                'accent' => '#2563eb',
                'border' => '#e5e7eb',
            ],
            'cookie_banner_radius' => '10px',
            'cookie_banner_custom_css' => '',
        ];
    }

    public static function getDefaultBlogSettings(): array {
        return [
            'blog_pagination_alignment' => 'center',
            'blog_pagination_gap' => '8px',
            'blog_pagination_radius' => '8px',
            'blog_pagination_padding' => '8px 12px',
            'blog_pagination_font_size' => '14px',
            'blog_pagination_prev_text' => 'Prev',
            'blog_pagination_next_text' => 'Next',
            'blog_pagination_localized_labels_enabled' => false,
            'blog_pagination_labels' => [],
            'blog_pagination_colors' => [
                'background' => '#ffffff',
                'text' => '#111827',
                'active_background' => '#2563eb',
                'active_text' => '#ffffff',
                'border' => '#d1d5db',
                'disabled' => '#9ca3af',
            ],
            'blog_pagination_custom_css' => '',
        ];
    }

    private static function getFilePath() {
        return C_CONFIG_PATH . '/settings.php';
    }

    public static function normalizeLanguageCode(string $code): string {
        $code = trim(str_replace('_', '-', $code));
        if ($code === '') {
            return '';
        }

        $parts = explode('-', $code);
        if (count($parts) > 2) {
            return '';
        }

        $lang = strtolower($parts[0]);
        if (!preg_match('/^[a-z]{2}$/', $lang)) {
            return '';
        }

        if (count($parts) === 1) {
            return $lang;
        }

        $region = strtoupper($parts[1]);
        if (!preg_match('/^[A-Z]{2}$/', $region)) {
            return '';
        }

        return $lang . '-' . $region;
    }

    public static function isValidLanguageCode(string $code): bool {
        $normalized = self::normalizeLanguageCode($code);
        if ($normalized === '') {
            return false;
        }

        $lang = explode('-', $normalized)[0];
        return isset(self::$iso639_1[$lang]);
    }

    public static function sanitizeLanguages(array $languages, ?array $fallbackLanguages = null): array {
        $fallback = $fallbackLanguages ?? self::getDefaultLanguages();

        $normalized = [];
        $seen = [];

        foreach ($languages as $lang) {
            if (!is_array($lang)) {
                continue;
            }

            $name = trim((string)($lang['name'] ?? ''));
            $code = self::normalizeLanguageCode((string)($lang['code'] ?? ''));

            if ($name === '' || $code === '' || !self::isValidLanguageCode($code) || isset($seen[$code])) {
                continue;
            }

            $seen[$code] = true;
            $normalized[] = [
                'code' => $code,
                'name' => $name,
                'enabled' => !empty($lang['enabled']),
            ];
        }

        if (empty($normalized)) {
            $normalized = self::sanitizeLanguages($fallback, self::getDefaultLanguages());
        }

        $firstEnabledIndex = null;
        foreach ($normalized as $idx => $lang) {
            if (!empty($lang['enabled'])) {
                $firstEnabledIndex = $idx;
                break;
            }
        }

        if ($firstEnabledIndex === null) {
            $normalized[0]['enabled'] = true;
        } elseif ($firstEnabledIndex > 0) {
            $primary = $normalized[$firstEnabledIndex];
            unset($normalized[$firstEnabledIndex]);
            array_unshift($normalized, $primary);
            $normalized = array_values($normalized);
        }

        return $normalized;
    }

    public static function load() {
        if (self::$settings !== null) {
            return self::$settings;
        }

        $file = self::getFilePath();
        $data = read_json_file($file);

        if (!isset($data['site_config_version'])) {
            $data['site_config_version'] = isset($data['version']) && is_scalar($data['version'])
                ? (string)$data['version']
                : '1.0.0';
        }

        if (!isset($data['editors']) || !is_array($data['editors'])) {
            $data['editors'] = [];
        }

        $initialLanguages = (isset($data['languages']) && is_array($data['languages'])) ? $data['languages'] : self::getDefaultLanguages();
        $data['languages'] = self::sanitizeLanguages($initialLanguages, self::getDefaultLanguages());

        if (!isset($data['site_name'])) {
            $data['site_name'] = 'Clipon CMS';
        }

        if (!isset($data['site_description'])) {
            $data['site_description'] = '';
        }

        if (!isset($data['site_email'])) {
            $data['site_email'] = '';
        }

        if (!isset($data['site_url'])) {
            $data['site_url'] = '';
        }

        if (!isset($data['analytics_retention'])) {
            $data['analytics_retention'] = 36;
        }
        $data = self::sanitizeAnalyticsBotFilterSettings($data);
        $data = self::sanitizeCookieBannerSettings($data);
        $data = self::sanitizeBlogSettings($data);

        if (!isset($data['history_retention_days'])) {
            $data['history_retention_days'] = 90;
        }

        if (!isset($data['enable_funnels'])) {
            $data['enable_funnels'] = true;
        }

        if (!isset($data['enable_attribution'])) {
            $data['enable_attribution'] = true;
        }

        $data['conversion_types'] = self::sanitizeConversionTypes(
            array_key_exists('conversion_types', $data) ? $data['conversion_types'] : self::getDefaultConversionTypes()
        );

        if (!isset($data['custom_conversion_events']) || !is_array($data['custom_conversion_events'])) {
            $data['custom_conversion_events'] = [];
        }

        if (!isset($data['powered_by_theme']) || !in_array($data['powered_by_theme'], ['light', 'dark'], true)) {
            $data['powered_by_theme'] = 'light';
        }

        if (!isset($data['powered_by_hidden'])) {
            $data['powered_by_hidden'] = false;
        }
        $data['powered_by_hidden'] = !empty($data['powered_by_hidden']);

        $data['editors']['page'] = 'tiptap';
        $data['editors']['blog'] = 'tiptap';

        self::$settings = $data;
        return self::$settings;
    }

    public static function save(array $data) {
        if (!isset($data['site_config_version'])) {
            $data['site_config_version'] = isset($data['version']) && is_scalar($data['version'])
                ? (string)$data['version']
                : '1.0.0';
        }
        unset($data['version']);

        if (!isset($data['languages']) || !is_array($data['languages'])) {
            $data['languages'] = self::getDefaultLanguages();
        }
        $data['languages'] = self::sanitizeLanguages($data['languages'], self::getDefaultLanguages());
        $data = self::sanitizeAnalyticsBotFilterSettings($data);
        $data = self::sanitizeCookieBannerSettings($data);
        $data = self::sanitizeBlogSettings($data);
        $data['conversion_types'] = self::sanitizeConversionTypes($data['conversion_types'] ?? null);
        if (!isset($data['powered_by_theme']) || !in_array($data['powered_by_theme'], ['light', 'dark'], true)) {
            $data['powered_by_theme'] = 'light';
        }
        $data['powered_by_hidden'] = !empty($data['powered_by_hidden']);
        self::$settings = $data;
        $file = self::getFilePath();
        write_json_file($file, self::$settings);
    }

    public static function get(string $key, $default = null) {
        $settings = self::load();
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public static function sanitizeCookieBannerSettings(array $data): array {
        $defaults = self::getDefaultCookieBannerSettings();
        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = $value;
            }
        }

        $data['analytics_mode'] = in_array(($data['analytics_mode'] ?? ''), ['privacy_basic', 'full_with_consent'], true)
            ? $data['analytics_mode']
            : 'privacy_basic';
        $data['cookie_banner_enabled'] = !empty($data['cookie_banner_enabled']);

        foreach (['cookie_banner_title', 'cookie_banner_text', 'cookie_accept_text', 'cookie_reject_text', 'cookie_policy_url', 'cookie_banner_custom_css'] as $key) {
            $data[$key] = is_string($data[$key] ?? null) ? trim($data[$key]) : '';
        }

        $data['cookie_banner_position'] = in_array(($data['cookie_banner_position'] ?? ''), ['bottom_bar', 'bottom_right'], true)
            ? $data['cookie_banner_position']
            : 'bottom_bar';
        $data['cookie_banner_theme'] = in_array(($data['cookie_banner_theme'] ?? ''), ['light', 'dark', 'auto'], true)
            ? $data['cookie_banner_theme']
            : 'auto';

        $colors = is_array($data['cookie_banner_colors'] ?? null) ? $data['cookie_banner_colors'] : [];
        foreach ($defaults['cookie_banner_colors'] as $name => $fallback) {
            $value = is_string($colors[$name] ?? null) ? trim($colors[$name]) : '';
            $colors[$name] = preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) ? $value : $fallback;
        }
        $data['cookie_banner_colors'] = $colors;

        $radius = is_string($data['cookie_banner_radius'] ?? null) ? trim($data['cookie_banner_radius']) : '';
        $data['cookie_banner_radius'] = preg_match('/^[0-9]{1,3}(px|rem|em)$/', $radius) ? $radius : '10px';

        return $data;
    }

    public static function sanitizeBlogSettings(array $data): array {
        $defaults = self::getDefaultBlogSettings();
        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = $value;
            }
        }

        $data['blog_pagination_alignment'] = in_array(($data['blog_pagination_alignment'] ?? ''), ['left', 'center', 'right'], true)
            ? $data['blog_pagination_alignment']
            : 'center';

        foreach (['blog_pagination_gap', 'blog_pagination_radius', 'blog_pagination_font_size'] as $key) {
            $value = is_string($data[$key] ?? null) ? trim($data[$key]) : '';
            $data[$key] = preg_match('/^[0-9]{1,3}(px|rem|em)$/', $value) ? $value : $defaults[$key];
        }

        $padding = is_string($data['blog_pagination_padding'] ?? null) ? trim($data['blog_pagination_padding']) : '';
        $data['blog_pagination_padding'] = preg_match('/^[0-9]{1,3}(px|rem|em)(\s+[0-9]{1,3}(px|rem|em)){0,3}$/', $padding)
            ? $padding
            : $defaults['blog_pagination_padding'];

        foreach (['blog_pagination_prev_text', 'blog_pagination_next_text', 'blog_pagination_custom_css'] as $key) {
            $data[$key] = is_string($data[$key] ?? null) ? trim($data[$key]) : '';
        }
        $data['blog_pagination_localized_labels_enabled'] = !empty($data['blog_pagination_localized_labels_enabled']);
        if ($data['blog_pagination_prev_text'] === '') {
            $data['blog_pagination_prev_text'] = $defaults['blog_pagination_prev_text'];
        }
        if ($data['blog_pagination_next_text'] === '') {
            $data['blog_pagination_next_text'] = $defaults['blog_pagination_next_text'];
        }

        $labels = is_array($data['blog_pagination_labels'] ?? null) ? $data['blog_pagination_labels'] : [];
        $normalizedLabels = [];
        foreach ($labels as $lang => $labelSet) {
            $lang = self::normalizeLanguageCode((string)$lang);
            if ($lang === '' || !self::isValidLanguageCode($lang) || !is_array($labelSet)) {
                continue;
            }
            $prev = is_string($labelSet['prev'] ?? null) ? trim($labelSet['prev']) : '';
            $next = is_string($labelSet['next'] ?? null) ? trim($labelSet['next']) : '';
            if ($prev === '' && $next === '') {
                continue;
            }
            $normalizedLabels[$lang] = [
                'prev' => $prev !== '' ? $prev : $data['blog_pagination_prev_text'],
                'next' => $next !== '' ? $next : $data['blog_pagination_next_text'],
            ];
        }
        $data['blog_pagination_labels'] = $normalizedLabels;

        $colors = is_array($data['blog_pagination_colors'] ?? null) ? $data['blog_pagination_colors'] : [];
        foreach ($defaults['blog_pagination_colors'] as $name => $fallback) {
            $value = is_string($colors[$name] ?? null) ? trim($colors[$name]) : '';
            $colors[$name] = preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) ? $value : $fallback;
        }
        $data['blog_pagination_colors'] = $colors;

        return $data;
    }

    public static function sanitizeAnalyticsBotFilterSettings(array $data): array {
        $data['analytics_bot_allowlist'] = AnalyticsBotFilter::sanitizePatterns($data['analytics_bot_allowlist'] ?? []);
        $data['analytics_bot_denylist'] = AnalyticsBotFilter::sanitizePatterns($data['analytics_bot_denylist'] ?? []);
        $data['analytics_bot_filter_debug'] = !empty($data['analytics_bot_filter_debug']);
        return $data;
    }

    public static function getConversionTypes(): array {
        $settings = self::load();
        return self::sanitizeConversionTypes($settings['conversion_types'] ?? []);
    }

    public static function getLanguages(): array {
        $settings = self::load();
        $settings['languages'] = self::sanitizeLanguages($settings['languages'] ?? [], self::getDefaultLanguages());
        
        // If Multilang PRO module is not active, return only primary language
        if (!class_exists('ModuleManager') || !ModuleManager::isProAvailable('multilang')) {
            if (!empty($settings['languages'])) {
                $settings['languages'] = [array_shift($settings['languages'])];
            }
        }
        
        self::$settings['languages'] = $settings['languages'];
        return $settings['languages'];
    }

    public static function getEditor(string $context): string {
        return 'tiptap';
    }

    public static function updateEditors(string $blogEditor) {
        $settings = self::load();
        $settings['editors']['page'] = 'tiptap';
        $settings['editors']['blog'] = 'tiptap';
        self::save($settings);
    }
}
