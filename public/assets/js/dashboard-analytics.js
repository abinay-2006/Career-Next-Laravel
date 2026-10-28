document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('.cn-analytics');
    if (!root || !window.Chart || !window.cnDashboardAnalytics) return;

    const initial = window.cnDashboardAnalytics;
    const rangeSelect = root.querySelector('#analyticsRange');
    const lastUpdated = root.querySelector('[data-last-updated]');
    const refreshError = root.querySelector('[data-refresh-error]');
    const role = root.dataset.role;
    const colors = ['#a5b4fc', '#6ee7b7', '#fcd34d', '#fda4af', '#7dd3fc', '#c4b5fd', '#fdba74'];
    const labels = value => ({ pending: 'Pending', accepted: 'Accepted', approved: 'Approved', rejected: 'Rejected', declined: 'Declined', active: 'Active', unknown: 'Unknown' }[value] || value.replace(/\b\w/g, letter => letter.toUpperCase()));
    const options = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { color: 'rgba(255,255,255,.82)', usePointStyle: true, padding: 18 } }, tooltip: { enabled: true } },
    };
    const trendChart = new Chart(root.querySelector('#activityTrendChart'), {
        type: 'line',
        data: { labels: initial.trend.labels, datasets: [{ label: 'Applications', data: initial.trend.values, borderColor: '#c4b5fd', backgroundColor: 'rgba(196,181,253,.17)', fill: true, tension: .35, pointRadius: 2.5, pointHoverRadius: 5 }] },
        options: { ...options, scales: { x: { ticks: { color: 'rgba(255,255,255,.68)', maxTicksLimit: 9 }, grid: { display: false } }, y: { beginAtZero: true, ticks: { color: 'rgba(255,255,255,.68)', precision: 0 }, grid: { color: 'rgba(255,255,255,.1)' } } } },
    });

    const jobApplicationsCanvas = root.querySelector('#jobApplicationsChart');
    const jobApplicationsChart = jobApplicationsCanvas ? new Chart(jobApplicationsCanvas, {
        type: 'bar',
        data: { labels: initial.applicationsByJob?.labels || [], datasets: [{ label: 'Applications', data: initial.applicationsByJob?.values || [], backgroundColor: colors[0], borderRadius: 7 }] },
        options: { ...options, scales: { x: { ticks: { color: 'rgba(255,255,255,.68)', maxRotation: 35, minRotation: 0 }, grid: { display: false } }, y: { beginAtZero: true, ticks: { color: 'rgba(255,255,255,.68)', precision: 0 }, grid: { color: 'rgba(255,255,255,.1)' } } }, plugins: { ...options.plugins, legend: { display: false } } },
    }) : null;

    const doughnut = (canvasId, data) => {
        const canvas = root.querySelector(`#${canvasId}`);
        if (!canvas) return null;
        return new Chart(canvas, {
            type: 'doughnut',
            data: { labels: Object.keys(data).map(labels), datasets: [{ data: Object.values(data), backgroundColor: colors, borderColor: 'rgba(255,255,255,.12)', borderWidth: 2, hoverOffset: 5 }] },
            options: { ...options, cutout: '66%' },
        });
    };
    const jobChart = role === 'employer' ? doughnut('jobStatusChart', initial.jobStatuses || {}) : null;
    const applicationChart = doughnut('applicationStatusChart', initial.applicationStatuses || {});

    const setEmpty = (name, empty) => {
        const canvas = root.querySelector(name === 'trend' ? '#activityTrendChart' : name === 'jobs' ? '#jobStatusChart' : '#applicationStatusChart');
        const message = root.querySelector(`[data-empty-for="${name}"]`);
        if (canvas) canvas.hidden = empty;
        if (message) message.hidden = !empty;
    };
    const updateDoughnut = (chart, values, emptyName) => {
        if (!chart) return;
        chart.data.labels = Object.keys(values || {}).map(labels);
        chart.data.datasets[0].data = Object.values(values || {});
        chart.update();
        setEmpty(emptyName, Object.values(values || {}).every(value => Number(value) === 0));
    };
    const textNode = (tag, text) => {
        const node = document.createElement(tag);
        node.textContent = text ?? '';
        return node;
    };
    const statusCell = status => {
        const cell = document.createElement('td');
        const pill = textNode('span', status || 'Unknown');
        pill.className = 'cn-status-pill';
        cell.append(pill);
        return cell;
    };
    const row = (cells, status) => {
        const tr = document.createElement('tr');
        cells.forEach(value => tr.append(textNode('td', value)));
        tr.append(statusCell(status));
        return tr;
    };
    const renderRecent = data => {
        const applicationsBody = root.querySelector('[data-recent-applications]');
        if (applicationsBody) {
            applicationsBody.replaceChildren();
            (data.recentApplications || []).forEach(application => {
                const tr = document.createElement('tr');
                const titleCell = document.createElement('td');
                if (role === 'jobseeker') {
                    const link = textNode('a', application.title);
                    link.href = root.dataset.applicationUrl.replace('__APPLICATION__', encodeURIComponent(application.id));
                    titleCell.append(link);
                } else titleCell.textContent = application.title || '';
                tr.append(titleCell);
                if (role === 'jobseeker') tr.append(textNode('td', application.company || '—'));
                tr.append(textNode('td', application.date ? new Date(application.date).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : 'Date unavailable'));
                tr.append(statusCell(application.status));
                applicationsBody.append(tr);
            });
            if (!(data.recentApplications || []).length) {
                const tr = document.createElement('tr');
                const td = textNode('td', role === 'employer' ? 'No applications received yet.' : 'Your submitted applications will appear here.');
                td.colSpan = role === 'employer' ? 3 : 4;
                td.className = 'cn-analytics-empty-row';
                tr.append(td);
                applicationsBody.append(tr);
            }
        }
        const jobsBody = root.querySelector('[data-recent-jobs]');
        if (jobsBody) {
            jobsBody.replaceChildren();
            (data.recentJobs || []).forEach(job => jobsBody.append(row([job.title], job.status)));
            if (!(data.recentJobs || []).length) {
                const tr = document.createElement('tr');
                const td = textNode('td', 'You have not posted any jobs yet.');
                td.colSpan = 2;
                td.className = 'cn-analytics-empty-row';
                tr.append(td);
                jobsBody.append(tr);
            }
        }
    };
    const update = data => {
        data.cards.forEach((card, index) => {
            const value = root.querySelector(`[data-stat-value="${index}"]`);
            const detail = root.querySelector(`[data-stat-detail="${index}"]`);
            if (value) value.textContent = card.value;
            if (detail) detail.textContent = card.detail;
        });
        trendChart.data.labels = data.trend.labels;
        trendChart.data.datasets[0].data = data.trend.values;
        trendChart.update();
        setEmpty('trend', data.trend.values.every(value => Number(value) === 0));
        updateDoughnut(jobChart, data.jobStatuses || {}, 'jobs');
        updateDoughnut(applicationChart, data.applicationStatuses || {}, 'applications');
        if (jobApplicationsChart) {
            jobApplicationsChart.data.labels = data.applicationsByJob?.labels || [];
            jobApplicationsChart.data.datasets[0].data = data.applicationsByJob?.values || [];
            jobApplicationsChart.update();
            const empty = !(data.applicationsByJob?.values || []).some(value => Number(value) > 0);
            const canvas = root.querySelector('#jobApplicationsChart');
            const message = root.querySelector('[data-empty-for="job-applications"]');
            canvas.hidden = empty;
            message.hidden = !empty;
        }
        const trendCaption = root.querySelector('[data-trend-caption]');
        if (trendCaption) trendCaption.textContent = data.range === 'all' ? 'Monthly activity' : 'Daily activity';
        const statusCaption = root.querySelector('[data-status-caption]');
        if (statusCaption) statusCaption.textContent = data.range === 'all' ? 'All time' : `Last ${data.range} days`;
        renderRecent(data);
        const timestamp = new Date(data.updatedAt);
        lastUpdated.dateTime = data.updatedAt;
        lastUpdated.textContent = timestamp.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        refreshError.hidden = true;
    };

    setEmpty('trend', initial.trend.values.every(value => Number(value) === 0));
    setEmpty('jobs', Object.values(initial.jobStatuses || {}).every(value => Number(value) === 0));
    setEmpty('applications', Object.values(initial.applicationStatuses || {}).every(value => Number(value) === 0));
    if (jobApplicationsChart) {
        const empty = !(initial.applicationsByJob?.values || []).some(value => Number(value) > 0);
        root.querySelector('#jobApplicationsChart').hidden = empty;
        root.querySelector('[data-empty-for="job-applications"]').hidden = !empty;
    }
    let refreshing = false;
    let timer = null;
    const refresh = async () => {
        if (refreshing || document.hidden) return;
        refreshing = true;
        try {
            const url = new URL(root.dataset.analyticsUrl, window.location.origin);
            url.searchParams.set('range', rangeSelect.value);
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error(`Analytics refresh failed (${response.status})`);
            update(await response.json());
        } catch (error) {
            refreshError.hidden = false;
        } finally {
            refreshing = false;
        }
    };
    const schedule = () => {
        window.clearInterval(timer);
        if (!document.hidden) timer = window.setInterval(refresh, 45000);
    };
    rangeSelect.addEventListener('change', refresh);
    document.addEventListener('visibilitychange', () => {
        schedule();
        if (!document.hidden) refresh();
    });
    schedule();
});
