<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/History.php';
require_once __DIR__ . '/../lib/AnalyticsStorage.php';
require_once __DIR__ . '/../lib/PrimaryLanguageMigrator.php';
require_once __DIR__ . '/../lib/ContentLocaleInitializer.php';

if (!$session->has('user')) {
    header('Location: login.php');
    exit;
}

$user = is_string($session->get('user')) ? $session->get('user') : 'unknown';
$role = is_string($session->get('role')) ? $session->get('role') : 'unknown';

requireAdmin('index.php');

$settings = Settings::load();
if (class_exists('License') && !License::isValid() && !empty($settings['powered_by_hidden'])) {
    $settings['powered_by_hidden'] = false;
    Settings::save($settings);
}
$conversionTypes = Settings::getConversionTypes();
$languages = Settings::getLanguages();
$proAnalyticsAvailable = class_exists('ProAnalyticsPolicy') && ProAnalyticsPolicy::isAvailable();
$enableFunnels = !empty($settings['enable_funnels']);
$enableAttribution = !empty($settings['enable_attribution']);
$historyRetentionDays = isset($settings['history_retention_days']) ? (int)$settings['history_retention_days'] : 90;
$geoIpUpdater = new AnalyticsGeoIpUpdater();
$geoIpStatus = $geoIpUpdater->status();

function loadBotFilterLog(int $limitDays = 30): array {
    $storage = new AnalyticsStorage(C_DATA_PATH . '/analytics');
    $files = $storage->listDataFiles();
    rsort($files);

    $rows = [];
    $reasons = [];
    $total = 0;
    $daysWithData = 0;

    foreach ($files as $file) {
        $date = basename($file, '.php');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            continue;
        }

        $data = $storage->loadData($file);
        $botFilter = is_array($data['bot_filter'] ?? null) ? $data['bot_filter'] : [];
        $dayReasons = is_array($botFilter['reasons'] ?? null) ? $botFilter['reasons'] : [];
        if (empty($dayReasons)) {
            continue;
        }

        $daysWithData++;
        foreach ($dayReasons as $reason => $count) {
            $count = (int)$count;
            if ($count <= 0) {
                continue;
            }

            $reason = is_string($reason) && $reason !== '' ? $reason : 'unknown';
            $rows[] = [
                'date' => $date,
                'reason' => $reason,
                'count' => $count,
            ];
            $reasons[$reason] = ($reasons[$reason] ?? 0) + $count;
            $total += $count;
        }

        if ($daysWithData >= $limitDays) {
            break;
        }
    }

    arsort($reasons);
    usort($rows, function(array $a, array $b): int {
        $dateCompare = strcmp((string)$b['date'], (string)$a['date']);
        return $dateCompare !== 0 ? $dateCompare : ((int)$b['count'] <=> (int)$a['count']);
    });

    return [
        'total' => $total,
        'reasons' => $reasons,
        'rows' => $rows,
        'days_with_data' => $daysWithData,
        'limit_days' => $limitDays,
    ];
}

$botFilterLog = loadBotFilterLog();

function normalizeLanguagesInput($rawLanguages, array $fallbackLanguages): array {
    if (!is_array($rawLanguages)) {
        return Settings::sanitizeLanguages($fallbackLanguages, $fallbackLanguages);
    }

    return Settings::sanitizeLanguages($rawLanguages, $fallbackLanguages);
}

function resolvePrimaryLanguageCode(array $languages): string {
    foreach ($languages as $lang) {
        $code = Settings::normalizeLanguageCode((string)($lang['code'] ?? ''));
        if (!empty($lang['enabled']) && $code !== '' && Settings::isValidLanguageCode($code)) {
            return $code;
        }
    }

    $fallback = Settings::normalizeLanguageCode((string)(Settings::load()['language'] ?? 'en'));
    if ($fallback === '' || !Settings::isValidLanguageCode($fallback)) {
        $fallback = 'en';
    }

    return $fallback;
}

