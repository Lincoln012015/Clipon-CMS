<?php
/**
 * Admin UI Components for Clipon CMS
 */
class AdminUI {
    /** @var bool */
    private static $proStylesLoaded = false;

    /**
     * Build safe attribute string from key-value pairs
     */
    private static function attrs(array $attrs = []): string {
        $parts = [];
        foreach ($attrs as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $parts[] = htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8');
                continue;
            }
            $parts[] = htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"';
        }
        return $parts ? (' ' . implode(' ', $parts)) : '';
    }

    private static function tr(string $key, string $fallback): string {
        if (function_exists('__')) {
            $translated = (string) __($key);
            if ($translated !== '' && $translated !== $key) {
                return $translated;
            }
        }

        return $fallback;
    }

    private static function assetVersion(string $relativePath, int $default = 1): int {
        $normalized = '/' . ltrim($relativePath, '/');
        $fullPath = rtrim((string) C_ROOT, '/') . '/assets' . $normalized;
        if (!is_file($fullPath)) {
            return $default;
        }

        $mtime = @filemtime($fullPath);
        if ($mtime === false || $mtime <= 0) {
            return $default;
        }

        return (int)$mtime;
    }

    private static function ensureProStyles(): void {
        if (self::$proStylesLoaded) {
            return;
        }

        self::$proStylesLoaded = true;
        $version = self::assetVersion('/css/admin-pro.css');
        echo '<link rel="stylesheet" href="' . htmlspecialchars((string) C_ASSETS_URL, ENT_QUOTES, 'UTF-8') . '/css/admin-pro.css?v=' . $version . '">';
    }

    /**
     * Рендеринг аккордеона (details/summary)
     */
    public static function accordionStart($summary, $isOpen = false, array $attrs = [], bool $summaryIsHtml = false) {
        $attrs['class'] = trim(($attrs['class'] ?? '') . ' page-details cms-accordion');
        $summaryValue = (string)$summary;
        $summaryContent = $summaryIsHtml ? $summaryValue : htmlspecialchars($summaryValue, ENT_QUOTES, 'UTF-8');
        if ($isOpen) {
            $attrs['open'] = true;
        }
        ?>
        <details<?= self::attrs($attrs) ?>>
            <summary class="tree-content">
                <span class="dir-toggle"><?= Icons::caretRight() ?></span>
                <?= $summaryContent ?>
            </summary>
            <div class="page-edit-form cms-accordion-content">
        <?php
    }

    public static function accordionEnd() {
        echo '</div></details>';
    }

    /**
     * Рендеринг сетки для чекбоксов (права доступа и т.д.)
     */
    public static function gridCheckStart() {
        echo '<div class="cms-grid-check">';
    }

    public static function formGridStart($columns = 2, array $attrs = []) {
        $columns = max(1, min(4, (int)$columns));
        $attrs['class'] = trim(($attrs['class'] ?? '') . ' form-group-grid');
        $style = trim((string)($attrs['style'] ?? ''));
        $gridStyle = 'grid-template-columns: repeat(' . $columns . ', minmax(0, 1fr));';
        $attrs['style'] = $style !== '' ? rtrim($style, ';') . '; ' . $gridStyle : $gridStyle;
        echo '<div' . self::attrs($attrs) . '>';
    }

    public static function formGridEnd() {
        echo '</div>';
    }

    public static function gridCheckEnd() {
        echo '</div>';
    }

    public static function modalTrigger($modalId, $label, $variant = 'primary', $iconSvg = '', bool $iconSvgTrusted = false) {
        $safeId = htmlspecialchars((string)$modalId, ENT_QUOTES, 'UTF-8');
        $safeLabel = htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8');
        $safeVariant = htmlspecialchars((string)$variant, ENT_QUOTES, 'UTF-8');
        $safeIcon = $iconSvgTrusted
            ? (string)$iconSvg
            : htmlspecialchars((string)$iconSvg, ENT_QUOTES, 'UTF-8');
        ?>
        <button type="button" class="btn btn-<?= $safeVariant ?>" onclick="AdminUI.openModal('<?= $safeId ?>')">
            <?= $safeIcon ?>
            <?= $safeLabel ?>
        </button>
        <?php
    }

    public static function proLockedButton(string $label, array $attrs = []): void {
        self::ensureProStyles();

        $attrs['type'] = $attrs['type'] ?? 'button';
        $attrs['class'] = trim(($attrs['class'] ?? '') . ' pro-locked-btn');
        $attrs['disabled'] = true;
        $attrs['aria-disabled'] = 'true';
        $tooltip = (string)($attrs['data-tooltip'] ?? self::tr('pro_locked_tooltip', 'Available in PRO version'));
        unset($attrs['data-tooltip']);

        echo '<span class="pro-locked-control pro-locked-control--button" tabindex="0" data-tooltip="' . htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') . '">';
        echo '<button' . self::attrs($attrs) . '>';
        echo '<span class="pro-lock-chip" aria-hidden="true">PRO</span>';
        echo '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</button>';
        echo '</span>';
    }

    public static function proLockedSwitch(string $label, bool $isOn = false, array $attrs = []): void {
        self::ensureProStyles();

        $attrs['type'] = $attrs['type'] ?? 'button';
        $attrs['role'] = 'switch';
        $attrs['aria-checked'] = $isOn ? 'true' : 'false';
        $attrs['disabled'] = true;
        $attrs['aria-disabled'] = 'true';
        $attrs['class'] = trim(($attrs['class'] ?? '') . ' pro-locked-switch' . ($isOn ? ' is-on' : ''));
        $tooltip = (string)($attrs['data-tooltip'] ?? self::tr('pro_locked_tooltip', 'Available in PRO version'));
        unset($attrs['data-tooltip']);

        echo '<span class="pro-locked-control pro-locked-control--switch" tabindex="0" data-tooltip="' . htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') . '">';
        echo '<button' . self::attrs($attrs) . '>';
        echo '<span class="pro-locked-switch__track" aria-hidden="true"><span class="pro-locked-switch__thumb"></span></span>';
        echo '<span class="pro-locked-switch__label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</button>';
        echo '</span>';
    }

    public static function proLockedInput(string $value = '', array $attrs = []): void {
        self::ensureProStyles();

        $attrs['type'] = $attrs['type'] ?? 'text';
        $attrs['value'] = $value;
        $attrs['readonly'] = true;
        $attrs['disabled'] = true;
        $attrs['aria-disabled'] = 'true';
        $attrs['class'] = trim(($attrs['class'] ?? '') . ' pro-locked-input');
        $tooltip = (string)($attrs['data-tooltip'] ?? self::tr('pro_locked_tooltip', 'Available in PRO version'));
        unset($attrs['data-tooltip']);

        echo '<span class="pro-locked-control pro-locked-control--input" tabindex="0" data-tooltip="' . htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') . '">';
        echo '<input' . self::attrs($attrs) . ' />';
        echo '</span>';
    }

    public static function proMetric(string $value, array $attrs = []): void {
        self::ensureProStyles();

        $attrs['class'] = trim(($attrs['class'] ?? '') . ' pro-metric pro-metric-locked');
        $attrs['data-tooltip'] = $attrs['data-tooltip'] ?? self::tr('pro_metric_tooltip', 'Metric hidden without PRO license');

        echo '<span' . self::attrs($attrs) . '>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    public static function proBlurStart(array $attrs = []): void {
        self::ensureProStyles();
        $attrs['class'] = trim(($attrs['class'] ?? '') . ' pro-blurred-content');
        echo '<div' . self::attrs($attrs) . '>';
    }

    public static function proBlurEnd(): void {
        echo '</div>';
    }

    public static function proUpgradeBanner(array $options = []): void {
        self::ensureProStyles();

        $title = (string)($options['title'] ?? self::tr('pro_upgrade_title', 'Unlock PRO features'));
        $description = (string)($options['description'] ?? self::tr('pro_upgrade_description', 'Activate your license to access advanced tools and analytics.'));
        $cta = (string)($options['cta'] ?? self::tr('pro_upgrade_cta', 'Upgrade to PRO'));
        $href = (string)($options['href'] ?? 'https://clipon-cms.com/pro');
        $variant = (string)($options['variant'] ?? 'locked');
        $showButton = !isset($options['show_button']) || (bool)$options['show_button'];

        echo '<div class="pro-upgrade-banner pro-upgrade-banner--' . htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') . '">';
        echo '<div class="pro-upgrade-banner__badge">PRO</div>';
        echo '<div class="pro-upgrade-banner__content">';
        echo '<strong class="pro-upgrade-banner__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong>';
        echo '<p class="pro-upgrade-banner__description">' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '</div>';

        if ($showButton) {
            echo '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" class="btn btn-primary pro-upgrade-banner__cta">';
            echo htmlspecialchars($cta, ENT_QUOTES, 'UTF-8');
            echo '</a>';
        }

        echo '</div>';
    }

    /**
     * Render a protection gate for PRO modules
     */
    public static function proGate($moduleId) {
        $state = self::getProGateState((string)$moduleId);
        if ($state['available']) {
            return;
        }

        if ($state['missing']) {
            self::renderMissingModule((string)$moduleId);
            return;
        }

        if (!empty($state['cms_incompatible'])) {
            self::renderCmsIncompatibleModule((string)$moduleId, (string)($state['min_cms_version'] ?? '0.0.0'));
            return;
        }

        self::renderLockedModule((string)$moduleId);
    }

    public static function getProGateState(string $moduleId): array {
        if (!class_exists('ModuleManager')) {
            return [
                'module_id' => $moduleId,
                'available' => false,
                'missing' => false,
                'locked' => true,
            ];
        }

        $isAvailable = ModuleManager::isProAvailable($moduleId);
        $isLicensed = ModuleManager::isProLicensed($moduleId);
        $allModules = ModuleManager::getModules();
        $moduleInfo = $allModules[$moduleId] ?? [];
        $isMissing = ModuleManager::isModuleMissing($moduleId);
        $isCmsCompatible = !isset($moduleInfo['cms_compatible']) || (bool)$moduleInfo['cms_compatible'];
        $minCmsVersion = (string)($moduleInfo['min_cms_version'] ?? '0.0.0');

        return [
            'module_id' => $moduleId,
            'available' => !$isMissing && $isCmsCompatible && $isAvailable,
            'licensed' => $isLicensed,
            'missing' => $isMissing,
            'cms_incompatible' => !$isCmsCompatible,
            'min_cms_version' => $minCmsVersion,
            'locked' => !$isMissing && $isCmsCompatible && !$isAvailable,
        ];
    }

    public static function proGateStart(string $moduleId, array $attrs = []): array {
        self::ensureProStyles();

        $state = self::getProGateState($moduleId);
        $state['overlay_opened'] = false;
        $isProtected = !$state['available'];
        $mode = (string)($attrs['mode'] ?? 'teaser');
        $renderBanner = !isset($attrs['render_banner']) || (bool)$attrs['render_banner'];
        $bannerOptions = (isset($attrs['banner_options']) && is_array($attrs['banner_options'])) ? $attrs['banner_options'] : [];

        if (!$isProtected) {
            return $state;
        }

        if ($mode !== 'overlay') {
            if ($renderBanner) {
                if ($state['missing']) {
                    self::renderMissingModule($moduleId);
                } elseif (!empty($state['cms_incompatible'])) {
                    self::renderCmsIncompatibleModule($moduleId, (string)($state['min_cms_version'] ?? '0.0.0'));
                } else {
                    $bannerOptions['variant'] = $state['licensed'] ? 'missing' : 'locked';
                    if ($state['licensed'] && empty($bannerOptions['title'])) {
                        $bannerOptions['title'] = self::tr('pro_missing_title', 'Module files are missing');
                        $bannerOptions['description'] = self::tr('pro_missing_description', 'Upload the module package to continue.');
                        $bannerOptions['cta'] = self::tr('pro_missing_cta', 'How to install PRO module');
                    }
                    self::proUpgradeBanner($bannerOptions);
                }
            }

            return $state;
        }

        $containerClass = trim('pro-stub-container ' . (string)($attrs['container_class'] ?? ''));
        $contentClass = trim('pro-blurred-content ' . (string)($attrs['content_class'] ?? ''));
        $overlayClass = trim('pro-stub-overlay ' . (string)($attrs['overlay_class'] ?? ''));
        $cardClass = trim('pro-stub-card ' . (string)($attrs['card_class'] ?? ''));

        echo '<div class="' . htmlspecialchars($containerClass, ENT_QUOTES, 'UTF-8') . '">';
        echo '<div class="' . htmlspecialchars($overlayClass, ENT_QUOTES, 'UTF-8') . '">';
        echo '<div class="' . htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8') . '">';

        if ($state['missing']) {
            self::renderMissingModule($moduleId);
        } elseif (!empty($state['cms_incompatible'])) {
            self::renderCmsIncompatibleModule($moduleId, (string)($state['min_cms_version'] ?? '0.0.0'));
        } else {
            if (!empty($bannerOptions)) {
                $bannerOptions['variant'] = $bannerOptions['variant'] ?? 'locked';
                self::proUpgradeBanner($bannerOptions);
            } else {
                self::renderLockedModule($moduleId);
            }
        }

        echo '</div>';
        echo '</div>';
        echo '<div class="' . htmlspecialchars($contentClass, ENT_QUOTES, 'UTF-8') . '">';

        $state['overlay_opened'] = true;

        return $state;
    }

    public static function proGateEnd(array $state = []): void {
        if (empty($state['overlay_opened'])) {
            return;
        }

        echo '</div></div>';
    }

    private static function renderMissingModule($moduleId) {
        $path = '/modules/' . $moduleId . '/';

        $pathLabel = '';

        if ($moduleId === 'pro_analytics') {
            $title = self::tr('pro_analytics_missing_title', 'Pro Analytics module is not installed');
            $description = self::tr('pro_analytics_missing_description', 'Your license includes Pro Analytics, but the module files are not on the server yet.');
            $pathLabel = self::tr('pro_analytics_missing_path', 'Expected path on the server:');
        } elseif ($moduleId === 'pro_users') {
            $title = self::tr('pro_users_missing_title', 'Pro Users module is not installed');
            $description = self::tr('pro_users_missing_description', 'Your license is active, but moderator management files are missing on the server. Upload the pro_users package to manage moderators and permissions.');
            $pathLabel = self::tr('pro_users_missing_path', 'Expected path on the server:');
        } else {
            $title = self::tr('pro_missing_title', 'Module files are missing');
            $description = self::tr('pro_missing_description', 'Upload the module package to continue.');
        }

        if ($pathLabel !== '') {
            $description .= ' ' . $pathLabel;
        }

        self::proUpgradeBanner([
            'variant' => 'missing',
            'title' => $title,
            'description' => $description . ' ' . $path,
            'cta' => self::tr('pro_missing_cta', 'How to install PRO module'),
            'href' => 'https://clipon-cms.com/pro'
        ]);
    }

    private static function renderLockedModule($moduleId) {
        self::proUpgradeBanner([
            'variant' => 'locked',
            'title' => self::tr('pro_locked_title', 'PRO module is locked'),
            'description' => self::tr('pro_locked_description', 'This section requires an active PRO license.'),
            'cta' => self::tr('pro_upgrade_cta', 'Upgrade to PRO'),
            'href' => 'https://clipon-cms.com/pro'
        ]);
    }

    private static function renderCmsIncompatibleModule(string $moduleId, string $minCmsVersion): void {
        self::proUpgradeBanner([
            'variant' => 'locked',
            'title' => self::tr('pro_cms_incompatible_title', 'CMS update required'),
            'description' => self::tr('pro_cms_incompatible_description', 'This module requires a newer CMS version.') . ' >= ' . $minCmsVersion,
            'cta' => self::tr('pro_cms_incompatible_cta', 'Update CMS'),
            'href' => 'https://clipon-cms.com/dist/'
        ]);
    }
}
