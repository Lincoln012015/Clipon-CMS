<?php

class UserService
{
    public const MIN_PASSWORD_LENGTH = 8;

    protected string $usersFile;
    protected array $users = [];
    protected bool $normalizationConflict = false;

    public function __construct()
    {
        $this->usersFile = C_CONFIG_PATH . '/users.php';
        $this->loadUsers();
    }

    protected function loadUsers(): void
    {
        if (file_exists($this->usersFile)) {
            $this->users = read_json_file($this->usersFile) ?: [];
            $this->normalizeUsersStore();
        }
    }

    protected function saveUsers(): bool
    {
        return write_json_file($this->usersFile, $this->users);
    }

    public function getUser(string $username): ?array
    {
        $username = self::normalizeLogin($username);
        return $this->users[$username] ?? null;
    }

    public function hasAdmin(): bool
    {
        foreach ($this->users as $user) {
            if (($user['role'] ?? '') === 'admin') {
                return true;
            }
        }

        return false;
    }

    public function createAdmin(string $login, string $password, string $name): bool
    {
        $login = self::normalizeLogin($login);
        if (!self::isValidLogin($login) || isset($this->users[$login]) || $this->normalizationConflict || $this->hasAdmin()) {
            return false;
        }

        $this->users[$login] = [
            'password' => self::hashPassword($password),
            'name' => $name,
            'role' => 'admin',
            'created' => date('Y-m-d')
        ];

        return $this->saveUsers();
    }

    public function updateProfile(string $username, string $name): bool
    {
        $username = self::normalizeLogin($username);
        if (isset($this->users[$username])) {
            $this->users[$username]['name'] = $name;
            return $this->saveUsers();
        }
        return false;
    }

    public function updatePassword(string $username, string $newPassword, ?string $currentPassword = null): bool
    {
        $username = self::normalizeLogin($username);
        if (isset($this->users[$username])) {
            if ($currentPassword !== null && !password_verify($currentPassword, (string)$this->users[$username]['password'])) {
                return false;
            }

            $this->users[$username]['password'] = self::hashPassword($newPassword);
            return $this->saveUsers();
        }
        return false;
    }

    public static function normalizeLogin(string $login): string
    {
        return strtolower(trim($login));
    }

    public static function isValidLogin(string $login): bool
    {
        $login = self::normalizeLogin($login);
        return $login !== ''
            && strlen($login) <= 64
            && preg_match('/^[a-z0-9._-]+$/', $login) === 1;
    }

    public static function isValidPassword(string $password): bool
    {
        return strlen($password) >= self::MIN_PASSWORD_LENGTH;
    }

    public static function hashPassword(string $password): string
    {
        if (class_exists('Auth')) {
            return Auth::hashPassword($password);
        }

        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 1 << 15,
                'time_cost' => 4,
                'threads' => 2
            ]);
        }

        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function hasNormalizationConflict(): bool
    {
        return $this->normalizationConflict;
    }

    public function loginExists(string $login): bool
    {
        return isset($this->users[self::normalizeLogin($login)]);
    }

    protected function normalizeUsersStore(): void
    {
        $normalized = [];
        $changed = false;
        $conflict = false;

        foreach ($this->users as $login => $user) {
            $key = self::normalizeLogin((string)$login);
            if ($key === '') {
                $key = (string)$login;
            }

            if (isset($normalized[$key])) {
                $conflict = true;
                continue;
            }

            if ($key !== $login) {
                $changed = true;
            }

            $normalized[$key] = is_array($user) ? $user : [];
        }

        if ($conflict) {
            $this->normalizationConflict = true;
            return;
        }

        if ($changed) {
            $this->users = $normalized;
            $this->saveUsers();
        } else {
            $this->users = $normalized;
        }
    }

    /**
     * Core version only allows basic info for current user.
     * Moderator management is handled by the pro_users module.
     */
    public function canManageUser(string $currentUsername, string $targetUsername): bool
    {
        return self::normalizeLogin($currentUsername) === self::normalizeLogin($targetUsername);
    }
}
