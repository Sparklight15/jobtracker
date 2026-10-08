import Chart from 'chart.js/auto';
import { shade } from './chart-theme';

/**
 * Donat dengan irisan membulat dan berjarak, angka total di tengah, dan daftar keterangan
 * di sampingnya (di bawah donat kalau layar sempit). Dipakai kalau payload grafik punya
 * variant: 'donut' (lihat GroupC::distribution()).
 *
 * Seluruh isinya (donat + daftar) dibangun di sini dan dipasang di dalam wadah kanvas
 * panel, jadi tinggi panel mengikuti isinya. clearDonut() membersihkannya sebelum
 * panel digambar ulang.
 *
 * Warna irisan memakai rampa monokrom yang sama dengan grafik lain (shade() di chart-theme.js):
 * paling gelap = channel terbanyak.
 */

// Palet proyek (lihat tailwind.config.js)
const INK = '#101010';
const NUDE = '#E3DBCC';
const OFFWHITE = '#FDFCF8';
const MUTED = 'rgba(16, 16, 16, 0.7)';

const DONUT_SIZE = '15rem';   // diameter donat
const CUTOUT = '64%';         // lubang tengah; makin besar makin tipis cincinnya
const SLICE_GAP = 4;          // jarak antar irisan (px)
const SLICE_RADIUS = 8;       // kebulatan sudut irisan (px)
const HOVER_OFFSET = 6;       // irisan menonjol keluar saat di-hover (px)
const LEGEND_MIN_COL = '13rem'; // lebar minimal satu kolom keterangan

const BODY_ATTR = 'data-donut-body';

function format(value, digits = 1) {
    return Number(value).toLocaleString('id-ID', { maximumFractionDigits: digits });
}

function el(tag, styles = {}, text) {
    const node = document.createElement(tag);
    Object.assign(node.style, styles);
    if (text !== undefined) node.textContent = text;
    return node;
}

// Panel tanpa kanvas (misalnya kalender aktivitas) mengirim canvas = null, jadi harus tahan null.
const hostOf = (canvas) => canvas?.parentElement ?? null;

/** Hapus donat dan daftar, kembalikan wadah ke keadaan semula. Aman dipanggil kapan saja. */
export function clearDonut(canvas) {
    const host = hostOf(canvas);
    if (!host) return;

    const body = host.querySelector(`[${BODY_ATTR}]`);
    if (body) {
        const inner = body.querySelector('canvas');
        if (inner) Chart.getChart(inner)?.destroy();
        body.remove();
    }

    host.style.height = '';
}

export function renderDonut(canvas, payload) {
    clearDonut(canvas);

    const host = hostOf(canvas);
    if (!host) throw new Error('Kanvas donat tidak ditemukan di panel.');

    const labels = payload.labels ?? [];
    const data = payload.series?.[0]?.data ?? [];
    const total = data.reduce((sum, n) => sum + Number(n || 0), 0);
    const percentOf = (n) => (total ? (Number(n) / total) * 100 : 0);
    const colors = labels.map((_, i) => shade(i, Math.max(labels.length, 2)));

    // Tinggi wadah mengikuti isi, bukan tinggi tetap milik grafik lain
    host.style.height = 'auto';

    const body = el('div', {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        justifyContent: 'center',
        gap: '2rem',
        padding: '0.5rem 0',
    });
    body.setAttribute(BODY_ATTR, '');

    // --- Donat + angka tengah ---
    const chartBox = el('div', {
        position: 'relative',
        flex: `0 0 ${DONUT_SIZE}`,
        width: DONUT_SIZE,
        height: DONUT_SIZE,
    });
    const inner = document.createElement('canvas');
    inner.setAttribute('role', 'img');
    inner.setAttribute(
        'aria-label',
        'Loker per channel: ' + labels.map((l, i) => `${l} ${data[i]} (${format(percentOf(data[i]))}%)`).join(', '),
    );

    const center = el('div', {
        position: 'absolute',
        inset: '0',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        pointerEvents: 'none',
        textAlign: 'center',
    });
    center.append(
        el('span', { fontSize: '2rem', fontWeight: '700', lineHeight: '1', color: INK }, format(total, 0)),
        el('span', { marginTop: '0.375rem', fontSize: '0.75rem', color: MUTED }, 'Total loker'),
    );

    chartBox.append(inner, center);

    // --- Daftar keterangan ---
    const legend = el('ul', {
        flex: '1 1 20rem',
        minWidth: '0',
        display: 'grid',
        gridTemplateColumns: `repeat(auto-fit, minmax(${LEGEND_MIN_COL}, 1fr))`,
        columnGap: '2rem',
        margin: '0',
        padding: '0',
        listStyle: 'none',
    });

    const items = labels.map((label, i) => {
        const item = el('li', {
            display: 'flex',
            alignItems: 'center',
            gap: '0.75rem',
            padding: '0.625rem 0',
            borderBottom: `1px solid ${NUDE}`,
        });

        const dot = el('span', {
            width: '10px',
            height: '10px',
            flexShrink: '0',
            borderRadius: '3px',
            backgroundColor: colors[i],
        });
        const name = el('span', { flex: '1 1 auto', minWidth: '0', fontSize: '0.875rem', color: MUTED }, label);
        const figures = el('span', { flexShrink: '0', textAlign: 'right', lineHeight: '1.2' });
        figures.append(
            el('span', { display: 'block', fontSize: '0.9375rem', fontWeight: '600', color: INK }, `${format(percentOf(data[i]))}%`),
            el('span', { display: 'block', fontSize: '0.6875rem', color: MUTED }, `${format(data[i], 0)} loker`),
        );

        item.append(dot, name, figures);
        legend.append(item);

        return item;
    });

    body.append(chartBox, legend);
    host.append(body);

    const chart = new Chart(inner, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: colors,
                borderWidth: 0,
                borderRadius: SLICE_RADIUS,
                spacing: SLICE_GAP,
                hoverOffset: HOVER_OFFSET,
            }],
        },
        options: {
            cutout: CUTOUT,
            layout: { padding: HOVER_OFFSET + 2 },
            plugins: {
                legend: { display: false }, // keterangan dibuat sendiri di atas
                tooltip: {
                    backgroundColor: OFFWHITE,
                    titleColor: INK,
                    bodyColor: INK,
                    borderColor: NUDE,
                    borderWidth: 1,
                    cornerRadius: 12,
                    padding: 12,
                    boxPadding: 4,
                    callbacks: {
                        title: () => '',
                        label: (ctx) => `${ctx.label}: ${format(ctx.parsed, 0)} loker (${format(percentOf(ctx.parsed))}%)`,
                    },
                },
            },
        },
    });

    // Arahkan kursor ke satu baris keterangan: irisannya ikut menonjol
    const highlight = (index) => {
        chart.setActiveElements(index === null ? [] : [{ datasetIndex: 0, index }]);
        chart.update();
    };

    items.forEach((item, i) => {
        item.addEventListener('pointerenter', () => highlight(i));
        item.addEventListener('pointerleave', () => highlight(null));
    });

    return chart;
}