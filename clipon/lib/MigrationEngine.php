<?php

require_once __DIR__ . '/JsonStorage.php';
require_once __DIR__ . '/AssetUrlNormalizer.php';

class MigrationEngine {
    private array $config;
    private array $report;
    private array $journal = [];
    private array $planned = ['files' => [], 'dirs' => []];
    private array $detectedLangs = [];
    private ?string $backupRoot = null;
    private ?string $reportDir = null;

    public function __construct(array $config = []) {
        $this->config = array_replace_recursive(self::defaultConfig(), $config);
        $this->config['assets']['base_path'] = self::normalizeBasePath((string)($this->config['assets']['base_path'] ?? ''));
        $this->report = [
            'status' => 'pending',
            'started_at' => date('c'),
            'finished_at' => null,
            'dry_run' => (bool)$this->config['dry_run'],
            'errors' => [],
            'warnings' => [],
            'error_matrix' => [],
            'preflight' => [],
            'plan' => [
                'files' => [],
                'dirs' => [],
                'risks' => [],
            ],
            'stages' => [
                'scan' => ['status' => 'pending', 'progress' => 0],
                'preflight' => ['status' => 'pending', 'progress' => 0],
                'directories' => ['status' => 'pending', 'progress' => 0],
                'files' => ['status' => 'pending', 'progress' => 0],
                'route_map' => ['status' => 'pending', 'progress' => 0],
                'finalize' => ['status' => 'pending', 'progress' => 0],
            ],
            'items' => [
                'dirs' => [],
                'files' => [],
            ],
            'summary' => [
                'scanned_files' => 0,
                'scanned_dirs' => 0,
                'migrated_templates' => 0,
                'migrated_page_data' => 0,
                'moved_directories' => 0,
                'skipped' => 0,
                'rolled_back' => false,
            ],
            'journal' => [],
            'paths' => [
                'root' => C_ROOT,
                'templates' => C_TEMPLATES_PATH,
                'pages' => C_CONTENT_PATH . '/pages',
                'route_map' => C_CONFIG_PATH . '/route_map.php',
                'backup_root' => null,
                'report_dir' => null,
            ],
        ];
    }

    public static function defaultConfig(): array {
        return [
            'scan' => [
                'page_extensions' => ['php', 'html', 'htm'],
                'skip_dom_for_php' => true,
            ],
            'tagging' => [
                'enabled' => true,
                'tags' => ['h1', 'h2', 'h3', 'p', 'img', 'a'],
                'exclude_sections' => ['header', 'footer', 'nav'],
                'add_ids' => true,
            ],
            'assets' => [
                'enabled' => true,
                'base_path' => defined('C_BASE_URL') ? C_BASE_URL : '',
                'tags' => [
                    'img' => 'src',
                    'link' => 'href',
                    'script' => 'src',
                    'a' => 'href',
                ],
                'process_links' => true,
                'treat_root_dirs_as_assets' => true,
            ],
            'languages' => [
                'enabled' => true,
                'default_lang' => 'uk', // початкова мова для міграції
                'detect_from_html' => true,
                'update_cms_settings' => true,
            ],
            'overwrite' => [
                'templates' => true,
                'page_data' => false,
            ],
            'backup' => [
                'enabled' => true,
                'base_dir' => C_ROOT . '/.clipon_backups/migrate_pages',
                'use_timestamp_subdir' => true,
                'backup_source_files' => true,
                'backup_existing_templates' => true,
                'backup_existing_page_data' => true,
                'backup_route_map' => true,
            ],
            'transactional' => [
                'enabled' => true,
                'auto_rollback_on_error' => true,
            ],
            'dry_run' => false,
        ];
    }

    public function buildPlan(): array {
        $this->setStage('scan', 'running', 10);
        $this->planned = $this->scanCandidates();
        $this->report['summary']['scanned_files'] = count($this->planned['files']);
        $this->report['summary']['scanned_dirs'] = count($this->planned['dirs']);

        $this->report['plan'] = $this->buildDetailedPlan($this->planned);
        $this->setStage('scan', 'success', 100);

        $this->setStage('preflight', 'running', 20);
        $this->report['preflight'] = $this->preflightChecks($this->planned);
        $this->setStage('preflight', empty($this->report['preflight']['errors']) ? 'success' : 'failed', 100);

        return [
            'candidates' => $this->planned,
            'plan' => $this->report['plan'],
            'preflight' => $this->report['preflight'],
            'errors' => $this->report['errors'],
            'warnings' => $this->report['warnings'],
            'stages' => $this->report['stages'],
        ];
    }

