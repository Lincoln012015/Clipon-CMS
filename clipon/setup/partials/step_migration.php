    <p class="muted-text">
        <?= str_replace('{count}', count($migrationPreview['candidates']['files'] ?? []), $trans['files_found']) ?>
        <br><?= htmlspecialchars($trans['step5_intro']) ?>
    </p>

    <?php if ($request->query('dry_run_done')): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($trans['dry_run_done']) ?>
        </div>
    <?php endif; ?>

    <?php if ($request->query('migration_done') && ($migrationReport['status'] ?? '') === 'failed'): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($trans['migration_issue_on_step6']) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="migration-form" action="setup.php?step=5">
        <?= Csrf::inputField(); ?>
    <div class="setup5-methods">
        <div class="setup5-method panel" id="markup-editor">
            <div class="setup5-method-summary">
                <span class="setup5-method-title">
                    <span class="badge"><?= htmlspecialchars($trans['markup_editor_badge'] ?? 'Unified') ?></span>
                    <strong><?= htmlspecialchars($trans['markup_editor_title'] ?? 'Markup Editor') ?></strong>
                </span>
            </div>
            <p><?= htmlspecialchars($trans['markup_editor_desc'] ?? 'Choose the right scenario for each file: static page content, blog post list, or single post template.') ?></p>
            <div class="setup5-picker-list">
                <?php if (!empty($migrationPreview['plan']['files'])): ?>
                    <?php foreach (($migrationPreview['plan']['files'] ?? []) as $f): ?>
                        <?php $fileName = $f['name'] ?? ''; ?>
                        <div class="blog-wizard-file">
                            <span><?= htmlspecialchars($fileName) ?></span>
                            <div class="blog-wizard-actions">
                                <a href="markup_picker.php?mode=auto&file=<?= urlencode($fileName) ?>" target="_blank" class="visual-picker-link visual-picker-link-primary">
                                    <?= htmlspecialchars($trans['markup_page_action'] ?? $trans['markup_editor_action'] ?? 'Mark page content') ?>
                                </a>
                                <a href="markup_picker.php?mode=blog-list&file=<?= urlencode($fileName) ?>" target="_blank" class="visual-picker-link">
                                    <?= htmlspecialchars($trans['blog_wizard_list_action']) ?>
                                </a>
                                <a href="markup_picker.php?mode=blog-post&file=<?= urlencode($fileName) ?>" target="_blank" class="visual-picker-link">
                                    <?= htmlspecialchars($trans['blog_wizard_post_action']) ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state"><?= htmlspecialchars($trans['markup_editor_empty']) ?></div>
                <?php endif; ?>
            </div>
            <div class="hint"><?= htmlspecialchars($trans['picker_plan_hint']) ?></div>
        </div>
    </div>

    <?php if (!empty($migrationPreview['plan'])): ?>
        <div class="panel panel-muted">
            <strong class="section-title"><?= htmlspecialchars($trans['migration_plan']) ?></strong>
            <div class="muted-text">
                <?= htmlspecialchars(str_replace(['{dirs}', '{files}'], [count($migrationPreview['plan']['dirs'] ?? []), count($migrationPreview['plan']['files'] ?? [])], $trans['plan_summary'])) ?>
            </div>
            <?php if (!empty($migrationPreview['plan']['risks'])): ?>
                <div class="alert alert-warning">
                    <strong><?= htmlspecialchars($trans['risks']) ?>:</strong>
                    <ul>
                        <?php foreach (($migrationPreview['plan']['risks'] ?? []) as $risk): ?>
                            <li><?= htmlspecialchars($risk) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <details>
                <summary class="btn-link"><?= htmlspecialchars($trans['plan_details']) ?></summary>
                <div class="details-scroll">
                    <?php foreach (($migrationPreview['plan']['dirs'] ?? []) as $d): ?>
                        <div>DIR: <?= htmlspecialchars($d['name'] ?? '') ?> - <?= htmlspecialchars($d['target'] ?? '') ?> (<?= htmlspecialchars($d['action'] ?? '') ?>)</div>
                    <?php endforeach; ?>
                    <?php foreach (($migrationPreview['plan']['files'] ?? []) as $f): ?>
                        <div class="status-row">FILE: <?= htmlspecialchars($f['name'] ?? '') ?></div>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>
    <?php endif; ?>

    <?php if (!empty($migrationReport['stages'])): ?>
        <div class="panel">
            <strong class="section-title"><?= htmlspecialchars($trans['stage_progress']) ?></strong>
            <?php foreach (($migrationReport['stages'] ?? []) as $stageName => $stage): ?>
                <div class="progress-item">
                    <div class="progress-head">
                        <span><?= htmlspecialchars($stageName) ?></span>
                        <span><?= (int)($stage['progress'] ?? 0) ?>% (<?= htmlspecialchars($stage['status'] ?? 'pending') ?>)</span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-bar" style="width:<?= (int)($stage['progress'] ?? 0) ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($migrationReport['errors'])): ?>
        <div class="alert alert-danger">
            <strong><?= htmlspecialchars($trans['migration_errors']) ?>:</strong>
            <ul>
                <?php foreach ($migrationReport['errors'] as $msg): ?>
                    <li><?= htmlspecialchars($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($migrationReport['warnings'])): ?>
        <div class="alert alert-warning">
            <strong><?= htmlspecialchars($trans['warnings_label']) ?>:</strong>
            <ul>
                <?php foreach ($migrationReport['warnings'] as $msg): ?>
                    <li><?= htmlspecialchars($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($migrationReport['error_matrix'])): ?>
        <div class="panel">
            <strong class="section-title"><?= htmlspecialchars($trans['error_matrix']) ?></strong>
            <div class="details-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Reason</th>
                            <th>Fix</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($migrationReport['error_matrix'] ?? []) as $issue): ?>
                            <tr>
                                <td class="truncate"><?= htmlspecialchars($issue['code'] ?? '') ?></td>
                                <td><?= htmlspecialchars($issue['reason'] ?? '') ?></td>
                                <td><?= htmlspecialchars($issue['fix'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

        <div class="panel">
            <strong class="section-title"><?= htmlspecialchars($trans['asset_options']) ?></strong>
            <label class="choice-row">
                <input type="checkbox" name="assets_enabled" checked>
                <?= htmlspecialchars($trans['assets_fix_paths']) ?>
            </label>
            <label class="choice-row">
                <input type="checkbox" name="process_links" checked>
                <?= htmlspecialchars($trans['assets_process_links']) ?>
            </label>
            <label class="form-label"><?= htmlspecialchars($trans['assets_base_path']) ?></label>
            <input type="text" name="assets_base_path" value="" placeholder="<?= htmlspecialchars($trans['assets_base_path_placeholder']) ?>" class="input-field compact">
        </div>

        <div class="panel">
            <strong class="section-title"><?= htmlspecialchars($trans['advanced_options']) ?></strong>
            <label class="choice-row">
                <input type="checkbox" name="skip_dom_for_php" checked>
                <?= htmlspecialchars($trans['tagging_skip_dom_php']) ?>
            </label>
            <label class="choice-row">
                <input type="checkbox" name="overwrite_page_data">
                <?= htmlspecialchars($trans['tagging_overwrite_page_data']) ?>
            </label>
            <label class="choice-row">
                <input type="checkbox" name="dry_run_only">
                <?= htmlspecialchars($trans['tagging_dry_run']) ?>
            </label>
            <label class="choice-row">
                <input type="checkbox" name="backup_enabled" checked>
                <?= htmlspecialchars($trans['backup_enable']) ?>
            </label>
            <label class="choice-row">
                <input type="checkbox" name="delete_originals" checked>
                <?= htmlspecialchars($trans['delete_originals']) ?>
            </label>
        </div>

        <div class="step-actions">
            <button type="submit" name="run_migration" class="btn" id="run-migration-btn"><?= $trans['start_migration'] ?></button>
        </div>
    </form>

    <?php if (!empty($migrationReport['paths']['report_dir'])): ?>
        <div class="report-links">
            <a href="setup.php?step=5&download_migration_report=1&type=json"><?= htmlspecialchars($trans['download_report_json']) ?></a>
            &nbsp;|&nbsp;
            <a href="setup.php?step=5&download_migration_report=1&type=txt"><?= htmlspecialchars($trans['download_report_txt']) ?></a>
        </div>
    <?php endif; ?>

    <script>
        (function() {
            const form = document.getElementById('migration-form');
            const btn = document.getElementById('run-migration-btn');
            if (!form || !btn) return;
            form.addEventListener('submit', function() {
                setTimeout(() => {
                    btn.disabled = true;
                    btn.textContent = <?= json_encode($trans['migration_running']) ?>;
                }, 10);
            });
        })();
    </script>
