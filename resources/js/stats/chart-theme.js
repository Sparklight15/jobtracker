import Chart from 'chart.js/auto';

// Palet proyek (lihat tailwind.config.js)
const INK = '#101010';
const NUDE = '#E3DBCC';
const IVORY = '#F3F0E9';
const OFFWHITE = '#FDFCF8';
const LIGHT = '#CFC6B3'; // ujung terang rampa monokrom, masih terbaca di atas kartu ivory
const MUTED = 'rgba(16, 16, 16, 0.7)';

// Gradasi batang: dasar batang tipis -> ujung batang pekat (ganti opasitas di sini)
const BAR_FILL_START = 0.22; // opasitas di pangkal batang
const BAR_FILL_END = 0.88;   // opasitas di ujung batang

// Gradasi area di bawah grafik garis: pekat di dekat garis -> memudar ke bawah
const AREA_FILL_TOP = 0.2;
const AREA_FILL_BOTTOM = 0.02;

// Grafik garis (Apply per minggu/bulan, dll.)
const LINE_WIDTH = 1;   // px, bisa pecahan: 0.75 = sangat tipis, 1.25 = agak tegas
const LINE_POINT = 2;   // radius titik

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

function mixRgb(from, to, t) {
    const a = hexToRgb(from);
    const b = hexToRgb(to);
    return a.map((v, i) => Math.round(v + (b[i] - v) * t));
}

function mix(from, to, t) {
    const c = mixRgb(from, to, t);
    return `rgb(${c[0]}, ${c[1]}, ${c[2]})`;
}

const rgba = (rgb, alpha) => `rgba(${rgb[0]}, ${rgb[1]}, ${rgb[2]}, ${alpha})`;

/** Warna ke-i dari n, rampa dari obsidian ke abu hangat. */
export function shade(i, n) {
    return n <= 1 ? INK : mix(INK, LIGHT, i / (n - 1));
}

/** Versi array [r, g, b] dari shade(), dipakai untuk gradasi batang dan area. */
function shadeRgb(i, n) {
    return n <= 1 ? hexToRgb(INK) : mixRgb(INK, LIGHT, i / (n - 1));
}

/**
 * Gradasi per batang (scriptable). Vertikal: dari dasar ke atas.
 * Horizontal: dari kiri ke kanan. Tiap batang memakai koordinatnya sendiri,
 * jadi batang pendek dan panjang sama-sama mendapat gradasi penuh.
 *
 * Saat grafik baru dibuat (atau dibaca legenda), posisi batang belum dihitung
 * sehingga koordinatnya bisa kosong/NaN. createLinearGradient() melempar error
 * untuk nilai seperti itu, jadi di kondisi tersebut dipakai warna solid dulu.
 */
function barGradient(rgb, horizontal) {
    const fallback = rgba(rgb, BAR_FILL_END);

    return (context) => {
        const { chart, element } = context;
        const ctx = chart?.ctx;

        if (!ctx || !chart.chartArea || !element || typeof element.getProps !== 'function') {
            return fallback;
        }

        const { x, y, base } = element.getProps(['x', 'y', 'base'], true);
        const coords = horizontal ? [base, x] : [base, y];

        if (!coords.every((v) => typeof v === 'number' && Number.isFinite(v))) {
            return fallback;
        }

        // Batang tanpa panjang (nilai 0): gradasi tidak perlu
        if (coords[0] === coords[1]) return fallback;

        try {
            const gradient = horizontal
                ? ctx.createLinearGradient(coords[0], 0, coords[1], 0)
                : ctx.createLinearGradient(0, coords[0], 0, coords[1]);

            gradient.addColorStop(0, rgba(rgb, BAR_FILL_START));
            gradient.addColorStop(1, rgba(rgb, BAR_FILL_END));
            return gradient;
        } catch (e) {
            return fallback;
        }
    };
}

/**
 * Gradasi area di bawah garis (scriptable): pekat di atas, memudar ke bawah.
 * Memakai area plot grafik (chartArea), jadi saat grafik belum siap
 * dikembalikan 'transparent' dan Chart.js menggambar ulang setelah ukurannya ada.
 */
function areaGradient(rgb) {
    return (context) => {
        const { chart } = context;
        const ctx = chart?.ctx;
        const area = chart?.chartArea;

        if (!ctx || !area) return 'transparent';

        const { top, bottom } = area;

        if (![top, bottom].every((v) => typeof v === 'number' && Number.isFinite(v)) || top === bottom) {
            return 'transparent';
        }

        try {
            const gradient = ctx.createLinearGradient(0, top, 0, bottom);
            gradient.addColorStop(0, rgba(rgb, AREA_FILL_TOP));
            gradient.addColorStop(1, rgba(rgb, AREA_FILL_BOTTOM));
            return gradient;
        } catch (e) {
            return 'transparent';
        }
    };
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
        case 'hbar': {
            const rgb = shadeRgb(index, total);
            return {
                ...base,
                backgroundColor: barGradient(rgb, type === 'hbar'),
                hoverBackgroundColor: rgba(rgb, 1), // batang yang di-hover jadi solid
                borderRadius: 6,
                maxBarThickness: 40,
            };
        }
        case 'line': {
            const color = shade(index, total);
            const rgb = shadeRgb(index, total);
            // Area berisi hanya untuk grafik satu seri. Kalau seri lebih dari satu,
            // area saling menumpuk dan sulit dibaca, jadi cukup garisnya.
            const filled = total === 1;

            return {
                ...base,
                borderColor: color,
                borderWidth: LINE_WIDTH,
                tension: 0.3,
                pointRadius: LINE_POINT,
                pointHoverRadius: LINE_POINT + 2,
                pointBackgroundColor: color, // titik tetap solid, tidak ikut gradasi
                pointBorderColor: color,
                borderDash: index > 0 ? [6, 4] : [], // garis putus-putus membedakan seri selain warna
                fill: filled ? 'origin' : false,
                backgroundColor: filled ? areaGradient(rgb) : color,
            };
        }
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

// Sparkline kartu angka: garis sangat tipis + gradasi lembut di bawahnya (ganti warna di sini)
const SPARK_LINE = 'rgba(16, 16, 16, 0.7)';
const SPARK_FILL_TOP = 'rgba(16, 16, 16, 0.22)';
const SPARK_FILL_BOTTOM = 'rgba(16, 16, 16, 0.02)';
const SPARK_LINE_WIDTH = 0.6; // px, bisa pecahan (Chart.js mendukung); 0.5 = paling tipis yang masih terbaca

/** Grafik mini tanpa sumbu, legenda, atau interaksi. values = array angka berurutan waktu. */
export function renderSparkline(canvas, values) {
    Chart.getChart(canvas)?.destroy();

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: values.map((_, i) => i + 1),
            datasets: [{
                data: values,
                borderColor: SPARK_LINE,
                borderWidth: SPARK_LINE_WIDTH,
                tension: 0.4,
                pointRadius: 0,
                fill: true,
                backgroundColor: (context) => {
                    const { ctx, chartArea } = context.chart;
                    if (!chartArea) return 'transparent';
                    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                    gradient.addColorStop(0, SPARK_FILL_TOP);
                    gradient.addColorStop(1, SPARK_FILL_BOTTOM);
                    return gradient;
                },
            }],
        },
        options: {
            events: [],
            animation: { duration: 600 },
            layout: { padding: { top: 4, bottom: 2, left: 1, right: 1 } },
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            scales: { x: { display: false }, y: { display: false } },
        },
    });
}