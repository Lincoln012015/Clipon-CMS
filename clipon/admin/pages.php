<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/JsonStorage.php';
require_once __DIR__ . '/lib/PageTreeTemplateRenderer.php';

AdminAccess::requireUser($session, 'login.php');

$user = $session->get('user', 'unknown');
$role = $session->get('role', 'unknown');

$pagesDir = C_CONTENT_PATH . '/pages/';
$directoriesFile = C_CONFIG_PATH . '/directories.php';
$conversionTypes = Settings::getConversionTypes() ?: [];
$conversionConfigFile = C_CONFIG_PATH . '/conversions.php';

$configuredLangs = Settings::getLanguages();
$activeLangs = array_values(array_filter($configuredLangs, static fn($l) => !empty($l['enabled'])));
if (empty($activeLangs)) {
    $activeLangs = [['code' => (Settings::load()['language'] ?? 'en'), 'name' => 'Default']];
}
$activeLangCodes = array_values(array_filter(array_map(static fn($l) => (string)($l['code'] ?? ''), $activeLangs)));
$primaryLang = (string)($activeLangCodes[0] ?? (Settings::load()['language'] ?? 'en'));
$requestedEditLang = $request->string('edit_lang');
$currentEditLang = in_array($requestedEditLang, $activeLangCodes, true) ? $requestedEditLang : $primaryLang;

// Services
$pageDirectoryService = new PageDirectoryService($directoriesFile);

// Allow module to provide PageService instance via filter; fallback to core PageService
$pageService = null;
if (class_exists('Hooks')) {
    $pageService = Hooks::applyFilters('multilang:page_service_instance', null, $pagesDir, $conversionConfigFile, $pageDirectoryService);
}
if (!is_object($pageService)) {
    $pageService = new PageService($pagesDir, $conversionConfigFile, $pageDirectoryService);
}

// UI helper: render nested directory options
function renderDirOptions($dirs, $selected = null, $prefix = '') {
    $html = '';
    foreach ($dirs as $dir) {
        $sel = ($selected === $dir['id']) ? ' selected' : '';
        $html .= '<option value="' . $dir['id'] . '"' . $sel . '>' . $prefix . htmlspecialchars($dir['name']) . '</option>';
        if (!empty($dir['children'])) {
            $html .= renderDirOptions($dir['children'], $selected, $prefix . '-- ');
        }
    }
    return $html;
}

function getAllPagesForParentSelect($currentSlug) {
    global $pageService, $session;
    return $pageService->getAllPagesForParentSelect($currentSlug, $session);
}

function getTemplates() {
    global $pageService;
    return $pageService->getTemplates();
}

function getEnabledConversionTypes(array $conversionTypes): array {
    return PageService::getEnabledConversionTypes($conversionTypes);
}

function getLocalizedFrontendUrl(array $page, string $langCode, string $primaryLang): string {
    global $pageService;
    return $pageService->getLocalizedFrontendUrl($page, $langCode, $primaryLang);
}

// Load pages and build tree model
$pages = $pageService->loadPages();
$directories = $pageDirectoryService->getDirectories();
$langs = Translation::getSupportedLangs();

$treeRenderer = new PageTreeRenderer();
$treeData = $treeRenderer->build($directories, $pages);
$rootDirs = $treeData['root_dirs'];
$rootPages = $treeData['root_pages'];
$treeJson = $treeRenderer->toJson($treeData);

function getAllPagesInDir($d, &$list) {
    global $session;

    $configuredLangs = Settings::getLanguages();
    $activeLangs = array_values(array_filter($configuredLangs, static fn($l) => !empty($l['enabled'])));
    $primaryLang = (string)($activeLangs[0]['code'] ?? (Settings::load()['language'] ?? 'en'));

    foreach ($d['pages'] as $p) {
        $lang = (string)$session->get('admin_lang', $primaryLang);
        $title = trim((string)($p['locales'][$lang]['title'] ?? ''));
        if ($title === '') {
            $title = trim((string)($p['locales'][$primaryLang]['title'] ?? ''));
        }

        if ($title === '') {
            $title = (string)($p['slug'] ?? '');
        }

        $list[] = $title;
    }
    foreach ($d['children'] as $c) {
        getAllPagesInDir($c, $list);
    }
}

function getEditIcon() {
    return Icons::pencil(18);
}

function getDeleteIcon() {
    return Icons::trash(18);
}

function getCopyIcon() {
    return Icons::copy(18);
}

function getViewIcon() {
    return Icons::eye(18);
}

function getStarIcon($filled = false) {
    return Icons::star($filled, 18);
}

function getToggleIcon($active = true) {
    return Icons::toggle($active, 20);
}
?>
<!DOCTYPE html>
<html lang="<?= Translation::getLang() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= __('pages_title') ?></title>
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin.css?v=18">
    <script src="<?= C_ASSETS_URL ?>/vendor/sortablejs/Sortable.min.js"></script>
</head>
<body>
    <div class="admin-container">
        <?php include __DIR__ . '/nav.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1><?= __('pages_manage_h1') ?></h1>
                    <p><?= __('site_structure') ?></p>
                </div>
                <div class="header-actions">
                    <?php if (hasPermission('create_pages')): ?>
                        <button class="btn" onclick="openPageModal()"><?= Icons::plus(18) ?> <?= __('create_page') ?></button>
                        <button class="btn btn-secondary" onclick="openDirModal('add_dir')"><?= Icons::folderPlus(18) ?> <?= __('add_dir') ?></button>
                    <?php endif; ?>

                    <?php
                        $partial = __DIR__ . '/partials/lang_switcher.php';
                        if (class_exists('Hooks')) {
                            $partial = Hooks::applyFilters('multilang:partial_path', $partial, 'lang_switcher.php');
                        }
                        include $partial;
                    ?>
                </div>
            </header>
            
            <?php
            $flash = Flash::pull('main');
            $partial = __DIR__ . '/partials/admin_flash.php';
            if (class_exists('Hooks')) {
                $partial = Hooks::applyFilters('multilang:partial_path', $partial, 'admin_flash.php');
            }
            include $partial;
            ?>
            <?php include __DIR__ . '/partials/pages_tree.php'; ?>
        </main>
    </div>

    <?php
        $partial = __DIR__ . '/partials/pages_modals.php';
        if (class_exists('Hooks')) $partial = Hooks::applyFilters('multilang:partial_path', $partial, 'pages_modals.php');
        include $partial;

        $partialCfg = __DIR__ . '/partials/pages_admin_config.php';
        if (class_exists('Hooks')) $partialCfg = Hooks::applyFilters('multilang:partial_path', $partialCfg, 'pages_admin_config.php');
        include $partialCfg;
    ?>
    <script src="<?= C_ASSETS_URL ?>/js/admin-shared.js?v=2"></script>
    <script src="<?= C_ASSETS_URL ?>/js/admin-pages.js?v=2"></script>
</body>
</html>
