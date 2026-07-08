<?php
/**
 * Clipon CMS Migration & Tagging Utility
 *
 * Unified migration entrypoint for CLI mode.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/MigrationEngine.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "[ERROR] migrate_pages.php має запускатися з CLI.\n");
    exit(1);
}

$config = MigrationEngine::defaultConfig();

$externalConfigFile = C_ROOT . '/.clipon-migrate-pages.php';
if (file_exists($externalConfigFile)) {
    $userConfig = require $externalConfigFile;
    if (is_array($userConfig)) {
        $config = array_replace_recursive($config, $userConfig);
    }
}

echo "--- Clipon CMS Migration Utility ---\n";
echo "Dry Run: " . (!empty($config['dry_run']) ? 'YES' : 'NO') . "\n";
echo "Tagging: " . (!empty($config['tagging']['enabled']) ? 'ENABLED' : 'DISABLED') . "\n";
echo "Asset Fixer: " . (!empty($config['assets']['enabled']) ? 'ENABLED' : 'DISABLED') . "\n\n";

$engine = new MigrationEngine($config);
$report = $engine->run();

echo $engine->getSummaryText() . "\n\n";

if (!empty($report['warnings'])) {
    echo "Warnings:\n";
    foreach ($report['warnings'] as $warning) {
        echo " - {$warning}\n";
    }
    echo "\n";
}

if (!empty($report['errors'])) {
    fwrite(STDERR, "Errors:\n");
    foreach ($report['errors'] as $error) {
        fwrite(STDERR, " - {$error}\n");
    }
    fwrite(STDERR, "\n");
}

if (!empty($report['paths']['report_dir'])) {
    echo "Report dir: " . $report['paths']['report_dir'] . "\n";
}
if (!empty($report['paths']['backup_root'])) {
    echo "Backup dir: " . $report['paths']['backup_root'] . "\n";
}

echo "\nMigration finished with status: " . strtoupper((string)$report['status']) . "\n";

exit($report['status'] === 'failed' ? 1 : 0);
