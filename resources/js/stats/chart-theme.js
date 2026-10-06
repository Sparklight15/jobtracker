import Chart from 'chart.js/auto';

// Palet proyek (lihat tailwind.config.js)
const INK = '#101010';
const NUDE = '#E3DBCC';
const IVORY = '#F3F0E9';
const OFFWHITE = '#FDFCF8';
const LIGHT = '#CFC6B3'; // ujung terang rampa monokrom, masih terbaca di atas kartu ivory
const MUTED = 'rgba(16, 16, 16, 0.7)';

Chart.defaults.font.family = '"Nunito Variable", ui-sans-serif, system-ui, sans-serif';
Chart.defaults.font.size = 12;
Chart.defaults.color = MUTED;
Chart.defaults.borderColor = NUDE;
Chart.defaults.responsive = true;
Chart.defaults.maintainAspectRatio = false;
Chart.defaults.animation.duration = 400;
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxWidth = 8;
Chart.defaults.plugins.tooltip.backgroundColor = INK;
Chart.defaults.plugins.tooltip.titleColor = OFFWHITE;
Chart.defaults.plugins.tooltip.bodyColor = OFFWHITE;
Chart.defaults.plugins.tooltip.cornerRadius = 8;
Chart.defaults.plugins.tooltip.padding = 10;

function hexToRgb(hex) {
    const n = parseInt(hex.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

function mix(from, to, t) {
    const a = hexToRgb(from);
    const b = hexToRgb(to);
    const c = a.map((v, i) => Math.round(v + (b[i] - v) * t));
    return `rgb(${c[0]}, ${c[1]}, ${c[2]})`;
}

/** Warna ke-i dari n, rampa dari obsidian ke abu hangat. */
export function shade(i, n) {
    return n <= 1 ? INK : mix(INK, LIGHT, i / (n - 1));
}

function withUnit(text, unit) {
    if (!unit) return text;
    if (unit === '%') return `${text}%`;
    if (unit === 'Rp') return `Rp ${text}`;
    return `${text} ${unit}`;
}

function buildDataset(type, series, index, total, labels) {
    const base = { label: series.name, data: series.data };

    switch (type) {
        case 'bar':
        case 'hbar':
            return {
                ...base,
                backgroundColor: total === 1 ? INK : shade(index, total),
                borderRadius: 6,
                maxBarThickness: 40,
            };
        case 'line':
            return {
                ...base,
                borderColor: shade(index, total),
                backgroundColor: shade(index, total),
                borderWidth: 2,
                tension: 0.3,
                pointRadius: 3,
                borderDash: index > 0 ? [6, 4] : [], // garis putus-putus membedakan seri selain warna
            };
        case 'doughnut':
            return {
                ...base,
                backgroundColor: labels.map((_, j) => shade(j, labels.length)),
                borderColor: IVORY,
                borderWidth: 2,
            };
        default: // scatter
            return { ...base, backgroundColor: INK, pointRadius: 4 };
    }
}

export function renderChart(canvas, payload) {
    Chart.getChart(canvas)?.destroy();

    const { type, labels = [], series = [], unit = null } = payload;
    const isDoughnut = type === 'doughnut';
    const horizontal = type === 'hbar';

    const valueAxis = {
        beginAtZero: true,
        grid: { color: NUDE },
        border: { display: false },
        ticks: { callback: (v) => withUnit(v, unit) },
    };
    const categoryAxis = { grid: { display: false }, border: { color: NUDE } };

    const options = {
        indexAxis: horizontal ? 'y' : 'x',
        cutout: isDoughnut ? '65%' : undefined,
        plugins: {
            legend: { display: isDoughnut || series.length > 1, position: isDoughnut ? 'right' : 'bottom' },
            tooltip: {
                callbacks: {
                    label: (ctx) => {
                        const prefix = isDoughnut
                            ? `${ctx.label}: `
                            : ctx.dataset.label ? `${ctx.dataset.label}: ` : '';
                        return prefix + withUnit(ctx.formattedValue, unit);
                    },
                },
            },
        },
    };

    if (!isDoughnut) {
        if (type === 'scatter') {
            options.scales = { x: valueAxis, y: valueAxis };
        } else {
            options.scales = horizontal
                ? { x: valueAxis, y: categoryAxis }
                : { x: categoryAxis, y: valueAxis };
        }
    }

    new Chart(canvas, {
        type: horizontal ? 'bar' : type,
        data: {
            labels,
            datasets: series.map((s, i) => buildDataset(type, s, i, series.length, labels)),
        },
        options,
    });
}