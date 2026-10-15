import {
    Chart, BarController, BarElement, LineController, LineElement, PointElement, DoughnutController, ArcElement,
    CategoryScale, LinearScale, Tooltip, Legend, Filler,
} from 'chart.js';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement, DoughnutController, ArcElement,
    CategoryScale, LinearScale, Tooltip, Legend, Filler);

const PALETTE = ['#2d2de8', '#f4511e', '#10b981', '#ec4899', '#0ea5e9', '#eab308', '#8b5cf6', '#64748b'];
Chart.defaults.font.family = "'Poppins','Hind Siliguri',sans-serif";
Chart.defaults.color = '#4b5563';
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.pointStyle = 'circle';
Chart.defaults.plugins.legend.labels.boxWidth = 10;
const charts = {};

function brand() {
    return getComputedStyle(document.body).getPropertyValue('--brand').trim() || PALETTE[0];
}

function setState(box, state, message = '') {
    const el = box.querySelector('.chart-state');
    const canvas = box.querySelector('canvas');
    if (!el) return;
    el.innerHTML = state === 'ready' ? '' : state === 'loading'
        ? '<div><div class="spinner-border text-secondary" role="status"></div><div class="small mt-2">Loading…</div></div>'
        : `<div><i class="bi ${state === 'error' ? 'bi-exclamation-triangle text-danger' : 'bi-bar-chart'} fs-3 d-block mb-1"></i><div class="small">${message}</div></div>`;
    el.style.display = state === 'ready' ? 'none' : 'grid';
    if (canvas) canvas.style.visibility = state === 'ready' ? 'visible' : 'hidden';
}

function isEmpty(chartData) {
    if (!chartData) return true;
    if (Array.isArray(chartData.data)) return chartData.data.every((v) => !v);
    if (Array.isArray(chartData.datasets)) return chartData.datasets.every((d) => d.data.every((v) => !v));
    return true;
}

function render(id, config) {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    charts[id]?.destroy();
    charts[id] = new Chart(canvas, config);
}

function datasetsFrom(chartData, type) {
    return chartData.datasets.map((d, i) => ({
        ...d,
        backgroundColor: type === 'line' ? `${PALETTE[i % PALETTE.length]}22` : PALETTE[i % PALETTE.length],
        borderColor: PALETTE[i % PALETTE.length],
        fill: type === 'line',
        tension: 0.35,
        borderWidth: type === 'line' ? 2.5 : 0,
        pointBackgroundColor: '#fff',
        pointBorderColor: PALETTE[i % PALETTE.length],
        pointRadius: type === 'line' ? 4 : 0,
        pointHoverRadius: 6,
        borderRadius: type === 'bar' ? 4 : 0,
    }));
}

const singleBar = (label) => (id, d) => render(id, {
    type: 'bar',
    data: { labels: d.labels, datasets: [{ label, data: d.data, backgroundColor: brand(), borderRadius: 4 }] },
    options: {
        responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { autoSkip: false, maxRotation: 45 } } },
        onClick: (_e, items) => {
            if (!items.length || !d.branch_ids) return;
            const url = new URL(window.location.href);
            url.searchParams.set('branch_id', d.branch_ids[items[0].index]);
            window.location.href = url.toString();
        },
    },
});

const percentBar = (label) => (id, d) => render(id, {
    type: 'bar',
    data: { labels: d.labels, datasets: [{ label, data: d.data, backgroundColor: d.data.map((v) => (v >= 90 ? '#10b981' : v >= 75 ? '#eab308' : '#ef4444')), borderRadius: 4 }] },
    options: {
        responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => `${c.parsed.y}%` } } },
        scales: { y: { beginAtZero: true, max: 100, ticks: { callback: (v) => `${v}%` } }, x: { ticks: { autoSkip: false, maxRotation: 45 } } },
        onClick: (_e, items) => {
            if (!items.length || !d.branch_ids) return;
            const url = new URL(window.location.href);
            url.searchParams.set('branch_id', d.branch_ids[items[0].index]);
            window.location.href = url.toString();
        },
    },
});

