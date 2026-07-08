<?php
/**
 * Language Switcher - Core Fallback
 * 
 * This fallback is used when the multilang module is not active or not properly loaded.
 * The multilang module overrides this via Hooks::applyFilters('multilang:partial_path')
 * 
 * This ensures $currentEditLang is always initialized for template rendering.
 */

// Initialize $currentEditLang even if multilang module is not active
if (!isset($currentEditLang)) {
    $configuredLangs = Settings::getLanguages();
    $activeLangs = array_values(array_filter($configuredLangs, fn($l) => !empty($l['enabled'])));
    if (empty($activeLangs)) {
        $activeLangs = [['code' => (Settings::load()['language'] ?? 'en'), 'name' => 'Default']];
    }
    $activeLangCodes = array_values(array_map(fn($l) => $l['code'], $activeLangs));
    $requestedEditLang = $request->string('edit_lang');
    $currentEditLang = in_array($requestedEditLang, $activeLangCodes, true) ? $requestedEditLang : ($activeLangs[0]['code'] ?? (Settings::load()['language'] ?? 'en'));
}
?>
