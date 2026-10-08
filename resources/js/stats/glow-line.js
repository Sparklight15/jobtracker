import Chart from 'chart.js/auto';

/**
 * Grafik garis dengan titik di tiap data dan tooltip putih berwarna.
 * Dipakai kalau payload grafik bertipe 'line' dan punya variant: 'glow'
 * (lihat GroupC::rates()). Grafik 'line' lain tidak terpengaruh.
 *
 * Di layar lebar, legenda dipasang di header kartu (kiri ikon info) sebagai elemen HTML,
 * jadi seluruh lebar kartu dipakai grafik. Di layar sempit, legenda tampil di atas grafik.
 *
 * Font, warna teks, dan warna grid dasar diatur di chart-theme.js lewat Chart.defaults.
 */

// Palet proyek (lihat tailwind.config.js)
const INK = '#101010';
const NUDE = '#E3DBCC';
const OFFWHITE = '#FDFCF8';

// Warna garis, berurutan sesuai series (Conversion, Response rate). Ganti di sini.
const GLOW_COLORS = ['#5B554D', '#C9A98A'];

const LINE_WIDTH = 1.25;       // px, tipis
const POINT_RADIUS = 3;        // radius titik (hover otomatis +2)
const POINT_BORDER = 1.5;      // tebal tepi titik
const LABEL_WRAP_AT = 12;      // nama channel dibungkus jadi baris baru kalau lebih panjang dari ini
const LEGEND_IN_HEADER_FROM = '(min-width: 768px)'; // di bawah lebar ini legenda pindah ke atas grafik

// Posisi legenda di header kartu. Sesuaikan kalau ikon info ukurannya berbeda.
const LEGEND_TOP = '1rem';     // jarak dari atas kartu (samakan dengan top ikon info)
const LEGEND_RIGHT = '5rem';   // jarak dari kanan kartu (lebar ikon + jarak ke ikon); makin besar makin ke kiri
const LEGEND_HEIGHT = '2rem';  // tinggi baris legenda (samakan dengan tinggi ikon info)

function formatNumber(value) {
    return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 1 });
}

/** Bungkus nama panjang jadi beberapa baris (Chart.js menerima array sebagai label multi-baris). */
function wrapLabel(label, max = LABEL_WRAP_AT) {
    const lines = [];
    let line = '';

    String(label).split(' ').forEach((word) => {
        if (line && `${line} ${word}`.length > max) {
            lines.push(line);
            line = word;
        } else {
            line = line ? `${line} ${word}` : word;
        }
    });
    if (line) lines.push(line);

    return lines;
}

const colorOf = (index) => GLOW_COLORS[index % GLOW_COLORS.length];

/** Legenda HTML di pojok kanan atas kartu, di kiri ikon info. Satu instans per kartu. */
function mountHeaderLegend(canvas, names) {
    const panel = canvas.closest('[data-chart-panel]');
    const host = panel?.parentElement ?? panel;
    if (!host) return;

    host.querySelector('[data-glow-legend]')?.remove();
    if (names.length === 0) return;

    const list = document.createElement('ul');
    list.dataset.glowLegend = '';
    Object.assign(list.style, {
        position: 'absolute',
        top: LEGEND_TOP,
        right: LEGEND_RIGHT,
        height: LEGEND_HEIGHT,
        display: 'flex',
        alignItems: 'center',
        gap: '1rem',
        margin: '0',
        padding: '0',
        listStyle: 'none',
        fontSize: '0.75rem',
        color: INK,
        zIndex: '10',
    });

    names.forEach((name, i) => {
        const item = document.createElement('li');
        Object.assign(item.style, { display: 'inline-flex', alignItems: 'center', gap: '0.375rem' });

        const dot = document.createElement('span');
        Object.assign(dot.style, {
            width: '8px',
            height: '8px',
            borderRadius: '9999px',
            backgroundColor: colorOf(i),
            flexShrink: '0',
        });

        const text = document.createElement('span');
        text.textContent = name;

        item.append(dot, text);
        list.append(item);
    });

    host.append(list);
}

function removeHeaderLegend(canvas) {
    const panel = canvas.closest('[data-chart-panel]');
    (panel?.parentElement ?? panel)?.querySelector('[data-glow-legend]')?.remove();
}

