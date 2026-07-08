<!-- Modal -->
<!-- Directory Modal -->
<?php Modal::start('dirModal', __('add_dir')); ?>
    <form id="dirForm" method="POST">
        <input type="hidden" name="action" id="dirAction" value="add_dir">
        <input type="hidden" name="id" id="dirId" value="">

        <div class="form-group">
            <label><?= __('dir_name') ?></label>
            <input type="text" name="name" id="dirName" required class="form-control">
        </div>

        <div class="form-group">
            <label><?= __('parent_dir') ?></label>
            <select name="parent" id="dirParent" class="form-control">
                <option value=""><?= __('root') ?></option>
                <?php
                echo renderDirOptions($rootDirs);
                ?>
            </select>
        </div>
    </form>
<?php Modal::footer(__('create'), null, 'dirForm'); ?>
<?php Modal::end(true); ?>

<!-- Page Modal -->
<?php Modal::start('pageModal', __('create_page_h3'), '600px'); ?>
    <form id="createPageForm" method="POST">
        <input type="hidden" name="action" value="create_page">

        <div class="form-group">
            <label><?= __('page_title') ?></label>
            <input type="text" name="title" required class="form-control" placeholder="<?= __('page_title_placeholder') ?>">
        </div>

        <div class="form-group">
            <label><?= __('url_slug_label') ?></label>
            <input type="text" name="slug" required class="form-control" placeholder="<?= __('slug_placeholder') ?>">
        </div>

        <div class="form-group">
            <label><?= __('post_template') ?></label>
            <select name="template" required class="form-control">
                <?php
                $templates = getTemplates();
                foreach ($templates as $tpl) {
                    echo '<option value="' . htmlspecialchars($tpl) . '">' . htmlspecialchars($tpl) . '</option>';
                }
                ?>
            </select>
        </div>

        <div class="form-group">
            <label><?= __('parent_page') ?></label>
            <select name="parent_id" class="form-control">
                <option value=""><?= __('none_root') ?></option>
                <?php
                foreach (getAllPagesForParentSelect('index') as $pSlug => $pData) {
                    $displayTitle = (string)($pData['title'] ?? $pSlug);
                    if ($pSlug !== 'index') {
                        echo '<option value="' . htmlspecialchars($pSlug) . '">' . htmlspecialchars($displayTitle) . '</option>';
                    }
                }
                ?>
            </select>
        </div>

        <div id="pageModalError" class="error-message" style="display: none;"></div>
    </form>
<?php Modal::footer(__('create'), null, 'createPageForm'); ?>
<?php Modal::end(true); ?>
