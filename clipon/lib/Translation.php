<?php

/**
 * Admin UI localization component.
 *
 * This file is intentionally limited to admin/system interface language only:
 * - loads dictionaries from core/lang/*.php
 * - stores selected admin language in the user profile, session and persistent cookie
 *
 * It does NOT handle public site language routing, URL building, or hreflang links.
 * Public site multilingual logic is implemented in SiteLanguage.php.
 */
class Translation {
    private static $lang = 'uk';
    private static $translations = [];
    private static $supportedLangs = null; // will be detected dynamically

    private static function getUiSupportedLangs() {
        return self::detectSupportedLangs();
    }

    private static function detectSupportedLangs() {
        if (self::$supportedLangs !== null) {
            return self::$supportedLangs;
        }

        $dir = C_CORE_DIR . '/lang';
        $langs = [];
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.php') as $file) {
                $code = basename($file, '.php');
                if ($code !== '') {
                    $langs[] = $code;
                }
            }
        }

        if (empty($langs)) {
            $langs = ['uk'];
        }

        self::$supportedLangs = array_values(array_unique($langs));
        return self::$supportedLangs;
    }

    public static function init() {
        self::detect();
    }

    public static function setInterfaceLang($lang) {
        $uiSupported = self::getUiSupportedLangs();
        if (!in_array($lang, $uiSupported, true)) {
            return false;
        }

        self::$lang = $lang;
        $session = new Session();
        $session->set('admin_lang', $lang);
        if ($session->has('user')) {
            self::saveUserLanguage($session->get('user'), $lang);
        } else {
            $session->set('admin_lang_explicit', true);
        }
        self::rememberLanguage($lang);
        self::detect();
        return true;
    }

    public static function detect() {
        $session = new Session();
        $uiSupported = self::getUiSupportedLangs();
        $configured = Settings::load()['language'] ?? 'en';
        $uiPrimary = in_array($configured, $uiSupported, true)
            ? $configured : (in_array('en', $uiSupported, true) ? 'en' : $uiSupported[0]);

        $profileLang = null;
        if ($session->has('user')) {
            $user = (new UserRepository())->findByLogin($session->get('user'));
            $profileLang = $user['interface_language'] ?? null;
        }
        self::$lang = $uiPrimary;
        foreach ([$profileLang, $session->get('admin_lang'), $_COOKIE['clipon_admin_lang'] ?? null] as $candidate) {
            if (in_array($candidate, $uiSupported, true)) {
                self::$lang = $candidate;
                break;
            }
        }
        $session->set('admin_lang', self::$lang);

        $file = C_CORE_DIR . '/lang/' . self::$lang . '.php';
        self::$translations = [];
        if (file_exists($file)) {
            self::$translations = require $file;
        }
    }

    // Called after authentication so an explicit choice on the login page wins.
    public static function onLogin(): void {
        $session = new Session();
        if ($session->get('admin_lang_explicit')) {
            self::saveUserLanguage($session->get('user'), self::$lang);
        }
        $session->remove('admin_lang_explicit');
        self::detect();
        self::saveUserLanguage($session->get('user'), self::$lang);
        self::rememberLanguage(self::$lang);
    }

    private static function saveUserLanguage(string $username, string $lang): void {
        $repository = new UserRepository();
        $user = $repository->findByLogin($username);
        if ($user && ($user['interface_language'] ?? null) !== $lang) {
            unset($user['username']);
            $user['interface_language'] = $lang;
            $repository->save($username, $user);
        }
    }

    private static function rememberLanguage(string $lang): void {
        if (!headers_sent()) {
            setcookie('clipon_admin_lang', $lang, [
                'expires' => time() + 365 * 86400,
                'path' => parse_url(C_ADMIN_URL, PHP_URL_PATH) ?: '/',
                'secure' => RequestSecurity::isSecure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE['clipon_admin_lang'] = $lang;
    }

    public static function get($key) {
        return self::$translations[$key] ?? $key;
    }

    public static function getLang() {
        return self::$lang;
    }

    public static function getSupportedLangs() {
        return self::getUiSupportedLangs();
    }

    public static function getInterfaceLangs() {
        return self::getUiSupportedLangs();
    }
}

function __($key) {
    return Translation::get($key);
}
