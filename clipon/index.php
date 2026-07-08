<?php
/**
 * Clipon CMS Admin Hub
 * Redirects to Setup if not installed, or to Login/Dashboard if installed.
 */

require_once __DIR__ . '/bootstrap.php';

if (!file_exists(C_CONFIG_PATH . '/settings.php')) {
    header('Location: setup.php');
    exit;
} else {
    header('Location: admin/');
    exit;
}

header('Location: admin/index.php');
exit;
