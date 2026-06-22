/**
 * Dashboard page — loads KPIs, charts, map, and tables.
 */
(async function () {
    'use strict';

    let trendChart, formsChart, map;
    let mapMarkers = [];

    // ── Bootstrap ──────────────────────────────────────────
    async function load() {
        try {
            const [summary, mapData] = await Promise.all([
                API.get('/dashboard/summary'),
                API.get('/dashboard/map'),
            ]);

            renderStats(summary.data);
            renderRankings(summary.data.hospital_rankings);
            renderMissing(summary.data.missing_reports);

            await loadTrends('month');
            renderFormsPie(summary.data.submissions_by_form);
            initMap(mapData.data.hospitals);
            loadRecentSubmissions();
        } catch (err) {
            App.toast('Failed to load dashboard: ' + err.message, 'error');
        }
    }

    // ── Stats KPI cards ────────────────────────────────────
    function renderStats(data) {
        const grid = document.getElementById('stats-grid');
        const cards = [
            {
                label: 'Total Submissions',
                value: App.formatNumber(data.total_submissions),
                icon:  'clipboard',
                color: 'primary',
                sub:   `${App.formatNumber(data.today_submissions)} today`,
            },
            {
                label: 'This Month',
                value: App.formatNumber(data.month_submissions),
                icon:  'calendar',
                color: 'info',
                sub:   `${App.formatNumber(data.week_submissions)} this week`,
            },
            {
                label: 'Pending Review',
                value: App.formatNumber(data.pending_review),
                icon:  'clock',
                color: 'warning',
                sub:   'Awaiting review',
            },
            {
                label: 'Reporting Rate',
                value: data.reporting_rate + '%',
                icon:  'activity',
                color: data.reporting_rate >= 80 ? 'success' : 'warning',
                sub:   `${data.active_hospitals} / ${data.total_hospitals} facilities`,
            },
            {
                label: 'Approved',
                value: App.formatNumber(data.approved),
                icon:  'check',
                color: 'success',
                sub:   `${App.formatNumber(data.rejected)} rejected`,
            },
        ];

        grid.innerHTML = cards.map(c => `
            <div class="stat-card">
                <div class="stat-icon ${c.color}">
                    ${statIcon(c.icon)}
                </div>
                <div class="stat-body">
                    <div class="stat-value">${App.escHtml(c.value)}</div>
                    <div class="stat-label">${App.escHtml(c.label)}</div>
                    <div class="stat-change">${App.escHtml(c.sub)}</div>
                </div>
            </div>
        `).join('');
    }

    // ── Trend chart ────────────────────────────────────────
    async function loadTrends(period) {
        try {
            const res = await API.get('/dashboard/trends', { period });
            const trend = res.data.trend;

            const labels = trend.map(r => App.formatDate(r.period));
            const counts  = trend.map(r => parseInt(r.count));

            if (trendChart) trendChart.destroy();

            const ctx = document.getElementById('trend-chart').getContext('2d');
            trendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label:           'Submissions',
                        data:            counts,
                        borderColor:     '#1a5276',
                        backgroundColor: 'rgba(26,82,118,0.08)',
                        fill:            true,
                        tension:         0.4,
                        pointRadius:     3,
                        pointHoverRadius:5,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } },
                },
            });
        } catch (err) {
            App.toast('Could not load trend data', 'warning');
        }
    }

    // ── Form distribution pie ──────────────────────────────
    function renderFormsPie(byForm) {
        const labels = byForm.map(r => r.name || r.category || 'Other');
        const values = byForm.map(r => parseInt(r.count));
        const colors = ['#1a5276','#2e86c1','#27ae60','#e67e22','#8e44ad','#c0392b','#16a085','#2980b9'];

        if (formsChart) formsChart.destroy();
        const ctx = document.getElementById('forms-chart').getContext('2d');
        formsChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{ data: values, backgroundColor: colors.slice(0, values.length), borderWidth: 2 }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12 } },
                },
                cutout: '60%',
            },
        });
    }

    // ── Map ────────────────────────────────────────────────
    function initMap(hospitals) {
        map = L.map('map').setView([0, 20], 3);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 18,
        }).addTo(map);

        hospitals.forEach(h => {
            if (!h.latitude || !h.longitude) return;

            const count  = parseInt(h.submission_count || 0);
            const color  = count > 20 ? '#27ae60' : count > 5 ? '#e67e22' : '#c0392b';
            const marker = L.circleMarker([h.latitude, h.longitude], {
                radius:      count > 0 ? Math.min(8 + Math.log(count + 1) * 3, 20) : 6,
                fillColor:   color,
                color:       '#fff',
                weight:      2,
                opacity:     1,
                fillOpacity: 0.8,
            }).addTo(map);

            marker.bindPopup(`
                <strong>${App.escHtml(h.name)}</strong><br>
                Region: ${App.escHtml(h.region_name)}<br>
                Submissions (30d): ${App.formatNumber(count)}<br>
                Last submission: ${h.last_submission ? App.formatDate(h.last_submission) : 'Never'}
            `);
        });

        document.getElementById('map-info').textContent = `${hospitals.length} hospitals`;
    }

    // ── Rankings table ─────────────────────────────────────
    function renderRankings(rankings) {
        const tbody = document.getElementById('rankings-table');
        if (!rankings?.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted" style="padding:24px;">No data</td></tr>';
            return;
        }
        tbody.innerHTML = rankings.slice(0, 10).map((r, i) => `
            <tr>
                <td>${i + 1}</td>
                <td>
                    <div style="font-weight:500;">${App.escHtml(r.name)}</div>
                    <div class="text-muted">${App.escHtml(r.region)}</div>
                </td>
                <td>${App.formatNumber(r.submissions)}</td>
            </tr>
        `).join('');
    }

    // ── Missing reports table ──────────────────────────────
    function renderMissing(missing) {
        const tbody = document.getElementById('missing-table');
        if (!missing?.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted" style="padding:24px;">All hospitals reporting ✓</td></tr>';
            return;
        }
        tbody.innerHTML = missing.map(r => `
            <tr>
                <td>${App.escHtml(r.name)}</td>
                <td>${App.escHtml(r.region)}</td>
                <td class="text-danger">${r.last_submission ? App.formatDate(r.last_submission) : 'Never'}</td>
            </tr>
        `).join('');
    }

    // ── Recent submissions ─────────────────────────────────
    async function loadRecentSubmissions() {
        try {
            const res   = await API.get('/submissions', { per_page: 8 });
            const tbody = document.getElementById('recent-table');

            if (!res.data?.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted" style="padding:24px;">No submissions yet</td></tr>';
                return;
            }

            tbody.innerHTML = res.data.map(s => `
                <tr>
                    <td>${App.escHtml(s.form_name)}</td>
                    <td>${App.escHtml(s.hospital_name)}</td>
                    <td>${App.statusBadge(s.status)}</td>
                    <td class="text-muted">${App.formatDateTime(s.submitted_at)}</td>
                </tr>
            `).join('');
        } catch (err) {
            document.getElementById('recent-table').innerHTML =
                `<tr><td colspan="4" class="text-center text-danger" style="padding:24px;">Failed to load</td></tr>`;
        }
    }

    // ── Period filter ──────────────────────────────────────
    document.getElementById('period-select')?.addEventListener('change', function () {
        loadTrends(this.value);
    });

    document.getElementById('trend-by-day')?.addEventListener('click',  () => loadTrends('day'));
    document.getElementById('trend-by-week')?.addEventListener('click', () => loadTrends('week'));

    // ── Inline icon helper ─────────────────────────────────
    function statIcon(name) {
        const icons = {
            clipboard: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>',
            calendar:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
            clock:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            activity:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
            check:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><polyline points="20 6 9 17 4 12"/></svg>',
        };
        return icons[name] || '';
    }

    // ── Init ───────────────────────────────────────────────
    load();
})();
