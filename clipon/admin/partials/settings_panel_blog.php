<?php
$blogDefaults = Settings::getDefaultBlogSettings();
$blogColors = is_array($settings['blog_pagination_colors'] ?? null) ? $settings['blog_pagination_colors'] : [];
$blogColors = array_merge($blogDefaults['blog_pagination_colors'], $blogColors);
$blogLabels = is_array($settings['blog_pagination_labels'] ?? null) ? $settings['blog_pagination_labels'] : [];
$blogActiveLangs = array_values(array_filter(Settings::getLanguages(), static fn($lang) => !empty($lang['enabled'])));
if (empty($blogActiveLangs)) {
    $blogActiveLangs = [['code' => (string)($settings['language'] ?? 'en'), 'name' => 'Default', 'enabled' => true]];
}
$fallbackPrev = (string)($settings['blog_pagination_prev_text'] ?? $blogDefaults['blog_pagination_prev_text']);
$fallbackNext = (string)($settings['blog_pagination_next_text'] ?? $blogDefaults['blog_pagination_next_text']);
$localizedLabelsEnabled = !empty($settings['blog_pagination_localized_labels_enabled']);
?>

<section class="settings-panel" data-tab="blog_settings" style="display:none;">
    <div class="settings-panel-heading">
        <h2><?= __('settings_blog_category') ?></h2>
    </div>

    <form method="POST" class="admin-form settings-blog-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

        <section class="settings-section">
            <div class="settings-section-header">
                <div>
                    <h3><?= __('settings_blog_pagination_title') ?></h3>
                    <p><?= __('settings_blog_pagination_desc') ?></p>
                </div>
            </div>

            <div class="settings-cookie-grid">
                <label>
                    <span><?= __('settings_blog_pagination_alignment') ?></span>
                    <select name="blog_pagination_alignment" class="form-control" data-blog-pagination-preview-field>
                        <?php foreach (['left', 'center', 'right'] as $align): ?>
                            <option value="<?= htmlspecialchars($align, ENT_QUOTES, 'UTF-8') ?>" <?= (($settings['blog_pagination_alignment'] ?? 'center') === $align) ? 'selected' : '' ?>>
                                <?= __('settings_blog_pagination_align_' . $align) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span><?= __('settings_blog_pagination_gap') ?></span>
                    <input type="text" name="blog_pagination_gap" class="form-control" value="<?= htmlspecialchars((string)($settings['blog_pagination_gap'] ?? $blogDefaults['blog_pagination_gap']), ENT_QUOTES, 'UTF-8') ?>" data-blog-pagination-preview-field>
                </label>
                <label>
                    <span><?= __('settings_blog_pagination_radius') ?></span>
                    <input type="text" name="blog_pagination_radius" class="form-control" value="<?= htmlspecialchars((string)($settings['blog_pagination_radius'] ?? $blogDefaults['blog_pagination_radius']), ENT_QUOTES, 'UTF-8') ?>" data-blog-pagination-preview-field>
                </label>
                <label>
                    <span><?= __('settings_blog_pagination_padding') ?></span>
                    <input type="text" name="blog_pagination_padding" class="form-control" value="<?= htmlspecialchars((string)($settings['blog_pagination_padding'] ?? $blogDefaults['blog_pagination_padding']), ENT_QUOTES, 'UTF-8') ?>" data-blog-pagination-preview-field>
                </label>
                <label>
                    <span><?= __('settings_blog_pagination_font_size') ?></span>
                    <input type="text" name="blog_pagination_font_size" class="form-control" value="<?= htmlspecialchars((string)($settings['blog_pagination_font_size'] ?? $blogDefaults['blog_pagination_font_size']), ENT_QUOTES, 'UTF-8') ?>" data-blog-pagination-preview-field>
                </label>
            </div>

            <div class="settings-section-subhead">
                <h4><?= __('settings_blog_pagination_global_labels_title') ?></h4>
                <p><?= __('settings_blog_pagination_global_labels_desc') ?></p>
            </div>

            <div class="settings-cookie-grid">
                <label>
                    <span><?= __('settings_blog_pagination_prev_text') ?></span>
                    <input type="text" name="blog_pagination_prev_text" class="form-control" value="<?= htmlspecialchars($fallbackPrev, ENT_QUOTES, 'UTF-8') ?>" data-blog-pagination-preview-field data-blog-pagination-global-prev>
                </label>
                <label>
                    <span><?= __('settings_blog_pagination_next_text') ?></span>
                    <input type="text" name="blog_pagination_next_text" class="form-control" value="<?= htmlspecialchars($fallbackNext, ENT_QUOTES, 'UTF-8') ?>" data-blog-pagination-preview-field data-blog-pagination-global-next>
                </label>
            </div>

            <div class="settings-section-subhead">
                <h4><?= __('settings_blog_pagination_labels_title') ?></h4>
                <p><?= __('settings_blog_pagination_labels_desc') ?></p>
            </div>

            <div class="settings-localized-labels-row">
                <label class="settings-inline-checkbox">
                    <input type="checkbox" name="blog_pagination_localized_labels_enabled" value="1" <?= $localizedLabelsEnabled ? 'checked' : '' ?> data-blog-pagination-localized-toggle>
                    <span><?= __('settings_blog_pagination_labels_enabled') ?></span>
                </label>
                <button type="button" class="btn btn-secondary btn-compact" data-open-blog-pagination-labels <?= $localizedLabelsEnabled ? '' : 'disabled' ?>>
                    <?= __('settings_blog_pagination_labels_open') ?>
                </button>
                <span class="help-text"><?= sprintf(__('settings_blog_pagination_labels_count'), count($blogActiveLangs)) ?></span>
            </div>

            <div id="blogPaginationLabelsModal" class="modal cms-modal" role="dialog" aria-modal="true" aria-labelledby="blogPaginationLabelsTitle">
                <div class="modal-content cms-modal-content" style="max-width: 760px;">
                    <div class="modal-header cms-modal-header">
                        <h3 id="blogPaginationLabelsTitle"><?= __('settings_blog_pagination_labels_modal_title') ?></h3>
                        <button type="button" class="close-modal cms-modal-close" data-close-blog-pagination-labels aria-label="Close">&times;</button>
                    </div>
                    <div class="modal-body cms-modal-body">
                        <div class="settings-blog-labels-list">
                            <?php foreach ($blogActiveLangs as $index => $lang): ?>
                    <?php
                    $code = Settings::normalizeLanguageCode((string)($lang['code'] ?? ''));
                    if ($code === '') {
                        continue;
                    }
                    $name = (string)($lang['name'] ?? $code);
                    $labels = is_array($blogLabels[$code] ?? null) ? $blogLabels[$code] : [];
                    $prevValue = (string)($labels['prev'] ?? $fallbackPrev);
                    $nextValue = (string)($labels['next'] ?? $fallbackNext);
                    ?>
                                <div class="settings-blog-label-row">
                                    <div class="settings-blog-label-lang">
                                        <strong><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <label>
                                        <span><?= __('settings_blog_pagination_prev_text') ?></span>
                                        <input type="text" name="blog_pagination_labels[<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>][prev]" class="form-control" value="<?= htmlspecialchars($prevValue, ENT_QUOTES, 'UTF-8') ?>" <?= $index === 0 ? 'data-blog-pagination-preview-field data-blog-pagination-preview-prev-input' : '' ?>>
                                    </label>
                                    <label>
                                        <span><?= __('settings_blog_pagination_next_text') ?></span>
                                        <input type="text" name="blog_pagination_labels[<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>][next]" class="form-control" value="<?= htmlspecialchars($nextValue, ENT_QUOTES, 'UTF-8') ?>" <?= $index === 0 ? 'data-blog-pagination-preview-field data-blog-pagination-preview-next-input' : '' ?>>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer cms-modal-footer">
                        <button type="button" class="btn btn-secondary" data-close-blog-pagination-labels><?= __('done') !== 'done' ? __('done') : 'Done' ?></button>
                    </div>
                </div>
            </div>

            <div class="settings-cookie-colors">
                <?php foreach (['background', 'text', 'active_background', 'active_text', 'border', 'disabled'] as $colorKey): ?>
                    <label>
                        <span><?= __('settings_blog_pagination_color_' . $colorKey) ?></span>
                        <input type="color" name="blog_pagination_color_<?= htmlspecialchars($colorKey, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($blogColors[$colorKey], ENT_QUOTES, 'UTF-8') ?>" data-blog-pagination-preview-field>
                    </label>
                <?php endforeach; ?>
            </div>

            <label class="settings-field-block">
                <span><?= __('settings_blog_pagination_custom_css') ?></span>
                <textarea name="blog_pagination_custom_css" class="form-control" rows="5" placeholder=".blog-pagination { ... }"><?= htmlspecialchars((string)($settings['blog_pagination_custom_css'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </label>

            <div class="settings-blog-pagination-preview-wrap">
                <div class="settings-blog-pagination-preview" id="blogPaginationPreview">
                    <span class="prev disabled" data-blog-pagination-preview-prev><?= htmlspecialchars($fallbackPrev, ENT_QUOTES, 'UTF-8') ?></span>
                    <a href="#">1</a>
                    <a href="#" class="active">2</a>
                    <a href="#">3</a>
                    <span class="ellipsis">...</span>
                    <a href="#">8</a>
                    <a href="#" class="next" data-blog-pagination-preview-next><?= htmlspecialchars($fallbackNext, ENT_QUOTES, 'UTF-8') ?></a>
                </div>
            </div>
        </section>

        <div class="form-actions">
            <button type="submit" name="save_blog" value="1" class="btn btn-success"><?= __('save_settings') ?></button>
        </div>
    </form>
</section>
