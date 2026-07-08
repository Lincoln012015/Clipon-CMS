<?php

require_once __DIR__ . '/Settings.php';

class CookieConsentPolicy {
    public const CONSENT_COOKIE = 'clipon_cookie_consent';
    public const MODE_BASIC = 'privacy_basic';
    public const MODE_FULL = 'full_with_consent';

    private array $settings;
    private ?Request $request;

    public function __construct(?array $settings = null, ?Request $request = null) {
        $this->settings = $settings ?? Settings::load();
        $this->request = $request;
    }

    public function configuredMode(): string {
        $mode = (string)($this->settings['analytics_mode'] ?? self::MODE_BASIC);
        return $mode === self::MODE_FULL ? self::MODE_FULL : self::MODE_BASIC;
    }

    public function isBannerEnabled(): bool {
        return !empty($this->settings['cookie_banner_enabled']);
    }

    public function consentValue(): string {
        if (!$this->request) return '';
        $value = (string)$this->request->cookie(self::CONSENT_COOKIE, '');
        return in_array($value, ['accepted', 'rejected'], true) ? $value : '';
    }

    public function canUseFullAnalytics(): bool {
        return $this->isBannerEnabled()
            && $this->configuredMode() === self::MODE_FULL
            && $this->consentValue() === 'accepted';
    }

    public function shouldRenderBanner(): bool {
        return $this->isBannerEnabled() && $this->configuredMode() === self::MODE_FULL && $this->consentValue() === '';
    }

    public function analyticsModeForRequest(): string {
        return $this->canUseFullAnalytics() ? self::MODE_FULL : self::MODE_BASIC;
    }

    public static function newPageviewId(): string {
        try {
            return bin2hex(random_bytes(16));
        } catch (Exception $e) {
            return md5(uniqid('pv', true));
        }
    }

    public static function signPageviewId(string $pageviewId): string {
        return hash_hmac('sha256', $pageviewId, self::signingSecret());
    }

    public static function verifyPageviewSignature(string $pageviewId, string $signature): bool {
        if (!preg_match('/^[a-f0-9]{32}$/', $pageviewId) || !preg_match('/^[a-f0-9]{64}$/', $signature)) {
            return false;
        }

        return hash_equals(self::signPageviewId($pageviewId), $signature);
    }

    private static function signingSecret(): string {
        $settings = Settings::load();
        $seed = (string)($settings['site_config_version'] ?? '1.0.0') . '|' . C_CONFIG_PATH . '|' . C_ROOT;
        return hash('sha256', $seed);
    }
}
