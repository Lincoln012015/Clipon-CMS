<?php

class Icons {
    private static function wrap($svg, $size = 20, $class = '') {
        $classAttr = $class ? ' class="' . $class . '"' : '';
        return str_replace(
            '<svg ', 
            '<svg width="' . $size . '" height="' . $size . '" ' . $classAttr . ' ', 
            $svg
        );
    }

    public static function dashboard($size = 20) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><rect x="40" y="40" width="72" height="72" rx="8"/><rect x="144" y="40" width="72" height="72" rx="8"/><rect x="40" y="144" width="72" height="72" rx="8"/><rect x="144" y="144" width="72" height="72" rx="8"/></svg>', $size);
    }

    public static function pages($size = 20) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><path d="M48,32H160l48,48V224a8,8,0,0,1-8,8H48a8,8,0,0,1-8-8V40A8,8,0,0,1,48,32Z"/><polyline points="152 32 152 88 208 88"/></svg>', $size);
    }

    public static function blog($size = 20) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><path d="M112,48h96a8,8,0,0,1,8,8V200a8,8,0,0,1-8,8H112"/><path d="M48,48h64a0,0,0,0,1,0,0V208a0,0,0,0,1,0,0H48a8,8,0,0,1-8-8V56A8,8,0,0,1,48,48Z"/><line x1="144" y1="96" x2="184" y2="96"/><line x1="144" y1="128" x2="184" y2="128"/><line x1="144" y1="160" x2="184" y2="160"/></svg>', $size);
    }

    public static function media($size = 20) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><rect x="32" y="48" width="192" height="160" rx="8"/><circle cx="92" cy="92" r="12"/><polyline points="32 168 88 112 144 168 176 136 224 184"/></svg>', $size);
    }

    public static function image($size = 20) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><rect x="32" y="48" width="192" height="160" rx="8"/><circle cx="96" cy="104" r="16"/><polyline points="32 176 88 120 144 176 176 144 224 192"/></svg>', $size);
    }

    public static function users($size = 20) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><path d="M231.9,212a120.7,120.7,0,0,0-67.1-54.2,72,72,0,1,0-73.6,0A120.7,120.7,0,0,0,24.1,212a7.7,7.7,0,0,0,0,8,7.8,7.8,0,0,0,7.9,4H224a7.8,7.8,0,0,0,7.9-4A7.7,7.7,0,0,0,231.9,212Z"/></svg>', $size);
    }

    public static function settings($size = 20) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><circle cx="128" cy="128" r="40"/><path d="M104,24l5.3,19.3a86,86,0,0,0,37.4,0L152,24h16l14.8,24.1a86,86,0,0,0,26.4,26.4L232,88v16l-19.3,5.3a86,86,0,0,0,0,37.4L232,152v16l-24.1,14.8a86,86,0,0,0-26.4,26.4L168,232H152l-5.3-19.3a86,86,0,0,0-37.4,0L104,232H88L73.2,207.9a86,86,0,0,0-26.4-26.4L24,168V152l19.3-5.3a86,86,0,0,0,0-37.4L24,104V88L48.1,73.2a86,86,0,0,0,26.4-26.4L88,24Z"/></svg>', $size);
    }

    public static function redirects($size = 20) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><polyline points="88 56 56 88 88 120"/><path d="M200,88H56"/><polyline points="168 136 200 168 168 200"/><path d="M56,168H200"/></svg>', $size);
    }

    public static function analytics($size = 20) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><rect x="40" y="128" width="32" height="72" rx="4"/><rect x="112" y="56" width="32" height="144" rx="4"/><rect x="184" y="104" width="32" height="96" rx="4"/><line x1="24" y1="200" x2="232" y2="200"/></svg>', $size);
    }

    public static function logout($size = 20) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><path d="M112,216H48a8,8,0,0,1-8-8V48a8,8,0,0,1,8-8h64"/><polyline points="160 80 208 128 160 176"/><line x1="88" y1="128" x2="208" y2="128"/></svg>', $size);
    }

    public static function folder($size = 20) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><path d="M32,80V192a8,8,0,0,0,8,8H216a8,8,0,0,0,8-8V96a8,8,0,0,0-8-8H120L96,56H40a8,8,0,0,0-8,8Z"/></svg>', $size);
    }

    public static function folderPlus($size = 20) {
        // Folder with a small plus badge in the corner
        return self::wrap(
            '<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round">
                <path d="M32,80V192a8,8,0,0,0,8,8H216a8,8,0,0,0,8-8V96a8,8,0,0,0-8-8H120L96,56H40a8,8,0,0,0-8,8Z"/>
                <circle cx="188" cy="92" r="20"/>
                <line x1="188" y1="82" x2="188" y2="102"/>
                <line x1="178" y1="92" x2="198" y2="92"/>
            </svg>',
            $size
        );
    }

    public static function fileText($size = 20) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><path d="M48,32H160l48,48V224a8,8,0,0,1-8,8H48a8,8,0,0,1-8-8V40A8,8,0,0,1,48,32Z"/><polyline points="152 32 152 88 208 88"/><line x1="80" y1="128" x2="176" y2="128"/><line x1="80" y1="160" x2="176" y2="160"/><line x1="80" y1="192" x2="136" y2="192"/></svg>', $size);
    }

    public static function eye($size = 20) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><path d="M128,56C48,56,16,128,16,128s32,72,112,72,112-72,112-72S208,56,128,56Z"/><circle cx="128" cy="128" r="40"/></svg>', $size);
    }

    public static function eyeClosed($size = 20) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><path d="M128,56C48,56,16,128,16,128s32,72,112,72,112-72,112-72S208,56,128,56Z"/><circle cx="128" cy="128" r="40"/><line x1="48" y1="48" x2="208" y2="208"/></svg>', $size);
    }

    public static function pencil($size = 20) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><path d="M92.7,216H48a8,8,0,0,1-8-8V163.3a7.9,7.9,0,0,1,2.3-5.6l120-120a8,8,0,0,1,11.4,0l44.6,44.6a8,8,0,0,1,0,11.4l-120,120A7.9,7.9,0,0,1,92.7,216Z"/><line x1="136" y1="64" x2="192" y2="120"/></svg>', $size);
    }

    public static function trash($size = 20) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><line x1="40" y1="56" x2="216" y2="56"/><path d="M176,56V40a16,16,0,0,1,16-16H64A16,16,0,0,1,48,40V56"/><path d="M192,56V208a16,16,0,0,1-16,16H80a16,16,0,0,1-16-16V56"/></svg>', $size);
    }

    public static function caretDown($size = 12) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="24" stroke-linecap="round" stroke-linejoin="round"><polyline points="208 96 128 176 48 96"/></svg>', $size);
    }

    public static function caretRight($size = 12) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="24" stroke-linecap="round" stroke-linejoin="round"><polyline points="96 48 176 128 96 208"/></svg>', $size);
    }

    public static function dotsSixVertical($size = 16) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="currentColor"><circle cx="92" cy="60" r="12"/><circle cx="164" cy="60" r="12"/><circle cx="92" cy="128" r="12"/><circle cx="164" cy="128" r="12"/><circle cx="92" cy="196" r="12"/><circle cx="164" cy="196" r="12"/></svg>', $size);
    }

    public static function star($filled = false, $size = 18) {
        $fill = $filled ? 'fill="currentColor"' : 'fill="none"';
        return self::wrap('<svg viewBox="0 0 256 256" ' . $fill . ' stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><polygon points="128 16 168 96 248 108 190 164 204 244 128 206 52 244 66 164 8 108 88 96 128 16"/></svg>', $size);
    }

    public static function lock($size = 18) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><rect x="40" y="104" width="176" height="112" rx="8"/><path d="M92,104V64a36,36,0,0,1,72,0v40"/></svg>', $size);
    }

    public static function info($size = 18) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><circle cx="128" cy="128" r="96"/><line x1="128" y1="120" x2="128" y2="176"/><circle cx="128" cy="80" r="12" fill="currentColor" stroke="none"/></svg>', $size);
    }

    public static function warning($size = 18) {
        // Triangle with exclamation mark
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><path d="M232 196L136 40a24 24 0 0 0-40 0L24 196a24 24 0 0 0 20 36h168a24 24 0 0 0 20-36z"/><line x1="128" y1="92" x2="128" y2="140"/><circle cx="128" cy="172" r="8" fill="currentColor" stroke="none"/></svg>', $size);
    }

    public static function copy($size = 18) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><rect x="40" y="72" width="112" height="112" rx="8"/><path d="M104,72V48a8,8,0,0,1,8-8H208a8,8,0,0,1,8,8V144a8,8,0,0,1-8,8H184"/></svg>', $size);
    }

    public static function duplicate($size = 18) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><rect x="48" y="48" width="72" height="72" rx="8"/><rect x="136" y="136" width="72" height="72" rx="8"/><path d="M120 120L136 136"/></svg>', $size);
    }

    public static function check($size = 16) {
        // Simple checkmark (tick) icon
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="20" stroke-linecap="round" stroke-linejoin="round"><polyline points="216 72 104 184 40 120"/></svg>', $size);
    }

    public static function x($size = 18) {
        // simple cross icon, stroke style
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><line x1="48" y1="48" x2="208" y2="208"/><line x1="208" y1="48" x2="48" y2="208"/></svg>', $size);
    }

    public static function toggle($active = true, $size = 20) {
        // simple switch/tumbler icon: pill shape with a circle that moves left/right
        $circleX = $active ? 16 : 8;
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
             . '<rect x="1" y="6" width="22" height="12" rx="6" />'
             . '<circle cx="' . $circleX . '" cy="12" r="4" fill="currentColor" stroke="none"/>'
             . '</svg>';
        return self::wrap($svg, $size);
    }

    // Generic action icons used across admin UI
    public static function plus($size = 18) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><circle cx="128" cy="128" r="96"/><line x1="128" y1="88" x2="128" y2="168"/><line x1="88" y1="128" x2="168" y2="128"/></svg>', $size);
    }

    public static function save($size = 18) {
        return self::wrap('<svg viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"><path d="M48,40H176l32,32V216a8,8,0,0,1-8,8H48a8,8,0,0,1-8-8V48A8,8,0,0,1,48,40Z"/><polyline points="96 80 160 80 160 136"/><rect x="96" y="136" width="64" height="56" rx="8"/></svg>', $size);
    }

    public static function delete($size = 18) {
        // Alias to existing trash icon for semantic clarity
        return self::trash($size);
    }

    public static function drag($size = 18) {
        // Alias to dotsSixVertical for semantic clarity
        return self::dotsSixVertical($size);
    }
}