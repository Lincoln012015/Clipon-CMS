(function () {
    const config = window.MEDIA_ADMIN_CONFIG || {};
    const LANG = config.lang || {};
    const API_URL = config.apiUrl || 'api/media.php';
    const UPLOAD_URL = config.uploadUrl || 'upload_handler.php';
    const CURRENT_DIR = config.currentDir || '';
    const CSRF_TOKEN = config.csrfToken || '';

    function postForm(action, data) {
        const formData = new FormData();
        formData.append('action', action);

        Object.keys(data || {}).forEach((key) => {
            const value = data[key];
            if (Array.isArray(value) || (value && typeof value === 'object')) {
                formData.append(key, JSON.stringify(value));
            } else {
                formData.append(key, value == null ? '' : String(value));
            }
        });

        if (CSRF_TOKEN && !formData.has('csrf_token')) {
            formData.append('csrf_token', CSRF_TOKEN);
        }

        return fetch(API_URL, {
            method: 'POST',
            body: formData,
        }).then((response) => response.json());
    }

    async function uploadFile() {
        const input = document.getElementById('fileInput');
        if (!input || !input.files || input.files.length === 0) {
            return;
        }

        const files = Array.from(input.files);
        const btn = document.getElementById('uploadBtn');
        const originalText = btn ? btn.innerText : '';
        
        if (btn) {
            btn.disabled = true;
        }

        let successCount = 0;
        let errorCount = 0;
        const totalFiles = files.length;

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            
            if (btn) {
                btn.innerText = `${LANG.uploading || 'Uploading...'} (${i + 1}/${totalFiles})`;
            }

            const formData = new FormData();
            formData.append('file', file);
            formData.append('dir', CURRENT_DIR);
            formData.append('csrf_token', CSRF_TOKEN);

            try {
                const response = await fetch(UPLOAD_URL, {
                    method: 'POST',
                    body: formData,
                });

                const text = await response.text();
                let result = null;
                try {
                    result = JSON.parse(text);
                } catch (parseErr) {
                    console.warn('Upload response is not valid JSON, raw body:', text);
                    errorCount++;
                    continue;
                }

                if (result.success) {
                    successCount++;
                } else {
                    errorCount++;
                    console.error('Upload failed for', file.name, result.error);
                }
            } catch (e) {
                console.error(e);
                errorCount++;
            }
        }

        if (btn) {
            btn.innerText = originalText;
            btn.disabled = false;
        }

        if (successCount > 0) {
            if (errorCount > 0) {
                cms_alert(`${LANG.uploaded || 'Uploaded'} ${successCount}/${totalFiles} files. ${errorCount} failed.`);
            } else {
                cms_alert(`${LANG.uploaded || 'Uploaded'} ${successCount} files successfully.`);
            }
            location.reload();
        } else {
            cms_alert(LANG.error_upload || 'Upload error');
        }

        input.value = '';
    }

    function openFilePicker() {
        const input = document.getElementById('fileInput');
        if (input) {
            input.click();
        }
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            cms_alert((LANG.copied || 'Copied:') + ' ' + text);
        });
    }

    async function deleteFile(filename) {
        cms_confirm((LANG.delete_file_confirm || 'Delete file') + ' "' + filename + '"?', async () => {
            try {
                const result = await postForm('delete_file', {
                    filename: filename,
                    currentDir: CURRENT_DIR,
                    csrf_token: CSRF_TOKEN,
                });

                if (result.success) {
                    location.reload();
                } else {
                    cms_alert((LANG.error_prefix || 'Error: ') + (result.error || 'delete'));
                }
            } catch (e) {
                console.error(e);
                cms_alert((LANG.error_prefix || 'Error: ') + 'delete');
            }
        });
    }

    function switchMediaLang(lang) {
        document.querySelectorAll('.alt-input').forEach((el) => {
            el.style.display = 'none';
        });
        document.querySelectorAll('.lang-alt-' + lang).forEach((el) => {
            el.style.display = 'block';
        });
    }

    async function saveAlt(filename, alt, lang) {
        try {
            const result = await postForm('save_alt', { filename, alt, lang });
            if (!result.success) {
                cms_alert((LANG.error_prefix || 'Error: ') + 'alt: ' + (result.error || 'save'));
            }
        } catch (e) {
            console.error(e);
            cms_alert((LANG.error_prefix || 'Error: ') + 'alt');
        }
    }

    const dropZone = document.querySelector('.upload-zone');
    let dragCounter = 0;

    if (dropZone) {
        window.addEventListener('dragenter', (e) => {
            if (!e.dataTransfer || !Array.from(e.dataTransfer.types || []).includes('Files')) {
                return;
            }
            e.preventDefault();
            dragCounter++;
            dropZone.classList.add('active');
        });

        window.addEventListener('dragover', (e) => {
            if (!e.dataTransfer || !Array.from(e.dataTransfer.types || []).includes('Files')) {
                return;
            }
            e.preventDefault();
        });

        window.addEventListener('dragleave', (e) => {
            if (!e.dataTransfer || !Array.from(e.dataTransfer.types || []).includes('Files')) {
                return;
            }
            e.preventDefault();
            dragCounter = Math.max(0, dragCounter - 1);
            if (dragCounter === 0) {
                dropZone.classList.remove('active');
            }
        });

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#007bff';
            dropZone.style.background = '#e2e6ea';
        });

        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#ced4da';
            dropZone.style.background = '#f9fafb';
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dragCounter = 0;
            dropZone.classList.remove('active');
            dropZone.style.borderColor = '#ced4da';
            dropZone.style.background = '#f9fafb';

            if (e.dataTransfer.files && e.dataTransfer.files.length) {
                const fileInput = document.getElementById('fileInput');
                if (fileInput) {
                    fileInput.files = e.dataTransfer.files;
                    uploadFile();
                }
            }
        });
    }

    let draggedItem = null;

    document.querySelectorAll('.media-item').forEach((item) => {
        item.addEventListener('dragstart', () => {
            draggedItem = {
                type: item.dataset.type,
                name: item.dataset.name,
            };
            item.classList.add('dragging');
        });

        item.addEventListener('dragend', () => {
            item.classList.remove('dragging');
        });

        if (item.classList.contains('folder-item')) {
            item.addEventListener('dragover', (e) => {
                e.preventDefault();
                item.classList.add('drag-over');
            });

            item.addEventListener('dragleave', () => {
                item.classList.remove('drag-over');
            });

            item.addEventListener('drop', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                item.classList.remove('drag-over');

                if (!draggedItem) {
                    return;
                }

                const targetFolder = item.dataset.name;

                try {
                    const result = await postForm('move_item', {
                        type: draggedItem.type,
                        name: draggedItem.name,
                        target: targetFolder,
                        currentDir: CURRENT_DIR,
                    });

                    if (result.success) {
                        location.reload();
                    } else {
                        cms_alert((LANG.error_prefix || 'Error: ') + 'move: ' + (result.error || 'move'));
                    }
                } catch (e) {
                    console.error(e);
                    cms_alert((LANG.error_prefix || 'Error: ') + 'move');
                }
            });
        }
    });

    function getSelectedItems() {
        const items = [];
        document.querySelectorAll('.select-checkbox:checked').forEach((ch) => {
            items.push({ name: ch.dataset.name, type: ch.dataset.type });
        });
        return items;
    }

    function updateBulkBar() {
        const items = getSelectedItems();
        const bar = document.getElementById('bulkBar');
        const bulkText = document.getElementById('bulkText');
        if (!bar || !bulkText) {
            return;
        }

        if (items.length > 0) {
            bar.style.display = 'flex';
            bulkText.innerText = items.length + ' ' + (LANG.selected || 'selected');
        } else {
            bar.style.display = 'none';
            const selectAll = document.getElementById('selectAll');
            if (selectAll) {
                selectAll.checked = false;
            }
        }
    }

    function toggleSelectAll(checkbox) {
        document.querySelectorAll('.select-checkbox').forEach((c) => {
            c.checked = checkbox.checked;
        });
        updateBulkBar();
    }

    async function bulkDeleteSelected() {
        const items = getSelectedItems();
        if (!items.length) {
            cms_alert('No items selected');
            return;
        }

        cms_confirm((LANG.bulk_delete_confirm || 'Delete %d items?').replace('%d', items.length), async () => {
            try {
                const result = await postForm('bulk_delete', {
                    items,
                    currentDir: CURRENT_DIR,
                    csrf_token: CSRF_TOKEN,
                });

                if (result.deleted && result.deleted.length) {
                    cms_alert((LANG.bulk_delete_success || 'Deleted %d').replace('%d', result.deleted.length));
                    location.reload();
                } else if (result.errors && result.errors.length) {
                    cms_alert((LANG.error_prefix || 'Error: ') + (result.errors[0].error || 'Error'));
                } else {
                    cms_alert((LANG.error_prefix || 'Error: ') + 'delete');
                }
            } catch (e) {
                console.error(e);
                cms_alert((LANG.error_prefix || 'Error: ') + 'delete');
            }
        });
    }

    async function bulkMoveSelected() {
        const items = getSelectedItems();
        const select = document.getElementById('bulkTarget');

        if (!items.length) {
            cms_alert('No items selected');
            return;
        }
        if (!select || select.selectedIndex <= 0) {
            cms_alert(LANG.select_destination || 'Select destination');
            return;
        }

        try {
            const result = await postForm('bulk_move', {
                items,
                currentDir: CURRENT_DIR,
                targetDir: select.value,
                csrf_token: CSRF_TOKEN,
            });

            if (result.moved && result.moved.length) {
                cms_alert((LANG.bulk_move_success || 'Moved %d').replace('%d', result.moved.length));
                location.reload();
            } else if (result.errors && result.errors.length) {
                cms_alert((LANG.error_prefix || 'Error: ') + (result.errors[0].error || 'Error'));
            } else {
                cms_alert((LANG.error_prefix || 'Error: ') + 'move');
            }
        } catch (e) {
            console.error(e);
            cms_alert((LANG.error_prefix || 'Error: ') + 'move');
        }
    }

    function createFolder() {
        openModal('createFolderModal');
        setTimeout(() => {
            const input = document.getElementById('newFolderName');
            if (input) {
                input.focus();
            }
        }, 100);
    }

    const createFolderForm = document.getElementById('createFolderForm');
    if (createFolderForm) {
        createFolderForm.onsubmit = async function (e) {
            e.preventDefault();
            const input = document.getElementById('newFolderName');
            const name = input ? input.value : '';
            if (!name) {
                return;
            }

            try {
                const result = await postForm('create_folder', {
                    name,
                    currentDir: CURRENT_DIR,
                });

                if (result.success) {
                    location.reload();
                } else {
                    cms_alert((LANG.error_prefix || 'Error: ') + (result.error || 'create folder'));
                }
            } catch (e2) {
                console.error(e2);
                cms_alert((LANG.error_prefix || 'Error: ') + 'create folder');
            }
        };
    }

    async function deleteFolder(name) {
        cms_confirm((LANG.delete_folder_confirm || 'Delete folder') + ' "' + name + '"?', async () => {
            try {
                const result = await postForm('delete_folder', {
                    name,
                    currentDir: CURRENT_DIR,
                });

                if (result.success) {
                    location.reload();
                } else {
                    cms_alert((LANG.error_prefix || 'Error: ') + (result.error || 'delete folder'));
                }
            } catch (e) {
                console.error(e);
                cms_alert((LANG.error_prefix || 'Error: ') + 'delete folder');
            }
        });
    }

    async function renameFolder(oldName) {
        cms_prompt(LANG.rename_folder_prompt || 'Rename folder', async (newName) => {
            if (!newName || newName === oldName) {
                return;
            }

            try {
                const result = await postForm('rename_folder', {
                    oldName,
                    newName,
                    currentDir: CURRENT_DIR,
                });

                if (result.success) {
                    location.reload();
                } else {
                    cms_alert((LANG.error_prefix || 'Error: ') + (result.error || 'rename folder'));
                }
            } catch (e) {
                console.error(e);
                cms_alert((LANG.error_prefix || 'Error: ') + 'rename folder');
            }
        }, oldName);
    }

    window.openFilePicker = openFilePicker;
    window.uploadFile = uploadFile;
    window.copyToClipboard = copyToClipboard;
    window.deleteFile = deleteFile;
    window.switchMediaLang = switchMediaLang;
    window.saveAlt = saveAlt;
    window.updateBulkBar = updateBulkBar;
    window.toggleSelectAll = toggleSelectAll;
    window.bulkDeleteSelected = bulkDeleteSelected;
    window.bulkMoveSelected = bulkMoveSelected;
    window.createFolder = createFolder;
    window.deleteFolder = deleteFolder;
    window.renameFolder = renameFolder;
})();
