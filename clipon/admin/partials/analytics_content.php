<?php
$stats = $stats ?? [];
$conv = $stats['conversions'] ?? ['total' => 0, 'pages' => [], 'types' => [], 'recent' => []];
$convTotal = (int)($conv['total'] ?? 0);
$convBase = (int)($stats['total_uniques'] ?? 0);
$convCr = $convBase > 0 ? round(($convTotal / $convBase) * 100, 2) : 0;
$analyticsSettings = Settings::load();
$analyticsPolicy = new CookieConsentPolicy($analyticsSettings, $request);
$dashboardMode = $analyticsPolicy->isBannerEnabled() && $analyticsPolicy->configuredMode() === CookieConsentPolicy::MODE_FULL
    ? CookieConsentPolicy::MODE_FULL
    : CookieConsentPolicy::MODE_BASIC;
?>

<div class="admin-container">
    <?php include __DIR__ . '/../nav.php'; ?>
    <main class="main-content">
        <?php $gateState = AdminUI::proGateStart('pro_analytics'); ?>
        <?php include __DIR__ . '/analytics_pro_helpers.php'; ?>
        <header class="header">
            <div class="header-title">
                <h1><?= __('analytics_h1') ?></h1>
                <p><?= __('welcome') ?>, <?= htmlspecialchars($user) ?></p>
            </div>
            <span class="analytics-mode-badge <?= $dashboardMode === CookieConsentPolicy::MODE_FULL ? 'is-full' : 'is-basic' ?>">
                <?= $dashboardMode === CookieConsentPolicy::MODE_FULL ? __('analytics_mode_badge_full') : __('analytics_mode_badge_basic') ?>
            </span>
        </header>

        <?php if ($dashboardMode === CookieConsentPolicy::MODE_BASIC): ?>
        <div class="analytics-mode-notice">
            <?= __('analytics_mode_basic_notice') ?>
        </div>
        <?php endif; ?>

        <?php include __DIR__ . '/analytics_filter.php'; ?>
        <?php include __DIR__ . '/analytics_pro_notice.php'; ?>

        <?php $analyticsProDataHidden = !empty($stats['is_missing_module']); ?>
        <?php if (!$analyticsProDataHidden): ?>
        <div class="stats-cards">
            <div class="stat-card">
                <h3><?= __('analytics_total_hits') ?></h3>
                <div class="value"><?php $analyticsMetric(number_format($stats['total_hits'])); ?></div>
            </div>
            <div class="stat-card">
                <h3><?= __('analytics_unique_visitors') ?></h3>
                <div class="value"><?php $analyticsMetric(number_format($stats['total_uniques'])); ?></div>
            </div>
            <div class="stat-card">
                <h3><?= __('analytics_bounce_rate') ?></h3>
                <?php
                    $totalEntries = (int)($stats['total_sessions'] ?? array_sum($stats['entry_pages'] ?? []));
                    $bounceCount = $stats['bounce_count'] ?? 0;
                    $bounceRate = ($totalEntries > 0) ? round(($bounceCount / $totalEntries) * 100, 1) : 0;
                ?>
                <div class="value"><?php $analyticsMetric($bounceRate . '%'); ?></div>
            </div>
            <div class="stat-card">
                <h3><?= __('analytics_avg_scroll') ?></h3>
                <?php
                    $scrollEvents = $stats['events']['scroll'] ?? [];
                    $totalS = 0;
                    $sum = 0;
                    foreach ($scrollEvents as $label => $counts) {
                        if (is_array($counts)) {
                            foreach ($counts as $threshold => $count) {
                                $totalS += $count;
                                $sum += ($count * (int)$threshold);
                            }
                        } else {
                            $totalS += $counts;
                            $sum += ($counts * (int)$label);
                        }
                    }
                    $avg = ($totalS > 0) ? round($sum / $totalS) : 0;
                ?>
                <div class="value"><?php $analyticsMetric($avg . '%'); ?></div>
            </div>
            <div class="stat-card">
                <h3><?= __('analytics_avg_time') ?></h3>
                <?php
                    $totalT = 0;
                    $countT = 0;
                    foreach ($stats['time_on_page'] ?? [] as $tdata) {
                        $totalT += $tdata['t'];
                        $countT += $tdata['c'];
                    }
                    $avgT = ($countT > 0) ? round($totalT / $countT) : 0;
                    $min = floor($avgT / 60);
                    $sec = $avgT % 60;
                    $displayTime = ($min > 0) ? "{$min}m {$sec}s" : "{$sec}s";
                ?>
                <div class="value"><?php $analyticsMetric($displayTime); ?></div>
            </div>
            <div class="stat-card">
                <h3><?= __('analytics_conversions') ?></h3>
                <div class="value"><?php $analyticsMetric(number_format($convTotal)); ?></div>
            </div>
            <div class="stat-card">
                <h3><?= __('analytics_cr') ?></h3>
                <div class="value"><?php $analyticsMetric($convCr . '%'); ?></div>
            </div>
        </div>

        <div class="analytics-chart-card">
            <h2 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600;"><?= __('analytics_visits_graph') ?></h2>
            <?php if ($analyticsProLocked): ?>
            <div class="pro-stub-blur" style="flex-grow: 1; position: relative; height: calc(100% - 2rem);">
                <canvas id="visitsChart"></canvas>
            </div>
            <?php else: ?>
            <div style="flex-grow: 1; position: relative; height: calc(100% - 2rem);">
                <canvas id="visitsChart"></canvas>
            </div>
            <?php endif; ?>
        </div>

        <div class="analytics-grid">
            <div class="analytics-card">
                <div class="analytics-card-header">
                    <h2><?= __('analytics_conversion_pages') ?></h2>
                    <?php if ($analyticsLockedButton(__('analytics_show_all'), ['class' => 'btn-show-all'])): ?>
                    <?php else: ?>
                    <button type="button" class="btn-show-all" onclick="showFullStats('conversion_pages')"><?= __('analytics_show_all') ?></button>
                    <?php endif; ?>
                </div>
                <table class="analytics-table">
                    <?php
                    $convPages = $conv['pages'] ?? [];
                    $maxConvPage = !empty($convPages) ? max($convPages) : 0;
                    if (empty($convPages)): ?>
                        <tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 2rem;"><?= __('no_details') ?></td></tr>
                    <?php else:
                        foreach (array_slice($convPages, 0, 10) as $url => $count):
                            $percent = getWidth($count, $maxConvPage);
                        ?>
                        <tr>
                            <td style="width: 70%; word-break: break-all;">
                                <?php $analyticsMetric($url); ?>
                                <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                            </td>
                            <td style="text-align: right; font-weight: 600; vertical-align: top;"><?php $analyticsMetric($count); ?></td>
                        </tr>
                        <?php endforeach;
                    endif; ?>
                </table>
            </div>

            <div class="analytics-card">
                <div class="analytics-card-header">
                    <h2><?= __('analytics_conversion_types') ?></h2>
                    <?php if ($analyticsLockedButton(__('analytics_show_all'), ['class' => 'btn-show-all'])): ?>
                    <?php else: ?>
                    <button type="button" class="btn-show-all" onclick="showFullStats('conversion_types')"><?= __('analytics_show_all') ?></button>
                    <?php endif; ?>
                </div>
                <table class="analytics-table">
                    <?php
                    $convTypes = $conv['types'] ?? [];
                    $maxConvType = !empty($convTypes) ? max($convTypes) : 0;
                    if (empty($convTypes)): ?>
                        <tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 2rem;"><?= __('no_details') ?></td></tr>
                    <?php else:
                        foreach (array_slice($convTypes, 0, 10) as $type => $count):
                            $percent = getWidth($count, $maxConvType);
                        ?>
                        <tr>
                            <td style="width: 70%; word-break: break-all; text-transform: capitalize;">
                                <?php $analyticsMetric($type); ?>
                                <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                            </td>
                            <td style="text-align: right; font-weight: 600; vertical-align: top;"><?php $analyticsMetric($count); ?></td>
                        </tr>
                        <?php endforeach;
                    endif; ?>
                </table>
            </div>

            <div class="analytics-card" style="grid-column: span 2;">
                <div class="analytics-card-header">
                    <h2><?= __('analytics_recent_conversions') ?></h2>
                </div>
                <table class="analytics-table">
                    <thead>
                        <tr style="background: #f9fafb;">
                            <td style="font-weight: 600; width: 22%;"><?= __('analytics_time') ?></td>
                            <td style="font-weight: 600; width: 18%;"><?= __('conversion_type') ?></td>
                            <td style="font-weight: 600; width: 25%;"><?= __('analytics_page') ?></td>
                            <td style="font-weight: 600; width: 20%;"><?= __('analytics_utm') ?></td>
                            <td style="font-weight: 600; text-align: right; width: 15%;"><?= __('analytics_referrer') ?></td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $convRecent = $conv['recent'] ?? [];
                        usort($convRecent, function($a, $b) { return ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0); });
                        $convRecent = array_slice($convRecent, 0, 10);
                        if (empty($convRecent)): ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 1.5rem;"><?= __('no_details') ?></td></tr>
                        <?php else:
                            foreach ($convRecent as $item):
                                $timeStr = !empty($item['ts']) ? date('Y-m-d H:i', (int)$item['ts']) : '-';
                                $type = (string)($item['type'] ?? 'conversion');
                                $uri = (string)($item['uri'] ?? '');
                                $utm = $item['utm'] ?? [];
                                $utmStr = [];
                                foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $u) {
                                    if (!empty($utm[$u])) {
                                        $utmStr[] = $utm[$u];
                                    }
                                }
                                $utmDisplay = $utmStr ? implode(' / ', $utmStr) : '-';
                                $refDisplay = (string)($item['ref'] ?? 'direct');
                            ?>
                            <tr>
                                <td><?php $analyticsMetric($timeStr); ?></td>
                                <td style="text-transform: capitalize; font-weight: 600; color: var(--text-primary);"><?php $analyticsMetric($type); ?></td>
                                <td style="word-break: break-all; color: var(--text-secondary);"><?php $analyticsMetric($uri); ?></td>
                                <td style="color: var(--text-secondary); word-break: break-all;"><?php $analyticsMetric($utmDisplay); ?></td>
                                <td style="text-align: right; color: var(--text-secondary);"><?php $analyticsMetric($refDisplay); ?></td>
                            </tr>
                            <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="analytics-card" id="analytics-top-pages">
                <div class="analytics-card-header">
                    <h2><?= __('analytics_top_pages') ?></h2>
                    <?php if ($analyticsLockedButton(__('analytics_show_all'), ['class' => 'btn-show-all'])): ?>
                    <?php else: ?>
                    <button type="button" class="btn-show-all" onclick="showFullStats('pages')"><?= __('analytics_show_all') ?></button>
                    <?php endif; ?>
                </div>
                <table class="analytics-table">
                    <?php
                    $maxPages = !empty($stats['pages']) ? max($stats['pages']) : 0;
                    if (empty($stats['pages'])): ?>
                        <tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 2rem;"><?= __('no_details') ?></td></tr>
                    <?php else:
                        foreach (array_slice($stats['pages'], 0, 10) as $url => $count):
                            $percent = getWidth($count, $maxPages);
                        ?>
                        <tr>
                            <td style="width: 70%; word-break: break-all;">
                                <?php $analyticsMetric($url); ?>
                                <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                            </td>
                            <td style="text-align: right; font-weight: 600; vertical-align: top;"><?php $analyticsMetric($count); ?></td>
                        </tr>
                        <?php endforeach;
                    endif; ?>
                </table>
            </div>

            <div class="analytics-card">
                <div class="analytics-card-header">
                    <h2><?= __('analytics_top_referrers') ?></h2>
                    <?php if ($analyticsLockedButton(__('analytics_show_all'), ['class' => 'btn-show-all'])): ?>
                    <?php else: ?>
                    <button type="button" class="btn-show-all" onclick="showFullStats('referrers')"><?= __('analytics_show_all') ?></button>
                    <?php endif; ?>
                </div>
                <table class="analytics-table">
                    <?php
                    $maxRef = !empty($stats['referrers']) ? max($stats['referrers']) : 0;
                    if (empty($stats['referrers'])): ?>
                        <tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 2rem;"><?= __('no_details') ?></td></tr>
                    <?php else:
                        foreach (array_slice($stats['referrers'], 0, 10) as $ref => $count):
                            $percent = getWidth($count, $maxRef);
                        ?>
                        <tr>
                            <td style="width: 70%;">
                                <?php $analyticsMetric($ref); ?>
                                <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                            </td>
                            <td style="text-align: right; font-weight: 600; vertical-align: top;"><?php $analyticsMetric($count); ?></td>
                        </tr>
                        <?php endforeach;
                    endif; ?>
                </table>
            </div>

            <div class="analytics-card">
                <div class="analytics-card-header">
                    <h2><?= __('analytics_devices') ?></h2>
                    <?php if ($analyticsLockedButton(__('analytics_show_all'), ['class' => 'btn-show-all'])): ?>
                    <?php else: ?>
                    <button type="button" class="btn-show-all" onclick="showFullStats('devices')"><?= __('analytics_show_all') ?></button>
                    <?php endif; ?>
                </div>
                <table class="analytics-table">
                    <?php
                    $maxDev = !empty($stats['devices']) ? max($stats['devices']) : 0;
                    if (empty($stats['devices'])): ?>
                        <tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 2rem;"><?= __('no_details') ?></td></tr>
                    <?php else:
                        foreach ($stats['devices'] as $dev => $count):
                            $percent = getWidth($count, $maxDev);
                        ?>
                        <tr>
                            <td style="width: 70%;">
                                <?php $analyticsMetric(ucfirst((string)$dev)); ?>
                                <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                            </td>
                            <td style="text-align: right; font-weight: 600; vertical-align: top;"><?php $analyticsMetric($count); ?></td>
                        </tr>
                        <?php endforeach;
                    endif; ?>
                </table>
            </div>

            <div class="analytics-card">
                <div class="analytics-card-header">
                    <h2><?= __('analytics_languages') ?></h2>
                    <?php if ($analyticsLockedButton(__('analytics_show_all'), ['class' => 'btn-show-all'])): ?>
                    <?php else: ?>
                    <button type="button" class="btn-show-all" onclick="showFullStats('languages')"><?= __('analytics_show_all') ?></button>
                    <?php endif; ?>
                </div>
                <table class="analytics-table">
                    <?php
                    $maxLang = !empty($stats['languages']) ? max($stats['languages']) : 0;
                    if (empty($stats['languages'])): ?>
                        <tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 2rem;"><?= __('no_details') ?></td></tr>
                    <?php else:
                        foreach ($stats['languages'] as $lang => $count):
                            $percent = getWidth($count, $maxLang);
                        ?>
                        <tr>
                            <td style="width: 70%;">
                                <?php $analyticsMetric(strtoupper((string)$lang)); ?>
                                <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                            </td>
                            <td style="text-align: right; font-weight: 600; vertical-align: top;"><?php $analyticsMetric($count); ?></td>
                        </tr>
                        <?php endforeach;
                    endif; ?>
                </table>
            </div>

            <div class="analytics-card">
                <div class="analytics-card-header">
                    <h2><?= __('analytics_geography') ?></h2>
                    <?php if ($analyticsLockedButton(__('analytics_show_all'), ['class' => 'btn-show-all'])): ?>
                    <?php else: ?>
                    <button type="button" class="btn-show-all" onclick="showFullStats('countries')"><?= __('analytics_show_all') ?></button>
                    <?php endif; ?>
                </div>
                <table class="analytics-table">
                    <?php
                    $maxGeo = !empty($stats['countries']) ? max($stats['countries']) : 0;
                    if (empty($stats['countries'])): ?>
                        <tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 2rem;"><?= __('no_details') ?></td></tr>
                    <?php else:
                        foreach (array_slice($stats['countries'], 0, 10) as $country => $count):
                            $percent = getWidth($count, $maxGeo);
                        ?>
                        <tr>
                            <td style="width: 70%;">
                                <span style="font-family: monospace;"><?php $analyticsMetric($country); ?></span>
                                <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                            </td>
                            <td style="text-align: right; font-weight: 600; vertical-align: top;"><?php $analyticsMetric($count); ?></td>
                        </tr>
                        <?php endforeach;
                    endif; ?>
                </table>
            </div>

            <div class="analytics-card">
                <div class="analytics-card-header">
                    <h2><?= __('analytics_entry_pages') ?></h2>
                    <?php if ($analyticsLockedButton(__('analytics_show_all'), ['class' => 'btn-show-all'])): ?>
                    <?php else: ?>
                    <button type="button" class="btn-show-all" onclick="showFullStats('entry_pages')"><?= __('analytics_show_all') ?></button>
                    <?php endif; ?>
                </div>
                <table class="analytics-table">
                    <?php
                    $maxEntry = !empty($stats['entry_pages']) ? max($stats['entry_pages']) : 0;
                    if (empty($stats['entry_pages'])): ?>
                        <tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 2rem;"><?= __('no_details') ?></td></tr>
                    <?php else:
                        foreach (array_slice($stats['entry_pages'], 0, 10) as $url => $count):
                            $percent = getWidth($count, $maxEntry);
                        ?>
                        <tr>
                            <td style="width: 70%; word-break: break-all;">
                                <?php $analyticsMetric($url); ?>
                                <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                            </td>
                            <td style="text-align: right; font-weight: 600;"><?php $analyticsMetric($count); ?></td>
                        </tr>
                        <?php endforeach;
                    endif; ?>
                </table>
            </div>

            <div class="analytics-card">
                <div class="analytics-card-header">
                    <h2><?= __('analytics_exit_pages') ?></h2>
                    <?php if ($analyticsLockedButton(__('analytics_show_all'), ['class' => 'btn-show-all'])): ?>
                    <?php else: ?>
                    <button type="button" class="btn-show-all" onclick="showFullStats('exit_pages')"><?= __('analytics_show_all') ?></button>
                    <?php endif; ?>
                </div>
                <table class="analytics-table">
                    <?php
                    $maxExit = !empty($stats['exit_pages']) ? max($stats['exit_pages']) : 0;
                    if (empty($stats['exit_pages'])): ?>
                        <tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 2rem;"><?= __('no_details') ?></td></tr>
                    <?php else:
                        foreach (array_slice($stats['exit_pages'], 0, 10) as $url => $count):
                            $percent = getWidth($count, $maxExit);
                        ?>
                        <tr>
                            <td style="width: 70%; word-break: break-all;">
                                <?php $analyticsMetric($url); ?>
                                <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                            </td>
                            <td style="text-align: right; font-weight: 600;"><?php $analyticsMetric($count); ?></td>
                        </tr>
                        <?php endforeach;
                    endif; ?>
                </table>
            </div>

            <div class="analytics-card" style="grid-column: span 2;">
                <div class="analytics-card-header">
                    <h2><?= __('analytics_campaigns') ?></h2>
                </div>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; padding: 1rem;">
                    <?php foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $type): ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <h4 style="margin: 0; font-size: 0.8125rem; color: var(--text-secondary);"><?= ucfirst(str_replace('utm_', '', $type)) ?></h4>
                                <?php if ($analyticsLockedButton(__('analytics_show_all'), ['class' => 'btn-show-all'])): ?>
                                <?php else: ?>
                                    <button type="button" class="btn-show-all" onclick="showFullStats('utm:<?= $type ?>')"><?= __('analytics_show_all') ?></button>
                                <?php endif; ?>
                            </div>
                            <table class="analytics-table" style="border: 1px solid var(--border-color); border-radius: var(--radius-sm);">
                                <?php
                                $typeData = $stats['utm'][$type] ?? [];
                                $maxUtm = !empty($typeData) ? max($typeData) : 0;
                                if (empty($typeData)): ?>
                                    <tr><td style="text-align: center; color: var(--text-muted); font-size: 0.75rem;"><?= __('no_details') ?></td></tr>
                                <?php else:
                                    foreach (array_slice($typeData, 0, 5) as $val => $count):
                                        $percent = getWidth($count, $maxUtm);
                                    ?>
                                    <tr>
                                        <td style="padding: 0.5rem; font-size: 0.75rem;">
                                            <?php $analyticsMetric($val); ?>
                                            <div class="bar-bg" style="height: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                                        </td>
                                        <td style="padding: 0.5rem; text-align: right; font-weight: 600; font-size: 0.75rem;"><?php $analyticsMetric($count); ?></td>
                                    </tr>
                                    <?php endforeach;
                                endif; ?>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="analytics-card">
                <div class="analytics-card-header">
                    <h2><?= __('analytics_time_on_page') ?></h2>
                    <?php if ($analyticsLockedButton(__('analytics_show_all'), ['class' => 'btn-show-all'])): ?>
                    <?php else: ?>
                    <button type="button" class="btn-show-all" onclick="showFullStats('time_on_page')"><?= __('analytics_show_all') ?></button>
                    <?php endif; ?>
                </div>
                <table class="analytics-table">
                    <thead>
                        <tr style="background: #f9fafb;">
                            <td style="font-weight: 600; width: 80%;"><?= __('analytics_page') ?></td>
                            <td style="font-weight: 600; text-align: right;"><?= __('analytics_avg') ?></td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $timeStats = $stats['time_on_page'] ?? [];
                        uasort($timeStats, function($a, $b) {
                            $avgA = $a['c'] > 0 ? ($a['t'] / $a['c']) : 0;
                            $avgB = $b['c'] > 0 ? ($b['t'] / $b['c']) : 0;
                            return $avgB <=> $avgA;
                        });

                        if (empty($timeStats)): ?>
                            <tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 2rem;"><?= __('no_details') ?></td></tr>
                        <?php else:
                            $slice = array_slice($timeStats, 0, 15);
                            foreach ($slice as $url => $tdata):
                                $avg = $tdata['c'] > 0 ? round($tdata['t'] / $tdata['c']) : 0;
                                $m = floor($avg / 60);
                                $s = $avg % 60;
                                $formatted = ($m > 0) ? "{$m}m {$s}s" : "{$s}s";
                            ?>
                            <tr>
                                <td style="word-break: break-all;"><?php $analyticsMetric($url); ?></td>
                                <td style="text-align: right; font-weight: 600;"><?php $analyticsMetric($formatted); ?></td>
                            </tr>
                            <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="analytics-card">
                <div class="analytics-card-header">
                    <h2><?= __('analytics_page_engagement') ?> (<?= __('analytics_avg_scroll') ?>)</h2>
                    <?php if ($analyticsLockedButton(__('analytics_show_all'), ['class' => 'btn-show-all'])): ?>
                    <?php else: ?>
                    <button type="button" class="btn-show-all" onclick="showFullStats('events:scroll')"><?= __('analytics_show_all') ?></button>
                    <?php endif; ?>
                </div>
                <table class="analytics-table">
                    <thead>
                        <tr style="background: #f9fafb;">
                            <td style="font-weight: 600; width: 60%;"><?= __('analytics_page') ?></td>
                            <td style="font-weight: 600; text-align: center;">25%</td>
                            <td style="font-weight: 600; text-align: center;">50%</td>
                            <td style="font-weight: 600; text-align: center;">75%</td>
                            <td style="font-weight: 600; text-align: center;">100%</td>
                            <td style="font-weight: 600; text-align: right;"><?= __('analytics_avg') ?></td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $pageScrolls = [];
                        foreach ($scrollEvents as $url => $data) {
                            if (!is_array($data)) continue;
                            $tS = array_sum($data);
                            if ($tS === 0) continue;
                            $sSum = (($data['25%']??0)*25 + ($data['50%']??0)*50 + ($data['75%']??0)*75 + ($data['100%']??0)*100);
                            $pageScrolls[$url] = [
                                'avg' => round($sSum / $tS),
                                'data' => $data,
                                'total' => $tS
                            ];
                        }
                        uasort($pageScrolls, function($a, $b) { return $b['total'] <=> $a['total']; });

                        if (empty($pageScrolls)): ?>
                            <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;"><?= __('no_details') ?></td></tr>
                        <?php else:
                            foreach (array_slice($pageScrolls, 0, 15) as $url => $info): ?>
                            <tr>
                                <td style="word-break: break-all; font-weight: 500;"><?php $analyticsMetric($url); ?></td>
                                <td style="text-align: center; color: var(--text-secondary);"><?php $analyticsMetric($info['data']['25%'] ?? 0); ?></td>
                                <td style="text-align: center; color: var(--text-secondary);"><?php $analyticsMetric($info['data']['50%'] ?? 0); ?></td>
                                <td style="text-align: center; color: var(--text-secondary);"><?php $analyticsMetric($info['data']['75%'] ?? 0); ?></td>
                                <td style="text-align: center; color: var(--text-secondary);"><?php $analyticsMetric($info['data']['100%'] ?? 0); ?></td>
                                <td style="text-align: right;">
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-weight: 600; background: <?= $info['avg'] > 50 ? '#ecfdf5' : '#fff7ed' ?>; color: <?= $info['avg'] > 50 ? '#059669' : '#d97706' ?>;">
                                        <?php $analyticsMetric($info['avg'] . '%'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        <?php AdminUI::proGateEnd($gateState ?? []); ?>
    </main>
</div>

<div id="statsModal" class="modal-overlay" onclick="closeModal(event)">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 id="modalTitle">Details</h3>
            <button class="btn-close" onclick="closeModal()">×</button>
        </div>
        <div class="modal-search">
            <input type="text" id="modalSearch" placeholder="Search..." oninput="filterModalTable()">
        </div>
        <div class="modal-body">
            <table class="modal-table">
                <thead>
                    <tr id="modalTableHead"></tr>
                </thead>
                <tbody id="modalTableBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script src="<?= C_ASSETS_URL ?>/vendor/chartjs/chart.min.js"></script>
<script>
    const allStats = <?= json_encode($stats, JSON_UNESCAPED_UNICODE) ?>;
    const fromDate = '<?= $from ?>';
    const toDate = '<?= $to ?>';

    function createMetricLegendPoint(color, active) {
        const canvas = document.createElement('canvas');
        const size = 14;
        const center = size / 2;
        canvas.width = size;
        canvas.height = size;

        const context = canvas.getContext('2d');
        context.beginPath();
        context.arc(center, center, 5, 0, Math.PI * 2);
        context.fillStyle = color;
        context.fill();

        if (active) {
            context.beginPath();
            context.arc(center, center, 2, 0, Math.PI * 2);
            context.fillStyle = '#ffffff';
            context.fill();
        }

        return canvas;
    }

    function initChart() {
        const ctx = document.getElementById('visitsChart').getContext('2d');
        const daily = allStats.daily || {};
        const labels = [];
        const hitsData = [];
        const viewsData = [];
        const convData = [];
        const [y, m, d] = fromDate.split('-').map(Number);
        let current = new Date(y, m - 1, d);
        const [ey, em, ed] = toDate.split('-').map(Number);
        const end = new Date(ey, em - 1, ed);
        while (current <= end) {
            const dateStr = current.getFullYear() + '-' + String(current.getMonth() + 1).padStart(2, '0') + '-' + String(current.getDate()).padStart(2, '0');
            labels.push(dateStr);
            hitsData.push(daily[dateStr]?.hits || 0);
            viewsData.push(daily[dateStr]?.uniques || 0);
            convData.push(daily[dateStr]?.conversions || 0);
            current.setDate(current.getDate() + 1);
        }
        new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: '<?= __('analytics_total_hits') ?>', data: hitsData, borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.05)', fill: true, tension: 0.3, borderWidth: 2, pointRadius: 3, pointHoverRadius: 5 },
                    { label: '<?= __('analytics_unique_visitors') ?>', data: viewsData, borderColor: '#10b981', backgroundColor: 'transparent', fill: false, tension: 0.3, borderWidth: 2, pointRadius: 3, pointHoverRadius: 5 },
                    { label: '<?= __('analytics_conversions') ?>', data: convData, borderColor: '#f97316', backgroundColor: 'rgba(249, 115, 22, 0.08)', fill: true, tension: 0.3, borderWidth: 2, pointRadius: 3, pointHoverRadius: 5 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 14,
                            boxHeight: 14,
                            padding: 20,
                            font: { size: 12 },
                            generateLabels(chart) {
                                return Chart.defaults.plugins.legend.labels.generateLabels(chart).map((item) => {
                                    const dataset = chart.data.datasets[item.datasetIndex] || {};
                                    item.pointStyle = createMetricLegendPoint(dataset.borderColor || item.fillStyle, !item.hidden);
                                    return item;
                                });
                            }
                        }
                    },
                    tooltip: { mode: 'index', intersect: false, padding: 12, backgroundColor: 'rgba(255, 255, 255, 0.95)', titleColor: '#1f2937', bodyColor: '#4b5563', borderColor: '#e5e7eb', borderWidth: 1, titleFont: { weight: 'bold' } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f3f4f6' }, ticks: { stepSize: 1, font: { size: 11 } } },
                    x: { grid: { display: false }, ticks: { font: { size: 11 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } }
                },
                interaction: { mode: 'nearest', axis: 'x', intersect: false }
            }
        });
    }
    document.addEventListener('DOMContentLoaded', initChart);
    const copyIcon = <?= json_encode(Icons::copy(16)) ?>;
    const checkIcon = <?= json_encode(Icons::check(16)) ?>;
    let currentMetric = '';
    function showFullStats(metric) {
        currentMetric = metric;
        const modal = document.getElementById('statsModal');
        const title = document.getElementById('modalTitle');
        const head = document.getElementById('modalTableHead');
        const search = document.getElementById('modalSearch');
        search.value = '';
        modal.style.display = 'flex';
        let data = {};
        const titles = { 'conversion_pages': '<?= __('analytics_conversion_pages') ?>', 'conversion_types': '<?= __('analytics_conversion_types') ?>', 'conversion_recent': '<?= __('analytics_recent_conversions') ?>', 'languages': '<?= __('analytics_languages') ?>', 'devices': '<?= __('analytics_devices') ?>', 'referrers': '<?= __('analytics_top_referrers') ?>', 'countries': '<?= __('analytics_geography') ?>' };
        const displayTitle = titles[metric] ?? metric.replace('_', ' ').replace(':', ' - ').toUpperCase();
        if (metric === 'conversion_pages') data = (allStats.conversions && allStats.conversions.pages) ? allStats.conversions.pages : {};
        else if (metric === 'conversion_types') data = (allStats.conversions && allStats.conversions.types) ? allStats.conversions.types : {};
        else if (metric === 'conversion_recent') data = (allStats.conversions && allStats.conversions.recent) ? allStats.conversions.recent : [];
        else if (metric.includes(':')) { const parts = metric.split(':'); data = allStats[parts[0]] ? (allStats[parts[0]][parts[1]] || {}) : {}; }
        else data = allStats[metric] || {};
        title.innerText = displayTitle;
        if (metric === 'conversion_recent') { head.innerHTML = `<th><?= __('analytics_time') ?></th><th><?= __('conversion_type') ?></th><th><?= __('analytics_page') ?></th><th><?= __('analytics_utm') ?></th><th style="text-align: right;"><?= __('analytics_referrer') ?></th>`; renderRecentTable(Array.isArray(data) ? data : []); return; }
        if (metric === 'events:scroll') { head.innerHTML = `<th>Page</th><th style="text-align: right;">Avg. Scroll</th>`; const processed = {}; for (let [url, counts] of Object.entries(data)) { if (typeof counts !== 'object') continue; const total = Object.values(counts).reduce((a, b) => a + b, 0); if (total === 0) continue; const sum = (counts['25%'] || 0) * 25 + (counts['50%'] || 0) * 50 + (counts['75%'] || 0) * 75 + (counts['100%'] || 0) * 100; processed[url] = Math.round(sum / total); } renderModalTable(processed, true); return; }
        if (metric === 'time_on_page') { head.innerHTML = `<th>Page</th><th style="text-align: right;">Avg. Time</th>`; const processed = {}; for (let [url, info] of Object.entries(data)) { processed[url] = (info && info.c > 0) ? Math.round(info.t / info.c) : 0; } renderModalTable(processed, false, true); return; }
        head.innerHTML = `<th>Name</th><th style="text-align: right;">Count</th>`; renderModalTable(data);
    }
    function renderModalTable(data, isPercentage = false, isTime = false) {
        const body = document.getElementById('modalTableBody'); body.innerHTML = '';
        const items = Object.entries(data).sort((a, b) => { const valA = typeof a[1] === 'number' ? a[1] : parseInt(a[1]); const valB = typeof b[1] === 'number' ? b[1] : parseInt(b[1]); return valB - valA; });
        const formatTime = (sec) => { const m = Math.floor(sec / 60); const s = sec % 60; return m > 0 ? `${m}m ${s}s` : `${s}s`; };
        items.forEach(([name, count]) => { const tr = document.createElement('tr'); let displayCount = count.toLocaleString(); if (isPercentage) displayCount = count + '%'; if (isTime) displayCount = formatTime(count); tr.innerHTML = `<td style="word-break: break-all; display: flex; align-items: center; gap: 8px;"><div style="flex: 1;">${escapeHtml(name)}</div><button class="copy-hint" data-tooltip="Copy" onclick="copyToClipboard(this)" data-name="${encodeURIComponent(name)}" aria-label="Copy">${copyIcon}</button></td><td style="text-align: right; font-weight: 600;">${displayCount}</td>`; body.appendChild(tr); });
    }
    function renderRecentTable(list) {
        const body = document.getElementById('modalTableBody'); body.innerHTML = ''; const items = [...list].sort((a, b) => (b.ts || 0) - (a.ts || 0));
        if (!items.length) { const tr = document.createElement('tr'); tr.innerHTML = `<td colspan="5" style="text-align:center; color: var(--text-muted); padding: 1rem;"><?= __('no_details') ?></td>`; body.appendChild(tr); return; }
        items.forEach(item => { const ts = item.ts ? new Date(item.ts * 1000) : null; const tsStr = ts ? `${ts.getFullYear()}-${String(ts.getMonth() + 1).padStart(2, '0')}-${String(ts.getDate()).padStart(2, '0')} ${String(ts.getHours()).padStart(2, '0')}:${String(ts.getMinutes()).padStart(2, '0')}` : '-'; const type = (item.type || 'conversion'); const uri = item.uri || ''; const utm = item.utm || {}; const utmParts = ['utm_source', 'utm_medium', 'utm_campaign'].map(k => utm[k]).filter(Boolean); const utmStr = utmParts.length ? utmParts.join(' / ') : '—'; const ref = item.ref || 'direct'; const tr = document.createElement('tr'); tr.innerHTML = `<td>${tsStr}</td><td style="text-transform: capitalize; font-weight: 600;">${escapeHtml(type)}</td><td style="word-break: break-all;">${escapeHtml(uri)}</td><td style="word-break: break-all; color: var(--text-secondary);">${escapeHtml(utmStr)}</td><td style="text-align: right; color: var(--text-secondary);">${escapeHtml(ref)}</td>`; body.appendChild(tr); });
    }
    function filterModalTable() {
        const query = document.getElementById('modalSearch').value.toLowerCase(); let data = {};
        if (currentMetric === 'conversion_pages') data = (allStats.conversions && allStats.conversions.pages) ? allStats.conversions.pages : {};
        else if (currentMetric === 'conversion_types') data = (allStats.conversions && allStats.conversions.types) ? allStats.conversions.types : {};
        else if (currentMetric === 'conversion_recent') { const list = (allStats.conversions && Array.isArray(allStats.conversions.recent)) ? allStats.conversions.recent : []; const filtered = list.filter(item => { const uri = (item.uri || '').toLowerCase(); const type = (item.type || '').toLowerCase(); const ref = (item.ref || '').toLowerCase(); const utm = item.utm || {}; const utmStr = ['utm_source', 'utm_medium', 'utm_campaign'].map(k => utm[k] || '').join(' ').toLowerCase(); return uri.includes(query) || type.includes(query) || ref.includes(query) || utmStr.includes(query); }); renderRecentTable(filtered); return; }
        else if (currentMetric.includes(':')) { const parts = currentMetric.split(':'); data = allStats[parts[0]] ? (allStats[parts[0]][parts[1]] || {}) : {}; }
        else data = allStats[currentMetric] || {};
        if (currentMetric === 'events:scroll') { const processed = {}; for (let [url, counts] of Object.entries(data)) { if (url.toLowerCase().includes(query)) { const total = Object.values(counts).reduce((a, b) => a + b, 0); if (total === 0) continue; const sum = (counts['25%'] || 0) * 25 + (counts['50%'] || 0) * 50 + (counts['75%'] || 0) * 75 + (counts['100%'] || 0) * 100; processed[url] = Math.round(sum / total); } } renderModalTable(processed, true); return; }
        if (currentMetric === 'time_on_page') { const processed = {}; for (let [url, info] of Object.entries(data)) { if (url.toLowerCase().includes(query)) { processed[url] = (info && info.c > 0) ? Math.round(info.t / info.c) : 0; } } renderModalTable(processed, false, true); return; }
        const filtered = {}; for (let [name, count] of Object.entries(data)) { if (name.toLowerCase().includes(query)) filtered[name] = count; } renderModalTable(filtered);
    }
    function closeModal(e) { document.getElementById('statsModal').style.display = 'none'; }
    function copyToClipboard(btn) {
        const text = decodeURIComponent(btn.dataset.name || '');
        navigator.clipboard.writeText(text).then(() => { try { if (btn) { const prevTitle = btn.getAttribute('title'); const prevHtml = btn.innerHTML; btn.classList.add('copied'); btn.setAttribute('title', 'Copied!'); btn.innerHTML = checkIcon; setTimeout(() => { btn.classList.remove('copied'); btn.innerHTML = prevHtml; if (prevTitle) btn.setAttribute('title', prevTitle); else btn.removeAttribute('title'); }, 1500); } } catch (e) { console.error('Copy fallback', e); } }).catch(err => { console.error('Copy failed', err); });
    }
    function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
</script>
