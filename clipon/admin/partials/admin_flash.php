<?php if (!empty($flash)): ?>
    <div class="toast-container" id="toast-container">
        <div class="toast <?= ($flash['type'] ?? 'success') === 'success' ? 'toast-success' : 'toast-error' ?>" role="status">
            <div class="toast-body" style="font-weight: 600;">
                <?= htmlspecialchars($flash['message'] ?? '') ?>
            </div>
            <button class="toast-close" type="button" aria-label="Close">x</button>
        </div>
    </div>
<?php endif; ?>
