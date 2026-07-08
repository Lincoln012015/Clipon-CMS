<?php

/**
 * Localized status messages and payloads for PRO analytics (core + module).
 */
class ProAnalyticsMessages {
    private static function tr(string $key, string $fallback): string {
        if (function_exists('__')) {
            $value = (string)__($key);
            return $value !== '' ? $value : $fallback;
        }

        if (class_exists('Translation')) {
            $value = (string)Translation::get($key);
            return $value !== '' ? $value : $fallback;
        }

        return $fallback;
    }

    public static function statusMessage(string $mode): string {
        switch ($mode) {
            case 'install_required':
                return self::tr('pro_analytics_status_missing_files', 'Your license is active, but the Pro Analytics module files are missing.');
            case 'licensed_unavailable':
                return self::tr('pro_analytics_status_unavailable', 'Pro Analytics is temporarily unavailable.');
            case 'mock':
            default:
                return self::tr('pro_analytics_status_locked', 'Advanced analytics is available only with Clipon Pro.');
        }
    }

    public static function statusMessageForFlags(bool $isMissingFiles, bool $isLicensed, bool $isAvailable): string {
        if ($isMissingFiles) {
            return self::statusMessage('install_required');
        }

        if ($isLicensed && $isAvailable) {
            return '';
        }

        if ($isLicensed) {
            return self::statusMessage('licensed_unavailable');
        }

        return self::statusMessage('mock');
    }

    /**
     * @return array<string,mixed>
     */
    public static function emptyStatsShell(): array {
        return [
            'total_hits' => 0,
            'total_uniques' => 0,
            'total_sessions' => 0,
            'daily' => [],
            'pages' => [],
            'referrers' => [],
            'internal_referrers' => [],
            'traffic_sources' => [
                'direct' => 0,
                'external' => [],
                'campaign' => [],
            ],
            'devices' => [],
            'languages' => [],
            'countries' => [],
            'entry_pages' => [],
            'exit_pages' => [],
            'time_on_page' => [],
            'utm' => [],
            'events' => [],
            'bounce_count' => 0,
            'conversions' => [
                'total' => 0,
                'pages' => [],
                'types' => [],
                'recent' => []
            ],
            'funnels' => [
                'completed' => [],
                'recent' => []
            ],
            'attribution' => [
                'first_touch' => [],
                'last_touch' => [],
                'recent' => []
            ],
        ];
    }

    /**
     * Stats payload when license includes pro_analytics but module files are not on disk.
     *
     * @return array<string,mixed>
     */
    public static function missingModulePayload(): array {
        return array_merge(self::emptyStatsShell(), [
            'mode' => 'install_required',
            'is_missing_module' => true,
            'stub_message' => self::statusMessage('install_required'),
        ]);
    }

    public static function moduleInstallPath(): string {
        return '/modules/pro_analytics/';
    }
}
