<?php
require_once __DIR__ . '/../../lib/VisualPickerSupport.php';

$markupTemplates = VisualPickerSupport::listEditableTemplates();
?>
<section class="settings-panel" data-tab="markup_settings">
    <div class="settings-markup-header">
        <h2><?= __('settings_markup_category') ?></h2>
        <p><?= __('settings_markup_help') ?></p>
    </div>

    <div class="settings-markup-warning">
        <?= __('settings_markup_content_warning') ?>
    </div>

    <?php if (empty($markupTemplates)): ?>
        <div class="settings-markup-empty">
            <?= __('settings_markup_empty') ?>
        </div>
    <?php else: ?>
        <div class="settings-markup-list">
            <?php foreach ($markupTemplates as $template): ?>
                <div class="settings-markup-row">
                    <div class="settings-markup-file">
                        <strong><?= htmlspecialchars($template, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                    </div>
                    <div class="settings-markup-actions">
                        <div class="settings-markup-action-group">
                            <span><?= htmlspecialchars(__('settings_markup_group_content'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                            <a
                                class="btn btn-secondary btn-sm settings-markup-primary-action"
                                href="../markup_picker.php?scope=template&mode=auto&file=<?= urlencode($template) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <?= htmlspecialchars(__('settings_markup_action_page_content'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </a>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="template" value="<?= htmlspecialchars($template, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                <button
                                    class="btn btn-secondary btn-sm"
                                    type="submit"
                                    name="import_template_content"
                                    value="1"
                                    onclick="return confirm('<?= htmlspecialchars(__('settings_markup_import_confirm'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>')"
                                >
                                    <?= htmlspecialchars(__('settings_markup_action_import_template_content'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                </button>
                            </form>
                        </div>
                        <div class="settings-markup-action-group">
                            <span><?= htmlspecialchars(__('settings_markup_group_blog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                            <a
                                class="btn btn-secondary btn-sm"
                                href="../markup_picker.php?scope=template&mode=blog-list&file=<?= urlencode($template) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <?= htmlspecialchars(__('settings_markup_action_blog_list'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </a>
                            <a
                                class="btn btn-secondary btn-sm"
                                href="../markup_picker.php?scope=template&mode=blog-post&file=<?= urlencode($template) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <?= htmlspecialchars(__('settings_markup_action_blog_post'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
