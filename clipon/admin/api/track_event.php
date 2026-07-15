<?php
if (!defined('CLIPON_SESSIONLESS_BOOTSTRAP')) {
    define('CLIPON_SESSIONLESS_BOOTSTRAP', true);
}
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../lib/Analytics.php';
require_once __DIR__ . '/../../lib/CookieConsentPolicy.php';

// Базовий ендпоінт для трекінгу івентів (наприклад, глибина скролу)
// Очікує POST з JSON: { "category": "scroll", "action": "50%" }

header('Content-Type: application/json; charset=utf-8');

function clipon_analytics_basic_event_allowed(string $pageviewId): bool {
    $dir = C_DATA_PATH . '/analytics';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $stateFile = $dir . '/event_rate.php';
    $lockFile = $dir . '/event_rate.lock';
    $lock = @fopen($lockFile, 'c');
    if ($lock === false) {
        return true;
    }

    if (!flock($lock, LOCK_EX)) {
        fclose($lock);
        return true;
    }

    try {
        $state = [];
        if (is_file($stateFile)) {
            $raw = (string)file_get_contents($stateFile);
            $json = str_replace("<?php die(); ?>\n", '', $raw);
            $decoded = json_decode($json, true);
            $state = is_array($decoded) ? $decoded : [];
        }

        $now = time();
        $bucket = (int)floor($now / 60);
        $key = hash('sha256', $pageviewId);

        foreach ($state as $storedKey => $row) {
            if (!is_array($row) || (int)($row['bucket'] ?? 0) < $bucket - 1) {
                unset($state[$storedKey]);
            }
        }

        $row = is_array($state[$key] ?? null) ? $state[$key] : ['bucket' => $bucket, 'count' => 0];
        if ((int)($row['bucket'] ?? 0) !== $bucket) {
            $row = ['bucket' => $bucket, 'count' => 0];
        }

        $row['count'] = (int)($row['count'] ?? 0) + 1;
        $state[$key] = $row;

        file_put_contents($stateFile, "<?php die(); ?>\n" . json_encode($state, JSON_UNESCAPED_SLASHES), LOCK_EX);

        return $row['count'] <= 120;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

if (!$request->isPost()) {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$data = $request->json();

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}

$category = $data['category'] ?? null;
$action = $data['action'] ?? null;
$label = $data['label'] ?? null;
$pageviewId = $data['pageview_id'] ?? null;
$signature = $data['signature'] ?? null;

if (!is_string($category) || !is_string($action) || ($label !== null && !is_string($label))) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid event payload']);
    exit;
}

$category = trim($category);
$action = trim($action);

$customRule = null;
if ($category === 'conversion' && function_exists('registry') && registry()->has('pro_analytics.service') && method_exists(registry()->get('pro_analytics.service'), 'getCustomConversionEvent')) {
    $analyticsSettings = Settings::load();
    $customRule = registry()->get('pro_analytics.service')->getCustomConversionEvent(
        $action,
        is_array($analyticsSettings['custom_conversion_events'] ?? null) ? $analyticsSettings['custom_conversion_events'] : []
    );
}

$allowed = [
    'scroll' => ['25%', '50%', '75%', '100%'],
    'system' => ['timer_pulse']
];

if ($customRule === null && (!isset($allowed[$category]) || !in_array($action, $allowed[$category], true))) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Unsupported event']);
    exit;
}

if ($label !== null) {
    $label = trim($label);
    if ($label !== '' && (strlen($label) > 220 || $label[0] !== '/')) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid label']);
        exit;
    }
}

if ($category !== '' && $action !== '') {
    $hasBasicAttempt = is_string($pageviewId) || is_string($signature);
    if ($hasBasicAttempt) {
        if (!is_string($pageviewId) || !is_string($signature) || !CookieConsentPolicy::verifyPageviewSignature($pageviewId, $signature)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
            exit;
        }

        if (!clipon_analytics_basic_event_allowed($pageviewId)) {
            http_response_code(429);
            echo json_encode(['status' => 'error', 'message' => 'Too many events']);
            exit;
        }

        if ($customRule !== null) {
            registry()->get('pro_analytics.service')->trackCustomConversion($customRule, (string)($label ?: '/'), true, $pageviewId);
        } else {
            Analytics::trackBasicEvent($category, $action, $label);
        }
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if (session_status() !== PHP_SESSION_ACTIVE && class_exists('SessionManager')) {
        SessionManager::start();
        SessionManager::enforceActivity();
    }

    $tokenHeader = (string)$request->server('HTTP_X_ANALYTICS_TOKEN', '');
    $sessionToken = (string)$session->get('analytics_event_token', '');

    if ($tokenHeader === '' || $sessionToken === '' || !hash_equals($sessionToken, $tokenHeader)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }

    $bucketStartedAt = (int)$session->get('analytics_event_bucket_started', 0);
    $bucketCount = (int)$session->get('analytics_event_bucket_count', 0);
    $now = time();
    if ($bucketStartedAt === 0 || ($now - $bucketStartedAt) >= 60) {
        $bucketStartedAt = $now;
        $bucketCount = 0;
    }

    $bucketCount++;
    $session->set('analytics_event_bucket_started', $bucketStartedAt);
    $session->set('analytics_event_bucket_count', $bucketCount);

    if ($bucketCount > 120) {
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => 'Too many events']);
        exit;
    }

    if ($customRule !== null) {
        registry()->get('pro_analytics.service')->trackCustomConversion($customRule, (string)($label ?: '/'));
    } else {
        Analytics::trackEvent($category, $action, $label);
    }
    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