    public function run(): array {
        $plan = $this->buildPlan();
        if (!empty($plan['preflight']['errors'])) {
            $this->reportError('E_PREFLIGHT_FAILED', 'Preflight failed. Міграція не запущена.', 'Виправте помилки preflight і повторіть запуск.');
            return $this->finalize('failed');
        }

        $this->initReportDir();

        if ($this->config['dry_run']) {
            return $this->finalize('dry-run');
        }

        try {
            $this->ensureRuntimeDirectories();
            $this->initBackupDir();
            $this->runMigration();

            if (!empty($this->report['errors'])) {
                throw new RuntimeException('Migration completed with errors.');
            }

            return $this->finalize('success');
        } catch (Throwable $e) {
            $this->reportError('E_RUNTIME', $e->getMessage(), 'Перевірте журнал міграції та виправте проблемні файли/права доступу.');

            if (($this->config['transactional']['enabled'] ?? false) && ($this->config['transactional']['auto_rollback_on_error'] ?? false)) {
                $this->rollback();
            }

            return $this->finalize('failed');
        }
    }

    public function getSummaryText(): string {
        $s = $this->report['summary'];
        $lines = [];
        $lines[] = 'Migration status: ' . strtoupper((string)$this->report['status']);
        $lines[] = 'Dry run: ' . ($this->report['dry_run'] ? 'YES' : 'NO');
        $lines[] = 'Scanned files: ' . $s['scanned_files'];
        $lines[] = 'Scanned dirs: ' . $s['scanned_dirs'];
        $lines[] = 'Moved dirs: ' . $s['moved_directories'];
        $lines[] = 'Migrated templates: ' . $s['migrated_templates'];
        $lines[] = 'Migrated page-data: ' . $s['migrated_page_data'];
        $lines[] = 'Skipped: ' . $s['skipped'];
        $lines[] = 'Warnings: ' . count($this->report['warnings']);
        $lines[] = 'Errors: ' . count($this->report['errors']);
        $lines[] = 'Rollback executed: ' . ($s['rolled_back'] ? 'YES' : 'NO');
        return implode(PHP_EOL, $lines);
    }

