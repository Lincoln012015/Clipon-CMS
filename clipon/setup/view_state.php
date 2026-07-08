<?php
// Step 2 Logic: Perform checks
$checks = [];
if ($currentStep === 2) {
    // 1. PHP Version
    $checks['php'] = [
        'label' => $trans['check_php'],
        'status' => version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'fail',
        'value' => PHP_VERSION
    ];

    // 2. Extensions
    $requiredExts = ['mbstring', 'dom', 'json', 'libxml', 'fileinfo', 'session', 'openssl'];
    $missingExts = [];
    foreach ($requiredExts as $ext) {
        if (!extension_loaded($ext)) $missingExts[] = $ext;
    }
    $checks['extensions'] = [
        'label' => $trans['check_ext'],
        'status' => empty($missingExts) ? 'ok' : 'fail',
        'value' => empty($missingExts)
            ? $trans['extensions_all_loaded']
            : ($trans['extensions_missing_prefix'] . ': ' . implode(', ', $missingExts))
    ];

    // 3. Writable directories
    $dirsToCheck = [
        'config' => C_CONFIG_PATH,
        'content' => C_CONTENT_PATH,
        'data' => C_ROOT . '/data',
        'logs' => C_ROOT . '/logs',
        'modules' => C_ROOT . '/modules',
        'templates' => C_ROOT . '/templates',
        'assets/uploads' => C_ROOT . '/assets/uploads'
    ];
    $writableResults = [];
    $allWritable = true;
    foreach ($dirsToCheck as $name => $path) {
        if (!is_dir($path)) @mkdir($path, 0755, true);
        $isWritable = is_writable($path);
        if (!$isWritable) $allWritable = false;
        $writableResults[] = $name . ($isWritable ? ' ✓' : ' ✗');
    }
    $checks['writable'] = [
        'label' => $trans['check_writable'],
        'status' => $allWritable ? 'ok' : 'fail',
        'value' => implode(', ', $writableResults)
    ];

    // 4. Integrity check using external manifest
    $cliponDir = dirname(__DIR__);
    $integrityManifestPath = $cliponDir . '/config/integrity_manifest.php';
    $root = dirname($cliponDir);
    $requiredManifest = [];
    $optionalManifest = [];

    if (is_file($integrityManifestPath)) {
        $manifestData = require $integrityManifestPath;
        if (is_array($manifestData)) {
            if (isset($manifestData['required']) && is_array($manifestData['required'])) {
                $requiredManifest = $manifestData['required'];
            }
            if (isset($manifestData['optional']) && is_array($manifestData['optional'])) {
                $optionalManifest = $manifestData['optional'];
            }
        }
    }

    if (empty($requiredManifest)) {
        $checks['integrity'] = [
            'label' => $trans['check_integrity'],
            'status' => 'fail',
            'value' => $trans['integrity_manifest_missing']
        ];
    } else {
        $corrupted = [];
        foreach ($requiredManifest as $file => $hash) {
            $path = $root . '/' . $file;
            if (!is_file($path) || hash_file('sha256', $path) !== $hash) {
                $corrupted[] = $file;
            }
        }

        $indexExpected = $optionalManifest['index.php'] ?? null;
        $indexPath = $root . '/index.php';
        $indexMatched = is_string($indexExpected) && is_file($indexPath) && hash_file('sha256', $indexPath) === $indexExpected;

        $checks['integrity'] = [
            'label' => $trans['check_integrity'],
            'status' => empty($corrupted) ? 'ok' : 'fail',
            'value' => empty($corrupted)
                ? ($indexMatched ? $trans['integrity_system_verified'] : $trans['integrity_internal_verified_pending_index'])
                : ($trans['integrity_required_corrupted'] . ': ' . implode(', ', $corrupted))
        ];
    }
}

$canContinue = true;
if ($currentStep === 2) {
    foreach ($checks as $c) if ($c['status'] === 'fail') $canContinue = false;
}

$migrationReport = $session->get('migration_report', ['errors' => [], 'warnings' => []]);
$migrationPreview = $session->get('migration_preview', ['candidates' => ['files' => [], 'dirs' => []]]);
$migrationStatus = $migrationReport['status'] ?? '';

if ($request->query('download_migration_report') && $request->int('download_migration_report') === 1) {
    $reportDir = $migrationReport['paths']['report_dir'] ?? null;
    $type = $request->query('type', 'json');
    if (!$reportDir || !is_dir($reportDir)) {
        http_response_code(404);
        exit('Report is unavailable.');
    }

    $targetFile = $type === 'txt' ? ($reportDir . '/summary.txt') : ($reportDir . '/report.json');
    if (!is_file($targetFile)) {
        http_response_code(404);
        exit('Requested report file not found.');
    }

    header('Content-Type: ' . ($type === 'txt' ? 'text/plain; charset=utf-8' : 'application/json; charset=utf-8'));
    header('Content-Disposition: attachment; filename="migration-' . ($type === 'txt' ? 'summary.txt' : 'report.json') . '"');
    readfile($targetFile);
    exit;
}

$setupStepTitles = [
    1 => $trans['step1_title'] ?? $trans['title'],
    2 => $trans['step2_title'] ?? $trans['title'],
    3 => $trans['step3_title'] ?? $trans['title'],
    4 => $trans['step4_title'] ?? $trans['title'],
    5 => $trans['step5_title'] ?? $trans['title'],
    6 => $trans['step6_title'] ?? $trans['title'],
    7 => $trans['setup_complete'] ?? $trans['title'],
];
