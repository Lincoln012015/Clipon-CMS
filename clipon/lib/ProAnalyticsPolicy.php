<?php

class ProAnalyticsPolicy {
    public const MODULE_ID = 'pro_analytics';

    public static function isLicensed(): bool {
        return class_exists('ModuleManager') && ModuleManager::isProLicensed(self::MODULE_ID);
    }

    public static function isAvailable(): bool {
        return class_exists('ModuleManager') && ModuleManager::isProAvailable(self::MODULE_ID);
    }

    public static function isMissingFiles(): bool {
        return class_exists('ModuleManager') && ModuleManager::isModuleMissing(self::MODULE_ID);
    }
}
