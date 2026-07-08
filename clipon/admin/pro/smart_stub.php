<?php
define('C_PRO_PROXY', true);

require_once __DIR__ . '/../../lib/Auth.php';

if (!$session->has('user')) {
    header('Location: ../login.php');
    exit;
}

$moduleId = (string)($request->query('module', ''));
if (!preg_match('/^[a-z0-9_\-]+$/', $moduleId)) {
    header('Location: ../index.php');
    exit;
}

if (function_exists('hasPermission') && !hasPermission('view_' . $moduleId)) {
    header('Location: ../index.php');
    exit;
}

$moduleInfo = [];
if (class_exists('ModuleManager')) {
    $allModules = ModuleManager::getModules();
    $moduleInfo = $allModules[$moduleId] ?? [];
}

if (empty($moduleInfo) || empty($moduleInfo['pro'])) {
    header('Location: ../index.php');
    exit;
}

$moduleLabel = (string)($moduleInfo['label'] ?? $moduleId);
$moduleDescription = trim((string)($moduleInfo['description'] ?? ''));
$tr = static function (string $key, string $fallback): string {
    $translated = Translation::get($key);
    return $translated !== $key ? (string)$translated : $fallback;
};

if ($moduleDescription === '') {
    $moduleDescription = $tr(
        'pro_smart_stub_default_description',
        'This premium module is available in PRO and helps unlock advanced workflows for your CMS.'
    );
}

$releaseStatus = (string)($moduleInfo['release_status'] ?? 'available');
if (!in_array($releaseStatus, ['available', 'coming_soon', 'in_development'], true)) {
    $releaseStatus = 'available';
}

$requiresLicense = !isset($moduleInfo['requires_license']) || (bool)$moduleInfo['requires_license'];
$isLicensed = ModuleManager::isProLicensed($moduleId);
$isMissingPackage = ModuleManager::isModuleMissing($moduleId);
$minCmsVersion = (string)($moduleInfo['min_cms_version'] ?? '0.0.0');
$cmsCompatible = !isset($moduleInfo['cms_compatible']) || (bool)$moduleInfo['cms_compatible'];

$bannerVariant = 'locked';
$bannerTitle = $moduleLabel;
$bannerDescription = $moduleDescription;
$bannerCta = $tr('pro_smart_stub_cta_learn_more', 'Learn more');
$bannerHref = '';

if ($isMissingPackage) {
    $bannerVariant = 'missing';
    $bannerTitle = $moduleLabel . ' - ' . $tr('pro_smart_stub_suffix_files_missing', 'files missing');
    $bannerDescription = $tr(
        'pro_smart_stub_missing_description',
        'Your license includes this module, but files are not installed on this site yet.'
    );
    $bannerCta = $tr('pro_missing_cta', 'How to install PRO module');
} elseif (!$cmsCompatible) {
    $bannerVariant = 'locked';
    $bannerTitle = $moduleLabel . ' - ' . $tr('pro_smart_stub_suffix_cms_update_required', 'CMS update required');
    $bannerDescription = $tr('pro_cms_incompatible_description', 'This module requires a newer CMS version.') . ' >= ' . $minCmsVersion;
    $bannerCta = $tr('pro_cms_incompatible_cta', 'Update CMS');
    $bannerHref = 'https://clipon-cms.com/dist/';
} elseif ($releaseStatus === 'in_development') {
    $bannerVariant = 'locked';
    $bannerTitle = $moduleLabel . ' - ' . $tr('pro_smart_stub_suffix_in_development', 'in development');
    $bannerDescription = $isLicensed
        ? $tr('pro_smart_stub_dev_description_with_entitlement', 'This module is included in your PRO plan and will unlock automatically after release.')
        : $tr('pro_smart_stub_dev_description_without_entitlement', 'This module is currently in development and will be available for PRO users after release.');
    $bannerCta = $tr('pro_smart_stub_cta_view_roadmap', 'View roadmap');
} elseif ($releaseStatus === 'coming_soon') {
    $bannerVariant = 'locked';
    $bannerTitle = $moduleLabel . ' - ' . $tr('pro_smart_stub_suffix_coming_soon', 'coming soon');
    $bannerDescription = $isLicensed
        ? $tr('pro_smart_stub_soon_description_with_entitlement', 'This module is included in your PRO plan and will unlock as soon as it is published.')
        : $tr('pro_smart_stub_soon_description_without_entitlement', 'This module is coming soon and will be available for PRO users.');
    $bannerCta = $tr('pro_smart_stub_cta_learn_more', 'Learn more');
} elseif ($requiresLicense && !$isLicensed) {
    $bannerVariant = 'locked';
    $bannerTitle = $moduleLabel;
    $bannerDescription = $moduleDescription;
    $bannerCta = $tr('pro_upgrade_cta', 'Upgrade to PRO');
}

