<?php
// Clipon CMS Upload Handler using MediaManager
require_once __DIR__ . '/../lib/Auth.php';

AdminAccess::requireUserApi($session);

// Start output buffering so JSON is not broken by warnings/notices
if (function_exists('ob_start')) {
    ob_start();
}

header('Content-Type: application/json');
if (function_exists('ini_set')) {
    @ini_set('display_errors', '0');
}

function clipon_send_json(array $payload, int $statusCode = 200): void {
    if (!headers_sent()) {
        http_response_code($statusCode);
    }
    if (function_exists('ob_get_length') && function_exists('ob_clean')) {
        if (ob_get_length()) {
            @ob_clean();
        }
    }
    echo json_encode($payload);
    exit;
}

if ($request->server('REQUEST_METHOD') !== 'POST') {
    clipon_send_json(['error' => __('error_method_not_allowed')], 405);
}

$csrfToken = $request->post('csrf_token', '');
if (!Csrf::validate($csrfToken)) {
    clipon_send_json(['error' => __('error_invalid_csrf')], 403);
}

$uploadedFile = $request->file('file');
if (!$uploadedFile) {
    clipon_send_json(['error' => __('error_no_file_uploaded')]);
}

$currentDir = $request->post('dir', '');

try {
    $manager = new MediaManager();
    $result = $manager->upload($uploadedFile, $currentDir);
    $relativePath = $result['relativePath'] ?? $result['filename'];

    clipon_send_json([
        'success' => true,
        'filename' => $result['filename'],
        'path' => '/assets/' . ltrim($relativePath, '/')
    ]);
} catch (Exception $e) {
    clipon_send_json(['error' => $e->getMessage()]);
}
