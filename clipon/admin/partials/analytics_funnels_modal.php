<div id="add-funnel-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?= __('analytics_add_funnel') ?></h2>
            <button type="button" class="modal-close" onclick="document.getElementById('add-funnel-modal').classList.remove('active')">&times;</button>
        </div>
        <form id="funnel-add-form" method="POST" class="modal-form">
            <?= Csrf::inputField(); ?>
            <input type="hidden" name="action" value="add_funnel">

            <div class="form-group">
                <label>* <?= __('analytics_funnel_name') ?></label>
                <input type="text" name="funnel_name" placeholder="Checkout" required class="form-control">
            </div>

            <div class="form-group">
                <label>* <?= __('analytics_funnel_steps') ?></label>
                <textarea name="funnel_steps" rows="6" placeholder="/cart
/checkout
/thank-you" required class="form-control"></textarea>
                <small style="display: block; margin-top: 0.25rem; color: var(--text-muted);"><?= __('analytics_funnel_steps_help') ?></small>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="funnel_ordered" value="1">
                    <span><?= __('analytics_funnel_ordered') ?></span>
                </label>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('add-funnel-modal').classList.remove('active')"><?= __('cancel') ?></button>
                <button type="submit" class="btn"><?= __('analytics_add_funnel') ?></button>
            </div>
        </form>
    </div>
</div>
