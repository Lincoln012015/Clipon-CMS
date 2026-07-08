<?php
require_once __DIR__ . '/../lib/Auth.php';

AdminAccess::requireUser($session, 'login.php');

$user = $session->get('user', 'unknown');
$role = $session->get('role', 'unknown');

$blogDir = C_CONTENT_PATH . '/blog/';
$directoriesFile = C_CONFIG_PATH . '/blog_directories.php';
$pagesDir = C_CONTENT_PATH . '/pages/';

$blogDirectoryService = new BlogDirectoryService($directoriesFile, $blogDir);

// Allow module to provide BlogService instance via filter; fallback to core BlogService
$blogService = null;
if (class_exists('Hooks')) {
    $blogService = Hooks::applyFilters('multilang:blog_service_instance', null, $blogDir, $pagesDir);
}
if (!is_object($blogService)) {
    $blogService = new BlogService($blogDir, $pagesDir);
}
$blogService->ensureBlogDirectory();
$blogTagService = new BlogTagService(C_DATA_PATH . '/blog_tags.php', $blogDir);
$blogActiveLangs = array_values(array_filter(Settings::getLanguages(), fn($l) => !empty($l['enabled'])));
if (empty($blogActiveLangs)) {
    $blogActiveLangs = [['code' => (Settings::load()['language'] ?? 'en'), 'name' => 'Default', 'enabled' => true]];
}
$blogPrimaryLang = (string)($blogActiveLangs[0]['code'] ?? (Settings::load()['language'] ?? 'en'));
$blogActiveLangCodes = array_values(array_map(fn($l) => (string)($l['code'] ?? ''), $blogActiveLangs));
$requestedEditLang = $request->string('edit_lang');
$currentEditLang = in_array($requestedEditLang, $blogActiveLangCodes, true) ? $requestedEditLang : $blogPrimaryLang;

// Helper functions
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

// Helper: отримати список шаблонів
function getTemplates() {
    global $blogService;
    return $blogService->getTemplates();
}

$posts = $blogService->loadPosts();
$blogTagService->syncFromPosts($posts, $currentEditLang);
$blogTags = $blogTagService->getTags($posts, $currentEditLang);
$directories = $blogDirectoryService->getDirectories();
$treeData = $blogService->buildTree($directories, $posts);
$rootDirs = $treeData['root_dirs'];
$rootPosts = $treeData['root_posts'];

function getLocalizedPostTitle(array $post, string $lang, string $primaryLang): string {
    $title = trim((string)($post['locales'][$lang]['title'] ?? ''));
    if ($title === '') {
        $title = trim((string)($post['locales'][$primaryLang]['title'] ?? ''));
    }

    return $title !== '' ? $title : (string)($post['slug'] ?? '');
}

function getAllPostsInDir($d, &$list, string $lang, string $primaryLang) {
    foreach ($d['posts'] as $p) {
        $list[] = getLocalizedPostTitle($p, $lang, $primaryLang);
    }
    foreach ($d['children'] as $c) {
        getAllPostsInDir($c, $list, $lang, $primaryLang);
    }
}

// Icons
function getEditIcon() {
    return Icons::pencil(18);
}

function getDeleteIcon() {
    return Icons::trash(18);
}

function getViewIcon() {
    return Icons::eye(18);
}

function getToggleIcon($active = true) {
    // switch/tumbler style icon
    return Icons::toggle($active, 20);
}

function getDuplicateIcon() {
    return Icons::copy(18);
}

