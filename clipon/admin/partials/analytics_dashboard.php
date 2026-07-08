            <form class="filter-bar" method="GET">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <label style="font-size: 0.875rem; color: var(--text-secondary);"><?= __('analytics_period') ?>:</label>
                    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
                    <span>—</span>
                    <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
                </div>
                <button type="submit" class="btn-apply"><?= __('analytics_apply') ?></button>
                <div style="margin-left: auto; display: flex; gap: 0.5rem;">
                    <a href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>" class="bg-secondary-btn"><?= __('analytics_today') ?></a>
                    <a href="?from=<?= date('Y-m-d', strtotime('-30 days')) ?>&to=<?= date('Y-m-d') ?>" class="bg-secondary-btn"><?= __('analytics_last_30_days') ?></a>
                </div>
            </form>

            <?php
                $conv = $stats['conversions'] ?? ['total' => 0, 'pages' => [], 'types' => [], 'recent' => []];
                $convTotal = (int)($conv['total'] ?? 0);
                $convBase = (int)($stats['total_uniques'] ?? 0);
                $convCr = $convBase > 0 ? round(($convTotal / $convBase) * 100, 2) : 0;
            ?>

            <div class="stats-cards">
                <div class="stat-card">
                    <h3><?= __('analytics_total_hits') ?></h3>
                    <div class="value"><?= number_format($stats['total_hits']) ?></div>
                </div>
                <div class="stat-card">
                    <h3><?= __('analytics_unique_visitors') ?></h3>
                    <div class="value"><?= number_format($stats['total_uniques']) ?></div>
                </div>
                <div class="stat-card">
                    <h3><?= __('analytics_bounce_rate') ?></h3>
                    <?php
                        $totalEntries = (int)($stats['total_sessions'] ?? array_sum($stats['entry_pages'] ?? []));
                        $bounceCount = $stats['bounce_count'] ?? 0;
                        $bounceRate = ($totalEntries > 0) ? round(($bounceCount / $totalEntries) * 100, 1) : 0;
                    ?>
                    <div class="value"><?= $bounceRate ?>%</div>
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
                    <div class="value"><?= $avg ?>%</div>
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
                    <div class="value"><?= $displayTime ?></div>
                </div>
                <div class="stat-card">
                    <h3><?= __('analytics_conversions') ?></h3>
                    <div class="value"><?= number_format($convTotal) ?></div>
                </div>
                <div class="stat-card">
                    <h3><?= __('analytics_cr') ?></h3>
                    <div class="value"><?= $convCr ?>%</div>
                </div>
            </div>

            <div class="analytics-chart-card">
                <h2 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600;"><?= __('analytics_visits_graph') ?></h2>
                <div style="flex-grow: 1; position: relative; height: calc(100% - 2rem);">
                    <canvas id="visitsChart"></canvas>
                </div>
            </div>

            <div class="analytics-grid">
                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2><?= __('analytics_conversion_pages') ?></h2>
                        <button type="button" class="btn-show-all" onclick="showFullStats('conversion_pages')"><?= __('analytics_show_all') ?></button>
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
                                    <?= htmlspecialchars($url) ?>
                                    <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                                </td>
                                <td style="text-align: right; font-weight: 600; vertical-align: top;"><?= $count ?></td>
                            </tr>
                            <?php endforeach;
                        endif; ?>
                    </table>
                </div>

                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2><?= __('analytics_conversion_types') ?></h2>
                        <button type="button" class="btn-show-all" onclick="showFullStats('conversion_types')"><?= __('analytics_show_all') ?></button>
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
                                    <?= htmlspecialchars($type) ?>
                                    <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                                </td>
                                <td style="text-align: right; font-weight: 600; vertical-align: top;"><?= $count ?></td>
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
                                    $type = htmlspecialchars((string)($item['type'] ?? 'conversion'));
                                    $uri = htmlspecialchars((string)($item['uri'] ?? ''));
                                    $utm = $item['utm'] ?? [];
                                    $utmStr = [];
                                    foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $u) {
                                        if (!empty($utm[$u])) {
                                            $utmStr[] = $utm[$u];
                                        }
                                    }
                                    $utmDisplay = $utmStr ? htmlspecialchars(implode(' / ', $utmStr)) : '—';
                                    $refDisplay = htmlspecialchars((string)($item['ref'] ?? 'direct'));
                                ?>
                                <tr>
                                    <td><?= $timeStr ?></td>
                                    <td style="text-transform: capitalize; font-weight: 600; color: var(--text-primary);"><?= $type ?></td>
                                    <td style="word-break: break-all; color: var(--text-secondary);"><?= $uri ?></td>
                                    <td style="color: var(--text-secondary); word-break: break-all;"><?= $utmDisplay ?></td>
                                    <td style="text-align: right; color: var(--text-secondary);"><?= $refDisplay ?></td>
                                </tr>
                                <?php endforeach;
                            endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2><?= __('analytics_top_pages') ?></h2>
                        <button type="button" class="btn-show-all" onclick="showFullStats('pages')"><?= __('analytics_show_all') ?></button>
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
                                    <?= htmlspecialchars($url) ?>
                                    <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                                </td>
                                <td style="text-align: right; font-weight: 600; vertical-align: top;"><?= $count ?></td>
                            </tr>
                            <?php endforeach;
                        endif; ?>
                    </table>
                </div>

                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2><?= __('analytics_top_referrers') ?></h2>
                        <button type="button" class="btn-show-all" onclick="showFullStats('referrers')"><?= __('analytics_show_all') ?></button>
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
                                    <?= htmlspecialchars($ref) ?>
                                    <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                                </td>
                                <td style="text-align: right; font-weight: 600; vertical-align: top;"><?= $count ?></td>
                            </tr>
                            <?php endforeach;
                        endif; ?>
                    </table>
                </div>

                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2><?= __('analytics_devices') ?></h2>
                        <button type="button" class="btn-show-all" onclick="showFullStats('devices')"><?= __('analytics_show_all') ?></button>
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
                                    <?= htmlspecialchars(ucfirst($dev)) ?>
                                    <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                                </td>
                                <td style="text-align: right; font-weight: 600; vertical-align: top;"><?= $count ?></td>
                            </tr>
                            <?php endforeach;
                        endif; ?>
                    </table>
                </div>

                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2><?= __('analytics_languages') ?></h2>
                        <button type="button" class="btn-show-all" onclick="showFullStats('languages')"><?= __('analytics_show_all') ?></button>
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
                                    <?= htmlspecialchars(strtoupper($lang)) ?>
                                    <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                                </td>
                                <td style="text-align: right; font-weight: 600; vertical-align: top;"><?= $count ?></td>
                            </tr>
                            <?php endforeach;
                        endif; ?>
                    </table>
                </div>

                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2><?= __('analytics_geography') ?></h2>
                        <button type="button" class="btn-show-all" onclick="showFullStats('countries')"><?= __('analytics_show_all') ?></button>
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
                                    <span style="font-family: monospace;"><?= $country ?></span>
                                    <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                                </td>
                                <td style="text-align: right; font-weight: 600; vertical-align: top;"><?= $count ?></td>
                            </tr>
                            <?php endforeach;
                        endif; ?>
                    </table>
                </div>

                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2><?= __('analytics_entry_pages') ?></h2>
                        <button type="button" class="btn-show-all" onclick="showFullStats('entry_pages')"><?= __('analytics_show_all') ?></button>
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
                                    <?= htmlspecialchars($url) ?>
                                    <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                                </td>
                                <td style="text-align: right; font-weight: 600;"><?= $count ?></td>
                            </tr>
                            <?php endforeach;
                        endif; ?>
                    </table>
                </div>

                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2><?= __('analytics_exit_pages') ?></h2>
                        <button type="button" class="btn-show-all" onclick="showFullStats('exit_pages')"><?= __('analytics_show_all') ?></button>
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
                                    <?= htmlspecialchars($url) ?>
                                    <div class="bar-bg" style="margin-top: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                                </td>
                                <td style="text-align: right; font-weight: 600;"><?= $count ?></td>
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
                                    <button type="button" class="btn-show-all" onclick="showFullStats('utm:<?= $type ?>')"><?= __('analytics_show_all') ?></button>
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
                                                <?= htmlspecialchars($val) ?>
                                                <div class="bar-bg" style="height: 4px;"><div class="bar-fill" style="width: <?= $percent ?>%"></div></div>
                                            </td>
                                            <td style="padding: 0.5rem; text-align: right; font-weight: 600; font-size: 0.75rem;"><?= $count ?></td>
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
                        <button type="button" class="btn-show-all" onclick="showFullStats('time_on_page')"><?= __('analytics_show_all') ?></button>
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
                                    <td style="word-break: break-all;"><?= htmlspecialchars($url) ?></td>
                                    <td style="text-align: right; font-weight: 600;"><?= $formatted ?></td>
                                </tr>
                                <?php endforeach;
                            endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2><?= __('analytics_page_engagement') ?> (<?= __('analytics_avg_scroll') ?>)</h2>
                        <button type="button" class="btn-show-all" onclick="showFullStats('events:scroll')"><?= __('analytics_show_all') ?></button>
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
                                if (!is_array($data)) {
                                    continue;
                                }
                                $tS = array_sum($data);
                                if ($tS === 0) {
                                    continue;
                                }
                                $sSum = (($data['25%'] ?? 0) * 25 + ($data['50%'] ?? 0) * 50 + ($data['75%'] ?? 0) * 75 + ($data['100%'] ?? 0) * 100);
                                $pageScrolls[$url] = [
                                    'avg' => round($sSum / $tS),
                                    'data' => $data,
                                    'total' => $tS,
                                ];
                            }
                            uasort($pageScrolls, function($a, $b) { return $b['total'] <=> $a['total']; });

                            if (empty($pageScrolls)): ?>
                                <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;"><?= __('no_details') ?></td></tr>
                            <?php else:
                                foreach (array_slice($pageScrolls, 0, 15) as $url => $info): ?>
                                <tr>
                                    <td style="word-break: break-all; font-weight: 500;"><?= htmlspecialchars($url) ?></td>
                                    <td style="text-align: center; color: var(--text-secondary);"><?= $info['data']['25%'] ?? 0 ?></td>
                                    <td style="text-align: center; color: var(--text-secondary);"><?= $info['data']['50%'] ?? 0 ?></td>
                                    <td style="text-align: center; color: var(--text-secondary);"><?= $info['data']['75%'] ?? 0 ?></td>
                                    <td style="text-align: center; color: var(--text-secondary);"><?= $info['data']['100%'] ?? 0 ?></td>
                                    <td style="text-align: right;">
                                        <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-weight: 600; background: <?= $info['avg'] > 50 ? '#ecfdf5' : '#fff7ed' ?>; color: <?= $info['avg'] > 50 ? '#059669' : '#d97706' ?>;">
                                            <?= $info['avg'] ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach;
                            endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
