document.addEventListener('DOMContentLoaded', () => {
    const attachFormListener = (form) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const config = window.USER_ADMIN_CONFIG || {};
            if (config.csrfToken && !formData.has('csrf_token')) {
                formData.append('csrf_token', config.csrfToken);
            }
            const action = formData.get('action');

            if (!action) return;

            if (action === 'delete_user') {
                const confirmMsg = (window.USER_ADMIN_LANG && window.USER_ADMIN_LANG.delete_user_confirm) || 'Delete this user?';
                
                if (!form.dataset.confirmed) {
                    AdminUI.confirm(confirmMsg, () => {
                        form.dataset.confirmed = 'true';
                        form.requestSubmit();
                        delete form.dataset.confirmed;
                    });
                    return;
                }
            }

            if (action === 'update_permissions' || action === 'create_moderator' || action === 'delete_user') {
                // Ensure array data is collected correctly for multi-checkbox
                const permissions = Array.from(form.querySelectorAll('input[name="permissions[]"]:checked')).map(cb => cb.value);
                formData.delete('permissions[]');
                permissions.forEach(p => formData.append('permissions[]', p));
                
                // Ensure username is present for all actions
                if (!formData.has('username') && form.querySelector('[name=username]')) {
                    formData.append('username', form.querySelector('[name=username]').value);
                }
            }

            try {
                const response = await fetch('api/users.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.status === 'success') {
                    showToast(result.message, 'success');
                    if (action === 'delete_user' || action === 'create_moderator' || action === 'update_permissions') {
                        setTimeout(() => location.reload(), 1000); // Reload after toast
                    }
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('API error:', error);
                const lang = window.USER_ADMIN_LANG || {};
                showToast(lang.api_error || 'API Error', 'error');
            }
        });
    };

    const userForms = document.querySelectorAll('.user-item form, .cms-accordion form, .page-details form, #createModeratorForm');
    userForms.forEach(attachFormListener);

    // Helper for modals
    window.openCreateModeratorModal = function() {
        const modal = document.getElementById('createModeratorModal');
        if (modal) modal.classList.add('active');
    };

    window.closeCreateModeratorModal = function() {
        const modal = document.getElementById('createModeratorModal');
        if (modal) modal.classList.remove('active');
    };
});
