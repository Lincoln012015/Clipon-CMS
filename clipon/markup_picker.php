<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/VisualPickerSupport.php';
require_once __DIR__ . '/lib/MarkupPickerConfig.php';
require_once __DIR__ . '/lib/ContentTemplateImporter.php';
require_once __DIR__ . '/lib/Blog.php';
require_once __DIR__ . '/admin/lib/BlogTagService.php';

Csrf::init();

function clipon_markup_picker_json(int $status, array $payload): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$scope = $request->query('scope', 'setup');
$hasSetupAdmin = is_string($session->get('admin_login')) && $session->get('admin_login') !== '';
$hasAdminUser = is_string($session->get('user')) && $session->get('user') !== '' && $session->get('role') === 'admin';

if ($scope === 'template') {
    if (!$hasAdminUser) {
        die('Access denied');
    }
} elseif (!$hasSetupAdmin) {
    die('Access denied');
}

$requestedFile = $request->query('file');
if (!$requestedFile) {
    die('No file specified');
}

$launchState = MarkupPickerConfig::resolveLaunchState((string)$request->query('mode', MarkupPickerConfig::MODE_MANUAL));
$mode = $launchState['mode'];
$scenario = $launchState['scenario'];
$initialPanel = $launchState['initialPanel'];

$filePath = $scope === 'template'
    ? VisualPickerSupport::resolveTemplateFile($requestedFile)
    : VisualPickerSupport::resolveEditableFile($requestedFile);
$file = VisualPickerSupport::normalizeFileName($requestedFile);
$sessionFileKey = $scope . ':' . $file;

if ($filePath === null) {
    if ($request->post('action') === 'save') {
        clipon_markup_picker_json(404, ['ok' => false, 'error' => 'File not found or not allowed']);
    }
    http_response_code(404);
    die('File not found or not allowed');
}

if ($request->post('action') === 'import_template_content') {
    if ($scope !== 'template') {
        clipon_markup_picker_json(400, ['ok' => false, 'error' => 'Template content import is only available for template scope']);
    }
    if (!Csrf::validate($request->post('csrf_token', ''))) {
        clipon_markup_picker_json(403, ['ok' => false, 'error' => __('error_invalid_csrf') ?: 'Invalid CSRF token']);
    }

    $importer = new ContentTemplateImporter();
    $result = $importer->importForTemplate($file);
    $status = !empty($result['ok']) ? 200 : 400;
    clipon_markup_picker_json($status, $result);
}

if ($request->post('action') === 'save') {
    if (!Csrf::validate($request->post('csrf_token', ''))) {
        clipon_markup_picker_json(403, ['ok' => false, 'error' => __('error_invalid_csrf') ?: 'Invalid CSRF token']);
    }

    $newHtml = $request->post('html');
    if (!$newHtml) {
        clipon_markup_picker_json(400, ['ok' => false, 'error' => 'Missing html payload']);
    }

    $newHtml = VisualPickerSupport::restoreMarkupShortcodePreviews($newHtml);
    $newHtml = VisualPickerSupport::sanitizeMarkupHtml($newHtml);
    $protectedPhpBlocks = $session->get('markup_picker_php_blocks', []);
    $filePhpBlocks = is_array($protectedPhpBlocks) && isset($protectedPhpBlocks[$sessionFileKey]) && is_array($protectedPhpBlocks[$sessionFileKey])
        ? $protectedPhpBlocks[$sessionFileKey]
        : [];
    if (!empty($filePhpBlocks)) {
        $phpRestore = VisualPickerSupport::restorePhpBlocks($newHtml, $filePhpBlocks);
        if (empty($phpRestore['ok'])) {
            clipon_markup_picker_json(400, ['ok' => false, 'error' => $phpRestore['error'] ?? 'Protected PHP block mismatch']);
        }
        $newHtml = $phpRestore['html'];
    }
    if (file_put_contents($filePath, $newHtml) === false) {
        clipon_markup_picker_json(500, ['ok' => false, 'error' => 'Unable to write file']);
    }

    $preparedFiles = $session->get('markup_picker_prepared_files', []);
    if (!is_array($preparedFiles)) {
        $preparedFiles = [];
    }
    $preparedFiles[] = $file;
    $session->set('markup_picker_prepared_files', array_values(array_unique($preparedFiles)));

    clipon_markup_picker_json(200, ['ok' => true]);
}

$setupLang = $scope === 'template' && class_exists('Translation')
    ? Translation::getLang()
    : $session->get('setup_lang', 'uk');
$langFile = __DIR__ . '/lang/' . $setupLang . '.php';
$allTrans = is_file($langFile) ? require $langFile : [];
$setupLabels = $allTrans['setup'] ?? [];
$blogLabels = $setupLabels['blog_picker_labels'] ?? [];
$blogTags = [];
if (class_exists('BlogTagService') && defined('C_DATA_PATH') && defined('C_CONTENT_PATH')) {
    $blogTagService = new BlogTagService(C_DATA_PATH . '/blog_tags.php', C_CONTENT_PATH . '/blog/');
    $blogTags = $blogTagService->getTags([], is_string($setupLang) ? $setupLang : null);
}

