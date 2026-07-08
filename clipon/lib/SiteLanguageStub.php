<?php

class SiteLanguageStub {

    public static function init() {
        // Stub: do nothing when multilang is not active
    }

    public static function getEnabledSiteLangs() {
        // Return only the primary language when Multilang PRO is not active
        $langs = Settings::getLanguages();
        if (!empty($langs) && isset($langs[0]['code'])) {
            return [$langs[0]['code']];
        }
        return ['en'];
    }

    public static function getCurrent() {
        $langs = \Settings::getLanguages();
        if (!empty($langs) && isset($langs[0]['code'])) {
            return $langs[0]['code'];
        }
        return 'en';
    }

    public static function setCurrent($lang) {
        // Stub: do nothing
    }

    public static function url($path, $lang = null) {
        // Stub: return path with no prefixes
        return $path;
    }

    public static function resource_url($slug, $type = 'page', $lang = null) {
        // Stub: return just url(RouteMap::getUrl(...))
        return self::url(\RouteMap::getUrl($slug, null, $type));
    }

    public static function getLanguageLinks($slug = null, $type = 'page', $is_home = false) {
        // Stub: return empty array
        return [];
    }
    public static function resource_alternates($slug = null, $type = 'page', $is_home = false) {
        return [];
    }
    public static function getActiveAlternates($slug = null, $type = 'page') {
        return [];
    }
}
