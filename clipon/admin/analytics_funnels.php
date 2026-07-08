<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Settings.php';
require_once __DIR__ . '/../lib/Funnels.php';
require_once __DIR__ . '/../lib/Csrf.php';
require_once __DIR__ . '/../lib/Flash.php';
require_once __DIR__ . '/lib/analytics_service.php';

if (!$session->has('user')) {
    header('Location: login.php');
    exit;
}

if (!hasPermission('view_analytics')) {
    header('Location: index.php');
    exit;
}

$user = is_string($session->get('user')) ? $session->get('user') : 'unknown';

$to = $request->query('to', date('Y-m-d'));
$from = $request->query('from', date('Y-m-d', strtotime('-30 days')));

$settings = Settings::load();
$proAnalyticsAvailable = class_exists('ProAnalyticsPolicy') && ProAnalyticsPolicy::isAvailable();
$funnelsEnabled = !empty($settings['enable_funnels']);

Csrf::init();

$funnelsConfig = Funnels::load();
$funnelsList = is_array($funnelsConfig['items'] ?? null) ? $funnelsConfig['items'] : [];

$selectedFunnelId = $request->query('funnel_id', '');
$selectedFunnelName = '';
if ($selectedFunnelId) {
    foreach ($funnelsList as $f) {
        if ($f['id'] === $selectedFunnelId) {
            $selectedFunnelName = $f['name'];
            break;
        }
    }
}
$flash = Flash::pull('funnel');

if ($request->isPost() && !$proAnalyticsAvailable) {
    Flash::set('funnel', 'error', __('pro_analytics_status_locked'));
    header('Location: analytics_funnels.php?' . http_build_query(['from' => $from, 'to' => $to, 'funnel_id' => $selectedFunnelId]));
    exit;
}

if ($request->isPost() && $funnelsEnabled) {
    $csrfOk = Csrf::validate($request->post('csrf_token', ''));
    if (!$csrfOk) {
        Flash::set('funnel', 'error', __('error_invalid_csrf'));
        header('Location: analytics_funnels.php?' . http_build_query(['from' => $from, 'to' => $to, 'funnel_id' => $selectedFunnelId]));
        exit;
    }

    $shouldRedirect = false;
    $redirectParams = ['from' => $from, 'to' => $to];
    if ($selectedFunnelId !== '') {
        $redirectParams['funnel_id'] = $selectedFunnelId;
    }

    $action = $request->post('action', '');

    if ($action === 'add_funnel') {
        $name = $request->string('funnel_name');
        $stepsRaw = $request->string('funnel_steps');
        $ordered = $request->bool('funnel_ordered');

        $steps = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $stepsRaw)));
        $steps = array_values(array_unique($steps));

        if ($name && !empty($steps)) {
            $newId = Funnels::add($name, $steps, $ordered);
            if ($newId) {
                $selectedFunnelId = $newId;
                $redirectParams['funnel_id'] = $newId;
                Flash::set('funnel', 'success', __('analytics_funnel_created'));
                $shouldRedirect = true;
            } else {
                Flash::set('funnel', 'error', __('system_error'));
                $shouldRedirect = true;
            }
        } else {
            Flash::set('funnel', 'error', __('error_invalid_parameters'));
            $shouldRedirect = true;
        }
    }

    if ($action === 'delete_funnel') {
        $id = $request->string('funnel_id');
        if ($id) {
            if (Funnels::delete($id)) {
                if ($selectedFunnelId === $id) {
                    unset($redirectParams['funnel_id']);
                    $selectedFunnelId = '';
                }
                Flash::set('funnel', 'success', __('analytics_funnel_deleted'));
                $shouldRedirect = true;
            } else {
                Flash::set('funnel', 'error', __('system_error'));
                $shouldRedirect = true;
            }
        }
    }

    if ($shouldRedirect) {
        header('Location: analytics_funnels.php?' . http_build_query($redirectParams));
        exit;
    }

    $funnelsConfig = Funnels::load();
    $funnelsList = is_array($funnelsConfig['items'] ?? null) ? $funnelsConfig['items'] : [];
}

$stats = clipon_pro_analytics_service()->getFunnelsViewData($from, $to, $selectedFunnelId, $funnelsList);

