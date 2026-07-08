<?php

if (!function_exists('pageTreeHtmlAttr')) {
    function pageTreeHtmlAttr($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('pageTreeJsCall')) {
    function pageTreeJsCall(string $functionName, array $args): string {
        $encodedArgs = array_map(static function ($arg): string {
            return json_encode((string)$arg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        }, $args);

        return $functionName . '(' . implode(', ', $encodedArgs) . ')';
    }
}

if (!function_exists('renderDirTemplates')) {
    function renderDirTemplates(array $dirs): void {
        foreach ($dirs as $dir) {
            $pagesList = [];
            getAllPagesInDir($dir, $pagesList);
            $pagesStr = implode(', ', $pagesList);

            $dirId = (string)($dir['id'] ?? '');
            $dirName = (string)($dir['name'] ?? '');
            $dirParent = (string)($dir['parent'] ?? '');

            echo '<li class="tree-item" data-id="' . pageTreeHtmlAttr($dirId) . '" data-type="dir">';
            echo '<details open>';
            echo '<summary class="tree-content">';
            echo '<span class="drag-handle">' . Icons::dotsSixVertical() . '</span>';
            echo '<span class="dir-toggle">' . Icons::caretRight() . '</span>';
            echo '<span class="icon">' . Icons::folder() . '</span>';
            echo '<span class="title">' . htmlspecialchars($dirName) . '</span>';
            echo '<div class="actions">';
            if (hasPermission('edit_pages')) {
                echo sprintf(
                    '<button onclick="%s" class="icon-btn edit-btn">%s</button>',
                    pageTreeHtmlAttr('event.preventDefault(); ' . pageTreeJsCall('openDirModal', ['edit_dir', $dirId, $dirName, $dirParent])),
                    getEditIcon()
                );
            }
            if (hasPermission('delete_pages')) {
                echo sprintf(
                    '<button onclick="%s" class="icon-btn delete-btn">%s</button>',
                    pageTreeHtmlAttr(pageTreeJsCall('deleteDir', [$dirId, $pagesStr])),
                    getDeleteIcon()
                );
            }
            echo '</div>';
            echo '</summary>';
            echo '</details>';
            echo '</li>';

            if (!empty($dir['children'])) {
                renderDirTemplates($dir['children']);
            }
        }
    }
}

if (!function_exists('renderPageTemplate')) {
    function renderPageTemplate(array $page): void {
        global $conversionTypes, $currentEditLang, $request;

        $pageSlug = (string)($page['slug'] ?? '');

        echo '<li class="tree-item" data-id="' . pageTreeHtmlAttr($pageSlug) . '" data-type="page">';
        echo '<details class="page-details">';
        echo '<summary class="tree-content">';
        echo '<span class="drag-handle">' . Icons::dotsSixVertical() . '</span>';
        echo '<span class="icon" style="margin-left: 24px;">' . Icons::fileText() . '</span>';

        $activeLangs = array_values(array_filter(Settings::getLanguages(), fn($l) => !empty($l['enabled'])));
        $primaryLang = (string)($activeLangs[0]['code'] ?? (Settings::load()['language'] ?? 'en'));
        $currentAdminLang = is_string($currentEditLang ?? null) && $currentEditLang !== ''
            ? $currentEditLang
            : $primaryLang;

        $displayTitle = $page['title'] ?? $pageSlug;
        $adminLang = $currentAdminLang;
        if (isset($page['locales'][$adminLang]['title']) && $page['locales'][$adminLang]['title'] !== '') {
            $displayTitle = $page['locales'][$adminLang]['title'];
        }

        echo '<span class="title">' . htmlspecialchars((string)$displayTitle) . ' <small style="color: #999">(' . pageTreeHtmlAttr($pageSlug) . ')</small></span>';
        echo '<div class="actions">';
        $pageUrl = getLocalizedFrontendUrl($page, $currentAdminLang, $primaryLang);
        echo '<button onclick="' . pageTreeHtmlAttr('window.open(' . json_encode($pageUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ', \'_blank\')') . '" class="icon-btn view-btn" data-tooltip="' . pageTreeHtmlAttr(__('view_tooltip')) . '">' . getViewIcon() . '</button>';

        if (hasPermission('edit_pages')) {
            $isHome = $page['is_home'] ?? false;
            $homeClass = $isHome ? 'home-btn active' : 'home-btn';
            echo '<button onclick="' . pageTreeHtmlAttr(pageTreeJsCall('setHomePage', [$pageSlug])) . '" class="icon-btn ' . $homeClass . '" data-tooltip="' . pageTreeHtmlAttr(__('make_home_tooltip')) . '" data-slug="' . pageTreeHtmlAttr($pageSlug) . '">' . getStarIcon($isHome) . '</button>';
            $isActive = $page['active'] ?? true;
            $activeClass = $isActive ? 'active-btn active' : 'active-btn inactive';
            echo '<button onclick="' . pageTreeHtmlAttr(pageTreeJsCall('toggleActive', [$pageSlug])) . '" class="icon-btn ' . $activeClass . '" data-tooltip="' . pageTreeHtmlAttr($isActive ? __('active_tooltip') : __('inactive_tooltip')) . '" data-slug="' . pageTreeHtmlAttr($pageSlug) . '">' . getToggleIcon($isActive) . '</button>';
            $baseUrl = getLocalizedFrontendUrl($page, $currentAdminLang, $primaryLang);
            $editUrl = $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'edit=1';
            echo '<button onclick="' . pageTreeHtmlAttr('window.location.href=' . json_encode($editUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) . '" class="icon-btn edit-btn" data-tooltip="' . pageTreeHtmlAttr(__('edit_tooltip')) . '">' . getEditIcon() . '</button>';
        }

        if (hasPermission('create_pages')) {
            echo '<button onclick="' . pageTreeHtmlAttr(pageTreeJsCall('copyPage', [$pageSlug, (string)$displayTitle])) . '" class="icon-btn copy-btn" data-tooltip="' . pageTreeHtmlAttr(__('copy_tooltip')) . '">' . getCopyIcon() . '</button>';
        }

        if (hasPermission('delete_pages')) {
            echo '<button onclick="' . pageTreeHtmlAttr(pageTreeJsCall('deletePage', [$pageSlug, (string)$displayTitle])) . '" class="icon-btn delete-btn" data-tooltip="' . pageTreeHtmlAttr(__('delete_tooltip')) . '">' . getDeleteIcon() . '</button>';
        }

        echo '</div>';
        echo '</summary>';

        if (hasPermission('edit_pages')) {
            echo '<div class="page-edit-form">';

            $configuredLangs = Settings::getLanguages();
            $activeLangs = array_filter($configuredLangs, fn($l) => !empty($l['enabled']));
            if (empty($activeLangs)) {
                $activeLangs = [['code' => 'uk', 'name' => 'Українська']];
            }
            $primaryLang = $activeLangs[array_key_first($activeLangs)]['code'];
            $editingLang = $request->query('edit_lang', $primaryLang);

            $normalizeForJs = function($val) {
                return is_scalar($val) ? (string)$val : '';
            };

            $primaryLocaleData = (isset($page['locales'][$primaryLang]) && is_array($page['locales'][$primaryLang])) ? $page['locales'][$primaryLang] : [];

            $primaryDataPrepared = [
                'title' => $normalizeForJs($primaryLocaleData['title'] ?? ''),
                'slug' => $pageSlug,
                'url' => getLocalizedFrontendUrl($page, $primaryLang, $primaryLang),
                'seo' => [
                    'meta_title' => $normalizeForJs($primaryLocaleData['seo']['meta_title'] ?? ''),
                    'meta_description' => $normalizeForJs($primaryLocaleData['seo']['meta_description'] ?? '')
                ]
            ];

            $localesPrepared = [];
            if (isset($page['locales']) && is_array($page['locales'])) {
                foreach ($page['locales'] as $lCode => $tData) {
                    $localesPrepared[$lCode] = [
                        'title' => $normalizeForJs($tData['title'] ?? ''),
                        'slug' => $tData['slug'] ?? '',
                        'url' => getLocalizedFrontendUrl($page, $lCode, $primaryLang),
                        'seo' => [
                            'meta_title' => $normalizeForJs($tData['seo']['meta_title'] ?? ''),
                            'meta_description' => $normalizeForJs($tData['seo']['meta_description'] ?? '')
                        ]
                    ];
                }
            }

            echo '<div class="page-edit-head">';
            echo '<h4 class="page-edit-head-title">' . __('page_settings') . '</h4>';
            echo '<div class="page-edit-head-lang"><small>' . __('edit_language') . ': <strong>' . strtoupper($editingLang) . '</strong></small></div>';
            echo '</div>';

            echo '<form method="POST" class="page-edit-form-ajax page-edit-grid page-edit-grid-with-history" data-locales=\'' . pageTreeHtmlAttr(json_encode($localesPrepared)) . '\' data-primary=\'' . pageTreeHtmlAttr(json_encode($primaryDataPrepared)) . '\' data-primary-lang="' . pageTreeHtmlAttr($primaryLang) . '">';
            echo '<input type="hidden" name="action" value="update_page">';
            echo '<input type="hidden" name="slug" value="' . pageTreeHtmlAttr($pageSlug) . '">';
            echo '<input type="hidden" name="editing_lang" value="' . pageTreeHtmlAttr($editingLang) . '">';

            $getVal = function($field) use ($page, $editingLang, $primaryLang) {
                if ($editingLang === $primaryLang) {
                    return (string)($page['locales'][$primaryLang][$field] ?? '');
                }

                return (string)($page['locales'][$editingLang][$field] ?? '');
            };
            $getSeoVal = function($field) use ($page, $editingLang, $primaryLang) {
                if ($editingLang === $primaryLang) {
                    return (string)($page['locales'][$primaryLang]['seo'][$field] ?? '');
                }

                return (string)($page['locales'][$editingLang]['seo'][$field] ?? '');
            };

            $titleVal = $getVal('title');
            echo '<div><label>' . __('page_title') . ' - <span class="lang-code">' . strtoupper($editingLang) . '</span>: <input type="text" name="title" value="' . htmlspecialchars($titleVal) . '" required></label></div>';

            $slugVal = ($editingLang === $primaryLang) ? $pageSlug : ($page['locales'][$editingLang]['slug'] ?? $pageSlug);
            echo '<div><label>' . __('url_slug_label') . ' - <span class="lang-code">' . strtoupper($editingLang) . '</span>: <input type="text" name="new_slug" value="' . htmlspecialchars($slugVal) . '" required></label></div>';

            $https = $request->server('HTTPS');
            $serverPort = $request->server('SERVER_PORT');
            $protocol = ((!empty($https) && $https !== 'off') || (string)$serverPort === '443') ? 'https://' : 'http://';
            $actualUrl = getLocalizedFrontendUrl($page, $editingLang, $primaryLang);
            $fullUrl = $protocol . $request->server('HTTP_HOST', 'localhost') . $actualUrl;
            $escapedFull = htmlspecialchars($fullUrl, ENT_QUOTES | ENT_SUBSTITUTE);

            $allPagesForSelect = getAllPagesForParentSelect($pageSlug);
            echo '<div class="page-edit-span2"><label>' . __('parent_page') . ': <select name="parent_id">';
            echo '<option value="">' . __('none_root') . '</option>';
            foreach ($allPagesForSelect as $pSlug => $pData) {
                $selected = (($page['parent_id'] ?? null) === $pSlug) ? ' selected' : '';
                $pTitle = (string)($pData['title'] ?? $pSlug);
                echo '<option value="' . htmlspecialchars($pSlug) . '"' . $selected . '>' . htmlspecialchars($pTitle) . ' (' . htmlspecialchars($pData['url']) . ')</option>';
            }
            echo '</select><small class="page-edit-note">' . __('actual_url') . ': ';
            echo '<a href="' . $escapedFull . '" target="_blank" class="page-full-url">' . $escapedFull . '</a> ';
            echo '<button type="button" class="copy-url-btn icon-btn" data-url="' . $escapedFull . '" onclick="copyToClipboard(event, this)" data-tooltip="' . __('copy_url') . '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>';
            echo '</small></label></div>';

            $metaTitleVal = $getSeoVal('meta_title');
            $metaDescVal = $getSeoVal('meta_description');
            echo '<div class="page-edit-span2"><label>' . __('seo_meta_title') . ' - <span class="lang-code">' . strtoupper($editingLang) . '</span>: <input type="text" name="meta_title" value="' . htmlspecialchars($metaTitleVal) . '"></label></div>';
            echo '<div class="page-edit-span2"><label>' . __('seo_meta_description') . ' - <span class="lang-code">' . strtoupper($editingLang) . '</span>: <textarea name="meta_description" rows="2">' . htmlspecialchars($metaDescVal) . '</textarea></label></div>';

            $isConversion = !empty($page['conversion']);
            $conversionType = $page['conversion_type'] ?? 'conversion';
            $enabledConvTypes = getEnabledConversionTypes($conversionTypes);
            $labels = [
                'conversion' => __('conversion_type_generic'),
                'lead' => __('conversion_type_lead'),
                'registration' => __('conversion_type_registration'),
                'purchase' => __('conversion_type_purchase'),
                'add_to_cart' => __('conversion_type_add_to_cart'),
                'begin_checkout' => __('conversion_type_begin_checkout'),
                'subscribe' => __('conversion_type_subscribe'),
                'contact' => __('conversion_type_contact'),
                'sign_up' => __('conversion_type_sign_up'),
                'other' => __('conversion_type_other'),
            ];
            if ($conversionType && !in_array($conversionType, $enabledConvTypes, true)) {
                $enabledConvTypes[] = $conversionType;
            }
            echo '<div class="page-edit-span2 page-edit-row-inline">';
            echo '<label><input type="checkbox" name="is_conversion" value="1" ' . ($isConversion ? 'checked' : '') . '> ' . __('conversion_count') . '</label>';
            echo '<label>' . __('conversion_type') . ': <select name="conversion_type">';
            foreach ($enabledConvTypes as $val) {
                $label = $labels[$val] ?? ucfirst(str_replace('_', ' ', $val));
                $sel = ($conversionType === $val) ? ' selected' : '';
                echo '<option value="' . htmlspecialchars($val) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
            }
            echo '</select></label>';
            echo '</div>';
            echo '<div class="page-edit-span2 page-edit-meta">' . __('author') . ': ' . htmlspecialchars($page['author'] ?? __('unknown')) . ' | ' . __('modified') . ': ' . htmlspecialchars($page['modified'] ?? __('unknown')) . '</div>';
            echo '<button type="submit" class="btn page-edit-submit page-edit-span2">' . __('save') . '</button>';

            echo '<div class="history-container page-edit-history">';
            echo '<h4 class="history-title">' . __('history_versions') . '</h4>';
            echo '<div class="history-list" data-slug="' . pageTreeHtmlAttr($pageSlug) . '">';
            echo '<p class="history-empty">' . __('history_loading') . '</p>';
            echo '</div>';
            echo '</div>';

            echo '</form>';
            echo '</div>';
        }

        echo '</details>';
        echo '</li>';
    }
}
