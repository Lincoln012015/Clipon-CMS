<?php
$currentPage = basename($request->server('PHP_SELF'));

// System hook for module UI initialization
Hooks::doAction('admin_ui_init', $currentPage);

// Ensure $role is available. It should be set in the parent script.
if (!isset($role)) {
    $role = $session->get('role', '');
}

$cmsVersion = class_exists('CmsVersion') ? CmsVersion::current() : '0.0.0';
$isProLicenseActive = class_exists('License') && License::isValid();

// 1. Define base menu structure
$adminMenu = [
    'dashboard' => [
        'label' => __('dashboard'),
        'icon'  => Icons::dashboard(),
        'url'   => C_ADMIN_URL . '/index.php',
        'active' => $currentPage == 'index.php',
        'priority' => 10
    ],
    'pages' => [
        'label' => __('pages'),
        'icon'  => Icons::pages(),
        'url'   => C_ADMIN_URL . '/pages.php',
        'active' => $currentPage == 'pages.php',
        'priority' => 20
    ],
    'blog' => [
        'label' => __('blog'),
        'icon'  => Icons::blog(),
        'url'   => C_ADMIN_URL . '/blog.php',
        'active' => $currentPage == 'blog.php',
        'priority' => 30
    ]
];

// 2. Add permission-based items
if (!function_exists('hasPermission') || $role === 'admin' || hasPermission('view_media') || hasPermission('manage_media')) {
    $adminMenu['media'] = [
        'label' => __('media'),
        'icon'  => Icons::media(),
        'url'   => C_ADMIN_URL . '/media.php',
        'active' => $currentPage == 'media.php',
        'priority' => 40
    ];
}

if ($role === 'admin' || (function_exists('hasPermission') && hasPermission('view_pro_users'))) {
    $adminMenu['users'] = [
        'label' => __('users'),
        'icon'  => Icons::users(),
        'url'   => C_ADMIN_URL . '/users.php',
        'active' => $currentPage == 'users.php',
        'priority' => 50
    ];
}

if ($role === 'admin') {
    $adminMenu['settings'] = [
        'label' => __('settings'),
        'icon'  => Icons::settings(),
        'url'   => C_ADMIN_URL . '/settings.php',
        'active' => $currentPage == 'settings.php',
        'priority' => 100 // Settings usually last
    ];
}

if (function_exists('hasPermission') && hasPermission('manage_redirects')) {
    $adminMenu['redirects'] = [
        'label' => __('redirects'),
        'icon'  => Icons::redirects(),
        'url'   => C_ADMIN_URL . '/redirects.php',
        'active' => $currentPage == 'redirects.php',
        'priority' => 60
    ];
}

// 3. Submenus (like Analytics)
if (function_exists('hasPermission') && hasPermission('view_analytics')) {
    $analyticsSettings = class_exists('Settings') ? Settings::load() : [];
    $analyticsSubmenu = [
        ['label' => __('analytics_general_tab'), 'url' => C_ADMIN_URL . '/analytics.php', 'active' => $currentPage == 'analytics.php']
    ];

    if (!empty($analyticsSettings['enable_funnels'])) {
        $analyticsSubmenu[] = ['label' => __('analytics_funnels_tab'), 'url' => C_ADMIN_URL . '/analytics_funnels.php', 'active' => $currentPage == 'analytics_funnels.php'];
    }

    if (!empty($analyticsSettings['enable_attribution'])) {
        $analyticsSubmenu[] = ['label' => __('analytics_attribution_tab'), 'url' => C_ADMIN_URL . '/analytics_attribution.php', 'active' => $currentPage == 'analytics_attribution.php'];
    }

    $analyticsPages = ['analytics.php', 'analytics_funnels.php', 'analytics_attribution.php'];
    $adminMenu['analytics'] = [
        'label' => __('analytics'),
        'icon'  => Icons::analytics(),
        'url'   => C_ADMIN_URL . '/analytics.php',
        'active' => in_array($currentPage, $analyticsPages),
        'priority' => 70,
        'submenu' => $analyticsSubmenu
    ];
}

// 4. APPLY FILTERS (The Hook)
// This allows Pro modules to inject themselves or modify the menu
$adminMenu = Hooks::applyFilters('admin_menu', $adminMenu);