if (!empty($stats['is_mock']) && empty($funnelsConfig['items'] ?? null)) {
    $funnelsList = AnalyticsView::getMockFunnelsList();
    if ($selectedFunnelId === '' && !empty($funnelsList[0]['id'])) {
        $selectedFunnelId = (string)$funnelsList[0]['id'];
    }
    foreach ($funnelsList as $f) {
        if (($f['id'] ?? '') === $selectedFunnelId) {
            $selectedFunnelName = (string)($f['name'] ?? '');
            break;
        }
    }
    $stats = clipon_pro_analytics_service()->getFunnelsViewData($from, $to, $selectedFunnelId, $funnelsList);
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(Translation::getLang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('analytics_funnels_tab') ?> - Admin</title>
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin.css?v=15">
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin-analytics.css">
</head>
<body>
    <div class="admin-container">
        <?php include __DIR__ . '/nav.php'; ?>
        <main class="main-content">
            <?php $gateState = AdminUI::proGateStart('pro_analytics'); ?>
            <?php include __DIR__ . '/partials/analytics_pro_helpers.php'; ?>
            <header class="header">
                <div class="header-title">
                    <h1><?= __('analytics_funnels_tab') ?></h1>
                    <p><?= __('welcome') ?>, <?= htmlspecialchars($user) ?></p>
                    <?php if ($selectedFunnelName): ?>
                        <p style="margin: 0.35rem 0 0 0; color: var(--text-secondary); font-size: 0.9rem;">
                            <?= __('analytics_funnel_active') ?>: <strong><?= htmlspecialchars($selectedFunnelName) ?></strong>
                        </p>
                    <?php endif; ?>
                </div>
            </header>

            <?php if ($flash): ?>
                <div class="toast-container" id="toast-container">
                    <div class="toast <?= $flash['type'] === 'success' ? 'toast-success' : 'toast-error' ?>" role="status">
                        <div class="toast-body" style="font-weight: 600;">
                            <?= htmlspecialchars($flash['message'] ?? '') ?>
                        </div>
                        <button class="toast-close" type="button" aria-label="Close">x</button>
                    </div>
                </div>
            <?php endif; ?>

            <?php include __DIR__ . '/partials/analytics_pro_notice.php'; ?>
            <?php $analyticsProDataHidden = !empty($stats['is_missing_module']); ?>

            <?php if (!$analyticsProDataHidden): ?>
            <?php if ($funnelsEnabled): ?>
                <?php include __DIR__ . '/partials/analytics_filter.php'; ?>
            <?php endif; ?>

            <?php if (!$funnelsEnabled): ?>
                <div class="analytics-card" style="padding: 1.25rem; border: 1px dashed var(--border-color);">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
                        <div>
                            <h2 style="margin: 0 0 0.35rem 0; font-size: 1rem; font-weight: 600;"><?= __('analytics_feature_disabled') ?></h2>
                            <p style="margin: 0; color: var(--text-secondary); font-size: 0.95rem;">
                                <?= __('settings_funnels_desc') ?>
                            </p>
                        </div>
                        <?php if ($analyticsLockedButton(__('analytics_enable_in_settings'), ['class' => 'btn', 'style' => 'white-space: nowrap;'])): ?>
                        <?php else: ?>
                            <a class="btn" href="settings.php" style="white-space: nowrap;"><?= __('analytics_enable_in_settings') ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <?php include __DIR__ . '/partials/analytics_funnels_content.php'; ?>
            <?php endif; ?>
            <?php endif; ?>
            <?php AdminUI::proGateEnd($gateState ?? []); ?>
        </main>
    </div>

    <?php include __DIR__ . '/partials/analytics_funnels_modal.php'; ?>

    <script>
    (function() {
        const modal = document.getElementById('add-funnel-modal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) modal.classList.remove('active');
            });
        }

        const form = document.getElementById('funnel-add-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const name = (form.querySelector('input[name="funnel_name"]')?.value || '').trim();
                const textarea = form.querySelector('textarea[name="funnel_steps"]');
                const raw = (textarea?.value || '');
                const parts = raw.split(/[\n,]/).map(s => s.trim()).filter(Boolean);
                const uniq = Array.from(new Set(parts)).slice(0, 20);
                if (textarea) textarea.value = uniq.join('\n');
                if (!name || uniq.length === 0) {
                    e.preventDefault();
                    if (window.cmsAlert) cmsAlert('<?= addslashes(__('error_invalid_parameters')) ?>');
                }
            });
        }

        const toast = document.querySelector('.toast');
        if (toast) {
            const closer = toast.querySelector('.toast-close');
            const remove = () => toast.remove();
            if (closer) closer.addEventListener('click', remove);
            setTimeout(remove, 4000);
        }
    })();
    </script>
</body>
</html>
