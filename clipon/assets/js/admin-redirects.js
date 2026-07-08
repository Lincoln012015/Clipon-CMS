(function() {
    const REDIRECTS_API_URL = 'api/redirects.php';
    const REDIRECT_FORM = document.getElementById('addRedirectForm');
    const REDIRECT_TABLE_BODY = document.querySelector('.admin-table tbody');
    const STATS_CARDS = document.querySelector('.stats');
    const CSRF_TOKEN = (window.REDIRECTS_ADMIN_CONFIG && window.REDIRECTS_ADMIN_CONFIG.csrfToken) || window.CLIPON_CSRF_TOKEN || '';

    if (REDIRECT_FORM) {
        REDIRECT_FORM.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            if (CSRF_TOKEN && !formData.has('csrf_token')) {
                formData.append('csrf_token', CSRF_TOKEN);
            }

            fetch(REDIRECTS_API_URL, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    cms_alert(data.message);
                    location.reload(); // Refresh to update list and stats
                } else {
                    cms_alert(data.message || 'Error');
                }
            })
            .catch(error => console.error('Error:', error));
        });
    }

    window.deleteRedirect = function(oldUrl) {
        cms_confirm(window.LANG_DELETE_CONFIRM || 'Delete this redirect?', () => {
            const formData = new FormData();
            formData.append('action', 'delete_redirect');
            formData.append('old_url', oldUrl);
            if (CSRF_TOKEN) {
                formData.append('csrf_token', CSRF_TOKEN);
            }

            fetch(REDIRECTS_API_URL, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    cms_alert(data.message);
                    location.reload();
                } else {
                    cms_alert(data.message || 'Error');
                }
            })
            .catch(error => console.error('Error:', error));
        });
    };

})();
