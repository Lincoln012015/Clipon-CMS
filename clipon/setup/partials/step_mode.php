    <div class="modes-grid">
        <div class="mode-card panel panel-muted">
            <div class="badge badge-safe"><?= htmlspecialchars($trans['safe_badge']) ?></div>
            <h3><?= $trans['mode_manual'] ?></h3>
            <p><?= $trans['mode_manual_desc'] ?></p>
            <div class="form-group">
                <a href="https://docs.clipon-cms.com/transfer-guide" target="_blank" class="btn-link">
                    <?= htmlspecialchars($trans['transfer_guide']) ?>
                </a>
            </div>
            <form method="POST" action="setup.php?step=4">
                <?= Csrf::inputField(); ?>
                <button type="submit" name="set_mode" value="manual" class="btn"><?= $trans['select_mode'] ?></button>
            </form>
        </div>
        <div class="mode-card panel">
            <h3><?= $trans['mode_smart'] ?></h3>
            <p><?= $trans['mode_smart_desc'] ?></p>
            <form method="POST" action="setup.php?step=4">
                <?= Csrf::inputField(); ?>
                <button type="submit" name="set_mode" value="smart" class="btn btn-secondary"><?= $trans['select_mode'] ?></button>
            </form>
        </div>
    </div>
