<?php
require_once __DIR__ . '/../lib/Auth.php';

// Require login and permission
if (!$session->has('user')) {
    header('Location: login.php');
    exit;
}

if (!hasPermission('view_versions')) {
    http_response_code(403);
    echo __('error_forbidden');
    exit;
}

require_once __DIR__ . '/../lib/History.php';
require_once __DIR__ . '/../lib/Renderer.php';
require_once __DIR__ . '/../lib/JsonStorage.php';

$slug = $request->query('slug', '');
$timestamp = $request->query('timestamp', '');

if (!$slug || !$timestamp) {
    http_response_code(400);
    echo __('error_missing_parameters');
    exit;
}

$history = new History();
$version = $history->getVersionEntry($slug, $timestamp);
$canRestore = hasPermission('restore_versions');

if (!$version) {
    http_response_code(404);
    echo __('error_version_not_found');
    exit;
}

$pageData = $version['data'] ?? null;
if (!$pageData) {
    http_response_code(500);
    echo __('error_invalid_version_data');
    exit;
}

$currentPageFile = C_CONTENT_PATH . '/pages/' . $slug . '.php';
$isCurrentVersion = false;
if (file_exists($currentPageFile)) {
    $currentPageData = read_json_file($currentPageFile);
    $currentHash = $history->computeDataHash($currentPageData);
    $versionHash = $history->computeDataHash($pageData);
    $isCurrentVersion = ($currentHash !== '' && $versionHash !== '' && hash_equals($currentHash, $versionHash));
}

$template = $pageData['template'] ?? null;
$templatePath = $template ? __DIR__ . '/../../templates/' . $template : null;

// If template exists, load it as HTML; otherwise render simple preview
if ($templatePath && file_exists($templatePath)) {
    // Get HTML of template
    $html = file_get_contents($templatePath);
    // Use Renderer to inject content blocks
    $renderer = new Renderer($templatePath, $pageData['content'] ?? []);
    $rendered = $renderer->render();
} else {
    // Fallback simple HTML
    $title = htmlspecialchars($pageData['title'] ?? $slug, ENT_QUOTES | ENT_SUBSTITUTE);
    $contentHtml = '';
    foreach ($pageData['content'] ?? [] as $id => $val) {
        $contentHtml .= '<div><strong>' . htmlspecialchars($id) . ':</strong><div>' . $val . '</div></div>';
    }
    $rendered = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Preview: ' . $title . '</title></head><body><h1>' . $title . '</h1>' . $contentHtml . '</body></html>';
}

// Inject admin toolbar before </body>
$toAdminLabel = __('to_admin');
$closeLabel = __('close_preview');
$setCurrentLabel = __('set_version_current');
$previewLabel = __('preview');
$restoreConfirm = __('restore_confirm');
$restoreSuccess = __('restore_success');
$errorPrefix = __('error_prefix');
$errorNetwork = __('error_network');
$currentVersionLabel = __('current_version');

$toolbarActions = '<a href="/clipon/admin/pages.php" style="color:#fff;text-decoration:underline;margin-right:12px;">' . htmlspecialchars($toAdminLabel, ENT_QUOTES | ENT_SUBSTITUTE) . '</a>';
if ($canRestore) {
    $disabledAttr = $isCurrentVersion ? ' disabled' : '';
    $disabledStyle = $isCurrentVersion ? 'opacity:0.55;cursor:not-allowed;' : 'cursor:pointer;';
    $toolbarActions .= '<button id="set-current-version-btn" type="button"' . $disabledAttr . ' style="margin-right:12px;background:none;border:none;color:#fff;text-decoration:underline;padding:0;font:inherit;' . $disabledStyle . '">' . htmlspecialchars($setCurrentLabel, ENT_QUOTES | ENT_SUBSTITUTE) . '</button>';
}
$toolbarActions .= '<a href="javascript:window.close();" style="color:#fff;text-decoration:underline;">' . htmlspecialchars($closeLabel, ENT_QUOTES | ENT_SUBSTITUTE) . '</a>';

$currentBadge = $isCurrentVersion
    ? '<span style="display:inline-flex;align-items:center;margin-left:8px;padding:0.08rem 0.45rem;border-radius:9999px;background:#dcfce7;color:#166534;font-size:0.66rem;font-weight:700;letter-spacing:0.01em;">' . htmlspecialchars($currentVersionLabel, ENT_QUOTES | ENT_SUBSTITUTE) . '</span>'
    : '';

$toolbar = '<div style="position:fixed;left:0;right:0;top:0;background:#111827;color:#fff;padding:10px 12px;z-index:99999;display:flex;justify-content:space-between;align-items:center;font-family: sans-serif;gap:12px;">
<div>' . htmlspecialchars($previewLabel, ENT_QUOTES | ENT_SUBSTITUTE) . ': <strong>' . htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE) . '</strong>' . $currentBadge . ' &middot; ' . htmlspecialchars($version['author'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE) . ' @ ' . date('Y-m-d H:i:s', (int)$version['timestamp']) . '</div>
<div style="display:flex;align-items:center;">' . $toolbarActions . '</div>
</div>';

$restoreScript = '';
if ($canRestore) {
    $restoreScript = '<script>(function(){'
        . 'var btn=document.getElementById("set-current-version-btn");'
        . 'if(!btn){return;}'
        . 'btn.addEventListener("click",function(){'
        . 'if(!window.confirm(' . json_encode($restoreConfirm) . ')){return;}'
        . 'var formData=new FormData();'
        . 'formData.append("action","restore_version");'
        . 'formData.append("slug",' . json_encode((string)$slug) . ');'
        . 'formData.append("timestamp",' . json_encode((string)$timestamp) . ');'
        . 'fetch("/clipon/admin/pages.php",{method:"POST",body:formData})'
        . '.then(function(response){return response.json();})'
        . '.then(function(data){'
        . 'if(data.status==="success"){alert(' . json_encode($restoreSuccess) . ');window.location.href="/clipon/admin/pages.php";return;}'
        . 'alert(' . json_encode($errorPrefix) . '+(data.message||""));'
        . '})'
        . '.catch(function(){alert(' . json_encode($errorNetwork) . ');});'
        . '});'
        . '})();</script>';
}

if (strpos($rendered, '</body>') !== false) {
    $rendered = str_replace('</body>', $toolbar . $restoreScript . '</body>', $rendered);
} else {
    $rendered = $toolbar . $restoreScript . $rendered;
}

echo $rendered;

?>
