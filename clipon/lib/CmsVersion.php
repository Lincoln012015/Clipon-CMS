<?php

class CmsVersion
{
    public static function current(): string
    {
        $versionFile = C_CORE_DIR . '/config/version.php';
        if (!is_file($versionFile)) {
            return '0.0.0';
        }

        $versionData = require $versionFile;
        if (is_array($versionData) && isset($versionData['version'])) {
            return self::normalize((string)$versionData['version']);
        }

        if (is_string($versionData)) {
            return self::normalize($versionData);
        }

        return '0.0.0';
    }

    public static function normalize(string $version): string
    {
        $value = trim($version);
        return self::isValid($value) ? $value : '0.0.0';
    }

    public static function isValid(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+$/', trim($version)) === 1;
    }

    public static function compare(string $a, string $b): int
    {
        return version_compare(self::normalize($a), self::normalize($b));
    }
}
