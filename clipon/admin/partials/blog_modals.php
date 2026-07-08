<!-- Directory Modal -->
<?php Modal::start('dirModal', __('add_category')); ?>
    <form id="dirForm" method="POST">
        <input type="hidden" name="action" id="dirAction" value="add_dir">
        <input type="hidden" name="id" id="dirId" value="">

        <div class="form-group">
            <label><?= __('category_name') ?></label>
            <input type="text" name="name" id="dirName" required class="form-control">
        </div>

        <div class="form-group">
            <label><?= __('category_parent') ?></label>
            <select name="parent" id="dirParent" class="form-control">
                <option value=""><?= __('category_root') ?></option>
                <?php echo renderDirOptions($rootDirs); ?>
            </select>
        </div>
    </form>
<?php Modal::footer(__('create'), null, 'dirForm'); ?>
<?php Modal::end(true); ?>

<!-- Post Modal -->
<?php Modal::start('postModal', __('create_new_post'), '600px'); ?>
    <form id="createPostForm" method="POST">
        <input type="hidden" name="action" value="create_post">

        <div class="form-group">
            <label><?= __('post_title') ?></label>
            <input type="text" name="title" required class="form-control" placeholder="Post Title">
        </div>
        <div class="form-group">
            <label><?= __('post_slug') ?></label>
            <input type="text" name="slug" class="form-control" placeholder="post-slug">
        </div>
        <div class="form-group">
            <label><?= __('blog_post_excerpt') ?></label>
            <textarea name="excerpt" class="form-control" rows="3" placeholder="<?= htmlspecialchars(__('blog_post_excerpt_placeholder'), ENT_QUOTES | ENT_SUBSTITUTE) ?>"></textarea>
        </div>
        <div class="form-group">
            <label><?= __('post_thumbnail') ?: 'Thumbnail' ?></label>
            <div class="input-group">
                <input type="text" name="thumbnail" id="postThumbnail" class="form-control" placeholder="/assets/uploads/image.jpg">
                <button type="button" class="btn btn-secondary" onclick="openMediaSelector('postThumbnail')"><?= Icons::image(16) ?></button>
            </div>
        </div>
        <div class="form-group">
            <label><?= __('blog_tags_label') ?></label>
            <?= renderBlogTagPicker('tags', [], $blogTags ?? [], 'createPostTags') ?>
        </div>
        <div class="form-group">
            <label><?= __('post_template') ?></label>
            <select name="template" required class="form-control">
                <?php
                $templates = getTemplates();
                foreach ($templates as $tpl) {
                    $sel = ($tpl === 'blog_post.php') ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($tpl) . '" ' . $sel . '>' . htmlspecialchars($tpl) . '</option>';
                }
                ?>
            </select>
        </div>
        <div id="postModalError" class="error-message" style="display: none;"></div>
    </form>
<?php Modal::footer(__('create'), null, 'createPostForm'); ?>
<?php Modal::end(true); ?>

<!-- Tag Manager Modal -->
<?php Modal::start('tagManagerModal', __('blog_tags_manage'), '720px'); ?>
    <div class="blog-tag-manager">
        <form id="blogTagCreateForm" class="blog-tag-manager-add">
            <input type="hidden" name="action" value="create_tag">
            <input type="hidden" name="lang" value="<?= htmlspecialchars($currentEditLang ?? '', ENT_QUOTES | ENT_SUBSTITUTE) ?>">
            <input type="text" name="name" class="form-control" placeholder="<?= htmlspecialchars(__('blog_tag_new_placeholder'), ENT_QUOTES | ENT_SUBSTITUTE) ?>">
            <button type="submit" class="btn"><?= __('blog_tag_add') ?></button>
        </form>

        <div class="blog-tag-manager-list" data-tag-languages='<?= htmlspecialchars(json_encode($blogActiveLangs ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE) ?>'>
            <?php foreach (($blogTags ?? []) as $tag): ?>
                <details class="blog-tag-accordion" data-tag-row data-id="<?= htmlspecialchars($tag['id'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE) ?>">
                    <summary class="blog-tag-summary">
                        <span class="dir-toggle"><?= Icons::caretRight(12) ?></span>
                        <span class="blog-tag-summary-main">
                            <code class="blog-tag-summary-id"><?= htmlspecialchars($tag['id'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE) ?></code>
                            <span class="blog-tag-summary-label"><?= htmlspecialchars($tag['label'] ?? ($tag['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE) ?></span>
                        </span>
                        <span class="blog-tag-count"><?= htmlspecialchars(sprintf(__('blog_tag_posts_count'), (int)($tag['count'] ?? 0)), ENT_QUOTES | ENT_SUBSTITUTE) ?></span>
                        <span class="blog-tag-actions">
                            <button type="button" class="icon-btn edit-btn" data-tag-edit data-tooltip="<?= htmlspecialchars(__('edit') ?: 'Edit', ENT_QUOTES | ENT_SUBSTITUTE) ?>"><?= Icons::pencil(18) ?></button>
                            <button type="button" class="icon-btn delete-btn" data-tag-delete data-tooltip="<?= htmlspecialchars(__('delete'), ENT_QUOTES | ENT_SUBSTITUTE) ?>"><?= Icons::trash(18) ?></button>
                        </span>
                    </summary>
                    <div class="blog-tag-panel">
                        <div class="blog-tag-manager-main">
                        <?php foreach (($blogActiveLangs ?? []) as $lang): ?>
                            <?php
                                $langCode = (string)($lang['code'] ?? '');
                                if ($langCode === '') {
                                    continue;
                                }
                                $labelValue = (string)(($tag['labels'] ?? [])[$langCode] ?? '');
                            ?>
                            <div class="blog-tag-locale-row">
                                <span class="lang-code"><?= htmlspecialchars(strtoupper($langCode), ENT_QUOTES | ENT_SUBSTITUTE) ?></span>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($labelValue, ENT_QUOTES | ENT_SUBSTITUTE) ?>" placeholder="<?= htmlspecialchars(__('blog_tags_label'), ENT_QUOTES | ENT_SUBSTITUTE) ?>" data-tag-label="<?= htmlspecialchars($langCode, ENT_QUOTES | ENT_SUBSTITUTE) ?>" disabled>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <div class="blog-tag-panel-actions">
                            <button type="button" class="btn btn-secondary" data-tag-rename disabled><?= Icons::save(16) ?> <?= __('save') ?></button>
                        </div>
                        <div class="blog-tag-posts">
                            <strong><?= htmlspecialchars(__('posts') ?: 'Posts', ENT_QUOTES | ENT_SUBSTITUTE) ?></strong>
                            <?php if (!empty($tag['posts'])): ?>
                                <ul>
                                    <?php foreach ($tag['posts'] as $tagPost): ?>
                                        <li>
                                            <a href="<?= htmlspecialchars($tagPost['url'] ?? '#', ENT_QUOTES | ENT_SUBSTITUTE) ?>" target="_blank">
                                                <?= htmlspecialchars($tagPost['title'] ?? ($tagPost['slug'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="blog-tag-empty"><?= htmlspecialchars(__('blog_tag_empty') ?: 'No posts.', ENT_QUOTES | ENT_SUBSTITUTE) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>
            <?php if (empty($blogTags)): ?>
                <p class="blog-tag-empty"><?= __('blog_tag_empty') ?></p>
            <?php endif; ?>
        </div>
    </div>
<?php Modal::footer(null, __('close') ?: __('cancel')); ?>
<?php Modal::end(true); ?>
