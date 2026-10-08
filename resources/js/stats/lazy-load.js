import { renderChart, renderSparkline } from './chart-theme';
import { renderActivityCalendar } from './activity-calendar';
import { renderDonut, clearDonut } from './donut';

const show = (el, on = true) => el?.classList.toggle('hidden', !on);

function formatNumber(value) {
    return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 1 });
}

/** Kartu: loading | ok | insufficient | error | belum tersedia */
function applyCard(card, data, failed = false) {
    const q = (s) => card.querySelector(s);
    const [loading, value, note, empty, error] = [
        '[data-stat-loading]', '[data-stat-value]', '[data-stat-note]', '[data-stat-empty]', '[data-stat-error]',
    ].map(q);
    const sparkWrap = q('[data-stat-spark-wrap]');
    const sparkCanvas = q('[data-stat-spark]');

    [loading, value, note, empty, error, sparkWrap].forEach((el) => show(el, false));

    if (failed) return show(error);

    if (!data) {
        note.textContent = 'Belum tersedia.';
        return show(note);
    }

    if (data.status === 'insufficient') {
        q('[data-stat-empty-detail]').textContent =
            `Butuh minimal ${data.min_required} loker, baru ada ${data.current}.`;
        return show(empty);
    }

    const unit = data.unit ?? null;
    const number = data.value === null ? '—' : formatNumber(data.value);

    value.textContent = '';
    if (unit === 'Rp') value.append(`Rp ${number}`);
    else if (unit === '%') value.append(`${number}%`);
    else value.append(number);

    if (unit && unit !== 'Rp' && unit !== '%') {
        const suffix = document.createElement('span');
        suffix.className = 'ml-2 font-sans text-sm text-obsidian/70';
        suffix.textContent = unit;
        value.append(suffix);
    }
    show(value);

    if (data.note) {
        note.textContent = data.note;
        show(note);
    }

    // Sparkline opsional: hanya kalau backend mengirim data.spark (minimal 2 titik).
    // Dibungkus try/catch sendiri supaya kegagalan sparkline tidak merusak angka kartu.
    if (sparkWrap && sparkCanvas && Array.isArray(data.spark) && data.spark.length >= 2) {
        try {
            show(sparkWrap); // tampilkan dulu supaya Chart.js bisa mengukur ukurannya
            renderSparkline(sparkCanvas, data.spark);
        } catch (e) {
            console.error('[stats] sparkline gagal digambar:', e);
            show(sparkWrap, false);
        }
    }
}

// Tombol pilihan rentang (Hari/Minggu/Bulan). Ganti warna/ukuran tombol di sini.
const VIEW_BUTTON_BASE = 'rounded-full px-3 py-1 text-xs font-semibold transition-colors';
const VIEW_BUTTON_ON = 'bg-obsidian text-offwhite';
const VIEW_BUTTON_OFF = 'text-obsidian/70 hover:bg-nude/60';

/**
 * Grafik dengan beberapa tampilan (payload.views = { kunci: { label, labels, series } }).
 * Tombol dibuat dari isi views, klik tombol menggambar ulang grafik dari data yang
 * sudah ada (tanpa request baru). payload.default_view menentukan tampilan awal.
 */
function setupViews(panel, box, canvas, data) {
    const keys = Object.keys(data.views);
    let current = keys.includes(data.default_view) ? data.default_view : keys[0];

    const group = document.createElement('div');
    group.className = 'inline-flex gap-1 rounded-full bg-nude/40 p-1';
    group.setAttribute('role', 'group');
    group.setAttribute('aria-label', 'Rentang waktu grafik');

    const buttons = keys.map((key) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.view = key;
        button.textContent = data.views[key].label ?? key;
        group.append(button);
        return button;
    });

    const draw = () => {
        const view = data.views[current];

        buttons.forEach((button) => {
            const active = button.dataset.view === current;
            button.className = `${VIEW_BUTTON_BASE} ${active ? VIEW_BUTTON_ON : VIEW_BUTTON_OFF}`;
            button.setAttribute('aria-pressed', String(active));
        });

        try {
            renderChart(canvas, { ...data, labels: view.labels, series: view.series });
        } catch (e) {
            console.error('[stats] grafik gagal digambar saat ganti rentang:', e);
            applyChart(panel, null, true);
        }
    };

    group.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-view]');
        if (!button || button.dataset.view === current) return;

        current = button.dataset.view;
        draw();
    });

    box.replaceChildren(group);
    show(box);
    draw();
}