if ($request->isPost()) {
    $csrfToken = (string)$request->post('csrf_token', '');
    if (!Csrf::validate($csrfToken)) {
        Flash::error(__('error_invalid_csrf'));
        header('Location: settings.php');
        exit;
    }

    $oldLanguagesSnapshot = $languages;
    $addedLanguageCodes = [];
    $languageMigrationResult = null;
    $shouldRunPrimaryMigration = false;

    if ($request->post('activate_license') !== null) {
        $key = $request->string('license_key');
        $res = License::activate($key);
        if ($res['success']) {
            Flash::success(__('settings_license_activated_success') ?? 'Ліцензію успішно активовано!');
        } else {
            $errorCode = (string)($res['error'] ?? '');
            $errorMessages = [
                'invalid_key' => __('settings_license_error_invalid_key') ?? 'Невірний або неактивний ліцензійний ключ.',
                'invalid_input' => __('settings_license_error_invalid_input') ?? 'Невірні параметри запиту ліцензії.',
                'invalid_key_format' => __('settings_license_error_invalid_key_format') ?? 'Невірний формат ліцензійного ключа.',
                'domain_mismatch' => __('settings_license_error_domain_mismatch') ?? 'Ключ прив\'язаний до іншого домену.',
                'license_not_bound' => __('settings_license_error_license_not_bound') ?? 'Ключ ще не прив\'язаний до домену. Зверніться до підтримки.',
                'rate_limited' => __('settings_license_error_rate_limited') ?? 'Забагато запитів. Спробуйте ще раз трохи пізніше.',
                'No key provided' => __('settings_license_error_no_key') ?? 'Введіть ліцензійний ключ.',
                'License server unreachable' => __('settings_license_error_server_unreachable') ?? 'Сервер ліцензій тимчасово недоступний.',
                'Invalid server payload format' => __('settings_license_error_invalid_payload') ?? 'Отримано некоректну відповідь сервера ліцензій.',
                'Server signature mismatch' => __('settings_license_error_signature_mismatch') ?? 'Помилка перевірки підпису відповіді сервера ліцензій.',
                'Invalid response from server' => __('settings_license_error_invalid_response') ?? 'Сервер ліцензій повернув некоректну відповідь.',
            ];

            if (isset($errorMessages[$errorCode])) {
                Flash::error($errorMessages[$errorCode]);
            } else {
                $unknownPattern = __('settings_license_error_unknown') ?? 'Помилка активації ліцензії: %s';
                $unknownDetail = $errorCode !== '' ? $errorCode : 'unknown';
                Flash::error(sprintf($unknownPattern, $unknownDetail));
            }
        }
        header('Location: settings.php');
        exit;
    }

    if ($request->post('save_powered_by') !== null) {
        $poweredByDesign = (string)$request->post('powered_by_design', 'light');
        if (!in_array($poweredByDesign, ['light', 'dark', 'disabled'], true)) {
            $poweredByDesign = 'light';
        }

        $settings['powered_by_hidden'] = $poweredByDesign === 'disabled' && License::isValid();
        if (in_array($poweredByDesign, ['light', 'dark'], true)) {
            $settings['powered_by_theme'] = $poweredByDesign;
        }
    }

    if ($request->post('save_general') !== null) {
        $settings['site_name'] = $request->post('site_name') ?? '';
        $settings['site_description'] = $request->post('site_description') ?? '';
        $settings['site_email'] = $request->post('site_email') ?? '';
        $settings['site_url'] = $request->post('site_url') ?? '';
    }

    if ($request->post('clear_analytics') !== null) {
        Analytics::clearAllData();
        $historyCleared = true;
    }

    if ($request->post('update_geoip') !== null) {
        $geoIpStatus = $geoIpUpdater->update(true);
        if (($geoIpStatus['status'] ?? '') === 'installed') {
            Flash::success(__('settings_geoip_update_success'));
        } else {
            $error = (string)($geoIpStatus['error'] ?? '');
            $localizedError = $error;
            if ($error === 'GeoIP download failed') {
                $localizedError = __('settings_geoip_error_download_failed');
            } elseif (strpos($error, 'GeoIP download failed:') === 0) {
                $detail = trim(substr($error, strlen('GeoIP download failed:')));
                $localizedError = sprintf(__('settings_geoip_error_download_failed_with_detail'), $detail);
            } elseif ($error === 'Failed to write GeoIP database') {
                $localizedError = __('settings_geoip_error_write_failed');
            }
            Flash::error($localizedError !== '' ? sprintf(__('settings_geoip_update_failed'), $localizedError) : __('settings_geoip_update_failed_generic'));
        }
        header('Location: settings.php');
        exit;
    }

    if ($request->post('save_analytics') !== null) {
        $settings['analytics_retention'] = (int)$request->post('analytics_retention', 36);
        $settings['analytics_bot_allowlist'] = AnalyticsBotFilter::sanitizePatterns($request->string('analytics_bot_allowlist', '', false));
        $settings['analytics_bot_denylist'] = AnalyticsBotFilter::sanitizePatterns($request->string('analytics_bot_denylist', '', false));
        $settings['analytics_bot_filter_debug'] = $request->bool('analytics_bot_filter_debug');
        $settings['enable_funnels'] = $proAnalyticsAvailable ? $request->bool('enable_funnels') : true;
        $settings['enable_attribution'] = $proAnalyticsAvailable ? $request->bool('enable_attribution') : true;
        $settings['analytics_mode'] = $request->post('analytics_mode', 'privacy_basic') === 'full_with_consent' ? 'full_with_consent' : 'privacy_basic';
        $settings['cookie_banner_enabled'] = $request->bool('cookie_banner_enabled');
        $settings['cookie_banner_title'] = $request->string('cookie_banner_title');
        $settings['cookie_banner_text'] = $request->string('cookie_banner_text');
        $settings['cookie_accept_text'] = $request->string('cookie_accept_text');
        $settings['cookie_reject_text'] = $request->string('cookie_reject_text');
        $settings['cookie_policy_url'] = $request->string('cookie_policy_url');
        $settings['cookie_banner_position'] = in_array($request->post('cookie_banner_position', 'bottom_bar'), ['bottom_bar', 'bottom_right'], true) ? $request->post('cookie_banner_position') : 'bottom_bar';
        $settings['cookie_banner_theme'] = in_array($request->post('cookie_banner_theme', 'auto'), ['light', 'dark', 'auto'], true) ? $request->post('cookie_banner_theme') : 'auto';
        $settings['cookie_banner_radius'] = $request->string('cookie_banner_radius', '10px');
        $settings['cookie_banner_custom_css'] = $request->string('cookie_banner_custom_css', '', false);
        $settings['cookie_banner_colors'] = [
            'background' => $request->string('cookie_banner_color_background', '#ffffff'),
            'text' => $request->string('cookie_banner_color_text', '#111827'),
            'muted' => $request->string('cookie_banner_color_muted', '#4b5563'),
            'accent' => $request->string('cookie_banner_color_accent', '#2563eb'),
            'border' => $request->string('cookie_banner_color_border', '#e5e7eb'),
        ];
        $settings = Settings::sanitizeAnalyticsBotFilterSettings($settings);
        $settings = Settings::sanitizeCookieBannerSettings($settings);

        $baseTypes = $conversionTypes;
        if (empty($baseTypes)) {
            $baseTypes = Settings::getConversionTypes();
        }

        $postedItems = $request->post('conversion_type_items', null);
        if (is_array($postedItems)) {
            $updated = Settings::sanitizeConversionTypes($postedItems);
        } else {
            // Backward compatibility with the previous checkbox-only form.
            $postedConversionTypes = $request->post('conversion_types', []);
            $postedConversionTypes = is_array($postedConversionTypes) ? $postedConversionTypes : [];
            $legacyItems = [];
            foreach ($baseTypes as $item) {
                $item['enabled'] = array_key_exists((string)($item['key'] ?? ''), $postedConversionTypes);
                $legacyItems[] = $item;
            }
            $updated = Settings::sanitizeConversionTypes($legacyItems);
        }
        $settings['conversion_types'] = $updated;
        $conversionTypes = $updated;

        unset($settings['custom_conversion_events']);
    }

    if ($request->post('save_blog') !== null) {
        $settings['blog_pagination_alignment'] = in_array($request->post('blog_pagination_alignment', 'center'), ['left', 'center', 'right'], true)
            ? $request->post('blog_pagination_alignment')
            : 'center';
        $settings['blog_pagination_gap'] = $request->string('blog_pagination_gap', '8px');
        $settings['blog_pagination_radius'] = $request->string('blog_pagination_radius', '8px');
        $settings['blog_pagination_padding'] = $request->string('blog_pagination_padding', '8px 12px');
        $settings['blog_pagination_font_size'] = $request->string('blog_pagination_font_size', '14px');
        $settings['blog_pagination_prev_text'] = $request->string('blog_pagination_prev_text', 'Prev');
        $settings['blog_pagination_next_text'] = $request->string('blog_pagination_next_text', 'Next');
        $settings['blog_pagination_localized_labels_enabled'] = $request->bool('blog_pagination_localized_labels_enabled');
        $postedPaginationLabels = $request->post('blog_pagination_labels', []);
        $settings['blog_pagination_labels'] = is_array($postedPaginationLabels) ? $postedPaginationLabels : [];
        $settings['blog_pagination_custom_css'] = $request->string('blog_pagination_custom_css', '', false);
        $settings['blog_pagination_colors'] = [
            'background' => $request->string('blog_pagination_color_background', '#ffffff'),
            'text' => $request->string('blog_pagination_color_text', '#111827'),
            'active_background' => $request->string('blog_pagination_color_active_background', '#2563eb'),
            'active_text' => $request->string('blog_pagination_color_active_text', '#ffffff'),
            'border' => $request->string('blog_pagination_color_border', '#d1d5db'),
            'disabled' => $request->string('blog_pagination_color_disabled', '#9ca3af'),
        ];
        $settings = Settings::sanitizeBlogSettings($settings);
    }

    if ($request->post('save_history') !== null) {
        $allowedRetentionDays = [0, 7, 14, 30, 60, 90, 180, 365];
        $postedRetention = (int)$request->post('history_retention_days', 90);
        if (!in_array($postedRetention, $allowedRetentionDays, true)) {
            $postedRetention = 90;
        }

        $settings['history_retention_days'] = $postedRetention;
        $historyRetentionDays = $postedRetention;
    }

    if ($request->post('clear_history_versions') !== null) {
        $history = new History();
        $deletedCount = $history->clearAllExceptCurrent();
        Flash::success(sprintf(__('settings_history_cleared'), (int)$deletedCount));
        header('Location: settings.php');
        exit;
    }

    if ($request->post('import_template_content') !== null) {
        $template = trim((string)$request->post('template', ''));
        $importer = new ContentTemplateImporter();
        $result = $importer->importForTemplate($template);

        if (!empty($result['ok'])) {
            $primary = (int)($result['primary_added'] ?? 0);
            $secondary = (int)($result['secondary_added'] ?? 0);
            $pages = (int)($result['pages_updated'] ?? 0);
            $kept = (int)($result['kept'] ?? 0);

            if ($primary === 0 && $secondary === 0) {
                Flash::success(__('settings_markup_import_none'));
            } else {
                Flash::success(sprintf(__('settings_markup_import_done'), $pages, $primary, $secondary, $kept));
            }
        } else {
            $error = (string)($result['error'] ?? __('system_error'));
            Flash::error(sprintf(__('settings_markup_import_failed'), $error));
        }

        header('Location: settings.php');
        exit;
    }

    if ($request->post('save_languages') !== null && $request->post('languages_json') !== null) {
        $decodedLanguages = json_decode((string)$request->post('languages_json', '[]'), true);
        $validatedLanguages = normalizeLanguagesInput($decodedLanguages, $languages);

        $oldPrimaryLang = resolvePrimaryLanguageCode($languages);
        $newPrimaryLang = resolvePrimaryLanguageCode($validatedLanguages);
        $addedLanguageCodes = ContentLocaleInitializer::detectAddedLanguageCodes($languages, $validatedLanguages);

        $shouldRunPrimaryMigration = $request->bool('migrate_primary_content') && ($oldPrimaryLang !== $newPrimaryLang);

        $settings['languages'] = $validatedLanguages;
        $languages = $validatedLanguages;
    }

    Settings::save($settings);

    if ($request->post('save_languages') !== null && !empty($addedLanguageCodes)) {
        $localeInitializer = new ContentLocaleInitializer();
        $localeInitializer->initialize($addedLanguageCodes);
    }

    if ($shouldRunPrimaryMigration) {
        $oldPrimary = resolvePrimaryLanguageCode($oldLanguagesSnapshot);
        $newPrimary = resolvePrimaryLanguageCode($languages);

        $migrator = new PrimaryLanguageMigrator();
        $languageMigrationResult = $migrator->migrate($oldPrimary, $newPrimary, false);

        if (!($languageMigrationResult['success'] ?? false)) {
            $settings['languages'] = $oldLanguagesSnapshot;
            Settings::save($settings);
            $languages = $oldLanguagesSnapshot;

            /** @var object $routeMap */
            $routeMap = registry()->get('route_map');
            $routeMap->clearCache();
            $routeMap->rebuild(C_CONTENT_PATH . '/pages/');

            $errorText = (string)($languageMigrationResult['error'] ?? __('system_error'));
            Flash::error('Primary language migration failed: ' . $errorText);
            header('Location: settings.php');
            exit;
        }
    }

    if ($request->post('save_languages') !== null) {
        /** @var object $routeMap */
        $routeMap = registry()->get('route_map');
        $routeMap->rebuild(C_CONTENT_PATH . '/pages/');

        if (is_array($languageMigrationResult) && !empty($languageMigrationResult['changed'])) {
            $reportPath = (string)($languageMigrationResult['report_path'] ?? '');
            $suffix = $reportPath !== '' ? ' Report: ' . $reportPath : '';
            Flash::success('Primary language content migration completed.' . $suffix);
            header('Location: settings.php');
            exit;
        }
    }
    
    $saved = true;

    $proAnalyticsAvailable = class_exists('ProAnalyticsPolicy') && ProAnalyticsPolicy::isAvailable();
    $enableFunnels = !empty($settings['enable_funnels']);
    $enableAttribution = !empty($settings['enable_attribution']);
    $geoIpStatus = $geoIpUpdater->status();
}
?>