export function renderGlowLine(canvas, payload) {
    Chart.getChart(canvas)?.destroy();

    const { labels = [], series = [], unit = null, series_counts: counts = [] } = payload;
    const suffix = unit === '%' ? '%' : '';
    const legendInHeader = window.matchMedia(LEGEND_IN_HEADER_FROM).matches;

    if (legendInHeader) {
        mountHeaderLegend(canvas, series.map((s) => s.name));
    } else {
        removeHeaderLegend(canvas);
    }

    // Pembaca layar tidak bisa membaca isi canvas, jadi ringkasannya dipasang sebagai teks
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', series.map((s) => {
        const parts = labels.map((label, i) => {
            const v = s.data[i];
            const text = typeof v === 'number' && Number.isFinite(v) ? `${formatNumber(v)}${suffix}` : 'belum cukup data';
            return `${label} ${text}`;
        });

        return `${s.name}: ${parts.join(', ')}`;
    }).join('. '));

    return new Chart(canvas, {
        type: 'line',
        data: {
            // Label dibungkus untuk sumbu x; `labels` asli tetap dipakai untuk aria-label dan judul tooltip
            labels: labels.map((l) => wrapLabel(l)),
            datasets: series.map((s, i) => {
                const color = colorOf(i);

                return {
                    label: s.name,
                    data: s.data,
                    borderColor: color,
                    borderWidth: LINE_WIDTH,
                    // 'monotone' melengkung halus tapi tidak melewati nilai data,
                    // jadi garis tidak pernah turun di bawah 0% atau naik di atas 100%.
                    // Hapus baris ini kalau mau garis lurus antar titik.
                    cubicInterpolationMode: 'monotone',
                    spanGaps: false, // channel yang sampelnya kurang (null) memutus garis
                    fill: false,
                    pointRadius: POINT_RADIUS,
                    pointHoverRadius: POINT_RADIUS + 2,
                    pointBorderWidth: POINT_BORDER,
                    pointBackgroundColor: OFFWHITE,
                    pointBorderColor: color,
                    pointHoverBackgroundColor: color,
                    pointHoverBorderColor: OFFWHITE,
                };
            }),
        },
        options: {
            // Ruang kiri-kanan supaya label pertama dan terakhir tidak terpotong
            layout: { padding: { top: 16, right: 12, left: 12 } },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    // Di layar lebar legenda ada di header kartu (HTML), jadi yang bawaan dimatikan
                    display: !legendInHeader,
                    position: 'top',
                    align: 'start',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 8,
                        boxHeight: 8,
                        padding: 16,
                        // Titik di dataset berwarna putih (berongga), tapi di legenda harus terisi
                        generateLabels: (chart) => Chart.defaults.plugins.legend.labels
                            .generateLabels(chart)
                            .map((item) => ({
                                ...item,
                                fillStyle: colorOf(item.datasetIndex),
                                strokeStyle: colorOf(item.datasetIndex),
                            })),
                    },
                },
                tooltip: {
                    backgroundColor: OFFWHITE,
                    titleColor: INK,
                    borderColor: NUDE,
                    borderWidth: 1,
                    cornerRadius: 12,
                    padding: 12,
                    boxPadding: 4,
                    usePointStyle: true,
                    callbacks: {
                        // Judul tooltip tetap satu baris (nama asli, bukan yang dibungkus)
                        title: (items) => labels[items[0].dataIndex],
                        label: (ctx) => {
                            const name = ctx.dataset.label;
                            const value = ctx.parsed.y;

                            if (value === null || value === undefined) {
                                return `${name}: belum cukup data`;
                            }

                            const base = `${name}: ${formatNumber(value)}${suffix}`;
                            const count = counts[ctx.datasetIndex]?.[ctx.dataIndex];

                            return count ? `${base} (${count.n} dari ${count.of})` : base;
                        },
                        labelTextColor: (ctx) => colorOf(ctx.datasetIndex),
                        labelColor: (ctx) => ({
                            borderColor: colorOf(ctx.datasetIndex),
                            backgroundColor: colorOf(ctx.datasetIndex),
                            borderRadius: 4,
                        }),
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { color: NUDE },
                    // Label tegak (tanpa rotasi), rata tengah di bawah titiknya, tidak ada yang dilewati
                    ticks: { autoSkip: false, minRotation: 0, maxRotation: 0, padding: 10, font: { size: 11 } },
                },
                y: {
                    min: 0,
                    max: 100,
                    grid: { color: NUDE },
                    border: { display: false },
                    ticks: { stepSize: 20, callback: (v) => `${v}${suffix}` },
                },
            },
        },
    });
}