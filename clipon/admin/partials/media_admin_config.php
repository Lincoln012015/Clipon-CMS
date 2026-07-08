<script>
    window.MEDIA_ADMIN_CONFIG = {
        currentDir: <?= json_encode($currentDir, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        csrfToken: <?= json_encode(getCsrfToken(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        apiUrl: 'api/media.php',
        uploadUrl: 'upload_handler.php',
        lang: <?= json_encode([
            'uploading' => __('uploading'),
            'error_upload' => __('error_upload'),
            'copied' => __('copied'),
            'enter_folder_name' => __('enter_folder_name'),
            'delete_file_confirm' => __('delete_file_confirm'),
            'delete_folder_confirm' => __('delete_folder_confirm'),
            'move_confirm' => __('move_confirm'),
            'into' => __('into'),
            'select_all' => __('select_all'),
            'select_destination' => __('select_destination'),
            'bulk_delete_confirm' => __('bulk_delete_confirm'),
            'bulk_delete_success' => __('bulk_delete_success'),
            'bulk_move_success' => __('bulk_move_success'),
            'selected' => __('selected'),
            'error_prefix' => __('error_prefix'),
            'rename_folder_prompt' => __('rename_folder_prompt'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
</script>
