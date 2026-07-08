<?php
require_once __DIR__ . '/../lib/Auth.php';

if (!hasPermission('manage_redirects')) {
    header('Location: index.php');
    exit;
}

$redirectService = new RedirectService();

$redirects = $redirectService->getAllRedirects();
$routesCount = count($redirectService->getAllRoutes());
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(Translation::getLang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('redirects_title'); ?> - Admin</title>
    <link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin.css?v=15">
    <style>
        .form-row {
            display: grid;
            grid-template-columns: 2fr 2fr 1fr auto;
            gap: 16px;
            align-items: flex-end;
        }
        code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 13px;
            color: #475569;
        }
        .help-section {
            margin-top: 48px;
            padding: 24px;
            background: #f8fafc;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
        }
        .help-section h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .help-section ul {
            list-style: none;
            padding: 0;
        }
        .help-section li {
            position: relative;
            padding-left: 20px;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
        }
        .help-section li::before {
            content: "•";
            position: absolute;
            left: 0;
            color: var(--primary-color);
        }
        .empty-state {
            text-align: center;
            padding: 64px 32px;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include __DIR__ . '/nav.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <h1><?= __('redirects_h1') ?></h1>
                    <p><?= __('redirects_manage_description') ?></p>
                </div>
            </header>
            
            <?php
            $flash = Flash::pull('main');
            include __DIR__ . '/partials/admin_flash.php';
            ?>

            <div class="stats">
                <div class="stat-card">
                    <div class="stat-icon"><?= Icons::fileText() ?></div>
                    <div>
                        <div class="stat-number"><?php echo $routesCount; ?></div>
                        <div class="stat-label"><?php echo __('active_pages'); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><?= Icons::redirects() ?></div>
                    <div>
                        <div class="stat-number"><?php echo count($redirects); ?></div>
                        <div class="stat-label"><?php echo __('active_redirects'); ?></div>
                    </div>
                </div>
            </div>

            <div class="add-form-card">
                <h3><?php echo __('add_new_redirect'); ?></h3>
                <form id="addRedirectForm">
                    <input type="hidden" name="action" value="add_redirect">
                    <div class="form-row">
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 12px; margin-bottom: 4px;"><?php echo __('old_url'); ?></label>
                            <input type="text" name="old_url" placeholder="/old-page" required class="form-control">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 12px; margin-bottom: 4px;"><?php echo __('new_url'); ?></label>
                            <input type="text" name="new_url" placeholder="/new-page" required class="form-control">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 12px; margin-bottom: 4px;"><?php echo __('code'); ?></label>
                            <select name="code" class="form-control">
                                <option value="301">301</option>
                                <option value="302">302</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><?php echo __('add_btn'); ?></button>
                    </div>
                </form>
            </div>

            <h3 style="margin-bottom: 16px; font-size: 16px;"><?php echo __('redirects_list'); ?></h3>
            
            <div class="table-container">
                <?php if (empty($redirects)): ?>
                    <div class="empty-state">
                        <div style="margin-bottom: 16px; opacity: 0.5;"><?= Icons::redirects() ?></div>
                        <p><?php echo __('no_redirects'); ?></p>
                        <small><?php echo __('add_first_redirect'); ?></small>
                    </div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?php echo __('old_url'); ?></th>
                                <th><?php echo __('new_url'); ?></th>
                                <th><?php echo __('code'); ?></th>
                                <th><?php echo __('created_at_short'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($redirects as $oldUrl => $redirect): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($oldUrl); ?></code></td>
                                    <td><code><?php echo htmlspecialchars($redirect['target']); ?></code></td>
                                    <td>
                                        <span class="badge badge-<?php echo ($redirect['code'] == 301) ? 'success' : 'warning'; ?>">
                                            <?php echo $redirect['code']; ?>
                                        </span>
                                    </td>
                                    <td style="color: var(--text-muted); font-size: 13px;"><?php echo htmlspecialchars($redirect['created'] ?? 'Н/Д'); ?></td>
                                    <td>
                                        <button onclick="window.deleteRedirect('<?php echo htmlspecialchars($oldUrl, ENT_QUOTES); ?>')" class="icon-btn delete-btn" data-tooltip="<?= __('delete') ?>">
                                            <?= Icons::trash() ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="help-section">
                <h3><?= Icons::info() ?> <?php echo __('about_redirects'); ?></h3>
                <ul>
                    <li><?php echo __('redirect_301_desc'); ?></li>
                    <li><?php echo __('redirect_302_desc'); ?></li>
                    <li><?php echo __('redirect_auto_desc'); ?></li>
                    <li><?php echo __('redirect_priority_desc'); ?></li>
                </ul>
            </div>
        </main>
    </div>

    <script>
        window.LANG_DELETE_CONFIRM = '<?php echo __('delete_redirect_confirm'); ?>';
        window.REDIRECTS_ADMIN_CONFIG = {
            csrfToken: <?= json_encode(getCsrfToken(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
        };
    </script>
    <script src="<?= C_ASSETS_URL ?>/js/admin-shared.js?v=2"></script>
    <script src="<?= C_ASSETS_URL ?>/js/admin-redirects.js?v=1"></script>
</body>
</html>