<!DOCTYPE html>
<html lang="<?= Translation::getLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('settings_title') ?> - Admin</title>
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin.css?v=17">
    <script src="<?= C_ASSETS_URL ?>/vendor/sortablejs/Sortable.min.js"></script>
</head>
<body>
    <div class="admin-container">
        <?php include __DIR__ . '/nav.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1><?= __('settings_h1') ?></h1>
                    <p><?= __('welcome') ?>, <?= htmlspecialchars($user) ?></p>
                </div>
            </header>

            <?php if ($msg = Flash::pull('msg')): ?>
                <div class="alert <?= $msg['type'] ?>"><?= $msg['message'] ?></div>
            <?php endif; ?>

            <?php if (!empty($saved) && empty($historyCleared)): ?>
                <div class="alert success"><?= __('settings_saved') ?></div>
            <?php endif; ?>
            <?php if (!empty($historyCleared)): ?>
                <div class="alert success"><?= __('settings_analytics_cleared') ?></div>
            <?php endif; ?>

            <?php include __DIR__ . '/partials/settings_tabs.php'; ?>
        </main>
    </div>

    <?php include __DIR__ . '/partials/settings_admin_config.php'; ?>
    <script src="<?= C_ASSETS_URL ?>/js/admin-shared.js?v=2"></script>
    <script src="<?= C_ASSETS_URL ?>/js/admin-settings.js?v=2"></script>
</body>
</html>
