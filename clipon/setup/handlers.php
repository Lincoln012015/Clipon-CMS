<?php
// Step 6 Logic: Final Settings
// Use has() to detect submitted buttons reliably (value-less buttons may submit empty strings)
function setup_handle_request(int $currentStep, array $availableLangs, string $setupLang, array $trans): string {
    global $request, $session;

    $error = '';

    $stateChangingActions = ['set_lang', 'register_admin', 'set_mode', 'run_migration', 'finish_setup'];
    foreach ($stateChangingActions as $action) {
        if ($request->has($action) && !Csrf::validate($request->post('csrf_token', ''))) {
            http_response_code(403);
            exit(htmlspecialchars($trans['error_invalid_csrf'] ?? 'Invalid CSRF token.', ENT_QUOTES, 'UTF-8'));
        }
    }

    if ($currentStep === 1 && $request->post('set_lang')) {
        $setLang = $request->post('set_lang');
        if (in_array($setLang, $availableLangs)) {
            $session->set('setup_lang', $setLang);
            header('Location: setup.php?step=2');
            exit;
        }
    }

    if ($currentStep === 6 && $request->has('finish_setup')) {
    $siteName = trim($request->string('site_name', '')) ?: 'My CMS Site';
    $siteUrl = rtrim(trim($request->string('site_url', '')), '/');
    
    // Пріоритет мови:
    // 1. Мова, вписана вручну (якщо користувач обрав "Інша")
    // 2. Мова, обрана зі списку
    // 3. Мова зі звіту міграції
    // 4. Мова інсталятора
    $customLang = trim($request->string('custom_lang_code', ''));
    $selectedLang = $request->string('site_lang', '');

    // Validate custom language input properly instead of naive substr truncation.
    if ($selectedLang === 'other' && $customLang !== '') {
        $normalized = Settings::normalizeLanguageCode($customLang);
        if ($normalized !== '' && Settings::isValidLanguageCode($normalized)) {
            $finalLang = $normalized;
        } else {
            // Invalid custom code — fallback to migrated/setup language
            if (class_exists('Log')) {
                Log::warning('Invalid custom language code provided in setup; using default.');
            }
            $finalLang = $session->get('migrated_primary_lang') ?: $session->get('setup_lang', 'uk');
        }
    } else {
        $finalLang = $selectedLang ?: ($session->get('migrated_primary_lang') ?: $session->get('setup_lang', 'uk'));
    }
    
    // Normalize language name
    $langName = $finalLang;
    $customName = trim($request->string('custom_lang_name', ''));
    
    $langNames = [
        'uk' => 'Українська',
        'en' => 'English',
        'ru' => 'Русский',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'es' => 'Español',
        'pl' => 'Polski'
    ];
    
    $langName = ($selectedLang === 'other' && !empty($customName))
        ? $customName
        : ($langNames[$finalLang] ?? strtoupper($finalLang));

    $settings = [
        'site_name' => $siteName,
        'site_url' => $siteUrl,
        'language' => $finalLang,
        'installed_at' => date('Y-m-d H:i:s'),
        'site_config_version' => '1.0.0',
        'powered_by_theme' => 'light',
        'powered_by_hidden' => false,
        'languages' => [
            ['code' => $finalLang, 'name' => $langName, 'enabled' => true]
        ]
    ];
    
    Settings::save($settings);

    if (!setup_finalize_homepage_routes()) {
        http_response_code(500);
        exit(htmlspecialchars($trans['system_error'] ?? 'System error: route map rebuild failed.', ENT_QUOTES, 'UTF-8'));
    }
    
    $session->set('setup_finished', true);
    $session->remove('files_to_migrate');
    $session->remove('migration_results');
    $session->remove('markup_picker_prepared_files');
    header('Location: setup.php?step=7');
    exit;
}

// Step 5 Logic: Tagging & Migration Execution
// Use has() to detect submitted buttons reliably
    if ($currentStep === 5 && $request->has('run_migration')) {
    $migrationReport = $session->get('migration_report', ['errors' => [], 'warnings' => []]);

    $taggingEnabled = false;
    $assetsEnabled = $request->bool('assets_enabled');
    $skipDomForPhp = $request->bool('skip_dom_for_php');
    $processLinks = $request->bool('process_links');
    $overwritePageData = $request->bool('overwrite_page_data');
    $dryRunOnly = $request->bool('dry_run_only');
    $backupEnabled = $request->bool('backup_enabled');
    $deleteOriginals = $request->bool('delete_originals');

    $config = [
        'tags' => [],
        'exclude' => [],
        'add_ids' => false,
        'assets_base_path' => setup_normalize_base_path($request->string('assets_base_path', ''))
    ];

    if ($deleteOriginals && !$backupEnabled) {
        $backupEnabled = true;
        setup_report_warning($migrationReport, 'Видалення оригіналів потребує backup. Backup автоматично увімкнено.');
    }
    
    $engineConfig = MigrationEngine::defaultConfig();
    $engineConfig['tagging']['enabled'] = $taggingEnabled;
    $engineConfig['tagging']['tags'] = $config['tags'];
    $engineConfig['tagging']['exclude_sections'] = $config['exclude'];
    $engineConfig['tagging']['add_ids'] = $config['add_ids'];
    $engineConfig['tagging']['skip_files'] = array_values(array_unique(array_filter(
        $session->get('markup_picker_prepared_files', []),
        static fn($file) => is_string($file) && $file !== ''
    )));
    $engineConfig['scan']['skip_dom_for_php'] = $skipDomForPhp;
    $engineConfig['assets']['enabled'] = $assetsEnabled;
    $engineConfig['assets']['process_links'] = $processLinks;
    $engineConfig['assets']['base_path'] = $config['assets_base_path'];
    $engineConfig['overwrite']['page_data'] = $overwritePageData;
    $engineConfig['backup']['enabled'] = $backupEnabled;
    $engineConfig['scan']['delete_originals'] = $deleteOriginals;
    $engineConfig['dry_run'] = $dryRunOnly;
    $engineConfig['transactional']['enabled'] = true;
    $engineConfig['transactional']['auto_rollback_on_error'] = true;

    $engine = new MigrationEngine($engineConfig);
    $result = $engine->run();

    if (!empty($result['detected_langs'])) {
        $session->set('migrated_primary_lang', $result['detected_langs'][0]);
    }

    if (!empty($migrationReport['warnings'])) {
        $result['warnings'] = array_values(array_unique(array_merge($migrationReport['warnings'], $result['warnings'] ?? [])));
    }

    $session->set('migration_report', $result);
    $session->set('migration_preview', [
        'candidates' => [
            'files' => array_map(static fn($f) => $f['name'] ?? '', $result['plan']['files'] ?? []),
            'dirs' => array_map(static fn($d) => $d['name'] ?? '', $result['plan']['dirs'] ?? []),
        ],
        'plan' => $result['plan'] ?? ['files' => [], 'dirs' => [], 'risks' => []],
        'stages' => $result['stages'] ?? [],
    ]);

    if (($result['status'] ?? '') === 'success' && !$dryRunOnly) {
        header('Location: setup.php?step=6');
        exit;
    }

    if (($result['status'] ?? '') === 'dry-run') {
        header('Location: setup.php?step=5&dry_run_done=1');
        exit;
    }

    if (($result['status'] ?? '') === 'failed') {
        header('Location: setup.php?step=5&migration_done=1');
        exit;
    }
}

// Step 4 Logic: Mode Selection
// Use has() to detect submitted buttons reliably
    if ($currentStep === 4 && $request->has('set_mode')) {
    $mode = $request->post('set_mode');
    $session->set('setup_mode', $mode);
    
    if ($mode === 'smart') {
        $previewConfig = MigrationEngine::defaultConfig();
        $previewConfig['dry_run'] = true;
        $engine = new MigrationEngine($previewConfig);
        $plan = $engine->buildPlan();

        $session->set('migration_preview', $plan);
        $session->set('migration_report', [
            'status' => 'preview',
            'errors' => $plan['errors'] ?? [],
            'warnings' => $plan['warnings'] ?? [],
            'error_matrix' => [],
            'summary' => [
                'scanned_files' => count($plan['candidates']['files'] ?? []),
                'scanned_dirs' => count($plan['candidates']['dirs'] ?? []),
            ],
            'stages' => $plan['stages'] ?? [],
            'paths' => ['report_dir' => null, 'backup_root' => null],
            'plan' => $plan['plan'] ?? ['files' => [], 'dirs' => [], 'risks' => []],
            'items' => ['files' => [], 'dirs' => []],
        ]);
        header('Location: setup.php?step=5');
        exit;
    } else {
        header('Location: setup.php?step=6'); // Skip tagging if manual
        exit;
    }
}

// Step 3 Logic: Register Admin
    if ($currentStep === 3 && $request->has('register_admin')) {
    $login = trim($request->string('login', ''));
    $password = $request->string('password', '');
    $confirm = $request->string('confirm', '');
    $name = trim($request->string('name', ''));
    $userService = new UserService();

    if (empty($login) || empty($password) || empty($name)) {
        $error = $trans['error_empty'];
    } elseif (!UserService::isValidLogin($login)) {
        $error = $trans['error_login_format'] ?? 'Invalid login format.';
    } elseif (!UserService::isValidPassword($password)) {
        $error = $trans['error_pass_len'];
    } elseif ($password !== $confirm) {
        $error = $trans['error_pass_match'];
    } else {
        if ($userService->createAdmin($login, $password, $name)) {
            $session->set('admin_login', UserService::normalizeLogin($login)); // Store for migration author info
            header('Location: setup.php?step=4');
            exit;
        }

        $error = $userService->hasNormalizationConflict()
            ? ($trans['error_login_conflict'] ?? 'Users file contains a login conflict.')
            : ($trans['error_user_exists'] ?? 'User already exists.');
    }
}

// Step 2 Logic: Perform checks
    return $error;
}
