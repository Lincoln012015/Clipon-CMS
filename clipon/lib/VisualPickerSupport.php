<?php

require_once __DIR__ . '/MarkupFileResolver.php';
require_once __DIR__ . '/MarkupHtmlSanitizer.php';
require_once __DIR__ . '/MarkupInjector.php';
require_once __DIR__ . '/MarkupPhpBlockProtector.php';
require_once __DIR__ . '/MarkupShortcodePreview.php';

class VisualPickerSupport {
    public static function normalizeFileName(string $file): string {
        return MarkupFileResolver::normalizeFileName($file);
    }

    public static function resolveEditableFile(string $file): ?string {
        return MarkupFileResolver::resolveEditableFile($file);
    }

    public static function listEditableTemplates(): array {
        return MarkupFileResolver::listEditableTemplates();
    }

    public static function resolveTemplateFile(string $file): ?string {
        return MarkupFileResolver::resolveTemplateFile($file);
    }

    public static function sanitizeMarkupHtml(string $html): string {
        return MarkupHtmlSanitizer::sanitizeMarkupHtml($html);
    }

    public static function renderMarkupShortcodePreviews(string $html): string {
        return MarkupShortcodePreview::renderMarkupShortcodePreviews($html);
    }

    public static function restoreMarkupShortcodePreviews(string $html): string {
        return MarkupShortcodePreview::restoreMarkupShortcodePreviews($html);
    }

    public static function protectPhpBlocks(string $html): array {
        return MarkupPhpBlockProtector::protect($html);
    }

    public static function restorePhpBlocks(string $html, array $blocks): array {
        return MarkupPhpBlockProtector::restore($html, $blocks);
    }

    public static function calculateBaseHref(string $scriptName, string $file): string {
        return MarkupInjector::calculateBaseHref($scriptName, $file);
    }

    public static function calculateTemplateBaseHref(string $scriptName, string $file): string {
        return MarkupInjector::calculateTemplateBaseHref($scriptName, $file);
    }

    public static function injectBaseAndScript(string $content, string $baseHref, string $configId, string $globalName, array $config, string $scriptUrl, ?string $styleUrl = null): string {
        return MarkupInjector::injectBaseAndScript($content, $baseHref, $configId, $globalName, $config, $scriptUrl, $styleUrl);
    }
}
