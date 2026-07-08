<?php
/**
 * Простий throttle для захисту від брутфорсу.
 * Зберігає лічильники у `config/login_throttle.php`.
 */

require_once __DIR__ . '/JsonStorage.php';
require_once __DIR__ . '/AuthLogger.php';

function throttle_file_path(): string {
    return __DIR__ . '/../config/login_throttle.php';
}

function throttle_load(): array {
    $file = throttle_file_path();
    return read_json_file($file);
}

function throttle_save(array $data): bool {
    $file = throttle_file_path();
    return write_json_file($file, $data);
}

function throttle_clean(array &$data) {
    $now = time();
    $window = 900; // 15 minutes window for stale counts
    foreach (['by_user','by_ip'] as $k) {
        if (empty($data[$k]) || !is_array($data[$k])) continue;
        foreach ($data[$k] as $id => $entry) {
            $last = $entry['last'] ?? 0;
            $lock = $entry['lock_until'] ?? 0;
            if ($lock > $now) continue; // keep locked entries
            if ($now - $last > $window) {
                unset($data[$k][$id]);
            }
        }
    }
}

function throttle_is_locked(string $username, string $ip): array {
    $data = throttle_load();
    throttle_clean($data);
    $now = time();
    $userEntry = $data['by_user'][$username] ?? null;
    $ipEntry = $data['by_ip'][$ip] ?? null;
    $userLocked = $userEntry && !empty($userEntry['lock_until']) && $userEntry['lock_until'] > $now;
    $ipLocked = $ipEntry && !empty($ipEntry['lock_until']) && $ipEntry['lock_until'] > $now;
    $remaining = 0;
    if ($userLocked) $remaining = max($remaining, ($userEntry['lock_until'] - $now));
    if ($ipLocked) $remaining = max($remaining, ($ipEntry['lock_until'] - $now));
    return ['locked' => ($userLocked || $ipLocked), 'remaining' => $remaining];
}

function throttle_register_failure(string $username, string $ip): int {
    $data = throttle_load();
    if (!isset($data['by_user'])) $data['by_user'] = [];
    if (!isset($data['by_ip'])) $data['by_ip'] = [];
    throttle_clean($data);

    $now = time();
    $maxAttempts = 5;
    $lockDuration = 15 * 60; // 15 minutes

    // update user entry
    $ue = $data['by_user'][$username] ?? ['count' => 0, 'first' => $now, 'last' => $now, 'lock_until' => 0];
    if (($ue['last'] ?? 0) < $now - 3600) {
        $ue['count'] = 0; $ue['first'] = $now;
    }
    $ue['count'] = ($ue['count'] ?? 0) + 1;
    $ue['last'] = $now;
    if ($ue['count'] >= $maxAttempts) {
        $ue['lock_until'] = $now + $lockDuration;
    }
    $data['by_user'][$username] = $ue;

    // update ip entry
    $ie = $data['by_ip'][$ip] ?? ['count' => 0, 'first' => $now, 'last' => $now, 'lock_until' => 0];
    if (($ie['last'] ?? 0) < $now - 3600) {
        $ie['count'] = 0; $ie['first'] = $now;
    }
    $ie['count'] = ($ie['count'] ?? 0) + 1;
    $ie['last'] = $now;
    if ($ie['count'] >= ($maxAttempts * 2)) {
        $ie['lock_until'] = $now + $lockDuration;
    }
    $data['by_ip'][$ip] = $ie;

    throttle_save($data);

    // compute a small delay to slow brute force (exponential, capped)
    $delay = (int) min(8, pow(2, max(0, $ue['count'] - 1)));
    if ($delay < 1) $delay = 1;
    // Log the failed attempt
    auth_log('warning', 'Failed login attempt', [
        'user' => $username,
        'ip' => $ip,
        'user_count' => $ue['count'],
        'ip_count' => $ie['count']
    ]);
    return $delay;
}

function throttle_reset(string $username, string $ip): void {
    $data = throttle_load();
    $changed = false;
    if (!empty($data['by_user'][$username])) { unset($data['by_user'][$username]); $changed = true; }
    if (!empty($data['by_ip'][$ip])) { unset($data['by_ip'][$ip]); $changed = true; }
    if ($changed) throttle_save($data);
}

?>
