<?php

/**
 * Admin UI localization component.
 *
 * This file is intentionally limited to admin/system interface language only:
 * - loads dictionaries from core/lang/*.php
 * - stores selected admin language in session (admin_lang)
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
        return true;
    }

    public static function detect() {
        $session = new Session();
        $uiSupported = self::getUiSupportedLangs();
        $uiPrimary = $uiSupported[0] ?? (Settings::load()['language'] ?? 'en');

        $candidateUi = null;
        if ($session->has('admin_lang') && in_array($session->get('admin_lang'), $uiSupported, true)) {
            $candidateUi = $session->get('admin_lang');
        } else {
            $candidateUi = $uiPrimary;
        }

        self::$lang = $candidateUi;
        $session->set('admin_lang', self::$lang);

        $file = C_CORE_DIR . '/lang/' . self::$lang . '.php';
        self::$translations = [];
        if (file_exists($file)) {
            self::$translations = require $file;
        }
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
