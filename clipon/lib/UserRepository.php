<?php

class UserRepository {
    private string $filePath;

    public function __construct(?string $filePath = null) {
        $this->filePath = $filePath ?? C_CONFIG_PATH . '/users.php';
    }

    public function getAll(): array {
        if (!file_exists($this->filePath)) {
            return [];
        }
        return read_json_file($this->filePath) ?: [];
    }

    public function findByLogin(string $login): ?array {
        $login = self::normalizeLogin($login);
        $users = $this->getAll();
        foreach ($users as $username => $user) {
            if (self::normalizeLogin((string)$username) === $login) {
                $user['username'] = self::normalizeLogin((string)$username);
                return $user;
            }
        }
        return null;
    }

    public function save(string $username, array $userData): bool {
        $username = self::normalizeLogin($username);
        $users = $this->getAll();
        foreach (array_keys($users) as $existingUsername) {
            if (self::normalizeLogin((string)$existingUsername) === $username && $existingUsername !== $username) {
                unset($users[$existingUsername]);
            }
        }
        $users[$username] = $userData;
        return write_json_file($this->filePath, $users);
    }

    public function updatePassword(string $username, string $newHash): bool {
        $username = self::normalizeLogin($username);
        $users = $this->getAll();
        $matches = [];
        foreach (array_keys($users) as $existingUsername) {
            if (self::normalizeLogin((string)$existingUsername) === $username) {
                $matches[] = (string)$existingUsername;
            }
        }

        if (!isset($users[$username]) && count($matches) === 1) {
            $users[$username] = $users[$matches[0]];
            unset($users[$matches[0]]);
        } elseif (!isset($users[$username]) && count($matches) > 1) {
            return false;
        }

        if (!isset($users[$username])) {
            return false;
        }
        $users[$username]['password'] = $newHash;
        return write_json_file($this->filePath, $users);
    }

    private static function normalizeLogin(string $login): string {
        return strtolower(trim($login));
    }
}