function renderBlogTagPicker(string $name, array $selectedTags, array $allTags, string $id): string {
    $selected = array_values(array_filter(array_map('trim', $selectedTags), static fn($tag) => $tag !== ''));
    $selectedJson = htmlspecialchars(json_encode($selected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE);
    $allJson = htmlspecialchars(json_encode($allTags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE);
    $placeholder = htmlspecialchars(__('blog_tag_search_placeholder'), ENT_QUOTES | ENT_SUBSTITUTE);
    $addLabel = htmlspecialchars(__('blog_tag_add'), ENT_QUOTES | ENT_SUBSTITUTE);

    return '<div class="blog-tag-picker" data-blog-tag-picker data-selected=\'' . $selectedJson . '\' data-tags=\'' . $allJson . '\'>'
        . '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE) . '" value="' . htmlspecialchars(implode(',', $selected), ENT_QUOTES | ENT_SUBSTITUTE) . '">'
        . '<div class="blog-tag-selected" data-tag-selected></div>'
        . '<div class="blog-tag-input-row">'
        . '<input type="text" id="' . htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE) . '" class="form-control" data-tag-input placeholder="' . $placeholder . '">'
        . '<button type="button" class="btn btn-secondary" data-tag-add>' . $addLabel . '</button>'
        . '</div>'
        . '<div class="blog-tag-options" data-tag-options></div>'
        . '</div>';
}?>
<!DOCTYPE html>
<html lang="<?= Translation::getLang() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= __('blog_title') ?></title>
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin.css?v=18">
    <script src="<?= C_ASSETS_URL ?>/vendor/sortablejs/Sortable.min.js"></script>
</head>
<body>
    <div class="admin-container">
        <?php include __DIR__ . '/nav.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1><?= __('blog') ?></h1>
                    <p><?= __('blog_subtitle') ?></p>
                </div>
                <div class="header-actions">
                    <?php if (hasPermission('create_blog')): ?>
                        <button type="button" class="btn" data-blog-action="open-post-modal"><?= Icons::fileText(18) ?> <?= __('create_post') ?></button>
                        <button type="button" class="btn btn-secondary" data-blog-action="open-dir-modal" data-dir-action="add_dir"><?= Icons::folder(18) ?> <?= __('add_category') ?></button>
                    <?php endif; ?>
                    <?php if (hasPermission('edit_blog')): ?>
                        <button type="button" class="btn btn-secondary" data-blog-action="open-tag-manager"><?= __('blog_tags') ?></button>
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
            include __DIR__ . '/partials/admin_flash.php';
            ?>



                <div class="tree-view">
                    <?php
                    function renderTree($dirs, $posts, $parentId = '') {
                        global $currentEditLang, $request, $blogTags, $blogTagService, $blogActiveLangs, $blogPrimaryLang;
                        echo '<ul class="sortable-list" data-parent-id="' . $parentId . '">';
                        foreach ($dirs as $dir) {
                            $postsList = [];
                            getAllPostsInDir($dir, $postsList, $currentEditLang ?? $blogPrimaryLang, $blogPrimaryLang);
                            $postsStr = implode(', ', $postsList);
                            
                            echo '<li class="tree-item" data-id="' . $dir['id'] . '" data-type="dir">';
                            echo '<details open>';
                            echo '<summary class="tree-content">';
                            echo '<span class="drag-handle">' . Icons::dotsSixVertical() . '</span>';
                            echo '<span class="dir-toggle">' . Icons::caretRight() . '</span>';
                            echo '<span class="icon">' . Icons::folder() . '</span>';
                            echo '<span class="title">' . htmlspecialchars($dir['name']) . '</span>';
                            echo '<div class="actions">';

                            $dirId = $dir['id'];
                            $dirName = $dir['name'];
                            $dirParent = $dir['parent'] ?? '';
                            $escapedDirId = htmlspecialchars((string)$dirId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            $escapedDirName = htmlspecialchars((string)$dirName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            $escapedDirParent = htmlspecialchars((string)$dirParent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            $escapedPostsStr = htmlspecialchars((string)$postsStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                            if (hasPermission('edit_blog')) {
                                echo '<button type="button" class="icon-btn edit-btn" data-blog-action="edit-dir" data-dir-id="' . $escapedDirId . '" data-dir-name="' . $escapedDirName . '" data-dir-parent="' . $escapedDirParent . '">' . getEditIcon() . '</button>';
                            }
                            if (hasPermission('delete_blog')) {
                                echo '<button type="button" class="icon-btn delete-btn" data-blog-action="delete-dir" data-dir-id="' . $escapedDirId . '" data-dir-posts="' . $escapedPostsStr . '">' . getDeleteIcon() . '</button>';
                            }
                            echo '</div>';
                            echo '</summary>';
                            renderTree($dir['children'], $dir['posts'], $dir['id']);
                            echo '</details>';
                            echo '</li>';
                        }
                        
                        foreach ($posts as $post) {
                            $adminLang = $currentEditLang ?? $blogPrimaryLang;
                            $displayTitle = getLocalizedPostTitle($post, $adminLang, $blogPrimaryLang);
                            $postSlug = (string)($post['slug'] ?? '');
                            $escapedSlug = htmlspecialchars($postSlug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            $escapedTitle = htmlspecialchars($displayTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                            echo '<li class="tree-item" data-id="' . $escapedSlug . '" data-type="post">';
                            echo '<details class="page-details">';
                            echo '<summary class="tree-content">';
                            echo '<span class="drag-handle">' . Icons::dotsSixVertical() . '</span>';
                            echo '<span class="icon" style="margin-left: 20px;">' . Icons::fileText() . '</span>';
                            echo '<span class="title">' . $escapedTitle . '</span>';
                            echo '<div class="actions">';
                             // View
                             $url = $post['url'] ?? ('/blog/' . $postSlug);
                             $viewLang = $currentEditLang ?? $blogPrimaryLang;
                             if ($viewLang !== $blogPrimaryLang) {
                                 $translatedSlug = trim((string)($post['locales'][$viewLang]['slug'] ?? ''));
                                 if ($translatedSlug !== '') {
                                     $url = '/' . $viewLang . '/blog/' . $translatedSlug;
                                 }
                             }
                             $escapedUrl = htmlspecialchars((string)$url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                             echo '<button type="button" class="icon-btn view-btn" data-blog-action="view" data-url="' . $escapedUrl . '">' . getViewIcon() . '</button>';
                             
                             if (hasPermission('create_blog')) {
                                 echo '<button type="button" class="icon-btn duplicate-btn" data-blog-action="duplicate" data-slug="' . $escapedSlug . '" data-title="' . $escapedTitle . '">' . getDuplicateIcon() . '</button>';
                             }
                             
                             if (hasPermission('edit_blog')) {
                                 // Active Toggle
                                $isActive = $post['active'] ?? true;
                                $activeClass = $isActive ? 'active-btn active' : 'active-btn inactive';
                                echo '<button type="button" class="icon-btn ' . $activeClass . '" data-blog-action="toggle-active" data-slug="' . $escapedSlug . '">' . getToggleIcon($isActive) . '</button>';
                                 // Edit
                                 $baseUrl = $post['url'] ?? ('/blog/' . $postSlug);

                                 // Визначаємо мову для редагування
                                 $activeLangs = array_filter(Settings::getLanguages(), fn($l) => !empty($l['enabled']));
                                 $primaryLang = !empty($activeLangs) ? $activeLangs[array_key_first($activeLangs)]['code'] : 'uk';
                                 $currentAdminLang = $currentEditLang ?? $primaryLang;

                                 if ($currentAdminLang !== $primaryLang) {
                                     $baseUrl = '/' . $currentAdminLang . $baseUrl;
                                 }

                                 $editUrl = $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'edit=1';
                                 $escapedEditUrl = htmlspecialchars((string)$editUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                 echo '<button type="button" class="icon-btn edit-btn" data-blog-action="edit" data-url="' . $escapedEditUrl . '" data-tooltip="Inline Edit">'.getEditIcon().'</button>';
                             }
                             if (hasPermission('delete_blog')) {
                                 echo '<button type="button" class="icon-btn delete-btn" data-blog-action="delete" data-slug="' . $escapedSlug . '" data-title="' . $escapedTitle . '">'.getDeleteIcon().'</button>';
                             }
                            echo '</div>';
                            echo '</summary>';
                            
                            // Edit Form
                            if (hasPermission('edit_blog')) {
                                echo '<div class="page-edit-form">';
                                
                                // Language selection for editing
                                $configuredLangs = Settings::getLanguages();
                                $activeLangs = array_values(array_filter($configuredLangs, fn($l) => !empty($l['enabled'])));
                                if (empty($activeLangs)) {
                                    $activeLangs = [['code' => 'uk', 'name' => 'Українська']];
                                }
                                $primaryLang = $activeLangs[0]['code'];
                                $editingLang = $request->query('edit_lang', $primaryLang);

                                // Normalization helper for JS injection
                                $normalizeForJs = function($val) {
                                    return is_scalar($val) ? (string)$val : '';
                                };

                                $primaryLocaleData = (isset($post['locales'][$primaryLang]) && is_array($post['locales'][$primaryLang])) ? $post['locales'][$primaryLang] : [];

                                $primaryDataPrepared = [
                                    'title' => $normalizeForJs($primaryLocaleData['title'] ?? ''),
                                    'excerpt' => $normalizeForJs($primaryLocaleData['excerpt'] ?? ''),
                                    'slug' => $post['slug'] ?? '',
                                    'thumbnail' => $post['thumbnail'] ?? '',
                                    'url' => '/blog/' . ($post['slug'] ?? ''),
                                    'seo' => [
                                        'meta_title' => $normalizeForJs($primaryLocaleData['seo']['meta_title'] ?? ''),
                                        'meta_description' => $normalizeForJs($primaryLocaleData['seo']['meta_description'] ?? '')
                                    ]
                                ];

                                $localesPrepared = [];
                                if (isset($post['locales']) && is_array($post['locales'])) {
                                    foreach ($post['locales'] as $lCode => $tData) {
                                        $localesPrepared[$lCode] = [
                                            'title' => $normalizeForJs($tData['title'] ?? ''),
                                            'excerpt' => $normalizeForJs($tData['excerpt'] ?? ''),
                                            'slug' => $tData['slug'] ?? '',
                                            'seo' => [
                                                'meta_title' => $normalizeForJs($tData['seo']['meta_title'] ?? ''),
                                                'meta_description' => $normalizeForJs($tData['seo']['meta_description'] ?? '')
                                            ]
                                        ];
                                    }
                                }

                                echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">';
                                echo '<h4 style="margin: 0; font-size: 0.95rem; font-weight: 600;">' . __('page_settings') . '</h4>';
                                echo '<div><small style="color:#666;">' . __('edit_language') . ': <strong>' . strtoupper($editingLang) . '</strong></small></div>';
                                echo '</div>';

                                echo '<form method="POST" class="page-edit-form-ajax page-edit-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;" data-locales=\'' . htmlspecialchars(json_encode($localesPrepared), ENT_QUOTES) . '\' data-primary=\'' . htmlspecialchars(json_encode($primaryDataPrepared), ENT_QUOTES) . '\' data-primary-lang="'.$primaryLang.'">';
                                echo '<input type="hidden" name="action" value="update_post">';
                                echo '<input type="hidden" name="slug" value="' . $post['slug'] . '">';
                                echo '<input type="hidden" name="editing_lang" value="' . htmlspecialchars($editingLang) . '">';

                                $getVal = function($field) use ($post, $editingLang, $primaryLang) {
                                    if ($editingLang === $primaryLang) {
                                        return (string)($post['locales'][$primaryLang][$field] ?? '');
                                    }

                                    return (string)($post['locales'][$editingLang][$field] ?? '');
                                };
                                $getSeoVal = function($field) use ($post, $editingLang, $primaryLang) {
                                    if ($editingLang === $primaryLang) {
                                        return (string)($post['locales'][$primaryLang]['seo'][$field] ?? '');
                                    }

                                    return (string)($post['locales'][$editingLang]['seo'][$field] ?? '');
                                };

                                $titleVal = $getVal('title');
                                echo '<div><label>' . __('post_title') . ' - <span class="lang-code">' . strtoupper($editingLang) . '</span>: <input type="text" name="title" value="' . htmlspecialchars($titleVal) . '" required></label></div>';
                                
                                $slugVal = ($editingLang === $primaryLang) ? $post['slug'] : ($post['locales'][$editingLang]['slug'] ?? $post['slug']);
                                echo '<div><label>' . __('post_slug') . ' - <span class="lang-code">' . strtoupper($editingLang) . '</span>: <input type="text" name="new_slug" value="' . htmlspecialchars($slugVal) . '" required></label></div>';

                                $excerptVal = $getVal('excerpt');
                                echo '<div style="grid-column: 1 / span 2;"><label>' . __('blog_post_excerpt') . ' - <span class="lang-code">' . strtoupper($editingLang) . '</span>: <textarea name="excerpt" rows="3" maxlength="320" placeholder="' . htmlspecialchars(__('blog_post_excerpt_placeholder'), ENT_QUOTES | ENT_SUBSTITUTE) . '">' . htmlspecialchars($excerptVal) . '</textarea></label></div>';

                                $thumbnailVal = $post['thumbnail'] ?? '';
                                echo '<div style="grid-column: span 2;"><label>' . (__('post_thumbnail') ?: 'Thumbnail') . ':</label>';
                                echo '<div class="input-group">';
                                $thumbnailInputId = 'editThumbnail_' . preg_replace('/[^a-z0-9_\-]/i', '_', $postSlug);
                                $escapedThumbnailInputId = htmlspecialchars($thumbnailInputId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                echo '<input type="text" name="thumbnail" id="' . $escapedThumbnailInputId . '" value="' . htmlspecialchars($thumbnailVal) . '" class="form-control" placeholder="/assets/uploads/image.jpg">';
                                echo '<button type="button" class="btn btn-secondary" data-blog-action="open-media-selector" data-input-id="' . $escapedThumbnailInputId . '">' . Icons::image(16) . '</button>';
                                echo '</div></div>';
                                
                                $https = $request->server('HTTPS');
                                $serverPort = $request->server('SERVER_PORT');
                                $protocol = ((!empty($https) && $https !== 'off') || (string)$serverPort === '443') ? "https://" : "http://";
                                $actualUrl = ($editingLang === $primaryLang) ? ('/blog/' . $post['slug']) : ('/' . $editingLang . '/blog/' . ($post['locales'][$editingLang]['slug'] ?? $post['slug']));
                                $fullUrl = $protocol . $request->server('HTTP_HOST', 'localhost') . $actualUrl;
                                $escapedFull = htmlspecialchars($fullUrl, ENT_QUOTES | ENT_SUBSTITUTE);

                                echo '<div style="grid-column: 1 / span 2;"><small style="color: #666;">URL: ';
                                echo '<a href="' . $escapedFull . '" target="_blank" class="page-full-url">' . $escapedFull . '</a> ';
                                echo '<button type="button" class="copy-url-btn icon-btn" data-blog-action="copy-url" data-url="' . $escapedFull . '" data-tooltip="Copy URL"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>';
                                echo '</small></div>';
                                
                                echo '<div><label>' . __('blog_post_author') . ': <input type="text" name="author" value="' . htmlspecialchars($post['author'] ?? '') . '"></label></div>';
                                echo '<div><label>' . __('blog_post_date') . ': <input type="date" name="date" value="' . htmlspecialchars($post['date'] ?? '') . '"></label></div>';
                                
                                echo '<div style="grid-column: 1 / span 2; margin-top: 10px; padding-top: 10px; border-top: 1px solid #f1f5f9;">';
                                $metaTitleVal = $getSeoVal('meta_title');
                                $metaDescVal = $getSeoVal('meta_description');
                                echo '<label>Meta Title - <span class="lang-code">' . strtoupper($editingLang) . '</span>: <input type="text" name="meta_title" value="' . htmlspecialchars($metaTitleVal) . '"></label>';
                                echo '<label style="margin-top: 10px;">Meta Description - <span class="lang-code">' . strtoupper($editingLang) . '</span>: <textarea name="meta_description" rows="2">' . htmlspecialchars($metaDescVal) . '</textarea></label>';
                                echo '</div>';

                                $postTags = $blogTagService->parseTagIds($post['tags'] ?? [], $editingLang, false);
                                echo '<div style="grid-column: 1 / span 2;"><label>' . __('blog_tags_label') . ':</label>' . renderBlogTagPicker('tags', $postTags, $blogTags, 'tags_' . preg_replace('/[^a-z0-9_\-]/i', '_', $post['slug'])) . '</div>';
                                
                                echo '<div><label>' . __('post_template') . ': <select name="template">';
                                $templates = getTemplates();
                                foreach($templates as $tpl) {
                                    $sel = ($post['template'] ?? '') === $tpl ? 'selected' : '';
                                    echo "<option value=\"{$tpl}\" {$sel}>{$tpl}</option>";
                                }
                                echo '</select></label></div>';

                                echo '<div style="grid-column: 1 / span 2;">' . __('blog_post_modified') . ': ' . htmlspecialchars($post['modified'] ?? '') . '</div>';
                                echo '<button type="submit" class="btn" style="grid-column: 1 / span 2;">' . __('blog_post_save_settings') . '</button>';
                                echo '</form>';
                                echo '</div>';
                            }
                            echo '</details>';
                            echo '</li>';
                        }
                        echo '</ul>';
                    }
                    renderTree($rootDirs, $rootPosts);
                    ?>
                </div>
        </main>
    </div>

    <?php include __DIR__ . '/partials/blog_modals.php'; ?>
    <?php include __DIR__ . '/partials/blog_admin_config.php'; ?>
    <script src="<?= C_ASSETS_URL ?>/js/admin-shared.js?v=2"></script>
    <script type="module" src="<?= C_ASSETS_URL ?>/js/admin-media-picker.js?v=1"></script>
    <script src="<?= C_ASSETS_URL ?>/js/admin-blog.js?v=4"></script>
</body>
</html>