/** Panel grafik: loading | ok | insufficient | error | belum tersedia */
function applyChart(panel, data, failed = false) {
    const q = (s) => panel.querySelector(s);
    const [loading, canvas, empty, error] = [
        '[data-chart-loading]', '[data-chart-canvas]', '[data-chart-empty]', '[data-chart-error]',
    ].map(q);
    const viewsBox = q('[data-chart-views]');
    const calendarBox = q('[data-calendar-body]');

    [loading, canvas, empty, error, viewsBox, calendarBox].forEach((el) => show(el, false));
    viewsBox?.replaceChildren();
    calendarBox?.replaceChildren();
    clearDonut(canvas); // bersihkan donat dari render sebelumnya (kalau ada)
    empty.classList.remove('flex');
    error.classList.remove('flex');

    const showFlex = (el) => { show(el); el.classList.add('flex'); };

    if (failed) return showFlex(error);

    const detail = q('[data-chart-empty-detail]');

    if (!data) {
        detail.textContent = 'Belum tersedia.';
        return showFlex(empty);
    }

    if (data.status === 'insufficient') {
        detail.textContent = `Butuh minimal ${data.min_required} loker, baru ada ${data.current}.`;
        return showFlex(empty);
    }

    // Kalender aktivitas digambar dari HTML (bukan canvas) ke wadah miliknya sendiri.
    if (data.type === 'calendar') {
        if (!calendarBox) throw new Error('Wadah [data-calendar-body] tidak ada di panel.');

        show(calendarBox); // tampilkan dulu supaya posisi popup bisa diukur
        return renderActivityCalendar(calendarBox, data);
    }

    // Donat membangun kanvas dan keterangannya sendiri di wadah kanvas, jadi kanvas bawaan tetap tersembunyi.
    if (data.variant === 'donut') return renderDonut(canvas, data);

    show(canvas); // tampilkan dulu supaya Chart.js bisa mengukur ukurannya

    // Grafik dengan lebih dari satu tampilan memakai tombol pilihan, selain itu gambar biasa.
    if (viewsBox && data.views && Object.keys(data.views).length > 1) {
        setupViews(panel, viewsBox, canvas, data);
    } else {
        renderChart(canvas, data);
    }
}

/** Jalankan satu langkah render; kalau error, hanya elemen itu yang ditandai gagal. */
function safely(label, fn, onError) {
    try {
        fn();
    } catch (e) {
        console.error(`[stats] ${label} gagal digambar:`, e);
        onError();
    }
}

async function loadGroup(section) {
    const cards = section.querySelectorAll('[data-stat-card]');
    const panels = section.querySelectorAll('[data-chart-panel]');
    show(section.querySelector('[data-stats-retry]'), false);

    let payload;

    // Tahap 1: ambil data. Hanya kegagalan di sini yang dianggap "gagal memuat".
    try {
        const response = await fetch(section.dataset.url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(`HTTP ${response.status} untuk ${section.dataset.url}`);

        payload = await response.json();
    } catch (e) {
        console.error('[stats] request gagal:', e);
        cards.forEach((card) => applyCard(card, null, true));
        panels.forEach((panel) => applyChart(panel, null, true));
        show(section.querySelector('[data-stats-retry]'));
        return;
    }

    // Tahap 2: gambar. Error satu kartu atau grafik tidak menjatuhkan yang lain.
    cards.forEach((card) => safely(
        `kartu ${card.dataset.statCard}`,
        () => applyCard(card, payload.cards?.[card.dataset.statCard]),
        () => applyCard(card, null, true),
    ));

    panels.forEach((panel) => safely(
        `grafik ${panel.dataset.chartPanel}`,
        () => applyChart(panel, payload.charts?.[panel.dataset.chartPanel]),
        () => applyChart(panel, null, true),
    ));

    const sample = section.querySelector('[data-stats-sample]');
    if (sample) sample.textContent = `Berdasarkan ${payload.sample} loker`;
}

const sections = document.querySelectorAll('[data-stats-group][data-url]');

const observer = new IntersectionObserver(
    (entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            observer.unobserve(entry.target);
            loadGroup(entry.target);
        });
    },
    { rootMargin: '200px 0px' },
);

sections.forEach((section) => observer.observe(section));

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-stats-retry]');
    if (button) loadGroup(button.closest('[data-stats-group]'));
});