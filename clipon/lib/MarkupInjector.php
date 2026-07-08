<?php

require_once __DIR__ . '/MarkupFileResolver.php';

class MarkupInjector {
    public static function calculateBaseHref(string $scriptName, string $file): string {
        $cmsBase = self::cmsBaseFromScriptName($scriptName);
        $fileDir = dirname(MarkupFileResolver::normalizeFileName($file));
        return rtrim($cmsBase . '/' . ($fileDir === '.' ? '' : $fileDir), '/') . '/';
    }

    public static function calculateTemplateBaseHref(string $scriptName, string $file): string {
        return rtrim(self::cmsBaseFromScriptName($scriptName), '/') . '/';
    }

    public static function injectBaseAndScript(string $content, string $baseHref, string $configId, string $globalName, array $config, string $scriptUrl, ?string $styleUrl = null): string {
        $baseTag = '<base href="' . htmlspecialchars($baseHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
        $styleTag = $styleUrl
            ? '<link id="clipon-markup-picker-style" rel="stylesheet" href="' . htmlspecialchars($styleUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            : '';
        if (preg_match('/<head[^>]*>/i', $content, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);
            $content = substr($content, 0, $pos) . $baseTag . $styleTag . substr($content, $pos);
        } else {
            $content = $baseTag . $styleTag . $content;
        }

        $configTag = '<script id="' . htmlspecialchars($configId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">window.' . $globalName . ' = ' .
            json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) .
            ';</script>';
        $scriptTag = $configTag . '<script src="' . htmlspecialchars($scriptUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></script></body>';

        $replaced = 0;
        $content = preg_replace('/<\/body>/i', $scriptTag, $content, 1, $replaced) ?? $content;
        if ($replaced === 0) {
            $content .= $configTag . '<script src="' . htmlspecialchars($scriptUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></script>';
        }

        return $content;
    }

    private static function cmsBaseFromScriptName(string $scriptName): string {
        $cmsBase = $scriptName;
        foreach (['/clipon/', '/core/clipon/'] as $needle) {
            if (($pos = strpos($scriptName, $needle)) !== false) {
                $cmsBase = substr($scriptName, 0, $pos);
                break;
            }
        }

        return $cmsBase;
    }
}