$defaultBlogLabels = [
    'title' => 'Clipon Markup Editor',
    'mode_list' => 'Blog List',
    'mode_post' => 'Post Template',
    'interact' => 'Interact with page',
    'return_picker' => 'Return to editor',
    'save' => 'Save Changes',
    'cancel' => 'Cancel',
    'container' => 'List container',
    'card' => 'Post card',
    'field_title' => 'Title',
    'field_image' => 'Image',
    'field_excerpt' => 'Excerpt',
    'field_date' => 'Date',
    'field_author' => 'Author',
    'field_tags' => 'Tags',
    'field_link' => 'Link',
    'field_pagination' => 'Pagination',
    'field_content' => 'Content',
    'field_thumbnail' => 'Thumbnail',
    'per_page' => 'Per page',
    'page_param' => 'Page param',
    'filter_tags' => 'Filter tags',
    'all_tags' => 'All tags',
    'blog_lists' => 'Blog lists',
    'list_name' => 'List name',
    'list_default_name' => 'List',
    'rename_list' => 'Rename',
    'rename_list_prompt' => 'List name',
    'add_list' => 'Add list',
    'remove_list' => 'Remove',
    'active_list' => 'Active list',
    'page_param_help' => 'Unique URL query parameter used by this list pagination, so multiple blog lists on one page do not share the same page number.',
    'choose_parent' => 'Choose element',
    'clicked_element' => 'clicked',
    'selected' => 'Selected',
    'not_selected' => 'Not selected',
    'collapse_sidebar' => 'Collapse sidebar',
    'expand_sidebar' => 'Expand sidebar',
    'need_list_fields' => 'Select a title field before saving.',
    'need_per_page' => 'Per page must be a number from 1 to 50.',
    'need_pagination_wrapper' => 'Select a pagination wrapper outside the post card.',
    'need_thumbnail_image' => 'Select an image element for thumbnail.',
    'select_tool' => 'Choose a tool, then click an element.',
    'list_ready' => 'Ready to save blog loop.',
    'pagination_ready' => 'Ready to save blog loop with custom pagination.',
    'post_ready' => 'Ready to save post template.',
    'need_container_card' => 'Select a list container and post card before saving.',
    'need_post_field' => 'Select at least one post template field before saving.',
    'field_card_warning' => 'Select fields inside the selected post card.',
    'saving' => 'Saving...',
    'saved' => 'Saved successfully!',
    'server_error' => 'Server error:',
    'request_error' => 'Request error:',
    'close_confirm' => 'Close without saving?',
];

$labels = array_merge([
    'title' => 'Clipon Markup Editor',
    'auto' => 'Auto',
    'manual' => 'Manual',
    'scenario_page_content' => 'Page Content',
    'scenario_blog_list' => 'Blog List',
    'scenario_blog_post' => 'Blog Post Template',
    'page_content_tools' => 'Page content tools',
    'blog_list_tools' => 'Blog list tools',
    'blog_post_tools' => 'Blog post template tools',
    'blog_list' => 'Blog List',
    'blog_post' => 'Blog Post',
    'interact' => 'Interact with page',
    'return_picker' => 'Return to editor',
    'apply_auto' => 'Apply auto rules',
    'import_template_content' => 'Імпортувати контент з шаблону',
    'import_template_content_done' => 'Імпорт завершено. Оновлено сторінок: {pages}. Додано ключів основної мови: {primary}. Додано ключів перекладів: {secondary}. Залишено без змін: {kept}.',
    'import_template_content_none' => 'Нових ключів не додано. Існуючий контент залишено без змін.',
    'save' => 'Save Changes',
    'cancel' => 'Cancel',
    'tags' => 'Tags',
    'exclude' => 'Exclude',
    'manual_hint' => 'Click elements to toggle editable markers.',
    'auto_ready' => 'Auto rules are ready.',
    'auto_applied' => 'Auto rules applied: {count} elements marked.',
    'marked' => 'Marked',
    'removed' => 'Removed marker',
    'saving' => 'Saving...',
    'saved' => 'Saved successfully!',
    'server_error' => 'Server error:',
    'request_error' => 'Request error:',
    'close_confirm' => 'Close without saving?',
], $setupLabels['markup_picker_labels'] ?? []);

$content = file_get_contents($filePath);
$protectedPhp = VisualPickerSupport::protectPhpBlocks($content === false ? '' : $content);
$content = $protectedPhp['html'];
$protectedPhpBlocks = $session->get('markup_picker_php_blocks', []);
if (!is_array($protectedPhpBlocks)) {
    $protectedPhpBlocks = [];
}
$protectedPhpBlocks[$sessionFileKey] = $protectedPhp['blocks'];
$session->set('markup_picker_php_blocks', $protectedPhpBlocks);
$content = VisualPickerSupport::renderMarkupShortcodePreviews($content);
$scriptName = $request->server('SCRIPT_NAME', '');
$scriptUrl = dirname($scriptName) . '/assets/js/markup_picker.js';
$styleUrl = dirname($scriptName) . '/assets/css/markup_picker.css';
$baseHref = $scope === 'template'
    ? VisualPickerSupport::calculateTemplateBaseHref($scriptName, $file)
    : VisualPickerSupport::calculateBaseHref($scriptName, $file);
$slug = pathinfo($file, PATHINFO_FILENAME) ?: 'page';
$autoTagDefaults = MarkupPickerConfig::autoTagDefaults();

$config = [
    'mode' => $mode,
    'scenario' => $scenario,
    'initialPanel' => $initialPanel,
    'scope' => $scope,
    'file' => $file,
    'slug' => preg_replace('/[^a-zA-Z0-9_-]+/', '_', $slug) ?: 'page',
    'csrfToken' => Csrf::token(),
    'tags' => $autoTagDefaults['tags'],
    'defaultTags' => $autoTagDefaults['defaultTags'],
    'exclude' => $autoTagDefaults['exclude'],
    'labels' => $labels,
    'blogLabels' => array_merge($defaultBlogLabels, is_array($blogLabels) ? $blogLabels : []),
    'blogTags' => $blogTags,
    'styleUrl' => $styleUrl,
];

echo VisualPickerSupport::injectBaseAndScript(
    $content,
    $baseHref,
    'clipon-markup-picker-config',
    'CLIPON_MARKUP_PICKER_CONFIG',
    $config,
    $scriptUrl,
    $styleUrl
);