// 5. Apply Module-based menu items (Real and Virtual)
if ($role === 'admin' && class_exists('ModuleManager')) {
    $currentModuleQuery = (string)$request->query('module', '');
    $currentScriptName = basename((string)$request->server('PHP_SELF', ''));

    foreach (ModuleManager::getModules() as $id => $module) {
        if (!empty($module['hide_in_nav'])) continue;
        // If module is already in menu (added via filter/hook), skip it
        if (isset($adminMenu[$id])) continue;

        // Determine if it's a PRO module and its license state
        $isPro = !empty($module['pro']);
        $isLicensed = ModuleManager::isProLicensed($id);
        $isAvailable = ModuleManager::isProAvailable($id);
        $isMissing = !empty($module['missing_files']);
        $releaseStatus = (string)($module['release_status'] ?? 'available');
        $hasUpdate = !empty($module['has_update']);
        $cmsCompatible = !isset($module['cms_compatible']) || (bool)$module['cms_compatible'];
        $minCmsVersion = (string)($module['min_cms_version'] ?? '0.0.0');

        // If it's a PRO module, we always show it, but with different styling if locked or missing
        if ($isPro) {
            $label = $module['label'] ?? $id;
            
            if ($isMissing) {
                $label .= ' <span class="badge-pro-missing" data-tooltip="' . htmlspecialchars(__('pro_nav_missing_tooltip'), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(__('pro_nav_missing_label'), ENT_QUOTES, 'UTF-8') . '</span>';
            } elseif (!$cmsCompatible) {
                $checkTooltipTemplate = (string)__('pro_nav_cms_incompatible_tooltip');
                if ($checkTooltipTemplate === 'pro_nav_cms_incompatible_tooltip') {
                    $checkTooltipTemplate = 'Requires CMS version %s or newer.';
                }
                $checkTooltip = sprintf($checkTooltipTemplate, $minCmsVersion);
                $checkLabel = (string)__('pro_nav_cms_incompatible_label');
                if ($checkLabel === 'pro_nav_cms_incompatible_label') {
                    $checkLabel = 'CHECK';
                }
                $label .= ' <span class="badge-pro-check" data-tooltip="' . htmlspecialchars($checkTooltip, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($checkLabel, ENT_QUOTES, 'UTF-8') . '</span>';
            } elseif (in_array($releaseStatus, ['coming_soon', 'in_development'], true)) {
                $badgeText = $releaseStatus === 'in_development'
                    ? (string)__('pro_nav_in_dev_label')
                    : (string)__('pro_nav_soon_label');
                if ($badgeText === 'pro_nav_in_dev_label' || $badgeText === 'pro_nav_soon_label') {
                    $badgeText = $releaseStatus === 'in_development' ? 'IN DEV' : 'SOON';
                }
                $badgeTooltip = $releaseStatus === 'in_development'
                    ? (string)__('pro_nav_in_dev_tooltip')
                    : (string)__('pro_nav_soon_tooltip');
                if ($badgeTooltip === 'pro_nav_in_dev_tooltip' || $badgeTooltip === 'pro_nav_soon_tooltip') {
                    $badgeTooltip = $releaseStatus === 'in_development'
                        ? 'This PRO module is currently in development.'
                        : 'This PRO module will be available soon.';
                }
                $label .= ' <span class="badge-pro-locked" data-tooltip="' . htmlspecialchars($badgeTooltip, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8') . '</span>';
            } elseif (!$isLicensed) {
                $label .= ' <span class="badge-pro-locked" data-tooltip="' . htmlspecialchars(__('pro_nav_locked_tooltip'), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(__('pro_nav_locked_label'), ENT_QUOTES, 'UTF-8') . '</span>';
            }

            if ($hasUpdate) {
                $updateTooltip = (string)__('pro_nav_update_tooltip');
                if ($updateTooltip === 'pro_nav_update_tooltip') {
                    $updateTooltip = 'Module update is available.';
                }
                $updateLabel = (string)__('pro_nav_update_label');
                if ($updateLabel === 'pro_nav_update_label') {
                    $updateLabel = 'UPDATE';
                }
                $label .= ' <span class="badge-pro-locked" data-tooltip="' . htmlspecialchars($updateTooltip, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($updateLabel, ENT_QUOTES, 'UTF-8') . '</span>';
            }

            $moduleUrl = $module['url'] ?? 'index.php';
            // Only prepend C_ADMIN_URL if the URL is not already absolute or external
            if (strpos($moduleUrl, 'http') !== 0 && strpos($moduleUrl, '/') !== 0) {
                $moduleUrl = C_ADMIN_URL . '/' . $moduleUrl;
            }

            $modulePath = (string)($module['url'] ?? '');
            $moduleScript = basename(parse_url($modulePath, PHP_URL_PATH) ?? '');
            $moduleQuery = '';
            $queryString = parse_url($modulePath, PHP_URL_QUERY);
            if (is_string($queryString) && $queryString !== '') {
                parse_str($queryString, $queryParts);
                $moduleQuery = (string)($queryParts['module'] ?? '');
            }

            $isModuleActive = ($currentScriptName === $moduleScript);
            if ($moduleQuery !== '') {
                $isModuleActive = $isModuleActive && ($currentModuleQuery === $moduleQuery);
            }

            $adminMenu[$id] = [
                'label' => $label,
                'icon'  => (isset($module['icon']) && method_exists('Icons', $module['icon'])) ? Icons::{$module['icon']}() : Icons::analytics(),
                'url'   => $moduleUrl,
                'active' => $isModuleActive,
                'priority' => $module['priority'] ?? 100,
                'is_pro' => $isPro,
                'is_locked' => !$isAvailable
            ];
        }
    }
}

// 6. Sort by priority (Ensure modules with 'priority' param are placed correctly)
uasort($adminMenu, fn($a, $b) => ($a['priority'] ?? 50) <=> ($b['priority'] ?? 50));

?>
<aside class="sidebar">
    <h2 class="<?= $isProLicenseActive ? 'has-pro-license' : '' ?>">
        <?php if ($isProLicenseActive): ?>
            <span class="sidebar-pro-badge">PRO</span>
        <?php endif; ?>
        <span class="sidebar-brand-name">Clipon CMS</span>
        <small class="sidebar-version">v<?= htmlspecialchars($cmsVersion, ENT_QUOTES, 'UTF-8') ?></small>
    </h2>
    <nav>
        <ul>
            <?php foreach ($adminMenu as $id => $item): ?>
                <?php if (isset($item['submenu'])): ?>
                    <li class="has-submenu <?= $item['active'] ? 'open' : '' ?>">
                        <a href="<?= $item['url'] ?? '#' ?>" class="submenu-toggle">
                            <span style="display: inline-flex; align-items: center; gap: 0.75rem;">
                                <?= $item['icon'] ?> <?= $item['label'] ?>
                            </span>
                            <span class="arrow">&darr;</span>
                        </a>
                        <ul class="submenu">
                            <?php foreach ($item['submenu'] as $subItem): ?>
                                <li><a href="<?= $subItem['url'] ?>" class="<?= ($subItem['active'] ?? false) ? 'active' : '' ?>"><?= $subItem['label'] ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="<?= $item['url'] ?>" class="<?= $item['active'] ? 'active' : '' ?>">
                            <?= $item['icon'] ?> <?= $item['label'] ?>
                            <?php if (!empty($item['badge'])): ?>
                                <span class="pro-badge"><?= $item['badge'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </nav>
    <a href="<?= C_ADMIN_URL ?>/switch_lang.php?lang=<?= Translation::getLang() ?>" style="display:none"></a>
    <div class="lang-switcher">
        <select onchange="window.location.href='<?= C_ADMIN_URL ?>/switch_lang.php?lang=' + this.value">
            <?php foreach (Translation::getInterfaceLangs() as $l): ?>
                <option value="<?= $l ?>" <?= Translation::getLang() == $l ? 'selected' : '' ?>>
                    <?= strtoupper($l) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <form method="post" action="<?= C_ADMIN_URL ?>/logout.php" class="logout-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="logout"><?= Icons::logout() ?> <?= __('logout') ?></button>
    </form>
</aside>

<script>
// Shared UI config consumed by admin-shared.js
window.AdminUIConfig = window.AdminUIConfig || {
    cancelLabel: '<?= addslashes(__('cancel')) ?>',
    okLabel: '<?= addslashes(__('ok')) ?>',
    session: {
        statusUrl: '<?= C_ADMIN_URL ?>/api/session.php',
        refreshUrl: '<?= C_ADMIN_URL ?>/api/session.php',
        logoutUrl: '<?= C_ADMIN_URL ?>/logout.php',
        loginUrl: '<?= C_ADMIN_URL ?>/login.php',
        csrfToken: '<?= addslashes(getCsrfToken()) ?>',
        warningBefore: <?= (int)SessionManager::WARNING_BEFORE ?>,
        activityRefreshInterval: 300,
        labels: {
            warningTitle: '<?= addslashes(__('session_warning_title')) ?>',
            warningText: '<?= addslashes(__('session_warning_text')) ?>',
            extend: '<?= addslashes(__('session_extend')) ?>',
            loginAgain: '<?= addslashes(__('session_login_again')) ?>',
            expiredToast: '<?= addslashes(__('session_expired')) ?>'
        }
    }
};

// Backward-compatible wrappers; actual implementation lives in admin-shared.js
function openModal(id) {
    if (window.AdminUI && typeof window.AdminUI.openModal === 'function') {
        window.AdminUI.openModal(id);
    }
}

function closeModal(id) {
    if (window.AdminUI && typeof window.AdminUI.closeModal === 'function') {
        window.AdminUI.closeModal(id);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var toggles = document.querySelectorAll('.sidebar .submenu-toggle');
    toggles.forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            var parent = this.closest('.has-submenu');
            if (parent) {
                parent.classList.toggle('open');
            }
        });
    });

    // Async heartbeat to keep internal state (License, etc.) synchronized without blocking UI
    setTimeout(() => {
        const body = new URLSearchParams();
        body.set('action', 'sync');
        body.set('csrf_token', '<?= addslashes(getCsrfToken()) ?>');
        fetch('<?= C_ADMIN_URL ?>/api/health.php', {
            method: 'POST',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body
        }).catch(() => {});
    }, 1000);
});
</script>
