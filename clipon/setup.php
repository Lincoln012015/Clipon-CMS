<?php
require_once __DIR__ . '/bootstrap.php';

$totalSteps = 7;
$currentStep = $request->int('step', 1);
if ($currentStep < 1 || $currentStep > $totalSteps) {
    $currentStep = 1;
}

// Security check: Prevent access if CMS is already installed
$settingsFile = C_CONFIG_PATH . '/settings.php';
if (file_exists($settingsFile)) {
    $settings = read_json_file($settingsFile);
    // Check if settings contain essential configuration (site_url indicates installation is complete)
    $canShowCompletionStep = $currentStep === 7 && $session->get('setup_finished') === true;
    if (!empty($settings) && isset($settings['site_url']) && !empty($settings['site_url']) && !$canShowCompletionStep) {
        header('Content-Type: text/html; charset=utf-8');
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Access Denied</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #333; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f4f7f6; }
                .container { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 500px; text-align: center; }
                h1 { color: #e74c3c; margin-top: 0; }
                p { margin-bottom: 20px; }
                a { color: #3498db; text-decoration: none; }
                a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Access Denied</h1>
                <p>Clipon CMS is already installed and configured.</p>
                <p>For security reasons, the setup wizard cannot be accessed on an installed system.</p>
                <p><a href="<?= htmlspecialchars(c_site_url() ?: '/') ?>">Go to your site</a></p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Load languages for selection
$langDir = __DIR__ . '/lang';
$availableLangs = [];
if (is_dir($langDir)) {
    foreach (glob($langDir . '/*.php') as $langFile) {
        $code = basename($langFile, '.php');
        $availableLangs[] = $code;
    }
}
sort($availableLangs);
if (empty($availableLangs)) {
    $availableLangs = ['uk'];
}

// Current setup language (default uk)
$setupLang = $session->get('setup_lang', 'uk');
if (!in_array($setupLang, $availableLangs, true)) {
    $setupLang = in_array('uk', $availableLangs, true) ? 'uk' : $availableLangs[0];
    $session->set('setup_lang', $setupLang);
}

// Load translations from system language files
$langFile = $langDir . '/' . $setupLang . '.php';
$trans = [];
if (file_exists($langFile)) {
    $allTrans = require $langFile;
    $trans = $allTrans['setup'] ?? [];
}

// Fallback logic
if (empty($trans) || !isset($trans['title'])) {
    $enLangFile = $langDir . '/en.php';
    if (file_exists($enLangFile)) {
        $enTrans = require $enLangFile;
        $trans = array_merge($enTrans['setup'] ?? [], $trans);
    }
}

$setupDefaults = require __DIR__ . '/setup/defaults.php';
$trans = array_merge($setupDefaults, $trans);

require_once __DIR__ . '/lib/JsonStorage.php';
require_once __DIR__ . '/lib/MigrationEngine.php';

Csrf::init();

require_once __DIR__ . '/setup/helpers.php';
require_once __DIR__ . '/setup/handlers.php';

$error = setup_handle_request($currentStep, $availableLangs, $setupLang, $trans);

require __DIR__ . '/setup/view_state.php';

?>
<!DOCTYPE html>
<html lang="<?= $setupLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $trans['title'] ?></title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/setup.css">

</head>
<body class="setup-page">
    <div class="setup-shell">
        <aside class="setup-aside" aria-label="Setup progress">
            <div class="setup-brand">Clipon CMS</div>
            <div class="setup-progress-card">
                <div class="setup-progress-label">
                    <span><?= htmlspecialchars($trans['title']) ?></span>
                    <span><?= (int)round(($currentStep / $totalSteps) * 100) ?>%</span>
                </div>
                <div class="setup-progress-track">
                    <div class="setup-progress-bar" style="width: <?= (int)round(($currentStep / $totalSteps) * 100) ?>%;"></div>
                </div>
            </div>
            <ol class="setup-steps">
                <?php foreach ($setupStepTitles as $stepNumber => $stepTitle): ?>
                    <?php
                    $stepClass = $stepNumber === $currentStep ? 'is-active' : ($stepNumber < $currentStep ? 'is-complete' : 'is-disabled');
                    $stepHref = $stepNumber <= $currentStep ? 'setup.php?step=' . $stepNumber : '#';
                    ?>
                    <li>
                        <a class="setup-step-link <?= $stepClass ?>" href="<?= htmlspecialchars($stepHref) ?>">
                            <span class="setup-step-number"><?= $stepNumber < $currentStep ? '✓' : (int)$stepNumber ?></span>
                            <span class="setup-step-title"><?= htmlspecialchars($stepTitle) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </aside>
        <main class="setup-main">
            <div class="setup-main-inner">
    <div class="wizard-card <?= in_array($currentStep, [4, 5, 6], true) ? 'is-wide' : '' ?>">
        <header class="step-header">
            <h1><?= htmlspecialchars($setupStepTitles[$currentStep] ?? $trans['title']) ?></h1>
            <div class="step-info"><?= str_replace(['{step}', '{total}'], [$currentStep, $totalSteps], $trans['step_of']) ?></div>
        </header>
        
        <?php
        $setupStepPartials = [
            1 => 'step_language.php',
            2 => 'step_checks.php',
            3 => 'step_admin.php',
            4 => 'step_mode.php',
            5 => 'step_migration.php',
            6 => 'step_final.php',
            7 => 'step_complete.php',
        ];
        include __DIR__ . '/setup/partials/' . $setupStepPartials[$currentStep];
        ?>
    </div>
            </div>
        </main>
    </div>
</body>
</html>