    private function scanCandidates(): array {
        $root = C_ROOT;
        $items = scandir($root) ?: [];
        $toMigrate = ['files' => [], 'dirs' => []];

        $systemPaths = [
            basename(C_CORE_DIR),
            'assets',
            'templates',
            'vendor',
            'logs',
            'data',
            'modules',
            'content',
            'config',
            'bin',
            'docs',
            'node_modules',
            '.git',
            '.github',
            '.vscode',
        ];

        $customIgnore = file_exists($root . '/.clipon-ignore')
            ? array_map('trim', file($root . '/.clipon-ignore', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            : [];

        $exclude = array_unique(array_merge($systemPaths, $customIgnore));

        foreach ($items as $name) {
            $path = $root . '/' . $name;
            if (in_array($name, $exclude, true)) {
                continue;
            }
            if ($name === '.' || $name === '..' || str_starts_with($name, '.')) {
                continue;
            }
            if ($name === 'index.php') {
                continue;
            }

            if (is_dir($path)) {
                $dirFiles = scandir($path) ?: [];
                if (count($dirFiles) > 2) {
                    $toMigrate['dirs'][] = $name;
                }
            } elseif (is_file($path)) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (in_array($ext, $this->config['scan']['page_extensions'], true)) {
                    $toMigrate['files'][] = $name;
                }
            }
        }

        sort($toMigrate['files']);
        sort($toMigrate['dirs']);

        return $toMigrate;
    }

    private function preflightChecks(array $plan): array {
        $checks = ['errors' => [], 'warnings' => []];

        $paths = [
            C_ROOT,
            C_TEMPLATES_PATH,
            C_CONTENT_PATH,
            C_CONTENT_PATH . '/pages',
            C_CONFIG_PATH,
        ];

        foreach ($paths as $path) {
            $parent = is_dir($path) ? $path : dirname($path);
            if (!is_dir($parent) || !is_writable($parent)) {
                $checks['errors'][] = [
                    'code' => 'E_WRITE_ACCESS',
                    'message' => 'No write access: ' . $parent,
                    'fix' => 'Надайте права на запис для цієї директорії.',
                ];
            }
        }

        foreach ($plan['dirs'] as $dirName) {
            $dest = C_ROOT . '/assets/' . $dirName;
            if (file_exists($dest)) {
                $checks['warnings'][] = [
                    'code' => 'W_DIR_EXISTS',
                    'message' => 'Directory already exists in assets and will be skipped: ' . $dirName,
                    'fix' => 'Видаліть/перейменуйте директорію в assets або увімкніть іншу політику міграції.',
                ];
            }
        }

        $incomingUrls = [];
        foreach ($plan['files'] as $filename) {
            $slug = pathinfo($filename, PATHINFO_FILENAME);
            $url = $slug === 'index' ? '/' : '/' . $slug;
            if (isset($incomingUrls[$url])) {
                $checks['errors'][] = [
                    'code' => 'E_DUPLICATE_URL',
                    'message' => 'Duplicate target URL in migration set: ' . $url,
                    'fix' => 'Перейменуйте один із вхідних файлів або явно задайте інший URL.',
                ];
            }
            $incomingUrls[$url] = true;

            $destName = $slug . '.php';
            $destTemplate = C_TEMPLATES_PATH . '/' . $destName;
            if (file_exists($destTemplate) && !($this->config['overwrite']['templates'] ?? false)) {
                $checks['warnings'][] = [
                    'code' => 'W_TEMPLATE_EXISTS',
                    'message' => 'Template exists and overwrite disabled: ' . $destName,
                    'fix' => 'Увімкніть overwrite templates або звільніть ім\'я шаблону.',
                ];
            }

            $pageDataFile = C_CONTENT_PATH . '/pages/' . $slug . '.php';
            if (file_exists($pageDataFile) && !($this->config['overwrite']['page_data'] ?? false)) {
                $checks['warnings'][] = [
                    'code' => 'W_PAGE_DATA_EXISTS',
                    'message' => 'Page-data exists and overwrite disabled: ' . $slug . '.php',
                    'fix' => 'Увімкніть overwrite page-data або залиште існуючий файл без змін.',
                ];
            }
        }

        $existingHomeCount = 0;
        foreach (glob(C_CONTENT_PATH . '/pages/*.php') ?: [] as $pageFile) {
            $data = read_json_file($pageFile);
            if (!empty($data['is_home'])) {
                $existingHomeCount++;
            }
        }
        $incomingHome = 0;
        foreach ($plan['files'] as $filename) {
            if (pathinfo($filename, PATHINFO_FILENAME) === 'index') {
                $incomingHome = 1;
                break;
            }
        }
        if ($existingHomeCount + $incomingHome > 1) {
            $checks['warnings'][] = [
                'code' => 'W_MULTI_HOME',
                'message' => 'Potential multiple home pages detected (existing=' . $existingHomeCount . ', incoming=' . $incomingHome . ').',
                'fix' => 'Перевірте прапор is_home у page-data після міграції та залиште лише одну головну сторінку.',
            ];
        }

        foreach ($checks['errors'] as $error) {
            $this->reportError($error['code'], $error['message'], $error['fix']);
        }
        foreach ($checks['warnings'] as $warning) {
            $this->reportWarning($warning['code'], $warning['message'], $warning['fix']);
        }

        return $checks;
    }

    private function buildDetailedPlan(array $plan): array {
        $details = ['files' => [], 'dirs' => [], 'risks' => []];

        foreach ($plan['dirs'] as $dirName) {
            $src = C_ROOT . '/' . $dirName;
            $dest = C_ROOT . '/assets/' . $dirName;
            $exists = file_exists($dest);
            $details['dirs'][] = [
                'name' => $dirName,
                'source' => $src,
                'target' => $dest,
                'target_exists' => $exists,
                'action' => $exists ? 'skip' : 'move',
            ];
            if ($exists) {
                $details['risks'][] = 'Directory target already exists: ' . $dirName;
            }
        }

        foreach ($plan['files'] as $filename) {
            $slug = pathinfo($filename, PATHINFO_FILENAME);
            $destName = $slug . '.php';
            $templateTarget = C_TEMPLATES_PATH . '/' . $destName;
            $pageDataTarget = C_CONTENT_PATH . '/pages/' . $slug . '.php';
            $url = $slug === 'index' ? '/' : '/' . $slug;

            $templateExists = file_exists($templateTarget);
            $pageDataExists = file_exists($pageDataTarget);
            $willOverwriteTemplate = $templateExists && !empty($this->config['overwrite']['templates']);
            $willOverwritePageData = $pageDataExists && !empty($this->config['overwrite']['page_data']);

            $details['files'][] = [
                'name' => $filename,
                'slug' => $slug,
                'url' => $url,
                'source' => C_ROOT . '/' . $filename,
                'template_target' => $templateTarget,
                'page_data_target' => $pageDataTarget,
                'template_exists' => $templateExists,
                'page_data_exists' => $pageDataExists,
                'template_action' => $templateExists ? ($willOverwriteTemplate ? 'overwrite' : 'skip') : 'create',
                'page_data_action' => $pageDataExists ? ($willOverwritePageData ? 'overwrite' : 'skip') : 'create',
            ];

            if ($templateExists && !$willOverwriteTemplate) {
                $details['risks'][] = 'Template will be skipped (exists): ' . $destName;
            }
            if ($pageDataExists && !$willOverwritePageData) {
                $details['risks'][] = 'Page-data will be skipped (exists): ' . $slug . '.php';
            }
        }

        return $details;
    }

    private function ensureRuntimeDirectories(): void {
        $dirs = [
            C_TEMPLATES_PATH,
            C_ROOT . '/assets',
            C_CONTENT_PATH,
            C_CONTENT_PATH . '/pages',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('Cannot create required directory: ' . $dir);
            }
        }
    }

