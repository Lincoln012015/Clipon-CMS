<?php
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Analytics.php';
require_once __DIR__ . '/../../lib/Funnels.php';
require_once __DIR__ . '/../../lib/Csrf.php';

header('Content-Type: application/json');

if (!$session->has('user')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!hasPermission('view_analytics')) {
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$proAnalyticsAvailable = class_exists('ProAnalyticsPolicy') && ProAnalyticsPolicy::isAvailable();
if (!$proAnalyticsAvailable) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Pro Analytics required']);
    exit;
}

$action = $request->post('action', '');

if ($action === 'add_funnel') {
    $csrfOk = Csrf::validate($request->post('csrf_token', ''));
    if (!$csrfOk) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF']);
        exit;
    }

    $name = $request->string('funnel_name');
    $stepsRaw = $request->string('funnel_steps');
    $ordered = $request->bool('funnel_ordered');

    $steps = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $stepsRaw)));
    $steps = array_values(array_unique($steps));

    if ($name && !empty($steps)) {
        $newId = Funnels::add($name, $steps, $ordered);
        if ($newId) {
            echo json_encode(['success' => true, 'id' => $newId]);
        } else {
            echo json_encode(['success' => false, 'error' => 'System error']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    }
    exit;
}

if ($action === 'delete_funnel') {
    $csrfOk = Csrf::validate($request->post('csrf_token', ''));
    if (!$csrfOk) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF']);
        exit;
    }

    $id = $request->string('funnel_id');
    if ($id && Funnels::delete($id)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Delete failed']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
