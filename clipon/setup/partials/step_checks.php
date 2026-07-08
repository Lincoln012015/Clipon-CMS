    <div class="checks">
        <?php foreach ($checks as $id => $c): ?>
            <div class="check-item">
                <div>
                    <strong><?= $c['label'] ?></strong><br>
                    <small class="check-value"><?= $c['value'] ?></small>
                </div>
                <span class="status-<?= $c['status'] ?>"><?= strtoupper($trans[$c['status']] ?? $c['status']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="step-actions">
        <button onclick="location.href='setup.php?step=3'" class="btn" <?= !$canContinue ? 'disabled' : '' ?>>
            <?= $trans['next'] ?>
        </button>
    </div>