const RENDERERS = {
    branch_attendance: percentBar('Attendance %'),
    class_attendance: percentBar('Attendance %'),
    attendance_trend: (id, d) => render(id, {
        type: 'line',
        data: { labels: d.labels, datasets: datasetsFrom(d, 'line').map((ds) => ({ ...ds, spanGaps: true })) },
        options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            plugins: { tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${c.parsed.y ?? '—'}%` } } },
            scales: { y: { beginAtZero: true, max: 100, ticks: { callback: (v) => `${v}%` } } } },
    }),
    staff_status_today: (id, d) => render(id, {
        type: 'doughnut',
        data: { labels: d.labels, datasets: [{ data: d.data, backgroundColor: ['#10b981', '#ef4444', '#eab308', '#0ea5e9', '#6366f1', '#94a3b8', '#e2e8f0'] }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '60%' },
    }),
    branch_students: singleBar('Students'),
    students_by_class: singleBar('Students'),
    exam_grades: singleBar('Students'),
    fee_collection: (id, d) => render(id, {
        type: 'bar',
        data: { labels: d.labels, datasets: datasetsFrom(d, 'bar') },
        options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            plugins: { tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ৳${Number(c.parsed.y).toLocaleString()}` } } },
            scales: { y: { beginAtZero: true, ticks: { callback: (v) => `৳${Number(v).toLocaleString()}` } } } },
    }),
    exam_pass_by_class: percentBar('Pass %'),
    admission_trend: (id, d) => render(id, {
        type: 'line',
        data: { labels: d.labels, datasets: datasetsFrom(d, 'line') },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
    }),
    branch_accounts: (id, d, el) => render(id, {
        type: 'bar',
        data: { labels: d.labels, datasets: datasetsFrom(d, 'bar') },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { x: { stacked: true, ticks: { autoSkip: false, maxRotation: 45 } }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } },
            // Drill-down: click a bar → reload dashboard filtered to that branch
            onClick: (_e, items) => {
                if (!items.length || !d.branch_ids) return;
                const url = new URL(window.location.href);
                url.searchParams.set('branch_id', d.branch_ids[items[0].index]);
                window.location.href = url.toString();
            },
        },
    }),
    login_trend: (id, d) => render(id, {
        type: 'line',
        data: { labels: d.labels, datasets: datasetsFrom(d, 'line') },
        options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
    }),
    branch_status: (id, d) => render(id, {
        type: 'doughnut',
        data: { labels: d.labels, datasets: [{ data: d.data, backgroundColor: [brand(), '#cbd5e1'] }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '65%' },
    }),
    departments: (id, d) => render(id, {
        type: 'bar',
        data: { labels: d.labels, datasets: [{ label: 'Departments', data: d.data, backgroundColor: brand(), borderRadius: 4 }] },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } },
    }),
    accounts_by_type: (id, d) => render(id, {
        type: 'doughnut',
        data: { labels: d.labels, datasets: [{ data: d.data, backgroundColor: PALETTE }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '60%' },
    }),
};

function renderActivity(container, items, emptyText) {
    if (!container) return;
    if (!items.length) {
        container.innerHTML = `<div class="empty-state"><i class="bi bi-clock-history"></i>${emptyText}</div>`;
        return;
    }
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    container.innerHTML = '<ul class="list-group list-group-flush">' + items.map((a) => `
        <li class="list-group-item px-0 d-flex gap-2">
            <i class="bi bi-dot fs-4 text-brand"></i>
            <div class="flex-grow-1">
                <div><strong>${esc(a.user || 'System')}</strong> <span class="text-body-secondary">${esc(a.event)}</span> ${esc(a.subject || '')}</div>
                <div class="small text-body-secondary">${esc(a.branch || '')}${a.branch ? ' · ' : ''}<time datetime="${esc(a.at)}">${esc(a.ago)}</time></div>
            </div>
        </li>`).join('') + '</ul>';
}

export async function initDashboard(root) {
    const url = new URL(root.dataset.dashboard, window.location.origin);
    new URLSearchParams(window.location.search).forEach((v, k) => url.searchParams.set(k, v));

    const boxes = root.querySelectorAll('[data-chart]');
    boxes.forEach((box) => setState(box, 'loading'));
    root.querySelectorAll('[data-card]').forEach((el) => { el.textContent = '…'; });

    let payload;
    try {
        const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        payload = await res.json();
    } catch (err) {
        boxes.forEach((box) => setState(box, 'error', root.dataset.errorText || 'Could not load data.'));
        root.querySelectorAll('[data-card]').forEach((el) => { el.textContent = '—'; });
        const alert = root.querySelector('[data-dashboard-error]');
        if (alert) alert.classList.remove('d-none');
        return;
    }

    const fmt = new Intl.NumberFormat(document.documentElement.lang === 'bn' ? 'bn-BD' : 'en-IN');
    root.querySelectorAll('[data-card]').forEach((el) => {
        const v = payload.cards?.[el.dataset.card];
        el.textContent = v === undefined ? '—' : fmt.format(v);
    });

    boxes.forEach((box) => {
        const key = box.dataset.chart;
        const data = payload.charts?.[key];
        if (isEmpty(data)) {
            setState(box, 'empty', root.dataset.emptyText || 'No data for the selected period.');
            return;
        }
        setState(box, 'ready');
        RENDERERS[key]?.(box.querySelector('canvas').id, data, box);
    });

    renderActivity(root.querySelector('[data-activity]'), payload.recent_activity || [], root.dataset.emptyText || 'No activity yet.');
    const stamp = root.querySelector('[data-generated]');
    if (stamp && payload.generated_at) stamp.textContent = new Date(payload.generated_at).toLocaleString();
}
