    <script>
        let currentMetric = '';

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
                const dateStr = current.getFullYear() + '-'
                    + String(current.getMonth() + 1).padStart(2, '0') + '-'
                    + String(current.getDate()).padStart(2, '0');
                labels.push(dateStr);
                hitsData.push(daily[dateStr]?.hits || 0);
                viewsData.push(daily[dateStr]?.uniques || 0);
                convData.push(daily[dateStr]?.conversions || 0);
                current.setDate(current.getDate() + 1);
            }

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: '<?= __('analytics_total_hits') ?>',
                            data: hitsData,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.05)',
                            fill: true,
                            tension: 0.3,
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                        },
                        {
                            label: '<?= __('analytics_unique_visitors') ?>',
                            data: viewsData,
                            borderColor: '#10b981',
                            backgroundColor: 'transparent',
                            fill: false,
                            tension: 0.3,
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                        },
                        {
                            label: '<?= __('analytics_conversions') ?>',
                            data: convData,
                            borderColor: '#f97316',
                            backgroundColor: 'rgba(249, 115, 22, 0.08)',
                            fill: true,
                            tension: 0.3,
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                        },
                    ],
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
                                },
                            },
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            padding: 12,
                            backgroundColor: 'rgba(255, 255, 255, 0.95)',
                            titleColor: '#1f2937',
                            bodyColor: '#4b5563',
                            borderColor: '#e5e7eb',
                            borderWidth: 1,
                            titleFont: { weight: 'bold' },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f3f4f6' },
                            ticks: {
                                stepSize: 1,
                                font: { size: 11 },
                            },
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 11 },
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 10,
                            },
                        },
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false,
                    },
                },
            });
        }

        document.addEventListener('DOMContentLoaded', initChart);

        function showFullStats(metric) {
            currentMetric = metric;
            const modal = document.getElementById('statsModal');
            const title = document.getElementById('modalTitle');
            const head = document.getElementById('modalTableHead');
            const search = document.getElementById('modalSearch');

            search.value = '';
            modal.style.display = 'flex';

            let data = {};
            const titles = {
                conversion_pages: '<?= __('analytics_conversion_pages') ?>',
                conversion_types: '<?= __('analytics_conversion_types') ?>',
                conversion_recent: '<?= __('analytics_recent_conversions') ?>',
                languages: '<?= __('analytics_languages') ?>',
                devices: '<?= __('analytics_devices') ?>',
                referrers: '<?= __('analytics_top_referrers') ?>',
                countries: '<?= __('analytics_geography') ?>',
            };
            const displayTitle = titles[metric] ?? metric.replace('_', ' ').replace(':', ' - ').toUpperCase();

            if (metric === 'conversion_pages') {
                data = (allStats.conversions && allStats.conversions.pages) ? allStats.conversions.pages : {};
            } else if (metric === 'conversion_types') {
                data = (allStats.conversions && allStats.conversions.types) ? allStats.conversions.types : {};
            } else if (metric === 'conversion_recent') {
                data = (allStats.conversions && allStats.conversions.recent) ? allStats.conversions.recent : [];
            } else if (metric.includes(':')) {
                const parts = metric.split(':');
                data = allStats[parts[0]] ? (allStats[parts[0]][parts[1]] || {}) : {};
            } else {
                data = allStats[metric] || {};
            }

            title.innerText = displayTitle;

            if (metric === 'conversion_recent') {
                head.innerHTML = `
                    <th><?= __('analytics_time') ?></th>
                    <th><?= __('conversion_type') ?></th>
                    <th><?= __('analytics_page') ?></th>
                    <th><?= __('analytics_utm') ?></th>
                    <th style="text-align: right;"><?= __('analytics_referrer') ?></th>
                `;
                renderRecentTable(Array.isArray(data) ? data : []);
                return;
            }

            if (metric === 'events:scroll') {
                head.innerHTML = `
                    <th>Page</th>
                    <th style="text-align: right;">Avg. Scroll</th>
                `;
                const processed = {};
                for (const [url, counts] of Object.entries(data)) {
                    if (typeof counts !== 'object') {
                        continue;
                    }
                    const total = Object.values(counts).reduce((a, b) => a + b, 0);
                    if (total === 0) {
                        continue;
                    }
                    const sum = (counts['25%'] || 0) * 25 + (counts['50%'] || 0) * 50 + (counts['75%'] || 0) * 75 + (counts['100%'] || 0) * 100;
                    processed[url] = Math.round(sum / total);
                }
                renderModalTable(processed, true);
                return;
            }

            if (metric === 'time_on_page') {
                head.innerHTML = `
                    <th>Page</th>
                    <th style="text-align: right;">Avg. Time</th>
                `;
                const processed = {};
                for (const [url, info] of Object.entries(data)) {
                    processed[url] = (info && info.c > 0) ? Math.round(info.t / info.c) : 0;
                }
                renderModalTable(processed, false, true);
                return;
            }

            head.innerHTML = `
                <th>Name</th>
                <th style="text-align: right;">Count</th>
            `;
            renderModalTable(data);
        }

        function renderModalTable(data, isPercentage = false, isTime = false) {
            const body = document.getElementById('modalTableBody');
            body.innerHTML = '';

            const items = Object.entries(data).sort((a, b) => {
                const valA = typeof a[1] === 'number' ? a[1] : parseInt(a[1], 10);
                const valB = typeof b[1] === 'number' ? b[1] : parseInt(b[1], 10);
                return valB - valA;
            });

            const formatTime = (sec) => {
                const m = Math.floor(sec / 60);
                const s = sec % 60;
                return m > 0 ? `${m}m ${s}s` : `${s}s`;
            };

            items.forEach(([name, count]) => {
                const tr = document.createElement('tr');
                let displayCount = count.toLocaleString();
                if (isPercentage) {
                    displayCount = count + '%';
                }
                if (isTime) {
                    displayCount = formatTime(count);
                }

                tr.innerHTML = `
                    <td style="word-break: break-all; display: flex; align-items: center; gap: 8px;">
                        <div style="flex: 1;">${escapeHtml(name)}</div>
                        <button class="copy-hint" data-tooltip="Copy" onclick="copyToClipboard(this)" data-name="${encodeURIComponent(name)}" aria-label="Copy">
                            ${copyIcon}
                        </button>
                    </td>
                    <td style="text-align: right; font-weight: 600;">${displayCount}</td>
                `;
                body.appendChild(tr);
            });
        }

        function renderRecentTable(list) {
            const body = document.getElementById('modalTableBody');
            body.innerHTML = '';
            const items = [...list].sort((a, b) => (b.ts || 0) - (a.ts || 0));

            if (!items.length) {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td colspan="5" style="text-align:center; color: var(--text-muted); padding: 1rem;"><?= __('no_details') ?></td>`;
                body.appendChild(tr);
                return;
            }

            items.forEach((item) => {
                const ts = item.ts ? new Date(item.ts * 1000) : null;
                const tsStr = ts
                    ? `${ts.getFullYear()}-${String(ts.getMonth() + 1).padStart(2, '0')}-${String(ts.getDate()).padStart(2, '0')} ${String(ts.getHours()).padStart(2, '0')}:${String(ts.getMinutes()).padStart(2, '0')}`
                    : '-';
                const type = item.type || 'conversion';
                const uri = item.uri || '';
                const utm = item.utm || {};
                const utmParts = ['utm_source', 'utm_medium', 'utm_campaign'].map((k) => utm[k]).filter(Boolean);
                const utmStr = utmParts.length ? utmParts.join(' / ') : '—';
                const ref = item.ref || 'direct';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${tsStr}</td>
                    <td style="text-transform: capitalize; font-weight: 600;">${escapeHtml(type)}</td>
                    <td style="word-break: break-all;">${escapeHtml(uri)}</td>
                    <td style="word-break: break-all; color: var(--text-secondary);">${escapeHtml(utmStr)}</td>
                    <td style="text-align: right; color: var(--text-secondary);">${escapeHtml(ref)}</td>
                `;
                body.appendChild(tr);
            });
        }

        function filterModalTable() {
            const query = document.getElementById('modalSearch').value.toLowerCase();
            let data = {};

            if (currentMetric === 'conversion_pages') {
                data = (allStats.conversions && allStats.conversions.pages) ? allStats.conversions.pages : {};
            } else if (currentMetric === 'conversion_types') {
                data = (allStats.conversions && allStats.conversions.types) ? allStats.conversions.types : {};
            } else if (currentMetric === 'conversion_recent') {
                const list = (allStats.conversions && Array.isArray(allStats.conversions.recent)) ? allStats.conversions.recent : [];
                const filtered = list.filter((item) => {
                    const uri = (item.uri || '').toLowerCase();
                    const type = (item.type || '').toLowerCase();
                    const ref = (item.ref || '').toLowerCase();
                    const utm = item.utm || {};
                    const utmStr = ['utm_source', 'utm_medium', 'utm_campaign'].map((k) => utm[k] || '').join(' ').toLowerCase();
                    return uri.includes(query) || type.includes(query) || ref.includes(query) || utmStr.includes(query);
                });
                renderRecentTable(filtered);
                return;
            } else if (currentMetric.includes(':')) {
                const parts = currentMetric.split(':');
                data = allStats[parts[0]] ? (allStats[parts[0]][parts[1]] || {}) : {};
            } else {
                data = allStats[currentMetric] || {};
            }

            if (currentMetric === 'events:scroll') {
                const processed = {};
                for (const [url, counts] of Object.entries(data)) {
                    if (url.toLowerCase().includes(query)) {
                        const total = Object.values(counts).reduce((a, b) => a + b, 0);
                        if (total === 0) {
                            continue;
                        }
                        const sum = (counts['25%'] || 0) * 25 + (counts['50%'] || 0) * 50 + (counts['75%'] || 0) * 75 + (counts['100%'] || 0) * 100;
                        processed[url] = Math.round(sum / total);
                    }
                }
                renderModalTable(processed, true);
                return;
            }

            if (currentMetric === 'time_on_page') {
                const processed = {};
                for (const [url, info] of Object.entries(data)) {
                    if (url.toLowerCase().includes(query)) {
                        processed[url] = (info && info.c > 0) ? Math.round(info.t / info.c) : 0;
                    }
                }
                renderModalTable(processed, false, true);
                return;
            }

            const filtered = {};
            for (const [name, count] of Object.entries(data)) {
                if (name.toLowerCase().includes(query)) {
                    filtered[name] = count;
                }
            }
            renderModalTable(filtered);
        }

        function closeModal() {
            document.getElementById('statsModal').style.display = 'none';
        }

        function copyToClipboard(btn) {
            const text = decodeURIComponent(btn.dataset.name || '');
            navigator.clipboard.writeText(text).then(() => {
                try {
                    if (btn) {
                        const prevTitle = btn.getAttribute('title');
                        const prevHtml = btn.innerHTML;
                        btn.classList.add('copied');
                        btn.setAttribute('title', 'Copied!');
                        btn.innerHTML = checkIcon;
                        setTimeout(() => {
                            btn.classList.remove('copied');
                            btn.innerHTML = prevHtml;
                            if (prevTitle) {
                                btn.setAttribute('title', prevTitle);
                            } else {
                                btn.removeAttribute('title');
                            }
                        }, 1500);
                    }
                } catch (err) {
                    console.error('Copy fallback', err);
                }
            }).catch((err) => {
                console.error('Copy failed', err);
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