    private function initBackupDir(): void {
        if (!($this->config['backup']['enabled'] ?? false)) {
            return;
        }

        $base = rtrim((string)($this->config['backup']['base_dir'] ?? ''), '/');
        if ($base === '') {
            $this->reportWarning('W_BACKUP_BASE_EMPTY', 'Backup enabled but base_dir is empty.', 'Вкажіть backup.base_dir або вимкніть backup.');
            return;
        }

        $this->backupRoot = $base;
        if ($this->config['backup']['use_timestamp_subdir'] ?? false) {
            $this->backupRoot .= '/' . date('Ymd_His');
        }

        if (!is_dir($this->backupRoot) && !mkdir($this->backupRoot, 0755, true) && !is_dir($this->backupRoot)) {
            $this->backupRoot = null;
            $this->reportWarning('W_BACKUP_DIR_CREATE_FAILED', 'Unable to create backup directory. Rollback will be limited.', 'Перевірте права доступу до backup.base_dir.');
        }

        $this->report['paths']['backup_root'] = $this->backupRoot;
    }

    private function initReportDir(): void {
        $ts = date('Ymd_His');
        $target = C_LOGS_PATH . '/migrations/' . $ts;
        if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
            return;
        }
        $this->reportDir = $target;
        $this->report['paths']['report_dir'] = $this->reportDir;
    }

    private function runMigration(): void {
        $root = C_ROOT;
        $templatesDir = C_TEMPLATES_PATH;
        $pagesDataDir = C_CONTENT_PATH . '/pages';
        $routeMapFile = C_CONFIG_PATH . '/route_map.php';

        $this->detectedLangs = [];
        $assetRootDirs = ($this->config['assets']['treat_root_dirs_as_assets'] ?? false) ? $this->planned['dirs'] : [];

        $totalDirs = max(1, count($this->planned['dirs']));
        $doneDirs = 0;
        $this->setStage('directories', 'running', 0);

        foreach ($this->planned['dirs'] as $dirName) {
            $src = $root . '/' . $dirName;
            $dest = $root . '/assets/' . $dirName;

            if (file_exists($dest)) {
                $this->report['summary']['skipped']++;
                $this->registerItemStatus('dirs', $dirName, 'skipped', 'W_DIR_EXISTS', 'Directory skipped (exists): ' . $dirName, 'Звільніть шлях в assets або змініть назву директорії.');
                $this->reportWarning('W_DIR_EXISTS', 'Directory skipped (exists): ' . $dirName, 'Звільніть шлях в assets або змініть назву директорії.');
                $doneDirs++;
                $this->setStage('directories', 'running', (int)floor(($doneDirs / $totalDirs) * 100));
                continue;
            }

            if (($this->config['backup']['backup_source_files'] ?? false) && is_dir($src)) {
                $this->backupDir($src, 'dirs_original/' . $dirName);
            }

            // In Clipon CMS, original directories are MOVED to assets/
            if (!@rename($src, $dest)) {
                $this->registerItemStatus('dirs', $dirName, 'error', 'E_MOVE_DIR', 'Failed to move directory: ' . $dirName, 'Перевірте права доступу та наявність блокувань файлів.');
                throw new RuntimeException('Failed to move directory: ' . $dirName);
            }

            $this->journalAction('rename_dir', [
                'src' => $src,
                'dest' => $dest,
            ]);
            $this->report['summary']['moved_directories']++;
            $this->registerItemStatus('dirs', $dirName, 'success', null, 'Directory moved to assets.', '');
            $doneDirs++;
            $this->setStage('directories', 'running', (int)floor(($doneDirs / $totalDirs) * 100));
        }
        $this->setStage('directories', 'success', 100);

        $totalFiles = max(1, count($this->planned['files']));
        $doneFiles = 0;
        $this->setStage('files', 'running', 0);
        foreach ($this->planned['files'] as $filename) {
            $sourcePath = $root . '/' . $filename;
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $destName = in_array($ext, $this->config['scan']['page_extensions'], true)
                ? pathinfo($filename, PATHINFO_FILENAME) . '.php'
                : $filename;

            $slug = pathinfo($destName, PATHINFO_FILENAME);
            $dest = $templatesDir . '/' . $destName;

            if (!is_file($sourcePath)) {
                $this->report['summary']['skipped']++;
                $this->registerItemStatus('files', $filename, 'skipped', 'W_SOURCE_NOT_FOUND', 'Source file not found, skipped: ' . $filename, 'Перевірте, чи файл не було видалено перед стартом міграції.');
                $this->reportWarning('W_SOURCE_NOT_FOUND', 'Source file not found, skipped: ' . $filename, 'Перевірте, чи файл не було видалено перед стартом міграції.');
                $doneFiles++;
                $this->setStage('files', 'running', (int)floor(($doneFiles / $totalFiles) * 100));
                continue;
            }

            $content = @file_get_contents($sourcePath);
            if ($content === false) {
                $this->registerItemStatus('files', $filename, 'error', 'E_READ_SOURCE', 'Failed to read source file: ' . $filename, 'Перевірте права доступу до файла та його цілісність.');
                throw new RuntimeException('Failed to read source file: ' . $filename);
            }

            $sourceBackupPath = null;
            if (($this->config['backup']['backup_source_files'] ?? false)) {
                $sourceBackupPath = $this->backupFile($sourcePath, 'files_original/' . $filename);
            }

            $containsPhp = strpos($content, '<?php') !== false;

            if (($this->config['assets']['enabled'] ?? false) && (!$containsPhp || !($this->config['scan']['skip_dom_for_php'] ?? true))) {
                $content = $this->fixAssetPaths($content, $assetRootDirs, $this->config['assets']);
            }

            $skipTaggingFiles = array_map('strval', $this->config['tagging']['skip_files'] ?? []);
            $skipTaggingForFile = in_array($filename, $skipTaggingFiles, true) || in_array($destName, $skipTaggingFiles, true);
            if (($this->config['tagging']['enabled'] ?? false) && !$skipTaggingForFile && (!$containsPhp || !($this->config['scan']['skip_dom_for_php'] ?? true))) {
                $content = $this->tagContent($content, $destName, $this->config['tagging']);
            }

            if (file_exists($dest) && !($this->config['overwrite']['templates'] ?? false)) {
                $this->report['summary']['skipped']++;
                $this->registerItemStatus('files', $filename, 'skipped', 'W_TEMPLATE_EXISTS', 'Template exists and skipped: ' . $destName, 'Увімкніть overwrite templates або звільніть ім\'я шаблону.');
                $this->reportWarning('W_TEMPLATE_EXISTS', 'Template exists and skipped: ' . $destName, 'Увімкніть overwrite templates або звільніть ім\'я шаблону.');
            } else {
                $backupPath = null;
                $existedBefore = file_exists($dest);
                if ($existedBefore && ($this->config['backup']['backup_existing_templates'] ?? false)) {
                    $backupPath = $this->backupFile($dest, 'templates_existing/' . $destName);
                }

                if (@file_put_contents($dest, $content) === false) {
                    $this->registerItemStatus('files', $filename, 'error', 'E_WRITE_TEMPLATE', 'Failed to write template: ' . $destName, 'Перевірте права доступу до templates та вільне місце на диску.');
                    throw new RuntimeException('Failed to write template: ' . $destName);
                }

                $this->journalAction('write_file', [
                    'path' => $dest,
                    'existed_before' => $existedBefore,
                    'backup_path' => $backupPath,
                ]);

                $this->report['summary']['migrated_templates']++;

                // Remove the original only after the template was written and a restorable backup exists.
                if (!empty($this->config['scan']['delete_originals']) && is_file($sourcePath)) {
                    if ($sourceBackupPath && is_file($sourceBackupPath)) {
                        if (@unlink($sourcePath)) {
                            $this->journalAction('delete_file', [
                                'path' => $sourcePath,
                                'backup_path' => $sourceBackupPath,
                            ]);
                        } else {
                            $this->reportWarning('W_DELETE_ORIGINAL_FAILED', 'Unable to delete original source file: ' . $filename, 'Перевірте права доступу або видаліть файл вручну після міграції.');
                        }
                    } else {
                        $this->reportWarning('W_DELETE_ORIGINAL_NO_BACKUP', 'Original source file kept because backup is unavailable: ' . $filename, 'Увімкніть backup або перевірте права доступу до backup директорії.');
                    }
                }
            }

            $extractedTitle = ucfirst($slug);
            $extractedDesc = '';
            $pageLang = $this->config['languages']['default_lang'] ?? ($this->config['language'] ?? 'en');

            if (preg_match('/<title>(.*?)<\/title>/is', $content, $matches)) {
                $extractedTitle = trim($matches[1]);
            }
            if (
                preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\'](.*?)["\'][^>]*>/is', $content, $matches)
                || preg_match('/<meta[^>]*content=["\'](.*?)["\'][^>]*name=["\']description["\'][^>]*>/is', $content, $matches)
            ) {
                $extractedDesc = trim($matches[1]);
            }

            if (!empty($this->config['languages']['detect_from_html'])) {
                if (preg_match('/<html[^>]*lang=["\']([a-zA-Z-]{2,5})["\']/i', $content, $m)) {
                    $detectedCode = strtolower(substr($m[1], 0, 2));
                    if (in_array($detectedCode, ['uk', 'en', 'ru', 'de', 'fr', 'es', 'pl'])) {
                        $pageLang = $detectedCode;
                    }
                }
            }
            $this->detectedLangs[$pageLang] = true;

            $isHome = ($slug === 'index');
            $contentFile = $pagesDataDir . '/' . $slug . '.php';

            if (file_exists($contentFile) && !($this->config['overwrite']['page_data'] ?? false)) {
                $this->report['summary']['skipped']++;
                $this->reportWarning('W_PAGE_DATA_EXISTS', 'Page-data exists and skipped: ' . $slug . '.php', 'Увімкніть overwrite page-data, якщо потрібна перезапис.');
            } else {
                $backupPath = null;
                $existedBefore = file_exists($contentFile);
                if ($existedBefore && ($this->config['backup']['backup_existing_page_data'] ?? false)) {
                    $backupPath = $this->backupFile($contentFile, 'page_data_existing/' . $slug . '.php');
                }

                $pageData = [
                    'title' => $extractedTitle,
                    'description' => $extractedDesc,
                    'url' => $isHome ? '/' : '/' . $slug,
                    'template' => $destName,
                    'parent_id' => null,
                    'active' => true,
                    'is_home' => $isHome,
                    'lang' => $pageLang,
                    'created_at' => date('Y-m-d H:i:s'),
                    'author' => 'migration',
                    'content' => [],
                ];

                if (!write_json_file($contentFile, $pageData)) {
                    $this->registerItemStatus('files', $filename, 'error', 'E_WRITE_PAGE_DATA', 'Failed to write page-data: ' . $slug . '.php', 'Перевірте права доступу до content/pages.');
                    throw new RuntimeException('Failed to write page-data: ' . $slug . '.php');
                }

                $this->journalAction('write_file', [
                    'path' => $contentFile,
                    'existed_before' => $existedBefore,
                    'backup_path' => $backupPath,
                ]);

                $this->report['summary']['migrated_page_data']++;
            }

            $this->registerItemStatus('files', $filename, 'success', null, 'Template and page-data processed.', '');
            $doneFiles++;
            $this->setStage('files', 'running', (int)floor(($doneFiles / $totalFiles) * 100));
        }
        $this->setStage('files', 'success', 100);

        $routeMapBackupPath = null;
        $routeMapExisted = file_exists($routeMapFile);
        if ($routeMapExisted && ($this->config['backup']['backup_route_map'] ?? false)) {
            $routeMapBackupPath = $this->backupFile($routeMapFile, 'config/route_map.php');
        }

        $this->setStage('route_map', 'running', 30);
        /** @var RouteMap $routeMap */
        $routeMap = registry()->get('route_map');
        $routeMap->rebuild(rtrim($pagesDataDir, '/') . '/');
        $this->setStage('route_map', 'success', 100);

        if (!empty($this->config['languages']['update_cms_settings']) && !empty($this->detectedLangs)) {
            $this->updateCmsLanguages(array_keys($this->detectedLangs));
        }

        $this->journalAction('write_file', [
            'path' => $routeMapFile,
            'existed_before' => $routeMapExisted,
            'backup_path' => $routeMapBackupPath,
        ]);
    }

    private function rollback(): void {
        $this->reportWarning('W_ROLLBACK_STARTED', 'Rollback started.', 'Дочекайтесь завершення rollback перед повторним запуском.');

        for ($i = count($this->journal) - 1; $i >= 0; $i--) {
            $entry = $this->journal[$i];
            $type = $entry['type'];
            $data = $entry['data'];

            try {
                if ($type === 'rename_dir') {
                    if (is_dir($data['dest'])) {
                        @rename($data['dest'], $data['src']);
                    }
                }

                if ($type === 'write_file') {
                    $path = $data['path'];
                    $existedBefore = !empty($data['existed_before']);
                    $backupPath = $data['backup_path'] ?? null;

                    if ($existedBefore && $backupPath && is_file($backupPath)) {
                        @copy($backupPath, $path);
                    } elseif (!$existedBefore && is_file($path)) {
                        @unlink($path);
                    }
                }

                if ($type === 'delete_file') {
                    $path = $data['path'];
                    $backupPath = $data['backup_path'] ?? null;

                    if ($backupPath && is_file($backupPath) && !is_file($path)) {
                        $targetDir = dirname($path);
                        if (!is_dir($targetDir)) {
                            @mkdir($targetDir, 0755, true);
                        }
                        @copy($backupPath, $path);
                    }
                }
            } catch (Throwable $e) {
                $this->reportWarning('W_ROLLBACK_STEP_FAILED', 'Rollback step failed: ' . $e->getMessage(), 'Перевірте журнал міграції та відновіть файли вручну за потреби.');
            }
        }

        $this->report['summary']['rolled_back'] = true;
        $this->reportWarning('W_ROLLBACK_FINISHED', 'Rollback finished.', 'Перевірте стан templates/content/config після rollback.');
    }

    private function journalAction(string $type, array $data): void {
        $entry = [
            'time' => date('c'),
            'type' => $type,
            'data' => $data,
        ];
        $this->journal[] = $entry;
        $this->report['journal'][] = $entry;
    }

    private function backupFile(string $sourcePath, string $relativePath): ?string {
        if (!$this->backupRoot || !is_file($sourcePath)) {
            return null;
        }

        $targetPath = rtrim($this->backupRoot, '/') . '/' . ltrim($relativePath, '/');
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            $this->reportWarning('W_BACKUP_TARGET_DIR', 'Unable to create backup directory: ' . $targetDir, 'Перевірте права доступу до backup директорій.');
            return null;
        }

        if (!@copy($sourcePath, $targetPath)) {
            $this->reportWarning('W_BACKUP_FILE_COPY', 'Unable to backup file: ' . $sourcePath, 'Перевірте права доступу і наявність файлу.');
            return null;
        }

        return $targetPath;
    }

    private function backupDir(string $sourceDir, string $relativePath): void {
        if (!$this->backupRoot || !is_dir($sourceDir)) {
            return;
        }

        $targetDir = rtrim($this->backupRoot, '/') . '/' . ltrim($relativePath, '/');
        $this->recursiveCopy($sourceDir, $targetDir);
    }

    private function recursiveCopy(string $src, string $dst): void {
        if (!is_dir($src)) {
            return;
        }

        if (!is_dir($dst) && !mkdir($dst, 0755, true) && !is_dir($dst)) {
            $this->reportWarning('W_BACKUP_RECURSIVE_DIR', 'Unable to create backup directory: ' . $dst, 'Перевірте права доступу до backup директорій.');
            return;
        }

        $items = scandir($src) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $src . '/' . $item;
            $to = $dst . '/' . $item;

            if (is_dir($from)) {
                $this->recursiveCopy($from, $to);
            } else {
                if (!@copy($from, $to)) {
                    $this->reportWarning('W_BACKUP_RECURSIVE_COPY', 'Unable to backup file: ' . $from, 'Перевірте права доступу і цілісність джерела.');
                }
            }
        }
    }

    private function finalize(string $status): array {
        $this->report['status'] = $status;
        $this->report['finished_at'] = date('c');
        
        // Додаємо виявлені мови в звіт для інсталятора
        $this->report['detected_langs'] = isset($this->detectedLangs) ? array_keys($this->detectedLangs) : [];
        
        $this->setStage('finalize', $status === 'failed' ? 'failed' : 'success', 100);

        $this->writeReportArtifacts();
        return $this->report;
    }

    private function writeReportArtifacts(): void {
        if (!$this->reportDir) {
            return;
        }

        $reportJsonPath = $this->reportDir . '/report.json';
        $summaryPath = $this->reportDir . '/summary.txt';

        @file_put_contents(
            $reportJsonPath,
            json_encode($this->report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        @file_put_contents($summaryPath, $this->getSummaryText() . PHP_EOL);
    }

    private function updateCmsLanguages(array $detectedCodes): void {
        if (!class_exists('Settings') || !class_exists('License')) {
            return;
        }

        // Перевірка наявності Pro-ліцензії (мультимовність — це Pro функція)
        $isPro = false;
        $licenseData = License::isValid() ? (require C_CONFIG_PATH . '/license.php') : [];
        if (!empty($licenseData['modules']) && (in_array('*', $licenseData['modules']) || in_array('multilang', $licenseData['modules']))) {
            $isPro = true;
        }

        $settings = Settings::load();
        $currentLangs = $settings['languages'] ?? [];
        $currentCodes = array_column($currentLangs, 'code');
        $changed = false;

        $langNames = [
            'uk' => 'Українська',
            'en' => 'English',
            'ru' => 'Русский',
            'de' => 'Deutsch',
            'fr' => 'Français',
            'es' => 'Español',
            'pl' => 'Polski',
        ];

        // Якщо це Base-версія, ми дозволяємо лише одну (першу знайдену або дефолтну) мову
        if (!$isPro && count($detectedCodes) > 1) {
            $primary = $detectedCodes[0];
            $this->reportWarning('W_LANG_LIMIT', 'Виявлено кілька мов, але у вас Base-версія. Всі сторінки будуть мігровані як ' . ($langNames[$primary] ?? strtoupper($primary)) . '.');
            $detectedCodes = [$primary];
        }

        foreach ($detectedCodes as $code) {
            if (!in_array($code, $currentCodes, true)) {
                $currentLangs[] = [
                    'code' => $code,
                    'name' => $langNames[$code] ?? strtoupper($code),
                    'enabled' => true,
                ];
                $changed = true;
            }
        }

        if ($changed) {
            $settings['languages'] = $currentLangs;
            Settings::save($settings);
            $this->reportWarning('I_LANG_UPDATED', 'CMS language settings updated with detected languages: ' . implode(', ', $detectedCodes));
        }
    }

    private function reportError(string $code, string $message, string $fix = '', array $context = []): void {
        $entry = [
            'level' => 'error',
            'code' => $code,
            'reason' => $message,
            'fix' => $fix,
            'context' => $context,
        ];

        $this->report['errors'][] = '[' . $code . '] ' . $message;
        $this->report['error_matrix'][] = $entry;
    }

    private function reportWarning(string $code, string $message, string $fix = '', array $context = []): void {
        $entry = [
            'level' => 'warning',
            'code' => $code,
            'reason' => $message,
            'fix' => $fix,
            'context' => $context,
        ];

        $this->report['warnings'][] = '[' . $code . '] ' . $message;
        $this->report['error_matrix'][] = $entry;
    }

    private function setStage(string $stage, string $status, int $progress): void {
        if (!isset($this->report['stages'][$stage])) {
            return;
        }
        $this->report['stages'][$stage]['status'] = $status;
        $this->report['stages'][$stage]['progress'] = max(0, min(100, $progress));
    }

    private function registerItemStatus(string $type, string $name, string $status, ?string $code, string $reason, string $fix): void {
        if (!isset($this->report['items'][$type])) {
            return;
        }
        $this->report['items'][$type][] = [
            'name' => $name,
            'status' => $status,
            'code' => $code,
            'reason' => $reason,
            'fix' => $fix,
        ];
    }

    private static function normalizeBasePath(string $basePath): string {
        $basePath = str_replace('\\', '/', trim($basePath));
        if ($basePath === '.' || $basePath === './' || $basePath === '/') {
            return '';
        }

        $basePath = rtrim($basePath, '/');
        if ($basePath !== '' && $basePath[0] !== '/') {
            $basePath = '/' . ltrim($basePath, '/');
        }

        return $basePath;
    }

    private function tagContent(string $html, string $filename, array $config): string {
        if (empty($config['enabled']) || $html === '') {
            return $html;
        }

        $slug = pathinfo($filename, PATHINFO_FILENAME) ?: 'page';
        $blogShortcodeBlocks = [];
        $htmlForDom = preg_replace_callback('/\[(blog_loop|blog_pagination)([^\]]*)\](.*?)\[\/\1\]/s', static function(array $matches) use (&$blogShortcodeBlocks): string {
            $key = '<!--CLIPON_BLOG_SHORTCODE_' . count($blogShortcodeBlocks) . '-->';
            $blogShortcodeBlocks[$key] = $matches[0];
            return $key;
        }, $html) ?? $html;

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $htmlForDom, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        if (!$loaded) {
            return $html;
        }

        $excludeSectionsLower = array_map('strtolower', $config['exclude_sections'] ?? []);

        $isExcluded = function ($node) use ($excludeSectionsLower) {
            $parent = $node->parentNode;
            while ($parent && $parent->nodeType !== XML_DOCUMENT_NODE) {
                if (in_array(strtolower($parent->nodeName), $excludeSectionsLower, true)) {
                    return true;
                }
                $parent = $parent->parentNode;
            }
            return false;
        };

        $counter = 0;
        foreach (($config['tags'] ?? []) as $tagName) {
            $nodes = $dom->getElementsByTagName($tagName);
            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                $node = $nodes->item($i);
                if ($isExcluded($node)) {
                    continue;
                }

                $existingClass = trim($node->getAttribute('class'));
                $classes = preg_split('/\s+/', $existingClass) ?: [];
                if (!in_array('clipon', $classes, true)) {
                    $node->setAttribute('class', trim($existingClass . ' clipon'));
                }

                if (!empty($config['add_ids']) && !$node->hasAttribute('id')) {
                    $node->setAttribute('id', $slug . '_' . $tagName . '_' . $counter++);
                }
            }
        }

        AssetUrlNormalizer::decodeEscapedNumericEntities($dom);

        $result = $dom->saveHTML();
        $result = str_replace('<?xml encoding="utf-8" ?>', '', $result);
        if (!empty($blogShortcodeBlocks)) {
            $result = str_replace(array_keys($blogShortcodeBlocks), array_values($blogShortcodeBlocks), $result);
        }
        return mb_convert_encoding($result, 'UTF-8', 'HTML-ENTITIES');
    }

    private function fixAssetPaths(string $html, array $assetRootDirs, array $assetsConfig): string {
        if (empty($assetsConfig['enabled']) || $html === '') {
            return $html;
        }

        $tags = $assetsConfig['tags'] ?? [];
        foreach ($tags as $tagName => $attrName) {
            if (is_array($attrName)) {
                continue;
            }
            $tags[$tagName] = [$attrName];
        }

        return AssetUrlNormalizer::normalizeHtml($html, [
            'base_path' => $assetsConfig['base_path'] ?? '',
            'asset_root_dirs' => $assetRootDirs,
            'tags' => $tags,
            'process_links' => !empty($assetsConfig['process_links']),
            'rewrite_page_links' => true,
            'page_extensions' => $this->config['scan']['page_extensions'] ?? ['php', 'html', 'htm'],
            'assets_only' => false,
        ]);
    }
}
