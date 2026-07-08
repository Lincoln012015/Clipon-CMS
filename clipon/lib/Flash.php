<?php
class Flash
{
    private const PREFIX = 'flash_';

    public static function set(string $key, string $type, string $message): void
    {
        $session = new Session();
        $session->set(self::PREFIX . $key, [
            'type' => $type,
            'message' => $message
        ]);
    }

    public static function get(string $key): ?array
    {
        $session = new Session();
        return $session->get(self::PREFIX . $key);
    }

    public static function pull(string $key): ?array
    {
        $session = new Session();
        $k = self::PREFIX . $key;
        if (!$session->has($k)) return null;
        $val = $session->get($k);
        $session->remove($k);
        return $val;
    }

    public static function has(string $key): bool
    {
        $session = new Session();
        return $session->has(self::PREFIX . $key);
    }

    public static function clear(string $key): void
    {
        $session = new Session();
        $session->remove(self::PREFIX . $key);
    }

    public static function success(string $message): void
    {
        self::set('msg', 'success', $message);
    }

    public static function error(string $message): void
    {
        self::set('msg', 'danger', $message);
    }

    public static function info(string $message): void
    {
        self::set('msg', 'info', $message);
    }

    public static function warning(string $message): void
    {
        self::set('msg', 'warning', $message);
    }
}
