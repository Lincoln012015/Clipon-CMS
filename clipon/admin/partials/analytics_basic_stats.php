<?php
/**
 * Компонент дашборду аналітики з базовими метриками.
 * 
 * @var array $stats
 * @var string $from
 * @var string $to
 */
?>
<div class="analytics-dashboard">
    <?php include __DIR__ . '/analytics_filter.php'; ?>

    <div class="stats-cards">
        <div class="stat-card">
            <h3><?= __('analytics_total_hits') ?></h3>
            <div class="value"><?= number_format($stats['total_hits'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <h3><?= __('analytics_unique_visitors') ?></h3>
            <div class="value"><?= number_format($stats['total_uniques'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <h3><?= __('analytics_avg_hits_per_visitor') ?? 'Хітів на відвідувача' ?></h3>
            <?php 
                $avg = ($stats['total_uniques'] > 0) ? round($stats['total_hits'] / $stats['total_uniques'], 1) : 0;
            ?>
            <div class="value"><?= $avg ?></div>
        </div>
    </div>

    <div class="analytics-grid">
        <div class="analytics-chart-card" style="margin-bottom: 0; min-height: 350px;">
            <h2 style="margin: 0 0 1.25rem 0; font-size: 0.875rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.025em;"><?= __('analytics_visits_graph') ?></h2>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="basicVisitsChart"></canvas>
            </div>
        </div>

        <div class="analytics-card">
            <div class="analytics-card-header" style="justify-content: space-between; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <h2><?= __('analytics_top_pages') ?? 'Топ сторінок' ?></h2>
                    <span style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 500;"><?= __('analytics_top_5') ?? 'Топ 5' ?></span>
                </div>
                <a href="analytics.php#analytics-top-pages" class="btn-show-all"><?= __('analytics_show_all') ?></a>
            </div>
            <div style="flex-grow: 1; overflow-y: auto;">
                <table class="analytics-table">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid var(--border-color);">
                            <th style="padding: 0.75rem 1.5rem; text-align: left; font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;"><?= __('page') ?? 'Сторінка' ?></th>
                            <th style="padding: 0.75rem 1.5rem; text-align: right; font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; width: 80px;"><?= __('views') ?? 'Перегляди' ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stats['top_pages'])): ?>
                            <tr><td colspan="2" style="text-align: center; color: var(--text-secondary); padding: 2rem;"><?= __('no_data') ?? 'Немає даних' ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($stats['top_pages'] as $url => $hits): ?>
                            <tr>
                                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <a href="<?= htmlspecialchars($url) ?>" target="_blank" style="color: var(--primary-color); text-decoration: none;"><?= htmlspecialchars($url) ?></a>
                                </td>
                                <td style="text-align: right; font-weight: 600;"><?= number_format($hits) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="<?= C_ASSETS_URL ?>/vendor/chartjs/chart.min.js"></script>
    <script>
        (function() {
            const ctx = document.getElementById('basicVisitsChart').getContext('2d');
            const data = <?= json_encode($stats['daily'] ?? []) ?>;
            const labels = Object.keys(data);
            const hits = labels.map(l => data[l].hits);
            const uniques = labels.map(l => data[l].uniques);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels.map(d => d.split('-').slice(1).join('.')),
                    datasets: [
                        { 
                            label: '<?= __('analytics_total_hits') ?>', 
                            data: hits, 
                            borderColor: '#3b82f6', 
                            backgroundColor: 'rgba(59, 130, 246, 0.05)',
                            borderWidth: 2,
                            pointRadius: 3,
                            pointBackgroundColor: '#3b82f6',
                            tension: 0.3, 
                            fill: true 
                        },
                        { 
                            label: '<?= __('analytics_unique_visitors') ?>', 
                            data: uniques, 
                            borderColor: '#10b981', 
                            backgroundColor: 'rgba(16, 185, 129, 0.05)',
                            borderWidth: 2,
                            pointRadius: 3,
                            pointBackgroundColor: '#10b981',
                            tension: 0.3, 
                            fill: true 
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: { 
                        legend: { 
                            display: true,
                            position: 'top',
                            align: 'end',
                            labels: {
                                boxWidth: 12,
                                boxHeight: 12,
                                usePointStyle: true,
                                padding: 20,
                                font: { size: 11 }
                            }
                        },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 12,
                            titleFont: { size: 13 },
                            bodyFont: { size: 12 },
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            grid: { color: '#f1f5f9', drawBorder: false },
                            ticks: { font: { size: 11 }, color: '#64748b' }
                        },
                        x: { 
                            grid: { display: false },
                            ticks: { font: { size: 11 }, color: '#64748b' }
                        }
                    }
                }
            });
        })();
    </script>
</div>