$promoUrl = trim((string)($moduleInfo['promo_url'] ?? 'https://clipon-cms.com/pro'));
if (!preg_match('#^https://clipon-cms\.com(?:/.*)?$#i', $promoUrl)) {
    $promoUrl = 'https://clipon-cms.com/pro';
}

if ($bannerHref === '') {
    $bannerHref = $promoUrl;
}

$videoId = trim((string)($moduleInfo['video_id'] ?? ''));
$safeVideoId = '';
if ($videoId !== '' && preg_match('/^[A-Za-z0-9_-]{6,64}$/', $videoId)) {
    $safeVideoId = $videoId;
}
?>
<!DOCTYPE html>
<html lang="<?= Translation::getLang() ?>">
<head>
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin.css?v=15">
    <title><?= htmlspecialchars($moduleLabel, ENT_QUOTES, 'UTF-8') ?> - PRO</title>
</head>
<body class="admin-body">
    <div class="admin-container">
        <?php include __DIR__ . '/../nav.php'; ?>
        <main class="main-content" style="position: relative;">
            <?php AdminUI::proUpgradeBanner([
                'variant' => $bannerVariant,
                'title' => $bannerTitle,
                'description' => $bannerDescription,
                'cta' => $bannerCta,
                'href' => $bannerHref
            ]); ?>

            <section style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;margin-top:18px;display:grid;gap:16px;">
                <h2 style="margin:0;"><?= htmlspecialchars($tr('pro_smart_stub_learn_more_title', 'Learn More'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p style="margin:0;color:#475569;line-height:1.6;">
                    <?= htmlspecialchars($tr('pro_smart_stub_learn_more_description', 'Discover full module capabilities, pricing, and setup details on the official Clipon CMS website.'), ENT_QUOTES, 'UTF-8') ?>
                </p>
                <div>
                    <a href="<?= htmlspecialchars($promoUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
                        <?= htmlspecialchars($tr('pro_smart_stub_learn_more_cta', 'Learn more on clipon-cms.com'), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </div>
            </section>

            <?php if ($safeVideoId !== ''): ?>
            <section style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;margin-top:18px;display:grid;gap:12px;">
                <h2 style="margin:0;"><?= htmlspecialchars($tr('pro_smart_stub_video_preview', 'Video Preview'), ENT_QUOTES, 'UTF-8') ?></h2>
                <div style="position:relative;padding-top:56.25%;border-radius:10px;overflow:hidden;border:1px solid #e2e8f0;">
                    <iframe
                        src="https://www.youtube.com/embed/<?= htmlspecialchars($safeVideoId, ENT_QUOTES, 'UTF-8') ?>"
                        title="<?= htmlspecialchars($moduleLabel, ENT_QUOTES, 'UTF-8') ?> video"
                        style="position:absolute;inset:0;width:100%;height:100%;border:0;"
                        loading="lazy"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen>
                    </iframe>
                </div>
            </section>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
