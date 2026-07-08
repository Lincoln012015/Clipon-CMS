<?php Modal::start('createFolderModal', __('create_folder')); ?>
    <form id="createFolderForm">
        <div class="form-group">
            <label><?= __('folder_name') ?></label>
            <input type="text" name="folder_name" id="newFolderName" required class="form-control" autofocus>
        </div>
    </form>
<?php Modal::footer(__('create'), null, 'createFolderForm'); ?>
<?php Modal::end(true); ?>
