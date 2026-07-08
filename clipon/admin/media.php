<?php
require_once __DIR__ . '/../lib/Auth.php';

AdminAccess::requireUser($session, 'login.php');

$user = $session->get('user', 'unknown');
$role = $session->get('role', 'unknown');

// Current directory (relative to public assets/)
$mediaService = new MediaService();
$currentDir = $mediaService->sanitizeDir((string)$request->query('dir', ''));
$currentDir = $mediaService->ensureExistingDir($currentDir);

$mediaRoot = $mediaService->getMediaRoot();

// Get directories
$directories = $mediaService->listDirectories($currentDir);

// Get files (бібліотека зображень)
$files = $mediaService->listMediaFiles($currentDir);

// Settings and langs for UI
$langs = Settings::getLanguages();
$activeLangs = array_filter($langs, fn($l) => !empty($l['enabled']));
$currentLang = $session->get('admin_lang', 'uk');

$mediaMeta = $mediaService->loadMediaMeta();
$allDirs = $mediaService->getAllDirectories();
?>
<!DOCTYPE html>
<html lang="<?= Translation::getLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('media_library') ?> - Admin</title>
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin.css?v=11">
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin-media.css?v=2">
</head>
<body>
    <div class="admin-container">
        <?php include __DIR__ . '/nav.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1><?= __('media_h1') ?></h1>
                    <p><?= __('media_manage_description') ?></p>
                </div>
                <div class="header-actions">
                    <button class="btn" id="uploadBtn" type="button" onclick="openFilePicker()">
                        <?= Icons::plus() ?>
                        <?= __('select_file') ?>
                    </button>
                    <button class="btn btn-secondary" type="button" onclick="createFolder()">
                        <?= Icons::folderPlus() ?>
                        <?= __('create_folder') ?>
                    </button>
                    
                    <select class="btn btn-secondary lang-select" onchange="switchMediaLang(this.value)" aria-label="<?= __('select_lang') ?>">
                        <?php foreach($activeLangs as $l): ?>
                            <option value="<?= $l['code'] ?>" <?= $l['code'] === $currentLang ? 'selected' : '' ?>>
                                <?= strtoupper($l['code']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </header>

            <div id="bulkBar" class="actions-bar" style="display:none;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"> <label for="selectAll" style="margin:0;"><?= __('select_all') ?></label>
                    <span id="bulkText" style="margin-left:12px;"></span>
                </div>
                <div style="display:flex; align-items:center; gap:8px; margin-left:auto;">
                        <button class="btn btn-secondary" id="bulkDeleteBtn" type="button" onclick="bulkDeleteSelected()">
                            <?= Icons::trash() ?>
                            <?= __('delete') ?>
                        </button>
                    <select id="bulkTarget" class="btn btn-secondary lang-select">
                        <option value="" disabled selected><?= __('select_destination') ?></option>
                        <?php foreach ($allDirs as $dir): ?>
                            <option value="<?= htmlspecialchars($dir) ?>"><?= $dir === '' ? '/' : htmlspecialchars($dir) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn" id="bulkMoveBtn" type="button" onclick="bulkMoveSelected()"><?= __('move_confirm') ?></button>
                </div>
            </div>

            <?php include __DIR__ . '/partials/media_create_folder_modal.php'; ?>
            
            <div class="breadcrumb">
                <a href="media.php">
                    <span class="breadcrumb-icon"><?= Icons::dashboard() ?></span>
                    <?= __('home') ?>
                </a>
                <?php 
                if ($currentDir) {
                    $parts = explode('/', $currentDir);
                    $path = '';
                    foreach ($parts as $part) {
                        $path .= ($path ? '/' : '') . $part;
                        echo '<span class="breadcrumb-icon">' . Icons::caretRight() . '</span>';
                        echo '<a href="media.php?dir=' . urlencode($path) . '">' . htmlspecialchars($part) . '</a>';
                    }
                }
                ?>
            </div>

            <div class="upload-zone" onclick="document.getElementById('fileInput').click()">
                <input type="file" id="fileInput" style="display: none" multiple onchange="uploadFile()">
                <div class="upload-zone-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                </div>
                <h3 style="margin-bottom: 8px; font-weight: 600;"><?= __('select_file') ?></h3>
                <p style="color: var(--text-muted); font-size: 14px;"><?= __('drag_file') ?></p>
            </div>
            
            <div class="media-picker-grid" id="mediaGrid">
                <?php foreach($directories as $dir): 
                    $dirPath = $currentDir ? $currentDir . '/' . $dir : $dir;
                ?>
                    <div class="media-item folder-item media-picker-item" 
                         draggable="true" 
                         data-type="folder" 
                         data-name="<?= htmlspecialchars($dir) ?>"
                         ondblclick="window.location='media.php?dir=<?= urlencode($dirPath) ?>'">
                        <input type="checkbox" class="select-checkbox" data-type="folder" data-name="<?= htmlspecialchars($dir) ?>" onchange="updateBulkBar()">
                        <div class="media-preview">
                            <div class="folder-preview-icon"><?= Icons::folder(40) ?></div>
                        </div>
                        <div class="media-name" data-tooltip="<?= htmlspecialchars($dir) ?>"><?= htmlspecialchars($dir) ?></div>
                        <div class="item-actions">
                            <button class="media-btn" onclick="renameFolder('<?= htmlspecialchars($dir) ?>')">
                                <span class="media-icon"><?= Icons::pencil() ?></span>
                                <?= __('rename') ?>
                            </button>
                            <button class="media-btn delete-btn" onclick="deleteFolder('<?= htmlspecialchars($dir) ?>')">
                                <span class="media-icon"><?= Icons::trash() ?></span>
                                <?= __('delete') ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php foreach($files as $file): 
                    $normalizedFile = str_replace('\\', '/', $file);
                    $normalizedRoot = str_replace('\\', '/', rtrim($mediaRoot, DIRECTORY_SEPARATOR));
                    $relativePath = ltrim(substr($normalizedFile, strlen($normalizedRoot)), '/');
                    $webPath = $mediaService->getPublicPath($relativePath);
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    $isImage = in_array(strtolower($ext), ['jpg','jpeg','png','gif','webp']);
                    $fileName = basename($file);
                    
                    $rawAlt = $mediaMeta[$fileName]['alt'] ?? '';
                    $displayAlt = is_array($rawAlt) ? ($rawAlt[$currentLang] ?? reset($rawAlt) ?: '') : $rawAlt;
                ?>
                    <div class="media-item media-picker-item" 
                         draggable="true" 
                         data-type="file" 
                         data-name="<?= htmlspecialchars($fileName) ?>">
                        <input type="checkbox" class="select-checkbox" data-type="file" data-name="<?= htmlspecialchars($fileName) ?>" onchange="updateBulkBar()">
                        <div class="media-preview">
                            <?php if($isImage): ?>
                                <img src="<?= $webPath ?>" loading="lazy" alt="<?= htmlspecialchars($displayAlt) ?>">
                            <?php else: ?>
                                <div class="media-icon-file"><?= strtoupper($ext) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="media-name" data-tooltip="<?= $fileName ?>"><?= $fileName ?></div>
                        <?php if($isImage): ?>
                            <div class="alt-texts">
                                <?php foreach($activeLangs as $l): ?>
                                    <?php 
                                    $langCode = $l['code'];
                                    $localAlt = '';
                                    if (isset($mediaMeta[$fileName]['alt'])) {
                                        if (is_array($mediaMeta[$fileName]['alt'])) {
                                            $localAlt = $mediaMeta[$fileName]['alt'][$langCode] ?? '';
                                        } elseif ($langCode === 'uk') {
                                            $localAlt = $mediaMeta[$fileName]['alt'];
                                        }
                                    }
                                    ?>
                                    <input type="text" 
                                           class="alt-input lang-alt-<?= $langCode ?>" 
                                           placeholder="Alt (<?= strtoupper($langCode) ?>)" 
                                           value="<?= htmlspecialchars($localAlt) ?>" 
                                           style="<?= $langCode === $currentLang ? '' : 'display:none' ?>"
                                           onchange="saveAlt('<?= addslashes($fileName) ?>', this.value, '<?= $langCode ?>')">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="item-actions">
                            <button class="media-btn" onclick="copyToClipboard('<?= $webPath ?>')">
                                <span class="media-icon"><?= Icons::copy() ?></span>
                                <?= __('copy_path') ?>
                            </button>
                            <button class="media-btn delete-btn" onclick="deleteFile('<?= $fileName ?>')">
                                <span class="media-icon"><?= Icons::trash() ?></span>
                                <?= __('delete') ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <?php include __DIR__ . '/partials/media_admin_config.php'; ?>
    <script src="<?= C_ASSETS_URL ?>/js/admin-shared.js?v=2"></script>
    <script src="<?= C_ASSETS_URL ?>/js/admin-media.js?v=1"></script>
</body>
</html>
