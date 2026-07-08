<?php
/**
 * Бібліотека перевірки прав доступу (Рефакторинг)
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/JsonStorage.php';

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/UserRepository.php';
require_once __DIR__ . '/Gate.php';

Translation::init();

if (php_sapi_name() !== 'cli' && !headers_sent()) {
    // Admin pages and API responses are session-bound and must not be cached.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Vary: Cookie');
}

class Auth {
    private ?UserRepository $userRepository;

    public function __construct(?UserRepository $userRepository = null) {
        $this->userRepository = $userRepository ?? new UserRepository();
    }

    public static function hashPassword(string $password): string {
        if (defined('PASSWORD_ARGON2ID')) {
            $options = [
                'memory_cost' => 1 << 15, // 32 MB
                'time_cost' => 4,
                'threads' => 2
            ];
            return password_hash($password, PASSWORD_ARGON2ID, $options);
        }
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function passwordNeedsRehash(string $hash): bool {
        if (defined('PASSWORD_ARGON2ID')) {
            $options = [
                'memory_cost' => 1 << 15,
                'time_cost' => 4,
                'threads' => 2
            ];
            return password_needs_rehash($hash, PASSWORD_ARGON2ID, $options);
        }
        return false;
    }

    public function attemptLogin(string $login, string $password): bool {
        $user = $this->userRepository->findByLogin($login);
        
        if ($user && password_verify($password, $user['password'])) {
            $username = $user['username'];
            
            if (self::passwordNeedsRehash($user['password'])) {
                $newHash = self::hashPassword($password);
                $this->userRepository->updatePassword($username, $newHash);
            }

            $session = new Session();
            $session->set('user', $username);
            $session->set('role', $user['role']);
            $session->set('permissions', $user['permissions'] ?? []);
            $session->set('login_attempts', 0);
            SessionManager::refreshActivity();
            
            session_regenerate_id(true);
            Csrf::init();

            return true;
        }

        return false;
    }

    public function loadUserPermissions(string $username): void {
        $user = $this->userRepository->findByLogin($username);
        if ($user) {
            $session = new Session();
            $session->set('role', $user['role'] ?? 'moderator');
            $session->set('permissions', $user['permissions'] ?? []);
        }
    }
}

// Global functions for backwards compatibility
SessionManager::start();
SessionManager::enforceActivity();

function hasPermission($permission) {
    return Gate::hasPermission($permission);
}

function requirePermission($permission, $redirectUrl = 'index.php') {
    Gate::requirePermission($permission, $redirectUrl);
}

function isAdmin() {
    return Gate::isAdmin();
}

function getCsrfToken() {
    return Csrf::token();
}

function requireAdmin($redirectUrl = 'login.php') {
    Gate::requireAdmin($redirectUrl);
}

function loadUserPermissions($username) {
    $auth = new Auth();
    $auth->loadUserPermissions($username);
}

function hashPassword(string $password): string {
    return Auth::hashPassword($password);
}

function passwordNeedsRehashLocal(string $hash): bool {
    return Auth::passwordNeedsRehash($hash);
}

function updateUserPasswordInStore(string $username, string $newHash): bool {
    $repo = new UserRepository();
    return $repo->updatePassword($username, $newHash);
}
