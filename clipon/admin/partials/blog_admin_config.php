<script>
    window.BLOG_ADMIN_CONFIG = {
        apiUrl: 'api/blog.php',
        csrfToken: <?= json_encode(getCsrfToken(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        iconToggleActive: <?= json_encode(getToggleIcon(true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        iconToggleInactive: <?= json_encode(getToggleIcon(false), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        iconPencil: <?= json_encode(Icons::pencil(18), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        iconTrash: <?= json_encode(Icons::trash(18), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        iconSave: <?= json_encode(Icons::save(16), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        tags: <?= json_encode($blogTags ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        languages: <?= json_encode($blogActiveLangs ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        currentLang: <?= json_encode($currentEditLang ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        text: <?= json_encode([
            'add_category' => __('add_category'),
            'edit_category' => __('edit_category'),
            'delete_category_confirm' => __('delete_category_confirm'),
            'delete_category_with_posts' => __('delete_category_with_posts'),
            'delete_post_confirm' => __('delete_post_confirm'),
            'error_prefix' => __('error_prefix'),
            'network_error' => __('error_network'),
            'unknown_error' => __('system_error'),
            'ok' => __('ok'),
            'save' => __('save'),
            'edit' => __('edit') ?: 'Edit',
            'delete' => __('delete'),
            'posts' => __('posts') ?: 'Posts',
            'rename_folder_prompt' => __('rename_folder_prompt'),
            'tag_delete_confirm' => __('blog_tag_delete_confirm'),
            'tag_none_selected' => __('blog_tag_none_selected'),
            'tag_no_posts' => __('blog_tag_no_posts') ?: 'No posts with this tag.',
            'tag_posts_count' => __('blog_tag_posts_count'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
    window.CLIPON_CSRF_TOKEN = window.BLOG_ADMIN_CONFIG.csrfToken;
</script>
