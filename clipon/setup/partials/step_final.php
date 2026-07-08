    <?php if ($migrationStatus === 'failed'): ?>
        <p class="alert alert-danger"><?= htmlspecialchars($trans['migration_issue_on_step6']) ?></p>
    <?php elseif ($migrationStatus === 'dry-run' || $migrationStatus === 'preview'): ?>
        <p class="alert alert-warning"><?= htmlspecialchars($trans['dry_run_done']) ?></p>
    <?php elseif (empty($migrationReport['errors'])): ?>
        <p class="alert alert-success"><?= $trans['migration_success'] ?></p>
    <?php else: ?>
        <p class="alert alert-danger"><?= htmlspecialchars($trans['migration_issue_on_step6']) ?></p>
    <?php endif; ?>

    <?php if (!empty($migrationReport['items']['files']) || !empty($migrationReport['items']['dirs'])): ?>
        <div class="panel">
            <strong class="section-title"><?= htmlspecialchars($trans['item_statuses']) ?></strong>
            <div class="details-scroll">
                <?php foreach (($migrationReport['items']['dirs'] ?? []) as $row): ?>
                    <div class="status-row">DIR: <?= htmlspecialchars($row['name'] ?? '') ?> - <?= htmlspecialchars($row['status'] ?? '') ?><?= !empty($row['code']) ? ' [' . htmlspecialchars($row['code']) . ']' : '' ?></div>
                <?php endforeach; ?>
                <?php foreach (($migrationReport['items']['files'] ?? []) as $row): ?>
                    <div class="status-row">FILE: <?= htmlspecialchars($row['name'] ?? '') ?> - <?= htmlspecialchars($row['status'] ?? '') ?><?= !empty($row['code']) ? ' [' . htmlspecialchars($row['code']) . ']' : '' ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($migrationReport['paths']['report_dir'])): ?>
        <div class="report-links form-group">
            <a href="setup.php?step=6&download_migration_report=1&type=json"><?= htmlspecialchars($trans['download_report_json']) ?></a>
            &nbsp;|&nbsp;
            <a href="setup.php?step=6&download_migration_report=1&type=txt"><?= htmlspecialchars($trans['download_report_txt']) ?></a>
        </div>
    <?php endif; ?>
    
    <?php 
    $detectedUrl = '';
    $httpHost = $request->server('HTTP_HOST');
    if ($httpHost) {
        $https = $request->server('HTTPS');
        $protocol = (!empty($https) && $https !== 'off') ? "https://" : "http://";
        $host = $httpHost;
        $scriptName = $request->server('SCRIPT_NAME', '');
        $pos = strpos($scriptName, '/clipon/');
        $basePath = ($pos !== false) ? substr($scriptName, 0, $pos) : '';
        $detectedUrl = rtrim($protocol . $host . $basePath, '/');
    }
    ?>
    <form method="POST" action="setup.php?step=6">
        <?= Csrf::inputField(); ?>
        <div class="panel panel-muted">
            <div class="form-group">
                <label class="form-label"><?= $trans['site_name'] ?></label>
                <input type="text" name="site_name" class="input-field" placeholder="<?= htmlspecialchars($trans['site_name_placeholder']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">
                    <?= $trans['site_url'] ?>
                    <span class="form-help">(<?= $trans['site_url_help'] ?>)</span>
                </label>
                <input type="text" name="site_url" class="input-field" value="<?= htmlspecialchars($detectedUrl) ?>" placeholder="https://example.com" required>
            </div>

            <?php 
            $recLang = $session->get('migrated_primary_lang') ?: $session->get('setup_lang', 'uk');
            ?>
            <div class="form-group">
                <label class="form-label">
                    Site Language
                    <span class="form-help success">(Detected: <?= strtoupper($recLang) ?>)</span>
                </label>
                <select name="site_lang" id="site_lang_select" class="input-field" onchange="toggleCustomLang(this.value)">
                    <option value="uk" <?= $recLang === 'uk' ? 'selected' : '' ?>>Українська (UK)</option>
                    <option value="en" <?= $recLang === 'en' ? 'selected' : '' ?>>English (EN)</option>
                    <option value="ru" <?= $recLang === 'ru' ? 'selected' : '' ?>>Русский (RU)</option>
                    <option value="de" <?= $recLang === 'de' ? 'selected' : '' ?>>Deutsch (DE)</option>
                    <option value="fr" <?= $recLang === 'fr' ? 'selected' : '' ?>>Français (FR)</option>
                    <option value="es" <?= $recLang === 'es' ? 'selected' : '' ?>>Español (ES)</option>
                    <option value="pl" <?= $recLang === 'pl' ? 'selected' : '' ?>>Polski (PL)</option>
                    <option value="other">Other / Custom...</option>
                </select>
            </div>
        </div>

        <div id="custom_lang_box" class="panel panel-dashed panel-muted" style="display: none;">
            <div class="form-inline-grid">
                <div>
                    <label class="form-label">ISO Code (e.g. it, pt, ja)</label>
                    <input type="text" name="custom_lang_code" class="input-field compact" placeholder="it">
                </div>
                <div>
                    <label class="form-label">Language Name</label>
                    <input type="text" name="custom_lang_name" class="input-field compact" placeholder="Italiano">
                </div>
            </div>
        </div>

        <script>
            function toggleCustomLang(val) {
                document.getElementById('custom_lang_box').style.display = (val === 'other') ? 'block' : 'none';
            }
        </script>

        <div class="step-actions">
            <button type="submit" name="finish_setup" class="btn"><?= $trans['finish_setup'] ?></button>
        </div>
    </form>
